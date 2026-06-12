<?php
/**
 * Certificate PDF Generator Helper
 * 
 * This uses HTML/CSS to create a printable certificate
 * For production, consider using libraries like TCPDF or mPDF
 */

class CertificateGenerator {
    
    /**
     * Generate certificate HTML
     */
    public static function generateHTML($certificate) {
        $userName = htmlspecialchars($certificate['user_name']);
        $courseTitle = htmlspecialchars($certificate['course_title']);
        $certificateCode = htmlspecialchars($certificate['certificate_code']);
        $issueDate = date('F j, Y', strtotime($certificate['issued_at']));
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - {$userName}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Open+Sans:wght@400;600&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Open Sans', sans-serif;
            background: #f5f5f5;
            padding: 40px 20px;
        }
        
        .certificate-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 60px 80px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 20px solid #f0fdf4;
            position: relative;
        }
        
        .certificate-border {
            position: absolute;
            top: 40px;
            left: 40px;
            right: 40px;
            bottom: 40px;
            border: 3px solid #22c55e;
            pointer-events: none;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .logo-text {
            color: white;
            font-size: 40px;
            font-weight: bold;
            font-family: 'Playfair Display', serif;
        }
        
        .org-name {
            font-size: 32px;
            font-weight: bold;
            color: #1a1a1a;
            font-family: 'Playfair Display', serif;
        }
        
        .certificate-title {
            font-size: 48px;
            font-weight: bold;
            color: #22c55e;
            margin: 30px 0;
            font-family: 'Playfair Display', serif;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        .certificate-text {
            font-size: 18px;
            color: #4a4a4a;
            line-height: 1.8;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .recipient-name {
            font-size: 42px;
            font-weight: bold;
            color: #1a1a1a;
            margin: 30px 0;
            font-family: 'Playfair Display', serif;
            border-bottom: 3px solid #22c55e;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .course-name {
            font-size: 28px;
            font-weight: 600;
            color: #22c55e;
            margin: 20px 0;
        }
        
        .certificate-footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .signature-block {
            text-align: center;
            flex: 1;
        }
        
        .signature-line {
            border-top: 2px solid #1a1a1a;
            width: 200px;
            margin: 0 auto 10px;
            padding-top: 10px;
        }
        
        .signature-name {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .signature-title {
            font-size: 14px;
            color: #666;
        }
        
        .certificate-meta {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e5e7eb;
        }
        
        .certificate-code {
            font-size: 14px;
            color: #666;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .issue-date {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        
        .verify-text {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .certificate-container {
                box-shadow: none;
                page-break-inside: avoid;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-border"></div>
        
        <div class="certificate-header">
            <div class="logo">
                <span class="logo-text">I</span>
            </div>
            <div class="org-name">Internship Adda</div>
        </div>
        
        <div style="text-align: center;">
            <h1 class="certificate-title">Certificate of Completion</h1>
            
            <p class="certificate-text">
                This is to certify that
            </p>
            
            <div class="recipient-name">{$userName}</div>
            
            <p class="certificate-text">
                has successfully completed the course
            </p>
            
            <div class="course-name">{$courseTitle}</div>
            
            <p class="certificate-text" style="margin-top: 30px;">
                This achievement demonstrates dedication to professional development<br>
                and mastery of the course curriculum.
            </p>
        </div>
        
        <div class="certificate-footer">
            <div class="signature-block">
                <div class="signature-line">
                    <div class="signature-name">Director</div>
                </div>
                <div class="signature-title">Internship Adda</div>
            </div>
            
            <div style="text-align: center; flex: 1;">
                <svg width="80" height="80" viewBox="0 0 100 100" style="opacity: 0.3;">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#22c55e" stroke-width="3"/>
                    <path d="M30 50 L45 65 L70 35" fill="none" stroke="#22c55e" stroke-width="4" stroke-linecap="round"/>
                </svg>
            </div>
            
            <div class="signature-block">
                <div class="signature-line">
                    <div class="signature-name">CEO</div>
                </div>
                <div class="signature-title">Internship Adda</div>
            </div>
        </div>
        
        <div class="certificate-meta">
            <div class="certificate-code">Certificate ID: {$certificateCode}</div>
            <div class="issue-date">Issued on {$issueDate}</div>
            <div class="verify-text">
                Verify this certificate at: internshipadda.com/verify?code={$certificateCode}
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 30px;" class="no-print">
        <button onclick="window.print()" style="background: #22c55e; color: white; padding: 12px 32px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
            Download / Print Certificate
        </button>
        <a href="/app/views/learner/certificates.php" style="display: inline-block; margin-left: 20px; padding: 12px 32px; background: #e5e7eb; color: #1a1a1a; text-decoration: none; border-radius: 8px; font-weight: 600;">
            Back to Certificates
        </a>
    </div>
</body>
</html>
HTML;
    }
}
