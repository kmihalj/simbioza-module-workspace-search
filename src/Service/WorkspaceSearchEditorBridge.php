<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Service;

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorPublishedVersionProviderInterface;

/**
 * EN: Narrow adapter to published immutable Editor versions.
 *
 * HR: Uski adapter prema objavljenim, nepromjenjivim Editor verzijama.
 */
final readonly class WorkspaceSearchEditorBridge
{
    /**
     * EN: Initializes the object and its dependencies.
     *
     * HR: Inicijalizira objekt i njegove ovisnosti.
     */
    public function __construct(private EditorPublishedVersionProviderInterface $editor)
    {
    }

    /**
     * EN: Loads the requested published versions without relying on an active session.
     *
     * HR: Učitava zadane objavljene verzije bez oslanjanja na aktivnu sesiju.
     *
     * @param array<string, int> $versionsByDocument
     * @return array<string, \AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocumentVersion>
     */
    public function publishedVersions(array $versionsByDocument, string $language): array
    {
        return $this->editor->loadPublishedVersionsForIndexing($versionsByDocument, $language);
    }
}
