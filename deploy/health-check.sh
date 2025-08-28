#!/bin/bash

# 🏥 Script Kiểm Tra Sức Khỏe Hệ Thống
# Sử dụng: bash deploy/health-check.sh

set -e

echo "🏥 Bắt đầu kiểm tra sức khỏe hệ thống..."

# Màu sắc cho output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Hàm hiển thị kết quả
show_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $2${NC}"
    fi
}

# Hàm hiển thị cảnh báo
show_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Hàm hiển thị thông tin
show_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# 1. Kiểm tra PHP
echo ""
echo "🔍 Kiểm tra PHP..."
php --version > /dev/null 2>&1
show_result $? "PHP đã được cài đặt"

# 2. Kiểm tra Composer
echo ""
echo "🔍 Kiểm tra Composer..."
composer --version > /dev/null 2>&1
show_result $? "Composer đã được cài đặt"

# 3. Kiểm tra Laravel
echo ""
echo "🔍 Kiểm tra Laravel..."
if [ -f "artisan" ]; then
    show_result 0 "Laravel project đã được tìm thấy"
else
    show_result 1 "Không tìm thấy Laravel project"
    exit 1
fi

# 4. Kiểm tra .env
echo ""
echo "🔍 Kiểm tra file .env..."
if [ -f ".env" ]; then
    show_result 0 "File .env đã tồn tại"
    
    # Kiểm tra APP_KEY
    if grep -q "APP_KEY=base64:" .env; then
        show_result 0 "APP_KEY đã được tạo"
    else
        show_warning "APP_KEY chưa được tạo"
    fi
    
    # Kiểm tra database config
    if grep -q "DB_HOST=" .env && grep -q "DB_DATABASE=" .env; then
        show_result 0 "Database config đã được thiết lập"
    else
        show_warning "Database config chưa đầy đủ"
    fi
    
    # Kiểm tra mail config
    if grep -q "MAIL_HOST=" .env && grep -q "MAIL_USERNAME=" .env; then
        show_result 0 "Mail config đã được thiết lập"
    else
        show_warning "Mail config chưa đầy đủ"
    fi
else
    show_warning "File .env chưa tồn tại"
fi

# 5. Kiểm tra database connection
echo ""
echo "🔍 Kiểm tra kết nối database..."
if php artisan tinker --execute="echo 'Database connection: ' . (DB::connection()->getPdo() ? 'OK' : 'FAILED');" 2>/dev/null | grep -q "OK"; then
    show_result 0 "Kết nối database thành công"
else
    show_result 1 "Không thể kết nối database"
fi

# 6. Kiểm tra migrations
echo ""
echo "🔍 Kiểm tra migrations..."
if php artisan migrate:status | grep -q "No migrations found"; then
    show_warning "Chưa có migrations nào"
else
    show_result 0 "Migrations đã được tìm thấy"
    
    # Kiểm tra pending migrations
    PENDING_MIGRATIONS=$(php artisan migrate:status | grep "Pending" | wc -l)
    if [ $PENDING_MIGRATIONS -eq 0 ]; then
        show_result 0 "Tất cả migrations đã được chạy"
    else
        show_warning "Có $PENDING_MIGRATIONS migrations chưa chạy"
    fi
fi

# 7. Kiểm tra queue
echo ""
echo "🔍 Kiểm tra queue system..."
if php artisan queue:failed | grep -q "No failed jobs"; then
    show_result 0 "Queue system hoạt động bình thường"
else
    FAILED_JOBS=$(php artisan queue:failed | grep -v "No failed jobs" | wc -l)
    show_warning "Có $FAILED_JOBS failed jobs"
fi

# 8. Kiểm tra commands
echo ""
echo "🔍 Kiểm tra commands..."
if php artisan list | grep -q "coupons:send-birthday"; then
    show_result 0 "Command gửi coupon đã được đăng ký"
else
    show_result 1 "Command gửi coupon chưa được đăng ký"
fi

if php artisan list | grep -q "coupons:check-birthdays"; then
    show_result 0 "Command kiểm tra sinh nhật đã được đăng ký"
else
    show_result 1 "Command kiểm tra sinh nhật chưa được đăng ký"
fi

# 9. Kiểm tra cron jobs
echo ""
echo "🔍 Kiểm tra cron jobs..."
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    show_result 0 "Laravel Scheduler đã được thiết lập"
else
    show_warning "Laravel Scheduler chưa được thiết lập"
fi

if crontab -l 2>/dev/null | grep -q "coupons:send-birthday"; then
    show_result 0 "Cron job gửi coupon đã được thiết lập"
else
    show_warning "Cron job gửi coupon chưa được thiết lập"
fi

# 10. Kiểm tra supervisor
echo ""
echo "🔍 Kiểm tra Supervisor..."
if command -v supervisorctl >/dev/null 2>&1; then
    show_result 0 "Supervisor đã được cài đặt"
    
    # Kiểm tra trạng thái
    if supervisorctl status >/dev/null 2>&1; then
        show_result 0 "Supervisor service đang chạy"
        
        # Kiểm tra Laravel worker
        if supervisorctl status | grep -q "laravel-worker"; then
            show_result 0 "Laravel worker đã được cấu hình"
        else
            show_warning "Laravel worker chưa được cấu hình"
        fi
    else
        show_warning "Supervisor service không chạy"
    fi
else
    show_warning "Supervisor chưa được cài đặt"
fi

# 11. Kiểm tra logs
echo ""
echo "🔍 Kiểm tra logs..."
if [ -d "storage/logs" ]; then
    show_result 0 "Thư mục logs đã tồn tại"
    
    # Kiểm tra quyền ghi
    if [ -w "storage/logs" ]; then
        show_result 0 "Có quyền ghi logs"
    else
        show_warning "Không có quyền ghi logs"
    fi
else
    show_warning "Thư mục logs chưa tồn tại"
fi

# 12. Test gửi coupon
echo ""
echo "🔍 Test hệ thống gửi coupon..."
if php artisan coupons:check-birthdays >/dev/null 2>&1; then
    show_result 0 "Command kiểm tra sinh nhật hoạt động"
else
    show_result 1 "Command kiểm tra sinh nhật không hoạt động"
fi

# 13. Kiểm tra storage permissions
echo ""
echo "🔍 Kiểm tra quyền thư mục..."
if [ -w "storage" ] && [ -w "bootstrap/cache" ]; then
    show_result 0 "Quyền thư mục storage và cache đã đúng"
else
    show_warning "Quyền thư mục storage hoặc cache chưa đúng"
fi

# 14. Tóm tắt
echo ""
echo "📊 TÓM TẮT KIỂM TRA:"
echo "=================="

# Đếm số lỗi
ERRORS=$(grep -c "❌" <<< "$(tail -n +1 $0)")
WARNINGS=$(grep -c "⚠️" <<< "$(tail -n +1 $0)")

echo "✅ Thành công: $(grep -c "✅" <<< "$(tail -n +1 $0)")"
echo "⚠️  Cảnh báo: $WARNINGS"
echo "❌ Lỗi: $ERRORS"

if [ $ERRORS -eq 0 ]; then
    echo ""
    echo -e "${GREEN}🎉 Hệ thống đã sẵn sàng hoạt động!${NC}"
    echo "🚀 Hệ thống sẽ tự động gửi coupon mỗi ngày lúc 9:00 sáng"
else
    echo ""
    echo -e "${RED}⚠️  Có một số vấn đề cần khắc phục trước khi deploy${NC}"
    echo "💡 Hãy kiểm tra và sửa các lỗi trên"
fi

echo ""
echo "🔧 HƯỚNG DẪN TIẾP THEO:"
echo "1. Sửa các lỗi nếu có"
echo "2. Chạy: bash deploy/setup-cron.sh"
echo "3. Cấu hình Supervisor"
echo "4. Test hệ thống: php artisan coupons:send-birthday"
echo "5. Kiểm tra logs: tail -f storage/logs/laravel.log"

