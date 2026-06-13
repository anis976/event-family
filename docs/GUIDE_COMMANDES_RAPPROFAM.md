# Guide des commandes — RapproFam (rapprofam.fr)

> **Fichier de secours** à garder sur votre PC (hors Cursor si besoin).  
> Projet : Symfony 8 · Hébergeur : **o2switch** · Dépôt : `https://github.com/anis976/event-family`  
> Dernière mise à jour : juin 2026.

---

## Sommaire

1. [Où taper quelle commande](#1-où-taper-quelle-commande)
2. [Vos chemins importants](#2-vos-chemins-importants)
3. [Connexion SSH o2switch](#3-connexion-ssh-o2switch)
4. [Éditer un fichier](#4-éditer-un-fichier)
5. [Déploiement (mise en ligne)](#5-déploiement-mise-en-ligne)
6. [Git (versionner le code)](#6-git-versionner-le-code)
7. [Base de données](#7-base-de-données)
8. [Créer un compte admin](#8-créer-un-compte-admin)
9. [Fermer / rouvrir le site au public](#9-fermer--rouvrir-le-site-au-public)
10. [Cache Symfony](#10-cache-symfony)
11. [Variables `.env.local` (prod)](#11-variables-envlocal-prod)
12. [Cron (tâches automatiques)](#12-cron-tâches-automatiques)
13. [Dépannage rapide](#13-dépannage-rapide)
14. [Glossaire](#14-glossaire)

---

## 1. Où taper quelle commande

| Où | Quand l'utiliser |
|----|------------------|
| **Terminal VS Code** (PowerShell) sur votre PC | Développement local Laragon, `git`, `deploy.ps1`, build CSS |
| **SSH** (terminal connecté au serveur o2switch) | Tout ce qui touche la prod : cache, BDD, `.env.local` serveur |
| **cPanel** (navigateur) | BDD MySQL, fichiers sans SSH, logs, certificat SSL |

### Ouvrir le terminal dans VS Code

- Menu **Terminal** → **Nouveau terminal**
- Ou raccourci : **Ctrl + `** (accent grave)
- Vérifiez que le prompt ressemble à : `PS C:\laragon\www\eventFamily>`

### Ne pas confondre

- **Cliquer** sur un fichier `.ps1` dans l'explorateur → VS Code **ouvre** le fichier (ne l'exécute pas)
- **Exécuter** un script → taper la commande **dans le terminal**

---

## 2. Vos chemins importants

### Sur votre PC (Windows / Laragon)

```
C:\laragon\www\eventFamily\          ← code source
C:\laragon\www\eventFamily\.env.local ← secrets DEV (ne jamais commit)
C:\laragon\www\eventFamily\deploy.config ← config deploy (mot de passe pas dedans)
```

### Sur o2switch (serveur)

```
/home/soan5627/rapprofam.fr/           ← projet Symfony COMPLET
/home/soan5627/rapprofam.fr/public/    ← seul dossier visible sur le web
/home/soan5627/rapprofam.fr/.env.local ← secrets PROD (jamais sur GitHub)
/home/soan5627/public_html/            ← autre site / vide (cgi-bin) — pas RapproFam
```

### URLs

| URL | Rôle |
|-----|------|
| `https://rapprofam.fr` | Site public |
| `https://rapprofam.fr/login` | Connexion |
| `https://rapprofam.fr/VOTRE_CHEMIN_ADMIN` | Admin EasyAdmin (`EF_ADMIN_PATH` dans `.env.local`) |
| cPanel | `https://VOTRE-SERVEUR.o2switch.net:2083` |

---

## 3. Connexion SSH o2switch

### Prérequis (une fois)

1. E-mail **Bienvenue o2switch** : identifiant cPanel + nom du serveur (ex. `eglantier.o2switch.net`)
2. cPanel → **Autorisation SSH** → ajouter **votre IP** (sinon connexion refusée)

### Se connecter depuis VS Code / PowerShell

```powershell
ssh soan5627@eglantier.o2switch.net
```

- **Mot de passe** : le même que **cPanel**
- Une fois connecté, le prompt change : `[soan5627@eglantier ~]$`

### Aller dans le projet

```bash
cd ~/rapprofam.fr
```

### Se déconnecter

```bash
exit
```

### Alternative : Terminal cPanel

cPanel → **Terminal** → même shell, pas besoin d'installer SSH sur Windows.

---

## 4. Éditer un fichier

### Sur le serveur (SSH) — `nano`

```bash
cd ~/rapprofam.fr
nano .env.local
```

| Touche | Action |
|--------|--------|
| Flèches | Déplacer le curseur |
| **Ctrl + O** | Enregistrer (Write Out) → Entrée pour confirmer |
| **Ctrl + X** | Quitter |
| **Ctrl + K** | Couper une ligne |

### Lire un fichier sans l'éditer

```bash
cat .env.local
head -20 .env.local          # 20 premières lignes
grep APP_ENV .env.local      # chercher une variable
```

### Sur votre PC — VS Code

Ouvrez le fichier dans l'éditeur, modifiez, **Ctrl + S** pour enregistrer.

### Envoyer UN fichier modifié sur le serveur (sans deploy complet)

Depuis PowerShell sur le PC :

```powershell
cd C:\laragon\www\eventFamily
scp config/packages/security.yaml soan5627@eglantier.o2switch.net:/home/soan5627/rapprofam.fr/config/packages/security.yaml
```

Puis sur le serveur :

```bash
cd ~/rapprofam.fr
php bin/console cache:clear --env=prod
```

> **En pratique** : préférez `git push` + `deploy.ps1` pour ne pas oublier de fichiers.

---

## 5. Déploiement (mise en ligne)

### Configuration initiale (une fois sur le PC)

```powershell
cd C:\laragon\www\eventFamily
copy deploy.config.example deploy.config
```

Éditez `deploy.config` :

```ini
SSH_HOST=soan5627@eglantier.o2switch.net
REMOTE_PATH=/home/soan5627/rapprofam.fr
```

### Déploiement complet — LA commande à retenir

```powershell
cd C:\laragon\www\eventFamily
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

**Ce que fait `deploy.ps1` :**

1. Refuse si des fichiers ne sont pas commités (`git status`)
2. Compile le CSS/JS en local (`sass:build`, `asset-map:compile`)
3. `git push` vers GitHub (si nécessaire)
4. Lance `deploy-server.sh` sur o2switch via SSH
5. Affiche **`[OK] Deploy verifie — commit xxxxx`** si le serveur est sur le même commit que le PC

**Mot de passe cPanel** : demandé pour SSH. Sans la ligne `[OK] Deploy verifie`, le serveur n’est pas à jour.

### Ce que fait `deploy-server.sh` (sur le serveur, automatique)

1. `git reset --hard origin/main` — code identique à GitHub (`.env.local` intact)
2. `composer install --no-dev`
3. `composer dump-env prod`
4. Migrations BDD
5. Vide et réchauffe le cache

### Déploiement manuel serveur seulement (si besoin)

```bash
cd ~/rapprofam.fr
bash bin/deploy-server.sh
```

### Après modification de `.env.local` sur le serveur

Le deploy ne suffit pas toujours — refaire :

```bash
cd ~/rapprofam.fr
composer dump-env prod
php bin/console cache:clear --env=prod
```

---

## 6. Git (versionner le code)

### Vocabulaire rapide

| Commande | Définition |
|----------|------------|
| `git status` | Liste fichiers modifiés / non suivis |
| `git add` | Prépare des fichiers pour un commit |
| `git commit` | Enregistre un snapshot local avec un message |
| `git push` | Envoie les commits vers GitHub |
| `git pull` | Récupère les commits depuis GitHub |

### Workflow habituel après modification du code (PC)

```powershell
cd C:\laragon\www\eventFamily
git status
git add .
git commit -m "description courte de ce que vous avez changé"
git push origin main
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

### Voir l'historique

```powershell
git log --oneline -10
```

### Annuler des modifications locales NON commitées (attention)

```powershell
git checkout -- chemin/du/fichier
```

### Fichiers JAMAIS à committer

- `.env.local` (secrets)
- `deploy.config` (contient votre host SSH)
- `vendor/`, `node_modules/`, `public/assets/` (générés)

---

## 7. Base de données

### Infos prod (exemple — les vôtres sont dans `.env.local` serveur)

```
Hôte : 127.0.0.1
Base : soan5627_cpanel_rapproFam
User : soan5627_rapproFamBoss
```

### Appliquer les migrations (créer / mettre à jour les tables)

```bash
cd ~/rapprofam.fr
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

**Définition** : les fichiers dans `migrations/` décrivent l'évolution du schéma (`ef_users`, `ef_groups`, etc.).

### Voir l'état des migrations

```bash
php bin/console doctrine:migrations:status --env=prod
```

### Marquer une migration comme déjà faite (sans l'exécuter)

Utile si vous avez corrigé la BDD à la main :

```bash
php bin/console doctrine:migrations:version "DoctrineMigrations\\Version20260610100000" --add --no-interaction --env=prod
```

### Exécuter du SQL à la main (Symfony 8)

```bash
php bin/console dbal:run-sql "SHOW TABLES" --env=prod
php bin/console dbal:run-sql "SELECT id, email, roles FROM ef_users LIMIT 5" --env=prod
```

### Voir les tables

```bash
php bin/console dbal:run-sql "SHOW TABLES" --env=prod
```

### Créer la BDD (déjà fait sur o2switch via cPanel)

En local Laragon seulement si besoin :

```powershell
php bin/console doctrine:database:create
```

### Sauvegarde BDD (cPanel)

cPanel → **phpMyAdmin** → exporter la base `soan5627_cpanel_rapproFam`  
Ou **Sauvegardes** cPanel si disponible.

---

## 8. Créer un compte admin

Il n'y a pas de commande `create-admin` dans le projet. Procédure recommandée :

### Étape A — Inscription normale

1. Site ouvert (`EF_SITE_CLOSED=0`)
2. Aller sur `https://rapprofam.fr/register`
3. Créer le compte avec **votre vrai e-mail**

### Étape B — Vérifier l'e-mail

- Cliquer le lien dans l'e-mail de vérification  
- **OU** si SMTP pas encore configuré, activer à la main en SSH :

```bash
cd ~/rapprofam.fr
php bin/console dbal:run-sql "UPDATE ef_users SET is_verified = 1 WHERE email = 'votre@email.fr'" --env=prod
```

### Étape C — Donner le rôle admin (premier compte uniquement)

```bash
php bin/console dbal:run-sql "UPDATE ef_users SET roles = '[\"ROLE_USER\",\"ROLE_ADMIN\"]' WHERE email = 'votre@email.fr'" --env=prod
```

**Définition des rôles :**

| Rôle | Accès |
|------|--------|
| `ROLE_USER` | Membre normal |
| `ROLE_MODERATOR` | Modération + admin partiel |
| `ROLE_SUPER_MODERATOR` | Plus de droits admin |
| `ROLE_ADMIN` | Tout l'admin EasyAdmin |

### Étape D — Accéder à l'admin

URL : `https://rapprofam.fr/VOTRE_EF_ADMIN_PATH`  
(valeur de `EF_ADMIN_PATH` dans `.env.local` serveur, **sans** slash au début)

### Promouvoir un autre utilisateur (quand vous êtes déjà admin)

Via l'interface EasyAdmin → Utilisateurs → modifier le rôle.

---

## 9. Fermer / rouvrir le site au public

### Fermer (maintenance visiteurs)

Sur le serveur :

```bash
cd ~/rapprofam.fr
nano .env.local
```

Ajouter ou modifier :

```env
EF_SITE_CLOSED=1
```

Puis :

```bash
composer dump-env prod
php bin/console cache:clear --env=prod
```

- **Visiteurs** : page « RapproFam revient bientôt »
- **Vous** (admin/modo connecté) : site normal via `/login`

### Rouvrir

```env
EF_SITE_CLOSED=0
```

Puis `composer dump-env prod` + `cache:clear --env=prod`.

---

## 10. Cache Symfony

```bash
cd ~/rapprofam.fr
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

**Quand le faire** : après changement de config, `.env.local`, `security.yaml`, traductions déployées.

### Vérifier que Symfony démarre

```bash
php bin/console about --env=prod
```

---

## 11. Variables `.env.local` (prod)

Fichier **sur le serveur uniquement** : `~/rapprofam.fr/.env.local`

### Minimum utile

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=un_long_secret_aleatoire_unique

DEFAULT_URI=https://rapprofam.fr

DATABASE_URL="mysql://USER:MDP@127.0.0.1:3306/NOM_BDD?serverVersion=10.6.20-MariaDB&charset=utf8mb4"

MAILER_DSN=smtps://rf_contact%40rapprofam.fr:MOT_DE_PASSE@mail.rapprofam.fr:465
MAILER_FROM="RapproFam <rf_contact@rapprofam.fr>"
CONTACT_RECIPIENT=rf_contact@rapprofam.fr

GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
GOOGLE_OAUTH_REDIRECT_URI=https://rapprofam.fr/connect/google/check

EF_ADMIN_PATH=un-chemin-secret-sans-slash
EF_SITE_CLOSED=0
```

### Générer un secret

```bash
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
```

### Après TOUTE modification de `.env.local`

```bash
composer dump-env prod
php bin/console cache:clear --env=prod
```

---

## 12. Cron (tâches automatiques)

À configurer dans cPanel → **Tâches Cron** (fréquence au choix).

```bash
# Purge comptes inactifs (ex. tous les jours à 3h)
0 3 * * * cd /home/soan5627/rapprofam.fr && php bin/console app:users:purge-inactive --env=prod

# Purge événements passés (ex. 4h)
0 4 * * * cd /home/soan5627/rapprofam.fr && php bin/console app:events:purge-past --env=prod

# Purge vieux messages (ex. 5h)
0 5 * * * cd /home/soan5627/rapprofam.fr && php bin/console app:messages:purge-old --env=prod
```

---

## 13. Dépannage rapide

### Erreur 500 sur le site

```bash
cd ~/rapprofam.fr
php bin/console about --env=prod
php bin/console cache:clear --env=prod
```

Temporairement pour voir l'erreur (remettre `0` après) :

```env
APP_DEBUG=1
```

### `git pull` / deploy : conflit de fichiers

```bash
cd ~/rapprofam.fr
git fetch origin
git reset --hard origin/main
bash bin/deploy-server.sh
```

### Migration : colonne déjà existante

```bash
php bin/console doctrine:migrations:version "DoctrineMigrations\\VersionXXXX" --add --no-interaction --env=prod
```

### Google OAuth : diagnostic

```bash
php bin/console ef:google-oauth:diagnose --env=prod
```

### Chrome « Site dangereux »

- Search Console → Problèmes de sécurité → Demander un examen  
- Pas lié aux e-mails de test en local  
- Vérifier cohérence **rapprofam.fr** + marque **RapproFam**

### `deploy.ps1` : erreur PowerShell sur accents

Toujours lancer avec :

```powershell
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

### `sass:build` échoue sur le serveur

Normal si `npm` absent — les assets viennent du PC via `scp` dans `deploy.ps1`.

### Permissions dossiers upload

```bash
mkdir -p var/storage/avatars var/storage/events var/storage/message-photos
chmod -R u+w var
```

---

## 14. Glossaire

| Terme | Définition |
|-------|------------|
| **SSH** | Connexion sécurisée en ligne de commande au serveur |
| **scp** | Copie de fichiers vers le serveur via SSH |
| **cPanel** | Interface web de gestion o2switch |
| **Composer** | Gestionnaire de librairies PHP (`vendor/`) |
| **Git** | Historique du code ; GitHub = copie en ligne |
| **Commit** | Snapshot enregistré du code |
| **Push** | Envoi des commits vers GitHub |
| **Pull / reset** | Récupération du code depuis GitHub |
| **Migration** | Script qui met à jour la structure MySQL |
| **Cache Symfony** | Fichiers compilés de config — à vider après changement |
| **`.env.local`** | Secrets et config par machine (non versionnés) |
| **`dump-env prod`** | Compile `.env.local` en `.env.local.php` pour la prod |
| **`public/`** | Seul dossier web exposé (racine document = `rapprofam.fr/public`) |
| **`EF_SITE_CLOSED`** | `1` = maintenance visiteurs, `0` = site ouvert |
| **`EF_ADMIN_PATH`** | URL secrète de l'admin EasyAdmin |

---

## Récap — les 5 commandes les plus utiles

```powershell
# 1. Déployer après modification du code (PC)
cd C:\laragon\www\eventFamily
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

```bash
# 2. Se connecter au serveur
ssh soan5627@eglantier.o2switch.net
cd ~/rapprofam.fr
```

```bash
# 3. Après changement .env.local (serveur)
composer dump-env prod && php bin/console cache:clear --env=prod
```

```bash
# 4. Migrations BDD (serveur)
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

```bash
# 5. Maintenance ON/OFF (serveur — puis dump-env + cache:clear)
# EF_SITE_CLOSED=1 ou 0 dans .env.local
```

---

*Projet EventFamily / RapproFam — guide personnel de déploiement o2switch.*

