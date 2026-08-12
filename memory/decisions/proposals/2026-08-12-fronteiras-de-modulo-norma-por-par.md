---
title: "Fronteiras de módulo — a norma por par (mesa de decisão [W])"
status: proposta
date: "2026-08-12"
owners: [W]
parent_module: null
related_adrs: [93, 121, 123, 170, 256, 258, 275, 336]
related_specs:
  - memory/sessions/2026-08-12-arte-shared-kernel-laravel.md (gap #4 do parecer)
related_charters: []
---

# Fronteiras de módulo — a norma por par

> **Por que este doc existe.** As catracas dos 3 eixos estão armadas e congelam a dívida
> ([PR #5670](https://github.com/wagnerra23/oimpresso.com/pull/5670) · [#5675](https://github.com/wagnerra23/oimpresso.com/pull/5675) · [#5702](https://github.com/wagnerra23/oimpresso.com/pull/5702)).
> O que elas **não** fazem — de propósito — é dizer se cada fronteira **deveria** existir.
> A regra escrita na própria baseline: *"declaração serve pra NORMA (o que PODE), não pra
> FATO (o que É). A norma por par é decisão [W]."* Esta é a mesa dessa decisão.
>
> **O que eu proponho e o que NÃO proponho.** Onde existe ADR ou padrão da casa que já
> responde, eu proponho e digo qual é a âncora. Onde não existe, está escrito
> **PERGUNTAR** — anti-padrão inventado num charter é pior que ausente, porque parece
> canon (§5 2026-07-16). Não inventei norma pra fechar linha.

## 0. Como ler — e o que cada decisão CUSTA

Declarar `depends_on: [X]` no `SCOPE.md` do módulo de origem é dizer **"esta seta é
legítima"**. Não muda uma linha de código: muda o que a catraca considera dívida. O par
declarado sai da lista de não-declarados e o `catalog-graph` para de reportá-lo como
fronteira em aberto.

Não declarar também é decisão: o par **fica congelado** na baseline e vira fila de
desacoplamento. Nenhuma das duas opções quebra nada hoje.

**Os números, e de onde vêm** (todos re-rodáveis, nenhum escrito à mão):

```bash
node scripts/governance/catalog-graph.mjs --acoplamento      # os dois eixos
```

| | |
|---|---|
| arestas distintas (união dos 2 eixos) | **48** |
| eixo `use`: pares vivos | 37 · **14 declarados** · **23 não** |
| eixo `DB::table`: pares vivos | **20** — dos quais **8 ESCREVEM** na tabela alheia |
| ciclos (dependência mútua A↔B) | **10** |
| não resolvido (piso, não teto) | 17 `DB::table($var)` dinâmico · 105 sem migration localizada |

⚠️ **`Connector → FieldForce` não entra em conta nenhuma**: `Modules/FieldForce` **não
existe** no repo (embora `modules_statuses.json:15` diga `true`), e o `catalog-graph`
filtra alvo não-vivo. É `use Modules\FieldForce\Entities\FieldForce` em
`Modules/Connector/Http/Controllers/Api/FieldForce/FieldForceController.php:12` —
class-not-found esperando alguém chamar a rota. **Item separado, não é norma de fronteira.**

---

## 1. As que tocam DINHEIRO — decidir primeiro

### D-1 · `Officeimpresso → Financeiro` — INSERT direto em `fin_titulos`

| | |
|---|---|
| forma | `DB::table('fin_titulos')->insert([...])` — [OfficeimpressoImporterService.php:565](../../../Modules/Officeimpresso/Services/FirebirdImporter/OfficeimpressoImporterService.php) |
| e também | `->exists()` na linha 515 (idempotência do importer) |
| Tier 0? | **não** — `business_id` vai explícito na linha 566 |
| o que se perde | observers, `LogsActivity`, casts e as invariantes do dono (ex.: a regra "`fin_titulos` não permite delete") |

O docblock da linha 27 do **próprio arquivo** já declara que mapeia para
`Modules\Financeiro\Models\Titulo` — e o código não usa o Model.

**Proposta: NÃO declarar como norma; virar tarefa de desacoplamento** — trocar o
`DB::table` pelo `Titulo::create()`. Âncora: é importação de dado de cliente legado
(WR Comercial/Delphi) entrando como título financeiro; se o importer diverge das
invariantes do Financeiro, os títulos importados são de segunda classe e ninguém percebe.

⚠️ **Isto cai na REGRA MESTRE de valor** (`proibicoes.md`): mexer aqui exige dupla
confirmação do cálculo + tabela antes→depois dos títulos afetados **antes** de aplicar.
Não é refactor de rotina.

### D-2 · `Financeiro ↔ RecurringBilling` — ciclo, nos dois eixos

| direção | forma |
|---|---|
| `RecurringBilling → Financeiro` | `use ContaBancaria` (8 arq) + `use ExtratoLancamento` (1) · lê `fin_contas_bancarias` |
| `Financeiro → RecurringBilling` | `use Subscription`, `BoletoCredential`, `AssinaturaCobrancaService` (2 arq) · **UPDATE** em `rb_subscriptions` ([ProcessAsaasPixWebhookListener.php:186](../../../Modules/Financeiro/Listeners/ProcessAsaasPixWebhookListener.php)) |

Os consumidores do lado RecurringBilling são webhooks e jobs bancários
(`AsaasWebhookController`, `ProcessInterWebhookJob`, `SyncBankStatementsJob`) — precisam
mesmo saber em **que conta** o dinheiro caiu.

**Proposta: declarar `RecurringBilling → Financeiro` (a leitura de conta bancária é
legítima) e PERGUNTAR sobre a volta.** O `UPDATE` em `rb_subscriptions` de dentro de um
listener do Financeiro é a metade que me incomoda: quem manda no ciclo de vida da
assinatura é o RecurringBilling. Se a intenção é "pagou o Pix → ativa a assinatura", o
caminho é evento (`CobrancaPaga`, que **já existe** e o Superadmin já consome), não
`DB::table('rb_subscriptions')->update`.

**A pergunta pra você:** o Financeiro pode mudar o estado de uma assinatura, ou isso é
sempre do RecurringBilling reagindo a um evento?

### D-3 · `PaymentGateway → Financeiro` — UPDATE em `fin_contas_bancarias`

| | |
|---|---|
| forma | `use ContaBancaria` (3 arq) + `use Titulo` + `use TituloBaixa` · **UPDATE** em `fin_contas_bancarias` ([MigrateCredentialsCommand.php:115](../../../Modules/PaymentGateway/Console/Commands/MigrateCredentialsCommand.php)) |
| contexto | o `UPDATE` vincula a FK `payment_gateway_credential_id`, criada por **migration do próprio PaymentGateway** ([2026_05_19_130000](../../../Modules/PaymentGateway/Database/Migrations/2026_05_19_130000_add_payment_gateway_credential_id_to_fin_contas_bancarias.php)) |

⚠️ **O eixo `use` deste par JÁ É DECLARADO** — `PaymentGateway>Financeiro` está entre os 14
com norma, não entre os 23 em aberto. **O que está em aberto aqui é só o eixo tabela**: o
`UPDATE` cru. Confundir os dois seria propor decisão que já foi tomada.

**Proposta: manter a norma do import e trocar o `UPDATE` cru pelo Model.** A seta é
legítima ([ADR 0170-extração](../0170-paymentgateway-extracao-camada-cobranca.md): o
PaymentGateway foi **extraído** de RecurringBilling e é a camada de cobrança
que o Financeiro consome; baixar título quando o gateway confirma é o propósito do
módulo); o que não é legítimo é escrever na tabela sem passar pelo `ContaBancaria`, que o
mesmo comando **já importa**.

⚠️ **Mas registro a anomalia**: um módulo que adiciona coluna por migration na tabela de
outro e depois a preenche por `DB::table` é ownership difuso. A migration é honesta
(tem guarda `Schema::hasTable`). Vale saber se você quer isso como padrão.

### D-4 · `NfeBrasil → RecurringBilling` — emitir NFe ao receber pagamento

| | |
|---|---|
| forma | `use InvoicePaid` (evento, 2 arq) + `use Invoice` (Model, 3 arq) |

A metade por evento é exatamente a seta certa. A metade por Model é o acoplamento.

**Proposta: declarar.** Âncora: é a US-RB-044 (NFe-de-boleto-pago automática) citada em
[`why-oimpresso.md`](../../why-oimpresso.md) como **diferencial de produto** do vertical
ComunicacaoVisual. Fronteira deliberada, não acidente.

### D-5 · `Financeiro → PaymentGateway` — 14 símbolos, 12 de contrato

Exceptions, DTOs e `PaymentGatewayContract` (12 de 14) + `Cobranca` e
`PaymentGatewayCredential` (os 2 de `dado`).

**Proposta: declarar.** É a forma que a casa quer: consumir **contrato**, não interno.
Os 2 símbolos de `dado` ficam anotados como o resíduo a inverter algum dia — não vale
tarefa hoje.

### D-6 · `Financeiro → Superadmin` e `Fiscal → Superadmin` — UPDATE em `packages`/`subscriptions`

| | |
|---|---|
| onde | `Financeiro/Console/Commands/InstallCommand.php:153` · `ProvisionSmokeTenantCommand.php:116` · `Fiscal/Console/Commands/HabilitarBusinessCommand.php:152` |

Os três são **comandos de instalação/habilitação** — escrevem no pacote do Superadmin pra
ligar o módulo no tenant.

**Proposta: declarar os dois.** Âncora: `proibicoes.md` §Multi-tenant Camada 1 — habilitar
módulo por business é **sempre** via pacote do Superadmin, nunca hardcode. Estes comandos
são o caminho canônico fazendo o que o canon manda.

---

## 2. Backbone declarado — 1 decisão, não 7

`Cms` · `Financeiro` · `NfeBrasil` · `OficinaAuto` · `Repair` · `Whatsapp` **→ `Arquivos`**
(6 pares de import; símbolos `HasArquivos`, `Arquivo`, `ArquivosService`,
`VaultEncryptionService`), mais `NfeBrasil → arquivos` no eixo tabela.

**Proposta: declarar os 6.** Âncora: [ADR 0123 §4](../0123-modules-arquivos-backbone.md) já
nomeia `Modules/Arquivos` como *"backbone transversal que todo módulo passa a usar"*. O
projeto **já tem** o conceito; só nunca preencheu o campo que o declara. Isto é ratificar
ADR existente, não norma nova.

**Item separado (não é norma, é conserto):** `NfeBrasil` faz **INSERT direto** em
`arquivos` ([DanfeService.php:253](../../../Modules/NfeBrasil/Services/DanfeService.php),
[NfeService.php:1700](../../../Modules/NfeBrasil/Services/NfeService.php)) **enquanto
importa o `ArquivosService`** nos mesmos arquivos. Ter o serviço e escrever na tabela
direto é o smell — o conserto é usar o serviço.

---

## 3. Assinatura do tenant — 1 decisão, 4 pares

`Connector` · `Officeimpresso` · `PaymentGateway` · `VozDoCliente` **→ `Superadmin`**
(`Subscription`, `Package`; 1 arquivo cada).

Todos perguntam a mesma coisa: *"que plano este tenant assinou?"*

**Proposta: declarar os 4.** Ler a assinatura da plataforma é pergunta que qualquer módulo
pode fazer — é o mesmo dado que o `ModuleUtil::hasThePermissionInSubscription` já expõe.

⚠️ **Não mexer no nome da classe.** `Superadmin\Subscription` usa `LogsActivity`, que grava
`subject_type` = FQCN **em linha de banco**, lido pelo `Modules/Auditoria`; sem
`enforceMorphMap` (0 ocorrências no repo), renomear **cega o histórico de auditoria**.
Registrado no parecer de 2026-08-12 e vale repetir aqui.

---

## 4. Infra fiscal — certificado digital

| par | forma |
|---|---|
| `NFSe → NfeBrasil` | `use CertificadoService`, `use NfeCertificado` |
| `Crm → NfeBrasil` | `use SefazConsultaCadastroService` (lookup de cadastro na SEFAZ) |
| `Fiscal → NfeBrasil` | lê `nfe_business_configs` (já declarado) |

**Proposta: declarar os dois não-declarados.** Certificado A1/A3 e consulta SEFAZ são infra
fiscal de instância, não domínio de um módulo só.

⚠️ **Mas há um ownership DISPUTADO que a máquina acusa e ninguém resolveu** — e este é
decisão sua, não minha:

```
ownership DISPUTADO: `nfe_certificados` criada por migration de NFSe E NfeBrasil
ownership DISPUTADO: `nfse_emissoes`    criada por migration de NFSe E NfeBrasil
```

O `catalog-graph` atribui ao NFSe por ordem alfabética e **reporta em vez de resolver em
silêncio**. Duas migrations criando a mesma tabela é dívida real. **PERGUNTAR: de quem é
o certificado?**

---

## 5. Governança / IA — o cluster Forja·Jana·Governance·KB

| par | forma |
|---|---|
| `Forja → Governance` | 8 services de linha de brief (1 arquivo, todos `servico`) |
| `Governance → Whatsapp` | `use CentrifugoPublisher` (1) |
| `Governance → Jana` | **INSERT** em `mcp_alertas` ([ScorecardSnapshotCommand.php:258](../../../Modules/Governance/Console/Commands/ScorecardSnapshotCommand.php), [InitiativeService.php:286](../../../Modules/Governance/Services/InitiativeService.php)) |
| `Jana → Whatsapp` · `Governance → Forja` · `Jana → Forja` | leitura (`mcp_actors`, `channels`, `failed_jobs`) |

**Proposta: declarar `Forja → Governance` e `Governance → Whatsapp`.** O primeiro é
composição de brief (a Forja monta, cada módulo fornece a linha); o segundo é publicar em
canal realtime, que é infra.

**PERGUNTAR sobre o INSERT em `mcp_alertas`**: alerta é da Jana ou é infra de governança
que todo mundo emite? Se for infra, o certo é um serviço emissor, não `DB::table`.

---

## 6. Os 4 pares que sobram — sem âncora, PERGUNTAR

| par | o que atravessa | por que eu não proponho |
|---|---|---|
| `Connector → Crm` | `use CrmUtil` | Connector é API pública; usar util interno do CRM pode ser certo (fachada) ou vazamento. Sem ADR que decida |
| `Connector → Officeimpresso` | `use Licenca_Computador` + lê `licenca_computador` | licenciamento de instalação legada — não sei se é para durar |
| `Crm → OficinaAuto` | `use Vehicle` ([ClienteVeiculosController](../../../Modules/Crm/Http/Controllers/ClienteVeiculosController.php)) | "veículos do cliente" na ficha do CRM é feature real do Martinho. Mas é o CRM (núcleo) dependendo de um **vertical** — inverte a direção do [ADR 0121](../0121-oimpresso-modular-especializado-por-vertical.md) |
| `OficinaAuto → Whatsapp` | `use SendWhatsappMessageJob`, `WhatsappBusinessPhone` | avisar cliente por WhatsApp é legítimo; despachar o **Job** de outro módulo é acoplamento a implementação |
| `OficinaAuto → Ponto` | lê `ponto_marcacoes` | ⚠️ tabela **append-only por força de lei** (Portaria MTP 671/2021). Leitura não viola nada, mas quero seu aval antes de carimbar como norma |

O `Crm → OficinaAuto` é o que mais merece sua atenção: se o núcleo pode depender de
vertical, a tese modular do 0121 fica mais frouxa do que está escrita.

---

## 7. Calibração da máquina (não é norma de domínio)

**`Forja → Jana` tem 5 `UPDATE` em `mcp_tokens` — todos dentro de UMA migration**
([2026_05_05_240002_seed_initial_actors.php](../../../Modules/Forja/Database/Migrations/2026_05_05_240002_seed_initial_actors.php)).

Usar `DB::table` em migration é **prática canônica do Laravel** (o Model pode não existir
ou ter mudado na data da migration). Então:

**PERGUNTAR: o eixo tabela deve ignorar `Database/Migrations/`?**

- **A favor de ignorar:** migration com `DB::table` é o padrão certo; contá-la como
  fronteira produz ruído em cima de código que não pode ser escrito de outro jeito.
- **Contra:** uma migration de um módulo **escrevendo na tabela de outro** é uma pergunta
  de ownership legítima, e ignorar apagaria o sinal.

Minha inclinação é **não ignorar, mas marcar** — a fronteira existe; o que muda é o
remédio. Sem sua decisão eu não mexo: hoje conta, e a baseline já congelou com ela dentro.

---

## 8. Os 10 ciclos

Derivados da união dos dois eixos. Nenhum é erro por si — mas ciclo é o que impede um
módulo de ser extraído, e vale saber quais existem antes de prometer que algum sai.

```
Financeiro <-> PaymentGateway      Financeiro -> PG: use  |  PG -> Financeiro: use + tabela/ESCREVE
Financeiro <-> RecurringBilling    F -> RB: use + tabela/ESCREVE  |  RB -> F: use + tabela/lê
Forja      <-> Jana                Forja -> Jana: use + tabela/ESCREVE  |  Jana -> Forja: tabela/lê
Governance <-> Jana                Gov -> Jana: use + tabela/ESCREVE  |  Jana -> Gov: use
Forja      <-> Governance          Forja -> Gov: use  |  Gov -> Forja: use + tabela/lê
Forja      <-> KB                  use nos dois sentidos
Jana       <-> Whatsapp            Jana -> Wa: tabela/lê  |  Wa -> Jana: use
PaymentGateway <-> Superadmin      use nos dois sentidos (eventos de um lado)
Arquivos   <-> Cms                 Arquivos -> Cms: tabela/lê  |  Cms -> Arquivos: use
Arquivos   <-> Financeiro          Arquivos -> Financeiro: tabela/lê  |  Financeiro -> Arquivos: use
```

⚠️ Os dois ciclos com **`Arquivos`** merecem nota: o backbone transversal **lê tabela dos
módulos que o consomem** (`cms_pages`, `fin_boleto_remessas`). Um backbone que conhece
seus consumidores deixa de ser leaf — é a mesma doença que fez o `HasArquivos` **não** ser
promovido ao núcleo no parecer de 2026-08-12.

---

## 9. Resumo da mesa

Contagem sobre os **23 pares de import em aberto** (as decisões de tabela viajam junto,
mas não entram nesta conta — a baseline delas é outra):

| # | decisão | pares de import | minha proposta |
|---|---|---|---|
| §2 | backbone `Arquivos` | 6 | **declarar** (ratifica ADR 0123) |
| §3 | assinatura do tenant | 4 | **declarar** |
| §1 | dinheiro | 4 | **declarar** (`RecurringBilling→Financeiro`, `Financeiro→PaymentGateway`, `NfeBrasil→RecurringBilling`, `Superadmin→PaymentGateway`) |
| §4 | infra fiscal | 2 | **declarar** + resolver ownership disputado |
| §5 | governança/IA | 2 | **declarar** |
| §1 | `Financeiro→RecurringBilling` (a volta do ciclo) | 1 | **PERGUNTAR** |
| §6 | sem âncora | 4 | **PERGUNTAR** |
| | **total** | **23** | **18 declarar · 5 perguntar** |

Fora dessa conta, no eixo tabela: `Officeimpresso→fin_titulos` (**desacoplar**, §1 D-1),
`NfeBrasil→arquivos` e `PaymentGateway→fin_contas_bancarias` (**trocar `DB::table` pelo
Model/serviço que o próprio arquivo já importa**), `mcp_alertas` e a calibração de
migrations (**PERGUNTAR**, §5 e §7).

Se você aprovar as 18, elas caem numa passada e sobram 5 pra conversa.

**Nada disto muda código.** O PR que implementa esta mesa mexe só em `SCOPE.md` —
`depends_on` — e nas baselines, que encolhem na direção permitida pela catraca.
