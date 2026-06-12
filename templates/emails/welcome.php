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
            padding: 50px 30px;
            text-align: center;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 32px;
        }
        .content {
            padding: 40px 30px;
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
        .feature-box {
            background: #f0fdf4;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }
        .footer {
            background: #f9fafb;
            padding: 30px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Welcome to Internship Adda!</h1>
        </div>
        
        <div class="content">
            <h2 style="color: #111827; margin-top: 0;">Hi <?php echo htmlspecialchars($name); ?>! 🙌</h2>
            
            <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
                Congratulations! Your email has been successfully verified, and your account is now <strong>active</strong>! 🚀
            </p>
            
            <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
                You're now part of the <strong>Internship Adda</strong> community, where you can:
            </p>
            
            <div class="feature-box">
                <p style="margin: 0 0 10px 0;"><strong>📚 Learn:</strong> Access 100+ courses and internships</p>
                <p style="margin: 0 0 10px 0;"><strong>🎓 Earn:</strong> Get certified and boost your resume</p>
                <p style="margin: 0 0 10px 0;"><strong>💼 Grow:</strong> Build real-world skills and connections</p>
                <p style="margin: 0;"><strong>🎯 Succeed:</strong> Track progress and achieve your goals</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="https://internshipadda.com/app/views/learner/dashboard.php" class="button">
                    Go to Dashboard →
                </a>
            </div>
            
            <p style="color: #6b7280; font-size: 14px;">
                Need help? Our support team is always here for you. Just reply to this email or visit our Help Center.
            </p>
        </div>
        
        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                <strong>Internship Adda</strong><br>
                Your Gateway to Professional Success
            </p>
            <p style="margin: 0; font-size: 12px;">
                © 2026 Internship Adda. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
