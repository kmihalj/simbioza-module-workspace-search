# Testiranje na SQLiteu, PostgreSQL-u i MySQL-u

Engleska verzija: [testing_en.md](testing_en.md)

## Lokalni skup modula

```bash
composer on-commit
```

Unit/integracijski testovi provjeravaju prijenosnu migraciju, indeksiranje točne
objavljene verzije, jezični fallback, javni ACL za gosta, naslijeđeni ACL
prijavljenog korisnika, straničenje, isječke i registraciju menija.

Regresije metapodataka koriste stvarne SQLite Workspace/Auth tablice i izvorni
ACL: privatne oznake, ključevi i vrijednosti svojstava ne pojavljuju se u
rezultatima i biračima gosta, a uredničko stanje nacrta/pregleda ne mijenja
brojeve čitatelja. Testovi pokrivaju AND kombinacije, pune točne vrijednosti,
doslovne zamjenske znakove, fallback i trenutačne promjene objave/ovlasti.
Za 55 različitih oznaka birač vraća 25/25/5 opcija kroz tri stranice. Promatrač
upita potvrđuje jedan ograničen SELECT metapodataka (`LIMIT 26`) i najviše 20
SQL upita za prvu stranicu, bez upita metapodataka po svakom čvoru. Testovi
stvarno iscrtanih predložaka provjeravaju uklanjanje filtara, čuvanje URL-a i
jezika, escaping i nazive ovisnih kontrola. Te provjere ulaze u
`composer on-commit`; ne trebaju preglednik ni aplikacijske podatke.

## Stvarna aplikacijska matrica

Iz HFCleana pokrenite izolirani skup koji kreira privremene baze i ne mijenja
aplikacijski sadržaj:

```bash
composer e2e:sqlite
composer e2e:pgsql
composer e2e:mysql
```

Korisnik baze mora smjeti kreirati i obrisati izoliranu bazu koju navodi
runner. Produkcijski aplikacijski korisnik u pravilu ne treba imati to pravo;
administratorsku vjerodajnicu koristite samo kroz lokalno testno okruženje i
nikada je ne zapisujte u izvorni kod, dokumentaciju ni log.

Nakon vraćanja ili masovnog učitavanja izoliranih podataka obnovite indeks i
pokrenite provjere gosta i prijavljenog korisnika:

```bash
vendor/bin/hph workspace-search:rebuild
```

Ključna provjera nije samo pronalazak javnog naslova. Jedinstveni pojam koji
postoji samo na ograničenoj stranici gostu i neovlaštenom vlasniku API ključa
mora dati nula rezultata, ukupan broj nula i nijedan prijedlog.
