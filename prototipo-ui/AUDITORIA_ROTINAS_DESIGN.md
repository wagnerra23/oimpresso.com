# AUDITORIA_ROTINAS_DESIGN.md — mapa das rotinas de design (F0)

> **Disparo F0** da proposta [`COWORK_NOTES.amendment-otimizar-rotinas-design-2026-05-31.md`](COWORK_NOTES.amendment-otimizar-rotinas-design-2026-05-31.md) (G1–G6).
> **Autor:** [CL] (Claude Code) · **Data:** 2026-05-31 · **Mãe:** ADR 0114 · gate aplicado: PROTOCOL §10.4, **validado contra `origin/main` `e443c2ea4`**.
> **Escopo deliberado:** MEDIR, não mexer. **NÃO** altera `PROTOCOL.md` nem nenhuma skill. Não cunha ADR. Não mergeia código.
> **Método:** grep de artefatos reais por data em `prototipos/*/`, `memory/requisitos/*/`, `memory/sessions/`, `memory/governance/` + `git show origin/main:` blob-a-blob (não confiar em working tree de branch).
>
> 🔗 **Não-duplicação:** esta auditoria **complementa** (não repete) o processamento §10.4 da fila Cowork de 2026-05-31 ~07:00 ([CODE_NOTES](CODE_NOTES.md) + SYNC_LOG) — aquele cobriu **hops / validação-de-prompt / lint**; este cobre o ângulo que faltou: **fragmentação dos motores de score**.

---

## 0. TL;DR — o achado novo (e o que JÁ está resolvido)

**Achado novo (a parte que ninguém mediu ainda):** a medição de "qualidade de design" do oimpresso **fragmentou em 6 motores**, não 4 (a proposta subconta). E eles se partem em **duas camadas de destino oposto**:

| Camada | Motores | Custo | Estado real (main) |
|---|---|---|---|
| **A — cara / LLM `design:*` / com render** | `mwart-comparative`, `design-deep-analysis`, gate F1.5, gate F3.5 | alto (5-6 `design:*` por tela + render/screenshot) | ⚠️ **DORMANTE desde meados de maio** |
| **B — barata / estática / CI-ratchet** | `module:grade` (D1-D9), `screen-grade` (16-dim, 222 telas), ESLint `ds/*` + `ds-report`/`DS_ADOCAO_INDICE` | baixo (lê `.tsx`/AST, sem render, gate de CI) | ✅ **VIVA e escalou** (222 telas, 2026-05-30) |

**O `PROTOCOL.md` descreve só a Camada A** (F1.5/F3.5/`critique-score.json`/`a11y-report.md`) — e ignora `module:grade`/`screen-grade`/`ds-report`, que são o que de fato roda. **A Camada A morreu de custo, não de erro:** `design:*` + render + aprovação síncrona não escala pra 222 telas; a Camada B escalou e venceu por seleção natural.

**O que JÁ está resolvido em `main` (corrijo a premissa da proposta — não é gap):**
- ✅ `scripts/ds-report.mjs` + `npm run ds:report`/`ds:report:write` **existem** (criados 2026-05-30). A proposta acerta: "não recriar".
- ✅ Os 3 canais de retorno (§10.2) **foram reancorados em 2026-05-30** e estão **vivos** (SYNC_LOG/HANDOFF/CODE_NOTES correntes hoje). A dívida do G4 ("[W] carteiro / HANDOFF 15d stale") **era real em 30/05 e foi consertada no mesmo dia** — não é estado atual.
- ✅ O loop virou **0-humano** em 2026-05-31 00:45 (gates CI no lugar de [W2], merge autônomo `--admin`) + **ADR 0241**. Isso **leapfrogga o G3** (7→4 hops): já é ~0 hop humano.
- ✅ Os itens hops/§10.4/lint da fila Cowork já foram adjudicados às ~07:00 (CODE_NOTES).

→ **Sobra como trabalho real:** **G1/G2/G6** (consolidar os 6 motores) + a **metade `.css` do G5** (Stylelint — inexistente). E o G1 **já aconteceu de fato na Camada B** — o gap não é skill `design-score` nova; é **religar a Camada A como aprofundamento sob demanda da Camada B**.

---

## 1. Dispara de fato vs letra morta

Cada rotina, trigger, último artefato real e data (evidência em `origin/main`).

| Rotina | Artefato | Trigger vivo? | Último disparo real | Veredito |
|---|---|---|---|---|
| **`mwart-comparative`** (skill) | `memory/requisitos/<Mod>/<tela>-visual-comparison.md` (15-dim + `design:*`) | sim (Edit em Page sem comparison / `/mwart-comparative`) | **2026-05-17** (`Sells/index-r1`) · **40 arquivos**, todos 05-08→05-17 | ⚠️ **dormante** (14d de silêncio; substituído na prática por `screen-grade`) |
| **`design-deep-analysis`** (skill) | `memory/sessions/*-design-deep-*.md` | sim (`/design-deep <persona>`) | **NUNCA** — `git ls-tree -r origin/main memory/sessions/ \| grep design-deep` = **0** | ❌ **letra morta** (skill + `framework-15-dimensoes.md` + RUNBOOK existem; zero artefato na história) |
| **Gate F1.5** (`design:design-critique`) | `prototipos/<tela>/critique-score.json` | só no loop manual | crit. real só em `_BACKUP-NAO-USAR/` (05-15, 05-19) + `cowork-2026-05-26-comunicacao-visual/`. **Zero em `prototipos/` ativo** | ⚠️ **dormante** (gate no papel; artefato vivo não é mais gerado) |
| **Gate F3.5** (`design:accessibility-review`) | `prototipos/<tela>/a11y-report.md` | só no loop manual | **NUNCA como standalone** — `find -name a11y-report.md` = 0 (a11y só embutida na §F do visual-comparison) | ❌ **letra morta** (artefato canônico do gate nunca materializou) |
| **runbook `design-sync.md` §A.3** | delega a `mwart-comparative` | manual (Wagner cola zip) | last update **2026-05-08**, ainda cita `mwart-comparative` **V3** (skill é V4) | ⚠️ **stale/redundante** (subsumido pela skill) |
| **`cockpit-runbook`** (skill) | `RUNBOOK-<tela>.md` (**doc, não score**) | sim | `RUNBOOK-manifestacao.md` 2026-05-09 (eixo distinto) | 🟡 esporádica · documenta, não pontua |
| **`cowork-prototype-replication`** | `.tsx` + `prototipos/<tela>/` (**não score**) | sim | Kanban Oficina 2026-05-13 | 🟡 sob demanda · traduz, não pontua |
| **`comparativo-do-modulo`** | `CAPTERRA-INVENTARIO.md` (**não `design:*`**) | sim | eixo Capterra/mercado | 🟢 outro eixo |
| **`charter-first/write`** | `*.charter.md` (**contrato, não score**) | sim (Tier A) | governança de tela | 🟢 outro eixo |
| **`module:grade` V4** (`ModuleGradeV4Command`) | nota /100 D1-D9 por **módulo** | sim · **gate CI** `module-grades-gate` (ADR 0153/0155) | ativo (heurístico estático, **0 `design:*`**) | ✅ **vivo** (Camada B) |
| **`screen-grade`** (`SCREEN-GRADE-BOARD-2026-05-30.md`) | nota /100 16-dim por **tela** (222 telas, baseline JSON) | workflow `screen-grade-estado-arte` (19 agentes) · **ratchet ADR 0236** | **2026-05-30** · média 75/100 | ✅ **vivo, o mais recente** (LLM mas **STATIC**: lê `.tsx`, sem render, **0 `design:*`**) |
| **ESLint `ds/*`** (`eslint.config.js:114-160`) + `ds-report.mjs`→`DS_ADOCAO_INDICE` | violações `ds/*` por módulo (ratchet ADR 0209) | `npm run lint` / `npm run ds:report` | ativo · `DS_ADOCAO_INDICE` corrente · `ds-report.mjs` **no main** (criado 05-30) | ✅ **vivo** (Camada B) |

---

## 2. Sobreposição — rotina × `design:*` × artefato × dimensões

A bateria `design:*` é invocada por **2 motores LLM** + os 2 gates (que SÃO a bateria do `mwart-comparative` desmembrada):

| Rotina | `design:*` que invoca | Artefato | Dimensões | Render? |
|---|---|---|---|---|
| `mwart-comparative` | critique · system · **handoff** · ux-copy · accessibility-review · research-synthesis (**6**) | `<tela>-visual-comparison.md` | **15** (inline na SKILL) | sim |
| `design-deep-analysis` | critique · system · ux-copy · accessibility-review · research-synthesis (**5**) | `*-design-deep-*.md` | **15** ([`framework-15-dimensoes.md`](../memory/requisitos/_DesignSystem/framework-15-dimensoes.md), ponderado por persona) | sim |
| Gate **F1.5** | `design:design-critique` | `critique-score.json` | 5-cat critique | sim |
| Gate **F3.5** | `design:accessibility-review` | `a11y-report.md` | WCAG 2.1 AA | sim |
| `screen-grade` | **nenhuma** | board + baseline JSON | **16** ([`SCREEN-GRADE-METODO.md`](../memory/requisitos/_DesignSystem/SCREEN-GRADE-METODO.md)) | **não** |
| `module:grade` | **nenhuma** | snapshot /100 | **D1-D9** | **não** |

**Redundância real (G1 confere):** 4 rotinas (mwart-comparative, design-deep, F1.5, F3.5) compartilham a mesma bateria `design:*` e ~15 dimensões muito sobrepostas. `framework-15-dimensoes.md` (design-deep) e as 15-dim inline do mwart são **o mesmo conceito duplicado**; as 16-dim do screen-grade (Density, Speed-to-task, Error-recovery, Affordance, Pre-Flight-conformance…) **repetem nominalmente** o framework-15-dim.
→ **4 dicionários de dimensão distintos** (15 mwart · 15 framework-15-dim · 16 screen-grade · D1-D9 module) medindo coisas amplamente sobrepostas, em 3 granularidades (tela-migração / tela-persona / tela-estática / módulo).

---

## 3. Custo real

**3.1 Hops manuais por tela** — o loop canônico do PROTOCOL era `F0[W]→F1[CC]→F1.5[CD]→F2[W2]→F3[CL]→F3.5[CA]→F4[W2]` = 7 gates, 3 hops humanos síncronos. **Mas isso é histórico:** desde 2026-05-31 00:45 o loop é **0-humano** (ADR 0241) — gates CI no lugar de [W2], merge autônomo. **O G3 (7→4) já foi superado por ~0.**

**3.2 Quantas vezes `design:*` re-roda por tela** — DESENHADO: até ~5-6 `design:*` × 4 rotinas = redundância teórica de 3-4× por tela (o número do G1). **OBSERVADO: a redundância nunca se materializou** — as rotinas **não co-dispararam, serializaram em desuso**: só `mwart-comparative` rodou de fato (40 telas, 05-08→05-17), `design-deep` rodou 0×, os gates pararam de gerar artefato, e depois de 05-17 a medição **migrou pra `screen-grade` (estático, 0 `design:*`)**. → O desperdício real **não foi** "rodar `design:*` 4×"; foi **(a)** manter 4 rotinas + 4 dicionários de dimensão que nunca co-firam, e **(b)** a Camada B (barata) **não conversar** com a Camada A (screen-grade não lê nem alimenta os `visual-comparison`; o PROTOCOL não sabe que screen-grade existe).

---

## 4. GATE §10.4 — veredito contra `origin/main` (feito por [CL] sozinho)

Regra de ouro §10.4: onde a checagem tem resposta no git, [CL] decide e age — só informa [W]; escala só o subjetivo.

| Afirmação / dependência da proposta | Realidade em `origin/main` | Veredito [CL] |
|---|---|---|
| `ds:report`/`ds-report.mjs` "já existe — não recriar" | ✅ **existe** (`scripts/ds-report.mjs` + `package.json` `ds:report`/`ds:report:write`, criado 05-30) | ✅ **CONFIRMADO — não recriar.** (a v1 desta auditoria, feita em branch stale −46, errou aqui — corrigido) |
| `REGRAS_DS_LINT.md` + ESLint `ds/*` "já existe" | ✅ existem (`eslint.config.js:114-160`, ratchet ADR 0209) | ✅ **metade ESLint do G5 já DONE — não recriar** |
| `REGRAS_STYLELINT_CSS.md` "spec pronta" | ❌ **não existe** (repo-wide = 0; sem stylelint config) | 🔴 **afirmação FALSA.** Metade `.css` do G5 = **trabalho novo** (spec + config) |
| G1: "não duplicar skill que já faz o score" | `mwart-comparative` + `design-deep-analysis` + `module:grade` + `screen-grade` + `ds/*` = **6 motores** | ✅ **risco CONFIRMADO — NÃO cunhar `design-score` do zero.** Consolidar no que existe |
| §10.2 "canais stale / [W] carteiro" (premissa do G4) | canais **reancorados 05-30** + 0-humano 05-31; correntes hoje | 🟡 **premissa histórica, já consertada.** G4 essencialmente DONE |
| "não cunhar número de ADR (soberania [W], ADR 0238)" | — | ✅ **RESPEITADO.** ADR de evolução nasce rascunho mãe 0114, sem número, NÃO mergeia |
| Sequência `G4→G5→G2→G1→G6→G3` | proposta [CC] | ⏸️ **subjetivo (prioridade) → [W]** |

**Veredito:** a proposta **PASSA** (não viola append-only / não renumera ADR / não traz rascunho pro canon). Mas, contra o `main` real, **a maioria dos G já está feita ou superada** — sobra como trabalho genuíno: **G1/G2/G6** (consolidar motores) + **G5-`.css`** (Stylelint, inexistente). Nada mergeado de código.

---

## 5. Implicações pra G1–G6 (insumo; ordem = decisão [W])

- **G1 (um motor):** não é skill nova. A consolidação **já aconteceu na Camada B** (`screen-grade`). Alvo: **`screen-grade` como gate de 1ª linha** + **`mwart-comparative` como aprofundamento sob demanda** disparado quando uma tela cai abaixo do corte (PLANO-DESIGN-TELAS já lista as 44 telas <70). **Aposentar `design-deep-analysis`** (0 disparos) ou fundi-lo no `mwart-comparative`. Unificar os 4 dicionários de dimensão num só.
- **G2 (um schema):** `screen-grades-baseline-*.json` **já é** a fonte machine-readable por tela; `DS_ADOCAO_INDICE` cobre `ds/*`. `design-report.json` deve **agregar** esses dois (+ a11y quando a Camada A rodar), não criar 3ª fonte.
- **G3 (auto-check):** **superado** — loop já 0-humano (ADR 0241). Resta só o auto-check numérico do produtor (destravado pelo G1).
- **G4 (retorno automático):** **essencialmente DONE** — `ds-report` existe + loop 0-humano escreve os 3 canais. Falta só o hook pós-merge formal (opcional).
- **G5 (ratchet):** **metade DONE** (ESLint `ds/*` 0209 + screen-grade ratchet 0236 + `module-grades-gate` CI). Falta **só Stylelint `.css`** — e **sua spec `REGRAS_STYLELINT_CSS.md` ainda não existe** (escrever antes de "ligar").
- **G6 (não regenerar):** o `screen-grade` baseline + ratchet 0236 já fazem "nota só sobe / só ataca o ☐" por tela; falta estender o princípio às rotinas da Camada A (depende do schema único do G2).

---

## Changelog
| Versão | Data | Autor | Mudança |
|---|---|---|---|
| v1.0 | 2026-05-31 | [CL] | F0: inventário dispara-vs-morta + sobreposição + custo + gate §10.4 + implicações. **Validado contra `origin/main`** (v0 anterior foi feita em branch stale −46 e tinha 3 achados errados — corrigidos aqui: `ds:report` existe, canais já reancorados, G3 superado pelo 0-humano). Não muda PROTOCOL/skill. |
