# EventFamily

Plateforme Symfony 8 de gestion d'événements familiaux.

## Stack

- **PHP** 8.4+ · **Symfony** 8.0
- **Twig** · **Asset Mapper** · **Hotwire Turbo** · **Stimulus**
- **Bootstrap** 5.3.8 (CDN) · **Bootstrap Icons** 1.11.3
- **SASS** (dart-sass via `npm`, compilé par [symfonycasts/sass-bundle](https://github.com/symfonycasts/sass-bundle))
- **Doctrine ORM** · MySQL (`ef_` préfixe tables)

## Démarrage

```bash
composer install
npm install
cp .env .env.local   # puis DATABASE_URL, MAILER_DSN, etc.
php bin/console doctrine:migrations:migrate
php bin/console sass:build
symfony server:start
# ou : php -S localhost:8000 -t public
```

En développement, après chaque modification SCSS :

```bash
php bin/console sass:build
# ou en continu :
php bin/console sass:build --watch
```

**Les styles ne se mettent pas à jour ?**

```bash
composer assets:refresh
```

Puis **Ctrl+F5**. Le CSS passe par `<link href="{{ asset('styles/app.scss') }}">` (compilé vers `var/sass/*.css`) — ne pas importer le SCSS dans `app.js`.

### Mailer (dev)

Dans `.env.local` : `MAILER_DSN`, `MAILER_FROM`, `DEFAULT_URI`.

En dev, les e-mails partent en **synchrone** (`config/packages/messenger.yaml` → `SendEmailMessage: sync`) — pas besoin de worker Messenger pour tester l'inscription / la vérification.

## Layouts

| Layout | Fichier | Usage |
|--------|---------|--------|
| Site | `templates/base.html.twig` | Sidebar, topbar, footer, flash |
| Auth | `templates/layout/auth.html.twig` | Login / register (`data-turbo="false"`, thème en coin) |
| Légal | `templates/layout/legal.html.twig` | CGU, mentions (sans sidebar, sans sélecteur thème) |

### Thème (clair / sombre / auto)

- `localStorage` (`ef-theme`) · `assets/js/ef-theme-init.js` · `assets/js/ef-layout.js` (Turbo-safe)

### SCSS (`assets/styles/`)

- `@use` uniquement (pas `@import`) · classes **`ef-`**
- Pages : `home`, `about`, `contact`, `sign-in`, `legal`, `profile`, `groups`, `group-show`, `messages`
- Composants : `back-to-top`, `alerts`, `dropdowns`, `session-idle`

## Routes principales

| Route | Nom | Description |
|-------|-----|-------------|
| `/` | `app_home` | Accueil |
| `/login`, `/register`, `/logout` | — | Authentification |
| `/profil` | `app_profile` | Mon espace |
| `/profil/utilisateur/{id}` | `app_profile_show` | Profil public + MP |
| `/groupes` | `app_groups` | Mes groupes |
| `/groupes/{id}` | `app_groups_show` | Fiche groupe (membres, modération) |
| `/groupes/{id}/demandes` | `app_groups_manage_requests` | Demandes d'adhésion (chef / mod) |
| `/groupes/{id}/inviter` | `app_groups_invite_search` | Inviter des membres |
| `/invitations` | `app_invitations_index` | Hub invitations (reçues + demandes staff) |
| `/invitations/api/compteurs` | `app_invitations_counts` | API JSON badges (polling) |
| `/messages` | `app_messages` | Hub messages (privés / groupe) |
| `/messages/prives` | `app_messages_private` | Messages privés |
| `/messages/groupe/{groupId}` | `app_messages_group` | Messages de groupe |
| `POST /messages/direct` | `app_messages_send_direct` | Envoi MP direct |
| `POST /messages/lire/{id}` | `app_messages_read` | Marquer lu (AJAX) |
| `/contact` | `app_contact` | Formulaire contact (connecté) |
| `POST /profil/avatar` | `app_profile_avatar_upload` | Upload avatar |
| `GET /profil/avatar/{id}` | `app_profile_avatar_show` | Affichage avatar (selon visibilité) |

Accès public : `/login`, `/register`, `/verify-email/*`, `/cgu`, `/mentions-legales`.  
`/admin/*` exige `ROLE_ADMIN`. Le reste exige `ROLE_USER`.

## Notifications (sidebar + cloche)

| Emplacement | Compteur |
|-------------|----------|
| Sidebar **Invitations** | Invitations reçues + demandes staff non lues |
| Sidebar **Messages** | MP + messages de groupe non lus |
| **Cloche topbar** | Total (invitations + messages) |

- Polling automatique : `assets/js/ef-notifications.js` (30 s) + refresh après lecture d'un message
- **Cloche** : menu déroulant (Invitations / Messages) — ne redirige plus systématiquement vers `/invitations`
- Lien « Voir en priorité » : invitations si non lues, sinon hub messages

Services : `NotificationCountService`, extension Twig `NotificationExtension`.

## Groupes

| Fonctionnalité | Qui |
|----------------|-----|
| Créer un groupe | 1 max par utilisateur (owner) |
| Modifier infos | Chef seul |
| Passer / retirer modérateur | Chef seul (max 1 mod) |
| Bannir / débannir | Chef + mod (mod → membres simples) |
| Exclure un membre | Chef seul |
| Gérer demandes / inviter | Chef + mod |
| **Nom du groupe** | Unique (contrainte BDD) |
| **Nom de famille** | Doublon autorisé — avertissement si une famille existe déjà |

Entités : `Group`, `GroupMember`, `GroupRequest`, `UserBan`.  
Statuts demandes : `PENDING`, `INVITED`, `ACCEPTED`, `REFUSED`.

### Bannissement (3 strikes)

Règle active : **3 bans cumulés** sur le compte → soft-delete automatique.

| Ban | Action |
|-----|--------|
| 1er / 2e | E-mail + message privé plateforme (avertissement 1/3 ou 2/3) |
| 3e | Soft-delete + e-mail de confirmation |

- Ban **par groupe** (`UserBan`) ; compteur global via `UserBanRepository::countTotalBansForUser`
- Services : `BanEscalationService`, `BanNotificationService`, `UserAccountSoftDeleteService`
- Modale ban avec **motif obligatoire** (chef / mod groupe)

## Session & comptes inactifs

### Déconnexion automatique (session)

Inactivité navigateur → modale avec compte à rebours → déconnexion (remember-me effacé si coché).

| Variable | Prod (`.env`) | Dev test (`.env.dev`) |
|----------|---------------|------------------------|
| `EF_SESSION_IDLE_TIMEOUT` | 1800 s (30 min) | 1800 s |
| `EF_SESSION_IDLE_WARNING` | 30 s | 30 s |

Fichiers : `SessionIdleSubscriber`, `SessionActivityController`, `assets/js/ef-session-idle.js`, modale dans `base.html.twig`.

### Purge comptes inactifs (cron)

Commande : `php bin/console app:users:purge-inactive` (option `-v` pour le détail par compte).

**Ne se déclenche pas toute seule** après modification BDD — en prod, planifier **1×/jour** (ex. 3 h) :

```bash
# Exemple cron (adapter le chemin PHP / projet)
0 3 * * * cd /chemin/eventFamily && php bin/console app:users:purge-inactive --env=prod
```

| Profil | Avert. 1 | Avert. 2 | Suppression |
|--------|----------|----------|-------------|
| **Compte déjà connecté** (vérifié) | 8 mois | 10 mois | 11 mois |
| **Jamais activé** (non vérifié) | 30 j | — | 60 j |

Variables (secondes) — prod dans `.env`, tests courts dans `.env.dev` :

| Variable | Prod | Dev (tests) |
|----------|------|-------------|
| `EF_INACTIVE_CONNECTED_WARN1_SECONDS` | 20 736 000 | 10 |
| `EF_INACTIVE_CONNECTED_WARN2_SECONDS` | 25 920 000 | 30 |
| `EF_INACTIVE_CONNECTED_DELETE_SECONDS` | 28 512 000 | 60 |
| `EF_INACTIVE_UNVERIFIED_WARN_SECONDS` | 2 592 000 | 20 |
| `EF_INACTIVE_UNVERIFIED_DELETE_SECONDS` | 5 184 000 | 40 |

**Règles :**

- Compte vérifié : inactivité calculée sur `last_login_at` (secours `updated_at`) — **`created_at` ignoré**
- Chaque connexion : `last_login_at` mis à jour + reset des avertissements (`LoginActivitySubscriber`)
- Suppression : soft-delete + **retrait automatique des groupes** (membre) + fermeture bans actifs
- **Exclus** : admin/modo site, chefs de groupe (owner)
- Notifications : e-mail + message privé EventFamily (vérifiés) ; e-mail seul si jamais activé

**Tester en local :**

1. Compte membre d'un groupe (pas chef), vérifié
2. En BDD : `last_login_at` dans le passé, `inactive_warning_count = 0`
3. **Sans se connecter** avec ce compte : `php bin/console app:users:purge-inactive -v`
4. Se connecter ensuite pour lire le message privé

> Se connecter **avant** la commande remet `last_login_at` à maintenant et annule le test.

Services : `InactiveAccountPurgeService`, `InactiveAccountNotificationService`, config `config/packages/ef_inactive_accounts.yaml`.

## Messagerie

Entités : `Message`, `MessageRead`.

- **Message système** (tête du fil groupe) : toujours affiché, non supprimable / non répondable par les membres ; édition **admin site** uniquement (`GroupSystemNoticeService`, champs `Group.systemNoticeContent`)
- **Annonces staff** (admin/modo site) : fil privé orange « EventFamily », sans réponse (`PlatformNoticeVariant::EventFamily`)
- **Notices plateforme** (bans, inactivité) : messages privés système (`PlatformNoticeVariant::System` / `EventFamily`)
- **Privé** : auteur ↔ destinataire, max **2 réponses** par fil
- **Groupe** : membres du groupe ; sélecteur si plusieurs groupes ; point rouge sur groupes avec messages non lus
- **Lecture** : auto à l'affichage (Intersection Observer) ; consultation d'un groupe = messages du groupe marqués lus
- **Suppression** : hard delete (cascade réponses + lectures)
- Règles MP : `DirectMessagePolicy` (ban groupe, pas de MP à soi-même)

## Base de données

- **`.env.local`** : `DATABASE_URL` → MySQL `ef_base` (Laragon)
- Migrations : `php bin/console doctrine:migrations:migrate`

### Tables

| Table | Entité |
|-------|--------|
| `ef_users` | `User` (+ `inactive_warning_count`, `last_inactive_warning_at`, `last_login_at`, `deleted_at`) |
| `ef_groups` | `Group` |
| `ef_group_members` | `GroupMember` |
| `ef_group_requests` | `GroupRequest` |
| `ef_user_bans` | `UserBan` |
| `ef_messages` | `Message` |
| `ef_message_reads` | `MessageRead` |

### Horodatage (Europe/Paris)

- `App\Util\ParisClock` + `TimestampableParisTrait`

## Authentification

| Fonctionnalité | État |
|----------------|------|
| Inscription + vérif. e-mail | OK |
| Connexion + remember me | OK |
| Déconnexion auto inactivité session | OK |
| Purge comptes inactifs (cron) | OK |
| Escalade 3 bans → suppression | OK |
| Mot de passe oublié / changement / suppression compte | OK |
| Profil édition + profil public | OK |
| Avatar profil (upload, crop, public/privé) | OK |
| Formulaire contact | OK |
| Google OAuth | UI seulement — avant prod |

## Messages & erreurs

- Contrôleurs : **`AbstractAppController`**
- Flash : `components/_ef_flash_messages.html.twig`
- Formulaires : `form/ef_form_theme.html.twig`
- Traductions : `translations/*.fr.yaml`

## Déploiement (checklist)

### Variables d'environnement (`.env.local` prod)

| Variable | Action |
|----------|--------|
| `APP_ENV` | `prod` |
| `APP_SECRET` | Secret unique (≠ dev) |
| `DATABASE_URL` | MySQL hébergeur |
| `MAILER_DSN` | SMTP production |
| `MAILER_FROM` | E-mail expéditeur vérifié |
| `DEFAULT_URI` | URL publique HTTPS (ex. `https://eventfamily.fr`) |
| `CONTACT_RECIPIENT` | E-mail de réception du formulaire contact |
| `RECAPTCHA_SITE_KEY` | Clé site reCAPTCHA v3 ([Google Admin](https://www.google.com/recaptcha/admin)) |
| `RECAPTCHA_SECRET_KEY` | Clé secrète reCAPTCHA v3 — **obligatoire en prod** pour le contact |

### Infrastructure & commandes

| Tâche | Action |
|-------|--------|
| Migrations | `php bin/console doctrine:migrations:migrate --no-interaction` |
| Assets CSS | `php bin/console sass:build` (+ `asset-map:compile` si prod) |
| Cache prod | `APP_ENV=prod php bin/console cache:clear` |
| Cron quotidien | `php bin/console app:users:purge-inactive --env=prod` (ex. 3 h) |
| Dossier avatars | `var/storage/avatars/` writable par PHP |
| Extension PHP | **GD** activée (avatars + recadrage serveur) |

### Fonctionnalités à finaliser avant prod

| Élément | État |
|---------|------|
| **Google OAuth** | UI seulement — brancher ou masquer le bouton |
| **reCAPTCHA v3** | Configurer les clés (formulaire contact) |
| **WhatsApp / tel. contact** | Numéros placeholder dans `/contact` |
| **E-mail admin contact** | Vérifier `CONTACT_RECIPIENT` et `admin@eventfamily.com` affiché |
| **Messenger async** | En prod, configurer worker si e-mails async |
| **HTTPS** | Obligatoire (cookies session, remember-me) |
| **Sauvegardes BDD** | Planifier backups `ef_base` |

### Contact (anti-spam)

- Limites : **5 / heure**, **20 / jour** par compte
- Honeypot + délai minimum 3 s + reCAPTCHA v3 (si clés)
- Message min. 20 caractères, max. 2000

### Avatars

- Stockage : `var/storage/avatars/` (original + version 512×512 WebP/JPEG)
- Visibilité : publique (tous les membres) ou privée (membres d'un groupe commun)
- Fichiers renommés en UUID — jamais le nom d'origine

## Prochaines étapes

1. Module **Events** (cœur du projet)
2. OAuth Google · finaliser contact (numéros réels)
3. Modales Turbo · Tarteaucitron · i18n
4. Tests automatisés (PHPUnit)

## Changelog

### 2026-06-01 — Contact + avatars profil

- **Formulaire contact** fonctionnel (e-mail, limites 5/h & 20/j, honeypot, reCAPTCHA v3 optionnel)
- **Avatar profil** : upload, recadrage Cropper.js, original conservé, sortie 512 px, public/privé
- Compteur caractères contact corrigé après erreur validation

### 2026-05-31 — Session idle, purge inactivité, bans, UI

- **Déconnexion auto** après inactivité session (modale + compte à rebours, remember-me option B)
- **Purge comptes inactifs** : avertissements 8/10/11 mois (connectés), 30/60 j (non activés), commande `app:users:purge-inactive`
- **Escalade 3 bans** → soft-delete + e-mails + messages privés plateforme
- Annonces **staff** site + notices plateforme (EventFamily / System)
- Dropdowns unifiés (`ef-dropdown-menu`) — sidebar, topbar, groupe, messages, auth
- `LoginActivitySubscriber` : `last_login_at` + reset avertissements inactivité à chaque connexion

### 2026-05-30 — Message système groupe + règles création

- Message **System** en tête des messages de groupe (défaut plateforme, personnalisable admin)
- Nom de groupe **unique** ; avertissement si nom de famille déjà utilisé (création non bloquée)
- Sélecteur multi-groupes + indicateurs messages non lus par groupe

### 2026-05-29 — Groupes, invitations, messagerie, notifications

- Module **Groupes** complet (CRUD, modération, demandes, invitations)
- Hub **`/invitations`** + badges dynamiques sidebar / cloche
- **Messagerie** privée et de groupe (`Message`, `MessageRead`)
- Cloche topbar : menu déroulant cohérent (invitations + messages)
- `NotificationCountService`, polling JS, lecture auto messages

### 2026-05-29 — Profil & auth

- Profil, MP direct, `DirectMessagePolicy`, `UserBan`, `UserChecker`
- Vérification e-mail, reset MDP, changement MDP, suppression compte

### 2026-05-28 — Socle

- Entités `User`, `Group`, `GroupMember`, layouts, SCSS modulaire, légal
