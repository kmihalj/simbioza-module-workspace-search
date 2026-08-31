<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Event;

/**
 * HR: Bilježi ACL-filtriranu pretragu bez spremanja korisnikova upita.
 * EN: Records an ACL-filtered search without storing the user's query text.
 */
final readonly class WorkspaceSearchPerformed
{
    /** HR: Stvara agregirani opis jedne ACL-filtrirane pretrage. EN: Creates an aggregate description of one ACL-filtered search. */
    public function __construct(
        public string $language,
        public int $queryLength,
        public int $termCount,
        public int $resultCount,
        public string $workspaceSlug = '',
        public bool $authenticated = false,
    ) {
    }
}
