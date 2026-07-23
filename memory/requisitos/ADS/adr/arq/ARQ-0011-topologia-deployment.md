---
id: requisitos-ads-adr-arq-arq-0011-topologia-deployment
slug: ARQ-0011-topologia-deployment
title: "ADS — Topologia de deployment: Hostinger app + CT 100 Proxmox daemon"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0001, ARQ-0002, ARQ-0003]
---

# ARQ-0011 — Topologia de deployment do ADS

## Contexto

O projeto oimpresso tem dois ambientes de produção com responsabilidades distintas
(formalizado em CLAUDE.md §4 e auto-mems de infra):

- **Hostinger** — shared hosting do app web. Roda Laravel + MySQL. Daemons proibidos.
- **CT 100 Proxmox** (177.74.67.30) — máquina de daemons. Já hospeda mcp.oimpresso.com,
  Meilisearch, Traefik, Portainer, Vaultwarden, Centrifugo (planejado).

A máquina pessoal de Wagner (D:\oimpresso.com) é ambiente de desenvolvimento, não produção.
Não pode hospedar daemon 24/7 — precisa estar ligada para funcionar.

O Brain A daemon do ADS é um processo Node.js de longa duração que precisa rodar 24/7,
opcionalmente com Ollama (LLM local de 14B parâmetros, ~14GB RAM). Isso elimina Hostinger
imediatamente como destino do daemon.

## Decisão

**Cada componente do ADS é colocado no ambiente que melhor corresponde ao seu papel:**

### Hostinger (app web Laravel)

| Componente | Por quê |
|---|---|
| `Modules/ADS/` (código PHP completo) | Acoplado ao Laravel, namespace, providers, autoload |
| 5 tabelas `mcp_dual_brain_*` | MySQL principal está no Hostinger; Copiloto/MCP usam o mesmo DB |
| API endpoint `POST /api/ads/route` | Validação + roteamento + persistência exigem Laravel |
| API endpoint `GET /api/ads/recent-errors` (novo) | Expõe slice do laravel.log para CT 100 consumir |
| API endpoint `GET /api/ads/recent-commits` (novo) | Expõe HEAD recente do git para CT 100 consumir |
| UI `/ads/admin/decisoes` (Inertia + React) | Parte da SPA do app oimpresso.com |
| Cron `*/5 * * * * php artisan ads:process-brain-b` | Precisa do Laravel CLI + DB para chamar Claude API |

### CT 100 Proxmox (daemons + IA local)

| Componente | Por quê |
|---|---|
| Brain A Daemon (Node.js, systemd) | Daemon 24/7 — Hostinger não permite |
| Ollama qwen2.5-coder:14b | Precisa de ~14GB RAM (Hostinger não tem; CT 100 tem 128GB) |
| OllamaClient → `localhost:11434` | Daemon e Ollama no mesmo host = latência <100ms |
| Watchers HTTP poll dos endpoints Hostinger | Substituem os watchers de filesystem da v1 |

### Externo

| Componente | Onde |
|---|---|
| Claude API (Sonnet/Opus) | `api.anthropic.com` — chamado pelo cron no Hostinger |
| GitHub webhook | Já configurado em `mcp.oimpresso.com` para sync de docs |

### Máquina pessoal Wagner

Não roda nada do ADS em produção. É ambiente de:
- Desenvolvimento + testes Pest
- Claude Code para implementação
- Smoke test e2e antes de cada deploy

## Refator obrigatório (consequência desta decisão)

A v1 dos watchers em `scripts/dual-brain/watchers/` foi escrita para rodar **na mesma máquina
do app** (lê `git log` via `execSync` e `tail laravel.log` via `fs`). Isso não funciona quando
daemon está em CT 100 e app está em Hostinger.

### Substituições necessárias

| Watcher v1 (mesma máquina) | Watcher v2 (HTTP poll Hostinger) |
|---|---|
| `git rev-parse HEAD` via execSync | `GET /api/ads/recent-commits?since=<sha>` |
| `tail laravel.log` via fs.readSync | `GET /api/ads/recent-errors?since=<id>` |
| (futuro) métricas DB via mysql client | `GET /api/ads/internal/metrics` |

### Novos endpoints Laravel (Hostinger)

```php
// Routes/api.php (autenticado pela mesma ADS_API_KEY)
GET /api/ads/recent-commits?since=<sha>&limit=20
  → consulta git log via Laravel; retorna array de {sha, subject, files, committed_at}

GET /api/ads/recent-errors?since=<id>&limit=50
  → tail das últimas N linhas do laravel.log com level >= ERROR
  → retorna array de {id, level, message, stack_short, logged_at}

GET /api/ads/internal/metrics?since=<timestamp>
  → snapshot de copiloto_memoria_metricas para detecção de anomalia
```

Com esses 3 endpoints, o daemon no CT 100 fica desacoplado do filesystem local
e pode operar de qualquer host com acesso HTTPS ao Hostinger.

## Comunicação entre os ambientes

```
CT 100 (Brain A)                         Hostinger (app)
       │                                       │
       │ poll a cada 30s                       │
       ├──── GET /recent-commits ──────────────┤
       │ ◄──── 200 [{sha,subject,files}…] ─────┤
       │                                       │
       │ poll a cada 5s                        │
       ├──── GET /recent-errors ───────────────┤
       │ ◄──── 200 [{level,message}…] ─────────┤
       │                                       │
       │ para cada evento detectado            │
       ├──── POST /api/ads/route ──────────────┤
       │ ◄──── 200 {decision_id, dest…} ───────┤
       │                                       │
                                               │ a cada 5min via cron
                                               ├─→ artisan ads:process-brain-b
                                               │  → Anthropic API
                                               │  ◄─ JSON instruction
                                               │  → grava em
                                               │     mcp_dual_brain_decisions
```

Tudo via HTTPS. Auth única: `ADS_API_KEY` (Bearer) — mesma chave nos dois ambientes.

## Por que NÃO replicar o DB no CT 100

Considerado e rejeitado:
- Replicação MySQL Hostinger → CT 100 adiciona complexidade operacional sem ganho
- O daemon no CT 100 não precisa ler o DB; precisa SUBMETER eventos via HTTP
- Latência HTTP Hostinger ↔ CT 100 (Brasil) é ~80–150ms, irrelevante para poll a cada 30s
- Manter "DB único na Hostinger" mantém a fonte da verdade simples e auditável

## Por que NÃO Brain A na máquina Wagner

Considerado e rejeitado:
- Wagner desliga a máquina à noite/finais de semana → sistema fica idle
- Triage de erros que aconteceram com Wagner offline ficaria atrasada por horas
- O CT 100 já existe e já é o lugar canônico para daemons (ADR 0058 Centrifugo + skill `proxmox-docker-host`)

## Consequências

**Positivas:**
- Cada ambiente faz o que faz melhor; sem violar restrições do Hostinger
- Daemon 24/7 sem depender da máquina pessoal de Wagner
- Ollama com 14GB de RAM disponível (qualidade de triage muito superior ao regex)
- Auditoria centralizada no MySQL Hostinger (uma fonte da verdade)

**Negativas:**
- Refator dos watchers v1 → v2 antes de subir produção (estimativa: 4 horas)
- Acoplamento via HTTPS exige Hostinger sempre acessível pelo CT 100 (já é o caso)
- ADS_API_KEY precisa estar nos dois `.env` — gerenciar via Vaultwarden
