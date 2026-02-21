# RDAS Pricing Updater - Troubleshooting

## Masalah: Halaman Addon Blank

### Kemungkinan Penyebab dan Solusi:

#### 1. **Error Sintaks PHP**
✅ **Status**: Diperbaiki
- File utama dan dashboard telah diperbaiki
- Semua file lolos PHP syntax check

#### 2. **Database Table Belum Dibuat**
**Symptoms**: Dashboard menampilkan "Table not created"
**Solusi**:
1. Deactivate addon di WHMCS Admin → Setup → Addon Modules
2. Activate kembali addon
3. Pastikan table `mod_rdas_pricing_updater_log` terbuat

#### 3. **File Permission Issues**
**Symptoms**: File tidak dapat diakses
**Solusi**:
```bash
# Set permission yang benar
chmod 644 rdas_pricing_updater.php
chmod 644 lib/functions.php
chmod 644 pages/*.php
chmod -R 755 assets/
```

#### 4. **Missing Dependencies**
**Check**:
- File `lib/functions.php` ada
- Folder `pages/` dan semua file di dalamnya ada
- Folder `assets/css/` dan `assets/js/` ada

#### 5. **WHMCS Version Compatibility**
**Minimum Requirements**: WHMCS 7.0+
**Check**: Pastikan WHMCS version compatible

#### 6. **Debug Steps**

**Step 1: Check Error Logs**
```php
// Add to top of rdas_pricing_updater.php temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**Step 2: Check Database Connection**
```sql
-- Check if table exists
SHOW TABLES LIKE 'mod_rdas_pricing_updater_log';

-- Check if addon settings exist
SELECT * FROM tbladdonmodules WHERE module = 'rdas_pricing_updater';
```

**Step 3: Manual Table Creation** (if needed)
```sql
CREATE TABLE IF NOT EXISTS `mod_rdas_pricing_updater_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `timestamp` datetime NOT NULL,
    `type` varchar(50) NOT NULL,
    `message` text NOT NULL,
    `data` text NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. **Test API Connection**
URL untuk test manual:
```
https://api.rdash.id/api/domain-prices?currency=IDR
```

#### 8. **Common Fixes**

**Fix 1: Clear WHMCS Template Cache**
- Admin → Utilities → System → Template Cache → Clear

**Fix 2: Check Module Path**
- Pastikan folder ada di: `/path/to/whmcs/modules/addons/rdas_pricing_updater/`

**Fix 3: Reactivate Addon**
- Deactivate → Save → Activate → Save

### Emergency Recovery

Jika semua gagal, ganti isi `dashboard.php` dengan versi minimal:

```php
<?php
function showDashboardPage($vars) {
    return '<div class="alert alert-success">
        <h4>RDAS Pricing Updater</h4>
        <p>Addon is working! Dashboard loaded successfully.</p>
        <p><a href="' . $vars['modulelink'] . '&action=api_test" class="btn btn-primary">Test API</a></p>
    </div>';
}
?>
```

## Masalah: Can't Change Addon Module Access Permissions

### Symptoms
- Tidak bisa mengubah permissions di WHMCS Admin → Setup → Addon Modules → Configure
- Access Control section tidak berfungsi atau tidak muncul
- Error saat menyimpan pengaturan permissions

### Root Cause
Masalah ini disebabkan oleh tidak adanya entry 'access' di database table `tbladdonmodules` untuk addon rdas_pricing_updater.

### Solution

#### Method 1: Deactivate & Reactivate Addon (Recommended)
1. Login ke WHMCS Admin Area
2. Navigate ke `Setup > Addon Modules`
3. Find "RDAS Domain Price Updater"
4. Click **Deactivate**
5. Wait 2-3 seconds
6. Click **Activate**
7. Click **Configure**
8. Set Access Control permissions
9. Click **Save Changes**

#### Method 2: Manual Database Fix (Advanced)
Jika Method 1 tidak berhasil, gunakan script fix_permissions.php:

```php
// Include di halaman admin WHMCS atau jalankan dengan context WHMCS
require_once 'modules/addons/rdas_pricing_updater/fix_permissions.php';

// Check current status
displayPermissionsStatus();

// Fix permissions
$result = fixAddonPermissions();
if ($result['success']) {
    echo "Permissions fixed successfully!";
} else {
    echo "Error: " . $result['message'];
}
```

#### Method 3: Direct Database Query (Expert Only)
```sql
-- Check current addon settings
SELECT * FROM tbladdonmodules WHERE module = 'rdas_pricing_updater';

-- Get admin role IDs
SELECT id, name FROM tbladminroles ORDER BY name;

-- Add access control entry (replace 1,2,3 with actual role IDs)
INSERT INTO tbladdonmodules (module, setting, value) 
VALUES ('rdas_pricing_updater', 'access', '1,2,3');
```

### Verification
Setelah fix:
1. Login ke WHMCS Admin
2. Go to `Setup > Addon Modules`
3. Click **Configure** pada RDAS Domain Price Updater
4. Access Control section seharusnya sudah berfungsi
5. Select admin roles yang diinginkan
6. Click **Save Changes**

### Prevention
- Selalu activate addon melalui WHMCS Admin interface
- Jangan manual edit database kecuali diperlukan
- Backup database sebelum melakukan perubahan manual

---

### Contact Support

Jika masalah persists, hubungi Morden Team dengan informasi:
1. WHMCS version
2. PHP version
3. Error log dari WHMCS
4. Screenshot masalah
5. Database query result dari tbladdonmodules
