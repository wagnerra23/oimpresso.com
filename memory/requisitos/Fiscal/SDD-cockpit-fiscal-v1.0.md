---
id: requisitos-fiscal-sdd-cockpit-fiscal-v1-0
slug: fiscal-sdd
title: "SDD — Cockpit Fiscal unificado (domínio Fiscal)"
type: sdd
module: Fiscal
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-07-27"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - CAPTERRA-FICHA.md
  - CAPTERRA-INVENTARIO.md
  - AUDIT-SENIOR-2026-05-25.md
  - SUPERFICIE.md
  - PLANO-TESTES-FISCAL.md
  - RUNBOOK-cockpit.md
  - RUNBOOK-nfe.md
  - RUNBOOK-nfse.md
  - RUNBOOK-dfe.md
  - RUNBOOK-eventos.md
  - RUNBOOK-config.md
  - RUNBOOK-sped.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0321-pin-sped-nfe-dev-master-ibs-cbs
  - 0351-sdd-from-source
---

# SDD — Software Design Document · Cockpit Fiscal unificado (domínio Fiscal)

> **Escopo:** as **7 telas** de `/fiscal/*` (`Cockpit · Nfe · Nfse · Dfe · Eventos · Config · Sped`),
> o `AcoesController` (mutações) e o `PaletteSearchController` (⌘K). Este SDD **não substitui**
> o [SPEC.md](SPEC.md) (`US-FISCAL-NNN`) nem os 7 charters — ele é o mapa de cima e é **de onde
> o UC dos `casos.md` deriva** (nunca do `.tsx`, [ADR 0351](../../decisions/0351-sdd-from-source.md) D-A).
>
> **Documento-modelo:** [SDD-TEMPLATE.md](../_DesignSystem/SDD-TEMPLATE.md) (exemplar de origem:
> [Produto/SDD-tela-cadastro-produto-v1.0.md](../Produto/SDD-tela-cadastro-produto-v1.0.md)).
>
> ⚠️ **Tier 0 fiscal.** Este domínio emite documento com valor legal (CONFAZ SINIEF 07/2005) para
> uma carteira ×150 clientes. Nenhum `✅` aqui vem de leitura de código — só de veredito de lane.

> ### 🔖 Changelog v1.0.0 (2026-07-27) — nascimento
> Primeiro SDD do módulo (`CU no SDD` era **0**, medido por `node scripts/governance/requisitos-status.mjs Fiscal`).
> Derivado de **3 fontes** — a 4ª (Delphi/Office Comercial) **não existe** neste módulo, ver §0.1.
> Gerado pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md) na Onda 1 / S2 do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md). **Nenhum CU nasce `✅`** —
> os que têm teste nascem `🧪` (aguardando veredito da lane); os sem teste nascem `⬜`.

---

## 0. Base empírica <!-- curado: foto que envelhece -->

🖐 **curado** — foto datada. Re-medir com as portas citadas, não editar o número.

### 0.1 As fontes que existem — e a que NÃO existe

| # | Fonte | Estado neste módulo | O que deu |
|---|---|---|---|
| 1 | **Documentação canon** | ✅ rica | [SPEC.md](SPEC.md) 23 US + 3 regras Gherkin `R-FISCAL-001/002/003` · 7 charters · 7 RUNBOOKs · [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) |
| 2 | **React/Laravel atual** | ✅ | 11 Controllers · 1 Service · 7 telas · 19 Pest — inventário derivado em [SUPERFICIE.md](SUPERFICIE.md) |
| 3 | **Blade AdminLTE legada** | ⚠️ **não se aplica** — ver abaixo | — |
| 4 | **Delphi / Office Comercial** | ❌ **ausente** | `find memory -iname "*ANTI-REGRESSAO*"` = 2 arquivos, **ambos do Produto** |

**Por que a fonte 3 não se aplica aqui (e por que isso NÃO é um furo de varredura):** o Fiscal
**não é uma migração MWART de tela Blade**. É um módulo **nascido em React** (PR #1183, 2026-05-20)
cujo papel declarado é ser *thin agregador* sobre `Modules/NfeBrasil` + `Modules/NFSe`. O legado
correspondente não é uma Blade AdminLTE homônima — é a **UI própria do NfeBrasil**
(`Pages/NfeBrasil/Transactions/NfceStatus`, os endpoints `/nfe-brasil/configuracao/*` e as 3 entradas
de sidebar que a Onda ESTABILIZAR removeu, SPEC v1.9.0). A armadilha da "Blade homônima" foi
verificada e **não existe** neste módulo; a armadilha real aqui é outra e está em §5.4:
**telas competindo entre Fiscal e NfeBrasil**.

> ⛔ **Gap declarado, não preenchido.** Sem a fonte 4, o **contrato de paridade é mais fraco**: não
> há lista destilada de "o que o legado fazia e o React precisa manter". Onde o SDD afirma paridade,
> ela vem do **SPEC/charter** (fonte 1), nunca de suposição sobre o Delphi.
> **Isto é decisão [W]:** criar ou não um `ANTI-REGRESSAO-fiscal-legacy.md` a partir do manual WR
> Comercial. O agent é **proibido de inventá-lo** ([ADR 0351](../../decisions/0351-sdd-from-source.md), regra 6 do template).

### 0.2 O que a medição expôs (recibos datados)

| Fato | Porta que mediu (re-rodável) | Valor em 2026-07-27 |
|---|---|---|
| US no SPEC | `node scripts/governance/requisitos-status.mjs Fiscal` | 23 |
| CU no SDD **antes** deste arquivo | idem | **0** |
| Telas `.tsx` | idem (consome `page-path.mjs`) | 7 |
| `casos.md` presentes | idem | 7 |
| **UC declarados antes desta corrida** | idem | **0** — os 7 eram stubs de 37-41 linhas |
| Testes Pest do módulo | `ls Modules/Fiscal/Tests/Feature` | 19 arquivos |
| Testes na lane **required** | allowlist de `.github/workflows/nfebrasil-pest.yml` | **4** de 19 |
| Props Inertia servindo **mock** | `grep -n "Mock'\s*=>" Modules/Fiscal/Http/Controllers/*.php` | **4** props · **8** métodos `mock*` · **9** `TODO[CL]` |
| Nota Capterra do módulo | [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (foto 2026-07-03) | 75/100 |

> ⚠️ **`casos.md` presente ≠ tela coberta.** Os 7 arquivos existiam desde 2026-07-03 com **zero UC**
> — e um plano anterior leu "7/7 casos.md" como "contrato 100%". É a classe **LC-11** (presence-gate).
> A porta viva foi corrigida em 2026-07-27 e passou a acusar as 7 lacunas; este SDD é a resposta.

---

## 1. Visão geral <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

O Fiscal é um **cockpit agregador thin**: não emite NF-e, não fala com a SEFAZ, não calcula tributo.
Ele **lê** os Models de `Modules/NfeBrasil` + `Modules/NFSe` e **delega** toda mutação aos Services
daqueles módulos. A única lógica de negócio própria que ele carrega é o
`SpedIcmsIpiGeneratorService` (geração do TXT EFD-ICMS/IPI) — e é justamente ela que está
**travada por feature flag** (§5.4).

### 1.1 Família de telas (7 + 2 superfícies sem tela)

| Tela | Rota | Controller | Permissão | Papel |
|---|---|---|---|---|
| `Cockpit` | `GET /fiscal` | `CockpitController@index` | `fiscal.access` | KPIs + sparklines + alertas + quick-links |
| `Nfe` | `GET /fiscal/nfe` | `NfeCockpitController@index` | `fiscal.nfe.view` | lista NF-e(55)/NFC-e(65) + drawer + janela legal |
| `Nfse` | `GET /fiscal/nfse` | `NfseCockpitController@index` | `fiscal.nfse.view` | lista NFS-e por competência |
| `Dfe` | `GET /fiscal/dfe` | `DfeController@index` | `fiscal.dfe.manage` | NF-e emitidas **contra** o CNPJ + prazo de manifestação |
| `Eventos` | `GET /fiscal/eventos` | `EventosController@index` | `fiscal.access` | timeline append-only de eventos SEFAZ |
| `Config` | `GET /fiscal/config` | `ConfigController@index` | `fiscal.config.edit` | cert A1 + regime + ambiente + séries |
| `Sped` | `GET /fiscal/sped` | `SpedController@index` | `fiscal.sped.export` | panorama 5 meses + download EFD |
| — (sem tela) | 6 × `POST /fiscal/acoes/*` | `AcoesController` | `fiscal.nfe.acoes` / `fiscal.dfe.manage` | cancelar · CC-e · inutilizar · retransmitir · manifestar |
| — (sem tela) | `GET /fiscal/palette/search` | `PaletteSearchController@search` | `fiscal.access` | ⌘K cross-fiscal |

### 1.2 Personas do domínio

**Eliana [E]** (advogada + financeiro, contadora do grupo) é a persona-alvo declarada nos 7 charters —
**conferência e auditoria**, não emissão. **Wagner [W]** é o operador/dono que age (cancela, manifesta,
inutiliza). **Larissa** (ROTA LIVRE biz=4) **ainda não usa** este módulo: a `US-FISCAL-018` (habilitar
biz=4 + canary 7d) está **parcial** — as pernas humano-limitadas (`artisan` em prod, briefing, canary,
smoke) seguem em aberto. Ou seja: **o cockpit Fiscal não está em produção para o cliente de 99% do
volume**. Isso muda o risco de mudança aqui (baixo hoje) e o risco de *não* mudar (alto no cutover).

---

## 2. Público-alvo e personas <!-- curado: foto que envelhece -->

🖐 **curado** — validar com [W].

| Persona | Quem | O que faz nesta família | Fonte |
|---|---|---|---|
| **P1 · Eliana [E]** | contadora/advogada, time interno | confere emissões do mês, caça rejeição, manifesta DF-e, fecha SPED | os 7 charters (`Persona: Eliana (contadora)`) |
| **P2 · Wagner [W]** | operador-dono, biz=1 | cancela, aplica CC-e, inutiliza faixa, retransmite, troca ambiente/cert | SPEC US-FISCAL-001/012/013/014 |
| **P3 · Larissa** | ROTA LIVRE biz=4, vestuário SC | ⬜ **ainda não** — US-FISCAL-018 parcial (canary não rodou) | SPEC §Onda Audit Sênior |
| **P4 · Contador externo** | fora do sistema | recebe o pacote mensal (XML + SPED) | `mockContabilData()` — **superfície ainda mockada**, §5.4 |

---

## 3. Governança aplicável <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

| Regra Tier 0 | Como morde AQUI |
|---|---|
| **[ADR 0093] multi-tenant** | 5 Models lidos (`NfeEmissao`, `NfseEmissao`, `NfeDfeRecebido`, `NfeCertificado`, `NfeEvento`) usam global scope. Os 4 guards de isolamento estão na lane **required** `PHP / Pest (NfeBrasil · MySQL)`. `AcoesController` faz **defesa em profundidade**: `->where('business_id', $businessId)->firstOrFail()` além do scope. |
| **[ADR 0101] biz=1, nunca biz=4** | todos os testes do módulo usam biz=1 e biz=99/biz=2 como contraparte. |
| **REGRA MESTRE valor/estoque `[V0]`** | o **SPED** (`SpedIcmsIpiGeneratorService`) é `[V0]`: erro de CST/CFOP/alíquota vira **multa fiscal**. É por isso que existe a flag `fiscal.sped_simples_only_lock=true` (§5.4). Toda mudança ali exige dupla-confirmação + antes→depois. |
| **PII / LGPD** | `NfeCertificado.encrypted_password` é `$hidden`; `justificativa` do evento é truncada em 200 chars (`EventosController::mapRow`) porque o `xMotivo` do XML pode conter PII; `error_msg` da NFS-e só em hover. Os mocks já nascem com `[REDACTED-CNPJ]`. |
| **Append-only legal** | `NfeEvento::UPDATED_AT = null`; NFe cancelada **nunca** `forceDelete()` (CONFAZ SINIEF 07/2005 Art. 14 — o número segue usado oficialmente). |
| **[ADR 0062] runtime** | Pest deste módulo roda no CI/CT 100 — **nunca** local. |

---

## 4. Design system aplicável <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

- **Shell próprio do módulo:** `_components/FxShell.tsx` (chips das 7 sub-páginas + botão Buscar + mount global do `CmdKPalette`).
- **CSS escopado `.fx-*`** — o charter da `Nfe` declara explicitamente *"CSS scoped `.fx-page` (não vaza tokens fiscais pra outras telas)"*.
- **Padrão de tela:** os 7 charters declaram `related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)` — ou seja, **nenhuma das 7 telas é ancorável** por `proto-baseline` (não têm protótipo Cowork). Isso não é dívida: é o caso "nasce do DS" descrito na lápide [proibicoes §5](../../proibicoes.md) 2026-07-17.
- **Componentes de detalhe:** `NotaDrawer` / `NotaDrawerV2` (2 gerações convivendo — ver §5.4), `NFSeDrawer`, `EventosDrawer`, `InutilizacaoModal`, `SendToContabilDrawer`, `SavedViewsChips`, `WriteOffAuditoriaCard`, `_shared/DrawerBase`.
- **Comparações visuais existentes:** 7 arquivos `*-visual-comparison.md` neste diretório (1 por tela).

---

## 5. Arquitetura <!-- derivado: re-rodável do fonte -->

⚙️ **derivado** — re-rodável. Âncoras por **símbolo** (`Classe@metodo`), não por número de linha.

### 5.1 Visão em camadas

```
resources/js/Pages/Fiscal/<Tela>.tsx        (React 19 + Inertia v3)
        │  props eager (counts/filters/kpis)  +  Inertia::defer (rows)
        ▼
Modules/Fiscal/Http/Controllers/*.php        ← ÚNICA camada própria do módulo (thin)
        │  gate de permissão (abort 403)  →  query  →  mapRow()
        ▼
Modules/NfeBrasil/Models/*  ·  Modules/NFSe/Models/NfseEmissao      ← global scope Tier 0
        │
        ▼
nfe_emissoes · nfe_eventos · nfe_dfe_recebidos · nfe_certificados
nfe_business_configs · nfse_emissoes · nfe_fiscal_rules · business
```

**Mutação** inverte a última perna — o Fiscal **não escreve** no banco fiscal:

```
Pages/Fiscal/_components/NotaDrawer.tsx | Dfe.tsx | InutilizacaoModal.tsx
        │  POST /fiscal/acoes/...   (throttle 30/min — protege o webservice SEFAZ)
        ▼
Modules/Fiscal/Http/Controllers/AcoesController@{cancelarNfe,manifestarDfe,cartaCorrecao,inutilizar,retransmitir}
        │  gate perm → validate() → cross-tenant guard explícito → try/catch
        ▼
Modules/NfeBrasil/Services/{NfeService,NfeCartaCorrecaoService,NfeInutilizacaoService,Manifestacao/ManifestacaoService}
        │
        ▼  SEFAZ  +  NfeEvento (append-only)  +  FSM cascade (ADR 0143, cancelamento)
```

### 5.2 Modelo de dados (núcleo)

| Tabela | Dono | Colunas que o Fiscal lê | `business_id` |
|---|---|---|---|
| `nfe_emissoes` | NfeBrasil | `numero`, `serie`, `modelo` (55/65), `chave_44`, `status`, `cstat`, `motivo`, `valor_total`, `emitido_em`, `transaction_id`, `metadata` (JSON) | global scope ✔ · sem FK a `business` |
| `nfe_eventos` | NfeBrasil | `tipo` (tpEvento), `status`, `cstat_evento`, `justificativa`, `created_at`, `emissao` | global scope ✔ · `UPDATED_AT = null` |
| `nfe_dfe_recebidos` | NfeBrasil | `chave_44`, `nsu`, `cnpj_emitente`, `nome_emitente`, `valor_total`, `data_emissao`, `status_manifestacao`, `prazo_confirmacao_em`, `manifestado_em` | global scope ✔ |
| `nfe_certificados` | NfeBrasil | `uuid`, `cnpj_titular`, `valido_ate`, `ativo` · **`encrypted_password` é `$hidden`** | global scope ✔ |
| `nfe_business_configs` | NfeBrasil | `regime`, `auto_emission_enabled`, `tributacao_default` (JSON) | por `business_id` |
| `nfse_emissoes` | **NFSe** (schema VELHO) | `numero`, `provider_codigo_verificacao`, `tomador_nome`, `tomador_cnpj`, `tomador_cpf`, `lc116_codigo`, `aliquota_iss`, `valor_servicos`, `valor_iss`, `status` (PT-BR), `erro_mensagem`, `created_at` | `NfseBusinessScope` ✔ · **FK a `business`** |
| `business` / `business_locations` | core | `numero_serie_nfe`, `ultimo_numero_nfe`, `ncm_padrao`, `ambiente`, `state`, `city` | lido por `DB::table` em `ConfigController::montarPainelFiscal` (fora do Eloquent → **fora do global scope**, escopado à mão por `where('id', $businessId)`) |

> 🔴 **Armadilha de schema medida** (`NfseCockpitController` docblock, 2026-05-26): existem **dois**
> schemas de `nfse_emissoes`. O NOVO (migration `2026_05_11_150001`, idempotente `Schema::hasTable`)
> **nunca rodou em prod** porque a tabela já existia. O Controller foi revertido pro Model do
> `Modules/NFSe` (schema velho) e **traduz o status PT→EN** (`emitida`→`authorized`, …) só pra
> preservar o contrato do React. Coluna `municipio_prestacao` **não existe** → payload devolve `null`.

### 5.3 Fluxos críticos

**F1 · Cockpit (`GET /fiscal`)** — `CockpitController@index` → gate `fiscal.access`
→ `buildContexto()` (1× `$cert` + 1× `$dfeCount`, reuso deliberado que matou 2 queries redundantes)
→ `Cache::remember('fiscal:cockpit:kpis:biz:{id}', 60s, computeKpis)` → `computeSparklines()`
(1 query `selectRaw('DATE(emitido_em)…')` agrupada, **anti-N+1** declarado no charter) →
`computeAlerts()` (determinístico, **sem LLM**: rejeições 7d + cert <60d + DF-e pendente).
Tudo **eager** por decisão de charter (*"não fazer `Inertia::defer` nos KPIs — first paint deve
mostrar números"*). Invalidação do cache: `InvalidaCockpitCacheListener` nos eventos
`NFeAutorizada`/`NFCeAutorizada`. **A chave de cache carrega o `business_id`** — o charter chegou a
mandar o oposto e foi corrigido em 2026-07-06 (obedecê-lo criaria vazamento cross-tenant Tier 0).

**F2 · Lista NF-e/NFC-e (`GET /fiscal/nfe`)** — `NfeCockpitController@index` → gate `fiscal.nfe.view`
→ `computeCounts()` **eager** (7 counts + `cancelaveis` calculado em PHP porque depende de
`now()` × `emitido_em` × `modelo`) → `rows` via **`Inertia::defer(buildRowsPayload)`**
(`paginate(50)`, `orderByDesc('emitido_em')`, filtro por tab/status/search) →
`mapRow()` monta a linha e chama `isCancelavel()`. `sefazCodes()` é mapa **estático** (12 códigos,
tom `ok|warn|bad`) — o charter autoriza cache porque não depende de business.

**F3 · Janela legal de cancelamento `[reg]`** — `NfeCockpitController::isCancelavel`:
`status === 'autorizada' && emitido_em && diffInHours(now()) <= (modelo === '65' ? 24 : 168)`.
É **regra de lei** (CONFAZ Ajuste SINIEF 07/2005 Art. 14), não preferência de UI. Aparece em
3 lugares: a pílula da linha, o count `cancelaveis` e o drawer.
> ⚠️ **Risco R3 do charter, ainda aberto:** o servidor decide com `now()` (timezone do app) e o
> front redesenha a pílula com `Date.now()` (timezone do browser). Perto do deadline os dois
> discordam. O charter propõe passar `nowMs` server-rendered — **não feito**.

**F4 · Mutações NF-e (`POST /fiscal/acoes/nfe/*`)** — `AcoesController` é *thin delegate*, e o
padrão é idêntico nos 4 métodos: (1) gate `fiscal.nfe.acoes` (ou `superadmin`); (2) `validate()`
com as regras CONFAZ; (3) **cross-tenant guard explícito**; (4) guard de `status`; (5) `try` →
Service do NfeBrasil; (6) `Log::info`/`Log::error` estruturado; (7) `back()->with(flash)`.
As regras validadas, uma a uma:

| Ação | Regra dura | Origem |
|---|---|---|
| `cancelarNfe` | `motivo` 15–255 chars · só `status='autorizada'` | CONFAZ SINIEF 07/2005 Art. 14 |
| `cartaCorrecao` | `texto_correcao` 15–1000 · `n_seq_evento` 1–20 · só `autorizada` · janela 720h | CONFAZ Art. 14 + limite SEFAZ de 20 CC-e |
| `inutilizar` | `modelo ∈ {55,65}` · `numero_ate >= numero_de` · `justificativa` 15–255 | regra SEFAZ (cstat 102) |
| `retransmitir` | `status ∈ {rejeitada, denegada, erro_envio}` — **nunca** autorizada/cancelada | preservação CONFAZ Art. 14 |

**F5 · Retransmissão sem apagar `[reg]`** — a estratégia é **preservation contract**: o
`NfeService::retransmitir` faz `UPDATE` na emissão antiga (`status='inutilizada'`,
`transaction_id=null` pra liberar o UNIQUE `biz+tx`, guarda `metadata.original_transaction_id`) e
então **reemite com número novo**. **`forceDelete()` é proibido** — documento fiscal é imutável.

**F6 · Manifestação DF-e (`GET /fiscal/dfe` + `POST /fiscal/acoes/dfe/{id}/{acao}`)** —
`DfeController@index` (gate `fiscal.dfe.manage`) lista `NfeDfeRecebido` com `paginate(50)` e calcula
`prazoDias` a partir de **`prazo_confirmacao_em`** (o charter é explícito: *"prazo 90d hard-coded —
fonte de verdade é `prazo_confirmacao_em` no Model, calculado por SEFAZ"*).
A mutação é whitelist **fechada de 4 ações** (`cienciar` 210210 · `confirmar` 210200 ·
`desconhecer` 210220 · `nao_realizada` 210240) — a rota já restringe por `->where('acao', …)` **e** o
Controller re-checa com `abort(404)`. `desconhecer`/`nao_realizada` **exigem** justificativa ≥15;
`cienciar`/`confirmar` **não**.

**F7 · Timeline de eventos (`GET /fiscal/eventos`)** — `EventosController@index` (gate
`fiscal.access`) → `computeCounts()` + `rows` deferido, ambos filtrados por
`created_at >= now()->subDays(max(1,$dias))` (default 30d). `with('emissao:id,numero,modelo,chave_44')`
— **1 join só**, limite do charter. `mapRow()` **trunca `justificativa` em 200 chars** (anti-PII do
`xMotivo`) e resolve o `kind` pelo mapa `TIPOS` (7 códigos canônicos).
> Nota de fronteira: **inutilização não vive em `NfeEvento`** (vive em `NfeInutilizacao`) — o array
> `$inutTipos` do `computeCounts` é vazio **de propósito** e está documentado no código.

**F8 · Certificado e configuração (`GET /fiscal/config`)** — `ConfigController@index` (gate
`fiscal.config.edit`) → `NfeCertificado::where('ativo', true)->orderByDesc('valido_ate')->first()`
→ tom de urgência (`dias < 0` = vencido · `<= 30` = proximo_vencimento · senão ok) →
`montarPainelFiscal()` lê `business` / `nfe_business_configs` / `business_locations` **por
`DB::table`** (fora do Eloquent, logo **fora do global scope** — escopado à mão pelo
`where('id', $businessId)` vindo da sessão). A tela tem 4 abas (`cert|series|ambiente|sped`) com
whitelist server-side.

**F9 · SPED EFD-ICMS/IPI (`GET /fiscal/sped` + `/sped/icms-ipi/{ano}/{mes}`) `[V0]`** —
`SpedController@index` monta o panorama de 5 meses (2 queries por mês: `count` + `sum`);
`@gerar` faz, **nesta ordem**: (1) gate `fiscal.sped.export`; (2) **feature flag**
`fiscal.sped_simples_only_lock` → **503** explicativo se `true` e não-superadmin; (3)
`SpedIcmsIpiGeneratorService::gerar($businessId, $ano, $mes)` com cross-tenant guard **antes de
qualquer query**; (4) resposta `text/plain` com `Content-Disposition: attachment` +
`X-Sped-Layout-Version: 018`. 23 registros canônicos (Blocos 0 · C · E · H · 9), throttle 3/min.
O motor tributário entra por **DI opcional**: com regra configurada devolve CST/CFOP/alíquota reais;
sem ela cai no **fallback Simples** (CSOSN 102, alíq 0) **diferenciando CFOP interno 5102 ×
interestadual 6102** — foi esse diferencial que eliminou o risco R1 do audit sênior.

**F10 · ⌘K palette (`GET /fiscal/palette/search`)** — `PaletteSearchController@search`, gate
`fiscal.access`, `q` **min 3 chars** (era 2; subiu por anti-DOS: `LIKE %q%` com wildcard à esquerda
faz full-scan em tabela com 50k+ notas), throttle 60/min, 2 categorias (notas + DF-e) top 5.

### 5.4 Onde os dois mundos ainda não se conversam

Este módulo não tem dívida Blade↔React (§0.1). Tem **quatro** dívidas próprias, todas medidas:

#### 5.4.1 · Quatro props Inertia servem **dado inventado** a uma tela fiscal <!-- derivado -->

Varredura contada em `Modules/Fiscal/Http/Controllers/*.php` (2026-07-27):
**4 props** de `Inertia::render` recebem mock, alimentadas por **8 métodos `mock*`**, com **9**
marcadores `TODO[CL]`:

| Prop | Tela | Método | O que o operador vê |
|---|---|---|---|
| `notasMock` | `Cockpit` | `mockNotasUnificadas()` | 5 notas fictícias (NF-e/NFC-e/NFS-e) com itens, boleto, e-mails e trilha de auditoria |
| `eventosMock` | `Cockpit` | `mockEventos()` | 5 eventos fictícios com autor ("Eliana", "Wagner", "Larissa") |
| `historicoMock` | `Dfe` | `mockHistorico()` | 5 manifestações fictícias com ator e observação |
| `seriesMock` | `Config` | `mockSeriesFiscais()` | 3 séries fiscais, 2 delas inventadas ("Filial 02 (futuro)") |

Somam-se, **fora de prop nomeada `*Mock`**, mais quatro superfícies mockadas do `Cockpit`:
`savedViewCounts`, `sefazStatus` (*"SEFAZ-SP operacional"* — **hardcoded `true`**),
`contabilData` (*"184 NF-e autorizadas no período"* — número fixo no código) e
`writeOffSummary` (**2.470 candidatos inadimplentes >365d, com valor fixo no código**).

**Por que isto é achado e não estilo:** o dado é **PII-safe** (já nasce `[REDACTED-CNPJ]`) e o código
é honesto sobre si (`TODO[CL]` em todos), mas a **tela não é**. Um cockpit fiscal que exibe
"184 NF-e autorizadas" e "SEFAZ-SP operacional" sem marcar procedência é, para a persona,
indistinguível de leitura real — e a persona-alvo é a **contadora**. É a mesma família da lápide
[proibicoes §5](../../proibicoes.md) 2026-07-17 (*"número que outro sistema sabe melhor"*), aqui na
forma mais aguda: o número **não vem de sistema nenhum**.

> ⚖️ **Decisão [W], não do agente.** As saídas legítimas são pelo menos três — (a) marcar visualmente
> a procedência ("dados de demonstração"), (b) esconder a superfície atrás de flag até o serviço real
> existir, (c) declarar Non-Goal explícito no charter. Escolher é produto. Registrado como
> **`CU-FISC-16` `⬜`** (§6) e como `[BACKLOG]` nos `casos.md` de `Cockpit`, `Dfe` e `Config` —
> **não** virou UC com id porque não há contrato em 2 fontes que diga qual saída é a certa.

#### 5.4.2 · O charter do `Sped` proíbe o que o código já faz

`Sped.charter.md` declara `❌ Gerador SPED real (TXT layout SPED Fiscal) — PR dedicado` e o anti-hook
`🚫 NÃO emitir SPED real até implementação canônica`. Mas `US-FISCAL-016` + `US-FISCAL-017`
**entregaram** o gerador (`SpedIcmsIpiGeneratorService`, 23 registros) e a rota de download existe.
A contradição está **parcialmente reconciliada por acidente feliz**: a flag
`fiscal.sped_simples_only_lock=true` mantém o efeito do anti-hook em produção (503 para não-superadmin).
> ⚖️ **Divergência aberta [W]** — Non-Goal é **intenção**, e intenção só [W] altera
> ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.6). O agente **não** editou o charter.
> As opções: atualizar o Non-Goal (o gerador existe e a flag é o freio real) **ou** manter e assumir
> que o gerador é código dormente. Precedência ([proibicoes](../../proibicoes.md)) diz *teste > casos >
> charter*, e o `SimplesOnlyGateTest` prova o freio — mas quem decide a intenção é [W].

#### 5.4.3 · O charter do `Config` proíbe edição inline — e a tela edita

`Config.charter.md`: `❌ Edição inline (upload novo cert, mudar regime, editar tributação) — vive em
NfeBrasil canon` + anti-hook `🚫 Não criar UPDATE Controller — esta tela é read-only por design`.
Medido em `Config.tsx`: existem **2 formulários de mutação** — `uploadForm` (upload `.pfx`/`.p12` +
senha) e `ambienteForm` (radio Homologação/Produção + submit), ambos com `<form onSubmit=…>`.
A **letra** do anti-hook está honrada (não há UPDATE Controller no Fiscal; os forms postam para
`/nfe-brasil/configuracao/*`), mas o **Non-Goal** não: a edição **é** inline na tela do Fiscal.
> ⚖️ **Divergência aberta [W]** — mesma regra da 5.4.2. Trocar o ambiente SEFAZ de Homologação para
> Produção é uma ação de consequência fiscal alta feita numa tela declarada *read-only*; vale decidir
> conscientemente se o Non-Goal muda ou se o form sai.

#### 5.4.4 · Resíduos menores (contados, não corrigidos)

- **`NotaDrawer.tsx` × `NotaDrawerV2.tsx`** convivem em `_components/` — duas gerações do mesmo papel.
- **`Nfse.charter.md`** cita a coluna `NfseEmissao->cpf_cnpj_tomador`, que **não existe**: varredura contada = **3** ocorrências do literal no repo, e **as outras 2 são comentários explicando que ela não existe**. As reais são `tomador_cnpj`/`tomador_cpf` (`Modules/NFSe/Models/NfseEmissao.php`, `$fillable`). Corrigido nesta corrida como **fato** (a intenção do anti-hook — *não fazer JOIN com `transactions`* — ficou intacta).
- **`dest_name`/`dest_cnpj` vêm de `metadata` JSON**, não de JOIN com `contacts` — Non-Goal declarado do PR #1, com fallback `—`. Segue aberto.
- **`US-FISCAL-003/004/006/011`** são stubs de backlog já superados por US posteriores; o próprio SPEC pede *"consolidar/arquivar este stub"*. Não arquivados.

---

## 6. Casos de uso <!-- derivado: re-rodável do fonte -->

⚙️ **derivado + 🖐 [W] confere.** Estado: `✅` provado por teste verde que o cita · `🟡` parcial ·
`🔴` falso/quebrado · `🧪` tem teste, **veredito pendente da lane** · `⬜` não-verificado.
**Nenhum CU nasce `✅` nesta corrida** — o agent não roda teste ([ADR 0062]).

### 6.1 Leitura e conferência

#### CU-FISC-01 — Ver o estado fiscal do mês numa tela `[must]` 🧪
*Dado* uma contadora com `fiscal.access`; *quando* abre `/fiscal`; *então* vê KPIs do mês,
sparklines de 14 dias e alertas acionáveis sem abrir outra tela.
1. `[must]` a resposta é o componente Inertia `Fiscal/Cockpit` com `kpis`, `sparklines` e `alerts`
2. `[must]` `kpis` carrega as 7 medidas do ribbon (emitidas, autorizadas, %, rejeitadas, faturamento, DF-e aguardando, dias de cert)
3. `[must]` os alertas são **determinísticos** — nenhum campo de raciocínio de LLM viaja no payload
4. `[perf]` sparklines saem de **uma** query agrupada (sem N+1) — ⬜ sem teste

#### CU-FISC-02 — Conferir NF-e/NFC-e com status SEFAZ legível `[must]` 🧪
*Dado* notas emitidas; *quando* a contadora abre `/fiscal/nfe`; *então* cada linha traduz o `cstat`
em tom + rótulo + dica, e os chips contam por modelo e status.
1. `[must]` o mapa SEFAZ cobre ao menos 100/110/220/539/691/778/999 com o tom correto
2. `[must]` a lista é deferida e pagina de 50 em 50 por `emitido_em DESC` — ⬜ sem teste do payload filtrado

#### CU-FISC-03 — Agir dentro da janela legal de cancelamento `[must]` `[reg]` 🧪
*Dado* uma nota autorizada; *quando* o cockpit calcula se ela ainda é cancelável; *então* respeita
**24h para NFC-e (65)** e **168h para NF-e (55)** — CONFAZ SINIEF 07/2005 Art. 14.
1. `[must]` NFC-e com 10h é cancelável; com 30h **não** é
2. `[must]` NF-e com 48h é cancelável; com 200h **não** é
3. `[reg]` nota não-autorizada **nunca** é cancelável, qualquer que seja a idade

#### CU-FISC-04 — Conferir NFS-e por competência `[should]` 🧪
*Dado* NFS-e do mês; *quando* filtra por competência/status/busca; *então* vê a lista com os
6 indicadores e **competência malformada não derruba a tela**.

#### CU-FISC-05 — Auditar a timeline de eventos SEFAZ `[must]` 🧪
*Dado* eventos aplicados; *quando* abre `/fiscal/eventos`; *então* vê linha do tempo append-only
com os 7 tipos canônicos rotulados em PT-BR.
1. `[must]` os 7 `tpEvento` canônicos são reconhecidos e mapeados ao seu `kind`
2. `[must]` o evento **não é editável** (`UPDATED_AT = null`) — append-only por lei
3. `[must]` a justificativa é truncada em 200 chars (anti-PII do `xMotivo`) — ⬜ sem teste

#### CU-FISC-06 — Conferir certificado A1 e configuração fiscal `[must]` 🧪
*Dado* um certificado A1 ativo; *quando* abre `/fiscal/config`; *então* vê validade com tom de
urgência, regime, série e tributação default — **sem que a senha do certificado apareça**.

#### CU-FISC-07 — Manifestar DF-e dentro do prazo legal `[must]` 🧪
*Dado* NF-e emitidas contra o CNPJ; *quando* a contadora manifesta; *então* só valem as **4 ações
SEFAZ**, e `desconhecer`/`nao_realizada` exigem justificativa.
1. `[must]` whitelist de exatamente 4 ações — qualquer outra é rejeitada
2. `[must]` justificativa exigida só em `desconhecer`/`nao_realizada`
3. `[must]` "pendente de manifestação" = `pendente` **ou** `ciencia`
4. `[ux]` prazo exibido com 3 níveis de urgência a partir de `prazo_confirmacao_em` — ⬜ sem teste

### 6.2 Ação fiscal (mutação)

#### CU-FISC-08 — Cancelar NF-e autorizada com justificativa CONFAZ `[must]` 🧪
*Dado* uma nota autorizada dentro da janela; *quando* o operador cancela; *então* o motivo é exigido
com **≥15 caracteres** e a nota nunca é apagada — vira evento 110111.

#### CU-FISC-09 — Aplicar Carta de Correção (CC-e 110110) `[must]` 🧪
*Dado* uma nota autorizada; *quando* aplica CC-e; *então* o texto tem **15–1000** chars e a
sequência está em **1–20** (máx. 20 CC-e por NF-e).

#### CU-FISC-10 — Inutilizar faixa numérica `[must]` 🧪
*Dado* um buraco no sequencial; *quando* inutiliza a faixa; *então* modelo ∈ {55,65},
`numero_ate ≥ numero_de` e justificativa ≥15 chars.

#### CU-FISC-11 — Retransmitir sem apagar a nota antiga `[must]` `[reg]` 🧪
*Dado* uma nota `rejeitada`/`denegada`/`erro_envio`; *quando* retransmite; *então* a antiga é
**preservada** (marcada `inutilizada`, `transaction_id` liberado) e uma nova é emitida com número novo.
1. `[reg]` `forceDelete()` **nunca** é usado — documento fiscal é imutável (CONFAZ Art. 14)
2. `[must]` status fora de {rejeitada, denegada, erro_envio} é recusado

### 6.3 Invariantes transversais

#### CU-FISC-12 — Isolamento multi-tenant em toda a superfície fiscal `[must]` `[T0]` 🧪
*Dado* dados de outro business; *quando* qualquer tela ou ação do Fiscal consulta; *então*
**nada cross-tenant aparece** — nem em lista, nem em contagem, nem em cache, nem no SPED.
1. `[T0]` contagem de NF-e do cockpit esconde emissões de outro business
2. `[T0]` KPIs do `/fiscal` isolam por business
3. `[T0]` a chave de cache dos KPIs **carrega o `business_id`** e casa com a do listener de invalidação
4. `[T0]` timeline de eventos, listagem DF-e, certificados e NFS-e isolam por business
5. `[T0]` a geração de SPED para outro business lança exceção **antes de qualquer query**

#### CU-FISC-13 — Gate de permissão por sub-feature `[must]` `[T0]` ⬜
*Dado* um usuário com `fiscal.access` mas **sem** a permissão específica da sub-página; *quando*
acessa a rota; *então* recebe **403** — `superadmin` faz bypass. Âncora de contrato:
**`R-FISCAL-003`** do [SPEC.md](SPEC.md) §3.
1. `[T0]` `/fiscal` exige `fiscal.access` — 🧪 coberto
2. `[T0]` `/fiscal/nfe` exige `fiscal.nfe.view` — ⬜ **sem teste HTTP** (guard existe no Controller)
3. `[T0]` `/fiscal/nfse` exige `fiscal.nfse.view` — 🧪 coberto
4. `[T0]` `/fiscal/dfe` exige `fiscal.dfe.manage` — ⬜ **sem teste HTTP**
5. `[T0]` `/fiscal/eventos` exige `fiscal.access` — ⬜ **sem teste HTTP**
6. `[T0]` `/fiscal/config` exige `fiscal.config.edit` — ⬜ **sem teste HTTP**
7. `[T0]` `/fiscal/sped` exige `fiscal.sped.export` — 🧪 coberto (via gate de flag)

#### CU-FISC-14 — Não vazar segredo nem PII no payload da tela `[must]` `[T0]` 🧪
*Dado* certificado, evento e NFS-e com erro; *quando* a tela serializa; *então* a senha do
certificado **nunca** viaja, a justificativa do evento é truncada e o erro da NFS-e não vai para a
tabela.

### 6.4 Fronteira de valor `[V0]`

#### CU-FISC-15 — Gerar o SPED EFD-ICMS/IPI da competência `[must]` `[V0]` 🧪
*Dado* uma competência com notas autorizadas; *quando* a contadora baixa o TXT; *então* o arquivo
tem os 23 registros canônicos, o ICMS sai do motor tributário quando há regra, e o **CFOP distingue
operação interna (5102) de interestadual (6102)**.
1. `[V0]` alíquota/CST/CFOP vêm do `MotorTributarioService` quando configurado
2. `[V0]` sem regra, o fallback é Simples (CSOSN 102, alíq 0) — e **ainda assim** diferencia o CFOP por UF
3. `[V0]` a flag `fiscal.sped_simples_only_lock` bloqueia o download com **503** para não-superadmin
4. `[T0]` gerar SPED de outro business lança `RuntimeException` antes de qualquer query
5. `[must]` competência inválida (ano <2020, ano futuro, mês fora de 1–12) é recusada
6. ⬜ **sem teste** — validação do TXT no PVA-EFD oficial (nenhum golden file existe)

### 6.5 Procedência do dado

#### CU-FISC-16 — Distinguir dado real de dado de demonstração `[must]` ⬜
*Dado* que 4 props Inertia e 4 superfícies extras do cockpit servem dado inventado (§5.4.1);
*quando* a contadora lê a tela; *então* ela precisa conseguir dizer o que é leitura real e o que é
demonstração.
> ⛔ **Sem contrato em 2 fontes** → **não** virou UC com id (viraria órfão e bloquearia o merge de
> quem for atendê-lo, [proibicoes §5](../../proibicoes.md) 2026-07-16). Está como `[BACKLOG]` nos
> `casos.md` de `Cockpit`, `Dfe` e `Config` e **precisa de decisão [W]** (§5.4.1).

### 6.6 Non-Goals — **só [W] preenche** 🖐

> O agent é **proibido de inferir Non-Goal**. Os itens abaixo são **ponteiros** para os Non-Goals que
> [W] já aprovou nos 7 charters — não são novos, e nenhum foi criado nesta corrida.

- `Cockpit`: sem drill-down com filtro pré-aplicado · sem período custom · sem export · sem alerta push
- `Nfe`: sem emissão nova · sem `⌘K` na tela · sem JOIN com `transactions`/`contacts` para `dest_name`
- `Nfse`: sem drawer de detalhe · sem emissão · sem cancelamento (varia por município) · sem PDF
- `Dfe`: sem drawer com itens · sem XML viewer · sem manifestação em lote
- `Eventos`: sem drill-down do evento · sem emissão de evento aqui · sem export CSV
- `Config`: sem histórico de certificados · sem renovação automática — ⚠️ e o Non-Goal *"sem edição inline"* **está em conflito com o código** (§5.4.3)
- `Sped`: sem EFD-Contribuições (PIS/COFINS) · sem livros fiscais · sem workflow contador→SEFIN — ⚠️ e o Non-Goal *"sem gerador real"* **está em conflito com o código** (§5.4.2)

**Pendente de [W]:** os dois ⚠️ acima + a saída do `CU-FISC-16` + se `ANTI-REGRESSAO-fiscal-legacy.md`
deve existir (§0.1).

---

## 7. Requisitos não-funcionais <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

| NFR | Alvo declarado | Onde vive |
|---|---|---|
| **Tempo do cockpit** | <3s para o estado do mês | `Cockpit.charter.md` §Mission |
| **First paint com números** | KPIs **eager** (proibido `defer`) | anti-hook do `Cockpit.charter.md` |
| **Listas caras** | `Inertia::defer` obrigatório em `rows` (4 telas) | skill `inertia-defer-default` |
| **Cache** | KPIs 60s, chave **por business**, invalidada por evento | `CockpitController::KPIS_CACHE_TTL_SECONDS` |
| **Anti-N+1** | sparklines em 1 query agrupada; `with('emissao')` = 1 join | anti-hooks de `Cockpit`/`Eventos` |
| **Anti-DOS** | palette `q` ≥3 chars + 60/min · ações 30/min · SPED 3/min | rotas + `PaletteSearchController` |
| **Densidade** | linha ~48px, corpo 12.5px, número mono 13.5px | `Nfe.charter.md` §UX targets |
| **Drawer** | 480px desktop, full-width mobile, ESC e click-outside fecham | idem |
| **Observabilidade** | spans OTel `nfe.cce`, `nfe.retransmitir`, `fiscal.sped.gerar` + `Log::info/error` estruturado em toda ação | SPEC US-FISCAL-013/014/016 |

---

## 8. Estratégia de qualidade e rollout <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

### 8.1 Onde os testes deste módulo rodam — e o que **bloqueia merge**

Responder "esse teste roda?" com `grep` em workflow é a classe LC-08. As três portas, medidas:

| Pergunta | Porta | Resposta para `Modules/Fiscal/Tests/**` |
|---|---|---|
| roda em algum lugar? | `phpunit.xml` (testsuite `Feature`) + `scripts/tests/shards-plan.mjs --roots tests,Modules` | **Sim** — o diretório está listado no `phpunit.xml` e entra no universo de shards da suíte noturna CT 100 (MySQL real) |
| roda no PR? | `.github/workflows/modules-pest.yml` (matrix `Fiscal`, gatilho `Modules/Fiscal/**`) | **Sim, mas em SQLite** — e a maioria dos testes tem `markTestSkipped` no `beforeEach` porque o schema NfeBrasil exige MySQL. Na prática, no PR eles **pulam** |
| **bloqueia merge?** | [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) | **Só 4 arquivos** — `PHP / Pest (NfeBrasil · MySQL)` é **required**, e sua allowlist inclui `CockpitMultiTenantTest`, `NfeCockpitMultiTenantTest`, `EventosCockpitMultiTenantTest`, `NfseCockpitMultiTenantTest`. O job `Pest Fiscal` (modules-pest) **não** está no baseline → **advisory** |

> 🔒 **A allowlist da lane required é uma CATRACA por prova verde**, declarada no cabeçalho do próprio
> workflow (*"a lane roda só os arquivos comprovadamente VERDES contra o MySQL real semeado"*).
> Adicionar arquivo **não provado** ali bloquearia o merge de todo mundo. Por isso esta corrida
> **não alterou a allowlist** — o ratchet-up é proposta para [W] depois do primeiro verde (§8.3).

### 8.2 Pirâmide atual

- **19 arquivos Pest** em `Modules/Fiscal/Tests/Feature`, em três sabores: isolamento multi-tenant (MySQL-only), contratos de validação/whitelist (rodam sempre, sem DB) e contratos de fonte por reflexão.
- **0 specs Playwright** para `/fiscal/*`.
- **7 `*-visual-comparison.md`**, mas **nenhuma das 7 telas é ancorável** por `proto-baseline` (todas declaram `related_prototype: n/a`).

### 8.3 Rollout — o que falta para o cliente real

`US-FISCAL-018` está **parcial**: o provisionamento técnico existe (`fiscal:habilitar-business {biz}`,
idempotente, 6 permissões) mas as 4 pernas humano-limitadas seguem abertas — rodar o comando em prod,
briefing da Larissa, canary 7d e smoke salvo. **Enquanto isso, `biz=4` não vê o cockpit.**

**Proposta de ratchet-up da lane required** (decisão [W], depois de verde provado no CT 100):
`GatesPermissaoFiscalTest.php` (nasce nesta corrida) → allowlist de `nfebrasil-pest.yml`.

---

## 9. Riscos e dívidas conhecidas <!-- curado: foto que envelhece -->

🖐 **curado**

| # | Risco | Severidade | Estado |
|---|---|---|---|
| R1 | **Dado de demonstração indistinguível de dado real** numa tela fiscal (§5.4.1) | alta (confiança) | ⬜ decisão [W] |
| R2 | **6 hardcodes Tier-0 do SPED** — mitigados na Fase 1 (fallback + CFOP por UF); **Fase 2 (Strategy por regime) não existe** | alta (multa) | 🟡 contida pela flag |
| R3 | **Charter × código divergem** em `Sped` (§5.4.2) e `Config` (§5.4.3) — charter é lei e pode estar errado | média | ⬜ decisão [W] |
| R4 | **Janela de cancelamento decidida em 2 relógios** (servidor × browser) | média | ⬜ aberto (R3 do charter) |
| R5 | **IBS/CBS sem cálculo** (`US-FISCAL-021`) — produção obrigatória **03/08/2026** para CRT 3 | alta (regulatório) | ⬜ `todo` |
| R6 | **Schema race da `nfse_emissoes`** (batch 69 × 106) força tradução PT→EN e deixa `NfseCockpitControllerTest` skipado | média | 🟡 contornado |
| R7 | **15 dos 19 testes não bloqueiam merge**; no PR quase todos pulam (SQLite) | média | 🟡 por desenho da catraca |
| R8 | **Nenhum golden file do TXT SPED**; nenhuma validação no PVA-EFD oficial | alta (`[V0]`) | ⬜ aberto |
| R9 | `NotaDrawer` × `NotaDrawerV2` convivem | baixa | ⬜ aberto |
| R10 | **4 stubs de US** (003/004/006/011) já superados e não arquivados — o próprio SPEC pede | baixa | ⬜ aberto |

---

## 10. Roadmap de evolução <!-- curado: foto que envelhece -->

🖐 **curado — [W] prioriza.** Derivado das US `todo` do SPEC + das lacunas acima. Não é plano paralelo.

1. **Decidir o `CU-FISC-16`** (procedência do dado mockado) — destrava R1 e é pré-requisito honesto do canary da Larissa.
2. **Reconciliar os 2 charters divergentes** (`Sped`, `Config`) — R3. Só [W].
3. **`US-FISCAL-021` IBS/CBS** — prazo regulatório **03/08/2026**, único P0 zerado da ficha.
4. **`US-FISCAL-018`** pernas humano-limitadas → cockpit chega em biz=4.
5. **`US-FISCAL-022`** health-check do certificado A1 (o comando já existe: `CertHealthCheckCommand`).
6. **Fase 2 do motor tributário** (Strategy por regime) → destrava baixar a flag do SPED (R2/R8).
7. **`US-FISCAL-024`** split UF/Município do IBS.
8. Ratchet-up da lane required (§8.3) + fechar os 4 gates de permissão sem teste HTTP (`CU-FISC-13`).

---

## 11. Referências

- [SPEC.md](SPEC.md) · [BRIEFING.md](BRIEFING.md) · [SUPERFICIE.md](SUPERFICIE.md) (gerado) · [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) · [PLANO-TESTES-FISCAL.md](PLANO-TESTES-FISCAL.md)
- Charters: `resources/js/Pages/Fiscal/{Cockpit,Nfe,Nfse,Dfe,Eventos,Config,Sped}.charter.md`
- Casos: `resources/js/Pages/Fiscal/*.casos.md`
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) · [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) · [ADR 0321](../../decisions/0321-pin-sped-nfe-dev-master-ibs-cbs.md) · [ADR 0351](../../decisions/0351-sdd-from-source.md)
- Template: [SDD-TEMPLATE.md](../_DesignSystem/SDD-TEMPLATE.md) · Exemplar: [Produto/SDD-tela-cadastro-produto-v1.0.md](../Produto/SDD-tela-cadastro-produto-v1.0.md)
- Porta viva do estado: `node scripts/governance/requisitos-status.mjs Fiscal`
