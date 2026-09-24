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
   - **Interface Délégué (Responsable de classe)** : consultation des notes de sa filière avec **règle de rétention/recours de 48h** (déverrouillage automatique avec décompte).

---

## 2. Stack technique

| Composant | Choix | Rôle & Justification |
|---|---|---|
| **Framework** | Laravel 12 (PHP 8.2+) | Architecture modulaire (`app/Modules/Timetable`, `app/Modules/GradeTracking`) |
| **Authentification** | Laravel Sanctum | Protection des endpoints API par token Bearer et RBAC |
| **Temps réel** | Laravel Reverb | WebSockets natifs haute performance (canaux privés par filière) |
| **Génération PDF** | `barryvdh/laravel-dompdf` | Grille A4 paysage dynamique et fiches récapitulatives avec QR |
| **Tests automatisés** | PHPUnit | Suite complète de 25 tests automatisés |

---

## 3. Installation & Démarrage

### 3.1 Prérequis
- PHP ≥ 8.2 (extensions pdo_mysql ou pdo_sqlite, gd, mbstring, zip)
- Composer
- Base de données MySQL ou SQLite

### 3.2 Commandes d'installation

```bash
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
php artisan db:seed

# 5. Lancer les 3 processus nécessaires en développement
php artisan serve            # Serveur API HTTP (Port 8000)
php artisan reverb:start     # Serveur WebSocket Reverb (Port 8080)
php artisan queue:work       # Worker de diffusion des événements broadcast
```

---

## 4. Endpoints API

Base URL : `/api/v1` (Authentification requise : `auth:sanctum`)

### 4.1 Module Timetable — Consultation (Tous rôles)
| Méthode | Route | Description |
|---|---|---|
| GET | `/api/v1/timetable/filieres` | Liste des filières disponibles |
| GET | `/api/v1/timetable/filieres/{id}` | Séances d'une filière (`date_debut`, `date_fin` en filtres) |
| GET | `/api/v1/timetable/filieres/{id}/modules` | Modules d'une filière |
| GET | `/api/v1/timetable/enseignants` | Liste des enseignants |
| GET | `/api/v1/timetable/enseignants/{id}` | Séances programmées pour un enseignant |
| GET | `/api/v1/timetable/seances` | Toutes les séances |
| GET | `/api/v1/timetable/emploi-du-temps` | Liste des emplois du temps hebdomadaires générés |
| GET | `/api/v1/timetable/emploi-du-temps/latest` | Dernier emploi du temps généré pour une filière |

### 4.2 Module Timetable — Programmation (Réservé `responsable_pedagogique`)
| Méthode | Route | Description |
|---|---|---|
| POST | `/api/v1/timetable/modules` | Création d'un module d'enseignement |
| PATCH | `/api/v1/timetable/modules/{id}/couleur` | Mise à jour de la couleur du module (palette 8 teintes) |
| POST | `/api/v1/timetable/seances` | Programmation d'une séance (détection de conflit 409) |
| PUT | `/api/v1/timetable/seances/{id}` | Modification / report d'une séance |
| DELETE | `/api/v1/timetable/seances/{id}` | Annulation logique de séance (`statut = annule`) |
| POST | `/api/v1/timetable/emploi-du-temps` | Création de l'en-tête (validation des numéros de tél. 422) |
| PATCH | `/api/v1/timetable/enseignants/{id}/telephone` | Mise à jour rapide du téléphone d'un enseignant |

### 4.3 Module Timetable — Exports Officiels (Tous rôles)
| Méthode | Route | Description |
|---|---|---|
| GET | `/api/v1/timetable/emploi-du-temps/{id}/export` | Génération du flux PDF officiel A4 paysage dynamique |
| GET | `/api/v1/timetable/emploi-du-temps/{id}/export-csv` | Export du flux CSV plat avec en-têtes et BOM UTF-8 |

### 4.4 Module GradeTracking — Délégué / Responsable de Classe
| Méthode | Route | Description |
|---|---|---|
| GET | `/api/v1/grade-tracking/delegue/notes` | Consultation des notes de la filière avec application stricte de la règle de rétention des 48h (statut `disponible` avec notes et stats si > 48h, statut `en_attente_delai` avec décompte si < 48h). |

---

## 5. Diffusion Temps Réel (Laravel Reverb)

- **Canal privé par filière** : `private-filiere.{filiere_id}.timetable`
- **Événement** : `SeanceProgrammeeEvent` (`broadcastAs: seance.programmee`)
- **Actions diffusées** : `created`, `updated`, `cancelled`

---

## 6. Tests Automatisés

Pour exécuter la suite complète de tests backend :

```bash
php artisan test
```

Résultat : **25 / 25 tests passés avec succès** (94 assertions).