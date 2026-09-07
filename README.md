# Drop Picture

PWA de dépôt et de partage de photos et vidéos, **en qualité d'origine**. On glisse ses
fichiers, on les retrouve dans une galerie filtrable, on les partage par lien ou vers un
groupe — et ce que reçoit le destinataire est, octet pour octet, le fichier déposé.

Vert néon sur noir, la marque s'écrit `drop*picture`. Mobile, tablette et desktop pour un
même code ; installable comme application.

## La règle n°1 — la qualité d'origine

C'est le cœur du produit, et chaque choix technique en découle :

- **Aucune compression, aucun réencodage, aucune conversion.** Le navigateur lit le fichier
  tranche par tranche et envoie les octets tels quels ; le serveur les écrit à la suite dans
  un fichier partiel, puis le *renomme* en média — jamais de copie ni de réécriture. Aucune
  bibliothèque d'optimisation d'images ne touche jamais au fichier d'origine.
- **Dépôt par morceaux, jusqu'à 50 Go par fichier (réglable).** Chaque morceau (8 Mo) part dans
  sa propre requête : `upload_max_filesize` et `post_max_size` ne portent que sur un morceau, et
  une coupure passagère ne coûte qu'un morceau à renvoyer.
- **Empreinte SHA-256 de bout en bout, calculée au fil de l'eau.** Le navigateur calcule
  l'empreinte en flux pendant l'envoi ; le serveur fait de même morceau après morceau (l'état du
  hachage est conservé entre deux requêtes) et **refuse tout écart** à la clôture — immédiate,
  quelle que soit la taille. L'empreinte est stockée, affichée au destinataire et renvoyée en
  en-tête (`X-Checksum-SHA256`) : n'importe qui peut vérifier le fichier téléchargé.
- **Les aperçus sont les seuls dérivés.** Une miniature JPEG réduite, dans son propre dossier,
  produite par un worker qui *lit* l'original (GD pour les photos, ffmpeg pour les vidéos).
  RAW, TIFF et formats inconnus n'ont pas d'aperçu — et sont acceptés tels quels.
- **Le téléchargement et le partage servent toujours l'original**, sous son nom d'origine,
  en flux avec reprise (`Range`), sans compression de transport (`encode identity` dans le
  Caddyfile).
- **Le critère d'acceptation n°1 est vérifié à chaque déploiement** : `php artisan drop:selftest`
  dépose un fichier d'octets aléatoires par le même chemin que le navigateur, le retélécharge
  par HTTP via un lien de partage et compare les deux empreintes. `redeploie.sh` échoue si elles
  diffèrent.

## Stack

Même stack, mêmes conventions et même chaîne de livraison que le projet **pointage** :

- **Laravel 13** + **Inertia 3** + **Vue 3** (JavaScript, pas TypeScript)
- **Reka UI** pour les primitives accessibles (dialogues, onglets, interrupteur, menu,
  notifications)
- **Tailwind CSS 4** — les jetons du design system sont déclarés en `@theme` dans
  `resources/css/app.css`, les primitives (`btn`, `field`, `card`, `badge`, `chip`, `tab`…)
  en `@utility`
- **Lucide** pour les icônes, **Space Grotesk / Figtree / JetBrains Mono** auto-hébergées
- **hash-wasm** pour l'empreinte SHA-256 en flux côté navigateur
- **Laravel Sail** (MySQL 8.4) pour l'environnement local, **FrankenPHP** en production

## Démarrage

```bash
cp .env.example .env && composer install && ./vendor/bin/sail up -d
```

```bash
./vendor/bin/sail artisan key:generate && ./vendor/bin/sail artisan migrate --seed
```

```bash
npm install && npm run build
```

L'application répond sur **http://localhost:8080**. En développement, `npm run dev` remplace
`npm run build` (serveur Vite sur le port 5174). Les aperçus se calculent dans la file :
`./vendor/bin/sail artisan queue:work`.

Profil de démonstration : **demo@drop.pictures** / **password**.

### Mise en ligne

[DEPLOIEMENT.md](DEPLOIEMENT.md) : la pile rejoint le VPS de pointage — même Traefik, même
registre privé, même monitoring —, dans son propre dossier `/srv/drop-picture`.
[.github/workflows/deploiement.yml](.github/workflows/deploiement.yml) enchaîne les tests, la
publication de l'image et la bascule par SSH ; [redeploie.sh](redeploie.sh) attend `/up`, puis
joue le test d'intégrité.

## Écrans

| Chemin | Écran |
| --- | --- |
| `/connexion`, `/inscription` | Connexion et création de compte, sur fond animé |
| `/mot-de-passe-oublie`, `/nouveau-mot-de-passe/{token}` | Mot de passe oublié |
| `/` | Galerie — dépôt par glisser-déposer, filtres par tag et par type, recherche, badge « Original · 4K / RAW / 24 Mpx » ; onglets Partages et Groupes |
| `/groupes/{groupe}` | Un groupe — membres, fichiers partagés, invitation par lien ou e-mail |
| `/p/{token}` | Page publique d'un lien de partage — aperçu, empreinte, téléchargement de l'original |
| `/g/{token}`, `/invitations/{token}` | Rejoindre un groupe (par le lien du groupe, ou par une invitation nominative) |

## Choix d'implémentation

**Le fichier partiel devient le média par `rename()`.** Après vérification de l'empreinte,
l'inode est déplacé dans `storage/app/private/media/{user}/{uuid}/{nom d'origine}`. Aucun
octet n'est relu ni réécrit à cette étape.

**Un dépôt interrompu ne laisse rien traîner.** Le planificateur purge chaque heure les dépôts
inachevés depuis plus de 24 h, avec leur fichier partiel.

**Le quota compte les tailles d'origine.** 100 Go par compte par défaut ; la somme exacte des
octets déposés, puisque rien n'est jamais compressé. Le disque du serveur reste la vraie limite :
un dépôt qui entamerait la réserve (`DROP_DISK_RESERVE_BYTES`, 5 Go) est refusé à l'ouverture,
plutôt que d'échouer au dernier morceau.

**Les limites se règlent dans l'application.** Le premier compte créé administre l'instance
(`php artisan drop:admin e-mail` en nomme d'autres) et trouve « Réglages » dans son menu :
espace par compte, taille maximale d'une photo, d'une vidéo, d'un autre fichier — en Go, avec
la place réellement disponible sur le disque en regard. Les valeurs du `.env` ne servent que de
défaut tant que rien n'a été réglé.

**Après un temps sans consultation, la place se libère — sans perdre un octet.** Passé le
délai réglé (7 jours par défaut, dans « Réglages »), une photo est archivée : un JPEG est
recompressé en **JPEG XL avec ses données de reconstruction** (~20 % gagnés), un RAW, TIFF ou PNG
passe en **zstd** (10 à 40 %). L'archive est aussitôt décompressée dans un fichier témoin et
comparée à l'empreinte SHA-256 du dépôt : si un seul octet diffère, ou si le gain est inférieur
à 5 %, l'original reste. Sinon, il cède sa place. Au téléchargement — ou d'avance, par
« Recharger » —, l'original est reconstruit, vérifié à nouveau, et redevient « chaud ». Vidéos et
HEIC, déjà au plus serré, ne sont jamais touchés : pour eux, seule la durée de vie d'un groupe
libère de la place.

**Un groupe peut avoir une durée de vie.** 7 jours, 30 jours, 1 an ou sans limite, choisie à
la création et affichée sur le groupe. À l'échéance, un passage horaire détruit les fichiers
**déposés dans le groupe** (fichier, aperçu, liens), retire ceux qui venaient d'une galerie — ils
y restent — et supprime le groupe avec ses invitations.

**Plusieurs originaux d'un coup.** « Tout télécharger » sur un groupe, ou le mode « Sélectionner »
— dans la galerie comme dans un groupe —, produisent un ZIP fabriqué en flux, méthode *store* —
aucune compression : chaque entrée est le fichier d'origine, octet pour octet, et un ZIP de
plusieurs Go ne passe jamais par la mémoire de PHP. Depuis la galerie, la sélection peut aussi
être envoyée dans un groupe.

**« Tout dans Photos », lot par lot.** Sur iPhone et iPad, la galerie et chaque groupe ont leur
bouton « Tout dans Photos », à côté de « Tout télécharger » ; la barre de sélection aussi. Une
feuille de partage ne peut pas avaler vingt vidéos d'un coup — iOS la refuse —, alors la sélection
est découpée en lots que la feuille accepte (400 Mo ou 8 fichiers, un fichier de plus de 1,5 Go
restant hors de portée), et un dialogue les enchaîne : un appui, un lot, et il avance. **Aucune
tâche de fond ne peut faire ce travail à notre place** : la photothèque n'est accessible qu'à
Safari, et seulement dans la foulée d'un geste — un job côté serveur n'a aucun chemin vers l'app
Photos d'un téléphone. Le geste par lot est donc le minimum incontournable ; en échange, il n'y a
plus de limite au nombre de fichiers qu'on peut ranger d'une traite, et un lot dont la préparation
a dépassé la fenêtre du geste reste prêt en mémoire pour l'appui suivant, sans rien relire.

**Ce qui est déjà récupéré se voit.** Chaque carte porte une pastille « Dans Photos » ou
« Téléchargé » dès que ce fichier est reparti chez celui qui regarde — et la fiche donne la date et
le nombre de fois. L'indicateur est **personnel** : dans un groupe, deux membres ne voient pas les
mêmes pastilles. Les trois chemins de sortie le posent : un téléchargement, un ZIP (chaque entrée
est marquée), et la photothèque — que seul le navigateur peut confirmer, puisqu'iOS y dépose sans
passer par le serveur : la page le lui dit alors par `POST /fichiers/enregistres`.

**Le groupe se met à jour tout seul.** Chaque groupe a son canal privé (Soketi, protocole
Pusher, comme pointage) : un fichier déposé ou partagé par un membre apparaît chez les autres sans
recharger, avec une notification « Léo a ajouté … » ; l'aperçu prêt complète la carte de la même
façon. Depuis la page d'un groupe, « Déposer » et la zone de dépôt envoient directement dans le
groupe. Sans clé de diffusion, l'application vit très bien sans temps réel.

**Photothèque sur téléphone.** Sur mobile, le bouton « Déposer » annonce photos et vidéos pour
que le système propose la photothèque et l'appareil, et nomme HEIC/HEIF explicitement : sans
cela, Safari convertirait les HEIC en JPEG au passage. Sur ordinateur, aucun filtre — un RAW au
type inconnu reste sélectionnable.

**Les vidéos et l'iPhone — ce qu'iOS impose.** Une vidéo choisie dans la photothèque via Safari
arrive **toujours ré-encodée** par iOS (H.264, taille et métadonnées réduites) : c'est une décision
de WebKit/PhotoKit qu'aucun site ne peut contourner. Pour déposer l'original d'une vidéo depuis un
iPhone, il faut passer par l'app **Fichiers** (Partager → Enregistrer dans Fichiers, puis Déposer →
Parcourir) ou par un ordinateur. Quand iOS n'arrive pas à préparer une vidéo (original dans iCloud
non téléchargé, espace insuffisant, vidéo importée d'une autre app), Safari annule le sélecteur au
lieu de rendre le fichier : l'application le dit désormais, avec la marche à suivre, et note
l'incident dans ses journaux (`POST /journal-client`) pour qu'un dépôt qui « ne marche pas » laisse
une trace côté serveur. Sur iOS les morceaux font 4 Mo et partent en Blob, forme que Safari gère le
mieux.

**Partager par lien ne produit aucune version.** Un lien est un jeton de 16 caractères qui
donne accès au fichier d'origine — sans limite, ou pour 7 jours. Expiré, il répond 404 comme
un lien inexistant.

**Un groupe reçoit des fichiers, pas des copies.** Le média reste celui de son déposant ; les
membres le voient et téléchargent l'original. Supprimer le groupe ne supprime rien d'autre.

**L'invité arrive directement dans le groupe.** Un lien cliqué sans session est retenu en
session ; après inscription ou connexion, l'utilisateur est installé dans le groupe. Une
invitation en attente garde l'inscription ouverte même quand `DROP_REGISTRATION_OPEN=false`.

**« Enregistrer dans Photos » sur iPhone — un seul bouton, comme WhatsApp.** Un téléchargement
classique finit dans l'app Fichiers. Sur iPhone et iPad, le bouton d'une photo ou d'une vidéo passe
l'original à la feuille de partage d'iOS (`navigator.share` avec le fichier lu en flux) :
« Enregistrer l'image / la vidéo » l'envoie dans Photos, octets inchangés ; la même feuille propose
« Enregistrer dans Fichiers » à qui préfère. Safari n'ouvre la feuille que dans la foulée d'un geste :
si la lecture d'une grosse vidéo a pris trop de temps, le fichier reste prêt en mémoire et le bouton
demande un second appui. Sur chaque carte de la galerie, l'icône de photothèque est posée à côté de
celle du téléchargement : l'une range dans Photos, l'autre dans Fichiers. En repli seulement
(fichier trop gros, type refusé), une photo s'affiche
inline (`/fichiers/{id}/voir`, `/p/{token}/voir`) — un appui long propose « Enregistrer dans
Photos » — et une vidéo part dans Fichiers. Chaque repli laisse une trace dans les journaux
(`Client : photos.*`). Sur Android, le téléchargement suffit : la galerie ramasse d'elle-même le
dossier Téléchargements.

**Session expirée.** `App\Exceptions\RetryExpiredSession` renvoie l'utilisateur sur sa page,
rechargée avec un jeton frais.
