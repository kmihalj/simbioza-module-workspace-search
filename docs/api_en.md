# Web and HTTP API examples

Croatian version: [api_hr.md](api_hr.md)

## Web search

The Menu extension renders a compact search form in the right-hand navigation
area. It therefore remains beside language/account controls when the header is
disabled and moves into the same responsive drawer on a small screen. The full
page is `/search` and supports:

```text
?q=agenda&lang=en&workspaces[]=conference&workspaces[]=guides&author=Ana&from=2026-01-01&to=2026-12-31&page=1&per_page=20
```

The optional `/search/suggest?q=...&lang=...` JSON route returns only a bounded
title/URL/Workspace projection from the same ACL-filtered service. A result can
represent a published page or a Workspace matched by name, description, or slug.
The full page renders a bounded page-number window with previous/next links,
not one control for every page in a large result set.

Plain multi-word `q` values are exact phrases. Use `+word` and
`+"multiple words"` when every listed word and phrase must be present, for
example `+Part +1 +"Part 2"`.

Repeat `workspaces[]` to select multiple Workspaces. The special values
`__all__` and `__personal__` mean all visible Workspaces and all personal
Workspaces visible to the actor. The legacy single `workspace` parameter remains
supported.

An empty search can browse one selected Workspace or a set narrowed by an
author, date, label, property, or publication status. Multiple selected Workspace choices require a search term. A query
shorter than `minimum_query_length` (default 2 characters) is not executed.
Dates and sorting still apply to the same ACL-filtered result set.

### Labels, structured properties, and publication status

The full search form lets you select existing metadata; it does not create or
change page metadata. The same parameters work on `/search` and the HTTP API:

| Parameter | Meaning |
| --- | --- |
| `label` | One exact existing page label. |
| `property_key` | One existing structured-property key; without a value, any page containing that property matches. |
| `property_value` | Exact stored value for the selected `property_key`; a value without a key returns no results. |
| `workflow` | `published`, `draft`, or `in_review`. An empty value accepts all available states. |

For example, `/search?lang=en&label=conference&property_key=year&property_value=2026`
lists published pages with both the label and the exact property value. Every
selected criterion is combined with AND. Filtering by page metadata omits bare
Workspace cards. All selections remain in the URL, including when sorting or
changing result pages. Above the results, remove an individual filter using its
chip or choose **Clear filters**; clearing preserves the search term and locale.
Removing a property key also removes its dependent value.

Search still covers published versions only. `draft` and `in_review` mean
unpublished changes to an already published page, available only to actors who
can edit, publish, or manage that page. For readers, a readable published page
counts as `published` even if an editor has unpublished changes. Draft-only and
archived pages are excluded. Counts, lookup options, and API responses never
reveal a hidden page or its editorial state. Locale fallback follows the
ordinary search rules.

Each metadata picker loads on focus/open and searches on the server. These
same-origin JSON routes return at most 25 authorized options and `hasMore`,
without a total count:

```text
/search/lookups/metadata/labels?lang=en&q=conf&page=1
/search/lookups/metadata/properties?lang=en&q=year&page=1
/search/lookups/metadata/values?lang=en&property_key=year&q=2026&page=1
```

Repeat `workspaces[]` to limit options to the selected visible Workspaces;
`__all__` and `__personal__` work here too. `q` matches part of the label,
property key/display label, or value; `%` and `_` are literal input. **Load more**
requests the next server page. Property values are loaded only after selecting
a property key. Guests see options from public readable pages only. Requests
use the current actor's Workspace and inherited page ACL, and the response uses
`Cache-Control: no-store`. These are web lookup routes, not API-key endpoints;
the protected search API below accepts the same four filtering parameters.

Metadata is read from Workspace's existing tables with SQL predicates; this
feature needs no extra migration or metadata reindex. The derived published
content index and its existing maintenance commands remain unchanged.

## HTTP API

Install and enable `aaieduhr/heartphrame-module-api`, then issue a key containing
`workspace-search:read`. Search owns `WorkspaceSearchApiExtension` and its HTTP
controller; API supplies the shared authentication and response contracts.

```bash
export HPH_API_URL='https://example.test/example-app/api/v1'
export HPH_API_TOKEN='paste-the-one-time-secret-here'

curl --fail-with-body --silent --show-error \
  --header "Authorization: Bearer $HPH_API_TOKEN" \
  --header 'Accept: application/json' \
  "$HPH_API_URL/workspace-search?q=agenda&lang=en&per_page=10"
```

Example payload:

```json
{
  "data": [
    {
      "result_type": "page",
      "workspace_slug": "conference",
      "node_slug": "agenda",
      "title": "Agenda",
      "snippet": "Opening, accessibility, and publishing ...",
      "author_name": "Ana Example",
      "language": "en",
      "url": "/example-app/workspace/conference/agenda"
    }
  ],
  "meta": {"page": 1, "per_page": 10, "total": 1, "pages": 1}
}
```

Plain PHP without an external HTTP library:

```php
<?php

declare(strict_types=1);

$query = http_build_query(['q' => 'agenda', 'lang' => 'en', 'per_page' => 10]);
$url = rtrim((string)getenv('HPH_API_URL'), '/') . '/workspace-search?' . $query;
$context = stream_context_create(['http' => [
    'header' => [
        'Authorization: Bearer ' . (string)getenv('HPH_API_TOKEN'),
        'Accept: application/json',
    ],
    'ignore_errors' => true,
    'follow_location' => 0,
    'timeout' => 15,
]]);
$response = file_get_contents($url, false, $context);
$statusLine = $http_response_header[0] ?? '';
if (!is_string($response) || preg_match('/^HTTP\/\S+ 2[0-9]{2}\b/', $statusLine) !== 1) {
    throw new RuntimeException('The API request failed.');
}
$payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

foreach ($payload['data'] ?? [] as $item) {
    echo $item['title'] . ' -> ' . $item['url'] . PHP_EOL;
}
```

A missing scope returns `403 insufficient_scope`. The key owner may still
receive zero results when their Workspace/page ACL allows no matching Workspace
or page; this is not an error and intentionally reveals no hidden resource.
