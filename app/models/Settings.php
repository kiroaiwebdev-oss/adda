<?php

class Settings {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    /**
     * Get all settings as key-value pairs
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            return $settings;
            
        } catch (Exception $e) {
            error_log("Settings::getAll Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get single setting value
     */
    public function get($key) {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $row ? $row['setting_value'] : null;
            
        } catch (Exception $e) {
            error_log("Settings::get Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update single setting
     */
    public function update($key, $value) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = NOW()
            ");
            
            $result = $stmt->execute([$key, $value]);
            
            error_log("✏️ Updated setting: $key = " . substr($value, 0, 50));
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Settings::update Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update multiple settings at once (TRANSACTION SAFE)
     */
    public function updateMultiple($data) {
        try {
            error_log("🔄 Starting transaction for " . count($data) . " settings");
            
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = NOW()
            ");
            
            $successCount = 0;
            foreach ($data as $key => $value) {
                $result = $stmt->execute([$key, $value]);
                if ($result) {
                    $successCount++;
                    error_log("  ✓ $key = " . substr($value, 0, 30));
                } else {
                    error_log("  ✗ Failed to update: $key");
                }
            }
            
            $this->db->commit();
            
            error_log("✅ Transaction committed - Updated $successCount/" . count($data) . " settings");
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("❌ Settings::updateMultiple Error: " . $e->getMessage());
            error_log("🔙 Transaction rolled back");
            throw $e;
        }
    }
    
    /**
     * Delete a setting
     */
    public function delete($key) {
        try {
            $stmt = $this->db->prepare("DELETE FROM settings WHERE setting_key = ?");
            return $stmt->execute([$key]);
            
        } catch (Exception $e) {
            error_log("Settings::delete Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if setting exists
     */
    public function exists($key) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $row['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Settings::exists Error: " . $e->getMessage());
            throw $e;
        }
    }
}
