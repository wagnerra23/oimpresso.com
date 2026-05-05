---
slug: 0073-auto-capture-turn-level-pii-redactor-lgpd
number: 0073
title: "Auto-capture turn-level no Copiloto com PII redactor LGPD-aware (memória sem fricção)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-05
module: Copiloto
quarter: 2026-Q2
tags: [memory, auto-capture, lgpd, pii, copiloto, mem-hot, retention]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0035, 0036, 0047, 0048, 0049, 0050, 0052, 0061]
pii: false
review_triggers:
  - "ANPD publicar guidance específica sobre 'memória conversacional' de IA"
  - "Falso-positivo PII redactor > 5% em amostra de 200 turns"
  - "Volume mcp_memoria_turn > 10M rows (avaliar partition+archive)"
---

# ADR 0073 — Auto-capture turn-level com PII redactor LGPD

## Contexto

Memória do Copiloto hoje (ADRs 0035 / 0047 / 0052) tem 3 camadas:
1. **Hot** — `ContextoNegocio` snapshot injetado a cada turn no system prompt (faturamento 3 ângulos, ADR 0052). Recall em prod = 190 chars (MEM-HOT-1, sessão 17).
2. **Warm** — Meilisearch hybrid sobre `mcp_memory_documents` (ADR 0067) — 352 docs canônicos do git.
3. **Session log** — Wagner escreve **manualmente** em `memory/sessions/YYYY-MM-DD-*.md` no fim do dia.

**Gap exposto pela pesquisa OpenClaw (`memory-lancedb` auto-recall + auto-capture):** o que aconteceu **na conversa em si** (Larissa pergunta X, Copiloto responde Y, ela ajusta com Z) não é capturado em lugar nenhum entre turns — só no log manual posterior. Resultado: na próxima conversa, Copiloto não lembra que Larissa já corrigiu uma interpretação. Recall continua ≈190 chars (`ContextoNegocio`) porque não há histórico turn-level a recuperar.

**Por que não copiamos OpenClaw direto:** OpenClaw captura tudo localmente, single-user, zero-config. Nosso caso é multi-tenant (`business_id` global scope, ADR — UltimatePOS pattern), com `pii: true` em parte dos documentos canônicos, e LGPD Art. 7º (princípio de minimização) + Art. 18 (direito ao esquecimento) aplicáveis. Captura turn-level vira passivo legal se mal feita.

**Aprendizado meta da sessão 18 (ADR 0052):** smoke técnico passou (MEM-HOT-2 deployed `2be9930c`) com bug semântico latente. Larissa em prod expôs. Auto-capture **sem** policy explícita teria gravado os 3 turns errados (mesma resposta R$ 31.513,29) como verdade. Captura crua é arma de duplo gume.

**Decisão tomada antes (ADR 0061):** zero auto-mem privada local. Tudo conhecimento vai pra git → MCP. Mas turn-level conversacional **não é** conhecimento canônico — é evento operacional. Cabe em DB com retention policy, não em git.

## Decisão

Implementar **`AutoCaptureService` em `Modules/Copiloto/Services/Memory/`** que captura cada turn (user message + assistant response + tools called + token cost) na tabela nova `mcp_memoria_turn`, **passando obrigatoriamente** por:

1. **`PiiRedactorService`** — regex BR (CPF/CNPJ/email/cartão Luhn/telefone +55) substituindo por `[REDACTED:tipo]` antes do INSERT. Reaproveita stack do hook `pii-redactor` (US-COPI-086, done) mas em runtime application-level, não em commit-time.
2. **Filtro de capture** — turns com `confidence < 0.5` ou `flagged_by_user=true` (botão 👎 na UI) **não viram memória recuperável** (gravados com `usable_for_recall=false`, mantidos só pra audit/debug).
3. **Quota por tenant** — máximo `N=10.000` turns por `business_id` em janela rolling 30d. Excesso → archive em `mcp_memoria_turn_archive` com TTL 365d (ADR 0059 retenção LGPD).
4. **Direito ao esquecimento** — endpoint `DELETE /copiloto/admin/memoria/turn?user_id=X` faz hard delete cascata (turn + turn_archive + reranker cache) + entrada em `lgpd_audit_log`.

Schema tabela:
```sql
mcp_memoria_turn (
  id BIGINT PK,
  business_id INT NOT NULL,                    -- global scope
  conversa_id BIGINT NOT NULL,
  turn_index INT NOT NULL,
  user_id INT NOT NULL,
  user_message TEXT NOT NULL,                  -- já redacted
  assistant_response TEXT NOT NULL,            -- já redacted
  tools_called JSON NULL,
  token_cost INT NOT NULL,
  embedding VECTOR(1024) NULL,                 -- preenchido em background job
  confidence FLOAT NOT NULL DEFAULT 1.0,
  usable_for_recall BOOL NOT NULL DEFAULT 1,
  pii_redacted_count INT NOT NULL DEFAULT 0,   -- métrica observabilidade
  created_at TIMESTAMP,
  INDEX (business_id, conversa_id, turn_index),
  INDEX (business_id, created_at DESC)         -- pra recall mais recente
)
```

Recall integra-se ao `MemoriaContrato`: `MeilisearchDriver` ganha um source virtual `mcp_memoria_turn` (filtrável por `business_id`) que entra no hybrid search junto com `mcp_memory_documents`. Top-K candidates passam pelo reranker (ADR 0072) antes de virar contexto.

Métricas OTel (ADR 0050):
- `gen_ai.memoria.turn.captured` (counter)
- `gen_ai.memoria.turn.pii_redacted` (counter; alerta se > 10% turns)
- `gen_ai.memoria.turn.recall_hit_rate` (histogram)
- `gen_ai.memoria.turn.archive_size_bytes` (gauge)

## Justificativa

- **Por turn-level em DB e não em git** — turn é evento operacional alta-cardinalidade, não conhecimento canônico. Git é pra decisões/specs/sessions consolidadas. Misturar polui histórico e quebra ADR 0061 (zero auto-mem) que se aplica a `~/.claude/projects/*/memory/`. DB é o lugar correto: tem multi-tenancy, retention, LGPD delete cascade.
- **Por PII redactor obrigatório no caminho crítico** — Larissa cola CPF de cliente real no chat sem pensar. Não dá pra confiar que LLM "vai esquecer". Redaction acontece **antes do INSERT** — DB nunca vê PII raw. Mesma stack do US-COPI-086 (hook commit), só que em runtime de app.
- **Por `usable_for_recall=false` em turns flagged** — quando Larissa corrige Copiloto ("não, faturamento líquido é diferente de bruto"), o turn errado original **fica registrado pra audit** (debug, evidência LGPD) mas **não vira memória recuperável** — caso contrário, o erro se reforçaria. Padrão "negative cache" da literatura RAG.
- **Por quota 10k turns/30d/tenant** — Larissa volume real ≈ 200 turns/mês. Quota é 50× isso, dá folga sem virar bomba relógio. Excedeu → archive (ADR 0059 retention 365d).
- **Por DELETE cascata explícita** — LGPD Art. 18 (direito ao esquecimento) é dever legal. Endpoint admin precisa existir e ser auditado em `lgpd_audit_log`. Não faz parte do "talvez no futuro".
- **Por feature flag `COPILOTO_AUTO_CAPTURE_ENABLED`** — ativar primeiro em ROTA LIVRE (biz=4, Larissa) com consentimento explícito; depois geral. Padrão dogfooding adotado em todo deliverable Copiloto (ADR 0049).

**Quando reabrir:**
- ANPD publicar guidance específica sobre memória conversacional (mais rigorosa que LGPD genérica).
- Falso-positivo PII redactor > 5% em amostra de 200 turns auditados.
- Volume `mcp_memoria_turn` > 10M rows (avaliar partition mensal + archive).

## Consequências

**Positivas:**
- Recall útil cresce de ≈190 chars (`ContextoNegocio`) pra histórico turn-level genuíno → respostas mais contextuais sem prompt eng manual.
- Larissa não precisa repetir contexto entre conversas ("ah, essa é a mesma cliente que eu te falei semana passada").
- Auto-capture sem virar passivo LGPD: redaction obrigatória + retention 365d + delete cascata.
- Métricas OTel detectam regressão (PII leak rate, recall hit rate) antes do usuário reclamar.
- Compatível com reranker ADR 0072 — turn-level entra no mesmo hybrid search.

**Negativas / Trade-offs:**
- Tabela `mcp_memoria_turn` cresce rápido (~200 turns/mês × N tenants). Plano de partition+archive precisa estar pronto antes de ativar geral.
- PII redactor regex pode falsificar (regex BR não é bala de prata). Métrica `pii_redacted_count` + amostragem manual são gate.
- Schema migration grande (nova tabela + índices + FK + 1 endpoint admin + 1 service + tests). Estimo 12-16h de implementação.
- Aumenta latência de captura ~30ms por turn (regex + INSERT). Aceitável pq não bloqueia resposta (queue assíncrona).

**Riscos mitigados:**
- Captura crua sem policy → resolvido por redactor obrigatório + flag `usable_for_recall`.
- Crescimento descontrolado → quota + archive.
- LGPD passivo → delete cascade + audit log + retention 365d.
- Regressão semântica (turns errados reforçados) → flagged turns saem do recall.

## Implementação — referência rápida

```
Modules/Copiloto/Services/Memory/
  AutoCaptureService.php            # orquestra: redact → insert → enqueue embed
  PiiRedactorService.php            # regex BR (reaproveita US-COPI-086)
  TurnRecallService.php             # busca turns relevantes pro contexto
  Jobs/EmbedTurnJob.php             # async, popula vector
  Jobs/ArchiveOldTurnsJob.php       # daily, move > 30d pra archive

Modules/Copiloto/Database/Migrations/
  2026_05_05_000001_create_mcp_memoria_turn.php
  2026_05_05_000002_create_mcp_memoria_turn_archive.php

Modules/Copiloto/Http/Controllers/Admin/
  MemoriaTurnController.php         # listar + delete LGPD
```

Tests Pest mínimos:
- `AutoCaptureServiceTest` — feliz, redaction acontece, business_id scope
- `PiiRedactorServiceTest` — CPF/CNPJ/email/cartão (positivos + negativos + fixtures whitelist)
- `TurnRecallServiceTest` — só retorna `usable_for_recall=true`
- `MemoriaTurnControllerTest` — DELETE LGPD audit-logged
- Integration: turn flagged não reaparece em recall

## Referências

- ADR 0035 — Stack-alvo IA (laravel/ai + 4 Agents + MemoriaContrato)
- ADR 0036 — MeilisearchDriver hybrid
- ADR 0047 — Hot/Cold memory split (MEM-HOT-1)
- ADR 0048 — Vizra rejeitada (sem framework de memory turn-level pronto)
- ADR 0049 — Dogfooding ROTA LIVRE
- ADR 0050 — OpenTelemetry GenAI
- ADR 0052 — `ContextoNegocio` 3 ângulos faturamento
- ADR 0061 — Zero auto-mem privada (turn-level vai em DB, não em git)
- US-COPI-086 — Hook pii-redactor (reaproveita stack regex)
- US-COPI-088 — implementa esta ADR (a ser anexada)
- OpenClaw `memory-lancedb` — referência industry de auto-capture (sem o gating que adicionamos)
