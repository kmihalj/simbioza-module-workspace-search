# Simbioza Workspace Search modul

[English version](README.md)

Workspace Search daje globalnu pretragu objavljenih Workspace stranica. To
namjerno nije generički paket za pretragu cijelog sitea: svaki rezultat ovisi o
Workspace stablu, naslijeđenom page ACL-u, Editorovu tijeku objave i Menu
integraciji.

## Ovisnosti

Obavezno, redoslijedom uključivanja:

1. `aaieduhr/heartphrame-framework` (`^0.0.25`)
2. `aaieduhr/heartphrame-module-orm` (`^0.1.0`)
3. `aaieduhr/heartphrame-module-menu` (`^0.1.0`)
4. `aaieduhr/heartphrame-module-auth` (`^0.1.0`)
5. `aaieduhr/heartphrame-module-editor-html` (`^0.1.0`)
6. `aaieduhr/simbioza-module-workspace` (`^0.1.0`)
7. `aaieduhr/simbioza-module-workspace-search` (`^0.1.0`)

Opcionalna integracija:

- `aaieduhr/heartphrame-module-api` izlaže istu ACL pretragu preko
  `GET /api/v1/workspace-search`.

Modul nije upotrebljiv bez Workspacea, Editora i Menua, pa su oni obavezne
ovisnosti, a ne opcionalni prijedlozi.

## Instalacija

```bash
composer require aaieduhr/simbioza-module-workspace-search:^0.1.3
vendor/bin/hph workspace-search:install-migration
vendor/bin/hph orm-migrate:up
vendor/bin/hph workspace-search:rebuild
```

Paket uključite nakon svih obaveznih modula. Pretraga se tada pojavljuje desno
u gornjem meniju, a puna stranica dostupna je na `/search` ispod konfigurirane
bazne putanje aplikacije.

## Što se pretražuje

- naziv, opis i slug vidljivog područja, uključujući lokalizirano ime vlasnika
  osobnog područja kada ga isporuči prezentacijski provider;
- naslov objavljene stranice;
- sanitizirani obični tekst točne objavljene Editor verzije;
- autor objave;
- traženi jezik uz fallback na zadani jezik sitea;
- opcionalni filtri područja i datuma objave.

Puna forma pretrage prikazuje obična područja koja su vidljiva trenutačnom
posjetitelju. Sva vidljiva osobna područja objedinjena su u jednu mogućnost
**Osobna područja**, umjesto zasebne stavke za svakog korisnika. Popis je
dostupan i prije unosa upita, a skupni filtar i dalje primjenjuje uobičajene
ACL provjere područja i stranica.

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

Framework koristi `^0.0.25`, a svi interni moduli kompatibilnu liniju izdanja
`^0.1.0`. U ovom paketu nemojte vezati jedan interni modul uz stariji commit.
