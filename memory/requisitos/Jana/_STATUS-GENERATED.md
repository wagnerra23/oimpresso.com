---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Jana · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 63 |
| CU no SDD | 0 |
| Telas (.tsx) | 4 |
| Telas com `casos.md` | 2 |
| UC declarados | 11 |
| UC com teste que os cita | 11 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| Tela `Chat` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| `US-COPI-076` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — ADRs formais split modular + Permission Registry + atualizar |
| `US-COPI-078` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Schema tipado KB — migration + validação webhook |
| `US-COPI-081` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Sprint 7 RAGAS — gate de medição Cycle 01 |
| `US-COPI-082` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Sprint 9 retrieval — diagnóstico nomic + fixes (recovery 0.1 |
| `US-COPI-083` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Sprint 9b — qwen3-embedding:0.6b + stopwords PT-BR (em par c |
| `US-COPI-084` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Slash command /ultrareview — code review adversarial automát |
| `US-COPI-085` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Hook block-destructive — guardrails Bash em produção |
| `US-COPI-086` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Hook pii-redactor — bloquear commit com PII (LGPD) |
| `US-COPI-088` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — BRIEF-A1 — Fix aggregator (in_flight + ADR DATE bug + activi |
| `US-COPI-089` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — BRIEF-A2 — Validar brief-fetch exposto + remover do Hostinge |
| `US-COPI-092` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — GUARD-01 — Schema snapshot Pest test + procedure_drift healt |
| `US-COPI-093` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — GUARD-02 — Pest audit ModuleScaffolding |
| `US-COPI-094` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — BRIEF-A2 follow-up — Remover brief-fetch do Hostinger MCP se |
| `US-COPI-107` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Onda 4 R1 — Reranker BGE-v2-m3 self-host CT 100 |
| `US-COPI-108` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Onda 4 L1 — Langfuse v3 self-host CT 100 (MULTIPLICADOR) |
| `US-COPI-109` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Onda 4 C1 — Charters S4 ativos (charter-fetch tool + Tier A) |
| `US-COPI-110` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Onda 5 K1 — Time-decay weighting recall (boost recente + dec |
| `US-COPI-111` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Onda 5 V1 — Roadmap timeline UI (SVAR Gantt MIT + sub-issues |
| `US-COPI-112` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Onda 5 H1 — Auto-skeleton handoff-draft (tool MCP) |
| `US-COPI-113` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Onda 5 S1 — Schema rígido CI validation (SPEC/RUNBOOK/Sessio |
| `US-COPI-115` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — LGPD jana:retention-purge artisan + DSR Art. 18 §VI + tool M |
| `US-COPI-116` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — RAGAS canary CI daily 06:00 UTC + 30 golden questions gate |
| `US-COPI-123` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Remover startMockStream da rota live /ia/dashboard (Cockpit  |
| `US-COPI-136` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Piso de context_recall no baseline (recall 0,3839 pode cair  |
| `US-COPI-137` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Eval online em 5% dos traces reais (hoje: zero avaliação no  |
| `US-COPI-138` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Heartbeat langfuse_trace_uptime_24h no HealthCheckCommand |
| `US-COPI-140` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Os 2 evals de staging da Jana nunca rodam sozinhos (schedule |
| `US-COPI-141` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Chat declara tools READ-ONLY (a capacidade — atrás de flag) |
| `US-COPI-142` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Flip da flag chat_tools + medição antes/depois (decisão [W]) |
| `US-COPI-144` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Modelo forte no chat (JANA_CHAT_MODEL cirúrgico) — mecanismo |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-COPI-077 | `superseded` | ContextForTaskService consumir tasks-current MCP em vez de ler CURRENT.md |
| US-COPI-079 | `todo` | Demo Maiara real — Claude Code + /team-mcp + tela 360° |
| US-COPI-080 | `todo` | Buffer fix — corrigir o que demo Maiara encontrar |
| US-COPI-087 | `desconhecido` | Sprint 9c — Cross-encoder reranker (qwen3-reranker ou bge-reranker-v2-m3) |
| US-COPI-090 | `todo` | BRIEF-A3 — ADR 0096 superseding parcial 0091 (model real gpt-4o-mini) |
| US-COPI-091 | `todo` | BRIEF-A4 — Investigar baixa adoção brief-first (2 triggers em 7d) |
| US-COPI-101 | `todo` | Pages/Jana/Admin/Permissions — UI dedicada CRUD roles+scopes |
| US-COPI-102 | `todo` | Business switcher na sidebar do Chat (UI mid-conversa) |
| US-COPI-103 | `todo` | Pest cross-tenant biz=99 hardcoded em HitTrackerServiceTest |
| US-COPI-104 | `todo` | Smoke Browser MCP fresh (screenshot+console) para Chat Jana |
| US-COPI-105 | `todo` | Jana Chat V2 — block renderer (4 kinds) + streaming + citations + atalhos |
| US-COPI-106 | `desconhecido` | Jana V2 demo — tela navegável apresentável a 1 cliente piloto |
| US-COPI-117 | `desconhecido` | Deploy Langfuse self-host CT 100 (ADR 0132) |
| US-COPI-132 | `todo` | Langfuse traces sem tag business_id (isolamento multi-tenant observability) |
| US-COPI-118 | `todo` | Tokenizar cores cruas do card-de-prova Pro.tsx (fix ui:lint R1 pré-existente) |
| US-COPI-119 | `todo` | design:review Fase 2 — juiz LLM (R5/R8/R10 + nota holística + best_of_class) |
| US-COPI-124 | `todo` | Escopar delete do ContentReconciler por business_id (healable=false, Tier-0) |
| US-COPI-125 | `todo` | Adicionar kb_node_visibility + filtro ACL pre-retrieval no KbRagService (LGPD) |
| US-COPI-126 | `todo` | Propagar renames Copiloto→Jana / MemCofre→SRS nos ~112 PHP em Modules/ |
| US-COPI-127 | `todo` | Criar view cliente /copiloto/decisoes/{id}/revisao (LGPD Art.20) |
| US-COPI-128 | `todo` | Health-check multi_tenant_isolation cego a C3 — ler information_schema + probe c |
| US-COPI-129 | `todo` | Consertar jana:recall-eval (mock) — golden set estrutura_ok:false, 10 violações |
| US-COPI-130 | `todo` | Reranker BGE + Contextual Retrieval no docs_pipeline (context_recall 0.42 → ânco |
| US-COPI-131 | `todo` | Elevar tela Regras/Index a ≥70 (listar policies read-only + token roxo) |
| US-COPI-133 | `todo` | Descongelar Jana-BI — context_recall 0,38→0,60 (régua jana:ragas-real-eval CT100 |
| US-COPI-134 | `todo` | Régua ADR 0318 órfã — schedules staging (ragas-real-eval + recall-eval) sem runn |
| US-COPI-135 | `todo` | Desbloquear modelo frontier + fallback na Jana (gpt-4o-mini é o mais fraco do me |
| US-COPI-139 | `todo` | Badalo do ratio negócio÷governança no brief-fetch (o alarme existe e nunca dispa |
| US-COPI-143 | `todo` | Deprecar o `jana:drift-sentinel` tautológico (o "alarme de drift" mede gt-vs-gt, |
| US-COPI-145 | `todo` | Desbloquear modelo frontier no chat da Jana: ANTHROPIC_API_KEY em prod OU acesso |
| US-COPI-146 | `todo` | Migrar Jana/Dashboard pro padrão PT-04 (sair do bundle CSS paralelo .sells-cowor |
| US-COPI-147 | `todo` | Higiene de schema da camada de IA — 2 resíduos reais, 4 falsos positivos e 1 pro |
| US-COPI-148 | `todo` | Fundir as telas da Jana numa tela única `/ia` com abas Painel | Conversa | Memór |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-MEM-01 | Memoria | 🧪 aguarda veredito da lane |
| UC-MEM-02 | Memoria | 🧪 aguarda veredito da lane |
| UC-MEM-03 | Memoria | 🧪 aguarda veredito da lane |
| UC-MEM-04 | Memoria | 🧪 aguarda veredito da lane |
| UC-MEM-05 | Memoria | 🧪 aguarda veredito da lane |
| UC-PRO-01 | Pro | 🧪 aguarda veredito da lane |
| UC-PRO-02 | Pro | 🧪 aguarda veredito da lane |
| UC-PRO-03 | Pro | 🧪 aguarda veredito da lane |
| UC-PRO-04 | Pro | 🧪 aguarda veredito da lane |
| UC-PRO-05 | Pro | 🧪 aguarda veredito da lane |
| UC-PRO-06 | Pro | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
