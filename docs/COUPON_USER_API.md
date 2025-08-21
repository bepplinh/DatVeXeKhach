# CouponUser API Documentation

## Overview
The CouponUser API manages the relationship between users and coupons, tracking which users have access to which coupons and their usage status.

## Base URL
```
/api/coupon-users
```

## Endpoints

### 1. List All Coupon Users
**GET** `/api/coupon-users`

**Query Parameters:**
- `page` (optional): Page number for pagination (default: 1)
- `per_page` (optional): Items per page (default: 15, max: 100)
- `user_id` (optional): Filter by user ID
- `coupon_id` (optional): Filter by coupon ID
- `is_used` (optional): Filter by usage status (true/false)
- `sort` (optional): Sort field (id, user_id, coupon_id, is_used, used_at, created_at, updated_at)
- `order` (optional): Sort order (asc/desc, default: desc)

**Response:**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 1,
                "user_id": 1,
                "coupon_id": 1,
                "is_used": false,
                "used_at": null,
                "created_at": "2025-01-01T00:00:00.000000Z",
                "updated_at": "2025-01-01T00:00:00.000000Z",
                "user": { ... },
                "coupon": { ... }
            }
        ],
        "current_page": 1,
        "per_page": 15,
        "total": 1
    },
    "message": "Lấy danh sách coupon-user thành công."
}
```

### 2. Create Coupon User
**POST** `/api/coupon-users`

**Request Body:**
```json
{
    "user_id": 1,
    "coupon_id": 1,
    "is_used": false,
    "used_at": null
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": 1,
        "coupon_id": 1,
        "is_used": false,
        "used_at": null,
        "created_at": "2025-01-01T00:00:00.000000Z",
        "updated_at": "2025-01-01T00:00:00.000000Z"
    },
    "message": "Tạo coupon-user thành công."
}
```

### 3. Get Coupon User by ID
**GET** `/api/coupon-users/{id}`

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": 1,
        "coupon_id": 1,
        "is_used": false,
        "used_at": null,
        "created_at": "2025-01-01T00:00:00.000000Z",
        "updated_at": "2025-01-01T00:00:00.000000Z"
    },
    "message": "Lấy thông tin coupon-user thành công."
}
```

### 4. Update Coupon User
**PUT** `/api/coupon-users/{id}`

**Request Body:**
```json
{
    "is_used": true,
    "used_at": "2025-01-01T12:00:00.000000Z"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": 1,
        "coupon_id": 1,
        "is_used": true,
        "used_at": "2025-01-01T12:00:00.000000Z",
        "created_at": "2025-01-01T00:00:00.000000Z",
        "updated_at": "2025-01-01T12:00:00.000000Z"
    },
    "message": "Cập nhật coupon-user thành công."
}
```

### 5. Delete Coupon User
**DELETE** `/api/coupon-users/{id}`

**Response:**
```json
{
    "success": true,
    "message": "Xóa coupon-user thành công."
}
```

### 6. Get Coupon Users by User ID
**GET** `/api/coupon-users/user/{userId}`

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "coupon_id": 1,
            "is_used": false,
            "used_at": null,
            "created_at": "2025-01-01T00:00:00.000000Z",
            "updated_at": "2025-01-01T00:00:00.000000Z",
            "coupon": { ... }
        }
    ],
    "message": "Lấy danh sách coupon của user thành công."
}
```

### 7. Get Coupon Users by Coupon ID
**GET** `/api/coupon-users/coupon/{couponId}`

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "coupon_id": 1,
            "is_used": false,
            "used_at": null,
            "created_at": "2025-01-01T00:00:00.000000Z",
            "updated_at": "2025-01-01T00:00:00.000000Z",
            "user": { ... }
        }
    ],
    "message": "Lấy danh sách user sử dụng coupon thành công."
}
```

### 8. Mark Coupon as Used
**POST** `/api/coupon-users/{id}/use`

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "user_id": 1,
        "coupon_id": 1,
        "is_used": true,
        "used_at": "2025-01-01T12:00:00.000000Z",
        "created_at": "2025-01-01T00:00:00.000000Z",
        "updated_at": "2025-01-01T12:00:00.000000Z"
    },
    "message": "Đánh dấu coupon đã sử dụng thành công."
}
```

## Validation Rules

### Create/Update Validation
- `user_id`: Required, integer, must exist in users table
- `coupon_id`: Required, integer, must exist in coupons table
- `is_used`: Optional, boolean
- `used_at`: Optional, valid date format

## Error Responses

### Validation Error (422)
```json
{
    "success": false,
    "message": "Dữ liệu không hợp lệ.",
    "errors": {
        "user_id": ["ID người dùng là bắt buộc."],
        "coupon_id": ["ID mã giảm giá là bắt buộc."]
    }
}
```

### Not Found Error (404)
```json
{
    "success": false,
    "message": "Coupon-user không tồn tại."
}
```

### Server Error (500)
```json
{
    "success": false,
    "message": "Có lỗi xảy ra: [error message]"
}
```

## Business Logic

1. **Duplicate Prevention**: A user cannot have the same coupon assigned multiple times
2. **Usage Tracking**: Tracks when a coupon is used by a specific user
3. **Relationship Management**: Maintains many-to-many relationship between users and coupons
4. **Status Management**: Tracks whether a coupon has been used by a specific user

## Usage Examples

### Assign a Coupon to a User
```bash
curl -X POST http://localhost:8000/api/coupon-users \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "coupon_id": 1
  }'
```

### Mark a Coupon as Used
```bash
curl -X POST http://localhost:8000/api/coupon-users/1/use
```

### Get All Coupons for a User
```bash
curl http://localhost:8000/api/coupon-users/user/1
```

### Get All Users Using a Specific Coupon
```bash
curl http://localhost:8000/api/coupon-users/coupon/1
```
