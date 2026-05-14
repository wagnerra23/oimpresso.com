# Prometheus + Alertmanager — WhatsApp Baileys (US-WA-085)

Regras de alerta + template Alertmanager pro daemon Baileys CT 100.

## Estrutura

```
infra/prometheus/
├── alerts/
│   └── whatsapp.yml          # 10 alert rules (Tier 0/1/2/3 + drift)
├── alertmanager.yml.example  # Route → Slack (#oimpresso-alerts / -oncall)
└── README.md                 # este arquivo
```

## Provisionamento

### 1. Prometheus — scraping daemon CT 100

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'whatsapp-baileys-daemon'
    scrape_interval: 15s
    scrape_timeout: 10s
    static_configs:
      - targets: ['ct100.oimpresso.com:9091']  # ou IP interno
        labels:
          env: 'live'
          service: 'whatsapp-baileys'

rule_files:
  - 'alerts/whatsapp.yml'

alerting:
  alertmanagers:
    - static_configs:
        - targets: ['alertmanager:9093']
```

### 2. Validar rules

```bash
promtool check rules infra/prometheus/alerts/whatsapp.yml
promtool check config infra/prometheus/alertmanager.yml.example
```

### 3. Reload Prometheus

```bash
curl -X POST http://prometheus:9090/-/reload
```

## Alert taxonomy

| Tier | Alert | Threshold | Action |
|------|-------|-----------|--------|
| **0 critical** | WhatsappZombiesDetected | >0 in 5m | Page oncall, runbook whatsapp-doctor |
| **0 critical** | WhatsappBansCrossTenant | >0 in 1h | Page oncall, congelar envios programados |
| **0 critical** | WhatsappDaemonDown | up==0 for 2m | Page oncall, runbook ct100-rebuild |
| **1 warning** | WhatsappSessionDisconnected | state==0 for 10m | Notificar cliente reconectar |
| **1 warning** | WhatsappQrStuckPending | state==0.5 for 30m | Cliente esqueceu QR |
| **2 warning** | WhatsappMessageLagHigh | p95 >5s for 10m | Investigar rede CT 100 / Meta rate-limit |
| **2 warning** | WhatsappWebhookHighFailureRate | >5% failed for 5m | Hostinger queue saturada |
| **2 warning** | WhatsappWebhookLatencyHigh | p95 >10s for 10m | PHP-FPM saturado |
| **3 warning** | WhatsappQueueDepthHigh | pending >1000 for 5m | Worker travado / escalar pool |
| **drift** | WhatsappWebhookDropDetected | recv > dispatched for 15m | Loss silenciosa, investigar |

## Inhibit rules

- `WhatsappDaemonDown` silencia TODOS alerts WhatsApp (sem daemon não há métrica → derivados são ruído)
- `WhatsappBansCrossTenant` silencia `WhatsappQrStuckPending` no mesmo instance_id

## Secrets

Slack webhook URL fica em **Vaultwarden** item `slack-webhook-alertmanager`. NÃO commitar valor real em `alertmanager.yml`. Use Docker secrets ou file_sd_config.

## Test local

```bash
# Trigger manual via amtool
amtool alert add \
  alertname=WhatsappZombiesDetected \
  severity=critical \
  surface=whatsapp \
  instance_id=ch-test-zombie \
  --annotation=summary='Test alert' \
  --alertmanager.url=http://localhost:9093
```

## Referências

- Métricas daemon: `Modules/Whatsapp/daemon-node/src/observability/metrics.ts`
- Dashboard Grafana: `infra/grafana/dashboards/whatsapp-baileys.json`
- Runbook SRE: `.claude/agents/whatsapp-doctor.md`
