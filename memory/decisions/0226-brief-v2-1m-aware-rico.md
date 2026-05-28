---
adr: 0226
title: Brief v2 (1M-aware) — régua 3.5k → 8k tokens, reposiciona como estado-rico-pro-Wagner
status: accepted
date: 2026-05-28
deciders: [Wagner]
amends: []
supersedes_partially: [0091, 0097]
references:
  - 0091-daily-brief.md
  - 0097-brief-model-gpt4o-mini-supersede-parcial-0091.md
  - 0094-constituicao-v2-7-camadas-8-principios.md
  - 0224-hooks-block-vs-advisory-claude-4.8-aware.md
  - 0225-skills-tier-a-recalibracao-claude-4.8.md
lifecycle: active
---

## Contexto

Terceira ADR da reavaliação Claude 4.8 ([sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md](../sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md)), após 0224 (hooks) e 0225 (skills).

O Daily Brief ([ADR 0091](0091-daily-brief.md)) nasceu com régua **≤3.500 tokens** sob a premissa "contexto escasso/caro": janela ~200k → 15-30k de onboarding × 30 sessões/dia = ~500k desperdiçados, logo brief enxuto economiza. A régua estava em 3 lugares:
- `BriefGeneratorService::MAX_TOKENS = 4096` (teto de geração gpt-4o-mini)
- Prompt: "Total ≤3.500 tokens. Conte mentalmente. Se passar, corte..."
- `BriefValidator::MAX_TOKENS = 3500` (rejeita brief maior)

Com **Claude 4.8 (1M context)**, essa premissa caiu: 8k de brief = **0,8% da janela**. O custo de geração (gpt-4o-mini, ADR 0097) de um brief maior é trivial (~$0,001 extra/brief). O trade-off inverteu: **brief RICO (mais EM VOO, mais decisões, mais contexto pro Wagner decidir sem abrir 5 ferramentas) vale mais que brief telegráfico amputado**.

A própria régua de 3.500 forçava cortes ("corte da seção menos crítica") que removiam informação útil — exatamente o que o 1M context torna desnecessário.

## Decisão

Brief v2 — **régua 3.500 → 8.000 tokens**, reposicionado como **estado-rico-pro-Wagner**, não economia de tokens.

| Local | Antes | Depois |
|---|---|---|
| `BriefGeneratorService::MAX_TOKENS` | 4096 | **8192** (env `GOVERNANCE_BRIEF_TARGET_TOKENS` override) |
| Prompt instrução | "≤3.500, conte mentalmente, corte" | "≤8.000, priorize completude útil, corte só se exceder MUITO" |
| Prompt seção EM VOO | "Limite 8 linhas" | **"Limite 12 linhas"** |
| `BriefValidator::MAX_TOKENS` | 3500 | **8000** |
| Versão gerador (metadata) | v1 | **v2 (ADR 0226)** |

Tom ajustado: de "telegráfico, denso, sem floreio" → "denso e factual, mas com contexto suficiente pro Wagner decidir sem abrir 5 ferramentas. Sem floreio, mas sem amputar informação útil."

### O que NÃO muda

- **Modelo gpt-4o-mini** ([ADR 0097](0097-brief-model-gpt4o-mini-supersede-parcial-0091.md)) — continua o certo (geração estruturada barata, não é muleta de modelo)
- **7 seções obrigatórias** + ordem + headers exatos — estrutura canon intacta
- **Sentinela `---END---`** — intacta
- **Zero PII de cliente final** — regra LGPD intacta (validator continua rejeitando CPF/CNPJ)
- **Cron de geração** + cache 5min — intactos

## Não-goals

- ❌ **Não troca o modelo** (gpt-4o-mini fica — economia legítima de OPEX)
- ❌ **Não muda as 7 seções** nem a estrutura
- ❌ **Não afrouxa LGPD** (PII de cliente final continua bloqueada)
- ❌ **Não remove o cap** — só sobe pra 8k (ainda há teto, anti-runaway)
- ❌ **Não força brief sempre** (ADR 0225 já rebaixou brief-first pra auto-trigger)

## Implementação (este PR)

- `BriefGeneratorService.php`: `MAX_TOKENS 4096→8192` + método `targetTokens()` env-overridable + 3 edições de prompt (régua, EM VOO 8→12, validador interno, versão v2) + tom
- `BriefValidator.php`: `MAX_TOKENS 3500→8000` + comentários
- `GenerateBriefCommand.php`: comentário doc ≤8000
- `BriefValidatorTest.php`: 2 testes atualizados (assert 8000 + padding 36k chars pra overflow)
- Esta ADR (`supersedes_partially: [0091, 0097]`)

## Consequências

✅ **Boas:**
- Brief mais rico: mais EM VOO (12 vs 8 linhas), mais decisões, mais contexto — Wagner decide sem abrir cycles-active + my-work + decisions-search
- Custo extra trivial (~$0,001/brief gpt-4o-mini) — OPEX irrelevante
- Régua env-overridable: Wagner calibra `GOVERNANCE_BRIEF_TARGET_TOKENS` sem deploy
- Alinhado com 1M context: para de tratar contexto como recurso escasso
- Reposicionamento honesto: brief é UX-pro-Wagner, não hack de economia

⚠️ **Tradeoffs:**
- Brief maior = mais tokens lidos por sessão (~8k vs ~3.5k). Com 1M context é 0,8% — irrelevante. Mas em sessões hiper-curtas é leve overhead.
- gpt-4o-mini pode às vezes não preencher os 8k (gera o que tem) — OK, 8k é teto não piso
- Validator a 8k aceita briefs que antes rejeitaria — risco de brief verboso. Mitigação: prompt ainda pede "denso e factual, sem floreio"
- `supersedes_partially` 0091/0097 — append-only respeitado, ler 0226 junto

## Validação

- ✅ BriefValidatorTest 10/10 verde (assert MAX_TOKENS=8000 + overflow >8000 com 36k padding)
- ✅ Suite Brief completa 45 passed / 15 skipped (SQLite-incompat esperado)
- ✅ Sem refs residuais a 3500/4096 no código (só comentários históricos atualizados)
- ⏳ Teste real: próximo cron `brief:generate` produz brief até 8k; Wagner vê brief mais rico

## Notas

- Sequência reavaliação 4.8: 0224 (hooks) ✅ → 0225 (skills) ✅ → **0226 (brief v2)** ✅ → 0227 (MWART single-layer) → 0228 (subagent nativo)
- Wagner aprovou 2026-05-28 ("sim eu preciso")
- Reverter = `MAX_TOKENS` 8000/8192→3500/4096 + prompt (totalmente reversível)
- ADRs 0227 (MWART) + 0228 (subagent) ficam pra próxima sessão — são as 2 últimas da reavaliação, e 0228 é EVOLUIR (piloto, mais envolvido)
