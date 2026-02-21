# AGENTS.md

Project: RDAS Pricing Updater (WHMCS addon)

## Purpose
Sync domain pricing from rdash.id API into WHMCS with configurable margins, rounding, and logging.

## Repo map
- `rdas_pricing_updater.php` main addon entry
- `hooks.php` WHMCS hooks
- `lib/functions.php` helper and pricing logic
- `pages/` admin pages (dashboard, settings, pricing, api_test, logs)
- `assets/` CSS/JS for admin UI
- `templates/` UI templates
- `docs/` additional documentation

## Quick start
- This is a WHMCS addon; it runs inside WHMCS admin.
- PHP 7.2+ expected; no composer setup in this repo.

## Key behaviors
- Fetch prices from `https://api.rdash.id/api/domain-prices?currency=IDR`.
- Apply margin (percentage or fixed), then rounding rules.
- Update WHMCS `tbldomainpricing` for matching TLDs/registrars.
- Provide logging and admin UI for settings, pricing table, and API test.

## Conventions
- Keep PHP compatible with PHP 7.2+.
- Avoid breaking WHMCS hooks or admin UI paths.
- Prefer small, focused changes with clear error handling.
- UI changes should be reflected in `assets/` and related `pages/` or `templates/`.

## Tests / scripts
- No formal test runner.
- Manual helpers exist in root: `test_*.php` (run from a WHMCS context if needed).

## Safe changes
- Avoid schema changes unless required; when needed, include migration logic.
- Preserve existing settings keys and defaults.
- Handle API failures gracefully and log errors.

## Docs
- `README.md` for overview and usage.
- `docs/guidelines.md` for UI/feature details.
