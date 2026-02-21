# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project summary
RDAS Pricing Updater is a WHMCS addon that syncs domain pricing from rdash.id API into WHMCS with configurable margins, rounding options, and logging.

## Tech stack
- PHP 7.2+ (WHMCS addon)
- MySQL/MariaDB via WHMCS database functions (`select_query`, `full_query`, `insert_query`)
- HTML/CSS/JS for admin UI
- Smarty templates for some views

## Architecture

### Entry Points
- `rdas_pricing_updater.php` - Main addon file with WHMCS hook functions (`_config`, `_activate`, `_deactivate`, `_output`) and AJAX handlers
- `hooks.php` - WHMCS system hooks for cron jobs and admin area integration

### Core Logic
- `lib/functions.php` - All pricing helpers:
  - `rdasParsePrice()` - Converts Indonesian price format ("Rp45.000" → 45000)
  - `rdasFetchDomainPrices()` - API client with cURL
  - `rdasCalculateDomainPrices()` - Applies margin and rounding
  - `rdasUpdateDomainPricing()` - Updates WHMCS database
  - `rdasLogToAddon()` - Logging to custom table

### Admin Pages
Pages are loaded based on `$_REQUEST['page']` parameter. Each page file contains a `show*Page()` function:
- `pages/dashboard.php` - Overview and statistics
- `pages/settings.php` - Configuration interface
- `pages/pricing.php` - Pricing table with sync actions
- `pages/api_test.php` - API connection testing
- `pages/logs.php` - Activity log viewer

### Database Tables (created on activation)
- `mod_rdas_pricing_updater_log` - Activity logs with level, message, data JSON
- `mod_rdas_pricing_cache` - Cached API pricing data by extension

### WHMCS Integration
- Reads/writes `tbldomainpricing` table for domain pricing
- Settings stored in `tbladdonmodules` table
- Uses WHMCS native functions: `select_query()`, `full_query()`, `insert_query()`, `logActivity()`

## API Integration
- Endpoint: `https://api.rdash.id/api/domain-prices?currency=IDR`
- Response: JSON array with domain pricing objects containing `extension`, `registration`, `renewal`, `transfer`, `promo` fields
- Price format: Indonesian notation ("Rp199.000")

## Margin and Rounding
- **Margin types**: `percentage` (multiply) or `fixed` (add)
- **Rounding rules**: `none`, `up_1000`, `up_5000`, `nearest_1000`, `custom`
- Rounding function: `rdasApplyRounding($price, $rule, $customValue)`

## Development guidelines
- Use WHMCS native database functions, not raw SQL when possible
- Wrap operations in try/catch and log errors via `rdasLogToAddon()`
- Escape output with `htmlspecialchars()` in admin UI
- Keep settings keys stable for backward compatibility
- When adding new settings, add defaults to `rdas_pricing_updater_config()` and handle migrations in `_activate()`

## Testing
- No automated test framework
- Manual test scripts in root: `test_*.php` (require WHMCS environment)
- Use `check_error_logs.php` and `fix_permissions.php` for debugging in production

## File structure
```
rdas_pricing_updater/
├── rdas_pricing_updater.php   # Main addon (WHMCS hooks + AJAX handlers)
├── hooks.php                  # System hooks (DailyCronJob, AdminAreaPage)
├── lib/functions.php          # Core pricing logic and helpers
├── pages/                     # Admin UI pages
├── assets/css/style.css       # Admin UI styles
├── assets/js/script.js        # Admin UI JavaScript
├── templates/                 # Smarty templates
└── docs/                      # Development guidelines
```
