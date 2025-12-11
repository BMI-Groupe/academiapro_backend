<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking and repairing tables...\n\n";

$tables = ['sessions', 'personal_access_tokens'];

foreach ($tables as $table) {
    try {
        echo "Checking $table... ";
        $result = DB::select("CHECK TABLE $table");
        print_r($result);
        
        echo "Repairing $table... ";
        $result = DB::select("REPAIR TABLE $table");
        print_r($result);
        
        echo "$table done!\n\n";
    } catch (\Exception $e) {
        echo "Error with $table: " . $e->getMessage() . "\n\n";
    }
}

echo "All done!\n";
