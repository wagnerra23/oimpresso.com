---
date: "2026-09-15"
topic: "Refutação GT-G5 r4 do lote #7377 (69 docs memory/requisitos · anchors) — 240 itens, 6 refutados (2,5%), PII 0 → REPROVADO"
authors: ["C"]
prs: [7377]
outcomes:
  - "REPROVADO: 6 de 240 itens refutados (error_rate 2,5% ≥ 2%) — 5 carimbos 'proveniência não determinada' em alvos cuja proveniência o próprio lote (ou o charter dono em origin/main) já determina, e 1 lápide de linha que blinda afirmação falsa sobre âncora VIVA"
  - "Todos os 10 SHAs citados existem em origin/main com data batendo, e cada um de fato apagou (D) o path da linha que o cita — as 72 lápides `removido em` têm o commit certo"
  - "PII: 0 hits em 119 linhas `+`, 7 de 7 controles positivos casaram · árvore limpa após sondagem · máquinas: rc literal colado, nenhum vermelho atribuível ao lote"
---

## TL;DR

**REPROVADO.** 240 itens verificados contra `origin/main` (b057d1a73d), **6 erros confirmados** → `error_rate = 2,5%` (≥ 2%). PII 0 hits (7/7 controles OK). Os erros são de uma classe só: o lote declara *"proveniência não determinada"* (ou carimba lápide de linha) em alvos cuja proveniência **é medível e já estava medida** — pelo próprio lote em outra linha, ou pelo charter dono em `origin/main`.

## Cabeçalho

| campo | valor |
|---|---|
| Base | `origin/main` = `b057d1a73dc6589b4e78df3a024a80e9f6c6fef2` |
| HEAD | `3ef4b7cbe6c0e35eca8660d91c453e76e91e2251` (branch `claude/orfaos-mudos-requisitos`) |
| Repo raso | `git rev-parse --is-shallow-repository` → **false** (datas de `git log` valem como recibo) |
| Sessão fresca | sim — instância nova, sem contexto do gerador; **não abri** `memory/sessions/*refutacao*` (r1/r2/r3 estão no diff do PR e ficaram fechados) nem `memory/handoffs/` de hoje |
| Corpo do PR / commit message | **não usados** como evidência |
| Refutador | Fable 5.1 (`claude-fable-5-1`) |
| Arquivo de evidência já existia? | não (`ls` → No such file, 1º comando da sessão) |

## §3 checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (fable; gerador declarado no ledger pelo PR — não lido aqui)
- [x] Amostra: **100%** anchors (tipo do lote = anchors; sem seleção aleatória, sem seed)
- [x] Cada item verificado contra `origin/main` (`git ls-tree origin/main`, `git diff-tree <sha>^ <sha>`, `git log --all --diff-filter=A`), não contra o diff
- [x] Cada REFUTADO com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff — 7 padrões, controle positivo em cada um, 0 hits
- [x] `error_rate_pct` calculado = **2,5** (≥ 2 → reprovado)
- [ ] Entry no ledger — **não é minha** (reprovado → devolve ao gerador; não escrevo no ledger)

## Escopo medido

```
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l   → 69 (todos M)
git diff --shortstat  origin/main...HEAD -- memory/requisitos            → 69 files, +119 −119
```

Fora de `memory/requisitos` o PR também toca `scripts/governance/charter-blueprint-pointers.mjs` (gate que lê a notação), `scripts/governance/reconcile-triplet.test.mjs` e 3 session logs `*refutacao*` (não abertos).

Três classes de mudança nas 119 linhas `+`:

| classe | ocorrências | claim implícita |
|---|---|---|
| **A** repontar `prototipo-ui/design-docs/cowork-inbox/…` → `prototipo-ui/cowork/Wagner/cowork-inbox/…` (+ `cowork/repair-page.jsx` → `cowork/Wagner/repair-page.jsx`) e remover a lápide `#7224` | 31 + 1 | o destino existe em `origin/main` |
| **B** lápide `_(removido em <data>, <sha>)_` | 72 | `<sha>` está em main, tem essa data e **apagou** o alvo da linha |
| **C** `_(alvo não resolve no repo — proveniência não determinada)_` | 11 | o alvo não resolve **e** a origem não é medível |
| **D** link relativo `../../../../memory/…` → `../../../../../memory/…` (UI-0013, UI-0017) | 4 | 5 níveis acima de `adr/ui/` = raiz |

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1 · Âncora existe em origin/main | 36 | 36 | 0 | `git ls-tree origin/main -- <path>` nos 31 destinos `cowork/Wagner/cowork-inbox/**` + `repair-page.jsx` + 4 alvos dos links 5-up; controle negativo com path inventado → 0 |
| 2 · Não revogada / lida pelo leitor real | 23 | 23 | 0 | 22 valores de frontmatter que ganharam sufixo (`canon_reference`×10 · `blueprint_cowork`×7 · `visual_source`×2 · `component`×1 · `fonte_cowork`×1 · `visual_source_html`×1): `rg --hidden` de cada chave em `scripts/ .github/ .claude/hooks/`; `ancora.mjs` lê `canon_reference` só pra **reportar** (L205-215, "nunca promovida") e imprime o sufixo verbatim; os demais não são lidos de `*-visual-comparison.md` por máquina nenhuma (leem charter); `visual-comparison-staleness` lê `inertia_target` (intocado). + `ancora.mjs Sells/CreateV3` → `âncora ✓ related_prototype` (vira base do G3) |
| 3 · Ação × veredito da prosa / afirmação sobre código | 83 | 77 | **6** | 72 lápides B: `git diff-tree -r --name-status <sha>^ <sha> -- <path>` → `D` em **72/72** (tabela abaixo); 11 anotações C: `git log --all --diff-filter=A -- <path>` (+ busca por basename) — controle positivo `prototipo-ui/tokens.css` → 6 commits |
| 4 · Célula íntegra | 70 | 70 | 0 | 2 linhas de tabela (UI-0012:61 → 3 colunas, UI-0017:59 → 2 colunas, iguais ao original); frontmatter dos 68 docs com `---` parseado com `js-yaml` → 67 ok + 1 falha **idêntica em origin/main** (`7b-lote-crm-jana-forja.md` 2:42, frontmatter não tocado); 0 linhas `+` com backtick ímpar |
| 5 · Máquina derivada | 21 | 21 | 0 | rc literal na seção abaixo; nenhum vermelho é causado por arquivo do lote |
| 6 · PII | 7 | 7 | 0 | 7 padrões × 119 linhas `+`, controle positivo sintético por padrão |
| **Total** | **240** | **234** | **6** | **error_rate = 6/240 = 2,5%** |

### G3 — commit × alvo (todas as 72 lápides B batem)

| sha | PR | data (author = committer) | em main | alvos da lápide → status no commit |
|---|---|---|---|---|
| `1070e3759b7` | #1218 | 2026-05-20 | sim | `prototipo-ui/prototipos/**` (188 D, dir zerado) · `_DesignSystem/ui_kits/cowork-2026-04-27/**` (15 D) · `prototipo-ui/_cowork-export-2026-05-15/{app,sidebar}.jsx` (D) |
| `4fad2f11f60` | #1210 | 2026-05-20 | sim | `ui_kits/cowork-2026-05-09/{prod-page,chat,orc-page,produto-app}.jsx`, `README.md` (D) |
| `7810bf5cb40` | #1156 | 2026-05-19 | sim | `prototipos/sells-index/Oimpresso ERP - Chat.html` (D) |
| `9da73296d34` | #3259 | 2026-06-23 | sim | `prototipos/clientes/{clientes,cockpit}.css` (D) |
| `8bd2b479db4` | #3262 | 2026-06-23 | sim | `cowork-2026-05-26-comunicacao-visual/**` (178 D) |
| `56059e784c4` | #5758 | 2026-08-13 | sim | `prototipo-ui/{oficina-page.jsx,oficina-page.css,vendas-flow.jsx}` (D) |
| `539efa2a8aa` | #6489 | 2026-08-31 | sim | `cowork/prototipo-ui-patch/prototipos/produto/**` (4 D) |
| `878e6069d1a` | #7213 | 2026-09-11 | sim | `prototipo-ui/tokens.css`, `design-system.css` (D) |
| `4f51a9ec781` | #7224 | 2026-09-11 | sim | `prototipo-ui/design-docs/**` (454 D; `cowork-inbox` 323, `handoff-crm/PEDIDO-CODE.md` D mesmo com `-M`) |
| `d86c977a8dd` | #7225 | 2026-09-11 | sim | `cowork/Wagner/legado/ds-v6/{tokens.css,gabarito-vendas.html}` (D) |

## REFUTADOS

### R1 — `memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:728`

- **Item:** `prototipo-ui/Design System v4.html` → lote carimbou `_(alvo não resolve no repo — proveniência não determinada)_`.
- **O que origin/main diz:** proveniência **determinada**: `git log --all --diff-filter=A -- "prototipo-ui/Design System v4.html"` → 6 commits; `git diff-tree -M -r 878e6069d1a^ 878e6069d1a` → **`D prototipo-ui/Design System v4.html`** (#7213, 2026-09-11, em main). E há **cópia viva** em main: `git ls-tree origin/main -- 'prototipo-ui/design-system/prototipo-ui/Design System v4.html'` → blob `6e97ef1e70d0`.
- **Porquê é erro do lote:** `878e6069d1a` é o **mesmo sha** que o lote usa 3× para `prototipo-ui/tokens.css` (AUDITORIA-design-as-code:128, UI-0017:20 e :59). Medir o irmão e declarar este "não determinado" é o gate ensinando a próxima sessão a desistir de um alvo que resolve em 1 comando — e cujo sucessor existe no repo.

### R2 — `memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:38`

- **Item:** link `[prototipo-ui/prototipos/financeiro-*/](../../../../../prototipo-ui/prototipos/)` → `_(proveniência não determinada)_`.
- **O que origin/main diz:** o alvo do link é o diretório `prototipo-ui/prototipos/`, apagado **inteiro** por `1070e3759b7`: 188 arquivos antes, 188 `D` no commit, 0 depois (`git ls-tree -r 1070e3759b7 -- prototipo-ui/prototipos | wc -l` → 0). Os 4 `financeiro-{fluxo,plano-contas,dre,conciliacao}` somam 9 `D` nesse commit.
- **Porquê é erro do lote:** o lote cita `1070e3759b7` **13 vezes** neste mesmo lote para subpaths de `prototipo-ui/prototipos/` (inclusive `financeiro-fluxo/page.tsx` em `Financeiro/fluxo-visual-comparison.md:10`). A proveniência estava medida; o `*` do texto não impede resolver o **link**, que não tem glob.

### R3 — `memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:93`

- **Item:** `prototipo-ui/prototipos/financeiro-*/` (texto) → `_(proveniência não determinada)_`.
- **O que origin/main diz:** os 4 diretórios que o glob expande foram apagados por `1070e3759b7` em 2026-05-20 (mesma medição de R2); nenhum sobrevive em main (`git ls-tree -r --name-only origin/main | grep -c '^prototipo-ui/prototipos/'` → 0).
- **Porquê é erro do lote:** idem R2 — "não determinada" onde a determinação já está no próprio lote. `ehPlaceholder()` do gate não classifica `*` como notação, logo nem a desculpa de placeholder cabe.

### R4 — `memory/requisitos/Sells/RUNBOOK-create-v3.md:86`

- **Item:** `**Âncora de design:** prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` → `_(proveniência não determinada)_`.
- **O que origin/main diz:** o charter dono da tela, `resources/js/Pages/Sells/CreateV3.charter.md` (em main), L11 `related_prototype: prototipo-ui/cowork/Felipe/venda-v3/sells-create.jsx` e L146-154, literal: *"A redação anterior declarava a âncora em `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` … Corrigido versionando as 12 fontes + 8 CSS em `prototipo-ui/cowork/Felipe/venda-v3/`"*. `node scripts/design/ancora.mjs Sells/CreateV3 --staging prototipo-ui/cowork/Wagner` → **`âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/venda-v3/sells-create.jsx`**. O arquivo existe em main (`git ls-tree` → blob; adicionado em `4b39f8df3b7`, #5560, 2026-08-10).
- **Porquê é erro do lote:** proveniência determinada por **canon mais novo em main** (charter + `ancora.mjs`, o leitor real do item 2 do mandato). A ação correta era repontar (como o lote fez em 31 linhas de `cowork-inbox`); "não determinada" contradiz o dono e congela o ponteiro morto num RUNBOOK cuja tela tem âncora viva.

### R5 — `memory/requisitos/Sells/SPEC.md:1281`

- **Item:** `- Âncora de design: prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` → `_(proveniência não determinada)_`.
- **O que origin/main diz / porquê:** idêntico a R4 — mesmo alvo, mesmo charter dono, mesma resolução `âncora ✓`. Aqui pesa mais: é linha de âncora dentro do **SPEC** (US do Create V3), e o SPEC é justamente o elo que a precedência manda reconciliar contra o charter.

### R6 — `memory/requisitos/Produto/BRIEFING.md:134`

- **Item:** lápide de linha `_(removido em 2026-08-31, 539efa2a8aa)_` numa linha com **5 ponteiros**: `PROTOTIPO-preco-especial.md` · `prototipo-ui/cowork/Felipe/produto-preco-especial/` · `prototipo-ui/cowork/Wagner/produtos-page.jsx` *(com a lápide pré-existente "removido do git em 2026-09-11 · #7224 · ADR 0397")* · `prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto/` · `produtos-gap.md`.
- **O que origin/main diz:** `git ls-tree origin/main` → **4 dos 5 resolvem** (só `prototipo-ui-patch/prototipos/produto/` morreu, e esse sim em `539efa2a8aa`, 4 D). Em particular `prototipo-ui/cowork/Wagner/produtos-page.jsx` **está vivo em main** (blob `937352ad5c1b`) e `git diff-tree -M 4f51a9ec781^ 4f51a9ec781` mostra **`R100 prototipo-ui/cowork/produtos-page.jsx → prototipo-ui/cowork/Wagner/produtos-page.jsx`** — o #7224 **criou** esse path por rename, não o removeu. `ancora.mjs Produto/Index` resolve `âncora ✓: [-page.jsx (bundle)] produtos-page.jsx`.
- **Porquê é erro do lote:** a lápide inserida é verdadeira para 1 dos 5 alvos, mas é **de linha** — e `declaraMorte(linha)` do gate é por linha: a partir deste PR, a linha inteira vira "registro datado" e a afirmação falsa sobre a âncora **viva** (`produtos-page.jsx` "removido em #7224") fica blindada de qualquer cobrança futura. O mandato é explícito: prosa stale contra canon mais novo → o canon vence, e carimbar a linha sem corrigi-la é erro do lote. A ação correta era remover a lápide falsa do `produtos-page.jsx` (o lote tinha o repertório: fez isso em 31 linhas) e anotar o alvo morto por ponteiro, não por linha.

## Observações (não contadas como erro)

1. **Atribuição dupla `#7224` × `d86c977a8dd`** em UI-0020:13, UI-0027:92 (e sells-index-dsv6 ×4): `gabarito-vendas.html` foi **adicionado** em `cowork/Wagner/legado/ds-v6/` pelo #7224 (`1 A`) e apagado pelo **#7225** (`d86c977a8dd`). A lápide pré-existente "#7224" está errada; o lote apendou a certa ao lado sem reconciliar — a linha agora afirma dois PRs pro mesmo evento. Verdadeiro no que o lote inseriu; ruim de ler.
2. **`MANUAL-IDENTIDADE.md:131`**: a emenda pré-existente diz *"Sobrevivem no espelho … `tokens.css` … e `gabarito-vendas.html`"* e o lote apendou `_(removido em 2026-09-11, d86c977a8dd)_` — correto (ambos `D` no #7225), mas a linha ficou auto-contraditória ("sobrevivem" + "removido") sem uma vírgula de reconciliação. Mesmo padrão em `Governance/SPEC.md:904` (*"ainda está no git"* + removido).
3. **`Jana/RUNBOOK-metas.md:386`**: o `JANA-ERRATA-CAMADA-ESQUECIDA` que a linha diz ter descido pra `design-docs/cowork-inbox/` **existe em main** como `prototipo-ui/cowork/Wagner/cowork-inbox/JANA-ERRATA-CAMADA-ESQUECIDA-2026-08-27.md`; a lápide do diretório é verdadeira, mas o lote repontou 31 vizinhos e este não.
4. **`Superadmin/modules-index-gap.md:30`**: `prototipo-ui/cowork/Wagner/cowork-inbox/modulos/` **existe em main** (`ls-tree` → 1); lápide verdadeira para o dir antigo, repontar era possível.
5. **`OficinaAuto/…:105`** `prototipo-ui/alvos/roles/OficinaAuto--ServiceOrders--Show.json`: o arquivo nunca existiu (A=0), mas `prototipo-ui/alvos/roles/` existiu (Compras/Fiscal…) e morreu no #7224 — "não determinada" é literalmente verdadeira para o arquivo, imprecisa para o diretório. `Jana/AUDITORIA-reconciliacao:173` (basenames `produto-app.jsx` / `Produto Unificado.html`, 31 commits de A por basename) e `:210` (`prototipo-ui/audit/reports/…json`, A=0 para o arquivo; dir morreu no #7224) — mesma natureza.
6. **UI-0032:12/140/378** `design-docs/handoff-crm/PEDIDO-CODE.md`: `D` no #7224 mesmo com `-M`; não há `handoff-crm` em main (0 arquivos) — lápide correta, sem destino a repontar.
7. **Frontmatter com sufixo** (22 valores): YAML segue válido; único leitor de máquina (`ancora.mjs`, `canon_reference`) só reporta. Aceitável, mas o valor deixa de ser um path puro — se algum dia `canon_reference` virar âncora (lápide §5 2026-09-09 diz que não deve), o sufixo quebra.
8. **Máquinas vermelhas não atribuíveis ao lote**: `charter-blueprint-pointers --strict` rc=1 vem da **1ª raiz (charters)**, 10 órfãos em `resources/js/Pages/**` — o lote não toca `resources/` (diff = 0 arquivos lá); a 2ª raiz (requisitos) está em **0 órfãos mudos**. `design-code-map-check` rc=1 é `Ponto/espelho-show.map.json` STALE (Ponto fora do lote). `requisitos-status --check`: 7 módulos sem `_STATUS-GENERATED.md` (pré-existente) e 3 DRIFADOS (Jana/Repair/Sells) — regenerei em memória e o diff **não cita nenhum arquivo do lote** (Sells muda só "US no SPEC 53→57", que um sufixo de 1 linha não altera); restaurei com `git checkout`.
9. `7b-lote-crm-jana-forja.md` tem frontmatter YAML inválido (2:42) — **idêntico em origin/main**; o lote só tocou a L30 do corpo.

## Máquina derivada — rc literal

```
node scripts/governance/charter-blueprint-pointers.mjs --strict        → rc=1  (1ª raiz charters=10 órfãos, intocada; 2ª raiz requisitos: "0 em 0 doc(s)")
node scripts/governance/charter-blueprint-pointers.mjs --json          → rc=0  requisitos_total_orfaos=0 · total_orfaos(charters)=10
node scripts/governance/reconcile-triplet.test.mjs                     → rc=0  "todas as asserções passaram"
node scripts/governance/plans-index.mjs --check                        → rc=0  "em dia (8 registrados, 25 pendentes)"
node scripts/governance/design-code-map-check.mjs --check --strict     → rc=1  STALE Ponto/espelho-show.map.json (fora do lote)
node scripts/governance/doc-id-index.mjs --check-collisions            → rc=0  "0 colisão de id em 2754 ids"
node scripts/governance/visual-comparison-staleness.mjs --json         → rc=0
node scripts/memory-schemas/validate.mjs <69 arquivos>                 → rc=0  "15 arquivo(s) conformes" (54 fora de família com schema)
node scripts/governance/requisitos-status.mjs <Mod> --check ×13        → rc=0: Financeiro OficinaAuto Produto RecurringBilling · rc=1 "não existe": AssetManagement Essentials Governance Orcamento Superadmin TeamMcp · rc=1 DRIFADO: Jana Repair Sells (drift não cita arquivo do lote)
git status --short | wc -l  (após tudo)                                → 0
```

## Scan PII (119 linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`)

| padrão | hits | controle positivo sintético |
|---|---|---|
| CPF pontuado | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ | 0 | casou |
| telefone BR formatado | 0 | casou |
| telefone cru (10–11 dígitos) | 0 | casou |
| e-mail | 0 | casou |
| símbolo de reais seguido de dígito | 0 | casou (na 1ª rodada o controle falhou por falta de `re.escape` no símbolo — bug da sonda, corrigido e re-rodado; o padrão e o valor de controle não são reproduzidos aqui de propósito) |
| nomes de cliente CRM (`LTDA|Larissa|Martinho|Eliana`) | 0 | — |

**pii_hits = 0 · controles = 7/7.**

## Comandos reproduzíveis

```bash
# escopo
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l          # 69
git diff -U0 origin/main...HEAD -- memory/requisitos | grep -E '^\+[^+]' | wc -l # 119

# B: o sha apagou o alvo? (0 = alvo ausente em main; D = deletado no commit)
git diff-tree -r --name-status --no-commit-id 878e6069d1a^ 878e6069d1a -- "prototipo-ui/Design System v4.html"   # D  ← R1
git ls-tree -r 1070e3759b7 -- prototipo-ui/prototipos | wc -l                                                    # 0  ← R2/R3
git diff-tree -M -r --name-status --no-commit-id 4f51a9ec781^ 4f51a9ec781 | grep 'Wagner/produtos-page.jsx'     # R100 ← R6
git ls-tree origin/main -- prototipo-ui/cowork/Wagner/produtos-page.jsx                                          # blob (vivo)

# C: nunca existiu? (repo não-raso)
git log --all --format=%h --diff-filter=A -- prototipo-ui/design-oimpresso | wc -l   # 0 (o path do doc)
git ls-tree origin/main -- prototipo-ui/cowork/Felipe/venda-v3/sells-create.jsx      # blob (a âncora real)
node scripts/design/ancora.mjs Sells/CreateV3 --staging prototipo-ui/cowork/Wagner   # âncora ✓ related_prototype ← R4/R5

# controle negativo / positivo
git diff-tree -r --name-status 1070e3759b7^ 1070e3759b7 -- prototipo-ui/nao-existe-xyz/arquivo.jsx  # vazio
git log --all --format=%h --diff-filter=A -- prototipo-ui/tokens.css | wc -l                        # 6
```

```json
{"itens_verificados": 240, "erros_confirmados": 6, "error_rate_pct": 2.5, "pii_hits": 0, "veredito": "reprovado"}
```
