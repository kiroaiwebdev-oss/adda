<?php

/**
 * Settings Helper Functions
 * Global functions to access settings throughout the application
 */

// Global settings cache
$GLOBALS['_settings_cache'] = null;

/**
 * Load all settings from database
 */
function loadSettings() {
    global $db;
    
    if (!isset($db)) {
        error_log("Settings Helper: Database not initialized");
        return [];
    }
    
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Cache settings
        $GLOBALS['_settings_cache'] = $settings;
        return $settings;
    } catch (PDOException $e) {
        error_log("Settings Helper: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a setting value by key
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value
 */
function getSetting($key, $default = null) {
    // Check cache first
    if ($GLOBALS['_settings_cache'] === null) {
        loadSettings();
    }
    
    $settings = $GLOBALS['_settings_cache'] ?? [];
    return $settings[$key] ?? $default;
}

/**
 * Get all settings as array
 * 
 * @return array All settings
 */
function getAllSettings() {
    if ($GLOBALS['_settings_cache'] === null) {
        loadSettings();
    }
    
    return $GLOBALS['_settings_cache'] ?? [];
}

/**
 * Check if a boolean setting is enabled
 * 
 * @param string $key Setting key
 * @return bool
 */
function isSettingEnabled($key) {
    $value = getSetting($key, 'false');
    return $value === 'true' || $value === '1' || $value === 1 || $value === true;
}

/**
 * Get platform name
 */
function getPlatformName() {
    return getSetting('platform_name', 'Internship Adda');
}

/**
 * Get site tagline
 */
function getSiteTagline() {
    return getSetting('site_tagline', 'Learn, Grow, Succeed');
}

/**
 * Get currency symbol
 */
function getCurrencySymbol() {
    $currency = getSetting('currency', 'INR');
    $symbols = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];
    return $symbols[$currency] ?? $currency;
}

/**
 * Format price with currency
 * 
 * @param float $amount Price amount
 * @return string Formatted price
 */
function formatPrice($amount) {
    $symbol = getCurrencySymbol();
    $position = getSetting('currency_position', 'left');
    
    $formatted = number_format($amount, 2);
    
    if ($position === 'left') {
        return $symbol . $formatted;
    } else {
        return $formatted . $symbol;
    }
}

/**
 * Check if maintenance mode is enabled
 */
function isMaintenanceMode() {
    return isSettingEnabled('maintenance_mode');
}

/**
 * Check if user registration is enabled
 */
function isRegistrationEnabled() {
    return isSettingEnabled('enable_registration');
}

/**
 * Check if email verification is required
 */
function isEmailVerificationRequired() {
    return isSettingEnabled('email_verification');
}

/**
 * Get admin email
 */
function getAdminEmail() {
    return getSetting('admin_email', 'admin@internshipadda.com');
}

/**
 * Get contact phone
 */
function getContactPhone() {
    return getSetting('contact_phone', '');
}

/**
 * Get copyright text
 */
function getCopyrightText() {
    return getSetting('copyright_text', '© 2026 Internship Adda. All rights reserved.');
}

/**
 * Get header logo URL
 */
function getHeaderLogo() {
    return getSetting('header_logo', '/public/assets/logo.png');
}

/**
 * Get footer logo URL
 */
function getFooterLogo() {
    return getSetting('footer_logo', '/public/assets/logo-footer.png');
}

/**
 * Get favicon URL
 */
function getFavicon() {
    return getSetting('favicon', '/public/assets/favicon.ico');
}

/**
 * Check if Razorpay is enabled
 */
function isRazorpayEnabled() {
    return isSettingEnabled('razorpay_enabled');
}

/**
 * Get Razorpay key ID
 */
function getRazorpayKeyId() {
    return getSetting('razorpay_key_id', '');
}

/**
 * Check if Google Login is enabled
 */
function isGoogleLoginEnabled() {
    return isSettingEnabled('google_login_enabled');
}

/**
 * Check if Facebook Login is enabled
 */
function isFacebookLoginEnabled() {
    return isSettingEnabled('facebook_login_enabled');
}

/**
 * Get SMTP settings as array
 */
function getSmtpSettings() {
    return [
        'host' => getSetting('smtp_host', ''),
        'port' => getSetting('smtp_port', '587'),
        'username' => getSetting('smtp_username', ''),
        'password' => getSetting('smtp_password', ''),
        'encryption' => getSetting('smtp_encryption', 'tls'),
        'from_name' => getSetting('mail_from_name', getPlatformName()),
        'from_email' => getSetting('mail_from_email', getAdminEmail()),
    ];
}

/**
 * Refresh settings cache
 */
function refreshSettingsCache() {
    $GLOBALS['_settings_cache'] = null;
    return loadSettings();
}
