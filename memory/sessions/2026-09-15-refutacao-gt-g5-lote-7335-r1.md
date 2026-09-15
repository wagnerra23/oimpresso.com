---
date: "2026-09-15"
hour: "22:00 BRT"
topic: "Refutação GT-G5 r1 do lote PR #7335 (56 docs de memory/requisitos, ponteiros de protótipo): REPROVADO — 84 erros em 205 itens (40,98%); 72 anotações datam em #7224 remoções feitas em #1218/#3259 (mai/jun)"
authors: ["C"]
prs: [7335]
outcomes:
  - "Lote REPROVADO: error_rate 40,98% (84/205) — erro sistemático de prompt: toda remoção de prototipo-ui/prototipos/** foi atribuída ao PR #7224, que só removeu 33 arquivos em 11 dirs (nenhum dos paths citados, exceto inventario-migracao)"
  - "8 ponteiros do Repair apontam pro sucessor errado (repair-page.jsx, porte reverso dos blades) — o charter dono declara os-page.jsx; 3 células/fences corrompidos; 1 claim numérica (802 LOC) falsa pro arquivo novo"
  - "PII: 0 hits em 7 padrões, 7/7 controles positivos OK; máquinas derivadas: deadlink baseline regenerado idêntico, schema OK; os rc=1 de requisitos-status/design-code-map-check são HERDADOS (mesmo rc em origin/main)"
---

# Refutação GT-G5 — lote PR #7335 · rodada r1

## TL;DR

**Veredito: REPROVADO.** 205 itens verificados · 84 erros confirmados · error_rate **40,98 %** (limiar < 2 %) · PII hits 0 (7/7 controles positivos casaram). O erro é **sistemático**: das 73 anotações `(removido em 2026-09-11, PR #7224)`, 72 apontam paths que `origin/main` mostra removidos em **2026-05-20 (#1218, 1070e3759b7)** ou **2026-06-23 (#3259, 9da73296d34)** — o #7224 (4f51a9ec781) só removeu `inventario-migracao` entre os paths citados; somam-se 8 sucessores errados no Repair (charter declara `os-page.jsx`, lote escreveu `repair-page.jsx`), 1 claim numérica falsa e 3 células/fence corrompidos.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base | `origin/main` = `4ba95435fa6347297440f72760302d90dbe3893b` |
| HEAD | `b01628282c61b7d9758f267fa502129f2e8441c1` (branch `claude/ponteiros-podres-61`) |
| Repo raso | `git rev-parse --is-shallow-repository` → **false** (datas de `git log` valem como recibo) |
| Sessão | fresca — instância nova, sem contexto do gerador; nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje foi aberto (`abriu_evidencia_anterior: false`) |
| Tier | refutador Fable 5.1 (tier máximo) |
| Amostra | 100 % dos itens (tipo anchors — sem seed, sem sorteio) |
| Árvore | `git status --short` vazio ao final; a única escrita neste repo é este arquivo |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable — igualdade só no tier máximo)
- [x] Amostra: 100 % anchors (tipo anchors; sem prosa destilada amostrada)
- [x] Cada item verificado contra `origin/main` (`git ls-tree`/`git show`/`git log`/`git diff-tree`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff — 7 padrões × controle positivo (tabela abaixo) — 0 hits
- [x] `error_rate_pct` calculado: **40,98** (≥ 2 → reprovado)
- [ ] Entry no ledger — **não cabe ao refutador** (lote reprovado volta ao gerador; o ledger recebe o REPROVADO pelo caminho §2)

## Escopo medido

- `git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l` → **56** (todos `M`), `--numstat` → +94 −94 (1:1, linha a linha).
- Fora de `memory/requisitos` o PR toca `governance/deadlink-baseline.json` (793→779) e `governance/sdd-scorecard-baseline.json` (`distiller_freshness` 1→10).
- Commits: `e49d96ffbc4` (22 arquivos — a mensagem diz "21 docs"), `a07ebbdac04` (19), `6641b029535` (26), `b01628282c6` (1).
- Duas famílias de mudança: **(A)** ponteiro `prototipo-ui/prototipos/<x>` → `prototipo-ui/cowork/Wagner/<x>-page.jsx` (25 linhas); **(B)** anotação `(removido em 2026-09-11, PR #7224)` colada ao path antigo (71 linhas, 73 ocorrências).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1. Âncora existe/ausente em origin/main | 32 | 32 | 0 | 5 sucessores `cowork/Wagner/*-page.jsx` → `git ls-tree origin/main` devolve blob (controle negativo `nao-existe-xyz.jsx` → vazio); 27 paths antigos → `git ls-tree -r origin/main -- <path> \| wc -l` = 0 para todos (ausência confirmada — a DATA/PR da ausência é o grupo 3) |
| 2. Não revogada e lida pelo leitor real | 29 | 29 | 0 | 6 chaves de frontmatter cujo parse mudou (4× `mwart_pattern_reuse.blueprint_cowork` Sells, `canon_reference` index-r1, `visual_source` CaixaUnificada) conferidas contra o charter dono; 14 frontmatters com `# removido…` re-parseados (pyyaml) → **idênticos** a origin/main; `ancora.mjs` em 7 telas (Cliente/Index ✓ clientes-page · Sells/Index ✓ vendas-page · Produto/Index n/a+bundle · Repair×2 blueprint=os-page · Sells/Show, Sells/Drafts blueprint=vendas-page); charters PG Settings e WA CaixaUnificada lidos |
| 3. Ação × veredito da prosa / afirmação sobre código | 98 | 17 | **81** | 73 ocorrências "removido em 2026-09-11, PR #7224" × `git log origin/main -n1 --diff-filter=D -- <path>` + `git diff-tree -r --diff-filter=D 4f51a9ec781` (72 ✗); 25 linhas de sucessor × charter/ancora + `wc -l`/cabeçalho do arquivo (9 ✗: 8 Repair + 1 "802 LOC") |
| 4. Célula íntegra | 16 | 13 | **3** | 13 conversões link→code-span (contadas no diff) íntegras; 2 linhas de tabela ganharam célula extra (RB gap :26 · Sells index-r1 :363); 1 anotação markdown dentro de code fence (INVENTARIO :130) |
| 5. Máquina derivada | 23 | 23 | 0 | rc literais abaixo; os rc=1 reproduzem em origin/main (swap temporário `git checkout origin/main -- memory/requisitos governance` → mesmos rc → restaurado, árvore == HEAD) |
| 6. Scan PII | 7 | 7 | 0 | 7 padrões sobre as 94 linhas `+`, cada um com controle positivo sintético (7/7 casaram) |
| **Total** | **205** | **121** | **84** | error_rate = 84/205 = **40,98 %** |

## REFUTADOS

### R-A · 72 anotações "removido em 2026-09-11, PR #7224" com data e PR errados (70 linhas · 72 ocorrências · 39 arquivos)

**Afirmação do lote:** o path citado foi removido em 2026-09-11 pelo PR #7224.

**O que origin/main diz:** `git log origin/main --format='%H %s' | grep -F '(#7224)'` → `4f51a9ec781 2026-09-11 refactor(prototipo): separar fontes por dono e remover paralelos`. `git diff-tree -r --diff-filter=D --name-only 4f51a9ec781 | grep -c '^prototipo-ui/prototipos/'` → **33** arquivos, em 11 diretórios: `compras-grade-matrix financeiro-assinatura-atualizar financeiro-contador financeiro-prova-viva inventario-migracao nfe-tributacao nfse-emitir payment-gateway-cnab perfil recurring-billing-planos transaction-payment`. **Nenhum** de `clientes/ payment-gateway-ui/ boletos/ financeiro-fluxo/ chat/ kb/ producao-oficina/ produto-cockpit/ produto/ recurring/ vendas-cockpit/ sells-index/ sidebar-v3-unificado/ pageheader-canon-v3/` está lá. A remoção real de cada um (último commit `D` em origin/main):

| Removido de fato em | Commit | Dirs |
|---|---|---|
| 2026-05-20 | `1070e3759b7` (#1218) | boletos · chat · financeiro-fluxo · kb · payment-gateway-ui · produto · produto-cockpit · recurring · sells-index · vendas-cockpit · os · producao-oficina |
| 2026-05-19 | `7810bf5cb40` | `sells-index/Oimpresso ERP - Chat.html` |
| 2026-06-23 | `9da73296d34` (#3259) | clientes · caixa-unificada · sidebar-v3-unificado · pageheader-canon-v3 |
| 2026-09-11 | `4f51a9ec781` (#7224) | inventario-migracao (**único** correto) |

**Porquê é erro do lote:** a anotação é um *fato datado* (§5: "ponteiro podre atualiza, fato datado preserva") e nasceu falso em 72 de 73 ocorrências. Agrava: em 3 arquivos a anotação **contradiz a prosa vizinha do próprio documento** — `Crm/clientes-gap.md:31-32` ("Foi apagado em **2026-06-23** por `e8b49f4b63`"), `_DesignSystem/sidebar-v3-unificado-gap.md:6` e `:63` (o próprio texto diz "apagado em 2026-06-23 (commit 9da73296d3)"; na :63 a anotação foi inserida **no meio da frase** "apagado em ⟨anotação⟩ 2026-06-23"), `RecurringBilling/cobranca-recorrente-configuracoes-gap.md:26,41` (doc `gerado_em: 2026-09-06` já media `ls → No such file` — não podia ter sido removido 5 dias depois). Em `Sells/show-visual-comparison.md:19` o lote anota só `Vendas Cockpit.html` e deixa `visual-source-fsm-v1.html` (mesmo dir, mesma remoção 1070e3759b7) sem nota.

Linha a linha (coluna "Remoção real" = `git log origin/main -n1 --diff-filter=D --format='%h %cd' -- <path>`; `(sem D)` = diretório-pai cuja remoção se mede pelos filhos, todos em #1218/#3259):

| Arquivo:linha | Path anotado | ocorr. | Remoção real | #7224? |
|---|---|---:|---|---|
| `memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md:204` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Cliente/SPEC.md:357` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/HANDOFF-cliente-drawer-760-canon.md:11` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/HANDOFF-cliente-drawer-760-canon.md:228` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md:23` | `prototipo-ui/prototipos/clientes/` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md:41` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md:183` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md:383` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/_legado-fullpage/RUNBOOK-index-fullpage.md:116` | `prototipo-ui/prototipos/clientes/` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/_legado-fullpage/create-visual-comparison.md:142` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/_legado-fullpage/edit-visual-comparison.md:122` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/_legado-fullpage/index-visual-comparison.md:141` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/_legado-fullpage/show-visual-comparison.md:156` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:9` | `prototipo-ui/prototipos/clientes/` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:52` | `prototipo-ui/prototipos/clientes/` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:95` | `prototipo-ui/prototipos/clientes/clientes-icons.jsx` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:242` | `prototipo-ui/prototipos/clientes/Oimpresso ERP - Clientes.html` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:273` | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Crm/clientes-gap.md:31` | `prototipo-ui/prototipos/clientes/` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/Financeiro/RUNBOOK-cobranca.md:135` | `prototipo-ui/prototipos/payment-gateway-ui/critiques/REPORT.md` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Financeiro/SPEC.md:846` | `prototipo-ui/prototipos/boletos/cowork-app.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Financeiro/boletos-visual-comparison.md:9` | `prototipo-ui/prototipos/boletos/cowork-app.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Financeiro/boletos-visual-comparison.md:22` | `prototipo-ui/prototipos/boletos/cowork-app.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Financeiro/fluxo-visual-comparison.md:9` | `prototipo-ui/prototipos/financeiro-fluxo/page.tsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Financeiro/fluxo-visual-comparison.md:24` | `prototipo-ui/prototipos/financeiro-fluxo/page.tsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Jana/SPEC.md:937` | `prototipo-ui/prototipos/chat/cowork-app-v2.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/KB/CAPTERRA-FICHA.md:8` | `prototipo-ui/prototipos/kb/Bench KB.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/KB/CAPTERRA-FICHA.md:9` | `prototipo-ui/prototipos/kb/Bench KB v2.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/KB/CAPTERRA-FICHA.md:36` | `prototipo-ui/prototipos/kb/Bench KB v2.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/KB/CHANGELOG.md:141` | `prototipo-ui/prototipos/kb/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md:9` | `prototipo-ui/prototipos/producao-oficina/F1.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md:10` | `prototipo-ui/prototipos/producao-oficina/visual-source.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md:33` | `prototipo-ui/prototipos/producao-oficina/F1.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md:34` | `prototipo-ui/prototipos/producao-oficina/visual-source.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md:19` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md:103` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-edit.md:17` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md:19` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md:134` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-show.md:18` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md:9` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md:15` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-create-visual-comparison.md:9` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-create-visual-comparison.md:14` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-edit-visual-comparison.md:9` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-edit-visual-comparison.md:14` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-index-setor-matrix.md:24` | `prototipo-ui/prototipos/produto-cockpit` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-index-setor-matrix.md:25` | `prototipo-ui/prototipos/produto` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-index-visual-comparison.md:9` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-index-visual-comparison.md:15` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-index-visual-comparison.md:24` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-selling-prices-visual-comparison.md:9` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-selling-prices-visual-comparison.md:15` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-show-visual-comparison.md:9` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-show-visual-comparison.md:14` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-stock-history-visual-comparison.md:9` | `prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Produto/_telas/produto-stock-history-visual-comparison.md:15` | `prototipo-ui/prototipos/produto-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/RecurringBilling/Index-visual-comparison.md:9` | `prototipo-ui/prototipos/recurring/recurring-page.jsx`, `prototipo-ui/prototipos/recurring/recurring-data.jsx`, `prototipo-ui/prototipos/recurring/recurring-icons.jsx` | 3 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/RecurringBilling/Index-visual-comparison.md:133` | `prototipo-ui/prototipos/recurring/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes-gap.md:26` | `prototipo-ui/prototipos/recurring/recurring-page.jsx` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes-gap.md:41` | `prototipo-ui/prototipos/recurring/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Repair/jobsheet-visual-comparison.md:14` | `prototipo-ui/prototipos/producao-oficina/F1.html` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Sells/index-r1-visual-comparison.md:13` | `prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html` | 1 | 7810bf5cb40 2026-05-19 | ✗ |
| `memory/requisitos/Sells/index-r1-visual-comparison.md:57` | `prototipo-ui/prototipos/sells-index/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Sells/index-r1-visual-comparison.md:363` | `prototipo-ui/prototipos/sells-index/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/Sells/index-r1-visual-comparison.md:373` | `prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html` | 1 | 7810bf5cb40 2026-05-19 | ✗ |
| `memory/requisitos/Sells/show-visual-comparison.md:19` | `prototipo-ui/prototipos/vendas-cockpit/` | 1 | 1070e3759b7 2026-05-20 | ✗ |
| `memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md:130` | `prototipo-ui/prototipos/inventario-migracao/visual-source.html` | 1 | 4f51a9ec781 2026-09-11 | ✓ |
| `memory/requisitos/_DesignSystem/sidebar-v3-unificado-gap.md:6` | `prototipo-ui/prototipos/sidebar-v3-unificado/visual-source.html` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/_DesignSystem/sidebar-v3-unificado-gap.md:63` | `prototipo-ui/prototipos/sidebar-v3-unificado/visual-source.html` | 1 | 9da73296d34 2026-06-23 | ✗ |
| `memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md:10` | `prototipo-ui/prototipos/pageheader-canon-v3/` | 1 | 9da73296d34 2026-06-23 | ✗ |

### R-B · 8 ponteiros do Repair apontam pro sucessor ERRADO (`repair-page.jsx` em vez de `os-page.jsx`)

- **Arquivos/linhas:** `Repair/RUNBOOK-jobsheet-create.md:16` · `RUNBOOK-jobsheet-edit.md:16` · `RUNBOOK-jobsheet-index.md:18` · `RUNBOOK-jobsheet-show.md:18` · `RUNBOOK-repair-index.md:18` · `RUNBOOK-repair-show.md:16` · `jobsheet-visual-comparison.md:13` · `repair-visual-comparison.md:12`.
- **Afirmação do lote:** "blueprint `prototipo-ui/cowork/Wagner/repair-page.jsx` (NewOsModal / OsDetailPanel / listagem OS com tabs stage…)".
- **O que origin/main diz:** os charters donos declaram outro arquivo — `resources/js/Pages/Repair/JobSheet/Index.charter.md:17`, `JobSheet/Show.charter.md:13`, `JobSheet/Create.charter.md:13`, `JobSheet/Edit.charter.md:13`, `Repair/Show.charter.md:14`: `mwart_pattern_reuse.blueprint_cowork: "prototipo-ui/cowork/Wagner/os-page.jsx"` (o `repair-page.jsx` aparece só como `bundle_source`). `node scripts/design/ancora.mjs Repair/JobSheet/Index --staging prototipo-ui/cowork/Wagner` → `âncora ✓ [bundle_source] repair-page.jsx` **e** `blueprint_cowork: …/os-page.jsx — é o blueprint do Index de OUTRA tela, reusado aqui`. O próprio arquivo: `repair-page.jsx:1-2` = *"módulo Repair (assistência técnica) **importado dos blades** Modules/Repair/Resources/views/…"* (porte reverso do código vivo — §5 2026-08-28); `os-page.jsx:1` = *"Listagem + Detalhe de Ordens de Serviço"*, que é exatamente o que o antigo `os/cowork-app.jsx` era ("listagem + detalhe OS Cowork", texto que o lote manteve).
- **Porquê é erro do lote:** confundiu `bundle_source` (o que o bundle empacota) com `blueprint_cowork` (a fonte de design) e pendurou 8 docs num porte reverso — a lápide §5 2026-08-28 proíbe justamente promover `bundle_source` a fonte de design em leva.

### R-C · `Whatsapp/CaixaUnificadaV4-visual-comparison.md:243` — "(802 LOC)" agora aponta pra um arquivo de 1487 linhas

- **Afirmação:** `[prototipo-ui/cowork/Wagner/inbox-page.jsx] — fonte visual canônica (802 LOC)`.
- **Medido:** `git show origin/main:prototipo-ui/cowork/Wagner/inbox-page.jsx | wc -l` → **1487**. (O antigo `caixa-unificada/inbox-page.jsx`, no parent de `9da73296d34`, tinha 1141 — o "802" já era stale; o lote trocou o sujeito da frase e manteve o número, tornando a afirmação falsa sobre o arquivo novo.)

### R-D · 3 células/fence corrompidos

1. `RecurringBilling/cobranca-recorrente-configuracoes-gap.md:26` — tabela de 3 colunas (header :22-25); a linha ganhou **4ª célula** `| _(removido…)_`.
2. `Sells/index-r1-visual-comparison.md:363` — `## Sync log` tem 4 colunas (`Data|Quem|Fase|Notas`); a linha datada 2026-05-17 ganhou **5ª célula** — e é registro histórico.
3. `_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md:130` — `_(removido…)_` inserido **dentro do bloco ```** que cita literalmente o valor declarado pelos 4 charters de Estoque; renderiza literal e o "valor declarado" deixa de bater com o charter. Única anotação do lote com data certa, mas a prosa vizinha (:133 "Esse arquivo existe, tem 30 KB") ficou intocada e agora contradiz.

## Observações não contadas

- **Baseline do scorecard absorvido (`distiller_freshness` 1→10, commit `b01628282c6`)** — mecanismo tem precedente (3 commits `[BASELINE-ABSORB]` em origin/main: #5853, #5855, #6170) e o script declara *"piorar = PR editando o baseline, diff visível"*. Mas a citação "(ADR 0275 §3)" não bate: o §3 é "Regra de armamento" e a ADR não contém "absor" (0 matches). Medido por módulo (`distilled_at` do BRIEFING × data-git do doc mais novo): Cliente/Financeiro/PaymentGateway/RecurringBilling/Sells/OficinaAuto já estavam >7d **em origin/main** (drift de calendário herdado); **Crm e Whatsapp** viram stale **por causa deste PR** (main 09-11 → HEAD 09-15, distilled 09-06). Ou seja: ~2 das 9 portas novas são auto-infligidas e absorvidas sem re-destilar. Não contado como erro do lote de anchors; fica pra decisão [W].
- Commit `e49d96ffbc4` diz "21 docs", toca 22 arquivos.
- `Sells/show-visual-comparison.md:19`: dos dois paths do mesmo dir, só um recebeu anotação (o outro, `visual-source-fsm-v1.html`, também morreu em `1070e3759b7`).
- Os RUNBOOKs de Produto dizem "o charter de Produto/Index declara `related_prototype: n/a (herda PT-01 Lista)`" — **verdadeiro** (charter :5); os charters de Create/Edit/Show declaram PT-02/PT-03, mas a frase nomeia o Index explicitamente, então é correta.
- `requisitos-status` Jana/Repair/Sells (DRIFADO) e PaymentGateway/Whatsapp (`_STATUS-GENERATED.md` não existe) e `design-code-map-check --strict` (STALE `Ponto/espelho-show.map.json`) reprovam **igual em origin/main** — herdado, não do lote.
- Chaves de frontmatter tocadas (`canon_reference`, `prototype_source`, `visual_source_html`, `prototipo_nota`, `canon_reference_v*`) têm 0–1 consumidor em `scripts/`; `visual_source`/`blueprint_cowork` de RUNBOOK/visual-comparison são lidos por `ancora.mjs` e `detectar-telas.mjs`. Os comentários YAML `# removido…` não alteram o parse (14/14 idênticos).

## Scan PII (94 linhas `+`)

| Padrão | Hits | Controle positivo |
|---|---:|---|
| CPF pontuado | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ | 0 | casou |
| Telefone BR formatado | 0 | casou |
| Telefone cru (10–11 dígitos) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo + dígito) | 0 | casou |

Nomes: as linhas `+` só carregam "Larissa biz=4 ROTA LIVRE" e "Wagner", já presentes na mesma linha em origin/main (persona piloto documentada em `memory/why-oimpresso.md`) — não contam.

## Máquina derivada — rc literais (HEAD)

| Comando | rc HEAD | rc origin/main (swap) |
|---|---|---|
| `requisitos-status.mjs {Cliente,Crm,Financeiro,KB,OficinaAuto,Produto,RecurringBilling} --check` | 0 ×7 | — |
| `requisitos-status.mjs {Jana,Repair,Sells} --check` | 1 (DRIFADO) | 1 |
| `requisitos-status.mjs {PaymentGateway,Whatsapp} --check` | 1 (`_STATUS-GENERATED.md` não existe) | 1 |
| `plans-index.mjs --check` | 0 | — |
| `design-code-map-check.mjs --check --strict` | 1 (STALE Ponto) | 1 |
| `doc-id-index.mjs --check-collisions` | 0 | — |
| `deadlink-gate.mjs --check` | 0 | 0 |
| `deadlink-gate.mjs --write-baseline` → `git diff governance/deadlink-baseline.json` | vazio (baseline commitado == regenerado) | — |
| `scripts/memory-schemas/validate.mjs <56 arquivos>` | 0 (23 conformes, 33 fora de família) | — |
| `.github/scripts/validate-memory-schema.sh spec` ×4 SPECs | 0 ×4 | — |
| `sdd-scorecard.mjs --json` → `distiller_freshness.value` | 10 (measured) | baseline dizia 1 |
| `sdd-scorecard.mjs --ratchet` | 1 — mas a única linha 🔴 é `full_suite_pass_rate` (fonte órfã `governance/nightly-floor` não materializada neste checkout local — ambiente, não lote); `distiller_freshness` NÃO aparece como regressão com o baseline absorvido em 10 | — |

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l          # 56
M=$(git log origin/main --format='%H %s' | grep -F '(#7224)' | cut -d' ' -f1)   # 4f51a9ec781
git diff-tree -r --diff-filter=D --name-only $M | grep '^prototipo-ui/prototipos/' | cut -d/ -f3 | sort -u
for d in clientes boletos kb produto-cockpit recurring os vendas-cockpit sells-index caixa-unificada sidebar-v3-unificado pageheader-canon-v3 inventario-migracao; do
  git log origin/main -n1 --diff-filter=D --format="$d %h %cd" --date=short -- "prototipo-ui/prototipos/$d/"; done
for f in clientes-page pg-payment-gateways-page repair-page vendas-page inbox-page; do git ls-tree origin/main -- prototipo-ui/cowork/Wagner/$f.jsx; done
git show origin/main:resources/js/Pages/Repair/JobSheet/Index.charter.md | grep -n blueprint_cowork
node scripts/design/ancora.mjs Repair/JobSheet/Index --staging prototipo-ui/cowork/Wagner
git show origin/main:prototipo-ui/cowork/Wagner/inbox-page.jsx | wc -l          # 1487
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' > plus.txt   # scan PII sobre este arquivo
```

```json
{"itens_verificados": 205, "erros_confirmados": 84, "error_rate_pct": 40.98, "pii_hits": 0, "veredito": "reprovado"}
```
