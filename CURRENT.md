# CURRENT — Cycle 01 (29-abr → 12-mai-2026, 10 dias úteis)

> Foto do agora. Backlog completo: [`TASKS.md`](TASKS.md). Histórico: `memory/sessions/`. Cycles fechados: `memory/cycles/`.

**Branch ativa:** `main` · **Cycle anterior:** N/A (primeiro Cycle formal) · **Cycle owner:** Wagner [W] · **Modo:** ⚡ **SOLO** (time redistribuído no Cycle 02)

---

## 🎯 Goal do ciclo (outcome, não output)

> **"Copiloto assertivo e econômico em produção: Larissa pergunta faturamento e recebe resposta correta, com cache semântico reduzindo custos de token ≥50%."**

**3 métricas de sucesso:**
1. 🟡 **Copiloto responde faturamento/metas Larissa corretamente** — código deployed (29-abr `2be9930c`); ctx prod = 4 meses faturamento + 5993 clientes em 164 tokens; aguarda validação real Larissa (A4)
2. ✅ **`memoria_recall_chars > 0` nos logs** — bateu 190 em 2026-04-29 (prod) após MEM-HOT-1
3. 🔲 **Dashboard `/copiloto/admin/custos` validado em test + merged** (US-COPI-070)

**Gate opcional Cycle 01:**
- 🔲 Semantic cache hit rate >30% após 10 conversas similares (MEM-S8-1)
- 🔲 50 perguntas golden set — baseline RAGAS registrado (MEM-P2-1)

---

## 🔥 Active — Wagner solo (WIP máx 2)

| # | WIP | Task | Prazo | Status |
|---|---|---|---|---|
| A1 | — | ~~MEM-HOT-1: Hybrid embedder no MeilisearchDriver~~ | 30-abr | ✅ **29-abr** (`c631042c` — recall 0→190 chars em prod) |
| A2 | — | ~~MEM-HOT-2: ContextoNegocio → ChatCopilotoAgent~~ | 02-mai | ✅ **29-abr** (`2be9930c` — prompt biz=4 ROTA LIVRE com 4 meses faturamento + 5993 clientes em 164 tokens) |
| A3 | 1/2 | **MEM-S8-1: SemanticCacheMiddleware** (-68.8% tokens LLM, ADR 0037 Sprint 8) | 06-mai | ⏳ |
| A4 | 2/2 | **Validar Larissa** — pedir pra ela perguntar "qual meu faturamento de março" no chat (esperado: R$ 38.215,07) | sex 02-mai | ⏳ |

**On-deck imediato (puxar quando A3 fechar):**

| # | Task | Dias | Bloqueado por |
|---|------|------|--------------|
| O1 | **MEM-S8-2: ConversationSummarizer** (comprime hot window >15 turnos) | 1.5d | A3 pronto |
| O2 | **MEM-S8-3: ProfileDistiller** (job diário extrai perfil negócio <300 tokens) | 1d | O1 |
| O3 | **MEM-P2-1: Golden set v1** (50 perguntas Larissa-style para RAGAS baseline) | 1.5d | A4 |
| O4 | **MEM-P2-2: RRF tuning** A/B `semanticRatio` 0.3 vs 0.7 | 0.5d | O3 |

---

## ✅ Desbloqueados neste Cycle (2026-04-28)

| Item | Data | Notas |
|------|------|-------|
| OPENAI_API_KEY no Hostinger | 2026-04-28 | Wagner forneceu |
| DNS `meilisearch.oimpresso.com` | 2026-04-28 | API `developers.hostinger.com` — PUT overwrite:false |
| Cert Let's Encrypt R12 Meilisearch | 2026-04-28 | Restart Traefik após DNS propagar |
| `config/ai.php` commitado | 2026-04-28 | Era untracked; gpt-5.4 fallback eliminado |
| Log channel `copiloto-ai` | 2026-04-28 | Estava faltando em `config/logging.php` |
| SCOUT_DRIVER + MEILISEARCH env | 2026-04-28 | `.env` Hostinger configurado |
| Embedder OpenAI text-embedding-3-small | 2026-04-28 | Index `copiloto_memoria_facts` configurado e validado e2e |
| **Copiloto IA real em produção** | 2026-04-28 | gpt-4o-mini respondendo (Wagner + Larissa testaram) |

---

## 🚧 Gaps conhecidos (não-bloqueantes, roadmap)

| Gap | ADR | Sprint | Status |
|-----|-----|--------|--------|
| ~~MeilisearchDriver usa Scout default = full-text~~ | ADR 0046 | A1 fechou 29-abr | ✅ resolvido `c631042c` (prod recall=190 chars) |
| ~~ChatCopilotoAgent "burrinho" — sem contexto de negócio~~ | ADR 0046 | A2 fechou 29-abr | ✅ resolvido `2be9930c` (164 tokens ctx em prod) |
| Semantic cache não implementado (-68.8% tokens) | ADR 0037 Sprint 8 | O1 semana 2 | 🟡 on-deck |
| Conversation summarizer não implementado | ADR 0047 | O2 semana 2 | 🟡 on-deck |
| RAGAS golden set (50 perguntas) — baseline nunca medido | ADR 0037/0041 | O5 semana 3 | 🟠 P2 |

---

## 📊 Métricas do Cycle (Wagner atualiza toda sexta)

| Indicador | Alvo | Track |
|-----------|------|-------|
| `memoria_recall_chars > 0` | sim | ❌ hoje |
| Copiloto responde faturamento Larissa | sim | ❌ hoje |
| Conv 20 turnos usa <2.000 tokens contexto | sim | — |
| Semantic cache hit rate | >30% | — |
| PRs merged / tasks fechadas | ≥ 5 | 0 |

---

## 🖥️ Infra ativa (2026-04-28 estado final)

| Serviço | URL | Status |
|---------|-----|--------|
| App Hostinger | `https://oimpresso.com` | ✅ L13.6 PHP 8.4 |
| Traefik v3.6 | `https://traefik.oimpresso.com` | ✅ TLS auto |
| Portainer | `https://portainer.oimpresso.com` | ✅ |
| Vaultwarden | `https://vault.oimpresso.com` | ✅ |
| Reverb | `https://reverb.oimpresso.com` | ✅ WebSocket |
| Meilisearch v1.10.3 | `https://meilisearch.oimpresso.com` | ✅ TLS R12 |
| Copiloto IA | `/copiloto/chat` | ✅ gpt-4o-mini |

**Copiloto stack de memória:**

| Camada | Componente | Estado |
|--------|-----------|--------|
| A | `laravel/ai ^0.6.3` + `config/ai.php` | ✅ gpt-4o-mini |
| B | `LaravelAiSdkDriver` + 4 Agents | ✅ prod |
| C Hot | `SqlDriver` — conversas em DB | ✅ |
| C Cold | `MeilisearchDriver` | ✅ hybrid embedder ativo (29-abr commit `c631042c`) |
| Embedder | OpenAI text-embedding-3-small | ✅ funcional |

---

## 📅 Próximo Cycle 02 (13-mai → 26-mai-2026)

**Goal provável:** *Redistribuir time + iniciar PontoWr2 Tier A + Eliana(WR2) validação.*

Tasks candidatas (não puxar antes!):
- MEM-P2-2: RRF tuning (semantic_ratio A/B)
- PNT-001: PontoWr2 Tier A Dashboard vivo
- PNT-002: Validar Eliana(WR2)

---

## 🔄 Mudanças desde abertura do Cycle (2026-04-28)

- Infra CT 100: Traefik + Portainer + Vaultwarden + Reverb + Meilisearch todos ✅ em prod
- Copiloto IA real ativo (gpt-4o-mini) — primeiro dia de conversas reais
- ADRs criados: 0042 (Reverb) · 0043 (Docker) · 0044 (Vaultwarden) · 0045 (DNS API) · 0046 (ChatAgent gap) · 0047 (Wagner solo + sprint memória)
- CURRENT re-escrito: time 5 pessoas → Wagner solo, foco memória assertiva

---

> Esse arquivo é sobrescrito quando Cycle muda. Cycle anterior arquivado em `memory/cycles/CICLO-NN-YYYY-MM-DD.md` com retro de 5 linhas.
