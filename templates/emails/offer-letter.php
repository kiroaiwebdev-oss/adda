<?php
// Template variables: $name, $internship_title, $internship_duration, $internship_type, $start_date, $enrollment_date, $offer_id
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Letter - Internship Adda</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7f6;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7f6;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                
                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg,#16a34a 0%,#15803d 100%);padding:40px 50px;text-align:center;">
                        <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:700;letter-spacing:-0.5px;">Internship Adda</h1>
                        <p style="margin:8px 0 0;color:#bbf7d0;font-size:14px;">internshipadda.com</p>
                    </td>
                </tr>

                <!-- Offer Badge -->
                <tr>
                    <td style="padding:0 50px;text-align:center;background:#f0fdf4;border-bottom:2px dashed #86efac;">
                        <div style="display:inline-block;background:#16a34a;color:#ffffff;font-size:12px;font-weight:700;letter-spacing:2px;padding:8px 24px;border-radius:0 0 12px 12px;text-transform:uppercase;">
                            🎉 Offer Letter
                        </div>
                        <p style="margin:16px 0;color:#166534;font-size:13px;font-weight:500;">Offer ID: #<?php echo htmlspecialchars($offer_id); ?></p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:40px 50px;">
                        <p style="margin:0 0 20px;font-size:16px;color:#374151;">Dear <strong style="color:#111827;"><?php echo htmlspecialchars($name); ?></strong>,</p>

                        <p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
                            We are pleased to inform you that you have been successfully enrolled in our internship program. 
                            Congratulations on taking this important step in your career journey!
                        </p>

                        <!-- Offer Details Box -->
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin:24px 0;">
                            <tr>
                                <td style="padding:28px 30px;">
                                    <h3 style="margin:0 0 20px;font-size:16px;color:#166534;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">📋 Internship Details</h3>
                                    
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;">
                                                <span style="font-size:13px;color:#6b7280;font-weight:500;">Position</span>
                                            </td>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;text-align:right;">
                                                <span style="font-size:14px;color:#111827;font-weight:600;"><?php echo htmlspecialchars($internship_title); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;">
                                                <span style="font-size:13px;color:#6b7280;font-weight:500;">Duration</span>
                                            </td>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;text-align:right;">
                                                <span style="font-size:14px;color:#111827;font-weight:600;"><?php echo htmlspecialchars($internship_duration); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;">
                                                <span style="font-size:13px;color:#6b7280;font-weight:500;">Type</span>
                                            </td>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;text-align:right;">
                                                <span style="font-size:14px;color:#111827;font-weight:600;"><?php echo htmlspecialchars($internship_type); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;">
                                                <span style="font-size:13px;color:#6b7280;font-weight:500;">Enrollment Date</span>
                                            </td>
                                            <td style="padding:8px 0;border-bottom:1px solid #dcfce7;text-align:right;">
                                                <span style="font-size:14px;color:#111827;font-weight:600;"><?php echo htmlspecialchars($enrollment_date); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px 0;">
                                                <span style="font-size:13px;color:#6b7280;font-weight:500;">Organization</span>
                                            </td>
                                            <td style="padding:8px 0;text-align:right;">
                                                <span style="font-size:14px;color:#111827;font-weight:600;">Internship Adda</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
                            You are now expected to complete the assigned modules, tasks, and assessments within the stipulated time. 
                            Upon successful completion, you will be awarded a <strong>Certificate of Internship</strong>.
                        </p>

                        <!-- CTA Button -->
                        <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                            <tr>
                                <td align="center">
                                    <a href="https://internshipadda.com/app/views/learner/my-internships.php" 
                                       style="display:inline-block;background:linear-gradient(135deg,#16a34a,#15803d);color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 40px;border-radius:8px;letter-spacing:0.3px;">
                                        🚀 Start Your Internship
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
                            If you have any questions, feel free to reach out to us at 
                            <a href="mailto:support@internshipadda.com" style="color:#16a34a;font-weight:600;">support@internshipadda.com</a>.
                        </p>

                        <p style="margin:24px 0 0;font-size:15px;color:#374151;">
                            Best regards,<br>
                            <strong style="color:#111827;">Team Internship Adda</strong><br>
                            <span style="font-size:13px;color:#6b7280;">internshipadda.com</span>
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:24px 50px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;">
                            This is an auto-generated offer letter. Please do not reply to this email.<br>
                            © <?php echo date('Y'); ?> Internship Adda. All rights reserved.
                        </p>
                        <p style="margin:10px 0 0;font-size:12px;">
                            <a href="https://internshipadda.com" style="color:#16a34a;text-decoration:none;">internshipadda.com</a>
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>