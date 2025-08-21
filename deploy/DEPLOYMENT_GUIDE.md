# 🚀 Hướng Dẫn Deploy Hệ Thống Tự Động Gửi Coupon

## 📋 **Tổng Quan**

Hướng dẫn này sẽ giúp bạn deploy hệ thống tự động gửi coupon sinh nhật lên server production một cách an toàn và hiệu quả.

## 🎯 **Yêu Cầu Hệ Thống**

- **OS**: Ubuntu 18.04+ / CentOS 7+ / Debian 9+
- **PHP**: 8.2+
- **Database**: MySQL 8.0+ / PostgreSQL 12+
- **Web Server**: Nginx / Apache
- **Queue**: Database / Redis (khuyến nghị)

## 🔧 **Bước 1: Chuẩn Bị Server**

### 1.1 Cài đặt dependencies
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl

# CentOS/RHEL
sudo yum install -y epel-release
sudo yum install -y php php-fpm php-mysql php-xml php-mbstring php-curl php-zip php-gd php-bcmath php-intl
```

### 1.2 Cài đặt Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 1.3 Cài đặt Supervisor
```bash
# Ubuntu/Debian
sudo apt install -y supervisor

# CentOS/RHEL
sudo yum install -y supervisor
sudo systemctl enable supervisord
sudo systemctl start supervisord
```

## 🚀 **Bước 2: Deploy Code**

### 2.1 Clone/Pull code
```bash
cd /var/www/
git clone your-repository.git datve-backend
cd datve-backend
```

### 2.2 Cài đặt dependencies
```bash
composer install --no-dev --optimize-autoloader
npm install --production
npm run build
```

### 2.3 Cấu hình environment
```bash
cp .env.example .env
# Chỉnh sửa .env với thông tin thực tế
nano .env
```

### 2.4 Tạo key và optimize
```bash
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.5 Chạy migration
```bash
php artisan migrate --force
php artisan db:seed --class=BirthdayCouponSeeder --force
```

## ⚙️ **Bước 3: Cấu Hình Hệ Thống**

### 3.1 Thiết lập cron jobs
```bash
# Chạy script tự động
bash deploy/setup-cron.sh

# Hoặc thiết lập thủ công
crontab -e

# Thêm dòng sau:
* * * * * cd /var/www/datve-backend && php artisan schedule:run >> /dev/null 2>&1
0 9 * * * cd /var/www/datve-backend && php artisan coupons:send-birthday >> /dev/null 2>&1
```

### 3.2 Cấu hình Supervisor
```bash
# Copy file config
sudo cp supervisor/laravel-worker.conf /etc/supervisor/conf.d/

# Cập nhật đường dẫn trong file
sudo nano /etc/supervisor/conf.d/laravel-worker.conf

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
sudo supervisorctl start laravel-scheduler
```

### 3.3 Kiểm tra trạng thái
```bash
sudo supervisorctl status
```

## 📧 **Bước 4: Cấu Hình Email**

### 4.1 Cấu hình SMTP trong .env
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="DatVe Backend"
```

### 4.2 Test email configuration
```bash
php artisan tinker
Mail::raw('Test email from server', function($message) { 
    $message->to('test@example.com')->subject('Test Email'); 
});
```

## 🧪 **Bước 5: Test Hệ Thống**

### 5.1 Test commands
```bash
# Kiểm tra user sinh nhật
php artisan coupons:check-birthdays

# Test gửi coupon (chỉ development)
php artisan coupons:test-birthday-email your-email@example.com

# Gửi coupon thực tế
php artisan coupons:send-birthday
```

### 5.2 Test queue
```bash
# Kiểm tra queue
php artisan queue:work --once

# Kiểm tra failed jobs
php artisan queue:failed
```

### 5.3 Test scheduler
```bash
# Xem danh sách scheduled tasks
php artisan schedule:list

# Test chạy scheduler
php artisan schedule:run
```

## 📊 **Bước 6: Monitoring & Logs**

### 6.1 Kiểm tra logs
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Queue worker logs
tail -f storage/logs/worker.log

# Scheduler logs
tail -f storage/logs/scheduler.log
```

### 6.2 Kiểm tra trạng thái
```bash
# Cron jobs
crontab -l

# Supervisor status
sudo supervisorctl status

# Queue status
php artisan queue:failed
```

## 🔒 **Bước 7: Bảo Mật**

### 7.1 File permissions
```bash
sudo chown -R www-data:www-data /var/www/datve-backend
sudo chmod -R 755 /var/www/datve-backend
sudo chmod -R 775 /var/www/datve-backend/storage
sudo chmod -R 775 /var/www/datve-backend/bootstrap/cache
```

### 7.2 Firewall
```bash
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

## 🚨 **Troubleshooting**

### Email không gửi được
```bash
# Kiểm tra cấu hình SMTP
php artisan tinker
config('mail')

# Kiểm tra logs
tail -f storage/logs/laravel.log | grep -i mail
```

### Queue không hoạt động
```bash
# Kiểm tra supervisor
sudo supervisorctl status

# Restart worker
sudo supervisorctl restart laravel-worker:*

# Kiểm tra failed jobs
php artisan queue:failed
```

### Cron job không chạy
```bash
# Kiểm tra crontab
crontab -l

# Test command
cd /var/www/datve-backend && php artisan coupons:check-birthdays

# Kiểm tra logs
tail -f /var/log/syslog | grep CRON
```

## 📈 **Maintenance**

### Backup database
```bash
# Tạo backup script
nano /root/backup-db.sh

#!/bin/bash
mysqldump -u username -p database_name > /backup/db_$(date +%Y%m%d_%H%M%S).sql

# Thêm vào crontab (backup hàng ngày lúc 2:00 sáng)
0 2 * * * /root/backup-db.sh
```

### Update code
```bash
cd /var/www/datve-backend
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart laravel-worker:*
```

## 🎉 **Hoàn Thành**

Sau khi hoàn thành tất cả các bước trên, hệ thống của bạn sẽ:

✅ **Tự động gửi coupon sinh nhật** mỗi ngày lúc 9:00 sáng  
✅ **Xử lý queue bất đồng bộ** với Supervisor  
✅ **Logging đầy đủ** cho monitoring  
✅ **Bảo mật và ổn định** cho production  

## 📞 **Hỗ Trợ**

Nếu gặp vấn đề:
1. Kiểm tra logs trong `storage/logs/`
2. Chạy commands test để debug
3. Kiểm tra trạng thái supervisor và cron
4. Liên hệ team development nếu cần thiết

---

**Chúc bạn deploy thành công! 🚀**
