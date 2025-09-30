<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã xác thực SMS - DatVe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .otp-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            border: 2px solid #c3e6cb;
        }
        
        .otp-label {
            font-size: 16px;
            color: #155724;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .otp-code {
            font-size: 36px;
            font-weight: 700;
            color: #155724;
            letter-spacing: 8px;
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            border: 2px dashed #28a745;
            display: inline-block;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
        
        .otp-info {
            font-size: 14px;
            color: #155724;
            margin-top: 15px;
        }
        
        .phone-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .phone-info-icon {
            color: #0c5460;
            font-size: 20px;
            margin-right: 10px;
        }
        
        .phone-info-text {
            color: #0c5460;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .footer p {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .footer .brand {
            color: #28a745;
            font-weight: 600;
            font-size: 16px;
        }
        
        .security-tips {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
        }
        
        .security-tips h3 {
            color: #856404;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .security-tips ul {
            color: #856404;
            font-size: 14px;
            padding-left: 20px;
        }
        
        .security-tips li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .header, .content, .footer {
                padding: 20px;
            }
            
            .otp-code {
                font-size: 28px;
                letter-spacing: 4px;
                padding: 15px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>📱 Xác thực SMS</h1>
            <p>Mã xác thực bảo mật từ DatVe</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Xin chào! 👋
            </div>
            
            <p style="color: #6c757d; font-size: 16px; margin-bottom: 30px;">
                Bạn đang thực hiện đăng ký tài khoản DatVe bằng số điện thoại <strong>{{ $phone }}</strong>. 
                Vui lòng sử dụng mã OTP bên dưới để hoàn tất quá trình xác thực và tạo mật khẩu.
            </p>
            
            <!-- OTP Section -->
            <div class="otp-section">
                <div class="otp-label">Mã xác thực SMS của bạn</div>
                <div class="otp-code">{{ $code }}</div>
                <div class="otp-info">
                    ⏰ Mã có hiệu lực trong <strong>10 phút</strong>
                </div>
            </div>
            
            <!-- Phone Info -->
            <div class="phone-info">
                <span class="phone-info-icon">📞</span>
                <span class="phone-info-text">
                    <strong>Thông tin:</strong> Sau khi xác thực thành công, bạn sẽ được yêu cầu tạo mật khẩu để đăng nhập lần sau bằng số điện thoại và mật khẩu.
                </span>
            </div>
            
            <!-- Security Tips -->
            <div class="security-tips">
                <h3>🛡️ Lưu ý bảo mật</h3>
                <ul>
                    <li>Không chia sẻ mã OTP với bất kỳ ai</li>
                    <li>Tạo mật khẩu mạnh (ít nhất 6 ký tự)</li>
                    <li>Không sử dụng mật khẩu dễ đoán</li>
                    <li>Báo cáo ngay nếu nhận được SMS lạ</li>
                </ul>
            </div>
            
            <p style="color: #6c757d; font-size: 14px; margin-top: 30px;">
                Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua SMS này hoặc liên hệ hỗ trợ.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p class="brand">🚌 DatVe - Hệ thống đặt vé xe</p>
            <p>SMS này được gửi tự động, vui lòng không trả lời.</p>
            <p style="font-size: 12px; color: #adb5bd;">
                © {{ date('Y') }} DatVe. Tất cả quyền được bảo lưu.
            </p>
        </div>
    </div>
</body>
</html>
