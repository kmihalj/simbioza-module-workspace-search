# Testing on SQLite, PostgreSQL, and MySQL

Croatian version: [testing_hr.md](testing_hr.md)

## Local module suite

```bash
composer on-commit
```

The unit/integration suite checks migration portability, exact published-version
indexing, locale fallback, guest/public ACL, authenticated inherited ACL,
pagination, snippets, and menu registration.

## Real application matrix

From HFClean run the isolated suite, which creates disposable databases and
does not modify application content:

```bash
composer e2e:sqlite
composer e2e:pgsql
composer e2e:mysql
```

The database user must be allowed to create/drop the isolated database named by
the runner. Production application users should normally not have that right;
use an administrative credential only through the local test environment and
never write it to source, docs, or logs.

After restoring or bulk-loading an isolated fixture, rebuild the index and run
both guest and authenticated checks:

```bash
vendor/bin/hph workspace-search:rebuild
```

The essential assertion is not merely that a public title is found. A unique
term placed only in a restricted page must return zero results, zero total, and
no suggestion for a guest and for an unauthorized API-key owner.
