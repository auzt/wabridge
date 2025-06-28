# WhatsApp PHP Bridge

Sistem PHP bridge sederhana untuk Node.js WhatsApp API yang memungkinkan aplikasi pihak ketiga mengakses WhatsApp API melalui PHP dengan autentikasi dan logging.

## Fitur

- ✅ Login sederhana dengan user admin
- ✅ Manajemen device (CRUD dengan token unik per device)
- ✅ Tampilan pesan masuk dan keluar
- ✅ Webhook receiver untuk menyimpan pesan dari Node.js
- ✅ API lengkap untuk semua fungsi Node.js
- ✅ Webhook sender ke aplikasi external
- ✅ Rate limiting dan security
- ✅ Logging lengkap (API, webhook, error)
- ✅ Dashboard admin yang responsif

## Struktur Proyek

```
whatsapp-php-bridge/
├── config/
│   ├── database.php          # Konfigurasi database MySQL
│   ├── config.php            # Konfigurasi umum aplikasi
│   └── node_api.php          # Konfigurasi koneksi ke Node.js API
├── includes/
│   ├── auth.php              # Fungsi autentikasi
│   ├── functions.php         # Fungsi utility umum
│   ├── api_client.php        # Class untuk komunikasi dengan Node.js API
│   └── webhook_handler.php   # Handler untuk menerima webhook
├── api/
│   ├── devices.php           # API management device
│   ├── messages.php          # API untuk kirim/terima pesan
│   ├── webhook.php           # API webhook endpoint
│   ├── auth.php              # API autentikasi session
│   └── status.php            # API status device/session
├── admin/
│   ├── login.php             # Halaman login
│   ├── dashboard.php         # Dashboard utama
│   ├── devices.php           # Management devices
│   ├── messages.php          # View pesan masuk/keluar
│   └── logout.php            # Logout
├── webhooks/
│   └── receiver.php          # Endpoint untuk menerima webhook dari Node.js
├── logs/                     # Directory untuk log files
├── uploads/                  # Directory untuk file upload
├── .htaccess                 # Apache rewrite rules
├── index.php                 # Redirect ke admin
└── README.md                 # Dokumentasi ini
```

## Instalasi

### 1. Persiapan Database

Jalankan script SQL yang sudah disediakan untuk membuat database dan tabel:

```sql
-- Import file database/whatsapp_bridge.sql
-- Database sudah include struktur lengkap dan data default
```

### 2. Konfigurasi PHP

1. Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'whatsapp_bridge');
define('DB_USER', 'root');
define('DB_PASS', '');
```

2. Edit `config/node_api.php`:
```php
define('NODE_API_URL', 'http://localhost:3000/api');
```

### 3. Setup Node.js API

Pastikan Node.js WhatsApp API sudah running di `http://localhost:3000`

### 4. Permissions

```bash
chmod 755 logs/
chmod 755 uploads/
chmod 644 .htaccess
```

## Penggunaan

### Login Admin

- URL: `http://localhost/whatsapp-php-bridge/admin/login.php`
- Username: `admin`
- Password: `password`

**⚠️ Segera ganti password default setelah login pertama!**

### API Endpoints

Semua API memerlukan API Key di header `X-API-Key` atau `Authorization: Bearer {api_key}`

#### Device Management

```bash
# Buat device baru
POST /api/devices
{
    "device_name": "My Device",
    "session_id": "optional_custom_id",
    "webhook_url": "https://myapp.com/webhook",
    "config": {
        "countryCode": "62",
        "autoRead": true
    }
}

# Get semua device
GET /api/devices

# Update device
PUT /api/devices
{
    "device_id": 1,
    "webhook_url": "https://newwebhook.com"
}

# Hapus device
DELETE /api/devices
{
    "device_id": 1
}
```

#### Authentication & Session

```bash
# Get status session
GET /api/auth?action=status

# Get QR Code
GET /api/auth?action=qr

# Connect session
POST /api/auth
{
    "action": "connect"
}

# Disconnect session
POST /api/auth
{
    "action": "disconnect"
}

# Restart session
POST /api/auth
{
    "action": "restart"
}
```

#### Send Messages

```bash
# Kirim pesan teks
POST /api/messages
{
    "action": "send_text",
    "to": "628123456789",
    "text": "Hello World!",
    "options": {
        "delay": 1000
    }
}

# Kirim ke multiple nomor
POST /api/messages
{
    "action": "send_text",
    "to": ["628123456789", "628987654321"],
    "text": "Broadcast message"
}

# Kirim media
POST /api/messages
{
    "action": "send_media",
    "to": "628123456789",
    "media_url": "https://example.com/image.jpg",
    "type": "image",
    "caption": "Image caption"
}

# Kirim lokasi
POST /api/messages
{
    "action": "send_location",
    "to": "628123456789",
    "latitude": -6.2,
    "longitude": 106.8,
    "name": "Jakarta",
    "address": "Jakarta, Indonesia"
}

# Kirim kontak
POST /api/messages
{
    "action": "send_contact",
    "to": "628123456789",
    "contacts": [
        {
            "name": "John Doe",
            "phone": "628111222333"
        }
    ]
}
```

#### Get Messages

```bash
# Get pesan
GET /api/messages?limit=50&offset=0&direction=incoming

# Get statistik pesan
GET /api/status?action=messages&days=7
```

#### Webhook Management

```bash
# Test webhook
POST /api/webhook
{
    "action": "test",
    "webhook_url": "https://myapp.com/webhook"
}

# Update webhook URL
POST /api/webhook
{
    "action": "update_url",
    "webhook_url": "https://newwebhook.com"
}

# Get webhook stats
GET /api/webhook
```

#### Status & Health

```bash
# Health check (no auth required)
GET /api/status?action=health

# Device statistics
GET /api/status?action=device

# API usage stats
GET /api/status?action=api&hours=24
```

### Webhook Events

Bridge akan mengirim webhook ke URL yang dikonfigurasi untuk events berikut:

#### Message Received
```json
{
    "event": "message_received",
    "device_id": 1,
    "device_name": "My Device",
    "session_id": "session_123",
    "message": {
        "id": "message_id",
        "type": "text",
        "from": "628123456789",
        "to": "628987654321",
        "content": "Hello!",
        "timestamp": "2024-01-01 12:00:00"
    },
    "timestamp": "2024-01-01 12:00:00"
}
```

#### Connection Update
```json
{
    "event": "connection_update",
    "device_id": 1,
    "device_name": "My Device",
    "session_id": "session_123",
    "status": "connected",
    "node_status": "CONNECTED",
    "phone_number": "628987654321",
    "timestamp": "2024-01-01 12:00:00"
}
```

#### QR Code
```json
{
    "event": "qr_code",
    "device_id": 1,
    "device_name": "My Device",
    "session_id": "session_123",
    "qr_code": "qr_code_string",
    "timestamp": "2024-01-01 12:00:00"
}
```

#### Auth Failure
```json
{
    "event": "auth_failure",
    "device_id": 1,
    "device_name": "My Device",
    "session_id": "session_123",
    "reason": "banned",
    "status": "banned",
    "timestamp": "2024-01-01 12:00:00"
}
```

## Status Mapping

Bridge memetakan status dari Node.js API:

| Node.js Status | Bridge Status | Deskripsi |
|---------------|---------------|-----------|
| CONNECTING | connecting | Sedang menghubungkan |
| CONNECTED | connected | Terhubung dan siap |
| DISCONNECTED | disconnected | Terputus |
| BANNED | banned | Nomor dibanned WhatsApp |
| QR_GENERATED | connecting | Menunggu scan QR |

## Security Features

- ✅ API Key authentication per device
- ✅ Rate limiting (100 req/menit default)
- ✅ CSRF protection untuk admin panel
- ✅ Input validation dan sanitization
- ✅ SQL injection protection (prepared statements)
- ✅ XSS protection
- ✅ File upload validation
- ✅ Access control untuk direktori sensitif

## Logging

System mencatat semua aktivitas:

- `logs/api_YYYY-MM-DD.log` - API calls
- `logs/error_YYYY-MM-DD.log` - Error logs
- `logs/info_YYYY-MM-DD.log` - Info logs
- Database: `api_logs`, `webhook_logs` tables

## Configuration

Edit `config/config.php` untuk mengubah:

- Session timeout
- File upload limits
- Rate limiting
- Webhook settings
- Logging levels

## Troubleshooting

### Node.js API tidak terdeteksi
- Pastikan Node.js API running di port yang benar
- Cek konfigurasi `NODE_API_URL` di `config/node_api.php`
- Test manual: `curl http://localhost:3000/api/health`

### Database connection error
- Cek kredensial database di `config/database.php`
- Pastikan MySQL service running
- Verify database dan tabel sudah dibuat

### Webhook tidak diterima
- Cek URL webhook di device settings
- Verify webhook endpoint dapat diakses
- Check webhook logs di database

### Permission denied
- Set proper file permissions
- Pastikan direktori `logs/` dan `uploads/` writable
- Check Apache/Nginx user permissions

## API Rate Limits

- Global: 100 requests/menit per IP
- Auth endpoints: 5 requests/15 menit
- Message sending: 60 messages/menit per session
- File upload: 10 uploads/menit

## Development

Untuk development mode, edit `config/config.php`:

```php
define('LOG_LEVEL', 'DEBUG');
ini_set('display_errors', 1);
```

## License

MIT License - Gunakan sesuka hati untuk project komersial maupun personal.

## Support

Untuk pertanyaan atau masalah:
1. Check logs di direktori `logs/`
2. Verify database connections
3. Test Node.js API health endpoint
4. Check webhook endpoint accessibility