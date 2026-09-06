# Mise en ligne

Drop Picture se déploie sur le VPS qui héberge déjà **pointage**, et réutilise tout ce que
pointage a installé une fois pour toutes (voir son `DEPLOIEMENT.md`, chemin C) : le reverse
proxy Traefik, le registre d'images privé, le monitoring, l'utilisateur `deploiement` et sa
clé SSH. Il ne reste que la pile applicative elle-même à préparer.

```
/srv
├── proxy/          Traefik (pointage)        — déjà en place
├── registry/       registre privé (pointage) — déjà en place
├── monitoring/     Prometheus + Grafana      — déjà en place
├── pointage/       la pile pointage
└── drop-picture/   la pile Drop Picture      ← ce qui suit
```

## 1. Le domaine

Créez un enregistrement DNS **A** pour le sous-domaine de Drop Picture (par exemple
`drop-pictures.vallau.com`) vers l'IP du serveur. Traefik obtiendra le certificat à la
première requête.

## 2. Le dossier de l'application

Sur le serveur, en tant qu'utilisateur `deploiement` :

```bash
sudo mkdir -p /srv/drop-picture && sudo chown deploiement:deploiement /srv/drop-picture
```

Déposez-y un `.env` à partir de [.env.production.example](.env.production.example) et
remplissez chaque valeur marquée « À REMPLIR » : `APP_KEY` (`php artisan key:generate --show`
en local, ou `openssl rand -base64 32` préfixé de `base64:`), `APP_URL`, `APP_DOMAIN`,
`TLS_EMAIL`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`.

Deux réglages méritent un regard :

- **`DROP_QUOTA_BYTES` / `DROP_MAX_FILE_BYTES`** (10 Go / 5 Go). Les originaux s'accumulent
  dans le volume docker `drop-picture_storage` : vérifiez que le disque de la machine peut les
  contenir (`df -h /var/lib/docker`).
- **Les e-mails** (invitations de groupe, mot de passe oublié). Par défaut ils vont dans le
  Mailpit de la pile — lisible par tunnel SSH sur le port **8026** (le 8025 est celui de
  pointage) : `ssh -L 8026:127.0.0.1:8026 vps` puis http://localhost:8026. Pour que les invités
  reçoivent réellement leur e-mail, renseignez un SMTP externe.

## 3. Les secrets GitHub

Les mêmes que pointage, avec la même clé de déploiement : `VPS_HOST`, `VPS_USER`,
`VPS_SSH_KEY`, `VPS_KNOWN_HOSTS`, `REGISTRY_USER`, `REGISTRY_PASSWORD` — et `APP_HEALTH_URL`
avec l'adresse de Drop Picture (`https://drop-pictures.vallau.com`).

## 4. Premier déploiement

Poussez sur `main` : le workflow [deploiement.yml](.github/workflows/deploiement.yml) lance
les tests, construit l'image, la publie sur le registre du VPS par tunnel SSH, dépose
`compose.deploy.yaml` et `redeploie.sh` dans `/srv/drop-picture`, puis bascule.

`redeploie.sh` attend que `https://APP_DOMAIN/up` réponde, puis joue **le test d'intégrité**
dans le conteneur : `php artisan drop:selftest` dépose 3 Mo d'octets aléatoires par morceaux,
les retélécharge par HTTP via un lien de partage et compare les empreintes SHA-256. Le
déploiement n'est déclaré réussi que si elles sont identiques.

À la main, sur le serveur :

```bash
cd /srv/drop-picture && ./redeploie.sh latest
```

## 5. Le monitoring

Rien à installer : Traefik et cAdvisor voient déjà la nouvelle pile. Déposez le tableau de
bord Grafana à côté de ceux de pointage — il est rechargé automatiquement :

```bash
scp monitoring/grafana/dashboards/drop-picture.json vps:/srv/monitoring/grafana/dashboards/
```

Détails dans [monitoring/README.md](monitoring/README.md).

## 6. Vérifier soi-même l'intégrité

Depuis n'importe quelle machine, avec un compte :

```bash
sha256sum ma-photo.jpg            # avant dépôt
# … dépôt par l'interface, puis « Télécharger l'original » …
sha256sum ~/Téléchargements/ma-photo.jpg
```

Les deux empreintes sont identiques, et l'en-tête `X-Checksum-SHA256` de la réponse de
téléchargement porte la même valeur. C'est la promesse du produit.

## Dépannage

**`/up` ne répond pas après la bascule.** `docker compose -f compose.deploy.yaml logs app` :
en général la base n'est pas encore prête (le conteneur réessaie 30 fois) ou une variable du
`.env` manque.

**Les aperçus n'apparaissent pas.** Ils sont calculés par le conteneur `queue` :
`docker compose -f compose.deploy.yaml logs queue`. Un RAW ou un TIFF n'a pas d'aperçu par
construction — l'original, lui, est intact.

**Le dépôt échoue sur un gros fichier.** Le fichier part par morceaux de 8 Mo ; si un morceau
est refusé, c'est la limite d'un intermédiaire (proxy d'entreprise, VPN). `DROP_CHUNK_BYTES`
peut être abaissé sans reconstruire l'image.

**Mémoire.** La pile ajoute ~450 Mo à la machine (MySQL 64 Mo de buffer pool, app, queue,
scheduler, Mailpit). Sur 2 Go avec pointage et le monitoring, c'est serré mais tenable ;
`free -m` et le tableau « Mémoire des conteneurs » de Grafana le confirment.
