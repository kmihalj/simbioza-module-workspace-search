<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WorkspaceSearchViewTest extends TestCase
{
    /** HR: Veliki rezultat ne smije iscrtati poveznicu za svaku stranicu. EN: A large result must not render one link for every page. */
    public function testPaginationUsesBoundedWindowAndPreviousNextLinks(): void
    {
        $view = file_get_contents(dirname(__DIR__) . '/views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('$page - 2', $view);
        $this->assertStringContainsString('$page + 2', $view);
        $this->assertStringContainsString("__('Previous page')", $view);
        $this->assertStringContainsString("__('Next page')", $view);
        $this->assertStringNotContainsString('for ($number = 1; $number <= $pages;', $view);
    }

    /** HR: Rezultat područja ima jasnu vrstu i ne prikazuje prazne metapodatke stranice. EN: A Workspace result has a clear type and omits empty page metadata. */
    public function testWorkspaceResultHasDedicatedPresentation(): void
    {
        $view = file_get_contents(dirname(__DIR__) . '/views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString("['result_type']", $view);
        $this->assertStringContainsString("=== 'workspace'", $view);
        $this->assertStringContainsString("__('Workspace')", $view);
    }

    /** HR: Filter ne ispisuje stotine osobnih područja nego jednu skupnu mogućnost. EN: The filter presents one aggregate choice instead of hundreds of personal Workspaces. */
    public function testWorkspaceFilterAggregatesPersonalWorkspaces(): void
    {
        $view = file_get_contents(dirname(__DIR__) . '/views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('PERSONAL_WORKSPACES_FILTER', $view);
        $this->assertStringContainsString("__('Personal Workspaces')", $view);
        $this->assertStringContainsString("['is_personal_workspace']", $view);
    }

    /** HR: Ugrađena pretraga prikazuje zaključano izvorno područje. EN: Embedded search renders its originating Workspace as a locked scope. */
    public function testEmbeddedSearchKeepsWorkspaceScopeVisibleAndFixed(): void
    {
        $view = file_get_contents(dirname(__DIR__) . '/views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('$embeddedWorkspaceSearch', $view);
        $this->assertStringContainsString('name="embedded" value="1"', $view);
        $this->assertStringContainsString("__('Search is limited to this Workspace.')", $view);
    }

    /** HR: Forma objašnjava zadanu frazu i napredne operatore. EN: The form explains default phrase and advanced operator semantics. */
    public function testSearchFormDocumentsPhraseSyntax(): void
    {
        $view = file_get_contents(dirname(__DIR__) . '/views/search/index.php');

        $this->assertIsString($view);
        $this->assertStringContainsString('Without operators, multiple words are searched as an exact phrase.', $view);
        $this->assertStringContainsString('+word and +"multiple words"', $view);
    }
}
