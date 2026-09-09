# COLAR NO CODE — EXPORT `Relatorios` (pacote em ondas · 10 blocos)

> **Leitura do `main` feita NESTE turno** (2026-09-05, árvore `ed21b17a8bb8`) — 4 arquivos da âncora, integrais:
> 1. `resources/js/Pages/Financeiro/Relatorios/Index.tsx`
> 2. `resources/js/Pages/Ponto/Relatorios/Index.tsx`
> 3. `resources/js/Pages/Financeiro/Relatorios/Index.charter.md`
> 4. `resources/js/Pages/Ponto/Relatorios/Index.charter.md`
> Mais, no mesmo turno, **por listagem/grep (não integral)**: árvore `resources/views/report/` (27 views + 23 partials) e o bloco de rotas `routes/web.php:833–874` + `:1075` (41 GET em `App\Http\Controllers\ReportController`).
> Tudo o que não está acima = **não verifiquei** (bloco 8).

## Resposta curta — "Relatórios é módulo?" e quantos arquivos

**Não é módulo nWidart e não é família Inertia.** No `main` de hoje "Relatórios" é:
- **41 rotas GET** em `App\Http\Controllers\ReportController` (`/reports/*`, `routes/web.php:833–874` + `reports/activity-log:1075`) servindo **27 Blade views + 23 partials** em `resources/views/report/` — legado UltimatePOS, sem `Pages/Relatorios/`;
- **duas telas Inertia homônimas que são de OUTROS módulos**: `/financeiro/relatorios` (Fluxo + Resumo) e `/ponto/relatorios` (catálogo de documentos). Nenhuma das duas é o índice geral.
- `app.jsx` deste protótipo já declara `relatorios` como `phase 5 · status "later"`. Ou seja: **exportar Relatórios = migração Blade→Inertia**, não "portar um módulo".

**Contagem de arquivos (front + ponte, sem Blade legado):**

| escopo | novos | editados |
|---|---|---|
| **ONDA 1 — índice** (mínimo que sobe sozinho) | **6** | 2 |
| **Módulo completo (ONDAS 1–7)** | **24** | 2 |

Os 24: `Pages/Relatorios/Index.tsx` · `Show.tsx` · `_shared/RelatoriosSubNav.tsx` · `_shared/catalogo.ts` (tipos+índice) + `_shared/catalogo/<grupo>.ts` ×6 · `Index.charter.md` + `Index.casos.md` + `Show.charter.md` + `Show.casos.md` · `prototipo-ui/contrato/relatorios.index.contract.json` + `relatorios.show.contract.json` · `App/Http/Controllers/RelatoriosInertiaController.php` · 7 testes (índice, contrato do Show, 5 de grupo).
Os 2 editados: `routes/web.php` (grupo Inertia novo, **sem tocar nas 41 rotas legadas**) e o nav do shell (arquivo **não verifiquei** — ver bloco 7/8).

---

## 0 · Leis DESTE módulo

constituição: `CONSTITUICAO-COWORK.md` (C1–C13) + `memory/proibicoes.md` — **citadas, não copiadas**. Abaixo, só o que é lei DESTE módulo.

- Relatório é **100% leitura**. Nenhuma rota nova escreve; **nenhuma mutação em GET**.
- **C1 aplicada aqui, com medição:** o banner de `/financeiro/relatorios` tem `oklch(...)` **literal em `style={{}}`** (lido no bloco DRE) — é o `main` **ATRÁS** do DS: não copiar esse padrão, e não vira alvo.
- **Granularidade deste módulo (C6):** onda = **grupo do catálogo**; página única → onda = seção.

## 1 · Ordem das ondas + âncora por onda

| onda | escopo | âncora de implementação no `main` | lida hoje? |
|---|---|---|---|
| **0a** | a11y do alvo (bloco 2) — **corrige aqui, não vira pedido** | — | — |
| **1** | Índice/catálogo: 6 grupos, 27 cards, filtros globais | `Pages/Ponto/Relatorios/Index.tsx` (grade de cards por categoria, `<Label htmlFor>`, badge Disponível/Em breve, `hrefGerar`) | ✅ integral |
| **2** | Casca do relatório: header + período + abas + tabela + rodapé de totais + export CSV | `Pages/Financeiro/Relatorios/Index.tsx` (PageHeader v3.8 + partial reload `only:[...]` + `export-csv` no SubNav overflow) | ✅ integral |
| **3** | Grupo **Financeiro** (6) | idem onda 2 + `ReportController` (**não verifiquei**) | parcial |
| **4** | Grupo **Comercial** (6) | idem | parcial |
| **5** | Grupo **Estoque** (9) | idem | parcial |
| **6** | Grupos **Fiscal** (1) + **Sistema** (1) | idem | parcial |
| **7** | Grupo **Gráfica** (4 · relatórios NOVOS) | **sem receptor** — ver bloco 7 | — |

**Denominador (27 relatórios, o que reprova "faltou"):**
Financeiro 6 — `profit_loss` `purchase_sell` `sell_payment_report` `purchase_payment_report` `expense_report` `register_report` ·
Comercial 6 — `contact` `customer_group` `sale_report` `purchase_report` `sales_representative` `service_staff_report` ·
Estoque 9 — `stock_report` `product_stock_details` `lot_report` `stock_expiry_report` `stock_adjustment_report` `product_purchase_report` `product_sell_report` `items_report` `trending_products` ·
Fiscal 1 — `tax_report` · Sistema 1 — `activity_log` · Gráfica 4 (novos) — `cv_m2` `cv_bobina` `cv_lucro_os` `cv_retrabalho`.
**FORA, com motivo:** `gst_sales_report` · `gst_purchase_report` (fiscal Índia) · `table_report` (Restaurante).

## 1-bis · Instrução de execução (forma padrão §4-ter)

```
ONDA 1 — índice do catálogo
  ARQUIVOS A EDITAR   : routes/web.php (só ADICIONAR grupo Inertia /relatorios*)
  REUSAR (não recriar): Components/PageHeader (canon v3.8) · Components/ui/{card,button,badge,input,select,label}
                        · Layouts/AppShellV2 · padrão de SubNav de Pages/{Ponto,Financeiro}/_shared/*SubNav
  CRIAR               : Pages/Relatorios/Index.tsx · _shared/RelatoriosSubNav.tsx · _shared/catalogo.ts
                        · Index.charter.md · Index.casos.md · contrato/relatorios.index.contract.json
                        · App/Http/Controllers/RelatoriosInertiaController.php (só @index, read-only)
  NÃO TOCAR           : as 41 rotas /reports/* legadas · resources/views/report/** · ReportController
                        · Pages/Financeiro/Relatorios/* · Pages/Ponto/Relatorios/* · cockpit.css/bundle
  PASSO A PASSO       : 1) catalogo.ts (27 defs: id, label, grupo, rota legada, disponivel)
                        2) Index.tsx (h1 + 6 seções H2 c/ contador + cards BUTTON)
                        3) SubNav + rota Inertia + controller @index devolvendo o catálogo
                        4) charter + casos.md com UC citado por teste + contrato json
  DADO                : catálogo é ESTÁTICO no controller (nome/grupo/rota). Nenhum número no índice.
  PARAR SE            : colisão de rota — /reports/* legado responde Blade; se o prefixo Inertia
                        escolhido conflitar, PARAR e perguntar a [W] (bloco 7)
```

## 2 · Onda 0a — a11y do ALVO (medido no protótipo hoje; **corrige no build daqui**)

Sanidade da sonda antes de qualquer veredito: a contagem por `el.onclick` deu **43 "div clicável"** e foi **descartada** — o caso de valor conhecido mostrou `button.onclick === noop` (algo atribui `noop` em massa). Remedi por `role`/`tabindex`.

| # | verificação | índice | tela de relatório (`profit_loss`) | ação |
|---|---|---|---|---|
| A1 | interativo é elemento nativo | ✅ 27 `BUTTON`, 0 `DIV`/`SPAN` com role/tabindex | ✅ 0 não-nativo | — |
| A2 | nome acessível em todo botão | ✅ 0/27 sem nome | ❌ **10/36 sem nome** (ícones anônimos: colunas, kebab, paginação) | `aria-label` no build |
| A3 | rótulo em todo campo | ✅ 1/1 | ❌ **1/12 sem rótulo** | `aria-label`/`htmlFor` |
| A4 | hierarquia de título | ✅ h1 + 6 h2 | ✅ h1 do relatório | — |
| A5 | `th` com `scope` | n/a | ❌ **0/4** com `scope`; **0 `<caption>`** | `scope="col"` + caption sr-only |
| A6 | mudança assíncrona anunciada | n/a | ❌ **0 `aria-live`** (aplicar filtro / carregando) | região polite no build |
| A7 | abas com semântica | n/a | ⚠️ 13 `role=tab` / 3 `role=tablist` — `aria-selected`/`aria-controls` **não medi** | medir na 0a |
| A8 | `tabindex>0` | ✅ 0 | ✅ 0 | — |
| A9 | um `<main>` no documento | ❌ **0 `<main>`** no host (shell, não desta onda) | declarado, não corrigido aqui |
| A10–A12 | contraste · reduced-motion · foco visível | **não medi** | **não medi** | medir na 0a |

**Consequência de protocolo:** A2/A3/A5/A6 **não entram no pedido** — exportar `th` sem `scope` e ícone anônimo é exportar dívida com selo. A **ONDA 1 só abre depois da 0a fechada** no build daqui.

## 3 · ALVO medido por seção (protótipo, `.jc-page.rel-page`)

- **Índice** — 390 nós; `h1 "Relatórios"`; 6 seções `H2` na ordem `Financeiro 6 · Comercial 6 · Estoque 9 · Fiscal 1 · Gráfica 4 · Sistema 1` (rótulo + contador; Gráfica traz a marca "novo — pendente de dado"); card = `BUTTON` com ícone do grupo + label + descrição + rótulo legado; 0 link.
- **Tela de relatório** — `h1` = label do relatório; 36 botões; 12 campos (período De/Até + `Select` de filtro); 1 tabela; abas quando o relatório tem `tabs` (`tax_report` 3 · `sales_representative` 3 · `product_sell_report` 3 · `service_staff_report` 2 · `profit_loss` grupos de lucro).
- **Composição (do build, `relatorios-page.jsx`)**: `PageHeader` · `PeriodBar`+`Select` (Filtros) · menu de **Colunas** (colvis) · `TabBar` · `KpiCard` · `Chart` · `DataTablePro` + `Pagination` + `BulkBar` + `DropdownMenu` (ações por linha) · `Drawer`+`DrawerSection` (detalhe) · `Modal` (editar validade) · `Toast` · `Alert` · `EmptyState` · `Skeleton`.
- **Resumo de duas colunas** (`profit_loss`, `purchase_sell`, `product_stock_details`): entradas × saídas + linha de fecho apurada **das linhas**, nunca digitada.

## 4 · Comportamento + invariantes

| elemento | estados | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|---|
| card do índice (`BUTTON`) | default·hover·focus-visible | clique/`↵` | rota do relatório | não persiste | voltar | teste: 27 cards, 6 grupos na ordem |
| aba (`role=tab`) | +selected | clique/←→ | troca dataset | não persiste | — | `aria-selected` único |
| período (De/Até + presets) | +loading | change/preset | partial reload `only:['filtros','linhas','resumo']` | não persiste | preset volta a `custom` | request tem só as 3 chaves |
| menu Colunas | +open | clique/`esc` | oculta coluna | `localStorage` **chave a declarar por [W]** | clicar desliga | reload mantém |
| ação de linha (kebab) | +open | clique c/ `stopPropagation` | navega | não persiste | `esc` fecha 1 nível | clique no kebab não abre o drawer |
| export CSV | default·loading | clique | download BOM UTF-8 (padrão Financeiro) | — | — | GET não escreve |

Valem as 10 invariantes do §5 do protocolo, com o reforço: **nenhuma escrita nesta migração** (as ações "editar validade"/"editar ajuste" pertencem a Estoque — ver bloco 7).

## 5 · Não inventar

- **Colunas e rótulos**: vêm do catálogo do protótipo (já traduzido do Blade; cada def carrega `blade:` de origem). Não renomear, não reordenar, não somar coluna nova.
- **Formatos**: `BRL` `QTD` `PCT` do build (R$ 1.234,56 · vírgula decimal · `tabular-nums`).
- **Números**: sem fonte ⇒ `—` + linha no PR. KPI e fecho são **apurados das linhas**.
- **CSS/átomos**: reusar `Components/ui/*` e `Components/shared/*` do `main`; não escrever CSS novo de tabela.
- **Copy**: PT-BR do catálogo; não reintroduzir rótulo legado como título ("Compre e venda" é o rótulo antigo, fica só na linha de origem).

## 6 · DoD + PLACAR

DoD por onda: (1) diff ≤300 ln · (2) charter+`casos.md` com ≥1 UC citado por teste, **mesmo PR** · (3) contrato `.contract.json` (ADR 0286) verde no CI · (4) `prototipo-readiness.mjs` ✅ · (5) `cowork-mirror-freshness` + `cowork-ssot-guard` verdes · (6) PLACAR no corpo do PR · (7) bloco de contrato destilado no charter · (8) `github.md` com linha do ciclo. **Nada é "igual ao design" antes do T7** (`design-diff --compare --check` nos dois renders, prod deployada).

```
PLACAR Relatorios (2026-09-05)
  enumerados          27  (23 espelham Blade legado + 4 novos de gráfica)
  com dado no main    23  (85%)      sem dado: 4 (Gráfica — bloco 7)
  fora, com motivo     3  (GST×2, mesas)
  exportados           0  (0%)  — ONDA 0a pendente, nenhuma onda aberta
  arquivos previstos  24 novos + 2 editados   |   ONDA 1: 6 novos + 2 editados
```

## 7 · O que a ancoragem NÃO resolve

- **Dado inexistente (4 relatórios de Gráfica)**: `cv_m2`, `cv_bobina`, `cv_lucro_os`, `cv_retrabalho` exigem m² por OP, hora-máquina, sobra de bobina e motivo de retrabalho. Não achei receptor — **ONDA 7 não abre sem [W] declarar a fonte**.
- **Superfície sem receptor**: não existe `Pages/Relatorios/` nem controller Inertia; o índice geral **não tem receptor hoje** — é criação, e por isso a ONDA 1 é criação de casca, não port.
- **Colisão de rota**: `/reports/*` (41 GET) responde Blade. O prefixo Inertia (`/relatorios` ou `/reports/v2`) é **decisão de [W]**. PARAR SE não estiver declarada.
- **Decisão [W] aberta**: chave de `localStorage` das colunas; se o índice entra no nav do shell e em que grupo; se `/financeiro/relatorios` e `/ponto/relatorios` viram links do índice ou permanecem soltos.
- **Verificação bloqueada**: `ColumnManager`/`ColumnPrefs` existem no espelho do DS; **não verifiquei** equivalente no `main` — se não existir, a coluna persistente sai da onda (não se recria mecanismo).
- **Ações fora do escopo read-only**: `editarValidade` e `editarAjuste` do build são mutações de Estoque. Exportar como botão sem endpoint = afordância falsa (LC-15) → **saem do pedido** ou viram link pra tela de Estoque.

## 8 · Não medido — declarado

Não li neste turno: `App/Http/Controllers/ReportController.php` (13.4 KB) · as 27 Blade views e 23 partials (só a árvore) · `Pages/Financeiro/_shared/FinanceiroSubNav.tsx` e `Pages/Ponto/_shared/PontoSubNav.tsx` · `Components/shared/*` e `Components/ui/*` · `Layouts/AppShellV2.tsx` e o arquivo do nav · `memory/proibicoes.md` · `memory/LICOES_CC.md` · `prototipo-ui/PRE-FLIGHT-TELA.md` · `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` · `COWORK-ESTRUTURA-E-TELAS.md` · `memory/governance/scorecards/screens/{financeiro,ponto}-relatorios-index.yaml` · spec do ADR 0286. A11y A7/A10–A12 do alvo: **não medi**.

## 9 · Recibo

- **Pacote NÃO regenerado** — o gerador exige os arquivos em disco e não roda deste lado (ADR 0374). Comando para [W]/Code:
  `node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json`
  e depois a linha em `github.md`: `bundle regenerado (<data> · N arquivos)`.
- Este ciclo fecha **sem pacote** e **sem escrita no git**: o arquivo vive só neste projeto Cowork.
