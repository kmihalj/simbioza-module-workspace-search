# Integracija backupa

Indeks pretrage izvedeni je podatak i namjerno se ne nalazi ni u jednoj arhivi. Kopiranje rezultata tokenizacije specifičnog za bazu povećalo bi arhivu, moglo otkriti zastarjelu ACL vidljivost i smanjilo bi prijenosnost između SQLitea, PostgreSQL-a i MySQL-a.

Workspace Search zato daje završne providere umjesto skupova podataka:

- `workspace-search-site` ponovno gradi cijeli indeks nakon site/component povrata;
- `workspace-search-workspace` ponovno indeksira samo vraćeno područje nakon selektivnog povrata/kopiranja.

Finalizeri se izvode nakon importa svih izvornih zapisa, ali prije zajedničkog DB commita. Pogreška finalizera zato rollbacka restore i prijavljuje se operatoru. Administrator može indeks izgraditi i izričito:

```bash
vendor/bin/hph workspace-search:rebuild
```

Nakon povrata uvijek testirajte jedan gostujući i jedan prijavljeni upit; ponovno izgrađeni indeks i dalje pri svakom upitu primjenjuje aktualni Workspace/page ACL.
