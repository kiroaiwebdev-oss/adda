<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 40px 30px;
        }
        .otp-box {
            background: #f0fdf4;
            border: 2px dashed #22c55e;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .otp {
            font-size: 42px;
            font-weight: bold;
            color: #16a34a;
            letter-spacing: 8px;
            margin: 10px 0;
        }
        .button {
            display: inline-block;
            background: #22c55e;
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 20px 0;
        }
        .footer {
            background: #f9fafb;
            padding: 30px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Internship Adda</h1>
        </div>
        
        <div class="content">
            <h2 style="color: #111827; margin-top: 0;">Hello <?php echo htmlspecialchars($name); ?>! 👋</h2>
            
            <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
                Thank you for signing up with <strong>Internship Adda</strong>! To complete your registration and verify your email address, please use the One-Time Password (OTP) below:
            </p>
            
            <div class="otp-box">
                <p style="color: #6b7280; margin: 0; font-size: 14px;">Your Verification Code</p>
                <div class="otp"><?php echo $otp; ?></div>
                <p style="color: #6b7280; margin: 10px 0 0 0; font-size: 14px;">
                    Valid for <?php echo $expiry; ?> minutes
                </p>
            </div>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong>
                <ul style="margin: 10px 0; padding-left: 20px; color: #92400e;">
                    <li>Do not share this OTP with anyone</li>
                    <li>Our team will never ask for your OTP</li>
                    <li>This code expires in <?php echo $expiry; ?> minutes</li>
                </ul>
            </div>
            
            <p style="color: #6b7280; font-size: 14px;">
                If you didn't request this verification code, please ignore this email or contact our support team.
            </p>
        </div>
        
        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                <strong>Internship Adda</strong><br>
                Your Gateway to Professional Success
            </p>
            <p style="margin: 0; font-size: 12px;">
                This is an automated email. Please do not reply to this message.
            </p>
        </div>
    </div>
</body>
</html>
