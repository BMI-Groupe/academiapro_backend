<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Attempting to fix personal_access_tokens table...\n\n";

try {
    // Get the table structure
    echo "1. Getting table structure...\n";
    $columns = DB::select("SHOW CREATE TABLE personal_access_tokens");
    $createStatement = $columns[0]->{'Create Table'};
    echo "Table structure retrieved.\n\n";
    
    // Drop the table
    echo "2. Dropping table...\n";
    DB::statement("DROP TABLE IF EXISTS personal_access_tokens_backup");
    DB::statement("RENAME TABLE personal_access_tokens TO personal_access_tokens_backup");
    echo "Table renamed to backup.\n\n";
    
    // Recreate the table
    echo "3. Recreating table...\n";
    DB::statement($createStatement);
    echo "Table recreated.\n\n";
    
    // Copy data back (if any)
    echo "4. Copying data back...\n";
    try {
        DB::statement("INSERT INTO personal_access_tokens SELECT * FROM personal_access_tokens_backup");
        echo "Data copied.\n\n";
    } catch (\Exception $e) {
        echo "No data to copy or error: " . $e->getMessage() . "\n\n";
    }
    
    // Test insert
    echo "5. Testing insert...\n";
    DB::table('personal_access_tokens')->insert([
        'tokenable_type' => 'App\\Models\\User',
        'tokenable_id' => 1,
        'name' => 'test',
        'token' => hash('sha256', 'test'),
        'abilities' => json_encode(['*']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Insert successful!\n\n";
    
    // Clean up test
    DB::table('personal_access_tokens')->where('name', 'test')->delete();
    echo "Test cleaned up.\n\n";
    
    echo "✓ Table fixed successfully!\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
