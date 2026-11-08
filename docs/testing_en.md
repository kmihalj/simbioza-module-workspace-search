# Testing on SQLite, PostgreSQL, and MySQL

Croatian version: [testing_hr.md](testing_hr.md)

## Local module suite

```bash
composer on-commit
```

The unit/integration suite checks migration portability, exact published-version
indexing, locale fallback, guest/public ACL, authenticated inherited ACL,
pagination, snippets, and menu registration.

Metadata regressions use real SQLite Workspace/Auth tables and canonical ACL:
private-only labels, property keys and values are absent from guest results and
lookups; editorial draft/review state cannot change reader counts. Tests cover
AND combinations, full-length exact values, literal wildcard input, fallback,
and live publication/ACL changes. With 55 distinct labels the lookup returns
25/25/5 options across three pages. A query listener asserts one bounded
metadata SELECT (`LIMIT 26`) and at most 20 SQL queries for the first page,
preventing a per-node metadata query. Rendered-view tests verify filter removal,
URL/locale persistence, escaping, and named dependent controls. These checks run
inside `composer on-commit`; they do not require a browser or application data.

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
