---
title: "Fronteiras de módulo — a norma por par (mesa de decisão [W])"
status: proposta
date: "2026-08-12"
owners: [W]
parent_module: null
related_adrs: [93, 105, 121, 123, 137, 179, 186, 201, 214, 256, 258, 275, 280, 301, 336, 358]
related_specs:
  - memory/sessions/2026-08-12-arte-shared-kernel-laravel.md (gap #4 do parecer)
  - memory/reference/contrato-delphi-inviolavel.md (Tier 0 — sustenta o par Connector→Officeimpresso)
related_charters: []
---

# Fronteiras de módulo — a norma por par

> ## ⚠️ ERRATA — a v1 deste doc propunha o instrumento ERRADO
>
> A primeira versão (commit `02b2b0a86a4`) recomendava declarar `depends_on: [X]` no
> `SCOPE.md` para 18 pares. **Sete auditorias adversariais independentes derrubaram isso**,
> duas delas convergindo no mesmo ponto por caminhos diferentes. A refutação foi então
> **verificada por experimento**, não por leitura:
>
> ```
> $ # depends_on: [Arquivos] adicionado a memory/requisitos/Cms/SCOPE.md
> $ node scripts/governance/catalog-graph.mjs --acoplamento --catraca
> ℹ️  catraca import (`use`): 1 par(es) da baseline JÁ FORAM CURADOS — remova do JSON: Cms>Arquivos
> ```
>
> **Nada foi curado.** O import continua idêntico. Declarar faz um acoplamento vivo
> **parecer dívida resolvida** e instrui o próximo a apagar a linha da baseline. Aplicar a
> v1 teria convertido 18 acoplamentos reais em "curados", removido-os da dívida congelada,
> e deixado a razão de cada um só num `.md` que máquina nenhuma lê. É o vetor
> **"apagar o item"** ([§5 2026-08-10](../../proibicoes.md)), em escala, dentro do PR que
> existia pra endurecer a fronteira.
>
> A v1 fica registrada aqui, não apagada — errata apagada não ensina.

---

## §0 · O instrumento certo (a correção-mãe)

A própria catraca **já imprime** as opções quando reprova
([`catalog-graph.mjs:1315-1319`](../../../scripts/governance/catalog-graph.mjs)) — e
`depends_on` **não está entre elas**:

> `(a)` declarar a delegação no `not_contains` do SCOPE.md do módulo de ORIGEM
> `(b)` inverter via contrato/evento
> `(c)` se for dívida consciente, entrar em `<baseline> > allowlist` **COM razão declarada**

**A distinção que decide qual usar** — e ela é semântica, não de gosto:

| slot | o que AFIRMA | efeito na catraca | adoção medida |
|---|---|---|---|
| `not_contains` | *"esta responsabilidade **não é minha**, é do módulo X"* — delegação | par **sai** da dívida rastreada | **30 de 32** SCOPE.md |
| `depends_on` | *"eu dependo de X"* | par **sai** da dívida rastreada | **1 de 32** (só `Financeiro`, e declara `Sells`/`Compras`, que não são seus imports reais) |
| `allowlist` + `allowlist_razoes` | *"este acoplamento é **deliberado**, e a razão é esta"* | par **fica visível**, isento de morder | **0 de 0**, nos dois eixos |

⚠️ **O sumiço não é exclusivo do `depends_on`.** `compararFronteira` conta `dependsOn`
**e** `delegatesTo` como declarado — então declarar por `not_contains` **também** faz o par
desaparecer da dívida. A diferença é o que a frase afirma: `not_contains` diz *"não faço
isso, quem faz é X"*; usá-lo para dizer *"importo X e tudo bem"* seria mentir no slot certo.

**Logo, para "este acoplamento existe, é legítimo, e quero a razão registrada", o slot é
`allowlist_razoes`** — o único que preserva a visibilidade. Ele está vazio nos dois eixos:
nenhum par jamais foi declarado legítimo-por-norma neste projeto. Esta mesa é a primeira vez.

---

## §1 · Os quatro desfechos possíveis

1. **REGISTRAR** em `allowlist_razoes` — a seta é legítima e a razão vai escrita ao lado.
2. **DESACOPLAR** — existe caminho canônico melhor (contrato, evento, Model do dono, fachada).
3. **PERGUNTAR** — falta decisão de produto ou dado empírico que eu não tenho.
4. **ARQUIVAR** — não é fronteira de negócio; é artefato do detector.

Nenhum deles é `depends_on`.

---

## §2 · REGISTRAR — a razão já existe em canon (10 pares)

Aqui não há decisão nova: cada linha **transcreve** norma já escrita e datada para o slot
que a máquina lê.

| par | razão a escrever no `allowlist_razoes` | âncora |
|---|---|---|
| `Connector → Officeimpresso` (import **e** tabela) | Handshake de licenciamento/registro dos ~6 builds Delphi vivos em prod; contrato **não pode mudar** porque os Delphi não serão recompilados | [`contrato-delphi-inviolavel.md`](../../reference/contrato-delphi-inviolavel.md) `trust_required: tier-0` · ADR 0170-onda5 §70 · `Connector/SCOPE.md:3` |
| `Cms → Arquivos` · `Financeiro → Arquivos` · `OficinaAuto → Arquivos` | Backbone DMS transversal, adoção **opt-in** por módulo | ADR 0123 (aceita pela **0214**) — a cláusula que autoriza é o Não-goal *"migram opt-in, não compulsório"*, **não** *"todo módulo passa a usar"* |
| `Repair → Arquivos` · `Whatsapp → Arquivos` | Importam **só** `HasArquivos` — o próprio detector os classifica como *primitiva cross-cutting: alojamento, não fronteira* | `catalog-graph --acoplamento` (*"1 decisão, não 2"*) |
| `Officeimpresso → Superadmin` | Leitura pura de plano (`active_subscription()` + `Package::find()` → view) | — |
| `Superadmin → PaymentGateway` | **Só eventos** (`CobrancaPaga`/`CobrancaVencida`) via `Event::listen`; todo `save()` em entidade própria. A mais limpa do lote | `SuperadminServiceProvider.php:128-129` |
| `Financeiro → PaymentGateway` | Consome **contrato** (12 dos 14 símbolos são Exception/DTO/Contract) | ADR 0170-extração |
| `NfeBrasil → RecurringBilling` | Emitir NFe ao receber pagamento — diferencial de produto | `RecurringBilling/SPEC.md:588` **US-RB-044 `status: done`** com `**Implementado em:**` |
| `NFSe → NfeBrasil` | NfeBrasil é **dono do cofre de certificado**; NFSe **herda** (`NfseCertificado extends NfeCertificado`) e delega ao `CertificadoService` | `NfeBrasil/SCOPE.md:22` (`db_tables_owned`) · ADR 0186 |
| `Crm → NfeBrasil` | Toda tela de cadastro fiscal BR **deve** oferecer Buscar CNPJ | **ADR 0186** (IRREVOGÁVEL) · **ADR 0201** |
| `RecurringBilling → Financeiro` | Webhooks e jobs bancários operam sobre a conta do Financeiro | ⚠️ ver ressalva |

**Ressalvas que precisam ir escritas junto, não escondidas:**

- **`RecurringBilling → Financeiro` não é leitura.** Três dos consumidores **escrevem**:
  `increment('saldo_cached')`, `update` em massa e `ExtratoLancamento::updateOrCreate`
  (cria linha na tabela do Financeiro). A v1 dizia *"só precisam saber em que conta o
  dinheiro caiu"* — descrevia leitura enquanto o código escreve dinheiro. **O problema Tier 0
  desse par virou item próprio** (chip aberto 2026-08-12): o sync bancário agendado itera
  contas de **todos os tenants** sem filtro.
- **`Financeiro → PaymentGateway`:** as 2 leituras usam `withoutGlobalScopes()` — com o
  comentário `// SUPERADMIN:` presente, como o canon exige.
- **`NfeBrasil → RecurringBilling`:** a capacidade está atrás de **2 flags default `false`**.
  A seta é legítima; **está apagada em prod**. E `RecurringBilling/CAPTERRA-INVENTARIO-v2.md:84`
  afirma *"nenhum listener em Modules/NfeBrasil"* — **stale**, corrigir junto.

---

## §3 · DESACOPLAR — existe caminho canônico melhor (6 itens)

| item | por que não registrar | o caminho certo |
|---|---|---|
| `Connector → Superadmin` | Faz `Package::create()` + `Subscription::create()`, e o bloco é **cópia** do `BaseController` do próprio Superadmin | usar o `BaseController` do dono, não duplicar o ciclo de vida de assinatura |
| `Crm → OficinaAuto` | **Defeito ativo** — ver §5 | aplicar o gate canon do núcleo + corrigir a rota que mente |
| `OficinaAuto → Whatsapp` | Reimplementou `withoutGlobalScope` à mão num job multi-tenant, existindo fachada | `WhatsappBusinessPhone::resolveForEvent()` + evento próprio. **Prova por controle negativo:** `Repair` notifica por WhatsApp com **aresta zero** |
| `Governance → Whatsapp` | O `CentrifugoPublisher` tem docblock que **confessa**: *"dívida arquitetural — mora em Whatsapp; refator futuro"*. Registrar viraria confissão em norma | mover o publisher pro núcleo (ADR 0058:102 já planejou `app/Broadcasting/`, que **não existe**). ⚠️ `app/Console/Commands/SecretsAuditCommand.php:293` também o consome — e `app/` não tem SCOPE, então essa seta é **indeclarável por construção** |
| `Financeiro → RecurringBilling` | **Código morto**: a coluna `last_payment_at` não existe em `rb_subscriptions`, o evento não existe, `shouldDiscoverEvents()` é `false`. Nunca rodou | deletar ou consertar o listener órfão |
| `Officeimpresso → fin_titulos` (tabela) | INSERT cru grava título `quitado` com `valor_aberto` cheio | **chip aberto** — cai na REGRA MESTRE de valor |

⚠️ **`NfeBrasil → arquivos` (tabela) NÃO entra aqui.** A v1 chamou de smell alegando que ele
*"importa `ArquivosService` nos mesmos arquivos onde insere"* — **falso, medido**: `DanfeService`
tem zero ocorrências de `ArquivosService`; `NfeService` tem zero imports de `Modules\Arquivos`.
Os INSERTs são **double-write transicional** (US-ARQ-021), guardados por `Schema::hasTable`,
idempotentes, em `try/catch` cujo docblock diz *"NUNCA propaga — fluxo fiscal NÃO pode quebrar
por falha em arquivos table"*. Usar `ArquivosService::attach()` exigiria um `UploadedFile` e
acoplaria a emissão fiscal à saúde do backbone. **Vai pra §4 (PERGUNTAR).**

---

## §4 · PERGUNTAR — decisão sua, com a pergunta fechada

| # | pergunta | por que eu não decido |
|---|---|---|
| P1 | O endpoint `/connector/api/crm/follow-ups` ainda é chamado por cliente externo nos últimos 90d — **manter `Connector→Crm` vivo, ou executar o E4 e devolver 410 Gone**? | É empírico e o instrumento existe (middleware `log.delphi` grava todo hit). O CRM está em depreciação (ADR 0301) com **BLOQUEIO E4** aberto no plano |
| P2 | `PaymentGateway → Superadmin` é **faturar o produto** (query cross-tenant por `status='waiting'` + `trial_end_date`), não consultar plano. Registrar carimba um **ciclo** com a volta já declarada. Registrar ou inverter? | é decisão de desenho de cobrança |
| P3 | `Forja → Governance`: são **10 injetores de 3 módulos** (8 Governance + 1 Jana + 1 Forja), sem contrato — `BriefLineContract` **não existe**. Registrar congela acoplamento a 10 classes concretas. Extrair contrato + tag no container, ou registrar como está? | o conserto barato some do radar no dia em que a seta vira norma |
| P4 | `VozDoCliente → Superadmin` **escreve** `package_details` — é o mesmo ato dos install commands do Financeiro/Fiscal. É habilitação de módulo (Camada 1) fazendo o caminho certo, ou deveria passar por um serviço único? | 3 módulos fazem o mesmo ato de 2 formas |
| P5 | `ClienteVeiculosController` **fica em `Modules/Crm`** (e a fronteira é `Crm→OficinaAuto`) **ou migra pro `app/`** no DEPRECATION-PLAN (e a fronteira vira `núcleo→OficinaAuto`, junto com **13 refs de `app/` que já existem e ninguém declarou**)? | ADR 0301 diz que o dono real é o `ContactController` do core |
| P6 | Os INSERTs em `mcp_alertas` gravam `kind`/`canal` **fora do enum** — com `strict => false`, o MySQL trunca para `''` em silêncio. Consertar os valores, ampliar o enum, ou migrar pro Model `McpAlerta` (que existe, com `HasBusinessScope`, e tem **zero consumidores de produção**)? | migrar aplicaria o global scope pela 1ª vez = mudança de comportamento |
| P7 | O `NfeBrasil` mantém um **segundo emissor NFSe inteiro** escrevendo em `nfse_emissoes` com colunas que não existem no baseline. Dívida a remover, ou caminho vivo? | decisão de produto |
| P8 | `NfeBrasil → arquivos`: manter o double-write transicional (e registrar a razão) ou fechar a transição da US-ARQ-021? | é fim de transição, não fronteira |

---

## §5 · Defeito ativo achado no caminho (não é fronteira)

**`Modules/Crm/Routes/web.php:159-161` afirma em tempo presente** que a rota
*"retorna [] se Vehicle model inexistente em ambiente sem modulo"*. **Não retorna** —
`ClienteVeiculosController:90` chama `Vehicle::where(...)` direto, e o arquivo inteiro tem
**zero** `class_exists`, `isModuleInstalled` ou `Module::has`.

O núcleo escreveu o gate correto e o Crm copiou o *helper* sem o *guarda*:

- `ContactController.php:2174-2179` — gate duplo (`oficinaauto_enabled` + `Inertia::defer` condicional)
- `SellController.php:950-961` — variante com `hasThePermissionInSubscription` **e** `class_exists`

**Só não quebra hoje por acidente de infra:** PSR-4 na raiz torna a classe autoloadable com o
módulo desligado, e `vehicles` viaja no `mysql-schema.sql`. Segurança por acidente, não por
desenho. É **LC-10 em código de produção** — o artefato afirma o enforcement que não tem.

Bônus stale no mesmo arquivo: o `@see` aponta `_drawer/oss/PlacasSubTab.tsx`, que **não existe**
(o consumidor real é `_drawer/PlacasMainTab.tsx:81`).

---

## §6 · ARQUIVAR — o detector capturou o que não é fronteira

**`OficinaAuto → Ponto`** — único site: `OficinaAutoSanityCheckCommand.php:154-181`. É
`COUNT(*)` agregado, escopado por `business_id`, guardado por `Schema::hasTable`, **sem
`select` de coluna alguma** — o módulo **assertando a invariante da Portaria MTP 671/2021**
(*"append-only `ponto_marcacoes` — assert sem rows DELETE"*). Sem PII, sem risco de finalidade.

Registrar inventaria norma de negócio onde há sonda; desacoplar **removeria uma verificação
de conformidade legal**. O slot, se incomodar no report, é `allowlist_razoes` com a razão
*"sonda de conformidade, não dependência"*.

**`Governance → Whatsapp` no eixo TABELA** — é **100% falso-positivo**: as 2 queries são
`failed_jobs`, tabela de infra do Laravel criada por uma migration do Whatsapp. A mitigação
"≥3 módulos = infra compartilhada" não dispara porque só Jana e Governance a leem: **um
módulo abaixo do limiar**. O par só sobrevive porque também existe no eixo `use`.

---

## §7 · Calibração da máquina — trabalho meu, não decisão sua

Registrado aqui para não se perder; sai em PR próprio.

1. **Terceiro sub-eixo invisível: `Schema::table()`** (ALTER em tabela alheia). O dono só sai
   de `Schema::create` e a query só de `DB::table` — adicionar coluna na casa do outro não é
   visto por nenhum dos dois. Medido: **3 pares cross-module por ALTER**, e
   **`NfeBrasil → NFSe` não está em eixo nenhum nem em baseline nenhuma**. Mais **61 ALTERs
   em tabela core** sem radar. Adicionar coluna é sinal de ownership **mais forte** que
   escrever linha — a catraca vê o fraco e não vê o forte.
2. **Migrations contam?** Medido: só **2 de 20** pares são só-migration (10%), **7 de 60**
   queries (11,7%) — a condição de auto-refutação não disparou. Forma proposta: imprimir
   **`mig=N runtime=M`** na linha do par (uma regex de path na função que já filtra `/Tests/`).
   Não ignorar: migration é append-only por construção, então escrita cross-module que entra
   por lá **nunca sai**.
3. **O FP do `failed_jobs`** (§6) — o limiar de infra erra por 1 módulo.
4. **`Modules/FieldForce` não existe** e o `Connector` importa dele. **Correção da v1:** o
   runtime é **403 fail-secure**, não class-not-found — os 3 métodos guardam com
   `isModuleInstalled` **antes** de tocar a classe. `modules_statuses.json` governa
   enabled/disabled, **não existência**. E o módulo **nunca existiu neste repo** (história
   completa, `git log --all` vazio): é herança do fork UltimatePOS. Remover controller + rotas
   ⚠️ **junto com** `AuthApiTest.php:75-89`, que asserta 401/422 naquela rota e viraria 404.

---

## §8 · Resumo

| desfecho | pares | precisa de você? |
|---|---|---|
| **REGISTRAR** em `allowlist_razoes` | 10 | ratificar as razões (todas transcrevem canon existente) |
| **DESACOPLAR** | 6 | 2 já viraram chip; 4 são trabalho de código |
| **PERGUNTAR** | 8 perguntas | **sim — é aqui que a mesa precisa de você** |
| **ARQUIVAR** | 2 | ratificar |
| calibração da máquina | 4 itens | não |

**Método desta versão.** Sete auditorias adversariais independentes, cada uma com mandato de
**refutar** e instrução explícita de que "REFUTA" é sucesso. **Sete de sete voltaram com
correção material.** Os achados que eu mesmo re-verifiquei antes de escrever: o experimento do
`depends_on` (§0), a adoção 30/32 do `not_contains`, o job sem filtro de tenant, o
`valor_aberto` do importer, e o par `Governance → Jana` (onde **um dos auditores errou** — ele
é par de import, 6 arquivos, declarado via `not_contains`). O resto é relato dos pareceres,
com arquivo:linha, não re-medido linha a linha por mim.
