---
date: "2026-09-06"
topic: "Refutação GT-G5 r1 — lote 'seis gap.md de fundação/shell' (branch claude/gap-md-seis-fundacao-shell-6897, HEAD b4e526500e vs base 80bc4ef8b9)"
authors: ["C"]
outcomes:
  - "253 itens verificados (tipo anchors, 100%) · 7 erros confirmados · error_rate 2,77% · veredito REPROVADO (limiar < 2%)"
  - "Leitor real: os 3 map.json regenerados com --atualizar são idênticos ao commitado; selftest + test.mjs + --check --strict verdes (único DRIFT = Cliente/clientes.map.json, pré-existente — diff vs base vazio)"
  - "Scan PII nas 741 linhas adicionadas: 0 hits em 7 padrões, controle positivo 7/7"
  - "Refutados: badge de contagem por aba EXISTE no Cliente/Index (:827 + PageHeaderTabs:279-300); listener G X não é AppShellV2:400; RECURSOS é :33 (41-47 é MECANICOS); oficina-print.js está no bundle; Valor em curso não é clicável no proto; 'quem cita' incompleto"
---

# Refutação GT-G5 r1 — seis gap.md de fundação/shell (+3 scripts)

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2–§4.
> Sessão fresca (zero contexto do gerador; nenhum `memory/sessions/*refutacao*` de outro lote foi aberto).
> Modelo refutador: Fable 5.1 (`claude-fable-5-1`). Base real = `git merge-base origin/main HEAD` = `80bc4ef8b9`
> (o `origin/main` local já estava em `4fbab283a7`; o diff foi feito contra a base declarada). Clone raso → nenhuma
> data de `git log` foi usada como recibo. Todos os comandos rodados de `D:/oimpresso.com/.claude/worktrees/serene-sutherland-9c4897`.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (gerador `[C]` opus, per corpo do lote; refutador fable)
- [x] Amostra: **100% anchors** (tipo `anchors`; sem seleção aleatória — sem seed a declarar)
- [x] Cada item verificado contra o código real (arquivo aberto em `HEAD`, que não toca nenhum `.tsx`/`.css`/`.jsx` — diff do lote é só `memory/requisitos/**` + 3 scripts), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê) — §Refutados abaixo
- [x] Scan PII no diff — 7 padrões, 0 hits, controle positivo 7/7 — §PII abaixo
- [x] `error_rate_pct` calculado: **2,77%** (≥ 2 → reprovado)
- [ ] Entry no ledger — **NÃO escrita** (fora do mandato desta rodada; fica pro gerador no PR do lote)

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| A · `pageheader-canon-v3-gap.md` (12 linhas: 29 âncoras da coluna "Estado no vivo" + 12 Ações) + `pageheader-canon-v3.map.json` (12 `vivo.linhas` + leitor) | 54 | 52 | 2 | `awk NR=a..b` em `PageHeader.tsx`, `PageHeaderPrimary.tsx`, `index.ts`, `PageHeaderTabs.tsx`, `SubNav.tsx`, `Cliente/Index.tsx`, `KpiGrid/KpiCard.tsx`; `git grep` de ausência (`PageHeaderOverflow`, `KpiStripCanon`, `data-contract`); `grep -nEi sticky|blur|density|i18n|storybook`; ADR 0189 `status:`; prosa P1–P12/§2/§3 lida na íntegra |
| B · `sidebar-v3-unificado-gap.md` (17 linhas: 29 âncoras + 17 Ações) + `sidebar-v3-unificado.map.json` (17 `vivo.linhas`) | 63 | 62 | 1 | idem em `Sidebar.tsx`, `AppShellV2.tsx`, `cockpit.css`, `shared.ts`, `useSidebarShortcut.ts`; `grep -niE pinned|fixados|buscar|search`; contagem de keys de `SIDEBAR_GROUPS`; UI-0023 existe; prosa #1–#17 + §Ordem sugerida + §Veredito lidas |
| C · `ordens-servico-board-gap.md` (8 linhas: 22 âncoras do proto + 37 do vivo + 8 Ações + 7 citações charter/ancora) + `ordens-servico-board.map.json` (8×2 ranges + sha) | 91 | 88 | 3 | `awk` em `Board.tsx` (1300 ln) e `prototipo-ui/cowork/oficina-page.jsx` (1296 ln); `git ls-tree 80bc4ef8b9 -- prototipo-ui/cowork/oficina-print.js`; `Board.charter.md` Goals/Non-Goals/Anti-hooks por grep; `Repair/ProducaoOficina/Index.charter.md:14-17`; `node prototipo-ui/ancora.mjs <tela> --staging prototipo-ui/cowork` ×2; `ServiceOrderRichSheet.tsx:589-592`; `Lib/printOficinaFila.ts` |
| D · 4 banners/blocos (`kanban-producao-gap.md` 6 · `Crm/clientes-gap.md` 8 · `Sells/vendas-index-gap.md` 14 · `Sells/vendas-create-gap.md` 6) | 34 | 33 | 1 | charters (`Cliente/Index.charter.md:5-6`, `Sells/Index.charter.md:6-7`, `Sells/Create.charter.md` sem `related_prototype`/`bundle_source`/`related_us`); `ancora.mjs` ×4; `Sells/Index.tsx` (`:499`, `:725`, `grep -c vd-totalbar`=0, `:1074`, `:1465-1476`); `vendas.map.json` contado por script (6 partes · 11 âncoras reais · 1 TODO · `sha256:0462e8dec988`); `git grep -l` na base; `git cat-file -t` 9da73296d3/e8b49f4b63; `gh pr view` 3343/3451/6893; `Cliente/clientes.map.json` 7 partes; `git diff 80bc4ef8b9..HEAD -- memory/requisitos/Cliente/` vazio |
| E · scripts + leitor real (3 regenerações, `--selftest`, `test.mjs`, `--check --strict`, 5 claims do docblock de `resolverArquivoVivo`) | 11 | 11 | 0 | `node prototipo-ui/gerar-map.mjs <gap> --atualizar > scratch` + comparação JSON sem `gerado_em` (idêntico ×3); selftest OK; test.mjs OK; check: 20/20 cobertura · 4 fora do denominador · 1 DRIFT pré-existente; FP reproduzido na base com script próprio (23 gap.md via `git ls-tree`+`git show`, `MSYS_NO_PATHCONV=1`) |
| **Total** | **253** | **246** | **7** | |

## Refutados (lista completa, com evidência)

| # | Grupo | Item do lote | Evidência (path:linha) | Por quê |
|---|---|---|---|---|
| R1 | A | `pageheader-canon-v3-gap.md:138` (linha "Counter por tab"): *"`Cliente/Index.tsx:807-811` `tab_counts` vem do backend (AP18) e **NÃO é renderizado como badge na aba**"*; idem `pageheader-canon-v3.map.json` parte `counter-por-tab` (`status: decisao-registrada`) | `resources/js/Pages/Cliente/Index.tsx:822-828` — `contactGhosts = SLOT2_TABS.map(t => ({ …, badge: tabCounts[t.key] }))`; `:961-965` `<PageHeaderTabs ghosts={contactGhosts} …>`; `resources/js/Components/shared/PageHeaderTabs.tsx:76` `badge?: number \| string` e `:279-300` `{ghost.badge != null && (… {ghost.badge} …)}`; comentário `Cliente/Index.tsx:956-959`: *"label completo + ícone roxo + **badge de contagem** … [W] 2026-07-14"* | O contador **É** renderizado como badge na aba, hoje. A âncora `:807-811` só cobre a origem do dado; a afirmação de ausência é falsa (medi o consumidor, não só a origem — §5 2026-07-15) |
| R2 | A | Ação da mesma linha: *"Nada — decisão [W] 2026-05-25 registrada (P5: 'não re-adicionar sem sinal')"* | mesma evidência de R1 + regra da própria tabela (`:130`): *"onde o código fechou o gap DEPOIS da prosa … Em conflito prosa × código, o código medido vence e a linha diz isso"* | A Ação deriva da prosa P5 (2026-06-23) e ignora a decisão **posterior** [W] 2026-07-14 que re-adicionou o badge. Pela regra da tabela a linha devia ser `fechado-no-codigo`/decisão posterior e dizer isso — é a mesma família de "decisão [W] posterior vence" que a linha "Tema" do sidebar aplicou corretamente |
| R3 | B | `sidebar-v3-unificado-gap.md:142` (linha "Atalhos kbd"): *"listener em `AppShellV2.tsx:400`"* | `resources/js/Layouts/AppShellV2.tsx:386-402` — o `onKey` registrado em `:400` trata **só** `Cmd/Ctrl+K` (palette) e `Cmd/Ctrl+\` (toggle rail). O listener das sequências `G X` é `useSidebarShortcut(shellMenu)` em `AppShellV2.tsx:355` → `resources/js/Components/cockpit/useSidebarShortcut.ts:178` `document.addEventListener('keydown', aoTeclar)` | Linha citada existe mas contém **outro** listener; a âncora aponta pro mecanismo errado |
| R4 | C | `ordens-servico-board-gap.md:31` (linha "Filtros — boxes/elevadores", coluna Protótipo): *"`RECURSOS` `:41-47`, hardcoded"* | `prototipo-ui/cowork/oficina-page.jsx:33` `const RECURSOS = [`; `:41-46` é `const MECANICOS = [ … ]` | Range errado: 41-47 contém `MECANICOS`, não `RECURSOS` |
| R5 | C | `ordens-servico-board-gap.md:36` (linha "Impressão", coluna Protótipo): *"helper `oficina-print.js` referenciado e **ausente** no bundle"* (afirmado como medido 2026-09-06) | `git ls-tree 80bc4ef8b9 -- prototipo-ui/cowork/oficina-print.js` → `100644 blob b4715dae87…` (tracked na base); `git ls-files` também lista `prototipo-ui/cowork/oficina-print.css` | O helper **está** no bundle do repo. A ausência era verdade no handoff lido em 2026-06-30 (`kanban-producao-gap.md:102,104,121`); restatear em presente como "ausente no bundle" é a lápide §5 2026-09-01 (afirmação de bloqueio herdada sem re-medir) |
| R6 | C | `ordens-servico-board-gap.md:30` (linha "KPIs", coluna Protótipo): *"`:977-1005` — **6 cartões clicáveis** (`kpiClick` …), incl. Urgentes e 'Valor em curso'"* | `oficina-page.jsx:978,983,988,993,998` — 5 `<div className="prod-kpi…" role="button" tabIndex={0} onClick={() => kpiClick(…)}>`; `:1003` `<div className="prod-kpi">` (Valor em curso) **sem** `role`/`onClick` | São 6 cartões, **5** clicáveis; "Valor em curso" não é clicável no proto (a coluna vivo da mesma linha e o charter `:54` dizem 5 — a coluna proto contradiz ambos). Menor, mas é o conteúdo que a âncora afirma |
| R7 | D | `Sells/vendas-index-gap.md:17` (tabela §Reconciliação, linha "quem cita (`git grep -l`, fora do `doc-id-index.json`)"): vendas-gap.md → *"`08-handoff.md` · `handoffs/2026-06-22-2324-*` · `sessions/2026-06-23-arte-handoff-*` · `vendas.map.json`"*; este arquivo → *"**só** o selftest do `prototipo-ui/gerar-contrato.mjs:153-158` … e um comentário em `scripts/pr-critic/coleta.mjs:14`"* | `git grep -l 'vendas-index-gap'` (fora de doc-id-index) = 7 arquivos: + `memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897{,-r4,-r5,-r6}.md` — todos **já na base** (`git ls-tree --name-only 80bc4ef8b9 memory/sessions/` lista r2…r9); `git grep -l 'vendas-gap\.md'` = 8: inclui `prototipo-ui/gerar-contrato.mjs:153` (cita **os dois**) e sessions r5/r6, omitidos na coluna do dono | Contagem "só" incompleta nos dois lados: 4 citações a mais deste arquivo e ≥3 a mais do dono. Não muda a proposta de dono, mas o "quem cita" é o argumento numérico dela |

## Confirmados com observação (não contam como erro)

| Item | Observação |
|---|---|
| B · `shared.ts:208` *"`SIDEBAR_GROUP_HUE` (comercial 55 · financas 145 · **fiscal 175** …)"* | Texto literal em `:222` é `fiscal: 175` ✓ — mas o mesmo objeto redeclara `fiscal: 145` em `:239` (bloco "Legacy v2") e `estoque: 350` em `:238` (`:225` é 315). Em JS a chave posterior vence: **em runtime `fiscal` = 145 (colide com `financas`) e `estoque` = 350**. Defeito pré-existente do código, não do lote; mas a frase "escala ≥25° entre grupos" que a prosa #11 herda **não vale em runtime**. Fica como chip pra quem cuidar do `shared.ts` (fora deste lote) |
| B · *"`Sidebar.tsx`: 0 ocorrências de caixa de busca (`Buscar`/`search`)"* | `Buscar` = 0 ✓; `search` case-insensitive = 3 hits (`:16` import e `:1249` ícone Lucide `<Search>` no link "Central de ajuda" do user-menu). Nenhum é caixa de busca — a afirmação vale, o grep declarado não é literalmente zero |
| B · *"`.sb-pin-empty` de `cockpit.css:344` é da aba Chat, não do menu"* | Nenhum consumidor em `resources/js` (`git grep sb-pin` = 0 fora do CSS); no proto `prototipo-ui/cowork/sidebar.jsx:122-130` o bloco `FIXADAS`/`sb-pin-empty` renderiza `ConvRow` de conversas (`activeConvId`) — é Chat ✓; no vivo é CSS morto (Chat removido em 2026-05-05, `Sidebar.tsx:483`) |
| B · Ação "Seção Pinned — §Ordem sugerida 3 (G · **Fase 7 da ADR 0180**)" | A prosa (`:96`, `:119`) diz "Fase 7 da ADR 0180" ✓ e a Ação cita a prosa. Na ADR 0180 o Pinned aparece como item "### 4. Cmd+K global + Pinned" e "faltam 5 ajustes"; não achei "Fase 7" por grep. Não é erro do lote (deriva da prosa, como a regra manda) — é imprecisão herdada |
| A · map `vivo.linhas` de `subnav-abas` = `PageHeaderTabs.tsx:113` (só a linha da assinatura) e de `filtros` = `Cliente/Index.tsx:998` (comentário) | Ranges "informativos" por desenho (o `_doc` do map diz isso); apontam pro lugar certo, mas são linha única onde a tabela cita blocos maiores |
| C · `Board.charter.md:56-59` *"header fica só com Imprimir fila + Nova OS"* | A frase está em `:57-59` (`:56` ainda é a bullet "menu Visão"). Range contém a citação ✓ |
| E · docblock `resolverArquivoVivo`: *"os outros 21 resolvem igual (**18 sob Pages/**, 3 sem campo)"* | Reproduzido na base (23 gap.md): **21 iguais · 2 mudam · 3 sem campo** ✓. Dos 21 iguais, **16** resolvem pra um path `Pages/`/`fixtures` e **2** têm `tela_viva` que cita `Pages` sem casar em nenhum regex (`Atendimento/caixa-unificada-gap.md` → `Modules/Whatsapp/Resources/js/Pages/…`; `Crm/clientes-gap.md` → `resources/js/Pages/Cliente/` sem `.tsx`). 16 + 2 = 18 "sob Pages/" só contando textualmente |
| D · `Sells/vendas-index-gap.md`: *"`vendas-gap.md` (2026-06-22, #3343) e este (2026-06-30, #3451)"* | `gh pr view 3343` → "docs(sells): gap-spec da tela de Vendas" merged 2026-06-24 (`gerado_em: 2026-06-22` no frontmatter ✓); `#3451` → "docs(comvis): salva os 7 gap-maps…" merged 2026-06-30, e `vendas-index-gap.md` está nos files ✓ |
| D · referências a rodadas r1–r5 do #6897 ("refutação r3 R-1 tirou o mapa", "r4 R-A", "r5 apontou a duplicação") | **Não verificadas por desenho** — o mandato proíbe abrir `memory/sessions/*refutacao*` de outro lote. Contam como itens não-verificados, fora do denominador |
| C/A · `git grep -n data-contract` nos 9 arquivos vivos do lote = 0 | Confirma `vivo.ancora: false` explícito nos 3 maps (37 partes) e a nota "0 data-contract (medido)" |
| E · DRIFT `memory/requisitos/Cliente/clientes.map.json` (STALE `sha256:8f284ad79fb3` → `2be4c00c452a`) | **Pré-existente**: `git diff 80bc4ef8b9..HEAD -- memory/requisitos/Cliente/` vazio; `prototipo-ui/cowork/clientes-page.jsx` não está no diff do lote; último commit que o tocou é `2ae9a5a064` (#6893), na base. O bloco do `Crm/clientes-gap.md` descreve exatamente isso ✓ |

## Item 2 — leitor real (recibos)

```
node prototipo-ui/gerar-map.mjs memory/requisitos/_DesignSystem/pageheader-canon-v3-gap.md --atualizar
# --atualizar: 12 parte(s) preservada(s) · 0 nova(s) TODO · sha sem-arquivo → sem-arquivo   → IDENTICO(sem gerado_em)= true
node prototipo-ui/gerar-map.mjs memory/requisitos/_DesignSystem/sidebar-v3-unificado-gap.md --atualizar
# --atualizar: 17 parte(s) preservada(s) · 0 nova(s) TODO                                   → IDENTICO(sem gerado_em)= true
node prototipo-ui/gerar-map.mjs memory/requisitos/OficinaAuto/ordens-servico-board-gap.md --atualizar
# --atualizar: 8 parte(s) preservada(s) · sha sha256:46891d3a4c5c → sha256:46891d3a4c5c       → IDENTICO(sem gerado_em)= true
node prototipo-ui/gerar-map.mjs --selftest                        → SELFTEST OK (inclui os 4 BITEs novos R-1/R-3 + 2 controles negativos)
node scripts/governance/design-code-map-check.test.mjs            → SELFTEST OK (8 checks novos de map_json: n/a + contradição + publicarResumo)
node scripts/governance/design-code-map-check.mjs --check --strict → cobertura 20/20 (100%) · 4 gap.md fora do denominador por 'map_json: n/a' · [DRIFT] 1 = Cliente/clientes.map.json STALE (pré-existente) · rc=1
```

O `--atualizar` imprime em stdout (não escreve no destino) — a comparação foi feita em scratch, sem tocar o repo. O aviso `⚠️ ancora.mjs: sem charter pra essa tela` nos 2 de fundação é esperado (não há charter de Foundation/Shell).

## Item 3 — Ação × veredito (resumo)

- **PageHeader (12):** 11 Ações derivam da prosa citada (P1 "— (só doc)", P2 "decidir se sticky+blur … Claude Design (ADR 0235)", P4 "features não-aceitas (backlog)", P7 "decisão de tela, não de fundação", P9, P10 "Ignorar pra fins de fundação", P11, P12 "Tratar como backlog, não gap"; §2 PASSO 2 (G) / PASSO 3 (M); §3 "Onde propõe ALÉM … precisa ADR + decisão Claude Design [W]") ✓. "Decidir." só nas 3 partes com gap aberto na prosa (P2, P7, P8) ✓. "Fechado no código depois da prosa" (SubNav) confere: `PageHeaderTabs.tsx` existe com overflow `⋯ Mais` ✓. ADR 0189 `status: aceito` ✓ (`memory/decisions/0189-…:6`). **1 Ação refutada** (R2).
- **Sidebar (17):** todas derivam da tabela de gaps #1–#17 / §Ordem 1–5 / §Veredito ✓ ("NÃO trocar Lucide por glyph" #9 `:72`; "preservar rail (protótipo não cobre, não remover)" #12 `:75`; "n/a — separar do PR de fundação" #16 `:79`; UI-0023 existe e é posterior à prosa → linha "Tema" aplica "decisão posterior vence" ✓). "Decidir." só em #2/#4/#5 ✓. "Fechado no código" (kbd) confere: `ItemEnd` renderiza `.sb-kbd` ✓ (a âncora do listener é que está errada — R3).
- **Board (8):** todas derivam do charter (Goals `:45-48` duas portas · `:53-54` KPIs 5 clicáveis · `:55` abas box · `:57-59` header · `:60` 4 views · `:67` Onda 2 v5; Non-Goals `:90` no-mock-in-prod · `:91` NÃO mexe no board de caçamba · `:96-97` NÃO inventa campo; Anti-hook `:112`) ✓, nenhuma da descrição do mockup. Zero "Decidir." — coerente com veredito PARIDADE. "Fechado no código" (impressão) confere no vivo (`Board.tsx:88,638-665`; `ServiceOrderRichSheet.tsx:589-592`) — o erro R5 é na coluna **proto**, não na Ação.

## Scan PII (linhas `+` do diff `80bc4ef8b9..HEAD -- memory/requisitos`, 741 linhas)

| Padrão | Regex | Hits no lote | Controle positivo (string fictícia, fora do repo, rodada no shell) |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 0 | `<CPF fictício pontuado — redigido>` → 1 ✓ |
| CPF cru (11 dígitos) | `(^\|[^0-9])[0-9]{11}([^0-9]\|$)` | 0 | `x 12345678909 y` → 1 ✓ |
| CNPJ pontuado | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 0 | `<CNPJ fictício pontuado — redigido>` → 1 ✓ |
| CNPJ cru (14 dígitos) | `(^\|[^0-9])[0-9]{14}([^0-9]\|$)` | 0 | `x 12345678000195 y` → 1 ✓ |
| Telefone BR | `\(?0?[1-9]{2}\)?[ -]?9?[0-9]{4}-?[0-9]{4}` | 0 | `(48) 99999-1234` → 1 ✓ |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 0 | `fulano@exemplo.com.br` → 1 ✓ |
| Valor em reais | `R\$ ?[0-9]` | 0 | cifrão + espaço + "1.234,56" (fictício; o literal não entra neste .md por causa do hook `block-brl-values-in-memory`) → 1 ✓ |

`pii_hits = 0`.

## Veredito

7 erros em 253 itens = **2,77%** ≥ 2% → **REPROVADO** (§2.6: volta pro gerador; re-verificar o lote inteiro na r2, não só os 7).
Dos 7, dois são de baixa gravidade (R6 contagem 6→5 no proto; R7 lista de citações incompleta) e dois compartilham a mesma raiz (R1/R2). Os que mais importam pra próxima rodada: **R1/R2** (afirmação de ausência derrubada pelo consumidor — e a Ação registra decisão superada), **R3** (âncora pro mecanismo errado) e **R5** (afirmação de bloqueio herdada de 2026-06-30 e restateada em presente). Nenhum `.tsx`/`.jsx`/`.css` foi tocado por esta refutação; nenhum outro arquivo foi editado; nada commitado; ledger intocado.

```json
{"itens_verificados": 253, "erros_confirmados": 7, "error_rate_pct": 2.77, "pii_hits": 0, "veredito": "reprovado"}
```
