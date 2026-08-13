# Backup integration

The search index is derived data and is deliberately absent from every archive. Copying database-specific tokenization output would enlarge the archive, leak stale ACL visibility, and reduce portability between SQLite, PostgreSQL, and MySQL.

Workspace Search therefore contributes finalizer providers rather than datasets:

- `workspace-search-site` rebuilds the complete index after site/component restore;
- `workspace-search-workspace` reindexes only the restored workspace after selective restore/copy.

Finalizers run after every source record has been imported but before the shared database commit. A finalizer failure therefore rolls back the restore and is reported to the operator. Administrators can also rebuild explicitly:

```bash
vendor/bin/hph workspace-search:rebuild
```

Always test one guest and one authenticated query after restore; the rebuilt index still applies current Workspace/page ACL at query time.
