#!/bin/bash

# 🚀 Script Thiết Lập Hệ Thống Tự Động Gửi Coupon
# Sử dụng: bash deploy/setup-cron.sh

set -e

echo "🎯 Bắt đầu thiết lập hệ thống tự động gửi coupon..."

# Lấy đường dẫn hiện tại của dự án
PROJECT_PATH=$(pwd)
echo "📁 Đường dẫn dự án: $PROJECT_PATH"

# 1. Thiết lập Laravel Scheduler (Khuyến nghị)
echo "⏰ Thiết lập Laravel Scheduler..."
if ! crontab -l 2>/dev/null | grep -q "schedule:run"; then
    (crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1") | crontab -
    echo "✅ Đã thêm Laravel Scheduler vào crontab"
else
    echo "ℹ️  Laravel Scheduler đã có trong crontab"
fi

# 2. Thiết lập Cron Job Trực Tiếp (Backup)
echo "🔄 Thiết lập Cron Job trực tiếp (backup)..."
if ! crontab -l 2>/dev/null | grep -q "coupons:send-birthday"; then
    (crontab -l 2>/dev/null; echo "0 9 * * * cd $PROJECT_PATH && php artisan coupons:send-birthday >> /dev/null 2>&1") | crontab -
    echo "✅ Đã thêm Cron Job gửi coupon vào crontab"
else
    echo "ℹ️  Cron Job gửi coupon đã có trong crontab"
fi

# 3. Kiểm tra crontab
echo "📋 Nội dung crontab hiện tại:"
crontab -l

# 4. Tạo thư mục logs nếu chưa có
echo "📝 Tạo thư mục logs..."
mkdir -p storage/logs
chmod 755 storage/logs

# 5. Tạo file .env.example nếu chưa có
if [ ! -f .env.example ]; then
    echo "⚙️  Tạo file .env.example..."
    cat > .env.example << 'EOF'
# Cấu hình Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# Cấu hình Queue
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90

# Cấu hình Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Cấu hình App
APP_NAME="DatVe Backend"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://your-domain.com
EOF
    echo "✅ Đã tạo file .env.example"
fi

# 6. Hướng dẫn cài đặt Supervisor
echo ""
echo "🔧 HƯỚNG DẪN CÀI ĐẶT SUPERVISOR:"
echo "1. Cài đặt Supervisor: sudo apt-get install supervisor"
echo "2. Copy file supervisor/laravel-worker.conf vào /etc/supervisor/conf.d/"
echo "3. Cập nhật đường dẫn trong file config"
echo "4. Chạy: sudo supervisorctl reread && sudo supervisorctl update"
echo "5. Kiểm tra: sudo supervisorctl status"

# 7. Hướng dẫn test
echo ""
echo "🧪 HƯỚNG DẪN TEST:"
echo "1. Test command: php artisan coupons:check-birthdays"
echo "2. Test gửi coupon: php artisan coupons:send-birthday"
echo "3. Test queue: php artisan queue:work --once"
echo "4. Kiểm tra logs: tail -f storage/logs/laravel.log"

# 8. Kiểm tra trạng thái
echo ""
echo "📊 KIỂM TRA TRẠNG THÁI:"
echo "1. Cron jobs: crontab -l"
echo "2. Queue: php artisan queue:failed"
echo "3. Scheduler: php artisan schedule:list"

echo ""
echo "🎉 Hoàn thành thiết lập hệ thống tự động gửi coupon!"
echo "💡 Hãy cập nhật file .env với thông tin thực tế của bạn"
echo "🚀 Hệ thống sẽ tự động gửi coupon mỗi ngày lúc 9:00 sáng"

