# Agent instructions — SaaS Lab

## cPanel deployment maintenance

Namecheap cPanel deploys this repository through the root `.cpanel.yml` file into:

- Web document root: `/home/iainmcok/public_html/` (contents of `public/` only)
- Private application root: `/home/iainmcok/saas-lab/`

Treat deployment compatibility as part of the definition of done for every change.

Before completing any coding task:

1. Review whether files, folders, entry points, build outputs, dependencies, or runtime storage changed.
2. Re-open `.cpanel.yml`.
3. Update it when deployment requirements changed.
4. Leave it unchanged when no deployment change is required.
5. Validate every path referenced by `.cpanel.yml`.
6. Mention in the final summary either:
   - `.cpanel.yml updated`, with the reason, or
   - `.cpanel.yml reviewed; no update required`.

Update `.cpanel.yml` only when production deployment inputs or process actually change, including:

- deployment source paths or destination paths
- build requirements
- Composer/npm or other dependencies
- web entry points
- persistent runtime storage (`data/`, `projects/`, `storage/`, config overrides)

Do not modify `.cpanel.yml` mechanically after every edit.
