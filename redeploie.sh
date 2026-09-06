#!/usr/bin/env bash
#
# Bascule de la pile Drop Picture sur le serveur — le même script pour tous :
# la CI l'appelle à chaque déploiement, et on le lance à la main pour
# recharger un .env modifié ou revenir sur une image précise.
#
#   ./redeploie.sh                 relance avec l'image en cours (recharge le .env)
#   ./redeploie.sh latest          tire puis bascule sur la dernière image publiée
#   ./redeploie.sh <sha>           tire puis bascule sur l'image de ce commit
#   ./redeploie.sh <référence>     référence complète, par exemple localhost:5000/drop-picture:abc123
#
# Le script vit dans le dépôt et arrive sur le serveur avec chaque déploiement :
# ne le modifiez pas sur place, il serait écrasé.

set -euo pipefail
cd "$(dirname "$0")"

REGISTRY_IMAGE='localhost:5000/drop-picture'
COMPOSE='docker compose -f compose.deploy.yaml'

# ---------------------------------------------------------------- l'image
REFERENCE="${1:-}"

if [ -z "$REFERENCE" ]; then
    # Sans argument : l'image du conteneur qui tourne. Une pile jamais lancée
    # retombe sur « latest », que la CI publie à chaque déploiement.
    IMAGE=$(docker inspect drop-picture-app-1 --format '{{.Config.Image}}' 2>/dev/null \
        || echo "${REGISTRY_IMAGE}:latest")
    echo "→ Relance avec l'image en place : ${IMAGE}"
else
    case "$REFERENCE" in
        *:*) IMAGE="$REFERENCE" ;;
        *) IMAGE="${REGISTRY_IMAGE}:${REFERENCE}" ;;
    esac
    echo "→ Bascule demandée vers : ${IMAGE}"

    # Le registre exige les identifiants de l'utilisateur qui a fait
    # « docker login » : si le tirage échoue mais que l'image est déjà sur la
    # machine, on continue avec elle plutôt que d'échouer pour rien.
    if ! DROP_IMAGE="$IMAGE" $COMPOSE pull app 2>/dev/null; then
        if docker image inspect "$IMAGE" >/dev/null 2>&1; then
            echo "  (tirage impossible — identifiants du registre absents pour cet utilisateur ; l'image est déjà locale, on continue)"
        else
            echo "✗ Impossible de tirer ${IMAGE} et elle n'existe pas localement." >&2
            echo "  Connectez-vous au registre (docker login localhost:5000) ou relancez en tant qu'utilisateur « deploiement »." >&2
            exit 1
        fi
    fi
fi

# ---------------------------------------------------------------- la bascule
DROP_IMAGE="$IMAGE" $COMPOSE up -d --remove-orphans

# Les couches des anciennes images s'accumulent vite sur un petit disque.
docker image prune -f >/dev/null

# ---------------------------------------------------------------- la santé
# Le conteneur applique ses migrations avant d'écouter : on lui laisse du temps.
APP_DOMAIN=$(grep -E '^APP_DOMAIN=' .env | cut -d= -f2- | tr -d '"' || true)

if [ -n "$APP_DOMAIN" ]; then
    echo "→ Attente de la réponse de https://${APP_DOMAIN}/up…"
    if ! curl --fail --silent --show-error --insecure \
        --retry 12 --retry-delay 5 --retry-all-errors --max-time 30 \
        --resolve "${APP_DOMAIN}:443:127.0.0.1" \
        "https://${APP_DOMAIN}/up" >/dev/null; then
        echo "✗ L'application ne répond pas après la bascule — voir : $COMPOSE logs app" >&2
        exit 1
    fi
    echo "✓ L'application répond."
else
    echo "✓ Pile relancée (APP_DOMAIN absent du .env : vérification de santé sautée)."
fi

# ---------------------------------------------------------------- l'intégrité
# Le critère d'acceptation n°1 : un fichier déposé par morceaux, puis téléchargé
# par HTTP, doit revenir octet pour octet. Joué dans le conteneur, sur la pile
# qui vient de basculer.
echo "→ Vérification de l'intégrité dépôt → téléchargement…"
DROP_IMAGE="$IMAGE" $COMPOSE exec -T app php artisan drop:selftest --base=http://127.0.0.1
echo "✓ Bascule terminée."
