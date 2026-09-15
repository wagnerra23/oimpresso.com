---
id: requisitos-governanca-programa-ondas-onda-7-paridade-prototipo-7b-lote-crm-jana-forja
titulo: Onda 7b — Lote Crm + Jana + Forja: casamento inventário↔tela
status: proposto
owner: W
criado: '2026-09-08'
etapa: onda-7-paridade-prototipo
related: ../PLANO-MESTRE.md
---

# Onda 7b — Lote Crm + Jana + Forja (casamento inventário↔tela)

> Status vivo do programa: [PLANO-MESTRE.md](../PLANO-MESTRE.md) §Status vivo (1 plano = 1 registro · ADR 0294).
> Método: [7a](7a-inventario-e-metodo.md). Este doc é o **resultado medido** de um lote, não método novo.

## Medida antes → depois

Porta viva única: `node scripts/qa/design-coverage.mjs` (rodada nas duas pontas do mesmo PR).

| medida | antes | depois |
|---|---|---|
| inventários de paridade (denominador) | 84 | 84 |
| 🔗 `parityLinked` | **18** | **27** |
| 🧩 órfãos | 66 | 57 |
| ❌ vínculo quebrado | 0 | **0** |

**Denominador declarado** (§5 2026-07-27): os 84 são o universo do próprio script. A varredura
completa do repo devolve 87 arquivos casando `visual-comparison`; os 3 a mais **não são
inventário** — uma ADR (`0107-…`), um script (`visual-comparison-staleness.mjs`) e uma cópia-patch
sob `prototipo-ui/design-docs/`. Conferido antes de citar o número, não presumido.

## Os 17 órfãos do lote, e a QUAL tela cada um pertence

A tela foi lida **no conteúdo** de cada inventário (`inertia_target` / `target_charter` /
`charter:`), nunca por semelhança de nome (§5 2026-06-30).

| # | inventário | tela lida no conteúdo | veredito |
|---|---|---|---|
| 1 | `Jana/Index-visual-comparison.md` | `Pages/Jana/Index.tsx` (charter v10) | 🔗 **vinculado** |
| 2 | `Jana/Memoria-visual-comparison.md` | `Pages/Jana/Memoria.tsx` (charter v2) | 🔗 **vinculado** |
| 3 | `Jana/Chat-visual-comparison.md` | `Pages/Jana/Chat.tsx` (`target_charter`) | 🔗 **vinculado** |
| 4 | `Jana/governance-dashboard-extension-visual-comparison.md` | **`Pages/governance/Dashboard.tsx`** — não é tela da Jana | 🔗 **vinculado** (achado B) |
| 5 | `Jana/Chat-header-tabs-visual-comparison.md` | `Pages/Jana/Chat.tsx` **+** `Pages/Jana/Index.tsx` | 🧩 órfão legítimo — escopo `header-only` cross-tela; o charter-alvo é o mesmo do #3, e `related_visual_comparison` é 1:1 |
| 6 | `Crm/_legado-fullpage/index-visual-comparison.md` | `Pages/Cliente/Index.tsx` | 🧩 órfão legítimo — pasta declarada **histórico** |
| 7 | `Crm/_legado-fullpage/create-visual-comparison.md` | `Pages/Cliente/Create.tsx` | 🧩 idem |
| 8 | `Crm/_legado-fullpage/edit-visual-comparison.md` | `Pages/Cliente/Edit.tsx` | 🧩 idem |
| 9 | `Crm/_legado-fullpage/show-visual-comparison.md` | `Pages/Cliente/Show.tsx` | 🧩 idem |
| 10 | `Crm/cliente-drawer-760-visual-comparison.md` | `Pages/Cliente/Index.tsx` (drawer 760) | 🧩 órfão — charter-alvo já vinculado (achado C) |
| 11 | `Forja/projectmgmt-index-visual-comparison.md` | `Forja/Triage/Index.tsx` + `Forja/Inbox/Index.tsx` | 🧩 órfão legítimo — **telas revogadas** |
| 12 | `Forja/triage-analista-visual-comparison.md` | `Forja/Triage/Index.tsx` | 🧩 idem |
| 13 | `TeamMcp/cc-sessions-visual-comparison.md` | `Modules/Forja/…/team-mcp/CcSessions/Index.tsx` | 🔗 **vinculado** (achado E) |
| 14 | `TeamMcp/scorecard-visual-comparison.md` | `Modules/Forja/…/team-mcp/Scorecard/Index.tsx` | 🔗 **vinculado** (achado E) |
| 15 | `TeamMcp/tasks-visual-comparison.md` | `Modules/Forja/…/team-mcp/Tasks/Index.tsx` | 🔗 **vinculado** (achado E) |
| 16 | `TeamMcp/team-visual-comparison.md` | `Modules/Forja/…/team-mcp/Team/Index.tsx` | 🔗 **vinculado** (achado E) |
| 17 | `TeamMcp/forja-cockpit-visual-comparison.md` | `Modules/Forja/…/team-mcp/Forja/Cockpit.tsx` | 🔗 **vinculado** (achado E) |

Os 6..9 caem no README **datado** da própria pasta (`_legado-fullpage/README.md`, 2026-06-01):
*"docs da UI antiga de Cliente (pré-drawer 760px) … Status: histórico"*. As telas seguem vivas,
mas já apontam para o inventário vigente — vincular o histórico por cima seria **trocar** um
ponteiro certo por um vencido, não somar cobertura.

## Achados (medidos, não deduzidos)

**A — Forja: 2 de 2 inventários apontam para telas que não existem mais.**
`git ls-files | grep -E "Pages/Forja/(Triage|Inbox)/"` devolve **vazio**. O próprio
`projectmgmt-index-visual-comparison.md` registra a causa: os charters *"foram revogados com as
telas na Onda 11 (2026-09-02, ADR 0367 D1/D6)"*. Nenhum vínculo forçado (passo 4 do método).
Consequência para a fila da onda: **Forja não tem tela de paridade a medir por estes dois docs.**

**B — inventário arquivado no módulo errado.** `governance-dashboard-extension-visual-comparison.md`
vive em `memory/requisitos/Jana/`, mas seu frontmatter diz `module: Governance` e
`charter_target: resources/js/Pages/governance/Dashboard.charter.md`. É exatamente o caso que o
método manda resolver lendo o conteúdo. O **vínculo foi declarado no charter certo**; o arquivo
segue na pasta da Jana — mover é realocação de documento, que tem dono e adversário próprios
(`document-relocation-*`), fora do intent deste PR.

**C — `Cliente/Index` aponta para o inventário superado (não mexido, é decisão [W]).**
O charter declara `cliente-index-visual-comparison.md` (2026-05-15 · descreve **drawer 480px** ·
`Gate F1.5: ⏳ Pendente`). O órfão `cliente-drawer-760-visual-comparison.md` (2026-05-21 ·
`status: validated-prod` · aprovado por [W] em smoke ao vivo) afirma no corpo que **substitui**
aquele paradigma. **Medido no código, não herdado do doc:** `resources/js/Pages/Cliente/Index.tsx:1953`
renderiza `className="cw-sheet w-[760px] sm:max-w-[760px]"`, e a linha 1792 registra
*"ClienteSheet: drawer 480 → 760 + 8 tabs cadastrais"*. Ou seja: a tela viva **é** o drawer 760,
e o inventário que o charter aponta descreve um drawer 480 que o código não tem mais.
O enunciado desta sessão proibiu mexer nos charters de Cliente já vinculados —
respeitado. Trocar o ponteiro **não move o contador** (um sobe, o outro vira órfão); o ganho seria
de verdade, não de cobertura.

**D — as telas da Jana estão fora do universo do `design-diff-lote`.**
O harness seleciona de `scripts/design-sync/state/application-report.json` por
`lifecycleState === 'anchored'`. Medido: **93 `screens`, 0 citando Jana**; as **54 fontes
distintas são todas `*-page.jsx`**, e a âncora das 3 telas da Jana é `jana-merge.jsx` — que aparece
no relatório só em `transportChanges`/`manifestFiles`, nunca como fonte de tela. Logo o
`--dry` lista 62 telas e nenhuma da Jana, embora `ancora.mjs Jana/<Tela>` resolva as três.
Estender o universo é mexer no dono do report — PR próprio, não este.

**E — 5 telas da Forja tinham inventário, e a contagem por NOME DE PASTA não os via.**
Os 5 arquivos de `memory/requisitos/TeamMcp/` declaram, no próprio `inertia_target`,
telas que vivem **dentro de `Modules/Forja/Resources/js/Pages/team-mcp/`** — logo são telas
da Forja, não de um módulo `TeamMcp`. A fila do lote foi montada por pasta (`Forja: 2`) e
por isso nasceu cega a eles; quem leu o conteúdo achou 5 telas vivas, com charter existente
e sem vínculo. É a mesma classe do achado B — **a pasta do inventário não é o módulo dele**,
e é a razão pela qual o método 7a manda ler o conteúdo. Os 5 foram vinculados.

O `forja-cockpit-visual-comparison.md` declara o alvo como glob (`team-mcp/Forja/*.tsx`);
conferido, ele resolve para **uma** Page — `Cockpit.tsx` — porque os outros 12 arquivos da
pasta são `_components/`, que o walker de cobertura exclui. Sem ambiguidade, sem adivinhação.

⚠️ O `team-visual-comparison.md` carrega `canon_reference: forja-mcp.jsx … ref expirada, ver
nota` — é a **âncora de protótipo** dele que está vencida, não o casamento com a tela. O
vínculo inventário↔tela vale; a âncora de design daquela tela é assunto da medição de runtime.

## O que este lote NÃO mediu, e por quê

**A medição de runtime (passo 5 do método) não foi executada.** Registro datado, com o comando que
reproduz — não é afirmação atemporal de bloqueio (§5 2026-09-01):

1. **Não existe o lado vivo neste ambiente** (2026-09-07): `curl --max-time 6 http://127.0.0.1:8000/`
   devolve **HTTP 000**; `php` não está no PATH; não há `.env` neste worktree. As 11 entradas de
   `.claude/launch.json` servem **só o lado protótipo** (`python -m http.server` sobre
   `prototipo-ui/cowork`) — nenhuma sobe o app.
2. **Mesmo com app de pé, o harness não seleciona a Jana** (achado D).

Medir **um lado só** responderia pergunta diferente da que a onda faz. Por isso **nenhum veredito
de paridade foi emitido** neste lote, e o passo 7 (verificar divergência antes de virar trabalho)
não produziu itens — não porque não haja divergência, mas porque a medição não rodou.

O que **foi** verificado, e vale: âncora resolvida pela porta **per-tela** para as 3 telas da Jana —
`node scripts/design/ancora.mjs Jana/{Index,Memoria,Chat} --staging prototipo-ui/cowork` → todas em
`prototipo-ui/cowork/Wagner/jana-merge.jsx`, frescor `2026-09-07T20:57:48.072Z`. **Não** é o
`chat-jana.jsx` proibido pela lápide §5 2026-08-10/08-11.

## Próximo passo desta onda (não executado aqui)

Medir no runtime as 3 telas da Jana exige, nesta ordem: (a) um lado vivo servindo a app;
(b) decidir se a Jana entra no `application-report` ou se as 3 medições saem uma a uma por
`design-diff.mjs --probe` nos dois lados. As duas são decisão [W] sobre a fila da onda.
