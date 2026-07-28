---
id: requisitos-comunicacao-visual-sdd-tela-orcamento-m2-v1-0
slug: comunicacaovisual-sdd
title: "SDD — Orçamento por m² (domínio ComunicacaoVisual)"
type: sdd
module: ComunicacaoVisual
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-07-28"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - CAPTERRA-FICHA.md
  - PII-LGPD.md
  - ROADMAP.md
  - SUPERFICIE.md
  - UI-CATALOG.md
  - PLANO-MIGRACAO-6-SAUDAVEIS.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0110-cockpit-pattern-v2-canon-list-detail
  - 0119-migration-factory-capacidade-institucional
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0351-sdd-from-source
related_us:
  - US-COMVIS-001
  - US-COMVIS-002
  - US-COMVIS-004
  - US-COMVIS-006
---

# SDD — Software Design Document · Orçamento por m² (domínio ComunicacaoVisual)

> Criado pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)), chip da **Onda 4** do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
>
> ⚠️ **Este módulo é o primeiro do passo 5 SEM CLIENTE EM PRODUÇÃO.** Os irmãos (Produto,
> Sells, Vestuario) documentam código que a Larissa usa hoje. Aqui, **14 das 18 US são plano**
> e o SDD documenta a **fatia de código que existe** — não o SPEC inteiro. Onde não há código,
> **não nasce CU** (§6.9): caso sem implementação vira UC órfão, que o `casos-gate` G-2 pune e
> que **bloqueia o merge de quem for implementar** ([proibicoes §5](../../proibicoes.md) 2026-07-16).
>
> **Escopo:** a família de **orçamento por m² + apontamento de produção** — a tela
> `ComunicacaoVisual/Index` (`/comunicacao-visual`) e as duas APIs JSON que a sustentam.
> O SDD é do **MÓDULO/família, nunca da tela** ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.1).

---

## 0. Base empírica

<!-- curado: foto que envelhece -->

### 0.1 As fontes que sustentam este documento — e as que faltam

| # | Fonte | Estado | O que rendeu |
|---|---|---|---|
| 1 | **Documentação canon** | ✅ | [`SPEC.md`](SPEC.md) (18 US) · [`BRIEFING.md`](BRIEFING.md) (destilado honesto, 2026-06-15) · [`PII-LGPD.md`](PII-LGPD.md) · [`Index.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md) · [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) |
| 2 | **React/Laravel atual** | ✅ | `Index.tsx` (531 linhas) · `OrcamentoController` · `OrcamentoCalculator` · `ApontamentoController` · `ApontamentoTracker` · `Routes/web.php` · 10 Entities · 9 migrations · 20 arquivos Pest |
| 3 | **Blade AdminLTE legada** | ❌ **não existe** | `find resources/views -ipath "*comvis*" -o -ipath "*comunicacao*"` = **0 arquivos**. Não há tela Blade a migrar: o módulo **nasceu Inertia**. Não é gap de análise — é ausência de fato. |
| 4 | **Delphi / Office Comercial** | ⚠️ **existe corpus, mas NÃO destilado pra este módulo** | `find memory -iname "*ANTI-REGRESSAO*"` = **2 arquivos, ambos do Produto**. O corpus legado existe ([`memory/legacy-delphi/`](../../legacy-delphi/_INDEX.md) · [`memory/dominios/wr-comercial/`](../../dominios/wr-comercial/README.md) · [`PLANO-MIGRACAO-6-SAUDAVEIS.md`](PLANO-MIGRACAO-6-SAUDAVEIS.md)) e é **do ramo certo** (gráfica), mas **nenhuma feature do OfficeImpresso foi destilada em contrato de paridade** pra ComVis. |

> **Sobre a fonte 4 — a diferença que importa.** No Produto, a fonte 4 é **contrato de paridade**:
> a tela React substituiu uma tela Delphi que a Larissa usava, então feature que some é regressão.
> Aqui **não há cutover**: nenhuma das 6-7 gráficas saudáveis migrou. O legado é **fonte de
> requisito futuro** (o que a gráfica precisa), não contrato de não-regressão. Por isso este SDD
> **não** cria `ANTI-REGRESSAO-*.md` — seria inventar contrato de paridade sobre migração que não
> aconteceu, e anti-padrão inventado parece canon. **Quando a 1ª piloto migrar, o
> `ANTI-REGRESSAO` nasce ali** — é pré-requisito do cutover, não deste PR (§9 D-4).

### 0.2 A Blade de referência — resolvida, não assumida

A [Fase 1.1 do agent](../../../.claude/agents/sdd-from-source.md) avisa que a Blade homônima
engana (no Produto, `show.blade.php` não era a ficha que a operadora abre). Aqui a varredura foi
feita e **voltou vazia**: não há `resources/views/**` do módulo, e `Modules/ComunicacaoVisual/Resources/views/`
tem só o esqueleto nWidart. A única superfície humana é a rota Inertia `/comunicacao-visual`
([`Routes/web.php`](../../../Modules/ComunicacaoVisual/Routes/web.php)) — **1 render, contado**
(`git grep "ComunicacaoVisual/Index" -- '*.php'` = 2 hits: 1 comentário no `DataController`, 1 render real).

### 0.3 O que este SDD NÃO cobre — e por quê

O `SPEC.md` descreve um ERP vertical completo (PCP Kanban, instalação/EPI, NFSe, DAM, CT-e,
loja whitelabel, IA Jana). **Nada disso tem código.** Cobrir aqui seria escrever contrato pra
vapor. O inventário do que é plano está no §6.9 — como lista, não como CU.

---

## 1. Visão geral

### 1.1 O que é

O vertical **ComunicacaoVisual** (CNAE 1813-0/01 — impressão de material publicitário) entrega
hoje **uma coisa que funciona ponta-a-ponta**: transformar `largura × altura × qtd × preço/m²`
num total confiável, com o **servidor como fonte de verdade**. Em volta disso há um segundo
serviço sem tela — o **apontamento de produção** (spool de plotter: iniciar/finalizar/cancelar,
com duração e *drift* entre m² orçado e m² produzido).

O resto do módulo é **schema + contrato**, não fluxo navegável.

### 1.2 Família — 1 tela, 2 APIs, 10 entidades

| Superfície | Rota | Estado |
|---|---|---|
| **Hub + calculadora** `Index.tsx` | `GET /comunicacao-visual` | ✅ **entregue** (única tela) |
| **API de orçamento** | `POST /…/api/calcular` · `POST /…/api/orcamentos` · `GET /…/api/orcamentos/{id}` | ✅ entregue — a tela consome **só** `calcular` |
| **API de apontamento** | `POST iniciar` · `POST {id}/finalizar` · `POST {id}/cancelar` · `GET` · `GET em-andamento` | ✅ entregue — **sem tela** |
| **Install 1-clique** | `GET install` · `install/uninstall` · `install/update` | ✅ entregue ([ADR 0024](../../decisions/0024-instalacao-1-clique-modulos.md)) |
| PCP / OS / instalação / substrato / acabamento | — | 🟡 **schema órfão** — migrations + Entities, zero controller/rota |

### 1.3 Vertical

Módulo **em construção** ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)),
piloto previsto Q3/2026. Candidatos: as 6-7 gráficas saudáveis do OfficeImpresso legado
(Extreme, Gold, Zoom, Fixar, Mhundo, Produart — Vargas saiu pra OficinaAuto). **Zero business
em produção hoje** — ou seja, nenhum dos CU abaixo tem sinal de cliente ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

---

## 2. Público-alvo e personas

<!-- curado: foto que envelhece -->

| Persona | Quem é | O que faz nesta família | Fonte |
|---|---|---|---|
| **Larissa-equivalente** (dona-operadora de gráfica pequena) | 3-25 funcionários, balcão, monitor 1280px, não-técnica | Chega o cliente pedindo "banner 3×1,5 pra sábado"; ela precisa do preço em <2min sem abrir Excel | [`Index.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md) §Persona-alvo · SPEC §2 |
| **Operador de plotter** | opera a máquina, usa o celular ao lado dela | Registra início/fim do trabalho e m² produzido | SPEC US-COMVIS-004 · `ApontamentoTracker` |
| **Wagner [W]** (biz=1, WR2 SC) | dono do produto | É o business dos testes ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) — **nunca biz=4** | proibicoes §R6 |

> ⚠️ **A persona operadora ainda é hipótese.** Diferente do Produto/Sells (onde a Larissa da
> ROTA LIVRE usa a tela todo dia), aqui **ninguém usou a calculadora em produção**. O perfil
> vem de pesquisa de mercado, não de uso observado.

---

## 3. Governança aplicável — o Tier 0 que morde AQUI

| Regra | Onde morde nesta família |
|---|---|
| **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | **10/10 Entities** declaram `addGlobalScope('business_id')`. O `OrcamentoCalculator` resolve preço de catálogo via `Material::find()` — se o scope cair, um business precifica com a tabela de outro. **CU-CV-04.** |
| **REGRA MESTRE valor/estoque** ([proibicoes](../../proibicoes.md)) | Todo o `OrcamentoCalculator` é `[V0]`: área, subtotal, desconto, extras, total. É a razão de o backend ser authoritative (**CU-CV-02**). O incidente `num_uf` ×100 de 2026-06-05 é o precedente vivo. |
| **Tests biz=1, nunca biz=4** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) | Cumprido — `Wave27ComVisPolishTest` tem guard que varre `business_id=4` no código PHP das waves. |
| **PII / LGPD** | `observacoes` é o **único** campo de texto livre com risco de PII; o `OrcamentoCalculator` passa por `PiiRedactor` antes de qualquer span/log. Whitelist de `LogsActivity` exclui `contato_id` e `observacoes`. **CU-CV-08.** |
| **MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) | ⚠️ **Não se aplica como migração** — não há Blade de origem (§0.2). O charter existe (F1.5), mas o `RUNBOOK-<tela>.md` do módulo **não existe** — o hook runtime bloqueia Edit no `.tsx` até criar. **Reportado, não resolvido neste PR** (§9 D-5). |
| **Append-only por lei** | `Apontamento` **não** usa `SoftDeletes` de propósito (registro produtivo); `Orcamento`/`Os` usam. Coberto por `LgpdComplianceTest`. |

---

## 4. Design system aplicável

<!-- curado: foto que envelhece -->

`Index.tsx` segue o canon: `AppShellV2` + `PageHeader` ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) ·
[UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)), primitivas
`Card`/`Button`/`Input`/`Label`/`Badge` de `@/Components/ui`, tokens DS (sem cor crua).

**Resíduo honesto declarado no próprio código:** a linha de item usa `<select>` **nativo** com
`eslint-disable no-restricted-syntax` e justificativa inline (*"linha de grid densa multi-item;
migração p/ `<Select>` shadcn é Wave 2"*). ⚠️ Quando migrar, cuidado com a lápide
[proibicoes §5 2026-06-29](../../proibicoes.md): `<SelectItem value="">` derruba o render inteiro —
usar [`<SafeSelectItem>`](../_DesignSystem/SAFE-SELECT-ITEM.md).

O charter declara `related_prototype: n/a — ferramenta bespoke`; não há protótipo Cowork nem
proto-baseline pra esta tela, logo ela **não é ancorável** no `render-proto-baseline` — é
ausência por natureza, não lacuna.

---

## 5. Arquitetura

### 5.1 Visão em camadas

<!-- derivado: re-rodável do fonte -->

```
Pages/ComunicacaoVisual/Index.tsx          preview client-side (espelho da fórmula)
        │  fetch POST /comunicacao-visual/api/calcular   (X-CSRF-TOKEN)
        ▼
Routes/web.php   ── closure com gate de permission ─→ Inertia::render('ComunicacaoVisual/Index')
        │                                                    props: bizName  ⚠️ só isso (§5.4.1)
        ▼
OrcamentoController  @calcular · @store · @show          validação PT-BR centralizada
        │
        ▼
OrcamentoCalculator  ← AUTHORITATIVE                     OtelHelper::spanBiz + PiiRedactor
        │  resolverPreco():  input override → Material (global scope) → throw
        ▼
Entities  Orcamento · OrcamentoItem · Material           addGlobalScope(business_id)

ApontamentoController  @iniciar/@finalizar/@cancelar/@index/@emAndamento     (SEM TELA)
        └→ ApontamentoTracker  ← duração + drift server-side, 1 spool por operador
```

### 5.2 Modelo de dados (núcleo entregue)

| Tabela | Papel | Chaves que importam |
|---|---|---|
| `comvis_materiais` | catálogo por business | `preco_venda_m2` alimenta o cálculo · sem UNIQUE por nome (duplicata permitida) |
| `comvis_orcamentos` | cabeçalho | **UNIQUE `(business_id, numero)`** — a trava real da numeração (§5.4.3) |
| `comvis_orcamento_itens` | linha calculada | `area_m2`, `preco_unitario_m2`, `subtotal` gravados **já calculados** pelo Service |
| `comvis_os` / `comvis_apontamentos` | OS + spool | `drift_percent` e `duracao_segundos` derivados no Service, nunca no cliente |
| `cv_substratos` / `cv_acabamentos` / `cv_instalacoes_catalogo` / `cv_ordens_producao` / `cv_instalacoes` | schema do PCP | migrados, **sem controller nem rota** — órfãos (§5.4.5) |

### 5.3 Fluxos críticos

<!-- derivado: re-rodável do fonte -->

#### F1 — Abrir o hub `/comunicacao-visual`
`Routes/web.php` closure → gate `superadmin ∥ comvis.orcamento.view ∥ comvis.os.view` (senão **403**)
→ `Inertia::render('ComunicacaoVisual/Index', ['bizName' => session('business.name')])`.
⚠️ **Só `bizName`** — ver §5.4.1.

#### F2 — Calcular na tela (preview client-side)
`areaDe = max(0,largura) × max(0,altura) × max(0,qtd)` · `subtotalDe = area × max(0,preço)` ·
`totalLocal = max(0, subtotal − desconto + extras)`. Qualquer edição **invalida** o resultado
conferido (`setConferido(null)`) — o selo de "conferido" nunca sobrevive a uma mudança.

#### F3 — Conferir no servidor (`POST /…/api/calcular`) `[V0]`
`OrcamentoController@calcular` → `validarPayload` (`largura_m`/`altura_m` `gt:0`, `quantidade min:1`,
`itens min:1`) → `OrcamentoCalculator::calcular`:
`area_m2 = round(l × a × q, 3)` · `subtotal_item = round(area × preço, 2)` ·
`total = round(subtotal − desconto + extras + custo_instalacao + custo_entrega, 2)`,
tudo `PHP_ROUND_HALF_UP`. `InvalidArgumentException` → **422** com mensagem PT-BR.
A tela compara `|servidor − local| < 0,01` e, divergindo, **mostra o do servidor e diz "vale este"**.

#### F4 — Resolver o preço/m² `[V0]`
`resolverPreco()`, em ordem dura: **(1)** `input.preco_unitario_m2` (override do operador; `≤0` ⇒ throw) →
**(2)** `Material::find(material_id)` **com global scope ativo** (material de outro business ⇒ "não
encontrado"; `preco_venda_m2 ≤ 0` ⇒ throw) → **(3)** throw *"obrigatório quando material_id não é informado"*.

#### F5 — Persistir orçamento (`POST /…/api/orcamentos`)
Recalcula (nunca confia no cliente) → `business_id` de `session('user.business_id') ?? session('business.id')`
→ `gerarNumero()` (`ORC-{ano}-{5 dígitos}`, `MAX(numero)` filtrado por business + prefixo do ano) →
`DB::transaction` grava cabeçalho + itens → **201** com `itens` carregados.
⚠️ A tela **não chama este endpoint** (o botão "salvar" não existe) — §5.4.2.

#### F6 — Apontar produção (spool)
`iniciar(osId, operadorId, orcamentoItemId?, maquina?)`: valida OS pelo global scope; **recusa
se o operador já tem apontamento aberto**; faz snapshot de `m2_orcado` a partir de
`OrcamentoItem.area_m2`. `finalizar`: `duracao_segundos` + `drift_percent = round(((prod − orc)/orc) × 100, 2)`,
**null** se `m2_orcado ≤ 0` (divisão por zero evitada). `cancelar`: `m2_produzido = 0` + prefixo
`[CANCELADO]` nas observações. Sem tela — só HTTP JSON.

### 5.4 Onde os dois mundos ainda não se conversam

#### 5.4.1 A rota não entrega o catálogo que a tela declara ⚙️ derivado · medido 2026-07-28

`Index.tsx` declara `Props { bizName?, materiais?, podeCriar? }`, monta o `<select>` de material a
partir de `materiais` e escolhe a copy final por `podeCriar`. **A única rota que renderiza a página
passa apenas `bizName`** — varredura contada: `git grep "ComunicacaoVisual/Index" -- '*.php'` = 2
ocorrências, sendo **1 comentário** (`DataController:89`) e **1 render real** (`Routes/web.php:25`);
`git grep "'materiais'" -- Modules/ComunicacaoVisual` = **1 ocorrência, e é string de tradução**.

Consequência, em cadeia: `materiais = []` ⇒ `semCatalogo = true` ⇒ o `<select>` fica **`disabled`
mostrando "Sem catálogo"** ⇒ `material_id` é sempre `null` ⇒ o payload nunca exercita o ramo (2)
do `resolverPreco` (§F4) ⇒ **a operadora digita o preço/m² à mão em toda peça**. E `podeCriar`
sempre `false` ⇒ a copy final é sempre a variante *"Salvar e enviar orçamento chega em breve"*.

Três fontes prometem o contrário: o **docblock do próprio `.tsx`** (*"Seletor de material puxa o
catálogo do business (preço/m² preenche sozinho)"*), o **[`BRIEFING.md`](BRIEFING.md)** (mesma frase)
e o **DoD da US-COMVIS-002** (*"alimentar US-COMVIS-001 sem hard-code"*). O `MaterialSeeder` **semeia
5 materiais** — que a tela nunca vê. **CU-CV-09**, previsto quebrado.

> **Duas correções são válidas** (passar `materiais` na closure **ou** a tela buscar por fetch).
> O contrato é *"o catálogo do business chega à calculadora"*, não *"a prop se chama `materiais`"* —
> o teste é a instância de hoje e deve ser reescrito se [W] escolher o outro caminho.

#### 5.4.2 Metade da API de orçamento não tem consumidor ⚙️ derivado

`store` e `show` existem, estão testados e **ninguém os chama**: `git grep "api/orcamentos" -- '*.tsx'`
= **0**. A tela só usa `calcular`. Não é bug — é o TODO declarado no cabeçalho do `.tsx` ("Sprint 2").
Registrado pra que ninguém conclua, lendo os testes, que "salvar orçamento" funciona pela UI.

#### 5.4.3 A numeração é sequencial por leitura, não por reserva ⚙️ derivado

`gerarNumero()` faz `MAX(numero)` **fora** da transação de escrita e sem lock. Duas requisições
simultâneas leem o mesmo máximo e tentam gravar o mesmo `numero`. **O dano é contido, não silencioso:**
a migration tem `UNIQUE (business_id, numero)`, então a segunda estoura *duplicate key* (erro 500)
em vez de gravar dois orçamentos com o mesmo número. Fica como dívida (§9 D-2), não como Tier 0 —
e o `[V0]` aqui é o **valor**, que não é afetado.

#### 5.4.4 O `business_id` da escrita vem da sessão, não do usuário autenticado ⚙️ derivado

`store()` usa `session('user.business_id') ?? session('business.id')`. É a convenção UltimatePOS e
está coberta por `MultiTenantTest`/`OrcamentoControllerTest`, mas difere do padrão que o Compras
adotou (`auth()->user()->business_id`, US-COM-007) justamente por ser mais difícil de forjar.
**Observação, não achado** — não varri o middleware `SetSessionData` a fundo o suficiente pra
afirmar exploração ([proibicoes §5](../../proibicoes.md) 2026-07-15). Vira §9 D-3.

#### 5.4.5 Cinco entidades do PCP são schema órfão ⚙️ derivado

`Substrato`, `Acabamento`, `InstalacaoCatalogo`, `OrdemProducao`, `Instalacao` têm migration,
Entity, global scope e teste de isolamento — e **zero controller, zero rota, zero tela**. O
`FsmProcessoComunicacaoVisualSeeder` (em `database/seeders/`, não no módulo) cadastra o pipeline
FSM de 13 stages e é testado, mas **nada o executa em runtime**. É custo de manutenção sem uso —
declarado, não escondido (§9 D-1).

#### 5.4.6 O `minimo_m2` do DoD não existe no schema ⚙️ derivado · medido 2026-07-28

O DoD da US-COMVIS-001 pede *"mínimo cobrado configurável (`material.minimo_m2` — ex: 0,5m² mesmo
se a peça é menor)"*. A migration `comvis_materiais` tem `estoque_minimo_m2` (**estoque**, outra
coisa) e **não tem** `minimo_m2`; o `OrcamentoCalculator` não aplica piso algum. A linha
`Implementado em:` da US já declara isso — aqui fica a confirmação pelo schema.

#### 5.4.7 O vertical não tem dicionário de domínio ⚠️

Não existe `memory/dominio/comunicacao-visual.md` — o módulo **não é coberto pelo `dominio-gate`**
(G-4, required). Os enums do schema (`categoria`, `unidade`, `status`) não têm fonte única.
Enquanto o módulo não tiver piloto isso é barato; no cutover vira obrigatório. §9 D-6.

---

## 6. Casos de uso

> **Derivados das 3 fontes disponíveis** (canon → código → *ausência* de Blade), nunca só do
> `.tsx` ([ADR 0351](../../decisions/0351-sdd-from-source.md) D-A). Estado vem do **veredito da
> lane**, nunca da leitura: `✅` provado por teste verde que o cita · `🟡` parcial · `🔴`
> falso/quebrado · `⬜` não-verificado · `🧪` teste existe, veredito pendente.
>
> ⚖️ **A força do veredito aqui é MAIS FRACA do que parece — leia antes de confiar.** Três portas
> distintas, medidas separadamente:
>
> | Pergunta | Porta medida | Resposta |
> |---|---|---|
> | roda em algum lugar? | [`phpunit.xml`](../../../phpunit.xml) (`./Modules/ComunicacaoVisual/Tests/Feature` na testsuite `Feature`) + [`scripts/tests/shards-plan.mjs`](../../../scripts/tests/shards-plan.mjs) (`--roots tests,Modules`) | ✅ **sim** — está no universo da full-suite noturna (MySQL real) |
> | roda no PR? | [`.github/workflows/modules-pest.yml`](../../../.github/workflows/modules-pest.yml) — matrix de 6 módulos, `DB_CONNECTION=sqlite :memory:` **sem migrate** | ⚠️ **parcialmente**: **6 dos 20 arquivos** abortam no `beforeEach` com `markTestSkipped('SQLite-incompatível')` — incluindo `MultiTenantTest`, `Tier0GuardTest`, `OrcamentoControllerTest`, `MaterialSeederTest`, `MigrationsTest` e `CustomerJourneyTest` |
> | **bloqueia merge?** | [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) — os únicos contextos Pest **required** são `PHP / Pest (Financeiro · MySQL)`, `PHP / Pest (NfeBrasil · MySQL)` e `PHP / Pest (Unit)` | ❌ **não** — `Pest ComunicacaoVisual` é **advisory** |
>
> **Leitura honesta:** o verde da lane `Pest ComunicacaoVisual` no PR **não prova o isolamento
> multi-tenant** — prova que os testes que o provariam foram *pulados*. É a família "verde por
> não-execução" ([proibicoes §5](../../proibicoes.md) 2026-07-24). O que os `[T0]` deste SDD
> exercitam de verdade é a **full-suite noturna**, não o PR. Marcado caso a caso abaixo com **⏭ PR-skip**.

### 6.1 Cálculo de valor — o núcleo `[V0]` (`CU-CV`)

#### CU-CV-01 — Calcular o orçamento por m² na tela `[must]` 🟡
<!-- derivado: re-rodável do fonte (F1 · F2 · Index.tsx areaDe/subtotalDe/totalLocal) -->
*Dado* uma operadora com `comvis.orcamento.view`; *quando* abre `/comunicacao-visual` e digita
largura, altura, quantidade e preço/m²; *então* vê área, subtotal por peça e total estimado
atualizando enquanto digita — sem recarregar nada.

1. `[must]` sem nenhuma das três permissões (`superadmin`, `comvis.orcamento.view`, `comvis.os.view`) ⇒ **403**.
2. `[must]` o componente Inertia renderizado é `ComunicacaoVisual/Index`.
3. `[must]` dimensões negativas **não** viram crédito: `areaDe` aplica `max(0, …)` em cada fator.
4. `[must]` o botão "Conferir no servidor" só habilita com **ao menos um item válido**
   (largura>0 ∧ altura>0 ∧ qtd≥1 ∧ preço>0).
5. `[ux]` remover a última peça é impossível (o botão fica `disabled`) — nunca há lista vazia.

#### CU-CV-02 — O servidor é a fonte de verdade do valor `[must]` `[V0]` 🧪
<!-- derivado: re-rodável do fonte (F3 · OrcamentoCalculator::calcularInterno · OrcamentoCalculatorTest) -->
*Dado* um orçamento montado na tela; *quando* a operadora confere no servidor; *então* o total
oficial é o **recalculado no backend**, e qualquer valor vindo do cliente é descartado.

1. `[V0]` `area_m2 = round(largura × altura × qtd, 3, HALF_UP)`.
2. `[V0]` `subtotal_item = round(area_m2 × preco_unitario_m2, 2, HALF_UP)`.
3. `[V0]` `total = round(subtotal − desconto + extras + custo_instalacao + custo_entrega, 2, HALF_UP)`.
4. `[V0]` **dupla-confirmação** no cenário canônico do `OrcamentoCalculatorTest` (banner 3 m × 1,5 m,
   1 peça): `area = 4,5` e o total fecha por **dois caminhos independentes** — recomputo à mão da
   fórmula **e** soma das linhas devolvida pelo Service.
5. `[must]` payload inválido (largura ≤ 0, altura ≤ 0, qtd < 1, zero itens) ⇒ **422** com mensagem PT-BR — nunca 200 com lixo.
6. `[must]` divergindo do preview, a tela mostra o valor **do servidor** e o rotula "vale este".

#### CU-CV-03 — Resolver o preço/m² por prioridade dura `[must]` `[V0]` 🧪 ⏭ PR-skip (ramo do catálogo)
<!-- derivado: re-rodável do fonte (F4 · OrcamentoCalculator::resolverPreco · OrcamentoCalculatorTest cenários 3/7/7b) -->
*Dado* um item que pode trazer preço digitado, material do catálogo, ou nada; *quando* o
servidor calcula; *então* a origem do preço segue uma ordem única e explícita.

1. `[must]` override do operador vence o catálogo (prioridade 1).
2. `[must]` override `≤ 0` ⇒ **erro**, nunca "de graça".
3. `[must]` sem override, o preço vem de `Material.preco_venda_m2` do **próprio business**.
4. `[T0]` `material_id` de **outro business** ⇒ erro *"não encontrado ou não pertence a este business"* —
   o global scope é o que faz isso, e a mensagem não revela existência.
5. `[must]` material com `preco_venda_m2 ≤ 0` ⇒ erro nomeando o material — não calcula zero.
6. `[must]` sem override **e** sem `material_id` ⇒ erro. **Nenhum caminho produz preço implícito.**

#### CU-CV-04 — Isolar catálogo, orçamento, OS e apontamento por business `[must]` `[T0]` 🧪 ⏭ PR-skip
<!-- derivado: re-rodável do fonte (MultiTenantTest · Tier0GuardTest · MaterialSeederTest · ADR 0093) -->
*Dado* dados nos businesses 1 e 99; *quando* a sessão é do business 1; *então* nada do 99 aparece,
em nenhuma das 10 entidades.

1. `[T0]` `Material`, `Orcamento`, `OrcamentoItem` e `Os` do biz=1 **não** aparecem com sessão biz=99 (e o controle positivo — aparecem com sessão biz=1 — também vale).
2. `[T0]` o mesmo para as 5 entidades do PCP (`Substrato`, `Acabamento`, `InstalacaoCatalogo`, `OrdemProducao`, `Instalacao`).
3. `[T0]` `GET /…/api/orcamentos/{id}` de outro business ⇒ **404** (não 403 — não revelar existência).
4. `[T0]` `GET /…/api/apontamentos/em-andamento` com sessão de outro business ⇒ vazio.
5. `[T0]` o evento `creating` auto-popula `business_id` da sessão — criar sem informar **não** vaza pro business errado.
6. `[must]` `MaterialSeeder.run(1)` e `.run(99)` produzem catálogos independentes e é **idempotente** (rodar 2× não duplica).

#### CU-CV-05 — Persistir o orçamento com número sequencial por business `[should]` 🧪 ⏭ PR-skip
<!-- derivado: re-rodável do fonte (F5 · OrcamentoController@store/@gerarNumero · OrcamentoControllerTest) -->
*Dado* um orçamento válido; *quando* o cliente da API chama `POST /…/api/orcamentos`; *então*
cabeçalho e itens são gravados **com os valores recalculados** e o número é `ORC-{ano}-{5 dígitos}`
sequencial dentro do business.

1. `[must]` responde **201** com o orçamento e seus `itens`.
2. `[V0]` o que é gravado em `area_m2`/`preco_unitario_m2`/`subtotal` é a saída do Service — nunca o número do cliente.
3. `[must]` cabeçalho e itens são atômicos (`DB::transaction`) — não existe orçamento sem linha.
4. `[must]` a sequência é **por business e por ano-civil**.
5. `[should]` a corrida entre dois `store` simultâneos é barrada pelo `UNIQUE (business_id, numero)` — falha visível, nunca dois orçamentos com o mesmo número. **`[BACKLOG]`** — sem teste hoje (§9 D-2).
6. ⚠️ **Nenhuma tela chama este endpoint** (§5.4.2) — o contrato é de API, não de UI.

### 6.2 Produção (`CU-CV`)

#### CU-CV-06 — Apontar a produção com duração e drift server-side `[must]` 🧪 ⏭ PR-skip (parte HTTP)
<!-- derivado: re-rodável do fonte (F6 · ApontamentoTracker · ApontamentoTrackerTest · ApontamentoControllerTest) -->
*Dado* uma OS do meu business; *quando* o operador inicia e depois finaliza o trabalho; *então*
duração e desvio de m² saem do servidor, e um operador nunca tem dois spools abertos.

1. `[must]` `iniciar` numa OS de outro business ⇒ `RuntimeException` (global scope).
2. `[must]` **1 spool ativo por operador**: iniciar com um aberto ⇒ exceção nomeando o apontamento em andamento.
3. `[must]` `duracao_segundos` é calculada no servidor a partir de `iniciado_em`/`finalizado_em`.
4. `[V0]` `drift_percent = round(((m2_produzido − m2_orcado) / m2_orcado) × 100, 2)`; com `m2_orcado ≤ 0` ⇒ **null**, nunca divisão por zero.
5. `[must]` finalizar duas vezes ⇒ exceção (append-only de fato).
6. `[must]` `cancelar` zera `m2_produzido` e prefixa `[CANCELADO]` — não apaga o registro.
7. `[reg]` `Apontamento` **não** tem `SoftDeletes`; `Orcamento`/`Os` têm. Inverter isso é regressão legal.

### 6.3 Catálogo e fiscal (`CU-CV`)

#### CU-CV-07 — Semear o catálogo de materiais de partida `[should]` 🧪 ⏭ PR-skip
<!-- derivado: re-rodável do fonte (MaterialSeeder · MaterialSeederTest · SPEC US-COMVIS-002) -->
*Dado* um business novo do vertical; *quando* o seeder roda; *então* existem 5 materiais default
com preço/m², isolados por business e idempotentes.

1. `[must]` exatamente **5** materiais por business após o 1º run.
2. `[must]` rodar 2× continua em 5 (idempotente).
3. `[T0]` `run(1)` e `run(99)` não se enxergam.
4. ⚠️ **Não existe CRUD de material** — nem controller, nem tela. O catálogo só nasce por seeder ou SQL. `[BACKLOG]`.

#### CU-CV-08 — Guardar o rastro sem vazar PII `[must]` `[reg]` 🧪
<!-- derivado: re-rodável do fonte (AuditTrailIntegrityTest · LgpdComplianceTest · Wave26/28 · PII-LGPD.md) -->
*Dado* que orçamento, OS e apontamento mudam de estado; *quando* a mudança é registrada; *então*
o log guarda o que é de negócio e **nunca** o que pode ser pessoal.

1. `[reg]` a whitelist `logOnly` do `Orcamento` **não** inclui `contato_id` (referência a PII) nem `observacoes` (texto livre).
2. `[reg]` a do `Apontamento` **não** inclui `observacoes` nem `operador_id`.
3. `[must]` a whitelist **cobre** o que é de negócio (status, totais, datas) — não é lista vazia.
4. `[reg]` `observacoes` passa por `PiiRedactor` **antes** de qualquer span/log do `OrcamentoCalculator`; o DB guarda o original (a anonimização é do `right_to_be_forgotten`).
5. `[must]` `logOnlyDirty` + `dontSubmitEmptyLogs` ativos — sem entrada de log sem mudança real.
6. `[reg]` `retention.php` declara janela e `pii_fields` para as 3 entidades; telemetria ≤ 365 dias.
7. `[reg]` **10/10** Entities declaram `LogsActivity` **e** `addGlobalScope('business_id')`.

#### CU-CV-09 — A calculadora recebe o catálogo do business `[must]` 🔴 **previsto quebrado** (§5.4.1)
<!-- derivado: re-rodável do fonte (Routes/web.php:25 · Index.tsx Props/escolherMaterial · BRIEFING · SPEC US-COMVIS-002 DoD) -->
*Dado* um business com materiais cadastrados; *quando* a operadora abre `/comunicacao-visual`;
*então* ela escolhe o material numa lista e o preço/m² preenche sozinho — em vez de digitar
o preço de cabeça em cada peça.

1. `[must]` a página entrega à calculadora o catálogo **ativo** do business — hoje **não entrega**.
2. `[T0]` o catálogo entregue contém **apenas** materiais do próprio business.
3. `[must]` escolher um material preenche o preço/m² a partir do catálogo (`escolherMaterial`).
4. `[should]` sem material cadastrado, a tela continua usável com preço digitado (o aviso "sem catálogo" já existe e é bom).
5. `[should]` a copy final de salvar/WhatsApp reflete a permissão real de criar, não o default `false`.

> **Duas correções são válidas** — passar a prop na closure da rota **ou** a tela buscar o catálogo
> por fetch. O contrato é *"o catálogo chega à calculadora"*; o assert é comportamental (o **nome**
> do material chega pra escolha), não acoplado ao nome da prop.

#### CU-CV-10 — Nascer com a tributária do CNAE 1813 `[should]` 🟡 **parcial**
<!-- derivado: re-rodável do fonte (migration cv_substratos · SPEC US-COMVIS-006 DoD) -->
*Dado* um substrato do catálogo; *quando* ele é usado numa emissão; *então* NCM, CFOP e CSOSN já
vêm preenchidos com o padrão de impresso publicitário — sem contador configurar item a item.

1. `[must]` o schema de substratos carrega `ncm`, `cfop_padrao` e `csosn_padrao` — **existe** (migration `2026_05_12_000010`).
2. `[should]` há **seed** populando os NCMs do ramo (4911.10, 4911.99, 3919, 7610, 9405) e os CFOP 5101/5102/5933/5949 — **não existe**. `[BACKLOG]`.
3. `[should]` wizard de onboarding detecta CNAE 1813 e pré-popula — **não existe**. `[BACKLOG]`.

### 6.9 O que NÃO vira CU — porque não tem código

> Regra dura do chip: **US `todo` não ganha UC**. Caso sem implementação vira UC órfão, o G-2
> pune, e o merge de quem for implementar trava ([proibicoes §5](../../proibicoes.md) 2026-07-16).
> As 14 US abaixo estão declaradas `_pendente_` no [`SPEC.md`](SPEC.md) e ficam aqui **como
> inventário**, não como contrato.

| US | Capacidade | O que existe hoje |
|---|---|---|
| US-COMVIS-003 | PCP gráfico (Kanban) | migration `cv_ordens_producao` + Entity + seeder FSM — **sem controller/rota/tela** |
| US-COMVIS-005 | Pós-cálculo (orçado × realizado) | nada (depende de US-004 completa) |
| US-COMVIS-007 | Instalação/fachada (agenda + EPI) | migration `cv_instalacoes` + Entity — **sem controller** |
| US-COMVIS-008 | NFSe da instalação | `Modules/NFSe` existe; o **trigger** ComVis não |
| US-COMVIS-009 | NFe de boleto pago | núcleo entregue em `RecurringBilling`; o **adapter** ComVis não existe |
| US-COMVIS-010 | Provador de orçamento público | nada |
| US-COMVIS-011 | Comissão por OS | nada |
| US-COMVIS-012 | DAM (arquivo print-ready) | nada |
| US-COMVIS-013 | Bulk update de preço via Jana | nada |
| US-COMVIS-014 | Dashboard conversacional Jana | nada |
| US-COMVIS-015 | Cadastro de máquina + CMYK | nada (`cv_maquinas` nem migrada) |
| US-COMVIS-016 | CT-e / MDF-e | nada |
| US-COMVIS-017 | Importador do OfficeImpresso legado | nada no módulo (a skill de snapshot é ferramenta externa) |
| US-COMVIS-018 | Loja whitelabel | nada |

### 6.10 Non-Goals — **só [W] preenche**

> O agente é **proibido de inferir** Non-Goal ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.1).
> Os anti-padrões vigentes da tela vivem no [§Anti-padrões do `Index.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md)
> e **não foram tocados neste PR**. O `SPEC.md §10` tem 15 anti-padrões de **construção do módulo**
> (não de tela) — também intocados.

⬜ _Seção aguardando [W]._

---

## 7. Requisitos não-funcionais

| # | NFR | Estado |
|---|---|---|
| NFR-1 | **Cálculo authoritative server-side** — o cliente nunca decide valor | ✅ por desenho (`OrcamentoCalculator`) |
| NFR-2 | **Rate limit** no endpoint de cálculo | ✅ `throttle:60,1` no grupo `/api` |
| NFR-3 | **Observabilidade** — span OTel por operação de negócio | ✅ 4 spans (`comvis.orcamento.calcular` + 3 de apontamento), cobertos por `Wave28SaturationTest` |
| NFR-4 | **PII fora da telemetria** | ✅ `PiiRedactor` antes do span |
| NFR-5 | `Inertia::defer` em prop cara | ⚪ **não se aplica hoje** — a página não tem prop cara (só `bizName`). Vira obrigatório quando o catálogo entrar (CU-CV-09) |
| NFR-6 | **Feedback <2min no balcão** (SPEC §3) | 🟡 o preview é instantâneo; o ida-e-volta ao servidor é 1 request. **Nunca medido com usuário real** |
| NFR-7 | **Mobile-first no apontamento** (DoD US-004) | ❌ não existe tela |
| NFR-8 | **Acessibilidade** | 🟡 `aria-label` nos controles densos; sem auditoria a11y da tela |

---

## 8. Estratégia de qualidade e rollout

**O que a suíte cobre de verdade (20 arquivos, ~3,6k linhas):**

| Camada | Arquivos | Executa no PR? |
|---|---|---|
| Fórmula pura (`OrcamentoCalculator`) | `OrcamentoCalculatorTest` | ✅ **sim** — os cenários sem DB são a cobertura real do `[V0]` na lane |
| HTTP + DB (orçamento, apontamento, multi-tenant, migrations, seeder) | `OrcamentoControllerTest` · `ApontamentoControllerTest` · `MultiTenantTest` · `Tier0GuardTest` · `MigrationsTest` · `MaterialSeederTest` · `CustomerJourneyTest` | ⚠️ **não** — `markTestSkipped` em SQLite; só na full-suite noturna (MySQL) |
| Estrutura/governança (charter, changelog, spans, whitelist, scorecard) | `Wave23/25/26/27/28` · `AuditTrailIntegrityTest` · `LgpdComplianceTest` · `DataControllerTest` | ✅ sim (reflection/arquivo, sem DB) |

> ⚠️ **A conclusão desconfortável:** boa parte da suíte é **guard estrutural** (o arquivo existe,
> a classe declara o método, o changelog tem a entrada) — trava o contorno, não exercita o
> comportamento. E o pedaço que exercita comportamento com DB é justamente o que **não roda no PR**.
> Isso não invalida os testes; invalida a leitura de que "lane verde = módulo provado".

**Rollout:** [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) manda não
escalar além de P0 antes da 1ª piloto migrada. Este SDD **não propõe feature** — só nomeia o
contrato do que já existe.

---

## 9. Riscos e dívidas conhecidas

| # | Dívida | Gravidade | Dono |
|---|---|---|---|
| **D-1** | 5 entidades do PCP + pipeline FSM = **schema órfão** sem consumidor (§5.4.5) | 🟡 custo de manutenção; risco de "parece pronto" | decisão de produto — [W] |
| **D-2** | Numeração de orçamento sem reserva atômica (§5.4.3) — falha visível, não silenciosa | 🟡 | técnico; vira US quando `store` ganhar consumidor |
| **D-3** | `business_id` da escrita vem da sessão, não de `auth()` (§5.4.4) — **observação, não achado** | ⚪ a confirmar | precisa varredura do `SetSessionData` antes de virar afirmação |
| **D-4** | **Sem `ANTI-REGRESSAO-*.md`** — a fonte 4 não foi destilada (§0.1) | 🟠 **pré-requisito do cutover** | [W] decide quando (nasce com a 1ª piloto) |
| **D-5** | **Sem `RUNBOOK-<tela>.md`** — o hook MWART bloqueia Edit no `.tsx` até criar | 🟡 trava trabalho futuro na tela | técnico, fora do escopo deste PR |
| **D-6** | Sem `memory/dominio/comunicacao-visual.md` → módulo fora do `dominio-gate` (G-4, required) (§5.4.7) | 🟡 barato hoje, obrigatório no cutover | técnico |
| **D-7** | Os `[T0]` **não são exercitados no PR** (§6, tabela das 3 portas) | 🟠 o mais sério: Tier 0 sem prova executada no gate de entrada | decisão [W] — ver §10 |
| **D-8** | `minimo_m2` do DoD não existe no schema (§5.4.6) | ⚪ feature não entregue, já declarada no SPEC | — |

---

## 10. Roadmap de evolução

Ordenado por **razão custo/risco**, não por tamanho. Nada aqui é promessa — é fila proposta.

1. **Fechar CU-CV-09** (a rota entregar o catálogo). É o menor diff do módulo e destrava a única
   promessa de UI que hoje é falsa. Cuidado: ao virar prop cara, `Inertia::defer` (NFR-5).
2. **Resolver D-7** — decidir se as suítes DB do ComVis passam a rodar contra MySQL no PR
   (como Financeiro/NfeBrasil) ou se o veredito Tier 0 fica declaradamente noturno. **É decisão
   [W]**, porque mexer no `modules-pest.yml` afeta **6 módulos** — este chip não toca lá.
3. **Seed tributária CNAE 1813** (CU-CV-10 item 2) — barato, e é P0 no SPEC.
4. **CRUD de materiais** (CU-CV-07 item 4) — sem ele o catálogo do CU-CV-09 nasce vazio na prática.
5. **Decidir o destino do schema órfão** (D-1): construir o PCP ou marcar as 5 entidades como
   reservadas — hoje elas custam manutenção e sugerem capacidade inexistente.
6. **`ANTI-REGRESSAO` + `RUNBOOK` + dicionário de domínio** (D-4/D-5/D-6) — o pacote de cutover,
   quando a 1ª piloto for escolhida.

---

## 11. Referências

- [`SPEC.md`](SPEC.md) — 18 US · [`BRIEFING.md`](BRIEFING.md) — destilado honesto do que roda
- [`Index.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md) (lei) ·
  [`Index.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.casos.md) (contrato)
- [`PII-LGPD.md`](PII-LGPD.md) · [`ROADMAP.md`](ROADMAP.md) · [`PLANO-MIGRACAO-6-SAUDAVEIS.md`](PLANO-MIGRACAO-6-SAUDAVEIS.md)
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) modular por vertical ·
  [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0 ·
  [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) casos-gate ·
  [ADR 0351](../../decisions/0351-sdd-from-source.md) este método
- [passo 5 do programa-ondas](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md)

---

## Changelog

| Data | Versão | O quê |
|---|---|---|
| 2026-07-28 | 1.0.0 | Nasce. Chip da Onda 4 do passo 5 — 3 fontes trianguladas (Blade **inexistente**, Delphi **não destilado**), 10 CU sobre a superfície entregue, 14 US inventariadas como plano sem CU, 8 dívidas nomeadas. Achado principal: **CU-CV-09** (a rota não entrega o catálogo que a tela declara) e **D-7** (os `[T0]` não rodam no PR). |
