# Primjeri web i HTTP API pretrage

Engleska verzija: [api_en.md](api_en.md)

## Web pretraga

Menu ekstenzija renderira kompaktnu tražilicu u desnom dijelu navigacije. Zato
ostaje uz kontrole jezika i računa kada je zaglavlje isključeno te na malom
ekranu ulazi u isti responzivni bočni panel. Puna stranica je `/search` i
podržava:

```text
?q=raspored&lang=hr&workspaces[]=konferencija&workspaces[]=upute&author=Ana&from=2026-01-01&to=2026-12-31&page=1&per_page=20
```

Opcionalna JSON ruta `/search/suggest?q=...&lang=...` vraća samo ograničeni
prikaz naslova, URL-a i područja iz istog ACL-filtriranog servisa. Rezultat može
biti objavljena stranica ili samo područje pronađeno po nazivu, opisu ili slugu.
Puna stranica prikazuje ograničen prozor brojeva stranica s prethodnom i
sljedećom poveznicom, ne jedan element za svaku stranicu velikog rezultata.

Obična višerječna vrijednost parametra `q` točna je fraza. Upotrijebite
`+riječ` i `+"više riječi"` kada svaka navedena riječ i fraza mora biti
prisutna, primjerice `+Dio +1 +"Dio 2"`.

Parametar `workspaces[]` može se ponoviti za više područja. Posebne vrijednosti
`__all__` i `__personal__` znače sva vidljiva odnosno sva korisniku vidljiva
osobna područja. Stari jednokratni parametar `workspace` ostaje podržan.

Prazna pretraga može pregledavati jedno odabrano područje ili skup sužen
autorom, datumom, oznakom, svojstvom ili statusom objave. Više odabranih područja traži pojam pretrage. Pojam
kraći od `minimum_query_length` (zadano 2 znaka) ne izvršava se. Datumi i
redoslijed primjenjuju se na isti skup rezultata filtriran ovlastima.

### Oznake, strukturirana svojstva i status objave

Puna forma pretrage omogućuje odabir postojećih metapodataka; ne stvara niti
mijenja metapodatke stranica. Isti parametri vrijede za `/search` i HTTP API:

| Parametar | Značenje |
| --- | --- |
| `label` | Jedna točna postojeća oznaka stranice. |
| `property_key` | Ključ postojećeg strukturiranog svojstva; bez vrijednosti pronalaze se sve stranice koje imaju to svojstvo. |
| `property_value` | Točna spremljena vrijednost odabranog `property_key`; vrijednost bez ključa ne vraća rezultate. |
| `workflow` | `published`, `draft` ili `in_review`. Prazna vrijednost prihvaća sve dostupne statuse. |

Primjer `/search?lang=hr&label=konferencija&property_key=godina&property_value=2026`
prikazuje objavljene stranice s odabranom oznakom i točnom vrijednošću svojstva.
Svaki odabrani kriterij dodatno sužava skup (AND). Kod filtriranja metapodataka
stranice ne prikazuju se samostalne kartice područja. Odabir ostaje u URL-u i
pri sortiranju ili promjeni stranice rezultata. Iznad rezultata pojedini filtar
uklonite njegovom oznakom ili odaberite **Očisti filtre**; čišćenje zadržava
traženi pojam i jezik. Uklanjanje ključa svojstva uklanja i ovisnu vrijednost.

Pretražuju se samo objavljene verzije. `draft` i `in_review` označavaju
neobjavljene izmjene već objavljene stranice, dostupne samo korisniku koji smije
uređivati, objavljivati ili upravljati tom stranicom. Čitatelju se čitljiva
objavljena stranica računa kao `published` i kada urednik ima neobjavljene izmjene.
Stranice koje nikada nisu objavljene i arhivirane stranice izostavljene su.
Brojevi rezultata, opcije birača i API odgovori ne otkrivaju skrivenu stranicu
ni njezino uredničko stanje. Jezični fallback slijedi obična pravila pretrage.

Birač učitava opcije tek pri fokusu ili otvaranju, a pretražuje ih poslužitelj.
Ove JSON rute istog izvora vraćaju najviše 25 dopuštenih opcija i `hasMore`,
bez ukupnog broja:

```text
/search/lookups/metadata/labels?lang=hr&q=konf&page=1
/search/lookups/metadata/properties?lang=hr&q=godina&page=1
/search/lookups/metadata/values?lang=hr&property_key=godina&q=2026&page=1
```

Ponovite `workspaces[]` za ograničenje opcija na odabrana vidljiva područja;
ovdje vrijede i `__all__` te `__personal__`. `q` traži dio oznake,
ključa/prikaznog naziva svojstva ili vrijednosti; `%` i `_` doslovni su znakovi.
**Učitaj još** dohvaća sljedeću stranicu s poslužitelja. Vrijednosti svojstava
dohvaćaju se tek nakon odabira ključa svojstva. Gost vidi samo opcije javnih
čitljivih stranica. Zahtjev koristi aktualne ovlasti područja i naslijeđene
ovlasti stranica, a odgovor ima `Cache-Control: no-store`. To su web rute birača,
ne rute za API ključeve; zaštićena API pretraga u nastavku prihvaća ista četiri
parametra filtara.

Metapodaci se čitaju iz postojećih Workspace tablica SQL uvjetima. Dodatna
migracija ni obnova indeksa metapodataka nisu potrebne. Izvedeni indeks
objavljenog sadržaja i njegove postojeće naredbe održavanja ostaju isti.

## HTTP API

Instalirajte i uključite `aaieduhr/heartphrame-module-api`, pa izdajte ključ
koji sadrži `workspace-search:read`. Search posjeduje
`WorkspaceSearchApiExtension` i svoj HTTP kontroler, dok API daje zajedničku
autentikaciju i ugovore odgovora.

```bash
export HPH_API_URL='https://example.test/example-app/api/v1'
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
      "result_type": "page",
      "workspace_slug": "konferencija",
      "node_slug": "raspored",
      "title": "Raspored",
      "snippet": "Otvaranje, pristupačnost i objavljivanje ...",
      "author_name": "Ana Primjer",
      "language": "hr",
      "url": "/example-app/workspace/konferencija/raspored"
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
    'follow_location' => 0,
    'timeout' => 15,
]]);
$response = file_get_contents($url, false, $context);
$statusLine = $http_response_header[0] ?? '';
if (!is_string($response) || preg_match('/^HTTP\/\S+ 2[0-9]{2}\b/', $statusLine) !== 1) {
    throw new RuntimeException('API zahtjev nije uspio.');
}
$payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

foreach ($payload['data'] ?? [] as $item) {
    echo $item['title'] . ' -> ' . $item['url'] . PHP_EOL;
}
```

Nedostajući scope vraća `403 insufficient_scope`. Vlasnik ključa može dobiti
nula rezultata kada mu Workspace/page ACL ne dopušta nijedno podudarno područje
ni stranicu; to nije greška i namjerno ne otkriva skriveni resurs.
