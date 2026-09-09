<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Tests;

use AaiEduHr\HeartPhrameModuleAuth\ModuleAuth;
use AaiEduHr\HeartPhrameModuleAuth\Service\AuthUserService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocumentVersion;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorPublishedVersionProviderInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceAccessService;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceConfig;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceRepository;
use AaiEduHr\SimbiozaModuleWorkspace\Service\WorkspaceWorkflowService;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchConfig;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchEditorBridge;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchIndexer;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchService;
use HeartPhrame\Authn\AuthnHandlerInterface;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use HeartPhrame\Routing\UrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkspaceSearchService::class)]
#[UsesClass(WorkspaceSearchConfig::class)]
#[UsesClass(WorkspaceSearchEditorBridge::class)]
#[UsesClass(WorkspaceSearchIndexer::class)]
final class WorkspaceSearchServiceTest extends TestCase
{
    private Database $database;

    private WorkspaceRepository $repository;

    private WorkspaceSearchService $search;

    private WorkspaceSearchIndexer $indexer;

    /** @var array<string, EditorDocumentVersion> */
    private array $versions = [];

    /**
     * HR: Gradi stvarni SQLite Auth, Workspace, Editor i Search presjek bez lažnog ACL-a.
     * EN: Builds a real SQLite Auth, Workspace, Editor, and Search slice without fake ACL.
     */
    protected function setUp(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
            'workspace' => ['site_default_language' => 'hr'],
        ]);
        $this->database = new Database($config, $helper);
        $this->runMigration(
            dirname(__DIR__) . '/vendor/aaieduhr/heartphrame-module-auth/resources/migrations/initial_auth_schema.php',
        );
        $this->runMigration(
            dirname(__DIR__)
            . '/vendor/aaieduhr/simbioza-module-workspace/resources/migrations/initial_workspace_schema.php',
        );
        $this->runMigration(dirname(__DIR__) . '/resources/migrations/initial_workspace_search_schema.php');
        $names = [
            1 => ['Ivo', 'Alfa'],
            2 => ['Bruno', 'Beta'],
            3 => ['Cvita', 'Gama'],
        ];
        foreach ([1, 2, 3] as $userId) {
            $this->database->table(ModuleAuth::TABLE_AUTH_USERS)->insert([
                'id' => $userId,
                'login_identifier' => 'user' . $userId,
                'is_admin' => $userId === 1,
                'is_active' => true,
                'auth_source' => 'local',
                'must_change_password' => false,
                'created_at' => '2026-08-12 10:00:00',
                'updated_at' => '2026-08-12 10:00:00',
            ]);
            foreach (
                [
                'first_name' => $names[$userId][0],
                'last_name' => $names[$userId][1],
                ] as $field => $value
            ) {
                $this->database->table(ModuleAuth::TABLE_AUTH_USER_ATTRIBUTE_VALUES)->insert([
                    'user_id' => $userId,
                    'field_key' => $field,
                    'value_text' => $value,
                    'created_at' => '2026-08-12 10:00:00',
                    'updated_at' => '2026-08-12 10:00:00',
                ]);
            }
        }

        $this->repository = new WorkspaceRepository($this->database);
        $workflow = new WorkspaceWorkflowService($this->repository);
        $workspaceConfig = new WorkspaceConfig(
            $config,
            dirname(__DIR__) . '/vendor/aaieduhr/simbioza-module-workspace',
        );
        $access = new WorkspaceAccessService($this->repository, $this->authnHandler(), $workspaceConfig, $workflow);
        $provider = new class ($this->versions) implements EditorPublishedVersionProviderInterface {
            /** @param array<string, EditorDocumentVersion> $versions */
            public function __construct(private array &$versions)
            {
            }

            /**
             * HR: Vraća samo izričito tražene objavljene verzije testa.
             * EN: Returns only explicitly requested published test versions.
             */
            public function loadPublishedVersionsForIndexing(array $versionNumbersByDocument, string $language): array
            {
                $result = [];
                foreach ($versionNumbersByDocument as $documentKey => $versionNumber) {
                    $version = $this->versions[$language . ':' . $documentKey] ?? null;
                    if ($version instanceof EditorDocumentVersion && $version->versionNumber === $versionNumber) {
                        $result[$documentKey] = $version;
                    }
                }

                return $result;
            }
        };
        $searchConfig = new WorkspaceSearchConfig($config, dirname(__DIR__));
        $this->indexer = new WorkspaceSearchIndexer(
            $this->database,
            $this->repository,
            $workflow,
            new WorkspaceSearchEditorBridge($provider),
            new AuthUserService($this->database),
            $workspaceConfig,
            $searchConfig,
        );
        $urls = $this->createMock(UrlGenerator::class);
        $urls->method('getPathFor')->willReturnCallback(
            static fn(string $route, array $parameters = []): string => '/workspace/'
                . ($parameters['workspaceSlug'] ?? '') . '/' . ($parameters['nodeSlug'] ?? ''),
        );
        $this->search = new WorkspaceSearchService(
            $this->database,
            $access,
            $this->repository,
            $workspaceConfig,
            $searchConfig,
            $this->indexer,
            $urls,
        );
    }

    /**
     * HR: Gost ne vidi ograničenu stranicu ni kroz rezultate, broj ili isječak;
     *     izričito ovlašteni korisnik je vidi, a drugi prijavljeni korisnik ne.
     * EN: A guest cannot see a restricted page through results, totals, or snippets;
     *     an explicitly allowed user can see it while another signed-in user cannot.
     */
    public function testSearchAppliesWorkspaceAndPageAclBeforeTotalsAndSnippets(): void
    {
        $public = $this->workspace('Javno područje', 'public', 'public');
        $publicNode = $this->page($public, 'Javni vodič', 'public-guide', 'Zajedničko javno znanje');
        $restricted = $this->workspace('Ograničeno područje', 'restricted', 'restricted');
        $this->repository->replaceWorkspaceAcl((int)$restricted['id'], [
            'user' => [2 => ['can_view' => true]],
        ]);
        $secretNode = $this->page($restricted, 'Tajni plan', 'secret-plan', 'Nevidljiva šifra orhideja');

        $guestPublic = $this->search->search('javno znanje', 'hr');
        $this->assertSame(1, $guestPublic['total']);
        $this->assertSame((int)$publicNode['id'], $guestPublic['items'][0]['node_id']);

        $guestSecret = $this->search->search('orhideja', 'hr');
        $this->assertSame(0, $guestSecret['total']);
        $this->assertSame([], $guestSecret['items']);

        $allowed = $this->search->search('orhideja', 'hr', [], ['id' => 2, 'is_admin' => false]);
        $this->assertSame(1, $allowed['total']);
        $this->assertSame((int)$secretNode['id'], $allowed['items'][0]['node_id']);
        $this->assertStringContainsString('<mark>orhideja</mark>', (string) $allowed['items'][0]['snippet_html']);

        $denied = $this->search->search('orhideja', 'hr', [], ['id' => 3, 'is_admin' => false]);
        $this->assertSame(0, $denied['total']);
    }

    /**
     * HR: Naziv područja mora biti pravi rezultat, ali tek nakon istog ACL
     *     filtra koji štiti stranice, prijedloge i ukupan broj.
     * EN: A Workspace name must be a real result, but only after the same ACL
     *     filter that protects pages, suggestions, and totals.
     */
    public function testSearchFindsVisibleWorkspaceByNameWithoutLeakingRestrictedWorkspace(): void
    {
        $workspace = $this->workspace('Područje od: Dario Pinturić', 'osobno-dario', 'restricted');
        $this->repository->replaceWorkspaceAcl((int)$workspace['id'], [
            'user' => [2 => ['can_view' => true]],
        ]);

        $allowed = $this->search->search('Dario', 'hr', [], ['id' => 2, 'is_admin' => false]);
        $this->assertSame(1, $allowed['total']);
        $this->assertSame('workspace', $allowed['items'][0]['result_type']);
        $this->assertSame('Područje od: Dario Pinturić', $allowed['items'][0]['title']);
        $this->assertSame('/workspace/osobno-dario/', $allowed['items'][0]['url']);

        $suggestions = $this->search->suggest('Dario', 'hr', ['id' => 2, 'is_admin' => false]);
        $this->assertSame('Područje od: Dario Pinturić', $suggestions[0]['title']);

        $this->assertSame(0, $this->search->search('Dario', 'hr')['total']);
        $this->assertSame(
            0,
            $this->search->search('Dario', 'hr', [], ['id' => 3, 'is_admin' => false])['total'],
        );
    }

    /** HR: Filter područja puni se i prije unosa minimalnog broja znakova. EN: The Workspace filter is populated before the minimum query length is entered. */
    public function testEmptyQueryStillReturnsVisibleWorkspaceOptions(): void
    {
        $this->workspace('Dokumentacija', 'docs', 'public');

        $result = $this->search->search('', 'hr');

        $this->assertSame(0, $result['total']);
        $this->assertSame(['docs'], array_column($result['workspaces'], 'slug'));
    }

    /** HR: Prazan pojam bez filtra ne pokreće globalni popis, ali odabrano područje vraća sortirljivu paginiranu tablicu. EN: An empty unfiltered term does not run a global listing, while a selected Workspace returns a sortable paginated table. */
    public function testEmptyQueryRequiresNarrowingFilterAndBrowsesSelectedWorkspace(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $this->page($workspace, 'Beta stranica', 'beta', 'Sadržaj beta');
        $this->page($workspace, 'Alfa stranica', 'alfa', 'Sadržaj alfa');

        $unfiltered = $this->search->search('', 'hr');
        $browse = $this->search->search('', 'hr', [
            'workspaces' => ['docs'],
            'sort' => 'title',
            'direction' => 'asc',
            'per_page' => 1,
        ]);

        $this->assertFalse($unfiltered['search_executed']);
        $this->assertTrue($browse['search_executed']);
        $this->assertTrue($browse['browse_mode']);
        $this->assertSame(2, $browse['total']);
        $this->assertSame(2, $browse['pages']);
        $this->assertSame('Alfa stranica', $browse['items'][0]['title']);
        $this->assertSame('Alfa Ivo', $browse['items'][0]['author_name']);
        $this->assertSame('Alfa Ivo', $browse['items'][0]['modified_by_name']);
        $this->assertSame('2026-08-12 10:00:00', $browse['items'][0]['modified_at']);
    }

    /** HR: Naslovi u pregledu slijede hrvatsku abecedu. EN: Browse titles follow Croatian alphabet rules. */
    public function testBrowseResultsUseRequestedLocaleForTitleOrdering(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        foreach (['Žaba', 'Zec', 'Šuma', 'Sava', 'Đak', 'Dabar', 'Ćuk', 'Čar', 'Cesta', 'Džep'] as $index => $title) {
            $this->page($workspace, $title, 'locale-' . $index, 'Sadržaj');
        }

        $result = $this->search->search('', 'hr', [
            'workspaces' => ['docs'],
            'sort' => 'title',
            'direction' => 'asc',
            'per_page' => 25,
        ]);

        $this->assertSame(
            ['Cesta', 'Čar', 'Ćuk', 'Dabar', 'Džep', 'Đak', 'Sava', 'Šuma', 'Zec', 'Žaba'],
            array_column($result['items'], 'title'),
        );
    }

    /** HR: Dva ili više odabranih područja zahtijevaju pojam i zadržavaju klasični prikaz. EN: Two or more selected Workspaces require a term and retain classic results. */
    public function testMultipleWorkspaceSelectionRequiresQuery(): void
    {
        $docs = $this->workspace('Dokumentacija', 'docs', 'public');
        $news = $this->workspace('Novosti', 'news', 'public');
        $this->page($docs, 'Dokumentacija stranica', 'docs-page', 'Zajednički sadržaj');
        $this->page($news, 'Novosti stranica', 'news-page', 'Zajednički sadržaj');

        $withoutQuery = $this->search->search('', 'hr', [
            'workspaces' => ['docs', 'news'],
            'author' => '1',
        ]);
        $withQuery = $this->search->search('Zajednički', 'hr', [
            'workspaces' => ['docs', 'news'],
        ]);

        $this->assertFalse($withoutQuery['search_executed']);
        $this->assertFalse($withoutQuery['browse_mode']);
        $this->assertTrue($withoutQuery['query_required_for_multiple_workspaces']);
        $this->assertSame(0, $withoutQuery['total']);
        $this->assertTrue($withQuery['search_executed']);
        $this->assertFalse($withQuery['browse_mode']);
        $this->assertFalse($withQuery['query_required_for_multiple_workspaces']);
        $this->assertSame(2, $withQuery['total']);
    }

    /** HR: Autor pronalazi i stranice koje je korisnik stvorio i one koje je zadnji izmijenio. EN: Author filtering finds pages the user created and pages the user last modified. */
    public function testAuthorFilterIncludesPageAuthorAndLastModifier(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $this->page($workspace, 'Autor dva', 'author-two', 'Sadržaj', 2, 1);
        $this->page($workspace, 'Izmijenio dva', 'modifier-two', 'Sadržaj', 1, 2);
        $this->page($workspace, 'Bez korisnika dva', 'unrelated', 'Sadržaj', 1, 1);

        $result = $this->search->search('', 'hr', ['author' => '2']);

        $this->assertTrue($result['browse_mode']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(['Autor dva', 'Izmijenio dva'], array_column($result['items'], 'title'));
    }

    /**
     * HR: Indeks koristi autora i datum nastanka Editor dokumenta, a ne
     *     administratora i datum tehničkog Workspace importa.
     * EN: The index uses the Editor document creator and creation date, not
     *     the administrator and date of the technical Workspace import.
     */
    public function testImportedPageUsesDocumentCreationMetadataInsteadOfTechnicalNodeMetadata(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $this->page(
            $workspace,
            'Životopis',
            'biography',
            'Sadržaj',
            1,
            2,
            '2026-09-09 10:49:57',
            3,
            '2023-03-15 13:02:10',
        );

        $result = $this->search->search('', 'hr', ['workspaces' => ['docs']]);
        $bySourceAuthor = $this->search->search('', 'hr', ['author' => '3']);
        $byImportingAdministrator = $this->search->search('', 'hr', ['author' => '1']);

        $this->assertSame('Gama Cvita', $result['items'][0]['author_name']);
        $this->assertSame('2023-03-15 13:02:10', $result['items'][0]['published_at']);
        $this->assertSame('Beta Bruno', $result['items'][0]['modified_by_name']);
        $this->assertSame(1, $bySourceAuthor['total']);
        $this->assertSame(0, $byImportingAdministrator['total']);
    }

    /** HR: Sam datum od automatski završava danas, a sam datum do obuhvaća cijelu raniju povijest. EN: A lone from date ends today automatically, while a lone to date includes all earlier history. */
    public function testPublicationDateFiltersSupportOpenAndAutomaticBoundaries(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $this->page($workspace, 'Stara stranica', 'old', 'Sadržaj', 1, 1, '2025-01-10 09:00:00');
        $this->page($workspace, 'Nova stranica', 'new', 'Sadržaj', 1, 1, '2026-08-12 10:00:00');

        $from = $this->search->search('', 'hr', ['from' => '2026-01-01']);
        $to = $this->search->search('', 'hr', ['to' => '2025-12-31']);

        $this->assertSame(date('Y-m-d'), $from['filters']['to']);
        $this->assertSame(['Nova stranica'], array_column($from['items'], 'title'));
        $this->assertSame(['Stara stranica'], array_column($to['items'], 'title'));
    }

    /** HR: Naslov objavljene stranice ostaje pretraživ neovisno o tekstu tijela. EN: A published page title remains searchable independently of its body text. */
    public function testSearchFindsPublishedPageByTitle(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $node = $this->page(
            $workspace,
            'Jedinstveni naslov stranice',
            'jedinstveni-naslov',
            'Tijelo bez traženih riječi',
        );

        $result = $this->search->search('Jedinstveni naslov', 'hr');

        $this->assertSame(1, $result['total']);
        $this->assertSame('page', $result['items'][0]['result_type']);
        $this->assertSame((int)$node['id'], $result['items'][0]['node_id']);
        $this->assertSame('Jedinstveni naslov stranice', $result['items'][0]['title']);
    }

    /** HR: Običan višerječni upit traži cijelu frazu. EN: A plain multi-word query searches the complete phrase. */
    public function testPlainMultiWordQueryUsesExactPhrase(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $this->page($workspace, 'Točna fraza', 'exact-phrase', 'Početak Dio 1 završetak');
        $this->page($workspace, 'Razdvojeni pojmovi', 'split-terms', 'Početak Dio između 1 završetak');
        $this->indexer->rebuild();

        $result = $this->search->search('Dio 1', 'hr');

        $this->assertSame(1, $result['total']);
        $this->assertSame('Točna fraza', $result['items'][0]['title']);
        $this->assertStringContainsString('<mark>Dio 1</mark>', (string) $result['items'][0]['snippet_html']);
    }

    /** HR: Plus i navodnici zadržavaju redoslijed obaveznih pojmova i fraza. EN: Plus and quotes preserve the order of required terms and phrases. */
    public function testAdvancedQueryRequiresEveryTermAndQuotedPhrase(): void
    {
        $this->database->table(ModuleAuth::TABLE_AUTH_USERS)
            ->where('id', '=', 1)
            ->update(['login_identifier' => 'administrator']);
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $this->page($workspace, 'Potpuni rezultat', 'complete-result', 'Dio 1 sadrži i Dio 2 u nastavku.');
        $this->page($workspace, 'Nedostaje fraza', 'missing-phrase', 'Dio 1 bez druge tražene fraze.');
        $this->page($workspace, 'Nedostaje broj', 'missing-number', 'Dio i Dio 2 bez zasebne jedinice.');
        $this->indexer->rebuild();

        $result = $this->search->search('+Dio +1 +"Dio 2"', 'hr');

        $this->assertSame(1, $result['total']);
        $this->assertSame('Potpuni rezultat', $result['items'][0]['title']);
        $this->assertMatchesRegularExpression(
            '/<mark>Dio<\/mark>.*<mark>1<\/mark>.*<mark>Dio 2<\/mark>/u',
            $result['items'][0]['snippet_html'],
        );
    }

    /**
     * HR: Dokazuje da se jezični retci ne prepisuju te da ciljana obnova jednog
     *     područja uklanja samo njegove zastarjele objave.
     * EN: Proves that language rows do not overwrite each other and that a
     *     targeted Workspace rebuild removes only its stale publications.
     */
    public function testIndexIsSeparatedByLanguageAndWorkspaceRebuildRemovesStaleRows(): void
    {
        $workspace = $this->workspace('Višejezično područje', 'languages', 'public');
        $node = $this->page($workspace, 'Hrvatski naslov', 'language-page', 'Hrvatski sadržaj');
        $this->repository->saveNodeWorkflow((int)$node['id'], 'en', [
            'status' => 'published',
            'current_version_number' => 2,
            'published_version_number' => 2,
            'published_by_user_id' => 1,
            'published_at' => '2026-08-12 11:00:00',
        ], 1);
        $this->versions['en:language-page'] = new EditorDocumentVersion(
            'language-page',
            'en',
            2,
            'English title',
            '<p>English content</p>',
            '2026-08-12 11:00:00',
            1,
            'Administrator',
            true,
        );

        $result = $this->indexer->rebuildWorkspace((int)$workspace['id']);
        $this->assertSame(2, $result['indexed']);
        $this->assertSame(1, $this->search->search('hrvatski sadržaj', 'hr')['total']);
        $this->assertSame(1, $this->search->search('english content', 'en')['total']);

        $this->repository->disableNodeTree((int)$workspace['id'], (int)$node['id'], 1);
        $removed = $this->indexer->synchronizeWorkspace((int)$workspace['id']);
        $this->assertSame(2, $removed['removed']);
        $this->assertSame(0, $this->search->search('hrvatski sadržaj', 'hr')['total']);
        $this->assertSame(0, $this->search->search('english content', 'en')['total']);
    }

    /**
     * HR: Ugrađena pretraga područja ne smije vratiti podudaranje iz drugog
     *     područja čak ni kada korisnik smije vidjeti oba područja.
     * EN: Embedded Workspace search must not return a match from another
     *     Workspace even when the actor may view both Workspaces.
     */
    public function testSuggestionsCanBeRestrictedToOneWorkspace(): void
    {
        $first = $this->workspace('Prvo područje', 'first', 'public');
        $second = $this->workspace('Drugo područje', 'second', 'public');
        $firstNode = $this->page($first, 'Prvi rezultat', 'first-result', 'Zajednička tražilica');
        $this->page($second, 'Drugi rezultat', 'second-result', 'Zajednička tražilica');

        $this->indexer->rebuild();
        $suggestions = $this->search->suggest('zajednička', 'hr', null, 8, 'first');

        $this->assertCount(1, $suggestions);
        $this->assertSame('Prvi rezultat', $suggestions[0]['title']);
        $this->assertSame('/workspace/first/' . $firstNode['slug'], $suggestions[0]['url']);
        $this->assertSame('Prvo područje', $suggestions[0]['workspace']);

        $embedded = $this->search->search('zajednička', 'hr', [
            'workspace' => 'first',
            'embedded' => '1',
        ]);
        $this->assertSame('1', $embedded['filters']['embedded']);
        $this->assertSame('first', $embedded['filters']['workspace']);
        $this->assertSame(['Prvi rezultat'], array_column($embedded['items'], 'title'));
    }

    /** HR: Višestruki filtar vraća samo odabrana vidljiva područja. EN: A multi-filter returns only selected visible Workspaces. */
    public function testSearchCanBeRestrictedToMultipleVisibleWorkspaces(): void
    {
        $first = $this->workspace('Prvo područje', 'first', 'public');
        $second = $this->workspace('Drugo područje', 'second', 'public');
        $third = $this->workspace('Treće područje', 'third', 'public');
        $this->page($first, 'Prvi rezultat', 'first-result', 'Zajednička tražilica');
        $this->page($second, 'Drugi rezultat', 'second-result', 'Zajednička tražilica');
        $this->page($third, 'Treći rezultat', 'third-result', 'Zajednička tražilica');
        $this->indexer->rebuild();

        $result = $this->search->search('zajednička', 'hr', [
            'workspaces' => ['first', 'third'],
        ]);

        $this->assertSame(['first', 'third'], $result['workspace_scopes']);
        $this->assertSame(
            ['Prvi rezultat', 'Treći rezultat'],
            array_values(array_intersect(
                ['Prvi rezultat', 'Treći rezultat'],
                array_column($result['items'], 'title'),
            )),
        );
        $this->assertNotContains('Drugi rezultat', array_column($result['items'], 'title'));
    }

    /** HR: Ugrađena pretraga bez valjanog cilja ne smije postati globalna. EN: Embedded search without a valid target must not become global. */
    public function testEmbeddedSearchWithoutVisibleScopeReturnsNoResults(): void
    {
        $workspace = $this->workspace('Dokumentacija', 'docs', 'public');
        $this->page($workspace, 'Vidljiv rezultat', 'visible-result', 'Zajednička tražilica');
        $this->indexer->rebuild();

        $missing = $this->search->search('zajednička', 'hr', [
            'workspaces' => ['not-imported-yet'],
            'embedded' => '1',
        ]);
        $empty = $this->search->search('zajednička', 'hr', ['embedded' => '1']);

        $this->assertSame([], $missing['workspace_scopes']);
        $this->assertSame([], $missing['items']);
        $this->assertSame(0, $missing['total']);
        $this->assertSame(0, $empty['total']);
    }

    /**
     * HR: Pokreće jednu reverzibilnu migraciju i prekida test na pogrešnom ugovoru.
     * EN: Runs one reversible migration and fails the test on an invalid contract.
     */
    private function runMigration(string $path): void
    {
        $migration = require $path;
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($this->database);
    }

    /**
     * HR: Sprema testno područje s vlasnikom administratorom.
     * EN: Persists a test Workspace owned by the administrator.
     *
     * @return array<string, mixed>
     */
    private function workspace(string $name, string $slug, string $visibility): array
    {
        return $this->repository->saveWorkspace([
            'name' => $name,
            'slug' => $slug,
            'visibility' => $visibility,
            'owner_user_id' => 1,
        ], 1);
    }

    /**
     * HR: Sprema dokument, njegov objavljeni workflow i nepromjenjivu Editor verziju.
     * EN: Persists a document, its published workflow, and immutable Editor version.
     *
     * @param array<string, mixed> $workspace
     * @return array<string, mixed>
     */
    private function page(
        array $workspace,
        string $title,
        string $key,
        string $content,
        int $authorUserId = 1,
        int $modifiedByUserId = 1,
        string $publishedAt = '2026-08-12 10:00:00',
        ?int $documentAuthorUserId = null,
        string $documentCreatedAt = '',
    ): array {
        $node = $this->repository->saveNode((int)$workspace['id'], [
            'title' => $title,
            'slug' => $key,
            'node_type' => 'document',
            'document_key' => $key,
        ], $authorUserId);
        $this->repository->saveNodeWorkflow((int)$node['id'], 'hr', [
            'status' => 'published',
            'current_version_number' => 1,
            'published_version_number' => 1,
            'published_by_user_id' => 1,
            'published_at' => $publishedAt,
        ], 1);
        $this->versions['hr:' . $key] = new EditorDocumentVersion(
            $key,
            'hr',
            1,
            $title,
            '<p>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</p>',
            $publishedAt,
            $modifiedByUserId,
            'Izmjenitelj ' . $modifiedByUserId,
            true,
            $documentAuthorUserId,
            $documentCreatedAt,
        );

        return $node;
    }

    /**
     * HR: Daje praznu web sesiju jer test izričito predaje svakog API/web aktera.
     * EN: Supplies an empty web session because the test explicitly passes every API/web actor.
     */
    private function authnHandler(): AuthnHandlerInterface
    {
        return new class implements AuthnHandlerInterface {
            public function login(mixed $credentials): ?array
            {
                return is_array($credentials) ? $credentials : null;
            }

            public function logout(): void
            {
            }

            public function check(): bool
            {
                return false;
            }

            public function user(): ?array
            {
                return null;
            }

            public function userData(): ?array
            {
                return null;
            }
        };
    }
}
