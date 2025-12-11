<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dbName = env('DB_DATABASE');

echo "Recreating database: $dbName\n\n";

try {
    // Drop the database
    echo "1. Dropping database...\n";
    DB::statement("DROP DATABASE IF EXISTS `$dbName`");
    echo "   ✓ Database dropped\n\n";
    
    // Create the database
    echo "2. Creating database...\n";
    DB::statement("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   ✓ Database created\n\n";
    
    // Reconnect to the new database
    echo "3. Reconnecting...\n";
    DB::purge('mysql');
    DB::reconnect('mysql');
    echo "   ✓ Reconnected\n\n";
    
    echo "✓ Database recreated successfully!\n\n";
    echo "Now run: php artisan migrate:fresh --seed\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
