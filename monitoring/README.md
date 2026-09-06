# Monitoring

Drop Picture se branche sur la pile d'observation déjà en place sur le VPS — celle
de **pointage** (`compose.monitoring.yaml` : Prometheus, Grafana, node-exporter,
cAdvisor). Rien à ajouter côté collecte :

- **Traefik** compte déjà les requêtes, codes de réponse, latences et débits de
  chaque service qu'il route. Drop Picture y apparaît sous le nom
  `drop-picture@docker` dès sa première requête.
- **cAdvisor** mesure la consommation de chaque conteneur : `drop-picture-app-1`,
  `drop-picture-queue-1`, `drop-picture-scheduler-1`, `drop-picture-mysql-1`.
- L'application elle-même n'expose pas de métriques — comme pointage, la santé se
  lit sur `/up`, sondée par la CI et par `redeploie.sh`.

## Tableau de bord

`grafana/dashboards/drop-picture.json` est un tableau de bord provisionné : requêtes,
erreurs, latence, **débit entrant (les dépôts) et sortant (les téléchargements)**,
puis processeur et mémoire des conteneurs de la pile.

Grafana recharge les tableaux du dossier `/etc/grafana/dashboards` quand ils
changent sur le disque. Pour l'installer, il suffit de le déposer à côté de ceux de
pointage :

```bash
scp monitoring/grafana/dashboards/drop-picture.json vps:/srv/monitoring/grafana/dashboards/
```

Il apparaît dans le dossier « Serveur » de Grafana en moins d'une minute.
