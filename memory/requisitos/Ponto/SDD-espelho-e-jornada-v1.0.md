---
id: requisitos-ponto-sdd-espelho-e-jornada-v1-0
slug: ponto-sdd
title: "SDD — Espelho de Ponto e Jornada (domínio Ponto / registro eletrônico CLT)"
type: sdd
module: Ponto
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-07-27
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SUPERFICIE.md
  - UI-CATALOG.md
  - AUDIT-SENIOR-2026-05-25.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0351-sdd-from-source
related_us: [US-PONTO-001, US-PONTO-002, US-PONTO-003, US-PONTO-004, US-PONTO-005, US-PONTO-006, US-PONTO-007, US-PONTO-008, US-PONTO-009, US-PONTO-010]
---

# SDD — Espelho de Ponto e Jornada (`Modules/Ponto`)

> **Como este documento nasceu.** Derivado pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)), chip **S3 da Onda 1** do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md). É a **primeira corrida do ramo
> "SDD não existe → criar §0–§10"** — até 2026-07-27 o agent só tinha rodado com o SDD já pronto (Produto).
>
> **O SDD é do MÓDULO, nunca da tela.** O fluxo de cada tela entra como `F<n>` no §5.3; o caso de uso
> entra na numeração única `CU-PONTO-NN` do §6. Os `*.casos.md` por tela **derivam daqui** — nunca do `.tsx`.

<!-- derivado: re-rodável do fonte -->

---

## 0. Base empírica

### 0.1 As fontes cruzadas (e a que falta)

| # | Fonte | Estado nesta corrida | O que deu |
|---|---|---|---|
| 1 | **Documentação canon** | ✅ [`SPEC.md`](SPEC.md) (10 US) + 20 `*.charter.md` (todos `status: draft`) | a âncora dos CU |
| 2 | **React/Laravel atual** | ✅ 13 controllers · 20 telas Inertia · 10 entities · 8 services | o fluxo vivo → §5.3 |
| 3 | **Blade AdminLTE legada** | ✅ **26 views vivas** em `Modules/Ponto/Resources/views/` | o contrato de paridade da migração |
| 4 | **Delphi / Office Comercial** | ❌ **NÃO EXISTE** — `ANTI-REGRESSAO-*.md` só existe no módulo Produto | gap declarado, **não inventado** |

> ⚠️ **A fonte 4 é ausência declarada, não omissão.** O Ponto nasceu como *Ponto WR2* (legacy Delphi/Firebird)
> e foi **reimplementado**, não migrado tela-a-tela — não há destilado do manual legado neste repo.
> Consequência honesta: **o contrato de paridade deste módulo é mais fraco que o do Produto**. Onde a
> paridade importa, a âncora aqui é a **lei** (Portaria MTP 671/2021 + CLT), que é mais dura que qualquer
> manual — e é por isso que este módulo é um bom chip apesar da fonte faltante.

### 0.2 A vantagem deste domínio: o comportamento é fechado por LEI

Diferente do Produto (onde "o certo" é o que o Delphi fazia), aqui o certo está em texto normativo
público. Toda vez que um CU puder ancorar em lei, ele ancora — a lei não apodrece com refactor:

| Norma | O que fecha |
|---|---|
| **CLT Art. 58 §1º** | tolerância 5 min/marcação, 10 min/dia |
| **CLT Art. 59** | HE até 2h/dia · **§5º** banco de horas com validade (acordo até 6 meses) |
| **CLT Art. 66** | interjornada mínima **11h** consecutivas |
| **CLT Art. 71 §1º/§4º** | intrajornada 1h se jornada >6h; não concedida = HE + 50% |
| **CLT Art. 73** | adicional noturno 20% (22h→05h) |
| **CLT Art. 74 §2º** | registro de jornada obrigatório (>20 empregados) |
| **Portaria MTP 671/2021** Anexo I | integridade por hash encadeado, NSR, comprovante com QR |
| **Portaria MTP 671/2021** Anexo VI | **AEJ** — substitui AFDT + ACJEF |
| **Portaria MTE 1.510/2009** | AFD legacy (REP-A INMETRO), válido transitivamente |
| **LGPD Art. 7º II** | base legal do tratamento de dado de jornada |

### 0.3 A régua no início da corrida (recibo datado)

Medido em `node scripts/governance/requisitos-status.mjs Ponto` — 2026-07-27, **antes** deste PR:

| Elo | Valor |
|---|---:|
| US no SPEC | 10 |
| **CU no SDD** | **0** (este documento não existia) |
| Telas `.tsx` | 20 |
| Telas com `casos.md` | **0** |
| UC declarados | **0** |

> Número derivado de porta viva — **re-rode o comando, não edite o número**
> ([proibicoes §5](../../proibicoes.md) 2026-07-17).

<!-- curado: foto que envelhece -->

---

## 1. Visão geral

O `Modules/Ponto` é o **registro eletrônico de ponto** do oimpresso: captura marcações (REP-P web/mobile e
importação AFD de REP-A), **apura a jornada aplicando as regras CLT**, materializa o resultado dia-a-dia em
`ponto_apuracao_dia`, e entrega ao RH o **espelho de ponto** — o documento que sustenta o fechamento de
folha e a defesa em fiscalização/processo trabalhista.

Duas propriedades o separam dos demais módulos do ERP:

1. **Append-only por força de lei.** `ponto_marcacoes` não sofre UPDATE nem DELETE — nem por Eloquent,
   nem por SQL (trigger MySQL `SIGNAL SQLSTATE '45000'`). Corrigir = **criar** marcação com
   `origem=ANULACAO` apontando a original. Isto é Tier 0 e está em [proibicoes.md](../../proibicoes.md).
2. **O erro é caro e silencioso.** Um minuto perdido na apuração vira verba trabalhista; uma divergência
   de jornada **não exibida** vira folha fechada errada. Por isso os CU de jornada nascem `[V0]`.

### 1.1 Família de telas (20 · medidas na árvore)

| Grupo | Telas | Papel |
|---|---|---|
| **Espelho** | `Espelho/Index` · `Espelho/Show` | ⭐ coração — seleção + espelho mensal |
| **Intercorrências** | `Intercorrencias/{Index,Create,Show}` | justificativa de ausência/ajuste |
| **Aprovações** | `Aprovacoes/Index` | fila de decisão hierárquica |
| **Banco de horas** | `BancoHoras/{Index,Show}` | saldo + ledger append-only |
| **Importações** | `Importacoes/{Index,Create,Show}` | AFD/AFDT de REP-A |
| **Cadastro** | `Colaboradores/{Index,Edit}` · `Escalas/{Index,Form}` | quem controla ponto, sob qual escala |
| **Config** | `Configuracoes/{Index,Reps}` | parâmetros CLT + REPs |
| **Painéis** | `Dashboard/Index` · `Relatorios/Index` · `Welcome` | visão geral + catálogo de relatórios |

---

## 2. Público-alvo e personas

| # | Persona | Contexto | O que exige do módulo |
|---|---|---|---|
| **P1** | **Wagner [W]** — WR2 SC (**biz=1**) | operador-dono, time CLT real, **cliente piloto do módulo** | apuração que ele possa conferir à mão; espelho que ele assine |
| **P2** | **RH / DP** de empregador CLT | fecha folha mensalmente | espelho fiel, divergências **visíveis**, PDF imprimível |
| **P3** | **Colaborador** | registra e consulta a própria jornada | comprovante verificável (US-PONTO-010, backlog) |
| **P4** | **Auditor MTE** | fiscalização | AFD/AEJ íntegro, hash encadeado, imutabilidade |
| **P5** | **Eliana [E]** (advogada) | revisão jurídica | a lei aplicada certa — **revisão obrigatória** antes do AEJ (US-PONTO-009) |

> ⚠️ **Larissa/ROTA LIVRE (biz=4) NÃO é persona deste módulo.** O SPEC é explícito: <20 empregados,
> Art. 74 §2º CLT **desobriga** o registro. Piloto é **biz=1** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

<!-- curado: foto que envelhece -->

---

## 3. Governança aplicável

### 3.1 Tier 0 — IRREVOGÁVEL

| Invariante | Onde vive | Consequência de violar |
|---|---|---|
| **Append-only legal** de `ponto_marcacoes` | trigger MySQL + override em `Marcacao` | registro perde valor probatório (Portaria 671/2021) |
| **Append-only** do ledger `ponto_banco_horas_movimentos` | override em `BancoHorasMovimento` | saldo deixa de ser auditável |
| **Multi-tenant** `business_id` | `HasBusinessScope` em **9 de 10** entities | jornada de um empregador vaza para outro (LGPD + sigilo trabalhista) |
| **Testes em biz=1, nunca em cliente** | [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) | poluir base de cliente real |
| **PII**: CPF, PIS, selfie, geolocalização | `PiiRedactor`; `selfie_base64` **nunca** logada | LGPD Art. 7º; repo é público |

### 3.2 A marca `[V0]` neste módulo

A REGRA MESTRE de [proibicoes.md](../../proibicoes.md) fala em **valor ou estoque**. Aqui o análogo de
valor é o **minuto de jornada**: HE, atraso, falta e saldo de banco de horas viram dinheiro na folha.
**Todo CU que altere ou exiba minutos apurados nasce `[V0]`** — dupla confirmação por 2 caminhos e tabela
antes→depois antes de qualquer correção de cálculo.

### 3.3 Nomenclatura legacy preservada

O módulo foi renomeado `PontoWr2 → Ponto` em 2026-05-06 (**rename PHP-only**). Seguem legacy **de propósito**:
URLs `/ponto/*` com permissions `pontowr2.*`, namespace de views `pontowr2::`, config `pontowr2.clt.*`.
Não "corrigir" isso sem ADR — ver [`SCOPE.md`](../../../Modules/Ponto/SCOPE.md).

---

## 4. Design system aplicável

- **Shell:** AppShellV2 + PageHeader ([ADR 0182](../../decisions/)); sidebar preta dark-fixo (UI-0023).
- **Padrão de tela:** `Espelho/Index`, `Intercorrencias/Index`, `BancoHoras/Index`, `Importacoes/Index`
  e `Aprovacoes/Index` herdam **PT-01 Lista**. `Espelho/Show` é **bespoke** (heatmap + totalizadores +
  tabela dia-a-dia) e o charter declara isso.
- **Componentes locais:** `_components/{MonthHeatmap,PresenceStrip,AlertInbox,ActivityFeed}.tsx` +
  `_shared/{PontoSubNav,PontoPrimaryButton}.tsx`.
- **`Inertia::defer` é o default** do módulo ([RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)):
  toda prop com `paginate()`/`sum()`/loop de 31 dias vira closure lazy; filtros de UI ficam eager.

<!-- curado: foto que envelhece -->

---

## 5. Arquitetura

<!-- derivado: re-rodável do fonte -->

### 5.1 Visão em camadas

```
Inertia/React  resources/js/Pages/Ponto/**            20 telas
      ↓ props (defer nas caras)
Controller     Modules/Ponto/Http/Controllers/**      13 controllers
      ↓
Service        Modules/Ponto/Services/**              ApuracaoService · MarcacaoService · BancoHorasService
                                                     IntercorrenciaService · AfdParserService · ReportService
                                                     NsrService · MobileMarcacaoService · IntercorrenciaAIClassifier
      ↓
Entity         Modules/Ponto/Entities/**              10 models (9 com HasBusinessScope)
      ↓
MySQL          ponto_*                                trigger append-only em ponto_marcacoes
```

**Roteamento:** um único `Modules/Ponto/Http/routes.php` — grupo web `prefix=ponto` com a stack
UltimatePOS + `ponto.access`; grupo API `prefix=ponto/api` sob `auth:api` (**hoje 8 rotas em `abort(501)`**);
grupo `ponto/install`.

### 5.2 Modelo de dados (núcleo)

| Tabela | Append-only | Scope | Observação |
|---|---|---|---|
| `ponto_marcacoes` | **sim** (trigger + Eloquent) | ✅ | hash SHA-256 encadeado + NSR único por REP |
| `ponto_banco_horas_movimentos` | **sim** (Eloquent) | ✅ | ⚠️ **sem trigger DB** — lacuna registrada no SPEC |
| `ponto_apuracao_dia` | recalculável | ✅ | `unique(colaborador_config_id, data)` · `estado` enum · `divergencias` JSON |
| `ponto_banco_horas_saldo` | não | ✅ | saldo corrente |
| `ponto_intercorrencias` | não (workflow) | ✅ | SoftDeletes · 6 estados |
| `ponto_escalas` / `ponto_escalas_turnos` | não | ✅ / ⚠️ | **`EscalaTurno` é a única entity SEM `HasBusinessScope`** |
| `ponto_colaborador_config` · `ponto_reps` · `ponto_importacoes` | não | ✅ | — |

**Campos de `ponto_apuracao_dia` que a lei produz** (medidos na migration
`2026_04_18_000006_create_ponto_apuracao_dia_table.php`): `atraso_minutos`, `saida_antecipada_minutos`,
`falta_minutos`, `he_diurna_minutos`, `he_noturna_minutos`, `adicional_noturno_minutos`,
`dsr_repercussao_minutos`, **`interjornada_violacao_minutos`** (Art. 66), **`intrajornada_violacao_minutos`**
(Art. 71), `banco_horas_credito_minutos`, `banco_horas_debito_minutos`, `estado` (enum, inclui
`DIVERGENCIA`), `divergencias` (JSON).

### 5.3 Fluxos críticos

#### F1 · Selecionar colaborador e mês → `Espelho/Index`

`Espelho/Index.tsx` → `GET /ponto/espelho` → `EspelhoController@index`
→ `Colaborador::where('business_id')->where('controla_ponto', true)->whereNull('desligamento')->paginate(25)`.

- `colaboradores` é `Inertia::defer`; `mes` é eager (estado de UI).
- **Paridade com a Blade** (`espelho/index.blade.php`): a Blade exibia a coluna **"Controla ponto" (Sim/Não)**
  — no React a coluna sumiu, mas **sem perda de contrato**: a query já filtra `controla_ponto = true`, logo
  a coluna era constante "Sim". Perda **cosmética**, não funcional.
- **Divergência real:** a busca por matrícula/nome/CPF está `disabled` ("em breve") no React; a Blade também
  não tinha busca. **Não é regressão** — é lacuna herdada.

#### F2 · Ler o espelho mensal → `Espelho/Show` ⭐

`Espelho/Show.tsx` → `GET /ponto/espelho/{colaborador}?mes=YYYY-MM` → `EspelhoController@show`
→ cabeçalho eager via `Colaborador::where('business_id')->findOrFail()` (valida tenant **antes** de tudo)
→ `totais` = `EspelhoController::buildTotaisEspelho()` (9 agregações sobre `ApuracaoDia`)
→ `linhas` = `EspelhoController::buildLinhasEspelho()` (1 linha por dia do mês + marcações do dia,
   filtrando `origem = ANULACAO`).

> 🔴 **F2 carrega o achado central desta corrida — ver §9 D-1.** O sinal de divergência que a tela
> renderiza é lido de um atributo **inexistente**, então nunca acende.

#### F3 · Imprimir o espelho → PDF

`Espelho/Show.tsx` (botão) → `GET /ponto/espelho/{id}/imprimir?mes=` → `EspelhoController@imprimir`
→ `ReportService::espelhoPdf()` → view `pontowr2::reports.espelho-pdf` → `stream()` inline.

- É **o único relatório implementado** do módulo (ver F8).
- O tenant é validado igual ao F2 (`Colaborador::where('business_id')->findOrFail()`).

#### F4 · Registrar e submeter intercorrência

`Intercorrencias/Create.tsx` → `POST /ponto/intercorrencias` → `IntercorrenciaController@store`
→ `IntercorrenciaService::criar()` → estado `RASCUNHO`
→ `POST /ponto/intercorrencias/{id}/submeter` → `IntercorrenciaService::submeter()` → `PENDENTE`.

Ciclo canônico: `RASCUNHO → PENDENTE → APROVADA|REJEITADA → APLICADA`, com `CANCELADA` como saída.
Apoio opcional de IA: `POST /ponto/intercorrencias-ai/classify` (`throttle:10,1`) →
`IntercorrenciaAIClassifier::classificar()` sugere tipo/prioridade a partir de texto livre —
**sugere, nunca decide** (o estado só muda por ação humana).

#### F5 · Decidir a intercorrência → `Aprovacoes/Index`

`Aprovacoes/Index.tsx` → `GET /ponto/aprovacoes` → `AprovacaoController@index`
→ `buildAprovacoesPagina()` (filtro `estado`/`tipo`/`prioridade`, ordem `FIELD(prioridade,'URGENTE','NORMAL')`)
+ `buildContagensEstado()` (6 buckets), ambos `Inertia::defer`.

Ações: `POST .../{id}/aprovar` · `.../{id}/rejeitar` (**exige `motivo`**, `required|string|max:500`) ·
`POST .../lote` (`ids` array de uuid) → `IntercorrenciaService::{aprovar,rejeitar,aprovarEmLote}`.

- **Isolamento:** os handlers usam `Intercorrencia::findOrFail($id)` **sem** `where('business_id')` — a
  defesa é o **global scope** `HasBusinessScope`. Funciona, mas é **defesa única**: se o trait sair da
  entity, os 3 handlers passam a decidir intercorrência de outro tenant. Por isso vira CU `[T0]` com teste.

#### F6 · Consultar banco de horas → `BancoHoras/{Index,Show}`

`BancoHoras/Index` → `BancoHorasController@index` → `buildSaldosPagina()` (30/pág, `orderByDesc(saldo_minutos)`)
+ `buildTotaisSaldos()` (4 agregações), ambos defer.
`BancoHoras/Show` → `BancoHorasSaldo::where('colaborador_config_id')->firstOrFail()` (tenant pelo global scope)
+ `buildMovimentosPagina()` (ledger, 50/pág, `orderByDesc(created_at)`).
Ajuste: `POST /ponto/banco-horas/{id}/ajuste` (`minutos` int + `observacao` obrigatória)
→ `BancoHorasService::ajustarManual()` → **novo movimento** no ledger (nunca edição do anterior).

#### F7 · Importar AFD/AFDT → `Importacoes/{Index,Create,Show}`

`Importacoes/Create` → `POST /ponto/importacoes` (`ImportacaoAfdRequest`) → `ImportacaoController@store`:
1. `hash_file('sha256')` do upload;
2. **dedup por hash dentro do business** → se já existe, volta com erro citando data + id (idempotência);
3. `store()` em `local` sob `ponto/importacoes/{businessId}` (**storage segregado por tenant**);
4. `Importacao::create(...)`;
5. `ProcessarImportacaoAfdJob::dispatch($businessId, $importacao->id)` — **`$businessId` no construtor**,
   conforme [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (fila não tem `session()`).

`Importacoes/Show` acompanha `estado`, `linhas_processadas/criadas/ignoradas`, `erro_mensagem` e o
`hash_arquivo`. `GET .../{id}/original` baixa o arquivo original (prova de origem para auditoria).

#### F8 · Catálogo de relatórios → `Relatorios/Index`

`RelatorioController@index` devolve **8 relatórios**, cada um com flag `disponivel`. Medido no fonte:
**apenas `espelho` tem `disponivel: true`**; os outros 7 (`afd`, `afdt`, `aej`, `he`, `banco-horas`,
`atrasos`, `esocial`) são `false`. `RelatorioController@gerar()` é `abort(501)` **para qualquer chave** —
inclusive `espelho` (o PDF do espelho sai por F3, não por aqui).

### 5.4 Onde os dois mundos ainda não se conversam

| # | Dívida | Evidência |
|---|---|---|
| **1** | **A tela de EDIÇÃO de intercorrência ainda é Blade.** `Route::resource` expõe `GET /ponto/intercorrencias/{id}/edit` → `IntercorrenciaController@edit` → `view('pontowr2::intercorrencias.edit')`. **Não existe `Intercorrencias/Edit.tsx`.** O operador que clica "editar" num rascunho **sai do shell React e cai no AdminLTE**. Varredura contada: **21 renders nos controllers = 20 Inertia + 1 Blade**. | `IntercorrenciaController.php` (`edit`) |
| **2** | **26 Blade legadas seguem no repo** com 1 rota viva (a #1). As outras 25 são **fósseis** — não alcançáveis, mas ainda lidas por quem procura "como a tela faz". | `Modules/Ponto/Resources/views/` |
| **3** | **API REP-P inteira em `abort(501)`** — 8 rotas. US-PONTO-001 depende disso e está `_parcial_`. | `routes.php` grupo `ponto/api` |
| **4** | **`Relatorios/Index` promete 8, entrega 0 por ali** (F8). O catálogo é honesto (`disponivel: false`), mas `gerar()` é 501 até para o espelho. | `RelatorioController` |
| **5** | **`EscalaTurno` sem `HasBusinessScope`** — única das 10 entities. | `Modules/Ponto/Entities/EscalaTurno.php` |
| **6** | **Ledger de banco de horas sem trigger DB** — só override Eloquent. SQL cru ainda edita. | SPEC §Tabelas canon |

---

## 6. Casos de uso

> **Numeração única do módulo.** Varredura sem corte em `memory/`, `resources/js/Pages/Ponto/` e
> `Modules/Ponto/` retornou **zero** ids `CU-PONTO-*`/`UC-PONTO-*` pré-existentes — esta é a alocação
> inicial, começando em `01`. Nenhum id foi pulado.
>
> Marcadores: `[T0]` multi-tenant · `[V0]` minuto de jornada = valor · `[must]`/`[should]`.
> Status: ⬜ não verificado · 🧪 teste cita o UC, sem veredito · ✅ verde na lane · ❌ vermelho.

### 6.1 Espelho de ponto (`CU-PONTO-01..04`)

#### CU-PONTO-01 — Ler o espelho mensal de um colaborador `[must]` ⬜
Dado um colaborador do meu business e um mês de referência, o espelho apresenta **todos os dias do mês**
(inclusive os sem marcação), com as marcações do dia e a apuração consolidada.
**Âncora:** CLT Art. 74 §2º (registro fidedigno) · Blade `espelho/show.blade.php` (paridade) · F2.

#### CU-PONTO-02 — Sinalizar dia com divergência de apuração `[must]` `[V0]` ❌
Dado um dia cuja apuração violou regra CLT (interjornada Art. 66, intrajornada Art. 71 §4º, HE Art. 59),
o espelho **destaca esse dia** e informa **quantos** dias do mês estão divergentes.
**Âncora:** US-PONTO-005 (aceitação lista Art. 66 e 71) · `ApuracaoService::addDivergencia()` grava
`divergencias[]` e promove `estado = DIVERGENCIA` · Blade legada contava por `estado === 'DIVERGENCIA'`.
**Estado:** ❌ **quebrado hoje** — ver §9 D-1. O RH fecha folha sem enxergar a violação.

#### CU-PONTO-03 — Imprimir o espelho em PDF `[must]` ⬜
O espelho exibido tem versão imprimível, com os **mesmos números** da tela.
**Âncora:** F3 · charter `Espelho/Show` §Pendências ("paridade de números PDF vs tela").

#### CU-PONTO-04 — Escolher colaborador e mês sem recarregar a página `[should]` ⬜
A lista traz só colaboradores **ativos com controle de ponto**; trocar de mês faz recarga parcial.
**Âncora:** F1 · charter `Espelho/Index` §Goals/§Automation hooks.

### 6.2 Intercorrência e aprovação (`CU-PONTO-05..07`)

#### CU-PONTO-05 — Registrar intercorrência e submeter à decisão `[must]` ⬜
A intercorrência nasce `RASCUNHO`, só vai a `PENDENTE` por ação explícita, e **só rascunho é editável**.
**Âncora:** US-PONTO-003 (estados canon) · F4 · `IntercorrenciaController@edit` (`abort_unless RASCUNHO`).

#### CU-PONTO-06 — Aprovar ou rejeitar deixando trilha `[must]` ⬜
Aprovar registra aprovador e momento; **rejeitar exige motivo** (a rejeição sem justificativa é recusada).
**Âncora:** US-PONTO-003 (aceitação: `aprovador_id`, `aprovado_em`, `motivo_rejeicao`) · F5.

#### CU-PONTO-07 — Decidir em lote sem atravessar tenant `[must]` `[T0]` ⬜
Aprovação em lote só afeta intercorrências do **meu** business, mesmo que a lista de ids inclua outras.
**Âncora:** US-PONTO-007 · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · F5
(hoje defendido **só** pelo global scope).

### 6.3 Banco de horas (`CU-PONTO-08..09`)

#### CU-PONTO-08 — Consultar saldo e extrato `[must]` `[V0]` ⬜
Saldo do colaborador + ledger de movimentos em ordem cronológica reversa, com tipo e observação.
**Âncora:** US-PONTO-004 · CLT Art. 59 §5º · F6.

#### CU-PONTO-09 — Ajustar saldo **acrescentando** movimento, nunca editando `[must]` `[V0]` `[T0]` ⬜
Ajuste manual exige observação e cria **novo** movimento; movimento existente **não** pode ser alterado
nem apagado (o extrato é prova).
**Âncora:** US-PONTO-008 (append-only) · [proibicoes.md](../../proibicoes.md) · F6.

### 6.4 Importação AFD (`CU-PONTO-10..11`)

#### CU-PONTO-10 — Importar o mesmo arquivo 2× não duplica marcação `[must]` ⬜
Idempotência por `sha256` do conteúdo, **escopada ao business** — o mesmo arquivo pode ser importado por
outro empregador sem colisão.
**Âncora:** US-PONTO-002 (aceitação: "importação idempotente") · F7.

#### CU-PONTO-11 — Acompanhar o resultado e reter o original `[must]` ❌
A importação mostra estado, **contagens fiéis ao que foi processado**, erro quando houver, e permite
baixar o arquivo original com o hash exibido.
**Âncora:** US-PONTO-002 (*"registra arquivo + checksum + linhas processadas + erros"*) ·
Portaria 671/2021 Anexo I (rastreabilidade) · F7.
**Estado:** ❌ **quebrado hoje** — ver §9 D-8: as contagens de criadas/ignoradas exibem 0 sempre.

### 6.5 Invariantes transversais (`CU-PONTO-12..14`)

#### CU-PONTO-12 — Nenhuma tela do Ponto expõe dado de outro empregador `[must]` `[T0]` ⬜
Abrir por id um recurso de outro business responde **404** — nunca 200 com dado, nunca 500.
**Âncora:** US-PONTO-007 · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · LGPD Art. 7º.

#### CU-PONTO-13 — Marcação gravada não muda nem some `[must]` `[T0]` ⬜
UPDATE/DELETE em `ponto_marcacoes` falha nas duas camadas (Eloquent + trigger). Correção é `ORIGEM_ANULACAO`,
e marcação anulada **não** aparece no espelho.
**Âncora:** US-PONTO-008 · Portaria 671/2021 · `EspelhoController::buildLinhasEspelho` (`whereNotIn ANULACAO`).

#### CU-PONTO-14 — O catálogo de relatórios não promete o que não entrega `[should]` ⬜
Relatório ainda não implementado aparece marcado como indisponível; nenhum botão leva a erro 501 sem aviso.
**Âncora:** F8 · US-PONTO-006/009 (`_pendente_` no SPEC).

### 6.6 Non-goals explícitos (por design — **[W] é o único que altera esta lista**)

- ❌ **Folha de pagamento** — handoff via eSocial (SPEC §Non-Goals).
- ❌ **Biometria própria** — REP-P certificado de terceiros.
- ❌ **Substituir REP-A homologado INMETRO** — o módulo importa, não substitui.
- ❌ **Editar marcação** — é ilegal, não é feature faltante (Portaria 671/2021).
- ❌ **Recalcular apuração a partir do espelho** — o espelho **lê** `ApuracaoDia`; quem calcula é o
  `ApuracaoService` (charter `Espelho/Show` §Non-Goals).

> ⚠️ **Os 20 charters do módulo estão `status: draft`** e cada um traz o aviso *"Wagner aprova Non-Goals +
> Anti-hooks ANTES de virar `status: live`"*. Vários Non-Goals trazem literalmente *"(inferência pendente
> de Wagner)"*. **Este SDD não promoveu nenhuma inferência a lei** — os CU acima derivam de SPEC, lei e
> Blade legada; onde só havia inferência de charter, virou `[BACKLOG]` no `casos.md` da tela.

---

## 7. Requisitos não-funcionais

| Dimensão | Alvo | Como é sustentado hoje |
|---|---|---|
| **Latência** | p95 < 1500ms (admin) | `Inertia::defer` em toda prop cara (todos os charters) |
| **Volume** | mês × colaborador = até 31 linhas + N marcações | paginação 20–50 por tela |
| **Integridade** | hash SHA-256 encadeado + NSR único por REP | `MarcacaoService` + `NsrService` + `unique(rep_id, nsr)` |
| **Imutabilidade** | append-only em 2 camadas | trigger MySQL + override Eloquent |
| **Isolamento** | `business_id` em toda query de negócio | `HasBusinessScope` (9/10 entities) |
| **Privacidade** | CPF/PIS/selfie/geo nunca em log ou PR | `PiiRedactor`; `selfie_base64` proibida em log (SCOPE.md) |
| **Retenção** | arquivo AFD original preservado | `ponto/importacoes/{businessId}` no disco `local` |

---

## 8. Estratégia de qualidade e rollout

### 8.1 Testes — onde eles realmente rodam (as 3 portas, medidas)

| Pergunta | Porta | Resposta medida (2026-07-27) |
|---|---|---|
| roda em algum lugar? | `phpunit.xml` | ✅ `./Modules/Ponto/Tests/Feature` **e** `/Unit` estão na testsuite → os **29 arquivos** rodam na suíte completa (shards/nightly CT100) |
| roda no PR? | allowlist de [`ponto-pest.yml`](../../../.github/workflows/ponto-pest.yml) | ⚠️ **1 arquivo só** — `Wave27CrossTenantEscalaTest.php`. Os outros 28 **não** rodam por PR |
| **bloqueia merge?** | [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) | ❌ **NÃO** — `PHP / Pest (Ponto · MySQL)` **não está** na lista de required (as únicas lanes Pest required são Financeiro, NfeBrasil e Unit). A lane é **advisory**: fica vermelha visível, **não trava o merge** |

> Isto responde à pergunta certa com a porta certa: *"esse teste roda?"* **não** se responde com `grep`
> em workflow (classe LC-08). Cada linha acima cita **qual** porta foi medida.

**Desenho da lane:** `pull_request` sem `paths` (always-run) + `dorny/paths-filter` interno = *skip-as-pass*
([ADR 0271](../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) onda 2) —
required-ready sem deadlock. A allowlist é **catraca**: cada teste MySQL-only novo entra ali.
⚠️ A lane proíbe `RefreshDatabase`/`migrate:fresh` (dropam o schema e limpam o seed biz=1).

### 8.2 Rollout

Piloto **biz=1 (WR2, time CLT real)**; cliente externo só depois do AEJ (US-PONTO-009), que exige
**revisão da Eliana [E] + ADR formal antes de codar** — o SPEC marca isso como pré-requisito duro.

---

## 9. Riscos e dívidas conhecidas

### D-1 · 🔴 O espelho nunca sinaliza divergência de apuração — **regressão da migração Blade→React**

**O que foi medido** (varredura contada, repo inteiro, sem `head_limit`):

| Fato | Recibo |
|---|---|
| `tem_divergencia` aparece **2 vezes no repo inteiro** | ambas em `EspelhoController` (montagem de `totais` e de `linhas`) |
| **Não é coluna** | `2026_04_18_000006_create_ponto_apuracao_dia_table.php` tem `estado` (enum com `DIVERGENCIA`) e `divergencias` (JSON) — **não** tem `tem_divergencia` |
| **Não é accessor nem `$appends`** | `ApuracaoDia.php` — busca por accessor/`Attribute`/`appends` retorna vazio |
| **Não é `$fillable`** | idem |
| **Quem grava a verdade** | `ApuracaoService`: `$temDivergencia = count($apuracao->divergencias) > 0` → `estado = ESTADO_DIVERGENCIA` |
| **O que a Blade fazia** | `espelho/show.blade.php`: conta por `$ap->estado === 'DIVERGENCIA'` e pinta a linha `bg-warning` |

**Consequência:** `$a->tem_divergencia` resolve `null` → o contador de divergências do mês é **0 sempre**
e o realce da linha é **false sempre**. O `Show.tsx` só mostra o alerta `t.divergencias > 0`, então
**o aviso nunca aparece** — mesmo quando a apuração detectou violação de **interjornada (Art. 66)** ou
**intrajornada (Art. 71 §4º)**. A Blade legada mostrava. **A migração perdeu a feature em silêncio** —
exatamente a classe de regressão que a triangulação de 3 fontes existe para pegar.

**Duas correções possíveis, ambas legítimas** — por isso o teste do CU-PONTO-02 é escrito contra o
**comportamento** ("o dia divergente aparece sinalizado"), não contra a chave literal:
(a) expor `tem_divergencia` como accessor derivado de `estado`; **ou** (b) o controller passar a ler
`estado === DIVERGENCIA` (o que restaura a paridade com a Blade). Assert por chave reprovaria uma delas
arbitrariamente.

**Decisão é do [W]** — este SDD registra o achado e o teste; não corrige o código.

### D-2 · 🟠 `saida_antecipada_minutos` sumiu dos totalizadores

A Blade somava **9** totalizadores (incluindo saída antecipada); o React soma **8 + contador de
divergência** e **não** expõe saída antecipada — embora o `ApuracaoService` a calcule (e a debite do banco
de horas: `banco_horas_debito += falta + saida_antecipada`). O RH vê o débito no saldo sem ver a causa.

### D-3 · 🟠 Art. 66 e Art. 71 não têm superfície própria

`interjornada_violacao_minutos` e `intrajornada_violacao_minutos` são **calculados e gravados**, mas
**nenhuma tela os exibe** (varredura: só `ApuracaoDia` e `ApuracaoService` os tocam). Hoje eles só
chegariam ao RH pelo sinal de divergência — que é justamente o que está quebrado (D-1). **Somados, D-1 e
D-3 significam que as duas violações mais caras da CLT são invisíveis na UI.**

### D-4 · 🟠 A edição de intercorrência ainda é Blade dentro de app React

Ver §5.4 #1. Rota viva, sem `.tsx`, sem charter, sem casos — e portanto **fora** da conta de 20 telas da
porta viva (a régua conta `.tsx`; uma tela servida por Blade é invisível para ela).

### D-5 · 🟡 Isolamento por defesa única em 3 handlers de decisão

`AprovacaoController@{aprovar,rejeitar}` e os handlers de `IntercorrenciaController` usam `findOrFail`
cru. Correto **hoje** (global scope), mas sem teste que o prove. CU-PONTO-07/12 fecham isso.

### D-6 · 🟡 `EscalaTurno` sem `HasBusinessScope` · ledger sem trigger DB

Ver §5.4 #5 e #6.

### D-8 · 🔴 A importação sempre exibe "0 marcações criadas" — **a mesma classe do D-1, reincidindo**

**O que foi medido** (varredura contada, sem `head_limit`):

| Fato | Recibo |
|---|---|
| `linhas_criadas` / `linhas_ignoradas` aparecem **9 vezes** | **3** em `ImportacaoController` (`index` e `show`) + **6** consumindo no front (`Importacoes/Index.tsx`, `Importacoes/Show.tsx`) |
| **Não são colunas** | `create_ponto_importacoes_table` tem `linhas_total`, `linhas_processadas`, **`linhas_sucesso`**, **`linhas_erro`** |
| **Não estão no `$fillable`** | `Importacao.php` lista `linhas_total`, `linhas_processadas`, `linhas_sucesso`, `linhas_erro` |
| O mascaramento | o controller usa `(int) ($i->linhas_criadas ?? 0)` — o `?? 0` **esconde** o campo ausente |

**Consequência:** `Importacoes/Show` renderiza *"Marcações criadas: 0"* e *"Linhas ignoradas: 0"* para
**toda** importação, inclusive as 100% bem-sucedidas; `Importacoes/Index` mostra `0/N`. O RH pergunta
"entrou tudo?" e a tela responde zero.

> 🔎 **Isto não é um segundo bug isolado — é um PADRÃO.** D-1 e D-8 são a mesma falha de forma: **o
> controller lê um atributo que o modelo não tem**, e a linguagem esconde (`null → false`, `?? 0`).
> Aconteceu em **2 das 8 famílias de tela** do módulo. Vale uma varredura dirigida no restante
> (`Dashboard`, `Colaboradores`, `Escalas`, `Configuracoes`) antes de fechar o módulo — **não fiz nesta
> corrida** e declaro como pendência, não como "verificado".

### D-7 · 🟡 Compliance regulatório em aberto

US-PONTO-009 (AEJ, Anexo VI) e US-PONTO-010 (comprovante QR) seguem `_pendente_`. **Não geram CU agora** —
US sem código vira UC órfão e o `casos-gate` G-2 pune ([proibicoes §5](../../proibicoes.md) 2026-07-16).

---

## 10. Roadmap de evolução

| Onda | Item | Por quê nesta ordem |
|---|---|---|
| **1** | Corrigir **D-1** (sinal de divergência) e **D-8** (contagens da importação) | mesma classe de defeito (atributo fantasma), correção barata, e destrava o valor de dado que já existe no banco |
| **1** | **Varrer as 4 famílias de tela não auditadas** atrás de outros atributos fantasma | D-1 e D-8 apareceram em 2 de 8 famílias — o padrão pede varredura, não amostra |
| **1** | Expor **D-3** (Art. 66/71) e **D-2** (saída antecipada) no espelho | os minutos já existem no banco; falta superfície |
| **2** | Fechar **D-4** (`Intercorrencias/Edit.tsx`) | remove a última Blade viva e torna o módulo 100% React |
| **2** | `EscalaTurno` + trigger no ledger (**D-6**) | fecha as 2 lacunas Tier 0 conhecidas |
| **3** | **US-PONTO-009 AEJ** (após revisão [E] + ADR) | prioridade regulatória #1 do audit sênior |
| **3** | US-PONTO-001 REP-P real (8 rotas 501) + US-PONTO-010 comprovante QR | depende da cadeia de assinatura da onda 3 |
| **4** | Promover a lane `ponto-pest` a required | só com mordida provada ([ADR 0336](../../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)) — **não** antes |

---

## 11. Referências

- [`SPEC.md`](SPEC.md) · [`BRIEFING.md`](BRIEFING.md) · [`SUPERFICIE.md`](SUPERFICIE.md) · [`AUDIT-SENIOR-2026-05-25.md`](AUDIT-SENIOR-2026-05-25.md)
- [`Modules/Ponto/SCOPE.md`](../../../Modules/Ponto/SCOPE.md)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) · [ADR 0351](../../decisions/0351-sdd-from-source.md)
- [`passo-5-sdd-por-modulo.md`](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md)
- Legislação: CLT Art. 58/59/66/71/73/74 · Portaria MTP 671/2021 (Anexos I e VI) · Portaria MTE 1.510/2009 · LGPD Art. 7º II · CF/88 Art. 7º XVI

---

## Changelog

| Versão | Data | O que mudou |
|---|---|---|
| 1.0.0 | 2026-07-27 | Nascimento. Chip S3 da Onda 1 do passo 5, agent `sdd-from-source` — **1ª corrida do ramo "SDD do zero"**. §5.3 com 8 fluxos, §6 com 14 CU (alocação inicial, nenhum id pulado). Dois achados da **mesma classe** (atributo fantasma lido pelo controller), ambos com varredura contada: **D-1** (sinal de divergência, 2/2 ocorrências) e **D-8** (contagens da importação, 9/9). Fonte 4 (Delphi) declarada **ausente**. Cobertas 6 de 20 telas com contrato real — as 14 restantes declaradas no session log, não stubadas. |
