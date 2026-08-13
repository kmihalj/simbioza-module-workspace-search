# Primjeri web i HTTP API pretrage

English version: [api_en.md](api_en.md)

## Web pretraga

Menu ekstenzija renderira kompaktnu tražilicu u desnom dijelu navigacije. Zato
ostaje uz kontrole jezika i računa kada je zaglavlje isključeno te na malom
ekranu ulazi u isti responzivni bočni panel. Puna stranica je `/search` i
podržava:

```text
?q=raspored&lang=hr&workspace=konferencija&author=Ana&from=2026-01-01&to=2026-12-31&page=1&per_page=20
```

Opcionalna JSON ruta `/search/suggest?q=...&lang=...` vraća samo ograničeni
prikaz naslova, URL-a i područja iz istog ACL-filtriranog servisa.

## HTTP API

Instalirajte i uključite `aaieduhr/heartphrame-module-api`, pa izdajte ključ
koji sadrži `workspace-search:read`.

```bash
export HPH_API_URL='https://example.test/hfc/api/v1'
export HPH_API_TOKEN='ovdje-zalijepite-jednokratno-prikazanu-tajnu'

curl --fail-with-body --silent --show-error \
  --header "Authorization: Bearer $HPH_API_TOKEN" \
  --header 'Accept: application/json' \
  "$HPH_API_URL/workspace-search?q=raspored&lang=hr&per_page=10"
```

Primjer payloada:

```json
{
  "data": [
    {
      "workspace_slug": "konferencija",
      "node_slug": "raspored",
      "title": "Raspored",
      "snippet": "Otvaranje, pristupačnost i objavljivanje ...",
      "author_name": "Ana Primjer",
      "language": "hr",
      "url": "/hfc/workspace/konferencija/raspored"
    }
  ],
  "meta": {"page": 1, "per_page": 10, "total": 1, "pages": 1}
}
```

Obični PHP bez vanjske HTTP biblioteke:

```php
<?php

declare(strict_types=1);

$query = http_build_query(['q' => 'raspored', 'lang' => 'hr', 'per_page' => 10]);
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

Nedostajući scope vraća `403 insufficient_scope`. Vlasnik ključa može dobiti
nula rezultata kada mu Workspace/page ACL ne dopušta nijednu stranicu; to nije
greška i namjerno ne otkriva skriveni resurs.
