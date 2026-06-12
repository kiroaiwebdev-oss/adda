<?php
/**
 * Verify Razorpay Payment API
 * Verifies payment signature and creates enrollment
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getFirstInternshipLessonUrl($db, $internshipId) {
    try {
        error_log("🔍 Finding first lesson for internship: $internshipId");
        
        $moduleStmt = $db->prepare("SELECT id FROM internship_modules WHERE internship_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
        $moduleStmt->execute([$internshipId]);
        $moduleId = $moduleStmt->fetchColumn();
        
        if (!$moduleId) return '/app/views/learner/my-internships.php';
        
        $lessonStmt = $db->prepare("SELECT id FROM internship_lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
        $lessonStmt->execute([$moduleId]);
        $lessonId = $lessonStmt->fetchColumn();
        
        if (!$lessonId) return '/app/views/learner/my-internships.php';
        
        $redirectUrl = "/app/views/learner/internship-player.php?id={$internshipId}&lesson={$lessonId}";
        error_log("🚀 Redirecting to: $redirectUrl");
        return $redirectUrl;
        
    } catch (Exception $e) {
        error_log("❌ First lesson URL error: " . $e->getMessage());
        return '/app/views/learner/my-internships.php';
    }
}

function getFirstCourseTopicUrl($db, $courseId) {
    try {
        error_log("🔍 Finding first topic for course: $courseId");
        
        $chapterStmt = $db->prepare("SELECT id FROM chapters WHERE course_id = ? ORDER BY chapter_order ASC, sort_order ASC, id ASC LIMIT 1");
        $chapterStmt->execute([$courseId]);
        $chapterId = $chapterStmt->fetchColumn();
        
        if (!$chapterId) return '/app/views/learner/my-courses.php';
        
        $topicStmt = $db->prepare("SELECT id FROM topics WHERE chapter_id = ? ORDER BY topic_order ASC, sort_order ASC, id ASC LIMIT 1");
        $topicStmt->execute([$chapterId]);
        $topicId = $topicStmt->fetchColumn();
        
        if (!$topicId) return '/app/views/learner/my-courses.php';
        
        $redirectUrl = "/app/views/learner/course-player.php?id={$courseId}&topic={$topicId}";
        error_log("🚀 Redirecting to: $redirectUrl");
        return $redirectUrl;
        
    } catch (Exception $e) {
        error_log("❌ First topic URL error: " . $e->getMessage());
        return '/app/views/learner/my-courses.php';
    }
}

try {
    $dbConfig = require __DIR__ . '/../app/config/database.php';
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database'],
        $dbConfig['charset']
    );

    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    spl_autoload_register(function ($class) {
        $paths = [
            __DIR__ . '/../app/core/' . $class . '.php',
            __DIR__ . '/../app/models/' . $class . '.php',
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    });

    $auth = new Auth($db);
    if (!$auth->check()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $userId = $auth->id();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $razorpayOrderId   = $data['razorpay_order_id'] ?? null;
    $razorpayPaymentId = $data['razorpay_payment_id'] ?? null;
    $razorpaySignature = $data['razorpay_signature'] ?? null;
    $courseId          = $data['course_id'] ?? null;
    $internshipId      = $data['internship_id'] ?? null;

    if (!$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature) {
        echo json_encode(['success' => false, 'error' => 'Missing payment fields']);
        exit;
    }

    if (!$courseId && !$internshipId) {
        echo json_encode(['success' => false, 'error' => 'Missing course_id or internship_id']);
        exit;
    }

    $settingsModel = new Settings($db);
    $razorpayKeySecret = $settingsModel->get('razorpay_key_secret', '');

    if (empty($razorpayKeySecret)) {
        echo json_encode(['success' => false, 'error' => 'Payment gateway not configured']);
        exit;
    }

    $generatedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $razorpayKeySecret);

    if ($generatedSignature !== $razorpaySignature) {
        echo json_encode(['success' => false, 'error' => 'Invalid payment signature']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM payment_orders WHERE razorpay_order_id = ? AND user_id = ?");
    $stmt->execute([$razorpayOrderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    $redirectUrl = null;

    // ✅ IMPORTANT FIX: if already processed, still return success + redirect
    if ($order['status'] === 'completed') {
        if ($courseId) {
            $redirectUrl = getFirstCourseTopicUrl($db, $courseId);
        } elseif ($internshipId) {
            $redirectUrl = getFirstInternshipLessonUrl($db, $internshipId);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Payment already verified',
            'redirect' => $redirectUrl
        ]);
        exit;
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            UPDATE payment_orders 
            SET status = 'completed', razorpay_payment_id = ?, razorpay_signature = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$razorpayPaymentId, $razorpaySignature, $order['id']]);

        $shouldSendOfferLetter = false;

        if ($courseId) {
            error_log("💳 Processing COURSE enrollment - Course ID: $courseId");

            $checkStmt = $db->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
            $checkStmt->execute([$userId, $courseId]);

            if ($checkStmt->rowCount() == 0) {
                $stmt = $db->prepare("
                    INSERT INTO enrollments (user_id, course_id, enrollment_date, payment_status, amount_paid, payment_method, transaction_id)
                    VALUES (?, ?, NOW(), 'completed', ?, 'razorpay', ?)
                ");
                $stmt->execute([$userId, $courseId, $order['amount'], $razorpayPaymentId]);
                error_log("✅ Course enrollment created");

                try {
                    $db->prepare("UPDATE courses SET enrolled_count = COALESCE(enrolled_count, 0) + 1 WHERE id = ?")->execute([$courseId]);
                } catch (Exception $e) {
                    error_log("⚠️ Course count: " . $e->getMessage());
                }
            }

            $redirectUrl = getFirstCourseTopicUrl($db, $courseId);

        } elseif ($internshipId) {
            error_log("💳 Processing INTERNSHIP enrollment - Internship ID: $internshipId");

            $checkStmt = $db->prepare("SELECT id FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
            $checkStmt->execute([$userId, $internshipId]);

            if ($checkStmt->rowCount() == 0) {
                $stmt = $db->prepare("
                    INSERT INTO internship_enrollments (user_id, internship_id, enrolled_at, payment_status, amount_paid, payment_method, transaction_id)
                    VALUES (?, ?, NOW(), 'completed', ?, 'razorpay', ?)
                ");
                $stmt->execute([$userId, $internshipId, $order['amount'], $razorpayPaymentId]);
                error_log("✅ Internship enrollment created");

                try {
                    $db->prepare("UPDATE internships SET enrolled_count = COALESCE(enrolled_count, 0) + 1 WHERE id = ?")->execute([$internshipId]);
                } catch (Exception $e) {
                    error_log("⚠️ Internship count: " . $e->getMessage());
                }
            }

            // ✅ IMPORTANT FIX: internship payment verified means offer letter send karo
            $shouldSendOfferLetter = true;
            $redirectUrl = getFirstInternshipLessonUrl($db, $internshipId);
        }

        // ✅ Award referral points on first paid purchase
        try {
            $countStmt = $db->prepare("
                SELECT
                    (SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND payment_status = 'completed') +
                    (SELECT COUNT(*) FROM internship_enrollments WHERE user_id = ? AND payment_status = 'completed') AS total
            ");
            $countStmt->execute([$userId, $userId]);
            $totalPurchases = (int) $countStmt->fetchColumn();

            if ($totalPurchases <= 1) {
                $refStmt = $db->prepare("
                    SELECT id, referrer_user_id, referral_code
                    FROM referrals
                    WHERE referred_user_id = ? AND status = 'pending'
                    LIMIT 1
                ");
                $refStmt->execute([$userId]);
                $pendingRef = $refStmt->fetch(PDO::FETCH_ASSOC);

                if ($pendingRef) {
                    $pointsToAward = 100;
                    try {
                        $pStmt = $db->prepare("SELECT setting_value FROM referral_settings WHERE setting_key = 'points_per_referral'");
                        $pStmt->execute();
                        $val = $pStmt->fetchColumn();
                        if ($val !== false && $val !== null) {
                            $pointsToAward = (int) $val ?: 100;
                        }
                    } catch (Exception $e) { /* defaults */ }

                    $db->prepare("UPDATE referrals SET status = 'completed', first_purchase_date = NOW() WHERE id = ?")
                       ->execute([$pendingRef['id']]);

                    $db->prepare("
                        INSERT INTO referral_earnings
                            (user_id, referral_id, points_earned, points_type, transaction_type, description)
                        VALUES (?, ?, ?, 'purchase_bonus', 'credit', ?)
                    ")->execute([
                        $pendingRef['referrer_user_id'],
                        $pendingRef['id'],
                        $pointsToAward,
                        'Referral purchase bonus'
                    ]);

                    error_log("🎁 Referral completed: referrer={$pendingRef['referrer_user_id']} user={$userId} points={$pointsToAward}");
                }
            }
        } catch (Exception $e) {
            error_log('Referral award skipped: ' . $e->getMessage());
        }

        $db->commit();

        // ✅ SEND OFFER LETTER AFTER COMMIT
        if ($shouldSendOfferLetter && $internshipId) {
            try {
                $emailService = new EmailService($db);
                $emailResult = $emailService->sendInternshipOfferLetter($userId, $internshipId);
                error_log("📧 Offer letter email: " . ($emailResult['success'] ? '✅ Sent' : '❌ Failed - ' . ($emailResult['error'] ?? '')));
            } catch (Exception $emailEx) {
                error_log("📧 Offer letter exception: " . $emailEx->getMessage());
            }
        }

        error_log("🎉 Payment processed! Redirect: $redirectUrl");

        echo json_encode([
            'success' => true,
            'message' => 'Payment verified and enrollment created',
            'redirect' => $redirectUrl
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("❌ Payment verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>