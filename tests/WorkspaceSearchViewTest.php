<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Tests;

use DOMDocument;
use DOMXPath;
use HeartPhrame\View\View;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversNothing]
final class WorkspaceSearchViewTest extends TestCase
{
    /**
     * EN: Rebuild scope and its search have associated names.
     *
     * HR: Opseg obnove i njegovo pretraživanje imaju povezana imena.
     */
    public function testReindexPickerHasNamedControlsAndLiveFeedback(): void
    {
        $view = (string)file_get_contents(__DIR__ . '/../views/settings/index.php');
        $this->assertStringContainsString('for="search-reindex-toggle"', $view);
        $this->assertSame(1, substr_count($view, 'id="search-reindex-toggle"'));
        $this->assertStringContainsString('aria-labelledby="search-reindex-label search-reindex-toggle"', $view);
        $this->assertMatchesRegularExpression('/role="status"\s+data-workspace-lookup-loading/', $view);
        $this->assertMatchesRegularExpression('/role="alert"\s+data-workspace-lookup-error/', $view);
        $this->assertStringContainsString('aria-label="<?= $this->escape(__(\'Pretraži područja\')) ?>"', $view);
    }

    /**
     * EN: A large result must not render one link for every page.
     *
     * HR: Veliki rezultat ne smije iscrtati poveznicu za svaku stranicu.
     */
    public function testPaginationUsesBoundedWindowAndPreviousNextLinks(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('$page - 2', $view);
        $this->assertStringContainsString('$page + 2', $view);
        $this->assertStringContainsString("__('Prethodna stranica')", $view);
        $this->assertStringContainsString("__('Sljedeća stranica')", $view);
        $this->assertStringNotContainsString('for ($number = 1; $number <= $pages;', $view);
    }

    /**
     * EN: A Workspace result has a clear type and omits empty page metadata.
     *
     * HR: Rezultat područja ima jasnu vrstu i ne prikazuje prazne metapodatke stranice.
     */
    public function testWorkspaceResultHasDedicatedPresentation(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString("['result_type']", $view);
        $this->assertStringContainsString("=== 'workspace'", $view);
        $this->assertStringContainsString("__('Područje')", $view);
    }

    /**
     * EN: The filter presents one aggregate choice instead of hundreds of personal Workspaces.
     *
     * HR: Filter ne ispisuje stotine osobnih područja nego jednu skupnu mogućnost.
     */
    public function testWorkspaceFilterAggregatesPersonalWorkspaces(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('PERSONAL_WORKSPACES_FILTER', $view);
        $this->assertStringContainsString("__('Osobna područja')", $view);
        $this->assertStringContainsString("['is_personal_workspace']", $view);
    }

    /**
     * EN: Embedded search preselects Workspaces in the regular editable picker.
     *
     * HR: Ugrađena pretraga unaprijed označava područja u običnom promjenjivom odabiru.
     */
    public function testEmbeddedSearchKeepsWorkspaceScopesVisibleAndEditable(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringNotContainsString('$embeddedWorkspaceSearch', $view);
        $this->assertStringNotContainsString('name="embedded" value="1"', $view);
        $this->assertStringContainsString('name="workspaces[]"', $view);
        $this->assertStringContainsString('data-workspace-search-scope-picker', $view);
        $this->assertStringNotContainsString('aria-readonly="true"', $view);
    }

    /**
     * EN: Global search uses a multi-picker with one all-Workspaces option.
     *
     * HR: Globalna pretraga koristi višestruki odabir s jednom opcijom za sva područja.
     */
    public function testGlobalSearchUsesCheckboxWorkspacePicker(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('data-workspace-search-scope-picker', $view);
        $this->assertStringContainsString('data-workspace-search-scope-all', $view);
        $this->assertStringContainsString('type="checkbox"', $view);
        $this->assertStringContainsString('data-bs-auto-close="outside"', $view);
        $this->assertStringContainsString('ALL_WORKSPACES_FILTER', $view);
    }

    /**
     * EN: The form explains default phrase and advanced operator semantics.
     *
     * HR: Forma objašnjava zadanu frazu i napredne operatore.
     */
    public function testSearchFormDocumentsPhraseSyntax(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('Kako radi pretraga', $view);
        $this->assertStringContainsString('Odaberite jedno područje bez traženog pojma', $view);
        $this->assertStringContainsString('stranica koje je stvorio ili zadnji izmijenio', $view);
        $this->assertStringContainsString('Traženi pojam, područje, autora', $view);
        $this->assertStringContainsString('dva ili više područja', $view);
        $this->assertStringContainsString('+dio +drugi +"Dio 2"', $view);
    }

    /**
     * EN: Submitting the form preserves the selected search language.
     *
     * HR: Slanje forme zadržava odabrani jezik pretrage.
     */
    public function testSearchFormPreservesSelectedLanguage(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('name="lang"', $view);
        $this->assertStringContainsString("\$result['language']", $view);
    }

    /**
     * EN: Author uses a remote paged picker with load-more support.
     *
     * HR: Autor koristi udaljeni birač s ograničenim stranicama i dodatnim učitavanjem.
     */
    public function testAuthorUsesRemotePagedPicker(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('data-workspace-search-author-picker', $view);
        $this->assertStringContainsString('data-author-more', $view);
        $this->assertStringContainsString("url.searchParams.set('page'", $view);
        $this->assertStringContainsString("__('Učitaj još')", $view);
    }

    /**
     * EN: An empty term renders the sortable table with every requested column.
     *
     * HR: Prazan pojam prikazuje sortirljivu tablicu sa svim traženim kolonama.
     */
    public function testBrowseModeUsesSortablePageTable(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('$browseMode', $view);
        $this->assertStringContainsString("'title' => __('Ime stranice')", $view);
        $this->assertStringContainsString("'modified_at' => __('Zadnja izmjena')", $view);
        $this->assertStringContainsString("'modified_by' => __('Izmijenio')", $view);
        $this->assertStringContainsString('$sortPath($column)', $view);
    }

    /**
     * EN: Actual rendered links remove only their filter and preserve the query and locale without injecting HTML.
     *
     * HR: Stvarno iscrtane poveznice uklanjaju samo svoj filtar i čuvaju upit i jezik bez umetanja HTML-a.
     */
    public function testRenderedFilterChipsPreserveUrlStateAndEscapeValues(): void
    {
        $parameters = [
            'q' => 'Report', 'lang' => 'fr', 'workspaces' => ['docs'], 'label' => '<script>alert(1)</script>',
            'property_key' => 'owner', 'property_value' => 'A&B', 'workflow' => 'in_review', 'page' => 7,
        ];
        $view = (new ReflectionClass(View::class))->newInstanceWithoutConstructor();
        $html = $view->forExternalPartial(__DIR__ . '/../views/search/filter-chips.php', [
            'filters' => $parameters,
            'paginationQuery' => $parameters,
            'searchPath' => '/example/search',
            'query' => 'Report',
            'language' => 'fr',
            'selectedWorkspaceScopes' => ['docs'],
            'workspaceNames' => ['docs' => 'Documentation'],
            'personalWorkspaceFilter' => '__personal__',
            'allWorkspaceFilter' => '__all__',
            'selectedAuthorLabel' => '',
        ]);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//script')->length);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $links = $xpath->query('//nav/a');
        $this->assertCount(6, $links);
        $states = [];
        foreach ($links as $link) {
            $url = $link->attributes?->getNamedItem('href')->nodeValue ?? '';
            $state = [];
            parse_str((string)parse_url($url, PHP_URL_QUERY), $state);
            $this->assertSame('Report', $state['q']);
            $this->assertSame('fr', $state['lang']);
            $this->assertArrayNotHasKey('page', $state);
            $states[] = $state;
        }

        $this->assertArrayNotHasKey('workspaces', $states[0]);
        $this->assertArrayNotHasKey('label', $states[1]);
        $this->assertSame('A&B', $states[1]['property_value']);
        $this->assertArrayNotHasKey('property_key', $states[2]);
        $this->assertArrayNotHasKey('property_value', $states[2]);
        $this->assertSame('owner', $states[3]['property_key']);
        $this->assertArrayNotHasKey('property_value', $states[3]);
        $this->assertSame(['q' => 'Report', 'lang' => 'fr'], $states[5]);
    }

    /**
     * EN: Metadata controls have names, live feedback, and an initially disabled dependent value picker.
     *
     * HR: Kontrole metapodataka imaju nazive, povratne poruke i početno isključen ovisni birač vrijednosti.
     */
    public function testRenderedMetadataPickersAreNamedAndDoNotEmbedOptions(): void
    {
        $view = (new ReflectionClass(View::class))->newInstanceWithoutConstructor();
        $html = $view->forExternalPartial(__DIR__ . '/../views/search/metadata-filters.php', [
            'filters' => ['label' => '"><script>alert(1)</script>'],
            'metadataLookupPaths' => [
                'label' => '/example/search/lookups/metadata/labels',
                'property_key' => '/example/search/lookups/metadata/properties',
                'property_value' => '/example/search/lookups/metadata/values',
            ],
        ]);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//script')->length);
        foreach (['label', 'property_key', 'property_value'] as $name) {
            $id = 'workspace-search-' . $name . '-toggle';
            $this->assertSame(1, $xpath->query('//label[@for="' . $id . '"]')->length);
            $this->assertSame(1, $xpath->query('//button[@id="' . $id . '"]')->length);
        }

        $dependent = $xpath->query('//button[@id="workspace-search-property_value-toggle"][@disabled]');
        $this->assertSame(1, $dependent->length);
        $this->assertSame(3, $xpath->query('//*[@data-metadata-loading][@role="status"]')->length);
        $this->assertSame(3, $xpath->query('//*[@data-metadata-error][@role="alert"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-metadata-list]/*')->length);
    }
}
