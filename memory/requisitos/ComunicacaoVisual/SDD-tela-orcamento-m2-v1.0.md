---
id: requisitos-comunicacao-visual-sdd-tela-orcamento-m2-v1-0
slug: comunicacaovisual-sdd
title: "SDD — Orçamento por m² (domínio ComunicacaoVisual)"
type: sdd
module: ComunicacaoVisual
status: ativo
owner: W
version: 1.1.0
last_updated: "2026-11-10"
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
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0110-cockpit-pattern-v2-canon-list-detail
  - 0119-migration-factory-capacidade-institucional
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0431-pcp-sai-do-desejo-modules-producao
  - 0436-os-por-item-producao-dona-da-os-do-pedido
  - 0351-sdd-from-source
related_us:
  - US-COMVIS-001
  - US-COMVIS-002
  - US-COMVIS-003
  - US-COMVIS-004
  - US-COMVIS-005
  - US-COMVIS-006
  - US-COMVIS-007
  - US-COMVIS-019
  - US-COMVIS-020
  - US-COMVIS-021
  - US-COMVIS-022
  - US-COMVIS-023
---

# SDD — Software Design Document · Orçamento por m² (domínio ComunicacaoVisual)

> Criado pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)), chip da **Onda 4** do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
>
> ⚠️ **Módulo sem cliente em produção registrado.** Os irmãos (Produto, Sells, Vestuario) documentam
> código que a Larissa usa hoje; aqui nenhum documento registra piloto migrado, e o uso real não foi
> medido. Quantas US têm código não se repete neste texto: `node scripts/governance/anchor-lint.mjs --json`
> responde. Onde não há código, **não nasce CU** (§6.9): caso sem implementação vira UC órfão, que o
> `casos-gate` G-2 pune e que **bloqueia o merge de quem for implementar** ([proibicoes §5](../../proibicoes.md) 2026-07-16).
>
> **Escopo:** o fluxo do orçamento até o pós-cálculo. Telas `ComunicacaoVisual/Index` (`/comunicacao-visual`),
> `ComunicacaoVisual/Agenda` (`/comunicacao-visual/agenda`) e `ComunicacaoVisual/AceitePublico`
> (`/orcamento-cv/{token}`), mais as APIs JSON que as sustentam. O SDD é do **MÓDULO/família, nunca da tela**
> ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.1).
>
> **Revisão 2026-11-10** (auditoria de 2026-11-05): o que era verdade em 2026-07-28 e deixou de ser
> virou passado datado (§5.4), o fluxo ponta a ponta entrou em §5.5 e o inventário de §6.9 foi refeito.
> Faturamento segue como lacuna (§5.5, passo 8).

---

## 0. Base empírica

<!-- curado: foto que envelhece -->

### 0.1 As fontes que sustentam este documento — e as que faltam

| # | Fonte | Estado | O que rendeu |
|---|---|---|---|
| 1 | **Documentação canon** | ✅ | [`SPEC.md`](SPEC.md) · [`BRIEFING.md`](BRIEFING.md) · [`PII-LGPD.md`](PII-LGPD.md) · charter e casos das 3 telas em `resources/js/Pages/ComunicacaoVisual/` · [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) |
| 2 | **React/Laravel atual** | ✅ | Inventário de arquivos em [`SUPERFICIE.md`](SUPERFICIE.md), conferido por `node scripts/governance/module-surface.mjs ComunicacaoVisual --check`. Serviços que carregam o fluxo: `OrcamentoCalculator` · `OrcamentoGravador` · `MargemDoOrcamento` · `OrcamentoViraOs` · `AceiteDoOrcamento` · `RoteiroPadraoDaOs` · `AgendaInstalacao` · `PosCalculoDaOs` · `ApontamentoTracker` |
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
feita e **voltou vazia**: não há `resources/views/**` do módulo nem `Modules/ComunicacaoVisual/Resources/views/`.
As superfícies humanas são os **3 renders Inertia**: `git grep -n "Inertia::render" -- 'Modules/ComunicacaoVisual/*.php'`
devolve `Routes/web.php` (Index), `AgendaController` (Agenda) e `OrcamentoAceitePublicoController` (AceitePublico),
além de 1 comentário no `DataController`.

### 0.3 O que este SDD NÃO cobre — e por quê

O `SPEC.md` descreve um ERP vertical completo. Parte já tem código e está em §5.5; o resto (NFSe, DAM,
CT-e, loja whitelabel, IA Jana, comissão) não tem. Cobrir o que não existe seria escrever contrato pra
vapor: o inventário do que é plano está no §6.9, como lista, não como CU. A produção propriamente dita
(quadro, roteiros, apontamento por etapa) é do `Modules/Producao` ([ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md));
aqui só entra a costura com ele.

---

## 1. Visão geral

### 1.1 O que é

O vertical **ComunicacaoVisual** (CNAE 1813-0/01, impressão de material publicitário) transforma
`largura × altura × qtd × preço/m²` num total confiável, com o **servidor como fonte de verdade**, e leva
esse orçamento até a OS: salvar, mandar ao cliente por link, aprovar, entrar no quadro do PCP, agendar a
instalação e comparar orçado com realizado. O fluxo completo está em §5.5.

Um segundo serviço segue **sem tela**: o spool de plotter do próprio módulo (iniciar/finalizar/cancelar,
com duração e *drift* entre m² orçado e m² produzido). O apontamento que o operador faz de fato é por
etapa, no `Modules/Producao`.

### 1.2 Família

| Superfície | Rota | Estado |
|---|---|---|
| **Hub + calculadora** `Index.tsx` | `GET /comunicacao-visual` | ✅ entregue |
| **Agenda de instalação** `Agenda.tsx` | `GET /comunicacao-visual/agenda` | ✅ entregue |
| **Aceite do cliente** `AceitePublico.tsx` | `GET` · `POST …/aceitar` · `POST …/ajuste` em `/orcamento-cv/{token}`, sem login | ✅ entregue |
| **API de orçamento** | `calcular` · `orcamentos` (`GET`/`POST`) · `orcamentos/{id}` (`GET`/`PUT`) · `{id}/aprovar` · `{id}/link` · `{id}/arquivos` | ✅ entregue; `Index.tsx` consome as de escrita |
| **API de roteiro padrão** | `GET`/`PUT /…/api/roteiro-padrao` | ✅ entregue |
| **API de instalação** | `GET /…/api/instalacoes` · `POST /…/api/os/{os}/instalacao` | ✅ entregue |
| **API de pós-cálculo** | `GET /…/api/os/{os}/pos-calculo` | 🟡 só JSON, atrás de `COMVIS_POS_CALCULO_ENABLED` (padrão desligada, `config/comunicacao-visual.php`) |
| **API de apontamento (spool)** | `POST iniciar` · `POST {id}/finalizar` · `POST {id}/cancelar` · `GET` · `GET em-andamento` | ✅ entregue, **sem tela** |
| **Orçamento rápido do app** | `/api/app/orcamentos` (`AppOrcamentoRapidoController`) | ✅ entregue; grava pelo mesmo `OrcamentoGravador` |
| **Install 1-clique** | `GET install` · `install/uninstall` · `install/update` | ✅ entregue ([ADR 0024](../../decisions/0024-instalacao-1-clique-modulos.md)) |
| Substrato / catálogo de instalação / `cv_ordens_producao` | — | 🟡 **schema sem fluxo** (§5.4.5) |

### 1.3 Vertical

Módulo **em construção** ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)).
A previsão de piloto em Q3/2026 passou sem piloto registrado; a nova data é decisão [W]. Candidatos: as
gráficas saudáveis do OfficeImpresso legado ([`PLANO-MIGRACAO-6-SAUDAVEIS.md`](PLANO-MIGRACAO-6-SAUDAVEIS.md)).
Nenhum CU abaixo tem sinal de cliente ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

---

## 2. Público-alvo e personas

<!-- curado: foto que envelhece -->

| Persona | Quem é | O que faz nesta família | Fonte |
|---|---|---|---|
| **Larissa-equivalente** (dona-operadora de gráfica pequena) | 3-25 funcionários, balcão, monitor 1280px, não-técnica | Chega o cliente pedindo "banner 3×1,5 pra sábado"; ela precisa do preço em <2min sem abrir Excel | [`Index.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md) §Persona-alvo · SPEC §2 |
| **Operador de plotter** | opera a máquina, usa o celular ao lado dela | Registra início/fim do trabalho e m² produzido | SPEC US-COMVIS-004 · `ApontamentoTracker` |
| **Coordenador de instalação** | agenda equipe e veículo | Vê a semana por pessoa e agenda sem conflito | [`Agenda.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Agenda.charter.md) |
| **Cliente da gráfica** | sem conta no sistema, quase sempre no celular | Confere o orçamento pelo link e aceita ou pede ajuste | [`AceitePublico.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/AceitePublico.charter.md) |
| **Wagner [W]** | dono do produto | Teste automatizado roda em tenant fictício 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)), **nunca biz=4** | proibicoes §R6 |

> ⚠️ **A persona operadora ainda é hipótese.** Diferente do Produto/Sells (onde a Larissa da
> ROTA LIVRE usa a tela todo dia), aqui não há registro de uso em produção. O perfil vem de pesquisa
> de mercado, não de uso observado.

---

## 3. Governança aplicável — o Tier 0 que morde AQUI

| Regra | Onde morde nesta família |
|---|---|
| **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | Toda Entity declara o escopo de `business_id` (`git grep -lE "addGlobalScope\('business_id'|EscopoPorNegocioDaSessao" -- 'Modules/ComunicacaoVisual/Entities/*.php'` lista todas). Fora de sessão (fila, token, link público) o escopo não filtra, e por isso `OrcamentoGravador`, `OrcamentoViraOs` e `AceiteDoOrcamento` passam `business_id` explícito. O `OrcamentoCalculator` resolve preço de catálogo via `Material`: se o escopo cair, um business precifica com a tabela de outro. **CU-CV-04.** |
| **REGRA MESTRE valor/estoque** ([proibicoes](../../proibicoes.md)) | Todo o `OrcamentoCalculator` é `[V0]`: área, subtotal, desconto, extras, total. É a razão de o backend ser authoritative (**CU-CV-02**). O incidente `num_uf` ×100 de 2026-06-05 é o precedente vivo. |
| **Teste nunca em biz=4** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) | `Wave27ComVisPolishTest` tem guard que varre `business_id=4` no código PHP das waves. Os contratos de outubro já usam 98 contra 99; a migração dos arquivos antigos é do lote de testes. |
| **PII / LGPD** | `observacoes` e o comentário do aceite são texto livre. O `OrcamentoCalculator` passa `observacoes` por `PiiRedactor` antes de span/log. Whitelist de `LogsActivity` exclui `contato_id` e `observacoes`. Superfícies e lacunas em [`PII-LGPD.md`](PII-LGPD.md). **CU-CV-08.** |
| **MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) | ⚠️ **Não se aplica como migração**: não há Blade de origem (§0.2). Cada tela tem charter, casos e RUNBOOK ao lado (`RUNBOOK-comunicacao-visual-index.md`, `RUNBOOK-agenda.md`, `RUNBOOK-aceite-publico.md`). |
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
        │  fetch /comunicacao-visual/api/*   (X-CSRF-TOKEN)
        ▼
Routes/web.php   ── closure com gate de permission ─→ Inertia::render('ComunicacaoVisual/Index')
        │                     props: bizName · materiais · podeCriar · podeVerCusto · orcamento · recentes (defer)
        ▼
OrcamentoController  calcular · index · store · show · update · aprovar     validação PT-BR centralizada
        │
        ▼
OrcamentoCalculator  ← AUTHORITATIVE                     OtelHelper::spanBiz + PiiRedactor
        │  resolverPreco():  input override → Material (global scope) → throw
        ▼
OrcamentoGravador → Orcamento · OrcamentoItem            business_id explícito
        ▼
OrcamentoViraOs → Os · OsItem → RoteiroPadraoDaOs → App\Contracts\Producao\ProgramacaoDaOs (Modules/Producao)

OrcamentoAceiteController + Public/OrcamentoAceitePublicoController → AceiteDoOrcamento → OrcamentoViraOs
AgendaController + InstalacaoController → AgendaInstalacao → evento InstalacaoAgendada → Modules/Whatsapp
OsPosCalculoController → PosCalculoDaOs → App\Contracts\Producao\CustoRealDaOs (Modules/Producao)
ApontamentoController → ApontamentoTracker               spool sem tela; 1 aberto por operador
```

### 5.2 Modelo de dados (núcleo entregue)

| Tabela | Papel | Chaves que importam |
|---|---|---|
| `comvis_materiais` | catálogo por business | `preco_venda_m2` alimenta o cálculo · `minimo_m2`, `perda_pct`, `largura_bobina_m`, `preco_solda_m` entram na área cobrada e na emenda · `preco_custo_m2` alimenta custo e margem · sem UNIQUE por nome |
| `comvis_orcamentos` | cabeçalho | **UNIQUE `(business_id, numero)`**, a trava real da numeração (§5.4.3) · status `rascunho`/`enviado`/`aprovado`/`recusado`/`expirado` |
| `comvis_orcamento_itens` | linha calculada | `area_m2`, `preco_unitario_m2`, `subtotal` gravados **já calculados** · `custo_previsto` e `custo_maquina_previsto` guardam o custo do dia |
| `comvis_orcamento_aceites` | link do cliente | só o `token_hash` (sha256) · `expira_em`, `decisao`, `comentario`, `decidido_em` |
| `comvis_os` / `comvis_os_itens` | OS e peças | nasce de `OrcamentoViraOs` com `status_etapa = arte` e `valor_total` do orçamento |
| `pcp_programacoes` (Producao) | etapas da OS no quadro | `os_tipo = comvis_os`, cópia do roteiro no instante |
| `cv_instalacoes` | agenda da instalação | janela, equipe e veículo por OS |
| `cv_acabamentos` | acabamentos cobrados | preço do catálogo no dia, por tipo de base |
| `comvis_apontamentos` | spool sem tela | `drift_percent` e `duracao_segundos` derivados no Service, nunca no cliente |
| `cv_substratos` / `cv_instalacoes_catalogo` / `cv_ordens_producao` | schema sem fluxo | migrados, **sem controller nem rota** (§5.4.5) |

### 5.3 Fluxos críticos

<!-- derivado: re-rodável do fonte -->

#### F1 — Abrir o hub `/comunicacao-visual`
`Routes/web.php` closure → gate `superadmin ∥ comvis.orcamento.view ∥ comvis.os.view` (senão **403**)
→ `Inertia::render('ComunicacaoVisual/Index', …)` com `bizName`, `materiais` (catálogo ativo do business,
eager de propósito), `podeCriar`, `podeVerCusto`, `orcamento` (`?orcamento={id}`) e `recentes` (deferido).

#### F2 — Calcular na tela (preview client-side)
`areaDe = arred(max(0,largura) × max(0,altura) × max(0,qtd), 3)` · `subtotalDe = arred(area × preço, 2)` ·
`totalLocal = subtotal − desconto + extras + instalação + entrega`, sem piso em zero, porque o servidor
também não tem. Qualquer edição **invalida** o resultado conferido (`setConferido(null)`). Mínimo, perda,
emenda e acabamentos só existem no servidor: o preview não os calcula.

#### F3 — Conferir no servidor (`POST /…/api/calcular`) `[V0]`
`OrcamentoController@calcular` → `validarPayload` (`largura_m`/`altura_m` `gt:0`, `quantidade min:1`,
`itens min:1`) → `OrcamentoCalculator::calcular`:
`area_m2 = round(l × a × q, 3)` · `area_cobrada_m2 = max(l × a, minimo_m2) × (1 + perda_pct/100) × q` ·
`subtotal_item = round(area_cobrada × preço, 2)` ·
`total = round(subtotal − desconto + extras + acabamentos + custo_instalacao + custo_entrega, 2)`,
tudo `PHP_ROUND_HALF_UP`. Peça maior que a bobina soma a linha "Emenda (solda)" junto dos acabamentos.
`InvalidArgumentException` → **422** com mensagem PT-BR. Para quem vê custo, `MargemDoOrcamento` só
acrescenta custo e margem: preço e total não mudam.
A tela compara `|servidor − local| < 0,01` e, divergindo, **mostra o do servidor e diz "vale este"**.

#### F4 — Resolver o preço/m² `[V0]`
`resolverPreco()`, em ordem dura: **(1)** `input.preco_unitario_m2` (override do operador; `≤0` ⇒ throw) →
**(2)** `Material::find(material_id)` **com global scope ativo** (material de outro business ⇒ "não
encontrado"; `preco_venda_m2 ≤ 0` ⇒ throw) → **(3)** throw *"obrigatório quando material_id não é informado"*.

#### F5 — Persistir orçamento (`POST`/`PUT /…/api/orcamentos`)
Recalcula (nunca confia no cliente) → `OrcamentoGravador::gravar`/`regravar` com `business_id` da sessão →
`proximoNumero()` (`ORC-{ano}-{5 dígitos}`, `MAX(numero)` filtrado por business + prefixo do ano) →
`DB::transaction` grava cabeçalho, itens e custo do dia → **201** com `itens`. A tela chama este endpoint
pelo botão "Salvar orçamento" / "Salvar alterações".

#### F6 — Apontar produção (spool)
`iniciar(osId, operadorId, orcamentoItemId?, maquina?)`: valida OS pelo global scope; **recusa
se o operador já tem apontamento aberto**; faz snapshot de `m2_orcado` a partir de
`OrcamentoItem.area_m2`. `finalizar`: `duracao_segundos` + `drift_percent = round(((prod − orc)/orc) × 100, 2)`,
**null** se `m2_orcado ≤ 0` (divisão por zero evitada). `cancelar`: `m2_produzido = 0` + prefixo
`[CANCELADO]` nas observações. Sem tela, só HTTP JSON; `PosCalculoDaOs` não o lê, para não contar o mesmo m² duas vezes.

### 5.4 Onde os dois mundos se conversam, e onde ainda não

Os itens 5.4.1, 5.4.2, 5.4.5 e 5.4.6 foram lacunas medidas em 2026-07-28. Ficam com o número original
porque testes e casos citam estes parágrafos.

#### 5.4.1 O catálogo chega à tela ✅ resolvido em 2026-09-24

Em 2026-07-28 a rota passava só `bizName`, o seletor ficava em "Sem catálogo" e a operadora digitava o
preço/m² em toda peça. O [PR #7870](https://github.com/wagnerra23/oimpresso.com/pull/7870) passou
`materiais` na closure de `Routes/web.php`. É eager de propósito: deferido, o primeiro paint mostraria
o aviso falso de catálogo vazio. **CU-CV-09.**

#### 5.4.2 A API de orçamento tem consumidor ✅ resolvido em outubro de 2026

Em 2026-07-28 `store` e `show` não tinham quem os chamasse. Hoje `Index.tsx` usa `calcular`, `store`,
`update`, `aprovar`, `link` e `arquivos` (`git grep -n "api/orcamentos" -- 'resources/js/*.tsx'`). Reabrir
e listar passam pelas props da rota, então `GET /…/api/orcamentos` e `GET /…/api/orcamentos/{id}` seguem
sem consumidor nas telas.

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

#### 5.4.5 Três entidades seguem sem fluxo ⚙️ derivado · medido 2026-11-10

Em 2026-07-28 eram cinco. `Instalacao` ganhou a agenda (`AgendaInstalacao`) e `Acabamento` entrou no
preço ([PR #9852](https://github.com/wagnerra23/oimpresso.com/pull/9852)). `Substrato`,
`InstalacaoCatalogo` e `OrdemProducao` seguem com migration, Entity, escopo e teste de isolamento, sem
controller, rota ou tela. O `FsmProcessoComunicacaoVisualSeeder` (em `database/seeders/`) cadastra o
pipeline FSM e é testado, mas **nada o executa em runtime**, e `cv_ordens_producao`, a tabela que o
consumiria, nunca recebe linha fora dos testes. Qual OS é a canônica é decisão [W] aberta (§9 D-1).

#### 5.4.6 O mínimo cobrado existe ✅ resolvido em 2026-10-26

Em 2026-07-28 `comvis_materiais` não tinha `minimo_m2`. A migration `2026_10_26_100000_add_minimo_e_acabamentos_ao_orcamento_cv`
criou a coluna, e o `OrcamentoCalculator` aplica o mínimo por peça ([PR #9852](https://github.com/wagnerra23/oimpresso.com/pull/9852), UC-CVPRC-01).

#### 5.4.7 O vertical não tem dicionário de domínio ⚠️

Não existe `memory/dominio/comunicacao-visual.md` — o módulo **não é coberto pelo `dominio-gate`**
(G-4, required). Os enums do schema (`categoria`, `unidade`, `status`) não têm fonte única.
Enquanto o módulo não tiver piloto isso é barato; no cutover vira obrigatório. §9 D-6.

### 5.5 Fluxo ponta a ponta — do orçamento ao pós-cálculo

<!-- derivado: re-rodável do fonte -->

| # | Passo | Quem | Onde está | Casos |
|---|---|---|---|---|
| 1 | **Calcular e salvar** o orçamento (`rascunho`), com peças, acabamentos, custo do dia e arquivos | operadora | `Index.tsx` · `OrcamentoController` · `OrcamentoGravador` · `OrcamentoArquivoController` | UC-CV, UC-CVPRC, UC-CVSAL, UC-CVMRG, UC-CVARQ |
| 2 | **Mandar ao cliente:** "Enviar no WhatsApp" gera o link (7 dias, uma resposta) e oferece "Copiar link" e "Abrir no WhatsApp". O envio é do WhatsApp do próprio vendedor, nada sai pelo sistema. O orçamento passa a `enviado` | operadora | `OrcamentoAceiteController` · `AceiteDoOrcamento::gerar` | UC-CVACE-01, UC-CVACE-03 |
| 3 | **Cliente decide** em `/orcamento-cv/{token}`, sem login e uma vez só. Aceitar segue para o passo 4; pedir ajuste exige texto e volta o orçamento a `rascunho` | cliente | `AceitePublico.tsx` · `AceiteDoOrcamento::decidir` | UC-CVACE-02, UC-CVACEP |
| 3b | **Aprovação interna**, alternativa ao link: "Aprovar e criar OS" | operadora | `OrcamentoController@aprovar` | UC-FLX-01 |
| 4 | **OS sem redigitar:** `comvis_os` em `status_etapa = arte`, itens, arquivos e `valor_total` do orçamento, sem recalcular. Idempotente | sistema | `OrcamentoViraOs::aprovar` | UC-CVOS-01..08 |
| 5 | **Roteiro no PCP:** com roteiro padrão definido e `Modules/Producao` ligado, as etapas entram em `pcp_programacoes` e a OS aparece em `/producao/quadro`. Sem isso, a OS nasce fora do quadro. Trocar o roteiro de uma OS à mão ainda não existe | sistema | `RoteiroPadraoDaOs` · `App\Contracts\Producao\ProgramacaoDaOs` | UC-CVOS-09..11 |
| 6 | **Produzir:** operador aponta por etapa e informa o m² impresso | operador | `Modules/Producao` (`ApontamentoOperadorService`) | casos do Producao |
| 7 | **Instalação:** agenda equipe e veículo na OS, com bloqueio de conflito; o agendamento dispara `InstalacaoAgendada` e o `Modules/Whatsapp` avisa o cliente | coordenador | `Agenda.tsx` · `InstalacaoController` · `AgendaInstalacao` · `NotifyInstalacaoCvCustomer` | UC-CVINST |
| 8 | **Faturamento: LACUNA, não é passo do fluxo.** Nenhum código liga `comvis_os` a venda, NF-e ou NFS-e: em `Services`, `Entities` e `Http` do módulo a única menção é `OrdemProducao.transaction_id`, entidade sem fluxo (§5.4.5). Se entra no roadmap é decisão [W] | — | — | — |
| 9 | **Pós-cálculo:** custo orçado × real e as duas margens, em JSON, para quem vê custo e com a flag ligada | gestor | `OsPosCalculoController` · `PosCalculoDaOs` · `App\Contracts\Producao\CustoRealDaOs` | UC-CVPOS-01..05 |

**Três costuras abertas, descritas como estão:**

- **Etapa da OS parada.** `status_etapa` nasce `arte` e nenhum código de produção a atualiza (`git grep -n "status_etapa" -- 'Modules/*.php' 'app/*.php'`: fora do seed de demonstração, só leituras). Por isso o `fechada` do pós-cálculo, que depende de `concluida`, não fica verdadeiro pelo fluxo. Quem avança a etapa e qual OS é a canônica são decisão [W] aberta.
- **Aviso de instalação.** Segue o opt-out por contato: `Contact::canReceiveWhatsappNotification()` trata `null` como permitido. O charter do módulo promete opt-in por OS; qual dos dois vale é decisão [W] aberta.
- **Dono da OS por item.** A [ADR 0436](../../decisions/0436-os-por-item-producao-dona-da-os-do-pedido.md) segue `proposto`, e a US-COMVIS-003 já se apoia nela.

---

## 6. Casos de uso

> **Derivados das 3 fontes disponíveis** (canon → código → *ausência* de Blade), nunca só do
> `.tsx` ([ADR 0351](../../decisions/0351-sdd-from-source.md) D-A). Estado vem do **veredito da
> lane**, nunca da leitura: `✅` provado por teste verde que o cita · `🟡` parcial · `🔴`
> falso/quebrado · `⬜` não-verificado · `🧪` teste existe, veredito pendente.
>
> ⚖️ **A força do veredito aqui é MAIS FRACA do que parece — leia antes de confiar.** Três perguntas
> distintas, cada uma com o seu dono:
>
> | Pergunta | Quem responde hoje |
> |---|---|
> | roda em algum lugar? | [`phpunit.xml`](../../../phpunit.xml) + [`scripts/tests/shards-plan.mjs`](../../../scripts/tests/shards-plan.mjs) (full-suite noturna, MySQL) |
> | roda no PR? | `node scripts/governance/test-lane-coverage.mjs` · listas de arquivos em [`.github/workflows/verticais-pest.yml`](../../../.github/workflows/verticais-pest.yml) (MySQL, nascida em 2026-08-10) e [`.github/ci-sqlite-pest.list`](../../../.github/ci-sqlite-pest.list) |
> | bloqueia merge? | [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) |
>
> **Fato datado, 2026-07-28:** a lane `Pest ComunicacaoVisual` do PR rodava em SQLite e 6 dos 20 arquivos
> abortavam em `markTestSkipped`, incluindo `MultiTenantTest` e `Tier0GuardTest`. O verde provava que tinham
> sido *pulados* ([proibicoes §5](../../proibicoes.md) 2026-07-24). A `verticais-pest.yml` fechou parte disso.
> Os marcadores **⏭ PR-skip** abaixo são daquela medição: o veredito atual de cada caso está no
> `Index.casos.md` e em `scripts/casos-test-results.json`.

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
3. `[V0]` `total = round(subtotal − desconto + extras + acabamentos + custo_instalacao + custo_entrega, 2, HALF_UP)`.
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
em nenhuma das entidades.

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
6. A tela chama este endpoint pelo botão "Salvar orçamento" (§5.4.2).

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
7. `[reg]` Toda Entity declara o escopo de `business_id`, e todas menos `OsItem` declaram `LogsActivity` (`git grep -L LogsActivity -- 'Modules/ComunicacaoVisual/Entities/*.php'`).

#### CU-CV-09 — A calculadora recebe o catálogo do business `[must]` 🧪 (§5.4.1; estado em `UC-CV-07` do `Index.casos.md`)
<!-- derivado: re-rodável do fonte (Routes/web.php · Index.tsx Props/escolherMaterial · ContratoTelaOrcamentoTest) -->
*Dado* um business com materiais cadastrados; *quando* a operadora abre `/comunicacao-visual`;
*então* ela escolhe o material numa lista e o preço/m² preenche sozinho — em vez de digitar
o preço de cabeça em cada peça.

1. `[must]` a página entrega à calculadora o catálogo **ativo** do business.
2. `[T0]` o catálogo entregue contém **apenas** materiais do próprio business.
3. `[must]` escolher um material preenche o preço/m² a partir do catálogo (`escolherMaterial`).
4. `[should]` sem material cadastrado, a tela continua usável com preço digitado (o aviso "sem catálogo" já existe e é bom).
5. `[should]` a copy final de salvar/WhatsApp reflete a permissão real de criar, não o default `false`.

> Em 2026-09-24 a correção escolhida foi passar a prop na closure da rota. O assert segue comportamental
> (o **nome** do material chega pra escolha), não acoplado ao nome da prop.

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
> As US abaixo estão declaradas `_pendente_` no [`SPEC.md`](SPEC.md) e ficam aqui **como inventário**,
> não como contrato. US-COMVIS-003, 005, 007, 019, 020, 021, 022 e 023 ganharam código e estão em §5.5.

| US | Capacidade | O que existe hoje |
|---|---|---|
| US-COMVIS-008 | NFSe da instalação | `Modules/NFSe` existe; o **trigger** ComVis não |
| US-COMVIS-009 | NFe de boleto pago | núcleo entregue em `RecurringBilling`; o **adapter** ComVis não existe |
| US-COMVIS-010 | Provador de orçamento público | nada (o aceite por link da US-023 é outra coisa: orçamento já feito pela gráfica) |
| US-COMVIS-011 | Comissão por OS | nada |
| US-COMVIS-012 | DAM (arquivo print-ready) | nada (arquivos anexados ao orçamento não têm validação de arte) |
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
| NFR-5 | `Inertia::defer` em prop cara | ✅ `recentes` é deferido. `materiais` é eager de propósito (§5.4.1) |
| NFR-6 | **Feedback <2min no balcão** (SPEC §3) | 🟡 o preview é instantâneo; o ida-e-volta ao servidor é 1 request. **Nunca medido com usuário real** |
| NFR-7 | **Mobile-first no apontamento** (DoD US-004) | 🟡 o apontamento por etapa é do `Modules/Producao`; o spool do módulo segue sem tela |
| NFR-8 | **Acessibilidade** | 🟡 `aria-label` nos controles densos; sem auditoria a11y da tela |

---

## 8. Estratégia de qualidade e rollout

**Fato datado, 2026-07-28** (20 arquivos à época): a fórmula pura e os guards estruturais rodavam no PR; os
arquivos HTTP + DB pulavam em SQLite. Desde então os contratos de outubro nasceram em MySQL. Em que lane
cada arquivo roda hoje é do `test-lane-coverage.mjs` e da `verticais-pest.yml` (§6).

> ⚠️ **A conclusão segue valendo:** parte da suíte é **guard estrutural** (o arquivo existe, a classe declara
> o método, o changelog tem a entrada): trava o contorno, não exercita o comportamento. Lane verde não
> significa módulo provado; o veredito por caso está no manifesto.

**Rollout:** [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) manda não
escalar além de P0 antes da 1ª piloto migrada. Este SDD **não propõe feature** — só nomeia o
contrato do que já existe.

---

## 9. Riscos e dívidas conhecidas

| # | Dívida | Gravidade | Dono |
|---|---|---|---|
| **D-1** | 3 entidades + pipeline FSM **sem fluxo** (§5.4.5) e duas noções de OS (`comvis_os` viva × `cv_ordens_producao`) | 🟡 custo de manutenção; risco de "parece pronto" | decisão [W] |
| **D-2** | Numeração de orçamento sem reserva atômica (§5.4.3) — falha visível, não silenciosa | 🟡 | técnico; vira US quando `store` ganhar consumidor |
| **D-3** | `business_id` da escrita vem da sessão, não de `auth()` (§5.4.4) — **observação, não achado** | ⚪ a confirmar | precisa varredura do `SetSessionData` antes de virar afirmação |
| **D-4** | **Sem `ANTI-REGRESSAO-*.md`** — a fonte 4 não foi destilada (§0.1) | 🟠 **pré-requisito do cutover** | [W] decide quando (nasce com a 1ª piloto) |
| **D-5** | ✅ fechada: as 3 telas têm RUNBOOK | — | — |
| **D-6** | Sem `memory/dominio/comunicacao-visual.md` → módulo fora do `dominio-gate` (G-4, required) (§5.4.7) | 🟡 barato hoje, obrigatório no cutover | técnico |
| **D-7** | Os `[T0]` não eram exercitados no PR em 2026-07-28 (§6). `MultiTenantTest` e `Tier0GuardTest` entraram na `verticais-pest.yml` | 🟡 conferir por arquivo no `test-lane-coverage.mjs` | lote de testes |
| **D-8** | ✅ fechada: `minimo_m2` existe (§5.4.6) | — | — |
| **D-9** | **Faturamento da OS não existe** (§5.5, passo 8) | 🟠 o fluxo termina sem nota | decisão [W] |
| **D-10** | `status_etapa` da OS nunca avança (§5.5) | 🟠 etapa e `fechada` do pós-cálculo não refletem a produção | decisão [W] |

---

## 10. Roadmap de evolução

Ordenado por **razão custo/risco**, não por tamanho. Nada aqui é promessa — é fila proposta.

1. **Decidir faturamento e etapa da OS** (D-9, D-10): sem isso o fluxo não fecha. **Decisão [W].**
2. **Seed tributária CNAE 1813** (CU-CV-10 item 2): barato, e é P0 no SPEC.
3. **CRUD de materiais** (CU-CV-07 item 4): sem ele o catálogo só nasce por seeder.
4. **Decidir o destino do schema sem fluxo** (D-1): construir ou marcar como reservado.
5. **`ANTI-REGRESSAO` + dicionário de domínio** (D-4/D-6): o pacote de cutover, quando a 1ª piloto for escolhida.
   quando a 1ª piloto for escolhida.

---

## 11. Referências

- [`SPEC.md`](SPEC.md) · [`BRIEFING.md`](BRIEFING.md) · [`SUPERFICIE.md`](SUPERFICIE.md)
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
| 2026-11-10 | 1.1.0 | Revisão pós-auditoria de 2026-11-05: §5.4 com as lacunas resolvidas em passado datado (#7870, #9852), §5.5 com o fluxo orçamento → aceite → OS → PCP → instalação → pós-cálculo, faturamento como lacuna declarada, §6.9 e dívidas refeitos. Nenhum CU novo: os casos seguem nos `.casos.md`. |
