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
- Composants : `back-to-top`, `alerts`

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

Accès public : `/login`, `/register`, `/verify-email/*`, `/cgu`, `/mentions-legales`.  
Le reste exige `ROLE_USER` (`config/packages/security.yaml`).

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

Entités : `Group`, `GroupMember`, `GroupRequest`, `UserBan`.  
Statuts demandes : `PENDING`, `INVITED`, `ACCEPTED`, `REFUSED`.

## Messagerie

Entités : `Message`, `MessageRead`.

- **Privé** : auteur ↔ destinataire, max **2 réponses** par fil
- **Groupe** : membres du groupe, formulaire en bas de page
- **Lecture** : auto à l'affichage (Intersection Observer) → badge mis à jour
- **Suppression** : hard delete (cascade réponses + lectures)
- Règles MP : `DirectMessagePolicy` (ban groupe, pas de MP à soi-même)

## Base de données

- **`.env.local`** : `DATABASE_URL` → MySQL `ef_base` (Laragon)
- Migrations : `php bin/console doctrine:migrations:migrate`

### Tables

| Table | Entité |
|-------|--------|
| `ef_users` | `User` |
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
| Mot de passe oublié / changement / suppression compte | OK |
| Profil édition + profil public | OK |
| Google OAuth | UI seulement — avant prod |

## Messages & erreurs

- Contrôleurs : **`AbstractAppController`**
- Flash : `components/_ef_flash_messages.html.twig`
- Formulaires : `form/ef_form_theme.html.twig`
- Traductions : `translations/*.fr.yaml`

## Déploiement (checklist)

| Variable | Action |
|----------|--------|
| `APP_SECRET` | Unique en prod |
| `DATABASE_URL` | MySQL hébergeur |
| `MAILER_DSN` | SMTP prod |
| `DEFAULT_URI` | URL publique |
| `APP_ENV` | `prod` |

## Prochaines étapes

1. Module **Events**
2. OAuth Google · Contact fonctionnel
3. Modales Turbo · Tarteaucitron · i18n
4. Tests automatisés (PHPUnit)

## Changelog

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
