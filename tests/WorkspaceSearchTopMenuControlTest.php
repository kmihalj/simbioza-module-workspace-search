<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Tests;

use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service\WorkspaceSearchTopMenuControl;
use HeartPhrame\Localization\TranslatorInterface;
use HeartPhrame\Routing\UrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkspaceSearchTopMenuControl::class)]
final class WorkspaceSearchTopMenuControlTest extends TestCase
{
    /**
     * HR: Tražilica u navigaciji mora se okomito poravnati s jezikom i računom.
     * EN: Navigation search must align vertically with language and account controls.
     */
    public function testControlUsesVerticallyCenteredNavigationItem(): void
    {
        $urls = $this->createStub(UrlGenerator::class);
        $urls->method('getPathFor')->willReturn('/search');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Search workspaces');
        $control = new WorkspaceSearchTopMenuControl($urls, $translator);

        $html = $control->render();

        $this->assertStringContainsString('hph-workspace-search-control d-flex align-items-center', $html);
        $this->assertStringContainsString('action="/search"', $html);
    }
}
