# Code Review - RDAS Pricing Updater

## Status: FIXED ✓

All critical issues have been addressed. See below for original findings and fixes applied.

---

## Promo Price Feature - Analysis & Fix

### Issue: Promo Price Handling Not Working
**Files:** `lib/functions.php`, `hooks.php`

The addon's main purpose is to:
1. Fetch domain pricing from `https://api.rdash.id/api/domain-prices`
2. Check for promo prices and apply them if within active date range
3. Add profit margin percentage to the price
4. Update WHMCS domain pricing

**API Response Structure:**
```json
{
  "extension": ".com",
  "registration": "Rp199.000",
  "renewal": "Rp199.000",
  "transfer": "Rp199.000",
  "promo": {
    "registration": "Rp149.000",
    "start_date": "2025-07-04T02:00:00.000000Z",
    "end_date": "2025-08-31T16:59:00.000000Z"
  }
}
```

**Original Problems:**
1. `rdasCalculateDomainPrices()` used `floatval()` on price strings - doesn't parse "Rp199.000"
2. No promo price detection or date validation
3. Used wrong config key (`profit_margin` vs `default_margin`)
4. `hooks.php` used non-existent classes (`RdasApiClient`, `RdasPricingEngine`, `RdasLogger`)

**Fix Applied:**
- Updated `rdasCalculateDomainPrices()` to:
  - Use `rdasParsePrice()` for proper string parsing
  - Check for promo object and validate dates
  - Use promo price if active, fallback to regular price
  - Support both `profit_margin` and `default_margin` config keys

- Rewrote `hooks.php` to:
  - Use existing functions from `lib/functions.php`
  - Remove non-existent class references
  - Properly handle promo price tracking

---

## Fixed Issues

### 1. ✓ Security: Hardcoded Token (FIXED)
**File:** `lib/functions.php`

**Fix:** Removed hardcoded fallback token. Function now returns `false` if no valid token exists.

---

### 2. ✓ Database Schema Inconsistency (FIXED)
**Files:** `lib/functions.php` vs `pages/logs.php`

**Fix:** Updated `pages/logs.php` to use correct column names (`level`, `date`).

---

### 3. ✓ Code Duplication - CSRF Tokens (FIXED)
**Original:** 4 duplicate `generateCSRFToken()` functions.

**Fix:** Created centralized `rdasGenerateCSRFToken($context)` and `rdasValidateCSRFToken()`.

---

### 4. ✓ Double Insert Bug (FIXED)
**File:** `lib/functions.php:rdasCreateDomainEntry()`

**Fix:** Simplified to single `insert_query()` call.

---

### 5. ✓ Non-existent Classes in hooks.php (FIXED)
**Original:** Used `RdasApiClient`, `RdasPricingEngine`, `RdasLogger` which don't exist.

**Fix:** Rewrote hooks.php to use functions from `lib/functions.php`.

---

### 6. ✓ Promo Price Logic (FIXED)
**Original:** No promo handling, wrong price parsing.

**Fix:** Added promo date validation and `rdasParsePrice()` usage.

---

## Current Flow (After Fix)

```
API Response → rdasFetchDomainPrices()
                    ↓
              rdasCalculateDomainPrices()
                    ↓
         Check promo.start_date & end_date
              (if promo active now)
                    ↓
         Use promo.registration OR regular registration
                    ↓
              Apply profit margin (%)
                    ↓
              Apply rounding rule
                    ↓
              rdasUpdateDomainPricing()
                    ↓
              Update WHMCS tbldomainpricing
```

---

## Original Issues (For Reference)

### 1. Security: Hardcoded Token (CRITICAL)
**File:** `lib/functions.php:155`

```php
return 'f030cc60937f98b1e896c09b5da3cf0ada8efb45'; // Use the token from user's example
```

**Risk:** This hardcoded fallback token is a major security vulnerability. Anyone with access to the code can impersonate admin sessions.

**Fix:** Remove the hardcoded token and properly handle the case when no token is available.

---

### 2. Database Schema Inconsistency (HIGH)
**Files:** `lib/functions.php` vs `pages/logs.php`

The log table schema and queries are inconsistent:
- `functions.php` uses columns: `level`, `date`, `message`, `data`
- `logs.php` uses columns: `type`, `created_at`, `message`, `details`

This will cause SQL errors when trying to access logs.

---

### 3. Setting Key Mismatches (HIGH)
Multiple different key names for similar settings:

| File | Key Name |
|------|----------|
| `rdas_pricing_updater.php` | `profit_margin` |
| `functions.php` | `margin_value` (line 96) |
| `settings.php` | `default_margin` |
| `hooks.php` | `default_margin` |

---

## Code Duplication

### 1. CSRF Token Generation (4 instances)
Duplicated in:
- `pages/settings.php:825-830`
- `pages/pricing.php:792-797`
- `pages/logs.php:901-906`
- `pages/api_test.php:631-636`

Each uses a different session key, which is unnecessary.

### 2. cURL Setup Code
Repeated in:
- `lib/functions.php:195-257` - `rdasFetchDomainPrices()`
- `lib/functions.php:117-130` - `rdasImportDomainViaTLDSync()`
- `pages/api_test.php:90-122` - `testApiConnection()`

### 3. Database Fallback Pattern
Repeated pattern of `if (class_exists('Capsule'))...else...` in many places.

### 4. `getLogTypeClass()` Function
Defined in both PHP (`pages/logs.php:881-894`) and JavaScript (`pages/logs.php:761-769`).

---

## Logic Errors

### 1. `rdasCreateDomainEntry()` Double Insert
**File:** `lib/functions.php:427-449`

```php
$result = full_query($query, [$extension]);  // First insert
if ($result) {
    $insertId = insert_query('tbldomainpricing', [...]);  // Second insert!
    return $insertId;
}
```

This performs TWO inserts for the same domain, causing duplicates.

### 2. `rdasGetAddonLogs()` Unused Variable
**File:** `lib/functions.php:648-673`

```php
$params = [];  // Built but never used
if (!empty($level)) {
    $params[] = $level;
}
// Later uses string concatenation instead of $params
```

### 3. `rdasCleanOldLogs()` Incorrect Affected Rows
**File:** `lib/functions.php:715-716`

```php
$affected = ($result) ? 1 : 0;  // Always returns 0 or 1, not actual count
```

### 4. Rounding Rule Mismatch
**Files:** `rdas_pricing_updater.php` vs `settings.php`

Main file expects: `none`, `up_1000`, `up_5000`, `nearest_1000`, `custom`
Settings page provides: `nearest_thousand`, `nearest_hundred`, `custom`

---

## Unused/Dead Code

### 1. Unused Variables
- `lib/functions.php:654` - `$params` array built but never used
- `lib/functions.php:659` - `$whereClause` built but never used

### 2. Redundant Functions
- `rdasDomainExistsInWHMCS()` - can use `rdasGetDomainId()` instead
- `rdasTestDatabaseConnection()` - just runs `SELECT 1`, not useful

### 3. Unused Settings in `settings.php`
Many settings defined but not used in main logic:
- `tiered_pricing_enabled`, `tier_1_threshold`, `tier_2_margin`, etc.
- `registrar_validation`
- `debug_mode`

---

## Inconsistencies

### 1. Function Naming Convention
Mixed naming patterns:
- `rdasGet...()` - most common
- `get...()` - some functions in pages
- `rdas...()` - some helper functions

### 2. Default API URL
- `rdas_pricing_updater.php`: `https://api.rdash.id/api/domain-prices?currency=IDR`
- `settings.php`: `https://rdash.id/api/domain-prices`
- `api_test.php`: `https://rdash.id/api/domain-prices`

### 3. Return Types
- Some functions return `false` on error
- Some return `['success' => false, 'message' => '...']`
- Some throw exceptions

---

## Recommended Fixes

### Priority 1: Security
1. Remove hardcoded token from `rdasGetWHMCSAdminToken()`
2. Fix SQL column name inconsistencies in logs
3. Standardize setting key names

### Priority 2: Bugs
1. Fix double insert in `rdasCreateDomainEntry()`
2. Fix rounding rule option values in settings
3. Fix `rdasCleanOldLogs()` affected rows reporting

### Priority 3: Simplification
1. Create single `rdasGenerateCSRFToken()` function
2. Create single `rdasCurlRequest()` helper
3. Remove unused settings or implement their functionality
4. Consolidate database access patterns
