<?php
/**
 * Site-wide configurable settings.
 * Loads from DB (site_settings table). Falls back to defaults if missing.
 */

if (!function_exists('site_setting')) {
    function site_setting($key, $default = '') {
        global $db;
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            $res = $db->query("SELECT setting_key, setting_value FROM site_settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        }

        return isset($cache[$key]) && $cache[$key] !== ''
            ? $cache[$key]
            : $default;
    }
}

// Shorthand constants
define('SUPPORT_PHONE',      site_setting('support_phone',   '+91 98765 43210'));
define('WHATSAPP_NUMBER',    site_setting('whatsapp_number', '+91 98765 43210'));
define('SUPPORT_EMAIL',      site_setting('support_email',   'support@localjobs.com'));
define('PRIVACY_EMAIL',      site_setting('privacy_email',   'privacy@localjobs.com'));
define('SITE_ADDRESS',       site_setting('address',         'Main Road, Nichlaul, Uttar Pradesh 273162, India'));
define('SUPPORT_HOURS',      site_setting('support_hours',   'Monday to Saturday, 9 AM to 6 PM'));
define('SITE_TAGLINE',       site_setting('tagline',         'Find Real Jobs in Your Town'));
define('LEGAL_LAST_UPDATED', site_setting('last_updated',    date('Y-m-d')));

// ============ OTP ADMIN TOGGLE ============
define('OTP_REQUIRED', site_setting('otp_required', '0') === '1');

// ============ RESUME BUILDER TOGGLE ============
if (!defined('RESUME_BUILDER_ENABLED')) {
    define('RESUME_BUILDER_ENABLED', site_setting('resume_builder_enabled', '1') === '1');
}

// ============ BILLING TOGGLE (EMPLOYER) — NEW ============
if (!defined('BILLING_ENABLED')) {
    define('BILLING_ENABLED', site_setting('employer_show_billing', '0') === '1');
}

// Site-wide language default
if (!defined('DEFAULT_LANGUAGE')) {
    define('DEFAULT_LANGUAGE', site_setting('default_language', 'en'));
}

// Job expiry days
if (!defined('JOB_EXPIRY_DAYS')) {
    define('JOB_EXPIRY_DAYS', (int)site_setting('job_expiry_days', '30'));
}

if (!function_exists('whatsapp_url')) {
    function whatsapp_url() {
        $num = preg_replace('/\D/', '', WHATSAPP_NUMBER);
        return 'https://wa.me/' . $num;
    }
}