# Web and HTTP API examples

Croatian version: [api_hr.md](api_hr.md)

## Web search

The Menu extension renders a compact search form in the right-hand navigation
area. It therefore remains beside language/account controls when the header is
disabled and moves into the same responsive drawer on a small screen. The full
page is `/search` and supports:

```text
?q=agenda&lang=en&workspace=conference&author=Ana&from=2026-01-01&to=2026-12-31&page=1&per_page=20
```

The optional `/search/suggest?q=...&lang=...` JSON route returns only a bounded
title/URL/Workspace projection from the same ACL-filtered service. A result can
represent a published page or a Workspace matched by name, description, or slug.
The full page renders a bounded page-number window with previous/next links,
not one control for every page in a large result set.

Plain multi-word `q` values are exact phrases. Use `+word` and
`+"multiple words"` when every listed word and phrase must be present, for
example `+Part +1 +"Part 2"`.

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
]]);
$response = file_get_contents($url, false, $context);
$payload = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

foreach ($payload['data'] ?? [] as $item) {
    echo $item['title'] . ' -> ' . $item['url'] . PHP_EOL;
}
```

A missing scope returns `403 insufficient_scope`. The key owner may still
receive zero results when their Workspace/page ACL allows no matching Workspace
or page; this is not an error and intentionally reveals no hidden resource.
