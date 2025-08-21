# 🎂 Hệ Thống Tự Động Gửi Coupon Sinh Nhật

## 📋 Tổng Quan

Hệ thống tự động gửi coupon sinh nhật cho phép gửi mã giảm giá đặc biệt đến các user có sinh nhật trong ngày, kèm theo email chúc mừng đẹp mắt.

## 🏗️ Cấu Trúc Hệ Thống

### 1. **Database**
- **Bảng `users`**: Thêm trường `birthday` (date, nullable)
- **Bảng `coupons`**: Sử dụng trường `type` = 'birthday' để đánh dấu coupon sinh nhật
- **Bảng `coupon_user`**: Lưu trữ mối quan hệ user-coupon và trạng thái sử dụng

### 2. **Mail System**
- **`BirthdayCouponMail`**: Class gửi email với template đẹp
- **Template**: `resources/views/emails/birthday-coupon.blade.php`

### 3. **Job System**
- **`SendBirthdayCouponJob`**: Job xử lý logic gửi coupon sinh nhật
- **Queue**: Hỗ trợ xử lý bất đồng bộ

### 4. **Console Commands**
- **`coupons:send-birthday`**: Gửi coupon sinh nhật
- **`coupons:test-birthday-email`**: Test gửi email
- **`coupons:check-birthdays`**: Kiểm tra user có sinh nhật

## 🚀 Cách Sử Dụng

### 1. **Chạy Migration**
```bash
php artisan migrate
```

### 2. **Chạy Seeder**
```bash
php artisan db:seed --class=BirthdayCouponSeeder
```

### 3. **Test Hệ Thống**

#### Kiểm tra user có sinh nhật hôm nay:
```bash
php artisan coupons:check-birthdays
```

#### Test gửi email:
```bash
php artisan coupons:test-birthday-email your-email@example.com
```

#### Gửi coupon sinh nhật thực tế:
```bash
php artisan coupons:send-birthday
```

### 4. **Thiết Lập Cron Job**

Để hệ thống tự động chạy mỗi ngày, thêm vào crontab:

```bash
# Mở crontab
crontab -e

# Thêm dòng sau (chạy mỗi ngày lúc 9:00 sáng)
0 9 * * * cd /path/to/your/project && php artisan coupons:send-birthday >> /dev/null 2>&1
```

## 📧 Cấu Hình Email

### 1. **Cấu hình SMTP trong `.env`**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 2. **Test Email Configuration**
```bash
php artisan tinker
Mail::raw('Test email', function($message) { $message->to('test@example.com')->subject('Test'); });
```

## 🎯 Logic Hoạt Động

### 1. **Kiểm tra sinh nhật**
- Hệ thống kiểm tra user có `birthday` và `email` hợp lệ
- So sánh ngày-tháng sinh nhật với ngày hiện tại
- Bỏ qua user đã có coupon sinh nhật

### 2. **Gán coupon**
- Tìm coupon có `type = 'birthday'` và `is_active = true`
- Kiểm tra thời gian hiệu lực (`valid_from`, `valid_until`)
- Tạo record trong bảng `coupon_user`

### 3. **Gửi email**
- Sử dụng template HTML đẹp mắt
- Bao gồm thông tin coupon và hướng dẫn sử dụng
- Ghi log kết quả gửi email

## 🔧 Tùy Chỉnh

### 1. **Thay đổi thời gian chạy**
Sửa file `routes/console.php`:
```php
// Thay đổi logic kiểm tra sinh nhật
->whereRaw("DATE_FORMAT(birthday, '%m-%d') = ?", [$today->format('m-d')])
```

### 2. **Thêm loại coupon khác**
```php
// Trong BirthdayCouponSeeder
'type' => 'birthday_special', // Loại coupon mới
```

### 3. **Tùy chỉnh template email**
Chỉnh sửa file `resources/views/emails/birthday-coupon.blade.php`

## 📊 Monitoring & Logs

### 1. **Kiểm tra logs**
```bash
tail -f storage/logs/laravel.log
```

### 2. **Log messages quan trọng**
- `Bắt đầu kiểm tra và gửi coupon sinh nhật`
- `Đã gửi email coupon sinh nhật cho user {id}`
- `Hoàn thành gửi coupon sinh nhật. Thành công: X, Lỗi: Y`

### 3. **Kiểm tra queue**
```bash
php artisan queue:work
php artisan queue:failed
```

## 🚨 Troubleshooting

### 1. **Email không gửi được**
- Kiểm tra cấu hình SMTP
- Kiểm tra log lỗi
- Test với command `coupons:test-birthday-email`

### 2. **Job không chạy**
- Kiểm tra queue worker: `php artisan queue:work`
- Kiểm tra failed jobs: `php artisan queue:failed`
- Kiểm tra cron job có chạy không

### 3. **Coupon không được gán**
- Kiểm tra có coupon `type = 'birthday'` không
- Kiểm tra coupon có `is_active = true` không
- Kiểm tra thời gian hiệu lực

## 📈 Mở Rộng

### 1. **Thêm loại coupon khác**
- Welcome coupon cho user mới
- Loyalty coupon cho user thân thiết
- Flash sale coupon

### 2. **Thêm notification**
- Push notification
- SMS notification
- In-app notification

### 3. **Analytics**
- Thống kê coupon được sử dụng
- Tỷ lệ mở email
- Tỷ lệ chuyển đổi

## 🔒 Bảo Mật

### 1. **Rate limiting**
- Giới hạn số email gửi mỗi giờ
- Giới hạn số lần gửi cho mỗi user

### 2. **Validation**
- Kiểm tra email hợp lệ
- Kiểm tra user có quyền nhận coupon
- Tránh spam và duplicate

### 3. **Logging**
- Ghi log tất cả hoạt động
- Theo dõi lỗi và exception
- Audit trail cho compliance

---

## 📞 Hỗ Trợ

Nếu gặp vấn đề, hãy:
1. Kiểm tra logs trong `storage/logs/laravel.log`
2. Chạy command test để debug
3. Kiểm tra cấu hình email và database
4. Liên hệ team development nếu cần thiết
