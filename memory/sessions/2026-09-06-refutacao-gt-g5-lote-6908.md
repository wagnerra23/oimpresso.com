---
date: "2026-09-06"
topic: "Refutação GT-G5 — lote PR #6908 (7 gap.md de paridade Repair + 7 map.json + 7 compared): 121 itens, 2 refutados, PII 0 — aprovado"
authors: ["C"]
prs: [6908]
---

# Refutação GT-G5 — lote PR #6908 (Repair × `repair-page.jsx`, modo PARIDADE)

> Sessão fresca, sem contexto do gerador. Mandato: provar que o lote está errado, buscando evidência no código real — nunca no texto do PR. Base medida: `merge-base origin/main HEAD` = `80bc4ef8b9` (o `origin/main` local já está em `29fdc3e0f0`; conferido por `git diff --name-only 80bc4ef8b9 HEAD` que **nenhum** arquivo-fonte citado pelo lote — `resources/js/Pages/Repair/**`, `Modules/Repair/**`, `prototipo-ui/cowork/repair-page.jsx`, `repair-data.jsx`, scripts — mudou entre a base e o HEAD, logo o worktree é fonte válida). HEAD do lote: `136cd66fa9`. `git rev-parse --is-shallow-repository` = `false`.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador; não abri nenhum `*refutacao*` anterior — o único `ls` foi para não colidir o nome deste arquivo)
- [x] Modelo de tier superior/igual ao teto de política (refutador Fable; gerador declarado no PR — não verificado por mim, é campo do ledger)
- [x] Amostra: 100% (tipo `anchors` — todas as 46 linhas, os 7 maps, os 7 frontmatters, os 7 registros `compared`)
- [x] Cada item verificado contra o código real (`sed -n`/`nl -ba` no arquivo e linha citados), não contra o diff
- [x] Cada REFUTADO com evidência (path + linha + porquê) e correção sugerida
- [x] Scan PII nas linhas `+` do diff em `memory/requisitos` (935 linhas) — 7 padrões, cada um com controle positivo sintético — 0 hits
- [x] `error_rate_pct` calculado: 1,65% (< 2)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não é minha**; fica para o dono do PR

## Lote medido

`git diff --name-status origin/main...HEAD` — 17 arquivos:

| Grupo | Arquivos | Linhas |
|---|---|---|
| 7 `memory/requisitos/Repair/repair-<tela>-gap.md` (A) | dashboard 19 · jobsheet 24 · index 21 · status 19 · device-models 19 · producao-oficina 21 · settings 22 | 145 |
| 7 `memory/requisitos/Repair/repair-<tela>.map.json` (A) | 89 · 149 · 119 · 89 · 89 · 119 · 135 | 789 |
| `memory/requisitos/Cliente/clientes.map.json` (M) | só `prototipo_sha: 8f284ad79fb3 → 2be4c00c452a` | 1/1 |
| `scripts/design-sync/state/applications.json` (M) | +7 registros `repair-page.jsx` → 7 targets Repair | +91 |
| `scripts/design-sync/state/application-report.json` (M) | 7 entradas `anchored → compared`; contadores `anchored 62→55`, `compared 4→11` | +101/−31 |

Linhas de tabela por gap.md: dashboard 5 · jobsheet 9 · index 7 · status 5 · device-models 5 · producao-oficina 7 · settings 8 = **46** (bate com o enunciado).

## Resultado por grupo

| Grupo | O que | Itens | Confirmados | Refutados |
|---|---|---|---|---|
| 1 | Âncoras dos 7 map.json (arquivo proto existe · arquivo vivo existe · `prototipo_sha` = regen · `acao`/`_acionavel`/`partes[].id` = regen · `mapping.source/target` coerente · `ancora.mjs` consistente) — 6 checks × 7 | 42 | 42 | 0 |
| 2 | Frontmatter dos 7 gap.md (`tela_viva` e `prototipo` existem e são lidos por `fmVal`/`parsePartes` via `gerar-contrato.mjs`) — 2 × 7 | 14 | 14 | 0 |
| 3 | As 46 linhas de tabela (citações `arquivo:linha`, veredito × descrição, Decidir × charter/casos, Nada × gap real, Blade, controller) | 46 | 44 | **2** |
| 4 | `--mark-compared`: 7 registros (source/target/map/`mapSha256`/`targetSha256`/`sourceSha256`) + `status.mjs --check-lifecycle … --minimum compared` rc 0 | 8 | 8 | 0 |
| 5 | Derivados: `design-code-map-check --check --strict` · `requisitos-status Repair --check` · `doc-id-index --check-collisions` · `plans-index --check` | 4 | 4 | 0 |
| 6 | Scan PII — 7 padrões com controle positivo | 7 | 7 | 0 |
| **Total** | | **121** | **119** | **2** |

### Grupo 1 — detalhe

- Regenerei os 7 esqueletos com `node prototipo-ui/gerar-map.mjs <gap.md>` (stdout, sem escrever no repo) e comparei chave a chave com um script (`cmp.mjs`, 279 asserts): `tela`, `gap_fonte`, `prototipo_sha` (`sha256:548d060ec551` nas 7 — o regen dá o mesmo), `partes.length`, `partes[].id` (ordem inclusive), `partes[].acao` (texto idêntico), `partes[]._acionavel`, `prototipo.arquivo`/`vivo.arquivo` (iguais ao regen **e** existem no disco), e que nenhuma parte ficou em `TODO`/`pendente-mapeamento`. **279/279 iguais.** Chaves de topo que só existem no commitado: `mapping` (nas 7 — vem do `--mark-compared`, que o `gerar-map` preserva por desenho, teste "MORDE: chave de topo desconhecida … sobrevive ao --atualizar") e `_nota_ancora` (só no Settings).
- `mapping.target` == `vivo.arquivo` de todas as partes, nas 7; `mapping.source` == `repair-page.jsx` nas 7.
- `ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` nas 7 (rc 0 em todas):
  - Dashboard, JobSheet, Status → `âncora ✓ [-page.jsx (bundle · bundle_source)] repair-page.jsx`.
  - Index, DeviceModels, ProducaoOficina → imprime **os dois**: `sem âncora: n/a (herda PT-0X …)` (declaração legítima) **e** `âncora ✓ [bundle_source] repair-page.jsx` — o charter das 3 tem `related_prototype: n/a` **e** `bundle_source: repair-page.jsx`.
  - Settings → só `sem âncora: "n/a (herda PT-02 Form …) — repair-page.jsx … é porte REVERSO … ancorar aqui seria ancorar a tela nela mesma (§5 2026-08-28)"`; **não** há `bundle_source`. O gap e o map do Settings carregam a nota de que medem paridade e **não** legitimam o mockup como fonte visual. Julgamento: **honesto** — (a) as 8 linhas do Settings são `Nada` (nenhuma pede que a tela se pareça com o mockup); (b) o par `repair-page.jsx → Settings/Index.tsx` **já existia** no `application-report.json` da base `80bc4ef8b9` como `anchored` (mapping `charter.component`, linha 3180 do arquivo na base) — o lote não criou esse par, só o moveu a `compared`; (c) a nota está no lugar certo (frontmatter do gap + chave de topo do map) e não em prosa solta. Não é âncora revogada disfarçada.

### Grupo 2 — detalhe

`node prototipo-ui/gerar-contrato.mjs <gap.md>` nas 7: `fonte` = `prototipo-ui/cowork/repair-page.jsx` (via `fmVal(fm,'prototipo')`), `alvo` = pasta da tela (via `pagesPath(fmVal(fm,'tela_viva'))`), `tela` = campo `tela:`. `parsePartes` leu as tabelas: seções acionáveis 2/5 · 4/9 · 2/7 · 1/5 · 1/5 · 2/7 · 0/8 — bate exatamente com a contagem de `_acionavel: true` de cada map.

### Grupo 3 — detalhe por tela (46 linhas)

Legenda: OK = todas as citações da linha abrem no que a célula diz e o veredito não contradiz charter/casos/Blade/controller. Cito só o que exigiu prova além do óbvio.

**Repair/Dashboard/Index (5 linhas) — 5 OK.**
- Header: `Index.tsx:41-45` PageHeader sem `action` ✓; `:225` AppShellV2 ✓; `repair-page.jsx:693-706` shell ✓; `Index.charter.md:43` CRUD Non-Goal ✓.
- Alerta vencida: `grep -ic vencid` = 0 e `grep -c "<Alert"` = 0 no `.tsx` (225 ln) ✓; `DashboardController.php:79-81` só `total_repairs`/`service_staff_count` ✓; `repair-page.jsx:54-58` banner ✓; `dashboard/index.blade.php:19-99` = status (19-43) · equipe (44-74) · tendências (75-104) ✓; charter `:47` drilldown ✓.
- KPIs: `Index.tsx:47-55` ✓; `total_repairs = count($job_sheets_by_status)` (`:80`) ✓; `Index.casos.md:40-52` UC-RDSH-02 ✓; mockup `:59-67` ✓; charter Goals `:30-31` ✓.
- Barras: `Index.tsx:58-66` e `:68-79` ✓ (`BarChartCard` corta em 10, `:175`); mockup `:68-91` ✓ (ver Observação 3).
- Tendências: `:81-93`, `:95-107`, `:110-122` ✓; mockup `:92-99` ✓; `DashboardController.php:86` `'trending_devices_chart' => []` ✓; `Index.casos.md:54-75` UC-RDSH-03 ✓.

**Repair/JobSheet/Index (9 linhas) — 9 OK.** Tela sem `Index.casos.md` (confirmado: `ls` só lista `Index.charter.md`/`Index.tsx`); charter v3 com Non-Goals `:51-55`, UX Target `:65`, anti-hook `:93`, nota `:26-27` ✓.
- Header `:99-108` ✓ · mockup `:705` ✓.
- Abas: dropdown `:113` ✓; params `:70-73` só `location_id/status_id/contact_id` ✓; `JobSheetController.php:155-160` (`=== '1'` senão pendentes) ✓; `:135-160` nenhum filtro por `delivery_date` ✓; `TabBar` `:211-222` ✓.
- Filtros: `:111-127` 3 `FilterSelect` + Limpar ✓; aviso `:128-132` ✓ ↔ mockup `:223` ✓; `filters.service_staffs` `:31` ✓ e `JobSheetController.php:315` ✓; `technician` `:146` ✓; nenhum `Input` importado (`:8-20`, grep `Input` = 0) ✓; busca do shell `:698-703` e filtro `:161-165` ✓; técnico `:225-226` ✓.
- Tabela: 6 colunas `:164-169` ✓; ponto `bg-primary` `:187` ✓; `status_color` `:50` e select `JobSheetController.php:115` ✓; `flags.show_serial_no` `:35` ✓ (não usado: 0 ocorrências fora da interface); `rawColumns` `:288` inclui `delivery_date`, `estimated_cost`, `service_type` ✓ (`editColumn` `:240-259`); mockup 11 colunas `:171-181` ✓; Blade `job_sheet/index.blade.php:77/80/97/98` serviço/prazo/série/custo ✓; select `:115` sem `current_stage_id` ✓; `FsmStepper` mockup `:191` ✓.
- Ações por linha: link `:178` ✓; mockup `:196-204` ✓; Blade `:75` Ação ✓; `Routes/web.php:32` `job-sheet/print/{id}` ✓; charter `:51-52`, `:54`, `:93` ✓.
- Lote: `grep -ic checkbox` = 0 ✓; `BulkBar` `:246-253` ✓, `LoteModal` `:579-602` ✓.
- Paginação: `length: '200'` `:70` ✓; rodapé `:204-206` ✓; charter `:26-27` ✓; `Pagination` `:243-244` ✓.
- Drawer: `Show.charter.md`/`Show.casos.md` existem em `JobSheet/` ✓; `FolhaDrawer` `:428-527` ✓.
- Estados: skeleton `:136-141` ✓; `EmptyState` `:142-159` ✓; mockup `:237-238` e `:720-727` ✓.

**Repair/Index (7 linhas) — 7 OK.**
- Header `:192-205`, `/sells/create?sub_type=repair` `:199`, `permissions.create` `:197` ✓; mockup `:705`, `:297` ✓; UC-RIDX-04 ✓.
- KPIs `:208-232` ✓ (ver Observação 4); mockup `:286-292` e `:290` ✓; charter `:33` ✓; BACKLOG do casos `:93-95` ✓.
- Filtros `:235-325` ✓; `RepairController.php:555-560` ✓; aba Reparos sem filtro `:258-300` ✓.
- Tabela: 10 `<th>` `:345-354` ✓; `warranty_name` tipado `:32`, não renderizado (0 ocorrências no JSX) ✓; select Inertia `:515-538` **sem** `rjs`/`brand`/`total_paid`/`added_by` ✓ e **com** `rw.name as warranty_name` `:536` ✓; ramo AJAX `:172-186` (ver Observação 1); mockup 8 colunas `:267-274` ✓; Blade `repair/index.blade.php:74/76/80/85` ✓; charter `:45` e `:74` ✓.
- Ações: link `:364` ✓; `Repair/Show.tsx` + `Show.charter.md` + `Show.casos.md` existem ✓; mockup `:295` ✓; Blade `:63` ✓; charter `:42-43`, `:74` ✓.
- Paginação `:404-443` ✓; mockup `:293-296` ✓.
- Estados `:329-340` ✓; UC-RIDX-02 ✓.

**Repair/Status/Index (5 linhas) — 4 OK · 1 REFUTADO.**
- Header `:29-41` ✓; `RepairStatusController.php:44` gate ✓; UC-RSTIDX-06 ✓; mockup `:330-331` ✓ (`:307-310` — ver Observação 2).
- Lista `:50-97` ✓; `orderBy('sort_order')` `:76-78` ✓; UC-RSTIDX-01/02 ✓; mockup `:314-325` ✓; Blade `status/index.blade.php:11-14` nome/cor/ordem/ação ✓.
- Metadados: **REFUTADO (R-1)** — ver lista. O resto da linha confere: `Index.tsx:14-20` interface com os 5 campos ✓; `sms_template`/`email_subject`/`email_body` nas migrations `2020_07_11_120308` (`:18`) e `2020_08_22_104640` (`:17-18`) e em `RepairStatus.php:28-30` ✓; mockup `:319-321` ✓; Non-Goals `:40-44` não vetam ✓.
- Alerta de exclusão: `grep -ic "excluir\|delete\|destroy"` = 0 no `.tsx` ✓; charter `:61` ✓; mockup `:327-329` ✓.
- Vazio `:43-48` ✓.

**Repair/DeviceModels/Index (5 linhas) — 5 OK.**
- Header `:138-150` ✓; mockup `:357-359` ✓.
- KPIs `:152-158` ✓; UC-DMIDX-05 ✓.
- Filtros `:160-214` + `localStorage` `:49,93-106` ✓; UC-DMIDX-01/04 ✓.
- Tabela `:228-275` ✓; badge `:255-265` ✓; `has_checklist` `DeviceModelController.php:158` ✓ e `repair_checklist` selecionado `:150` ✓; mockup `split("|")` `:351` e contagem `:353` ✓; Blade `device_model/index.blade.php:12` ✓; `editColumn('repair_checklist')` `:97-104` (citado 97-100, contém o `editColumn` e o `explode`) ✓; charter Non-Goals `:42-45` não vetam ✓.
- Vazio `:217-226` ✓.

**Repair/ProducaoOficina/Index (7 linhas) — 6 OK · 1 REFUTADO.**
- Cabeçalho: sem `PageHeader` (0 ocorrências) ✓; badge mock `:222-229` ✓; contadores `:230-243` ✓; mockup `:112-114`, `:139` ✓.
- Filtros `:201-244` ✓; UC-RPOE-03 ✓.
- Colunas: `:248-271`, `:359-372` ✓; mockup `:117-135` ✓; **REFUTADO (R-2)** na citação do `repair-data.jsx` — ver lista.
- Card `:417-474` ✓; `ProducaoOficinaController.php:412-425` (`code` 412, `wait` 419, `slot`/`area` null 420-421, `pending_approval: false` 422) ✓; mockup `TaskCard` `:124-133` traz `module: f.cliente`, `due`, `isOverdue`, `f.defeitos[0]` ✓; `JobSheetController.php:419` traz `contact_id`, `delivery_date`, `defects` ✓; charter Non-Goals `:60-66` ✓.
- DnD `:136-165` ✓; `Routes/web.php:44` ✓; mockup `:109-121`, `:658-663` ✓; UC-RPOE-06 ✓.
- Drawer `:477-578` ✓; `VendaDerivadaCard` `:528-530` ✓; sintoma fixo `:533-536` ✓; fotos `:539-550` ✓; itens com preços literais `:552-565` ✓; timeline `:567-574` ✓; banner `:505-519` com "enviado há 2h via WhatsApp" fixo e `<button>` sem `onClick` ✓; mockup `:479`, `:490-498`, `:500-512`, `:515-524` ✓; charter Goal `:38` ✓.
- Modais: `grep -c "<form\|Modal\|Dialog"` = 0 ✓; UC-RPOE-04 ✓; mockup `:529-630` ✓; charter `:93` ✓.

**Repair/Settings/Index (8 linhas) — 8 OK.**
- Header `:187-190` ✓; mockup `<h3>` `:378/387/397/407` ✓.
- Folha `:200-321` (prefixo 203, status 212, produto readOnly 232, barcode 236/255, 4 textareas 276-309, checklist 312) ✓; mockup `:380-384` ✓; UC-RSET-01 ✓.
- Campos personalizados `:324-344` (5 inputs, cada um lendo `job_sheet_custom_field_${n}`) ✓; mockup `:399-401` ✓.
- Impressão `:357-442` (17 chaves = 2+5+4+2+4 em `GRUPOS_IMPRESSAO` `:72-115`; 3 rótulos `:370-391`; largura/altura `:394-409`; 2º endpoint `:360`) ✓; UC-RSET-06 ✓.
- Aviso: link `/repair/status` `:450-452` ✓; mockup `:403-405` ✓.
- Permissões: nenhum painel (0 ocorrências de `PERMISS`/`papel`) ✓; charter `:43` ✓; UC-RSET-04 ✓; mockup `:407-419` ✓; `repair-data.jsx` `PERMISSOES` `:137-152`, `PAPEIS` `:154-160`, `can` `:161` — citado 137-161 ✓.
- Salvar `:346-353` e `:437-441` ✓; mockup `:421-422` ✓; anti-hook `:51` ✓.
- Atalhos `:445-457` ✓; mockup `:709-718` ✓; charter Goal `:34`, Non-Goal `:41` ✓.

### Grupo 4 — detalhe

Os 7 registros novos em `applications.json` (linhas 178-268 do arquivo): `source: repair-page.jsx`; `target` = cada `Index.tsx` das 7 telas; `comparison.map` = o map da tela correspondente. Recalculei: `sourceSha256` = sha256 real de `prototipo-ui/cowork/repair-page.jsx` (7/7 OK) · `targetSha256` = sha256 real de cada `.tsx` (7/7 OK) · `mapSha256` = sha256 real de cada map commitado (7/7 OK — inclusive o Settings, cujo `_nota_ancora` já estava no arquivo quando o recibo foi gravado). `tests: []` / `smokes: []` (coerente com `--minimum compared`). `node scripts/design-sync/status.mjs --check-lifecycle --source repair-page.jsx --minimum compared` → rc 0; `--check-mapping` → rc 0. O `application-report.json` regenerado é coerente: as 7 entradas Repair passam `anchored → compared`, `nextAction` muda para "aplicar no alvo e registrar evidência durável", contadores `anchored 62→55` / `compared 4→11` (delta 7).

### Grupo 5 — detalhe

- `design-code-map-check --check --strict` → rc 0: 24 map.json, "nenhum map.json com âncora quebrada ou sha stale".
- `requisitos-status Repair --check` → rc 0 (`_STATUS-GENERATED.md` em dia).
- `doc-id-index --check-collisions` → rc 0 (0 colisão em 2602 ids — os 7 ids novos `requisitos-repair-repair-*-gap` não colidem).
- `plans-index --check` → rc 0.
- Adicional: `git status --short` limpo após todas as regenerações (nada do que rodei escreveu no repo).

## REFUTADOS (lista completa)

**R-1 · `repair-status-gap.md:17` (linha "Metadados por status")** — cita `RepairStatusController.php:74-78` para "A prop `statuses` traz só `id`/`name`/`color`/`sort_order`/`is_completed_status`". Medido com `nl -ba`: `:74` `if (mwartEnabled…)`, `:75` `return Inertia::render(`, `:76` `RepairStatus::where(…)`, `:77-78` os dois `orderBy`; a lista de colunas está em **`:79`** (`->get(['id', 'name', 'color', 'sort_order', 'is_completed_status'])`), fora do intervalo. O fato é verdadeiro (e a 2ª âncora, `Index.tsx:14-20`, o prova), mas a citação do controller não contém o que a célula atribui a ela. **Correção:** `RepairStatusController.php:74-79` (ou só `:79`). Propaga para `repair-status.map.json` parte `metadados-por-status` (campo `acao` é texto idêntico ao gap — regenerar com `--atualizar` após corrigir o gap).

**R-2 · `repair-producao-oficina-gap.md:17` (linha "Colunas do kanban")** — cita "`COLUNAS` em repair-data.jsx:41-47". Medido: `const COLUNAS = [` está em **`:39`**, as 5 entradas em `:40-44`, `];` em `:45`; `:46` é linha em branco e `:47` é o comentário `// FSM canônica do Repair …`. O intervalo citado começa 2 linhas depois do início (perde a declaração e a coluna `recepcao`) e termina em 2 linhas que não pertencem ao símbolo. **Correção:** `repair-data.jsx:39-45`. Propaga para `repair-producao-oficina.map.json` parte `colunas-do-kanban` (`acao`).

Nenhuma linha foi refutada por veredito (Ação copiando descrição), por `**Decidir.**` reabrindo Non-Goal/Anti-hook/UC fechado, por `Nada` escondendo gap não referenciado, nem por alegação falsa sobre Blade ou controller.

## Observações (não contadas)

1. **`repair-index-gap.md:18`** — "`total_paid`/saldo … colunas que o ramo AJAX do Blade seleciona (`RepairController.php:172-186`)": `warranty_name` (172), `device_model` (181), `brand` (182), `added_by` (184), `job_sheet_no`/`job_sheet_id` (185-186) estão no intervalo; `total_paid` está em **`:168`**, 4 linhas antes. Imprecisão de intervalo que não muda o veredito (5 dos 6 nomes estão lá e o select Inertia `:515-538` de fato não traz nenhum deles). Sugestão: `:168-186`.
2. **`repair-status-gap.md:15`** — "título/descrição … (`repair-page.jsx:307-310`)": o `<h3>Status do reparo</h3>` está em `:310`, mas a descrição (`<p>Cada status carrega cor, ordem…`) está em **`:311`**. Sugestão: `:310-311`.
3. **`repair-dashboard-gap.md:18`** — "As barras do mockup são clicáveis (`onIr`, `:68-91`)": só as barras **por status** são `<button onClick={() => onIr(...)}>` (`:73`); as barras **por técnico** são `<div className="rep-bar plain">` sem handler (`:84-88`). Generalização; o veredito (drilldown é Non-Goal `:47`, conteúdo coberto) não muda.
4. **`repair-index-gap.md:16`** — "3 cards clicáveis Em andamento/Concluídas/Total exibido, que alternam o filtro `is_completed`": `Total exibido` (`Index.tsx:225-231`) **não** tem `onClick`; só os dois primeiros alternam o filtro. Descrição ligeiramente inflada; o `Decidir` segue válido.
5. **Aviso do `gerar-map` nas 3 telas com `related_prototype: n/a` + `bundle_source`** (Index, DeviceModels, ProducaoOficina): o cross-check em `gerar-map.mjs:329-337` compara o 1º arquivo do `prototipo:` do gap com `r.ancoras.map(a => a.valor)` — que, nessas 3, é a string `n/a (…)`, e por isso imprime "âncora computada do charter não cita repair-page.jsx". O `ancora.mjs` da mesma tela, porém, resolve `âncora ✓ [bundle_source] repair-page.jsx`. É uma inconsistência **da ferramenta** (o cross-check não olha o bundle), não do lote; os 3 gaps não mencionam o aviso, e não precisavam (o Settings menciona porque lá não há `bundle_source` algum).
6. **Fora do lote, medido de passagem:** o vivo `JobSheet/Index.tsx` nunca envia `is_completed_status`, e o endpoint (`JobSheetController.php:155-162`) cai no ramo "pendentes" por padrão — logo a tela lista **só OS não-concluídas**, apesar de o dropdown dizer "Todos os status". O gap (linha "Abas de estado") deixa isso implícito ("a tela não o envia"); vale explicitar quando [W] decidir as abas.
7. `repair-settings-gap.md:16` diz que no mockup os 3 campos da folha são "todos `readOnly`": o `Select` de status padrão (`repair-page.jsx:382`) não tem `readOnly`, tem `onChange={() => {}}` — inerte na prática. Estilo, não erro.
8. O enunciado da refutação diz base `origin/main = 80bc4ef8b9`; no clone o `origin/main` está em `29fdc3e0f0` (4 merges à frente). O `merge-base` é `80bc4ef8b9` e nenhum arquivo citado difere entre os dois — registrado para que a próxima rodada não tropece no número.
9. Nota de processo desta rodada: a 1ª versão deste arquivo foi barrada pelo hook `block-brl-values-in-memory` porque o **controle positivo** do padrão "valor monetário" estava escrito literalmente na tabela abaixo. Corrigi descrevendo o formato em vez de gravar o literal — vale para CPF/CNPJ/telefone também, que um scanner leria como hit. Os controles rodaram com literais só na memória do processo (`pii.mjs` no scratchpad), nunca em arquivo do repo.

## Scan PII

Universo: 935 linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` (os 7 gap.md, os 7 map.json e a linha do `clientes.map.json`). Cada padrão foi testado antes numa linha sintética (controle positivo, gerada e descartada no scratchpad — não reproduzida aqui) para provar que a regex casa.

| Padrão | Regex | Hits | Controle positivo (formato da linha sintética) |
|---|---|---|---|
| CPF pontuado | `\b\d{3}\.\d{3}\.\d{3}-\d{2}\b` | 0 | OK — três blocos de 3 dígitos com ponto + hífen + 2 dígitos |
| CPF cru 11 dígitos | `(?<!\d)\d{11}(?!\d)` | 0 | OK — sequência isolada de 11 dígitos |
| CNPJ | `\b\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}\b` | 0 | OK — 2.3.3/4-2 dígitos |
| Telefone BR | `(?:\(\d{2}\)\s?\|\b\d{2}\s)9?\d{4}-\d{4}\b` | 0 | OK — DDD entre parênteses + 9 dígitos com hífen |
| E-mail | `[\w.+-]+@[\w-]+\.[\w.-]+` | 0 | OK — usuário arroba domínio ponto tld |
| Valor monetário | `R\$\s?\d` | 0 | OK — sigla da moeda seguida de dígito |
| Nomes de cliente do CRM | `Larissa\|Martinho\|Daniela\|Anderson Prado\|Wagner Rocha\|Larissa Nunes\|ROTA LIVRE` (i) | 0 | OK — frase contendo um dos nomes |

Nota: os nomes `Larissa Nunes`/`Anderson Prado`/`Wagner Rocha` existem em `prototipo-ui/cowork/repair-data.jsx:155-158` (dado mock do protótipo, fora do diff); o lote **não** os copiou.

## Veredito

121 itens verificados · 2 erros confirmados (ambos citação de intervalo de linha em célula "Estado no vivo", com o fato subjacente verdadeiro e a correção trivial) · 0 PII · todos os derivados rc 0 · 7 maps idênticos ao regen nas chaves de conteúdo · 7 recibos `compared` com shas batendo. `error_rate = 2/121 = 1,65% < 2%`.

Recomendação ao dono do PR: corrigir R-1 e R-2 no gap.md e regenerar os 2 maps com `gerar-map.mjs --atualizar` (o `mapSha256` do recibo `compared` muda; refazer o `--mark-compared` dos 2) **antes** do merge — são 4 caracteres, e deixar linha errada em canon é o que a lápide §5 2026-07-15 chama de instrução ativa. Opcionalmente absorver as observações 1-4.

`{"itens_verificados": 121, "erros_confirmados": 2, "error_rate_pct": 1.65, "pii_hits": 0, "veredito": "aprovado"}`
