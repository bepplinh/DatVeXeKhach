<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mã xác thực OTP</title>
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
        <h2 style="color: #333;">Mã xác thực của bạn</h2>
        <p>Xin chào!</p>
        <p>Mã OTP của bạn là: <strong style="font-size: 24px; color: #007bff;">{{ $code }}</strong></p>
        <p>Mã này có hiệu lực trong 10 phút.</p>
        <p>Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email này.</p>
        <hr style="margin: 20px 0;">
        <p style="color: #666; font-size: 12px;">Email này được gửi tự động, vui lòng không trả lời.</p>
    </div>
</body>
</html>
