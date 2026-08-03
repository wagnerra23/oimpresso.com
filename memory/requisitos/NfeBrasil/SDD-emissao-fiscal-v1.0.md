---
id: requisitos-nfe-brasil-sdd-emissao-fiscal-v1-0
slug: nfebrasil-sdd
title: "SDD — Emissão fiscal e manifestação (domínio NfeBrasil)"
type: sdd
module: NfeBrasil
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-07-28"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SUPERFICIE.md
  - ARCHITECTURE.md
  - CAPTERRA-FICHA.md
  - GLOSSARY.md
  - PII-LGPD-FISCAL.md
  - RUNBOOK-manifestacao.md
  - RUNBOOK-smoke-sefaz.md
  - RUNBOOK-smoke-sefaz-biz1.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0116-pivot-gold-manifestacao-destinatario-emenda-0115
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0321-pin-sped-nfe-dev-master-ibs-cbs
  - 0351-sdd-from-source
related_us:
  - US-NFE-002
  - US-NFE-008
  - US-NFE-010
  - US-NFE-050
  - US-NFE-052
---

# SDD — Software Design Document · Emissão fiscal e manifestação (domínio NfeBrasil)

> **Escopo:** as **6 telas** de `resources/js/Pages/NfeBrasil/**`, os **11 Controllers** e os
> **15 Services** de `Modules/NfeBrasil/**`, mais os **3 fluxos sem tela** que carregam o valor do
> módulo (auto-emissão por listener, DANFE/e-mail, sync NSU). Este SDD **não substitui** o
> [SPEC.md](SPEC.md) (`US-NFE-NNN`) nem os 6 charters — ele é o mapa de cima e é **de onde o UC dos
> `casos.md` deriva** (nunca do `.tsx`, [ADR 0351](../../decisions/0351-sdd-from-source.md) D-A).
>
> **Documento-modelo:** [SDD-TEMPLATE.md](../_DesignSystem/SDD-TEMPLATE.md) · exemplar de formato:
> [Produto/SDD-tela-cadastro-produto-v1.0.md](../Produto/SDD-tela-cadastro-produto-v1.0.md) ·
> irmão direto: [Fiscal/SDD-cockpit-fiscal-v1.0.md](../Fiscal/SDD-cockpit-fiscal-v1.0.md).
>
> ⚠️ **Tier 0 fiscal.** Este módulo **emite** documento com valor legal (CONFAZ Ajuste SINIEF
> 07/2005) para uma carteira ×150 clientes. Nenhum `✅` aqui vem de leitura de código — só de
> veredito de lane.

> ### 🔖 Changelog v1.0.0 (2026-07-28) — nascimento
> Primeiro SDD do módulo (`CU no SDD` era **0**, medido por
> `node scripts/governance/requisitos-status.mjs NfeBrasil`). Derivado de **3 fontes** — a 4ª
> (Delphi / Office Comercial) **não existe** neste módulo, ver §0.1. Gerado pelo agent
> [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md) na Onda 5 do
> [passo 5](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
> **Nenhum CU nasce `✅`** — os que têm teste nascem `🧪` (aguardando veredito da lane); os sem
> teste nascem `⬜`.

---

## 0. Base empírica <!-- curado: foto que envelhece -->

🖐 **curado** — foto datada. Re-medir com as portas citadas, não editar o número.

### 0.1 As fontes que existem — e a que NÃO existe

| # | Fonte | Estado neste módulo | O que deu |
|---|---|---|---|
| 1 | **Documentação canon** | ✅ rica | [SPEC.md](SPEC.md) 34 US · 6 charters · 3 RUNBOOKs · 10 ADRs próprias (`adr/arq` · `adr/tech` · `adr/ui`) · [ARCHITECTURE.md](ARCHITECTURE.md) · [GLOSSARY.md](GLOSSARY.md) · [PII-LGPD-FISCAL.md](PII-LGPD-FISCAL.md) |
| 2 | **React/Laravel atual** | ✅ | 11 Controllers · 15 Services · 6 telas · 47 arquivos Pest — inventário derivado em [SUPERFICIE.md](SUPERFICIE.md) (156 arquivos / 18 papéis) |
| 3 | **Blade AdminLTE legada** | ⚠️ **residual, e a que existia foi REDIRECIONADA** — ver abaixo | — |
| 4 | **Delphi / Office Comercial** | ❌ **ausente** | `find memory -iname "*ANTI-REGRESSAO*"` = 2 arquivos, **ambos do Produto** |

**Sobre a fonte 3 — a armadilha da Blade homônima foi verificada e NÃO existe aqui, mas por um
motivo diferente do Fiscal.** O NfeBrasil nasceu como módulo nWidart em React
([ADR arq/0001](adr/arq/0001-modulo-isolado-via-nwidart.md)); o que ele tem de "legado" é uma tela
React **anterior** que foi desligada por redirect, não uma Blade:

```php
// Modules/NfeBrasil/Routes/web.php — GET certificado
Route::get('certificado', fn () => redirect('/fiscal/config', 302))
```

O comentário no próprio arquivo declara: *"Page `Pages/NfeBrasil/Configuracao/Certificado.tsx` foi
removida — todo fluxo de cert/ambiente/testar usa `Fiscal/Config.tsx` agora"*. Os **POSTs**
(`certificado`, `certificado/testar`, `certificado/ambiente`) continuam vivos como endpoints,
consumidos pela tela do **Fiscal**. Ou seja: a superfície de certificado deste módulo é
**backend-only e a tela dela mora no Fiscal** (§5.5).

> ⛔ **Gap declarado, não preenchido.** Sem a fonte 4, o contrato de paridade é mais fraco: não há
> lista destilada de "o que o Office Comercial fazia e o React precisa manter". Onde este SDD
> afirma paridade, ela vem de SPEC/charter/ADR (fonte 1), **nunca** de suposição sobre o Delphi.
> **Decisão [W]:** criar ou não um `ANTI-REGRESSAO-nfe-legacy.md` a partir do manual WR Comercial.
> O agent é **proibido de inventá-lo** ([ADR 0351](../../decisions/0351-sdd-from-source.md)).

### 0.2 O que a medição expôs (recibos datados)

| Fato | Porta que mediu (re-rodável) | Valor em 2026-07-28 |
|---|---|---|
| US no SPEC | `node scripts/governance/requisitos-status.mjs NfeBrasil` | 34 |
| CU no SDD **antes** deste arquivo | idem | **0** |
| Telas `.tsx` | idem (consome `page-path.mjs`) | 6 |
| `casos.md` presentes **no início desta corrida** | idem | **0** — e **2** nasceram em `origin/main` no dia anterior (§0.3) |
| `Inertia::render` no módulo | `grep -rn "Inertia::render(" Modules/NfeBrasil --include=*.php` | **7** chamadas / **6** componentes (`RegraForm` é renderizado 2×: `create` e `edit`) |
| Arquivos Pest do módulo | `ls Modules/NfeBrasil/Tests/{Feature,Unit}` | **47** em `Feature`, **0** em `Unit` |
| `Implementado em:` no SPEC | `grep -c "Implementado em:" memory/requisitos/NfeBrasil/SPEC.md` | 34 de 34 |
| `Testado em:` no SPEC | `grep -c "Testado em:"` | 17 |
| `anchor_coverage` | `node scripts/governance/anchor-lint.mjs memory/requisitos/NfeBrasil/SPEC.md` | **100%** — mas **9 US** "implementada SEM teste que a cobre" (§8.4) |
| `can(` / `abort` em `TributacaoController` | `grep -n "can(\|abort" Modules/NfeBrasil/Http/Controllers/TributacaoController.php` | **0** — ver §5.4.1 |

### 0.3 Colisão de sessões paralelas — medida antes de escrever

Rodado `git log origin/main --since=2.days -- Modules/NfeBrasil resources/js/Pages/NfeBrasil`
([ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)): **duas** telas de
`Tributacao` ganharam contrato em `origin/main` em 2026-07-27, por sessão irmã:

| Tela | `casos.md` | UC | PR |
|---|---|---|---|
| `Tributacao/Index` | ✅ em `origin/main` | `UC-NFTR-01..06` | [#4880](https://github.com/wagnerra23/oimpresso.com/pull/4880) |
| `Tributacao/ConfigDefault` | ✅ em `origin/main` | `UC-NFCD-01..06` | [#4876](https://github.com/wagnerra23/oimpresso.com/pull/4876) |

**Esta corrida NÃO tocou nenhum dos dois** — nem os `casos.md`, nem os testes deles. Os prefixos
`NFTR`/`NFCD` ficam **reservados**; os UC novos usam `NFST` · `NFMA` · `NFRF` · `NFIM`
(varredura contada: `git grep -ohE "\b(CU|UC)-NF[A-Z]*-[0-9]+" origin/main` devolveu **12** ids,
todos `NFTR`/`NFCD`, nenhum `CU-NFE-*` — o namespace de CU deste SDD nasce livre).

---

## 1. Visão geral <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

O NfeBrasil é o **motor**: ele fala com a SEFAZ, assina com o certificado A1, calcula o tributo,
grava o documento fiscal e guarda o XML. O [Fiscal](../Fiscal/SDD-cockpit-fiscal-v1.0.md) é o
**cockpit** que lê esses mesmos Models e delega toda mutação de volta pra cá. A regra de fronteira,
em uma frase: **quem escreve documento fiscal é o NfeBrasil; quem confere é o Fiscal.**

### 1.1 Família de telas (6 + 3 superfícies sem tela)

| Tela | Rota | Controller | Gate | Papel |
|---|---|---|---|---|
| `Transactions/NfceStatus` | `GET /nfe-brasil/transactions/{tx}/status` | `NfeStatusController@showPage` | ⚠️ **nenhum** (só `auth`) | resultado da emissão de uma venda, com polling |
| `Manifestacao/Index` | `GET /nfe-brasil/manifestacao` | `ManifestacaoController@index` | `nfe.manifestacao.view` | DF-e recebidos + 4 eventos + bulk + sync NSU |
| `Tributacao/Index` | `GET /nfe-brasil/tributacao` | `TributacaoController@index` | ⚠️ **nenhum** | regras NCM + templates + gate de auto-emissão |
| `Tributacao/ConfigDefault` | `GET /nfe-brasil/tributacao/config-default` | `ConfigDefaultController@show` | ⚠️ **nenhum** no `show` | Nível 4 da cascade |
| `Tributacao/RegraForm` | `GET .../regras/create` · `.../regras/{id}/edit` | `TributacaoController@create` · `@edit` | ⚠️ **nenhum** no GET | form de uma regra NCM (Níveis 2/3) |
| `Tributacao/ImportCsv` | `GET /nfe-brasil/tributacao/import` | `ImportRegrasController@show` | ⚠️ **nenhum** no `show` | import em massa 2 passos |
| — (sem tela) | `POST /nfe-brasil/transactions/{tx}/emitir` | `NfeEmissaoController@emitir` | `auth` + throttle 30/min | **a emissão manual** — disparada do `Sells` |
| — (sem tela) | listener `SellCreatedOrModified` → `EmitirNfceJob` | — | gate `auto_emission_enabled` | **a emissão automática** |
| — (sem tela) | `GET /nfe-brasil/emissoes/{id}/danfe-pdf` · `POST .../reenviar-email` | `NfeEmissaoController` | `auth` | DANFE + reenvio |

> 🔴 **"A tela de emissão" não existe — e isso é o achado, não um detalhe de nomenclatura.**
> O chip pediu para começar "pela tela de emissão". Medido: **nenhum** dos 6 componentes é um
> formulário de emissão. A emissão acontece em **dois caminhos sem tela própria** — o botão
> `Emitir NF-e` do `Sells` (`Pages/Sells/_components/FiscalSection.tsx` e `VdNfeEmitModal.tsx`,
> ambos `fetch(POST /nfe-brasil/transactions/{id}/emitir)`) e o listener automático. A tela mais
> próxima da emissão, e a **única** deste módulo que o operador abre por causa de uma nota
> específica, é `Transactions/NfceStatus` — que é o **resultado** da emissão, não o disparo. Por
> isso ela é a tela-âncora desta corrida.

### 1.2 Personas

| Persona | Quem | O que faz aqui |
|---|---|---|
| **P1 · Larissa** (ROTA LIVRE biz=4) | operadora de caixa, vestuário SC | finaliza a venda e precisa saber se a NFC-e **autorizou** — é a única persona que toca `NfceStatus` no dia a dia |
| **P2 · Wagner [W]** | operador-dono, biz=1 | liga a auto-emissão, aplica template, cadastra regra NCM, reemite |
| **P3 · Responsável fiscal / contador** | interno ou do cliente | manifesta DF-e, ajusta cascade, importa CSV de NCM |
| **P4 · Gold (Comunicação Visual)** | cliente on-prem, origem da manifestação | 🔒 trilha **dormente** — `US-NFE-043..048` estão `blocked` |

---

## 2. Público-alvo e personas <!-- curado: foto que envelhece -->

🖐 **curado** — validar com [W].

A diferença de persona entre este módulo e o Fiscal é **o que muda o desenho**: o Fiscal é para
**conferência** (Eliana, contadora, olhando o mês); o NfeBrasil é para **operação** (Larissa, com
cliente na frente do balcão, precisando de um sim/não em segundos). É por isso que aqui há polling
de 2s e lá há cache de 60s. Confundir os dois é o vetor das telas competindo (§5.5).

---

## 3. Governança aplicável <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

| Regra Tier 0 | Como morde AQUI |
|---|---|
| **[ADR 0093] multi-tenant** | Models com `HasBusinessScope`: `NfeEmissao`, `NfeEvento`, `NfeDfeRecebido`, `NfeDfeEvento`, `NfeDfeNsuState`, `NfeCertificado`, `NfeFiscalRule`, `NfeBusinessConfig`. **Assimetria medida (§5.4.2):** parte dos Controllers faz `where('business_id', …)` explícito (defesa em profundidade) e parte confia só no scope — e o `ScopeByBusiness` faz early-return quando `! auth()->check()`. |
| **[ADR 0101] biz=1, nunca biz=4** | biz=1 e biz=2 são os semeados pelo `pest-mysql-setup`. biz=4 é a Larissa em produção — **jamais** em teste. |
| **REGRA MESTRE valor/estoque `[V0]`** | `MotorTributarioService` e a cascade `ProdutoFiscalContext` decidem **alíquota, CST/CSOSN e CFOP** de cada item — erro ali vira multa fiscal, não bug de tela. `NfeFiscalRule` e `NfeBusinessConfig` são superfícies `[V0]`. |
| **Numeração fiscal** | NFe cancelada via SEFAZ **nunca** sofre `forceDelete()` — o número permanece usado oficialmente (CONFAZ Ajuste SINIEF 07/2005 Art. 14). Só `rejeitada`/`denegada`/`erro_envio` viram `inutilizada`. Ver [adr/tech/0001](adr/tech/0001-numeracao-com-lockForUpdate.md) e [adr/tech/0003](adr/tech/0003-retencao-xml-5-anos-write-once.md). |
| **PII / LGPD** | `NfeCertificado.encrypted_password` é `$hidden`. O XML da NF-e carrega **CPF/CNPJ e endereço do consumidor** — [PII-LGPD-FISCAL.md](PII-LGPD-FISCAL.md) é o documento dono. Anti-hook de charter: *"não loga PII — conteúdo XML não vai em log plain text"*. |
| **[ADR 0062] runtime** | Pest deste módulo roda no CI / CT 100 — **nunca** local. E o `oimpresso-staging` **não tem as tabelas do NfeBrasil** (§8.2), então a lane de CI é o único lugar que executa de verdade. |

---

## 4. Design system aplicável <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

- **Shell:** `AppShellV2` nas 6 telas. Sem shell próprio de módulo (diferente do Fiscal, que tem `FxShell`).
- **Padrões de tela declarados nos charters:** `NfceStatus` → PT-03 Detalhe · `RegraForm` → PT-02 Formulário · `Manifestacao/Index` → Cockpit V2 list+detail ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)).
- **Ancorabilidade:** os charters declaram `related_prototype: n/a (herda PT-0X; segue o Padrão de Tela)` — logo **nenhuma das 6 telas é ancorável** por `proto-baseline`. Não é dívida: é o caso "nasce do DS" da lápide [proibicoes §5](../../proibicoes.md) 2026-07-17. **Exceção:** `Manifestacao/Index` tem [manifestacao-visual-comparison.md](manifestacao-visual-comparison.md) aprovado em 2026-05-09.
- **Componentes de domínio:** `Components/NfeBrasil/{NfceStatusBadge,FiscalStatusBadge,fiscalStatus.ts}` (compartilhados com `Sells`) + `Manifestacao/_components/{LinkedItens,LinkedFornecedor,LinkedHistorico}`.
- **Hooks:** `useNfceStatus` (polling 2s, cap 30, aborta no unmount) e `useEmissoesPorTransaction` (substitui o primeiro quando precisa dos **dois** modelos 55+65).

---

## 5. Arquitetura <!-- derivado: re-rodável do fonte -->

⚙️ **derivado** — re-rodável. Âncoras por **símbolo** (`Classe@metodo`), não por número de linha.

### 5.1 Visão em camadas

```
Pages/Sells/_components/{FiscalSection,VdNfeEmitModal}.tsx     ← o disparo mora no Sells
Pages/NfeBrasil/**/*.tsx                                       ← as 6 telas do módulo
        │  POST /nfe-brasil/transactions/{tx}/emitir  (throttle 30/min)
        │  GET  /nfe-brasil/api/transactions/{tx}/nfe-status  (polling 2s)
        ▼
Modules/NfeBrasil/Http/Controllers/*  (11)
        │  gate (quando existe) → cross-tenant guard → Service
        ▼
Modules/NfeBrasil/Services/*  (15)
   NfeService · CertificadoService · DanfeService · MotorTributarioService
   Manifestacao/{ManifestacaoService,DistribuicaoDfeService}
   Tributacao/{ImportRegrasCsvService,TributacaoTemplateService,ProdutoFiscalContext,TributoCalculado}
        │
        ▼  SEFAZ (sped-nfe/NFePHP)  +  Models append-only  +  Modules/Arquivos (XML/DANFE, ADR 0123)
```

### 5.2 Modelo de dados (núcleo)

| Tabela | Colunas que sustentam contrato | Invariante |
|---|---|---|
| `nfe_emissoes` | `modelo` (55/65) · `serie` · `numero` · `chave_44` · `status` · `cstat` · `motivo` · `transaction_id` · `metadata` (JSON) | UNIQUE `(business_id, transaction_id)` — é o que obriga o `transaction_id=null` na retransmissão (§5.3 F5) |
| `nfe_eventos` | `tipo` (tpEvento) · `cstat_evento` · `justificativa` | `UPDATED_AT = null` — append-only por lei |
| `nfe_dfe_recebidos` | `chave_44` · `nsu` · `cnpj_emitente` · `status_manifestacao` · `prazo_confirmacao_em` | prazo vem da **SEFAZ**, não de `now()+180d` hard-coded |
| `nfe_dfe_eventos` | `tipo` ∈ {`210210`,`210200`,`210220`,`210240`} · `status` · `cstat_evento` | UNIQUE `(business_id, dfe_recebido_id, tipo, nseq_evento)` = a idempotência |
| `nfe_dfe_nsu_state` | `last_nsu` · `ultimo_check_em` · `ultimo_lote_count` | 1 row por business — o cursor da distribuição |
| `nfe_fiscal_rules` | `ncm` · `uf_origem` · `uf_destino` (NULL = "todas") · `cfop` · `csosn`/`cst` · 4 alíquotas + `mva`/`fcp` | `SoftDeletes` — regra fiscal não some do histórico |
| `nfe_business_configs` | `regime` · `tributacao_default` (JSON) · `auto_emission_enabled` | 1 row por business; `auto_emission_enabled` é **o gate da emissão automática** |
| `nfe_certificados` | `uuid` · `cnpj_titular` · `valido_ate` · `ativo` · **`encrypted_password` `$hidden`** | a tela dele mora no **Fiscal** (§0.1) |

### 5.3 Fluxos críticos

**F1 · Emissão manual a partir de uma venda `[V0]` `[T0]`** —
`Pages/Sells/_components/FiscalSection.tsx` (ou `VdNfeEmitModal.tsx`) →
`POST /nfe-brasil/transactions/{tx}/emitir` (throttle **30/min**, comentado no `web.php` como
proteção do webservice SEFAZ: *"disparos descontrolados podem ban IP"*) →
`NfeEmissaoController@emitir`, nesta ordem: (1) `StoreEmissaoRequest::authorize()` — que só exige
**usuário logado**, sem permissão fiscal; (2) `business.id` da sessão, senão **400**; (3) `modelo`
∈ {`55`,`65`}, senão **422**; (4) cross-tenant guard explícito
(`Transaction::where('business_id')->where('id')->where('type','sell')`), senão **404**;
(5) **idempotência** — se já existe `NfeEmissao` do mesmo modelo com `status='autorizada'`,
devolve a existente **sem tocar a SEFAZ**; (6) `NfeService::emitirParaTransaction($transaction, $modelo)`;
(7) `Log::error` estruturado + **500** no `Throwable`.

**F2 · Emissão automática por listener `[V0]`** — `SellCreatedOrModified` → listener →
`EmitirNfceJob` (fila, `$businessId` no constructor porque `session()` não existe em fila).
O **gate** é `nfe_business_configs.auto_emission_enabled` do tenant, ligado pela tela
`Tributacao/Index`. Contrato já coberto por `UC-NFTR-01` (não duplicado aqui).
Testes existentes: `EmitirNfceAoFinalizarVendaTest`, `EmitirNFeAoReceberPagamentoTest`,
`EmitirParaInvoiceFallbackTest`, `EmitirNfceJobTest`.

**F3 · Acompanhar o resultado (`GET /nfe-brasil/transactions/{tx}/status`)** —
`NfeStatusController@showPage` renderiza `NfeBrasil/Transactions/NfceStatus` com **uma única prop**
(`transaction_id`). Todo o dado chega depois, por polling: o hook `useNfceStatus` bate em
`GET /nfe-brasil/api/transactions/{tx}/nfe-status` a cada **2s**, no máximo **30×** (= 1 min),
não repete fetch com requisição em voo, e **para no estado terminal**.
`NfeStatusController@show` monta a resposta com `where('business_id')` + `where('transaction_id')`
+ `where('modelo', 65)` + `orderByDesc('id')` (pega a mais recente em caso de retentativa), e
devolve `is_terminal = status ∈ {autorizada, rejeitada, denegada}`.

> ⚠️ **Assimetria medida:** `show()` filtra **`modelo = 65`** (só NFC-e). Uma venda que emitiu
> **NF-e 55** devolve `status: null` + *"NFC-e ainda não foi emitida pra essa venda."* — a tela
> mostra "Aguardando emissão" para uma nota **autorizada**. O `NfeEmissaoController@listar`
> (`/api/transactions/{tx}/emissoes`) existe justamente para cobrir os dois modelos, e o docblock
> dele diz *"Substituiu o GET nfe-status que retornava só modelo 65"* — mas a **tela `NfceStatus`
> continua no endpoint antigo**. É `CU-NFE-13`.

**F4 · Reemitir a partir da tela de status** — botão *"Reemitir nota"* aparece só quando
`status ∈ {rejeitada, denegada}`; faz `window.confirm` e então `router.post` no **mesmo** endpoint
de F1. A regra de quem pode ser reemitido é do `NfeService::retransmitir` — mesma família do
`CU-FISC-11` do Fiscal (§5.5), não duplicada aqui.

**F5 · Preservação da numeração `[T0]` `[reg]`** — na retransmissão, a emissão antiga é
**marcada** `inutilizada` e tem `transaction_id` liberado (para não violar o UNIQUE
`business_id + transaction_id`), guardando `metadata.original_transaction_id`; a nova sai com
**número novo**. `forceDelete()` é proibido: o número já foi consumido oficialmente
(CONFAZ Ajuste SINIEF 07/2005 Art. 14). Coberto por `NfeServiceRetransmitirTest` e, do lado do
cockpit, por `CU-FISC-11`.

**F6 · Cascade tributária em 4 níveis `[V0]`** — [ADR arq/0006](adr/arq/0006-cascade-defaults-ncm-produto.md).
A resolução do tributo de um item desce: **N1** template setorial →
**N2** `NfeFiscalRule` com NCM + UF origem + UF destino específicos →
**N3** `NfeFiscalRule` com `uf_destino = NULL` ("todas") →
**N4** `nfe_business_configs.tributacao_default`. Sem nenhum dos quatro, o motor **falha**
(`TributacaoNaoConfiguradaException`) em vez de inventar alíquota — é o ramo
*"❌ ERROR — exige tenant cadastrar default mínimo"* da ADR. A listagem de `Tributacao/Index`
ordena por `ncm → uf_origem → uf_destino IS NULL DESC → uf_destino`, e **essa ordem é a semântica
da precedência** (`UC-NFTR-06`).

**F7 · Cadastrar/editar uma regra NCM (`RegraForm`)** — `TributacaoController@create` (render com
`regra => null`) e `@edit` (render com a regra **escopada** por `business_id` + `firstOrFail`).
A gravação é `@store` (`UpsertRegraTributariaRequest`, injeta `business_id` da sessão) e `@update`
(re-busca escopada + `firstOrFail`). As duas escrevem `activity('nfe.tributacao')` com
`business_id` + NCM/UF.

**F8 · Import CSV em 2 passos `[V0]` `[T0]`** — `ImportRegrasController`:
`@show` (form, entrega `ImportRegrasCsvService::COLUNAS_OBRIGATORIAS`) →
`@preview` (`ImportRegrasCsvRequest` → `parse()` → **guarda as linhas válidas na SESSÃO** em
`nfe_import_csv_linhas` e devolve amostras + erros, **sem persistir**) →
`@aplicar` (checa `nfe.tributacao.manage` explicitamente, lê `session('business.id')` **e** as
linhas da sessão, chama `aplicar($businessId, $linhas)`, `forget` da sessão, `activity(...)`).

> ⚠️ **O tenant é resolvido DUAS vezes, em momentos diferentes.** As linhas são parseadas no passo
> 2 e escritas no passo 3, e o `business_id` do passo 3 vem da sessão **naquele instante**. Um
> usuário multi-business que troque de negócio entre o preview e o aplicar grava as regras do
> tenant A dentro do tenant B — sem erro, sem aviso. É `CU-NFE-07` `[T0]`.

**F9 · Manifestação do destinatário (`GET /nfe-brasil/manifestacao`)** —
`ManifestacaoController@index` (gate `nfe.manifestacao.view` **ou** `manage` **ou** `superadmin`)
monta 4 payloads: `itens` (`paginate(50)`, ordenado por *pendente primeiro* via
`orderByRaw` + `prazo_confirmacao_em ASC`, com filtros `status` e `q` sobre
`cnpj_emitente`/`nome_emitente`/`chave_44`), `kpis` (3 counts), `nsuState` e `permissions`.
**Nada é deferido** — e o código declara por quê: *"ROLLBACK Wave L/W7 PR #963: `Inertia::defer`
quebrava Pages (initial render undefined)"*. É desvio consciente da skill `inertia-defer-default`,
não esquecimento.

**F10 · Os 4 eventos SEFAZ + bulk + sync `[T0]`** — `@cienciar` · `@confirmar` · `@desconhecer` ·
`@naoRealizada` convergem em `aplicarEvento()` privado: gate `canManage()` → cross-tenant
`where('business_id')->where('id')->firstOrFail()` → **guarda de estado** (`status_manifestacao`
precisa ser `pendente`, **exceto** para `cienciar`) → `ManifestacaoService::{metodo}` → flash por
`$evento->isAutorizado()`. `desconhecer`/`naoRealizada` validam `justificativa` **15–255** chars
(NT 2014.002). `@bulkConfirmar` percorre os ids **em série** — o comentário do código explica:
*"cada chave precisa `nSeqEvento` isolado; paralelo geraria duplicidade (cstat 573)"* — e só toca
os `pendente`, contando sucessos/falhas. `@syncNow` **despacha** `BuscarDfesRecebidosJob` na fila
(*"não sync — SEFAZ pode travar 30s+"*).
No Service, os códigos são constantes: `210210` ciência · `210200` confirmação · `210220`
desconhecimento · `210240` não realizada; `CSTAT_AUTORIZADOS = ['135','136']`;
`JUSTIFICATIVA_MIN_CHARS = 15`; e a **idempotência** é uma busca por evento já `autorizado` do mesmo
tipo, que retorna o existente **sem tocar a SEFAZ**.

### 5.4 Dívidas próprias (contadas, não corrigidas)

#### 5.4.1 · Três das cinco mutações de tributação não têm gate de permissão `[T0]`

Varredura contada em `Modules/NfeBrasil/Http/Controllers/TributacaoController.php`
(`grep -n "can(\|abort"` → **0 ocorrências**), cruzada com as assinaturas dos métodos:

| Mutação | Assinatura | Gate | Consequência se qualquer usuário do tenant chamar |
|---|---|---|---|
| `@store` | `UpsertRegraTributariaRequest` | ✅ `nfe.tributacao.manage` | — |
| `@update` | `UpsertRegraTributariaRequest` | ✅ `nfe.tributacao.manage` | — |
| `@destroy` | `Request` | ❌ **nenhum** | apaga (soft) uma regra NCM → o item cai para o Nível 4 e passa a sair com **outra** alíquota |
| `@toggleAutoEmission` | `Request` | ❌ **nenhum** | **liga a emissão automática de documento fiscal** do tenant |
| `@aplicarTemplate` | `Request` | ❌ **nenhum** | substitui regime + `tributacao_default` inteiros |

O rota-group é `['web','auth','SetSessionData','language','timezone','AdminSidebarMenu']` — **sem**
middleware de permissão. E o docblock da classe **afirma o contrário**: *"Permissão:
`nfe.tributacao.manage` (FormRequest::authorize + `DataController::user_permissions`)"* — verdadeiro
para 2 dos 5. Os GETs (`@index`, `@create`, `@edit`, `ConfigDefaultController@show`,
`ImportRegrasController@show`) também são ungated.

**Não é cross-tenant** (o escopo por business segura, e isso foi verificado): é **RBAC dentro do
tenant**. Um usuário de caixa autenticado liga a auto-emissão. Numa carteira ×150 clientes fiscais,
isso é severo — e é exatamente o que `CU-NFE-06` mede, **falhando primeiro**.

> ⚖️ **O que é decisão [W]:** se o `destroy`/`toggle`/`template` ganham `abort_unless` agora
> (correção de segurança) ou viram US priorizada. O agent **não** editou Controller.

#### 5.4.2 · Duas chaves de sessão para o mesmo tenant, no mesmo módulo

Medido: `ManifestacaoController` usa `session('user.business_id')`; `NfeStatusController`,
`NfeEmissaoController`, `TributacaoController`, `ConfigDefaultController` e `ImportRegrasController`
usam `session('business.id')`. As duas existem e coincidem em operação normal — mas o
`ScopeByBusiness` lê **`user.business_id`**, então um teste que semeie só uma delas produz
**verde que não prova nada** (foi a causa registrada na allowlist do `nfebrasil-pest.yml` em
2026-06-24). Os testes desta corrida semeiam **as duas**, por isso.

Somam-se as **assimetrias de guard**: `ManifestacaoController::buildItensPayload` (a listagem)
**não** tem `where('business_id')` explícito — confia só no global scope — enquanto
`buildKpisPayload` e todos os POSTs do mesmo Controller têm. Defesa em profundidade parcial.

#### 5.4.3 · A tela de status pergunta só por NFC-e, e tem um link morto

Duas medições no mesmo arquivo (`Pages/NfeBrasil/Transactions/NfceStatus.tsx`):

1. **Link morto.** O botão *"Baixar DANFE"* aponta para `/nfe-brasil/transactions/{tx}/danfe`.
   Varredura contada nas rotas (`grep -rn "danfe" Modules/NfeBrasil/Routes/*.php routes/*.php`):
   **2 linhas, ambas de `emissoes/{id}/danfe-pdf`** — a rota `transactions/{tx}/danfe` **não
   existe**. O próprio `.tsx` se autodenuncia no rodapé: *"rota `/danfe` assumida; confirmar nome
   real no controller"*. Consequência: 404 no clique, na tela que a operadora abre quando a nota
   **autorizou**.
2. **Modelo fixo 65** (§5.3 F3) — venda com NF-e 55 nunca sai de "Aguardando emissão".

E o consumidor da tela erra o parâmetro: `Pages/Sells/_components/FiscalSection.tsx` monta
*"Detalhes"* como `/nfe-brasil/transactions/${em.id}/status`, onde `em` é uma **emissão**
(`serializeEmissao` devolve `id` = id da emissão) e a rota espera `{tx}` = id da **transaction**.
Contado: no mesmo bloco, o link do DANFE usa `em.id` **corretamente** (a rota é por emissão) e o de
status usa `em.id` **incorretamente** (a rota é por transaction).

> ⚖️ **Decisão [W] + fora da área deste chip.** Os três defeitos vivem em `.tsx`
> (`NfceStatus.tsx` é do módulo; `FiscalSection.tsx` é do **Sells**), e o agent é proibido de
> editar tela viva sem charter + gate visual. Registrado como `CU-NFE-13` `⬜` e como `[BACKLOG]`
> no `NfceStatus.casos.md`. **Não** virou UC com id: a saída certa (corrigir o link? passar
> `emissao_id` na prop? migrar a tela para `/emissoes`?) é escolha de produto, e UC sem contrato em
> 2 fontes nasce órfão e trava o merge de quem for atendê-lo
> ([proibicoes §5](../../proibicoes.md) 2026-07-16).

#### 5.4.4 · Charters prometendo teste que não existe (fato, corrigido nesta corrida)

Varredura contada: `find Modules/NfeBrasil/Tests -iname "*Charter*"` → **0 arquivos**;
o diretório `Modules/NfeBrasil/Tests/Charters/` **não existe**. Mesmo assim:

| Charter | Promete | Real |
|---|---|---|
| `NfceStatus.charter.md` §Métricas vivas | `Tests/Charters/NfceStatusCharterTest.php` com **10** `it(...)` | 0 — o teste real é `NfeStatusControllerTest.php` |
| `Manifestacao/Index.charter.md` §Métricas vivas | `Tests/Charters/ManifestacaoCharterTest.php` com **13** `it(...)` | 0 — os reais são `ManifestacaoControllerTest` + `ManifestacaoServiceTest` |

Mais três fatos errados no `NfceStatus.charter.md`, todos verificados por varredura contada:
`NfceStatusController::show` (a classe é `NfeStatusController` e o método da página é `showPage`) ·
`GET /nfe-brasil/transactions/{tx}/nfce/status` (a rota real é
`/nfe-brasil/api/transactions/{tx}/nfe-status`) · `NfeService::consultarStatusEmissao` — método que
**não existe** (`grep` em todo o repo: **1** ocorrência, e é a própria linha do charter).

Corrigidos como **fato** nesta corrida (Fase 2.6 do agent: fato sim, intenção não). Nenhum
Non-Goal, Anti-hook, Goal ou persona foi tocado.

#### 5.4.5 · Um charter que se contradiz sobre o próprio status — **[W]**

`Manifestacao/Index.charter.md` tem `status: draft` no frontmatter, e o corpo diz
*"**Status:** live em 2026-05-10"* + *"Non-Goals + Anti-hooks aprovados por Wagner em 2026-05-10"*,
com o mesmo registro no §Histórico. Os dois não podem estar certos.

> ⚖️ **Não corrigido de propósito.** `draft → live` é **promoção**, não fato: muda o que passa a
> ser lei executável (os ❌ viram `[must]`). É decisão [W] — do mesmo jeito que a tela irmã
> `Tributacao/Index` só pôde ter `[must]` derivado de ❌ porque o charter dela **é** `live`.

### 5.5 Fronteira com o Fiscal — as telas que competem

O [SDD do Fiscal](../Fiscal/SDD-cockpit-fiscal-v1.0.md) §5.4 já nomeia isto do lado dele
(*"telas competindo entre Fiscal e NfeBrasil"*). Do lado de cá, medido:

| Capacidade | Tela NfeBrasil | Tela Fiscal | Quem é o dono do contrato |
|---|---|---|---|
| **Certificado A1** | ❌ removida (redirect 302) | `Fiscal/Config` | **Fiscal** — `CU-FISC-06`. Aqui sobram só os POSTs |
| **Manifestar DF-e** | `Manifestacao/Index` | `Fiscal/Dfe` | **as duas existem**. O contrato dos 4 eventos SEFAZ é `CU-FISC-07`; aqui ficam bulk, sync NSU e append-only (`CU-NFE-08/09/10`) |
| **Cancelar / CC-e / inutilizar / retransmitir** | sem tela (Services) | `Fiscal/Nfe` + `AcoesController` | **Fiscal** — `CU-FISC-08/09/10/11`. Os Services são daqui |
| **Janela legal de cancelamento** | `NfeEmissao::isCancelavel()` | `NfeCockpitController::isCancelavel` | **Fiscal** — `CU-FISC-03` |
| **Timeline de eventos** | — | `Fiscal/Eventos` | **Fiscal** — `CU-FISC-05` |
| **SPED EFD** | — | `Fiscal/Sped` | **Fiscal** — `CU-FISC-15` |
| **Emitir** | `POST .../emitir` + listener | ❌ Non-Goal declarado (*"sem emissão nova"*) | **NfeBrasil** — `CU-NFE-01/02/03` |
| **Cascade tributária** | `Tributacao/*` (4 telas) | — | **NfeBrasil** — `CU-NFE-05/06/07` + `UC-NFTR-*`/`UC-NFCD-*` |

> 🔗 **Regra deste SDD:** onde a coluna "dono" diz **Fiscal**, este documento **aponta** para o CU
> de lá e **não** escreve um CU equivalente. CU duplicado entre módulos é dívida, não cobertura —
> ele dobra o custo de manutenção e cria dois vereditos para o mesmo comportamento.

---

## 6. Casos de uso <!-- derivado: re-rodável do fonte -->

⚙️ **derivado + 🖐 [W] confere.** Estado: `✅` provado por teste verde que o cita · `🟡` parcial ·
`🔴` falso/quebrado · `🧪` tem teste, **veredito pendente da lane** · `⬜` não-verificado.
**Nenhum CU nasce `✅`** — o agent não roda teste ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

### 6.0 O que este SDD NÃO redefine — ponteiros para o Fiscal

> Estes comportamentos **existem** neste módulo (os Services são daqui) mas o **contrato** já está
> escrito e enforçado do lado do cockpit. Citar em vez de duplicar é decisão de desenho, §5.5.

| Comportamento | Contrato canônico | Onde o código mora |
|---|---|---|
| Janela legal de cancelamento (24h NFC-e / 168h NF-e) | [`CU-FISC-03`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `NfeEmissao::isCancelavel` |
| Timeline append-only de eventos SEFAZ | [`CU-FISC-05`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `NfeEvento` (`UPDATED_AT = null`) |
| Certificado A1 + validade + senha nunca no payload | [`CU-FISC-06`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `CertificadoService` · `NfeCertificado` |
| **Os 4 eventos de manifestação + regra de justificativa** | [`CU-FISC-07`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `Manifestacao/ManifestacaoService` |
| Cancelar com motivo ≥15 chars (evento 110111) | [`CU-FISC-08`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `NfeService::cancelar` |
| CC-e 110110 (15–1000 chars, seq 1–20) | [`CU-FISC-09`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `NfeCartaCorrecaoService` |
| Inutilizar faixa numérica | [`CU-FISC-10`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `NfeInutilizacaoService` |
| **Retransmitir sem apagar (`forceDelete` proibido)** | [`CU-FISC-11`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `NfeService::retransmitir` |
| SPED EFD-ICMS/IPI | [`CU-FISC-15`](../Fiscal/SDD-cockpit-fiscal-v1.0.md) | `Modules/Fiscal` (o motor tributário é daqui) |

E, **dentro do próprio módulo**, os contratos já escritos pela sessão irmã (§0.3) — também não
reabertos: `UC-NFTR-01..06` (`Tributacao/Index`) e `UC-NFCD-01..06` (`Tributacao/ConfigDefault`).

### 6.0-bis Os CU que nascem sem UC — e por quê

A porta viva (`requisitos-status.mjs`) lista como lacuna todo CU do §6 que nenhum `casos.md`
ancore. Isso está **certo** e é a fila de crescimento honesta — mas os motivos são de **três**
naturezas diferentes, e confundi-los faria a lista parecer um placar de falha:

| CU | Por que sem UC | O que fecharia |
|---|---|---|
| `CU-NFE-01` emitir sem emitir 2× | **a emissão não tem tela** (§1.1) — o disparo mora em `Pages/Sells/**`, fora da área deste chip | um `casos.md` do lado do `Sells`, ou um contrato de fluxo-sem-tela |
| `CU-NFE-03` auto-emissão por tenant | **ponteiro** — o contrato é `UC-NFTR-01`, já escrito e testado | nada: duplicar seria dívida |
| `CU-NFE-04` numeração preservada | **ponteiro** — o contrato é `CU-FISC-11`; o teste deste lado é `NfeServiceRetransmitirTest` | declarar `@covers-us` naquele teste, se [W] quiser a cadeia fechada aqui também |
| `CU-NFE-05` cascade tributária | cobertura **existe** (`MotorTributarioServiceTest`) mas é de Service, não de tela — e a tela do Nível 4 é `UC-NFCD-*` | UC de fluxo-sem-tela, se [W] quiser |
| `CU-NFE-06` permissão fiscal | ✅ **ancorado** por `UC-NFRF-01` e `UC-NFRF-04` | — |
| `CU-NFE-10` evento append-only | parcial: o Service é coberto; falta o caso que prova a **constraint** no MySQL | `[BACKLOG]` já registrado em `Manifestacao/Index.casos.md` |
| `CU-NFE-12` segredo/PII | ⬜ **sem teste deste lado** — só o cockpit tem (`CU-FISC-14`); o `UC-NFST-05` cobre **um** payload, não a superfície toda | teste de log + XML |
| `CU-NFE-13` status certo da nota | ⛔ **sem contrato em 2 fontes** — a saída é decisão [W] (§5.4.3) | decisão, depois UC |

> Regra que este SDD seguiu: **CU sem UC é honesto; UC sem teste não é.** Um UC com id e sem teste
> que o cite é órfão, e o `casos-gate` G-2 (required) bloqueia o merge de quem for atendê-lo
> ([proibicoes §5](../../proibicoes.md) 2026-07-16). Por isso os 8 acima ficaram como CU + fila,
> e não viraram 8 UC vazios.

### 6.1 Emissão — a razão de o módulo existir

#### CU-NFE-01 — Emitir NFC-e/NF-e de uma venda sem emitir duas vezes `[must]` `[V0]` `[T0]` ⬜
*Dado* uma venda finalizada do meu business; *quando* o operador dispara a emissão; *então* sai
**uma** nota — e um segundo clique devolve a mesma, sem tocar a SEFAZ.
1. `[T0]` venda de outro business → **404**, e nenhuma emissão é criada
2. `[must]` `modelo` fora de {55, 65} → **422** (a SEFAZ não é chamada)
3. `[must]` já existindo emissão `autorizada` do mesmo modelo, a resposta é a **existente** e a
   SEFAZ **não** é chamada — idempotência
4. `[must]` sem `business.id` na sessão → **400**
5. ⬜ **sem teste** — o throttle 30/min (proteção do webservice; `RetencaoLoopE2ETest` só prova que
   a rota está registrada)

#### CU-NFE-02 — Acompanhar o resultado da emissão até o estado terminal `[must]` 🧪
*Dado* uma venda que acabou de ser finalizada; *quando* a operadora abre a tela de status; *então*
ela vê o desfecho SEFAZ da nota **daquela venda** e a consulta para sozinha quando o desfecho chega.
1. `[T0]` a consulta de status de uma venda de outro business **não** devolve a emissão dele
2. `[must]` venda sem emissão devolve estado "ainda não emitida" — e **não** 404 nem erro
3. `[must]` `is_terminal` é verdadeiro **exatamente** em `autorizada`/`rejeitada`/`denegada`
   (é o que faz o polling parar; um `pendente` marcado terminal congela a tela num estado falso)
4. `[must]` havendo mais de uma emissão para a mesma venda (retentativa), a consulta devolve a
   **mais recente**
5. `[T0]` o segredo do certificado e o XML **não** viajam no payload da consulta

#### CU-NFE-03 — Emitir automaticamente só onde o tenant pediu `[must]` `[T0]` 🧪
*Dado* dois businesses; *quando* um liga a emissão automática; *então* só o dele emite sozinho.
> 🔗 **Contrato já escrito e testado** em `UC-NFTR-01` (`Tributacao/Index.casos.md`, `origin/main`).
> Fica aqui como **entrada do mapa**, sem UC novo — duplicar o caso duplicaria o veredito.

#### CU-NFE-04 — O número fiscal nunca é reaproveitado nem apagado `[must]` `[T0]` `[reg]` 🧪
*Dado* uma nota que precisou ser retransmitida; *quando* a nova sai; *então* a antiga **permanece
no banco** (marcada `inutilizada`, `transaction_id` liberado) e a nova recebe número novo.
> 🔗 Contrato canônico: [`CU-FISC-11`](../Fiscal/SDD-cockpit-fiscal-v1.0.md). Base legal: CONFAZ
> Ajuste SINIEF 07/2005 Art. 14 + [adr/tech/0001](adr/tech/0001-numeracao-com-lockForUpdate.md).
> Testes deste lado: `NfeServiceRetransmitirTest`, `NfeServiceIdempotenciaRetryTest`.

### 6.2 Tributação — o que decide a alíquota da nota

#### CU-NFE-05 — A cascade resolve o tributo, e falha alto quando não resolve `[must]` `[V0]` 🧪
*Dado* um item com NCM; *quando* o motor calcula; *então* desce N1→N2→N3→N4 e, **não achando
nenhum**, lança `TributacaoNaoConfiguradaException` em vez de inventar alíquota.
> Contrato: [ADR arq/0006](adr/arq/0006-cascade-defaults-ncm-produto.md). Testes existentes:
> `MotorTributarioServiceTest`, `TributacaoTemplateServiceTest`, `SyncFiscalRuleToTaxRateTest`.
> A ordem de precedência exibida na tela é `UC-NFTR-06`.

#### CU-NFE-06 — Só quem tem permissão fiscal mexe na tributação `[must]` `[T0]` 🧪
*Dado* um usuário autenticado **sem** `nfe.tributacao.manage`; *quando* ele tenta criar, editar ou
apagar uma regra NCM, aplicar template ou ligar a emissão automática; *então* recebe **403** e
**nada muda no banco**.
1. `[must]` `POST /regras` e `PUT /regras/{id}` sem a permissão → 403 (controle positivo: **com** a
   permissão, gravam)
2. `[T0]` `PUT`/`DELETE` de regra de **outro** business → 404, e a regra alheia sobrevive
3. `[must]` 🔴 **falha esperada** — `DELETE /regras/{id}` sem a permissão **apaga a regra** hoje
   (§5.4.1)
4. `[must]` 🔴 **falha esperada** — `POST /auto-emission/toggle` sem a permissão **liga a emissão
   automática** hoje (§5.4.1)
> ⚠️ Os itens 3 e 4 nascem **vermelhos por desenho**: o teste é *failing-first* e o `❌` é o
> achado, não um conserto silencioso ([proibicoes §Precedência](../../proibicoes.md)). Corrigir o
> Controller é decisão [W].

#### CU-NFE-07 — Import CSV grava no tenant certo, e só depois do preview `[must]` `[V0]` `[T0]` 🧪
*Dado* um CSV de regras NCM; *quando* o responsável fiscal importa em 2 passos; *então* nada é
gravado no preview, e o que é gravado no aplicar pertence ao business **de quem parseou**.
1. `[must]` o `preview` **não** escreve em `nfe_fiscal_rules` (só devolve amostras + erros)
2. `[must]` `aplicar` sem preview anterior é recusado com erro de campo — não grava vazio
3. `[must]` `aplicar` sem `nfe.tributacao.manage` → **403**
4. `[T0]` 🔴 **falha esperada** — trocar o business entre o preview e o aplicar grava as linhas do
   tenant A dentro do tenant B (§5.3 F8)
5. `[must]` CSV sem as colunas obrigatórias é recusado inteiro, sem gravar linha parcial

### 6.3 Manifestação — o que o Fiscal não cobre

> Os **4 eventos** e a regra de justificativa são [`CU-FISC-07`](../Fiscal/SDD-cockpit-fiscal-v1.0.md).
> Os três CU abaixo são o que **só** existe na tela deste módulo.

#### CU-NFE-08 — Confirmar em lote sem duplicar evento nem tocar o que não é meu `[must]` `[T0]` 🧪
*Dado* várias NF-e recebidas; *quando* o responsável confirma em lote; *então* só as **pendentes**
do **meu** business são manifestadas, uma a uma, e o resultado diz quantas foram.
1. `[T0]` id de DF-e de outro business no lote é **ignorado** (não manifesta, não vaza)
2. `[must]` DF-e já manifestada no lote é ignorada — não gera segundo evento
3. `[must]` lote vazio devolve erro e **não** dispara SEFAZ
4. `[must]` o processamento é **sequencial** — paralelo colidiria `nSeqEvento` (cstat 573)

#### CU-NFE-09 — Buscar DF-e novas sob demanda sem travar a tela `[should]` `[T0]` 🧪
*Dado* que a SEFAZ pode demorar 30s+; *quando* o operador pede sincronizar agora; *então* a tela
volta imediatamente e o trabalho vai para a fila com o **meu** `business_id`.
1. `[must]` o job é **despachado**, não executado no request
2. `[T0]` o job carrega o `business_id` do tenant da sessão (em fila não há `session()`)
3. `[must]` sem `nfe.manifestacao.manage` → **403**

#### CU-NFE-10 — Evento de manifestação é append-only e idempotente `[must]` `[reg]` 🧪
*Dado* uma NF-e já confirmada; *quando* o mesmo evento é aplicado de novo; *então* o registro
existente é devolvido **sem** nova chamada SEFAZ, e nada é editado nem apagado.
1. `[reg]` UNIQUE `(business_id, dfe_recebido_id, tipo, nseq_evento)` — a idempotência é do schema,
   não só do código
2. `[must]` só `cstat` **135** ou **136** conta como evento autorizado
3. `[must]` DF-e não-`pendente` recusa `confirmar`/`desconhecer`/`naoRealizada` — mas **aceita**
   `cienciar` (ciência é registrável a qualquer momento)

### 6.4 Invariantes transversais

#### CU-NFE-11 — Isolamento multi-tenant nas 6 telas do módulo `[must]` `[T0]` 🧪
*Dado* dados de outro business; *quando* qualquer tela ou endpoint do NfeBrasil consulta; *então*
nada cross-tenant aparece — em lista, contagem, JSON lazy ou payload de status.
1. `[T0]` listagem de manifestação e seus KPIs isolam por business
2. `[T0]` `/manifestacao/{id}/itens` e `/{id}/eventos` de DF-e alheia → **404**
3. `[T0]` `/api/transactions/{tx}/nfe-status` de venda alheia não devolve a emissão dela
4. `[T0]` listagem e mutação de regras NCM isolam por business (também em `UC-NFTR-04/05`)
> Este é o **meio-tenant** do mesmo invariante que `CU-FISC-12` cobre do lado do cockpit — não é
> duplicata: são superfícies HTTP diferentes, e o cockpit não passa por estas rotas.

#### CU-NFE-12 — Segredo e PII fiscal não viajam nem vão para log `[must]` `[T0]` ⬜
*Dado* certificado, XML e dados do consumidor; *quando* a tela serializa ou o Service loga; *então*
a senha do certificado **nunca** viaja e o XML não vai em log plain text.
> Contrato: [PII-LGPD-FISCAL.md](PII-LGPD-FISCAL.md) + anti-hooks dos 2 charters + `$hidden` no
> Model. O lado do cockpit é `CU-FISC-14`. **⬜ sem teste deste lado.**

### 6.5 Procedência e alcance — o que precisa de [W]

#### CU-NFE-13 — O operador chega ao status certo da nota `[must]` ⬜
*Dado* uma venda com nota emitida; *quando* a operadora clica para ver o resultado; *então* ela vê
**aquela** nota — qualquer que seja o modelo — e consegue baixar o DANFE.
> ⛔ **Três defeitos medidos (§5.4.3), sem contrato em 2 fontes que diga qual é a saída certa** →
> **não** virou UC com id (viraria órfão e bloquearia o merge de quem for atendê-lo). Está como
> `[BACKLOG]` em `NfceStatus.casos.md` e **precisa de decisão [W]**.

### 6.6 Non-Goals — **só [W] preenche** 🖐

> O agent é **proibido de inferir Non-Goal**. Abaixo são **ponteiros** para os que [W] já aprovou
> nos charters — nenhum foi criado nesta corrida.

- `NfceStatus`: sem reemissão pela tela¹ · sem cancelamento direto · sem edição da venda · sem download direto de DANFE¹ · sem histórico de status · sem broadcast no Hostinger
- `Manifestacao/Index`: sem import manual de XML · sem editar/anular evento registrado · sem aprovar XAPI · sem NF-e inversa ao desconhecer · sem sync fora do schedule · sem notificar fornecedor
- `RegraForm`: sem import em massa · sem listar/excluir · sem derivar alíquota do NCM · sem gravar em outro tenant
- `ImportCsv` · `Tributacao/Index` · `ConfigDefault`: ver os charters (os dois últimos já com `casos.md` em `origin/main`)

> ¹ ⚠️ **Conflito medido, não resolvido:** o `NfceStatus.charter.md` declara `❌ Reemissão NFC-e` e
> `❌ Download direto DANFE` como Non-Goals — e o `.tsx` **implementa os dois** (botão *"Reemitir
> nota"* postando em `/emitir`, botão *"Baixar DANFE"*). O charter é `status: draft` (Non-Goals
> *"aguardam aprovação Wagner"*, texto do próprio arquivo), então os ❌ **não** viraram `[must]`
> nesta corrida. **Decisão [W]:** aprovar os Non-Goals (e então o código precisa mudar) ou
> reconciliá-los com o que a tela faz.

**Pendente de [W]:** os dois ⚠️ acima · o `status` contraditório do charter de Manifestação
(§5.4.5) · a saída do `CU-NFE-13` · o gate das 3 mutações (§5.4.1) · se
`ANTI-REGRESSAO-nfe-legacy.md` deve existir (§0.1).

---

## 7. Requisitos não-funcionais <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

| NFR | Alvo declarado | Onde vive |
|---|---|---|
| **Resposta do status** | polling 2s, cap **30** (≈1 min, cobre p99 SEFAZ ~30s) | `useNfceStatus` + docblock do `NfeStatusController` |
| **First-paint `NfceStatus`** | p95 < 1200ms (tela simples: badge + título) | `NfceStatus.charter.md` §UX Targets |
| **First-paint `Manifestacao`** | p95 < 1500ms (lista 50 + KPIs + nsuState) | `Index.charter.md` §UX Targets |
| **Aplicar evento** | < 2000ms (síncrono SEFAZ) · sync NSU < 4000ms (assíncrono) | idem |
| **Anti-DOS SEFAZ** | `POST .../emitir` **throttle 30/min** | `Routes/web.php` + comentário CONFAZ |
| **Listas caras** | `Inertia::defer` em `regras`/`templates` (Tributação) — **e desvio consciente** em Manifestação (§5.3 F9) | skill `inertia-defer-default` |
| **Densidade** | cabe em **1280px** sem scroll horizontal (canon ROTA LIVRE) | os 2 charters |
| **Observabilidade** | span OTel `nfe.manifestar` com `chave_44` + `tipo_evento`; `Log::info`/`error` estruturado nas ações | `ManifestacaoService::aplicarEvento` · `ObservabilityTest` |
| **Retenção de XML** | write-once, 5 anos | [adr/tech/0003](adr/tech/0003-retencao-xml-5-anos-write-once.md) · `Modules/Arquivos` (ADR 0123, signed URL 60min) |

---

## 8. Estratégia de qualidade e rollout <!-- derivado: re-rodável do fonte -->

⚙️ **derivado**

### 8.1 Onde os testes deste módulo rodam — e o que **bloqueia merge**

Responder *"esse teste roda?"* com `grep` em workflow é a classe **LC-08**. As três portas, cada
uma respondendo uma pergunta diferente:

| Pergunta | Porta | Resposta para `Modules/NfeBrasil/Tests/**` |
|---|---|---|
| roda em algum lugar? | `phpunit.xml` + `scripts/tests/shards-plan.mjs --roots tests,Modules` | **Sim** — o diretório entra no universo de shards da suíte noturna CT 100 (MySQL real). Não existe "verde impossível" aqui |
| roda no PR? | `.github/workflows/modules-pest.yml` (matrix `NfeBrasil`) | **Sim, em SQLite** — e os testes que exigem schema real dão `markTestSkipped`. Na prática, no PR eles **pulam** |
| **bloqueia merge?** | [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) | **`PHP / Pest (NfeBrasil · MySQL)` é required com `enforce_admins`** — e roda **só a allowlist explícita** do `nfebrasil-pest.yml` |

> 🔒 **A allowlist da lane required é uma CATRACA POR PROVA VERDE.** Ela lista arquivo a arquivo os
> testes comprovadamente verdes contra o MySQL real semeado. Esta corrida **não roda teste**
> ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)), logo **não tem a prova
> que a catraca exige** — e adicionar arquivo não provado ali **bloquearia o merge de todos**.
> Por isso a allowlist **não foi tocada**; o ratchet-up é **proposta** para [W] em §8.3.

### 8.2 Pirâmide atual

- **47 arquivos Pest** em `Modules/NfeBrasil/Tests/Feature`, **0** em `Unit`.
- **1 spec Pest Browser**: `tests/Browser/NfeBrasil/NfceStatusTest.php` — declarada
  *characterization* do estado S0 da tela.
- **`oimpresso-staging` (CT 100) não tem as tabelas do NfeBrasil** — medido pela sessão irmã em
  2026-07-27 (`Schema::hasTable` → ausentes). A suíte **SKIPa inteira** lá. Consequência dura ao
  ler qualquer resultado: **conferir a contagem de testes passados no JUnit, não o check verde** —
  suite que SKIPa também fica verde (gate mudo).

### 8.3 Proposta de ratchet-up da lane required — **decisão [W], depois de verde provado**

Os 3 arquivos que nascem nesta corrida, na ordem de prioridade Tier 0:

| Arquivo | Por que entraria | Pré-requisito |
|---|---|---|
| `TributacaoGatesContratoTest.php` | `CU-NFE-06`/`CU-NFE-07` — RBAC + cross-tenant de escrita fiscal | **vai nascer vermelho** (§5.4.1) — só entra depois de o gate existir |
| `ManifestacaoContratoTest.php` | `CU-NFE-08/09/10/11` — bulk + sync + append-only | 1 corrida verde no CT 100 |
| `NfeStatusContratoTest.php` | `CU-NFE-02` — o payload que a operadora lê | 1 corrida verde no CT 100 |

### 8.4 As 9 US "implementada sem teste que a cobre"

`anchor-lint` acusa 9 US com `Implementado em:` e **nenhum** `@covers-us`: `US-NFE-040`, `041`,
`049`, `050`, `051`, `052`, `060`, `061`, `062`. Esta corrida fecha as que os testes novos
**de fato** cobrem — `US-NFE-052` (UI de manifestação) e `US-NFE-050` (os eventos, pela borda HTTP).
As demais seguem abertas e **não** foram marcadas: declarar cobertura que o teste não dá é o
falso-verde que este processo existe para matar.

### 8.5 Rollout

`US-NFE-054` (smoke homologação SEFAZ-SC biz=1, **Goal #1 do CYCLE-03**) e `US-NFE-059` (smoke prod
end-to-end) seguem `todo` — as pernas são humano-limitadas. Enquanto isso, o pipeline está
**armado** (biz=1/SC, ambiente=2, cert ativo) mas **a primeira nota real ainda não saiu**.
A trilha Gold (`US-NFE-042..048`) está 🔒 dormente.

---

## 9. Riscos e dívidas conhecidas <!-- curado: foto que envelhece -->

🖐 **curado**

| # | Risco | Severidade | Estado |
|---|---|---|---|
| R1 | **3 de 5 mutações de tributação sem gate de permissão** — incluindo o toggle da auto-emissão (§5.4.1) | **alta** (fiscal ×150) | 🔴 teste failing-first nesta corrida |
| R2 | **Import CSV resolve o tenant duas vezes** — troca de business entre preview e aplicar grava no lugar errado (§5.3 F8) | alta (`[V0]` `[T0]`) | 🔴 teste failing-first nesta corrida |
| R3 | **Tela de status só enxerga NFC-e 65** — NF-e 55 fica eternamente "aguardando" (§5.3 F3) | média (confiança) | ⬜ decisão [W] |
| R4 | **Link "Baixar DANFE" aponta para rota inexistente** (§5.4.3) | média | ⬜ decisão [W] |
| R5 | **"Detalhes" no Sells passa id de emissão onde a rota espera id de transaction** (§5.4.3) | média | ⬜ fora da área deste chip |
| R6 | **Duas chaves de sessão para o mesmo tenant** no mesmo módulo (§5.4.2) | média | 🟡 contornado nos testes novos |
| R7 | **Charter de Manifestação com `status` contraditório** (§5.4.5) | média | ⬜ decisão [W] |
| R8 | **Non-Goals do `NfceStatus` conflitam com o `.tsx`** (§6.6 nota ¹) | média | ⬜ decisão [W] |
| R9 | **`oimpresso-staging` sem as tabelas do módulo** — a suíte SKIPa e o verde não prova nada (§8.2) | alta (gate mudo) | 🟡 conhecido, reportado |
| R10 | **A 1ª nota real nunca foi emitida** — `US-NFE-054`/`059` `todo` | alta (negócio) | ⬜ humano-limitado |
| R11 | **`Manifestacao` não usa `Inertia::defer`** — desvio consciente por regressão de render (§5.3 F9) | baixa | 🟡 documentado no código |
| R12 | **Contingência EPEC, MDF-e/CT-e** ausentes ([BRIEFING](BRIEFING.md) §Gaps) | média | ⬜ backlog |

---

## 10. Roadmap de evolução <!-- curado: foto que envelhece -->

🖐 **curado — [W] prioriza.** Derivado das US `todo` do SPEC + das lacunas acima.

1. **Fechar R1** (gate das 3 mutações) — é segurança, não feature; o teste já aponta onde.
2. **Fechar R2** (tenant do import) — `[V0]`: regra tributária no tenant errado vira nota errada.
3. **`US-NFE-054`** — a 1ª NFC-e em homologação SEFAZ-SC. Sem ela, todo o resto é teoria.
4. **Decidir R3/R4/R5** (`CU-NFE-13`) — a tela que a Larissa abre é a que mais engana hoje.
5. **`US-NFE-062`** AuditLog nas mutações fiscais — parcialmente feito (tributação já loga).
6. **`US-NFE-055`..`058`** dual-mode dos testes — é o que destrava o ratchet-up da lane (§8.3).
7. **Reconciliar os charters** (R7/R8) — só [W].
8. **`US-NFE-059`** smoke prod end-to-end com cliente opt-in.

---

## 11. Referências

- [SPEC.md](SPEC.md) · [BRIEFING.md](BRIEFING.md) · [SUPERFICIE.md](SUPERFICIE.md) (gerado) · [ARCHITECTURE.md](ARCHITECTURE.md) · [GLOSSARY.md](GLOSSARY.md) · [PII-LGPD-FISCAL.md](PII-LGPD-FISCAL.md) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)
- RUNBOOKs: [manifestacao](RUNBOOK-manifestacao.md) · [smoke-sefaz](RUNBOOK-smoke-sefaz.md) · [smoke-sefaz-biz1](RUNBOOK-smoke-sefaz-biz1.md)
- ADRs do módulo: [arq/0001](adr/arq/0001-modulo-isolado-via-nwidart.md) · [arq/0002](adr/arq/0002-lib-sped-nfe-vs-acbr.md) · [arq/0003](adr/arq/0003-cert-a1-storage-criptografado.md) · [arq/0005](adr/arq/0005-tax-rates-core-vs-fiscal-rules.md) · [arq/0006](adr/arq/0006-cascade-defaults-ncm-produto.md) · [tech/0001](adr/tech/0001-numeracao-com-lockForUpdate.md) · [tech/0002](adr/tech/0002-contingencia-epec-fsda-retentativa-ordenada.md) · [tech/0003](adr/tech/0003-retencao-xml-5-anos-write-once.md) · [ui/0001](adr/ui/0001-fluxo-emissao-1-clique-no-pos.md) · [ui/0003](adr/ui/0003-configuracao-fiscal-3-niveis.md)
- **SDD irmão (fronteira):** [Fiscal/SDD-cockpit-fiscal-v1.0.md](../Fiscal/SDD-cockpit-fiscal-v1.0.md)
- Charters: `resources/js/Pages/NfeBrasil/{Transactions/NfceStatus,Manifestacao/Index,Tributacao/*}.charter.md`
- Casos: `resources/js/Pages/NfeBrasil/**/*.casos.md`
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) · [ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md) · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) · [ADR 0351](../../decisions/0351-sdd-from-source.md)
- Porta viva do estado: `node scripts/governance/requisitos-status.mjs NfeBrasil`
