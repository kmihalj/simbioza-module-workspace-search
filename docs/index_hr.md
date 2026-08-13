# Arhitektura, ACL i indeksiranje

English version: [index_en.md](index_en.md)

## Granica modula

Workspace Search posjeduje samo pronalaženje i svoj izvedeni indeks. Workspace
ostaje izvor istine za stablo, pokazivače objave i ACL. Editor ostaje izvor
istine za nepromjenjive verzije stranice. Auth daje oznake autora, Menu
ekstenzijsku točku, a ORM prijenosnu pohranu.

Indekser koristi `EditorPublishedVersionProviderInterface`, uski ugovor za
točno objavljene verzije. Namjerno ne koristi CLI sesiju jer obnova nema
prijavljenog korisnika, ali ne može odabrati proizvoljni nacrt ili verziju.
Servis pretrage zasebno primjenjuje stvarni ACL korisnika prije izlaza rezultata
iz modula.

## Redoslijed sigurnosnih provjera

Za svaki web ili API zahtjev servis:

1. izlista područja vidljiva izričitom korisniku, odnosno javna područja gostu;
2. izgradi vidljivo stablo na traženom i fallback jeziku;
3. primijeni naslijeđena ograničenja čvora i ukloni nedostupne potomke;
4. ograniči SQL kandidate na dobivene ID-eve čvorova;
5. primijeni tekstualni i opcionalne filtre područja, autora i datuma;
6. odabere traženi locale ili točno objavljeni fallback zadanog jezika sitea;
7. tek tada izračuna broj, isječke, isticanje i straničenje.

Indeks namjerno ne sprema odluku o ovlaštenju jer se korisnici, grupe i
naslijeđena ograničenja mogu promijeniti bez ponovnog indeksiranja teksta.

## Ponašanje jezika

Pretraga daje prednost parametru `lang`. Ako čvor nema točno objavljenu
stranicu na tom jeziku, može koristiti konfigurirani zadani Workspace/site
locale. Nikada ne koristi nacrt. Isti čvor pojavljuje se jednom, a ne po jednom
za svaki jezik. Hrvatski je sigurni aplikacijski fallback kada valjani locale
nije konfiguriran.

## Operativno ponašanje

Redak indeksa jedinstven je po `(node_id, language_code)`. Workspace nakon
promjene objavljenog sadržaja, metapodataka stranice/stabla ili dostupnosti
područja šalje neutralni događaj `WorkspaceContentChanged`. Search ga sluša bez
obrnute Workspace→Search ovisnosti i sinkronizira samo to područje; poznati jezik
objave dodatno sužava posao. Spremanje nacrta zadržava zadnji objavljeni redak
indeksa do izričite objave.

Obična HTTP pretraga i dalje provjerava najnoviji `indexed_at` u bazi i oznaku
PHP procesa kao sigurnosni sloj. Administrator može obnoviti cijeli site ili
jedno područje u **Postavke → Područja → Indeks pretrage**. CLI i deployment
automatizacija mogu koristiti:

```bash
vendor/bin/hph workspace-search:rebuild
vendor/bin/hph workspace-search rebuild --workspace=42
```

Izvedena tablica uvijek se može obrisati i rekonstruirati iz Workspace i Editor
podataka.

Pretraga koristi obične ORM query-builder operacije koje podržavaju SQLite,
PostgreSQL i MySQL/MariaDB. Nijedan proizvođač baze ne posjeduje funkcionalnost.

Indeks se namjerno ponovno gradi umjesto arhiviranja. Vidi
[integraciju backupa](backup_hr.md).
