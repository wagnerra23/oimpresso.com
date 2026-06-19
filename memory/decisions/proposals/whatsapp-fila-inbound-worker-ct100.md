---
status: proposal
title: Drenar a fila `whatsapp` (inbound) por worker persistente no CT 100 — real-time sem cron
proposed_by: Wagner + Claude
proposed_at: 2026-06-19
relates_to:
  - 0062-separacao-runtime-hostinger-ct100
  - 0060-workers-pesados-ct-app-hostinger
  - 0058-reverb-substituido-por-centrifugo-frankenphp
  - 0288-slo-sli-saude-canal-whatsapp
---

# PROPOSAL — fila `whatsapp` inbound drenada por worker persistente no CT 100

> **Status:** `proposal` — **gate de decisão: medir o efeito do [PR #3022](https://github.com/wagnerra23/oimpresso.com/pull/3022) em produção PRIMEIRO.** Só promover a ADR + implementar se o resíduo de latência importar OU o Hostinger reclamar de CPU/processos do worker quase-contínuo.

## Contexto

Mensagem recebida do cliente demorava ~20s pra aparecer na Caixa (e mídia "uma eternidade"). Causa-raiz (confirmada em prod, read-only 2026-06-19):

- Inbound: `cliente → daemon (CT 100) → webhook (Hostinger) → enfileira ProcessIncomingWebhookJob na fila database `whatsapp` (tabela `jobs` no MySQL Hostinger) → drena → grava DB + publica Centrifugo → Caixa`.
- O drain era um cron `everyMinute` com **`--stop-when-empty`** → o worker morria na fila vazia → maior parte do minuto sem worker → ~30s médio de espera.
- `DownloadMediaJob` está na **mesma fila `whatsapp`** → mídia sofria o mesmo wait.

**PR #3022 (já aberto)** remove `--stop-when-empty` → worker fica vivo os 55s do `--max-time` → wait cai pra ~1-3s (texto **e** mídia). Esse é o conserto imediato, baixo risco.

## Resíduo que a Opção C atacaria

1. ~5s de buraco por minuto (entre o worker sair em 55s e o próximo tick).
2. O worker quase-contínuo faz poll leve do DB no Hostinger (shared hosting, sensível a CPU/processos LSPHP) — era o motivo original do `--stop-when-empty`.

Opção C = **worker persistente, real-time de verdade, e tira o processamento da fila do Hostinger.**

## Viabilidade verificada (2026-06-19, prod read-only)

| Fato | Valor | Implicação |
|---|---|---|
| Hostinger `QUEUE_CONNECTION` | `database` (MySQL) | a fila vive no MySQL **compartilhado** Hostinger↔CT 100 (ADR 0060) |
| Hostinger tailscale | **ausente** (shared hosting) | não dá pra alcançar rede interna do CT 100 |
| Redis do CT 100 (`oimpresso-workers-redis`) | rede docker interna, **não exposto** | Hostinger não alcança; expor Redis publicamente = risco de segurança → **redis-cross-runtime REJEITADO** |
| CT 100 → MySQL Hostinger | já conecta (ADR 0060, IP whitelist) | um worker no CT 100 já consegue ler/gravar a tabela `jobs` |
| CT 100 → Centrifugo | local (mesmo host, ADR 0058) | publish do CT 100 é local/rápido |
| `ProcessIncomingWebhookJob` | grava DB + Centrifugo, **sem arquivo local** (media_url null) | **seguro** rodar no CT 100 |
| `DownloadMediaJob` | mesma fila `whatsapp`, grava `Storage::disk('public')` = **disco local** Hostinger | **NÃO** pode rodar no CT 100 sem storage compartilhado |
| `FILESYSTEM_DISK` prod | `local` (sem S3) | mídia é servida do disco do Hostinger |

**Conclusão:** a abordagem certa **não é Redis** — é um worker `queue:work database` no CT 100 lendo a fila do MySQL já compartilhado. Mas a mídia (disco local Hostinger) bloqueia mover a fila inteira.

## Variantes

### C-split (recomendada se a Opção C for adiante)
- Separar a mídia numa fila própria (ex. `whatsapp-media`): mudar o `onQueue()` de `DownloadMediaJob` (+ `SendMediaJob`/`RetryFailedMediaDownloadsJob`) para `whatsapp-media`.
- **Hostinger** mantém um cron drenando `whatsapp-media` (mídia continua no disco local — onde é servida).
- **CT 100** ganha um serviço novo no `docker/oimpresso-workers/docker-compose.yml`: `queue:work database --queue=whatsapp --tries=3` **persistente** (`restart: unless-stopped`, sem `--max-time`). Drena a persistência de mensagem (texto) em tempo real do MySQL compartilhado; Centrifugo é local.
- Dedup: fila `database` usa lock de linha (`FOR UPDATE SKIP LOCKED`) → manter o cron do Hostinger como **fallback** não causa processamento duplo.

### C-s3 (ideal de longo prazo, maior)
- Migrar storage de mídia pra S3/compartilhado (driver `s3` já existe em `config/filesystems.php`). Então a fila `whatsapp` **inteira** roda no CT 100 (texto + mídia), e a mídia ganha serving/CDN melhor.
- Custo: setup S3 + migração das mídias existentes + ajuste de URLs. Projeto à parte.

## Riscos / pontos de atenção
- **Código atualizado no CT 100:** o worker do CT 100 roda de um `git clone` próprio (ADR 0060) — garantir que o deploy atualiza esse checkout, senão processa com código stale.
- **Double-processing:** mitigado por `SKIP LOCKED`; ainda assim, ter 2 dreners (Hostinger fallback + CT 100) precisa de teste.
- **Observabilidade:** OTel/Centrifugo do CT 100 — confirmar que o span e o publish saem certos de lá.
- **Incidente de referência:** worker `whatsapp` órfão já deixou a tela vazia (2026-05-28, PR #1825) — qualquer mudança no drain do inbound é Tier-sensível; rollout additivo + fallback.
- **Rollback:** additivo. Subir o worker CT 100 sem remover o cron Hostinger; só remover o cron depois de provado. Reverter = derrubar o serviço do compose.

## Critério de aceite (se promovido)
- Latência inbound p95 < 2s medida ponta-a-ponta (cliente manda → aparece na Caixa).
- Zero mensagem órfã / duplicada em 7 dias.
- CPU/processos do Hostinger sem regressão (idealmente melhor, já que sai do Hostinger).

## Recomendação
**Não implementar ainda.** Mergear o #3022, medir a latência real em prod por alguns dias. Se ~1-3s já resolve a dor do Wagner e o Hostinger não reclama de CPU, **a Opção C vira opcional/desnecessária**. Se o resíduo importar, seguir **C-split** (mídia fica no Hostinger, persistência de mensagem vai pro CT 100); **C-s3** fica como evolução maior de storage.
