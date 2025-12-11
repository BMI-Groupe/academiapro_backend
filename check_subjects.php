<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Subjects in database:\n";
echo "Count: " . App\Models\Subject::count() . "\n\n";

$subjects = App\Models\Subject::take(10)->get(['id', 'name', 'code', 'school_year_id']);
foreach ($subjects as $subject) {
    echo "ID: {$subject->id} | Name: {$subject->name} | Code: {$subject->code} | School Year: {$subject->school_year_id}\n";
}
