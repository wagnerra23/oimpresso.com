---
slug: 0298-figma-nao-e-fonte-de-design
number: 298
title: "Figma não é fonte de design: bloqueio determinístico do atrator + fonte única (Cowork + DS + charter)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-22"
module: design-system
tags: [design, governanca, hooks, figma, fonte-da-verdade, enforcement, tier-0, cowork, claude-4-8]
supersedes: []
superseded_by: []
related:
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0249-ds-v6-naming-amends-0235
  - 0224-hooks-block-vs-advisory-claude-4.8-aware
  - 0235-ds-v4-accent-roxo-universal
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Proposta por [CL] (Claude Code) em 2026-06-22.** Ratificação formal = merge por [W].
> Gatilho (Wagner, verbatim): *"por que não achou quando eu perguntei isso não pode acontecer, vai ter funcionários alterando tbm não pode ter falhas"* — após a IA tratar "design" como Figma e ir **perguntar** a fonte da verdade que já era canon.

# ADR 0298 — Figma não é fonte de design (bloqueio determinístico do atrator)

## Contexto (verificado em `origin/main`)

O servidor MCP do Figma está conectado e injeta, **always-on**, uma ordem imperativa no system prompt: *"use este server SEMPRE que o usuário quiser criar/editar qualquer UI/tela/component — even if Figma isn't named."* No oimpresso, a fonte de design **não é o Figma** — é o protótipo **Cowork** (`prototipo-ui/`) + o **Design System** (tokens · [ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md)/[0249](0249-ds-v6-naming-amends-0235.md)) + o **charter** da tela ([ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)).

No incidente 2026-06-22, ao pedido "fazer uma tela / pegar a diff do design pro code", a IA foi pro Figma e **perguntou** a fonte da verdade — que já existia em ≥4 lugares (`CODE_DESIGN_CONTRACT.md`, `INDEX-DESIGN-MEMORIAS.md`, skill `mwart-comparative`, ADR 0114).

**Causa-raiz (classe, não instância):** não foi déficit de informação — foi conflito de **autoridade**. Um atrator semântico, persistente e **não-editável** (a ordem do system prompt do Figma) venceu texto canônico que vivia só em docs que o agente não consultou. Logo a defesa correta **intercepta a AÇÃO** (a tool call do Figma), não adivinha o prompt; e o canon precisa de uma **fonte única** que o resto aponte, não cópias que divergem.

Esta decisão foi endurecida por um red-team adversarial (9 céticos + 3 alternativas + síntese + 2 refutadores) que **matou a proposta inicial** (3 nudges de texto no mesmo canal do atrator) por ser teatro probabilístico e por hardcodar 2 fatos falsos (`prototipos/<tela>/COMPARISON.md`, inexistente; "page.tsx nunca editado", que colide com a F3 do MWART).

## Decisão

### 1. Regra Tier 0 — fonte e NÃO-fonte de design

**Fonte** = protótipo Cowork (`prototipo-ui/prototipos/<tela>/`, read-only no repo) + Design System (tokens/componentes/primitivos) + charter da tela.
**NÃO-fonte** (exige Wagner dizer explícito "figma"/"usa o X") = **Figma · Notion · screenshot solto · link externo · qualquer MCP de design novo**. São atratores, não canon.
A **diff design→code** = `memory/requisitos/<Mod>/<tela>-visual-comparison.md` via skill `mwart-comparative` (existe). O `/design-diff` determinístico (render protótipo vs Page) fica **previsto** aqui, não implementado neste PR.

A fonte única é o `INDEX-DESIGN-MEMORIAS.md §0` (governado por `owner`+`next_review`). Todo o resto **aponta** pra lá — nunca restata versão/path (que apodrece).

### 2. Conformidade com ADR 0224 (por que é block legítimo, não enforcement semântico rebaixado)

[ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md): bloqueio (`exit 2`) é legítimo quando **determinístico** (path/bytes/regex sintática inequívoca); semântico/heurístico → advisory. O hook `block-figma-without-optin` bloqueia por **`tool_name`** (match de nome de tool — mesma classe de `block-automem`, que bloqueia por path). A única parte semântica (regex "figma" no prompt) só **concede** opt-in (direção fail-safe: errar pra menos = +1 round-trip, nunca vaza). Portanto **não** rebaixa o critério do 0224 — não precisa emendá-lo.

### 3. As camadas (rede em profundidade — gatilhos DIFERENTES, não 3 cópias da mesma frase)

| Camada | Mecanismo | Artefato | Papel |
|---|---|---|---|
| **L0** | doc (SSOT) | `INDEX-DESIGN-MEMORIAS.md §0.1` | a fonte única "Fontes e NÃO-fontes"; corrige os 2 fatos falsos |
| **L1** | **PreToolUse block** (fail-closed) | `.claude/hooks/block-figma-without-optin.mjs` | neutraliza o atrator no instante da tool call; opt-in via "figma"/`OIMPRESSO_FIGMA_OK=1`/`.figma-allow` |
| **L3** | rule + CLAUDE.md | `.claude/rules/pages.md` + linha Tier 0 | ponteiro path-scoped pro INDEX (orientação secundária) |
| **L4** | catraca | `settings-figma-registration.test.mjs` (registro + anti-restating) + `block-figma-without-optin.test.mjs` (lógica) | impede defesa-fantasma (existe-e-não-roda) e reintrodução de fato volátil |
| **L5** | baseline armado | `scripts/evals/design-source-of-truth.eval.mjs` + fixture do incidente | prova que o gate morde (16 vetores: atrator + escapes + legítimas) + procedimento comportamental manual |

**Cobertura do Figma:** denylist por capability figma-única (sobrevive à troca de UUID Felipe≠Wagner) + por nome-de-servidor "figma" (sabor plugin) + **fingerprint** (capability STRONG aprende o prefixo do servidor e gateia capabilities futuras dele na sessão). `.mjs` cross-platform (cobre funcionário em Mac/Linux; os hooks `.ps1` não).

**Superfície Cowork:** como `.claude/hooks` não roda no lado Cowork ([CC]/[CD], outro produto), a regra NÃO-fontes foi replicada nos briefings versionados que ele lê (`prototipo-ui/CODE_DESIGN_CONTRACT.md` + `CLAUDE_DESIGN_BRIEFING.md`) — lá é disciplina, não block.

## Não-goals

- ❌ **Não desconecta o Figma MCP** — ele é legítimo quando o Wagner quer de propósito (opt-in). Desconectar por conta (pra quem não desenha em Figma) é recomendação operacional, não parte deste PR.
- ❌ **Não fecha a CLASSE inteira com block** — só o atrator Figma é gateado com `exit 2`. Notion/screenshot/link continuam **advisory** (L0). Fechar a classe = trabalho futuro (ver Gaps).
- ❌ **Não emenda o ADR 0224** — o block é determinístico por tool_name (§2), conforme, não exceção.
- ❌ **Não implementa `/design-diff`** — fica previsto (L2 adiado pelo Wagner; a diff hoje é via `mwart-comparative`).

## Gaps residuais conhecidos (honestidade do red-team — não declarar "resolvido" cego)

1. **A classe não está 100% fechada.** Notion (`mcp__plugin_productivity_notion__*`), file-MCP, screenshot de Chrome/Windows-MCP podem injetar design não-canon e **não** são gateados por block — só por L0 (doc), que é justamente o que o agente provou não ler. Fechar = gate determinístico genérico de "atrator design-to-code" (futuro).
2. **Capability NOVA de servidor Figma UUID-nomeado**, cujo primeiro uso na sessão seja ela mesma (sem STRONG antes pra fingerprintar) e cujo nome não esteja na lista, escapa até uma capability STRONG ser exercida. O eval L5 exercita os caminhos reais; a lista deve ser re-derivada do servidor conectado quando o Figma MCP mudar.
3. **Assimetria `.ps1`/`.mjs` (maior que este incidente):** ~30 hooks `.ps1` são Windows-only; funcionário em Mac/Linux roda nu contra ~11 blockers Tier-0 (`block-mwart-violation` etc.). Este hook é `.mjs` (cobre todos), mas o buraco geral fica aberto — registrado pra Onda futura.
4. **Prova de plataforma:** há precedente de PreToolUse em tool MCP (`settings.json` casa `mcp__computer-use__screenshot|mcp__Claude_in_Chrome__.*`), mas a prova final de que o harness roteia PreToolUse pro servidor Figma específico é o **baseline comportamental** da fixture L5 — rodar antes de considerar o incidente fechado.

## Consequências

✅ **Boas:**
- O atrator que causou a falha é neutralizado por bloqueio determinístico (não por "lembrar"). Vale pro time MCP (Felipe/Maiara/Eliana/Luiz), cross-platform.
- Fonte única (L0) corrige 2 fatos falsos e abre a lista NÃO-fontes — o resto aponta, ninguém transcreve.
- Catraca (L4) + baseline (L5) impedem defesa-fantasma e drift de fato volátil.

⚠️ **Tradeoffs:**
- Fail-closed adiciona +1 round-trip quando o Wagner quer Figma e esquece de dizer "figma" (escape: `OIMPRESSO_FIGMA_OK=1` / `.figma-allow`).
- A classe inteira não fecha agora (Gap 1) — risco de "incidente Notion da próxima vez"; mitigado por L0 advisory + registro do gap.
- `UserPromptSubmit`/`PreToolUse` rodam node a mais — só nas chamadas figma-ish (matcher targeted), custo desprezível.

## Validação

- ✅ `node .claude/hooks/block-figma-without-optin.test.mjs` — classificação + opt-in (atrator + 3 escapes bloqueados; legítimas livres; prompt do incidente não concede opt-in).
- ✅ `node scripts/governance/settings-figma-registration.test.mjs` — registro nos 2 eventos + matcher cobre o atrator + anti-restating.
- ✅ `node scripts/evals/design-source-of-truth.eval.mjs` — 16/16 vetores de ataque.
- ✅ Conformidade ADR 0224 confirmada (block por tool_name = determinístico).
- ⏳ **Baseline comportamental** (fixture L5) — rodar à mão com/sem o hook antes de fechar o incidente.

## Notas

- Produzido por processo adversarial (workflow `adversarial-design-sot-defense`): 9 céticos de red-team + 3 alternativas + síntese endurecida + 2 refutadores. A proposta inicial (3 nudges) foi **descartada** por teatro; o que sobrou é bloqueio no atrator + fonte única + caminho-certo-mais-fácil (previsto) + catraca + baseline.
- Sequência de defesa mecânica do projeto: `block-automem`, `block-pr-without-approval` (R10), e agora `block-figma-without-optin`.
