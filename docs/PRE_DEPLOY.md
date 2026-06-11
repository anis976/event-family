# Checklist avant déploiement production

> **À relire intégralement** avant `APP_ENV=prod`, migration sur l'hébergeur et mise en ligne HTTPS.  
> Dernière mise à jour : 2026-06-07.

---

## Bloquant (sans ça, ne pas ouvrir au public)

| # | Sujet | Action | Statut |
|---|--------|--------|--------|
| 1 | **HTTPS** | Certificat TLS actif ; `DEFAULT_URI=https://…` | ☐ |
| 2 | **Secrets prod** | `APP_SECRET` unique ; jamais les secrets dev en prod | ☐ |
| 3 | **Base de données** | `DATABASE_URL` hébergeur ; `doctrine:migrations:migrate --no-interaction` | ☐ |
| 4 | **Mailer** | `MAILER_DSN` SMTP prod ; `MAILER_FROM` expéditeur vérifié ; **SPF + DKIM + DMARC** (voir § Délivrabilité) | ☐ |
| 5 | **reCAPTCHA contact** | [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin) — clés **prod** + domaines du site (`RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`) | ☐ |
| 5b | **Google Analytics** | Propriété GA4 prod → `EF_GOOGLE_ANALYTICS_ID` ; URL du site ; test après consentement cookies « analytics » | ☐ |
| 6 | **Chemin admin** | Personnaliser `EF_ADMIN_PATH` (≠ valeur par défaut du repo) | ☐ |
| 7 | **Assets & cache** | `sass:build` (`app.scss`, `error-page.scss`, `ef-admin.scss`) ; `asset-map:compile` si prod ; `cache:clear` en prod | ☐ |
| 8 | **Dossiers writable** | `var/storage/avatars/` et `var/storage/events/` | ☐ |
| 9 | **PHP GD** | Extension activée (avatars + photos événements) | ☐ |

---

## Contenu & légal (à renseigner)

| # | Sujet | Où | Statut |
|---|--------|-----|--------|
| 10 | **E-mails professionnels** | `MAILER_FROM`, `CONTACT_RECIPIENT`, `MODERATION_CONTACT` (SMTP prod) ; remplacer `admin@rapprofam.fr` et adresses de test sur `/contact` + mentions | ☐ |
| 10b | **Adresse éditeur** | Vérifier `PUBLISHER_ADDRESS` dans `.env` prod (mentions + RGPD) | ☐ |
| 11 | **WhatsApp / téléphone** | `/contact` — numéro réel + lien `wa.me` (**pas de clé API** en v1). API Meta → v2 | ☐ |
| 12 | **`CONTACT_RECIPIENT`** | `.env` prod — boîte qui reçoit les messages du formulaire | ☐ |
| 13 | **`MODERATION_CONTACT`** | Recours suspension site (défaut = contact) | ☐ |
| 14 | **Mentions légales — hébergeur** | Remplacer `legal.mentions.hosting.*_placeholder` (nom, adresse, contact hébergeur) — FR + EN | ☐ |
| 15 | **CGU** | Renseigner les infos **hébergeur** / éditeur dans `legal.cgu.*` + relecture du corps (`legal.*` dans `messages.*.yaml`) | ☐ |
| 16 | **Bandeau cookies** | Livré (`ef_consent` : nécessaires, analytics, marketing) — AdSense : balises **après** approbation Google | ☑ code · ☐ pub en prod |
| 16b | **Accueil public (AdSense)** | Livré : `/` + `/about` publics, vitrine invité — re-tester sur URL HTTPS avant demande AdSense | ☑ code · ☐ vérif prod |
| 16c | **Demande AdSense** | Après #1–#15 : examen sur domaine HTTPS **sans** script pub ; puis balises + `ads.txt` si `marketing: true` | ☐ |
| 16d | **PayPal Donate (soutien)** | Bouton footer **« Offrir un coup de pouce »** → [page donate hébergée](https://www.paypal.com/donate/) (`hosted_button_id` dans `templates/layout/_footer.html.twig`) | ☐ config prod |

---

## PayPal Donate (soutien footer)

Le bouton **Offrir un coup de pouce** / **Give a little boost** ouvre la page PayPal Donate en nouvel onglet (footer, membres connectés).

**Avant mise en production**, dans le [tableau de bord PayPal](https://www.paypal.com/donate/) (bouton / page hébergée liée à `hosted_button_id=E8ULND24DQE2W`) :

| Paramètre | Dev / local | Prod |
|-----------|-------------|------|
| **URL de retour** (après don) | Souvent `http://localhost:8000/…` ou URL Laragon | `https://VOTRE-DOMAINE/…` (ex. accueil ou page de remerciement) |
| **URL d'annulation** | Idem local | `https://VOTRE-DOMAINE/…` |
| **Lien du bouton site** | `templates/layout/_footer.html.twig` | Vérifier que le `hosted_button_id` correspond au bouton PayPal prod (recréer le bouton si besoin) |

Ne pas laisser les redirections PayPal pointer vers **localhost** une fois le site déployé en HTTPS.

---

## Fonctionnalités volontairement incomplètes

Choisir **brancher** ou **masquer** avant prod :

| # | Sujet | État actuel | Options | Statut |
|---|--------|-------------|---------|--------|
| 17 | **Google OAuth** | Code livré — **prod** : `GOOGLE_OAUTH_*`, `GOOGLE_OAUTH_REDIRECT_URI`, URI Cloud Console | ☐ |
| 18 | **Messenger async** | E-mails en sync en dev | Worker Messenger si async en prod | ☐ |

---

## Exploitation (cron & sauvegardes)

| # | Tâche | Commande suggérée | Statut |
|---|--------|-------------------|--------|
| 19 | Purge comptes inactifs | `app:users:purge-inactive --env=prod` (ex. 3 h) | ☐ |
| 20 | Purge événements passés | `app:events:purge-past --env=prod` (ex. 4 h) | ☐ |
| 21 | Purge messages | `app:messages:purge-old --env=prod` (ex. 5 h) | ☐ |
| 22 | Sauvegardes BDD | Backups réguliers `ef_base` | ☐ |

---

## Tests manuels recommandés (prod-like)

Tester en local avec `APP_ENV=prod` + `APP_DEBUG=0` :

| # | Scénario | Statut |
|---|----------|--------|
| 23 | Inscription → e-mail vérif → connexion | ☐ |
| 23b | Inscription / connexion **Google** (CGU, finalisation, annulation) | ☐ |
| 24 | Mot de passe oublié / changement / suppression compte | ☐ |
| 25 | Formulaire contact + reCAPTCHA (avec clés de test ou prod) | ☐ |
| 26 | Création groupe, événement, messages, invitations | ☐ |
| 26b | **Messages privés** — fil unique, réponses, « Lu le… », e-mail notif + opt-out Mon espace | ☐ |
| 26c | **Messages groupe** — publication, réponses, rate limit, pastilles non-lues | ☐ |
| 27 | Bascule locale FR ↔ EN (sidebar) | ☐ |
| 28 | Session idle (site + admin séparés) | ☐ |
| 29 | Upload avatar + photos événement | ☐ |
| 30 | Admin EasyAdmin (CRUD, suspension site, message système groupe) | ☐ |
| 31 | Page **404** (connecté, URL inexistante) — styles + FR/EN ; dev : `/_error/404` après `sass:build` | ☐ |
| 32 | Footer — **Offrir un coup de pouce** : ouverture PayPal + retour depuis PayPal vers le domaine **prod** (pas localhost) | ☐ |
| 33 | **Délivrabilité e-mail** — mail-tester.com ≥ 8/10 ; MP test reçu (Gmail / Outlook) ; List-Unsubscribe fonctionnel | ☐ |

---

## Google OAuth

1. **Google Cloud Console** — écran de consentement OAuth + identifiants « Application Web »
2. **Variables** : `GOOGLE_OAUTH_CLIENT_ID` + `GOOGLE_OAUTH_CLIENT_SECRET` (`.env.local` / secrets prod)
3. **URI de redirection autorisées** (doivent correspondre **exactement** à l’URL du navigateur) :
   - Dev : `http://localhost:8000/connect/google/check` (utiliser `localhost`, pas `127.0.0.1`, si `DEFAULT_URI` pointe sur localhost)
   - Prod : `https://VOTRE-DOMAINE/connect/google/check` (remplacer `www.monsite.com` par le domaine réel)
4. **Stack** : `knpuniversity/oauth2-client-bundle` + `league/oauth2-google`, migration `google_id` / `oauth_registration_complete`
5. **Règles actuelles** : e-mail Google vérifié → `isVerified` ; **tout nouveau compte Google** passe par `/inscription/google/terminer` (CGU obligatoires + profil si besoin) ; e-mail déjà inscrit en classique → message « connecte-toi avec ton mot de passe » (pas de liaison auto).

---

## Variables `.env` prod — rappel

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=…
DATABASE_URL=…
MAILER_DSN=…
MAILER_FROM=…
DEFAULT_URI=https://…
CONTACT_RECIPIENT=…
MODERATION_CONTACT=…
RECAPTCHA_SITE_KEY=…
RECAPTCHA_SECRET_KEY=…
GOOGLE_OAUTH_CLIENT_ID=…
GOOGLE_OAUTH_CLIENT_SECRET=…
GOOGLE_OAUTH_REDIRECT_URI=https://…/connect/google/check
EF_GOOGLE_ANALYTICS_ID=G-…
EF_ADMIN_PATH=…          # personnalisé
EF_ADMIN_IDLE_TIMEOUT=900
EF_SESSION_IDLE_TIMEOUT=1800
```

(Voir aussi `.env` pour purge inactivité, timeouts session, etc.)

---

## Délivrabilité e-mail (éviter les indésirables)

Les notifications (messages privés, vérification compte, etc.) doivent arriver en **boîte de réception**, pas en spam. Le code applique déjà : version **texte + HTML**, en-tête **List-Unsubscribe** (lien Mon espace), sujet sobre. **En prod, la configuration DNS / SMTP est indispensable.**

### 1. Expéditeur cohérent

| Règle | Exemple |
|--------|---------|
| `MAILER_FROM` = domaine du site | `RapporFam <rf_contact@rapprofam.fr>` |
| Même domaine que `DEFAULT_URI` | Site `https://rapprofam.fr` → `@rapprofam.fr` |
| **Pas** Ethereal / Gmail perso en prod | Réservé au dev local |

### 2. DNS — SPF, DKIM, DMARC

À configurer chez le registrar / hébergeur mail (Brevo, Mailgun, OVH, Google Workspace, etc.) :

| Enregistrement | Rôle |
|----------------|------|
| **SPF** (TXT `@`) | Autorise le serveur SMTP à envoyer pour votre domaine |
| **DKIM** (TXT `selector._domainkey`) | Signature cryptographique — fournie par le prestataire SMTP |
| **DMARC** (TXT `_dmarc`) | Politique si SPF/DKIM échouent (`p=none` puis `quarantine` / `reject`) |

Vérifier après déploiement : [mail-tester.com](https://www.mail-tester.com), [Google Postmaster Tools](https://postmaster.google.com/).

### 3. Prestataire SMTP transactionnel

Préférer un relay dédié (Brevo, Mailjet, SendGrid, SMTP OVH…) plutôt qu’un SMTP mutualisé non configuré. `MAILER_DSN` exemple :

```env
# O2Switch (compte cPanel rapprofam.fr) :
MAILER_DSN=smtps://rf_contact%40rapprofam.fr:MOT_DE_PASSE@mail.rapprofam.fr:465
# ou relay dédié : brevo+smtp://USERNAME:PASSWORD@default
```

### 4. Bonnes pratiques côté app (livré)

- Notifications MP : **opt-in / opt-out** dans Mon espace → Notifications
- **Throttle** (1 e-mail / 30 min / conversation) — limite le volume suspect
- Lien **« Se désabonner »** implicite via List-Unsubscribe → `/profil#notifications`
- Pas de pièces jointes inutiles, pas de mots « spam » en majuscules dans les sujets

### 5. Messages de groupe (v2)

Si des e-mails groupe sont ajoutés plus tard, réutiliser le même helper (`applyMemberNotificationHeaders`) et une préférence dédiée.

### 6. Test avant prod

1. Envoyer un MP test entre deux comptes réels  
2. Vérifier réception **Gmail + Outlook + Orange/Free** si possible  
3. Demander aux beta-testeurs d’ajouter l’expéditeur en **contacts** la première fois  
4. Score mail-tester **≥ 8/10**
