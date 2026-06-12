<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background-color: #f0fdf4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); overflow: hidden;">
                    
                    <!-- Header with Green Gradient -->
                    <tr>
                        <td style="padding: 50px 40px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); text-align: center;">
                            <div style="display: inline-block; width: 80px; height: 80px; background-color: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                                <span style="font-size: 48px;">🔐</span>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;">Password Reset</h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.9); font-size: 16px;">Internship Adda</p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 50px 40px;">
                            <p style="margin: 0 0 16px; font-size: 18px; line-height: 1.6; color: #1f2937;">
                                Hi <strong style="color: #16a34a;"><?php echo htmlspecialchars($name); ?></strong> 👋
                            </p>
                            
                            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.7; color: #4b5563;">
                                We received a request to reset your password for your <strong style="color: #16a34a;">Internship Adda</strong> account.
                            </p>
                            
                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 1.7; color: #4b5563;">
                                Click the button below to create a new password:
                            </p>
                            
                            <!-- Green Button -->
                            <table role="presentation" style="width: 100%; margin: 0 0 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="<?php echo htmlspecialchars($reset_link); ?>" 
                                           style="display: inline-block; padding: 18px 48px; font-size: 17px; font-weight: 600; color: #ffffff; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); text-decoration: none; border-radius: 12px; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.4); transition: all 0.3s ease;">
                                            Reset My Password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Divider -->
                            <div style="margin: 40px 0; text-align: center; position: relative;">
                                <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background-color: #e5e7eb;"></div>
                                <span style="position: relative; background-color: #ffffff; padding: 0 16px; color: #9ca3af; font-size: 14px; font-weight: 500;">OR</span>
                            </div>
                            
                            <p style="margin: 0 0 12px; font-size: 14px; line-height: 1.6; color: #6b7280; font-weight: 500;">
                                Copy and paste this link:
                            </p>
                            
                            <div style="margin: 0 0 32px; padding: 16px 20px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 2px solid #bbf7d0; border-radius: 10px; word-break: break-all;">
                                <a href="<?php echo htmlspecialchars($reset_link); ?>" style="font-size: 13px; color: #15803d; text-decoration: none; font-family: 'Courier New', monospace; font-weight: 500;">
                                    <?php echo htmlspecialchars($reset_link); ?>
                                </a>
                            </div>
                            
                            <!-- Warning Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 32px 0 0;">
                                <tr>
                                    <td style="padding: 20px 24px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; border-radius: 10px;">
                                        <table role="presentation" style="width: 100%;">
                                            <tr>
                                                <td style="width: 28px; vertical-align: top; padding-right: 12px;">
                                                    <span style="font-size: 24px;">⚠️</span>
                                                </td>
                                                <td>
                                                    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #92400e;">
                                                        <strong style="display: block; margin-bottom: 4px;">Security Notice</strong>
                                                        This link expires in <strong>1 hour</strong>. If you didn't request this password reset, please ignore this email or contact our support team immediately.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Help Section -->
                            <div style="margin: 40px 0 0; padding: 24px; background-color: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 12px; font-size: 15px; font-weight: 600; color: #1f2937;">
                                    Need help? 🤔
                                </p>
                                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                    If you're having trouble with the reset button, copy and paste the link above into your browser. Still having issues? Contact us at <a href="mailto:support@internshipadda.com" style="color: #16a34a; text-decoration: none; font-weight: 500;">support@internshipadda.com</a>
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 40px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-top: 1px solid #bbf7d0; text-align: center;">
                            <div style="margin: 0 0 20px;">
                                <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #1f2937;">
                                    Internship Adda Team
                                </p>
                                <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                    Your Gateway to Career Success 🚀
                                </p>
                            </div>
                            
                            <!-- Social Links (Optional) -->
                            <div style="margin: 24px 0;">
                                <a href="#" style="display: inline-block; margin: 0 8px; width: 36px; height: 36px; background-color: #16a34a; border-radius: 50%; text-align: center; line-height: 36px; text-decoration: none; color: #ffffff; font-size: 16px;">📧</a>
                                <a href="#" style="display: inline-block; margin: 0 8px; width: 36px; height: 36px; background-color: #16a34a; border-radius: 50%; text-align: center; line-height: 36px; text-decoration: none; color: #ffffff; font-size: 16px;">🌐</a>
                                <a href="#" style="display: inline-block; margin: 0 8px; width: 36px; height: 36px; background-color: #16a34a; border-radius: 50%; text-align: center; line-height: 36px; text-decoration: none; color: #ffffff; font-size: 16px;">💼</a>
                            </div>
                            
                            <div style="margin: 24px 0 0; padding-top: 20px; border-top: 1px solid #bbf7d0;">
                                <p style="margin: 0 0 8px; font-size: 12px; color: #9ca3af;">
                                    This is an automated email. Please do not reply to this message.
                                </p>
                                <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                    © <?php echo date('Y'); ?> Internship Adda. All rights reserved.
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                </table>
                
                <!-- Bottom Tagline -->
                <p style="margin: 24px 0 0; text-align: center; font-size: 13px; color: #6b7280;">
                    Sent with 💚 from <strong style="color: #16a34a;">Internship Adda</strong>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
