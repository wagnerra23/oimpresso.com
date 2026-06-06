---
date: "2026-06-06"
hour: "21:56 BRT"
slug: design-system-revitalizacao
topic: "Revitalização do sistema de design — primitivos + grade determinístico + Manual de Identidade + inspeção/lápides + porta consertada + teste que trava tudo"
duration: "~longa (sessão-épico · 7 PRs merged)"
authors: [C, W]
tldr: "Overhaul completo do design system, OFF-CYCLE (CYCLE-08 é Receita — dívida de design ≠ receita). Saiu de 'espalhado + sem identidade + apodrecendo em silêncio' → 'consolidado + voz Clareza Confiante + protegido por CI'. 7 PRs merged: ADR 0253 primitivos de layout (@/Components/layout Box/Stack/Inline/Grid/Container/Text) · ADR 0254 grade de identidade DETERMINÍSTICO (σ=0, cura o LLM-judge que dava 91→71; hoje 66/100) · pilot ServiceOrderItemRow · UI-0018 mata snapshots zip-cowork (UI-0010/0012) · inspeção 5 agentes → 9 lápides com morreu_porque + §3e Índice Negativo no SSOT · DESIGN.md (porta) parava de apontar pro cemitério (_BACKUP/UI-0010 mortos) · TESTE DesignEntryPointAndTombstonesTest (porta resolve + lápide honesta). Manual de Identidade canon 'Clareza Confiante' (type-scale 8 tokens incl 2xs, 3 assinaturas focus-ring-roxo/barra-accent/tabular, motion 150ms). CONTINUAÇÃO QUEBROU porque esta sessão não tinha handoff — /continuar achava o handoff anterior (CSS trail 1650). ESTE handoff conserta."
---

# Handoff — Revitalização do sistema de design (2026-06-06)

> Sessão-épico autônoma ("sim/pode fazer/segue" repetidos). Pedido-raiz evoluiu de "fazer a venda funcionar na oficina" → análise → **trilha de design**: identidade, qualidade de componente, e por fim **revitalização total do conhecimento de design**.

## Estado MCP no momento
- Cycle: **CYCLE-08 Receita — Onda A** (22d restantes). Esta trilha é **OFF-CYCLE** (dívida de design ≠ receita). Drift esperado.
- Branch worktree origem: `docs/handoff-parecer-pr2270` (frosty-greider-83ab2f) — **STALE** (estava 54 commits atrás da main no início). Trabalho landeou na `main` via PRs.
- my-work inalterado (P0 de receita seguem: US-OFICINA-026 Martinho, US-FISCAL-018 Larissa, US-SELL-009 ROTA LIVRE).

## O que aconteceu (7 PRs merged)
1. **#2332/#2333** — ADR **0253** primitivos de layout (`@/Components/layout`: Box/Stack/Inline/Grid/Container/Text, props=token via CVA, focus impossível-px-literal) + showcase em `/showcase/components`.
2. **#2334** — fix casing `@/lib`→`@/Lib` resizable.tsx (TS1149 pré-existente, chip).
3. **#2335** — pilot: `ServiceOrderItemRow` migrado pra primitivos (pixel-fiel) + cores cruas tokenizadas (juiz alucinou "hardcoded" — refutei com eslint delta −1).
4. **#2336** — ADR **0254** grade de identidade **DETERMINÍSTICO** (`scripts/design-identity-grade.mjs`, σ=0 anti-alucinação; cura o LLM-judge 91→71). Hoje **66/100** (refino justo v1.1 creditou 1823 controles shared). Piores: layout 0 · foco 13→71 · movimento 24→77.
5. **#2340** (revitalização, bloco único) — UI-**0018** mata snapshots zip-cowork (UI-0010/0012 superseded, append-only) · 9 lápides de docs com `morreu_porque` (histórico negativo) · SSOT INDEX **§3e Lápides** + Manual/primitivos/grade visíveis · `DESIGN.md` (porta) consertada (parava de apontar pro `_BACKUP`/UI-0010 mortos) · **TESTE** `DesignEntryPointAndTombstonesTest` (porta resolve 62/62 + lápide honesta).
6. **#2341** — faxina: next_review nos 6 docs vivos core (anti-rot freshness-checker).

## Artefatos gerados (canon na main)
- ADRs: **0253** (primitivos), **0254** (grade determinístico), **UI-0018** (canon visual vivo).
- Código: `resources/js/Components/layout/*` (6 primitivos + barrel) · `scripts/design-identity-grade.mjs` + `config/design-identity-baseline.json` (ratchet) · `.github/workflows/design-identity-gate.yml`.
- Docs canon: **`MANUAL-IDENTIDADE.md`** ("Clareza Confiante" — a VOZ visual) · INDEX-DESIGN-MEMORIAS §3e · DESIGN.md porta.
- Testes: `tests/Feature/Design/DesignEntryPointAndTombstonesTest.php` (NOVO) + `Modules/OficinaAuto/.../ServiceOrderItemRow` test.
- Lápides (append-only, com morreu_porque): UI-0010/0012, BRIEFINGs, CATALOGO, sidebar-rail, GUIA-SIDEBAR, AUTOMATION-ROADMAP, from-claude-design/*, audits/2026-04-24.

## Persistência
- **git:** 7 PRs merged na main (#2332/2333/2334/2335/2336/2340/2341). Webhook→MCP propaga docs.
- **MCP:** nenhuma task nova criada (off-cycle; trabalho foi doc/ADR canon via git).

## Próximos passos pra retomar
```
/continuar    # ou: ler ESTE handoff
node scripts/design-identity-grade.mjs    # estado da identidade (66/100, meta 85)
```
**Capítulo aberto: 66 → 85 (a elevação)** — encarnar o Manual no código (precisa gate visual Wagner):
1. **ADR de type-scale** (token `2xs`) → tokeniza 2.230 `text-[px]` (tipografia 61↑). _Doc, sem UI global, sem gate._
2. **Recraftar Button + Card** voz Clareza Confiante (focus-ring roxo, motion, densidade) → foco/movimento↑. _UI global → gate visual._
3. **Codemod** Stack/Inline nos top-ofensores → layout 0↑.
+ Faxina restante: next_review nos ~14 docs vivos do long-tail · dedup 2 GLOSSARYs.

## Lições catalogadas
- **L (a que o Wagner pegou):** a **continuação entre sessões quebrou porque esta sessão não tinha handoff** — `/continuar` lê o handoff mais recente, que era o ANTERIOR (CSS trail 1650). Sessão-épico SEM R12 = próxima sessão resume contexto errado. **Disparar `encerrar-sessao` é o que conserta.** (defesa: hook `force-r12-closing-signal`).
- **Grade subjetivo (LLM) alucina** (91→71 mesmo PR, σ=14) → SCREEN-GRADE §7 T1 manda **endurecer em critério binário/medível** (máquina conta, não opina). Virou ADR 0254.
- **"Por parte" não revitaliza** — 7 PRs encadeados se cross-referenciam → isolados o gate Design-index falhava. **1 bloco coordenado** (#2340) resolve.
- **Histórico negativo é first-class** (Wagner): lápide preserva o morto COM a lição (`morreu_porque`), nunca esconde. `_BACKUP-NAO-USAR-` intocado.
- **Worktree fiel off origin/main obrigatório** pra frontend/lint (eslint Windows≠CI) — repetida.

## Pointers detalhados
- Voz/identidade: `memory/requisitos/_DesignSystem/MANUAL-IDENTIDADE.md` · SSOT: `INDEX-DESIGN-MEMORIAS.md` (§3e Lápides)
- Grade: `scripts/design-identity-grade.mjs` (ADR 0254) · Primitivos: `resources/js/Components/layout/` (ADR 0253)
- Análise origem: `memory/sessions/2026-06-04-analise-tela-venda-vs-oficina.md` (gatilho da trilha)
