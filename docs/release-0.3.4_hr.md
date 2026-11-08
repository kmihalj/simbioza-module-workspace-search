# Workspace Search 0.3.4

Engleska verzija: [release-0.3.4_en.md](release-0.3.4_en.md)

Globalna forma pretrage i postojeći API pretrage sada prihvaćaju oznaku
stranice, ključ strukturiranog svojstva s neobveznom točnom vrijednošću i status
objave. Ti se kriteriji kombiniraju s pojmom, područjem, autorom i datumima
objave. Prazan pojam može pregledavati skup sužen metapodacima; izričit odabir
više područja i dalje zahtijeva pojam pretrage.

Opcije metapodataka učitavaju se pri fokusu ili otvaranju, a pretražuje ih i
straniči poslužitelj: 25 dopuštenih opcija i oznaka nastavka, bez ukupnog broja
ili cijelog kataloga ugrađenog u stranicu. Promjena svojstva poništava ovisnu
vrijednost. Aktivni filtri pojavljuju se iznad rezultata i mogu se ukloniti
pojedinačno ili svi zajedno. Sortiranje i straničenje čuvaju odabire u URL-u.

Indeks i dalje obuhvaća točne objavljene verzije. Filtri nacrta/pregleda
opisuju izmjene već objavljene stranice i zahtijevaju trenutačno pravo
uređivanja, objavljivanja ili upravljanja. Čitatelji iz brojeva i opcija ne
mogu zaključiti neobjavljeno stanje. Privatne vrijednosti, nikada objavljene i
arhivirane stranice ostaju izostavljene. Filtri koriste izvorne Workspace
tablice metapodataka; nema migracije, dodatnog indeksa ni obvezne obnove indeksa.

Regresijski testovi pokrivaju privatne metapodatke, pune točne vrijednosti,
Unicode pretragu opcija, jezični fallback, privatnost uredničkog stanja,
aktualnu vidljivost, doslovne zamjenske znakove i sigurne poveznice filtara.
Fixture s 55 stranica potvrđuje SQL straničenje 25/25/5 opcija, jedan SELECT
metapodataka i najviše 20 ukupnih upita za prvu stranicu.

Pogledajte [web/API parametre i primjere birača](api_hr.md),
[arhitekturu i ACL](index_hr.md) te [provjere](testing_hr.md).
