<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/phpmailer/PHPMailer-7.0.2/src/PHPMailer.php';
require __DIR__ . '/../../vendor/phpmailer/PHPMailer-7.0.2/src/Exception.php';
require __DIR__ . '/../../vendor/phpmailer/PHPMailer-7.0.2/src/SMTP.php';

class EmailService {
    private $config;
    private $db;

    public function __construct($db) {
        $this->config = require __DIR__ . '/../config/email.php';
        $this->db = $db;
    }

    public function generateOTP() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function storeOTP($email, $otp, $purpose = 'signup') {
        $stmt = $this->db->prepare("DELETE FROM email_verifications WHERE email = ? AND purpose = ?");
        $stmt->execute([$email, $purpose]);

        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $this->config['otp']['expiry_minutes'] . ' minutes'));
        $stmt = $this->db->prepare("
            INSERT INTO email_verifications (email, otp, purpose, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$email, $otp, $purpose, $expiresAt]);
    }

    public function verifyOTP($email, $otp, $purpose = 'signup') {
        $stmt = $this->db->prepare("
            SELECT * FROM email_verifications 
            WHERE email = ? AND otp = ? AND purpose = ? 
            AND expires_at > NOW() AND verified = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email, $otp, $purpose]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            $updateStmt = $this->db->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?");
            $updateStmt->execute([$record['id']]);
            return true;
        }
        return false;
    }

    public function sendOTP($email, $name, $otp) {
        $subject = "Verify Your Email - Internship Adda";
        $body = $this->renderTemplate('verification-otp', [
            'name'   => $name,
            'otp'    => $otp,
            'expiry' => $this->config['otp']['expiry_minutes']
        ]);
        return $this->sendEmail($email, $name, $subject, $body);
    }

    public function sendWelcome($email, $name, $profileUrl) {
        $subject = "Welcome to Internship Adda!";
        $body = $this->renderTemplate('welcome', [
            'name'        => $name,
            'profile_url' => $profileUrl
        ]);
        return $this->sendEmail($email, $name, $subject, $body);
    }

    public function sendPasswordReset($email, $name, $resetLink, $token) {
        $subject = "Reset Your Password - Internship Adda";
        $body = $this->renderTemplate('password-reset', [
            'name'       => $name,
            'reset_link' => $resetLink,
            'token'      => $token
        ]);
        return $this->sendEmail($email, $name, $subject, $body);
    }

    // Send Internship Offer Letter
    public function sendInternshipOfferLetter($userId, $internshipId) {
        try {
            $userStmt = $this->db->prepare("SELECT name, email FROM users WHERE id = ? AND status = 'active'");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                error_log("Offer Letter: User not found - ID: $userId");
                return ['success' => false, 'error' => 'User not found'];
            }

            // Using mode and category columns (internship_type column does not exist in DB)
            $internStmt = $this->db->prepare("SELECT title, duration, mode, category FROM internships WHERE id = ?");
            $internStmt->execute([$internshipId]);
            $internship = $internStmt->fetch(PDO::FETCH_ASSOC);

            if (!$internship) {
                error_log("Offer Letter: Internship not found - ID: $internshipId");
                return ['success' => false, 'error' => 'Internship not found'];
            }

            $offerId = 'IA' . date('Y') . strtoupper(substr(md5($userId . $internshipId . time()), 0, 8));
            $subject = "Offer Letter - " . $internship['title'] . " | Internship Adda";

            $body = $this->renderTemplate('internship-offer-letter', [
                'name'                => $user['name'],
                'internship_title'    => $internship['title'],
                'internship_duration' => $internship['duration'] ?? 'As per program',
                'internship_type'     => ucfirst($internship['mode'] ?? 'Remote'),
                'enrollment_date'     => date('d M Y'),
                'offer_id'            => $offerId,
            ]);

            $result = $this->sendEmail($user['email'], $user['name'], $subject, $body);

            if ($result['success']) {
                error_log("Offer Letter sent to: {$user['email']} for: {$internship['title']}");
            } else {
                error_log("Offer Letter failed for: {$user['email']} - " . ($result['error'] ?? ''));
            }

            return $result;

        } catch (Exception $e) {
            error_log("sendInternshipOfferLetter exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendEmail($to, $toName, $subject, $body) {
        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = $this->config['smtp']['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['smtp']['username'];
            $mail->Password   = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port       = $this->config['smtp']['port'];

            $mail->setFrom($this->config['from']['email'], $this->config['from']['name']);
            $mail->addAddress($to, $toName);
            $mail->addReplyTo($this->config['from']['email'], $this->config['from']['name']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();

            return ['success' => true, 'message' => 'Email sent successfully'];

        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'message' => 'Failed to send email'];
        }
    }

    private function renderTemplate($template, $data) {
        $templatePath = __DIR__ . '/../views/emails/' . $template . '.php';

        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found: $template");
        }

        extract($data);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    private function logEmail($to, $subject, $status) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (recipient, subject, status, sent_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$to, $subject, $status]);
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
}