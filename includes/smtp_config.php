<?php
// SMTP configuration - update these values for your SMTP provider
return [
    'enabled' => false, // set to true to enable SMTP sending
    'host' => 'smtp.example.com',
    'port' => 587,
    'username' => 'user@example.com',
    'password' => 'yourpassword',
    'secure' => 'tls', // 'tls' or 'ssl' or ''
    'from_email' => 'noreply@example.com',
    'from_name' => '9BARS COFFEE POS'
];
