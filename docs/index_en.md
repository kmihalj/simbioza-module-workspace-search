# Architecture, ACL, and indexing

Croatian version: [index_hr.md](index_hr.md)

## Module boundary

Workspace Search owns only discovery and its derived index. Workspace remains
the source of truth for tree structure, publication pointers, and ACL. Editor
remains the source of truth for immutable page versions. Auth supplies author
labels, Menu supplies the extension point, and ORM supplies portable storage.

The indexer consumes `EditorPublishedVersionProviderInterface`, a narrow
contract for exact published versions. It deliberately bypasses a CLI session,
because a rebuild has no signed-in user, but it cannot choose an arbitrary
draft or version. The search service separately applies the requesting actor's
actual ACL before results leave the module.

## Security order

For every web or API request the service:

1. enumerates Workspaces visible to the explicit actor (or public Workspaces
   for a guest);
2. localizes Workspace names/descriptions and matches the name, description,
   and slug only in that ACL-authorized collection;
3. builds the visible tree in requested and fallback languages;
4. applies inherited node restrictions and removes inaccessible descendants;
5. limits the SQL candidate set to those node IDs;
6. searches the exact published page title and body and applies optional
   Workspace/author/date filters;
7. selects the requested locale or exact published site-default fallback;
8. only then combines Workspace/page results and computes totals, snippets,
   highlights, and pagination.

A Workspace-name match is a real result even when it has no published page. A
published page title remains searchable independently of its body text.

The index intentionally contains no authorization decision because users,
groups, and inherited restrictions can change without reindexing page text.

## Language behavior

Search prefers `lang`. If one node has no exact published page in that locale,
it may use the configured Workspace/site default locale. It never falls back
to a draft. The same node appears once, not once per language. Croatian is the
safe application fallback when no valid locale is configured.

## Operational behavior

Index rows are unique by `(node_id, language_code)`. Workspace dispatches a
neutral `WorkspaceContentChanged` event after published content, page/tree
metadata, or Workspace availability changes. Search listens without a reverse
Workspace-to-Search dependency and synchronizes only that Workspace; a known
publication language further narrows the work. Draft saves keep the last
published index row unchanged until explicit publication.

Routine HTTP searches still check the newest database `indexed_at` value and a
per-process timestamp as a recovery layer. Administrators may rebuild the full
site or one Workspace at **Settings → Workspaces → Search index**. CLI and
deployment automation can use:

```bash
vendor/bin/hph workspace-search:rebuild
vendor/bin/hph workspace-search rebuild --workspace=42
```

The derived table may always be deleted and reconstructed from Workspace plus
Editor data.

Search uses ordinary ORM query-builder operations supported by SQLite,
PostgreSQL, and MySQL/MariaDB. No database vendor owns the feature.

Input without special characters is searched as one complete phrase. To require
several separate words or phrases, prefix every term with `+`;
`+part +second +"Part 2"` therefore requires all three expressions.

The index is deliberately rebuilt rather than archived. See
[Backup integration](backup_en.md).

## Embedded selected-Workspace search

The Workspace **Workspace search** dynamic block uses the same index and ACL
service as the full page and API, but adds one or more selected Workspace slugs
and an embedded-search marker to the request. The current Workspace is the
default. Results from an unselected or currently invisible Workspace cannot
appear. The compact form submits without a live suggestion overlay. On the
result page all selected Workspaces are rendered as a named, read-only scope;
pagination preserves it. With no available target the search returns zero
results and never broadens to all Workspaces.

Permanent Workspace deletion emits the cleanup event before source rows vanish.
Search removes rows with that `workspace_id` immediately; a later rebuild remains
safe and deterministic.
