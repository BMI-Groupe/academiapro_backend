<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcademiaPro - Système de Gestion Scolaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }
        
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.3;
            animation: blob 7s infinite;
        }
        
        @keyframes blob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 gradient-bg rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold gradient-text">AcademiaPro</span>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-600 hover:text-purple-600 transition">Fonctionnalités</a>
                    <a href="#about" class="text-gray-600 hover:text-purple-600 transition">À propos</a>
                    <a href="#contact" class="text-gray-600 hover:text-purple-600 transition">Contact</a>
                    <a href="/docs" class="px-6 py-2 gradient-bg text-white rounded-lg hover:shadow-lg transition">
                        API Docs
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden py-20 lg:py-32">
        <!-- Animated Background Blobs -->
        <div class="blob w-96 h-96 bg-purple-400 top-0 left-0"></div>
        <div class="blob w-96 h-96 bg-blue-400 bottom-0 right-0" style="animation-delay: 2s;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <h1 class="text-5xl lg:text-6xl font-bold text-gray-900 mb-6 leading-tight">
                        Gérez votre école avec
                        <span class="gradient-text">AcademiaPro</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                        Une solution complète et moderne pour la gestion scolaire. Simplifiez l'administration, suivez les performances et améliorez la communication.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="/docs" class="px-8 py-4 gradient-bg text-white rounded-lg font-semibold hover:shadow-xl transition transform hover:scale-105">
                            Découvrir l'API
                        </a>
                        <a href="#features" class="px-8 py-4 bg-white text-purple-600 rounded-lg font-semibold border-2 border-purple-600 hover:bg-purple-50 transition">
                            En savoir plus
                        </a>
                    </div>
                </div>
                <div class="hidden lg:block animate-float">
                    <svg viewBox="0 0 500 500" class="w-full h-full">
                        <defs>
                            <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <!-- Illustration abstraite d'une école -->
                        <rect x="100" y="150" width="300" height="250" rx="10" fill="url(#grad1)" opacity="0.1"/>
                        <rect x="120" y="170" width="80" height="80" rx="5" fill="url(#grad1)" opacity="0.3"/>
                        <rect x="220" y="170" width="80" height="80" rx="5" fill="url(#grad1)" opacity="0.3"/>
                        <rect x="320" y="170" width="60" height="80" rx="5" fill="url(#grad1)" opacity="0.3"/>
                        <circle cx="250" cy="100" r="40" fill="url(#grad1)" opacity="0.2"/>
                        <path d="M 200 100 L 250 60 L 300 100" stroke="url(#grad1)" stroke-width="8" fill="none" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Fonctionnalités Principales</h2>
                <p class="text-xl text-gray-600">Tout ce dont vous avez besoin pour gérer votre établissement</p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="card-hover bg-gradient-to-br from-purple-50 to-white p-8 rounded-2xl border border-purple-100">
                    <div class="w-14 h-14 gradient-bg rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Gestion des Élèves</h3>
                    <p class="text-gray-600 leading-relaxed">Inscriptions, dossiers, présences et suivi personnalisé de chaque élève.</p>
                </div>

                <!-- Feature 2 -->
                <div class="card-hover bg-gradient-to-br from-blue-50 to-white p-8 rounded-2xl border border-blue-100">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Notes & Évaluations</h3>
                    <p class="text-gray-600 leading-relaxed">Saisie des notes, calcul automatique des moyennes et génération de bulletins.</p>
                </div>

                <!-- Feature 3 -->
                <div class="card-hover bg-gradient-to-br from-green-50 to-white p-8 rounded-2xl border border-green-100">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Emplois du Temps</h3>
                    <p class="text-gray-600 leading-relaxed">Création et gestion des emplois du temps pour toutes les classes.</p>
                </div>

                <!-- Feature 4 -->
                <div class="card-hover bg-gradient-to-br from-yellow-50 to-white p-8 rounded-2xl border border-yellow-100">
                    <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Gestion Financière</h3>
                    <p class="text-gray-600 leading-relaxed">Suivi des paiements, frais de scolarité et gestion de la comptabilité.</p>
                </div>

                <!-- Feature 5 -->
                <div class="card-hover bg-gradient-to-br from-red-50 to-white p-8 rounded-2xl border border-red-100">
                    <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Rapports & Statistiques</h3>
                    <p class="text-gray-600 leading-relaxed">Tableaux de bord et rapports détaillés sur les performances.</p>
                </div>

                <!-- Feature 6 -->
                <div class="card-hover bg-gradient-to-br from-indigo-50 to-white p-8 rounded-2xl border border-indigo-100">
                    <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Application Mobile</h3>
                    <p class="text-gray-600 leading-relaxed">Accès mobile pour parents, élèves et enseignants.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-20 gradient-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 text-center text-white">
                <div>
                    <div class="text-5xl font-bold mb-2">500+</div>
                    <div class="text-xl opacity-90">Écoles</div>
                </div>
                <div>
                    <div class="text-5xl font-bold mb-2">50K+</div>
                    <div class="text-xl opacity-90">Élèves</div>
                </div>
                <div>
                    <div class="text-5xl font-bold mb-2">2K+</div>
                    <div class="text-xl opacity-90">Enseignants</div>
                </div>
                <div>
                    <div class="text-5xl font-bold mb-2">99%</div>
                    <div class="text-xl opacity-90">Satisfaction</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="contact" class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold text-gray-900 mb-6">Prêt à moderniser votre école ?</h2>
            <p class="text-xl text-gray-600 mb-8">Rejoignez des centaines d'établissements qui font confiance à AcademiaPro</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/docs" class="px-8 py-4 gradient-bg text-white rounded-lg font-semibold hover:shadow-xl transition transform hover:scale-105">
                    Consulter la Documentation
                </a>
                <a href="mailto:contact@academiapro.com" class="px-8 py-4 bg-white text-purple-600 rounded-lg font-semibold border-2 border-purple-600 hover:bg-purple-50 transition">
                    Nous Contacter
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-10 h-10 gradient-bg rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold">AcademiaPro</span>
                    </div>
                    <p class="text-gray-400">La solution complète pour la gestion scolaire moderne.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Produit</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#features" class="hover:text-white transition">Fonctionnalités</a></li>
                        <li><a href="/docs" class="hover:text-white transition">API</a></li>
                        <li><a href="#" class="hover:text-white transition">Tarifs</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Entreprise</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#about" class="hover:text-white transition">À propos</a></li>
                        <li><a href="#" class="hover:text-white transition">Blog</a></li>
                        <li><a href="#contact" class="hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Légal</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white transition">Confidentialité</a></li>
                        <li><a href="#" class="hover:text-white transition">Conditions</a></li>
                        <li><a href="#" class="hover:text-white transition">Sécurité</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center text-gray-400">
                <p>&copy; {{ date('Y') }} AcademiaPro. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
