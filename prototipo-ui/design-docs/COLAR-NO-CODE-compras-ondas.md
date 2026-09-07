# COLAR NO CODE — Compras (`Modules/Compras` + core `/purchases`) · doc único · 2026-09-04

> **Resposta curta: 0 arquivos de export de tela.** A migração do Compras está **concluída no `main`** (`migracao_ui: "concluido — 0 Blade servido"`, lido hoje no SCOPE) e o único 🟠 que o FRESCOR apontava — a **grade tam×cor** — **já está implementado** (charter v2 de `Purchase/Create`, "F3 implementado + modo grade tam×cor", aguardando só smoke/canary seu). O que resta são **8 arquivos destravados** (trio furado + contratos + rede) e **1 a 11 travados** em 2 decisões suas. Contagem no bloco 6.
> **Ponte, não canon.** Não escrevo no git: desce por `cowork-inbox`/Issue → PR, ou [W] cola 1×. Este é o **doc único do módulo** — próximas ondas reescrevem este arquivo (anti-scatter §2-ter; não havia `COLAR-NO-CODE-*compras*` antes).

---

## Arquivos lidos no `main` NESTE turno (4 + 2 árvores)

| # | arquivo | o que me disse |
|---|---|---|
| 1 | **`memory/requisitos/Compras/SCOPE.md`** | **`migracao_ui: "concluido — 0 Blade servido"`** · missão: *"Cockpit de **leitura** de compras (`/compras`): lista, KPIs e drawer de detalhe sobre `transactions type=purchase`. **Complementa — não substitui** — o CRUD `/purchases` do core, pra onde delega criar, editar e excluir"* · `permission_prefix: compras.*` **futuro** (hoje usa `purchase.*` legacy) · Compras **não tem tabela própria** (reusa `transactions` + `transaction_lines`) · **Wave 3 (TODO): rota `/compras/create`** declarada como ghost no sidebar v3 |
| 2 | **`Modules/Compras/Routes/web.php`** | só **2 rotas operacionais**: `GET /compras` (`compras.index`) e `GET /compras/{id}/detalhe` (`compras.show`), ambas com `throttle:60,1` + stack UltimatePOS + `CheckUserLogin`; o comentário no topo diz literalmente que **create/store/edit/update/destroy + importar-dfe ficaram para as Waves 3 e 6** |
| 3 | **`resources/js/Pages/Purchase/Create.charter.md`** (v2, `last_validated: 2026-06-22`) | **`status_note: "F3 implementado + modo grade tam×cor (US-COM-005, aguarda smoke/canary Wagner)"`** · Non-Goal explícito: **"NÃO nasce `Pages/Compras/Create.tsx` — a grade vive aqui (convergência C1)"** · a grade faz **1 POST único de N `purchase_lines`** (1 célula = 1 `variation_id`) · reusa `App\Variation`/`App\ProductVariation` + `ProductUtil::createOrUpdatePurchaseLines` **reais** · R-PUR-001..004 (Tier 0, `permitted_locations`, `purchase.create`, estoque só após `received`) |
| 4 | **`memory/requisitos/Compras/SUPERFICIE.md`** (gerado por máquina) | inventário: 3 controllers · 1 Request · 1 Service · **1 tela** (`Compras/Index.tsx`) · 3 componentes · **1 charter · 1 casos** · **10 Pest**. Total 28 arquivos |
| 5 | árvore **`resources/js/Pages/{Compras,Purchase}/**`** | `Compras/`: `Index.tsx` (27.188 B) + `Index.charter.md` (12.050 B) + `Index.casos.md` (25.000 B) + `components/{AcoesDropdown,Drawer,VisibilidadeColunas}.tsx` · `Purchase/`: `{Index,Create,Edit,Show}.tsx` + **4 charters** e **0 `casos.md`** + `_components/{GradeMatrixInput,GradeProductCombobox}.tsx` |
| 6 | busca dirigida `GradeMatrixInput\|grade_matrix` em `Pages/**` | 4 ocorrências, **todas no `Compras/Index.charter.md`** declarando que a grade **não** mora no cockpit. *(o retorno avisou que o scan foi **bounded** — a ausência em outros arquivos não é prova)* |

**Ancoragem dupla:** alvo de layout = protótipo medido (bloco 3); âncora de implementação = os arquivos acima. **A produção está à frente**: cockpit com trio completo, 4 telas de CRUD com charter, grade implementada e 10 Pest.

---

## 0 · Leis que não se renegociam

1. **Compras é cockpit de LEITURA.** Criar/editar/excluir é `/purchases` (core). Está escrito no SCOPE e é a razão de o módulo ter 2 rotas.
2. **Convergência C1:** a grade tam×cor vive em `Pages/Purchase/Create.tsx`. **`Pages/Compras/Create.tsx` é Non-Goal declarado** no charter.
3. **`GradeMatrixInput` não é órfão mais** — o charter v2 declara o modo grade implementado. O 🟠 do FRESCOR de 23/06 está **vencido**; o que falta é **smoke/canary de [W]**, não código.
4. **Sem tabela própria:** tudo em `transactions`/`transaction_lines` com `business_id` via `Transaction::auth_scope()` (Tier 0, ADR 0093).
5. **Estoque só entra após `received`** (R-PUR-004) — nenhuma tela pode sugerir o contrário.
6. **Permissão hoje é `purchase.*`** (o `compras.*` é futuro): não inventar alias `compras.create` (o charter do cockpit chama isso de C1 explicitamente).
7. **Autoridade de token:** `TabBar` do DS → protótipo → produção. Medido: `NAV.ds-tabbar.jm-tabs` com **3 abas**, **3 de 3 com estado ARIA**. Zero cor crua.

---

## 1 · Ordem das frentes + âncora (MAPA do protótipo colhido do DOM)

Raiz: `DIV.compras-root.mp-page` → header (2) · `NAV.ds-tabbar.jm-tabs` (**3**) · `.mp-body` (6) · `.cmp-main` (3). T1 **estável 1245 nós**.

| # | aba do protótipo | medido | âncora no `main` | frescor | vira pedido? |
|---|---|---|---|---|---|
| — | **Painel** | `.mp-body` com 6 filhos (KPIs + painéis) | `Compras/Index.tsx` (cockpit com KPIs) + charter (12 KB) + casos (25 KB) | 🔵 **à frente** (trio completo) | ❌ |
| — | **Pedidos** (7) | `.cmp-main` (3) · `table.purchases` **9 colunas** · `SortTh` com `aria-sort` + `<button>` interno · 34 botões | `Compras/Index.tsx` + `components/{Drawer,AcoesDropdown,VisibilidadeColunas}.tsx` | 🔵 à frente | ❌ |
| **1** | **Fornecedores** (4) | aba própria no protótipo | **sem receptor**: não existe `Pages/Fornecedor*`; fornecedor é `contacts type=supplier` | 🟠/⚪ | ⛔ **[W]** |
| — | **Grade tam×cor** (`compras-grade-matrix.jsx`) | 12 `th` (matriz) | `Purchase/_components/GradeMatrixInput.tsx` (14.229 B) + `GradeProductCombobox.tsx`, **plugado** em `Purchase/Create.tsx` | 🔵 **à frente** — falta **seu smoke/canary** | ❌ |
| **2** | — | — | **trio furado**: `Purchase/{Index,Create,Edit,Show}` têm charter e **0 `casos.md`** | 🟠 | ✅ |
| **3** | — | — | **0 contratos** de tela: nenhum `compras-*`/`purchase-*` em `prototipo-ui/contrato/` (26 arquivos, lidos no ciclo anterior) | 🟠 | ✅ |
| **4** | — | — | **rede**: 10 Pest existem; E2E/VRT **não verifiquei** | ? | ✅ (medir primeiro) |
| **5** | — | — | **ghost `/compras/create`** no sidebar v3 × Non-Goal do `Purchase/Create` | ⚠️ **conflito de canon** | ⛔ **[W]** |

---

## 1-bis · Instrução de execução (as duas primeiras, sem trava)

```
FRENTE 2 — casos.md das 4 telas de Purchase (o trio furado)
  ARQUIVOS A EDITAR   : resources/js/Pages/Purchase/Index.casos.md    (CRIAR)
                        resources/js/Pages/Purchase/Create.casos.md   (CRIAR)
                        resources/js/Pages/Purchase/Edit.casos.md     (CRIAR)
                        resources/js/Pages/Purchase/Show.casos.md     (CRIAR)
  REUSAR (não recriar): o formato do Compras/Index.casos.md que JÁ existe (25 KB, o maior do módulo)
                        os 10 Pest de Modules/Compras/Tests/Feature/** — cada UC cita um teste
                        REAL (PurchaseCalculoValorEstoqueE2ETest, MultiTenantTest,
                        ComprasContratoFiltrosTest, GapsHardeningTest…)
  CRIAR               : só os 4 .md. Zero .tsx, zero controller.
  NÃO TOCAR           : Compras/Index.{tsx,charter.md,casos.md} (trio completo — não mexer)
                        Purchase/*.tsx · GradeMatrixInput · GradeProductCombobox
                        Modules/Compras/** (nenhuma linha nesta frente)
  PASSO A PASSO       : 1) 1 PR por tela (4 PRs) ou 1 PR por par leitura/escrita — nunca big-bang
                        2) UC só do que JÁ está implementado (UC órfão quebra casos-gate G-2)
                        3) rodar casos:results pra o manifesto refletir o status real
  DADO                : nenhum novo.
  PARAR SE            : (a) o UC não tiver teste que o prove → NÃO inventar assertion; declarar
                            no PR como pendência (proibicoes §5: UC órfão bloqueia quem implementa)
                        (b) a US estiver em backlog 🔒 → não escrever caso

FRENTE 3 — contratos de tela (ADR 0286)
  ARQUIVOS A EDITAR   : prototipo-ui/contrato/compras-cockpit.contract.json     (CRIAR)
                        prototipo-ui/contrato/purchase-create.contract.json     (CRIAR)
  REUSAR              : contract.schema.json + o formato de ponto-painel/ponto-espelho
                        as âncoras data-contract que as telas JÁ tiverem
  NÃO TOCAR           : as telas (o contrato descreve, não altera)
  PASSO A PASSO       : 1) declarar seções + copy literal + estados · 2) rodar advisory
                        3) só promover a required após 3 execuções verdes
  PARAR SE            : a tela não tiver âncora data-contract → o contrato vira advisory e a
                            âncora entra num PR próprio de 1 arquivo (não inventar seletor)
```

---

## 2 · Onda 0a — a11y do ALVO (o que falhou foi corrigido AQUI)

Bateria no protótipo servido, dark, após estabilizar (**T1 estável 1245**; T5 de sanidade: `BUTTON` com `cursor: pointer` ✔).

| # | item | medido | veredito | ação |
|---|---|---|---|---|
| A1 | falso interativo | **0** — e o `SortTh` do módulo é exemplar: `<th aria-sort={...}>` com **`<button type="button">` dentro** | ✅ | **este módulo faz melhor que a grade do DS** (que expõe `TH` clicável sem semântica em CRM/Repair/HRM/Patrimônio) |
| A3 | ícone sem nome | **0 de 24** | ✅ | — |
| A5 | ARIA nas abas | **3 de 3** | ✅ | — |
| A7 | alvo <24px | **1 de 34** | ⚪ | decisão [W] (a mesma dos outros módulos) |
| A10 | `aria-live` | **1** | ✅ | — |
| — | `th scope` | **0 de 9** no cockpit · **0 de 12** na grade tam×cor | 🔴 → ✅ | **corrigido no build**: **21 `th`** ganharam `scope="col"` (`compras-page.jsx` 9 · `compras-grade-matrix.jsx` 12). Na matriz isso importa em dobro — cabeçalho de tamanho e de cor são os dois eixos de leitura |
| — | campo sem rótulo | **1 de 2** — a busca do header (`.mp-busca input`) | 🔴 → ✅ | **corrigido no build**: `aria-label="Buscar NF-e, fornecedor ou chave de acesso"` + `aria-hidden` no glifo `⌕` |

**Build alterado neste ciclo:** `compras-page.jsx` · `compras-grade-matrix.jsx` · `oimpresso.com.html` (bump `?v=cmp9a11y`; o `compras-grade-matrix.jsx` **não tinha `?v=`** — ganhou um, que é a convenção do host). Zero mudança de layout.

---

## 3 · ALVO medido por seção (read-only, dark)

| seção | alvo |
|---|---|
| abas do módulo | `NAV.ds-tabbar.jm-tabs` — **3**: Painel · Pedidos (7) · Fornecedores (4), com contador mono; **3 de 3** com `aria-selected` |
| **Painel** | `.mp-body` com **6 filhos** (KPIs + painéis) |
| **Pedidos** | `.cmp-main` com **3 filhos** · `table.purchases` com **9 colunas** (Ação · Compra · Fornecedor · … · Total · A pagar · NF-e), colunas ligáveis por `VisibilidadeColunas` · `SortTh` com `aria-sort` e botão interno · 34 botões · 1245 nós na view |
| **detalhe (drawer)** | `table.items-tbl` — Produto · Qtd · Custo unit. · Total · Venda · **Margem** (6 colunas; a coluna **Margem** é cálculo do protótipo — ver bloco 7) |
| **Grade tam×cor** | matriz com **12 `th`** (eixos tamanho × cor) — o alvo aqui é **referência histórica**: a produção já implementou (`GradeMatrixInput.tsx`) |

---

## 4 · Comportamento + invariantes

1. **Cockpit não escreve.** Ação de criar/editar/excluir **navega** para `/purchases` — nunca muta em `/compras`.
2. **Estoque entra só quando `received`** (R-PUR-004).
3. **Grade:** 1 célula = 1 `variation_id`; 1 POST único de N `purchase_lines`; catálogo sem variação composta cai para grade de **1 eixo** (auto-detect no backend) — **nunca grade vazia silenciosa**.
4. **`permitted_locations` filtra as filiais visíveis** (R-PUR-002).
5. **`purchase.create` obrigatória**, senão 403 (R-PUR-003) — e **sem alias `compras.create`**.
6. Ordenação e filtro são de servidor; preferência de colunas é do cliente (`VisibilidadeColunas`).
7. Sem número inventado: sem fonte ⇒ `—` + linha no PR.

---

## 5 · Não inventar

- **Componentes:** `AppShellV2` · `@/Components/ui` · e os 3 componentes que **já existem** no cockpit (`Drawer`, `AcoesDropdown`, `VisibilidadeColunas`) + os 2 da grade (`GradeMatrixInput`, `GradeProductCombobox`). Reusar, nunca recriar.
- **Serviço/dados:** `ComprasService` + `ListarComprasRequest` (existem) · `transactions`/`transaction_lines` · `App\Variation`/`App\ProductVariation` · `ProductUtil::createOrUpdatePurchaseLines`. **Nenhum Model novo.**
- **Tokens:** accent roxo, zero hex cru.
- **Copy:** PT-BR, sentence case, `NF-e` com o casing legal.

---

## 6 · DoD + PLACAR + **contagem de arquivos**

### PLACAR Compras — 2026-09-04

```
migracao_ui .......................... concluído — 0 Blade servido   ✅ (SCOPE, lido hoje)
Cockpit /compras ..................... Index.tsx + charter + casos + 3 componentes  ✅ trio completo
CRUD /purchases ...................... 4 telas + 4 charters + 2 componentes de grade
casos.md do Purchase ................. 0 de 4                        ← trio furado
Grade tam×cor (o único 🟠 do FRESCOR) . IMPLEMENTADA · aguarda smoke/canary [W]
Contratos de tela .................... 0
Pest ................................. 10
E2E / VRT ............................ não verifiquei
Export de .jsx do protótipo .......... 0  (produção à frente em todas as telas com receptor)
Aba sem receptor ..................... 1  (Fornecedores)
Conflito de canon .................... 1  (ghost /compras/create × Non-Goal do Purchase/Create)
```

### Quantos arquivos o Code precisa (a resposta)

| frente | novos | editados | total | trava |
|---|---:|---:|---:|---|
| 2 · `casos.md` de `Purchase/{Index,Create,Edit,Show}` | 4 | 0 | **4** | 🟢 |
| 3 · 2 contratos de tela (`compras-cockpit`, `purchase-create`) | 2 | 0 | **2** | 🟢 |
| 4 · rede (1 spec E2E + 1 baseline VRT do cockpit) | 2 | 0 | **2** | 🟢 (medir antes) |
| 5 · **ghost `/compras/create`** — se [W] **remover**: `DataController` (Fase 4 ADR 0180) | 0 | 1 | **1** | ⛔ **[W]** |
| 5' · se [W] **criar** `/compras/create`: Page + charter + casos + rota + controller + contrato | 5 | 1 | **6** | ⛔ **[W]** |
| 1 · **Fornecedores**: Page + charter + casos + controller/rota + contrato | 4 | 1 | **5** | ⛔ **[W]** |
| **total (cenário enxuto)** | **8** | **2** | **10** | 2 travados |
| **total (cenário máximo)** | **17** | **2** | **19** | 11 travados |

**8 arquivos destravados hoje** (frentes 2, 3 e 4) — e **nenhum deles é tela**: é trio, contrato e rede. O resto depende de duas respostas suas.
**Margem declarada:** a frente 4 pode ser **0** se o repo já tiver E2E/VRT de Compras — **não verifiquei**; a primeira tarefa é rodar `screen-coverage`/`migracao:report` e ler a linha do módulo.

**DoD por PR:** ≤8 arquivos · ≤~350 linhas · 1 assunto · `casos.md` com UC que a lane executa (nunca órfão) · placar no corpo do PR · lanes required verdes.

---

## 7 · O que a ancoragem NÃO resolve

| # | item | natureza | dono |
|---|---|---|---|
| 1 | **Conflito de canon:** o SCOPE lista "Wave 3 (TODO): rota `/compras/create`" e o sidebar v3 já declara o **ghost**; o charter do `Purchase/Create` diz **"NÃO nasce `Pages/Compras/Create.tsx`"** (convergência C1). Ghost apontando para rota que o canon proíbe = link morto ou tela proibida | **decisão [W]** | **[W]** |
| 2 | **Aba "Fornecedores" do meu protótipo não tem receptor**: fornecedor é `contacts type=supplier`, e não existe Page de fornecedor. Pode ser (a) uma view do cadastro de Cliente/contatos, (b) tela própria, (c) Non-Goal escrito | superfície sem receptor | **[W]** |
| 3 | **A grade espera VOCÊ, não o Code:** `status_note` do charter v2 diz "aguarda smoke/canary Wagner". Nenhum arquivo destrava isso — é aprovação de screenshot ([W2]) | gate humano | **[W]** |
| 4 | **Coluna "Margem" no drawer do protótipo** — margem = (venda − custo)/venda é conta derivada; **não verifiquei** se o `ComprasService` a entrega. Se não entrega, a coluna sai da tela ou nasce com fonte declarada. **Não inventar cálculo na UI** | dado não verificado | frente 2 |
| 5 | **Permissão em transição:** SCOPE declara `compras.*` como **futuro**; o código usa `purchase.*`. Qualquer tela nova herda `purchase.*` até haver ADR | governança | [W] |
| 6 | **Importar XML DFE** aparece como Wave A/6 no SCOPE — não há rota nem tela. Não estava no meu protótipo e **não entra** nesta contagem | escopo | [W] |
| 7 | **Grade do DS** (`th scope`, `TH` ordenável sem semântica): **5º módulo** com o mesmo achado. Aqui o meu `SortTh` já é a referência certa — vale portar esse padrão pro DS | dívida do DS | pedido DS próprio |
| 8 | Zero `<main>` no documento do protótipo (AP9) · rota do `app.jsx` sem componente (C6) | fundação / cobertura declarada | fundação |

---

## 8 · Não medido, declarado

- **Não verifiquei** o corpo de: `Compras/Index.tsx` (27 KB) · `Index.charter.md` (12 KB, só 4 linhas via busca) · `Index.casos.md` (25 KB) · os 3 componentes do cockpit · `Purchase/{Index,Create,Edit,Show}.tsx` · `GradeMatrixInput.tsx` · `GradeProductCombobox.tsx` · `ComprasController` · `ComprasService` (13 KB) · `ListarComprasRequest` · `DataController` (o ghost do sidebar) · os **10 Pest** · `SPEC.md` (41 KB) · `BRIEFING.md` · os 2 AUDIT/AUDITORIA · `compras-gap.md` · `compras-grade-matrix-gap.md` · `SDD-tela-cockpit-compras-v1.0.md`.
- **A busca por `GradeMatrixInput` voltou bounded** (325 de 400 arquivos, orçamento de 10 s): as 4 ocorrências que vi estão todas no charter do cockpit; **não afirmo** que o `Purchase/Create.tsx` importa o componente — o que afirmo é o que o **charter v2 declara** ("modo grade tam×cor usa `GradeMatrixInput`"). Confirmar lendo o `.tsx` é a primeira linha da frente 2.
- **E2E/VRT do módulo:** não verifiquei (a frente 4 existe para medir, não para supor).
- **Contraste (A8):** não medido (exige OKLCH→sRGB com caso de sanidade).
- **Largura:** medido em ~841px (janela do preview), não nos 1280px da Larissa — e este é o módulo cuja persona piloto é **exatamente** ela.

---

## 9 · Recibo

- **Build alterado (só a11y):** `compras-page.jsx` · `compras-grade-matrix.jsx` · `oimpresso.com.html`.
- **Ponte:** este arquivo — doc único do Compras.
- **Charter/casos:** nada a destilar do meu lado; o cockpit já tem trio no canon e as 4 telas de Purchase recebem `casos.md` no PR delas (nunca em lote).
- **Pacote (regra de saída):** **não regenerado** — o gerador exige os arquivos em disco e não roda do meu lado (ADR 0374). O ciclo fecha **sem pacote**:

  ```
  node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
  ```

---

## RESÍDUO Compras — fila de decisão de [W]

1. **Ghost `/compras/create`: remove ou cria?** O canon se contradiz hoje (SCOPE Wave 3 × Non-Goal C1). Remover custa **1 arquivo**; criar custa **6** — e contraria o charter vigente.
2. **Fornecedores é tela?** Se sim, onde: aba do cadastro de contatos ou módulo próprio (5 arquivos)? Se não, vira Non-Goal escrito no charter do cockpit.
3. **Smoke/canary da grade tam×cor** (US-COM-005, biz=4 Larissa): você aprova por screenshot? É o último passo do único gap que o FRESCOR apontava — e não depende do Code.
4. **Alvo de toque em 1280 denso:** mínimo WCAG 24×24 ou exceção declarada? (pendente também em CRM, Repair, HRM, Ponto e Patrimônio — uma resposta serve para o ERP todo).
