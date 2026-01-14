<?php

namespace App\Http\Controllers;

use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Schedule;
use Carbon\Carbon;

class ChatbotController extends Controller
{
    /**
     * Envoie un message au chatbot LLaMA et retourne la réponse
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_history' => 'sometimes|array',
            'context' => 'sometimes|array',
        ]);

        $user = $request->user();
        $message = $request->input('message');
        $conversationHistory = $request->input('conversation_history', []);
        $context = $request->input('context', []);

        try {
            // Utiliser l'API Hugging Face Router (format Chat Completions)
            $hfApiToken = config('services.huggingface.api_token');
            $hfModel = config('services.huggingface.model', 'mistralai/Mistral-7B-Instruct-v0.2');

            // Si le token est manquant, utiliser le fallback directement
            if (!$hfApiToken) {
                Log::warning('Configuration Hugging Face manquante. Utilisation du fallback.');
                $fallbackResponse = $this->getFallbackResponse($user, $context, $message, $conversationHistory);
                return ApiResponse::sendResponse(true, [
                    'response' => $fallbackResponse,
                    'model' => 'fallback-rule-based',
                ], 'Réponse (mode hors ligne)', 200);
            }

            // Construire le prompt système selon le rôle de l'utilisateur
            $systemPrompt = $this->buildSystemPrompt($user, $context);
            
            // Construire les messages au format Chat Completions
            $messages = $this->buildMessages($systemPrompt, $conversationHistory, $message);

            $apiUrl = "https://router.huggingface.co/v1/chat/completions";
            
            Log::info('Chatbot API Request', [
                'url' => $apiUrl,
                'model' => $hfModel,
            ]);
            
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $hfApiToken,
                    'Content-Type' => 'application/json',
                ])->timeout(10)->post($apiUrl, [ // Timeout réduit à 10s pour éviter de bloquer
                    'model' => $hfModel,
                    'messages' => $messages,
                    'max_tokens' => 512,
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                ]);

                if ($response->failed()) {
                    throw new \Exception('API Error: ' . $response->status());
                }

                $responseData = $response->json();
                $generatedText = $this->extractChatCompletionText($responseData);

                return ApiResponse::sendResponse(true, [
                    'response' => $generatedText,
                    'model' => $hfModel,
                ], 'Réponse générée avec succès', 200);

            } catch (\Exception $e) {
                Log::warning('Chatbot API/Connectivity Error: ' . $e->getMessage() . ' - Switching to fallback.');
                // En cas d'erreur API, timeout, ou autre : utiliser le fallback
                $fallbackResponse = $this->getFallbackResponse($user, $context, $message, $conversationHistory);
                
                return ApiResponse::sendResponse(true, [
                    'response' => $fallbackResponse,
                    'model' => 'fallback-rule-based',
                ], 'Réponse (mode fallback)', 200);
            }

        } catch (\Exception $e) {
            Log::error('Chatbot Critical Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Même en cas d'erreur critique, essayer de répondre quelque chose
            return ApiResponse::sendResponse(
                true,
                ['response' => "Désolé, je rencontre des difficultés techniques momentanées. Je peux tout de même vous aider avec les fonctions de base. Que souhaitez-vous faire ?", 'model' => 'error-fallback'],
                'Réponse de secours suite erreur critique',
                200
            );
        }
    }

    /**
     * Construit le prompt système selon le rôle de l'utilisateur
     */
    private function buildSystemPrompt($user, $context = [])
    {
        $role = $user->role ?? 'guest';
        $userName = $user->name ?? 'Utilisateur';
        $currentPage = $context['current_page'] ?? 'inconnue';
        
        $roleDescriptions = [
            'admin' => "Vous êtes un assistant virtuel pour une plateforme de gestion scolaire. Vous aidez l'administrateur à gérer l'ensemble du système. Vous pouvez guider l'administrateur sur : la gestion des utilisateurs, la configuration de l'école, la gestion des années scolaires, les statistiques et rapports, la gestion des paiements et finances.",
            'directeur' => "Vous êtes un assistant virtuel pour une plateforme de gestion scolaire. Vous aidez le directeur à gérer l'établissement. Vous pouvez guider le directeur sur : la gestion des classes et élèves, la gestion des enseignants, la gestion des matières, la consultation des bulletins, la gestion des paiements, les statistiques de l'école, la gestion des emplois du temps.",
            'enseignant' => "Vous êtes un assistant virtuel pour une plateforme de gestion scolaire. Vous aidez l'enseignant dans ses tâches quotidiennes. Vous pouvez guider l'enseignant sur : la saisie des notes, la consultation des élèves de ses classes, la consultation des bulletins, la gestion des devoirs et évaluations, l'emploi du temps.",
            'eleve' => "Vous êtes un assistant virtuel pour une plateforme de gestion scolaire. Vous aidez l'élève à consulter ses informations. Vous pouvez guider l'élève sur : la consultation de ses notes, la consultation de son bulletin, son emploi du temps, ses devoirs.",
            'parent' => "Vous êtes un assistant virtuel pour une plateforme de gestion scolaire. Vous aidez le parent à suivre la scolarité de son enfant. Vous pouvez guider le parent sur : la consultation des notes de son enfant, la consultation du bulletin, les paiements et factures, les absences et retards.",
        ];

        $basePrompt = $roleDescriptions[$role] ?? $roleDescriptions['admin'];
        
        $systemPrompt = "{$basePrompt}\n\n";
        $systemPrompt .= "Nom de l'utilisateur : {$userName}\n";
        $systemPrompt .= "Rôle : {$role}\n";
        $systemPrompt .= "Page actuelle : {$currentPage}\n\n";
        $systemPrompt .= "Instructions importantes :\n";
        $systemPrompt .= "- Répondez toujours en français\n";
        $systemPrompt .= "- Soyez concis et précis\n";
        $systemPrompt .= "- Guidez l'utilisateur étape par étape si nécessaire\n";
        $systemPrompt .= "- Si vous ne savez pas quelque chose, dites-le honnêtement\n";
        $systemPrompt .= "- Utilisez un ton professionnel mais amical\n\n";
        
        // Informations importantes sur le système
        $systemPrompt .= "Informations importantes sur le système :\n";
        $systemPrompt .= "- Les classes et matières sont filtrées par année scolaire. Il faut toujours sélectionner une année scolaire avant de choisir une classe ou une matière.\n";
        $systemPrompt .= "- Dans les emplois du temps, les classes et matières disponibles dépendent de l'année scolaire sélectionnée.\n";
        $systemPrompt .= "- Pour créer un emploi du temps, il faut d'abord sélectionner l'année scolaire, puis la classe, puis la matière.\n";
        $systemPrompt .= "- Les matières affichées dans le formulaire d'emploi du temps sont celles assignées à la classe sélectionnée pour l'année scolaire choisie.\n";
        $systemPrompt .= "- Chaque année scolaire a ses propres classes (sections) et matières.\n\n";
        
        $systemPrompt .= "Contexte de la conversation :\n";

        return $systemPrompt;
    }

    /**
     * Construit les messages au format Chat Completions API
     */
    private function buildMessages($systemPrompt, $conversationHistory, $newMessage)
    {
        $messages = [];
        
        // Ajouter le message système
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];
        
        // Ajouter l'historique de conversation
        if (!empty($conversationHistory)) {
            foreach ($conversationHistory as $entry) {
                if (isset($entry['role']) && isset($entry['content'])) {
                    // Convertir 'assistant' en 'assistant' pour l'API
                    $role = $entry['role'] === 'user' ? 'user' : 'assistant';
                    $messages[] = [
                        'role' => $role,
                        'content' => $entry['content'],
                    ];
                }
            }
        }

        // Ajouter le nouveau message de l'utilisateur
        $messages[] = [
            'role' => 'user',
            'content' => $newMessage,
        ];

        return $messages;
    }

    /**
     * Extrait le texte généré de la réponse Chat Completions API
     */
    private function extractChatCompletionText($responseData)
    {
        // Format Chat Completions API (router.huggingface.co)
        if (isset($responseData['choices'][0]['message']['content'])) {
            return trim($responseData['choices'][0]['message']['content']);
        }

        // Fallback pour l'ancien format (si jamais utilisé)
        if (isset($responseData[0]['generated_text'])) {
            return trim($responseData[0]['generated_text']);
        }
        
        if (isset($responseData['generated_text'])) {
            return trim($responseData['generated_text']);
        }

        // Fallback : retourner la réponse complète si elle est une chaîne
        if (is_string($responseData)) {
            return trim($responseData);
        }

        // Si rien ne correspond, retourner un message par défaut
        Log::warning('Unexpected Hugging Face response format', ['response' => $responseData]);
        return "Je n'ai pas pu traiter votre demande correctement. Veuillez reformuler votre question.";
    }

    /**
     * Système de fallback avec réponses pré-définies basées sur des règles
     */
    private function getFallbackResponse($user, $context, $message, $conversationHistory)
    {
        $role = $user->role ?? 'guest';
        $currentPage = $context['current_page'] ?? 'inconnue';
        $messageLower = mb_strtolower($message);
        
        // Détecter la langue demandée ou la langue de la question
        $requestedLanguage = $this->detectRequestedLanguage($messageLower);
        $isEnglish = $this->isEnglishMessage($message) || $requestedLanguage === 'en';
        
        // Vérifier d'abord les questions sur le rôle/utilisateur
        $userInfoResponse = $this->getUserInfoResponse($messageLower, $role, $user, $isEnglish);
        if ($userInfoResponse) {
            return $userInfoResponse;
        }
        
        // Vérifier les questions sur les informations de l'école (horaires, dates clés, procédures)
        $schoolInfoResponse = $this->getSchoolInfoResponse($messageLower, $user, $isEnglish);
        if ($schoolInfoResponse) {
            return $schoolInfoResponse;
        }
        
        // Vérifier si c'est une question hors sujet
        if ($this->isOffTopic($messageLower)) {
            return $this->getOffTopicResponse($role, $currentPage, $isEnglish);
        }
        
        // Réponses selon le rôle et la page actuelle
        $responses = $this->getRoleBasedResponses($role, $currentPage, $messageLower, $isEnglish);
        
        if ($responses) {
            return $responses;
        }
        
        // Vérifier les questions sur l'installation PWA
        if ((strpos($messageLower, 'installer') !== false || strpos($messageLower, 'install') !== false || strpos($messageLower, 'télécharger') !== false || strpos($messageLower, 'download') !== false) &&
            (strpos($messageLower, 'app') !== false || strpos($messageLower, 'application') !== false || strpos($messageLower, 'pwa') !== false || strpos($messageLower, 'mobile') !== false || strpos($messageLower, 'local') !== false)) {
            if ($isEnglish) {
                return "To install AcademiaPro as a Progressive Web App (PWA) on your device:\n\n**On Desktop (Chrome/Edge):**\n1. Look for the install icon (+) in the address bar\n2. Click it and select 'Install'\n3. Or go to the menu (three dots) → 'Install AcademiaPro'\n\n**On Mobile (Android/Chrome):**\n1. Open the site in Chrome\n2. Tap the menu (three dots) → 'Add to Home screen' or 'Install app'\n3. Confirm the installation\n\n**On Mobile (iOS/Safari):**\n1. Open the site in Safari\n2. Tap the Share button (square with arrow)\n3. Select 'Add to Home Screen'\n4. Customize the name if needed and tap 'Add'\n\n**Note:** The app must be accessed via HTTPS for installation to work. If you see an install prompt at the top of the page, you can click it directly.\n\nOnce installed, you can access AcademiaPro like a native app from your home screen or applications menu.";
            } else {
                return "Pour installer AcademiaPro en tant qu'application web progressive (PWA) sur votre appareil :\n\n**Sur ordinateur (Chrome/Edge) :**\n1. Cherchez l'icône d'installation (+) dans la barre d'adresse\n2. Cliquez dessus et sélectionnez 'Installer'\n3. Ou allez dans le menu (trois points) → 'Installer AcademiaPro'\n\n**Sur mobile (Android/Chrome) :**\n1. Ouvrez le site dans Chrome\n2. Appuyez sur le menu (trois points) → 'Ajouter à l'écran d'accueil' ou 'Installer l'application'\n3. Confirmez l'installation\n\n**Sur mobile (iOS/Safari) :**\n1. Ouvrez le site dans Safari\n2. Appuyez sur le bouton Partager (carré avec flèche)\n3. Sélectionnez 'Sur l'écran d'accueil'\n4. Personnalisez le nom si nécessaire et appuyez sur 'Ajouter'\n\n**Note :** L'application doit être accessible via HTTPS pour que l'installation fonctionne. Si vous voyez une invite d'installation en haut de la page, vous pouvez cliquer dessus directement.\n\nUne fois installée, vous pouvez accéder à AcademiaPro comme une application native depuis votre écran d'accueil ou le menu des applications.";
            }
        }
        
        // Réponses générales selon les mots-clés
        return $this->getKeywordBasedResponse($messageLower, $role, $currentPage, $isEnglish);
    }
    
    /**
     * Détecte si l'utilisateur demande une langue spécifique
     */
    private function detectRequestedLanguage($messageLower)
    {
        if (strpos($messageLower, 'répond en anglais') !== false || 
            strpos($messageLower, 'répond en angalis') !== false ||
            strpos($messageLower, 'answer in english') !== false ||
            strpos($messageLower, 'respond in english') !== false) {
            return 'en';
        }
        if (strpos($messageLower, 'répond en français') !== false ||
            strpos($messageLower, 'answer in french') !== false ||
            strpos($messageLower, 'respond in french') !== false) {
            return 'fr';
        }
        return null;
    }
    
    /**
     * Détecte si le message est en anglais
     */
    private function isEnglishMessage($message)
    {
        // Mots-clés anglais courants
        $englishKeywords = [
            'hello', 'hi', 'how are you', 'what', 'when', 'where', 'why', 'how',
            'please', 'thank you', 'thanks', 'yes', 'no', 'ok', 'okay',
            'i speak', 'i want', 'i need', 'can you', 'could you', 'help me',
            'student', 'class', 'grade', 'payment', 'dashboard', 'statistics',
        ];
        
        $messageLower = mb_strtolower($message);
        foreach ($englishKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Réponses basées sur le rôle et la page
     */
    private function getRoleBasedResponses($role, $currentPage, $messageLower, $isEnglish = false)
    {
        // Guide pour ajouter une école (admin uniquement)
        if ((strpos($messageLower, 'ajouter') !== false || strpos($messageLower, 'add') !== false || strpos($messageLower, 'créer') !== false || strpos($messageLower, 'create') !== false) && 
            (strpos($messageLower, 'école') !== false || strpos($messageLower, 'ecole') !== false || strpos($messageLower, 'school') !== false)) {
            
            if ($role !== 'admin') {
                if ($isEnglish) {
                    return "Only administrators can add schools. If you need to add a school, please contact an administrator.";
                } else {
                    return "Seuls les administrateurs peuvent ajouter des écoles. Si vous avez besoin d'ajouter une école, veuillez contacter un administrateur.";
                }
            }
            
            if ($isEnglish) {
                return "To add a school:\n1. Go to the 'School Management' section (admin only)\n2. Click on 'Add School' or 'New'\n3. Fill out the form with:\n   - School name (required)\n   - Address (optional)\n   - Phone number (required, unique)\n   - Email (required, unique)\n   - Logo (optional, image file)\n4. Click 'Save'\n\nNote: When you create a school, a director account is automatically created with the provided email and phone. The director will receive their login credentials.";
            } else {
                return "Pour ajouter une école :\n1. Allez dans la section 'Gestion des écoles' (admin uniquement)\n2. Cliquez sur 'Ajouter une école' ou 'Nouveau'\n3. Remplissez le formulaire avec :\n   - Nom de l'école (obligatoire)\n   - Adresse (optionnel)\n   - Numéro de téléphone (obligatoire, unique)\n   - Email (obligatoire, unique)\n   - Logo (optionnel, fichier image)\n4. Cliquez sur 'Enregistrer'\n\nNote : Lors de la création d'une école, un compte directeur est automatiquement créé avec l'email et le téléphone fournis. Le directeur recevra ses identifiants de connexion.";
            }
        }
        
        // Guide pour ajouter un élève
        if ((strpos($messageLower, 'ajouter') !== false || strpos($messageLower, 'add') !== false) && 
            (strpos($messageLower, 'élève') !== false || strpos($messageLower, 'eleve') !== false || strpos($messageLower, 'étudiant') !== false || strpos($messageLower, 'student') !== false)) {
            
            if ($isEnglish) {
                return "To add a student:\n1. Go to the 'Student Management' section\n2. Click on the 'Add Student' or 'New' button\n3. Fill out the form with the student's information (name, first name, date of birth, etc.)\n4. Select the school year first (required)\n5. Then select the class - only classes for the selected year will be displayed\n6. Click 'Save'\n\nImportant: Always select the school year first, as classes are filtered by school year.";
            } else {
                return "Pour ajouter un élève :\n1. Allez dans la section 'Gestion des élèves'\n2. Cliquez sur le bouton 'Ajouter un élève' ou 'Nouveau'\n3. Remplissez le formulaire avec les informations de l'élève (nom, prénom, date de naissance, etc.)\n4. Sélectionnez d'abord l'année scolaire (obligatoire)\n5. Ensuite sélectionnez la classe - seules les classes de l'année sélectionnée seront affichées\n6. Cliquez sur 'Enregistrer'\n\nImportant : Toujours sélectionner l'année scolaire en premier, car les classes sont filtrées par année scolaire.";
            }
        }
        
        // Guide pour ajouter une note
        if ((strpos($messageLower, 'ajouter') !== false || strpos($messageLower, 'add') !== false) && 
            (strpos($messageLower, 'note') !== false || strpos($messageLower, 'grade') !== false)) {
            
            if ($isEnglish) {
                return "To add a grade:\n1. Go to the 'Grade Management' section\n2. Click on 'Add Grade'\n3. Select the school year first (required)\n4. Select the class - only classes for the selected year will be displayed\n5. Select the student - only students enrolled in the selected class and year will be shown\n6. Select the assignment/exam - filtered by the selected school year\n7. Select the subject - filtered by the selected assignment (subjects available in that assignment)\n8. Enter the grade obtained\n9. Click 'Save'\n\nImportant: The selection order matters: school year → class → assignment → subject. All fields are filtered based on previous selections.";
            } else {
                return "Pour ajouter une note :\n1. Allez dans la section 'Gestion des notes'\n2. Cliquez sur 'Ajouter une note'\n3. Sélectionnez d'abord l'année scolaire (obligatoire)\n4. Sélectionnez la classe - seules les classes de l'année sélectionnée seront affichées\n5. Sélectionnez l'élève - seuls les élèves inscrits dans la classe et l'année sélectionnées seront affichés\n6. Sélectionnez le devoir/examen - filtré par l'année scolaire sélectionnée\n7. Sélectionnez la matière - filtrée par le devoir sélectionné (matières disponibles dans ce devoir)\n8. Entrez la note obtenue\n9. Cliquez sur 'Enregistrer'\n\nImportant : L'ordre de sélection est important : année scolaire → classe → devoir → matière. Tous les champs sont filtrés en fonction des sélections précédentes.";
            }
        }
        
        // Guide pour consulter les bulletins
        if (strpos($messageLower, 'bulletin') !== false || strpos($messageLower, 'report card') !== false) {
            if ($isEnglish) {
                if ($role === 'enseignant' || $role === 'admin' || $role === 'directeur') {
                    return "To view report cards:\n1. Go to the 'Student Management' section\n2. Click on a student to see their details\n3. In the 'Report Cards' tab, you'll see all the student's report cards\n4. Click on a report card to view it in detail or print it";
                } else {
                    return "To view your report card:\n1. Go to your profile or the 'Report Cards' section\n2. Select the school year and desired period\n3. Click on the report card to view it in detail or print it";
                }
            } else {
                if ($role === 'enseignant' || $role === 'admin' || $role === 'directeur') {
                    return "Pour consulter les bulletins :\n1. Allez dans la section 'Gestion des élèves'\n2. Cliquez sur un élève pour voir ses détails\n3. Dans l'onglet 'Bulletins', vous verrez tous les bulletins de l'élève\n4. Cliquez sur un bulletin pour le voir en détail ou l'imprimer";
                } else {
                    return "Pour consulter votre bulletin :\n1. Allez dans votre profil ou la section 'Bulletins'\n2. Sélectionnez l'année scolaire et la période souhaitée\n3. Cliquez sur le bulletin pour le voir en détail ou l'imprimer";
                }
            }
        }
        
        // Guide pour les paiements
        if (strpos($messageLower, 'paiement') !== false || strpos($messageLower, 'payer') !== false || 
            strpos($messageLower, 'écolage') !== false || strpos($messageLower, 'payment') !== false) {
            if ($isEnglish) {
                if ($role === 'admin' || $role === 'directeur') {
                    return "To record a payment:\n1. Go to the 'Payment Management' section\n2. Click on 'New Payment'\n3. Select the student concerned\n4. Enter the amount and payment type (Tuition, Registration, Other)\n5. Select the payment date\n6. Click 'Save'";
                } else {
                    return "To view your payments:\n1. Go to your profile or the 'Payments' section\n2. You'll see your payment history and remaining balance";
                }
            } else {
                if ($role === 'admin' || $role === 'directeur') {
                    return "Pour enregistrer un paiement :\n1. Allez dans la section 'Gestion des paiements'\n2. Cliquez sur 'Nouveau paiement'\n3. Sélectionnez l'élève concerné\n4. Entrez le montant et le type de paiement (Écolage, Inscription, Autre)\n5. Sélectionnez la date de paiement\n6. Cliquez sur 'Enregistrer'";
                } else {
                    return "Pour consulter vos paiements :\n1. Allez dans votre profil ou la section 'Paiements'\n2. Vous verrez l'historique de vos paiements et le solde restant";
                }
            }
        }
        
        // Guide pour les classes
        if (strpos($messageLower, 'classe') !== false || strpos($messageLower, 'salle') !== false || strpos($messageLower, 'class') !== false) {
            if ($isEnglish) {
                return "To manage classes:\n1. Go to the 'Class Management' section\n2. Select a school year first - classes are filtered by school year\n3. You can view all classes for the selected year, add new ones or modify existing ones\n4. For each class, you can manage subjects (filtered by school year), teachers and students\n\nImportant: Always select a school year before working with classes, as classes are specific to each school year.";
            } else {
                return "Pour gérer les classes :\n1. Allez dans la section 'Gestion des classes'\n2. Sélectionnez d'abord une année scolaire - les classes sont filtrées par année scolaire\n3. Vous pouvez voir toutes les classes de l'année sélectionnée, en ajouter de nouvelles ou modifier les existantes\n4. Pour chaque classe, vous pouvez gérer les matières (filtrées par année scolaire), les enseignants et les élèves\n\nImportant : Toujours sélectionner une année scolaire avant de travailler avec les classes, car les classes sont spécifiques à chaque année scolaire.";
            }
        }
        
        // Guide pour les statistiques
        if (strpos($messageLower, 'statistique') !== false || strpos($messageLower, 'tableau de bord') !== false || 
            strpos($messageLower, 'dashboard') !== false || strpos($messageLower, 'statistics') !== false ||
            strpos($messageLower, 'vente') !== false || strpos($messageLower, 'sales') !== false) {
            if ($isEnglish) {
                return "The dashboard displays:\n- General statistics (number of students, classes, teachers)\n- Financial statistics (revenue, payments)\n- Enrollments by school year\n- Latest additions and recent activities";
            } else {
                return "Le tableau de bord affiche :\n- Les statistiques générales (nombre d'élèves, classes, enseignants)\n- Les statistiques financières (revenus, paiements)\n- Les inscriptions par année scolaire\n- Les derniers ajouts et activités récentes";
            }
        }
        
        // Guide pour installer l'application PWA
        if ((strpos($messageLower, 'installer') !== false || strpos($messageLower, 'install') !== false || strpos($messageLower, 'télécharger') !== false || strpos($messageLower, 'download') !== false) &&
            (strpos($messageLower, 'app') !== false || strpos($messageLower, 'application') !== false || strpos($messageLower, 'pwa') !== false || strpos($messageLower, 'mobile') !== false || strpos($messageLower, 'local') !== false)) {
            if ($isEnglish) {
                return "To install AcademiaPro as a Progressive Web App (PWA) on your device:\n\n**On Desktop (Chrome/Edge):**\n1. Look for the install icon (+) in the address bar\n2. Click it and select 'Install'\n3. Or go to the menu (three dots) → 'Install AcademiaPro'\n\n**On Mobile (Android/Chrome):**\n1. Open the site in Chrome\n2. Tap the menu (three dots) → 'Add to Home screen' or 'Install app'\n3. Confirm the installation\n\n**On Mobile (iOS/Safari):**\n1. Open the site in Safari\n2. Tap the Share button (square with arrow)\n3. Select 'Add to Home Screen'\n4. Customize the name if needed and tap 'Add'\n\n**Note:** The app must be accessed via HTTPS for installation to work. If you see an install prompt at the top of the page, you can click it directly.\n\nOnce installed, you can access AcademiaPro like a native app from your home screen or applications menu.";
            } else {
                return "Pour installer AcademiaPro en tant qu'application web progressive (PWA) sur votre appareil :\n\n**Sur ordinateur (Chrome/Edge) :**\n1. Cherchez l'icône d'installation (+) dans la barre d'adresse\n2. Cliquez dessus et sélectionnez 'Installer'\n3. Ou allez dans le menu (trois points) → 'Installer AcademiaPro'\n\n**Sur mobile (Android/Chrome) :**\n1. Ouvrez le site dans Chrome\n2. Appuyez sur le menu (trois points) → 'Ajouter à l'écran d'accueil' ou 'Installer l'application'\n3. Confirmez l'installation\n\n**Sur mobile (iOS/Safari) :**\n1. Ouvrez le site dans Safari\n2. Appuyez sur le bouton Partager (carré avec flèche)\n3. Sélectionnez 'Sur l'écran d'accueil'\n4. Personnalisez le nom si nécessaire et appuyez sur 'Ajouter'\n\n**Note :** L'application doit être accessible via HTTPS pour que l'installation fonctionne. Si vous voyez une invite d'installation en haut de la page, vous pouvez cliquer dessus directement.\n\nUne fois installée, vous pouvez accéder à AcademiaPro comme une application native depuis votre écran d'accueil ou le menu des applications.";
            }
        }
        
        return null;
    }

    /**
     * Vérifie si la question concerne les informations de l'utilisateur
     */
    private function getUserInfoResponse($messageLower, $role, $user, $isEnglish = false)
    {
        // Questions sur le rôle
        if (strpos($messageLower, 'rôle') !== false || strpos($messageLower, 'role') !== false || 
            strpos($messageLower, 'qui suis') !== false || strpos($messageLower, 'mon rôle') !== false ||
            strpos($messageLower, 'mon role') !== false || strpos($messageLower, 'who am i') !== false ||
            strpos($messageLower, 'my role') !== false) {
            
            if ($isEnglish) {
                $roleNames = [
                    'admin' => 'Administrator',
                    'directeur' => 'Director',
                    'enseignant' => 'Teacher',
                    'eleve' => 'Student',
                    'parent' => 'Parent',
                ];
                $roleName = $roleNames[$role] ?? 'User';
                $userName = $user->name ?? 'User';
                return "You are logged in as **{$roleName}**.\n\nName: {$userName}\n\n";
            } else {
                $roleNames = [
                    'admin' => 'Administrateur',
                    'directeur' => 'Directeur',
                    'enseignant' => 'Enseignant',
                    'eleve' => 'Élève',
                    'parent' => 'Parent',
                ];
                $roleName = $roleNames[$role] ?? 'Utilisateur';
                $userName = $user->name ?? 'Utilisateur';
                return "Vous êtes connecté en tant que **{$roleName}**.\n\nNom : {$userName}\n\n";
            }
        }
        
        // Questions sur le statut admin
        if ((strpos($messageLower, 'admin') !== false && (strpos($messageLower, 'suis') !== false || strpos($messageLower, 'es-tu') !== false || strpos($messageLower, 'êtes-vous') !== false)) ||
            (strpos($messageLower, 'am i admin') !== false || strpos($messageLower, 'are you admin') !== false)) {
            if ($isEnglish) {
                if ($role === 'admin') {
                    return "Yes, you are an Administrator. You have access to all platform features: user management, configuration, statistics, finances, etc.";
                } else {
                    $roleNames = [
                        'directeur' => 'Director',
                        'enseignant' => 'Teacher',
                        'eleve' => 'Student',
                        'parent' => 'Parent',
                    ];
                    $currentRole = $roleNames[$role] ?? 'User';
                    return "No, you are not an Administrator. Your current role is: {$currentRole}.";
                }
            } else {
                if ($role === 'admin') {
                    return "Oui, vous êtes Administrateur. Vous avez accès à toutes les fonctionnalités de la plateforme : gestion des utilisateurs, configuration, statistiques, finances, etc.";
                } else {
                    return "Non, vous n'êtes pas Administrateur. Votre rôle actuel est : " . ($role === 'directeur' ? 'Directeur' : ($role === 'enseignant' ? 'Enseignant' : 'Utilisateur')) . ".";
                }
            }
        }
        
        return null;
    }

    /**
     * Vérifie si la question est hors sujet (non liée à la plateforme)
     */
    private function isOffTopic($messageLower)
    {
        // Mots-clés indiquant des questions hors sujet
        $offTopicKeywords = [
            'venezuela', 'venezuella', 'pays', 'monde', 'actualité', 'actualite', 'news', 'nouvelles',
            'politique', 'sport', 'football', 'foot', 'match', 'championnat',
            'météo', 'meteo', 'temps', 'climat',
            'cuisine', 'recette', 'manger', 'restaurant',
            'film', 'cinéma', 'cinema', 'série', 'serie', 'netflix',
            'musique', 'chanson', 'artiste',
            'voyage', 'vacances', 'hôtel', 'hotel',
            'santé', 'sante', 'médecine', 'medecine', 'docteur',
            'science', 'histoire', 'géographie', 'geographie',
            'économie', 'economie', 'bourse', 'finance mondiale',
        ];
        
        foreach ($offTopicKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return true;
            }
        }
        
        // Questions commençant par "qu'est-ce qui se passe" ou "que se passe" sans contexte plateforme
        if ((strpos($messageLower, 'qu\'est-ce qui se passe') !== false || strpos($messageLower, 'que se passe') !== false) &&
            strpos($messageLower, 'élève') === false && strpos($messageLower, 'eleve') === false &&
            strpos($messageLower, 'classe') === false && strpos($messageLower, 'note') === false &&
            strpos($messageLower, 'paiement') === false && strpos($messageLower, 'bulletin') === false) {
            return true;
        }
        
        return false;
    }

    /**
     * Réponse pour les questions hors sujet
     */
    private function getOffTopicResponse($role, $currentPage, $isEnglish = false)
    {
        if ($isEnglish) {
            $suggestions = [];
            
            if ($role === 'admin' || $role === 'directeur') {
                $suggestions = [
                    "How to add a student?",
                    "How to view statistics?",
                    "How to manage payments?",
                    "How to create a class?",
                ];
            } elseif ($role === 'enseignant') {
                $suggestions = [
                    "How to add a grade?",
                    "How to view my students' report cards?",
                    "How to see my classes?",
                ];
            } else {
                $suggestions = [
                    "How to view my report card?",
                    "How to see my grades?",
                    "How to check my payments?",
                ];
            }
            
            $suggestionsText = implode("\n- ", $suggestions);
            
            return "I'm sorry, but I can only answer questions about the school management platform.\n\nI can help you with:\n- {$suggestionsText}\n\nWhat would you like to do on the platform?";
        } else {
            $suggestions = [];
            
            if ($role === 'admin' || $role === 'directeur') {
                $suggestions = [
                    "Comment ajouter un élève ?",
                    "Comment consulter les statistiques ?",
                    "Comment gérer les paiements ?",
                    "Comment créer une classe ?",
                ];
            } elseif ($role === 'enseignant') {
                $suggestions = [
                    "Comment ajouter une note ?",
                    "Comment consulter les bulletins de mes élèves ?",
                    "Comment voir mes classes ?",
                ];
            } else {
                $suggestions = [
                    "Comment consulter mon bulletin ?",
                    "Comment voir mes notes ?",
                    "Comment consulter mes paiements ?",
                ];
            }
            
            $suggestionsText = implode("\n- ", $suggestions);
            
            return "Je suis désolé, mais je ne peux répondre qu'aux questions concernant la plateforme de gestion scolaire.\n\nJe peux vous aider avec :\n- {$suggestionsText}\n\nQue souhaitez-vous faire sur la plateforme ?";
        }
    }

    /**
     * Réponses basées sur les mots-clés
     */
    private function getKeywordBasedResponse($messageLower, $role, $currentPage, $isEnglish = false)
    {
        // Salutations
        if (strpos($messageLower, 'bonjour') !== false || strpos($messageLower, 'salut') !== false || strpos($messageLower, 'bonsoir') !== false) {
            return "Bonjour ! Je suis votre assistant virtuel. Je peux vous aider à naviguer dans la plateforme et à effectuer vos tâches. Que souhaitez-vous faire ?";
        }
        
        if (strpos($messageLower, 'hello') !== false || strpos($messageLower, 'hi') !== false || 
            strpos($messageLower, 'how are you') !== false || strpos($messageLower, 'good morning') !== false ||
            strpos($messageLower, 'good afternoon') !== false || strpos($messageLower, 'good evening') !== false) {
            return "Hello! I'm your virtual assistant. I can help you navigate the platform and perform your tasks. What would you like to do?";
        }
        
        // Remerciements
        if (strpos($messageLower, 'merci') !== false || strpos($messageLower, 'remerci') !== false) {
            return "De rien ! N'hésitez pas si vous avez d'autres questions. Je suis là pour vous aider !";
        }
        
        if (strpos($messageLower, 'thank you') !== false || strpos($messageLower, 'thanks') !== false) {
            return "You're welcome! Feel free to ask if you have any other questions. I'm here to help!";
        }
        
        // Questions sur la langue
        if (strpos($messageLower, 'i speak only english') !== false || strpos($messageLower, 'english only') !== false) {
            return "I understand. I can communicate in English. How can I help you with the school management platform?";
        }
        
        // Aide générale
        if (strpos($messageLower, 'aide') !== false || strpos($messageLower, 'help') !== false) {
            $helpText = "Je peux vous aider avec :\n";
            $helpText .= "- Ajouter ou modifier des élèves\n";
            $helpText .= "- Gérer les notes et bulletins\n";
            $helpText .= "- Gérer les paiements\n";
            $helpText .= "- Consulter les statistiques\n";
            $helpText .= "- Naviguer dans les différentes sections\n\n";
            $helpText .= "Posez-moi une question spécifique et je vous guiderai étape par étape !";
            return $helpText;
        }
        
        // Questions sur les statistiques/ventes
        if (strpos($messageLower, 'statistique') !== false || strpos($messageLower, 'vente') !== false || 
            strpos($messageLower, 'chiffre') !== false || strpos($messageLower, 'revenu') !== false) {
            return "Pour consulter les statistiques financières :\n1. Allez sur le Tableau de bord\n2. Vous verrez la section 'Aperçu Financier' avec :\n   - Total dû\n   - Total payé\n   - Non payé\n   - Reste à payer\n3. La section 'Chiffre d'Affaire par Année Scolaire' montre les revenus par année\n4. Vous pouvez aussi voir les statistiques des inscriptions par année scolaire";
        }
        
        // Réponse par défaut - seulement si vraiment aucune correspondance
        return "Je comprends votre demande. Pouvez-vous être plus spécifique ? Par exemple, vous pouvez me demander comment ajouter un élève, enregistrer une note, consulter un bulletin, ou gérer les paiements. Je suis là pour vous aider !";
    }

    /**
     * Réponses sur les informations de l'école (horaires, dates clés, procédures)
     */
    private function getSchoolInfoResponse($messageLower, $user, $isEnglish = false)
    {
        // Questions sur les horaires
        if (strpos($messageLower, 'horaire') !== false || strpos($messageLower, 'emploi du temps') !== false ||
            strpos($messageLower, 'schedule') !== false || strpos($messageLower, 'timetable') !== false ||
            (strpos($messageLower, 'quand') !== false && (strpos($messageLower, 'cours') !== false || strpos($messageLower, 'classe') !== false))) {
            
            try {
                $schoolYear = SchoolYear::where('is_active', true)->first();
                if ($schoolYear) {
                    if ($isEnglish) {
                        return "To view schedules:\n1. Go to the 'Schedules' section\n2. Select a school year first (required)\n3. Then select a class - only classes for the selected year will be displayed\n4. You'll see all courses for that class with times and days\n\nTo create a schedule:\n1. Go to 'Schedules' and click 'Add Schedule'\n2. Select the school year (required)\n3. Select the class (filtered by the selected year)\n4. Select the subject (filtered by the selected year and class)\n5. Select the teacher, day, start time, end time, and room\n6. Click 'Save'\n\nImportant: Classes and subjects are filtered by school year. Always select the school year first.\n\nFor the current school year ({$schoolYear->label}), schedules are available in the platform.";
                    } else {
                        return "Pour consulter les emplois du temps :\n1. Allez dans la section 'Emplois du temps'\n2. Sélectionnez d'abord une année scolaire (obligatoire)\n3. Ensuite sélectionnez une classe - seules les classes de l'année sélectionnée seront affichées\n4. Vous verrez tous les cours de cette classe avec les horaires et jours\n\nPour créer un emploi du temps :\n1. Allez dans 'Emplois du temps' et cliquez sur 'Ajouter un emploi du temps'\n2. Sélectionnez l'année scolaire (obligatoire)\n3. Sélectionnez la classe (filtrée par l'année sélectionnée)\n4. Sélectionnez la matière (filtrée par l'année et la classe sélectionnées)\n5. Sélectionnez l'enseignant, le jour, l'heure de début, l'heure de fin et la salle\n6. Cliquez sur 'Enregistrer'\n\nImportant : Les classes et matières sont filtrées par année scolaire. Toujours sélectionner l'année scolaire en premier.\n\nPour l'année scolaire en cours ({$schoolYear->label}), les horaires sont disponibles sur la plateforme.";
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error fetching school year for schedule', ['error' => $e->getMessage()]);
            }
            
            if ($isEnglish) {
                return "To view schedules:\n1. Go to the 'Schedules' section\n2. Select a school year first (required)\n3. Then select a class - only classes for the selected year will be displayed\n4. You'll see all courses for that class\n\nTo create a schedule, select the school year first, then the class, then the subject (all filtered by school year).";
            } else {
                return "Pour consulter les emplois du temps :\n1. Allez dans la section 'Emplois du temps'\n2. Sélectionnez d'abord une année scolaire (obligatoire)\n3. Ensuite sélectionnez une classe - seules les classes de l'année sélectionnée seront affichées\n4. Vous verrez tous les cours de cette classe\n\nPour créer un emploi du temps, sélectionnez d'abord l'année scolaire, puis la classe, puis la matière (tout filtré par année scolaire).";
            }
        }
        
        // Questions sur les dates clés (début/fin d'année, périodes)
        if (strpos($messageLower, 'date') !== false || strpos($messageLower, 'période') !== false ||
            strpos($messageLower, 'trimestre') !== false || strpos($messageLower, 'semestre') !== false ||
            strpos($messageLower, 'début') !== false || strpos($messageLower, 'fin') !== false ||
            strpos($messageLower, 'année scolaire') !== false) {
            
            try {
                $schoolYear = SchoolYear::where('is_active', true)->first();
                if ($schoolYear) {
                    $startDate = $schoolYear->start_date ? Carbon::parse($schoolYear->start_date)->format('d/m/Y') : 'Non définie';
                    $endDate = $schoolYear->end_date ? Carbon::parse($schoolYear->end_date)->format('d/m/Y') : 'Non définie';
                    $periodSystem = $schoolYear->period_system === 'trimester' ? 'Trimestres' : 'Semestres';
                    $totalPeriods = $schoolYear->total_periods ?? 0;
                    
                    if ($isEnglish) {
                        $response = "**Current School Year: {$schoolYear->label}**\n\n";
                        $response .= "Start Date: {$startDate}\n";
                        $response .= "End Date: {$endDate}\n";
                        $response .= "Period System: {$periodSystem} ({$totalPeriods} periods)\n\n";
                        $response .= "You can view all important dates and periods in the 'School Years' section.";
                        return $response;
                    } else {
                        $response = "**Année scolaire en cours : {$schoolYear->label}**\n\n";
                        $response .= "Date de début : {$startDate}\n";
                        $response .= "Date de fin : {$endDate}\n";
                        $response .= "Système de périodes : {$periodSystem} ({$totalPeriods} périodes)\n\n";
                        $response .= "Vous pouvez consulter toutes les dates importantes et périodes dans la section 'Années scolaires'.";
                        return $response;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error fetching school year for dates', ['error' => $e->getMessage()]);
            }
            
            if ($isEnglish) {
                return "You can find all important dates (start/end of school year, periods) in the 'School Years' section of the platform.";
            } else {
                return "Vous pouvez trouver toutes les dates importantes (début/fin d'année scolaire, périodes) dans la section 'Années scolaires' de la plateforme.";
            }
        }
        
        // Questions sur les procédures d'inscription
        if (strpos($messageLower, 'inscription') !== false || strpos($messageLower, 's\'inscrire') !== false ||
            strpos($messageLower, 'inscrire') !== false || strpos($messageLower, 'enrollment') !== false ||
            strpos($messageLower, 'register') !== false) {
            
            if ($isEnglish) {
                return "**Enrollment Procedure:**\n\n1. Contact the school administration\n2. Complete the enrollment form with required documents\n3. Submit the form through the platform or in person\n4. Pay the registration fees\n5. Once approved, you'll receive access to the platform\n\nFor more details, contact the school administration or check the 'Students' section if you're an administrator.";
            } else {
                return "**Procédure d'inscription :**\n\n1. Contactez l'administration de l'école\n2. Remplissez le formulaire d'inscription avec les documents requis\n3. Soumettez le formulaire via la plateforme ou en personne\n4. Effectuez le paiement des frais d'inscription\n5. Une fois approuvé, vous recevrez l'accès à la plateforme\n\nPour plus de détails, contactez l'administration ou consultez la section 'Élèves' si vous êtes administrateur.";
            }
        }
        
        // Questions sur les contacts de l'école
        if (strpos($messageLower, 'contact') !== false || strpos($messageLower, 'téléphone') !== false ||
            strpos($messageLower, 'adresse') !== false || strpos($messageLower, 'email') !== false ||
            strpos($messageLower, 'phone') !== false || strpos($messageLower, 'address') !== false) {
            
            try {
                $school = School::where('is_active', true)->first();
                if ($school) {
                    if ($isEnglish) {
                        $response = "**School Contact Information:**\n\n";
                        if ($school->name) $response .= "Name: {$school->name}\n";
                        if ($school->address) $response .= "Address: {$school->address}\n";
                        if ($school->phone) $response .= "Phone: {$school->phone}\n";
                        if ($school->email) $response .= "Email: {$school->email}\n";
                        return $response;
                    } else {
                        $response = "**Informations de contact de l'école :**\n\n";
                        if ($school->name) $response .= "Nom : {$school->name}\n";
                        if ($school->address) $response .= "Adresse : {$school->address}\n";
                        if ($school->phone) $response .= "Téléphone : {$school->phone}\n";
                        if ($school->email) $response .= "Email : {$school->email}\n";
                        return $response;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error fetching school contact info', ['error' => $e->getMessage()]);
            }
            
            if ($isEnglish) {
                return "You can find the school's contact information in the 'School Information' section of the dashboard.";
            } else {
                return "Vous pouvez trouver les informations de contact de l'école dans la section 'Informations de l'école' du tableau de bord.";
            }
        }
        
        // Questions sur les procédures de paiement
        if ((strpos($messageLower, 'procédure') !== false && strpos($messageLower, 'paiement') !== false) ||
            strpos($messageLower, 'comment payer') !== false || strpos($messageLower, 'how to pay') !== false) {
            
            if ($isEnglish) {
                return "**Payment Procedure:**\n\n1. Go to the 'Payment Management' section\n2. Click on 'New Payment'\n3. Select the student\n4. Enter the amount and payment type (Tuition, Registration, Other)\n5. Select the payment date\n6. Save the payment\n\nPayments can be made online through the platform or in person at the school administration.";
            } else {
                return "**Procédure de paiement :**\n\n1. Allez dans la section 'Gestion des paiements'\n2. Cliquez sur 'Nouveau paiement'\n3. Sélectionnez l'élève\n4. Entrez le montant et le type de paiement (Écolage, Inscription, Autre)\n5. Sélectionnez la date de paiement\n6. Enregistrez le paiement\n\nLes paiements peuvent être effectués en ligne via la plateforme ou en personne à l'administration de l'école.";
            }
        }
        
        // Questions sur les documents requis
        if (strpos($messageLower, 'document') !== false || strpos($messageLower, 'papier') !== false ||
            strpos($messageLower, 'pièce') !== false || strpos($messageLower, 'required') !== false) {
            
            if ($isEnglish) {
                return "**Required Documents for Enrollment:**\n\n- Birth certificate or ID\n- Previous school transcripts\n- Medical certificate\n- Recent photos\n- Registration form\n\nFor specific requirements, please contact the school administration.";
            } else {
                return "**Documents requis pour l'inscription :**\n\n- Acte de naissance ou pièce d'identité\n- Bulletins de l'année précédente\n- Certificat médical\n- Photos récentes\n- Formulaire d'inscription\n\nPour des exigences spécifiques, veuillez contacter l'administration de l'école.";
            }
        }
        
        return null;
    }
}

