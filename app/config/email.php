<?php
return [
    // SMTP Configuration
    'smtp' => [
        'host' => 'smtp.hostinger.com',  // CHANGE THIS
        'port' => 465,
        'encryption' => 'ssl', // or 'ssl' for port 465
        'username' => 'no-reply@internshipadda.com', // CHANGE THIS
        'password' => 'Internship2000@!', // CHANGE THIS
    ],
    
    // Sender Details
    'from' => [
        'email' => 'no-reply@internshipadda.com',
        'name' => 'Internship Adda'
    ],
    
    // OTP Settings
    'otp' => [
        'length' => 6,
        'expiry_minutes' => 10, // OTP valid for 10 minutes
        'max_attempts' => 3,
    ],
    
    // Email Templates Path
    'templates_path' => __DIR__ . '/../templates/emails/',
];
