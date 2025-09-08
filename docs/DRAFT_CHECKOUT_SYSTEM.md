# Hệ thống Draft Checkout

## Tổng quan

Hệ thống Draft Checkout cho phép người dùng lưu trữ thông tin đặt vé tạm thời trước khi thanh toán thành công. Điều này giúp cải thiện trải nghiệm người dùng và giảm tỷ lệ bỏ giỏ hàng.

## Cấu trúc Database

### Bảng `draft_checkouts`

```sql
- id: Primary key
- trip_id: ID chuyến đi
- seat_ids: JSON array các ID ghế đã chọn
- user_id: ID người dùng (nullable cho guest)
- passenger_name: Họ tên hành khách
- passenger_phone: Số điện thoại
- passenger_email: Email (nullable)
- pickup_location_id: ID điểm đón
- dropoff_location_id: ID điểm trả
- pickup_address: Địa chỉ chi tiết đón (nullable)
- dropoff_address: Địa chỉ chi tiết trả (nullable)
- total_price: Tổng tiền
- discount_amount: Số tiền giảm giá
- coupon_id: ID mã giảm giá (nullable)
- notes: Ghi chú (nullable)
- passenger_info: JSON thông tin bổ sung (nullable)
- status: Trạng thái (draft, processing, expired, completed)
- expires_at: Thời gian hết hạn
- completed_at: Thời gian hoàn thành (nullable)
- session_id: ID session cho guest users (nullable)
- checkout_token: Token duy nhất cho checkout
- created_at, updated_at: Timestamps
```

## API Endpoints

### 1. Tạo Draft Checkout
```
POST /api/draft-checkouts
```

**Request Body:**
```json
{
    "trip_id": 1,
    "seat_ids": [1, 2, 3],
    "passenger_name": "Nguyễn Văn A",
    "passenger_phone": "0123456789",
    "passenger_email": "user@example.com",
    "pickup_location_id": 1,
    "dropoff_location_id": 2,
    "pickup_address": "123 Đường ABC",
    "dropoff_address": "456 Đường XYZ",
    "total_price": 500000,
    "discount_amount": 50000,
    "coupon_id": 1,
    "notes": "Ghi chú đặc biệt",
    "passenger_info": {
        "cccd": "123456789",
        "date_of_birth": "1990-01-01",
        "gender": "male"
    },
    "session_id": "session_123" // Cho guest users
}
```

### 2. Lấy thông tin Draft
```
GET /api/draft-checkouts/{checkoutToken}
```

### 3. Cập nhật Draft
```
PUT /api/draft-checkouts/{checkoutToken}
```

### 4. Gia hạn Draft
```
POST /api/draft-checkouts/{checkoutToken}/extend
```

**Request Body:**
```json
{
    "minutes": 15
}
```

### 5. Hoàn thành Draft (Chuyển thành Booking)
```
POST /api/draft-checkouts/{checkoutToken}/complete
```

### 6. Hủy Draft
```
DELETE /api/draft-checkouts/{checkoutToken}
```

### 7. Danh sách Draft của User (Cần đăng nhập)
```
GET /api/draft-checkouts
```

## Workflow

### 1. Tạo Draft
1. User chọn ghế và nhập thông tin
2. Gọi API tạo draft checkout
3. Hệ thống tạo draft với thời gian hết hạn (mặc định 30 phút)
4. Trả về `checkout_token` để tracking

### 2. Cập nhật Draft
1. User có thể cập nhật thông tin trước khi thanh toán
2. Gọi API cập nhật với `checkout_token`
3. Hệ thống kiểm tra draft còn active không

### 3. Gia hạn Draft
1. Nếu user cần thêm thời gian, có thể gia hạn
2. Gọi API gia hạn (tối đa 60 phút)
3. Hệ thống cập nhật `expires_at`

### 4. Thanh toán thành công
1. Sau khi thanh toán thành công, gọi API complete
2. Hệ thống tạo booking, booking_items, tickets
3. Cập nhật trạng thái ghế
4. Đánh dấu draft là completed

### 5. Hủy Draft
1. User có thể hủy draft bất kỳ lúc nào
2. Hệ thống giải phóng lock ghế
3. Đánh dấu draft là expired

## Tự động dọn dẹp

### Command Line
```bash
# Dọn dẹp thủ công
php artisan draft:cleanup

# Dọn dẹp với force
php artisan draft:cleanup --force
```

### Scheduled Task
Hệ thống tự động dọn dẹp draft hết hạn mỗi 15 phút thông qua Laravel Scheduler.

## Security

### Rate Limiting
- Tạo draft: 10 requests/phút
- Cập nhật draft: 20 requests/phút
- Gia hạn draft: 5 requests/phút

### Validation
- Kiểm tra ghế còn available không
- Validate thông tin hành khách
- Kiểm tra thời gian hết hạn
- Validate mã giảm giá

## Monitoring

### Thống kê
```
GET /api/draft-checkouts/stats
```

Trả về:
- Tổng số draft
- Số draft active
- Số draft hết hạn
- Số draft hoàn thành

### Logs
- Log tất cả thao tác với draft
- Track conversion rate từ draft sang booking
- Monitor thời gian trung bình hoàn thành

## Best Practices

### 1. Thời gian hết hạn
- Mặc định: 30 phút
- Có thể gia hạn tối đa 60 phút
- Tự động dọn dẹp mỗi 15 phút

### 2. Session Management
- Guest users: dùng session_id
- Logged users: dùng user_id
- Token-based tracking cho security

### 3. Error Handling
- Graceful handling khi draft hết hạn
- Retry mechanism cho network errors
- Clear error messages cho user

### 4. Performance
- Index trên các trường quan trọng
- Batch cleanup để tránh lock
- Cache thông tin trip/seat

## Integration

### Frontend
```javascript
// Tạo draft
const draft = await api.post('/draft-checkouts', {
    trip_id: 1,
    seat_ids: [1, 2],
    passenger_name: 'John Doe',
    // ... other fields
});

// Lưu checkout_token
localStorage.setItem('checkout_token', draft.data.checkout_token);

// Gia hạn draft
await api.post(`/draft-checkouts/${token}/extend`, {
    minutes: 15
});

// Hoàn thành draft
await api.post(`/draft-checkouts/${token}/complete`);
```

### Payment Gateway
```php
// Sau khi thanh toán thành công
$result = $draftService->convertToBooking($checkoutToken);

if ($result['success']) {
    // Redirect to success page
    return redirect()->route('booking.success', $result['booking']);
} else {
    // Handle error
    return back()->with('error', $result['message']);
}
```

## Troubleshooting

### Common Issues

1. **Draft hết hạn**
   - Kiểm tra `expires_at`
   - Gia hạn draft nếu cần
   - Tạo draft mới nếu quá hạn

2. **Ghế không available**
   - Kiểm tra trạng thái ghế
   - Refresh danh sách ghế
   - Chọn ghế khác

3. **Validation errors**
   - Kiểm tra format dữ liệu
   - Validate required fields
   - Check business rules

### Debug Commands
```bash
# Xem thống kê draft
php artisan draft:cleanup

# Check expired drafts
php artisan tinker
>>> App\Models\DraftCheckout::expired()->count()

# Check active drafts
>>> App\Models\DraftCheckout::active()->count()
```

