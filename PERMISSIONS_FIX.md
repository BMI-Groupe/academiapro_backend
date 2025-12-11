# 🔐 Correction des Permissions - Rôle Admin

## 🐛 Problème identifié

L'utilisateur **admin** était connecté avec succès (token présent), mais toutes les requêtes API retournaient **403 (Forbidden)**.

### Cause
Les routes dans `routes/api.php` étaient protégées par le middleware `role:directeur,enseignant`, mais le rôle `admin` n'était pas inclus dans la liste des rôles autorisés.

## ✅ Solution appliquée

### Modifications dans `routes/api.php`

1. **Routes de lecture (ligne 38)** - Accès pour admin, directeur et enseignant
   ```php
   Route::middleware(['role:admin,directeur,enseignant'])->group(function () {
   ```
   
   Routes concernées :
   - `GET /school-years/active`
   - `GET /students`
   - `GET /classrooms`
   - `GET /teachers`
   - `GET /subjects`
   - `GET /grades`
   - `GET /schedules`
   - etc.

2. **Routes d'administration (ligne 66)** - Accès pour admin et directeur
   ```php
   Route::middleware(['role:admin,directeur'])->group(function () {
   ```
   
   Routes concernées :
   - `POST /classrooms` (création)
   - `PUT /classrooms/{id}` (modification)
   - `DELETE /classrooms/{id}` (suppression)
   - `POST /teachers`
   - `POST /students`
   - `POST /payments`
   - etc.

3. **Route d'enregistrement (ligne 33)** - Accès pour admin et directeur
   ```php
   Route::middleware(['role:admin,directeur'])->group(function () {
       Route::post('register', [AuthController::class, 'register']);
   });
   ```

## 🎯 Hiérarchie des rôles

| Rôle | Permissions |
|------|-------------|
| **admin** | Accès complet à toutes les fonctionnalités (lecture + écriture) |
| **directeur** | Accès complet à toutes les fonctionnalités (lecture + écriture) |
| **enseignant** | Accès en lecture seule + gestion des notes |

## 🧪 Test

Pour tester que ça fonctionne :

1. Connectez-vous avec l'admin :
   - Téléphone : `600000000`
   - Mot de passe : `password`

2. Les requêtes suivantes devraient maintenant fonctionner :
   - `GET /api/v1.0.0/school-years/active` ✅
   - `GET /api/v1.0.0/students` ✅
   - `GET /api/v1.0.0/classrooms` ✅
   - `GET /api/v1.0.0/teachers` ✅
   - `GET /api/v1.0.0/schedules` ✅

## 📝 Notes

- Le middleware `EnsureUserHasRole` vérifie que le rôle de l'utilisateur est dans la liste des rôles autorisés
- Les logs Laravel (dans `storage/logs/laravel.log`) enregistrent les tentatives d'accès refusées pour faciliter le débogage
- Le rôle `admin` a maintenant les mêmes permissions que le `directeur`
