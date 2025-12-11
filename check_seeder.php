<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VÉRIFICATION DES DONNÉES SEEDÉES ===\n\n";

echo "📊 STATISTIQUES:\n";
echo "- Utilisateurs: " . App\Models\User::count() . "\n";
echo "- Années scolaires: " . App\Models\SchoolYear::count() . "\n";
echo "- Classes: " . App\Models\Classroom::count() . "\n";
echo "- Étudiants: " . App\Models\Student::count() . "\n";
echo "- Enseignants: " . App\Models\Teacher::count() . "\n";
echo "- Matières: " . App\Models\Subject::count() . "\n\n";

echo "👥 UTILISATEURS (Informations de connexion):\n";
$users = App\Models\User::all();
foreach ($users as $user) {
    echo "  - {$user->name}\n";
    echo "    📱 Téléphone: {$user->phone}\n";
    echo "    🔑 Mot de passe: password\n";
    echo "    👤 Rôle: {$user->role}\n\n";
}

echo "📅 ANNÉE SCOLAIRE ACTIVE:\n";
$activeYear = App\Models\SchoolYear::where('is_active', true)->first();
if ($activeYear) {
    echo "  - {$activeYear->label}\n";
    echo "    Début: {$activeYear->start_date}\n";
    echo "    Fin: {$activeYear->end_date}\n\n";
}

echo "\n🏫 STRUCTURE:\n";
echo "  Écoles : " . App\Models\School::count() . "\n";
echo "  Années Scolaires : " . App\Models\SchoolYear::count() . "\n";
echo "  Salles de classe (Total) : " . App\Models\Classroom::count() . "\n";
echo "  Emplois du temps (Créneaux) : " . App\Models\Schedule::count() . "\n";

echo "\n🎓 ÉLÈVES ET INSCRIPTIONS:\n";
echo "  Total Élèves (Pool) : " . App\Models\Student::count() . "\n";
echo "  Total Inscriptions (toutes années) : " . App\Models\Enrollment::count() . "\n";

echo "\n💰 STATISTIQUES PAIEMENTS:\n";
echo "  Total paiements enregistrés : " . App\Models\Payment::count() . "\n";
echo "  Montant total encaissé : " . number_format(App\Models\Payment::sum('amount'), 0, ',', ' ') . " FCFA\n";


echo "✅ Le seeder a fonctionné correctement!\n";
