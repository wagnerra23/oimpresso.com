# ADR 0060 — Tudo na rede interna (Proxmox empresa), bye Hostinger

**Status:** ✅ Aceita (decisão Wagner 2026-04-30)
**Data:** 2026-04-30
**Decisores:** Wagner [W]
**Tags:** infra · proxmox · hostinger · migracao · soberania-dados · lgpd

Supersede parcialmente: ADR 0042 (Reverb em Hostinger — agora CT), ADR 0044 (Meilisearch em Hostinger — agora CT 100), uso geral do Hostinger como app primário.

Relacionada: [ADR 0042](0042-infra-empresa-padrao.md) (infra empresa padrão), [ADR 0053](0053-mcp-server-governanca-como-produto.md) (MCP em CT 100), [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) (Centrifugo em CT 100), [ADR 0059](0059-governanca-memoria-estilo-anthropic-team.md) (governança).

---

## Contexto

Wagner formalizou em 30-abr: *"precisa rodar tudo na maquina narede interna, no proxmox. nada na hostinger."*

### Por que mudar agora

Sintomas acumulados em 30-abr que validaram a decisão:

1. **`shell_exec` disabled** no Hostinger shared — `IndexarMemoryGitParaDb` precisou fix `function_exists()`
2. **Memory limit Killed** o `copiloto:eval --persist` (50 perguntas) hoje — 256MB shared não chega
3. **SSH flaky** — connection timed out 3-5× por sessão; receita warm-up não sempre funciona
4. **Rate-limit** silencioso → 429 Too Many Attempts no `/api/cc/ingest` durante watcher
5. **PHP-FPM workers** insuficientes pra MCP/SSE persistente — motivo de existir `mcp.oimpresso.com` no CT 100 já
6. **MySQL Hostinger** tem `connect_timeout` curto + sem replication slave nativa
7. **Custo:** plano Hostinger + bandwidth + addons vs hardware empresa **já pago e ocioso**

### O que já está na rede interna (Proxmox empresa)

`reference_proxmox_empresa`: **128GB RAM / 2TB SSD / IP fixo 177.74.67.30 / 1Gb LAN <1ms**.

CT 100 docker-host (Debian 12 LXC) já tem:
- ✅ Traefik v3.6 (TLS Let's Encrypt auto)
- ✅ Portainer (Docker UI)
- ✅ Vaultwarden (cofre senhas)
- ✅ Meilisearch v1.10.3 (vetor + BM25)
- ✅ MCP server `mcp.oimpresso.com` FrankenPHP (ADR 0053)
- 🔲 Centrifugo (planejado ADR 0058)
- 🔲 **App Laravel principal** ← MIGRAÇÃO PROPOSTA
- 🔲 **MySQL primary** ← MIGRAÇÃO PROPOSTA

---

## Decisão

**Migrar 100% do oimpresso (app Laravel + MySQL + filesystem) pra CT em Proxmox empresa em até 60 dias.** Hostinger vira read-only backup ou cancelado depois de cutover validado.

### Stack alvo (Proxmox empresa)

```
[Internet]
    ↓ DNS oimpresso.com → 177.74.67.30
[Roteador empresa: 192.168.0.1]
    ↓ port-forward 443 → CT 100
[CT 100 docker-host]
    ├── Traefik (TLS, routing)
    ├── oimpresso-app (FrankenPHP + Laravel 13.6)
    ├── mcp-oimpresso (MCP server, atual)
    ├── meilisearch-oimpresso (atual)
    ├── centrifugo-oimpresso (realtime, ADR 0058)
    └── redis-oimpresso (cache + queue + pub/sub)

[CT 101 mysql-primary] ← NOVO
    └── MySQL 8 primary

[CT 102 mysql-replica] ← NOVO opcional
    └── MySQL 8 read-replica (HA + backup)
```

### O que NÃO muda

- **GitHub** continua source-of-truth (push + webhook)
- **Cloudflare/Hostinger DNS** pode continuar (só DNS é barato)
- **Auto-mems** locais por dev continuam local

### Fases de migração

| Fase | Conteúdo | Duração | Cycle | Risco |
|---|---|---|---|---|
| **F0** | Audit completo (este ADR) — inventário do que migra | 0.5d | atual | baixo |
| **F1** | CT mysql-primary criado + MySQL 8 instalado + import schema vazio | 1d | 02 | baixo |
| **F2** | mysqldump Hostinger → import staging em CT 101 — validação shape | 0.5d | 02 | baixo |
| **F3** | Container `oimpresso-app` (FrankenPHP+Laravel) em CT 100 com `.env` apontando CT 101 | 2d | 02 | médio |
| **F4** | Smoke completo: fluxo Larissa, MCP, Copiloto chat, dashboard, login UPos | 1d | 02 | médio |
| **F5** | Replicação contínua Hostinger MySQL → CT 101 (24h+) | 0.5d | 02 | baixo |
| **F6** | **Cutover** — DNS swap oimpresso.com → 177.74.67.30, Hostinger read-only | 0.5d | 03 | **alto** |
| **F7** | Hardening (firewall, fail2ban, snapshots Proxmox, backup S3) | 1d | 03 | baixo |
| **F8** | Hostinger cancelar OU manter como cold backup | 0d | 04+ | n/a |

**Total: ~7 dias úteis distribuídos em Cycle 02-04.**

### Inventário do que migra

| Item Hostinger | Destino Proxmox | Notas |
|---|---|---|
| App Laravel (`~/domains/oimpresso.com/public_html`) | container `oimpresso-app` em CT 100 | FrankenPHP (já dominado) |
| MySQL `u906587222_oimpresso` | CT 101 mysql-primary | mysqldump + import |
| Storage `storage/` (logs, cache, sessions) | volume Docker em CT 100 | bind mount |
| Cron Hostinger (scheduler 23:55) | systemd timer no CT 100 OU Laravel scheduler container | docker compose com command schedule:work |
| `.env` Hostinger | `.env` no container (Vaultwarden source-of-truth) | rotacionar todos os secrets |
| SSH key `id_ed25519_oimpresso` | só pra cancelamento, depois descartar | n/a |
| Webhook GitHub | atualizar URL `https://oimpresso.com/api/mcp/sync-memory` (DNS resolve novo IP automático após cutover) | sem mudança no GitHub |

### O que rebatiza/abandona em retrospecto

- **`mcp.oimpresso.com` no CT 100** continua, mas vira **redundante** com app principal lá — pode unificar (1 container app + MCP) ou manter separado pra isolation
- **`oimpresso.com/api/cc/ingest`** que criei hoje vai pra CT junto com app
- **Watcher cc-watcher** muda `MCP_URL` default pra `mcp.oimpresso.com/api/cc/ingest` (já em CT)
- **CI workflows** (`deploy.yml`/`quick-sync.yml`) precisam novos secrets: `SSH_HOST=177.74.67.30`, `SSH_USER=root@ct100` (ou tunnel), `SSH_PORT=22XXX` (port forward)

---

## Stack IA no Proxmox (Cenário C híbrido escalável)

Wagner pediu 2026-04-30: "pode adicionar a parte da ia no proxmox / olha os recursos e decida".

### Recursos disponíveis (confirmados)

| Recurso | Específico | Capacidade IA |
|---|---|---|
| CPU | **Xeon E5-2680v4** 14C/28T (2.4-3.3 GHz) | 5-15 tok/s LLM 8B em CPU |
| RAM | **128 GB** | 3-5 LLMs concorrentes ou 1 LLM + N embedders |
| Disco | **2 TB SSD** | sobra (modelos 10-20 GB cada) |
| **GPU** | **❌ nenhuma** | sem inference acelerada |
| Latência | LAN <1ms | embedder local <50ms |

### Decisão: 3 níveis pra IA

**Nível 1 — Embedder local (Cycle 02 — sim, vai pro Proxmox)**
- **Stack:** Ollama + Nomic Embed Text v1.5 (768 dim) ou BGE-M3 (1024 dim multilingual)
- **Onde:** novo container `ollama-embedder` no CT 100 (porta 11434 interna)
- **Substitui:** OpenAI `text-embedding-3-small` (1536 dim) → reembedding de 11+ memórias
- **Migração:** schema `copiloto_memoria_facts.embedding` BLOB compatível (só muda dim)
- **Economia:** zero custo recorrente embedding (era R$ 0,02/1M tokens, frequente)
- **Trade-off:** qualidade comparable em PT-BR (BGE-M3 multi superior pra 3-small em low-resource langs)

**Nível 2 — LLM principal continua OpenAI (Cycle 02 — externo, mas via API)**
- **Por quê:** Larissa real-time exige qualidade gpt-4o-mini-grade + latência <2s
- **Self-host LLM 8B em CPU** = 3-5 tok/s = **inaceitável** pra resposta de chat (50 tokens = 10-15s só pra LLM)
- **Custo:** gpt-4o-mini = R$ 0,75/1M input, R$ 3/1M output → ~R$ 0,01 por conversa Larissa real
- **Privacidade:** prompt já é PII-redacted (CPF/CNPJ/email/tel mascarados antes de enviar pra OpenAI, ADR 0030)

**Nível 3 — LLM self-host pra batch / eval (Cycle 04+ — escalada futura)**
- **Stack:** Ollama + Llama 3.1 8B Instruct (~6 GB RAM) ou Mistral 7B v0.3
- **Onde:** novo container `ollama-llm` no CT 100 (porta 11434 separada)
- **Casos de uso:**
  - `copiloto:eval --persist` (50 perguntas batch, 5 min OK rodando em CPU)
  - LLM-as-judge pra RAGAS `faithfulness` + `answer_relevancy` (atualmente caro com OpenAI)
  - Background jobs: `ConversationSummarizer`, `ProfileDistiller`, `IndexarMemoryGitParaDb` PII-judge
  - Fallback se OpenAI cair
- **Economia eval:** R$ 5-10/run baseline → R$ 0 (CPU é grátis)
- **Trade-off:** qualidade ~70% gpt-4o-mini (aceitável pra batch, não pra Larissa)

### Containers Docker IA propostos

```yaml
ollama-embedder:
  image: ollama/ollama:latest
  command: serve
  volumes: [ollama-embedder-data:/root/.ollama]
  ports: ["11434:11434"]  # interno apenas, sem Traefik público
  deploy:
    resources:
      limits: { memory: 4G, cpus: '4' }
  # Após start: docker exec ollama-embedder ollama pull nomic-embed-text

ollama-llm:    # Cycle 04+, opt-in
  image: ollama/ollama:latest
  command: serve
  volumes: [ollama-llm-data:/root/.ollama]
  ports: ["11435:11434"]  # porta diferente
  deploy:
    resources:
      limits: { memory: 16G, cpus: '12' }
  # docker exec ollama-llm ollama pull llama3.1:8b
```

### Implicações pro código Laravel

- **`Modules/Copiloto/Services/Memoria/MeilisearchDriver`** ganha config `embedder`:
  - Hoje: `embedders.openai.apiKey` + `text-embedding-3-small`
  - Cycle 02: opção `embedders.local.url=http://ollama-embedder:11434/api/embeddings`
- **A/B test embedder** durante 2 semanas pré-cutover — comparar Recall@3 OpenAI vs local no gabarito
- **Se Recall@3 local < 0.05 abaixo do OpenAI:** mantém OpenAI no Cycle 02, adia local pra Cycle 03
- **Se Recall@3 local ≥ OpenAI:** migra 100%

### Custo estimado (mensal)

| Item | Hoje (Hostinger + OpenAI) | Pós-migração (CT + Híbrido) |
|---|---|---|
| Hostinger Cloud Startup | R$ 79/mês | R$ 0 |
| OpenAI embeddings | ~R$ 5-15/mês | R$ 0 (local) |
| OpenAI gpt-4o-mini chat | ~R$ 30-100/mês | mantido (R$ 30-100) |
| OpenAI eval (50 perguntas/run) | ~R$ 5/run × 4 runs/mês = R$ 20 | R$ 0 (Ollama 8B local) |
| **Total mensal** | **R$ 134-214** | **R$ 30-100** |

Economia: **~50-65%** + ZERO surpresa de bandwidth/billing.

---

## Justificativa

1. **Soberania de dados (LGPD)** — dado de Larissa/ROTA LIVRE em servidor físico empresa BR, não cloud terceira
2. **Performance** — LAN <1ms vs Hostinger 30-50ms; sem `shell_exec` disabled; sem 256MB cap
3. **Custo** — hardware empresa **ocioso**; cancelar Hostinger = R$ economizado/mês
4. **Confiabilidade** — sem SSH flaky, sem 429 silencioso, sem Killed inesperado
5. **Capacidades** — daemon persistente possível (Centrifugo, MCP/SSE, queue worker) sem hack
6. **Backup nativo** — Proxmox snapshot ZFS > backup Hostinger limitado
7. **Custo IA / experiments** — rodar `copiloto:eval` 50× ao dia é trivial (RAM/CPU sobra)

---

## Trade-offs aceitos

| Trade-off | Mitigação |
|---|---|
| **SLA empresa < SLA datacenter Hostinger** | Snapshot Proxmox + cold backup S3; se queda >1h, restore |
| **DNS sob controle Cloudflare** (rota de cancelamento) | Manter conta Hostinger 6 meses pós-cutover por segurança |
| **Cutover é momento crítico** | Replicação 24h antes; janela domingo 02h-04h BRT; rollback DNS reverso 5min |
| **Wagner é único admin Proxmox** | Felipe ganha acesso root (com alerta MFA); SLA de team-coverage |
| **Internet empresa pode cair** | Plano B Hostinger ainda ligado em standby; DNS TTL baixo permite swap rápido |

---

## Pegadinhas operacionais detectadas

- **`shell_exec`/`exec` work** no CT (sem `disable_functions`) — `IndexarMemoryGitParaDb::lerGitSha()` volta a funcionar nativo
- **MySQL 8** vs 5.7 do Hostinger — checar features deprecadas (`utf8mb3` → `utf8mb4`)
- **Reforçar firewall** CT antes de abrir 443 público — Hostinger tinha defaults; Proxmox NÃO tem
- **Backup pre-cutover** OBRIGATÓRIO — `mysqldump --all-databases --routines --triggers` + filesystem tar
- **Test smoke completo** ROTA LIVRE biz=4 antes de DNS swap — Larissa NÃO pode ver downtime

---

## O que paro de fazer agora

1. **❌ Não adicionar mais nada novo no Hostinger** — qualquer feature nova vai direto pro CT
2. **❌ Não otimizar Hostinger** — investimento em algo que vai morrer
3. **✅ Continuo entregando features** — apenas no destino certo (CT)
4. **✅ Manter Hostinger funcional** até cutover (não quebrar pra Larissa)

### Decisões pra próximas sessões

- **Próximo deploy** = container Docker no CT 100 (não SSH Hostinger)
- **Próxima migração** = pula Hostinger, vai direto MySQL primary CT
- **Monitoring novo** = Proxmox metrics + Prometheus no CT, não Hostinger logs
- **Workflows GitHub** ganham 2º job opcional `deploy-to-ct100.yml` (SSH via port-forward)

---

## Métricas de sucesso

| Métrica | Alvo Cycle 02 | Alvo pós-cutover |
|---|---|---|
| App Laravel respondendo no CT | ✅ smoke | 100% rotas funcionais |
| MySQL primary em CT | ✅ schema importado | replica < 1s lag |
| Custo Hostinger | continua | R$ 0 (cancelado) |
| Latência média p95 | continua | -50% (LAN) |
| Larissa nota diferença | n/a | 0 reclamações |
| `copiloto:eval --persist` completa | continua Killed | < 10 min sem fail |
| Backup automático Proxmox | n/a | 1 snapshot/dia + S3 semanal |

---

## Referências

- ADR 0042 — Infra empresa padrão (Proxmox + Docker + Traefik)
- ADR 0053 — MCP server em CT 100
- ADR 0058 — Centrifugo + FrankenPHP em CT 100
- Auto-mem `reference_proxmox_empresa.md` — capacidade hardware
- Auto-mem `reference_proxmox_acesso_2026_04_29.md` — receita acesso CT
- Auto-mem `project_infra_padrao_empresa.md` — padrão deploy

---

**Última atualização:** 2026-04-30
