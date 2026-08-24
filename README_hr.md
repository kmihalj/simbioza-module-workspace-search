# HeartPhrame Workspace Search modul

[English version](README.md)

Workspace Search daje globalnu pretragu objavljenih Workspace stranica. To
namjerno nije generički paket za pretragu cijelog sitea: svaki rezultat ovisi o
Workspace stablu, naslijeđenom page ACL-u, Editorovu tijeku objave i Menu
integraciji.

## Ovisnosti

Obavezno, redoslijedom uključivanja:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-menu` (`dev-main`)
4. `aaieduhr/heartphrame-module-auth` (`dev-main`)
5. `aaieduhr/heartphrame-module-editor-html` (`dev-main`)
6. `aaieduhr/heartphrame-module-workspace` (`dev-main`)
7. `aaieduhr/heartphrame-module-workspace-search` (`dev-main`)

Opcionalna integracija:

- `aaieduhr/heartphrame-module-api` izlaže istu ACL pretragu preko
  `GET /api/v1/workspace-search`.

Modul nije upotrebljiv bez Workspacea, Editora i Menua, pa su oni obavezne
ovisnosti, a ne opcionalni prijedlozi.

## Instalacija

```bash
composer require aaieduhr/heartphrame-module-workspace-search:dev-main
vendor/bin/hph workspace-search:install-migration
vendor/bin/hph orm-migrate:up
vendor/bin/hph workspace-search:rebuild
```

Paket uključite nakon svih obaveznih modula. Pretraga se tada pojavljuje desno
u gornjem meniju, a puna stranica dostupna je na `/search` ispod konfigurirane
bazne putanje aplikacije.

## Što se pretražuje

- naslov objavljene stranice;
- sanitizirani obični tekst točne objavljene Editor verzije;
- autor objave;
- traženi jezik uz fallback na zadani jezik sitea;
- opcionalni filtri područja i datuma objave.

Nacrti, arhivirani workflowi, obrisane stranice i nedostupni potomci nikada se
ne vraćaju. Gost dobiva samo javne stranice. Prijavljeni web korisnik i vlasnik
API ključa dobivaju samo sadržaj dopušten efektivnim Workspace i naslijeđenim
page ACL-om. Filtriranje se događa prije brojanja, isječaka, straničenja i
prijedloga, pa metapodaci ograničene stranice ne cure.

HTML Editor na Workspace stranici može umetnuti pretragu trenutačnog područja.
Ona dinamično koristi isti ACL-filtrirani endpoint prijedloga, ali serverski
ograničava rezultate na slug tog područja. Ne ovisi o tekstu ili skrivenom
polju koje bi posjetitelj mogao izmijeniti radi pristupa drugom sadržaju.

## Rad s indeksom

Tablica u bazi izvedeni je indeks koji se može ponovno izgraditi. Sprema
objavljeni tekst i stabilne identifikatore, ali ne sprema ACL retke. Redak je
jedinstven po stranici i jeziku. Objavljivanje, arhiviranje, povratak u
neobjavljeni nacrt, brisanje te promjena metapodataka područja/stranice šalju
sinkroni Workspace događaj, pa Search ponovno indeksira samo zahvaćeno područje
(i jezik kada je poznat). Spremanje nacrta ne zamjenjuje trenutačno indeksiranu
objavljenu verziju.

Obična pretraga dodatno radi ograničeno osvježavanje prema
`automatic_index_refresh_seconds` kao sigurnosni sloj. Nakon masovnog uvoza,
vraćanja backupa, deploya ili sumnje u poremećen indeks administrator u
**Postavke → Područja → Indeks pretrage** može obnoviti cijeli site ili jedno
područje. Ekvivalentne CLI naredbe su:

Trajno brisanje područja odmah uklanja njegove izvedene retke indeksa. Indeks
nije poslovni podatak pa se ponovno gradi umjesto spremanja u backup.

```bash
vendor/bin/hph workspace-search:rebuild
vendor/bin/hph workspace-search rebuild --workspace=42
```

Aplikacijski override pripada u `config/workspace-search.php`:

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

Postavite interval na `0` kada scheduler ili deployment pipeline uvijek
obnavlja indeks.

## Dokumentacija

- [Arhitektura, ACL i indeksiranje](docs/index_hr.md)
- [Primjeri web i HTTP API-ja](docs/api_hr.md)
- [Testiranje na tri baze](docs/testing_hr.md)
- [Integracija backupa](docs/backup_hr.md)

Framework i svi interni moduli slijede pomičnu politiku `dev-main`. U ovom
paketu nemojte zaključati jedan interni modul na stariji commit.
