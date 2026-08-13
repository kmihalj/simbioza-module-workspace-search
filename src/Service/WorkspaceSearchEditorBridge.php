<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspaceSearch\Service;

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorPublishedVersionProviderInterface;

/**
 * HR: Uski adapter prema objavljenim, nepromjenjivim Editor verzijama.
 * EN: Narrow adapter to published immutable Editor versions.
 */
final readonly class WorkspaceSearchEditorBridge
{
    /**
     * HR: Inicijalizira objekt i njegove ovisnosti.
     * EN: Initializes the object and its dependencies.
     */
    public function __construct(private EditorPublishedVersionProviderInterface $editor)
    {
    }

    /**
 * HR: Učitava zadane objavljene verzije bez oslanjanja na aktivnu sesiju.
 * EN: Loads the requested published versions without relying on an active session.
 *
 * @param array<string, int> $versionsByDocument
 * @return array<string, \AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocumentVersion>
 */
    public function publishedVersions(array $versionsByDocument, string $language): array
    {
        return $this->editor->loadPublishedVersionsForIndexing($versionsByDocument, $language);
    }
}
