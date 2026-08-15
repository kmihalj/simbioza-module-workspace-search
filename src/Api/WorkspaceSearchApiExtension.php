<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Api;

use AaiEduHr\HeartPhrameModuleApi\Contract\ApiExtensionInterface;
use AaiEduHr\HeartPhrameModuleApi\Contract\ApiRouteRegistry;

/**
 * HR: Oglašava rutu pretrage generičkoj API jezgri.
 * EN: Advertises the search route to the generic API core.
 * @see \AaiEduHr\HeartPhrameModuleWorkspaceSearch\Tests\Api\WorkspaceSearchApiExtensionTest
 */
final readonly class WorkspaceSearchApiExtension implements ApiExtensionInterface
{
    /**
     * HR: Vraća stabilni identifikator Search proširenja.
     * EN: Returns the stable Search extension identifier.
     */
    public function id(): string
    {
        return 'workspace-search';
    }

    /**
     * HR: Dodaje stabilni endpoint pretrage kroz zajednički sigurni registar.
     * EN: Adds the stable search endpoint through the shared secure registry.
     */
    public function register(ApiRouteRegistry $routes): void
    {
        $routes->add(
            'GET',
            '/api/v1/workspace-search',
            WorkspaceSearchResourceController::class,
            'search',
            'api.v1.workspace-search',
        );
    }
}
