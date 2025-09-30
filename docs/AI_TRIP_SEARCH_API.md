# AI Trip Search API Documentation

## Tổng quan

API AI Trip Search cho phép người dùng tìm kiếm chuyến xe bằng ngôn ngữ tự nhiên tiếng Việt. Hệ thống sử dụng Gemini AI để trích xuất tham số từ câu hỏi và tìm kiếm chuyến xe phù hợp.

## Endpoints

### 1. POST `/api/ai/search-trips`

Tìm kiếm chuyến xe sử dụng AI với validation route đầy đủ.

**Request Body:**
```json
{
  "query": "Cho mình 2 vé Thanh Hóa -> Hà Nội tối mai, gần Mỹ Đình, dưới 250k/ghế"
}
```

**Response:**
```json
{
  "filters": {
    "origin": "Thanh Hóa",
    "destination": "Hà Nội", 
    "date": "2024-01-15",
    "time_window": "18:00-23:59",
    "seats": 2,
    "price_cap": 250000,
    "bus_type": null,
    "pickup_hint": "Mỹ Đình",
    "dropoff_hint": null
  },
  "items": [
    {
      "id": 123,
      "route_id": 45,
      "route_name": "Thanh Hóa - Hà Nội",
      "bus_id": 67,
      "day": "2024-01-15",
      "departure_time": "19:30",
      "arrival_time": "23:45",
      "duration": 255,
      "duration_text": "4h15m",
      "price": 200000,
      "total_seats": 40,
      "seats_booked": 15,
      "seats_locked": 2,
      "available_seats": 23,
      "bus": {
        "name": "Xe khách VIP",
        "code": "VIP001",
        "plate_number": "29A-12345",
        "type": "Limousine"
      },
      "route": {
        "from_city_id": 1,
        "to_city_id": 2
      }
    }
  ]
}
```

### 2. POST `/api/ai/search-trips-by-route`

Tìm kiếm chuyến xe với thông tin route chi tiết hơn.

**Request Body:**
```json
{
  "query": "Tìm chuyến xe từ Hà Nội đi TP.HCM ngày mai sáng"
}
```

**Response:**
```json
{
  "filters": {
    "origin": "Hà Nội",
    "destination": "TP.HCM",
    "date": "2024-01-15",
    "time_window": "04:30-11:59",
    "seats": 1,
    "price_cap": null,
    "bus_type": null,
    "pickup_hint": null,
    "dropoff_hint": null,
    "route_ids": [12, 13, 14]
  },
  "items": [...],
  "route_count": 3
}
```

## Các tham số AI có thể hiểu

### Địa điểm
- Tên thành phố: "Hà Nội", "TP.HCM", "Đà Nẵng"
- Tên tỉnh: "Thanh Hóa", "Nghệ An", "Quảng Ninh"
- Tên quận/huyện: "Mỹ Đình", "Cầu Giấy"

### Thời gian
- Ngày: "hôm nay", "mai", "ngày mai", "thứ bảy tuần này"
- Khung giờ: "sáng", "chiều", "tối", "04:30-11:59"

### Số lượng
- Số vé: "2 vé", "3 người", "1 ghế"

### Loại xe
- "giường nằm", "limousine", "ghế ngồi"

### Giá cả
- "dưới 250k", "<= 300000", "khoảng 200 nghìn"

## Xử lý lỗi

### 422 - Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "query": [
      "Không tìm thấy tuyến đường từ Hà Nội đến Tokyo. Vui lòng chọn tuyến đường khác."
    ]
  }
}
```

### 500 - Server Error
```json
{
  "message": "Đã có lỗi khi xử lý yêu cầu tìm chuyến."
}
```

## Cải tiến mới

### PlaceResolverService
- `findRouteId()`: Tìm route_id từ text địa điểm
- `findAllRouteIds()`: Tìm tất cả route_id có thể
- `hasRoute()`: Kiểm tra route có tồn tại không

### Validation Route
- Kiểm tra route tồn tại trước khi search
- Thông báo lỗi rõ ràng khi không tìm thấy route
- Hỗ trợ tìm kiếm fuzzy cho địa danh

## Ví dụ sử dụng

```bash
# Tìm chuyến xe cơ bản
curl -X POST http://localhost:8000/api/ai/search-trips \
  -H "Content-Type: application/json" \
  -d '{"query": "Mai có chuyến nào từ Hà Nội đi Thanh Hóa không?"}'

# Tìm chuyến xe với filter phức tạp
curl -X POST http://localhost:8000/api/ai/search-trips \
  -H "Content-Type: application/json" \
  -d '{"query": "2 vé Hà Nội -> TP.HCM tối mai, limousine, dưới 500k"}'

# Tìm chuyến xe với thông tin route
curl -X POST http://localhost:8000/api/ai/search-trips-by-route \
  -H "Content-Type: application/json" \
  -d '{"query": "Sáng mai có xe nào từ Đà Nẵng đi Huế không?"}'
```
