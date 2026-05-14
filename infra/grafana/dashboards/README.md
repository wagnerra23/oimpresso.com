# Grafana Dashboards — oimpresso

## Setup

Dashboards JSON commitados aqui (`infra/grafana/dashboards/*.json`) são importados manualmente no Grafana CT 100 ou via provisioning.

### Importar 1 dashboard

1. Acesse Grafana CT 100 (TBD URL após US-WA-085)
2. **+ → Import** → cole o JSON OU upload do arquivo
3. Selecione datasource Prometheus
4. **Import**

### Provisioning automático (recomendado pós-Onda 2)

Adicione em `/etc/grafana/provisioning/dashboards/oimpresso.yaml` no CT 100:

```yaml
apiVersion: 1
providers:
  - name: 'oimpresso'
    orgId: 1
    folder: 'oimpresso'
    type: file
    disableDeletion: false
    editable: true
    updateIntervalSeconds: 60
    options:
      path: /var/lib/grafana/dashboards/oimpresso
```

E sincronize via cron:

```bash
*/15 * * * * rsync -avz git/oimpresso/infra/grafana/dashboards/ /var/lib/grafana/dashboards/oimpresso/
```

## Dashboards disponíveis

| Arquivo | UID | Descrição |
|---|---|---|
| [whatsapp-baileys.json](whatsapp-baileys.json) | `whatsapp-baileys-ct100` | Daemon Baileys CT 100 — 8 painéis (session state, send/recv rate, latency p50/p95/p99, bans cross-tenant, zombies, webhook success, source SHA) |

## Métricas Prometheus expostas

Daemon CT 100 `/metrics` endpoint (port 3000 internal, via Traefik):

- `whatsapp_baileys_session_state{instance_id,business_id}` gauge — 1=connected · 0.5=qr_required · 0=down
- `whatsapp_baileys_session_age_seconds{instance_id}` gauge
- `whatsapp_baileys_message_lag_ms_bucket{instance_id,le}` histogram
- `whatsapp_baileys_send_total{instance_id,status,kind}` counter
- `whatsapp_baileys_recv_total{instance_id}` counter
- `whatsapp_baileys_ban_detected_total{instance_id}` counter
- `whatsapp_baileys_zombies_detected_total{instance_id}` counter (PR #821)
- `whatsapp_baileys_webhook_dispatch_total{event,outcome}` counter
- `whatsapp_baileys_webhook_latency_ms_bucket{event,le}` histogram
- `whatsapp_baileys_daemon_source_sha` info — built from `git rev-parse HEAD:Modules/Whatsapp/daemon-node/src` (PR #825)

## Referências

- [US-WA-081 SPEC](../../memory/requisitos/Whatsapp/SPEC.md) — task original
- [memory/sessions/2026-05-14-arte-wa-structure.md](../../memory/sessions/2026-05-14-arte-wa-structure.md) — origem (dogfood arch-arte nota 71/100)
- [.claude/agents/whatsapp-doctor.md](../../.claude/agents/whatsapp-doctor.md) — runbook ops link no dashboard
