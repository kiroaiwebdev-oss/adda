<?php
/**
 * Referral Model - Handle referral operations
 */
class Referral {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get user's referral code
     */
    public function getUserReferralCode($userId) {
        $stmt = $this->db->prepare("SELECT referral_code FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get referrer user ID by referral code
     */
    public function getReferrerByCode($referralCode) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE referral_code = ? AND status = 'active'");
        $stmt->execute([$referralCode]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Create referral relationship
     */
    public function createReferral($referrerUserId, $referredUserId, $referralCode) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code, status)
                VALUES (?, ?, ?, 'pending')
            ");
            return $stmt->execute([$referrerUserId, $referredUserId, $referralCode]);
        } catch (PDOException $e) {
            error_log("Referral creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark referral as completed and award points
     */
    public function completeReferral($referredUserId, $orderId) {
        try {
            $this->db->beginTransaction();
            
            // Get referral info
            $stmt = $this->db->prepare("
                SELECT id, referrer_user_id, referral_code 
                FROM referrals 
                WHERE referred_user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$referredUserId]);
            $referral = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$referral) {
                $this->db->rollBack();
                return false;
            }
            
            // Get points setting
            $pointsStmt = $this->db->prepare("
                SELECT setting_value FROM referral_settings WHERE setting_key = 'points_per_referral'
            ");
            $pointsStmt->execute();
            $pointsToAward = (int)$pointsStmt->fetchColumn() ?: 100;
            
            // Update referral status
            $updateStmt = $this->db->prepare("
                UPDATE referrals 
                SET status = 'completed', first_purchase_date = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([$referral['id']]);
            
            // Award points
            $earningStmt = $this->db->prepare("
                INSERT INTO referral_earnings 
                (user_id, referral_id, points_earned, points_type, transaction_type, description, order_id)
                VALUES (?, ?, ?, 'purchase_bonus', 'credit', 'Referral purchase bonus', ?)
            ");
            $earningStmt->execute([
                $referral['referrer_user_id'],
                $referral['id'],
                $pointsToAward,
                $orderId
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Complete referral error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's total points
     */
    public function getUserPoints($userId) {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN transaction_type = 'credit' THEN points_earned
                    WHEN transaction_type = 'debit' THEN -points_earned
                    ELSE 0
                END
            ), 0) as total_points
            FROM referral_earnings
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get user's referral stats
     */
    public function getUserReferralStats($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_referrals,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_referrals,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_referrals
            FROM referrals
            WHERE referrer_user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user's referrals list with details
     */
    public function getUserReferrals($userId, $limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT 
                r.*,
                u.name as referred_user_name,
                u.email as referred_user_email,
                u.created_at as signup_date,
                COALESCE(SUM(o.amount), 0) as total_purchase_amount,
                COALESCE(re.points_earned, 0) as points_earned
            FROM referrals r
            INNER JOIN users u ON r.referred_user_id = u.id
            LEFT JOIN orders o ON o.user_id = r.referred_user_id AND o.status = 'completed'
            LEFT JOIN referral_earnings re ON re.referral_id = r.id
            WHERE r.referrer_user_id = ?
            GROUP BY r.id
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get earnings history
     */
    public function getEarningsHistory($userId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT 
                re.*,
                r.referred_user_id,
                COALESCE(u.name, 'System') as referred_user_name
            FROM referral_earnings re
            LEFT JOIN referrals r ON re.referral_id = r.id
            LEFT JOIN users u ON r.referred_user_id = u.id
            WHERE re.user_id = ?
            ORDER BY re.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Redeem points to coupon
     */
    public function redeemPoints($userId, $pointsToRedeem) {
        try {
            $this->db->beginTransaction();
            
            // Check available points
            $availablePoints = $this->getUserPoints($userId);
            
            if ($availablePoints < $pointsToRedeem) {
                throw new Exception("Insufficient points. Available: {$availablePoints}");
            }
            
            // Get min redemption setting (with fallback if referral_settings table missing)
            $minPoints = 50;
            try {
                $minStmt = $this->db->prepare("
                    SELECT setting_value FROM referral_settings WHERE setting_key = 'min_points_for_redemption'
                ");
                $minStmt->execute();
                $val = $minStmt->fetchColumn();
                if ($val !== false && $val !== null) {
                    $minPoints = (int)$val ?: 50;
                }
            } catch (Exception $e) {
                $minPoints = 50;
            }
            
            if ($pointsToRedeem < $minPoints) {
                throw new Exception("Minimum {$minPoints} points required");
            }
            
            // Get point value (with fallback)
            $pointValue = 1.0;
            try {
                $valueStmt = $this->db->prepare("
                    SELECT setting_value FROM referral_settings WHERE setting_key = 'point_value_in_rupees'
                ");
                $valueStmt->execute();
                $val = $valueStmt->fetchColumn();
                if ($val !== false && $val !== null) {
                    $pointValue = (float)$val ?: 1.0;
                }
            } catch (Exception $e) {
                $pointValue = 1.0;
            }
            
            $couponValue = $pointsToRedeem * $pointValue;
            
            // Generate unique coupon code
            $couponCode = 'REF' . strtoupper(substr(md5(uniqid($userId, true)), 0, 8));
            
            // Create coupon
            $couponStmt = $this->db->prepare("
                INSERT INTO coupons 
                (code, discount_type, discount_value, min_purchase, usage_limit, is_active, is_referral_coupon, generated_by_user_id, points_redeemed, valid_from, valid_until)
                VALUES (?, 'fixed', ?, 0, 1, 1, 1, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
            ");
            $couponStmt->execute([$couponCode, $couponValue, $userId, $pointsToRedeem]);
            $couponId = $this->db->lastInsertId();
            
            // Debit points - use a real referral_id if user has any, otherwise NULL
            $refLookup = $this->db->prepare("SELECT id FROM referrals WHERE referrer_user_id = ? ORDER BY id ASC LIMIT 1");
            $refLookup->execute([$userId]);
            $debitReferralId = $refLookup->fetchColumn();
            if (!$debitReferralId) {
                $debitReferralId = null;
            }
            
            // Try insert with NULL referral_id first; if column is NOT NULL fall back to using user's first referral row or a self-reference
            try {
                $debitStmt = $this->db->prepare("
                    INSERT INTO referral_earnings 
                    (user_id, referral_id, points_earned, transaction_type, description)
                    VALUES (?, ?, ?, 'debit', ?)
                ");
                $debitStmt->execute([$userId, $debitReferralId, $pointsToRedeem, "Points redeemed for coupon: {$couponCode}"]);
            } catch (PDOException $insertErr) {
                // Fallback: try with referral_id = 0 (some schemas might not allow NULL)
                if ($debitReferralId === null) {
                    $debitStmt = $this->db->prepare("
                        INSERT INTO referral_earnings 
                        (user_id, referral_id, points_earned, transaction_type, description)
                        VALUES (?, 0, ?, 'debit', ?)
                    ");
                    $debitStmt->execute([$userId, $pointsToRedeem, "Points redeemed for coupon: {$couponCode}"]);
                } else {
                    throw $insertErr;
                }
            }
            
            $this->db->commit();
            
            return [
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'coupon_value' => $couponValue,
                'points_redeemed' => $pointsToRedeem
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * Get referral settings
     */
    public function getSettings() {
        $settings = [];
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM referral_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Table may not exist yet — return defaults
            error_log("Referral settings unavailable: " . $e->getMessage());
        }
        // Defaults
        $settings += [
            'points_per_referral'        => '100',
            'signup_discount_percentage' => '40',
            'min_points_for_redemption'  => '50',
            'point_value_in_rupees'      => '1',
        ];
        return $settings;
    }
    
    /**
     * Update referral setting (Admin only)
     */
    public function updateSetting($key, $value) {
        $stmt = $this->db->prepare("
            UPDATE referral_settings 
            SET setting_value = ? 
            WHERE setting_key = ?
        ");
        return $stmt->execute([$value, $key]);
    }
    
    /**
     * Get all referrals (Admin)
     */
    public function getAllReferrals($limit = 100, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT 
                r.*,
                u1.name as referrer_name,
                u1.email as referrer_email,
                u2.name as referred_name,
                u2.email as referred_email,
                COALESCE(re.points_earned, 0) as points_awarded
            FROM referrals r
            INNER JOIN users u1 ON r.referrer_user_id = u1.id
            INNER JOIN users u2 ON r.referred_user_id = u2.id
            LEFT JOIN referral_earnings re ON re.referral_id = r.id
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get referral coupons (Admin)
     */
    public function getReferralCoupons($limit = 100) {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                u.name as user_name,
                u.email as user_email,
                cu.used_at as redeemed_at,
                cu.order_id as used_order_id
            FROM coupons c
            INNER JOIN users u ON c.generated_by_user_id = u.id
            LEFT JOIN coupon_usage cu ON cu.coupon_id = c.id
            WHERE c.is_referral_coupon = 1
            ORDER BY c.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
