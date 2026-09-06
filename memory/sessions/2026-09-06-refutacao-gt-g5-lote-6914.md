---
date: "2026-09-06"
topic: "Refutação GT-G5 — lote PR #6914 (6 gap.md + 6 map.json Estoque/Manufacturing + _STATUS-GENERATED + clientes.map.json + estado design-sync)"
authors: ["C"]
prs: [6914]
---

# Refutação GT-G5 · lote PR #6914

> Protocolo: `memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md` §2–§4. Sessão fresca (worktree `quizzical-hugle-38b479`, branch `claude/gap-map-estoque-mfg-oficina-oi`, HEAD `1ef4855a1d`, `origin/main` `4fbab283a7`, merge-base `80bc4ef8b9` — main está 5 commits à frente e **nenhum** toca os 10 arquivos-âncora: blobs HEAD ≡ main, conferido por `git rev-parse HEAD:<p>` × `origin/main:<p>`). Clone completo (`--is-shallow-repository` = false). Único `*refutacao*` aberto: o exemplo r9 do #6897, para calibrar formato. Refutador: Fable 5.1 (tier acima de opus). Tipo `anchors`, amostra 100%.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (fable > opus)
- [x] Amostra: 100% anchors (tipo `anchors`; sem prosa destilada no lote)
- [x] Cada item verificado contra `origin/main` (`git show origin/main:<path> | sed -n`, `git ls-tree`, `git log`, `git rev-parse`), nunca contra o texto do PR
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII nas 1.483 linhas `+` de `memory/requisitos` com controle positivo por padrão — 0 hits
- [x] `error_rate_pct` calculado: **12,5%** — ≥ 2% ⇒ **reprovado**
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — NÃO é deste refutador (este arquivo é o único produto); fica pro gerador/parent

## Lote medido

`git diff --name-status origin/main...HEAD` = 17 arquivos: 6 `*-gap.md` (A) + 6 `*.map.json` (A) em `Estoque/` e `Manufacturing/`, `Manufacturing/_STATUS-GENERATED.md` (A), `Cliente/clientes.map.json` (M, só `prototipo_sha`), `scripts/design-sync/state/{applications,application-report}.json` (M) e `scripts/governance/.cowork-freshness-ledger.json` (M, +1 entrada = rodada 38). Um commit só (`1ef4855a1d`). O nome do branch promete Oficina/OI; o lote entrega 6 telas — e o `manufacturing-recipes-gap.md:11` ainda fala em *"10 telas deste lote"* (ver R-23).

## Resultado por grupo

| Grupo | Itens | Confirmados | Refutados |
|---|---|---|---|
| 1. Âncoras dos `.map.json` (existência em main ×12 · `ancora.mjs` ×6 · não-revogação no charter ×6 · `prototipo_sha` recomputado no tip de main ×6) | 30 | 30 | 0 |
| 2. Frontmatter `tela_viva`/`prototipo` (existem em main + lidos por `fmVal`/`resolverArquivosPrototipo` — regeneração reproduz `tela`, `gap_fonte`, `arquivosPrototipo`) + `_STATUS-GENERATED` em dia | 13 | 13 | 0 |
| 3a. Linhas das 76 células da tabela (Estado no vivo × Ação × código real) | 76 | 54 | **22** |
| 3b. `acao`/`_acionavel`/`id`/`status`/`prototipo_sha` do map == regeneração em memória (`gerar()`) | 6 | 6 | 0 |
| 3c. Notas de cabeçalho dos gap.md (âncora, porte reverso, região, frescor, escopo) | 28 | 25 | **3** |
| 4. Claims de ausência recontadas (9 tokens + 14 capacidades + 1 contagem declarada) | 24 | 23 | **1** |
| 5. Non-Goals (reabertos indevidamente? / "Nada — Non-Goal" sem Non-Goal?) | 8 | 8 | 0 (o caso real está contado em 3a, R-19) |
| 6. Ledger de frescor (proveniência da rodada 38 · sustentação do STALE de `manufacturing-page.jsx`) | 2 | 2 | 0 — mas ver "Não sustentado" abaixo |
| 7. Dívida herdada `Cliente/clientes.map.json` | 3 | 3 | 0 |
| 8. Gates (`design-code-map-check --check --strict` · `requisitos-status` ×3 · `plans-index`) | 5 | 5 | 0 |
| 9. Scan PII (7 padrões, controle positivo 7/7) | 7 | 7 | 0 |
| 10. `application-report.json`: `mapSha256` e `targetSha256` das 6 telas batem com os arquivos | 6 | 6 | 0 |
| **Total** | **208** | **182** | **26** |

### Grupo 1 — âncoras (CONFIRMADAS)

`git ls-tree origin/main` devolve blob para os 10 paths (`estoque-forms.jsx` `6794fba5`, `estoque-page.jsx` `f629cbaa`, `manufacturing-page.jsx` `daa9f481`, `manufacturing-producao.jsx` `80614e07`, e os 6 `.tsx`). `node prototipo-ui/ancora.mjs <Tela> --staging prototipo-ui/cowork` resolve `âncora ✓ [-page.jsx (bundle · bundle_source)]` nas 5 telas de bundle e `[related_prototype (charter)] manufacturing-page.jsx` em Recipes — exatamente o que cada gap.md declara. Nenhum dos 6 charters tem `MIS-ANCHOR`/"removido related_prototype"; os 5 `n/a (herda PT-0X)` coexistem com `bundle_source` (§5 2026-08-28 c). `prototipo_sha` recomputado por `computeProtoHash` sobre os blobs de **main** (não do working tree): `bfd716b5fd4a` (4 Estoque) · `34e4ea8ca840` (Mfg/Index) · `0ffb98fa0d7b` (Recipes) · `2be4c00c452a` (Cliente) — todos idênticos aos commitados.

### Grupo 2 — frontmatter (CONFIRMADAS)

`fmVal` (`gerar-contrato.mjs:36`) casa `^prototipo:`/`^tela_viva:`; `resolverArquivosPrototipo` (`gerar-map.mjs:65-76`) resolve a forma `a.jsx + b.jsx` como 1º path completo + basenames no mesmo dir (selftest `:231-232` cobre). Prova: `gerar()` em memória sobre os 6 gap.md reproduziu `tela`, `gap_fonte`, `partes.length`, `id`, `status`, `acao`, `_acionavel` e `prototipo_sha` idênticos ao commitado. `requisitos-status.mjs Manufacturing --check` → em dia.

### Grupo 3b — map ↔ tabela (CONFIRMADAS, com 1 observação estrutural)

0 chaves divergentes em `acao`/`_acionavel`/`id`/`status`/`prototipo_sha`. A única diferença entre o gerado e o commitado é `prototipo.arquivo` em **14 partes** (1+2+1+2+8+0), que o gerador preenche sempre com `arquivosPrototipo[0]` (`gerar-map.mjs:167`) e o lote refinou à mão para o arquivo certo (ex.: `manufacturing-producao.jsx` nas 8 partes da aba de produção). O refino é correto e o `design-code-map-check` aceita — **mas não é durável**: `fundirComExistente` (`:205`) só preserva `prototipo.arquivo` quando `linhas !== 'TODO'`; rodei a fusão em memória e **os 14 revertem** no próximo `--atualizar`. Não conta como erro do lote (o map está certo hoje); fica registrado porque a Fase 4 depende dele.

### Grupo 3a — as 76 linhas

**StockAdjustment/Create (5/10).** Cabeçalho ✓ (`Create.tsx:131-145`, `estoque-forms.jsx:240-246`) · Dados do ajuste ✓ (`:147-203`, `:53-56`, `divergence_from_blueprint` charter l.17) · Lote ✓ (`:34`, `estoque-forms.jsx:78`) · Efeito no saldo ✓ (`:80`) · Pré-preenchimento ✓ (`estoque-page.jsx:530-536` `baixarVencido`, `:538-553` `fecharContagem`). **Refutadas:** Busca (R-1) · Valor recuperado (R-2) · Totais (R-3) · Motivo (R-4) · Multi-tenant (R-5).

**StockAdjustment/Index (12/13).** Header ✓ (`Index.tsx:117-126`; `MP.Header` `estoque-page.jsx:581-591`) · Filtros ✓ (`:128-160`, `:85-92`; `PeriodBar :144-147`, presets `:17-21`, chips `:176-182`) · Tabela ✓ (8 colunas `:168-175`; `COLS_AJ :196-206` tem 9 e as 2 que faltam são de fato `itens` e `motivo`) · Ordenação/densidade/colunas ✓ (`:280-283`, `:157`, `:161-170`, `:186-193`) · Exportação ✓ (`:158-160`, `:246-253`) · Rodapé ✓ (`:285-292`; cálculo em `:243-244`, cite `:245-246` off 2 — tolerado) · Paginação ✓ (`:179`; `:227`, `:293`) · Bulk ✓ (`:296-301`; `AlertDialog` do vivo `:239-258`) · Ações por linha ✓ (`:196-206`; `ModalExcluir :358-381`) · Drawer ✓ (`DrawerAjuste :503-565`, botão `:519-520`) · Estado vazio ✓ (`:210-233`; `:284`) · Multi-tenant ✓ (`:50-55`; `D.can :211`, `:277`). **Refutada:** Abas (R-6).

**StockTransfer/Create (10/12).** Cabeçalho ✓ (`Create.tsx:135-149`; `:345-353`; h2 em `estoque-page.jsx:660-661`) · Status ✓ (`:70`, `:177-187`; `help` `estoque-forms.jsx:294`, frase literal ✓) · Limpeza ✓ (`trocarDe :278` — cite `:279` off 1) · Trava de saldo ✓ (`dispDe :271`, `negativa :272`, alerta `:321-322` com INV-4, `podeSalvar :274` — cites off ≤3) · Lote ✓ (`:78`, `semLote :273`) · Conservação ✓ (`:323-328`, frases literais) · Frete ✓ (`:335-356`, `:84`; `:338-340`; help `:333`) · Notas ✓ (`:363-371`) · Edição ✓ (`editar` em `:253`) · Permissão ✓ (`semPermissao :280-284`). **Refutadas:** Origem→Destino (R-7) · Busca (R-8).

**StockTransfer/Index (12/15).** Header ✓ (`Index.tsx:149-157`) · Filtros ✓ (`:162-202`) · Tabela ✓ (`:211-218`; `COLS_TR :306-316`, `off: true` em `:316`) · Ordenação ✓ (`:393-395`, `:157`, `:161-170`) · Exportação ✓ (`:158-160`; `:360-367`) · Rodapé ✓ (`:398-405`; `:357-358`) · Paginação ✓ (`:405`) · Bulk ✓ (`:407-414`, 4 rótulos literais; `updateStatus` citado em `Index.tsx:125-126`) · Ações por linha ✓ (`:246-259`) · Diálogo ✓ (`:292-302`; `:358-381`) · Drawer/Folha ✓ (`:567+` endereço/cidade/telefone em `:598-600`; `FolhaTransferencia :403-488`, assinaturas `:467-471`, barras `:473`) · Estado vazio ✓ (`:263-285`) · Mexeu no saldo: coluna e predicado ✓ (`:215`, `:236`, `:118-134`), mas a célula do protótipo citada está errada (R-10). **Refutadas:** Abas (R-9) · Mexeu no saldo (R-10) · Sub-linha SKUs (R-11).

**Manufacturing/Index (8/12).** Filtros ✓ (`Index.tsx:218-285`; `manufacturing-producao.jsx:43-53`, `mfg-check :51`) · Tabela ✓ (`:319-326`, `:356-362`, `:369`; `CH :21`, `:71`; comentário `:391-393`) · Ordenação ✓ (varredura recontada abaixo; `Th :34-39`) · Paginação ✓ (`:78-86`, `POR_PAG :29`) · Rodapé ✓ (`:380-386` verbatim; `:87`; `:378-379`) · Estado vazio ✓ (`:291-313`; `:75`) · Criar/editar ✓ (Non-Goal charter l.38; `MfgProducaoForm :94-188`, `:137`, `:175`) · Multi-tenant ✓ (G4). **Refutadas:** Header (R-12) · Abas (R-13) · KPIs (R-14) · Drawer de detalhe (R-19).

**Manufacturing/Recipes (7/14).** Abas ✓ (`Recipes.tsx:167-195`; comentário `:187-191`) · Rodapé do drawer ✓ (`:539-559`, `ROTA_* :43-44`; mounts do protótipo `:130-142` — cite `:136-153` parcial, tolerado) · Criar receita ✓ (`:155-159`, `:42`; `criarReceita :88-100` — cite `:97-112` parcial, tolerado; `MfgNovaReceita :299`) · Excluir ✓ (contagens 0/0/0; modal `:277-292`, frase em `:284`) · Toast ✓ (`mfg-toast` 0; `toast` `:40`, render `:302`) · Impressão ✓ (`:406`, `:544-548`; `:295`) · Multi-tenant ✓ (`perms :25`). **Refutadas:** Header (R-15) · KPIs (R-16) · Busca (R-17) · Tabela (R-18) · Paginação (R-20) · Ações em massa (R-21) · Drawer (R-22).

### Grupo 4 — ausências recontadas (23/24)

`Manufacturing/Index.tsx`: `mfg-th` 0 · `<Th` 0 · `ordenar` 0 · `setPag` 0 · `mfg-pag` 0 (+ `POR_PAG`/`sort`/`Pagination`/`pagina` 0) ✓. `Recipes.tsx`: `Excluir` 0 · `AlertDialog` 0 · `mfg-modal` 0 · `mfg-toast` 0 (+ `toast`/`excluir`/`destroy`/`delete` 0) ✓. Estoque: `StockAdjustment/Index.tsx` e `StockTransfer/Index.tsx` sem `sort`/`slice(`/`Pagination`/`csv`/`Excel`/`BulkBar`/`Checkbox`/`localStorage`/`Drawer`/`tfoot`/`reduce(` (0 cada; o único `export` é `export default`, o único `print` é a rota `/print` da linha) ✓; `adjustment_type` aparece 2× em SA/Index e são tipo + badge, não filtro ✓. Creates: `Lote`/`saldo`/`dispon`/`autocomplete`/`fetch(`/`axios`/`setLinhas([])`/`STATUS`/`help` 0 ✓ (os 2 `lot` de SA/Create são `lot_no_line_id` do tipo e do `null`). **Refutada:** a contagem "os 5 casamentos de um grep frouxo eram `border`/`order`" (R-24).

### Grupo 5 — Non-Goals

Mfg/Index: os 3 Non-Goals do charter (l.38-40) não foram reabertos; "Criar/editar ordem" → Nada é correto. Recipes: "Atualizar preço" → Nada cita a decisão registrada (charter l.56 + `Recipes.tsx:387-392`) ✓; "Criar receita"/"Excluir" → Nada cobertos por *"Não escreve nada"* (l.55) ✓; "Rodapé do drawer" cita o charter do Index quando o próprio Recipes.charter l.64 já cobre — âncora mais fraca, não falsa; Toast → Decidir (não é Non-Goal) ✓. Os 4 charters de Estoque não declaram Non-Goal algum e o lote não inventou nenhum (marca "Decidir") ✓. O único "Nada — Non-Goal" sem Non-Goal declarado é o drawer de detalhe da ordem (R-19, contado em 3a).

### Grupo 6 — ledger de frescor

Rodada 38 (`.cowork-freshness-ledger.json`, entrada de `2026-09-06T11:48:39.076Z`): `files 258 · sync 0 · stale 2 · unchecked 256 · staleList ["officeimpresso-page.jsx","oficina-page.jsx"] · verified [] · verifiedHash {}`. A última rodada que verificou `estoque-page.jsx` e `manufacturing-page.jsx` é a **24** (`2026-08-27T21:56:54Z`, por hash). `oficina-os-page.jsx` só aparece nas rodadas 12, 13 e 24. A entrada 38 **não tem `origin`** (as 36/37 têm `"medicao"`): `ledgerEntry` grava `origin: snapshot._origin` (`cowork-mirror-freshness.mjs:2219-2220`), logo o snapshot que alimentou a rodada 38 não veio de `--snapshot-from` — a proveniência da medição não está registrada. Consequências contadas em 3c (R-25, R-26).

**Não sustentado pelo ledger (não contado como erro, mas é claim sem recibo):** `manufacturing-index-gap.md:15` e `manufacturing-recipes-gap.md:15` afirmam *"Frescor do espelho (medido 2026-09-06): `manufacturing-page.jsx` = STALE"*. O ledger não tem esse arquivo em nenhuma rodada de 2026-09-06; o próprio `ancora.mjs Manufacturing/Recipes` imprime *"SEM VEREDITO NOVO — verificado em 2026-08-27 … a última rodada (2026-09-06T11:48) (mediu 2 de 258) não o incluiu"*. Sem DesignSync eu não consigo refutar nem confirmar o conteúdo; o que consigo dizer é que "medido" aqui não tem recibo em lugar nenhum do repo (§5 2026-07-17). O mesmo vale, com menos gravidade, para o "verificação estrutural SYNC" das 4 telas de Estoque, que só existe na prosa do gap.

### Grupo 7 — dívida herdada (CONFIRMADA)

`git show 2ae9a5a064^:prototipo-ui/cowork/clientes-page.jsx` (blob pré-#6893) → `computeProtoHash` = **`sha256:8f284ad79fb3`**, exatamente o sha que `origin/main:Cliente/clientes.map.json` carrega; o blob atual dá **`2be4c00c452a`**, o que o branch grava. `#6893` (`2ae9a5a064`, 06:19) entrou em main **antes** de `#6897` (`01a4044c0e`, 06:31) — o map do #6897 nasceu num branch anterior ao #6893 e pousou stale. O branch deste lote **não toca** `clientes-page.jsx` (`git diff --name-only origin/main...HEAD` vazio para o path). As duas afirmações do PR conferem.

### Grupo 8 — gates (rc real)

`node scripts/governance/design-code-map-check.mjs --check --strict` → **rc=0** (*"[OK] nenhum map.json com âncora quebrada ou sha stale"*, 23/29 telas com gap.md têm map). `requisitos-status.mjs Estoque|Manufacturing|Cliente --check` → **rc=0 ×3**. `plans-index.mjs --check` → **rc=0**.

### Grupo 10 — estado design-sync (CONFIRMADO)

As 6 `screens[]` que passaram de `anchored` para `compared`: `sha256(map.json)` == `mapSha256` e `sha256(<Tela>.tsx)` == `targetSha256` nas 6.

## REFUTADOS (lista completa, 26)

Regra de tolerância usada nas citações de linha: cite aceito se contém o afirmado ou está a ≤3 linhas dele; refutado quando aponta para conteúdo diferente com deslocamento ≥7 linhas, ou quando a **afirmação** sobre vivo/mockup é falsa.

### Afirmações invertidas sobre o mockup (as mais graves — viram instrução errada na Fase 4)

- **R-2 · SA/Create · "Valor recuperado e R-ADJ-003"** — Ação: *"O protótipo … **não** implementa o bloqueio da R-ADJ-003; o vivo bloqueia no cliente"* → veredito "vivo à frente". **Falso.** `origin/main:prototipo-ui/cowork/estoque-forms.jsx:177` `const recExcede = rec > total; // R-ADJ-003`, `:178` `podeSalvar = … && !recExcede`, `:223` mensagem *"Recuperado não pode passar do total ajustado (…)"*, `:244` `if (!podeSalvar) { aviso(recExcede ? "Recuperado passou do total ajustado (R-ADJ-003)." …); return; }`. É paridade, não vantagem do vivo. (O cite `:144` do vivo é `}`; a mensagem real está em `Create.tsx:312-317`.)
- **R-7 · ST/Create · "Origem → Destino (R-XFER-004)"** — Ação: *"O protótipo valida `mesmoLocal` mas **mantém a origem selecionável no destino**; o vivo remove a opção impossível"* → "vivo à frente". **Falso.** `estoque-forms.jsx:303` `options={[…, ...Object.keys(D.LOCAIS).filter((k) => k !== de).map(…)]}` — o protótipo também filtra a origem do select de destino. Paridade.
- **R-12 · Mfg/Index · "Header do módulo"** — Estado no vivo: *"`os-page-h` com h1 'Produção'"*; Ação: *"paridade estrutural. O protótipo tem o mesmo `os-page-h`"*. **Falso no vivo:** `grep -c os-page-h resources/js/Pages/Manufacturing/Index.tsx` = **0**; a tela usa o componente compartilhado `<PageHeader icon="factory" title="Produção" …>` (`:145-156`, 4 ocorrências). É `Recipes.tsx:144` que tem `os-page-h`. Estrutura diferente, não paridade — e o cite do protótipo `manufacturing-page.jsx:161-171` é o `ABAS.filter(…).map` + início de `mfg-kpis`; o header real é `:148-157`.
- **R-19 · Mfg/Index · "Drawer de detalhe da ordem"** — *"Nada — Non-Goal por herança do anterior"*. O charter (`Index.charter.md:37-40`) declara três Non-Goals: CRUD completo (create/edit/destroy), Kanban, Recipes/BOM. Um drawer de **leitura** da ordem não é nenhum dos três; a própria célula admite *"abrir só a leitura sem o CRUD é decisão possível"*. Non-Goal é território [W] (charter l.46) — inferir herança é inventar Non-Goal. Devia ser "Decidir".

### Rótulos e nomes de componente que não existem

- **R-1 · SA/Create · "Busca e adição de produto"** — Estado no vivo cita botão *"Adicionar linha"*; `Create.tsx:223` diz **"Adicionar item"**. E o cite `:275-280` para *"product_name é string editável na linha"* aponta para `{brl(linha.quantity * linha.unit_price)}` + botão Remover; o `<Input value={linha.product_name}>` está em `:251-255`.
- **R-8 · ST/Create · "Busca e adição de produto"** — mesmo rótulo *"Adicionar linha"*; `Create.tsx:260` diz **"Adicionar item"**.
- **R-6 · SA/Index · "Abas por tipo de ajuste"** — *"O protótipo tem `window.CliTabs` com Todos/Normal/Anormal"*. `grep -c CliTabs prototipo-ui/cowork/estoque-page.jsx` = **0**; o que há em `:258-263` é `<TabBar tabs=…>` do DS (`const { …, TabBar, … } = DS()` em `:210`). `CliTabs` é o adaptador de `cli-tabs.jsx` usado em `clientes-page.jsx:432`, não aqui.
- **R-9 · ST/Index · "Abas por status"** — idem: *"`window.CliTabs` com uma aba por status (`:368-373`)"* — é `TabBar` (`:371-375`).

### Citações de linha que apontam para outro conteúdo

- **R-3 · SA/Create · "Totais"** — `Create.tsx:150-162` é `</CardHeader>` … options do select de Filial; os três números (Total ajustado / Recuperado / Perda líquida) e o gate estão em `:301-331`.
- **R-4 · SA/Create · "Motivo / notas"** — `Create.tsx:166-178` é o campo Ref. Nº; o `<Textarea placeholder="Razão do ajuste…">` está em `:340-345`.
- **R-5 · SA/Create · "Multi-tenant e permissões"** — `Create.tsx:97-112` é `adicionarLinha`/`removerLinha`/`atualizarLinha` e `:132` é `icon="package"`; os gates `permissions.view_purchase_price` estão em `:238-243`, `:266-281`, `:301`.
- **R-10 · ST/Index · "Mexeu no saldo?"** — *"célula em `estoque-page.jsx:355`"* → l.355 é `});`; a célula `saldo: <span className={move ? "est-mov ok" : "est-mov"}>` é `:348`.
- **R-11 · ST/Index · "Sub-linha com os SKUs"** — *"`estoque-page.jsx:353`"* → l.353 é `},`; `rota: { …, sub: t.itens.map((i) => i.sku).join(" · ") }` é `:346`.
- **R-13 · Mfg/Index · "Abas do módulo"** — *"as abas trocam estado local (`manufacturing-page.jsx:173-179`)"* → l.173-179 são os KPIs (`Custo médio / unidade`, `Margem abaixo de 45%`); o `<nav className="mfg-tabs">` com `onClick={() => setAba(a.id)}` é `:159-167`.
- **R-14 · Mfg/Index · "KPIs"** — *"os 4 que ele exibe (`:197-221`)"* → l.197-221 é o input de busca, chips e linhas da tabela; `mfg-kpis` é `:171-193`. (A tese — os KPIs do protótipo são da aba Receitas — está certa.)
- **R-15 · Recipes · "Header do módulo"** — `manufacturing-page.jsx:161-171` (nav/ABAS.map); header real `:148-157`.
- **R-16 · Recipes · "KPIs"** — `:197-221` (busca/tabela); real `:171-193`.
- **R-17 · Recipes · "Busca e chips"** — `:224-232` é `mfg-num`/`mfg-pill`/`mfg-empty`; busca+chips real `:194-202`.
- **R-18 · Recipes · "Tabela / colunas"** — *"cabeçalho em `:239-247`"* → l.239-247 é a paginação (`‹`/`›`); o `mfg-thead` com os 7 `<Th>` é `:206-215`.
- **R-20 · Recipes · "Paginação"** — `:252-262` é o `mfg-bulk` + `aba === "insumos"`; `mfg-pag` real `:235-246`.
- **R-21 · Recipes · "Ações em massa"** — *"a 3ª ação … (`manufacturing-page.jsx:290`)"* → l.290 é `</div>`; o botão *"Atualizar preço de venda do produto"* é `:254`.
- **R-22 · Recipes · "Drawer da receita"** — *"link para Compras (`:302` no vivo)"* → `Recipes.tsx:302` é `key={r.id}` de uma linha da tabela; o botão `Compras` é `:528-529`.

> Padrão: as 8 citações erradas de `manufacturing-page.jsx` (R-12..R-21) estão todas deslocadas de +13 a +33 linhas em relação ao blob de main, enquanto as de `manufacturing-producao.jsx` e do drawer (`:306-360`, `:355`) batem. Os dois gap.md afirmam *"Linhas citadas são do espelho versionado"* — para este arquivo, não são: consistente com o gerador ter lido outra versão (a viva do Cowork, mais longa?) justamente no arquivo que ele declara STALE. Não consigo provar a origem; consigo provar que não é o blob `daa9f4812c` de main.

### Frescor atribuído ao ledger que o ledger não sustenta

- **R-25 · Mfg/Index · nota de frescor (`manufacturing-index-gap.md:15`)** — *"atingiu `oficina-page.jsx`, `oficina-os-page.jsx` e `officeimpresso-page.jsx` (**esses dois últimos** medidos por hash, rodada 38)"*. A rodada 38 tem `staleList = ["officeimpresso-page.jsx", "oficina-page.jsx"]`; `oficina-os-page.jsx` não está nela (última menção: rodada 24, 2026-08-27). Os dois medidos por hash são `oficina-page` + `officeimpresso-page`.
- **R-26 · SA/Index · nota de frescor (`stock-adjustment-index-gap.md:15`)** — *"Frescor do espelho (medido 2026-09-06, `--compare --ledger` rodada 38): `estoque-page.jsx` = verificação estrutural SYNC"*. A rodada 38 registra `sync: 0 · verified: [] · verifiedHash: {}` e `estoque-page.jsx` entre os 256 `unchecked`. A "verificação estrutural" pode ter acontecido — mas fora do ledger, e a frase põe a rodada 38 como recibo de algo que ela não mediu (§5 2026-07-17: citar a tool ao lado não é recibo). As outras 3 telas de Estoque herdam a nota por link.

### Contagens declaradas que não reproduzem

- **R-23 · Recipes · cabeçalho (`manufacturing-recipes-gap.md:11`)** — *"a única das **10 telas deste lote** com âncora por `related_prototype`"*. O lote tem **6** telas (diff acima). A afirmação "única com `related_prototype`" é verdadeira para as 6.
- **R-24 · Mfg/Index · "Ordenação por coluna"** — *"os 5 casamentos de um grep frouxo eram `border`/`order`"*. Recontado em `origin/main:resources/js/Pages/Manufacturing/Index.tsx`: `grep -c order` = **4** linhas (218, 223, 289, 331); `grep -o order | wc -l` = **11** casamentos; `-ci` = 4; `-w` = 0. Nenhuma unidade dá 5. A conclusão (ordenação ausente) está certa; o número declarado como varredura contada não é de varredura nenhuma.

## Observações (não contam como erro)

- **`--atualizar` desfaz o refino dos maps** — `fundirComExistente` só preserva `prototipo.arquivo` com `linhas` preenchidas (`gerar-map.mjs:205`); simulado em memória: 14 partes (1+2+1+2+8) voltam para `estoque-forms.jsx`/`manufacturing-page.jsx`. Ou preenche-se `linhas` junto, ou o gerador passa a preservar `arquivo` sozinho.
- **Rodada 38 sem `origin`** — snapshot alimentado sem `_origin`; a proveniência da única medição de frescor deste dia não fica registrada. Combina com o fato de o STALE de `manufacturing-page.jsx` não constar em lugar nenhum.
- Cites tolerados (≤3 linhas): `mesmoLocal :268`→270 · `dispDe :270`→271 · `semLote :271`→273 · `podeSalvar :272`→274 · `trocarDe :279`→278 · `saldo col :310`→311 · `somaAj :245-246`→243-244 · `Pagination :404`→405 · `Th :36-42`→34-39 · `POR_PAG :31`→29 · `toast :41`→40 · `render :300`→302 · `FichaPrint :296`→295 · `Compras :355`→354 · `criarReceita :97-112`→88-100 (parcial) · `mounts :136-153`→130-142 (parcial: inclui `MfgProducaoForm`, não `MfgIngredientesEditor`).
- Recipes "Rodapé do drawer" ancora o Non-Goal no charter do **Index** quando `Recipes.charter.md:64` (*"Não edita ingredientes nem lança produção"*) já é o dono — não é falso, é âncora mais fraca.
- Mfg/Index "Abas do módulo": o comentário do vivo (`:158-162`) e o `<span aria-current="page">` (`:171`) conferem; só o cite do protótipo falha (R-13).
- SA/Create "Dados do ajuste" e ST/Create "Cabeçalho": paridade confirmada célula a célula — os placeholders diferem em copy (`(auto-gerado)` × `Gerada automática se vazio`), como o gap registra.

## Scan PII (linhas `+` do diff em `memory/requisitos`, 1.483 linhas)

| Padrão | Hits no diff | Controle positivo |
|---|---|---|
| CPF pontuado `\d{3}\.\d{3}\.\d{3}-\d{2}` | 0 | 1 |
| CPF cru (11 dígitos isolados) | 0 | 1 |
| CNPJ `\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}` | 0 | 1 |
| Telefone BR `(\d{2}) 9?\d{4}-\d{4}` | 0 | 1 |
| Telefone cru (10–11 dígitos) | 0 | 1 |
| E-mail | 0 | 1 |
| Valor em reais (`R$` + dígito) | 0 | 1 |

Controle = linha sintética com um exemplar de cada padrão, passada pelo mesmo `grep -E`; 7/7 casaram. Nomes próprios (`larissa|martinho|eliana|wr2|rota livre|vargas|extreme|jorge`) nas linhas `+`: 0. **pii_hits = 0.**

## Veredito

208 itens · 26 erros confirmados · **12,5%** ≥ 2% → **reprovado**. Não é um erro isolado: são 4 afirmações invertidas sobre o mockup/vivo (R-2, R-7, R-12, R-19), 4 rótulos/componentes que não existem (R-1, R-6, R-8, R-9), 14 citações de linha que apontam para outro conteúdo (concentradas em `StockAdjustment/Create.tsx` e `manufacturing-page.jsx`), 2 recibos de frescor que o ledger contradiz (R-25, R-26) e 2 contagens que não reproduzem (R-23, R-24). Pelo §2.6 do protocolo, o lote volta inteiro ao gerador — e a re-verificação tem de cobrir as 76 células de novo, porque o erro de linha em `manufacturing-page.jsx` é sistemático (outra versão do arquivo), não pontual. Os grupos 1, 2, 3b, 7, 8, 9 e 10 estão limpos e não precisam ser refeitos, só re-conferidos.

```json
{"itens_verificados": 208, "erros_confirmados": 26, "error_rate_pct": 12.5, "pii_hits": 0, "veredito": "reprovado"}
```
