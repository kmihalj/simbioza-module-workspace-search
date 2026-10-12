<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Api;

use AaiEduHr\HeartPhrameModuleApi\Contract\ApiExtensionInterface;
use AaiEduHr\HeartPhrameModuleApi\Contract\ApiRouteRegistry;

/**
 * EN: Advertises the search route to the generic API core.
 *
 * HR: Oglašava rutu pretrage generičkoj API jezgri.
 *
 * @see \AaiEduHr\SimbiozaModuleWorkspaceSearch\Tests\Api\WorkspaceSearchApiExtensionTest
 */
final readonly class WorkspaceSearchApiExtension implements ApiExtensionInterface
{
    /**
     * EN: Returns the stable Search extension identifier.
     *
     * HR: Vraća stabilni identifikator Search proširenja.
     */
    public function id(): string
    {
        return 'workspace-search';
    }

    /**
     * EN: Adds the stable search endpoint through the shared secure registry.
     *
     * HR: Dodaje stabilni endpoint pretrage kroz zajednički sigurni registar.
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
