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
5. Copie les assets compilés sur o2switch + vide le cache prod

Mot de passe cPanel demandé pour SSH. Attendre la ligne **`[OK] Deploy verifie`**.

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

> **Règle d'or** : tout ce qui est dans le code (Twig, PHP, SCSS, traductions, migrations…) passe par **Git + deploy**.  
> **Exception** : `.env.local` prod → **SSH + nano**, jamais Git.

Toujours commencer par :

```powershell
cd C:\laragon\www\eventFamily
git status
```

---

### A. Fichier Twig, Controller, Entité, Service, traduction YAML, migration…

Même procédure pour **tous** les fichiers versionnés du projet.

```powershell
cd C:\laragon\www\eventFamily
git add .
git commit -m "fix: décrire brièvement ce que vous avez changé"
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

**Vous n'avez pas besoin de `git push` à part** : `deploy.ps1` le fait à l'étape 3.

Exemples de message de commit :

- `fix: retour lien PayPal dans le footer`
- `feat: filtre messages par groupe`
- `fix: correction typo page contact`

---

### B. Fichier SCSS / CSS / JS (`assets/styles/…`, `assets/js/…`)

**Exactement la même procédure** que pour un Twig ou un Controller.

Le deploy compile le SCSS sur votre PC **automatiquement** (étape 2 de `deploy.ps1`).  
Vous n'avez **pas** à lancer `sass:build` vous-même.

```powershell
cd C:\laragon\www\eventFamily
git add .
git commit -m "style: ajuster le footer"
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

---

### C. `.env.local` sur votre PC (Laragon — développement local)

1. Ouvrir `.env.local` dans VS Code (ou `notepad .env.local`)
2. Modifier, **Ctrl + S**
3. **C'est tout.** Pas de `git add`, pas de `git commit`, pas de `deploy.ps1`

Si le site local se comporte bizarrement après un changement :

```powershell
cd C:\laragon\www\eventFamily
php bin/console cache:clear
```

---

### D. `.env.local` sur le serveur (production — rapprofam.fr)

1. Se connecter en SSH :

```powershell
ssh soan5627@eglantier.o2switch.net
```

2. Éditer :

```bash
cd ~/rapprofam.fr
nano .env.local
```

3. Enregistrer (**Ctrl + O**, Entrée) et quitter (**Ctrl + X**)

4. Appliquer :

```bash
composer dump-env prod
php bin/console cache:clear --env=prod
```

5. **Pas de Git, pas de deploy** pour ce cas.

---

### E. Résumé en une ligne

| Ce que vous modifiez | Où | Commandes |
|----------------------|-----|-----------|
| Twig, PHP, YAML traductions, migration… | PC | `git add .` → `git commit -m "..."` → `deploy.ps1` |
| SCSS / JS | PC | **Pareil** — deploy compile le CSS |
| `.env.local` dev | PC Laragon | VS Code + Ctrl + S (optionnel : `cache:clear` local) |
| `.env.local` prod | Serveur SSH | `nano` → `dump-env prod` → `cache:clear --env=prod` |

---

### F. Séquence complète type (copier-coller)

Après une session de dev sur le PC :

```powershell
cd C:\laragon\www\eventFamily
git status
git add .
git commit -m "fix: décrire vos changements"
powershell -ExecutionPolicy Bypass -File .\bin\deploy.ps1
```

Attendre **`[OK] Deploy verifie`**, puis tester `https://rapprofam.fr`.

---

*Projet RapproFam — guide personnel o2switch.*
