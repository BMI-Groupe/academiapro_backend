<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Testing ClassroomDetailController...\n\n";
    
    $controller = new App\Http\Controllers\ClassroomDetailController();
    $request = Illuminate\Http\Request::create('/api/v1.0.0/classrooms/7/details', 'GET');
    
    $response = $controller->show(7, $request);
    
    echo "Success!\n";
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}
