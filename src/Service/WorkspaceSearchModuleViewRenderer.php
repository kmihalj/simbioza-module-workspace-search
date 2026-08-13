<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service;

use AaiEduHr\HeartPhrameModuleWorkspaceSearch\ModuleWorkspaceSearch;
use HeartPhrame\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * HR: Renderira view datoteke Workspace Search modula kroz aplikacijski layout.
 * EN: Renders Workspace Search views through the application layout.
 */
final readonly class WorkspaceSearchModuleViewRenderer
{
    /**
     * HR: Inicijalizira objekt i njegove ovisnosti.
     * EN: Initializes the object and its dependencies.
     */
    public function __construct(private ResponseFactory $responses)
    {
    }

    /**
 * HR: Renderira modulsku view datoteku kroz glavni aplikacijski layout.
 * EN: Renders a module view through the main application layout.
 *
 * @param array<string, mixed> $data
 */
    public function render(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->responses->viewForModule(
            ModuleWorkspaceSearch::PACKAGE_NAME,
            $view,
            $data,
            true,
            $status,
        );
    }
}
