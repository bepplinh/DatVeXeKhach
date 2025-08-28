# 🚀 Hệ Thống Tự Động Gửi Coupon - Hướng Dẫn Deploy

## 📋 **Tổng Quan**

Dự án này đã được chuẩn bị đầy đủ để deploy lên server production với hệ thống tự động gửi coupon sinh nhật hoạt động ổn định.

## 🏗️ **Cấu Trúc Dự Án**

```
DatVe_Backend/
├── app/
│   ├── Console/
│   │   └── Kernel.php              # Laravel Scheduler
│   ├── Jobs/
│   │   └── SendBirthdayCouponJob.php  # Job xử lý gửi coupon
│   └── Mail/
│       └── BirthdayCouponMail.php     # Template email
├── routes/
│   └── console.php                 # Console commands
├── deploy/                         # Thư mục deploy
│   ├── setup-cron.sh              # Script thiết lập cron
│   ├── health-check.sh            # Script kiểm tra hệ thống
│   ├── production.env             # Cấu hình production
│   ├── laravel-worker.conf        # Cấu hình Supervisor
│   └── DEPLOYMENT_GUIDE.md        # Hướng dẫn chi tiết
└── docs/
    └── BIRTHDAY_COUPON_SYSTEM.md  # Tài liệu hệ thống
```

## ⚡ **Tính Năng Chính**

✅ **Tự động gửi coupon sinh nhật** mỗi ngày lúc 9:00 sáng  
✅ **Queue system** xử lý bất đồng bộ  
✅ **Laravel Scheduler** quản lý cron jobs  
✅ **Supervisor** quản lý queue workers  
✅ **Logging đầy đủ** cho monitoring  
✅ **Bảo mật cao** với validation và rate limiting  
✅ **Retry mechanism** xử lý lỗi tự động  

## 🚀 **Deploy Nhanh (5 Phút)**

### **Bước 1: Chạy Script Tự Động**
```bash
# Cấp quyền thực thi
chmod +x deploy/setup-cron.sh
chmod +x deploy/health-check.sh

# Thiết lập cron jobs
bash deploy/setup-cron.sh

# Kiểm tra sức khỏe hệ thống
bash deploy/health-check.sh
```

### **Bước 2: Cấu Hình Supervisor**
```bash
# Cài đặt Supervisor
sudo apt install supervisor

# Copy config
sudo cp supervisor/laravel-worker.conf /etc/supervisor/conf.d/

# Cập nhật đường dẫn trong file config
sudo nano /etc/supervisor/conf.d/laravel-worker.conf

# Reload và start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
sudo supervisorctl start laravel-scheduler
```

### **Bước 3: Test Hệ Thống**
```bash
# Kiểm tra user sinh nhật
php artisan coupons:check-birthdays

# Gửi coupon test
php artisan coupons:send-birthday

# Kiểm tra queue
php artisan queue:work --once
```

## 📧 **Cấu Hình Email**

### **Gmail (Khuyến nghị)**
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

### **Tạo Gmail App Password**
1. Bật 2FA cho Gmail
2. Truy cập: https://myaccount.google.com/apppasswords
3. Tạo App Password cho ứng dụng
4. Sử dụng App Password thay vì mật khẩu thường

## 🔧 **Commands Hữu Ích**

### **Kiểm Tra Hệ Thống**
```bash
# Kiểm tra sức khỏe
bash deploy/health-check.sh

# Kiểm tra cron jobs
crontab -l

# Kiểm tra supervisor
sudo supervisorctl status

# Kiểm tra queue
php artisan queue:failed
```

### **Test Hệ Thống**
```bash
# Kiểm tra user sinh nhật
php artisan coupons:check-birthdays

# Test gửi email (development)
php artisan coupons:test-birthday-email your-email@example.com

# Gửi coupon thực tế
php artisan coupons:send-birthday

# Test scheduler
php artisan schedule:run
```

### **Quản Lý Queue**
```bash
# Xem failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Work queue
php artisan queue:work --daemon
```

## 📊 **Monitoring & Logs**

### **Log Files**
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Queue worker logs
tail -f storage/logs/worker.log

# Scheduler logs
tail -f storage/logs/scheduler.log

# Supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log
```

### **Health Checks**
```bash
# Kiểm tra cron jobs
crontab -l

# Kiểm tra supervisor processes
sudo supervisorctl status

# Kiểm tra queue status
php artisan queue:failed

# Kiểm tra database connection
php artisan tinker --execute="echo DB::connection()->getPdo() ? 'OK' : 'FAILED';"
```

## 🚨 **Troubleshooting**

### **Email Không Gửi Được**
```bash
# Kiểm tra cấu hình SMTP
php artisan tinker
config('mail')

# Test gửi email
php artisan tinker
Mail::raw('Test', function($m) { $m->to('test@example.com')->subject('Test'); });

# Kiểm tra logs
tail -f storage/logs/laravel.log | grep -i mail
```

### **Queue Không Hoạt Động**
```bash
# Kiểm tra supervisor
sudo supervisorctl status

# Restart worker
sudo supervisorctl restart laravel-worker:*

# Kiểm tra failed jobs
php artisan queue:failed

# Test queue
php artisan queue:work --once
```

### **Cron Job Không Chạy**
```bash
# Kiểm tra crontab
crontab -l

# Test command
cd /path/to/project && php artisan coupons:check-birthdays

# Kiểm tra cron logs
tail -f /var/log/syslog | grep CRON
```

## 🔒 **Bảo Mật**

### **File Permissions**
```bash
# Thiết lập quyền đúng
sudo chown -R www-data:www-data /var/www/datve-backend
sudo chmod -R 755 /var/www/datve-backend
sudo chmod -R 775 /var/www/datve-backend/storage
sudo chmod -R 775 /var/www/datve-backend/bootstrap/cache
```

### **Firewall**
```bash
# Mở ports cần thiết
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

## 📈 **Maintenance**

### **Backup Database**
```bash
# Tạo backup script
nano /root/backup-db.sh

#!/bin/bash
mysqldump -u username -p database_name > /backup/db_$(date +%Y%m%d_%H%M%S).sql

# Thêm vào crontab (backup hàng ngày lúc 2:00 sáng)
0 2 * * * /root/backup-db.sh
```

### **Update Code**
```bash
cd /var/www/datve-backend
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart laravel-worker:*
```

## 🎯 **Kết Quả Mong Đợi**

Sau khi deploy thành công, hệ thống sẽ:

✅ **Tự động chạy mỗi ngày lúc 9:00 sáng**  
✅ **Gửi coupon sinh nhật cho user**  
✅ **Xử lý queue bất đồng bộ**  
✅ **Logging đầy đủ cho monitoring**  
✅ **Bảo mật và ổn định**  
✅ **Dễ dàng maintain và scale**  

## 📞 **Hỗ Trợ**

### **Khi Gặp Vấn Đề**
1. Chạy `bash deploy/health-check.sh` để kiểm tra
2. Kiểm tra logs trong `storage/logs/`
3. Chạy commands test để debug
4. Kiểm tra trạng thái supervisor và cron
5. Liên hệ team development nếu cần thiết

### **Tài Liệu Tham Khảo**
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Queue](https://laravel.com/docs/queues)
- [Laravel Scheduler](https://laravel.com/docs/scheduling)
- [Supervisor Documentation](http://supervisord.org/)

---

## 🎉 **Chúc Bạn Deploy Thành Công!**

Hệ thống của bạn đã được chuẩn bị đầy đủ và sẽ hoạt động hoàn hảo trên server production. Chỉ cần làm theo hướng dẫn trên là có thể deploy thành công!

**🚀 Hãy bắt đầu với: `bash deploy/setup-cron.sh`**

