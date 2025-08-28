# Hệ thống Đặt Ghế - Hướng dẫn sử dụng

## Tổng quan

Hệ thống đặt ghế mới cho phép nhiều user cùng chọn cùng một ghế và ai đặt trước thì được trước.

## Cách hoạt động

### 1. Chọn ghế (Tùy chọn)
- User có thể chọn ghế để "giữ chỗ" trong 30 giây
- Nhiều user có thể cùng chọn cùng một ghế
- Việc chọn ghế chỉ mang tính hiển thị, không ảnh hưởng đến việc đặt ghế

### 2. Đặt ghế
- User có thể đặt ghế trực tiếp mà không cần chọn trước
- Ai đặt trước thì được ghế trước
- Nếu ghế đã bị người khác đặt thì sẽ báo lỗi

## API Endpoints

### Chọn ghế
```bash
POST /api/trips/{tripId}/seats/select
Content-Type: application/json
Authorization: Bearer {token}

{
    "seat_ids": [1, 2, 3]
}
```

### Hủy chọn ghế
```bash
POST /api/trips/{tripId}/seats/unselect
Content-Type: application/json
Authorization: Bearer {token}

{
    "seat_ids": [1, 2, 3]
}
```

### Hủy tất cả ghế đang chọn
```bash
DELETE /api/trips/{tripId}/seats/unselect-all
Authorization: Bearer {token}
```

### Lấy danh sách ghế đang chọn
```bash
GET /api/trips/{tripId}/seats/selections
Authorization: Bearer {token}
```

### Kiểm tra trạng thái ghế
```bash
POST /api/trips/{tripId}/seats/check-status
Content-Type: application/json
Authorization: Bearer {token}

{
    "seat_ids": [1, 2, 3]
}
```

### Đặt ghế
```bash
POST /api/trips/{tripId}/bookings
Content-Type: application/json
Authorization: Bearer {token}

{
    "seat_ids": [1, 2, 3],
    "coupon_code": "DISCOUNT10"
}
```

### Lấy danh sách ghế đang chọn (từ booking)
```bash
GET /api/trips/{tripId}/bookings/selections
Authorization: Bearer {token}
```

### Hủy tất cả ghế đang chọn (từ booking)
```bash
DELETE /api/trips/{tripId}/bookings/selections
Authorization: Bearer {token}
```

## Ví dụ sử dụng

### Ví dụ 1: Chọn ghế rồi đặt
```javascript
// 1. Chọn ghế
const selectResponse = await fetch('/api/trips/1/seats/select', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
        seat_ids: [1, 2]
    })
});

const { session_token } = await selectResponse.json();

// 2. Đặt ghế
const bookingResponse = await fetch('/api/trips/1/bookings', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
        seat_ids: [1, 2]
    })
});
```

### Ví dụ 2: Đặt ghế trực tiếp
```javascript
// Đặt ghế mà không cần chọn trước
const bookingResponse = await fetch('/api/trips/1/bookings', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
        seat_ids: [1, 2]
    })
});
```

## Xử lý lỗi

### Ghế đã bị người khác đặt
```json
{
    "success": false,
    "message": "Một hoặc nhiều ghế đã có người khác đặt trước. Vui lòng chọn ghế khác."
}
```

### Ghế không khả dụng
```json
{
    "success": false,
    "message": "Tất cả ghế bạn muốn chọn đã không còn khả dụng."
}
```

## Lưu ý quan trọng

1. **Thời gian giữ ghế**: 30 giây
2. **Nhiều user cùng chọn**: Được phép
3. **Ai đặt trước**: Được ghế trước
4. **Không bắt buộc chọn**: Có thể đặt trực tiếp
5. **Session token**: Tự động tạo nếu không có
6. **Tự động cleanup**: Lock hết hạn được xử lý tự động khi kiểm tra tính khả dụng

## Testing

Chạy test để kiểm tra hệ thống:
```bash
php artisan test --filter=SeatSelectionFlowTest
```
