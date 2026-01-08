# 🚀 MangOyen API - Panduan Deploy ke Production

Panduan lengkap untuk deploy MangOyen API ke server production dengan HestiaCP.

---

## 📋 Prerequisites

- Server VPS dengan HestiaCP
- PHP 8.1+ dengan extensions: `mbstring`, `xml`, `curl`, `mysql`, `zip`, `gd`
- MySQL/MariaDB
- Composer
- Git
- Supervisor (untuk queue worker)

---

## 1️⃣ Setup Domain di HestiaCP

### Buat Web Domain
1. Login ke HestiaCP panel
2. Navigasi ke **WEB** → **Add Web Domain**
3. Isi domain: `api.mangoyen.com`
4. Centang **Enable SSL** dengan Let's Encrypt
5. Klik **Save**

### Ubah Document Root
```bash
# Edit nginx template atau gunakan custom config
# Document root harus mengarah ke folder public
# /home/mangoyen/web/api.mangoyen.com/public_html/public
```

---

## 2️⃣ Clone Repository

```bash
# Masuk ke server via SSH
ssh user@your-server-ip

# Navigasi ke folder web
cd /home/mangoyen/web/api.mangoyen.com

# Hapus public_html default dan clone repo
rm -rf public_html
git clone https://github.com/jharrvis/mangoyen-api.git public_html

# Masuk ke folder
cd public_html
```

---

## 3️⃣ Setup Environment

### Copy dan Edit .env
```bash
cp .env.example .env
nano .env
```

### Isi .env dengan Nilai Production:
```env
APP_NAME="MangOyen API"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.mangoyen.com
FRONTEND_URL=https://mangoyen.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mangoyen_db
DB_USERNAME=mangoyen_user
DB_PASSWORD=your_secure_password

# Mail (Google SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@mangoyen.com
MAIL_FROM_NAME="MangOyen"

# Queue (Database driver untuk production)
QUEUE_CONNECTION=database

# Midtrans
MIDTRANS_MERCHANT_ID=G123456789
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxx
MIDTRANS_SERVER_KEY=SB-Mid-server-xxx
MIDTRANS_IS_PRODUCTION=false

# WhatsApp Fonnte
WA_FONNTE_TOKEN=your-fonnte-token

# Google OAuth
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxx
GOOGLE_REDIRECT_URI=https://api.mangoyen.com/api/auth/google/callback
```

---

## 4️⃣ Install Dependencies

```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Create storage link
php artisan storage:link

# Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 5️⃣ Setup Nginx (HestiaCP)

Edit nginx config untuk domain:
```bash
nano /home/mangoyen/conf/web/api.mangoyen.com/nginx.conf_custom
```

Isi dengan:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

location ~ /\.ht {
    deny all;
}
```

Restart nginx:
```bash
sudo systemctl restart nginx
```

---

## 6️⃣ Setup Supervisor (Queue Worker)

### Install Supervisor
```bash
sudo apt install supervisor
```

### Buat Config File
```bash
sudo nano /etc/supervisor/conf.d/mangoyen-worker.conf
```

### Isi Config:
```ini
[program:mangoyen-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/mangoyen/web/api.mangoyen.com/public_html/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/home/mangoyen/web/api.mangoyen.com/public_html/storage/logs/worker.log
stopwaitsecs=3600
```

### Aktifkan Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mangoyen-worker:*
```

### Cek Status
```bash
sudo supervisorctl status
```

Output yang benar:
```
mangoyen-worker:mangoyen-worker_00   RUNNING   pid 12345, uptime 0:00:10
mangoyen-worker:mangoyen-worker_01   RUNNING   pid 12346, uptime 0:00:10
```

---

## 7️⃣ Setup Cron Job (Scheduler)

### Edit Crontab
```bash
crontab -e
```

### Tambahkan Baris Ini:
```cron
* * * * * cd /home/mangoyen/web/api.mangoyen.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Ini akan menjalankan scheduler setiap menit, termasuk:
- `adoptions:check-shipping-deadline` (setiap jam) - Auto-cancel jika deadline terlewat

---

## 8️⃣ Setup Midtrans Webhook

### Di Dashboard Midtrans:
1. Login ke https://dashboard.midtrans.com
2. Navigasi ke **Settings** → **Configuration**
3. Set **Payment Notification URL**:
   ```
   https://api.mangoyen.com/api/payments/webhook
   ```
4. **Save**

### Untuk Production:
- Ubah `MIDTRANS_IS_PRODUCTION=true` di `.env`
- Gunakan **Production** Server Key dan Client Key

---

## 9️⃣ Cache Configuration

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🔄 Update Deployment (Setelah Push ke GitHub)

Jalankan script deploy:
```bash
cd /home/mangoyen/web/api.mangoyen.com/public_html
./deploy.sh
```

Atau manual:
```bash
git pull origin master
composer install --no-dev --optimize-autoloader --ignore-platform-reqs
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

---

## 🔍 Troubleshooting

### Cek Log Laravel
```bash
tail -f storage/logs/laravel.log
```

### Cek Log Queue Worker
```bash
tail -f storage/logs/worker.log
```

### Cek Status Supervisor
```bash
sudo supervisorctl status
```

### Restart Queue Worker
```bash
sudo supervisorctl restart mangoyen-worker:*
```

### Permission Issues
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 500 Error
```bash
php artisan config:clear
php artisan cache:clear
```

---

## ✅ Checklist Deployment

- [ ] Domain SSL aktif
- [ ] `.env` terisi dengan benar
- [ ] Database migration sukses
- [ ] Storage link dibuat
- [ ] Permission storage 775
- [ ] Supervisor running
- [ ] Cron job aktif
- [ ] Midtrans webhook URL diset
- [ ] Test pembayaran di Sandbox
- [ ] Email terkirim (test dengan Mailtrap)
- [ ] WhatsApp terkirim (test dengan Fonnte)

---

## 📞 Kontak

Jika ada masalah, cek:
1. Log Laravel: `storage/logs/laravel.log`
2. Log Worker: `storage/logs/worker.log`
3. Log Nginx: `/var/log/nginx/error.log`
