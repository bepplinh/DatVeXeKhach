# PrivateChannel Structure for Seat Selection System

## Tổng quan

Hệ thống PrivateChannel được tổ chức theo cấu trúc đơn giản để phù hợp với mô hình dữ liệu:
- Mỗi Trip có một channel duy nhất
- Tất cả seat events trong trip được broadcast trên cùng một channel
- Đơn giản hóa việc quản lý và subscribe

## Cấu trúc Channel

### Channel cho toàn bộ Trip
```
private.trip.{tripId}
```

**Ví dụ:**
- `private.trip.123` - Channel cho toàn bộ trip 123

**Mục đích:**
- Broadcast tất cả seat events trong trip (selecting, unselecting, booked)
- Client có thể subscribe vào trip để nhận updates real-time cho tất cả ghế
- Đơn giản hóa việc quản lý channels

## Events và Channels

### SeatSelecting Event
```php
// Broadcast trên channel: private.trip.{tripId}

SeatSelecting::dispatch(
    tripId: 123,
    seatId: 1,
    byToken: 'user_token',
    byUserId: 789
);
```

### SeatBooked Event
```php
// Broadcast trên channel: private.trip.{tripId}

SeatBooked::dispatch(
    tripId: 123,
    seatId: 1,
    bookingId: 999,
    userId: 789
);
```

### SeatUnselecting Event
```php
// Broadcast trên channel: private.trip.{tripId}

SeatUnselecting::dispatch(
    tripId: 123,
    seatId: 1,
    byToken: 'user_token',
    byUserId: 789
);
```

## Cách sử dụng ở Client

### 1. Subscribe vào Trip
```javascript
// Laravel Echo
Echo.private(`trip.123`)
    .listen('seat.selecting', (e) => {
        console.log('Seat selecting:', e);
    })
    .listen('seat.booked', (e) => {
        console.log('Seat booked:', e);
    })
    .listen('seat.unselecting', (e) => {
        console.log('Seat unselecting:', e);
    });
```

### 2. Subscribe vào toàn bộ Trip
```javascript
// Laravel Echo
Echo.private(`trip.123`)
    .listen('seat.selecting', (e) => {
        console.log('Seat selecting in trip:', e);
    })
    .listen('seat.booked', (e) => {
        console.log('Seat booked in trip:', e);
    })
    .listen('seat.unselecting', (e) => {
        console.log('Seat unselecting in trip:', e);
    });
```

## Authorization

### 1. Channel Authorization cho Bus cụ thể
```php
// routes/channels.php
Broadcast::channel('trip.{tripId}.bus.{busId}', function ($user, $tripId, $busId) {
    // Kiểm tra user có quyền truy cập trip và bus này không
    return $user->canAccessTrip($tripId) && $user->canAccessBus($busId);
});
```

### 2. Channel Authorization cho Trip
```php
// routes/channels.php
Broadcast::channel('trip.{tripId}', function ($user, $tripId) {
    // Kiểm tra user có quyền truy cập trip này không
    return $user->canAccessTrip($tripId);
});
```

## Lợi ích của cấu trúc này

1. **Phân tách rõ ràng**: Mỗi bus có channel riêng, tránh conflict
2. **Scalability**: Có thể dễ dàng mở rộng cho nhiều buses
3. **Security**: PrivateChannel đảm bảo chỉ user có quyền mới nhận được events
4. **Flexibility**: Client có thể subscribe vào level phù hợp với nhu cầu
5. **Real-time updates**: Seat selection/booking được broadcast ngay lập tức

## Migration từ cấu trúc cũ

Cấu trúc cũ: `private.trips.{tripId}.buses.{busId}`
Cấu trúc mới: `private.trip.{tripId}.bus.{busId}`

**Thay đổi:**
- `trips` → `trip` (singular)
- `buses` → `bus` (singular)
- Thêm channel `trip.{tripId}` để theo dõi toàn bộ trip
