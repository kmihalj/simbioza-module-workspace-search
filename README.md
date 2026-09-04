# Simbioza Workspace Search module

[Hrvatska verzija](README_hr.md)

Workspace Search provides global search across published Workspace pages. It is
deliberately not a generic site-search package: every result depends on the
Workspace tree, inherited page ACL, Editor publication workflow, and Menu
integration.

## Dependencies

Required, in enable order:

1. `aaieduhr/heartphrame-framework` (`^0.0.25`)
2. `aaieduhr/heartphrame-module-orm` (`^0.1.0`)
3. `aaieduhr/heartphrame-module-menu` (`^0.1.0`)
4. `aaieduhr/heartphrame-module-auth` (`^0.1.0`)
5. `aaieduhr/heartphrame-module-editor-html` (`^0.1.0`)
6. `aaieduhr/simbioza-module-workspace` (`^0.1.0`)
7. `aaieduhr/simbioza-module-workspace-search` (`^0.1.0`)

Optional integration:

- `aaieduhr/heartphrame-module-api` exposes the same ACL-aware search through
  `GET /api/v1/workspace-search`.

The module is not useful without Workspace, Editor, and Menu, so those are hard
dependencies rather than optional suggestions.

## Installation

```bash
composer require aaieduhr/simbioza-module-workspace-search:^0.1.5
vendor/bin/hph workspace-search:install-migration
vendor/bin/hph orm-migrate:up
vendor/bin/hph workspace-search:rebuild
```

Enable the package after every required module. Search then appears in the
right side of the top menu and the full page is available at `/search` (under
the application's configured base path).

## What is searched

- visible Workspace name, description, and slug, including a localized personal-
  Workspace owner name supplied by an optional presentation provider;
- published page title;
- sanitized plain text of the exact published Editor version;
- publishing author;
- requested language with site-default fallback;
- optional Workspace and publication-date filters.

The full search form offers a checkbox picker for any combination of ordinary
Workspaces visible to the current visitor, plus **All visible Workspaces**.
All visible personal Workspaces are represented by one **Personal Workspaces**
choice instead of one option per user. The list is available before a query is
entered, and every combination still applies the normal Workspace and page ACL
checks.

Multiple words without operators are searched as one exact phrase. Advanced
queries use `+word` and `+"multiple words"`; every listed word and quoted phrase
is then required with AND semantics, in the order entered. For example,
`+Part +1 +"Part 2"` requires all three expressions. The same rule is shown
below both search forms.

Workspace matches are returned even when the Workspace has no published page.
Drafts, archived workflows, deleted pages, and inaccessible descendants are
never returned. Guest searches contain public Workspaces and pages only.
Authenticated web users and API-key owners receive only content allowed by
their effective Workspace and inherited page ACL. Filtering occurs before
totals, snippets, pagination, and suggestions, preventing metadata leaks.

The HTML Editor can insert search for one or more selected Workspaces, defaulting
to the current Workspace. That compact form submits directly to the full result
page without an overlapping suggestion overlay. The result form keeps all
selected Workspace names visible as a fixed scope instead of offering the global
picker. The server intersects every submitted slug with current ACL visibility;
an embedded form without any valid target returns no results instead of widening
to a global search.

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

The Framework uses `^0.0.25` and all internal modules use the compatible
`^0.1.0` release line. Do not pin one internal module to an older commit.
