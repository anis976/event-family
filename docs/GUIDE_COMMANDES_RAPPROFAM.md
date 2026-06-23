# Guide des commandes — RapproFam (rapprofam.fr)

> **Fichier de secours** à garder sur votre PC.  
> Projet : Symfony 8 · Hébergeur : **o2switch** · Dépôt : `https://github.com/anis976/event-family`  
> Dernière mise à jour : juin 2026.

---

## Sommaire

1. [Où taper quelle commande](#1-où-taper-quelle-commande)
2. [Chemins importants](#2-chemins-importants)
3. [Connexion SSH (serveur prod)](#3-connexion-ssh-serveur-prod)
4. [Éditer un fichier](#4-éditer-un-fichier)
5. [Git — vocabulaire minimum](#5-git--vocabulaire-minimum)
6. [Déploiement (mise en ligne)](#6-déploiement-mise-en-ligne)
7. [`.env.local` — PC vs serveur](#7-envlocal--pc-vs-serveur)
8. [Opérations utiles sur le serveur](#8-opérations-utiles-sur-le-serveur)
9. [Dépannage rapide](#9-dépannage-rapide)
10. [MÉMO — commit & deploy selon ce que vous modifiez](#10-mémo--commit--deploy-selon-ce-que-vous-modifiez)  
    - [E. Tableau récap](#e-tableau-récap-vue-densemble)  
    - [F. PC ou serveur ?](#f-pc-ou-serveur--à-ne-plus-confondre)  
    - [G. Scénario code (Twig, SCSS…)](#g-scénario-1--modifier-du-code-twig-controller-scss)  
    - [H. Scénario `.env.local`](#h-scénario-2--modifier-envlocal)  
    - [I. Maintenance + deploy](#i-scénario-3--grosse-modification-avec-maintenance)

---

## 1. Où taper quelle commande

| Où | Quand l'utiliser |
|----|------------------|
| **Terminal VS Code** (PowerShell) sur votre PC | Code PHP/Twig/SCSS, `git`, `deploy.ps1` |
| **SSH** (terminal connecté à o2switch) | `.env.local` prod, cache, BDD, maintenance |
| **cPanel** (navigateur) | BDD MySQL, logs, certificat SSL |

Ouvrir le terminal VS Code : **Ctrl + `** — le prompt doit être `PS C:\laragon\www\eventFamily>`.

> **Ne pas confondre** : cliquer sur un fichier `.ps1` l'**ouvre** dans l'éditeur. Pour l'**exécuter**, tapez la commande dans le terminal.

---

## 2. Chemins importants

### PC (Laragon)

```
C:\laragon\www\eventFamily\           ← code source
C:\laragon\www\eventFamily\.env.local ← secrets DEV (jamais commit)
C:\laragon\www\eventFamily\deploy.config ← config deploy (jamais commit)
```

### Serveur o2switch

```
/home/soan5627/rapprofam.fr/           ← projet Symfony
/home/soan5627/rapprofam.fr/.env.local ← secrets PROD (jamais sur GitHub)
```

| URL | Rôle |
|-----|------|
| `https://rapprofam.fr` | Site public |
| `https://rapprofam.fr/VOTRE_CHEMIN_ADMIN` | Admin (`EF_ADMIN_PATH` dans `.env.local` serveur) |

---

## 3. Connexion SSH (serveur prod)

```powershell
ssh soan5627@eglantier.o2switch.net
```

Mot de passe = celui du **cPanel**. Puis :

```bash
cd ~/rapprofam.fr
```

Pour quitter : `exit`

---

## 4. Éditer un fichier

### Sur votre PC — VS Code

Ouvrir le fichier, modifier, **Ctrl + S**.

### Sur le serveur — `nano` (surtout pour `.env.local` prod)

```bash
cd ~/rapprofam.fr
nano .env.local
```

| Touche | Action |
|--------|--------|
| **Ctrl + O** | Enregistrer → Entrée |
| **Ctrl + X** | Quitter |

---

## 5. Git — vocabulaire minimum

| Commande | Rôle |
|----------|------|
| `git status` | Voir ce qui a changé |
| `git add .` | Préparer **tous** les fichiers modifiés pour le commit |
| `git commit -m "..."` | Enregistrer localement avec un message |
| `git push` | Envoyer vers GitHub |

**Ordre obligatoire** : `git add` → `git commit` → (push ou deploy)

> **`git commit` seul ne suffit pas** : sans `git add` avant, rien n'est enregistré.

### Fichiers JAMAIS à committer

- `.env.local` (secrets)
- `deploy.config`
- `vendor/`, `node_modules/`, `public/assets/` (générés automatiquement)

---

## 6. Déploiement (mise en ligne)

### Configuration initiale (une seule fois sur le PC)

```powershell
cd C:\laragon\www\eventFamily
copy deploy.config.example deploy.config
```

Éditez `deploy.config` :

```ini
SSH_HOST=soan5627@eglantier.o2switch.net
REMOTE_PATH=/home/soan5627/rapprofam.fr
ASSETS_SOURCE=pc
```

### LA commande de deploy

```powershell
cd C:\laragon\www\eventFamily
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

**Prérequis** : tout doit être **commité** avant (sinon le script s'arrête).

**Ce que fait `deploy.ps1` automatiquement :**

1. Vérifie qu'il n'y a pas de fichiers non commités
2. Compile le CSS/JS en local (`sass:build`, SCSS inclus)
3. **`git push`** vers GitHub (si besoin — **vous n'avez pas à le faire vous-même**)
4. Met à jour le code sur le serveur (Composer, migrations BDD, etc.)
5. Copie les assets compilés sur o2switch
6. **`cache:clear --env=prod` + `cache:warmup --env=prod` sur le serveur** (étape finale — rien à faire à la main après un deploy OK)

Mot de passe cPanel demandé pour SSH. Attendre la ligne **`[OK] Deploy verifie`**.

> Après un deploy réussi, **ne relancez pas** `cache:clear --env=prod` sauf modification de `.env.local` en SSH ou dépannage.

---

## 7. `.env.local` — PC vs serveur

| Fichier | Où | Comment le mettre à jour |
|---------|-----|--------------------------|
| `.env.local` **PC** | Laragon | VS Code → Ctrl + S. **Pas de git, pas de deploy.** |
| `.env.local` **prod** | Serveur o2switch | SSH + `nano` → puis commandes serveur ci-dessous |

### Après modification de `.env.local` sur le **serveur**

```bash
cd ~/rapprofam.fr
composer dump-env prod
php bin/console cache:clear --env=prod
```

> Les changements dans `.env.local` **PC** n'impactent **pas** le site en ligne.  
> Les changements sur le **serveur** n'ont **pas** besoin de `git commit` ni de `deploy.ps1`.

---

## 8. Opérations utiles sur le serveur

Toutes ces commandes : après `ssh` puis `cd ~/rapprofam.fr`.

### Cache Symfony (après config, traductions déployées, etc.)

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### Fermer / rouvrir le site (maintenance)

Dans `nano .env.local` :

```env
EF_SITE_CLOSED=1   # fermé aux visiteurs
EF_SITE_CLOSED=0   # ouvert
```

Puis : `composer dump-env prod` + `cache:clear --env=prod`.

### Créer le premier admin

1. S'inscrire sur `https://rapprofam.fr/register`
2. Vérifier l'e-mail (ou activer à la main en SQL si besoin)
3. En SSH :

```bash
php bin/console dbal:run-sql "UPDATE ef_users SET roles = '[\"ROLE_USER\",\"ROLE_ADMIN\"]' WHERE email = 'votre@email.fr'" --env=prod
```

### Migrations BDD (normalement faites par deploy)

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

---

## 9. Dépannage rapide

### Erreur 500

```bash
cd ~/rapprofam.fr
php bin/console about --env=prod
php bin/console cache:clear --env=prod
```

### `deploy.ps1` refuse de partir

Message « modifications non commitées » → faites `git add .` + `git commit -m "..."` puis relancez deploy.

### CSS pas à jour en prod

Ne lancez **pas** `deploy-server.sh` seul depuis le serveur. Toujours :

```powershell
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

(sur o2switch, le SCSS est compilé sur **votre PC**, pas sur le serveur)

### Site bloqué après deploy

```bash
cd ~/rapprofam.fr
git fetch origin
git reset --hard origin/main
bash bin/deploy-server.sh
```

---

## 10. MÉMO — commit & deploy selon ce que vous modifiez

> **Par où commencer ?** Lis d'abord **F** (PC vs serveur), puis le scénario qui te concerne : **G** (code), **H** (`.env.local`) ou **I** (maintenance + deploy).

> **Règle d'or** : tout ce qui est dans le code (Twig, PHP, SCSS, traductions, migrations…) passe par **Git + deploy**.  
> **Exception** : `.env.local` **serveur** → **SSH + nano**, jamais Git.

---

### E. Tableau récap (vue d'ensemble)

| Ce que vous modifiez | Où ? | Que faire ? |
|----------------------|------|-------------|
| Twig, PHP, SCSS, traductions, migration… | **PC** (Laragon) | `git add .` → `git commit` → `deploy.ps1` |
| `.env.local` pour tester en local | **PC** (Laragon) | VS Code + Ctrl + S — **pas de git, pas de deploy** |
| `.env.local` du site en ligne | **Serveur** (SSH) | `nano` → `dump-env prod` → `cache:clear --env=prod` — **pas de git, pas de deploy** |

---

### F. PC ou serveur ? (à ne plus confondre)

| | **PC (PowerShell)** | **Serveur (SSH → bash)** |
|---|---------------------|---------------------------|
| **C'est quoi ?** | Ton Laragon, développement local | o2switch, le vrai site `rapprofam.fr` |
| **Terminal** | VS Code → prompt `PS C:\laragon\...>` | Après `ssh soan5627@...` → prompt `[soan5627@...]$` |
| **Fichier `.env.local`** | `C:\laragon\www\eventFamily\.env.local` | `/home/soan5627/rapprofam.fr/.env.local` |
| **Commande cache** | `php bin/console cache:clear` | `php bin/console cache:clear --env=prod` |
| **`dump-env prod`** | ❌ Ne pas utiliser sur le PC | ✅ Uniquement sur le serveur, après `nano .env.local` |

> **Astuce** : si tu vois `--env=prod` ou `dump-env prod`, tu es sur le **serveur**, pas dans PowerShell Windows.

---

### G. Scénario 1 — Modifier du code (Twig, Controller, SCSS…)

**Étape 1 — Travailler et tester en local (PC)**

1. Modifier les fichiers dans VS Code
2. Tester sur ton site Laragon (ex. `http://eventfamily.test` ou ton URL locale)
3. Corriger jusqu'à ce que **ça te convienne**

**Étape 2 — Mettre en ligne (PC, PowerShell)**

> Ne lance **`deploy.ps1` que si tu es satisfait** de ce que tu as testé en local.

```powershell
cd C:\laragon\www\eventFamily
git status
git add .
git commit -m "fix: Contenu enrichi sur les pages publiques"
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1

POUR AJOUTER UN FICHIER DANS LE DEPOT GIT EX
git add README.md docs/GUIDE_COMMANDES_RAPPROFAM.md
git commit -m "docs: état projet juin 2026, diffusion et mémo deploy"

Avant depploy on peux lancer : php bin/console lint:container   
php bin/console ef:staff-circle:sync --env=prod
Cette commande est optionel
php bin/console lint:container vérifie que le conteneur Symfony est cohérent : pour chaque service enregistré, Symfony contrôle que les types déclarés constructeur, arguments injectés, etc. correspondent bien à ce qui est réellement injecté.

Pour envoyer sur git uniquement la modification de fichier text : 
cd C:\laragon\www\eventFamily
git add docs/GUIDE_COMMANDES_RAPPROFAM.md
git commit -m "docs: mise à jour du guide commandes"

Pour le README à la racine
git add README.md
git commit -m "docs: mise à jour du README"

Quand vider le cache à la main (SSH)
Seulement si tu modifies .env.local sur le serveur sans redeployer, ou en cas de dépannage (erreur 500) :

cd ~/rapprofam.fr
composer dump-env prod    # si tu as changé des variables .env
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

si le scss ne charge pas : 

En local (dev) — recommandé

cd C:\laragon\www\eventFamily
composer assets:refresh

En dev (pas besoin la commande : deplloy le fait deja ceci : )

En prod (après deploy)
php bin/console sass:build --env=prod
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod
```

**Notes :**

- `git status` → voir ce qui va être enregistré (utile, pas obligatoire)
- `git commit -m "..."` → décrire ce que tu as changé (ex. `style: sidebar mobile`, `fix: footer PayPal`)
- `git push` → **pas besoin** : `deploy.ps1` le fait tout seul
- Attendre **`[OK] Deploy verifie`**, puis vérifier `https://rapprofam.fr`

**SCSS / JS** : même procédure. Le deploy compile le CSS sur ton PC automatiquement.

---

### H. Scénario 2 — Modifier `.env.local`

#### Cas A — Fichier sur ton PC (Laragon)

Pour tester quelque chose en local uniquement.

1. Ouvrir `.env.local` dans VS Code
2. Modifier → **Ctrl + S**
3. **C'est tout** — pas de `git add`, pas de `commit`, pas de `deploy.ps1`

Si le site local bugue après :

```powershell
cd C:\laragon\www\eventFamily
php bin/console cache:clear
```

#### Cas B — Fichier sur le serveur (site en ligne)

Pour la maintenance, les secrets prod, `EF_ADMIN_PATH`, etc.

**1. Se connecter (depuis PowerShell, mais les commandes suivantes sont sur le serveur) :**

```powershell
ssh soan5627@eglantier.o2switch.net
```

**2. Éditer le fichier (maintenant tu es en SSH / bash) :**

```bash
cd ~/rapprofam.fr
nano .env.local
```

Modifier (ex. `EF_SITE_CLOSED=1` pour maintenance) → **Ctrl + O**, Entrée → **Ctrl + X**

**3. Appliquer les changements (toujours en SSH / bash) :**

```bash
composer dump-env prod
php bin/console cache:clear --env=prod
```

**4. C'est fini** — pas de `git commit`, pas de `deploy.ps1`.

Pour **rouvrir** le site après maintenance : remettre `EF_SITE_CLOSED=0`, puis refaire les 2 commandes ci-dessus.

---

### I. Scénario 3 — Grosse modification avec maintenance

Ordre recommandé quand tu veux cacher le site aux visiteurs pendant que tu travailles :

| Étape | Où | Action |
|-------|-----|--------|
| 1 | **Serveur SSH** | `EF_SITE_CLOSED=1` → `dump-env prod` → `cache:clear --env=prod` |
| 2 | **PC** | Faire tes modifs, tester en local (Laragon) |
| 3 | **PC PowerShell** | `git add .` → `git commit` → `deploy.ps1` (si tout te convient) |
| 4 | **Serveur SSH** | `EF_SITE_CLOSED=0` → `dump-env prod` → `cache:clear --env=prod` |

> Le deploy **ne touche pas** au `.env.local` serveur : la maintenance reste active tant que tu ne la désactives pas toi-même (étape 4).

---

*Projet RapproFam — guide personnel o2switch.*
