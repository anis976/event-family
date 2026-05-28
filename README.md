# EventFamily

Plateforme Symfony 8 de gestion d'événements familiaux.

## Stack

- **PHP** 8.4+ · **Symfony** 8.0
- **Twig** · **Asset Mapper** · **Hotwire Turbo** · **Stimulus**
- **Bootstrap** 5.3.8 (CDN) · **Bootstrap Icons** 1.11.3
- **SASS** (dart-sass via `npm`, compilé par [symfonycasts/sass-bundle](https://github.com/symfonycasts/sass-bundle))

## Démarrage

```bash
composer install
npm install
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

**Les styles ne se mettent pas à jour ?** Lance une seule commande (supprime `public/assets/`, recompile Sass, vide le cache) :

```bash
composer assets:refresh
```

Équivalent manuel sous PowerShell :

```powershell
Remove-Item -Recurse -Force public\assets
php bin/console sass:build
php bin/console cache:clear
```

Puis recharge avec **Ctrl+F5**. Le message `Executing Sass` au build est normal (ce n’est pas une erreur).

> **CSS** : chargé via `<link href="{{ asset('styles/app.scss') }}">` dans les layouts, compilé vers `var/sass/*.css`. Ne pas importer le SCSS depuis `app.js`.

Ouvre [http://localhost:8000/](http://localhost:8000/).

## Layout global (socle)

| Zone | Fichier Twig | Comportement |
|------|----------------|--------------|
| Base | `templates/base.html.twig` | Structure HTML, CDN, importmap, sidebar/topbar |
| Auth | `templates/layout/auth.html.twig` | Pages login/register **sans** sidebar (thème en coin) |
| Sidebar | `templates/layout/_sidebar.html.twig` | Fixe desktop · burger + overlay &lt; 992px |
| Topbar | `templates/layout/_topbar.html.twig` | Sticky, titre dynamique (`{% block page_title %}`) |
| Recherche | `templates/layout/_search_panel.html.twig` | Panneau repliable |
| Footer | `templates/layout/_footer.html.twig` | 3 / 2 / 1 colonnes (lg / md / xs) |

### Thème (clair / sombre / auto)

- Préférence stockée dans `localStorage` (`ef-theme`)
- Script anti-flash : `assets/js/ef-theme-init.js` (chargé dans `<head>`)
- Logique UI : `assets/js/ef-layout.js` (`turbo:load`, dropdowns Bootstrap, bouton Google « bientôt »)

### JavaScript

- Point d'entrée : `assets/app.js`
- Layout Turbo-safe : pas de double init + nettoyage sur `turbo:before-cache`

### Styles SCSS

```
assets/styles/
  app.scss              # entrée unique (@use uniquement, pas @import)
  base/                 # variables, mixins, globals
  layout/               # sidebar, topbar, auth, footer…
  components/           # back-to-top
  pages/                # home, about, contact, sign-up
```

- Classes préfixées **`ef_`** (BEM : `ef-sidebar__nav-link`)
- Chaque partial charge ses dépendances : `@use '../base/variables' as *;` + `mixins`
- Auth partagé : `.ef-auth-page` dans `layout/_auth.scss` (boutons, champs, checkbox)
- Inscription spécifique : `.ef-signUp` dans `pages/_sign-up.scss` (layout + illustration)

## Routes

| Route | Nom | Description |
|-------|-----|-------------|
| `/` | `app_home` | Accueil (démo layout) |
| `/about` | `app_about` | À propos |
| `/contact` | `app_contact` | Contact (formulaire UI, pas d’envoi backend) |
| `/register` | `app_register` | Inscription (formulaire Symfony → `ef_users`) |
| `/login` | `app_login` | Connexion (placeholder UI, maquette finale à intégrer) |
| `/logout` | `app_logout` | Déconnexion |

Liens sidebar (menu paramètres) : `app_login`, `app_register`, `app_logout`.

## Base de données (local Laragon)

- **`.env.local`** (non versionné) : `DATABASE_URL` → MySQL `ef_base`
- Tables préfixées **`ef_`** (ex. `ef_users`)

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
composer assets:refresh
```

### Entité `User` (`ef_users`)

| Contrainte | Détail |
|------------|--------|
| E-mail | obligatoire, unique |
| Prénom + nom | obligatoires, **couple unique** |
| Pseudo | optionnel, **non** unique |
| Rôles site | `ROLE_USER`, `ROLE_MODERATOR`, `ROLE_ADMIN` |
| Rôles groupe | enum `GroupMemberRole` — sur `GroupMember` (à venir) |

**Règle métier** : 1 groupe créé max par user, membre de plusieurs groupes (relations `Group` / `GroupMember` à venir).

## Authentification

| Fonctionnalité | État |
|----------------|------|
| Inscription `/register` | OK — `RegistrationFormType`, validation, hash mot de passe |
| Connexion `/login` | OK (Symfony Security) — **UI minimale** en attendant maquette HTML/SCSS |
| Layout auth dédié | OK — `auth.html.twig`, styles partagés, animations boutons |
| Google « S'inscrire avec Google » | Bouton UI + hover ; **OAuth à brancher avant déploiement** |
| Google « Se connecter avec Google » | **À mettre en place avant déploiement** (même stack OAuth) |
| E-mail (vérif / reset) | En attente `MAILER_DSN` (Ethereal en dev, SMTP en prod) |
| Page profil | Plus tard |

> **OAuth Google** : prévu avant mise en production (inscription + connexion). En dev, le bouton inscription est en `aria-disabled` avec `data-ef-google-soon` (animation hover conservée, clic bloqué).

### Security (`config/packages/security.yaml`)

- Provider Doctrine sur `email`
- `form_login` → `/login`
- Accès public : `/login`, `/register`

## Déploiement (checklist)

| Variable | Action |
|----------|--------|
| `APP_SECRET` | Nouvelle valeur aléatoire **unique** (64 caractères hex) |
| `DATABASE_URL` | URL hébergeur (MySQL / MariaDB) |
| `MAILER_DSN` | SMTP production |
| `APP_ENV` | `prod` |
| **OAuth Google** | Client ID / secret + routes callback (login + register) |

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Prochaines étapes

1. **Login** — intégrer maquette HTML/SCSS (`_sign-in.scss` ou extension auth)
2. **Pages métier** — Events, Groups, invitations, messages
3. **Entités** — `Group`, `GroupMember`, `Event`, `Message`, etc.
4. **Contact** — backend (entité, mailer, CSRF)
5. **Sidebar** — afficher l’utilisateur connecté (`app.user` / `getDisplayName()`)
6. **Modales** — compatibilité Turbo à valider
7. **Tarteaucitron** · **i18n**

## Changelog

### 2026-05-28 — Auth inscription + layout auth

- Layout `auth.html.twig` (hors sidebar), thème coin, SCSS `_auth.scss` + `_sign-up.scss`
- `RegistrationController` + formulaire (prénom, nom, pseudo, e-mail, mot de passe, CGU)
- `SecurityController` + login fonctionnel (UI placeholder)
- Bouton Google inscription : style + animation ; OAuth reporté avant prod
- Sidebar : liens login / register / logout
- Fix bouton Google : `aria-disabled` au lieu de `disabled` (hover CSS)

### 2026-05-28 — Entité User + MySQL

- `.env.local` / `ef_base` (MySQL 8.4 Laragon)
- Table `ef_users` + migration
- Provider Security Doctrine

### 2026-05-28 — Pages statiques + socle layout

- Home, About, Contact (templates + SCSS)
- Layout responsive, thème persistant, Turbo-safe
- Architecture SCSS modulaire `@use`, script `composer assets:refresh`
- Fix CSS : SassBundle + lien Twig (plus d’import SCSS dans `app.js`)
