# RapproFam

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

### Mailer (SMTP O2Switch)

Compte messagerie prod : **`rf_contact@rapprofam.fr`** (cPanel o2switch). Variables dans **`.env.local`** (local **et** serveur `~/rapprofam.fr/.env.local`) :

```env
MAILER_DSN=smtps://rf_contact%40rapprofam.fr:MOT_DE_PASSE@mail.rapprofam.fr:465
MAILER_FROM="RapproFam <rf_contact@rapprofam.fr>"
CONTACT_RECIPIENT=rf_contact@rapprofam.fr
MODERATION_CONTACT=rf_contact@rapprofam.fr
```

| Paramètre | Valeur |
|-----------|--------|
| Serveur SMTP | `mail.rapprofam.fr` |
| Port | **465** (SSL/TLS — schéma `smtps://` dans le DSN) |
| Authentification | Obligatoire (identifiant = adresse complète) |

Remplacer `MOT_DE_PASSE` par le mot de passe du compte cPanel. Si le mot de passe contient des caractères spéciaux (`@`, `:`, `#`…), les **encoder en URL** dans le DSN (ex. `@` → `%40`). Après modification sur le serveur : `php bin/console cache:clear --env=prod`. Test : `php bin/console mailer:test rf_contact@rapprofam.fr`.

`CONTACT_RECIPIENT` alimente le formulaire `/contact`, les CGU, les mentions et la page À propos (`ef_contact_recipient` en Twig). Voir aussi [docs/GUIDE_COMMANDES_RAPPROFAM.md](docs/GUIDE_COMMANDES_RAPPROFAM.md) § `.env.local` prod et [PRE_DEPLOY § Délivrabilité](docs/PRE_DEPLOY.md).

> **Compte récent** : un compte o2switch créé la veille peut mettre **24–48 h** avant d’accepter les envois SMTP. Configurer SPF/DKIM/DMARC dans cPanel pour limiter le dossier spam.

En dev, les e-mails partent en **synchrone** (`config/packages/messenger.yaml` → `SendEmailMessage: sync`) — pas besoin de worker Messenger pour tester l'inscription / la vérification.

## Layouts

| Layout | Fichier | Usage |
|--------|---------|--------|
| Site | `templates/base.html.twig` | Sidebar, topbar, footer, flash ; blocs optionnels `meta_description` / `meta_og` (SEO page invité) |
| Auth | `templates/layout/auth.html.twig` | Login / register (`data-turbo="false"`, thème en coin) |
| Légal | `templates/layout/legal.html.twig` | CGU, mentions (sans sidebar ; `meta description` ; bandeau cookies) |
| Erreur | `templates/layout/error.html.twig` | 404 et pages d’erreur (sans sidebar ; CSS dédié `error-page.scss`) |

### Thème (clair / sombre / auto)

| Élément | Détail |
|---------|--------|
| Stockage site | `localStorage` clé `ef-theme` (`light` / `dark` / `auto`) |
| Init avant paint | `assets/js/ef-theme-init.js` + `templates/components/_ef_theme_critical.html.twig` |
| UI site | `assets/js/ef-layout.js` (sélecteur topbar / auth, Turbo-safe) |
| Sync admin | `assets/js/ef-admin-theme-sync.js` — copie `ef-theme` → `ea/colorScheme` (EasyAdmin) et inverse au changement dans l’admin |
| Turbo | Réapplication du thème sur `turbo:before-render` / `turbo:render` / `turbo:before-cache` (la réponse HTML force `data-bs-theme="light"` sur `<html>`) |

### Langue (français / anglais)

| Élément | Détail |
|---------|--------|
| Défaut | **Français** (`framework.default_locale: fr`) |
| Bascule | Dropup sidebar : affiche la **langue cible** (ex. « English » si le site est en français), clic → bascule |
| Persistance | Session `_ef_locale` + cookie `ef_locale` (1 an) + champ `User.locale` si connecté |
| Admin EA | Dropdown langue FR/EN dans le dashboard (`setLocales`) |
| Fichiers | `translations/messages.{fr,en}.yaml` (domaine `messages` pour `|trans`) · `security.*` · `validators.*` |

Services : `LocaleService`, `LocaleSubscriber`, `LocaleController`, extension Twig `LocaleExtension` (`ef_locale_switch_label` = libellé affiché dans le menu).

**Templates traduits (FR + EN)** : layout, accueil, événements, groupes, messages, invitations, profil, about, contact, auth, **pages légales** (CGU RGPD, mentions LCEN, placeholders hébergeur), composants communs, admin EasyAdmin, alertes JS avatar/contact (via `data-ef-alert-*`).

**Contenu juridique long** (`legal.*` dans `messages.{fr,en}.yaml`) : CGU RGPD + mentions LCEN ☑ livré juin 2026 (données réelles, tiers Google/SMTP/PayPal, droits, CNIL, projet perso). E-mails pro ☑ (`rf_contact@rapprofam.fr` — voir [Mailer](#mailer-smtp-o2switch)). Reste avant prod : placeholders **hébergeur** + `PUBLISHER_ADDRESS` si déménagement.

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
| **Marketing / publicité** | Livré (code) | **Google AdSense** — balise `<head>` + emplacements pub ; annonces visibles seulement si `marketing: true` (`assets/js/ef-adsense.js`) |
| **Mesure d’audience (analytics)** | Livré | GA4 via `EF_GOOGLE_ANALYTICS_ID` + `assets/js/ef-analytics.js` (chargement si `analytics: true` dans `ef_consent`) |

**Feuille de route cookies / pub** :

1. **Analytics** — livré ; `EF_GOOGLE_ANALYTICS_ID` renseigné en prod.
2. **AdSense** — ☑ code prod (`EF_GOOGLE_ADSENSE_CLIENT_ID`, `ads.txt`, emplacements discrets) · **examen Google en cours** (juin 2026).
3. **CMP Google certifiée (EEE / UK / CH)** — **en attente** : à activer dans AdSense **après approbation** (message 3 choix : Autoriser / Ne pas consentir / Gérer) ; le bandeau maison reste pour analytics et la transparence CNIL.

Ne pas ajouter de cases vides dans le bandeau avant d’avoir le service réel (recommandation CNIL).

Lien footer **Gérer les cookies** · CGU `#privacy` · après modif SCSS : `composer assets:refresh` ou `php bin/console sass:build --watch`.

### Soutien du projet (PayPal)

| Élément | Détail |
|---------|--------|
| Emplacement | Footer site — colonne **« Soutenir le projet »** (membres connectés uniquement) |
| Bouton | **« Offrir un coup de pouce »** / **« Give a little boost »** (`ui.footer.donate`) |
| Lien | Page PayPal Donate hébergée — `templates/layout/_footer.html.twig` (`hosted_button_id=E8ULND24DQE2W`) |
| Comportement | Nouvel onglet (`target="_blank"`, `rel="noopener noreferrer"`) |
| i18n | `ui.footer.donate_aria` — libellé accessible FR / EN |

> **Avant déploiement** : dans le [tableau de bord PayPal](https://www.paypal.com/donate/), remplacer les **URL de redirection** (retour / annulation) configurées en **local** par les **URLs HTTPS du site en production** — voir [checklist déploiement](#déploiement-checklist) et [docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md).

### SCSS (`assets/styles/`)

- `@use` uniquement (pas `@import`) · classes **`ef-`**
- Entrées SASS compilées : `app.scss` (site), `error-page.scss` (404 / erreurs), `ef-admin.scss` (back-office)
- Pages : `home`, `about`, `contact`, `sign-in`, `legal`, `error`, `profile`, `groups`, `group-show`, `messages`, `events`, `event-show`
- Composants : `back-to-top`, `alerts`, `dropdowns`, `session-idle`, `cookie-consent`, `messages-avatar`
- JS messagerie : `ef-messages.js`, `ef-group-message-photos.js`, `ef-message-photo-lightbox.js` (Cropper.js à la demande pour upload photos)
- **Images statiques** : `assets/images/event-placeholders/` (couvertures événements) · `assets/images/home/hero.jpg` (hero accueil). Dans le SCSS, les `url()` sont résolus **depuis** `assets/styles/` (fichier racine `app.scss`) — ex. `../images/home/hero.jpg`, pas `../../images/…`

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
| `GET /messages/photo/{id}` | `app_messages_photo_show` | Affichage photo message de groupe (membres) |
| `/contact` | `app_contact` | Formulaire contact (connecté) |
| `/locale/switch` | `app_locale_switch` | Bascule FR ↔ EN (session + profil utilisateur) |
| `POST /profil/avatar` | `app_profile_avatar_upload` | Upload avatar |
| `GET /profil/avatar/{id}` | `app_profile_avatar_show` | Affichage avatar (selon visibilité) |

Accès public **invité** (sans compte) : `/` (vitrine), `/about`, `/cgu`, `/mentions-legales`, `/locale/switch`, auth (`/login`, `/register`, reset / verify e-mail, …).  
**Réservé `ROLE_USER`** : `/evenements`, `/groupes`, `/messages`, `/contact`, `/profil`, invitations, etc. Voir [Accueil public & AdSense](#accueil-public--adsense).

Le back-office EasyAdmin est servi sous un **chemin obscur** (`EF_ADMIN_PATH`, ex. `/ef-console-8f3a2c91`) — réservé au **staff site** (`ROLE_MODERATOR` minimum : modérateur, super-modérateur, administrateur).

## Administration (EasyAdmin)

| Élément | Détail |
|---------|--------|
| URL | `/%EF_ADMIN_PATH%/` (défaut `ef-console-8f3a2c91`) — **à personnaliser en prod** (`.env.local`) |
| Sidebar site | Lien « Administration » visible staff site uniquement, ouverture **nouvel onglet** |
| Tableau de bord | Titre **« Administration RapproFam »** ; cartes vers chaque rubrique |
| Titres de rubrique | En-tête de chaque CRUD = nom de la section (Utilisateurs, Groupes, Événements, Messages, Bannissements) — plus le mot générique « Administration » |
| Droits | Hiérarchie **modo → super-modo → admin** (`AdminUserPolicyService`) : édition / ban selon le palier ; **suppression comptes** : super-modo + admin (admin seul pour un compte admin) ; **suppression** groupes / événements / messages : admin seul |
| CRUD | Utilisateurs, groupes, événements, **messages** (consultation litige), **bannissements** (historique lecture seule) — listes allégées, filtres traduits FR/EN |
| Erreurs admin | `AdminAccessDeniedSubscriber` : flash + retour liste (plus de page brute « Access Denied ») |
| Dates admin | Fuseau **Europe/Paris** ; format `jj/mm/aaaa HH:mm` (année courte `jj/mm/aa` en liste) — motifs ICU EasyAdmin (`dd/MM/yyyy`) |
| i18n | Menu + titres via clés `admin.*` ; sélecteur FR/EN EasyAdmin ; libellés natifs `EasyAdminBundle.*` |
| Thème sombre | Préférence site synchronisée (`ef-admin-theme-sync.js`) ; fond actif sidebar assombri en `.ea-dark-scheme` (`ef-admin.scss`) |
| CSRF session | Ping activité renvoie des jetons frais ; `AdminCsrfExceptionSubscriber` remplace la page brute « Invalid CSRF token. » par un flash FR + rechargement |
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
| Déban | Décocher la case → e-mail de réactivation (pied de page : *« Si tu ne t'attendais pas à cette réactivation… »*) |
| Historique | Entrée `UserBan` sans groupe (« Suspension site ») dans **Bannissements** |

Services : `AdminPlatformBanService`, `AdminUserPolicyService`, `PlatformBanAccessSubscriber`. Pas de création manuelle de ban site depuis **Bannissements** (lecture seule).

Variables : `EF_ADMIN_PATH`, `EF_ADMIN_IDLE_TIMEOUT`, `EF_ADMIN_IDLE_WARNING`, `MODERATION_CONTACT` (voir `.env`).

Contrôleurs : `DashboardController`, `UserCrudController`, `GroupCrudController`, `EventCrudController`, `MessageCrudController`, `UserBanCrudController`, `GroupSystemNoticeController`, `AdminSessionActivityController`.

### Rubriques CRUD (état juin 2026)

| Rubrique | Index | Édition / actions | Notes |
|----------|-------|-------------------|-------|
| **Utilisateurs** | Badges rôles staff ; comptes actifs par défaut (filtre *Comptes supprimés*) ; lignes non éditables non cliquables | Rôles : **admin seul** ; ban/déban selon palier ; motif suspension en lecture seule si déjà suspendu ; case suspension grisée si interdit | Comptes **admin** masqués aux non-admins |
| **Groupes** | Nom, famille, responsable | Réassignation responsable ; message système : **admin seul** | Suppression : admin seul |
| **Événements** | Titre, type, début, groupe | CRUD standard staff | Suppression : admin seul |
| **Messages** | Extrait, auteur, destinataire, groupe, métadonnées | **Lecture seule** + détail fil litige ; recherche ID + contenu | Suppression : admin seul ; variante notice = champ virtuel (plus d'erreur enum) |
| **Bannissements** | Utilisateur, motif (extrait), groupe, palier, statut | **Lecture seule** (+ détail) | Suppression : admin seul |

### Validation manuelle admin

| Zone | Statut |
|------|--------|
| Garde-fous rôles / ban / déban / suppression (Utilisateurs) | ☑ Smoke test **modo / super-modo / admin** validé en local (juin 2026) |
| Polish listes + filtres (toutes rubriques CRUD) | ☑ Juin 2026 |
| Timeout admin + CSRF expiré | Correctif en place (`AdminCsrfExceptionSubscriber`) — re-test rapide conseillé avant prod |
| Ouverture à de vrais modérateurs | Après relecture **PRE_DEPLOY** + modifs vitrine pré-déploiement |

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

## Messagerie

Hub `/messages` → **Messages privés** ou **Messages de groupe**.

| Fonctionnalité | Privé | Groupe |
|----------------|-------|--------|
| Fil / conversation | **1 fil actif par paire** (messages regroupés) | **1 fil racine par publication** (tableau d’affichage) |
| Réponses | Illimitées (pagination : 30 → 200 par fil) | Illimitées |
| **Photos** | Non | **0 à 2** par message racine ; légende optionnelle (500 car. max si photo) |
| Rate limit messages | 20 / h / utilisateur | 15 / h / utilisateur |
| Rate limit photos | — | **6 / h** + **20 / jour** / utilisateur |
| Marquage lu | AJAX au scroll (`ef-messages.js`) | En masse à l’ouverture du groupe |
| Accusé « Lu le… » | Oui (expéditeur) | Non |
| E-mail notification | Oui — opt-in Mon espace → Notifications | Non |
| E-mail délivrabilité | Texte + HTML, `List-Unsubscribe` → `/profil#notifications` | — |
| Purge | 12 mois (`app:messages:purge-old`) | Idem (+ fichiers photos) |

Config messages : `config/packages/ef_messages.yaml` (pagination, limites, throttle e-mail 30 min).

### Photos dans les messages de groupe (juin 2026)

| Élément | Détail |
|---------|--------|
| Périmètre | Messages **racine** de groupe uniquement (pas les réponses, pas les MP) |
| Nombre | **2 max** par message ; texte **optionnel** (0–500 car. si photo jointe) |
| Upload | JPG / PNG / WebP — **3 Mo** max à l’upload ; traitement GD → WebP **1200 px** (~100–250 Ko) |
| UX envoi | Preview + recadrage **optionnel** (Cropper.js, chargé à la demande) ; avertissement confidentialité à l’import |
| Affichage | Lightbox in-page (pas de nouvel onglet) ; **pas de bouton téléchargement** (consultation seule) |
| Visibilité | Membres du groupe + staff site ; route protégée `app_messages_photo_show` |
| Stockage | `var/storage/message-photos/` ; purge fichiers avec le message (12 mois ou suppression) |
| Anti-spam | Rate limit photos **6/h** + **20/j** ; messages groupe **15/h** (calibré mutualisé PlanetHoster The World) |

Config photos : `config/packages/ef_message_photos.yaml` · services `MessagePhotoService`, `MessagePhotoProcessor`, `GroupMessagePhotoRateLimitService` · JS `ef-group-message-photos.js`, `ef-message-photo-lightbox.js`.

**UI fils (privé + groupe)** : avatar **42 px** à gauche de chaque carte (message racine et réponses) — photo si `profile_avatar_visible()` (même règle que le profil : public, groupe commun ou soi) ; sinon icône `bi-person-fill`. Styles isolés dans `assets/styles/components/_messages-avatar.scss` (retirable sans toucher au reste de `_messages.scss`). **Mobile** : badge compteur de fils en pilule compacte (`ef-messages__threads-badge`, `align-items-start` sur l’en-tête privé / groupe).

Services : `MessageService`, `MessageRepository`, `PrivateMessageNotificationService`, `PrivateMessageRateLimitService`, `GroupMessageRateLimitService`, `GroupMessagePhotoRateLimitService`, `DirectMessagePolicy`.

**v2 reporté (si besoin)** : Web Push · e-mails groupe · pagination réponses groupe très longues · bouton téléchargement opt-in auteur.

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

Fichiers : `AdminSessionIdleSubscriber`, `AdminSessionActivityController`, `public/js/ef-admin-idle.js` (rafraîchit les jetons CSRF à chaque ping), modale dans le layout EA.

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
- Notifications : e-mail + message privé RapproFam (vérifiés) ; e-mail seul si jamais activé

**Tester en local :**

1. Compte membre d'un groupe (pas chef), vérifié
2. En BDD : `last_login_at` dans le passé, `inactive_warning_count = 0`
3. **Sans se connecter** avec ce compte : `php bin/console app:users:purge-inactive -v`
4. Se connecter ensuite pour lire le message privé

> Se connecter **avant** la commande remet `last_login_at` à maintenant et annule le test.

Services : `InactiveAccountPurgeService`, `InactiveAccountNotificationService`, config `config/packages/ef_inactive_accounts.yaml`.

## Messagerie — règles métier

Entités : `Message`, `MessageRead`, `MessagePhoto`.

- **Message système** (tête du fil groupe) : toujours affiché, non supprimable / non répondable par les membres ; édition **staff site** (`GroupSystemNoticeService`, `Group.systemNoticeContent`)
- **Annonces staff** (admin/modo site) : fil privé orange « RapproFam », sans réponse (`PlatformNoticeVariant::RapproFam`)
- **Notices plateforme** (bans, inactivité) : messages privés système (`PlatformNoticeVariant::System` / `RapproFam`)
- **Privé** : **1 fil actif par paire** d'utilisateurs ; réponses illimitées (pagination 30 → 200 par fil) ; rate limit **20/h**
- **E-mail nouveau MP** : opt-in Mon espace → Notifications (`notifyPrivateMessageEmail`) ; throttle 30 min / conversation ; texte + HTML + `List-Unsubscribe`
- **Accusé de lecture** : « Lu le… » visible par l'expéditeur (MP uniquement)
- **Masquage MP** : suppression « de mon côté » (`author_hidden_at` / `recipient_hidden_at`) — l'autre partie conserve le fil
- **Fil clôturé** : dès qu'un participant **masque** le MP, plus de réponse possible pour **aucun** des deux
- **Groupe** : seul l'auteur peut supprimer (hard delete pour tous les membres) ; rate limit **15/h** ; marquage lu en masse à l'ouverture
- **Photos groupe** : 0–2 par message racine ; upload 3 Mo → WebP 1200 px ; lightbox sans téléchargement ; avertissement confidentialité ; limites **6/h** + **20/j** photos ; fichiers `var/storage/message-photos/` supprimés avec le message
- **Purge auto** : `php bin/console app:messages:purge-old` (`ef.messages.purge_retention_months`, défaut **12 mois**) — MP + groupe + fichiers photos ; notices plateforme conservées
- **Groupe** : membres du groupe ; sélecteur si plusieurs groupes ; point rouge sur groupes avec messages non lus
- **Lecture MP** : auto à l'affichage (Intersection Observer, `ef-messages.js`)
- Règles MP : `DirectMessagePolicy` (ban groupe, pas de MP à soi-même)

**v2 reporté** : Web Push · e-mails groupe · masquage groupe (au lieu du hard delete auteur) · pagination réponses groupe très longues.

## Base de données

- **`.env.local`** : `DATABASE_URL` → MySQL `ef_base` (Laragon)
- Migrations : `php bin/console doctrine:migrations:migrate`

### Tables

| Table | Entité |
|-------|--------|
| `ef_users` | `User` (+ `locale`, `notify_private_message_email`, `inactive_warning_count`, `last_inactive_warning_at`, `last_login_at`, `deleted_at`) |
| `ef_groups` | `Group` |
| `ef_group_members` | `GroupMember` |
| `ef_group_requests` | `GroupRequest` |
| `ef_user_bans` | `UserBan` |
| `ef_messages` | `Message` |
| `ef_message_photos` | `MessagePhoto` |
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
| Formulaire contact | OK en dev (SMTP o2switch ou `null://null`, **sans** reCAPTCHA si clés vides) — voir [Services Google](#services-google-dev-ok--prod-à-reconfigurer) |
| Google OAuth | **Livré** en dev (connexion / inscription, CGU, finalisation profil) — **reconfigurer les URI et clés pour la prod** (voir [PRE_DEPLOY](docs/PRE_DEPLOY.md)) |

### Services Google (dev OK · prod à reconfigurer)

> En local, les clés peuvent être dans `.env.local`. **Avant déploiement**, chaque service doit être recréé ou mis à jour pour le **domaine HTTPS de production** (domaines autorisés, URI de redirection, etc.).

| Service | Console Google | Variables `.env` | Dev | Prod |
|---------|----------------|------------------|-----|------|
| **reCAPTCHA v3** (contact) | [reCAPTCHA Admin](https://www.google.com/recaptcha/admin) | `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` | Clés de test possibles | **Nouvelles clés** + domaine prod (`localhost` ≠ domaine final) |
| **OAuth 2.0** (Google) | [Cloud Console](https://console.cloud.google.com/) | `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, `GOOGLE_OAUTH_REDIRECT_URI` | OK si URI locales enregistrées | **URI de redirection** `https://DOMAIN/connect/google/check` · `DEFAULT_URI` prod |
| **Analytics** (GA4) | [Google Analytics](https://analytics.google.com/) | `EF_GOOGLE_ANALYTICS_ID` (ex. `G-XXXXXXXX`) | ID de test possible | **Propriété / flux prod** + domaine · chargé seulement si consentement `analytics` |
| **AdSense** (pub) | [Google AdSense](https://www.google.com/adsense/) | `EF_GOOGLE_ADSENSE_CLIENT_ID` (`ca-pub-…`) · `EF_GOOGLE_ADSENSE_SLOT_*` (par page, vide = pas de bloc) | ID dans `.env` | ☑ balise + `ads.txt` prod · **examen en cours** · slots + **CMP Google** après approbation |

Configurations **distinctes** (reCAPTCHA ≠ OAuth ≠ Analytics ≠ AdSense). Diagnostic OAuth local : `php bin/console ef:google-oauth:diagnose`.

#### Alerte Chrome « Site dangereux » (Safe Browsing)

En juin 2026, **Chrome** peut afficher *« Site dangereux »* sur `https://rapprofam.fr` **sans que le code actuel soit en cause** : un domaine réutilisé peut conserver une **réputation négative** si un ancien propriétaire l’a utilisé pour du contenu malveillant (phishing, malware, spam). Google **ne réinitialise pas** automatiquement l’alerte au changement de site.

| Étape | Action |
|-------|--------|
| 1 | Vérifier le statut : [Google Safe Browsing — rapport de transparence](https://transparencyreport.google.com/safe-browsing/search?url=rapprofam.fr) |
| 2 | Ajouter la propriété **`rapprofam.fr`** dans [Google Search Console](https://search.google.com/search-console) (validation DNS ou fichier HTML) |
| 3 | Menu **Sécurité et actions manuelles** → problèmes signalés → **Demander un examen** une fois le site propre (HTTPS, pas de malware, pas de pages trompeuses) |
| 4 | Attendre la réponse Google (**quelques jours à 2 semaines** en général) — pas d’action côté code Symfony en attendant |

**Statut juin 2026** : demande d’examen **déposée** (hypothèse : historique du nom de domaine). L’alerte peut persister sur Chrome **jusqu’à levée par Google** ; Firefox/Safari peuvent ne pas l’afficher. Ne pas paniquer : ce n’est en principe **pas** un blocage hébergeur o2switch.

> Distinct de **AdSense** (monétisation) et de **reCAPTCHA** (anti-spam contact) — trois services Google séparés.

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

Actions : retour accueil ; contact (connecté) ou about (invité / dev `/_error/404`) ; marque RapproFam en pied de carte.

### Pages légales (livré — juin 2026)

| Élément | Détail |
|---------|--------|
| URLs publiques | `/cgu` (CGU + politique de confidentialité, ancre `#privacy` pour le bandeau cookies) · `/mentions-legales` |
| Contrôleur | `src/Controller/LegalController.php` — `GET` uniquement |
| Templates | `templates/legal/cgu.html.twig`, `mentions.html.twig` · layout `layout/legal.html.twig` |
| i18n | `legal.*` + `ui.legal.*` dans `messages.{fr,en}.yaml` |
| Config éditeur | `CONTACT_RECIPIENT` → `ef_contact_recipient` (e-mail) · `PUBLISHER_ADDRESS` → `ef_publisher_address` (adresse postale LCEN / RGPD) |
| Contenu | Projet perso non commercial ; périmètre familial ; données alignées sur le site (OAuth, messages, cookies, Analytics, AdSense, SMTP, PayPal) ; droits RGPD + CNIL |
| Reste avant prod | Placeholders **hébergeur** (`legal.mentions.hosting.*`) — voir [PRE_DEPLOY](docs/PRE_DEPLOY.md) · e-mails ☑ `rf_contact@rapprofam.fr` |

**Volontairement non retenu** : vouvoiement dans le corps juridique (`legal.*`) ; Open Graph dédié sur pages légales.

## Déploiement (checklist)

> **Checklist complète et à jour** : **[docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md)** — à relire **intégralement** avant `APP_ENV=prod` et mise en ligne HTTPS.

### Synthèse — bloquant prod

| # | Sujet |
|---|--------|
| 1 | **HTTPS** + `DEFAULT_URI=https://…` |
| 2 | **`APP_SECRET`** unique (≠ dev) |
| 3 | **`DATABASE_URL`** + `doctrine:migrations:migrate` |
| 4 | **`MAILER_DSN`** o2switch (`smtps://rf_contact%40rapprofam.fr@mail.rapprofam.fr:465`) + **`MAILER_FROM`** + **SPF / DKIM / DMARC** cPanel |
| 5 | **reCAPTCHA** prod (`RECAPTCHA_*`) — domaines du site |
| 6 | **`EF_ADMIN_PATH`** personnalisé |
| 7 | **Assets** : `sass:build`, `asset-map:compile`, `cache:clear` prod |
| 8 | **Dossiers writable** : `var/storage/avatars/`, `var/storage/events/`, `var/storage/message-photos/` |
| 9 | **PHP GD** activé (avatars, photos événements, **photos messages groupe**) |

### Synthèse — contenu & légal

| # | Sujet |
|---|--------|
| 10 | E-mails pro ☑ `rf_contact@rapprofam.fr` — vérifier `MAILER_DSN` (mdp) sur le serveur + test envoi |
| 10b | **`PUBLISHER_ADDRESS`** — vérifier l’adresse postale éditeur en prod (défaut Lagord dans `.env`) |
| 11 | **`CONTACT_WHATSAPP`** — numéro réel pour `/contact` |
| 12–14 | **CGU & mentions — contenu** ☑ livré (RGPD, LCEN, projet perso) — FR + EN |
| 15 | **Hébergeur** — remplacer `legal.mentions.hosting.*_placeholder` quand l’hébergeur est choisi |
| 16 | Cookies / AdSense / PayPal — voir [PRE_DEPLOY](docs/PRE_DEPLOY.md) |

### Synthèse — services Google & OAuth

| # | Sujet |
|---|--------|
| 17 | **Google OAuth** — clés prod, `GOOGLE_OAUTH_REDIRECT_URI`, URI Cloud Console |
| 5b | **Google Analytics** — `EF_GOOGLE_ANALYTICS_ID` prod + test consentement analytics |
| 17b | **Safe Browsing** — si alerte « Site dangereux » : Search Console → demande d’examen (voir [§ Safe Browsing](#alerte-chrome-site-dangereux-safe-browsing)) |

### Synthèse — exploitation

| # | Sujet |
|---|--------|
| 18 | Worker **Messenger** si e-mails async en prod |
| 19–21 | Cron : `app:users:purge-inactive`, `app:events:purge-past`, `app:messages:purge-old` |
| 22 | **Sauvegardes BDD** régulières |

### Synthèse — tests manuels avant ouverture

| # | Scénario |
|---|----------|
| 23–24 | Inscription, vérif e-mail, mot de passe, Google OAuth |
| 25 | Contact + reCAPTCHA |
| 26 | Groupes, événements, **messages privés + groupe**, **photos groupe**, invitations |
| 26b | **MP** : fil unique, réponses, accusé « Lu », e-mail notif + désactivation Mon espace |
| 26c | **Photos groupe** : upload 1–2, recadrage, lightbox, limites 6/h · avertissement confidentialité |
| 27–32 | Locale FR/EN, session idle, avatars, admin, 404, PayPal |
| 33 | **Délivrabilité** : mail-tester.com ≥ 8/10 ; MP reçu Gmail + Outlook |

Détail, PayPal, OAuth, variables `.env` et **§ Délivrabilité e-mail** : [docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md).

### Variables d'environnement (`.env.local` prod)

| Variable | Action |
|----------|--------|
| `APP_ENV` | `prod` |
| `APP_SECRET` | Secret unique (≠ dev) |
| `DATABASE_URL` | MySQL hébergeur |
| `MAILER_DSN` | `smtps://rf_contact%40rapprofam.fr:MOT_DE_PASSE@mail.rapprofam.fr:465` |
| `MAILER_FROM` | `"RapproFam <rf_contact@rapprofam.fr>"` |
| `DEFAULT_URI` | URL publique HTTPS (ex. `https://rapprofam.fr`) |
| `CONTACT_RECIPIENT` | E-mail de réception du formulaire contact, mentions et CGU (`ef_contact_recipient`) |
| `PUBLISHER_ADDRESS` | Adresse postale de l’éditeur — mentions + responsable du traitement RGPD (`ef_publisher_address`) |
| `MODERATION_CONTACT` | Recours suspension site (défaut `${CONTACT_RECIPIENT}` dans `.env`) |
| `CONTACT_WHATSAPP` | Numéro WhatsApp E.164 (`336…`) pour lien `wa.me` sur `/contact` — vide = carte masquée |
| `RECAPTCHA_SITE_KEY` | Clé site reCAPTCHA v3 ([Google Admin](https://www.google.com/recaptcha/admin)) — **domaines prod** |
| `RECAPTCHA_SECRET_KEY` | Clé secrète reCAPTCHA v3 — **obligatoire en prod** pour le contact |
| `GOOGLE_OAUTH_CLIENT_ID` / `GOOGLE_OAUTH_CLIENT_SECRET` | Identifiants OAuth « Application Web » ([Cloud Console](https://console.cloud.google.com/)) |
| `GOOGLE_OAUTH_REDIRECT_URI` | URI exacte enregistrée chez Google, ex. `https://votredomaine.fr/connect/google/check` (sans `/` final avant `connect`) |
| `EF_GOOGLE_ANALYTICS_ID` | ID de mesure GA4 (`G-…`) — flux / URL du site prod |
| **PayPal Donate** | [Dashboard PayPal](https://www.paypal.com/donate/) — **URL de retour** et **URL d'annulation** : remplacer les URLs **locales** (`localhost`, Laragon…) par le domaine **HTTPS prod** ; vérifier le lien du bouton dans `templates/layout/_footer.html.twig` si le `hosted_button_id` change |

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
| Dossier photos messages | `var/storage/message-photos/` writable par PHP |
| Extension PHP | **GD** activée (avatars + photos événements + photos messages) |

### Fonctionnalités à finaliser avant prod

| Élément | État |
|---------|------|
| **Google OAuth** | Code livré — **clés + URI de redirection prod** ([PRE_DEPLOY](docs/PRE_DEPLOY.md)) |
| **reCAPTCHA v3** | Code livré — **clés + domaines prod** pour `/contact` |
| **Google Analytics** | Bandeau cookies + `EF_GOOGLE_ANALYTICS_ID` — **propriété GA4 prod** et URL du site |
| **WhatsApp contact** | **Livré (v1)** — `CONTACT_WHATSAPP` + lien `wa.me` sur `/contact` ; numéro réel en prod. API Meta → v2 |
| **Délivrabilité e-mail** | Code livré (texte + HTML, List-Unsubscribe) — **SPF + DKIM + DMARC** obligatoires en prod ([PRE_DEPLOY § Délivrabilité](docs/PRE_DEPLOY.md)) |
| **CGU & mentions — contenu juridique** | ☑ Livré juin 2026 — RGPD, LCEN, projet perso, données réelles, `PUBLISHER_ADDRESS` |
| **CGU & mentions — hébergeur** | Remplacer les placeholders `legal.mentions.hosting.*` (nom, adresse, contact hébergeur) — FR + EN |
| **E-mails professionnels** | ☑ `rf_contact@rapprofam.fr` — configurer `MAILER_DSN` (mdp) dans `.env.local` serveur ; SPF/DKIM en prod |
| **Safe Browsing (Chrome)** | Demande d’examen déposée juin 2026 — attente levée Google (historique domaine possible) |
| **Messenger async** | En prod, configurer worker si e-mails async |
| **HTTPS** | Obligatoire (cookies session, remember-me) |
| **Bandeau cookies** | Livré (nécessaires + analytics + marketing) | AdSense branché prod ; CMP Google certifiée **après** approbation AdSense |
| **PayPal — soutien** | Bouton footer livré (lien donate hébergé) | **Avant prod** : URLs de redirection PayPal (retour / annulation) → domaine HTTPS déployé, pas localhost |
| **Sauvegardes BDD** | Planifier backups `ef_base` |

### Contact (anti-spam)

| Environnement | reCAPTCHA | Statut |
|---------------|-----------|--------|
| **Dev local** | Désactivé si clés vides | **Utilisable** — SMTP o2switch dans `.env.local` (ou `null://null`), honeypot, rate limit |
| **Production** | **Obligatoire** (`RECAPTCHA_SECRET_KEY`) | Brancher **dès réception des clés** Google reCAPTCHA v3 |

- Limites : **5 / heure**, **20 / jour** par compte (assouplies en `APP_ENV=dev`)
- Honeypot + délai minimum 3 s en prod — **0 s en dev**
- Message min. **20** caractères, max. 2000
- Connecté uniquement (`ROLE_USER`)

**Test local** (`.env.local`) :

```env
MAILER_DSN=smtps://rf_contact%40rapprofam.fr:MOT_DE_PASSE@mail.rapprofam.fr:465
CONTACT_RECIPIENT=rf_contact@rapprofam.fr
RECAPTCHA_SITE_KEY=          # vide = pas de reCAPTCHA (normal en attendant les clés)
RECAPTCHA_SECRET_KEY=
```

Après envoi : flash vert *« Ton message a bien été envoyé… »* + e-mail dans la boîte `CONTACT_RECIPIENT`. Pas de bandeau spécial sur la page — si vous voyez une erreur, c’est un flash rouge ou une alerte de validation.

### Hébergement PlanetHoster The World (démarrage)

Calibrage **juin 2026** pour un compte N0C mutualisé (CPU / RAM / I/O limités) — ajuster dans `ef_message_photos.yaml` et `ef_messages.yaml` si montée en charge.

| Paramètre | Valeur prod initiale | Fichier |
|-----------|---------------------|---------|
| Photos / message groupe | 2 | `ef_message_photos.yaml` |
| Upload photo max | 3 Mo | idem |
| Dimension après GD | 1200 px WebP | idem |
| Photos / h / utilisateur | 6 | idem |
| Photos / jour / utilisateur | 20 | idem |
| Messages groupe / h | 15 | `ef_messages.yaml` |
| Messages privés / h | 20 | idem |
| Purge messages + photos | 12 mois | idem |

**Recommandation compte N0C** : allouer **≥ 2 Go RAM** au projet RapproFam si possible (traitement GD + Symfony). Sauvegardes **BDD + `var/storage/`** (avatars, events, message-photos).

### Avatars

- Stockage : `var/storage/avatars/` (original + version 512×512 WebP/JPEG)
- Visibilité : publique (tous les membres) ou privée (membres d'un groupe commun)
- Fichiers renommés en UUID — jamais le nom d'origine
- **Réutilisation messagerie** : partial `templates/messages/_user_avatar.html.twig` + `ProfileAvatarExtension` (`profile_avatar_visible`) — portrait profil (`_avatar_portrait.html.twig`) et cartes MP/groupe partagent la même logique d’affichage

## Performances & navigation (état au 2026-06-05)

### Environnement local

En **`APP_ENV=dev`** (`symfony serve`), chaque page est volontairement plus lente qu’en production :

- Web Profiler, pas de cache applicatif optimisé, PHP sans OPcache agressif
- Une page simple peut afficher **~0,8–1,5 s** dans la barre Symfony alors que MySQL reste rapide
- Les pages **About** et **Contact** paraissent plus rapides : elles n’ajoutaient pas de scripts supplémentaires dans le `<head>` (voir correctif Turbo ci-dessous)

**Test réaliste en local** :

```powershell
$env:APP_ENV="prod"; $env:APP_DEBUG="0"
php bin/console cache:clear
php bin/console cache:warmup
# puis relancer le serveur
```

### Navigation Turbo (correctifs juin 2026)

| Problème | Cause | Correctif |
|----------|-------|-----------|
| Voile blanc sur le contenu | Overlay `.ef-turbo-navigating` + garde-fou `turbo:before-render` qui bloquait le rendu | Garde-fou supprimé ; overlay retiré ; un seul bundle JS |
| Retour navigateur : URL change, pas le contenu | `turbo:before-visit` ne s’exécute **pas** sur l’historique — le filtre d’URL bloquait le rendu | Plus de `preventDefault()` sur `turbo:before-render` |
| Barre bleue bloquée longtemps | Visite Turbo jamais terminée (rendu bloqué) + scripts `<head>` par page | JS centralisé dans `assets/app.js` |
| Pages lentes (sauf About / Contact) | Turbo **attend** chaque nouveau `<script>` / `<link>` injecté dans `{% block javascripts %}` | Tous les modules page importés une fois dans `app.js` ; Cropper chargé à la demande (`ef-profile-avatar.js`) ; `groups-form-page.css` dans `app.scss` |

Fichiers : `assets/app.js`, `assets/js/ef-theme-init.js`, `templates/components/_ef_theme_critical.html.twig`, `meta turbo-cache-control no-preview` dans `base.html.twig`.

> **Règle** : ne pas ajouter de `<script type="module">` par page dans Twig — tout passer par `app.js` (ou import dynamique au clic).

### Optimisations déjà livrées

| Zone | Mesure |
|------|--------|
| **Badges sidebar / cloche** | AJAX (`ef-notifications.js`), plus de requêtes SQL dans chaque HTML |
| **Messages groupe** | Marquage lu à l’ouverture ; rate limit 15/h ; **photos** 6/h + 20/j ; pagination fils ; pastilles non-lues AJAX |
| **Messages privés** | Fil unique par contact ; e-mail notif (opt-out) ; accusés de lecture ; pagination fils + réponses ; rate limit 20/h |
| **Turbo** | JS unique, thème réappliqué avant rendu, pas de voile parasite ; `no-preview` cache |
| **Listes** | Pagination groupes / événements / membres ; cartes groupes allégées |
| **Admin messages** | Recherche index limitée à l’ID (plus de scan `content`) |

### Pistes restantes (à poursuivre)

- Valider les perfs en **prod** (`APP_ENV=prod`, cache warmup) — le léger délai local peut être normal
- Profiler ciblé : temps **Total** vs **Doctrine** sur messages groupe, admin dashboard
- Contact lent **au hasard** : souvent reCAPTCHA / réseau au **submit** ; page GET légère
- Index BDD si volume messages très élevé

## Prochaines étapes

> Le développement **accueil public / about / cookies / analytics / pages légales** est livré en local. **Déploiement, hébergeur, e-mails pro, AdSense et tests prod** sont regroupés dans [docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md) — à exécuter **juste avant** la mise en ligne.

### Avant déploiement & AdSense (checklist — pas maintenant)

À faire **en une passe** avant d’ouvrir le site au public et de demander l’examen AdSense :

| # | Action |
|---|--------|
| 1 | **HTTPS** + `DEFAULT_URI` prod · assets (`sass:build`, `asset-map:compile`) · secrets / `EF_ADMIN_PATH` |
| 2 | **E-mails pro** — `MAILER_DSN` o2switch (mdp dans `.env.local` serveur) ; `rf_contact@rapprofam.fr` ; SPF/DKIM ; `PUBLISHER_ADDRESS` si l’adresse a changé |
| 3 | **Hébergeur** — renseigner `legal.mentions.hosting.*` (placeholders FR + EN) |
| 4 | **Google OAuth** — clés prod ; `GOOGLE_OAUTH_REDIRECT_URI` = `https://DOMAIN/connect/google/check` ; URI enregistrée dans Cloud Console |
| 5 | **reCAPTCHA** — clés prod + **domaines** du site (`RECAPTCHA_*`) pour `/contact` |
| 6 | **Google Analytics** — propriété / flux **prod** ; `EF_GOOGLE_ANALYTICS_ID` ; URL du site dans GA4 ; test avec consentement cookies « analytics » |
| 6b | **Safe Browsing** — si Chrome affiche « Site dangereux » : Search Console → [demande d’examen](#alerte-chrome-site-dangereux-safe-browsing) (historique domaine, pas forcément le site actuel) |
| 7 | **Contact** — `CONTACT_WHATSAPP` + numéro réel (`wa.me`) |
| 7a | **Délivrabilité e-mail** — SPF + DKIM + DMARC ; mail-tester ≥ 8/10 |
| 7b | **PayPal Donate** — dashboard PayPal : remplacer les **URL de redirection** (retour / annulation) **locales** par les URLs **HTTPS du site déployé** ; lien : `templates/layout/_footer.html.twig` |
| 8 | **Test crawl invité** sur l’URL prod : `/`, `/about`, `/cgu`, `/mentions-legales` (pas de redirect login) |
| 9 | **Demande AdSense** sur `https://rapprofam.fr` | ☑ déposée — examen en cours |
| 10 | **Après approbation AdSense** — renseigner `EF_GOOGLE_ADSENSE_SLOT_*` · activer **CMP Google** (3 choix) · annonces sous consentement `marketing: true` |

Détail ligne par ligne : [docs/PRE_DEPLOY.md](docs/PRE_DEPLOY.md) (sections *Bloquant*, *Contenu & légal*, *Tests manuels*).

### À faire — relecture site (en cours)

| Priorité | Tâche | Statut |
|----------|--------|--------|
| 1 | **Messagerie** (privé + groupe + **photos groupe**) | ☑ livré juin 2026 |
| 2 | **Relecture page par page** | En cours — **Accueil** ☑ · **Hub Messages** ☑ · **Auth** ☑ · **Profil** ☑ · **Groupes** ☑ · **Événements** ☑ · **About** ☑ · **Contact** ☑ · **Invitations** ☑ · **Légal + 404** ☑ (juin 2026) · reste : **Admin EasyAdmin** |
| 3 | **Bugs** repérés lors de la relecture | — |
| 4 | **Perfs prod** — `APP_ENV=prod` + cache warmup | Avant déploiement |
| 5 | **Checklist prod** — exécuter [PRE_DEPLOY.md](docs/PRE_DEPLOY.md) | Juste avant mise en ligne |

**Synthèse relectures UX / i18n / accessibilité (juin 2026)** :

| Parcours | Statut | Détail |
|----------|--------|--------|
| [Accueil](#relecture--accueil-juin-2026) `/` | ☑ | Hero local, SEO invité, h1 unique, tutoiement FR |
| [Hub Messages](#relecture--hub-messages-juin-2026) | ☑ | h1 hub, aria-label cartes, titres sans doublon |
| [Auth](#relecture--auth-juin-2026) | ☑ | Illustrations locales, hiérarchie titres, SCSS |
| [Profil](#relecture--profil-juin-2026) | ☑ | Mon espace, avatar, MDP, suppression compte |
| [Groupes](#relecture--groupes-juin-2026) | ☑ | Liste, fiche, modération, invitations internes |
| [Événements](#relecture--événements-juin-2026) | ☑ | Liste, fiche, création / édition, modale |
| [About](#relecture--about-juin-2026) `/about` | ☑ | Titres, SEO, e-mail config, SCSS |
| [Contact](#relecture--contact-juin-2026) `/contact` | ☑ | Titres, formulaire, WhatsApp, SCSS |
| [Invitations](#relecture--invitations-juin-2026) `/invitations` | ☑ | Hub reçues + demandes staff, badges, sécurité |
| [Légal + 404](#relecture--légal--404-juin-2026) | ☑ | CGU RGPD, mentions LCEN, 404 ; `CONTACT_RECIPIENT` + `PUBLISHER_ADDRESS` |
| Admin EasyAdmin | — | *prochaine session* — contrôle rapide UX / i18n |

**Prochaine session** — ordre prévu :

1. **Admin EasyAdmin** — passage rapide UX / i18n (pas de refonte prévue)

**Pages restantes (relecture UX / i18n / accessibilité)** :

| # | Parcours | URL(s) principale(s) |
|---|----------|----------------------|
| 1 | **Admin EasyAdmin** | `/%EF_ADMIN_PATH%/` — vérif. rapide |

**Livré (juin 2026 — messagerie & contact)** : fil MP unique, e-mails notif MP (opt-out, délivrabilité), accusés de lecture, rate limits MP/groupe, WhatsApp `wa.me`, formulaire contact sans carte e-mail redondante.

**Livré (juin 2026 — navigation)** : voile blanc / Turbo, JS centralisé, sync thème site ↔ admin, CSRF admin.

**Livré (juin 2026 — légal)** : CGU RGPD complètes, mentions LCEN (adresse éditeur, projet perso, objet familial), page 404 ; config `PUBLISHER_ADDRESS` + `CONTACT_RECIPIENT`.

### Relecture — Accueil (juin 2026)

Parcours `/` (invité + connecté) — corrections SEO, accessibilité et assets (vitrine AdSense déjà ☑).

| Zone | Détail |
|------|--------|
| **Hero** | Image locale `assets/images/home/hero.jpg` — plus d’URL Unsplash externe |
| **SEO invité** | `meta description` + balises Open Graph (`ui.home.guest_meta_description`) |
| **Accessibilité** | Un seul `<h1>` par page (hero) ; topbar en `<p>` ; `alt` = titre sur images cartes événements |
| **UX connecté** | Cartes features → liens groupes / événements / messages ; libellé « Événements publics à venir » |
| **i18n FR** | Tutoiement unifié sur la vitrine invité |

| Fichier | Rôle |
|---------|------|
| `templates/home/index.html.twig` | Branche invité / connecté ; blocs SEO |
| `templates/layout/_topbar.html.twig` | Titre contexte en `<p>` |
| `assets/images/home/hero.jpg` | Photo hero |
| `assets/styles/pages/_home.scss` | Hero, cartes `.ef-card--link` |
| `translations/messages.{fr,en}.yaml` → `ui.home.*` | Textes vitrine invité |

**Volontairement non retenu** : liste d’événements sur la home invité (mur de connexion AdSense).

### Relecture — Hub Messages (juin 2026)

Parcours `/messages` (hub), `/messages/prives` et `/messages/groupe` — corrections UX, i18n et accessibilité (revue fonctionnelle MP/groupe déjà ☑).

| Zone | Détail |
|------|--------|
| **Hub `/messages`** | `<h1>` « Espace Messages » ; cartes Privé / Groupe avec `aria-label` (compteur non-lu) ; pastilles et icônes décoratives en `aria-hidden` |
| **Privé / Groupe** | Titre uniquement en topbar (suppression du `<h2>` dupliqué) ; badge total de fils sans paramètre `%count%` erroné |
| **Avatars fils** | Portrait **42 px** dans chaque carte MP et groupe (photo ou icône selon visibilité profil) ; layout `item-row` sans débordement (`min-w-0`, `text-truncate`) |
| **Mobile** | Badge compteur de fils : plus d’étirement pleine largeur (`ef-messages__threads-badge`, `align-items-start` sur en-têtes privé / groupe) |
| **i18n FR** | Tutoiement aligné (sous-titres hub, fils MP, placeholders, flashs `hidden_private` / `thread_closed`) |
| **SCSS** | Suppression du bloc mort `ef-messages__tabs` ; styles avatar isolés (`components/_messages-avatar.scss`) |

| Fichier | Rôle |
|---------|------|
| `templates/messages/index.html.twig` | h1 hub, `aria-label` liens, hiérarchie titres cartes (`h2`) |
| `templates/messages/_thread.html.twig` | Cartes fil + réponses avec avatar et contenu flex |
| `templates/messages/_user_avatar.html.twig` | Partial avatar compact (taille + styles inline de secours) |
| `templates/messages/private.html.twig` | En-tête sans titre dupliqué ; badge compact mobile ; lien alertes e-mail `ms-md-auto` |
| `templates/messages/group.html.twig` | Idem en-tête + toolbar groupe (`ms-md-auto`) ; sélecteur ⋮ |
| `assets/styles/components/_messages-avatar.scss` | Taille et fallback icône (fichier annulable à part) |
| `assets/styles/pages/_messages.scss` | `__threads-badge`, `__item-content` ; `@use` avatar |
| `translations/messages.{fr,en}.yaml` → `ui.messages.hub.*` | Libellés accessibles cartes hub (FR + EN) |

**Annuler les avatars dans les fils** : supprimer `_user_avatar.html.twig`, retirer les blocs avatar dans `_thread.html.twig`, supprimer `components/_messages-avatar.scss` et le `@use` dans `_messages.scss`.

**Volontairement non retenu** : refonte du sélecteur groupe (⋮) ; pagination réponses groupe (v2) ; meta SEO sur pages `ROLE_USER`.

### Relecture — Auth (juin 2026)

Parcours `/login`, `/register`, `/connect/google`, finalisation OAuth, `/forgot-password`, `/reset-password` — corrections UX, i18n, accessibilité et assets.

| Zone | Détail |
|------|--------|
| **Illustrations** | Images login / inscription hébergées localement (`assets/images/auth/`) — plus de CDN MDB externe ; décoratives (`role="presentation"`) |
| **Hiérarchie titres** | `<h1>` unique par page (formulaire) ; panneau renvoi e-mail d'activation en `<h2 class="h6">` |
| **Reset MDP** | Lien « Retour à la connexion » ; libellés champs via `ui.auth.field.*` (plus de clés profil) |
| **OAuth finalisation** | Labels `fw-semibold` alignés inscription ; colonne `ef-signUp__column` (largeur cohérente) |
| **Layout** | Classe layout (`ef-signIn` / `ef-signUp`) sur `<body>` uniquement — plus de doublon sur le wrapper interne |
| **i18n FR** | Tutoiement cookie `REMEMBERME` (bandeau cookies) |
| **SCSS** | Illustrations + padding carte dans `_auth.scss` ; fusion layout sign-in / sign-up ; retrait styles dupliqués (boutons, champs, divider) |

| Fichier | Rôle |
|---------|------|
| `templates/security/login.html.twig` | Illustration locale ; h2 renvoi activation ; wrapper simplifié |
| `templates/registration/register.html.twig` | Illustration locale ; `ef-signUp__column` |
| `templates/security/google_oauth_complete.html.twig` | Labels ; colonne responsive |
| `templates/security/reset_password.html.twig` | Lien retour connexion |
| `src/Form/ResetPasswordFormType.php` | Libellés auth |
| `assets/images/auth/login.svg`, `register.webp` | Illustrations auth |
| `assets/styles/layout/_auth.scss` | `.ef-auth__illustration`, padding carte |
| `assets/styles/pages/_sign-in.scss` | Layout partagé ; panneau renvoi ; retrait duplication |
| `translations/messages.fr.yaml` → `ui.consent.cookies.remember` | Tutoiement FR |

**Volontairement non retenu** : meta description / Open Graph sur pages auth (non indexées) ; bascule locale sur layout auth (v2) ; clés `illustration_alt` conservées mais inutilisées.

### Relecture — Profil (juin 2026)

Parcours Mon espace (`/profil`), profil membre, avatar, changement MDP, suppression compte — corrections UX, accessibilité et SCSS.

| Zone | Détail |
|------|--------|
| **Hiérarchie titres** | `<h1>` dans `_header` (Mon espace / profil membre) ; sous-pages MDP / suppression : titre unique en topbar + `<h1 class="visually-hidden">` ; blocage suppression en `<h1>` visible |
| **Titres dupliqués** | Suppression des `<h2>` redondants sur changement MDP et suppression compte (aligné Hub Messages) |
| **Champs lecture seule** | Légendes en `<span>` (plus de `<label>` sans `for` sur pseudo, e-mail, profil public) |
| **Sections Mon espace** | Notifications, Sécurité, Zone dangereuse, Avatar en `<h2 class="h6">` |
| **Accessibilité** | `aria-label` bouton MDP (« Modifier » générique) ; textarea MP profil ; retour arrière suppression compte ; image crop décorative |
| **SCSS** | Retrait `.ef-icon-box` / `.ef-readonly-input` (morts, doublon home) ; mode sombre encart notifications |

| Fichier | Rôle |
|---------|------|
| `templates/profile/_header.html.twig` | `h2` → `h1.h4` |
| `templates/profile/edit.html.twig` | Sections `h2` ; légendes statiques ; `aria-label` MDP |
| `templates/profile/show.html.twig` | Légendes statiques ; `aria-label` textarea MP |
| `templates/profile/change_password.html.twig` | Titre unique topbar ; `h1` SR-only ; retour `aria-label` |
| `templates/profile/delete_account.html.twig` | Idem ; retour accessible |
| `templates/profile/delete_account_blocked.html.twig` | `h2` → `h1` |
| `templates/profile/_avatar_manager.html.twig` | Titre section `h2` ; crop `role="presentation"` |
| `assets/styles/pages/_profile.scss` | Nettoyage + dark notifications |
| `translations/messages.{fr,en}.yaml` → `ui.profile.edit_password_aria`, `show.message_label` | Libellés accessibles |

**Volontairement non retenu** : hébergement local Cropper.js (CDN conservé, chargement dynamique) ; refonte successeur groupe (module Groupes v2).

### Relecture — Groupes (juin 2026)

Parcours liste, fiche groupe, création / édition, membres (modération), demandes d'adhésion et invitations internes.

| Zone | Détail |
|------|--------|
| **Hiérarchie titres** | `<h1 class="visually-hidden">` par page (liste, fiche, formulaires, demandes, invite) ; sections en `<h2>` / `<h3>` ; nom du groupe en topbar sans `<h2>` dupliqué sur la fiche |
| **Cartes liste** | `aria-label` sur lien carte (`%name%` + effectif) ; badge chef et compteur décoratifs en `aria-hidden` |
| **Fiche groupe** | Lien « Gérer les demandes » avec `aria-label` si badge ; icônes décoratives ; sticky sidebar via SCSS (plus de `style` inline) |
| **Membres / MP rapide** | Menu ⋮ `aria-label` contextualisé ; champ message avec `aria-label` |
| **Page inviter** (`/groupes/{id}/inviter`) | Barre recherche pleine largeur ; padding input/bouton alignés ; `gap-2` ; anti-scroll horizontal (`min-width: 0`) ; focus via `ef-input` |
| **Invitation interne** | Label accessible sur champ recherche |
| **i18n FR** | Tutoiement placeholder message membre (`Votre` → `Écris ton`) |
| **SCSS** | Retrait blocs morts `ef-groups-preview` / `ef-groups-modal` ; styles `__family-text`, `__group-name`, `__info-card` dans `_group-show.scss` |

| Fichier | Rôle |
|---------|------|
| `templates/groups/index.html.twig` | h1 SR-only ; icônes décoratives |
| `templates/groups/show.html.twig` | h1 SR-only ; nom groupe en `<p>` ; aria demandes |
| `templates/groups/new.html.twig`, `edit.html.twig` | Titre unique (SR-only + visuel) |
| `templates/groups/manage_requests.html.twig`, `invite.html.twig` | h1 SR-only ; label recherche |
| `templates/groups/_group_card.html.twig` | `aria-label` carte |
| `templates/groups/_members_table.html.twig` | aria menu / message |
| `assets/styles/pages/_groups.scss` | Nettoyage modal preview mort |
| `assets/styles/pages/_group-show.scss` | Sidebar sticky ; family / group name ; barre recherche invite (`__invite-search`) |
| `translations/messages.{fr,en}.yaml` → `ui.groups.*` | Clés aria + tutoiement FR |

**Volontairement non retenu** : refonte typo CSS `card-famyliName` ; module successeur chef de groupe (v2).

### Relecture — Événements (juin 2026)

Parcours liste, fiche, création / édition et modale aperçu.

| Zone | Détail |
|------|--------|
| **Hiérarchie titres** | `<h1 class="visually-hidden">` par page (liste, fiche, formulaires) ; titre visible fiche en `<p class="h3">` (plus de doublon topbar + `<h1>`) ; formulaires : SR-only + titre visuel en `<p class="h4">` |
| **Liste / modale** | `aria-label` sur le bouton aperçu carte ; icônes décoratives ; modale et suppression avec titres `<h2>` / `<h3>` |
| **Fiche événement** | Sticky sidebar via SCSS (plus de `style` inline) ; icônes meta décoratives |
| **Formulaire** | Bouton annuler stylé (`ef-events-form__btn-cancel`) ; `<select>` natifs — fond / texte cohérents en mode sombre (surbrillance bleue OS à l’ouverture, non personnalisable proprement) |
| **i18n FR** | Tutoiement confirmation suppression modale ; clé `card_modal_aria` |
| **SCSS** | Consolidation fiche dans `_event-show.scss` ; mode sombre filtres, badges, modale, carte formulaire ; `form-select` sombre sur `.ef-input` |

| Fichier | Rôle |
|---------|------|
| `templates/events/index.html.twig` | h1 SR-only ; icône bouton créer |
| `templates/events/show.html.twig` | h1 SR-only ; titre visible ; sidebar sticky SCSS |
| `templates/events/new.html.twig`, `edit.html.twig` | Titre unique (SR-only + visuel) |
| `templates/events/_article_card.html.twig` | `aria-label` aperçu modale |
| `assets/styles/pages/_events.scss` | Formulaire, modale, filtres, nettoyage mort |
| `assets/styles/pages/_event-show.scss` | Fiche, sidebar, meta |
| `assets/styles/pages/_profile.scss` | `form-select` sombre global `.ef-input` |
| `translations/messages.{fr,en}.yaml` → `ui.events.*` | Clés aria + tutoiement FR |

**Volontairement non retenu** : refonte bouton « Créer » liste (style `btn-save-profile` groupes) ; nested modale suppression empilée (comportement Bootstrap conservé) ; remplacement des `<select>` par menus Bootstrap custom (régression fermeture des dropdowns — selects natifs conservés).

### Relecture — About (juin 2026)

Parcours `/about` (public invité + connecté) — corrections UX, SEO, accessibilité et SCSS.

| Zone | Détail |
|------|--------|
| **Hiérarchie titres** | `<h1 class="visually-hidden">` ; topbar seule pour le titre page ; sections en `<h2>` ; cartes features en `<h3>` |
| **Titres dupliqués** | Suppression du `<h2>` « À propos » redondant avec la topbar ; sous-titre seul en intro |
| **SEO** | `meta description` (`ui.about.meta_description`) |
| **E-mail public** | `ef_contact_recipient` (env `CONTACT_RECIPIENT`) — plus d’adresse en dur dans le template |
| **Layout** | `ef-main-content-padding` + conteneur `ef-about__inner` (aligné groupes / événements) |
| **SCSS** | Consolidation BEM (`__feature-card`, `__intro-card`) ; mode sombre cartes intro + features |

| Fichier | Rôle |
|---------|------|
| `templates/about/index.html.twig` | h1 SR-only ; hiérarchie titres ; e-mail via global Twig |
| `config/packages/ef_contact.yaml`, `twig.yaml` | Paramètre + global `ef_contact_recipient` |
| `assets/styles/pages/_about.scss` | Layout simplifié, cartes unifiées |
| `translations/messages.{fr,en}.yaml` → `ui.about.meta_description` | SEO FR + EN |

**Volontairement non retenu** : Open Graph dédié (meta description suffit pour l’instant).

### Relecture — Contact (juin 2026)

Parcours `/contact` (membres connectés) — corrections UX, accessibilité et SCSS.

| Zone | Détail |
|------|--------|
| **Hiérarchie titres** | `<h1 class="visually-hidden">` « Contact » ; `<h2>` « Nous contacter » ; carte formulaire / WhatsApp en `<h3>` |
| **Layout** | `ef-main-content-padding` + `ef-contact__inner` ; suppression du double padding `p-4` |
| **Formulaire** | Bouton envoi via mixin `custom-btn` (`ef-contact__submit`) ; nom / e-mail affichés en `<span>` + `<p>` (plus de faux champs readonly) |
| **WhatsApp** | Carte lien unifiée (`ef-contact__whatsapp-link`) ; typo `adresses` corrigée |
| **SCSS** | Variables projet ; mode sombre cartes ; mixins importés |

| Fichier | Rôle |
|---------|------|
| `templates/contact/index.html.twig` | h1 SR-only ; hiérarchie titres ; layout harmonisé |
| `assets/styles/pages/_contact.scss` | Cartes, bouton, WhatsApp, dark mode |

**Volontairement non retenu** : meta SEO (page `ROLE_USER` non indexée).

### Relecture — Invitations (juin 2026)

Parcours `/invitations` — hub global + deux flux métier (`GroupRequest`).

| Zone | Détail |
|------|--------|
| **Mes invitations reçues** | `GroupRequestStatus::Invited` — chef/mod invite un membre ; accepter/refuser avec CSRF + retour hub |
| **Demandes d'adhésion** | `GroupRequestStatus::Pending` — membre demande à rejoindre ; visible si `isStaffAnywhere` |
| **Badges** | Compteurs non lus via AJAX `/invitations/api/compteurs` ; marquage lu à l'ouverture du hub |
| **UX** | `h1` SR-only ; `aria-hidden` icônes ; badges de comptage ; avatars demandeurs ; `familyName` conditionnel |
| **Sécurité** | `ROLE_USER` ; IDOR bloqué (`assertInvitationForUser`, `assertStaffCanHandle`) ; JSON API réservée au polling (`Accept` / `X-Requested-With`) ; blocage invitation compte suspendu site (`target_suspended`) |
| **Notifications** | In-app uniquement (badges, hub, fiche groupe) — **pas d’e-mail** à la réception d’invitation (prévu v2) |

| Fichier | Rôle |
|---------|------|
| `src/Controller/InvitationController.php` | Hub + API compteurs |
| `src/Service/GroupRequestService.php` | Logique invitations / demandes ; garde suspension site à l’invite |
| `templates/invitations/index.html.twig` | Deux sections du hub |
| `templates/groups/invite.html.twig` | Recherche + parcours inviter (i18n, barre recherche) |
| `src/Controller/GroupModerationController.php` | Actions POST (accepter/refuser/inviter) |
| `assets/styles/pages/_group-show.scss` | Styles barre recherche invite |

**Volontairement non retenu** : contrainte SQL anti-doublon `Invited`/`Pending` (race double-clic rare) ; SCSS hub dédié (réutilise styles groupe) ; e-mail notification invitation (v2).

### Relecture — Légal + 404 (juin 2026)

Parcours `/cgu`, `/mentions-legales` (public invité + connecté) et page **404** (membre connecté, URL inexistante).

| Zone | Détail |
|------|--------|
| **Sécurité** | Routes `GET` uniquement ; `PUBLIC_ACCESS` sur `/cgu` et `/mentions-legales` ; pas de données utilisateur exposées |
| **E-mail éditeur** | `ef_contact_recipient` (`CONTACT_RECIPIENT`) — plus d’adresse en dur dans les templates (aligné About) |
| **Adresse éditeur** | `ef_publisher_address` (`PUBLISHER_ADDRESS`) — mentions + responsable du traitement RGPD |
| **Contenu juridique** | RGPD enrichi (finalités, bases légales, tiers Google/SMTP/PayPal, cookies, droits, CNIL) ; liste de données alignée sur le site ; projet perso non commercial |
| **Mentions — éditeur** | Noms éditeur / directeur via `legal.mentions.editor.*_name` (FR + EN) |
| **SEO public** | `meta description` sur CGU et mentions (`ui.legal.*_meta_description`) |
| **Accessibilité** | Sections avec `aria-labelledby` ; icônes décoratives en `aria-hidden` ; 404 avec `<main>` + `h1` unique |
| **404 — actions** | Accueil toujours ; **Contact** si connecté (`ROLE_USER`) ; **À propos** si prévisualisation dev invité (`/_error/404`) |
| **SCSS** | Sélecteur unique `.ef-legal-page` (retrait `.ef-cgu-page` mort) ; bouton retour mentions harmonisé |

| Fichier | Rôle |
|---------|------|
| `src/Controller/LegalController.php` | CGU + mentions (GET) |
| `templates/legal/*.html.twig`, `layout/legal.html.twig` | Contenu + meta SEO |
| `templates/bundles/TwigBundle/Exception/error404.html.twig` | Page 404 personnalisée |
| `assets/styles/pages/_legal.scss`, `_error.scss` | Styles clair / sombre |
| `config/packages/ef_publisher.yaml`, `ef_contact.yaml`, `twig.yaml` | Adresse + e-mail éditeur configurables |
| `translations/messages.{fr,en}.yaml` → `legal.*`, `ui.legal.*`, `ui.error.not_found.*` | Contenu juridique FR + EN |

**Volontairement non retenu** : tutoiement dans le corps juridique (`legal.*` reste au vouvoiement) ; placeholders hébergeur (PRE_DEPLOY avant prod) ; Open Graph dédié légal.

### Accueil public & AdSense (référence — livré)

> Détail relecture UX / accessibilité : [Relecture — Accueil (juin 2026)](#relecture--accueil-juin-2026).

#### Livré — vitrine invité (juin 2026)

Réponse au mur de connexion sur `/` (bloquant l’examen AdSense). Comportement actuel :

| Zone | Invité (non connecté) | Membre connecté |
|------|------------------------|-----------------|
| **URLs publiques** | `/`, `/about`, `/cgu`, `/mentions-legales`, `/locale/switch`, auth | Inchangé |
| **Reste du site** | → **login** | Accès complet |
| **Home `/`** | Hero (image locale) + 3 cartes + « Comment ça marche » (3 étapes) + encart espace privé + CTA inscription/connexion + lien about — **sans** liste d’événements ; **meta description** + Open Graph | Hero + cartes **cliquables** (groupes / événements / messages) + aperçu **3 événements publics** |
| **Sidebar** | Accueil + lien **À propos** ; dropup connexion / inscription / langue (pas de lien Contact — page réservée aux connectés) | Sidebar complète |
| **Footer** | CGU + mentions + copyright (+ cookies si choix fait) | Footer complet (dont **Offrir un coup de pouce** → PayPal) |
| **Topbar** | Titre contexte (`<p>`, pas de h1) + thème uniquement | Recherche + notifications + thème |

**Fichiers concernés** :

| Fichier | Rôle |
|---------|------|
| `config/packages/security.yaml` | `PUBLIC_ACCESS` sur `^/$`, `^/about`, `^/locale/switch` |
| `src/Controller/HomeController.php` | `findUpcomingPublic()` si connecté uniquement |
| `templates/home/index.html.twig` | Branche `app.user` / vitrine `ef-home-guest` ; blocs SEO invité |
| `templates/layout/_sidebar.html.twig` | Mode invité (`ef-sidebar--guest`) |
| `templates/layout/_footer.html.twig` | `ef-footer--guest` |
| `templates/layout/_topbar.html.twig` | Masque recherche / notifications si invité ; titre en `<p>` (un seul `<h1>` = hero accueil) |
| `templates/base.html.twig` | Pas de panneau recherche pour invité ; blocs `meta_description` / `meta_og` |
| `assets/images/home/hero.jpg` | Photo hero accueil (hébergée localement, plus d’URL Unsplash externe) |
| `assets/styles/pages/_home.scss` | Hero, vitrine invité, cartes `.ef-card--link` (clair / sombre) |
| `translations/messages.{fr,en}.yaml` → `ui.home.*` | Textes vitrine invité en **tutoiement** FR ; `guest_meta_description` ; section connectée « Événements publics à venir » |

**Volontairement exclus** : événements, groupes, messages, contact (formulaire), admin, fausses données publiques.

**Test manuel** : navigation privée sur `/` et `/about` — pas de redirection `/login`. Après modif SCSS : `php bin/console sass:build` ou `npm run sass:watch`, puis rechargement forcé du navigateur.

#### AdSense — intégration prod (juin 2026)

| Élément | État |
|---------|------|
| Balise vérification `<head>` (`adsbygoogle.js`) | ☑ prod |
| `https://rapprofam.fr/ads.txt` | ☑ route Symfony + fichier `public/ads.txt` |
| Emplacements discrets (accueil, événements, groupes, about, hub messages) | ☑ code · slots vides tant qu’aucune unité AdSense créée |
| Consentement bandeau maison (`marketing`) | ☑ |
| Demande d’examen Google | ☑ en cours |
| **CMP Google certifiée** (EEE / UK / CH) | ☐ **en attente** — après approbation (option 3 choix dans AdSense) |
| Unités publicitaires (`EF_GOOGLE_ADSENSE_SLOT_HOME`, `_EVENTS`, etc.) | ☐ après approbation |

| Fichier / config | Rôle |
|------------------|------|
| `config/packages/ef_adsense.yaml` | Client ID + slots par page |
| `templates/components/_ef_adsense_head.html.twig` | Script `<head>` |
| `templates/components/_ef_adsense_unit.html.twig` | Bloc `<ins class="adsbygoogle">` |
| `assets/js/ef-adsense.js` | Chargement si consentement marketing |
| `src/Controller/LegalController.php` | Route `/ads.txt` |
| `public/ads.txt` | Ligne `google.com, pub-…` |
| `bin/deploy.ps1` | Deploy complet o2switch : build assets PC → push → code serveur → scp `public/assets/` → cache prod ; vérifie `DEPLOY_COMMIT` |

**Désactiver une page** : laisser le `EF_GOOGLE_ADSENSE_SLOT_*` correspondant vide dans `.env` / `.env.local` serveur.

#### Livré — enrichissement `/about` (juin 2026)

Sections ajoutées (FR + EN, `ui.about.*`) : public visé, fonctionnalités membres (liste), confidentialité, transparence (pas d’annuaire public), contact/éditeur (mentions + e-mail), CTA inscription/connexion pour invités.

| Fichier | Rôle |
|---------|------|
| `templates/about/index.html.twig` | Nouvelles sections + CTA invité |
| `translations/messages.{fr,en}.yaml` | Clés `audience_*`, `features_member_*`, `privacy_*`, `transparency_*`, `contact_public_*`, `guest_cta_*` |
| `assets/styles/pages/_about.scss` | Styles sections, encart, liens, CTA (clair / sombre) |

#### Critères AdSense — état (juin 2026)

| Critère | État |
|---------|------|
| Site sans login sur `/`, `/about`, légal | ☑ prod |
| Contenu original (home + about) | ☑ |
| CGU / confidentialité (mention AdSense) | ☑ |
| Balise + `ads.txt` sur HTTPS | ☑ prod |
| Demande d’examen | ☑ en cours |
| CMP Google (EEE / UK / CH) | ☐ après approbation |
| Slots publicitaires | ☐ après création des unités dans AdSense |

### En attente — AdSense (examen Google)

| Sujet | État | Quand |
|-------|------|--------|
| **Réponse examen AdSense** | En cours | Google (quelques jours à quelques semaines) |
| **CMP Google** (3 choix) | À configurer | **Après** approbation — ne pas activer avant |
| **`EF_GOOGLE_ADSENSE_SLOT_*`** | Vides | Créer les unités dans AdSense après approbation |

### En attente — hors AdSense

| Sujet | État | Quand |
|-------|------|--------|
| **reCAPTCHA / hébergeur (mentions)** | Voir [PRE_DEPLOY](docs/PRE_DEPLOY.md) | Si pas encore fait en prod |
| **Google OAuth** | Backend livré | Clés et URI **prod** — [PRE_DEPLOY](docs/PRE_DEPLOY.md) |
| **WhatsApp API** | Lien `wa.me` v1 suffit | API Meta → v2 |

### Court terme (hors prod / AdSense)

1. **Modifs vitrine** — pages publiques / UX avant déploiement (session en cours)
2. **Performances** — valider en mode prod local (section ci-dessus)
3. **Session admin / CSRF** — re-test inactivité longue + formulaire après pause avant prod
4. **Fonctionnalités métier** — selon priorités produit (v2 ci-dessous)

### v2 — Contact & intégrations (planifié)

- **WhatsApp Business API** : réception/envoi de messages depuis le site (webhooks Meta — compte Business, numéro dédié, **clés API**, facturation). Distinct du simple lien `wa.me` (v1, sans clé).
### v2 — Événements (planifié)

- **RSVP** : participation (oui / non / peut-être), compteurs, notifications
- **Recherche avancée** : filtre par groupe, dates, type d'événement, description

> Recherche simple (topbar → `/evenements?q=`, titre + lieu) : **livré** — voir section Événements.

### v2 — Messagerie (reporté)

- **Web Push** notifications
- **E-mails** nouveaux messages de groupe
- **Pagination réponses** groupe (fil très long)
- Masquage « de mon côté » pour les **messages de groupe** (au lieu du hard delete auteur)
- Téléchargement photo **opt-in** par l’auteur (au-delà de la lightbox consultation seule)

### Autres

1. OAuth Google + reCAPTCHA + Analytics — **reconfig prod** (voir [Services Google](#services-google-dev-ok--prod-à-reconfigurer))
2. WhatsApp **v2** (API Meta) — v1 `wa.me` livré
3. Modales Turbo · Tarteaucitron
4. **i18n** — couverture FR/EN complète ☑ ; contenu juridique enrichi ☑ (relecture avocat optionnelle avant prod)
5. Tests automatisés (PHPUnit)

## Changelog

### 2026-06-11 — Google AdSense + deploy fiable

- **AdSense** : balise `<head>`, `ads.txt`, emplacements discrets (accueil, événements, groupes, about, messages index) ; consentement marketing ; `EF_GOOGLE_ADSENSE_CLIENT_ID` en prod ; demande d’examen déposée
- **En attente** : CMP Google certifiée (3 choix) et `EF_GOOGLE_ADSENSE_SLOT_*` — **après** approbation Google
- **Cookies** : textes bandeau / CGU AdSense à jour ; catalogue cookies marketing (`__gads`, `IDE`)
- **Deploy** : `deploy.ps1` — une commande (`ASSETS_SOURCE=pc` dans `deploy.config`) ; bloque si fichiers non commités ; vérifie `DEPLOY_COMMIT`

### 2026-06-11 — SMTP o2switch + Safe Browsing Google

- **E-mails** : compte `rf_contact@rapprofam.fr` — SMTP o2switch (`mail.rapprofam.fr:465`, `smtps://`) ; `MAILER_FROM`, `CONTACT_RECIPIENT`, `MODERATION_CONTACT` alignés dans `.env` / `.env.local` / [GUIDE_COMMANDES](docs/GUIDE_COMMANDES_RAPPROFAM.md)
- **Safe Browsing** : section README — alerte Chrome « Site dangereux » (réputation domaine héritée possible) ; demande d’examen Search Console déposée ; distinct d’AdSense / reCAPTCHA
- **README** : section [Mailer](#mailer-smtp-o2switch) détaillée ; retrait références Ethereal / `admin@rapprofam.fr`

### 2026-06-09 — Photos messages de groupe + limites PlanetHoster

- **Photos groupe** : 0–2 par message racine ; upload 3 Mo → WebP 1200 px ; recadrage optionnel (Cropper.js) ; lightbox in-page **sans téléchargement**
- **Confidentialité** : avertissement à l’import (formulaire + modal recadrage) ; pas de route `/telecharger`
- **Anti-spam** : rate limit photos **6/h** + **20/j** ; messages groupe **15/h** ; MP **20/h** (profil mutualisé PlanetHoster The World)
- **Entité** : `MessagePhoto` (`ef_message_photos`) ; stockage `var/storage/message-photos/` ; purge fichiers avec messages
- **JS** : `ef-group-message-photos.js`, `ef-message-photo-lightbox.js` ; config `ef_message_photos.yaml`
- **README** : section photos messagerie + hébergement PlanetHoster

### 2026-06-08 — Enrichissement juridique CGU & mentions

- **Contenu** : politique RGPD détaillée (données réelles, finalités, bases légales, Google OAuth/reCAPTCHA/Analytics/AdSense, SMTP, PayPal, cookies, durées, droits, CNIL, transferts UE)
- **CGU** : périmètre familial, modération, projet perso non commercial, modification des CGU, droit français
- **Mentions** : adresse postale éditeur, statut projet passion, section objet du site ; numérotation hébergeur (§3) / PI (§4)
- **Config** : `PUBLISHER_ADDRESS` → global Twig `ef_publisher_address` (défaut Lagord ; modifiable sans code)
- **Correction** : retrait de la « ville de résidence » (donnée inexistante)
- **README** : section [Pages légales](#pages-légales-livré--juin-2026) ; variables `PUBLISHER_ADDRESS` ; synthèse déploiement mise à jour

### 2026-06-08 — Relecture Légal + 404

- **CGU / mentions** : e-mail via `ef_contact_recipient` ; noms éditeur en traductions ; `meta description` SEO public ; sections `aria-labelledby`
- **404** : lien Contact réservé aux connectés ; lien About en prévisualisation dev invité ; clé `ui.error.not_found.about`
- **SCSS** : retrait sélecteur mort `.ef-cgu-page`
- **README** : section relecture Légal + 404 ; prochaine session : Admin EasyAdmin

### 2026-06-08 — Relecture About, Contact, Invitations

- **About** : h1 SR-only ; suppression titre dupliqué topbar ; meta description ; e-mail via `ef_contact_recipient` ; layout `ef-main-content-padding` ; SCSS consolidé
- **Contact** : h1 SR-only ; hiérarchie h2/h3 ; layout harmonisé ; identité membre en texte statique (aligné profil) ; bouton + cartes mode sombre
- **Invitations** (`/invitations`) : hub reçues + demandes staff ; badges, avatars, i18n ; sécurité IDOR/CSRF ; blocage invite compte suspendu site
- **Page inviter** (`/groupes/{id}/inviter`) : barre recherche compacte (padding aligné input/bouton, `gap-2`, sans scroll horizontal) ; focus `ef-input`
- **Config** : global Twig `ef_contact_recipient` (`CONTACT_RECIPIENT`)
- **README** : sections relecture About / Contact / Invitations ; prochaine session : Légal + 404 → Admin

### 2026-06-07 — Relecture Événements

- **Titres** : `<h1>` SR-only par page ; fin du `<h1>` titre dupliqué sur la fiche (topbar + corps)
- **Accessibilité** : `aria-label` bouton aperçu modale sur cartes liste ; icônes décoratives
- **i18n FR** : tutoiement confirmation suppression modale
- **SCSS** : mode sombre selects (`.ef-input.form-select`), modale, filtres, formulaire ; sticky sidebar fiche ; retrait blocs morts `ef-event__*`
- **Non retenu** : select Bootstrap custom (menus restés ouverts) — `<select>` natifs conservés

### 2026-06-07 — Relecture Groupes

- **Titres** : `<h1>` SR-only par page ; fin du `<h2>` nom du groupe dupliqué (topbar + fiche)
- **Accessibilité** : `aria-label` cartes liste, lien demandes en attente, menu membre, recherche invite, MP rapide
- **i18n FR** : tutoiement placeholder message membre
- **SCSS** : retrait `ef-groups-preview` / `ef-groups-modal` morts ; sticky sidebar et styles fiche dans `_group-show.scss`

### 2026-06-07 — Relecture Profil

- **Titres** : `<h1>` dans l'en-tête profil ; sous-pages MDP / suppression sans doublon (topbar + SR-only) ; blocage suppression en `<h1>` visible
- **Accessibilité** : légendes statiques (plus de faux `<label>`) ; sections Mon espace en `<h2>` ; `aria-label` MDP et textarea MP
- **Avatar** : titre section en `<h2>` ; image crop décorative
- **SCSS** : retrait blocs morts `_profile.scss` ; mode sombre encart notifications

### 2026-06-07 — Relecture Auth

- **Illustrations** : login / inscription en local (`assets/images/auth/`) — fin du CDN MDB
- **Accessibilité** : `<h1>` par page ; panneau renvoi activation en `<h2>` ; illustrations décoratives
- **Reset MDP** : lien retour connexion ; libellés `ui.auth.field.*`
- **OAuth finalisation** : labels alignés inscription ; largeur colonne cohérente
- **SCSS** : fusion layout auth, retrait duplication boutons / champs dans `_sign-in.scss`
- **i18n FR** : tutoiement cookie `REMEMBERME`

### 2026-06-09 — Admin EasyAdmin : polish CRUD + validation staff

- **Utilisateurs** : badges rôles sur l'index (lecture seule) ; comptes soft-deleted masqués par défaut + filtre statut ; motif suspension visible à l'édition ; case suspension grisée selon droits ; e-mail déban (pied de page cohérent)
- **Politique staff** : `AdminUserPolicyService` centralisé ; smoke test modo / super-modo / admin validé (ban, déban, rôles, suppressions)
- **Messages** : recherche contenu ; booléens non cliquables ; fix enum `PlatformNoticeVariant` (champ virtuel + plus de warning `array_flip`)
- **Groupes / Événements / Bannissements** : filtres traduits ; index allégés (dates et colonnes secondaires en détail)
- **README** : tableau rubriques CRUD + statut validation admin à jour

### 2026-06-09 — Avatars messagerie + badge mobile

- **Fils MP / groupe** : avatar **42 px** dans chaque carte (racine + réponses) — `profile_avatar_visible()` ; partial `_user_avatar.html.twig` ; SCSS isolé `components/_messages-avatar.scss`
- **Mobile** : badge compteur de fils compact sur `/messages/prives` et `/messages/groupe` (`ef-messages__threads-badge`)
- **Dev** : rappel — si le CSS messagerie ne bouge pas, `composer assets:refresh` ou `php bin/console sass:build` (cache `var/sass/`)

### 2026-06-07 — Relecture Hub Messages

- **Hub** : `<h1>` Espace Messages ; `aria-label` sur cartes (compteur non-lu) ; clés `ui.messages.hub.link_*` (FR + EN)
- **Privé / Groupe** : titre unique en topbar ; correction attribut `title` des badges fils
- **i18n FR** : tutoiement messagerie (sous-titres, fils, flashs MP)
- **SCSS** : retrait du bloc mort `ef-messages__tabs`

### 2026-06-07 — Relecture page Accueil

- **Hero** : image locale `assets/images/home/hero.jpg` (SCSS `../images/home/hero.jpg` depuis `assets/styles/`)
- **SEO invité** : `meta description` + balises Open Graph (`ui.home.guest_meta_description`)
- **Accessibilité** : un seul `<h1>` par page (hero) ; topbar en `<p>` ; `alt` = titre sur images cartes événements
- **UX connecté** : cartes features → liens groupes / événements / messages ; libellé « Événements publics à venir »
- **i18n FR** : tutoiement unifié sur la vitrine invité (aligné avec le reste du site connecté)

### 2026-06-07 — Messagerie MP (fil unique, e-mails, lu) + contact WhatsApp

- **MP** : 1 fil actif par paire ; réponses illimitées + pagination (30 → 200) ; rate limit 30/h
- **Accusés de lecture** : « Lu le… » pour l'expéditeur
- **E-mail nouveau MP** : opt-out Mon espace → Notifications ; throttle 30 min ; texte + HTML + en-têtes délivrabilité
- **Groupe** : rate limit 25/h
- **Contact** : carte WhatsApp `wa.me` via `CONTACT_WHATSAPP` (carte e-mail redondante retirée)
- **PRE_DEPLOY** : tests MP (26b/26c), délivrabilité e-mail (33)

### 2026-06-05 — Navigation Turbo, thème admin, sidebar EA

- **Turbo** : suppression du garde-fou `ef-turbo-nav.js` (bloquait le rendu au retour navigateur et laissait la barre de progression / voile actifs) ; plus de scripts par page dans le `<head>` — modules importés dans `assets/app.js` uniquement
- **Voile blanc** : overlay de navigation retiré ; thème réappliqué sur `turbo:before-render` / `turbo:render` (`ef-theme-init.js`) + CSS critique `_ef_theme_critical.html.twig`
- **Thème admin** : sync `ef-theme` ↔ `ea/colorScheme` (`ef-admin-theme-sync.js`, `ef-layout.js`) ; préférence site respectée à l’ouverture de l’admin
- **Sidebar admin (sombre)** : fond actif EasyAdmin (`true-gray-300`) remplacé par surlignage discret + texte/icône orange (`ef-admin.scss`)
- **CSRF admin** : jetons rafraîchis à chaque ping d’activité ; `AdminCsrfExceptionSubscriber` + clé `admin.access.csrf_expired` (plus de page brute « Invalid CSRF token. »)
- **Cropper avatar** : chargement dynamique (plus de script CDN dans le `<head>` profil)
- **README** : section navigation Turbo à jour, tâches « flash blanc » archivées

### 2026-06-04 — Google OAuth + README déploiement

- **OAuth Google** : connexion / inscription, CGU obligatoires (écran finalisation), annulation, URI de redirection fixe (`GOOGLE_OAUTH_REDIRECT_URI`), commande `ef:google-oauth:diagnose`
- **README** : checklist avant déploiement (Analytics, reCAPTCHA, OAuth, CGU hébergeur, e-mails pro) ; section « prochaine session » (relecture, bugs, flash blanc)
- **PRE_DEPLOY** : aligné OAuth livré + rappels services Google prod

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

- **Turbo** : garde-fou clics rapides (`ef-turbo-nav.js` — **retiré le 2026-06-05**, voir changelog), voile de chargement, `turbo-cache-control: no-preview`
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
- **Titres CRUD** : nom de rubrique dynamique (Utilisateurs, Groupes…) ; tableau de bord « Administration RapproFam »
- **`MODERATION_CONTACT`** : adresse recours modération (défaut = contact)
- **Encart login** `?suspended=1` + lien mailto ; pas de formulaire public pour les suspendus
- **Bannissements admin** : historique lecture seule ; annonces staff / notices plateforme libellées « Administration RapproFam » ou « Système »

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
- Annonces **staff** site + notices plateforme (RapproFam / System)
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
