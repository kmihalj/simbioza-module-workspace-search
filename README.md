# HeartPhrame Workspace Search module

[Hrvatska verzija](README_hr.md)

Workspace Search provides global search across published Workspace pages. It is
deliberately not a generic site-search package: every result depends on the
Workspace tree, inherited page ACL, Editor publication workflow, and Menu
integration.

## Dependencies

Required, in enable order:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-menu` (`dev-main`)
4. `aaieduhr/heartphrame-module-auth` (`dev-main`)
5. `aaieduhr/heartphrame-module-editor-html` (`dev-main`)
6. `aaieduhr/heartphrame-module-workspace` (`dev-main`)
7. `aaieduhr/heartphrame-module-workspace-search` (`dev-main`)

Optional integration:

- `aaieduhr/heartphrame-module-api` exposes the same ACL-aware search through
  `GET /api/v1/workspace-search`.

The module is not useful without Workspace, Editor, and Menu, so those are hard
dependencies rather than optional suggestions.

## Installation

```bash
composer require aaieduhr/heartphrame-module-workspace-search:dev-main
vendor/bin/hph workspace-search:install-migration
vendor/bin/hph orm-migrate:up
vendor/bin/hph workspace-search:rebuild
```

Enable the package after every required module. Search then appears in the
right side of the top menu and the full page is available at `/search` (under
the application's configured base path).

## What is searched

- published page title;
- sanitized plain text of the exact published Editor version;
- publishing author;
- requested language with site-default fallback;
- optional Workspace and publication-date filters.

Drafts, archived workflows, deleted pages, and inaccessible descendants are
never returned. Guest searches contain public pages only. Authenticated web
users and API-key owners receive only content allowed by their effective
Workspace and inherited page ACL. Filtering occurs before totals, snippets,
pagination, and suggestions, preventing metadata leaks.

The HTML Editor can insert search for the current Workspace. It dynamically
uses the same ACL-filtered suggestion endpoint while the server limits results
to that Workspace slug. Access does not depend on text or a hidden field that
a visitor could alter to expose another Workspace.

## Index operations

The database table is a derived, rebuildable index. It stores published text
and stable identifiers but no ACL rows. Rows are unique per page and language.
Publishing, archiving, restoring to an unpublished draft, deleting, and changing
Workspace/page metadata dispatch a synchronous Workspace event, so Search
reindexes only the affected Workspace (and language when known). Saving a draft
does not replace the currently indexed published version.

A normal search also performs a bounded refresh according to
`automatic_index_refresh_seconds` as a recovery layer. After bulk import,
restore, deployment, or suspected drift, administrators can use **Settings →
Workspaces → Search index** to rebuild the entire site or one Workspace. The
equivalent CLI commands are:

Permanent Workspace deletion removes its derived index rows immediately. The
index is not business data and is therefore rebuilt, not backed up.

```bash
vendor/bin/hph workspace-search:rebuild
vendor/bin/hph workspace-search rebuild --workspace=42
```

Application overrides belong in `config/workspace-search.php`:

```php
<?php

return [
    'results_per_page' => 20,
    'maximum_results_per_page' => 100,
    'minimum_query_length' => 2,
    'snippet_length' => 320,
    'automatic_index_refresh_seconds' => 60,
];
```

Set the refresh interval to `0` when a scheduler or deployment pipeline always
rebuilds the index.

## Documentation

- [Architecture, ACL, and indexing](docs/index_en.md)
- [Web and HTTP API examples](docs/api_en.md)
- [Testing on three databases](docs/testing_en.md)
- [Backup integration](docs/backup_en.md)

The Framework and all internal modules follow the moving `dev-main` policy.
Do not pin one internal module to an older commit in this package.
