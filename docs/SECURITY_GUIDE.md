# 🔒 Hướng Dẫn Bảo Mật Hệ Thống Coupon

## 📋 Tổng Quan

Hệ thống này đã được tích hợp các biện pháp bảo mật **CHỈ TẬP TRUNG VÀO COUPON** để ngăn chặn lạm dụng, đặc biệt là:

- **Thay đổi ngày sinh nhật liên tục** để nhận coupon
- **Spam coupon khi thanh toán** (validate/apply quá nhiều lần)
- **Lạm dụng coupon** sinh nhật
- **Thay đổi thông tin cá nhân** quá thường xuyên

## 🛡️ Các Biện Pháp Bảo Mật

### 1. **Rate Limiting (Chỉ Cho Coupon)**

- **Coupon Apply**: Tối đa 5 lần áp dụng coupon trong 1 giờ
- **Coupon Validate**: Tối đa 10 lần kiểm tra coupon trong 1 giờ  
- **Coupon Use**: Tối đa 3 lần sử dụng coupon trong 1 giờ
- **Birthday Coupon Request**: Tối đa 1 lần yêu cầu trong 365 ngày

**Lưu ý**: Các API khác (book vé, tìm kiếm, xem thông tin) KHÔNG bị giới hạn!

### 2. **Fraud Detection (Phát Hiện Gian Lận)**

#### Thay Đổi Birthday
- **Giới hạn**: Tối đa 2 lần thay đổi trong 30 ngày
- **Cảnh báo**: Nếu thay đổi để trùng với ngày hiện tại
- **Block**: Tự động nếu có quá 5 hành vi đáng ngờ

#### Thay Đổi Email
- **Giới hạn**: Tối đa 3 lần thay đổi trong 30 ngày
- **Tracking**: Ghi log tất cả thay đổi với IP và User Agent

### 3. **Coupon Security (Bảo Mật Coupon)**

#### Birthday Coupon
- **Hash bảo mật**: Tạo hash dựa trên thông tin user + timestamp
- **Giới hạn thời gian**: Chỉ nhận 1 lần trong 365 ngày
- **Kiểm tra lịch sử**: Ngăn chặn nếu có thay đổi birthday gần đây

#### Coupon Validation & Usage
- **Kiểm tra tính hợp lệ**: Coupon bị đánh dấu đáng ngờ sẽ không hoạt động
- **Tracking IP**: Ghi lại IP và User Agent khi sử dụng coupon
- **Rate limiting**: Ngăn spam validate/apply coupon

### 4. **User Behavior Monitoring (Giám Sát Hành Vi)**

#### Logging System
- **User Change Logs**: Ghi lại mọi thay đổi thông tin
- **Security Events**: Ghi log các sự kiện bảo mật
- **IP Tracking**: Theo dõi IP của user

#### Automated Actions
- **Auto-block**: Tự động block user có hành vi đáng ngờ
- **Coupon Invalidation**: Vô hiệu hóa coupon của user bị block
- **Alert System**: Cảnh báo admin về hành vi đáng ngờ

## 🚀 Sử Dụng

### 1. **Chạy Quét Bảo Mật**

```bash
# Quét tất cả
php artisan security:scan

# Chỉ quét user đáng ngờ
php artisan security:scan --action=suspicious-users

# Chỉ quét lạm dụng coupon
php artisan security:scan --action=coupon-abuse

# Chỉ quét rate limits
php artisan security:scan --action=rate-limits
```

### 2. **Kiểm Tra Logs**

```bash
# Xem log bảo mật
tail -f storage/logs/laravel.log | grep "SECURITY"

# Xem log thay đổi user
tail -f storage/logs/laravel.log | grep "UserChangeLog"
```

### 3. **Kiểm Tra Database**

```sql
-- Xem các user bị block
SELECT * FROM rate_limits WHERE is_blocked = 1;

-- Xem các thay đổi đáng ngờ
SELECT * FROM user_change_logs WHERE is_suspicious = 1;

-- Xem coupon bị đánh dấu đáng ngờ
SELECT * FROM coupon_user WHERE is_suspicious = 1;
```

## ⚙️ Cấu Hình

### 1. **Rate Limiting (Chỉ Cho Coupon)**

```php
// Trong SecurityMiddleware
'coupon_apply' => ['max_attempts' => 5, 'decay_minutes' => 60],      // 5 lần/giờ
'coupon_validate' => ['max_attempts' => 10, 'decay_minutes' => 60],   // 10 lần/giờ
'coupon_use' => ['max_attempts' => 3, 'decay_minutes' => 60],         // 3 lần/giờ
'birthday_coupon_request' => ['max_attempts' => 1, 'decay_minutes' => 525600], // 1 lần/năm
```

### 2. **Fraud Detection Thresholds**

```php
// Trong SecurityService
'birthday_changes_per_month' => 2,
'email_changes_per_month' => 3,
'suspicious_actions_to_block' => 5,
'coupon_abuse_threshold' => 3, // Số lần nhận coupon trong 1 năm
```

## 🔍 Monitoring & Alerts

### 1. **Real-time Monitoring**

- **Coupon Usage**: Giám sát việc sử dụng coupon real-time
- **User Changes**: Theo dõi thay đổi thông tin
- **Rate Limits**: Theo dõi việc vượt quá giới hạn coupon

### 2. **Automated Alerts**

- **Suspicious Behavior**: Cảnh báo khi phát hiện hành vi đáng ngờ
- **Rate Limit Exceeded**: Thông báo khi vượt quá giới hạn coupon
- **User Blocked**: Báo cáo khi user bị block

### 3. **Daily Reports**

```bash
# Tạo báo cáo hàng ngày
php artisan security:report --date=today

# Tạo báo cáo tuần
php artisan security:report --period=week

# Tạo báo cáo tháng
php artisan security:report --period=month
```

## 🚨 Xử Lý Sự Cố

### 1. **User Bị Block Sai**

```bash
# Unblock user
php artisan security:unblock {user_id}

# Kiểm tra lý do block
php artisan security:check {user_id}
```

### 2. **False Positive**

```bash
# Đánh dấu hành vi là bình thường
php artisan security:whitelist {user_id} {action}

# Xem lịch sử hành vi
php artisan security:history {user_id}
```

### 3. **Emergency Override**

```bash
# Tạm thời tắt bảo mật (chỉ dùng trong trường hợp khẩn cấp)
php artisan security:disable

# Bật lại bảo mật
php artisan security:enable
```

## 📊 Metrics & Analytics

### 1. **Security Metrics**

- **Blocked Users**: Số user bị block
- **Suspicious Activities**: Số hoạt động đáng ngờ
- **False Positives**: Tỷ lệ cảnh báo sai
- **Response Time**: Thời gian phản ứng với mối đe dọa

### 2. **Performance Impact**

- **Coupon API Response Time**: Thời gian phản hồi API coupon
- **Database Queries**: Số lượng truy vấn database
- **Memory Usage**: Sử dụng bộ nhớ
- **CPU Usage**: Sử dụng CPU

## 🔧 Maintenance

### 1. **Database Cleanup**

```bash
# Dọn dẹp logs cũ (giữ 90 ngày)
php artisan security:cleanup --days=90

# Dọn dẹp rate limits cũ
php artisan security:cleanup --type=rate-limits

# Dọn dẹp user change logs cũ
php artisan security:cleanup --type=user-changes
```

### 2. **Performance Optimization**

```bash
# Tối ưu hóa database
php artisan security:optimize

# Rebuild indexes
php artisan security:rebuild-indexes
```

## 📞 Hỗ Trợ

Nếu gặp vấn đề hoặc cần hỗ trợ:

1. **Kiểm tra logs** trước tiên
2. **Chạy security scan** để phát hiện vấn đề
3. **Liên hệ admin** nếu cần hỗ trợ thêm

---

## 🎯 **Tóm Tắt: Chỉ Bảo Mật Coupon, Không Giới Hạn API**

**✅ Có Bảo Mật:**
- Validate coupon: 10 lần/giờ
- Apply coupon: 5 lần/giờ  
- Use coupon: 3 lần/giờ
- Birthday coupon: 1 lần/năm

**❌ Không Bảo Mật:**
- Book vé: Không giới hạn
- Tìm kiếm chuyến: Không giới hạn
- Xem thông tin: Không giới hạn
- Cập nhật profile: Không giới hạn

**Lưu ý**: Hệ thống bảo mật này được thiết kế để bảo vệ người dùng chân chính và ngăn chặn lạm dụng coupon. Hãy sử dụng một cách có trách nhiệm và tuân thủ các quy định về quyền riêng tư.
