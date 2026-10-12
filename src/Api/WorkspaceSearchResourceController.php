<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Api;

use AaiEduHr\HeartPhrameModuleApi\Http\ApiResponseFactory;
use AaiEduHr\HeartPhrameModuleApi\ModuleApi;
use AaiEduHr\HeartPhrameModuleAuth\Api\AuthApiIdentity;
use AaiEduHr\SimbiozaModuleWorkspaceSearch\Service\WorkspaceSearchService;
use HeartPhrame\Config\ConfigInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

/**
 * EN: Exposes ACL-aware Workspace search to the API-key owner.
 *
 * HR: Izlaže ACL-svjesnu pretragu područja vlasniku API ključa.
 */
final readonly class WorkspaceSearchResourceController
{
    /**
     * EN: Receives the shared API response factory, search service, and locale configuration.
     *
     * HR: Prima zajedničku API tvornicu, servis pretrage i lokalizacijsku konfiguraciju.
     */
    public function __construct(
        private ApiResponseFactory $responses,
        private WorkspaceSearchService $search,
        private ConfigInterface $config,
    ) {
    }

    /**
     * EN: Searches only pages visible to the API key's actual user.
     *
     * HR: Pretražuje samo stranice koje stvarni korisnik API ključa smije vidjeti.
     */
    public function search(ServerRequestInterface $request): ResponseInterface
    {
        $identity = $this->identity($request);
        if (!$identity->permits('workspace-search:read')) {
            return $this->responses->problem(
                $request,
                403,
                'insufficient_scope',
                __('Pristup nije dozvoljen'),
                sprintf(__('API ključ nema potreban scope "%s".'), 'workspace-search:read'),
            );
        }

        try {
            $query = $this->stringKeyArray($request->getQueryParams());
            $term = is_scalar($query['q'] ?? null) ? trim((string)$query['q']) : '';
            $language = is_scalar($query['lang'] ?? null)
                ? trim((string)$query['lang'])
                : $this->defaultLanguage();
            $result = $this->search->search($term, $language, $query, $identity->user);

            return $this->responses->success(
                $request,
                $result['items'] ?? [],
                meta: [
                    'query' => $result['query'] ?? $term,
                    'language' => $result['language'] ?? $language,
                    'default_language' => $result['default_language'] ?? $this->defaultLanguage(),
                    'page' => $result['page'] ?? 1,
                    'per_page' => $result['per_page'] ?? 10,
                    'total' => $result['total'] ?? 0,
                    'pages' => $result['pages'] ?? 0,
                    'filters' => $result['filters'] ?? [],
                ],
                links: ['self' => $this->responses->requestTarget($request)],
            );
        } catch (Throwable) {
            return $this->responses->problem(
                $request,
                500,
                'workspace_search_failed',
                __('Pretragu nije moguće izvršiti'),
                __('Zahtjev nije moguće obraditi. Obrati se administratoru uz request ID.'),
            );
        }
    }

    /**
     * EN: Returns the identity attached by the shared API middleware.
     *
     * HR: Vraća identitet koji je postavio zajednički API middleware.
     */
    private function identity(ServerRequestInterface $request): AuthApiIdentity
    {
        $identity = $request->getAttribute(ModuleApi::IDENTITY_ATTRIBUTE);
        if (!$identity instanceof AuthApiIdentity) {
            throw new RuntimeException('Authenticated API identity is missing.');
        }

        return $identity;
    }

    /**
     * EN: Keeps only string query keys.
     *
     * HR: Zadržava samo tekstualne query ključeve.
     *
     * @param array<mixed,mixed> $values
     * @return array<string,mixed>
     */
    private function stringKeyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * EN: Reads the application default language with a safe Croatian fallback.
     *
     * HR: Čita zadani jezik aplikacije uz hrvatski sigurni fallback.
     */
    private function defaultLanguage(): string
    {
        $language = trim(
            $this->config->getAsString('app.localization.locale')
                ?? $this->config->getAsString('app.locale')
                ?? '',
        );

        return preg_match('/^[a-z]{2,8}(?:-[a-z0-9]{2,8})*$/i', $language) === 1
            ? strtolower($language)
            : 'hr';
    }
}
