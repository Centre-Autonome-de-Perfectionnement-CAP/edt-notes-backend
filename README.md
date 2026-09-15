# Backend Modules — CAP · Module Emploi du Temps & Suivi des Notes

> Documentation technique du backend Laravel pour le **Centre Autonome de Perfectionnement (CAP / RdivFC)** de l'EPAC / UAC.
> Ce dépôt implémente le module **Emploi du Temps** et le module **Suivi des Notes & Archivage**.

---

## 1. Contexte du projet

Ce système répond à deux problématiques majeures :

1. **Diffusion de l'emploi du temps centralisée & officielle** :
   - Fin de la circulation informelle par WhatsApp.
   - Génération hebdomadaire par filière au format officiel du template RdivFC (grille A4 paysage dynamique).
   - Validation stricte des coordonnées enseignants (téléphone requis) pour la diffusion.
   - Personnalisation de la couleur par matière (palette officielle à 8 teintes).
   - Export PDF et CSV à plat pour l'ensemble des acteurs (étudiants, enseignants, secrétariat, responsables).
   - Diffusion en temps réel des créations, modifications et annulations de séances via Laravel Reverb.

2. **Suivi des notes & délibérations en temps réel** :
   - Dépôt et validation des fiches de notes avec calcul d'empreinte HMAC.
   - Export PDF récapitulatif avec QR code sécurisé.
   - Archivage physique avec confirmation par scan QR réservé au secrétariat.
   - Tableau de bord pour le responsable pédagogique.

---

## 2. Stack technique

| Composant | Choix | Rôle & Justification |
|---|---|---|
| **Framework** | Laravel 12 (PHP 8.2+) | Architecture modulaire (pp/Modules/Timetable, pp/Modules/GradeTracking) |
| **Authentification** | Laravel Sanctum | Protection des endpoints API par token Bearer et RBAC |
| **Temps réel** | Laravel Reverb | WebSockets natifs haute performance (canaux privés par filière) |
| **Génération PDF** | arryvdh/laravel-dompdf | Grille A4 paysage dynamique et fiches récapitulatives avec QR |
| **Tests automatisés** | PHPUnit | Suite complète de 23 tests automatisés |

---

## 3. Installation & Démarrage

### 3.1 Prérequis
- PHP ≥ 8.2 (extensions pdo_mysql ou pdo_sqlite, gd, mbstring, zip)
- Composer
- Base de données MySQL ou SQLite

### 3.2 Commandes d'installation

`ash
# 1. Cloner le projet
git clone https://github.com/Centre-Autonome-de-Perfectionnement-CAP/edt-notes-backend.git
cd edt-notes-backend

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 4. Migrations & Seeders
php artisan migrate
php artisan db:seed --class=TimetableSeeder

# 5. Lancer les 3 processus nécessaires en développement
php artisan serve            # Serveur API HTTP (Port 8000)
php artisan reverb:start     # Serveur WebSocket Reverb (Port 8080)
php artisan queue:work       # Worker de diffusion des événements broadcast
`

---

## 4. Endpoints API — Module Timetable

Base URL : /api/v1/timetable (Authentification requise : uth:sanctum)

### 4.1 Consultation (Accessible à tous les rôles)
| Méthode | Route | Description |
|---|---|---|
| GET | /filieres | Liste des filières disponibles |
| GET | /filieres/{id} | Séances d'une filière (date_debut, date_fin en filtres) |
| GET | /filieres/{id}/modules | Modules d'une filière |
| GET | /enseignants | Liste des enseignants |
| GET | /enseignants/{id} | Séances programmées pour un enseignant |
| GET | /seances | Toutes les séances |
| GET | /emploi-du-temps | Liste des emplois du temps hebdomadaires générés |
| GET | /emploi-du-temps/latest | Dernier emploi du temps généré pour une filière |

### 4.2 Programmation & Gestion (Réservé au esponsable_pedagogique via ole.responsable)
| Méthode | Route | Description |
|---|---|---|
| POST | /modules | Création d'un module d'enseignement |
| PATCH | /modules/{id}/couleur | Mise à jour de la couleur du module (palette 8 teintes) |
| POST | /seances | Programmation d'une séance (détection de conflit 409) |
| PUT | /seances/{id} | Modification / report d'une séance |
| DELETE | /seances/{id} | Annulation logique de séance (statut = annule) |
| POST | /emploi-du-temps | Création de l'en-tête (validation des numéros de tél. 422) |

### 4.3 Exports Officiels (Accessible à tous les rôles)
| Méthode | Route | Description |
|---|---|---|
| GET | /emploi-du-temps/{id}/export | Génération du flux PDF officiel A4 paysage |
| GET | /emploi-du-temps/{id}/export-csv | Export du flux CSV plat avec en-têtes et BOM UTF-8 |

---

## 5. Diffusion Temps Réel (Laravel Reverb)

- **Canal privé par filière** : private-filiere.{filiere_id}.timetable
- **Événement** : SeanceProgrammeeEvent (roadcastAs: seance.programmee)
- **Actions diffusées** : created, updated, cancelled

---

## 6. Tests Automatisés

Pour exécuter la suite complète de tests backend :

`ash
php artisan test
`

Résultat : **23 / 23 tests passés avec succès** (79 assertions).