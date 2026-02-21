# RDAS Pricing Updater - WHMCS Addon

## Overview

RDAS Pricing Updater adalah addon WHMCS yang memungkinkan Anda untuk memperbarui harga domain secara real-time dari API rdash.id dengan pengaturan margin dan pembulatan yang fleksibel.

## Features

### Core Features
- ✅ **Real-time Price Updates** - Sinkronisasi harga domain dari API rdash.id
- ✅ **Flexible Margin Settings** - Pengaturan margin dalam persentase atau nilai tetap
- ✅ **Multiple Rounding Options** - Berbagai opsi pembulatan harga
- ✅ **Bulk Operations** - Import dan update domain secara massal
- ✅ **Automated Scheduling** - Penjadwalan otomatis (harian/mingguan)
- ✅ **Comprehensive Logging** - Sistem logging lengkap untuk tracking
- ✅ **AJAX Interface** - Interface yang responsif dengan AJAX
- ✅ **API Testing** - Tool untuk testing koneksi API

### Dashboard Features
- 📊 **Overview Statistics** - Statistik domain dan update
- 🔄 **Quick Actions** - Aksi cepat untuk update harga
- 📈 **Update History** - Riwayat update harga
- ⚠️ **Error Monitoring** - Monitoring error dan peringatan

## Installation

### Requirements
- WHMCS 7.0 atau lebih tinggi
- PHP 7.2 atau lebih tinggi
- cURL extension enabled
- MySQL/MariaDB database

### Installation Steps

1. **Download & Extract**
   ```bash
   # Extract addon ke direktori WHMCS
   /path/to/whmcs/modules/addons/rdas_pricing_updater/
   ```

2. **Activate Addon**
   - Login ke WHMCS Admin Area
   - Navigate ke `Setup > Addon Modules`
   - Find "RDAS Pricing Updater" dan klik `Activate`
   - Configure permissions sesuai kebutuhan

3. **Initial Configuration**
   - Go to `Addons > RDAS Pricing Updater`
   - Configure settings di Settings page
   - Test API connection di API Test page

## Configuration

### Basic Settings

| Setting | Description | Default |
|---------|-------------|----------|
| **Default Margin Type** | Tipe margin (percentage/fixed) | percentage |
| **Default Margin Value** | Nilai margin default | 20 |
| **Rounding Option** | Opsi pembulatan harga | up_1000 |
| **Custom Rounding** | Nilai pembulatan custom | 1000 |
| **Update Frequency** | Frekuensi auto-update | manual |
| **Log Level** | Level logging | errors |

### Margin Types

- **Percentage**: Margin dalam persentase (contoh: 20% = 20)
- **Fixed**: Margin dalam nilai tetap (contoh: Rp 5000)

### Rounding Options

- **none**: Tanpa pembulatan
- **up_1000**: Pembulatan ke atas kelipatan 1000
- **up_5000**: Pembulatan ke atas kelipatan 5000
- **nearest_1000**: Pembulatan ke terdekat kelipatan 1000
- **custom**: Pembulatan custom sesuai nilai yang ditentukan

## Usage

### Dashboard

Dashboard memberikan overview lengkap tentang:
- Total TLD yang tersedia
- Waktu update terakhir
- Frekuensi update yang dikonfigurasi
- Jumlah error yang terjadi
- Riwayat update terbaru
- Quick actions untuk operasi cepat

### Pricing Table

Pricing Table menampilkan:
- Daftar semua TLD
- Harga saat ini vs harga dari API
- Margin yang diterapkan
- Status promo dan tanggal berlaku
- Aksi untuk sync individual atau bulk

### API Testing

API Test page memungkinkan:
- Test koneksi ke rdash.id API
- Validasi response data
- Debugging masalah koneksi
- Monitoring status API

### Logs

Log page menampilkan:
- Riwayat semua aktivitas
- Error dan warning messages
- Detail update yang dilakukan
- Filtering berdasarkan tipe log

## API Integration

### Endpoint
```
GET https://api.rdash.id/api/domain-prices?currency=IDR
```

### Response Format
```json
{
  ".com": {
    "register": {
      "promo": 150000,
      "current": 180000
    },
    "renew": {
      "promo": 160000,
      "current": 190000
    },
    "transfer": {
      "promo": 150000,
      "current": 180000
    },
    "redemption": {
      "promo": null,
      "current": 500000
    },
    "promo_dates": "2024-01-01 to 2024-12-31"
  }
}
```

## Troubleshooting

### Common Issues

#### 1. Empty Dashboard Page
**Problem**: Dashboard menampilkan halaman kosong

**Solution**: 
- Addon telah diperbaiki dengan fallback dashboard
- Deactivate dan reactivate addon jika masih bermasalah
- Check error logs di WHMCS

#### 2. API Connection Failed
**Problem**: Tidak bisa connect ke rdash.id API

**Solution**:
- Check internet connection
- Verify firewall settings
- Test API endpoint manually
- Check cURL extension

#### 3. Database Errors
**Problem**: Error saat mengakses database

**Solution**:
- Verify database connection
- Check table permissions
- Reactivate addon untuk recreate tables

### Debug Mode

Untuk debugging, set log level ke "all" di Settings page untuk mendapatkan informasi detail.

## File Structure

```
rdas_pricing_updater/
├── rdas_pricing_updater.php     # Main addon file
├── hooks.php                    # WHMCS hooks
├── lib/
│   └── functions.php           # Helper functions
├── pages/
│   ├── dashboard.php           # Dashboard page
│   ├── dashboard_simple.php    # Fallback dashboard
│   ├── settings.php            # Settings page
│   ├── pricing.php             # Pricing table page
│   ├── api_test.php           # API testing page
│   └── logs.php               # Logs page
├── assets/
│   ├── css/
│   │   └── style.css          # Stylesheet
│   └── js/
│       └── script.js          # JavaScript
├── templates/
│   ├── pricing_table.tpl      # Pricing table template
│   └── settings.tpl           # Settings template
├── docs/
│   └── guidelines.md          # Development guidelines
├── CHANGELOG.md               # Version history
├── BUGFOUND.md               # Bug tracking
└── README.md                 # This file
```

## Version History

### v1.0.1 (2024-01-15)
- **Fixed**: Empty dashboard page issue
- **Fixed**: CSS/JS asset loading problems
- **Added**: Fallback dashboard mechanism
- **Improved**: Error handling and debugging

### v1.0.0 (2024-01-01)
- Initial release
- Core functionality implementation
- Basic dashboard and settings

## Support

Untuk support dan pertanyaan:
- **Email**: support@morden.com
- **GitHub**: https://github.com/sadewadee
- **Documentation**: Check docs/ folder

## License

Copyright (c) 2025, Morden Team. All rights reserved.

## Contributing

1. Follow WHMCS addon development best practices
2. Update CHANGELOG.md untuk setiap perubahan
3. Document bugs di BUGFOUND.md
4. Test thoroughly sebelum release
5. Follow semantic versioning (major.minor.patch)

---

**Note**: Addon ini telah diperbaiki untuk mengatasi masalah halaman kosong dan menggunakan fallback mechanism untuk memastikan dashboard selalu dapat diakses. 🙏