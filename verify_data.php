<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$report = "=== RAPPORT COMPLET DES DONNÉES ===\n\n";

$report .= "📊 STATISTIQUES GLOBALES:\n";
$report .= "  Écoles: " . App\Models\School::count() . "\n";
$report .= "  Années scolaires: " . App\Models\SchoolYear::count() . "\n";
$report .= "  Classes (total): " . App\Models\Classroom::count() . "\n";
$report .= "  Étudiants (pool): " . App\Models\Student::count() . "\n";
$report .= "  Enseignants: " . App\Models\Teacher::count() . "\n";
$report .= "  Matières: " . App\Models\Subject::count() . "\n";
$report .= "  Inscriptions: " . App\Models\Enrollment::count() . "\n";
$report .= "  Paiements: " . App\Models\Payment::count() . "\n";
$report .= "  Emplois du temps (créneaux): " . App\Models\Schedule::count() . "\n";
$report .= "  Types d'évaluation: " . App\Models\EvaluationType::count() . "\n";
$report .= "  Devoirs/Examens: " . App\Models\Assignment::count() . "\n";
$report .= "  Notes: " . App\Models\Grade::count() . "\n\n";

$report .= "🏫 ÉCOLES:\n";
$schools = App\Models\School::all();
foreach ($schools as $school) {
    $report .= "  - {$school->name}\n";
    $report .= "    📍 {$school->address}\n";
    $report .= "    📞 {$school->phone}\n\n";
}

$report .= "📅 ANNÉES SCOLAIRES:\n";
$years = App\Models\SchoolYear::all();
foreach ($years as $year) {
    $report .= "  - {$year->label} (" . ($year->is_active ? 'ACTIVE' : 'inactive') . ")\n";
    $report .= "    Classes: " . App\Models\Classroom::where('school_year_id', $year->id)->count() . "\n";
    $report .= "    Inscriptions: " . App\Models\Enrollment::where('school_year_id', $year->id)->count() . "\n";
    $report .= "    Paiements: " . App\Models\Payment::where('school_year_id', $year->id)->count() . "\n";
    $report .= "    Devoirs/Examens: " . App\Models\Assignment::where('school_year_id', $year->id)->count() . "\n";
    $report .= "    Montant total: " . number_format(App\Models\Payment::where('school_year_id', $year->id)->sum('amount'), 0) . " FCFA\n\n";
}

$report .= "📝 TYPES D'ÉVALUATION:\n";
$evalTypes = App\Models\EvaluationType::all();
foreach ($evalTypes as $et) {
    $report .= "  - {$et->name} (Poids: {$et->weight})\n";
}

$report .= "\n💰 STATISTIQUES PAIEMENTS:\n";
$report .= "  Total paiements: " . App\Models\Payment::count() . "\n";
$report .= "  Montant total encaissé: " . number_format(App\Models\Payment::sum('amount'), 0, ',', ' ') . " FCFA\n\n";

$report .= "📚 STATISTIQUES ACADÉMIQUES:\n";
$report .= "  Total devoirs/examens: " . App\Models\Assignment::count() . "\n";
$report .= "  Total notes attribuées: " . App\Models\Grade::count() . "\n";
$report .= "  Moyenne générale: " . number_format(App\Models\Grade::avg('score'), 2) . "/20\n\n";

$report .= "✅ Vérification terminée!\n";

file_put_contents(__DIR__ . '/data_report.txt', $report);
echo $report;
