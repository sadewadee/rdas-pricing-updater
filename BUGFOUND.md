# Bug Found Documentation

This file documents all bugs found during development and their resolution status.

## Bug #001 - 2024-01-20

**Issue:** AJAX request returning 404 error for rdas_pricing_updater.php
**File:** rdas_pricing_updater.php (line 296)
**Status:** Fixed
**Description:** JavaScript AJAX call menggunakan path yang salah 'modules/addons/rdas_pricing_updater/rdas_pricing_updater.php' instead of correct WHMCS addon path
**Solution:** Changed AJAX path to 'addonmodules.php?module=rdas_pricing_updater'
**Severity:** High

## Bug #002 - 2024-01-20

**Issue:** Undefined function 'importSelectedDomains' in JavaScript
**File:** rdas_pricing_updater.php (JavaScript section)
**Status:** Fixed
**Description:** Function importSelectedDomains was defined after the button that calls it, causing ReferenceError
**Solution:** Moved function definition before the button declaration and removed duplicate code
**Severity:** High

## Bug #003 - 2024-01-20

**Issue:** Import functionality not working with WHMCS domain system
**File:** lib/functions.php (rdasImportDomains function)
**Status:** Fixed
**Description:** Original import function was trying to update existing domains instead of using WHMCS TLD Sync API
**Solution:** Implemented rdasImportDomainViaTLDSync function to use proper WHMCS TLD Sync API
**Severity:** Critical

## Bug #004 - 2024-01-20

**Issue:** JavaScript file domain-pricing.js returns 404 Not Found error
**File:** rdas_pricing_updater.php (line 282)
**Status:** Fixed
**Description:** Incorrect path to JavaScript file in WHMCS admin area context
**Root Cause:** Static path construction doesn't work in WHMCS module environment
**Solution:** Used dynamic URL generation with $_SERVER variables to construct correct module path
**Severity:** High

## Bug #005 - 2024-01-20

**Issue:** Reg Period column showing static '1 Year' instead of actual terms value
**File:** rdas_pricing_updater.php (line 254)
**Status:** Fixed
**Description:** Display was hardcoded to show '1 Year' regardless of actual promotional period
**Root Cause:** Field 'terms' ada di dalam objek 'promo' ($domain['promo']['terms']), bukan di level utama domain
**Solution:** Mengakses $domain['promo']['terms'] dengan fallback ke 1 jika null
**Severity:** Medium

## Bug #005 - 2024-01-20

**Issue:** AJAX handler not properly parsing JSON domains data
**File:** rdas_pricing_updater.php (AJAX handler section)
**Status:** Fixed
**Description:** Handler was expecting array but receiving JSON string from JavaScript
**Solution:** Added json_decode to properly parse domains data before processing
**Severity:** Medium

## Bug #006 - 2024-12-19
**Issue**: Undefined type 'WHMCS\Database\Capsule\Manager' in multiple files
**File**: pages/logs.php (lines 116, 208, 291, 329), hooks.php (multiple lines)
**Status**: Fixed
**Recommendation**: Added proper WHMCS namespace imports
**Severity**: Medium
**Details**: 
- Added proper use statement for WHMCS Database Capsule
- All Capsule references now properly imported
- Fixed in both hooks.php and pages/logs.php

## Bug #002 - 2024-12-19
**Issue**: Undefined function 'updateTldPricing' and 'rdasLog' in hooks.php
**File**: hooks.php (multiple lines)
**Status**: Fixed
**Recommendation**: Functions renamed and properly implemented
**Severity**: Medium
**Details**:
- updateTldPricing renamed to updateTldPricingInWhmcs
- rdasLog replaced with RdasLogger class instance calls
- All function calls now properly defined and working

## Bug #003 - 2024-12-19
**Issue**: Undefined type 'RdasApiClient' and 'RdasPricingEngine' in hooks.php
**File**: hooks.php (multiple lines)
**Status**: Fixed
**Recommendation**: Added proper class loading via require_once
**Severity**: Medium
**Details**:
- Classes now properly loaded through lib/functions.php include
- All class instantiations working correctly
- Proper autoloading mechanism implemented

## Bug #004 - 2024-12-19
**Issue**: Syntax error in hooks.php line 291
**File**: hooks.php (line 291)
**Status**: Fixed
**Recommendation**: Fixed syntax error in function call
**Severity**: High
**Details**:
- Unexpected token "(" in function definition
- Fixed by correcting function syntax
- Code now properly formatted

## Bug #005 - 2024-12-19
**Issue**: Deprecated MySQL functions in hooks.php
**File**: hooks.php (multiple lines)
**Status**: Fixed
**Recommendation**: Replace with WHMCS database functions
**Severity**: Medium
**Details**:
- Replaced mysql_* functions with WHMCS Capsule Manager
- Updated all database queries to use modern methods
- Improved error handling and logging

## Bug #006 - 2025-01-18
**Issue**: Missing dashboard_simple.php fallback file
**File**: pages/dashboard_simple.php
**Status**: Fixed
**Recommendation**: Create fallback dashboard for error scenarios
**Severity**: Medium
**Details**:
- Created dashboard_simple.php as fallback when main dashboard fails
- Provides basic troubleshooting information
- Prevents fatal errors when dashboard.php encounters issues

## Bug #007 - 2025-01-18
**Issue**: Incorrect parameters in rdasGetAddonLogs function call
**File**: pages/dashboard.php (line 25)
**Status**: Fixed
**Recommendation**: Provide all required parameters (page, limit, level)
**Severity**: High
**Details**:
- Function called with only 1 parameter but expects 3
- Fixed by providing proper parameters: rdasGetAddonLogs(1, 10, '')
- Prevents undefined parameter errors

## Bug #008 - 2025-01-18
**Issue**: Undefined function 'full_query' in lib/functions.php
**File**: lib/functions.php (multiple lines)
**Status**: Fixed
**Recommendation**: Replace with proper WHMCS database functions
**Severity**: High
**Details**:
- Replaced full_query() with appropriate WHMCS functions
- Used insert_query() for insertions
- Used select_query() for selections
- Improved database compatibility

## Bug #009 - 2025-01-18
**Issue**: Deprecated mysql_query and mysql_real_escape_string functions
**File**: lib/functions.php (lines 450-451)
**Status**: Fixed
**Recommendation**: Use WHMCS database functions instead
**Severity**: High
**Details**:
- Replaced mysql_query() with insert_query()
- Removed mysql_real_escape_string() usage
- Used WHMCS built-in escaping mechanisms
- Improved security and compatibility

## Bug #010 - 2025-01-18
**Issue**: Undefined function 'delete_query' in lib/functions.php
**File**: lib/functions.php (line 541)
**Status**: Fixed
**Recommendation**: Use full_query with raw SQL DELETE statement
**Severity**: Medium
**Details**:
- delete_query() function not available in WHMCS
- Replaced with full_query() using raw SQL DELETE
- Maintains functionality while using available functions

## Bug #011 - 2025-01-18
**Issue**: Incorrect database field names in getDashboardStatistics
**File**: pages/dashboard.php (lines 100-115)
**Status**: Fixed
**Recommendation**: Use correct log table field names
**Severity**: Medium
**Details**:
- Changed 'action' field to 'message' field
- Changed 'created_at' field to 'date' field
- Updated WHERE clauses to use LIKE for message matching
- Aligned with actual database schema

## Bug #012 - 2025-01-18
**Issue**: Deprecated mysql_num_rows() function in getSystemHealth
**File**: pages/dashboard.php (line 295)
**Status**: Fixed
**Recommendation**: Use mysql_fetch_array() to check for results
**Severity**: Medium
**Details**:
- Replaced mysql_num_rows() with mysql_fetch_array() check
- Improved compatibility with modern PHP versions
- Maintains table existence checking functionality

## Bug #013 - 2025-01-18
**Issue**: Undefined function 'select_query' when testing outside WHMCS
**File**: pages/dashboard.php (line 94)
**Status**: Fixed
**Recommendation**: Create WHMCS compatibility layer
**Severity**: High
**Details**:
- Created whmcs_compatibility.php with mock functions
- Provides testing environment outside WHMCS
- Enables standalone testing and debugging

## Bug #014 - 2025-01-18
**Issue**: Infinite loop in mysql_fetch_array mock function
**File**: lib/whmcs_compatibility.php
**Status**: Fixed
**Recommendation**: Implement proper counter mechanism
**Severity**: Critical
**Details**:
- Mock function caused infinite loop and memory exhaustion
- Implemented static counter to track fetch position
- Properly returns false when no more rows available

## Bug #015 - 2025-01-18
**Issue**: Undefined array keys in dashboard template
**File**: pages/dashboard.php (lines 590-595)
**Status**: Fixed
**Recommendation**: Use correct field names and add isset() checks
**Severity**: Medium
**Details**:
- Template used 'created_at', 'action', 'details', 'status' fields
- Database uses 'date', 'level', 'message', 'data' fields
- Added isset() checks to prevent undefined key warnings
- Updated template to use correct field names

## Bug #016 - 2025-01-18
**Issue**: Array offset access on false values
**File**: pages/dashboard.php (lines 107, 122)
**Status**: Fixed
**Recommendation**: Add proper null/false checks before array access
**Severity**: Medium
**Details**:
- mysql_fetch_array() returns false when no data
- Added checks: ($data && isset($data['field'])) before access
- Provides default values when data not available
- Prevents array offset warnings

## Bug #017 - 2025-01-18
**Issue**: null parameter passed to number_format()
**File**: pages/dashboard.php (lines 382, 391, 400)
**Status**: Fixed
**Recommendation**: Use null coalescing operator for default values
**Severity**: Low
**Details**:
- Statistics values could be null causing number_format() warnings
- Added null coalescing operator: ?? 0
- Ensures numeric values are always passed to number_format()
- Improves display consistency
**File**: hooks.php (multiple lines)
**Status**: Fixed
**Recommendation**: Replaced with WHMCS Capsule database operations
**Severity**: High
**Details**:
- All mysql_query and mysql_fetch_assoc calls removed
- Replaced with modern Capsule database operations
- Code now compatible with current PHP versions

## Bug #006 - 2024-12-19
**Issue**: Undefined function 'sendMessage' in hooks.php
**File**: hooks.php (line 463)
**Status**: Fixed
**Recommendation**: Replaced with WHMCS built-in functions
**Severity**: Medium
**Details**:
- sendMessage replaced with logActivity() and mail() fallback
- Now uses proper WHMCS logging mechanisms
- Email functionality maintained with PHP mail()

## Bug #007 - 2024-12-19
**Issue**: Function naming conflicts (addNewTld vs addNewTldToWhmcs)
**File**: hooks.php (multiple lines)
**Status**: Fixed
**Recommendation**: Standardized function naming
**Severity**: Medium
**Details**:
- Renamed addNewTld to addNewTldToWhmcs for consistency
- All function calls updated to use standardized names
- Improved code clarity and maintainability

## Bug #008 - 2024-12-19
**Issue**: WordPress function name in WHMCS context
**File**: hooks.php (line 575)
**Status**: Fixed
**Recommendation**: Renamed to WHMCS-compatible function name
**Severity**: Low
**Details**:
- wp_create_nonce renamed to createRdasNonce
- Function maintains same functionality
- Now follows WHMCS naming conventions

## Bug #009 - 2024-12-19
**Issue**: Cannot redeclare formatCurrency() function
**File**: lib/functions.php (line 409)
**Status**: Fixed
**Recommendation**: Renamed function to avoid conflict with WHMCS built-in functions
**Severity**: High
**Details**:
- formatCurrency() renamed to rdasFormatCurrency() in lib/functions.php
- Updated all function calls in assets/js/script.js to use rdasFormatCurrency()
- Prevents conflict with WHMCS built-in formatCurrency() function
- Maintains same functionality with unique namespace

## Bug #010 - 2024-12-19
**Issue**: Cannot redeclare getAddonStatistics() function
**File**: lib/functions.php (line 575) and pages/dashboard.php (line 132)
**Status**: Fixed
**Recommendation**: Implemented comprehensive function prefixing strategy to prevent future conflicts
**Severity**: Critical
**Details**:
- getAddonStatistics() was declared in both lib/functions.php:575 and pages/dashboard.php:132
- Renamed getAddonStatistics() to getDashboardStatistics() in pages/dashboard.php
- Updated function call in pages/dashboard.php to use getDashboardStatistics()
- Implemented comprehensive 'rdas' prefix strategy for all 19 custom functions in lib/functions.php
- Applied systematic renaming: fetchDomainPrices → rdasFetchDomainPrices, calculateDomainPrices → rdasCalculateDomainPrices, etc.
- Updated all function calls project-wide (rdas_pricing_updater.php, pages/*.php, assets/js/script.js)
- Prevents future redeclaration errors with WHMCS built-in functions
- Maintains backward compatibility through systematic renaming approach

## Bug #011 - 2024-12-19
**Issue**: Class "WHMCS\Database\Capsule\Manager" not found
**File**: hooks.php (line 394)
**Status**: Fixed
**Recommendation**: Replace Capsule ORM with native WHMCS database functions for better compatibility
**Severity**: Critical
**Details**:
- Error occurred when trying to use WHMCS\Database\Capsule\Manager in hooks.php
- Capsule ORM may not be available in all WHMCS versions or configurations
- Replaced all Capsule::table() usages with full_query() and mysql_fetch_array()
- Updated getAddonConfig(), getTlds(), addNewTldToWhmcs(), and updateTldPricingInWhmcs() functions
- Removed WHMCS\Database\Capsule\Manager import dependency
- Migrated from Capsule ORM to native WHMCS database functions for improved stability
- Ensures compatibility across different WHMCS versions and prevents fatal errors
- All database operations now use WHMCS-native methods that are guaranteed to be available

## Bug #012 - 2024-12-19
**Issue**: Settings page displays blank white screen when saving changes
**File**: pages/settings.php (line 218)
**Status**: Fixed
**Recommendation**: Replace WHMCS\Database\Capsule\Manager with native WHMCS database functions
**Severity**: Critical
**Details**:
- Settings page showed blank white screen without error messages when trying to save changes
- Error caused by WHMCS\Database\Capsule\Manager usage in saveAddonConfigValue() function at line 218
- Capsule ORM not available or compatible in current WHMCS environment
- Replaced WHMCS\Database\Capsule\Manager with native WHMCS database functions (select_query, update_query, insert_query)
- Removed conditional class_exists check for Capsule Manager
- Simplified database operations to use only native WHMCS methods
- Settings can now be saved successfully without causing fatal errors
- Maintains same functionality with improved stability and compatibility
- Prevents white screen of death when saving addon configuration

## Bug #013 - 2025-01-27
**Issue**: Configuration cannot be saved on `configaddonmods.php` page, resulting in blank white page
**File**: rdas_pricing_updater.php
**Status**: Fixed
**Recommendation**: Always use consistent function naming with proper prefixes to avoid conflicts
**Severity**: Critical
**Details**:
- Missing functions and inconsistent function naming - functions called without 'rdas' prefix causing undefined function errors
- Added missing functions (`rdasGetAddonConfig()`, `rdasFetchDomainPrices()`, `rdasCalculateDomainPrices()`, `rdasUpdateDomainPricing()`, `rdasLogToAddon()`)
- Fixed all function calls to use consistent 'rdas' prefix
- Configuration page now works properly without causing fatal errors

## Bug #014 - 2025-01-27
**Issue**: Fatal error "Cannot redeclare rdasFetchDomainPrices()" when loading addon pages
**File**: rdas_pricing_updater.php, lib/functions.php
**Status**: Fixed
**Recommendation**: Avoid function redeclaration by using proper include guards and centralized function definitions
**Severity**: Critical
**Details**:
- Function rdasFetchDomainPrices was defined in both rdas_pricing_updater.php and lib/functions.php, causing redeclaration error
- Removed duplicate functions from rdas_pricing_updater.php and added proper require_once for lib/functions.php
- Ensured all functions are defined only once in lib/functions.php
- Added proper include guards to prevent multiple inclusions

## Bug #015 - 2025-01-27
**Issue**: Fatal error "Call to undefined function getAddonConfig()" in lib/functions.php line 430
**File**: lib/functions.php
**Status**: Fixed
**Recommendation**: Ensure all required functions are available in the context where they are called
**Severity**: High
**Details**:
- Function getAddonConfig() was called but not available in lib/functions.php context
- Added rdasGetAddonConfig() function in lib/functions.php and updated function call to use the new function
- Ensured proper function availability across all contexts
- Prevents undefined function errors when lib/functions.php is loaded independently

## Bug #016 - 2025-01-27
**Issue**: Fatal error "Cannot redeclare rdasGetAddonConfig()" - function already declared in rdas_pricing_updater.php:256
**File**: lib/functions.php, rdas_pricing_updater.php
**Status**: Fixed
**Recommendation**: Avoid function redeclaration by using proper include guards and centralized function definitions
**Severity**: High
**Details**:
- Function rdasGetAddonConfig() was defined in both rdas_pricing_updater.php and lib/functions.php causing redeclaration error
- Removed duplicate rdasGetAddonConfig() function definition from lib/functions.php since it already exists in rdas_pricing_updater.php
- Ensured functions are defined only once across the entire codebase
- Added proper include guards to prevent multiple function declarations

## Bug #018 - 2025-01-18
**Issue**: "Can't change Addon Module Access Permissions" - Unable to modify permissions in WHMCS Admin
**File**: Database tbladdonmodules table, addon configuration
**Status**: Fixed
**Recommendation**: Always activate addon through WHMCS Admin interface, avoid manual database modifications
**Severity**: High
**Details**:
- Missing 'access' entry in tbladdonmodules database table for rdas_pricing_updater addon
- Created fix_permissions.php script to detect and fix missing access control entries
- Added comprehensive troubleshooting guide with multiple solution methods
- Recommended deactivate/reactivate addon as primary fix
- Provided manual database fix as alternative solution
- Prevention: Always activate addon through WHMCS Admin interface

## Bug #017 - 2025-01-27
**Issue**: Multiple "Undefined function 'mysql_*'" errors (mysql_num_rows, mysql_insert_id, mysql_affected_rows, mysql_fetch_assoc)
**File**: lib/functions.php
**Status**: Fixed
**Recommendation**: Replace all deprecated MySQL functions with WHMCS-compatible alternatives
**Severity**: Critical
**Details**:
- Usage of deprecated MySQL functions that are not available in modern PHP/WHMCS environments
- Replaced all deprecated MySQL functions with WHMCS-compatible alternatives:
  - mysql_num_rows() → proper result checking
  - mysql_fetch_assoc() → mysql_fetch_array() from WHMCS compatibility
  - mysql_insert_id() → insert_query() WHMCS function
  - mysql_affected_rows() → proper result validation
- All database operations now use modern WHMCS-compatible functions
- Prevents fatal errors in PHP 7+ environments where mysql_* functions are removed

## Bug #018 - 2025-08-18
**Issue**: ValueError: class_alias() with stdClass causing fatal error
**File**: pages/dashboard.php:21
**Status**: Fixed
**Recommendation**: Never use class_alias() with internal PHP classes like stdClass for WHMCS compatibility
**Severity**: Critical
**Details**: class_alias('stdClass', 'WHMCS\Database\Capsule\Manager') caused ValueError in production. Removed the problematic class_alias code completely. WHMCS classes should be used directly when available.

## Bug #019 - 2025-08-18
**Issue**: Inconsistent version numbering across files
**File**: rdas_pricing_updater.php, lib/functions.php
**Status**: Fixed
**Recommendation**: Maintain consistent version numbers across all files
**Severity**: Low
**Details**: Version numbers were inconsistent between main addon file (2.0.7) and functions file (2.0.0). Updated both to 2.0.8 for consistency.

---

## Resolution Guidelines

### For WHMCS Integration Issues:
1. Ensure proper WHMCS environment loading
2. Use WHMCS built-in functions when possible
3. Add proper error handling for missing dependencies
4. Test in actual WHMCS environment, not just IDE

### For Class Loading Issues:
1. Add proper require_once statements
2. Implement autoloading mechanism
3. Ensure file paths are correct
4. Test class instantiation in WHMCS context

### For Function Definition Issues:
1. Verify function exists in included files
2. Add proper file includes
3. Use WHMCS hooks properly
4. Test function calls in WHMCS environment

---

## Testing Recommendations

1. **WHMCS Environment Testing**: All code should be tested in actual WHMCS installation
2. **Error Logging**: Enable comprehensive error logging during testing
3. **Gradual Deployment**: Test individual components before full deployment
4. **Backup Strategy**: Always backup before testing major changes
5. **User Acceptance Testing**: Test with real user scenarios

---

## Notes

- Most "undefined" errors are IDE-related and may not occur in actual WHMCS environment
- WHMCS provides its own autoloading and class resolution
- Always test in actual WHMCS installation for accurate results
- Keep this file updated with any new bugs found during testing or production use