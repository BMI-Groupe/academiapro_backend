<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking Subject ID 4:\n";

$subject = App\Models\Subject::find(4);

if ($subject) {
    echo "Subject found!\n";
    echo "ID: {$subject->id}\n";
    echo "Name: {$subject->name}\n";
    echo "Code: {$subject->code}\n";
    echo "Coefficient: {$subject->coefficient}\n";
    echo "School Year ID: {$subject->school_year_id}\n";
    echo "School ID: {$subject->school_id}\n";
    
    // Try to load relationships
    try {
        $subject->load(['classrooms', 'teachers']);
        echo "\nRelationships loaded successfully\n";
        echo "Classrooms count: " . $subject->classrooms->count() . "\n";
        echo "Teachers count: " . $subject->teachers->count() . "\n";
    } catch (\Exception $e) {
        echo "\nError loading relationships: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "Subject ID 4 not found!\n";
}
