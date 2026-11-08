# Workspace Search 0.3.4

Croatian version: [release-0.3.4_hr.md](release-0.3.4_hr.md)

The global search form and existing search API now accept a page label, a
structured-property key with an optional exact value, and a publication status.
These criteria combine with the query, Workspace, author and publication dates.
An empty query can browse a metadata-filtered set; explicitly selecting multiple
Workspaces still requires a search term.

Metadata choices load on focus/open and are searched and paged by the server:
25 authorized options plus a has-more flag, without a total count or a complete
catalogue embedded in the page. Changing the property clears its dependent
value. Active filters appear above results and can be removed individually or
cleared together. Sorting and pagination preserve selections in the URL.

The index continues to cover exact published versions. Draft/review filters
describe changes to an already published page and require current edit,
publish or manage permission. Readers cannot infer unpublished state from
counts or options. Private values, draft-only pages and archived pages remain
excluded. Workspace's canonical metadata tables supply the filters directly;
no migration, extra index or mandatory reindex is introduced.

Regression tests cover private metadata, exact long values, Unicode lookup,
locale fallback, editorial-state privacy, current visibility, literal wildcard
input and escaped filter links. A 55-page lookup fixture verifies SQL pagination
of 25/25/5 options, one metadata SELECT and at most 20 total first-page queries.

See [web/API parameters and lookup examples](api_en.md),
[architecture and ACL](index_en.md), and [validation](testing_en.md).
