# Domain Price Updater - WHMCS Addon
## Overview

Addon WHMCS untuk mengupdate harga domain secara realtime dari webhook rdash.id dengan pengaturan margin dan rounding yang fleksibel.

## File Structure

```

/domain_price_updater/
├── domain_price_updater.php     \# Main addon file
├── hooks.php
├── templates/
│   ├── settings.tpl             \# Admin settings interface
│   └── pricing_table.tpl        \# Domain pricing table view
├── lib/
│   └── functions.php            \# Helper functions
├── assets/
│   ├── css/
│   │   └── style.css           \# Custom styling
│   └── js/
│       └── script.js           \# Frontend JavaScript
└── docs/
└── guidelines.md           \# This documentation

```

## Core Features

### 1. API Integration
- **Source**: `https://api.rdash.id/api/domain-prices?currency=IDR`
- **Method**: GET dengan headers CORS-compliant (public access, no API key required)
- **Format**: JSON array dengan pricing data
- **Focus**: Harga registrasi 1 tahun ('promo > registration' field) fallback 'registration'

### 2. Settings Interface
- **Margin Type**: Dropdown (Percentage/Fixed Amount)
- **Profit Margin**: Input field dengan validation
- **Round to Next**: Dropdown options untuk rounding rules
- **Update Controls**: Manual trigger dan status display

### 3. Domain Pricing Table Interface
- **Import Button**: "Import 0 TLDs" untuk bulk import
- **Currency Notice**: "All prices below are shown converted to your system default currency IDR"
- **Data Table Columns**:
  - **Selection**: Checkbox untuk bulk actions
  - **TLD**: Domain extension (.com, .net, dll)
  - **Existing TLD**: Status indicator (✓ if exists)
  - **Reg Period**: Registration period (1 Year, 2 Years, dll)
  - **Register**: Registration pricing (Promo/Current/Margin)
  - **Renew**: Renewal pricing (Promo/Current/Margin)
  - **Transfer**: Transfer pricing (Promo/Current/Margin)
  - **Redemption**: Redemption pricing (Promo/Current/Margin)
  - **Start|End date**: Promo period dates
  - **Action**: Sync button untuk individual update

### 4. Price Calculation Engine
- Input: base price dari API (format "Rp270.000")
- Parse: remove "Rp" dan convert ke numeric
- Apply margin: percentage atau fixed amount
- Apply rounding: sesuai settings
- Output: final price untuk WHMCS

### 5. Registrar Validation
- Pre-check: validasi default registrar aktif
- Cross-reference: domain extensions dengan registrar
- Safety: halt update jika registrar tidak aktif

## Technical Specifications

### API Headers (Required)
```

accept: application/json
accept-encoding: gzip, deflate, br, zstd
accept-language: en-US,en;q=0.9,id;q=0.8
cache-control: no-cache
origin: https://rna.id
referer: https://rna.id/
sec-fetch-mode: cors
sec-fetch-site: cross-site
user-agent: Mozilla/5.0 (compatible)

```

### Data Processing Logic
1. **Parse Price**: `"Rp270.000"` → `270000`
2. **Apply Margin**:
   - Percentage: `price * (1 + margin/100)`
   - Fixed: `price + margin`
3. **Apply Rounding**:
   - No Rounding: keep as-is
   - Round Up: `ceil(price/1000) * 1000`
   - Custom: configurable increments

### Database Operations
- **Target Table**: `tbldomainpricing`
- **Update Fields**: `msetupfee` (1 year registration)
- **Condition**: Match extension dan registrar ID
- **Safety**: Transaction-based updates

## Interface Layouts

### 1. Settings Configuration Page
- **Form Fields**: Margin Type, Profit Margin, Round to Next
- **Preview Section**: Real-time price calculation
- **Action Buttons**: Save Settings, Update Prices Now, Test API
- **Status Display**: Last update info dan error messages

### 2. Domain Pricing Table View
```

<!-- Table Header -->

<div class="pricing-controls">
    <button class="import-button">Import 0 TLDs</button>
    <div class="currency-notice">
        All prices below are shown converted to your system default currency IDR.
    </div>
</div>

<!-- Data Table -->

<table class="domain-pricing-table">
    <thead>
        <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th>TLD</th>
            <th>Existing TLD</th>
            <th>Reg Period</th>
            <th colspan="3">Register</th>
            <th colspan="3">Renew</th>
            <th colspan="3">Transfer</th>
            <th colspan="3">Redemption</th>
            <th>Start|End date</th>
            <th>Action</th>
        </tr>
        <tr class="sub-header">
            <th></th><th></th><th></th><th></th>
            <th>Promo</th><th>Current</th><th>Margin</th>
            <th>Promo</th><th>Current</th><th>Margin</th>
            <th>Promo</th><th>Current</th><th>Margin</th>
            <th>Promo</th><th>Current</th><th>Margin</th>
            <th></th><th></th>
        </tr>
    </thead>
    <tbody>
        <!-- Dynamic rows populated from API data -->
    </tbody>
</table>
```

### 3. Pricing Data Structure
```

// Example row data
\$domain_data = [
'tld' => '.com',
'existing' => true,
'reg_period' => '1 Year',
'register' => [
'promo' => 238800.00,
'current' => 199000.00,
'margin' => '20%'
],
'renew' => [
'promo' => 238800.00,
'current' => 199000.00,
'margin' => '20%'
],
'transfer' => [
'promo' => 238800.00,
'current' => 199000.00,
'margin' => '20%'
],
'redemption' => [
'promo' => 1620000.00,
'current' => 1350000.00,
'margin' => '20%'
],
'promo_dates' => '2025-06-08|2025-12-31',
'sync_status' => 'ready'
];

```

## Settings Configuration

### Margin Settings
- **Type Options**:
  - `percentage`: Apply as percentage markup
  - `fixed`: Apply as fixed amount addition
- **Value**: Numeric input dengan decimal support
- **Default**: 20% percentage margin

### Rounding Options
- `none`: No rounding applied
- `up_1000`: Round up to nearest 1000
- `up_5000`: Round up to nearest 5000
- `nearest_1000`: Round to nearest 1000
- `custom`: User-defined increment

### Table Features
- **Bulk Selection**: Checkbox untuk select multiple domains
- **Individual Sync**: Per-domain sync button
- **Price Comparison**: Show promo vs current pricing
- **Margin Display**: Visual margin indicator
- **Status Indicators**: Visual cues untuk sync status

## Development Guidelines

### Code Structure
- **Main File**: Standard WHMCS addon structure
- **Functions**: Separate file untuk reusable code
- **Templates**: Clean Smarty templates untuk settings dan table
- **Assets**: Minimal CSS/JS untuk UX

### Table Implementation
- **Data Source**: API data merged dengan WHMCS pricing
- **Real-time Updates**: AJAX untuk sync individual domains
- **Bulk Actions**: Mass import dan update functionality
- **Responsive Design**: Mobile-friendly table layout

### Error Handling
- **API Failures**: Use cached data atau skip update
- **Database Errors**: Rollback dan log errors
- **Invalid Data**: Skip problematic entries
- **Network Issues**: Retry mechanism

### Security Considerations
- **Input Validation**: Sanitize semua user inputs
- **SQL Injection**: Use prepared statements
- **Permission Check**: Admin-only access
- **API Security**: Handle rate limiting

### Performance Optimization
- **Batch Processing**: Update multiple records sekaligus
- **Caching**: Store API responses temporarily
- **Memory Management**: Process large datasets efficiently
- **Background Processing**: Non-blocking updates
- **Pagination**: Handle large domain lists

## Implementation Phases

### Phase 1: Core Functionality
- [x] Brainstorming dan requirements
- [ ] Main addon file structure
- [ ] API client implementation
- [ ] Basic settings interface

### Phase 2: UI/UX Enhancement
- [ ] Admin template design
- [ ] Settings form validation
- [ ] Domain pricing table layout
- [ ] Progress indicators
- [ ] Error messaging

### Phase 3: Advanced Features
- [ ] Bulk import functionality
- [ ] Individual domain sync
- [ ] Registrar validation
- [ ] Batch update processing
- [ ] Logging dan audit trail
- [ ] Testing dan debugging

## API Data Structure

### Expected JSON Format
```

{
"currency": "IDR",
"type": "ccTLD|gTLD",
"extension": ".com",
"registration": "Rp199.000",
"renewal": "Rp199.000",
"transfer": "Rp199.000",
"redemption": "Rp1.350.000",
"promo": {
"registration": "Rp149.000",
"start_date": "2025-07-04T02:00:00.000000Z",
"end_date": "2025-08-31T16:59:00.000000Z"
}
}

```

### Promo Price Logic
- Use `promo.registration` jika dalam periode aktif
- Fallback ke `registration` jika tidak ada promo
- Validate `start_date` dan `end_date` untuk promo validity
- Display both promo dan current pricing dalam table

## WHMCS Integration

### Required Tables
- `tbldomainpricing`: Main pricing table
- `tblregistrars`: Default registrar lookup
- `tbldomainpricing_extensions`: Extension mapping

### Addon Hooks
- `AdminAreaPage`: Settings interface dan pricing table
- `DailyCronJob`: Optional scheduled updates
- `ClientAreaPage`: Optional price display

### Configuration Storage
- Use WHMCS addon settings table
- Store JSON-encoded configuration
- Version control untuk settings migration

## Testing Strategy

### Unit Testing
- Price parsing dan calculation
- Margin application logic
- Rounding algorithm accuracy
- API response handling
- Table data population

### Integration Testing
- WHMCS database operations
- Registrar validation flow
- End-to-end update process
- Bulk action functionality
- Error recovery scenarios

### User Acceptance Testing
- Admin interface usability
- Settings persistence
- Table functionality
- Update notifications
- Performance benchmarks

## Deployment Checklist

- [ ] File permissions (755 folders, 644 files)
- [ ] Database connectivity test
- [ ] API endpoint accessibility
- [ ] Default settings configuration
- [ ] Admin user permissions
- [ ] Error logging setup
- [ ] Table view functionality
- [ ] Backup procedures

## Support & Maintenance

### Monitoring
- API response times
- Update success rates
- Table loading performance
- Error frequency
- Performance metrics

### Troubleshooting
- Check API connectivity
- Validate registrar status
- Review error logs
- Verify database permissions
- Test table data loading

### Updates
- API endpoint changes
- WHMCS version compatibility
- Table layout improvements
- Security patches
- Feature enhancements

---

**Author**: Morden Team
**Repository**: github.com/sadewadee
**Version**: 1.0.0
**Last Updated**: August 18, 2025
```

Update ini menambahkan:

1. **Domain Pricing Table Interface** - Layout komprehensif dengan kolom untuk semua jenis pricing
2. **Table Structure** - HTML structure dan PHP data format
3. **Bulk Actions** - Import dan sync functionality
4. **Enhanced File Structure** - Menambahkan pricing_table.tpl
5. **Table Features** - Selection, comparison, status indicators
6. **Implementation Details** - Pagination, responsive design, performance considerations

Interface ini memberikan kontrol granular atas pricing domain dengan visual feedback yang jelas.

## Installation path ##
