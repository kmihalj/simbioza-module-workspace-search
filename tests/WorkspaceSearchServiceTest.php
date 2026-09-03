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
    private function page(array $workspace, string $title, string $key, string $content): array
    {
        $node = $this->repository->saveNode((int)$workspace['id'], [
            'title' => $title,
            'slug' => $key,
            'node_type' => 'document',
            'document_key' => $key,
        ], 1);
        $this->repository->saveNodeWorkflow((int)$node['id'], 'hr', [
            'status' => 'published',
            'current_version_number' => 1,
            'published_version_number' => 1,
            'published_by_user_id' => 1,
            'published_at' => '2026-08-12 10:00:00',
        ], 1);
        $this->versions['hr:' . $key] = new EditorDocumentVersion(
            $key,
            'hr',
            1,
            $title,
            '<p>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</p>',
            '2026-08-12 10:00:00',
            1,
            'Administrator',
            true,
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
