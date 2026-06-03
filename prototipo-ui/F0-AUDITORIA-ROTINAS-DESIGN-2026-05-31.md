# F0 — Auditoria das rotinas de design (read-only · medido)

> **Quem:** Claude Code `[CL]` · **Quando:** 2026-05-31 · **Disparado por:** `COWORK_NOTES.amendment-otimizar-rotinas-design-2026-05-31` (Cowork `[CC]`)
> **Natureza:** read-only. Este documento **NÃO altera** nenhuma skill, script, hook, PROTOCOL.md ou tela. É o gate "medir antes de mexer" que o próprio amendment exige antes das otimizações G1–G6.
> **Método:** mesmo padrão de [AUDITORIA_DS_V4.md](AUDITORIA_DS_V4.md) — inventário do repo real + contagens via `eslint`/`find`/`grep`, sem chute.
> **Pareia com:** o resumo das 6 otimizações (G1–G6) + sequência `G4→G5→G2→G1→G6→G3` que o Wagner trouxe do lado Cowork.

---

## 0. Sumário executivo

> **⚠️ ERRATA 2026-05-31 (pós-PR #2054):** este F0 foi medido no working tree `feat/staging-ct100` (root). Ao basear o PR do G5 em **origin/main** (a linha viva — 64 commits à frente de staging-ct100), 2 correções: (1) **`ds:report` JÁ EXISTE** em origin/main (`scripts/ds-report.mjs` + scripts npm) — o "keystone (K)" da Entrega 4 já está implementado lá, não só speccado; isso reforça que G2/G4/G6 destravam mais rápido do que o F0 supôs; (2) ESLint ratchet `ds/*` confirmado em origin/main. O `main` **local** do Wagner está 155 commits stale (`git fetch` recomendado). **G5-stylelint entregue em [PR #2054](https://github.com/wagnerra23/oimpresso.com/pull/2054)** — `stylelint-gate` passou no CI.

A proposta do amendment é **forte (8/10)**: diagnóstico de causa-raiz certeiro, sequência barato→caro com dependências respeitadas, e o método F0 é a cultura do projeto. A medição confirma os 4 sintomas (redundância de score, artefatos dispersos, gates-ferry, carteiro manual). Mas a auditoria revela **3 ajustes materiais** que o resumo não tinha como saber — porque a informação não voltou do Code pro Cowork (o que, por si só, é a prova empírica do G4):

1. **G5 já está ~85% pronto, não no zero.** As 6 regras `ds/*`, o baseline ratchet (669 linhas), os scripts npm e o CI gate **já existem e rodam**. Falta só Stylelint (CSS) + virar *required check*. G5 é **fechar**, não **construir**.
2. **`ds:report` é o keystone oculto.** G2 (schema único), G4 (retorno automático) e G6 (checklist) **todos dependem** de existir um report gerável por máquina — e ele está só speccado, não implementado. Ele deveria vir **antes** na sequência, não emergir como subproduto.
3. **Dois riscos de design na proposta:** G1 não pode achatar 3 eixos de score ortogonais numa nota só; G3 não pode trocar verificação independente por auto-check (perde o adversarial). Detalhe nas Entregas 2 e 4.

E um achado que vale por si: **`a11y-report.md` = 0 arquivos no repo inteiro.** O gate F3.5 (a11y) do PROTOCOL.md nunca produziu um artefato. É teatro — ou foi sempre `/a11y-override`. Qualquer otimização que "dobre" esse gate (G3) está otimizando um gate que não roda.

---

## Entrega 1 — Inventário das rotinas que rodam hoje

### 1a. Motores de avaliação/score (o alvo do G1)

| Rotina | Tipo | O que mede | Escala | Quando dispara |
|---|---|---|---|---|
| `design:design-critique` ⭐ | skill plugin Anthropic | crítica visual 5 categorias + benchmark | 0–100 | via `mwart-comparative` |
| `design:design-system` | skill plugin | consistência tokens/componentes | qualitativo | via `mwart-comparative` |
| `design:ux-copy` | skill plugin | microcopy (labels/CTA/erros) | qualitativo | via `mwart-comparative` |
| `design:accessibility-review` | skill plugin | WCAG 2.1 AA | pass/fail + critical | F3.5 (raramente) |
| `design:design-handoff` | skill plugin | specs pré-impl | — | via `mwart-comparative` |
| `design:research-synthesis` | skill plugin | persona/padrões | — | via `mwart-comparative` |
| **`mwart-comparative` V4** | skill local (Tier A) | **15 dimensões** + orquestra as 6 acima | nota /10 + 0–100 | Edit/Write em Page Inertia |
| `module-grades-gate` / `avaliar-modulo` | skill local + `php artisan module:grade` | rubrica `module-grade-v3` | /100 (9 dims) | antes de fechar US |
| `pr-ui-judge-manual` | skill local | julgamento UI de PR | — | review manual |
| `design-deep-analysis` | skill local | análise profunda | — | sob demanda |
| `audit-constituicao` | skill local | 6 dimensões pós-Constituição | — | auditoria |

> **13 skills locais** de design/UI no total (`audit-constituicao`, `charter-first`, `charter-write`, `cockpit-runbook`, `constituicao-ui-aware`, `design-deep-analysis`, `module-grades-gate`, `mwart-comparative`, `mwart-process`, `mwart-quality`, `pageheader-canon`, `pr-ui-judge-manual`, `ui-component-creator`) + **6 skills `design:*`** do plugin. Uma única tela pode ser "pontuada" por `design-critique` (0–100), `mwart-comparative` (15-dim /10), `module-grade-v3` (/100) e `pr-ui-judge-manual` — **4 réguas diferentes, 4 unidades diferentes.** É o que o G1 quer unificar.

### 1b. Guards anti-drift (o alvo do G5) — **já existem**

| Peça | Path | Estado |
|---|---|---|
| 6 regras `ds/*` | `eslint.config.js` | ✅ coladas (`no-native-radio/checkbox/select`, `no-rounded-xl`, `no-arbitrary-color`, `no-adhoc-status-text`) |
| Baseline ratchet | `config/eslint-baseline.json` | ✅ 669 linhas / 58 KB (29/maio) |
| Motor do ratchet | `scripts/eslint-baseline.mjs` | ✅ existe |
| Scripts npm | `package.json` | ✅ `lint:baseline:write` + `lint:baseline:check` |
| CI gate | `.github/workflows/eslint-gate.yml` | ✅ falha só em delta>0 (ADR 0209) |
| Stylelint (CSS) | — | ❌ **AUSENTE** |
| `eslint-gate` como *required check* | GitHub branch protection | ❓ decisão Wagner (não verificável no FS) |
| Definição de pronto = tripé | `REGRAS_DS_LINT.md §5` + `DS_ADOCAO_INDICE.md` | ✅ doutrina escrita |

### 1c. Gates do loop (o alvo do G3) — PROTOCOL.md, 7 fases

`F0 brief → F1 design → F1.5 critique → F2 screenshot → F3 code → F3.5 a11y → F4 merge` (6 papéis: `[W] [CC] [CD] [CL] [CA] [W2]`). Auto-check parcial **já existe** fora dos gates formais: o checklist pre-commit de 7 greps em `CODE_DESIGN_CONTRACT.md` (cor/componente/spacing/radius/type/redeclaração).

### 1d. Carteiro / handoff (o alvo do G4)

| Arquivo | Função | Como é escrito hoje |
|---|---|---|
| `SYNC_LOG.md` | timeline append-only | **manual** (`[CL]`/`[W]` digitam cada linha) |
| `HANDOFF.md` | estado vivo ("onde estamos") | **manual**, sobrescrito à mão |
| `COWORK_NOTES.md` | inbox Wagner→Cowork | manual |
| `CODE_NOTES.md` | outbox Code→Wagner | manual |
| 42 hooks em `.claude/hooks/` | automações | nenhum escreve SYNC_LOG/HANDOFF (existe `post-merge-ui-smoke-required.ps1`, escopo diferente) |

---

## Entrega 2 — Mapa de redundância (onde roda 3–4×)

| Causa-raiz (G) | Evidência medida | Severidade |
|---|---|---|
| **Score multiplicado (G1)** | 4 réguas distintas para a mesma tela (critique 0–100 · mwart 15-dim · module-grade /100 · pr-ui-judge); `mwart-comparative` sozinho invoca 6 skills `design:*` por tela | 🟡 alta — mas ver risco abaixo |
| **Artefatos dispersos (G2)** | **25** `.md` na ponte `prototipo-ui/` · **66** `*-visual-comparison.md` em `memory/` · **3** `critique-score.json` · **1** `COMPARISON*.md` · **0** `a11y-report.md` | 🔴 muito alta |
| **Gates-ferry (G3)** | 7 hops; mas **3/7 telas** têm `critique-score.json` e **0/N** têm `a11y-report.md` → os gates "pesados" raramente produzem prova | 🟡 média (gate fantasma, não excesso) |
| **Carteiro manual (G4)** | `HANDOFF.md` congelado em **2026-05-15** enquanto `SYNC_LOG.md` tem eventos até **2026-05-25** e o trabalho seguiu → **16 dias de drift de estado** | 🔴 muito alta |

**Leitura crítica das redundâncias:**

- **G1 é real, mas a redundância é de *régua*, não de *cálculo*.** As 4 notas medem eixos diferentes: visual (critique), estrutura/UX (15-dim), governança de módulo (module-grade), adoção-DS (`ds/*`). Unificar o **invólucro** (um comando, um schema de saída) é ganho limpo. Unificar a **nota** numa só achata o diagnóstico → ver Entrega 4, risco G1.
- **G2 é o ganho mais óbvio e barato em risco.** 66 `visual-comparison.md` + 3 `critique-score.json` + 0 `a11y-report.md` provam que o "report por tela" hoje é 3 formatos meio-preenchidos. Um `design-report.json` único resolve.
- **G3 mira o problema errado.** O gargalo medido não é "hops demais" — é "gates que não produzem prova" (a11y=0). Dobrar/colapsar gate vazio não economiza nada. O valor está em **fazer o gate rodar** (gerar o artefato sempre), não em removê-lo.
- **G4 está empiricamente provado** pelos 16 dias de HANDOFF stale. É o item de maior ROI/risco.

---

## Entrega 3 — Baseline quantificado (os números "antes")

| Métrica | Valor medido (2026-05-31) | Fonte |
|---|---|---|
| Hops no loop | **7** (F0/F1/F1.5/F2/F3/F3.5/F4) | `PROTOCOL.md §2` |
| Skills de design/UI (local + plugin) | **13 + 6 = 19** | `ls .claude/skills` + `mwart-comparative` |
| Réguas de score distintas por tela | **4** (unidades diferentes) | inventário 1a |
| `.md` na ponte `prototipo-ui/` | **25** | `ls *.md` |
| `*-visual-comparison.md` em `memory/` | **66** | `find` |
| `critique-score.json` no repo | **3** | `find` |
| `a11y-report.md` no repo | **0** | `find` |
| Regras `ds/*` ligadas no ESLint | **6/6** | `grep eslint.config.js` |
| Baseline ratchet `ds/*` | **639 violações / 197 arquivos** (total geral 1455) | `config/eslint-baseline.json` + `DS_ADOCAO_INDICE.md` |
| Stylelint (CSS) | **ausente** | `grep package.json` |
| `ds:report` (script) | **não existe** (só speccado em `REGRAS_DS_LINT.md §4`) | `package.json` |
| Hooks que escrevem SYNC_LOG/HANDOFF | **0** de 42 | `ls .claude/hooks` |
| Dias de HANDOFF stale | **16** (15→31 maio) | `HANDOFF.md` header |

---

## Entrega 4 — Tabela atual% → target% por otimização

Maturidade atual **medida** (não estimada), esforço (escala recalibrada ADR 0106: baixo ≈ 1 sessão IA-pair), risco, gate de ADR/Wagner e dependências.

| G | Otimização | Atual | Target | Esforço | Risco | ADR/Decisão | Depende de |
|---|---|---:|---:|---|---|---|---|
| **G5** | Ratchet anti-drift `ds/*` + Stylelint | **85%** | 100% | baixo (fechar) | baixo | Wagner: *required check* | — |
| **G4** | Retorno automático (SYNC_LOG+HANDOFF via hook) | **5%** | 100% | baixo | baixo | não | parte "ds:report:write" precisa do `ds:report` |
| **(K)** | **`ds:report` — keystone oculto** | **10%** (speccado) | 100% | baixo-médio | baixo | não | regras `ds/*` (✅ existem) |
| **G2** | Schema único `design-report.json` por tela | **10%** | 100% | médio | baixo | leve (formato; toca ADR 0107) | (K) |
| **G6** | Checklist tela×gate idempotente | **25%** | 100% | baixo-médio | baixo | não | G2 |
| **G1** | Motor único de score | **15%** | 100% | médio | **médio** (não achatar eixos) | toca ADR 0107 (formato critique) | G2 |
| **G3** | Auto-check no produtor (7→4 hops) | **20%** | parcial | alto | **alto** (adversarial) | **SIM — emenda ADR 0114** | G1 + G2 |

### Riscos materiais (detalhe)

- **Risco G1 — achatamento de eixos.** Visual (critique 0–100), adoção-DS (`ds/*` count→0), a11y (WCAG) e governança (module-grade /100) são ortogonais. **Recomendação:** o "motor único" é UM CLI + UM schema (`design-report.json`) que **agrega** as notas mantendo-as **separadas por eixo**. Nunca uma média ponderada única — isso destrói o diagnóstico que cada régua existe pra dar.
- **Risco G3 — perda do adversarial.** O valor de um gate é *quem verifica ≠ quem produz*. Auto-check no produtor (Cowork/Code) é ótimo como **pré-gate obrigatório** (anexa o report e reduz round-trips), perigoso como **substituto** do verificador independente. **Recomendação:** "7 hops → 4 síncronos + 3 auto-checks anexados", verificação independente vira **spot-check via ratchet**, não some. E exige emenda à ADR 0114 (muda o protocolo canônico).
- **Risco transversal — `a11y-report.md`=0.** Antes de "otimizar" o gate F3.5, decidir se ele **existe de fato**: ou se automatiza (`design:accessibility-review` grava o report sempre) ou se assume oficialmente que é `/a11y-override` by default e some do protocolo. Otimizar um gate-fantasma é ruído.

### Sequência: amendment vs. recomendação do F0

| | 1º | 2º | 3º | 4º | 5º | 6º | 7º |
|---|---|---|---|---|---|---|---|
| **Amendment** | G4 | G5 | G2 | G1 | G6 | G3 | — |
| **F0 (refinada)** | **G5-finish** ‖ **G4-parte-handoff** | **(K) ds:report** | G4-completo | G2 | G6 | G1 | G3 (ADR) |

**Por que o ajuste:** G5 está quase pronto — fechá-lo primeiro é a vitória rápida que dá confiança (e é independente). A parte SYNC_LOG/HANDOFF do G4 também é barata e independente, roda em paralelo. **`ds:report` (K)** é a dependência que destrava G4-completo + G2 + G6 de uma vez — promovê-lo a item explícito evita que G2/G4/G6 tropecem na sua ausência. G1 e G3 (os arquiteturais, com risco e ADR) ficam por último, como o amendment já acertou.

---

## Gates antes de qualquer implementação (o que o F0 trava)

1. **Decisão Wagner — escopo da rodada:** seguir a sequência refinada do F0, ou a original do amendment? (a diferença prática é só promover `ds:report` e reconhecer G5 como *finish*.)
2. **Decisão Wagner — `eslint-gate` vira *required check*?** (fecha G5; é branch-protection, só você pode.)
3. **ADR necessária — G3** muda o número de fases do PROTOCOL.md → emenda à [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md). Não implementar G3 sem ela.
4. **ADR leve — G1/G2** tocam o formato do `critique-score.json` ([ADR 0107](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)). Documentar o schema `design-report.json` como emenda.
5. **Confirmar com Cowork** as "4 entregas" originais do F0 que o resumo cortou — este doc cobre inventário + redundância + baseline + tabela atual→target; se o Cowork tinha outra quarta entrega em mente, reconciliar.

> **Nada foi alterado.** Próximo passo é decisão do Wagner sobre escopo da Onda 1 (G5-finish + G4-handoff são os candidatos de menor risco pra começar).
</content>
</invoke>
