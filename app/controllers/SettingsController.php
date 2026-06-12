<?php

class SettingsController {
    private $db;
    private $auth;
    private $settingsModel;
    
    public function __construct($pdo, $auth) {
        $this->db = $pdo;
        $this->auth = $auth;
        $this->settingsModel = new Settings($pdo);
    }
    
    /**
     * Get all settings
     */
    public function getAll() {
        try {
            $settings = $this->settingsModel->getAll();
            
            return json_encode([
                'success' => true,
                'message' => 'Settings retrieved successfully',
                'data' => ['settings' => $settings],
                'count' => count($settings)
            ]);
            
        } catch (Exception $e) {
            error_log("SettingsController::getAll Error: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => 'Failed to retrieve settings: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get single setting
     */
    public function get($key) {
        try {
            $value = $this->settingsModel->get($key);
            
            return json_encode([
                'success' => true,
                'data' => ['key' => $key, 'value' => $value]
            ]);
            
        } catch (Exception $e) {
            error_log("SettingsController::get Error: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => 'Failed to retrieve setting'
            ]);
        }
    }
    
    /**
     * Update multiple settings
     */
    public function update($data) {
        try {
            // Validate input
            if (empty($data) || !is_array($data)) {
                return json_encode([
                    'success' => false,
                    'error' => 'Invalid data format - expected array/object'
                ]);
            }
            
            error_log("📥 SettingsController::update - Received " . count($data) . " settings");
            error_log("📦 Data keys: " . implode(', ', array_keys($data)));
            
            // Sanitize all values
            $sanitizedData = [];
            foreach ($data as $key => $value) {
                // Convert boolean values to string 'true'/'false'
                if ($value === true || $value === 'true' || $value === '1' || $value === 1) {
                    $sanitizedData[$key] = 'true';
                } elseif ($value === false || $value === 'false' || $value === '0' || $value === 0 || $value === '') {
                    $sanitizedData[$key] = 'false';
                } else {
                    // Sanitize string values
                    $sanitizedData[$key] = htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
                }
            }
            
            error_log("🧹 After sanitization: " . count($sanitizedData) . " settings");
            
            // Update settings in database
            $result = $this->settingsModel->updateMultiple($sanitizedData);
            
            if ($result) {
                error_log("✅ Settings updated successfully in database");
                
                // Log the activity
                $this->logActivity('settings_updated', $this->auth->user()['id']);
                
                return json_encode([
                    'success' => true,
                    'message' => 'Settings updated successfully',
                    'updated_count' => count($sanitizedData)
                ]);
            } else {
                error_log("❌ Failed to update settings in database");
                return json_encode([
                    'success' => false,
                    'error' => 'Failed to update settings in database'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("❌ SettingsController::update Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return json_encode([
                'success' => false,
                'error' => 'An error occurred while updating settings: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update single setting
     */
    public function updateSingle($key, $value) {
        try {
            // Sanitize value
            if ($value === true || $value === 'true' || $value === '1') {
                $value = 'true';
            } elseif ($value === false || $value === 'false' || $value === '0' || $value === '') {
                $value = 'false';
            } else {
                $value = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            }
            
            $result = $this->settingsModel->update($key, $value);
            
            if ($result) {
                $this->logActivity('setting_updated', $this->auth->user()['id']);
                return json_encode([
                    'success' => true,
                    'message' => 'Setting updated successfully'
                ]);
            } else {
                return json_encode([
                    'success' => false,
                    'error' => 'Failed to update setting'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("SettingsController::updateSingle Error: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => 'An error occurred'
            ]);
        }
    }
    
    /**
     * Reset settings to default
     */
    public function reset() {
        try {
            // Define default settings
            $defaults = [
                'platform_name' => 'Internship Adda',
                'site_tagline' => 'Learn, Grow, Succeed',
                'currency' => 'INR',
                'currency_position' => 'left',
                'enable_registration' => 'true',
                'email_verification' => 'true',
                'default_role' => 'student',
                'maintenance_mode' => 'false',
                'razorpay_enabled' => 'true',
                'razorpay_mode' => 'test',
                'enable_comments' => 'true',
                'enable_reviews' => 'true',
                'auto_generate_certificate' => 'true',
                'google_login_enabled' => 'false',
                'facebook_login_enabled' => 'false',
                'zoom_enabled' => 'false',
                'google_meet_enabled' => 'false',
            ];
            
            $result = $this->settingsModel->updateMultiple($defaults);
            
            if ($result) {
                $this->logActivity('settings_reset', $this->auth->user()['id']);
                return json_encode([
                    'success' => true,
                    'message' => 'Settings reset to defaults'
                ]);
            } else {
                return json_encode([
                    'success' => false,
                    'error' => 'Failed to reset settings'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("SettingsController::reset Error: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => 'An error occurred'
            ]);
        }
    }
    
    /**
     * Get public settings (no sensitive data)
     */
    public function getPublic() {
        try {
            $publicKeys = [
                'platform_name', 'site_tagline', 'currency', 'currency_position',
                'enable_registration', 'header_logo', 'footer_logo', 'favicon',
                'copyright_text', 'maintenance_mode'
            ];
            
            $settings = $this->settingsModel->getAll();
            $publicSettings = array_intersect_key($settings, array_flip($publicKeys));
            
            return json_encode([
                'success' => true,
                'data' => ['settings' => $publicSettings]
            ]);
            
        } catch (Exception $e) {
            error_log("SettingsController::getPublic Error: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => 'Failed to retrieve public settings'
            ]);
        }
    }
    
    /**
     * Log admin activity
     */
    private function logActivity($action, $userId) {
        try {
            // Check if table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'admin_activity_logs'");
            if ($checkTable->rowCount() === 0) {
                error_log("admin_activity_logs table does not exist - skipping log");
                return;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO admin_activity_logs (user_id, action, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            error_log("📝 Activity logged: $action for user $userId");
            
        } catch (Exception $e) {
            // Log but don't fail the main operation
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
