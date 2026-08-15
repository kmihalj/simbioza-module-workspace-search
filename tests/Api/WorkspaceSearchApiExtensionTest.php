<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Tests\Api;

use AaiEduHr\HeartPhrameModuleApi\Contract\ApiRouteRegistry;
use AaiEduHr\HeartPhrameModuleApi\Middleware\ApiAuthenticationMiddleware;
use AaiEduHr\HeartPhrameModuleWorkspaceSearch\Api\WorkspaceSearchApiExtension;
use HeartPhrame\Routing\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** HR: Provjerava Workspace Search API oglas. EN: Verifies the Workspace Search API declaration. */
#[CoversClass(WorkspaceSearchApiExtension::class)]
#[CoversClass(ApiRouteRegistry::class)]
final class WorkspaceSearchApiExtensionTest extends TestCase
{
    /** HR: Registrira ACL-svjesnu rutu pretrage sa zaštitom. EN: Registers the protected ACL-aware search route. */
    public function testRegistersOwnedRoute(): void
    {
        $routes = new Routes();
        (new WorkspaceSearchApiExtension())->register(new ApiRouteRegistry($routes));
        $namedRoutes = $routes->getNamedRoutes();
        $registeredRoutes = $routes->getRoutes();

        $this->assertCount(1, $namedRoutes);
        $this->assertSame('/api/v1/workspace-search', $namedRoutes['api.v1.workspace-search']['path'] ?? null);
        $this->assertContains(
            ApiAuthenticationMiddleware::class,
            $registeredRoutes['GET']['/api/v1/workspace-search']['middleware'],
        );
    }
}
