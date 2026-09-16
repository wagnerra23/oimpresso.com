---
date: "2026-09-15"
topic: "Refutação GT-G5 r1 do lote #7377 (73 docs em memory/requisitos, tipo anchors): 127 itens, 58 refutados (45,7%) — REPROVADO; tombstone em path vivo, 'nunca versionado' em kit que esteve no git, placeholder carimbado"
authors: ["C"]
prs: [7377]
outcomes:
  - "Lote REPROVADO: 58 de 127 itens refutados (error_rate 45,67% ≥ 2%) — volta pro gerador, re-verificação do lote inteiro"
  - "3 classes sistemáticas de erro no extrator/gerador: (a) reescrever path e manter 'removido do git' num arquivo que existe em origin/main; (b) 'nunca versionado' medido com --all sem conferir a história de main (kits ui_kits/cowork-2026-04-27 e 05-09 existiram de 05-05/05-09 até 05-20); (c) placeholder `<tela>`/`<modulo>`/`_ds/` carimbado com commit que só mexeu em .gitignore"
  - "PII: 0 hits em 7 padrões, 7/7 controles positivos casaram; árvore limpa; nada commitado, ledger intocado"
---

# Session log 2026-09-15 — Refutação GT-G5 · lote #7377 · rodada 1

## TL;DR

**REPROVADO.** 127 itens verificados (100% das âncoras do lote), **58 refutados**, `error_rate_pct = 45.67` (aceite < 2). PII: 0 hits (7/7 controles positivos OK). Os erros são sistemáticos, não pontuais: 25 paths reescritos pra `prototipo-ui/cowork/Wagner/cowork-inbox/**` mantendo a frase *"removido do git em 2026-09-11 · #7224"* quando o arquivo **existe em `origin/main`**; 14 carimbos *"nunca versionado no repo — medido no histórico completo: 0 commits"* em arquivos que **estiveram no git de main** (ui_kits 04-27 e 05-09, adicionados 05-05/05-09 e apagados em `1070e3759b7`/`4fad2f11f60` 2026-05-20); 18 tombstones cujo commit **não removeu** aquele path (placeholder `legado/<tela>/`, `_ds/` nunca versionado, `PROTOCOL.md` que existe, data/commit errados em sells-index/sells-create/financeiro-*); 1 célula de tabela quebrada.

## Cabeçalho

| campo | valor |
|---|---|
| PR / lote | #7377 · branch `claude/orfaos-mudos-requisitos` · tipo **anchors** |
| base | `origin/main` = `88732c60b945598513963ff75986ed0c64572c51` |
| HEAD | `4a73083750346617d14b46527800ade2e6bebada` (2 commits: `bdeb37d923c`, `4a730837503`) |
| repo raso | **false** (`git rev-parse --is-shallow-repository`) — datas de `git log` valem como recibo |
| sessão fresca | **true** — instância nova, sem contexto do gerador; nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje foi aberto; corpo do PR/commit **não** lido como evidência |
| refutador | Claude (Fable 5.1) — tier acima do gerador |
| amostra | 100% dos itens (anchors) — não há seleção aleatória, seed n/a |
| escopo medido | `git diff --name-status origin/main...HEAD -- memory/requisitos` = **73 M** (75 no PR: + `scripts/governance/charter-blueprint-pointers.mjs`, `scripts/governance/reconcile-triplet.test.mjs`); `--numstat` = **+127 / −127** (só substituição de linha) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador
- [x] Amostra: 100% anchors
- [x] Cada item verificado contra `origin/main` (`git ls-tree origin/main`, `git log origin/main --diff-filter=A|D`, `git show --name-status <sha>`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + commit + porquê)
- [x] Scan PII no diff — 7 padrões × controle positivo (tabela abaixo) — **0 hits**
- [x] `error_rate_pct` calculado: **45.67** (≥ 2 → reprovado)
- [ ] Entry no ledger — **não cabe ao refutador** nesta rodada (reprovado → volta pro gerador); ledger intocado

## Como o lote foi decomposto em itens

Cada linha `+` do diff carrega exatamente **uma** mudança (127 linhas → 127 itens):

| Grupo | O que é | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|---|
| G1 · Âncora existe em origin/main (paths reescritos `design-docs/cowork-inbox/**` → `cowork/Wagner/cowork-inbox/**` e `cowork/repair-page.jsx` → `cowork/Wagner/repair-page.jsx`) | 32 | 7 | **25** | `git ls-tree origin/main -- <novo>` (21/21 novos = blob presente); controle negativo `prototipo-ui/nao-existe-xyz.md` → 0 |
| G2 · Tombstone `(removido em DATA, SHA)` — path ausente em main **e** SHA ancestral de main **e** SHA remove aquele path **e** data bate | 78 | 60 | **18** | `git merge-base --is-ancestor` (10/10 SHAs ancestrais), `git show --name-status <sha>`, `git log origin/main --diff-filter=D -- <path>` |
| G3 · `_(nunca versionado no repo — artefato externo do Cowork[; 0 commits])_` | 16 | 2 | **14** | `git log origin/main --oneline -- <path>` (restrito à história de main, não `--all`); controle positivo `prototipo-ui/tokens.css` → 18 commits |
| G4 · Célula íntegra (pipe, code-span, fence) — só o item que não caiu em G1–G3 | 1 | 0 | **1** | leitura das linhas vizinhas (header da tabela / contagem de fences ` ``` ` antes da linha) |
| G5 · Máquina derivada | — | — | — | rc literal abaixo (não conta como item do lote) |
| G6 · PII | 7 padrões | 7 controles OK | 0 hits | script `pii.mjs` sobre as 127 linhas `+` |
| **Total** | **127** | **69** | **58** | **error_rate = 58/127 = 45,67%** |

Leitor real das chaves de frontmatter tocadas (`canon_reference` ×9, `visual_source` ×2, `visual_source_html`, `fonte_cowork`, `component`, `blueprint_cowork` ×7): `scripts/design/ancora.mjs` lê `canon_reference` via `chaveAninhada` e **só reporta** (bloco *"declarações de fonte que NÃO são âncora"*), e `visual_source` via `mockupJsx` (regex `-page.jsx`); rodado em `Produto/Index`, `Sells/Index`, `Jana/Chat` → rc=0, valor impresso verbatim com o sufixo. O sufixo não quebra consumidor — G2 aplica normalmente, sem item extra.

## REFUTADOS

### R1 — 25× path reescrito pra `prototipo-ui/cowork/Wagner/cowork-inbox/**` MANTENDO *"removido do git em 2026-09-11 · #7224 · ADR 0397"* — o arquivo novo EXISTE em `origin/main`

- **Afirmação do lote (padrão):** `` `X.md` (`prototipo-ui/cowork/Wagner/cowork-inbox/<...>/X.md`, removido do git em 2026-09-11 · #7224 · ADR 0397) ``
- **O que origin/main diz:** `git ls-tree origin/main -- prototipo-ui/cowork/Wagner/cowork-inbox/hrm/PEDIDO-CL-hrm.md` → `100644 blob 56602bf6…`; idem `patrimonio/playbook/_saida-07.md` → `blob a3714fd8…`; `REPAIR-ONDAS-2026-09-04.md` → `blob 87b42b87…`. Os **21 paths novos** medidos têm blob em main e **zero** commit de deleção (`git log --all --diff-filter=D` vazio). O #7224 (`4f51a9ec781`) apagou o path **antigo** (`D prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md`), que era o sujeito correto da frase em `origin/main`. O lote trocou o sujeito e deixou o predicado: agora a frase afirma que um arquivo vivo foi removido.
- **Por que é erro do lote:** a linha em `origin/main` era verdadeira (path antigo + removido); a linha nova é falsa. O conserto honesto era *"movido para … em #7224"* ou o path novo **sem** o tombstone — exatamente o que o mesmo lote fez em `07-painel.md`, `06-ui-bloqueada.md`, `11-configuracoes.md`, `DECISAO-W-portal-publico`, `RUNBOOK-repair-settings` (os 7 confirmados de G1).
- **Ocorrências (arquivo · linha · path):**
  1. `AssetManagement/Index-visual-comparison.md:236` · `_saida-07.md`
  2. `AssetManagement/Index-visual-comparison.md:237` · `_saida-06-painel.md`
  3. `AssetManagement/RUNBOOK-manutencoes.md:171` · `_saida-06-manutencoes.md`
  4. `Essentials/RUNBOOK-licencas.md:19` · `hrm/PEDIDO-CL-hrm.md`
  5. `Essentials/RUNBOOK-licencas.md:20` · `hrm/EXPORT-HRM-2026-09-04.md`
  6. `Essentials/RUNBOOK-licencas.md:23` · `hrm/PEDIDO-CL-hrm.md`
  7. `Essentials/RUNBOOK-metas.md:26` · `hrm/EXPORT-HRM-2026-09-04.md`
  8. `Essentials/RUNBOOK-metas.md:27` · `hrm/PEDIDO-CL-hrm.md`
  9. `Essentials/RUNBOOK-tipos.md:26` · `hrm/PEDIDO-CL-hrm.md`
  10. `Essentials/RUNBOOK-tipos.md:148` · `hrm/PEDIDO-CL-hrm.md`
  11. `Essentials/RUNBOOK-tipos.md:149` · `hrm/EXPORT-HRM-2026-09-04.md`
  12. `Essentials/documents-index-gap.md:14` · `essenciais/contrato/documentos.contract.json`
  13. `Essentials/documents-index-gap.md:15` · `essenciais/contrato/memorandos.contract.json`
  14. `Essentials/holidays-index-gap.md:13` · `hrm/playbook/08-feriados-puxar.md`
  15. `Essentials/knowledge-index-gap.md:15` · `essenciais/BaseConhecimento.charter.md`
  16. `Essentials/messages-index-gap.md:13` · `essenciais/contrato/mensagens.contract.json`
  17. `Essentials/reminders-index-gap.md:13` · `essenciais/contrato/lembretes.contract.json`
  18. `Essentials/reminders-index-gap.md:15` · `essenciais/Lembretes.charter.md`
  19. `Essentials/settings-index-gap.md:24` · `hrm/playbook/07-configuracoes-puxar.md`
  20. `Essentials/tipos-gap.md:18` · `hrm/playbook/03-tipos-licenca.md`
  21. `Essentials/todo-index-gap.md:14` · `essenciais/contrato/tarefas.contract.json`
  22. `Essentials/todo-index-gap.md:15` · `essenciais/Tarefas.charter.md`
  23. `_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md:11` · `REPAIR-ONDAS-2026-09-04.md`
  24. `_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md:13` · `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md`
  25. `_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md:142` · `REPAIR-ONDAS-2026-09-04.md`

### R2 — 14× *"nunca versionado no repo — artefato externo do Cowork[; medido no histórico completo: 0 commits]"* em arquivo que ESTEVE na história de `origin/main`

- **O que origin/main diz** (restrito à história de main, `git log origin/main --diff-filter=A|D -- <path>`):
  - `memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/{tasks,os-page,sidebar}.jsx`, `README.md`: **A `4ed189349f6` 2026-05-05 · D `1070e3759b7` 2026-05-20** (2 commits cada).
  - `memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/{prod-page,chat,orc-page,produto-app}.jsx`, `README.md`, `data-orc-prod.jsx`: **A `b3ac2669b24` 2026-05-09 · D `4fad2f11f60` 2026-05-20**.
  - `…/_cowork-export-2026-05-19-handoff-bundle/project/uploads/Design System/ui_kits/cockpit/index.html`: A `89b0910d673` 2026-05-19 · D `1070e3759b7`; e `memory/requisitos/_DesignSystem/ui_kits/cockpit.html`: A `00947ec477d` 2026-04-30 · D `1070e3759b7`.
  - Controle positivo da sonda: `git log origin/main -- prototipo-ui/tokens.css` → 18 commits.
- **Por que é erro do lote:** a claim é negativa e vem carimbada como *medida* ("0 commits"). O próprio `CHANGELOG.md:487` (linha anotada) diz *"importado em ui_kits/cowork-2026-04-27/ (14 arquivos …)"* — o lote carimbou "nunca versionado" em cima da linha que registra a importação. O veredito correto é tombstone datado (`removido em 2026-05-20, 1070e3759b7` / `4fad2f11f60`), não "externo".
- **Ocorrências:**
  1. `Financeiro/financeiro-unificado-visual-comparison.md:28` · `ui_kits/cowork-2026-04-27/tasks.jsx`
  2. `Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md:177` · `ui_kits/cowork-2026-05-09/prod-page.jsx` (+ `produto-app.jsx`)
  3. `Jana/Chat-visual-comparison.md:165` · `ui_kits/cowork-2026-05-09/chat.jsx`
  4. `Orcamento/_arquivo/Index.charter.md:157` · `ui_kits/cowork-2026-05-09/orc-page.jsx` + `data-orc-prod.jsx`
  5. `Produto/adr/arq/0001-selling-price-multiplier.md:173` · `ui_kits/cowork-2026-05-09/produto-app.jsx` — carimbo literal *"0 commits"*; medido: 2 em main, 7 em `--all`
  6. `Sells/sells-create-visual-comparison.md:23` · `ui_kits/cowork-2026-04-27/os-page.jsx`
  7. `_DesignSystem/BRIEFING_PROXIMA_SESSAO.md:60` · `ui_kits/cockpit/index.html` — versionado no bundle de export (path acima) e `ui_kits/cockpit.html` na própria pasta
  8. `_DesignSystem/CHANGELOG.md:487` · `ui_kits/cowork-2026-04-27/`
  9. `_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md:16` · `ui_kits/cowork-2026-04-27/README.md` — carimbo "0 commits"
  10. `_DesignSystem/adr/ui/0011-sidebar-single-pane-cascata-user-menu.md:11` · `ui_kits/cowork-2026-04-27/sidebar.jsx` — "0 commits"
  11. `_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:16` · `ui_kits/cowork-2026-05-09/README.md` — "0 commits"
  12. `_DesignSystem/adr/ui/0018-canon-visual-vivo-ds-v6-manual-identidade.md:54` · `ui_kits/cowork-2026-04-27/` — "0 commits"
  13. `Sells/RUNBOOK-create-v3.md:86` · `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` — o path nunca existiu (0 commits, verdade), mas *"artefato externo"* contradiz o canon dono da tela: `resources/js/Pages/Sells/CreateV3.charter.md:11` em origin/main declara `related_prototype: prototipo-ui/cowork/Felipe/venda-v3/sells-create.jsx`, blob presente em main. A âncora está **no repo**; a prosa estava stale contra canon mais novo e o lote carimbou a prosa em vez do canon (mandato §3: canon vence).
  14. `Sells/SPEC.md:1281` · idem ao 13

### R3 — 8× `_(removido em 2026-09-15, 2bed3bbb214)_` em placeholder ou em path nunca versionado — o commit só mexeu em `.gitignore`

- **O que origin/main diz:** `git show --name-status 2bed3bbb214` = `M .gitignore` + `D prototipo-ui/cowork/Wagner/.gitignore` — **nada mais**. `git ls-tree -r origin/main -- prototipo-ui/cowork/Wagner/legado` = **17 arquivos vivos** (`financeiro-contador/`, `nfe-tributacao/`, `nfse-emitir/`, …). `git log --all -- prototipo-ui/cowork/_ds` = **0 commits** (nunca versionado — a própria linha 206 do PARIDADE diz *"vazio"* e a 231 diz *"cache derivado, gitignored"*).
- **Por que é erro do lote:** `legado/<tela>/` e `legado/<modulo>/` são placeholders (o novo `ehPlaceholder` do extrator só cobre `YYYY-MM-DD` e `.../`); o diretório-pai existe. `_ds/` nunca entrou no git — não há o que "remover". Carimbar data+SHA aqui é inventar recibo.
- **Ocorrências:**
  1. `Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:206` · `prototipo-ui/cowork/_ds/office-impresso-design-system-019dd02f…/` — **e** o sufixo foi colado **depois do pipe de fechamento** da linha de tabela (header de 3 colunas → linha com 4 células)
  2. `Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:231` · `prototipo-ui/cowork/_ds/`
  3. `_DesignSystem/INDEX-DESIGN-MEMORIAS.md:34` · `prototipo-ui/cowork/Wagner/legado/<tela>/`
  4. `_DesignSystem/RUNBOOK-onda-cowork.md:81` · `…/legado/<modulo>/styles.css`
  5. `_DesignSystem/RUNBOOK-onda-cowork.md:208` · `…/legado/<modulo>/`
  6. `_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md:23` · `…/legado/<tela>/visual-source.html`
  7. `_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md:29` · idem
  8. `_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md:94` · idem — **e** a anotação foi inserida **dentro de um bloco ` ```bash `** (5 fences antes da linha = aberto), corrompendo o comando `wc -l …`

### R4 — 4× `_(removido em 2026-06-08, c3abe6ea51c)_` em arquivo que EXISTE em `origin/main`

- **Afirmação:** links `[memory/reference/prototipo-ui/PROTOCOL.md](../../../../memory/reference/prototipo-ui/PROTOCOL.md)` e `[…CODE_DESIGN_CONTRACT.md](../../../../memory/reference/prototipo-ui/CODE_DESIGN_CONTRACT.md)` "removidos em c3abe6ea51c".
- **O que origin/main diz:** `git ls-tree origin/main -- memory/reference/prototipo-ui/PROTOCOL.md` → `blob ca45b1c9…`; `CODE_DESIGN_CONTRACT.md` → `blob d8d54c74…`; `git log origin/main --diff-filter=D -- memory/reference/prototipo-ui/PROTOCOL.md` → **vazio**. O `c3abe6ea51c` apagou `prototipo-ui/PROTOCOL.md` e `prototipo-ui/CODE_DESIGN_CONTRACT.md` (outro path).
- **Por que é erro do lote:** o defeito real do doc é o **link relativo** (`../../../../` a partir de `_DesignSystem/adr/ui/` resolve em `memory/memory/reference/…`, path que nunca existiu). O lote diagnosticou "arquivo removido" onde o arquivo vive e o link é que está torto — e citou um commit que apagou outro arquivo.
- **Ocorrências:** `_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md:21` e `:37`; `_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:19` e `:109`.

### R5 — 3× `_(removido em 2026-09-11, 4f51a9ec781)_` em pino apagado em **2026-05-20 por `1070e3759b7`** (ADR UI-0012)

- **O que origin/main diz:** `git log origin/main --diff-filter=D -- prototipo-ui/prototipos/sells-create` → `1070e3759b7 2026-05-20`; idem `financeiro-fluxo`, `financeiro-dre`, `financeiro-plano-contas`, `financeiro-conciliacao` → todos `1070e3759b7 2026-05-20`. Em `4f51a9ec781^` a pasta `prototipos/` tinha só `compras-grade-matrix`, `financeiro-assinatura-atualizar`, `financeiro-contador`, `financeiro-prova-viva`, `inventario-migracao`, `nfe-tributacao`, `nfse-emitir`, `payment-gateway-cnab`, `perfil`, `recurring-billing-planos`, `transaction-payment` — nenhum dos pinos nomeados na linha.
- **Por que é erro do lote:** o extrator resolveu o diretório-pai (`prototipos/`, morto em 4f51) e carimbou o filho com data ~4 meses depois do fato. O mesmo lote datou corretamente `produto-cockpit/` e `produto-unificado/` com `1070e3759b7` — a inconsistência é interna.
- **Ocorrências:** `_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:61` (`sells-create/` — **e** sufixo colado após o pipe de fechamento, 4ª célula numa tabela de 3), `:88` (`sells-create/`), `:93` (`financeiro-*/` = Fluxo/PlanoContas/DRE/Conciliacao nomeados na própria linha).

### R6 — `Sells/index-r1-visual-comparison.md:14` · `visual_source_html: prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html (removido em 2026-05-20, 1070e3759b7)`

- **O que origin/main diz:** `git log origin/main --diff-filter=D -- 'prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html'` → **`7810bf5cb40 2026-05-19`** (#1156 "cleanup 8 cópias duplicadas de 'Oimpresso ERP - Chat.html'"). `git show --name-status 1070e3759b7 | grep -F 'prototipos/sells-index/Oimpresso ERP - Chat.html'` → **vazio**.
- **Por que é erro do lote:** commit e data errados — o path tem espaço e o extrator caiu no diretório (`sells-index/`, esse sim tocado por `1070e3759b7`). Recibo que aponta pro commit errado não é recibo.

### R7 — 2× tombstone em arquivo que **nunca esteve no git** (só o diretório-pai foi movido/apagado)

1. `OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md:105` · `prototipo-ui/alvos/roles/OficinaAuto--ServiceOrders--Show.json` `_(removido em 2026-09-11, 4f51a9ec781)_` — `git log --all -- <path>` = **0**; o `4f51a9ec781` fez `R100 prototipo-ui/alvos/** → governance/design/targets/**` (movido, não removido) e **não lista** esse json. O dono vivo é `governance/design/targets/roles/` (existe em main com `Compras--Index.json`, `Fiscal--Config.json`, …).
2. `Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md:210` · `_(removido em 2026-09-11, 4f51a9ec781)_` — nenhum path da linha foi um arquivo versionado removido por esse commit: `memory/reference/prototipo-ui/PROTOCOL.md` **existe**, `prototipo-ui/audit/reports/Produto__Index.design-report.json` tem **0 commits** em qualquer ref, `prototipo-ui/cowork-2026-05-26.../HANDOFF_PRODUTO_F1.md` é placeholder (`/.../`).

### R8 — `_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:59` · célula de tabela quebrada

- Linha: `| 1 · Fundações (tokens) | `prototipo-ui/tokens.css` | _(removido em 2026-09-11, 878e6069d1a)_` — o tombstone é **correto** (`878e6069d1a` apagou `prototipo-ui/tokens.css`), mas foi colado **depois do pipe de fechamento** de uma tabela com header de **2 colunas** (`| Camada UI v2 | Artefato DS v3 |`): a linha passa a ter 3 células e a tabela renderiza torta. Único item de G4 que não caiu já em G1–G3.

## Observações (não contadas como erro)

1. **ADR UI-0020:13 e UI-0027:92** — o lote acrescentou `_(removido em 2026-09-11, d86c977a8dd)_` (correto: `d86c977a8dd` = #7225 apagou `legado/ds-v6/gabarito-vendas.html`), mas a mesma linha já carregava, vinda de `origin/main`, *"removido do git em 2026-09-11 · #7224"* — e o #7224 (`4f51a9ec781`) fez **`R099 prototipo-ui/cowork/ds-v6/gabarito-vendas.html → …/Wagner/legado/ds-v6/gabarito-vendas.html`** (moveu, não removeu). A linha agora afirma dois commits de remoção; a afirmação nova é a verdadeira, a velha (pré-existente em main) é falsa. Erro herdado, não do lote — mas o lote passou por ela e não reconciliou.
2. **`Produto/BRIEFING.md:134`** — a linha mantém, vinda de `origin/main`, `` `produtos-page.jsx` (`prototipo-ui/cowork/Wagner/produtos-page.jsx`, removido do git em 2026-09-11 · #7224) `` enquanto `git ls-tree origin/main -- prototipo-ui/cowork/Wagner/produtos-page.jsx` devolve blob. Mesma classe do R1, pré-existente em main (o lote só apendou o tombstone correto de `prototipo-ui-patch/prototipos/produto/` → `539efa2a8aa`). Não contado; registrado pra que o conserto do R1 apanhe esta também.
3. **"removido" onde o fato é "movido"** — `documents/messages/reminders/todo-index-gap` (linhas 20–22: `design-docs/cowork-inbox/essenciais/contrato/` → 4f51), `ONDAS-MWART-A-CRIAR.md:14`, `Jana/RUNBOOK-metas.md:386`, `INVENTARIO-ANCORAS-2026-09-09.md:837`, `7b-lote-crm-jana-forja.md:30`, `Superadmin/modules-index-gap.md:30`: o commit citado de fato apagou aquele path (confirmados), mas o conteúdo vive em `prototipo-ui/cowork/Wagner/cowork-inbox/**` (355 arquivos em main). Um leitor conclui "sumiu" quando está a um `ls` de distância. Confirmado por evidência literal; redação induz erro.
4. **`0032:12`, `:140`, `:378`** — `design-docs/handoff-crm/PEDIDO-CODE.md` recebeu um 2º tombstone (`_(removido em 2026-09-11, 4f51a9ec781)_`) idêntico em conteúdo ao que a linha já tinha (`#7224` = `4f51a9ec781`). Redundante, não falso. Não há `cowork/Wagner/handoff-crm/` em nenhuma ref (0 commits) — por isso estes 3 não entraram no R1, corretamente.
5. **`PLAN-MWART-metas.md:130`** (`prototipo-ui/jana-metas/`) e **`RecurringBilling/Index-visual-comparison.md:10`** (`prototipo-ui/cowork-snapshot/`) — confirmados como nunca versionados (0 commits em `--all`). Nota: `prototipo-ui/cowork/Wagner/jana-metas.{jsx,css}` existe em main — o **diretório** de screenshots nunca existiu, a fonte da tela sim.
6. **Extrator (`charter-blueprint-pointers.mjs`)** — a mudança do PR faz `declaraMorte()` aceitar *"nunca versionado"*, e o `ehPlaceholder()` novo não cobre `<tela>`/`<modulo>`. Efeito medido: `--json` → `requisitos_docs_com_orfao: 0`, `requisitos_total_orfaos: 0` — o alarme foi zerado **em parte por carimbos falsos** (R2, R3), que agora são invisíveis à máquina. `--strict` (charters) rc=1 com `charters_com_orfao: 9 / total_orfaos: 10` — fora do escopo deste lote (nenhum charter no diff), pré-existente.
7. **Sessão fresca — nota de método:** medir "nunca versionado" com `git log --all` não basta neste repo: há refs pré-`filter-repo` cujos commits não são ancestrais de `origin/main` (`8c16705811f`, `799af73a3a7`, `2f6892d06ca`, `fe7454ff196` → `merge-base --is-ancestor` = NO). Tudo acima foi re-medido com `git log origin/main -- <path>` e os R2 continuam de pé (A/D em commits ancestrais de main).

## Máquinas derivadas (G5) — rc literal

| comando | rc | leitura |
|---|---|---|
| `node scripts/governance/reconcile-triplet.test.mjs` | **0** | "todas as asserções passaram" — inclui os 2 casos novos do PR (fixture própria) |
| `node scripts/governance/charter-blueprint-pointers.mjs --json` | 0 | `requisitos_docs_com_orfao: 0` · `requisitos_total_orfaos: 0` · `charters_com_orfao: 9` · `total_orfaos: 10` |
| `node scripts/governance/charter-blueprint-pointers.mjs --strict` | **1** | eixo charters (pré-existente; sem charter no diff) |
| `node scripts/governance/plans-index.mjs --check` | **0** | — |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | **0** | — |
| `node scripts/governance/visual-comparison-staleness.mjs --check` | **0** | — |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | **1** | falhas em `Governance/*ModuleGrades*.map.json` (vivo inexistente) e `Ponto/*.map.json` (STALE `prototipo_sha`) — nenhum `.map.json` no diff; pré-existente |
| `node scripts/governance/requisitos-status.mjs <Mod> --check` (só módulos com `_STATUS-GENERATED.md`, como o umbrella) | **1** em Compras · Jana · Ponto · Repair · Sells | drift pré-existente: regenerei Sells (`--write`), diff `+5/−1`, **0 linhas** relacionadas à mudança do lote (`SPEC.md:1281`); revertido com `git checkout -- <arquivo>`; Compras/Ponto nem estão no lote. Step é `continue-on-error: true` no umbrella |
| `bash .github/scripts/validate-memory-schema.sh spec Sells/SPEC.md Governance/SPEC.md` | **0** | 2 warnings de seção recomendada em Governance (pré-existentes) |
| `node scripts/memory-schemas/validate.mjs <17 RUNBOOK/SPEC/BRIEFING/charter do lote>` | **0** | "17 arquivo(s) conformes" (Ajv) |
| `node scripts/design/ancora.mjs {Produto/Index,Sells/Index,Jana/Chat} --staging prototipo-ui/cowork/Wagner` | 0 | `canon_reference` impresso verbatim com o sufixo; reporter, não gate |

`git status --short` após todas as sondagens: **vazio** (a única escrita foi o `--write` de Sells, revertido no mesmo comando).

## Scan PII (G6)

Sobre as **127** linhas `+` de `git diff -U0 origin/main...HEAD -- memory/requisitos` (script `pii.mjs`, regex do símbolo de moeda montada por `String.fromCharCode`, sem literal):

| padrão | hits | controle positivo |
|---|---|---|
| CPF pontuado | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ | 0 | OK |
| telefone BR formatado | 0 | OK |
| telefone cru (10–11 dígitos) | 0 | OK |
| e-mail | 0 | OK |
| valor em reais (símbolo + dígito) | 0 | OK |
| **total** | **0** | **7/7** |

Nomes de cliente do CRM: nenhum nas linhas `+` (o conteúdo é 100% path + tombstone). Nota de método: a 1ª rodada do controle de reais **falhou** (símbolo terminado em `$` virou âncora de regex) — corrigido escapando o caractere; só a rodada com 7/7 vale.

## Comandos reproduzíveis

```bash
# base / HEAD / raso / escopo
git rev-parse --is-shallow-repository; git rev-parse HEAD origin/main
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l      # 73
git diff --numstat  origin/main...HEAD -- memory/requisitos | awk '{a+=$1;d+=$2}END{print a,d}'   # 127 127

# R1 — path novo vivo em main
git ls-tree origin/main -- prototipo-ui/cowork/Wagner/cowork-inbox/hrm/PEDIDO-CL-hrm.md
git ls-tree origin/main -- prototipo-ui/nao-existe-xyz.md | wc -l            # controle negativo: 0
git show --name-status --format= 4f51a9ec781 | grep -E 'hrm/PEDIDO-CL-hrm'    # D só no path design-docs

# R2 — kits estiveram na história de main
git log origin/main --diff-filter=A --format='%h %cd' --date=short -- memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27
git log origin/main --diff-filter=D --format='%h %cd' --date=short -- memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09
git log origin/main --oneline -- prototipo-ui/tokens.css | wc -l              # controle positivo

# R3 — 2bed3bbb214 só tocou .gitignore; legado/ vivo; _ds nunca versionado
git show --name-status --format= 2bed3bbb214
git ls-tree -r --name-only origin/main -- prototipo-ui/cowork/Wagner/legado | wc -l   # 17
git log --all --oneline -- prototipo-ui/cowork/_ds | wc -l                              # 0

# R4 — PROTOCOL.md existe
git ls-tree origin/main -- memory/reference/prototipo-ui/PROTOCOL.md memory/reference/prototipo-ui/CODE_DESIGN_CONTRACT.md

# R5/R6 — quem apagou e quando (história de main)
for p in prototipo-ui/prototipos/sells-create prototipo-ui/prototipos/financeiro-fluxo 'prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html'; do
  git log origin/main --diff-filter=D --format='%h %cd' --date=short -- "$p" | head -1; done
git ls-tree -d --name-only 4f51a9ec781^ prototipo-ui/prototipos/

# R7
git show --name-status --format= 4f51a9ec781 | grep -E 'alvos' | head -3     # R100 → governance/design/targets
git log --all --oneline -- prototipo-ui/alvos/roles/OficinaAuto--ServiceOrders--Show.json | wc -l   # 0

# máquinas
node scripts/governance/reconcile-triplet.test.mjs
node scripts/governance/charter-blueprint-pointers.mjs --json
for m in Compras Jana Ponto Repair Sells; do node scripts/governance/requisitos-status.mjs $m --check; done
```

```json
{"itens_verificados":127,"erros_confirmados":58,"error_rate_pct":45.67,"pii_hits":0,"veredito":"reprovado"}
```
