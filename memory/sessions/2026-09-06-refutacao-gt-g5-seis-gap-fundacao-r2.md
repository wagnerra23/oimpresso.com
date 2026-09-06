---
date: "2026-09-06"
topic: "Refutação GT-G5 r2 — lote seis-gap-fundacao-shell (#6897 follow-up): 10 docs em memory/requisitos + 3 scripts"
authors: ["C"]
outcomes:
  - "316 itens verificados (100% âncoras + prosa dos banners + scripts) · 3 refutados · error_rate 0,95% · PII 0/7 padrões (7/7 controles positivos) · veredito APROVADO"
  - "R-1: pageheader-canon-v3.map.json parte counter-por-tab carrega acao STALE (P5 'não re-adicionar') que a tabela derivada já superou ([W] 2026-07-14) — --atualizar preserva acao e o map-check não cruza acao com a tabela"
  - "R-2: prototipo_nota do pageheader diz 'os 8 arquivos desta pasta saíram' no 9da73296d3 — são 7 (git show --name-status)"
  - "R-3: docblock de resolverArquivoVivo: headline '21 iguais, mudam só 2' CONFIRMADA; a decomposição '18 sob Pages/, 3 sem campo' está errada (16 sob Pages/ + 2 com campo sem match + 3 sem campo)"
---

# Refutação GT-G5 · rodada 2 · lote `claude/gap-md-seis-fundacao-shell-6897`

- **HEAD** `588f19c2f2` · base `80bc4ef8b9` · `git diff 80bc4ef8b9..HEAD -- memory/requisitos prototipo-ui scripts` = 13 arquivos (10 docs + 3 scripts).
- **Sessão fresca**, sem leitura de `memory/sessions/*refutacao*` (nem `-r1`, nem os do #6897). Modelo: Fable 5.1 (tier máximo; gerador desconhecido nesta sessão — se foi Opus/Sonnet, tier superior atendido).
- Clone raso: nenhuma data de `git log` é citada como recibo. `git show <ref>:<path>` rodado com `MSYS_NO_PATHCONV=1`. Temporários em `C:/Users/wagne/AppData/Local/Temp/claude/refut-r2/`.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 = tier máximo)
- [x] Amostra: **100%** anchors (tabelas + 3 map.json + 4 banners) · prosa dos 4 banners lida inteira (100% > 30%; sem amostragem aleatória, logo sem seed)
- [x] Cada item verificado contra o código real no worktree (HEAD = origin/main + lote), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff — 7 padrões, controle positivo por padrão, 0 hits
- [x] `error_rate_pct` = 0,95 < 2
- [ ] Entry no ledger — **não escrita** (fora do mandato desta sessão: "NÃO escreva no ledger")

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| A · `pageheader-canon-v3-gap.md` tabela (12 linhas) + `prototipo_nota` | 33 | 32 | 1 (R-2) | `sed -n` em cada range de `PageHeader.tsx`/`PageHeaderPrimary.tsx`/`index.ts`/`PageHeaderTabs.tsx`/`SubNav.tsx`/`Cliente/Index.tsx`; `wc -l`; `git ls-files`+`grep -rn` p/ `<PageHeaderOverflow>`/`<KpiStripCanon>`; `git show --name-status 9da73296d3` |
| A · `pageheader-canon-v3.map.json` (12 partes: vivo.*, ancora, acao) | 25 | 24 | 1 (R-1) | ranges lidos; `grep -c data-contract` = 0 nos 3 arquivos; regen `--atualizar` (idêntico) **e** regen fresca (sem flag) diffada parte a parte |
| A · Ação × veredito (12 linhas) | 12 | 12 | 0 | prosa §1 P1–P12 (caudas das linhas 61–72), §2 PASSO 2/3, §3; `status:` da ADR 0189 |
| B · `sidebar-v3-unificado-gap.md` tabela (17 linhas) + `prototipo_nota` | 30 | 30 | 0 | `sed -n` em `cockpit.css`/`Sidebar.tsx`/`AppShellV2.tsx`/`shared.ts`/`useSidebarShortcut.ts`; `grep -i buscar\|search\|pinned\|fixados`; contagem de `key:` em `SIDEBAR_GROUPS`; UI-0023 existe |
| B · `sidebar-v3-unificado.map.json` (17 partes) | 35 | 35 | 0 | ranges lidos (endpoints conferidos); `data-contract` = 0; regen `--atualizar` idêntico; regen fresca sem diff de `acao` |
| B · Ação × veredito (17 linhas) | 17 | 17 | 0 | tabela de gaps #1–#17 (col. Risco/Governança), §Ordem sugerida 1–5 + "NÃO fazer", §Veredito |
| C · `ordens-servico-board-gap.md` — âncoras protótipo `oficina-page.jsx` | 24 | 24 | 0 | `sed -n` em cada linha/range citada; `wc -l` = 1296; `git ls-files prototipo-ui/cowork/oficina-print.js` |
| C · idem — âncoras vivo `Board.tsx` + `ServiceOrderRichSheet.tsx` + charters | 42 | 42 | 0 | `sed -n` em cada range; `wc -l` = 1300; `Board.charter.md:5,8,18,56-59`; `Repair/ProducaoOficina/Index.charter.md:14-17` |
| C · citações do charter (Goals/Non-Goals/Anti-hooks) | 10 | 10 | 0 | `grep -n` sublinha/clicáveis/Abas de box/4 views/in-page/Duas portas/Onda 2/lastro/current_stage_id/caçamba |
| C · `ordens-servico-board.map.json` (8 partes duplas + sha) | 25 | 25 | 0 (1 obs.) | ranges dos dois lados; `prototipo_sha` bate na regen; regen fresca difere só no texto de `acao` de `impressao` (mesmo conteúdo — observação, não erro) |
| C · Ação × veredito (8 linhas) | 8 | 8 | 0 | charter Goals/Non-Goals/Anti-hooks + código |
| D · banner `kanban-producao-gap.md` | 7 | 7 | 0 | `ancora.mjs` ×2 (saída completa); charter Repair :14-17; prosa do próprio arquivo (ganhos :118-119, :72, :83); `RECONCILIACAO-os-inventario.md:45`; `gh pr view 6897` |
| D · bloco `Crm/clientes-gap.md` | 10 | 10 | 0 | `Cliente/clientes-gap.md:10` "Gerado 2026-06-30"; `Cliente/Index.charter.md:5-6`; `clientes.map.json` = 7 partes; STALE reproduzido no `--check --strict`; `gh pr view 6893 --json files` contém `clientes-page.jsx`; banner `:14` 2026-08-26; `PARIDADE-area-cliente-…md` existe |
| D · §Reconciliação `Sells/vendas-index-gap.md` | 22 | 22 | 0 | `vendas-gap.md:1-10`; base do arquivo só `id`; `vendas.map.json` 6 partes / 11 reais + 1 TODO (`devolucoes-relatorios.vivo`) / sha `0462e8dec988`; 8 `###` por região; `git grep -l` (listas idênticas); `gerar-contrato.mjs:153-158`; `coleta.mjs:14`; `Sells/Index.tsx:499,725,1074,1465-1469`; `grep -c vd-totalbar` = 0; `Index.charter.md:6-7`; PRs 3343/3451/4087/6897 |
| D · §Âncora `Sells/vendas-create-gap.md` | 8 | 8 | 0 | `ancora.mjs Sells/Create` (saída completa); `Create.charter.md` v2/live sem `related_prototype`/`bundle_source`/`related_us`; `vendas-create-page.jsx:1-3`; `gh pr view 6895` (body cita charter-us-lint/Suporte) |
| E · scripts (`gerar-map.mjs`, `design-code-map-check.mjs`, `.test.mjs`) | 8 | 7 | 1 (R-3) | `--selftest` OK; `.test.mjs` OK; `--check --strict` rc=1 só pelo DRIFT pré-existente em `Cliente/clientes.map.json`; reprodução do docblock com `frontmatterBlock`/`fmVal` do projeto sobre os 23 gap.md da base (e 24 do HEAD) |
| **Total** | **316** | **313** | **3** | |

## REFUTADOS (lista completa, com evidência)

### R-1 — `memory/requisitos/_DesignSystem/pageheader-canon-v3.map.json` · parte `counter-por-tab` · `acao` contradiz a tabela derivada

- **Map (commitado):** `"acao": "Nada — decisão [W] 2026-05-25 registrada (P5: \"não re-adicionar sem sinal\")."`
- **Tabela do gap.md (`pageheader-canon-v3-gap.md:138`):** *"Nada — decisão [W] 2026-07-14 POSTERIOR à prosa re-adicionou o badge (via backend, AP18 respeitado — nunca `rows.filter`); a P5 ("não re-adicionar sem sinal") foi superada por decisão datada. Não reabrir."*
- **Código:** `Cliente/Index.tsx:827` `badge: tabCounts[t.key]` → `PageHeaderTabs.tsx:279-300` renderiza o pill; comentário `:956-959` cita [W] 2026-07-14. Ou seja: a **tabela** está certa e o **map** afirma o oposto (que a P5 segue valendo).
- **Por que passou:** `gerar-map.mjs --atualizar` **preserva** `acao` das partes existentes (o próprio selftest diz "partes preservadas"); a regen fresca (`node prototipo-ui/gerar-map.mjs <gap>` sem flag) produz `acao` = texto da tabela. E `design-code-map-check.mjs` **não** lê a tabela do gap (`grep -n parsePartes\|gap_fonte` = 0 hits) — só valida schema/sha/âncoras. Logo nenhuma máquina do lote pega `acao` stale, e o `_doc` do map ("Gerado … a partir do gap_fonte") + `_nota_mapeamento` ("Status por parte espelha a coluna Ação da tabela derivada") ficam falsos nessa parte.
- **Conserto esperado:** regenerar essa parte (ou editar `acao`) e — fora deste lote — decidir se `--atualizar` deve re-derivar `acao`/`status` quando a tabela mudar.

### R-2 — `pageheader-canon-v3-gap.md` frontmatter `prototipo_nota`: "os 8 arquivos desta pasta saíram nele"

- `git show --name-status --format= 9da73296d3 | grep prototipos/pageheader-canon-v3` = **7** linhas (`3-familias.html`, `README.md`, `SPEC.md`, `b-v2-roxo-kpis.html`, `clientes-filtros-amostra.html`, `diagram.svg`, `index.html`). O commit existe (`git cat-file -t` = commit; 504 arquivos; 57 sob `prototipo-ui/prototipos/`) e a expurgação é real — só a contagem está errada (7, não 8). A frase sobre a sidebar (`visual-source.html` apagado no mesmo commit) confere.

### R-3 — `prototipo-ui/gerar-map.mjs` docblock de `resolverArquivoVivo`: "os outros 21 resolvem igual (**18 sob Pages/**, 3 sem campo)"

- Reprodução com as funções do projeto (`frontmatterBlock`/`fmVal` de `gerar-contrato.mjs`) sobre os **23** `-gap.md` da base `80bc4ef8b9`: `iguais 21 · mudam 2` ✓ (mudam: `pageheader-canon-v3` `Pages/Cliente/Index.tsx → Components/PageHeader/PageHeader.tsx`; `sidebar-v3-unificado` `null → Layouts/AppShellV2.tsx`). Decomposição dos 21: **16** sob `Pages/` + **2** com campo mas sem match nos dois regex (`Atendimento/caixa-unificada-gap.md` → `Modules/Whatsapp/Resources/js/Pages/...`; `Crm/clientes-gap.md` → `resources/js/Pages/Cliente/` diretório) + **3** sem campo (`kanban-producao`, `vendas-create`, `vendas-index`). O headline (FP medido, só 2 mudam) é verdadeiro; o "18 sob Pages/" não é.

## Confirmados com observação

1. **`ordens-servico-board.map.json` · `impressao.acao`** — texto commitado ("o `_pendente_` (fonte do `OficinaPrint`) foi resolvido com helper próprio; fila e OS individual existem") difere do que a regen fresca deriva da tabela (cita `printOficinaFila.ts` e "o arquivo desceu pro espelho"). **Mesmo conteúdo, redação anterior** — mesma mecânica do R-1 (`--atualizar` preserva `acao`), sem contradição factual; não contado como erro.
2. **`sidebar-v3-unificado-gap.md` · "`Sidebar.tsx`: 0 ocorrências de caixa de busca ("Buscar"/`search`)"** — `grep -i search` dá 4 hits (`:14` `FileSearch`, `:16` `Search` import, `:74-75` ícones, `:1249` `<Search>` no item "Central de ajuda" do user menu). São **ícones Lucide**, não caixa de busca — a afirmação substantiva (não há entry de busca no menu) se sustenta; a redação "0 ocorrências de `search`" é imprecisa.
3. **`kanban-producao-gap.md` · "o `gerar-map` avisou na r1 do #6897"** — não verificável sem abrir os logs de refutação (proibido nesta rodada). Não contado.
4. **`pageheader-canon-v3-gap.md` · "slot `below` em `PageHeader.tsx:82` (2026-09-03)"** — a linha `:82` confere; a data não é verificável em clone raso. Não contado como erro.
5. **`sidebar-v3-unificado-gap.md` · parte busca · "§Ordem sugerida 2 (P-M, sem valor)"** — a prosa diz "P-M · Ganho de discoverability barato"; "sem valor" é leitura do agente (= não toca VALOR Tier 0), não citação. Coerente, mas não é frase da prosa.
6. **Regen `--atualizar` idêntica ×3** — prova estabilidade, **não** derivação (a flag preserva tudo). A prova de derivação veio da regen fresca, que revelou R-1.
7. **`--check --strict` rc=1** — único DRIFT é `Cliente/clientes.map.json` STALE (`8f284ad79fb3` → `2be4c00c452a`), pré-existente (`git diff 80bc4ef8b9..HEAD -- memory/requisitos/Cliente/` vazio), causado pelo #6893 como o bloco do Crm descreve.

## Scan PII (linhas adicionadas em `memory/requisitos`, 741 linhas)

| Padrão | Regex | Controle positivo (string fictícia fora do repo) | Hits no diff |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 1 | **0** |
| CPF cru (11 dígitos) | `(^\|[^0-9])[0-9]{11}([^0-9]\|$)` | 1 | **0** |
| CNPJ pontuado | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 1 | **0** |
| CNPJ cru (14 dígitos) | `(^\|[^0-9])[0-9]{14}([^0-9]\|$)` | 1 | **0** |
| Telefone BR | `(\(?0?[1-9]{2}\)?[ -]?9?[0-9]{4}-[0-9]{4})\|(\+55[ -]?[0-9]{2}[ -]?9?[0-9]{4}[ -]?[0-9]{4})` | 1 | **0** |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 1 | **0** |
| Valor em reais | `R\$ ?[0-9]` | 1 | **0** |

Controle: `ctrl.txt` com CPF/CNPJ (pontuado e cru), 2 telefones, 1 e-mail, valor em reais — cada regex casou 1× no controle e 0× no diff.

## Recibos dos scripts

- `node prototipo-ui/gerar-map.mjs --selftest` → `SELFTEST OK`, rc=0
- `node scripts/governance/design-code-map-check.test.mjs` → `SELFTEST OK`, rc=0
- `node scripts/governance/design-code-map-check.mjs --check --strict` → 20 maps · 20/20 cobertas · 4 `map_json: n/a` listados (Crm, kanban, vendas-create, vendas-index) · `[DRIFT] 1` (Cliente, pré-existente) · rc=1
- `ancora.mjs` (`--staging prototipo-ui/cowork`): `Repair/ProducaoOficina` → `repair-page.jsx` (+ `n/a herda PT-05`) · `OficinaAuto/ServiceOrders/Board` → `oficina-page.jsx` · `Cliente/Index` → `clientes-page.jsx` (charter + bundle) · `Sells/Index` → `vendas-page.jsx` (charter + bundle; frescor sem veredito novo) · `Sells/Create` → "charter sem related_prototype nem -page.jsx"
- PRs: #6897 · #6893 · #6895 · #3343 · #3451 · #4087 — todos existem e estão MERGED (`gh pr view --json title,state`).

## Veredito

```json
{"itens_verificados": 316, "erros_confirmados": 3, "error_rate_pct": 0.95, "pii_hits": 0, "veredito": "aprovado"}
```
