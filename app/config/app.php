<?php
/**
 * Application Configuration
 * Core settings for the Enterprise LMS
 */

return [
    // Application
    'app_name' => 'Internship Adda',
    'app_url' => 'https://lms.devsarun.io/',
    'app_env' => 'development', // development, production
    'debug' => true,
    
    // Security
    'secret_key' => 'CHANGE_THIS_TO_RANDOM_64_CHAR_STRING_IN_PRODUCTION',
    'session_lifetime' => 7200, // 2 hours in seconds
    'password_min_length' => 8,
    
    // Timezone
    'timezone' => 'Asia/Kolkata',
    
    // Uploads
    'upload_max_size' => 10485760, // 10MB in bytes
    'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'allowed_video_types' => ['mp4', 'webm', 'avi', 'mov'],
    'allowed_document_types' => ['pdf', 'doc', 'docx', 'ppt', 'pptx'],
    
    // Pagination
    'per_page' => 12,
    'admin_per_page' => 20,
    
    // Email (to be configured later)
    'mail_from' => 'noreply@internshipadda.com',
    'mail_from_name' => 'Internship Adda',
    
    // Certificate
    'certificate_prefix' => 'IA',
    'certificate_validity_years' => 5,
    
    // Course Settings
    'video_watch_completion_percent' => 80, // 80% watched = complete
    'quiz_pass_percent' => 70, // 70% to pass
];
