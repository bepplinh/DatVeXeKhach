<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chúc mừng sinh nhật!</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .birthday-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .coupon-section {
            background-color: #f8f9fa;
            border: 2px dashed #28a745;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .coupon-code {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            background-color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 0;
            border: 2px solid #28a745;
        }
        .discount-info {
            background-color: #e8f5e8;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
        }
        .btn {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #218838;
        }
        .highlight {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="birthday-icon">🎂</div>
            <h1>Chúc mừng sinh nhật!</h1>
            <p>Chào {{ $user->name ?? $user->username }}, chúc bạn một ngày sinh nhật thật vui vẻ và hạnh phúc!</p>
        </div>

        <p>Xin chào <strong>{{ $user->name ?? $user->username }}</strong>,</p>
        
        <p>Nhân dịp sinh nhật của bạn, chúng tôi xin gửi tặng một <span class="highlight">mã giảm giá đặc biệt</span> để bạn có thể tận hưởng những chuyến đi tuyệt vời!</p>

        <div class="coupon-section">
            <h2>🎁 Mã giảm giá sinh nhật của bạn</h2>
            <div class="coupon-code">{{ $coupon->code }}</div>
            <p><strong>{{ $coupon->name }}</strong></p>
            <p>{{ $coupon->description }}</p>
        </div>

        <div class="discount-info">
            <h3>💡 Chi tiết mã giảm giá:</h3>
            <ul>
                @if($coupon->discount_type == 'percentage')
                    <li>Giảm <strong>{{ $coupon->discount_value }}%</strong> cho đơn hàng</li>
                @else
                    <li>Giảm <strong>{{ number_format($coupon->discount_value) }} VNĐ</strong> cho đơn hàng</li>
                @endif
                @if($coupon->minimum_order_amount > 0)
                    <li>Áp dụng cho đơn hàng từ <strong>{{ number_format($coupon->minimum_order_amount) }} VNĐ</strong></li>
                @endif
                @if($coupon->discount_type == 'percentage')
                    <li>Giảm <strong>{{ $coupon->discount_value }}%</strong> cho đơn hàng</li>
                @else
                    <li>Giảm <strong>{{ number_format($coupon->discount_value) }} VNĐ</strong> cho đơn hàng</li>
                @endif
            </ul>
        </div>

        <p><strong>⏰ Thời gian sử dụng:</strong></p>
        <ul>
            @if($coupon->valid_from)
                <li>Từ: <strong>{{ \Carbon\Carbon::parse($coupon->valid_from)->format('d/m/Y H:i') }}</strong></li>
            @endif
            @if($coupon->valid_until)
                <li>Đến: <strong>{{ \Carbon\Carbon::parse($coupon->valid_until)->format('d/m/Y H:i') }}</strong></li>
            @endif
        </ul>

        <p><strong>📋 Cách sử dụng:</strong></p>
        <ol>
            <li>Đăng nhập vào tài khoản của bạn</li>
            <li>Chọn chuyến xe bạn muốn đặt</li>
            <li>Nhập mã <strong>{{ $coupon->code }}</strong> vào ô "Mã giảm giá"</li>
            <li>Nhấn "Áp dụng" để sử dụng</li>
        </ol>

        <div style="text-align: center;">
            <a href="{{ config('app.url') }}" class="btn">Đặt vé ngay</a>
        </div>

        <div class="footer">
            <p><strong>Lưu ý:</strong></p>
            <ul style="text-align: left;">
                <li>Mã giảm giá chỉ có thể sử dụng một lần</li>
                <li>Không thể chuyển nhượng hoặc hoàn tiền</li>
                <li>Áp dụng cho tất cả các tuyến xe</li>
            </ul>
            
            <p style="margin-top: 20px;">
                Nếu bạn có bất kỳ câu hỏi nào, vui lòng liên hệ với chúng tôi qua email hoặc số điện thoại hỗ trợ.
            </p>
            
            <p style="margin-top: 20px; font-size: 14px; color: #999;">
                Email này được gửi tự động, vui lòng không trả lời.
            </p>
        </div>
    </div>
</body>
</html>
