# Backend Modules — CAP · Module Emploi du Temps & Suivi des Notes

> Documentation technique du module **Emploi du Temps** (partie backend, développeur **Judicaël**).
> Rédigée pour permettre la maintenance et la reprise du projet même en l'absence des développeurs d'origine.

---

## 1. Contexte du projet

Ce module est une **extension indépendante** du système existant de l'école (Division Formation Continue et Perfectionnement — RdivFC), développée en parallèle sans impacter le projet principal en cours.

Il répond à deux problèmes :

1. **Diffusion de l'emploi du temps non centralisée** — auparavant géré par WhatsApp, sans espace unique consultable.
2. **Absence de visibilité en temps réel sur le dépôt des notes** — géré par un autre développeur (Yannick), hors périmètre de ce document.

Ce README couvre uniquement la partie **Emploi du Temps**, assignée à **Judicaël** dans le plan de répartition des tâches (équipe de 5 : Judicaël, Yannick, Ashley, Mélina, Majuste).

### Stack technique

| Composant | Choix | Pourquoi |
|---|---|---|
| Backend | Laravel 12 | Framework déjà utilisé par l'équipe |
| Temps réel | Laravel Reverb | Natif Laravel, gratuit, auto-hébergé, compatible protocole Pusher (le client Flutter utilise `pusher_channels_flutter` sans configuration exotique) |
| Export PDF | `barryvdh/laravel-dompdf` | Génération du PDF conforme au template officiel RdivFC |
| Auth API | Sanctum | Installé via `install:api`, pas encore branché sur les endpoints du module (voir §7 Limitations) |
| Mobile | Flutter (`app-cap-mobile`) | Hors périmètre de ce dépôt, consomme cette API |

---

## 2. Installation depuis zéro

### 2.1 Prérequis

- PHP ≥ 8.2
- Composer
- Node.js ≥ 20 (avec un warning connu sur `concurrently` qui demande Node 22 — non bloquant)
- Une base de données (MySQL recommandé, testé en local)

### 2.2 Étapes

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> backend-modules
cd backend-modules

# 2. Installer les dépendances PHP
composer install

# 3. Copier et configurer l'environnement
cp .env.example .env
php artisan key:generate
```

Configurer dans `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cap_backend
DB_USERNAME=root
DB_PASSWORD=

APP_LOCALE=fr
APP_FALLBACK_LOCALE=en

BROADCAST_CONNECTION=reverb
```

Puis installer Reverb (si `composer install` ne l'a pas déjà fait via le lock file — normalement si, puisqu'il est dans `composer.json`) :

```bash
php artisan install:broadcasting
```
→ Choisir **Laravel Reverb**. Cela génère automatiquement les variables `REVERB_*` dans `.env`. Si elles manquent (bug déjà rencontré une fois lors du développement — voir §8 Problèmes rencontrés), les ajouter manuellement :

```bash
echo "REVERB_APP_ID=$(date +%s)" >> .env
echo "REVERB_APP_KEY=$(openssl rand -hex 20)" >> .env
echo "REVERB_APP_SECRET=$(openssl rand -hex 20)" >> .env
echo 'REVERB_HOST="localhost"' >> .env
echo "REVERB_PORT=8080" >> .env
echo "REVERB_SCHEME=http" >> .env
```

```bash
# 4. Migrations
php artisan migrate

# 5. Données de test
php artisan db:seed --class=TimetableSeeder

# 6. Lancer les 3 process nécessaires (3 terminaux séparés)
php artisan serve            # Terminal 1 — serveur HTTP
php artisan reverb:start     # Terminal 2 — serveur WebSocket temps réel
php artisan queue:work       # Terminal 3 — traitement des jobs de broadcast
```

⚠️ **Les 3 process doivent tourner simultanément** pour que la diffusion temps réel fonctionne (voir §5.3).

---

## 3. Architecture du module

```
app/
├── Models/
│   ├── Filiere.php                          # Modèle stub (voir §7)
│   └── Cycle.php                             # Modèle stub (voir §7)
└── Modules/
    └── Timetable/
        ├── Models/
        │   ├── Module.php                    # Matière/module enseigné
        │   ├── Seance.php                     # Occurrence programmée (cours/td/tp)
        │   └── EmploiDuTemps.php               # En-tête hebdomadaire par filière
        ├── Http/
        │   ├── Controllers/
        │   │   ├── TimetableController.php     # CRUD séances/modules/emploi du temps
        │   │   └── TimetableExportController.php  # Export PDF
        │   └── Requests/
        │       ├── StoreSeanceRequest.php
        │       └── UpdateSeanceRequest.php
        ├── Events/
        │   └── SeanceProgrammeeEvent.php       # Broadcast temps réel
        └── routes/
            └── api.php                         # Toutes les routes du module

database/
├── migrations/
│   ├── ..._create_filieres_table.php           # Stub
│   ├── ..._create_cycles_table.php              # Stub
│   ├── ..._create_modules_table.php
│   ├── ..._create_seances_table.php
│   ├── ..._create_emploi_du_temps_table.php
│   └── ..._add_telephone_to_users_table.php
└── seeders/
    └── TimetableSeeder.php

resources/
└── views/
    └── pdf/
        └── emploi-du-temps.blade.php            # Template de l'export PDF

routes/
├── api.php                                      # Point d'entrée, require le module
└── channels.php                                 # Autorisation des canaux de broadcast
```

---

## 4. Modèle de données

### 4.1 Schéma des tables

| Table | Colonnes clés | Rôle |
|---|---|---|
| `filieres` *(stub)* | `nom`, `code` | Référence des filières — **à remplacer par la vraie table du système principal en intégration** |
| `cycles` *(stub)* | `libelle` | Référence des cycles — idem |
| `modules` | `filiere_id`, `cycle_id`, `intitule`, `volume_horaire`, `enseignant_id` | Une matière enseignée dans une filière |
| `seances` | `module_id`, `enseignant_id`, `date`, `heure_debut`, `heure_fin`, `salle`, `type` (enum: `cours`/`td`/`tp`), `statut` (enum: `planifie`/`annule`/`reporte`, défaut `planifie`) | Une occurrence programmée d'un module |
| `emploi_du_temps` | `filiere_id`, `division` (défaut `RdivFC`), `semestre`, `date_debut_semaine`, `date_fin_semaine`, `observation`, `contact_responsable_nom`, `contact_responsable_tel` | En-tête d'une semaine d'emploi du temps, sert de base à l'export PDF |
| `users` (modifiée) | + `telephone` (nullable) | Ajout additif pour contact enseignant/responsable |

### 4.2 Relations Eloquent

```
Filiere 1—* Module 1—* Seance *—1 User (enseignant)
Cycle   1—* Module
Filiere 1—* EmploiDuTemps
```

### 4.3 Scopes utiles sur `Seance`

- `Seance::forFiliere($filiereId)` — séances d'une filière (via le module rattaché)
- `Seance::forEnseignant($enseignantId)` — séances d'un enseignant
- `Seance::upcoming()` — séances à venir, non annulées, triées par date/heure

---

## 5. API — Endpoints disponibles

Base URL : `/api/v1/timetable`

### 5.1 Consultation

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/filieres/{filiere_id}` | Emploi du temps d'une filière. Query params optionnels : `date_debut`, `date_fin`. → `404` si filière inexistante. |
| `GET` | `/enseignants/{enseignant_id}` | Emploi du temps d'un enseignant. Mêmes query params. → `404` si enseignant inexistant. |

### 5.2 Programmation (réservé au responsable pédagogique — **RBAC non encore branché**, voir §7)

| Méthode | Route | Description |
|---|---|---|
| `POST` | `/modules` | Créer un module. Payload : `filiere_id`, `intitule`, `volume_horaire`, `enseignant_id?` |
| `POST` | `/seances` | Créer une séance. Payload : `module_id`, `enseignant_id`, `date`, `heure_debut`, `heure_fin`, `salle?`, `type`. → `409` si conflit horaire pour l'enseignant, `422` si validation échoue. |
| `PUT` | `/seances/{id}` | Modifier/reprogrammer une séance (champs `sometimes`). Mêmes règles de conflit. |
| `DELETE` | `/seances/{id}` | **Annulation logique** (`statut = annule`), pas de suppression physique. |
| `POST` | `/emploi-du-temps` | Créer l'en-tête hebdomadaire. Payload : `filiere_id`, `semestre`, `date_debut_semaine`, `date_fin_semaine`, `division?` (défaut `RdivFC`), `observation?`, `contact_responsable_nom?`, `contact_responsable_tel?` |

### 5.3 Export

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/emploi-du-temps/{id}/export` | Génère et stream un PDF (DomPDF) conforme au template officiel, groupé par jour, filtré sur la plage de dates de l'emploi du temps. |

### 5.4 Codes d'erreur à connaître

| Code | Cas |
|---|---|
| `404` | Filière/enseignant/module/séance/emploi du temps inexistant |
| `409` | Chevauchement horaire pour un même enseignant sur `POST`/`PUT` séance |
| `422` | Validation échouée (ex : `heure_fin` ≤ `heure_debut`, date passée, champ requis manquant) |

### 5.5 Exemple de test manuel (curl)

```bash
curl -X POST http://localhost:8000/api/v1/timetable/seances \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "module_id": 1,
    "enseignant_id": 1,
    "date": "2026-09-15",
    "heure_debut": "08:00",
    "heure_fin": "10:00",
    "salle": "A101",
    "type": "cours"
  }'
```
→ `201` avec la séance créée. Rejouer la même requête → `409` (conflit).

---

## 6. Diffusion temps réel (Reverb)

### 6.1 Fonctionnement

À chaque création/modification/annulation d'une séance, l'événement `SeanceProgrammeeEvent` est déclenché et diffusé sur un **canal privé par filière** :

```
private-filiere.{filiere_id}.timetable
```

Payload diffusé (`broadcastAs: seance.programmee`) :
```json
{
  "action": "created | updated | cancelled",
  "seance": { ...objet Seance avec module et enseignant chargés... }
}
```

### 6.2 ⚠️ Point d'attention critique pour la maintenance

L'événement implémente `ShouldBroadcast` (et **non** `ShouldBroadcastNow`), ce qui signifie que **la diffusion passe par la file d'attente** (`QUEUE_CONNECTION=database`).

**Conséquence : si `php artisan queue:work` ne tourne pas, les séances ne se diffusent JAMAIS en temps réel** — elles restent en attente dans la table `jobs` indéfiniment. Ce n'est pas un bug silencieux à chercher ailleurs si "le temps réel ne marche plus" : **vérifier en premier que le worker de queue tourne**.

Deux options pour la suite du projet (à trancher en équipe, non tranché à ce jour) :
- Garder `ShouldBroadcast` + `queue:work` en tant que service permanent (ex. supervisé par Supervisor/systemd en prod) — plus robuste à la charge.
- Passer à `ShouldBroadcastNow` pour un envoi synchrone immédiat, plus simple mais bloquant le temps de la requête HTTP.

### 6.3 Autorisation des canaux

Définie dans `routes/channels.php` :

```php
Broadcast::channel('filiere.{filiereId}.timetable', function ($user, $filiereId) {
    return true; // ⚠️ Volontairement permissif, voir §7
});
```

---

## 7. Limitations connues / TODO pour la suite

Ces points sont **assumés et documentés volontairement**, pas des oublis :

1. **Pas de RBAC réel.** Les endpoints de programmation (`POST`/`PUT`/`DELETE` séances, `POST` modules/emploi-du-temps) sont censés être réservés au "responsable pédagogique", et l'autorisation du canal de broadcast devrait vérifier le rôle de l'utilisateur (responsable, enseignant de la filière, délégué, secrétariat). **Actuellement, tout est ouvert** (`authorize() => true` dans les Form Requests, `return true` dans `channels.php`). Sanctum est installé mais aucun middleware `auth:sanctum` n'est branché sur les routes du module. **À faire avant mise en production.**

2. **Tables `filieres` et `cycles` sont des stubs.** Elles ont été créées pour permettre le développement et les tests en local, en l'absence d'accès au système principal existant. **Lors de l'intégration réelle**, il faudra soit :
   - pointer les clés étrangères de `modules`/`emploi_du_temps` vers les vraies tables du système principal, et supprimer ces migrations stub ;
   - soit garder ces tables si le système principal n'a pas encore de notion de filière/cycle en base (à vérifier avec l'équipe).

3. **Locale de l'application.** Le PDF utilise `translatedFormat('l d/m')` pour afficher le jour de la semaine. Ça nécessite `APP_LOCALE=fr` dans `.env`. Si les jours s'affichent en anglais ("Tuesday" au lieu de "Mardi"), vérifier cette variable et faire `php artisan config:clear`.

4. **Aucun test automatisé (PHPUnit/Pest) n'a encore été écrit.** Tous les tests effectués à ce jour sont **manuels via curl**, documentés en §5.5. C'est un chantier à part entière si l'équipe souhaite industrialiser (voir section "Pour aller plus loin").

5. **Convention de nommage des canaux de broadcast** (`private-filiere.{id}.timetable` / `private-filiere.{id}.grades`) doit être strictement identique côté Laravel et côté Flutter (fichier `channel_names.dart` de Majuste) — toute divergence casse silencieusement le temps réel côté mobile sans erreur serveur visible.

---

## 8. Problèmes rencontrés pendant le développement (retour d'expérience)

Documentés ici pour éviter de perdre du temps à les re-diagnostiquer :

| Symptôme | Cause | Solution |
|---|---|---|
| `composer require laravel/reverb` échoue avec conflit `guzzlehttp/psr7` | Version verrouillée dans le lock file incompatible avec la contrainte de Reverb | Relancer avec `composer require laravel/reverb -W` (autorise l'ajustement des dépendances liées) |
| `RuntimeException: Pusher::__construct(): Argument #1 ($auth_key) must be of type string, null given` au premier `package:discover` après install de Reverb | Les variables `REVERB_*` n'ont pas été écrites dans `.env` (l'installation précédente avait échoué avant cette étape) | Ajouter manuellement les variables `REVERB_*` (voir §2.2), puis `php artisan config:clear` |
| Un event `ShouldBroadcast` est dispatché sans erreur mais rien n'apparaît côté Reverb | Le job est en attente dans la table `jobs`, aucun worker ne le traite | Lancer `php artisan queue:work` (voir §6.2) |
| Le jour de la semaine dans le PDF s'affiche en anglais | `APP_LOCALE=en` par défaut | Passer `APP_LOCALE=fr` dans `.env` + `php artisan config:clear` |
| `php artisan route:list` lève `ReflectionException: Class "TimetableController" does not exist` | Routes ajoutées dans le mauvais fichier (`routes/api.php` au lieu de `app/Modules/Timetable/routes/api.php`), sans le `use` d'import du contrôleur | Vérifier que chaque route utilise un contrôleur bien importé dans le fichier où elle est déclarée |

---

## 9. Historique Git (branche `judicael/module-timetable`)

Historique volontairement découpé en commits atomiques et thématiques pour faciliter la review et le débogage futur (`git log --oneline` puis `git show <hash>` pour le détail) :

1. `chore(deps)` — Installation Reverb, Sanctum, DomPDF
2. `feat(timetable)` — Migrations et modèles stub filières/cycles
3. `feat(timetable)` — Migrations modules/séances/emploi_du_temps
4. `feat(timetable)` — Modèles Eloquent (relations, scopes)
5. `feat(timetable)` — Endpoints CRUD séances/modules + gestion des conflits
6. `feat(timetable)` — Diffusion temps réel via Reverb
7. `feat(timetable)` — Export PDF
8. `test(timetable)` — Seeder de données de test

---

## 10. Pour aller plus loin (suggestions, non fait à ce jour)

- Écrire des tests Feature (Pest/PHPUnit) pour chaque endpoint et chaque cas d'erreur (409/422/404), en remplacement des tests manuels curl.
- Brancher `auth:sanctum` + policies sur les routes de programmation.
- Ajouter un rate limiting sur les endpoints d'export PDF (génération potentiellement coûteuse).
- Superviser `queue:work` et `reverb:start` en production (Supervisor, systemd, ou équivalent conteneurisé) pour garantir qu'ils redémarrent en cas de crash.
- Ajouter un endpoint `GET /emploi-du-temps/{id}` (consultation JSON de l'en-tête + séances associées) — actuellement seul l'export PDF permet de voir un emploi du temps complet.

---

*Document rédigé dans le cadre du développement du module CAP — Emploi du Temps & Suivi des Notes, équipe : Judicaël (Backend Emploi du Temps), Yannick (Backend Suivi des notes), Ashley, Mélina, Majuste (Mobile Flutter).*