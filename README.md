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

Puis **Ctrl+F5**. Le CSS passe par `<link href="{{ asset('styles/app.scss') }}">` (ou `error-page.scss` pour les pages d’erreur), compilé vers `var/sass/*.css` — ne pas importer le SCSS dans `app.js`.

### Mailer (dev)

Dans `.env.local` : `MAILER_DSN`, `MAILER_FROM`, `DEFAULT_URI`.

En dev, les e-mails partent en **synchrone** (`config/packages/messenger.yaml` → `SendEmailMessage: sync`) — pas besoin de worker Messenger pour tester l'inscription / la vérification.

## Layouts

| Layout | Fichier | Usage |
|--------|---------|--------|
| Site | `templates/base.html.twig` | Sidebar, topbar, footer, flash |
| Auth | `templates/layout/auth.html.twig` | Login / register (`data-turbo="false"`, thème en coin) |
| Légal | `templates/layout/legal.html.twig` | CGU, mentions (sans sidebar, sans sélecteur thème) |
| Erreur | `templates/layout/error.html.twig` | 404 et pages d’erreur (sans sidebar ; CSS dédié `error-page.scss`) |

### Thème (clair / sombre / auto)

- `localStorage` (`ef-theme`) · `assets/js/ef-theme-init.js` · `assets/js/ef-layout.js` (Turbo-safe)

### Langue (français / anglais)

| Élément | Détail |
|---------|--------|
| Défaut | **Français** (`framework.default_locale: fr`) |
| Bascule | Dropup sidebar : affiche la **langue cible** (ex. « English » si le site est en français), clic → bascule |
| Persistance | Session `_ef_locale` + cookie `ef_locale` (1 an) + champ `User.locale` si connecté |
| Admin EA | Dropdown langue FR/EN dans le dashboard (`setLocales`) |
| Fichiers | `translations/messages.{fr,en}.yaml` (domaine `messages` pour `|trans`) · `security.*` · `validators.*` |

Services : `LocaleService`, `LocaleSubscriber`, `LocaleController`, extension Twig `LocaleExtension` (`ef_locale_switch_label` = libellé affiché dans le menu).

**Templates traduits (FR + EN)** : layout, accueil, événements, groupes, messages, invitations, profil, about, contact, auth, pages légales (titres, retour, placeholders hébergeur), composants communs, admin EasyAdmin, alertes JS avatar/contact (via `data-ef-alert-*`).

**Contenu juridique long** (corps CGU / mentions hors structure) : rédigé en français dans `messages.fr.yaml` ; traduction EN disponible dans `messages.en.yaml`. Placeholders hébergeur à remplacer avant prod.

Clés Twig : `'ui.nav.home'|trans` (domaine **`messages`** — ne pas séparer en `ui.fr.yaml`). Voir [docs/I18N.md](docs/I18N.md).

### Cookies et consentement (RGPD / CNIL)

Bandeau + modale **maison** (pas de CMP tiers). Conforme CNIL : refus aussi simple qu’acceptation, pas de cookie wall, choix stocké 6 mois (`ef_consent`).

| Fichier / config | Rôle |
|------------------|------|
| `config/packages/ef_consent.yaml` | Nom du cookie, version, TTL |
| `src/Service/CookieConsentService.php` | Validation côté serveur |
| `src/Twig/CookieConsentExtension.php` | Variables Twig (`ef_consent`, catalogue cookies nécessaires) |
| `assets/js/ef-consent.js` | Écriture cookie, bandeau, modale (Turbo-safe, `data-turbo-permanent`) |
| `assets/styles/components/_cookie-consent.scss` | Styles bandeau flottant + modale |
| `templates/components/_ef_cookie_consent.html.twig` | UI bandeau / préférences |
| `translations/messages.{fr,en}.yaml` → `cookie.*` | Textes FR / EN |

| Catégorie | État | Détail |
|-----------|------|--------|
| **Strictement nécessaires** | Livré | Session, CSRF, `ef_locale`, `REMEMBERME`, `ef_consent` (mémorisation du choix) |
| **Marketing / publicité** | Prévu | **Google AdSense** — aucun script pub tant que l’utilisateur n’accepte pas (`marketing: true` dans le cookie) |
| **Mesure d’audience (analytics)** | **À venir** | **Créer d’abord le compte** (GA4, Matomo, Plausible, etc.) → **puis** ajouter la catégorie opt-in dans le bandeau et brancher les scripts |

**Feuille de route cookies (hors nécessaires)** :

1. **Analytics** — ouvrir le compte chez l’outil choisi, configurer la propriété / le site, récupérer l’ID de suivi.
2. **Code** — étendre le JSON `ef_consent` (`analytics: true/false`), case dans la modale, traductions, chargement conditionnel du script (événement `ef:consent-updated` côté JS).
3. **AdSense** — quand le compte pub sera prêt, brancher les tags sur la catégorie **marketing** déjà prévue.

Ne pas ajouter de cases vides dans le bandeau avant d’avoir le service réel (recommandation CNIL).

Lien footer **Gérer les cookies** · CGU `#privacy` · après modif SCSS : `composer assets:refresh` ou `php bin/console sass:build --watch`.

### SCSS (`assets/styles/`)

- `@use` uniquement (pas `@import`) · classes **`ef-`**
- Entrées SASS compilées : `app.scss` (site), `error-page.scss` (404 / erreurs), `ef-admin.scss` (back-office)
- Pages : `home`, `about`, `contact`, `sign-in`, `legal`, `error`, `profile`, `groups`, `group-show`, `messages`, `events`, `event-show`
- Composants : `back-to-top`, `alerts`, `dropdowns`, `session-idle`, `cookie-consent`

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
| `/evenements` | `app_events` | Liste (`?vue=upcoming\|ongoing\|past`, `?q=` recherche titre/lieu) |
| `/evenements/nouveau` | `app_events_new` | Créer (chef / mod / staff site) |
| `/evenements/{id}` | `app_events_show` | Fiche événement |
| `/evenements/{id}/modifier` | `app_events_edit` | Modifier |
| `/evenements/photo/{id}/couverture` | `app_events_photo_cover` | Photo couverture |
| `/evenements/photo/{id}/detail` | `app_events_photo_detail` | Photo lieu / détail |
| `/invitations` | `app_invitations_index` | Hub invitations (reçues + demandes staff) |
| `/invitations/api/compteurs` | `app_invitations_counts` | API JSON badges + non-lus par groupe (polling) |
| `POST /messages/groupe/{groupId}/marquer-lu` | `app_messages_group_mark_read` | Marquer tout le groupe lu (AJAX, après affichage) |
| `/messages` | `app_messages` | Hub messages (privés / groupe) |
| `/messages/prives` | `app_messages_private` | Messages privés |
| `/messages/groupe/{groupId}` | `app_messages_group` | Messages de groupe |
| `POST /messages/direct` | `app_messages_send_direct` | Envoi MP direct |
| `POST /messages/lire/{id}` | `app_messages_read` | Marquer lu (AJAX) |
| `/contact` | `app_contact` | Formulaire contact (connecté) |
| `/locale/switch` | `app_locale_switch` | Bascule FR ↔ EN (session + profil utilisateur) |
| `POST /profil/avatar` | `app_profile_avatar_upload` | Upload avatar |
| `GET /profil/avatar/{id}` | `app_profile_avatar_show` | Affichage avatar (selon visibilité) |

Accès public **invité** (sans compte) : `/` (vitrine), `/about`, `/cgu`, `/mentions-legales`, `/locale/switch`, auth (`/login`, `/register`, reset / verify e-mail, …).  
**Réservé `ROLE_USER`** : `/evenements`, `/groupes`, `/messages`, `/contact`, `/profil`, invitations, etc. Voir [Accueil public & AdSense](#accueil-public--adsense).

Le back-office EasyAdmin est servi sous un **chemin obscur** (`EF_ADMIN_PATH`, ex. `/ef-console-8f3a2c91`) — réservé aux **modérateurs et administrateurs site** (`ROLE_MODERATOR` / `ROLE_ADMIN`).

## Administration (EasyAdmin)

| Élément | Détail |
|---------|--------|
| URL | `/%EF_ADMIN_PATH%/` (défaut `ef-console-8f3a2c91`) — **à personnaliser en prod** (`.env.local`) |
| Sidebar site | Lien « Administration » visible staff site uniquement, ouverture **nouvel onglet** |
| Tableau de bord | Titre **« Administration EventFamily »** ; cartes vers chaque rubrique |
| Titres de rubrique | En-tête de chaque CRUD = nom de la section (Utilisateurs, Groupes, Événements, Messages, Bannissements) — plus le mot générique « Administration » |
| Droits | Modo + admin : lecture / création / édition ; **suppression** : admin seul |
| CRUD | Utilisateurs, groupes, événements, **messages** (consultation litige), **bannissements** (historique lecture seule) |
| Dates admin | Fuseau **Europe/Paris** ; format `jj/mm/aaaa HH:mm` (année courte `jj/mm/aa` en liste) — motifs ICU EasyAdmin (`dd/MM/yyyy`) |
| i18n | Menu + titres via clés `admin.*` ; sélecteur FR/EN EasyAdmin ; libellés natifs `EasyAdminBundle.*` |
| i18n (convention) | Voir [docs/I18N.md](docs/I18N.md) — toute nouvelle UI / flash / validation en FR+EN dès l’ajout |
| Unicité | E-mail, pseudo, prénom+nom, nom de groupe (validation groupe `Admin`) |
| Inactivité | Timeout zone admin (`EF_ADMIN_IDLE_TIMEOUT`, défaut **900 s** / 15 min) — synchronisé avec l’activité site |
| Confirmations | Suppressions EasyAdmin avec confirmation explicite |
| Message système groupe | Édition staff site (`GroupSystemNoticeController`) — titre page « Groupes — Message système » |

### Suspension site (admin → Utilisateurs)

Distincte du **ban groupe** (escalade 3 strikes). Cocher **« Compte suspendu (site) »** + **motif obligatoire** :

| Effet | Détail |
|-------|--------|
| Connexion | Bloquée (`UserChecker`, déconnexion session active) |
| E-mail | Motif + adresse de recours (`MODERATION_CONTACT`, défaut = `CONTACT_RECIPIENT`) |
| MP privé | Notice archive avec lien de recours |
| Login | Encart `?suspended=1` + lien `mailto:` modération |
| Déban | Décocher la case → e-mail de réactivation |
| Historique | Entrée `UserBan` sans groupe (« Suspension site ») dans **Bannissements** |

Services : `AdminPlatformBanService`, `PlatformBanAccessSubscriber`. Pas de création manuelle de ban site depuis **Bannissements** (lecture seule).

Variables : `EF_ADMIN_PATH`, `EF_ADMIN_IDLE_TIMEOUT`, `EF_ADMIN_IDLE_WARNING`, `MODERATION_CONTACT` (voir `.env`).

Contrôleurs : `DashboardController`, `UserCrudController`, `GroupCrudController`, `EventCrudController`, `MessageCrudController`, `UserBanCrudController`, `GroupSystemNoticeController`, `AdminSessionActivityController`.

## Notifications (sidebar + cloche)

| Emplacement | Compteur |
|-------------|----------|
| Sidebar **Invitations** | Invitations reçues + demandes staff non lues |
| Sidebar **Messages** | MP + messages de groupe non lus |
| **Cloche topbar** | Total (invitations + messages) |

- Compteurs **hors rendu HTML** (extension Twig à 0) : chargement AJAX `/invitations/api/compteurs`
- Polling : `assets/js/ef-notifications.js` (30 s) + refresh après lecture / marquage groupe lu
- **Sélecteur de groupes** (messages) : pastilles non-lues via le même appel AJAX (`group_unread` dans la réponse JSON)
- **Cloche** : menu déroulant (Invitations / Messages) — ne redirige plus systématiquement vers `/invitations`
- Lien « Voir en priorité » : invitations si non lues, sinon hub messages

Services : `NotificationCountService`, extension Twig `NotificationExtension` (globals neutres).

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

### Bannissement (3 strikes — groupe)

Règle active : **3 bans cumulés** sur le compte → soft-delete automatique.

| Ban | Action |
|-----|--------|
| 1er / 2e | E-mail + message privé plateforme (avertissement 1/3 ou 2/3) |
| 3e | Soft-delete + e-mail de confirmation |

- Ban **par groupe** (`UserBan` + `relatedGroup`) ; compteur global via `UserBanRepository::countTotalBansForUser`
- Services : `BanEscalationService`, `BanNotificationService`, `UserAccountSoftDeleteService`
- Modale ban avec **motif obligatoire** (chef / mod groupe)
- **Admin → Bannissements** : historique consultation seule (pas de création / édition)

## Événements

| Fonctionnalité | Qui |
|----------------|-----|
| Créer / modifier | Chef ou modérateur **du groupe** + staff site (admin / modo) |
| Supprimer | Chef **du groupe** + administrateur site uniquement |
| Membres simples | Bandeau + MP vers le **responsable** du groupe (chef ou modérateur — pas de bouton « Créer ») |
| Visibilité **publique** | Tous les utilisateurs connectés |
| Visibilité **privée** | Membres du groupe uniquement |

Entité : `Event` (`ef_events`) — champs `photo_cover`, `photo_detail` (facultatifs, max 5 Mo chacun).  
Sans photo : placeholder festif Unsplash aléatoire (couverture et détail distincts).  
Suppression événement ou case « supprimer photo » → fichiers disque + BDD effacés.

Catégories : **À venir** / **En cours** / **Passés** (`?vue=`, liens serveur — pas de tabs Bootstrap).  
**Recherche** (topbar) : `?q=` sur titre et lieu, dans la catégorie active ; visibilité respectée.  
Aperçu **modale** + **Voir plus** ; passés en N&B (couleur au survol).

Purge : `php bin/console app:events:purge-past` (`ef.events.purge_retention_months`, défaut 10 mois).

Services : `EventAccessService`, `EventImageService`, `EventPlaceholderService`, `EventPurgeService`.  
Config : `config/packages/ef_events.yaml`. JS modales : `assets/js/ef-events.js`.

## Session & comptes inactifs

### Déconnexion automatique (session)

Inactivité navigateur → modale avec compte à rebours → déconnexion (remember-me effacé si coché).

| Variable | Prod (`.env`) | Dev test (`.env.dev`) |
|----------|---------------|------------------------|
| `EF_SESSION_IDLE_TIMEOUT` | 1800 s (30 min) | 1800 s |
| `EF_SESSION_IDLE_WARNING` | 30 s | 30 s |

Fichiers : `SessionIdleSubscriber`, `SessionActivityController`, `assets/js/ef-session-idle.js`, modale dans `base.html.twig`.

**Zone admin (EasyAdmin)** — délai séparé, plus court que le site en prod si souhaité :

| Variable | Défaut (`.env`) | Rôle |
|----------|-----------------|------|
| `EF_ADMIN_IDLE_TIMEOUT` | 900 s (15 min) | Inactivité **sur les pages admin** |
| `EF_ADMIN_IDLE_WARNING` | 60 s | Compte à rebours modale admin |

Fichiers : `AdminSessionIdleSubscriber`, `AdminSessionActivityController`, `public/js/ef-admin-idle.js`, modale dans le layout EA.

**Comportement corrigé (déconnexions intempestives)** :

- Naviguer sur le **site** puis ouvrir l’**admin** ne doit plus déconnecter tout de suite : l’activité site (`_ef_last_activity`) prolonge la session admin à l’entrée.
- Travailler dans l’**admin** prolonge aussi la session **site** (évite une déconnexion au retour sur le site public).
- L’ancien défaut **120 s** sur l’admin seul provoquait une expulsion si la dernière requête admin datait de plus de 2 minutes — même après une navigation active ailleurs.

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

- **Message système** (tête du fil groupe) : toujours affiché, non supprimable / non répondable par les membres ; édition **staff site** (admin / modo) (`GroupSystemNoticeService`, champs `Group.systemNoticeContent`)
- **Annonces staff** (admin/modo site) : fil privé orange « EventFamily », sans réponse (`PlatformNoticeVariant::EventFamily`)
- **Notices plateforme** (bans, inactivité) : messages privés système (`PlatformNoticeVariant::System` / `EventFamily`)
- **Privé** : auteur ↔ destinataire, max **2 réponses** par fil
- **Masquage MP** : suppression « de mon côté » (`author_hidden_at` / `recipient_hidden_at`) — l'autre partie conserve le fil
- **Fil clôturé** : dès qu'un participant **masque** le MP, plus de réponse possible pour **aucun** des deux (évite les notifications fantômes)
- **Groupe** : seul l'auteur peut supprimer (hard delete pour tous les membres)
- **Purge auto** : `php bin/console app:messages:purge-old` (`ef.messages.purge_retention_months`, défaut **12 mois**) — MP + groupe ; notices plateforme conservées
- **Groupe** : membres du groupe ; sélecteur si plusieurs groupes ; point rouge sur groupes avec messages non lus
- **Lecture** : auto à l'affichage (Intersection Observer) ; marquage « tout le groupe lu » en **POST AJAX** après rendu (`ef-messages.js`, pas au GET)
- **Perf fils groupe** : chargement racines + réponses en 2 requêtes (sans `DISTINCT` cartésien) ; `COUNT` global évité si tous les fils visibles sont chargés
- Règles MP : `DirectMessagePolicy` (ban groupe, pas de MP à soi-même)

## Base de données

- **`.env.local`** : `DATABASE_URL` → MySQL `ef_base` (Laragon)
- Migrations : `php bin/console doctrine:migrations:migrate`

### Tables

| Table | Entité |
|-------|--------|
| `ef_users` | `User` (+ `locale`, `inactive_warning_count`, `last_inactive_warning_at`, `last_login_at`, `deleted_at`) |
| `ef_groups` | `Group` |
| `ef_group_members` | `GroupMember` |
| `ef_group_requests` | `GroupRequest` |
| `ef_user_bans` | `UserBan` |
| `ef_messages` | `Message` |
| `ef_message_reads` | `MessageRead` |
| `ef_events` | `Event` |

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
| Formulaire contact | OK en dev (Ethereal, **sans** reCAPTCHA) — voir [Services Google](#services-google-en-attente-de-clés) |
| Google OAuth | UI seulement — brancher **dès réception des identifiants OAuth** (voir ci-dessous) |

### Services Google (en attente de clés)

> **Décision projet** : OAuth connexion/inscription Google **et** reCAPTCHA contact seront branchés **dès que les identifiants Google seront disponibles** (pas bloquant pour continuer dev / tests locaux).

| Service | Console Google | Variables `.env` | État |
|---------|----------------|------------------|------|
| **reCAPTCHA v3** (formulaire contact) | [reCAPTCHA Admin](https://www.google.com/recaptcha/admin) | `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` | Clés vides — actif automatiquement en prod dès renseignement |
| **OAuth 2.0** (Se connecter avec Google) | [Cloud Console](https://console.cloud.google.com/) → Identifiants OAuth | `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` (à créer) | Boutons UI « Bientôt » — backend à implémenter en même temps que les clés |
| **Analytics** (mesure d’audience) | Selon outil : [GA4](https://analytics.google.com/), [Matomo](https://matomo.org/), etc. | Variables à définir (ex. `GA_MEASUREMENT_ID`) | **Compte à créer** → puis catégorie cookies + scripts (voir [Cookies et consentement](#cookies-et-consentement-rgpd--cnil)) |
| **AdSense** (publicité) | [Google AdSense](https://www.google.com/adsense/) | Publisher ID / balises à définir | Compte pub à créer → brancher sur catégorie **marketing** déjà prévue dans le bandeau |

Ce sont **des configurations distinctes** dans Google (reCAPTCHA ≠ OAuth ≠ Analytics ≠ AdSense). Prévoir l’URL HTTPS finale pour les URI de redirection OAuth en prod.

### Contact WhatsApp

| Niveau | Clés API ? | Description |
|--------|------------|-------------|
| **v1 — lien « Discuter sur WhatsApp »** | **Non** | Numéro au format international + lien `https://wa.me/33…` (ouvre l’app / WhatsApp Web). Suffit pour afficher un canal contact à côté de l’e-mail. |
| **v2 — intégration avancée** | **Oui** (Meta) | WhatsApp Business Platform / Cloud API : compte Meta Business, numéro dédié, approbation Meta, facturation — pour envoyer/recevoir des messages **depuis le site** (webhooks, templates). |

**Recommandation** : pour la mise en prod, un **lien wa.me** + numéro réel (variable `.env` ou config) ; reporter l’API WhatsApp en **v2** si besoin d’historique ou de réponses automatisées côté site. Le formulaire e-mail + reCAPTCHA reste le canal principal traçable.

## Messages & erreurs

- Contrôleurs : **`AbstractAppController`**
- Flash : `components/_ef_flash_messages.html.twig`
- Formulaires : `form/ef_form_theme.html.twig`
- Traductions : `translations/messages.{fr,en}.yaml` (UI `ui.*`, flash `auth.*` / `profile.*`, enums `event.*`, admin `admin.*`) · `security.{fr,en}.yaml` · `validators.{fr,en}.yaml`
- **Textarea** : `resize: none` sur tout le site (`assets/styles/base/_globals.scss` + thème formulaire). Exception possible : classe `.ef-textarea--resizable`.

### Page 404 (livré)

| Élément | Détail |
|---------|--------|
| Template | `templates/bundles/TwigBundle/Exception/error404.html.twig` |
| Layout | `templates/layout/error.html.twig` — thème clair/sombre (`ef-theme-init.js`), bandeau cookies |
| Styles | `assets/styles/pages/_error.scss` via entrée **`error-page.scss`** (à recompiler après modif : `sass:build` / `--watch`) |
| i18n | `ui.error.not_found.*` dans `messages.{fr,en}.yaml` |
| Prévisualisation dev | `/_error/404` (firewall `dev` : `/_error` sans auth) |
| Comportement site | URL inconnue : **invité** → redirection login (sauf routes publiques ci-dessus) ; **connecté** → page 404 personnalisée |

Actions : retour accueil, lien contact, marque EventFamily en pied de carte.

## Déploiement (checklist)

> **Checklist complète** : [docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md) — à relire avant toute mise en prod.

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
| `MODERATION_CONTACT` | Recours suspension site (défaut `${CONTACT_RECIPIENT}` dans `.env`) |
| `RECAPTCHA_SITE_KEY` | Clé site reCAPTCHA v3 ([Google Admin](https://www.google.com/recaptcha/admin)) |
| `RECAPTCHA_SECRET_KEY` | Clé secrète reCAPTCHA v3 — **obligatoire en prod** pour le contact |

### Infrastructure & commandes

| Tâche | Action |
|-------|--------|
| Migrations | `php bin/console doctrine:migrations:migrate --no-interaction` |
| Assets CSS | `php bin/console sass:build` (`app.scss`, `error-page.scss`, `ef-admin.scss`) + `asset-map:compile` si prod |
| Cache prod | `APP_ENV=prod php bin/console cache:clear` |
| Cron purge comptes | `php bin/console app:users:purge-inactive --env=prod` (ex. 3 h) |
| Cron purge events passés | `php bin/console app:events:purge-past --env=prod` (ex. 4 h) |
| Cron purge messages | `php bin/console app:messages:purge-old --env=prod` (ex. 5 h) |
| Dossier avatars | `var/storage/avatars/` writable par PHP |
| Dossier photos events | `var/storage/events/` writable par PHP |
| Extension PHP | **GD** activée (avatars + photos événements) |

### Fonctionnalités à finaliser avant prod

| Élément | État |
|---------|------|
| **Google OAuth** | UI seulement — **brancher quand les identifiants OAuth Google seront prêts** (voir [Services Google](#services-google-en-attente-de-clés)) ; sinon masquer les boutons pour la v1 |
| **reCAPTCHA v3** | **Brancher quand les clés reCAPTCHA seront prêtes** — obligatoire en prod pour le formulaire contact |
| **WhatsApp contact** | Placeholder `/contact` — **pas de clé API** pour un simple lien `wa.me` ; numéro réel à renseigner (v1). API Meta → v2 si messagerie intégrée |
| **Mentions légales — hébergeur** | Renseigner `legal.mentions.hosting.*_placeholder` (FR + EN) |
| **E-mail admin contact** | Vérifier `CONTACT_RECIPIENT` et `admin@eventfamily.com` affiché |
| **Messenger async** | En prod, configurer worker si e-mails async |
| **HTTPS** | Obligatoire (cookies session, remember-me) |
| **Bandeau cookies** | Livré (nécessaires + marketing AdSense à venir) | Analytics : compte d’abord, puis extension du consentement |
| **Sauvegardes BDD** | Planifier backups `ef_base` |

### Contact (anti-spam)

| Environnement | reCAPTCHA | Statut |
|---------------|-----------|--------|
| **Dev local** | Désactivé si clés vides | **Utilisable** — e-mail via Ethereal, honeypot, rate limit |
| **Production** | **Obligatoire** (`RECAPTCHA_SECRET_KEY`) | Brancher **dès réception des clés** Google reCAPTCHA v3 |

- Limites : **5 / heure**, **20 / jour** par compte (assouplies en `APP_ENV=dev`)
- Honeypot + délai minimum 3 s en prod — **0 s en dev**
- Message min. **20** caractères, max. 2000
- Connecté uniquement (`ROLE_USER`)

**Test local** (`.env.local`) :

```env
CONTACT_RECIPIENT=<même adresse que la boîte Ethereal>
RECAPTCHA_SITE_KEY=          # vide = pas de reCAPTCHA (normal en attendant les clés)
RECAPTCHA_SECRET_KEY=
```

Après envoi : flash vert *« Ton message a bien été envoyé… »* + e-mail sur [ethereal.email](https://ethereal.email). Pas de bandeau spécial sur la page — si vous voyez une erreur, c’est un flash rouge ou une alerte de validation.

### Avatars

- Stockage : `var/storage/avatars/` (original + version 512×512 WebP/JPEG)
- Visibilité : publique (tous les membres) ou privée (membres d'un groupe commun)
- Fichiers renommés en UUID — jamais le nom d'origine

## Performances & navigation (état au 2026-06-01)

### Environnement local

En **`APP_ENV=dev`** (`symfony serve`), chaque page est volontairement plus lente qu’en production :

- Web Profiler, pas de cache applicatif optimisé, PHP sans OPcache agressif
- Une page simple peut afficher **~0,8–1,5 s** dans la barre Symfony alors que MySQL reste rapide

**Test réaliste en local** :

```powershell
$env:APP_ENV="prod"; $env:APP_DEBUG="0"
php bin/console cache:clear
php bin/console cache:warmup
# puis relancer le serveur
```

### Optimisations déjà livrées

| Zone | Mesure |
|------|--------|
| **Badges sidebar / cloche** | AJAX (`ef-notifications.js`), plus de requêtes SQL dans chaque HTML |
| **Messages groupe** | Fils en 2 requêtes ; marquage lu en POST AJAX ; compteur groupe via AJAX ; formulaire staff si staff uniquement |
| **Messages privés** | Même chargement fils optimisé ; `COUNT` évité si liste complète |
| **Turbo** | `ef-turbo-nav.js` : voile pendant navigation, pas de « retour » si clic rapide ; `no-preview` cache |
| **Listes** | Pagination groupes / événements / membres ; cartes groupes allégées |
| **Admin messages** | Recherche index limitée à l’ID (plus de scan `content`) |

### Pistes restantes (à poursuivre)

- Profiler ciblé : temps **Total** vs **Doctrine** sur messages groupe, admin dashboard, contact
- Contact lent **au hasard** : souvent reCAPTCHA / réseau au **submit** ; page GET légère
- Index BDD si volume messages très élevé
- Mode prod local pour valider les perfs réelles avant déploiement

## Prochaines étapes

> **Suite** : **1.** (optionnel) enrichir `/about` pour AdSense · **2.** Déploiement prod + demande AdSense · **3.** Checklist [docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md).

### Accueil public & AdSense

#### Livré — vitrine invité (juin 2026)

Réponse au mur de connexion sur `/` (bloquant l’examen AdSense). Comportement actuel :

| Zone | Invité (non connecté) | Membre connecté |
|------|------------------------|-----------------|
| **URLs publiques** | `/`, `/about`, `/cgu`, `/mentions-legales`, `/locale/switch`, auth | Inchangé |
| **Reste du site** | → **login** | Accès complet |
| **Home `/`** | Hero + 3 cartes + « Comment ça marche » (3 étapes) + encart espace privé + CTA inscription/connexion + lien about — **sans** liste d’événements | Hero + cartes + aperçu événements publics |
| **Sidebar** | Accueil + lien **À propos** ; dropup connexion / inscription / langue | Sidebar complète |
| **Footer** | CGU + mentions + copyright (+ cookies si choix fait) | Footer complet |
| **Topbar** | Titre + thème uniquement | Recherche + notifications + thème |

**Fichiers concernés** :

| Fichier | Rôle |
|---------|------|
| `config/packages/security.yaml` | `PUBLIC_ACCESS` sur `^/$`, `^/about`, `^/locale/switch` |
| `src/Controller/HomeController.php` | `findUpcomingPublic()` si connecté uniquement |
| `templates/home/index.html.twig` | Branche `app.user` / vitrine `ef-home-guest` |
| `templates/layout/_sidebar.html.twig` | Mode invité (`ef-sidebar--guest`) |
| `templates/layout/_footer.html.twig` | `ef-footer--guest` |
| `templates/layout/_topbar.html.twig` | Masque recherche / notifications si invité |
| `templates/base.html.twig` | Pas de panneau recherche pour invité |
| `assets/styles/pages/_home.scss` | Styles vitrine invité (clair / sombre) |
| `translations/messages.{fr,en}.yaml` → `ui.home.guest_*` | Textes vitrine |

**Volontairement exclus** : événements, groupes, messages, contact (formulaire), admin, fausses données publiques.

**Test manuel** : navigation privée sur `/` et `/about` — pas de redirection `/login`. Après modif SCSS : `php bin/console sass:build` ou `npm run sass:watch`, puis rechargement forcé du navigateur.

**AdSense (scripts)** : pas encore branchés — catégorie **marketing** du bandeau prête ; balises **après** approbation du site et mise en prod HTTPS.

#### Optionnel — enrichir `/about` (pas encore fait)

> Décision **en attente** : utile pour réduire un éventuel refus Google « contenu insuffisant », mais **non obligatoire** si la home publique + CGU suffisent. On peut abandonner cette piste sans toucher à la vitrine déjà livrée.

Pistes de contenu **à ajouter seulement si vous validez** (FR + EN, `ui.about.*`) :

| Bloc suggéré | Objectif reviewer |
|--------------|-------------------|
| **Public visé** | Familles, groupes privés, pas un réseau social ouvert |
| **Fonctionnalités concrètes** | Groupes, événements, messagerie — toujours derrière compte |
| **Confidentialité** | Données membres non exposées aux visiteurs |
| **Contact / éditeur** | E-mail ou renvoi vers mentions légales (contact formulaire reste connecté) |
| **Pas de fausses promesses** | Pas de « annuaire public » ni d’événements visibles sans inscription |

Implémentation prévue le cas échéant : sections dans `templates/about/index.html.twig` + clés YAML — **sans** ouvrir de nouvelles routes publiques.

#### Critères AdSense (rappel)

- Site **consultable sans compte** sur accueil + about + légal — **OK côté technique**
- Texte **original** — home invité + about actuel ; enrichissement about = **optionnel**
- CGU / confidentialité (`#privacy`, mention AdSense) — **OK**
- Demande sur **URL prod HTTPS** ; mentions légales complètes (hébergeur, éditeur) avant soumission

### En attente — ne pas oublier

| Sujet | État actuel | À faire |
|-------|-------------|---------|
| **reCAPTCHA contact** | Clés **non** configurées (`RECAPTCHA_*` vides) | Dès que tu as les clés : [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin) → `RECAPTCHA_SITE_KEY` + `RECAPTCHA_SECRET_KEY` dans `.env.local` / prod |
| **Google OAuth** | Boutons UI « Bientôt » — pas de backend | Dès que tu as **Client ID + Secret** OAuth (Cloud Console) : implémenter connect/callback + variables `.env` — voir [Services Google](#services-google-en-attente-de-clés) |
| **WhatsApp** | Numéro placeholder sur `/contact` | **v1** : numéro réel + lien `wa.me` (aucune clé). **v2** : WhatsApp Business API si contact bidirectionnel depuis le site |
| **Contact en dev** | **Opérationnel** sans reCAPTCHA (honeypot + rate limit + e-mail Ethereal) | Tester l’envoi ; reCAPTCHA prod activé automatiquement quand la clé secrète est renseignée |
| **Analytics** | Bandeau cookies sans case analytics pour l’instant | **1.** Créer le compte (GA4 / Matomo / autre) · **2.** Ajouter catégorie opt-in + chargement conditionnel des scripts — voir [Cookies et consentement](#cookies-et-consentement-rgpd--cnil) |
| **AdSense** | Vitrine publique livrée ; scripts pub **non** branchés | Prod HTTPS → demande d’examen → balises si `marketing: true` ; enrichissement `/about` **optionnel** (voir ci-dessus) |

### Court terme

1. **Analytics** — compte GA4 / Matomo / autre, puis extension `ef_consent` + bandeau (voir [Cookies](#cookies-et-consentement-rgpd--cnil))
2. **Prêt au déploiement** — checklist [docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md) + tests manuels prod-like
3. **Performances** — suite des mesures Profiler (voir section ci-dessus)
4. **Session admin** — valider en navigation réelle (site → admin → site) après correctif idle
5. **Contact prod** — clés reCAPTCHA dès disponibles (voir [Services Google](#services-google-en-attente-de-clés))

### v2 — Contact & intégrations (planifié)

- **WhatsApp Business API** : réception/envoi de messages depuis le site (webhooks Meta — compte Business, numéro dédié, **clés API**, facturation). Distinct du simple lien `wa.me` (v1, sans clé).
- **OAuth Google** : si non livré en v1 (dépend des identifiants Google Cloud)

### v2 — Événements (planifié)

- **RSVP** : participation (oui / non / peut-être), compteurs, notifications
- **Recherche avancée** : filtre par groupe, dates, type d'événement, description

> Recherche simple (topbar → `/evenements?q=`, titre + lieu) : **livré** — voir section Événements.

### v2 — Messagerie (planifié)

- Masquage « de mon côté » pour les **messages de groupe** (au lieu du hard delete auteur)

### Autres

1. OAuth Google + reCAPTCHA — **dès réception des clés Google** (voir section Authentification)
2. WhatsApp v1 (lien `wa.me`) ou v2 (API) — selon besoin
3. Modales Turbo · Tarteaucitron
4. **i18n** — couverture FR/EN complète ; contenu juridique à relire avant prod
5. Tests automatisés (PHPUnit)

## Changelog

### 2026-06-04 — Page 404 + feuille SASS erreurs

- **404** : template Twig dédié, layout `error.html.twig`, styles clair/sombre (`_error.scss`), traductions FR/EN (`ui.error.not_found.*`)
- **Assets** : entrée `error-page.scss` (compilée à part de `app.scss`) ; page d’erreur charge `error-page.scss` + styles bandeau cookies
- **Dev** : prévisualisation `/_error/404` ; rappel `sass:build` / `--watch` après modif SCSS
- **README** : section 404, layouts, prochaines étapes (analytics → checklist déploiement)

### 2026-06-04 — i18n : page About + finitions

- **About** : traduction FR complète (`ui.about.*`) ; nav `Accueil` / `À propos` / tagline FR
- **Groupes** : fil d'Ariane via `ui.groups.breadcrumb_aria` (plus de texte en dur)
- **Mentions légales** : placeholders hébergeur traduits (`legal.mentions.hosting.*_placeholder`)
- **JS client** : alertes avatar + reCAPTCHA contact via `data-ef-alert-*` (locale session)
- **EventImageProcessor** : exceptions métier via clés `flash.event.image_*`
- **README** : section langue à jour (i18n considéré complet côté app)

### 2026-06-01 — Performances navigation + session admin

- **Turbo** : garde-fou clics rapides (`ef-turbo-nav.js`), voile de chargement, `turbo-cache-control: no-preview`
- **Messages groupe** : SQL allégé, marquage lu AJAX, pastilles groupe via API compteurs
- **Session admin** : sync activité site ↔ admin ; défaut `EF_ADMIN_IDLE_TIMEOUT` **900 s** (plus de déco à l’ouverture de l’admin après navigation site)
- **README** : section performances & pistes restantes

### 2026-05-28 — i18n FR/EN + fix traductions

- **Langue** : bascule FR ↔ EN depuis le dropup sidebar ; persistance session + `User.locale`
- Traductions dans **`messages.{fr,en}.yaml`** (domaine `messages` — éviter fichiers `ui.*.yaml` séparés)
- Pages principales + enums événements (`event.kind.*`, filtres) ; dashboard admin bilingue
- Fix EasyAdmin messages : champ `threadContext` (plus de `TextareaField` sur `id`)

### 2026-05-28 — Administration EasyAdmin + polish inputs

- **Back-office** EasyAdmin 5 sous chemin obscur (`EF_ADMIN_PATH`) ; staff site (modo + admin)
- CRUD utilisateurs / groupes / événements ; suppression réservée à l'admin
- Idle dédié zone admin (`EF_ADMIN_IDLE_TIMEOUT`) ; lien sidebar en nouvel onglet
- Arrondi léger des champs `.ef-input` (0.5 rem)

### 2026-06-03 — Admin : suspension site, dates, titres, recours

- **Suspension site** depuis Admin → Utilisateurs (motif obligatoire, e-mail, MP, blocage connexion, recours par e-mail)
- **Dates admin** : fuseau Europe/Paris, format `dd/MM/yyyy HH:mm` (ICU EasyAdmin ; année courte en liste)
- **Titres CRUD** : nom de rubrique dynamique (Utilisateurs, Groupes…) ; tableau de bord « Administration EventFamily »
- **`MODERATION_CONTACT`** : adresse recours modération (défaut = contact)
- **Encart login** `?suspended=1` + lien mailto ; pas de formulaire public pour les suspendus
- **Bannissements admin** : historique lecture seule ; annonces staff / notices plateforme libellées « Administration EventFamily » ou « Système »

### 2026-06-03 — Messagerie : masquage MP + purge 12 mois

- **MP** : masquage par utilisateur (plus de suppression des deux côtés) ; fil clôturé si l'expéditeur masque
- **Purge** : `app:messages:purge-old` — MP + groupe après 12 mois (notices plateforme conservées)
- Groupe : suppression auteur inchangée (hard delete) ; purge auto 12 mois

### 2026-05-31 — Recherche événements + polish Events

- **Recherche topbar** : formulaire GET → `/evenements?q=` (titre + lieu, catégorie `vue` active, visibilité)
- Harmonisation textes **responsable** ; fiche groupe (en cours + à venir) ; accueil FR ; padding fiche event

### 2026-06-02 — Module Events (MVP complet)

- CRUD événements ; droits chef/mod (+ staff site) ; suppression chef (+ admin site)
- 2 photos facultatives (couverture + lieu/détail) ; placeholders Unsplash ; purge `app:events:purge-past`
- Catégories À venir / En cours / Passés ; modales + fiche ; bandeau membres simples
- Textarea `resize: none` global ; fix requête Doctrine + fix upload sans symfony/uid

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
