# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.9] - 2024-12-20

### Changed
- Replaced AJAX import functionality with standard form POST submission
- Simplified JavaScript to only handle "Select All" functionality
- Removed external JavaScript file dependency for import process

### Removed
- Deleted `assets/js/domain-pricing.js` file (no longer needed)
- Removed complex AJAX handling for domain import

### Fixed
- Eliminated JavaScript path issues by using minimal inline JavaScript
- Improved reliability by using standard form submission instead of AJAX

## [2.1.8] - 2024-12-20

### Fixed
- Fixed 404 error for JavaScript file by implementing dynamic URL generation for WHMCS admin context
- Corrected path construction using $_SERVER variables for proper module asset loading

## [2.1.7] - 2024-12-20

### Added
- Added `rdasGetExistingTLDs()` function to filter TLDs based on `tbldomainpricing` table
- Created external JavaScript file `assets/js/domain-pricing.js` for better code organization
- Added "Select All" checkbox functionality in table header

### Changed
- Modified domain pricing table to show only TLDs that exist in `tbldomainpricing` table
- Moved all JavaScript functions to external file to avoid echo issues
- Improved user interface with Select All functionality

### Fixed
- Resolved JavaScript echo issues by externalizing scripts
- Fixed undefined function error for `rdasGetExistingTLDs`

## [2.1.6] - 2025-01-20

### Fixed
- Root cause analysis: Field 'terms' ada di dalam objek 'promo' bukan di level utama domain
- Kolom Reg Period sekarang mengakses $domain['promo']['terms'] dengan benar
- Update dokumentasi bug untuk mencerminkan struktur API yang akurat

## [2.1.5] - 2025-01-20

### Fixed
- Fixed 'Uncaught ReferenceError: importSelectedDomains is not defined' dengan memindahkan definisi fungsi sebelum button
- Menghapus duplikasi kode JavaScript untuk optimasi

## [2.1.4] - 2024-01-20

### Fixed
- Fixed 404 error pada AJAX request dengan mengubah path ke 'addonmodules.php?module=rdas_pricing_updater'
- Fixed undefined function 'importSelectedDomains' di JavaScript
- Implementasi integrasi dengan WHMCS TLD Sync API untuk import domain
- Perbaikan kolom Reg Period: API tidak menyediakan field 'terms', menampilkan '1 Year' sebagai default
- Perbaikan AJAX handler untuk menangani data domains yang dikirim sebagai JSON
- Penambahan fungsi helper rdasGetWHMCSAdminToken dan rdasGetWHMCSBaseURL
- Implementasi rdasImportDomainViaTLDSync untuk menggunakan WHMCS TLD Sync API

## [2.1.3] - 2024-12-19

### Added
- Import functionality with "Import Selected Domains" button
- Domain validation to ensure domains exist in WHMCS configuration before import
- AJAX handler for seamless domain import process with real-time feedback
- Status display area for import operations
- Domain existence check function (rdasDomainExistsInWHMCS)
- Domain pricing update function with margin and rounding rules
- Comprehensive error handling for import operations

### Enhanced
- Import process validates domains against admin/configdomains.php
- Real-time status updates during import process
- Proper error reporting for failed imports

## [2.1.2] - 2024-01-20

### Fixed
- Price parsing issue where API string prices ("Rp45.000") were showing as zero
- Layout updated to match new design requirements with checkbox selection
- Added `rdasParsePrice()` function to properly convert Indonesian price format

### Added
- New table layout with TLD, Existing TLD, Reg Period, Promo Base, and Margin columns
- Checkbox selection functionality with "Select All" feature
- JavaScript for bulk TLD selection
- Price parsing support for Indonesian currency format (Rp45.000 -> 45000)

### Enhanced
- Table now displays promo pricing alongside base pricing
- Visual indicators for existing TLDs in WHMCS
- Improved user interface matching design specifications

## [2.1.1] - 2024-12-19

### Added
- Margin calculation integration from configuration page to pricing display
- Base and Final price columns showing before/after margin application
- Real-time margin and rounding information display in pricing table header
- Support for percentage and fixed margin types from addon configuration
- Automatic rounding rule application (none, up_1000, up_5000, nearest_1000, custom)

### Changed
- Enhanced `rdas_pricing_updater_output()` to retrieve and apply addon configuration
- Updated table structure to show both base prices and final prices with margin
- Improved pricing display with margin information in panel header

## [2.1.0] - 2024-12-19

### Changed
- **MAJOR**: Simplified rdas_pricing_updater_output() function to display single pricing table page
- Removed complex page routing system (pricing, settings, api_test, logs pages)
- Direct API integration with api.rdash.id/api/domain-prices?currency=IDR
- Updated table structure to match actual API response format
- Displays: TLD, Type, Registration, Renewal, Transfer, Promo Registration, Description
- Improved error handling for API calls
- Removed dependency on complex dashboard system

### Fixed
- Resolved dashboard loading issues by simplifying architecture
- Fixed API response parsing to match actual JSON structure
- Improved currency handling with IDR default

## [2.0.9] - 2025-08-18

### Fixed
- **Bug #018** - **CRITICAL** ValueError: class_alias() with stdClass causing fatal error in production
- Removed problematic class_alias code that prevented dashboard from loading
- Dashboard now loads properly in WHMCS admin panel

## [2.0.8] - 2025-01-18

### Added
- Added dashboard_simple.php as fallback dashboard for error scenarios
- Added whmcs_compatibility.php for testing environment outside WHMCS
- Added test_dashboard.php for comprehensive dashboard function testing
- Added proper error handling and null checks throughout dashboard functions

### Fixed
- Fixed missing dashboard_simple.php fallback file (Bug #006)
- Fixed incorrect parameters in rdasGetAddonLogs function call (Bug #007)
- Fixed undefined function 'full_query' by replacing with proper WHMCS database functions (Bug #008)
- Fixed deprecated mysql_query and mysql_real_escape_string functions (Bug #009)
- Fixed undefined function 'delete_query' by using full_query with raw SQL (Bug #010)
- Fixed incorrect database field names in getDashboardStatistics (Bug #011)
- Fixed deprecated mysql_num_rows() function in getSystemHealth (Bug #012)
- Fixed undefined function 'select_query' when testing outside WHMCS (Bug #013)
- Fixed infinite loop in mysql_fetch_array mock function (Bug #014)
- Fixed undefined array keys in dashboard template (Bug #015)
- Fixed array offset access on false values (Bug #016)
- Fixed null parameter passed to number_format() (Bug #017)
- **CRITICAL** Fixed ValueError: class_alias() with stdClass causing fatal error in production (Bug #018)

### Changed
- Updated version to 2.0.8
- Improved database compatibility by replacing deprecated MySQL functions
- Enhanced error handling and null safety throughout dashboard code
- Updated dashboard template to use correct database field names
- Improved testing capabilities with mock WHMCS environment

## [2.0.7] - 2025-01-27

### Added
- Added fix_permissions.php script to resolve "Can't change Addon Module Access Permissions" issue
- Added comprehensive troubleshooting guide for permissions issues in TROUBLESHOOTING.md
- Added functions to check and fix missing 'access' entry in tbladdonmodules database

### Fixed
- Fixed "Can't change Addon Module Access Permissions" issue caused by missing 'access' entry in database
- Added automatic detection and repair of missing access control settings
- Improved addon activation process to ensure proper permissions setup

### Changed
- Updated version to 2.0.7
- Enhanced addon permissions management and troubleshooting capabilities

## [2.0.6] - 2025-01-27

### Fixed
- Fixed configuration save issues on `configaddonmods.php` page
- Added missing functions: `rdasGetAddonConfig()`, `rdasFetchDomainPrices()`, `rdasCalculateDomainPrices()`, `rdasUpdateDomainPricing()`, `rdasLogToAddon()`
- Fixed inconsistent function calls by adding 'rdas' prefix to all custom functions
- Resolved undefined function errors that prevented addon configuration from being saved
- Updated all AJAX functions to use consistent function naming
- Improved database operations with proper WHMCS native functions
- Fixed 'Cannot redeclare rdasFetchDomainPrices()' error by removing duplicate functions from main file
- Added proper require_once for lib/functions.php to avoid function redeclaration
- Fixed 'Call to undefined function getAddonConfig()' error by adding rdasGetAddonConfig() function in lib/functions.php
- Fixed 'Cannot redeclare rdasGetAddonConfig()' error by removing duplicate function definition from lib/functions.php (function already exists in rdas_pricing_updater.php)
- Fixed multiple "Undefined function 'mysql_*'" errors by replacing deprecated MySQL functions with WHMCS-compatible alternatives
- Replaced mysql_num_rows() with proper result checking
- Replaced mysql_fetch_assoc() with mysql_fetch_array() from WHMCS compatibility layer
- Replaced mysql_insert_id() with insert_query() WHMCS function
- Replaced mysql_affected_rows() with proper result validation

### Changed
- Updated version to 2.0.6
- Improved code consistency and maintainability
- Enhanced error handling in database operations
- Consolidated all helper functions in lib/functions.php for better organization

## [2.0.5] - 2024-12-19

### Fixed
- **Settings Page White Screen Error**: Fixed blank white page error when saving settings
  - Replaced WHMCS\Database\Capsule\Manager usage in saveAddonConfigValue() function at pages/settings.php:218
  - Migrated to native WHMCS database functions (select_query, update_query, insert_query) for compatibility
  - Prevents fatal error that caused settings page to display blank screen without error messages
  - Ensures settings can be saved successfully across all WHMCS versions
  - Maintains same functionality with improved stability

### Changed
- **Version Update**: Updated version number from 2.0.4 to 2.0.5
- **Database Compatibility**: Consistent use of native WHMCS database functions throughout the addon

## [2.0.4] - 2024-12-19

### Fixed
- **Database Compatibility Error**: Fixed "Class 'WHMCS\Database\Capsule\Manager' not found" error in hooks.php:394
  - Replaced all Capsule::table() usages with full_query() and mysql_fetch_array() for better WHMCS compatibility
  - Updated getAddonConfig(), getTlds(), addNewTldToWhmcs(), and updateTldPricingInWhmcs() functions
  - Removed WHMCS\Database\Capsule\Manager import dependency
  - Ensures compatibility across different WHMCS versions
  - Prevents fatal errors during addon initialization and cron operations

### Changed
- **Version Update**: Updated version number from 2.0.3 to 2.0.4
- **Database Layer**: Migrated from Capsule ORM to native WHMCS database functions for improved stability

## [2.0.3] - 2024-12-19

### Fixed
- **Function Redeclaration Error**: Fixed "Cannot redeclare getAddonStatistics()" error between lib/functions.php:575 and pages/dashboard.php:132
  - Renamed getAddonStatistics() to getDashboardStatistics() in pages/dashboard.php
  - Updated function call in pages/dashboard.php to use getDashboardStatistics()
  - Prevents conflict with function defined in lib/functions.php
- **Comprehensive Function Prefixing**: Implemented consistent 'rdas' prefix strategy for all custom functions
  - Applied 'rdas' prefix to all 19 custom functions in lib/functions.php to prevent WHMCS conflicts
  - Updated all function calls project-wide (rdas_pricing_updater.php, pages/*.php, assets/js/script.js)
  - Functions renamed: fetchDomainPrices → rdasFetchDomainPrices, calculateDomainPrices → rdasCalculateDomainPrices, etc.
  - Prevents future redeclaration errors with WHMCS built-in functions
  - Maintains backward compatibility through systematic renaming

### Changed
- **Version Update**: Updated version number from 2.0.2 to 2.0.3
- **Code Consistency**: All custom functions now follow consistent naming convention with 'rdas' prefix
- **Error Prevention**: Proactive approach to prevent function name conflicts with WHMCS core

## [2.0.2] - 2024-12-19

### Fixed
- **Function Redeclaration Error**: Fixed "Cannot redeclare formatCurrency()" error in lib/functions.php
  - Renamed formatCurrency() to rdasFormatCurrency() to avoid conflict with WHMCS built-in functions
  - Updated all function calls in assets/js/script.js to use rdasFormatCurrency()
  - Prevents fatal error when WHMCS already has formatCurrency() function loaded
  - Maintains same functionality with unique namespace

## [2.0.1] - 2024-12-19

### Removed
- Deleted unused PHP files: dashboard_backup.php, dashboard_new.php, dashboard_simple.php
- Removed test_addon.php (testing file no longer needed)

### Fixed
- Cleaned up project structure by removing redundant files
- Improved codebase maintainability

## [2.0.0] - 2024-12-19

### Added
- **Complete WHMCS Addon Rebuild**: Rebuilt entire addon structure following WHMCS best practices
- **Main Addon File**: Rebuilt `rdas_pricing_updater.php` with proper WHMCS addon structure
  - Configuration array with proper field definitions
  - Activation/deactivation hooks
  - Output function for admin area integration
  - Version 2.0.0 with comprehensive settings
- **Core Library**: Rebuilt `lib/functions.php` with modular architecture
  - `RdasApiClient` class for API communication with rdash.id
  - `RdasPricingEngine` class for price calculations and margin handling
  - `RdasLogger` class for comprehensive logging
  - Helper functions for WHMCS integration
- **Template System**: Complete template rebuild
  - `templates/pricing_table.tpl` - Interactive domain pricing table with bulk actions
  - `templates/settings.tpl` - Comprehensive settings interface with margin types
  - Modern responsive design with Bootstrap integration
- **Page Controllers**: Rebuilt all page controllers
  - `pages/dashboard.php` - Statistics dashboard with real-time updates
  - `pages/pricing.php` - Domain pricing management with filters and bulk actions
  - `pages/settings.php` - Configuration management with validation
  - `pages/api_test.php` - API connection testing interface
  - `pages/logs.php` - Log management with search and export
- **Frontend Assets**: Complete CSS and JavaScript rebuild
  - `assets/css/style.css` - Modern responsive styling with dark mode support
  - `assets/js/script.js` - AJAX framework with modular page-specific functionality
- **Hook Integration**: Comprehensive WHMCS hooks
  - `DailyCronJob` for automatic pricing updates
  - `AdminAreaPage` for admin menu integration
  - `AdminAreaHeadOutput` for CSS/JS loading
  - `AdminAreaFooterOutput` for JavaScript initialization
  - `AfterRegistrarRegistration` for auto TLD import
  - `AfterRegistrarTransfer` for auto TLD import
- **API Integration**: Full rdash.id API integration
  - Secure authentication with API keys
  - Comprehensive error handling
  - Rate limiting and retry mechanisms
  - Response caching for performance
- **Pricing Features**:
  - Multiple margin types (fixed amount, percentage, tiered)
  - Bulk pricing operations
  - Individual domain price sync
  - Price rounding options
  - Promotional pricing support
  - Currency conversion handling
- **User Interface Enhancements**:
  - Interactive dashboard with statistics
  - Advanced filtering and search
  - Bulk selection and actions
  - Modal dialogs for detailed operations
  - Real-time notifications
  - Loading indicators and progress bars
  - Responsive design for mobile devices
- **Logging System**:
  - Comprehensive activity logging
  - Log levels (info, warning, error, debug)
  - Log rotation and cleanup
  - Export functionality
  - Search and filtering
- **Security Features**:
  - CSRF protection with nonces
  - Input validation and sanitization
  - Secure API communication
  - Permission checks
  - SQL injection prevention

### Changed
- **Version Bump**: Updated from 1.x to 2.0.0 (major version)
- **Architecture**: Complete rewrite with modular, object-oriented design
- **Database Schema**: Enhanced with proper indexing and relationships
- **Performance**: Optimized queries and caching mechanisms
- **Code Quality**: Improved with PSR standards and best practices

### Technical Improvements
- **WHMCS Compliance**: Full adherence to WHMCS addon development guidelines
- **Error Handling**: Comprehensive error catching and user-friendly messages
- **Code Organization**: Modular structure with clear separation of concerns
- **Documentation**: Inline code documentation and comments
- **Validation**: Input validation on both client and server side
- **Accessibility**: WCAG compliant interface elements
- **Browser Support**: Cross-browser compatibility testing

### Dependencies
- **WHMCS**: Minimum version 7.0
- **PHP**: Minimum version 7.4
- **MySQL**: Minimum version 5.7
- **cURL**: Required for API communication
- **JSON**: Required for data processing

### Migration Notes
- This is a major version update requiring manual migration
- Backup existing data before upgrading
- Review configuration settings after upgrade
- Test API connectivity after installation

---

## [1.0.0] - Previous Version
- Initial release with basic functionality
- Basic pricing sync capabilities
- Simple admin interface
- Manual pricing updates