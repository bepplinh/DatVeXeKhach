# 📅 Hướng dẫn sử dụng Schedule Template Seeders

## 🎯 Mục đích

Seeder này tạo ra các lịch trình mẫu từ thứ 2 đến chủ nhật để người dùng có thể generate trips theo tuần, tháng mà không cần thêm thủ công.

## 🚀 Cách sử dụng

### 1. Chạy seeder chính (DatabaseSeeder)
```bash
php artisan db:seed
```

### 2. Chạy riêng ScheduleTemplateTripSeeder
```bash
php artisan db:seed --class=ScheduleTemplateTripSeeder
```

### 3. Chạy seeder đơn giản
```bash
php artisan db:seed --class=SimpleScheduleTemplateSeeder
```

## 📊 Lịch trình được tạo

### ScheduleTemplateTripSeeder (Đầy đủ)
- **Thứ 2**: 7 chuyến (06:00, 08:00, 10:00, 14:00, 16:00, 18:00, 20:00)
- **Thứ 3**: 7 chuyến (06:30, 08:30, 10:30, 14:30, 16:30, 18:30, 20:30)
- **Thứ 4**: 7 chuyến (07:00, 09:00, 11:00, 15:00, 17:00, 19:00, 21:00)
- **Thứ 5**: 7 chuyến (07:30, 09:30, 11:30, 15:30, 17:30, 19:30, 21:30)
- **Thứ 6**: 7 chuyến (08:00, 10:00, 12:00, 16:00, 18:00, 20:00, 22:00)
- **Thứ 7**: 7 chuyến (08:30, 10:30, 12:30, 16:30, 18:30, 20:30, 22:30)
- **Chủ nhật**: 7 chuyến (09:00, 11:00, 13:00, 17:00, 19:00, 21:00, 23:00)

**Tổng cộng**: 49 chuyến mẫu

### SimpleScheduleTemplateSeeder (Đơn giản)
- **Route ID cố định**: 1
- **Tất cả các ngày**: 7 chuyến/ngày (từ thứ 2 đến chủ nhật)
- **5 giờ cố định**: 06:00, 08:00, 12:00, 16:00, 20:00
- **2 giờ bất kỳ**: 10:00, 22:00

**Tổng cộng**: 49 chuyến mẫu (7 ngày × 7 chuyến)

## 🔧 Tính năng

### 1. Tự động phân bổ
- Luân phiên giữa các routes và buses có sẵn
- Tránh trùng lặp lịch trình

### 2. Kiểm tra trùng lặp
- Kiểm tra xem lịch trình đã tồn tại chưa
- Bỏ qua nếu đã có, tạo mới nếu chưa có

### 3. Thống kê chi tiết
- Hiển thị số lượng lịch trình theo ngày
- Báo cáo tổng số chuyến được tạo

### 4. Xử lý lỗi
- Bắt và hiển thị lỗi khi tạo lịch trình
- Cảnh báo nếu không có routes hoặc buses

## 📋 Yêu cầu trước khi chạy

1. **Routes**: Phải có ít nhất 1 route trong bảng `routes`
2. **Buses**: Phải có ít nhất 1 bus trong bảng `buses`
3. **Model**: Phải có model `ScheduleTemplateTrip`

## 🚨 Lưu ý quan trọng

### 1. Constraint Database
```sql
-- Mỗi xe chỉ có thể có 1 lịch trình cho mỗi ngày và giờ
UNIQUE(bus_id, weekday, departure_time)
```

### 2. Thứ tự chạy seeder
```php
// Trong DatabaseSeeder
$this->call([
    LocationSeeder::class,        // 1. Tạo địa điểm
    RouteSeeder::class,           // 2. Tạo tuyến đường
    BusSeeder::class,             // 3. Tạo xe
    ScheduleTemplateTripSeeder::class, // 4. Tạo lịch trình mẫu
]);
```

### 3. Xóa dữ liệu cũ (nếu cần)
```bash
# Xóa tất cả lịch trình mẫu
php artisan tinker
>>> App\Models\ScheduleTemplateTrip::truncate();

# Hoặc xóa theo ngày cụ thể
>>> App\Models\ScheduleTemplateTrip::where('weekday', 1)->delete();
```

## 🔄 Tùy chỉnh lịch trình

### 1. Thay đổi giờ khởi hành
```php
// Trong SimpleScheduleTemplateSeeder
$fixedDepartureTimes = [
    '06:00:00',  // Sáng sớm
    '08:00:00',  // Sáng
    '12:00:00',  // Trưa
    '16:00:00',  // Chiều
    '20:00:00',  // Tối
];

$additionalDepartureTimes = [
    '10:00:00',  // Giữa sáng
    '22:00:00',  // Đêm
];
```

### 2. Thay đổi số lượng chuyến
```php
// Thêm/bớt giờ trong mảng departure_times
$fixedDepartureTimes = ['06:00:00', '08:00:00', '12:00:00', '16:00:00', '20:00:00'];
$additionalDepartureTimes = ['10:00:00', '22:00:00', '14:00:00']; // Thêm 1 giờ nữa
```

### 3. Tạo lịch trình cho ngày cụ thể
```php
// Chỉ tạo cho thứ 2 và thứ 6
$schedules = [
    [
        'weekday' => 1, // Thứ 2
        'departure_times' => ['07:00:00', '19:00:00']
    ],
    [
        'weekday' => 5, // Thứ 6
        'departure_times' => ['08:00:00', '20:00:00']
    ]
];
```

## 📈 Kết quả sau khi chạy

```
Seeder hoàn thành!
✓ Đã tạo: 49 lịch trình mẫu
✓ Đã bỏ qua: 0 lịch trình (đã tồn tại)

📊 THỐNG KÊ LỊCH TRÌNH THEO NGÀY:
  Chủ nhật: 7 chuyến
  Thứ 2: 7 chuyến
  Thứ 3: 7 chuyến
  Thứ 4: 7 chuyến
  Thứ 5: 7 chuyến
  Thứ 6: 7 chuyến
  Thứ 7: 7 chuyến
  Tổng cộng: 49 chuyến

📊 THÔNG TIN CHI TIẾT:
  - Route ID cố định: 1
  - Số giờ khởi hành: 7 (5 cố định + 2 bất kỳ)
  - Số ngày trong tuần: 7 (thứ 2 đến chủ nhật)
  - Tổng lý thuyết: 49 chuyến
```

## 🎉 Lợi ích

1. **Tiết kiệm thời gian**: Không cần tạo lịch trình thủ công
2. **Đa dạng**: Nhiều giờ khởi hành khác nhau
3. **Linh hoạt**: Dễ dàng tùy chỉnh theo nhu cầu
4. **An toàn**: Kiểm tra trùng lặp và xử lý lỗi
5. **Báo cáo**: Thống kê chi tiết sau khi tạo

## 🆘 Xử lý sự cố

### Lỗi "Không có routes hoặc buses"
```bash
# Chạy seeder theo thứ tự
php artisan db:seed --class=LocationSeeder
php artisan db:seed --class=RouteSeeder
php artisan db:seed --class=BusSeeder
php artisan db:seed --class=ScheduleTemplateTripSeeder
```

### Lỗi "Duplicate entry"
```bash
# Xóa dữ liệu cũ
php artisan tinker
>>> App\Models\ScheduleTemplateTrip::truncate();
# Sau đó chạy lại seeder
```

### Lỗi "Model not found"
```bash
# Kiểm tra model có tồn tại không
php artisan tinker
>>> App\Models\ScheduleTemplateTrip::class
```
