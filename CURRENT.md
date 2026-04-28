# CURRENT — Cycle 01 (29-abr → 12-mai-2026, 10 dias úteis)

> Foto do agora. Backlog completo: [`TASKS.md`](TASKS.md). Equipe: [`TEAM.md`](TEAM.md). Histórico: `memory/sessions/`. Cycles fechados: `memory/cycles/`.

**Branch ativa:** `main` · **Cycle anterior:** N/A (este é o primeiro Cycle formal) · **Cycle owner:** Wagner [W]

---

## 🎯 Goal do ciclo (outcome, não output)

> **"Tirar Copiloto de fixtures e ter Larissa do ROTA LIVRE conversando ≥10× em produção real, sem PII vazado, com dashboard `/copiloto/admin/qualidade` rodando — tudo até 12-mai-2026."**

**3 métricas de sucesso (todas mensuráveis, todas precisam bater):**
1. ✅ **≥10 conversas reais Larissa** registradas em Langfuse (não-fixture)
2. ✅ **0 incidente de PII detectado** (CPF/CNPJ no payload outbound)
3. ✅ **Dashboard `/copiloto/admin/qualidade` no ar** com trend de faithfulness últimos 7 dias

**Se 3/3 batem em 12-mai → Cycle 01 sucesso. Avança Cycle 02 com PontoWr2 Tier A + Eliana(WR2) validação.**
**Se ≤1/3 batem → diagnóstico no fim do cycle: bloqueio Larissa? técnico? capacidade? capítulo crítico no retro.**

---

## 🔥 Active — TODAS as tasks repassadas pra Wagner [W+C] (modelo operacional 2026-04-28+)

> **Decisão 2026-04-28:** Wagner é executor único de TODAS as tasks por enquanto, na conta MAX dele pareado com Claude (`[W+C]`). Os "donos originais" (Felipe/Maíra/Luiz/Eliana) viram **observadores/revisores** até Wagner se acostumar com o processo. Ver [`TEAM.md` §"Modelo operacional ATUAL"](TEAM.md).
>
> **WIP máximo do Wagner subiu pra 6 ativas** temporariamente (4 absorvidas + 2 originais). Aceito como gargalo intencional.

| # | Executor | Original | Task | Prazo duro | Status |
|---|---|---|---|---|---|
| A1 | **W** | W | **Validar Larissa do ROTA LIVRE** (1h, 3 cenários — meta atual / conv >15 turnos / corrigir fato LGPD) | qua **30-abr** | ⏳ |
| A2 | **W** | W | **Merge US-COPI-070 Dashboard custo IA** (validação visual `https://oimpresso.test/copiloto/admin/custos`, branch `claude/nervous-burnell-f497b8`) | sex **02-mai** | ✅ validado, aguarda merge |
| A3 | **W+C** | F (revisor) | **PII redactor BR** (regex CPF/CNPJ/email/tel-BR em `OpenAiDirectDriver`) — LGPD-blocker | seg **05-mai** | ⏳ |
| A4 | **W+C** | F (revisor) | **Fix `OPENAI_API_KEY` no `.env` local** (chave colada como `OPENAI_KEY` linha 170 — renomear) + smoke `/copiloto` local | qui **30-abr** | 🔄 chave local OK, falta renomear var |
| A5 | **W+C** | M (revisor) | **Cleanup workflows YAML `6.7-bootstrap` → `main`** (`.github/workflows/{deploy,quick-sync}.yml`) | qua **30-abr** | ⏳ |
| A6 | **W+C** | M (revisor) | **Smoke /copiloto manual após A4** + registrar resultado | sex **02-mai** | ⏳ |
| A7 | **W+C-Design** | L (observador) | **Page `/copiloto/admin/qualidade` Inertia (skeleton)** seguindo padrão Chat Cockpit ADR 0039 — sessão **Claude Design** rotacionando contas Pro | qui **08-mai** | ⏳ |
| A8 | **W ou E** | E | **Atualizar cobrança ROTA LIVRE** (validar plano + emitir mensalidade) — única task que faz sentido manter com Eliana real (não-técnica) | sex **02-mai** | ⏳ |

**WIP atual:** 7/6 (acima do teto temporário, justificado por absorção). Não puxar O-deck até cair pra ≤4.

---

## 📋 On-deck (próxima fila do mesmo Cycle 01, em ordem)

> **Todas as tasks On-deck também repassadas pra W+C** enquanto modelo atual vigorar. "Original" = quem seria o dono no estado-alvo.

| # | Executor | Original | Task | Estimativa | Bloqueado por |
|---|---|---|---|---|---|
| O1 | **W+C** | F | **Sprint 7 ADR 0041 — Golden set v1 (50 perguntas)** | 3 dias úteis | A1 (Larissa OK) + A2 (US-070 merged) |
| O2 | **W+C** | F | **Sprint 7 ADR 0041 — DeepEval CI gate** (`.github/workflows/eval.yml`) | 2 dias úteis | O1 |
| O3 | **W+C** | F | **Langfuse self-host Hostinger** (Docker compose + OTEL no LaravelAiSdkDriver) | 3 dias úteis | A4 (key OK) |
| O4 | **W+C** | F | **`ApurarQualidadeJob` Horizon + tabela `copiloto_qualidade_scores`** | 2 dias úteis | O3 |
| O5 | **W+C-Design** | L | **Page `/copiloto/admin/qualidade` HITL — lógica anotação** (continua A7) | 3 dias úteis | O4 |
| O6 | **W+C** | M | **Backfill purchases legadas em `due` (FIN-001)** | 1 dia | — |

**Soma estimativas O1-O5 (caminho crítico Copiloto):** 13 dias úteis. Wagner sozinho com Claude Opus → realisticamente 6-8d se priorizar isso (sem multitarefa de líder). **Gap fica em ~3-5d** dentro do Cycle 01 — risco de estourar O3-O5 pro Cycle 02.

---

## 🚧 Bloqueios ativos

| Bloqueio | Impacto | Quem destrava | Prazo destrava |
|---|---|---|---|
| **`OPENAI_API_KEY` com nome errado no `.env` LOCAL** (linha 170 colada como `OPENAI_KEY=`) | Bloqueia smoke `/copiloto` local | W+C — renomear var (1min) | **hoje 28-abr** |
| **`.env` HOSTINGER ainda sem `OPENAI_API_KEY`** (Wagner colou no local achando que era prod) | Bloqueia smoke `/copiloto` em PROD | W+C — copiar bloco IA do `.env - Copia.example` (já tem skeleton) pro `.env` real + colar key | **qui 30-abr** |
| **Daemon Meilisearch Hostinger** sem PID confirmado | Bloqueia COP-008 embedder + busca real em prod | W+C — `curl http://127.0.0.1:7700/health` via SSH | **qui 30-abr** |
| **Larissa indisponível** (ainda não agendamos 1h) | Bloqueia A1 → cascata sprint 7 | Wagner (não-técnico, só ele) — manda WhatsApp hoje | **hoje 28-abr** |

**Se algum bloqueio ainda existir em 02-mai (sex):** virou **risco do cycle** — escalonar pra Wagner imediatamente, considerar replanejamento.

---

## 🚦 Diagrama de desbloqueio (resposta da Larissa A1)

```
[A1: Validar Larissa qua 30-abr]
       │
       ├─ "lembrou da meta!" / quer + memória ─────► Cycle 01 segue como planejado
       │                                              O1→O2 sprint 7 (golden set + CI)
       │
       ├─ "preciso PricingFpv/CT-e" ────────────────► PIVOT meio-cycle
       │                                              • Active O1-O5 viram blocked
       │                                              • CURRENT é re-escrito com goal
       │                                                "PricingFpv MVP + CT-e SPEC"
       │                                              • TASKS Copiloto sprints 7-9 viram P2
       │                                              • Felipe migra pro Modulo Financeiro
       │
       └─ silêncio / "não entendi" ─────────────────► PIVOT comercial
                                                       • Cycle 02 muda pra MCP server pro
                                                         Claude Desktop OU PontoWr2 Tier A
                                                       • Decisão Wagner mid-cycle
```

A1, A2, A4, A5, A8 **rodam paralelo** — não esperam Larissa. Bloqueio só se a A1 retornar pivot.

---

## 📅 Daily async (15 min cada manhã)

Cada um atualiza no `TASKS.md` antes das 09h:
- ✅ O que fechei ontem
- 🔄 O que vou tocar hoje
- ⛔ Bloqueado em quê (se algum)

Quem fica >2 dias na mesma task **sem mover status** → Wagner pinga (não-acusatório, "tá precisando de pair?").

---

## 📊 Métrica do cycle (atualizada por Wagner toda sex)

| Indicador | Alvo Cycle 01 | Track |
|---|---|---|
| Tasks fechadas (Active+On-deck) | ≥ 12 | 0/12 |
| Conversas Larissa em Langfuse | ≥ 10 | 0/10 |
| Incidentes PII | 0 | 0 |
| WIP médio time | 6-8 ativos | — |
| Bloqueios resolvidos < 48h | ≥ 80% | — |

---

## 📅 Próximo cycle (Cycle 02): 13-mai → 26-mai-2026

**Goal provável** (re-decidir 12-mai com base em Cycle 01):
> *"Validar PontoWr2 Tier A com Eliana(WR2) + começar Pricing Copiloto pago pra ROTA LIVRE."*

**Tasks candidatas pro Cycle 02 (não puxar antes!):**
- PNT-001 PontoWr2 Tier A Dashboard vivo
- PNT-002 Validar Eliana(WR2) — bloqueante comercial
- COP-001 (precificação Copiloto se Larissa quiser pagar)
- O1-O6 que escapam do Cycle 01 (planejado: 0)

---

## 🔄 Mudanças desta sessão (2026-04-28, Wagner+Claude)

**Reconciliação com main necessária:** sessão paralela (PR #56 mergeada antes desta) já tinha entregue slim CLAUDE.md + INFRA + DESIGN + /continuar + skill multi-tenant + ADR 0040 publication-policy. Esta branch **acrescenta** sem sobrescrever:

- `TEAM.md` (novo) — perfis das 5 pessoas, capacidade, matriz quem-pode-fazer-o-quê
- `TASKS.md` (novo) — backlog completo por módulo, donos por iniciais, estimativa em dias úteis
- `CURRENT.md` (sobrescreve versão narrativa de main) — agora Cycle 01 estado-da-arte com goal outcome-oriented + Active WIP + On-deck + bloqueios
- `memory/decisions/0041-stack-qa-ia-vizra-langfuse-deepeval.md` — ADR formal Caminho B (renumerado de 0040 → 0041 porque main já tem 0040 publication-policy)
- `memory/comparativos/qa_eval_ia_estado_arte_capterra_2026_04_28.md` — 564 linhas, 8 plataformas, 42 features
- `memory/cycles/README.md` — convenção arquivamento de Cycle ao fechar
- CLAUDE.md ganhou §11 Equipe (não substitui o slim de main; complementa)

**Mantidos da main como vieram (não duplicar):** CLAUDE.md slim, INFRA.md, DESIGN.md, `.claude/settings.json` (PowerShell version), `.claude/commands/continuar.md`, `.claude/skills/multi-tenant-patterns/`, `.claude/skills/publication-policy/`, ADR 0040 publication-policy.

---

> Esse arquivo é sobrescrito quando Cycle muda. Cycle anterior é arquivado em `memory/cycles/CICLO-NN-YYYY-MM-DD.md` antes da sobrescrita (com retro de 5 linhas).
