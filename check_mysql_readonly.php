<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking MySQL read-only status...\n\n";

try {
    $readOnly = DB::select("SHOW VARIABLES LIKE 'read_only'");
    echo "read_only: " . $readOnly[0]->Value . "\n";
    
    $superReadOnly = DB::select("SHOW VARIABLES LIKE 'super_read_only'");
    echo "super_read_only: " . $superReadOnly[0]->Value . "\n\n";
    
    if ($readOnly[0]->Value === 'ON' || $superReadOnly[0]->Value === 'ON') {
        echo "⚠ MySQL is in READ-ONLY mode!\n\n";
        echo "Attempting to disable read-only mode...\n";
        
        try {
            DB::statement("SET GLOBAL read_only = OFF");
            DB::statement("SET GLOBAL super_read_only = OFF");
            echo "✓ Read-only mode disabled!\n";
        } catch (\Exception $e) {
            echo "✗ Failed to disable read-only mode: " . $e->getMessage() . "\n";
            echo "\nYou need to manually edit MySQL config file (my.ini or my.cnf)\n";
            echo "Remove or comment out these lines:\n";
            echo "  read-only=1\n";
            echo "  super-read-only=1\n";
        }
    } else {
        echo "✓ MySQL is NOT in read-only mode.\n";
        echo "\nThe problem might be with individual tables.\n";
        echo "Checking table status...\n\n";
        
        $tables = DB::select("SHOW TABLE STATUS FROM " . env('DB_DATABASE'));
        foreach ($tables as $table) {
            if (isset($table->Comment) && stripos($table->Comment, 'read only') !== false) {
                echo "Table {$table->Name}: {$table->Comment}\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
