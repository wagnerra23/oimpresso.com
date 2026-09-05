> ## Recepção [CL] — 2026-09-04
>
> Pacote colado por [W] e aterrissado por Claude Code. **O corpo abaixo é o registro datado do lado design — não editei um caractere dele.** Este bloco é o único acréscimo, e diz o que eu medi na recepção.
>
> **Base da medição:** `origin/main` no tip `dea7d2c4ef` (2026-09-04 18:08 -0300), buscado neste turno. Nada foi verificado contra working tree.
>
> **Por que caiu aqui e não em `prototipo-ui/` (root), como o cabeçalho pede:** o dono da convenção é [`prototipo-ui/design-docs/CLAUDE.md`](../CLAUDE.md), escrito pelo próprio lado Cowork, e ele declara — *"Intake novo = GitHub Issue (form `cowork-intake`) ou drop em `cowork-inbox/`"*. A raiz de `prototipo-ui/` é fonte/protocolo; `prototipo-ui/cowork/` é **só build** (o `cowork-ssot-guard.mjs` reprova process-doc lá). O nome segue o padrão dos irmãos (`JANA-MODULO-ONDAS-PR-2026-08-09.md`, `COMVIS-REFINOS-PRODUCAO-2026-08-26.md`).
>
> ### Confere (medido, não lido)
>
> | claim do pacote | veredito |
> |---|---|
> | `Pages/Repair/Settings` não existe | ✅ `git ls-tree origin/main resources/js/Pages/Repair/` → 26 arquivos, zero `Settings` |
> | as 6 telas "à frente" têm Page | ✅ Dashboard · ProducaoOficina · JobSheet · Repair/Index · Status · DeviceModels — todas com `.tsx` + `.charter.md` |
> | `index()` ainda devolve Blade | ✅ `RepairSettingsController.php:78` → `view('repair::settings.index')` |
> | anti-scatter (sem `COLAR-NO-CODE-*repair*`, sem `cowork-inbox/PEDIDO-*repair*`) | ✅ varrido no repo inteiro |
> | **Onda 2 gated: ADR × legado divergem** | ✅ **confirmado** — ADR ARQ-0002 linha 18 exige *"número do reparo + telefone/CPF"*; `CustomerRepairStatusController:67-76` aceita **um** `search_type` (`job_sheet_no` \| `invoice_no` \| `mobile_num`) com `serial_no` opcional. Hoje **`mobile_num` sozinho já retorna resultado** — o segundo fator da ADR não existe |
>
> ### ⛔ Corrige — e isto quebraria a Onda 1 como está escrita
>
> O pacote afirma, em dois lugares (`1-bis` e bloco 7 item 2), que `/repair/repair-settings` tem **só `index`+`store`** e manda *"submit no MESMO endpoint `store()`"*. **Há um terceiro endpoint**, e ele é o dono de metade dos dados listados:
>
> | rota | método | coluna que grava |
> |---|---|---|
> | `POST /repair/repair-settings` | `store()` | `business.repair_settings` — prefixo, status padrão, produto padrão, barcode, campos personalizados 1..5, checklist |
> | `POST /repair/update-repair-jobsheet-settings` ([`web.php:40`](../../../Modules/Repair/Routes/web.php)) | `updateJobsheetSettings()` | `business.repair_jobsheet_settings` — os 5 rótulos de etiqueta **e os checkboxes de impressão** |
>
> Uma Page que mandasse tudo pro `store()` deixaria a seção 2 do alvo (*"O que aparece na impressão"*) **inerte** — salva sem erro, não persiste. É a classe LC-30 (correção que passa no CI e é inerte no runtime).
>
> **Segunda armadilha, no mesmo par:** os dois métodos fazem `$request->only([...])` + `Business::update([... => json_encode($input)])` — **sobrescrevem o JSON inteiro**. Submit parcial **apaga** as chaves ausentes. A Page tem que mandar o conjunto completo a cada endpoint, ou a Onda 1 vira perda de dado do cliente.
>
> **Precisão menor:** são **17 `show_*` + 1 `contact_custom_fields`** (18 entradas no array de `updateJobsheetSettings`), não "18 checkboxes `show_*`". O pacote pede pra não inventar chave — então a chave que não começa com `show_` fica nomeada.
>
> ### O que eu NÃO fiz
>
> - **Onda 1 não executada** — este PR é só a ponte (1 PR = 1 intent). A correção acima muda o desenho dela e é insumo pro PR seguinte.
> - **Bundle não regenerado** — resíduo já declarado pelo próprio pacote (§9); o `gerar-payload-partes.mjs` roda do lado que tem os arquivos em disco.
> - **Nada medido em runtime** — sem `design-diff --probe`, sem screenshot, sem 1280px. O DoD item 3 do pacote exige deploy e segue em aberto.
>
> ### Fila de decisão [W] (a do §RESÍDUO, com a minha medição junto)
>
> 1. **Portal público** — vale a ADR ARQ-0002 (dois fatores) ou o legado (um campo)? Medido: hoje o legado permite consultar por telefone sozinho. Enquanto não decidir, Onda 2 não abre.
> 2. **JobSheet troca o motor da tabela?** (DataTables legacy → grade do DS). Onda própria, não catch-up.
> 3. **Alvo de toque <24px em 1280 denso** — mínimo WCAG ou exceção declarada; a resposta vale pro ERP todo (pendente também no CRM e na Forja).

---

# EXPORT Repair (Assistência técnica) — pacote de export · 2026-09-04

> **Ponte, não canon.** Destino no `main`: `prototipo-ui/` (root). Eu não escrevo no git: desce por `cowork-inbox`/Issue → PR, ou [W] cola 1×.
> **Anti-scatter:** o módulo não tinha `COLAR-NO-CODE-*repair*` nem `cowork-inbox/PEDIDO-*repair*` (procurei). Este é o doc único do módulo — próximas ondas **reescrevem este arquivo**, não criam outro.
> **Veredito:** produção está **À FRENTE em 6 das 8 telas** (tem Page + charter, algumas `live`). Essas 6 **não viram pedido**. Sobram **2 ondas reais** — e a Onda 2 tem um **PARAR SE de canon** (ADR × código legado divergem).

---

## Arquivos lidos no `main` NESTE turno (11) — o resto é "não verifiquei"

**Âncora de implementação (código real da produção):**

| # | arquivo | o que me disse |
|---|---|---|
| 1 | `resources/js/Pages/Repair/Index.tsx` | listagem de reparos viva: `PageHeader`+`KpiCard`+chips de status+tabela+paginação Inertia; `applyFilter` com `only:['repairs','filters','meta']` (partial reload D-14) |
| 2 | `resources/js/Pages/Repair/Index.charter.md` | `status: draft`, tier B, `bundle_source: repair-page.jsx`, rota `/repair/repair`, pendências pré-`live` (Non-Goals + paridade de colunas + smoke 1280/1440) |
| 3 | `resources/js/Pages/Repair/JobSheet/Index.tsx` | folhas vivas: 3 `FilterSelect` com `aria-label`, fetch no endpoint DataTables legacy (`datatable_url`), skeleton `aria-busy`, `EmptyState` variant filtro×base |
| 4 | `resources/js/Pages/Repair/JobSheet/Index.charter.md` | **`status: live`**, tier A, 7 Pest GUARDs nomeados (p95, sem e-mail, sem job, sem mutação, tenant, anti-XSS, 1280 sem scroll-x) |
| 5 | `resources/js/Pages/Repair/Dashboard/Index.tsx` | painel vivo com `Deferred` por gráfico + `BarChartCard` **acessível** (role=img, `<title>`, coluna textual, resumo `sr-only`) e `barToneClass` com token semântico ("zero cor crua" escrito no código) |
| 6 | `resources/js/Pages/Repair/DeviceModels/Index.tsx` | modelos vivos: KPIs + filtros com `<label htmlFor>` + `aria-label`, persistência em `localStorage` (`oimpresso.repair.device_models.index.*`), partial reload `only:['models','filters']` |
| 7 | `resources/js/Pages/Repair/Status/Index.tsx` | status vivos: tabela com cor + ordem + flag concluído; `aria-label` na bolinha de cor e no ícone de conclusão |
| 8 | `resources/js/Pages/Repair/ProducaoOficina/Index.charter.md` | kanban 5 colunas **greenfield**, vocabulário **shared** (`code/item/usage_meter/executor/slot/area`), CI guard `repair-shared-vocab.yml` proíbe `placa|vehicle|km|mecanico|box|elevador`, drag-and-drop + `/move`, card de venda derivada (ADR 0192) com badge NF-e |
| 9 | `Modules/Repair/Routes/web.php` | rotas reais: `/repair-status` **público** com `throttle:30,1`; `Route::resource('/repair')` (daí `/repair/repair`); `/repair/repair-settings` **só `index`+`store`**; `/repair/producao-oficina` + `/move`; FSM em `/api/repair/job-sheets/{id}/fsm-actions` |
| 10 | `Modules/Repair/Http/Controllers/RepairSettingsController.php` | **dado real da Onda 1**: `business.repair_settings` (JSON) + `business.repair_jobsheet_settings` (JSON), campos exatos + gate de permissão |
| 11 | `Modules/Repair/Http/Controllers/CustomerRepairStatusController.php` + `memory/requisitos/Repair/adr/arq/0002-portal-publico-consulta-status-sem-login.md` | **dado real da Onda 2** — e a **divergência de canon** do bloco 7 |

---

## 0 · Leis que não se renegociam

1. **A produção é réplica e está À FRENTE em a11y nesta área.** Medido nos arquivos acima: `role="img"`+`<title>`+`sr-only` nos gráficos, `aria-label` em todos os selects de filtro, `<label htmlFor>` em DeviceModels. **Não se pede ao Code que regrida** para a minha versão.
2. **Vocabulário shared é lei de CI** (`repair-shared-vocab.yml` + ADR 0121 §P8): nada de `placa/vehicle/km/mecanico/box/elevador` em `Modules/Repair/**` ou `resources/js/Pages/Repair/**`. Use `code/item/usage_meter/executor/slot/area`.
3. **Portal público é Tier de risco:** `throttle:30,1` por IP (R-REPA-008) e **ADR ARQ-0002** manda mostrar **só status e data estimada — sem preço, sem peças, sem responsável**.
4. **Autoridade de token:** `TabBar` do DS → protótipo → produção. Medido: as 8 abas do protótipo são `NAV.ds-tabbar`, `gap 0px`, **8 de 8 com `aria-selected`**; `--accent` dark resolvido = `oklch(0.70 0.15 295)`. `repair-page.css` não tem cor crua no que o alvo cobre (o único hex do módulo é `--st`, a **cor do status vinda do banco**, `repair_statuses.color` — dado, não decisão de design).
5. **Onda nunca > 1 PR ≤300 linhas**; medir e aplicar são passos separados.

---

## 1 · Ordem das ondas + âncora por onda (MAPA colhido do DOM)

Raiz do módulo no protótipo: `DIV.rep-root.mp-page` → `[header, NAV.ds-tabbar.jm-tabs (8), corpo, DIV.rep-aviso-live]`. T1 estável em 8/8 views (961 · 982 · 1290 · 934 · 885 · 911 · 996 · 850 nós).

| # | rota (host) | view | corpo medido | âncora no `main` | frescor | vira pedido? |
|---|---|---|---|---|---|---|
| — | `repair` | painel | `.mp-body` (1) | `Pages/Repair/Dashboard/Index.tsx` + charter | 🔵 **à frente** (Deferred + gráfico a11y) | ❌ |
| — | `rep-producao` | produção (kanban) | `.mp-body` → `.rep-board-wrap` | `Pages/Repair/ProducaoOficina/Index.tsx` + charter (11 KB) | 🔵 **à frente** (drag+`/move`+venda derivada+NF-e) | ❌ |
| — | `rep-folhas` | folhas | `.rep-list` (4) · 2 tabbars (8+4) · grade DS 12 col | `Pages/Repair/JobSheet/Index.tsx` (**live**, 7 GUARDs) | 🔵 à frente em prova; protótipo à frente em **feature** (ver bloco 7 item 3) | ❌ (só resíduo) |
| — | `rep-reparos` | reparos | `.rep-list` (3) | `Pages/Repair/Index.tsx` + charter (draft) | 🔵 à frente | ❌ |
| — | `rep-status` | status | `.mp-body` (1) → `.rep-cfg` | `Pages/Repair/Status/Index.tsx` | 🔵 à frente | ❌ |
| — | `rep-modelos` | modelos | `.rep-list` (2) | `Pages/Repair/DeviceModels/Index.tsx` (+Create/Edit) | 🔵 à frente | ❌ |
| **1** | `rep-config` | configurações | `.rep-cfg` — **6 filhos** | **sem Page** (`repair::settings.index` Blade, 13.6 KB) | 🟠 **atrás** | ✅ **ONDA 1** |
| **2** | `rep-portal` | portal público | `.rp-wrap` (3) | **sem Page** (`repair::customer_repair.*` Blade) | 🟠 atrás — **mas ⛔ canon** | ⚠️ **ONDA 2 gated** |

**Receita do MAPA (reexecutável, não congelar):** `document.querySelector('.rep-root')` → filhos; corpo = `.mp-body`/`.rep-list`; abas = `.ds-tabbar > *`.

---

## 1-bis · Instrução de execução por onda

```
ONDA 1 — configurações do Repair (.rep-cfg)
  ARQUIVOS A EDITAR   : resources/js/Pages/Repair/Settings/Index.tsx        (CRIAR)
                        Modules/Repair/Http/Controllers/RepairSettingsController.php@index
                          (trocar view('repair::settings.index') por Inertia::render, props abaixo)
  REUSAR (não recriar): @/Layouts/AppShellV2 · @/Components/shared/PageHeader
                        @/Components/ui/{input,select,switch,button,card}
                        o padrão de filtro/persistência de DeviceModels/Index.tsx
                        o gate de permissão que JÁ existe no controller (não reescrever)
  CRIAR               : só a Page. Nenhuma rota nova: /repair/repair-settings (index+store) já existe.
  NÃO TOCAR           : store() e updateJobsheetSettings() (contrato de gravação intacto)
                        o Blade settings/index.blade.php (só deixa de ser renderizado quando a Page passar)
                        Pages/Repair/{JobSheet,ProducaoOficina,Dashboard,DeviceModels,Status}/** (vizinhas)
                        AppShellV2 e a sidebar (fundação)
  PASSO A PASSO       : 1) Inertia::render com as props reais do index() (lista abaixo)
                        2) 5 seções na ORDEM do alvo (bloco 3)
                        3) submit no MESMO endpoint store() (POST /repair/repair-settings)
                        4) casos.md da seção + 1 UC citado por teste, no mesmo PR
  DADO                : business.repair_settings (JSON) → job_sheet_prefix · default_status ·
                        default_product (+ default_product_name resolvido) · barcode_id ·
                        barcode_type · repair_tc_condition · problem_reported_by_customer ·
                        product_condition · product_configuration ·
                        job_sheet_custom_field_1..5 · default_repair_checklist
                        business.repair_jobsheet_settings (JSON) → customer_label · client_id_label ·
                        client_tax_label · label_width · label_height + 18 checkboxes show_*
                        dropdowns: barcode_settings · barcode_types · repair_statuses · brands ·
                        devices · module_category_data
  PARAR SE            : (a) qualquer campo do meu alvo não existir nesses 2 JSON → renderiza "—"
                            e entra no PR como ausência declarada (NÃO inventar chave)
                        (b) a permissão do controller (superadmin OU repair_module+repair.create)
                            não puder ser espelhada na Page → para e pergunta
                        (c) Sem tabela `settings`: tudo é JSON em `business` — não criar migration.

ONDA 2 — portal público de consulta (.rp-wrap)   ⛔ GATED
  ARQUIVOS A EDITAR   : (nada ainda)
  PARAR SE (ativo)    : ADR ARQ-0002 (aceita) manda "número do reparo + telefone/CPF" como
                        verificação. O código legado (CustomerRepairStatusController@postRepairStatus)
                        aceita UM campo: search_type ∈ {job_sheet_no, invoice_no, mobile_num} +
                        search_number, com serial_no OPCIONAL. ADR e código divergem →
                        decisão [W] antes de qualquer Page (bloco 7 item 1).
  QUANDO LIBERAR      : Page pública SEM AppShellV2 (layout próprio, é o
                        repair::layouts.repair_status), mantendo throttle:30,1 na rota.
```

---

## 2 · Onda 0a — a11y do ALVO (o que falhou foi corrigido AQUI)

Bateria no protótipo servido, dark, após `__oiLazyDone`, T1 com duas leituras iguais por view.

| # | item | medido | veredito | ação |
|---|---|---|---|---|
| A1 | falso interativo | **T5 aplicado à sonda** (sanidade `BUTTON`→pointer ✔, origem do pointer, não herança): folhas **7 falsos** = `TH` ordenável · produção **14 falsos** = card do kanban · config e portal **0** | 🟠 **DS** | os 7 `TH` e os 14 cards são markup do DS (`DataTablePro` e `TaskCard`, componentes de estilo inline) → bloco 7, não remendo o bundle |
| A3 | ícone sem nome | folhas 2 de 11 · produção 1 de 10 · config 1 de 10 | 🟠 **DS** | os anônimos estão dentro de `BUTTON < DIV < NAV` (setas da TabBar do DS) e do ícone do `Alert` — o `BUTTON` **tem** nome (`0 de 55` sem nome) |
| A5 | ARIA nas abas | **8 de 8** (e 12 de 12 em folhas, com as sub-abas) | ✅ | TabBar do DS + `window.CliTabs` com `ariaLabel` |
| A7 | alvo <24px | folhas **37 de 55** botões · demais views 1 de 11-13 | ⚪ | **decisão [W]** (ERP denso 1280) — mesma pergunta aberta da Forja e do CRM |
| A10 | `aria-live` | **1** — `DIV.rep-aviso-live` com `role="status" aria-live="polite" aria-atomic="true"` | ✅ | o Repair já resolve o que faltava no CRM |
| A12 | estado vazio | `EmptyState variant="no-results"` + título por filtro | ✅ | — |
| — | campo sem rótulo | 1 por view: a busca do header (`.mp-busca input`) | 🔴 → ✅ | **corrigido no build**: `aria-label="Buscar folha, cliente, série ou modelo"` + `aria-hidden` no glifo `⌕` (`repair-page.jsx`) |
| — | `th scope` / `aria-sort` | 0 de 12 com `scope`; `aria-sort` só na coluna ordenada (1 de 12) | 🟠 **DS** | `aria-sort` só na ativa é correto; `scope` e a semântica do `TH` clicável são do `DataTablePro` → bloco 7 |
| — | **canon do portal** (achado desta rodada) | o protótipo exibia **`Orçamento` em R$** no resultado público | 🔴 → ✅ | **corrigido no build**: ADR ARQ-0002 manda "sem preço" → agora mostra `Situação do orçamento` = *em avaliação* / *aprovado — valor na loja* (`repair-portal.jsx`). Exportar o alvo antigo seria exportar violação de canon |

**Build alterado neste ciclo:** `repair-page.jsx` (rótulo da busca) · `repair-portal.jsx` (canon ADR ARQ-0002) · `oimpresso.com.html` (bump `?v=`). Zero mudança de layout.

---

## 3 · ALVO medido por seção (read-only, dark)

**Shell do módulo:** `DIV.rep-root.mp-page` → 4 filhos **nesta ordem**: header (2) · `NAV.ds-tabbar.jm-tabs` (**8 abas**, `gap 0px`, tag `BUTTON`) · corpo · `DIV.rep-aviso-live` (`role=status`). `--accent` dark = `oklch(0.70 0.15 295)`.

**Onda 1 — `.rep-cfg` (`display:flex`, `gap 22px`, 6 filhos nesta ordem):**

| ordem | seção | conteúdo medido |
|---|---|---|
| 1 | `DIV` (3 filhos) | `H3 "Folha de OS"` (13px) + `<p>` + `.rep-cfg-grid` com **3 campos** (prefixo `readOnly` · status padrão `Select` · produto padrão `readOnly`) |
| 2 | `DIV` (3) | `H3 "O que aparece na impressão"` + `<p>` + `.rep-cfg-switches` (N `.rep-sw`, cada um `Switch` com `label` do rótulo e `sublabel` = "chave <k>") |
| 3 | `DIV` (3) | `H3 "Campos personalizados da folha"` + `<p>` + `.rep-cfg-grid` com **5** `Input` (`job_sheet_custom_field_1..5`), placeholder "sem rótulo — coluna oculta" |
| 4 | `DIV` (2) | `H3 "Permissões deste papel"` + conteúdo |
| 5 | `DIV` (3) | bloco final de configuração |
| 6 | `.rep-cfg-acoes` (2) | `BUTTON "Salvar configurações"` + `SPAN "Permissão: repair_module (assinatura) + admin do negócio"` |

Total medido na view: **16 campos** de formulário · 12 botões · 4 `H3` em 13px.

**Onda 2 — `.rp-wrap` (3 filhos):** cabeçalho (2) · `.rp-frame` → `[.rp-frame-bar (1), .rp-page (1)]` (o quadro que simula "como o cliente vê", 841px na janela medida) · `.rp-cfg` → `[LABEL, BUTTON]`. Campos: `SELECT` (tipo de busca) · `INPUT` nº da folha · `INPUT` série ("Como está na etiqueta") · `checkbox`. Resultado (`.rp-res`): cabeçalho com `mono` do nº + marca/modelo/série + pílula de status usando `--st` (cor do banco) · grid de 4 rótulos · `H4 "Andamento"` + `OL.rp-tl` (timeline de atividades) · nota final citando o número da OS.

---

## 4 · Comportamento + invariantes (Onda 1 — EARS)

| elemento | TAG | estados | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|---|---|
| `Select` "Status padrão da folha" | `SELECT`/DS | default · focus-visible · disabled (sem permissão) | `foco` + escolha | **QUANDO** o usuário escolhe um status **O SISTEMA DEVE** marcar o form como sujo — sem gravar | não persiste até submit | sim (volta ao valor do JSON) | teste: mudar e sair sem salvar não altera `business.repair_settings` |
| `Switch` de impressão (bloco 2) | `BUTTON`/DS | on · off · disabled | `clique` · `tecla espaço` | **QUANDO** alternado **O SISTEMA DEVE** alternar só a chave `show_*` daquele switch | idem | sim | teste por chave: 18 checkboxes do `updateJobsheetSettings` |
| `Input` campo personalizado 1..5 | `INPUT` | vazio (coluna oculta) · preenchido | `digitação` | **QUANDO** preenchido **O SISTEMA DEVE** criar a coluna na listagem de folhas; **QUANDO** vazio, a coluna não existe | idem | sim | teste: rótulo vazio ⇒ coluna ausente na grade |
| `BUTTON "Salvar configurações"` | `BUTTON` | default · loading · disabled sem permissão | `clique` | **QUANDO** clicado **O SISTEMA DEVE** POSTar em `/repair/repair-settings` e voltar com `status.success` | grava os 2 JSON em `business` | não (é escrita) | Pest: 403 sem `repair.create`; 1 update por submit |
| aviso de resultado | `DIV.rep-aviso-live` | — | após submit | **QUANDO** o POST responde **O SISTEMA DEVE** anunciar em `role=status aria-live=polite` | — | — | sonda: nó live existe e recebe texto |

**Invariantes do módulo (valem sem repetir por seção):**
1. Permissão nega antes de renderizar/gravar (`superadmin` OU `repair_module` na assinatura + `repair.create`).
2. Tudo é JSON em `business` — **sem tabela de settings, sem migration**.
3. Vocabulário shared (nada automotivo) — CI reprova.
4. Estado vazio é conteúdo (diz por que e o que fazer).
5. Sem número inventado: sem chave no JSON ⇒ `—` + linha no PR.
6. `focus-visible` accent em tudo clicável.
7. Escrita só por submit explícito — nada grava em GET.

---

## 5 · Não inventar (CSS · componentes · dados · copy)

- **CSS:** na produção, Tailwind + tokens do DS como as 6 Pages irmãs já fazem (`bg-card`, `border-border`, `text-muted-foreground`). **Zero hex cru** — exceção documentada: `repair_statuses.color` é **dado do banco** (a produção já injeta via `style={{backgroundColor}}` em `Status/Index.tsx` e `StatusPill`).
- **Componentes:** `@/Components/ui/*` + `@/Components/shared/{PageHeader,KpiCard,EmptyState,KpiGrid}` (REGISTRY). Nunca hand-roll.
- **Dados:** exatamente os `compact()` do `RepairSettingsController@index` (bloco 1-bis). Nome de chave que não estiver lá **não existe**.
- **Copy:** PT-BR, sentence case, literal do protótipo ("Folha de OS", "O que aparece na impressão", "Campos personalizados da folha", "Salvar configurações"). Sem emoji.

---

## 6 · DoD + PLACAR

**PLACAR Repair — ciclo 2026-09-04:** 8 telas mapeadas · **6 não viram pedido porque a produção está à frente** (Dashboard · ProducaoOficina · JobSheet · Repair/Index · Status · DeviceModels) · **1 onda executável** (config) · **1 onda gated por canon** (portal).
Cobertura pedida: **1 de 8** — e é o número certo: pedir as 6 seria pedir regressão.

**DoD da Onda 1 (recibo do PR):**
1. `.rep-cfg` com **6 seções na ordem** medida; 5 `Input` de campo personalizado; ações no fim com o texto da permissão.
2. Cada linha da tabela de comportamento com o teste que a prova.
3. `design-diff --compare --check` → 0 `DIVERGE(bug)` em `.rep-cfg` (**exige deploy — T7, não afirmável agora**).
4. Screenshot prod autenticado · dark · 1280px.
5. `Settings/Index.casos.md` com ≥1 UC citado por teste — MESMO PR.
6. **PLACAR no corpo do PR.**
7. Bloco de contrato destilado no `Settings/Index.charter.md` — MESMO PR.
8. `github.md`: linha do ciclo + `bundle regenerado (<data> · N arquivos)`.

---

## 7 · O que a ancoragem NÃO resolve

| # | item | natureza | dono |
|---|---|---|---|
| 1 | **ADR ARQ-0002 × código legado divergem no portal**: a ADR (aceita) exige "número **+** telefone/CPF"; o controller aceita **um** campo (`job_sheet_no` OU `invoice_no` OU `mobile_num`) com `serial_no` opcional. Meu protótipo replicou o **código**, não a ADR. Precedência do protocolo (teste > casos > charter > SPEC) não cobre "ADR × legado" | **decisão [W]** | **[W]** — bloqueia a Onda 2 |
| 2 | **`Pages/Repair/Settings` não existe** e `/repair/repair-settings` só tem `index`+`store` — a Page nasce sem receptor de update parcial | superfície a criar | Onda 1 |
| 3 | **Onde o protótipo está à FRENTE (resíduo, não pedido):** minha view `folhas` tem seleção múltipla + `BulkBar` + `Pagination` do DS + sub-abas com contadores; a `JobSheet/Index.tsx` viva é `live` mas ainda busca no **DataTables AJAX legacy** e o próprio charter declara a divergência ("tabela ainda usa DataTables legacy"). **Não vira pedido nesta rodada** (a tela é `live` com 7 GUARDs; trocar o motor é onda própria, ≥300 linhas) | fila de decisão | **[W]** decide se abre a onda "TanStack em JobSheet" |
| 4 | **`DataTablePro` / `TaskCard` do DS**: `TH` ordenável sem `role`/`tabindex` (7 na view folhas), card de kanban `DIV` clicável (14 na produção), `th` sem `scope`, svg do gatilho anônimo. **Mesmo achado do CRM** — é dívida do DS, medida em 2 módulos | dívida do DS | pedido DS próprio |
| 5 | **Arrasto sem alternativa de teclado (A9)** no kanban: `aria-keyshortcuts` = 0 no protótipo. Não medi o `.tsx` da produção (só o charter, que só fala de HTML5 drag) | WCAG 2.5.7 | DS + [W] |
| 6 | **Alvo de toque <24px** — 37 de 55 botões na view folhas | decisão [W] | **[W]** |
| 7 | **Zero `<main>` no documento do protótipo** (AP9 pede um) | fundação (shell) | PR de fundação |
| 8 | **Rota do `app.jsx` sem componente (C6)** | cobertura declarada (§11) | — |

---

## 8 · Não medido, declarado

- **Contraste (A8):** não medido neste módulo (exige conversão OKLCH→sRGB **com caso de sanidade**). Risco nomeado: `.rep-hint` e o cabeçalho de grade do DS em 10px.
- **A2 (foco):** os números de `outline:none` × `:focus-visible` que eu tenho são **globais** do documento, não por seção do Repair. Não é veredito.
- **Largura:** medi em **841px** (janela do preview), não nos 1280px da Larissa — o charter da produção exige 1280 sem scroll-x, e isso **não** foi verificado aqui.
- **Não verifiquei (não lidos neste turno):** `Pages/Repair/ProducaoOficina/Index.tsx` (só o charter) · `JobSheet/{Create,Edit,Show,AddParts}.tsx` · `Repair/Show.tsx` · `DeviceModels/{Create,Edit}.tsx` · `RepairController.php` (64 KB) · `JobSheetController.php` (62 KB) · `ProducaoOficinaController.php` · `RepairFsmActionController.php` · `KanbanProductionService.php` · `settings/index.blade.php` · `customer_repair/repair_details.blade.php` (por isso **não afirmo** se o Blade legado do portal mostra preço — corrigi o meu pela ADR, que é a autoridade) · `memory/requisitos/Repair/{SPEC.md,SPEC-FSM-WIREUP.md,SUPERFICIE.md}` · `LICOES_CC.md` · `proibicoes.md` · `REGISTRY_DS_COMPONENTES.md` · os 13 scorecards `memory/governance/scorecards/screens/repair-*.yaml`.
- **Estado dos gates do `charter-gate.yml`** (soft/enforced) hoje: não verifiquei.

---

## 9 · Recibo

- **Build alterado (a11y + canon, zero layout):** `repair-page.jsx` · `repair-portal.jsx` · `oimpresso.com.html` (bump `?v=`).
- **Ponte:** este arquivo (doc único do módulo — próxima onda reescreve).
- **Charter/casos:** nada a destilar ainda — o bloco de contrato destilado da Onda 1 nasce **no PR da Onda 1**, em `Pages/Repair/Settings/Index.charter.md`. Nunca em lote, nunca retroativo (`casos-gate` G-2).
- **Pacote (regra de saída):** **não regenerado** — o `gerar-payload-partes.mjs` exige os arquivos em disco e não roda do meu lado (transcrição pelo contexto do agente é proibida, ADR 0374). O ciclo fecha **sem pacote**; o comando é:

  ```
  node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
  ```

---

## RESÍDUO Repair — fila de decisão de [W]

1. **Portal público:** vale a ADR ARQ-0002 (número + telefone/CPF) ou o comportamento do legado (um campo)? Sem isso a Onda 2 não abre.
2. **JobSheet: troca o motor da tabela?** A tela viva é `live` com DataTables legacy; meu protótipo tem grade do DS com seleção/BulkBar/paginação. É onda própria, não catch-up.
3. **Alvo de toque em 1280 denso:** mínimo WCAG 24×24 ou exceção declarada? (resposta vale para o ERP todo — pendente também no CRM e na Forja).
