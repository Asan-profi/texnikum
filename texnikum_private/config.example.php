<?php
declare(strict_types=1);
// Copy to config.php. Never put this directory inside public_html.
return [
    'base_url' => 'https://YOUR-DOMAIN.uz', // No trailing slash. Include a subdirectory if used.
    'default_language' => 'uz',
    'force_https' => true,
    // Enable only if your hosting provider confirms it sets X-Forwarded-Proto itself.
    'trust_proxy_https' => false,
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'CPANEL_DATABASE',
        'user' => 'CPANEL_DATABASE_USER',
        'password' => 'CHANGE_DATABASE_PASSWORD',
    ],
    // Set a unique random secret of 32 or more characters before opening install.php.
    // After installation, set it to an empty string and remove public_html/install.php.
    'setup_key' => '',
    'phone' => '+998938830767',
    'phone_display' => '+998 93 883 07 67',
    // The source archive's email needs owner confirmation. Add the verified address here.
    'email' => '',
    'telegram_url' => 'https://t.me/+0AE8dC3qlBYyM2Qy',
    'instagram_url' => 'https://www.instagram.com/xojeli_1_sanli.texnikum/',
];
