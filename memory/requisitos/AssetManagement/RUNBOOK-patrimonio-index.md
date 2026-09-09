---
title: "RUNBOOK — Patrimonio/Index (Painel do Patrimônio)"
module: AssetManagement
tela: Patrimonio/Index
owner: W
status: ativo
last_validated: "2026-09-08"
preconditions:
  - "Modules/AssetManagement instalado no business (pacote assetmanagement_module)"
  - "Usuário com asset.view (ou superadmin)"
steps:
  - "Medir a âncora do dashboard() antes de editar — o sha muda a cada PR no controller"
  - "Conferir a coluna-fonte de cada KPI: sem fonte, renderiza — (C7)"
  - "A SubNav e da tela de Bens (#7035) — esta tela so a consome com active=dashboard"
  - "Rodar a suíte Asset no CT 100 e ler assertions, não '0 failed'"
related_adrs:
  - 0394-endereco-de-ui-do-patrimonio-pages-patrimonio
  - 0104-processo-mwart-canonico-unico-caminho
  - 0182-pageheadertabs-canon-pattern-telas
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — Patrimonio/Index (Painel do Patrimônio)

> **MWART F1 (PLAN)** da thread 07 do playbook SINCRONIZAR Patrimônio. A thread previa que este
> painel fundasse o `_shared/PatrimonioSubNav.tsx`; ele foi fundado pela tela de Bens, que
> mergeou primeiro ([#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035)). Ver §5.

## 1 · Âncoras (medidas em 2026-09-08, não herdadas)

| o quê | onde | medição |
|---|---|---|
| rota | `Modules/AssetManagement/Routes/web.php:21` | `Route::get('dashboard', …)` — **sem `->name()`** |
| controller | `AssetController::dashboard()` | `:617` (+ os 5 privados que ele chama, até `:788`) · arquivo 35.378 B · sha `ef1ac93dddd6` — pós-merge do #7035, que converteu o arquivo de CRLF para LF |
| fonte visual | `prototipo-ui/cowork/patrimonio-page.jsx` | aba "Painel": `painelData()` `:149`–`:194` · `PatPainel()` `:195`–`:245` |
| padrão | `PT-04 Dashboard` (golden `governance/Dashboard.tsx`) | `status: draft` — ver §7 |

> ⚠️ **A âncora do `07-painel.md` dizia `:467-:520` e sha `3eba5a4faae5`.** Remedida: o
> [PR #7018](https://github.com/wagnerra23/oimpresso.com/pull/7018) (fix do `orWhereNull` que
> escapava do tenant) inseriu 11 linhas de comentário e deslocou a faixa. O método real hoje
> começa em `:436`. Bytes coincidem (24.416) — **bytes iguais não provam conteúdo igual**.

## 2 · O que a tela mostra, e de onde vem cada número

O protótipo pede 4 KPIs, 3 blocos de análise e um "Resumo de hoje". A coluna **fonte** abaixo é
o que decide se o número renderiza ou vira `—` (C7 — número sem fonte não renderiza).

| bloco | fonte medida | veredito |
|---|---|---|
| **Patrimônio bruto** | `SUM(assets.quantity * assets.unit_price)` — as duas colunas existem (`decimal(22,4)`) | ✅ calcula |
| **Valor residual** | exigiria depreciação **calculada** | ❌ **`—`** · ver §3 |
| **Alocados X de Y** | X = `SUM(allocate) − SUM(revoke)` em `asset_transactions`; Y = `SUM(quantity)` dos `is_allocatable=1` | ✅ calcula |
| **Garantia vencida ou vencendo** | `asset_warranties.end_date` vs `CURDATE()` | ✅ calcula |
| **Patrimônio por categoria** | `SUM(quantity*unit_price)` agrupado por `categories.name` | ✅ calcula |
| **Situação da garantia** | 4 baldes: na garantia · vence em ≤30d · vencida · **sem registro** | ✅ calcula |
| **Manutenção em aberto** | `asset_maintenances` com `status NOT IN ('completed','cancelled')` | ✅ lista |
| ↳ **custo** da manutenção | **não há coluna de custo** em `asset_maintenances` | ❌ **`—`** · ver §3 |
| **Resumo de hoje** (prosa) | derivado dos números acima | ⚠️ parcial · ver §4 |

Colunas reais de `assets` (baseline `database/schema/mysql-schema.sql:674`): `id · business_id ·
asset_code · name · quantity · model · serial_no · category_id · location_id · purchase_date ·
purchase_type · unit_price · depreciation · is_allocatable · description · created_by · timestamps`.

### 2.1 · O header também mostra dado — e ele vem do SHELL do protótipo

Dois elementos do header não moram no `patrimonio-page.jsx`: vêm do `MP.Header`
(`modulo-padrao.jsx:18`), que delega pro `CliPageHead`. **Foi por isso que passaram batido** nos
PRs [#7133](https://github.com/wagnerra23/oimpresso.com/pull/7133)/[#7139](https://github.com/wagnerra23/oimpresso.com/pull/7139),
que alinharam o corpo da tela: quem lê só a página do módulo não os enxerga. Ao mexer neste
header, a fonte a abrir são os **três** arquivos, não só o primeiro.

| elemento | fonte do dado | fonte do desenho | veredito |
|---|---|---|---|
| **selo `Atualizado HH:MM`** | prop `apurado_em` (`AssetController:643`, eager) | `patrimonio-page.jsx:821-822` · posição em `cli-pagehead.jsx:159` (1º item de `actions`) | ✅ renderiza · clique = `router.reload()` |
| **linha de contexto** | `business.name` (share eager, `HandleInertiaRequests:85`) + `kpis.totalBens` (deferida) | `patrimonio-page.jsx:820` · `cli-pagehead.jsx:89` (`<p>` irmão acima do header) | ⚠️ parcial — 2 de 3 pedaços |
| ↳ **locais** (3º pedaço) | **não chega a esta página** — 0 ocorrências de local/locais em todo o `share()` | idem | ❌ omitido · ver abaixo |

O pedaço de locais é o único desvio, e a omissão é deliberada: `permitted_locations()` existe no
backend (o índice de Bens o usa, `AssetController:349`) mas **não é filtro deste painel** — as
consultas de `painelKpis()` filtram só `business_id`. Escrever "todos os locais" afirmaria um
escopo de permissão que a tela não aplica; escrever os locais restritos do usuário mentiria sobre
a abrangência de números que somam o business inteiro. Fechar a **R4** do contrato Cowork
(`permitted_locations` filtra lista, contadores e KPIs) nos KPIs vem primeiro — decisão [W].

A contagem de bens vem de prop **deferida**: no 1º paint a linha nasce só com o negócio, e o
pedaço entra quando `kpis` chega. Quem sustenta isso é o `filter` que o protótipo já tem
(`cli-pagehead.jsx:79`) — pedaço vazio **sai** do join, em vez de deixar um ` · ` órfão.

## 3 · Os dois `—`, e por que não invento fórmula

**Valor residual.** A coluna `assets.depreciation` existe e é **gravada**, mas ninguém a calcula:
ela é validada como `['nullable','string']` (`StoreAssetRequest:67`), normalizada por `num_uf` e
relida **só** pelo `edit.blade.php:71`, para repopular o próprio formulário. Zero aritmética no
repo — nenhum `book_value`, nenhum valor residual. E a regra (linear ou SAC, com que fonte
contábil) é **decisão [W] em aberto** — RESÍDUO 6 do playbook, com dono declarado no
`SPEC.md:96` (`US-ASSET-W01`). Renderiza `—`. Recibo: `_saida-04.md §4`.

**Custo de manutenção.** `asset_maintenances` tem `id · business_id · asset_id · maitenance_id ·
status · priority · created_by · assigned_to · details · maintenance_note · timestamps`. **Não há
coluna de valor** — o `additional_cost` mora em `asset_warranties`, que é outra coisa (custo da
garantia). O protótipo mostra um "custo de manutenção no ano"; esse número é do mock. RESÍDUO 3
do playbook (*"custo de manutenção entra (não há coluna)?"*) é [W]. Renderiza `—`.

## 4 · "Resumo de hoje" — o que entra e o que fica de fora

O protótipo escreve duas frases. A **primeira** é derivável (bens, unidades, bruto) menos o valor
residual, que vira `—`. A **segunda** cita `HP Latex`, `VS-640` e um custo por cabeça de
impressão: isso é **cenário do mock**, não copy de contrato — não há fonte no banco para nenhum
dos três. O painel renderiza a parte derivável e **omite** a parte de cenário, em vez de imitá-la
com texto plausível. Substituto plausível é a forma mais duradoura de mentira.

## 5 · SubNav — consumida, não fundada por esta tela

⚠️ **Corrigido em 2026-09-08, depois do merge do [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035).**
A thread 07 previa que este painel fundasse o `_shared/PatrimonioSubNav.tsx`. Ele foi fundado
**antes**, pela tela de Bens (thread 08), que mergeou primeiro. Este painel apenas o consome:

```tsx
<PatrimonioSubNav active="dashboard" />
```

O componente do `main` **deriva** as abas do `shell.menu` (`DataController::modifyAdminMenu`) em
vez de declarar lista própria — para não criar um segundo dono da mesma lista. Consequência
prática: são **6 abas** (Painel · Ativos · Alocações · Devoluções · Manutenção · Configurações),
não as 7 do protótipo. **Garantias** e **Auditoria** não têm rota e simplesmente não aparecem —
*"renderizar aba que não navega é afordância falsa"*, decisão registrada no cabeçalho dele.

Esta tela **não altera** esse componente. Duas divergências foram medidas e ficam declaradas,
para quem tiver escopo de mexer nele:

- **Vocabulário.** RESOLVIDO em 2026-09-09 por decisão [W]: a aba passou a dizer **"Bens"** e
  **"Manutenções"**, alinhada ao `pt/lang.php:9`, ao protótipo e ao `PageHeader` das próprias
  telas. Trocado no dono ÚNICO (`DataController`), não no componente. Ver §6.
- **Contador da aba (2026-09-09).** A barra aceita `badges` (`Record<key, number>`), que
  ENRIQUECE por chave a lista derivada do `shell.menu` — **não** a declara: some a aba do
  menu e o contador some junto. Hoje sai **um só**: `asset-maintenance`, via
  `AssetMaintenanceService::contarAbertas()` em `Inertia::defer`. Dos 5 contadores do
  protótipo, é o único com número **auditado** — Garantias/Auditoria não têm aba, e
  Alocações depende de `allocated_qty`/`revoked_qty`, que o `baseAssetsQuery()` declara
  não serem auditados enquanto o resíduo Tier 0 dela não fechar.
  ⚠️ **Só Painel e Bens o exibem** (os dois renders do `AssetController`). Alocações,
  Manutenções e Configurações ficam sem o pill: os controllers delas ainda são CRLF e o
  `.gitattributes` (`eol=lf`) obrigaria a converter — **2.694 linhas** de ruído medidas em
  2026-09-09, em arquivos que as threads 09-12 estão tocando. Entra por **forward-only**:
  quem tocar aquele controller adiciona a prop de graça.
- **Hue.** Ele passa `group="operar"` (ADR 0180). Medido: `Sidebar.tsx:243` lista
  `'Gestão de ativos'` na whitelist do grupo **`estoque`**, e a entry não declara `group`, então o
  `findGroupKey` resolve por label. `operar` é alias legacy v2 → `producao` (hue 8) e `estoque` é
  350 — ou seja, o botão primary do header e o grupo da sidebar usam matizes diferentes.

## 6 · Vocabulário — três em disputa, e qual venceu

> **Decidido por [W] em 2026-09-09: "Bens" e "Manutenções".** A tabela abaixo fica como estava
> — ela e o retrato da disputa — e a coluna `shell.menu` ganha o valor de hoje entre parênteses.
> O argumento que decidiu não foi a contagem do `lang.php`, e sim que as duas palavras estavam
> **na mesma dobra da mesma tela**: em dez linhas de `Bens.tsx` liam-se "Bens" (`:528`, o `h1`),
> "Novo ativo" (`:534`, o botão) e "Ativos" (`:539`, a aba). Trava: os 4 cenários de
> `Modules/AssetManagement/Tests/Feature/MenuGhostsContratoTest.php`, com bite-test.

| termo | `pt/lang.php` | protótipo | shell.menu |
|---|---|---|---|
| a entidade, no plural | **`assets` → "Bens"** | "Bens" (54×) | "Ativos" → **"Bens"** (2026-09-09) |
| a entidade, no singular | `asset` → "Ativo" | "bem" | — |
| o módulo | `asset_management` → "Gestão de ativos" | "Patrimônio" | "Gestão de ativos" |

A tela usa o `pt/lang.php`, que para o plural **já diz "Bens"** — coincidindo com o protótipo. A
divergência real era o `shell.menu` ("Ativos"), **fechada em 2026-09-09**. O que **permanece** em
aberto é o `lang.php` internamente inconsistente — medido em 2026-09-09 nos 99 valores do
arquivo: "ativo/ativos" em **20**, "recurso" em **9** (`view_asset`, `add_asset`, `asset_name`),
"Bens" em **1** — e o nome do módulo na sidebar, que segue "Gestão de ativos" enquanto a aba diz
"Bens". **Não unifiquei esses dois**: é decisão de produto com escopo próprio (tocaria a sidebar
de todo mundo), e [W] escolheu deliberadamente a opção menor. O retrato da disputa segue na
`_saida-07.md`, que é registro datado e não se edita.

## 7 · Riscos e travas

- **Tier 0 multi-tenant (ADR 0093):** `asset_warranties` **não tem `business_id`** — toda consulta
  de garantia entra por `join` com `assets` filtrando `assets.business_id`. As duas consultas do
  ramo não-admin do `dashboard()` hoje filtram só `receiver`; ao serem reescritas para alimentar
  as props, nascem com `business_id`. Declarado no PR.
- **`Inertia::defer`** (RUNBOOK canônico em `_DesignSystem/RUNBOOK-inertia-defer-pattern.md`): as
  6 agregações são deferidas; só `is_admin`, permissões e o carimbo de hora são eager.
- **`Util::num_uf`:** o painel é **somente leitura** — não grava valor, então o vetor do incidente
  de 2026-06-05 não se aplica aqui. Ele passa a se aplicar na thread 08 (formulário de Bens).
- **Golden do PT-04 é `draft`:** o `criar-tela.mjs` avisa que esta tela não fecha o
  "ciclo-completo" até o golden virar `live`. Não bloqueia a entrega; fica declarado.
- **`dashboard.blade.php` fica órfão** quando o `dashboard()` passa a devolver Inertia. Não
  deletado aqui: deleção de view é escopo de limpeza, e o arquivo não está no prefixo.

## 8 · Verificar

```bash
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test --filter=Asset"
npm run casos:report
node scripts/governance/anchor-lint.mjs --check

# Contrato de RENDER (a lane `patrimonio-painel-gate.yml` roda estes dois):
npx vitest run tests/js/patrimonio-header-contexto.test.tsx \
               tests/js/patrimonio-painel-sem-fonte.test.tsx
node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/patrimonio-index.contract.json
```

Ler **assertions**, nunca "0 failed" — teste que pula sai com exit 0. Tenant de teste é o
fictício **98** (ADR 0358); `biz=4` é proibido.
