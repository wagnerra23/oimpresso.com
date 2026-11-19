---
id: requisitos-officeimpresso-sdd-licenciamento-delphi-v1-0
slug: officeimpresso-sdd
title: "SDD — Licenciamento do desktop Delphi, ponta a ponta (Officeimpresso + Connector)"
type: sdd
module: Officeimpresso
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-11-18"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SCOPE.md
  - SUPERFICIE.md
  - RUNBOOK-licencas.md
  - RUNBOOK-empresa.md
  - RUNBOOK-empresas.md
  - RUNBOOK-logs.md
  - RUNBOOK-clientes.md
  - OFFICEIMPRESSO-FIREBIRD-SCHEMA.md
related_adrs:
  - 0017-officeimpresso-restaurado-superadmin-exclusivo
  - 0018-officeimpresso-log-acesso-passivo
  - 0019-officeimpresso-delphi-nao-autentica
  - 0021-officeimpresso-contrato-api-delphi
  - 0093-multi-tenant-isolation-tier-0
  - 0113-integracao-delphi-laravel-ads-3-caminhos
  - 0203-legacy-migration-pipeline-firebird-oimpresso-w29
related_us:
  - US-OI-008
  - US-OI-011
  - US-OI-014
---

# SDD — Licenciamento do desktop Delphi, ponta a ponta

> **Unidade:** o fluxo, não uma tela. Ele começa no executável Delphi (WR Comercial) instalado no cliente, passa pelo Passport, pela API do `Modules/Connector`, grava em tabelas do `Modules/Officeimpresso`, é administrado pelas telas do Officeimpresso e recebe um bloqueio automático do `Modules/Superadmin` quando a cobrança vence. A importação Firebird é o ramo de saída: tira os dados do banco do cliente e traz para o oimpresso.
>
> **Regra deste documento:** descreve o que o código faz em `origin/main` `ec57208855` (2026-11-18), lido do fonte, **inclusive os buracos**. Nada aqui foi medido em runtime ou em produção, salvo onde dito com data. Onde o comportamento depende de decisão do dono, a seção diz isso e para (§9).
>
> Toda referência `arquivo:linha` é de `ec57208855`. Linha anda; o símbolo citado ao lado é o que re-localiza.
>
> **Lotes em voo:** os lotes L0–L3 da auditoria de 2026-11-05 (redação de segredos, comandos quebrados, importador seguro, integrações) estavam abertos e **não mergeados** quando este SDD foi escrito. Eles mudam partes do §5 (o listener do Passport, o `POST /audit`, o importador PHP). Quando mergearem, a linha correspondente do §5 ganha uma entrada no Changelog, não reescrita silenciosa.

## 0. Base empírica 🖐 curado (foto de 2026-11-18)

- **Fonte 1 (código):** os arquivos citados no §5, lidos em `ec57208855`.
- **Fonte 2 (Blade legada):** o fluxo não tem tela Blade própria; a administração era Blade e foi para React (Licenças cutover 2026-10-06 no #8783, Empresas cutover 2026-10-27 no #10035, Logs com flag ligada para todos em 2026-10-14 no #9412).
- **Fonte 3 (Delphi):** o contrato está descrito nas ADRs [0021](../../decisions/0021-officeimpresso-contrato-api-delphi.md) (três gerações de endpoint, lidas do `.pas` em 2026-04-24) e no docblock dos controllers do Connector, que citam as units `Services.RegistroSistema.pas`, `Services.LicencaThread.pas` e `Services.OImpresso.Registro.pas`. A regra de não quebrar o wire está em [`memory/reference/contrato-delphi-inviolavel.md`](../../reference/contrato-delphi-inviolavel.md). Não há `ANTI-REGRESSAO-*` deste fluxo.
- **Recibo datado de produção, não remedido:** o docblock de `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php` (`saveEquipamentoRota`) registra que, medido no `licenca_log` entre 2026-04-23 e 2026-09-30, o desktop autentica com **um** usuário central da operadora para dezenas de negócios. É por isso que o `business_id` do usuário do token **não** identifica o cliente neste fluxo.
- **Auditoria de origem:** eixos 3 e 4 da auditoria do Officeimpresso de 2026-11-05 (lote L5).

## 1. Visão geral ⚙️ derivado

O Officeimpresso é a casa do licenciamento do desktop legado. Cada máquina em que o WR Comercial roda vira uma linha de `licenca_computador`; cada contato do desktop com o servidor vira uma linha de `licenca_log`. O operador da plataforma libera ou bloqueia máquina e empresa pelas telas; o desktop pergunta "posso rodar?" a cada abertura e recebe `S` ou `N`.

O ciclo tem seis etapas:

1. autenticação do desktop no Passport
2. registro da empresa e da máquina (nasce bloqueada)
3. liberação pelo operador
4. verificação a cada abertura (`S`/`N`) e verificação de versão
5. bloqueio: manual (máquina ou empresa) ou automático (cobrança vencida)
6. migração dos dados Firebird do cliente para o oimpresso (ramo de saída, à parte do licenciamento)

| Tela | Quem usa | Papel no fluxo |
|---|---|---|
| `Officeimpresso/Licencas/Index` (`/officeimpresso/licenca_computador`) | operador | lista as máquinas; bloqueia e libera com motivo |
| `Officeimpresso/Empresas/Index` (`/officeimpresso/businessall`) | operador | lista as empresas licenciadas |
| `Officeimpresso/Empresa/Show` (`/officeimpresso/licenca_computado/licencas/{id}`) | operador | ficha da empresa: versão obrigatória/disponível, caminho do banco, bloqueio da empresa, máquinas |
| `Officeimpresso/Logs/Index` e `Logs/Timeline` (`/officeimpresso/licenca_log`) | operador | o que cada máquina fez e quando |
| `Officeimpresso/Clientes/Index` (`/officeimpresso/client`) | operador | os clients OAuth que o desktop usa para pegar token |

## 2. Público-alvo e personas 🖐 curado — [W] valida

- **Operador da plataforma:** usuário da empresa operadora com `superadmin` ou com as permissões delegáveis (`officeimpresso.access`, `officeimpresso.licencas.gerenciar`, `officeimpresso.empresa.gerenciar`, `officeimpresso.licencas.excluir`), conferidas por `Modules/Officeimpresso/Services/AcessoOperador.php::pode`. A delegação ao suporte existe desde 2026-07-30; ela convive com a [ADR 0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md), que diz "superadmin exclusivo" — ver **B3** no §9.
- **Cliente com o desktop instalado:** não usa tela nenhuma deste fluxo. Ele vê só o efeito: o WR Comercial abre ou mostra a mensagem do `N;<motivo>`.
- Exemplos e testes deste fluxo usam tenants fictícios (98 e 99, [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)), nunca um cliente real.

## 3. Governança aplicável ⚙️ derivado

- **Tier 0 multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):** o fluxo é cross-tenant por desenho. A operadora administra máquinas de todos os clientes, e o desktop autentica com usuário central. Por isso a identidade do cliente vem do **corpo** da chamada (CNPJ, HD), não do token. As travas que existem no código: `AcessoOperador` (só a empresa operadora exerce as permissões delegáveis) e `saveEquipamentoRota` (só o dono do negócio ou um usuário listado em `config('connector.delphi_master_user_ids')` grava máquina pela rota `salvar-equipamento/{business_id}`).
- **Contrato com o desktop:** o wire (`S;msg`/`N;motivo`, JSON com `autorizado: "S"|"N"`, ordem de chaves do eco) não muda sem recompilar o Delphi, e a [ADR 0113](../../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) registra que [W] vetou recompilar como dependência. Detalhe em `memory/reference/contrato-delphi-inviolavel.md`.
- **LGPD:** o corpo que o desktop manda traz razão social, CNPJ, e-mail e telefone. O log passa pelo `PiiRedactor` antes de gravar (`LogDelphiAccess.php`, bloco do `body_preview`), e o motivo digitado pelo operador também (`LicencaComputadorController::bloquearComMotivo`).
- **Retenção do log:** `Config/retention.php` e `module.json` falam de expurgo do `licenca_log`. Não existe comando que o execute, e a proposta esbarra na lápide §5 de 2026-07-27 de `memory/proibicoes.md`. Ver **B4** no §9.

## 4. Design system aplicável ⚙️ derivado

Fora do escopo (é um fluxo, não uma tela). Cada tela tem charter e RUNBOOK próprios, listados no §11.

## 5. Arquitetura ⚙️ derivado

### 5.1 Visão em camadas

| Etapa | Entrada | Código |
|---|---|---|
| Token | `POST /oauth/token`, `grant_type=password` | Passport. O password grant foi ligado em `app/Providers/AuthServiceProvider.php` ([ADR 0019](../../decisions/0019-officeimpresso-delphi-nao-autentica.md)). Os clients são administrados em `Modules/Officeimpresso/Http/Controllers/ClientController.php` |
| Log do login | evento `AccessTokenCreated` | `Modules/Officeimpresso/Listeners/LogPassportAccessToken.php`, registrado em `OfficeimpressoServiceProvider::register` |
| Registro, geração 1 | `POST /connector/api/processa-dados-cliente` | `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php::ProcessaDadosCliente` |
| Registro, geração 1 (rotas soltas) | `POST /connector/api/salvar-cliente` · `POST /connector/api/salvar-equipamento/{business_id}` | `Connector/.../BusinessController::saveBusiness` · `LicencaComputadorController::saveEquipamentoRota` |
| Registro, geração 3 | `POST /connector/api/oimpresso/registrar` | `Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php::registrar` |
| Verificação de versão | `POST /connector/api/check-update` | `Modules/Connector/Http/Controllers/Api/CheckUpdateController.php` |
| Log de toda chamada `/connector/api/**` | middleware `log.delphi` | `Modules/Officeimpresso/Http/Middleware/LogDelphiAccess.php`; o alias nasce em `OfficeimpressoServiceProvider::boot` |
| API própria do desktop | `GET /api/officeimpresso` (ping) · `POST /api/officeimpresso/audit` | `Modules/Officeimpresso/Routes/api.php` → `AuditController::store` → `Services/LicencaAuditService.php`; middleware `log.desktop` (`Http/Middleware/LogDesktopAccess.php`) |
| Bloqueio manual | `POST /officeimpresso/licenca_computador/{id}/toggle-block` · `POST /officeimpresso/licenca_computador/businessbloqueado/{id}` | `Modules/Officeimpresso/Http/Controllers/LicencaComputadorController.php::toggleBlock` / `businessbloqueado` → `Services/LicencaService.php::alternarBloqueio` / `alternarBloqueioEmpresa` |
| Bloqueio por cobrança | eventos `CobrancaVencida` / `CobrancaPaga` | `Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php` / `OnCobrancaPagaUpdateSubscription.php`, registrados em `SuperadminServiceProvider::registerPaymentGatewayListeners` |
| Importação Firebird (PHP) | `php artisan officeimpresso:import {biz}` | `Modules/Officeimpresso/Console/Commands/ImportOfficeimpressoCommand.php` → `Services/FirebirdImporter/OfficeimpressoImporterService.php` |
| Importação Firebird (Python) | `scripts/legacy-migration/migrar-tudo.py` | os `import-*.py` da mesma pasta ([ADR 0203](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)) |

As rotas `/connector/api/**` do bloco Delphi passam por `['throttle:120,1', 'log.delphi', 'auth:api', 'timezone']` (`Modules/Connector/Routes/api.php:10`). O `log.delphi` vem **antes** do `auth:api`, então uma chamada recusada por token inválido também é logada (teste `log.delphi aplicado ANTES de auth:api` em `tests/Feature/Connector/DelphiOImpressoContractTest.php`).

### 5.2 Modelo de dados

- **`licenca_computador`** (migration `Modules/Officeimpresso/Database/Migrations/2024_11_05_101935_create_licenca_computador_table.php`; entidade `Entities/Licenca_Computador.php`): uma linha por máquina e empresa. Identidade: `business_id + hd + user_win`. Campos que decidem o fluxo: `bloqueado` (o que o desktop recebe), `motivo` (texto do `N;`), `dt_validade` (vira `data_expiracao` e `dias_restantes` no registro de geração 3), `dt_ultimo_acesso`. O resto é inventário da máquina (hostname, IP, processador, versão do executável e do banco, caminho do banco).
- **`licenca_log`** (migration `2026_04_23_200000_create_licenca_log_table.php` + `2026_04_24_100500_add_business_location_id_to_licenca_log.php`; entidade `Entities/LicencaLog.php`): append-only por convenção de uso. Cada linha tem `event`, `source`, `business_id`, `business_location_id`, `licenca_id`, `user_id`, `client_id`, `token_hint`, IP, endpoint, status HTTP, duração e `metadata` JSON. Os escritores, por `source`:

  | `source` | `event` | Quem grava |
  |---|---|---|
  | `passport_event` | `login_success` | `LogPassportAccessToken` |
  | `delphi_middleware` | `api_call` | `LogDelphiAccess` |
  | `api_middleware` | `api_call` | `LogDesktopAccess` (API própria `/api/officeimpresso/*`) |
  | `desktop_audit` | o `event` que o desktop mandar, ou `desktop_audit` | `LicencaAuditService` |
  | `admin_action` | `maquina_bloqueada` / `maquina_liberada` | `LicencaComputadorController::bloquearComMotivo` |

  Os triggers MySQL da primeira versão do log ([ADR 0018](../../decisions/0018-officeimpresso-log-acesso-passivo.md), migration `2026_04_23_200100`) foram removidos no dia seguinte (`2026_04_24_000000_drop_licenca_log_triggers.php`).
- **`business`**: `officeimpresso_bloqueado` (bloqueio da empresa inteira, lido pelo desktop), `versao_disponivel` e `versao_obrigatoria` (lidas pelo `check-update`), `caminho_banco_servidor`, `officeimpresso_numerodemaquinas`. São colunas da tabela do núcleo, não do módulo.
- **`business_locations.cnpj`**: primeira tentativa de identificar a empresa pelo CNPJ (multi-CNPJ), antes de `business.cnpj`.
- **Fronteira de dono:** as tabelas `licenca_computador` e `licenca_log` são **criadas** por migrations do Officeimpresso e **escritas** pelo Connector. O Connector depende ainda do alias `log.delphi`, que só o provider do Officeimpresso registra. Nenhum `module.json` declara isso (`"requires": []` nos dois em `ec57208855`); o lote L3 trata da declaração.

### 5.3 Fluxos críticos

**F1 — Token.** O desktop pede token por password grant num client OAuth administrado na tela Clientes. Cada `AccessTokenCreated` grava um `login_success` em `licenca_log`, com dedup por usuário + IP no mesmo minuto (`LogPassportAccessToken.php`). Em `ec57208855` o listener não filtra por client: todo token emitido pela aplicação entra nesse log, não só o do desktop. O lote L3 restringe ao client do Delphi. O `business_id` gravado é o do **usuário do token**, que no desktop é o usuário central; por isso o login raramente cai na licença certa.

**F2 — Registro, geração 1 (`processa-dados-cliente`).** O desktop manda um de dois formatos (docblock de `ProcessaDadosCliente`):
- **array com `NOME_TABELA`** (EMPRESA + LICENCIAMENTO): `saveBusiness` acha a empresa por `business.cnpj` ou **cria** uma (usuário dono provisório, pacote privado de contrato, módulos padrão com `officeimpresso` ligado). Se a empresa está com `officeimpresso_bloqueado`, responde `N;Cliente bloqueado`. Senão, `saveEquipamento` acha a máquina por `business_id + hd + user_win` ou cria uma com `bloqueado = true`, grava o inventário (sem as colunas de segredo, `salvarSemSegredosDoDesktop`) e responde `S;Cliente e equipamento liberados` ou `N;<motivo>`.
- **flat com `serial_hd`** (sem CNPJ), ou array só com LICENCIAMENTO: `processarApenasHd` acha **todas** as linhas com aquele HD, em qualquer empresa. Nenhuma: `N;Maquina nao cadastrada. Contate o suporte.` Alguma empresa bloqueada ou alguma máquina bloqueada: `N`. Senão atualiza o inventário de todas as linhas e responde `S`.

A resposta é texto, HTTP 200, inclusive no `N`.

**F2' — Registro, geração 3 (`oimpresso/registrar`).** Corpo JSON flat ou string separada por `|`. Exige `cnpj` e `serial_hd` (senão 400 com `autorizado: "N"`). Acha a empresa por `business_locations.cnpj` e depois `business.cnpj`; **não** cria empresa (`N` "Empresa nao cadastrada"). Empresa bloqueada: atualiza o inventário mesmo assim e responde `N`. Máquina nova nasce `bloqueado = true` com motivo "Aguardando aprovacao do superadmin" (`upsertLicenca`). Máquina liberada: `S` com `licenca_id`, `business_id`, `data_expiracao` e `dias_restantes` calculados de `dt_validade`.

**F3 — Liberação pelo operador.** A máquina nasce bloqueada nas duas gerações, então o primeiro acesso de toda máquina nova recebe `N` até o operador liberar na tela Licenças ou na ficha da empresa. O drawer manda a intenção (`bloquear: true|false`) com motivo obrigatório de 5 a 500 caracteres; intenção já cumprida é recusada (duplo clique não desfaz). O motivo vai **só** para `licenca_log` (`admin_action`, com o `business_id` do equipamento); o `licenca_computador.motivo`, que é o texto que o desktop mostra, **não** é reescrito por essa ação. A Blade antiga e a tela de Logs seguem chamando o toggle sem intenção (`toggleBlock`, ramo sem `bloquear`).

**F4 — Verificação a cada abertura.** É o mesmo F2/F2': o desktop repete o registro e lê `S`/`N`. Em paralelo, `check-update` recebe `CNPJ;VersaoAtual` em texto e responde `VersaoNova;VersaoMinObrigatoria` ou `N;VersaoMinObrigatoria`, a partir de `business.versao_disponivel` e `versao_obrigatoria`, que o operador edita na ficha da empresa (`businessupdate`). O que o desktop faz com a versão mínima é decisão do Delphi, fora deste código.

**F5 — Bloqueio da empresa.**
- **Manual:** `businessbloqueado` inverte `business.officeimpresso_bloqueado` (com a mesma guarda de intenção quando a ficha React manda `bloquear`). Em `ec57208855` essa ação **não** grava linha em `licenca_log`, ao contrário do bloqueio de máquina.
- **Automático:** quando o PaymentGateway dispara `CobrancaVencida` de uma cobrança `origem_type = subscription_license`, `OnCobrancaVencidaBloqueaSubscription` põe a assinatura em `declined` e liga `officeimpresso_bloqueado`. `CobrancaPaga` faz o inverso em `OnCobrancaPagaUpdateSubscription`. O ciclo da assinatura está no [SDD do Superadmin](../Superadmin/SDD-assinatura-ciclo-de-vida-v1.0.md) (CU-SUPER-03 e CU-SUPER-05). Essas duas escritas também não deixam linha em `licenca_log`.
- Nos dois casos o efeito chega ao desktop no próximo F4: `N;Cliente bloqueado` (geração 1) ou `autorizado: "N"`, "Empresa bloqueada" (geração 3).

**F6 — Log de tudo.** `LogDelphiAccess` roda depois do controller, dentro de `try/catch`: uma falha de log não muda a resposta ao desktop. Ele resolve a empresa pelo CNPJ do corpo (prioridade sobre o usuário do token), a máquina pelo HD, grava o estado de bloqueio no momento da chamada (`was_blocked`, `business_blocked`, `licenca_blocked`), a geração detectada e um `body_preview` de até 4 KB **depois** do `PiiRedactor`; se o redator falhar, grava `[REDACTED:BODY_PII_FALLBACK]` em vez do corpo cru.

**F7 — Importação Firebird (ramo de saída).** Dois caminhos convivem:
- **Python** (`scripts/legacy-migration/`, [ADR 0203](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)): `migrar-tudo.py` orquestra contas → empresas → contacts → produtos → vendas → itens → financeiro → notas fiscais, com idempotência por `legacy_id` ou chave única. É o caminho usado na migração de cliente registrada na ADR.
- **PHP** (`officeimpresso:import {biz}`): exige `{biz}`, tem `--dry-run`, e lê o Firebird só para leitura (`FirebirdConnector::assertReadOnly`). Desde o #11158 (2026-11-18), sem `--source-fb` ou sem a extensão `pdo_firebird` o modo mock só roda com `--dry-run`; sem ele o comando recusa antes de tocar o banco. Insert com erro conta em `Failed` e o comando sai com exit 1. `products`, `transactions` e `licenca_computador` não têm coluna `legacy_id`: essas linhas saem como `Blocked` e não são gravadas (criar a coluna em tabela core é a decisão B5 do [W]). Em 2026-11-18 o comando nunca tinha rodado contra um `.fdb` real; o teste dele (`ImportadorFirebirdFailClosedTest`) usa um connector de fixture no tenant 98.
- Nenhum dos dois grava a própria execução em `licenca_log` ou `activity_log`.

### 5.4 Onde os mundos ainda não se conversam

- **Dois registros com regras diferentes:** a geração 1 cria empresa nova a partir do CNPJ; a geração 3 recusa CNPJ desconhecido. A geração 1 identifica a máquina por `hd + user_win(DESCRICAO)`; a 3 por `hd + user_win(hostname)`. A mesma máquina pode virar duas linhas se o desktop alternar de geração. Lido do código; não medido.
- **Limite de máquinas não é aplicado:** `officeimpresso_numerodemaquinas` é editável na ficha, mas nenhum caminho de registro o lê (`git grep officeimpresso_numerodemaquinas -- Modules/Connector` sem resultado em `ec57208855`). O limite hoje é humano: a máquina nasce bloqueada e alguém libera.
- **Dois motivos:** o motivo do operador (log) e o `licenca_computador.motivo` (o que o desktop mostra) são campos diferentes. Uma máquina bloqueada pela tela nova mostra ao cliente o motivo antigo, ou "Motivo não informado".
- **Bloqueio de empresa sem trilha:** manual e automático não deixam linha em `licenca_log` (ver F5).
- **ADR 0113:** os três caminhos aditivos dependiam do módulo ADS, removido pela [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md); nenhum dos três existe em `ec57208855` (`git grep ads_delphi_commands -- ':!memory'` sem resultado).
- **ADR 0021, próximos passos:** pedia um `RegistroController` e um middleware de auth por `X-API-Key` dentro do Officeimpresso. O registro foi feito no Connector (`OImpressoRegistroController`); o middleware por `X-API-Key` não existe — as rotas exigem `auth:api`.

## 6. Casos de uso ⚙️+🖐 — agente propõe, **[W] confere**

Estado de cada CU pelo veredito de teste numa lane de PR. Em `ec57208855`, `node scripts/governance/test-lane-coverage.mjs --json` coloca `tests/Feature/Connector/DelphiOImpressoContractTest.php` e os testes de `Modules/Connector/Tests/Feature/` numa lane, e lista 13 dos 24 arquivos de `Modules/Officeimpresso/Tests/Feature/` como fora de lane (incluindo `E2EJourneyDelphiBiz1Test`). Parte dos testes do contrato Delphi lê o arquivo de rotas ou chama método isolado, sem exercitar o ramo bloqueado de ponta a ponta. Nenhum veredito de run foi lido nesta sessão; os CU ficam `⬜` até alguém ler e marcar.

#### CU-OI-01 — Desktop obtém token e o login entra no log `[must]` ⬜
*Dado* um client OAuth de senha ativo; *quando* o desktop pede token; *então* o Passport emite e `licenca_log` ganha um `login_success`.
1. `[must]` dois tokens do mesmo usuário e IP no mesmo minuto geram um só registro
2. `[must]` falha ao gravar o log não impede a emissão do token

#### CU-OI-02 — Máquina nova se registra bloqueada `[must]` ⬜
*Dado* empresa conhecida e HD nunca visto; *quando* o desktop registra (geração 1 ou 3); *então* nasce uma linha em `licenca_computador` com `bloqueado = true` e a resposta é `N`.
1. `[must]` geração 1 com EMPRESA desconhecida cria a empresa; geração 3 com CNPJ desconhecido responde `N` sem criar
2. `[T0]` a máquina pertence à empresa do CNPJ do corpo, não à do usuário do token
3. `[must]` `senha` e `contra_senha` do desktop não são gravadas e seguem no eco da resposta (`LicencaComputadorSegredoDesktopTest`)

#### CU-OI-03 — Máquina liberada recebe autorização `[must]` ⬜
1. `[must]` geração 1 responde `S;Cliente e equipamento liberados`
2. `[must]` geração 3 responde `autorizado: "S"` com `data_expiracao` e `dias_restantes` de `dt_validade`
3. `[must]` o inventário e `dt_ultimo_acesso` são atualizados

#### CU-OI-04 — Body sem CNPJ resolve pelo HD `[must]` ⬜
1. `[must]` HD desconhecido responde `N;Maquina nao cadastrada. Contate o suporte.`
2. `[must]` qualquer empresa ou máquina bloqueada com aquele HD responde `N`
3. `[must]` caso contrário atualiza todas as linhas daquele HD

#### CU-OI-05 — Operador bloqueia ou libera máquina com motivo `[must]` ⬜
1. `[must]` motivo obrigatório, 5 a 500 caracteres, com PII redigida antes de gravar
2. `[must]` intenção já cumprida é recusada
3. `[T0]` o registro em `licenca_log` leva o `business_id` do equipamento
4. `[T0]` permissão delegada só vale para usuário da empresa operadora (`LicencasAcessoPermissionTest`)

#### CU-OI-06 — Empresa bloqueada recusa todas as máquinas `[must]` ⬜
1. `[must]` com `officeimpresso_bloqueado`, geração 1 responde `N;Cliente bloqueado` e geração 3 `autorizado: "N"`
2. `[must]` cobrança de licença vencida liga o bloqueio e cobrança paga o desliga (coberto do lado Superadmin por `OnCobrancaVencidaBloqueaSubscriptionTest` e `OnCobrancaPagaUpdateSubscriptionTest`)

#### CU-OI-07 — Desktop consulta versão `[should]` ⬜
1. `[must]` resposta em texto `VersaoNova;VersaoMinObrigatoria` ou `N;VersaoMinObrigatoria`, de `business.versao_disponivel` / `versao_obrigatoria`

#### CU-OI-08 — Toda chamada do desktop fica no log sem PII `[must]` ⬜
1. `[must]` chamada sem HD também é logada; chamada recusada por token também
2. `[must]` `body_preview` passa pelo `PiiRedactor`; se ele falhar, o corpo cru não é gravado
3. `[must]` falha de log não altera a resposta ao desktop

#### CU-OI-09 — Importação Firebird de um cliente `[should]` ⬜
1. `[T0]` `{biz}` obrigatório e o Firebird aberto só para leitura
2. `[must]` segunda execução não duplica (idempotência por `legacy_id` ou chave única)
3. `[must]` sem fonte real, não grava dado — **em `ec57208855` o comando PHP não cumpre isto**; lote L2

### 6.4 Non-goals explícitos — 🖐 **só [W] preenche**

_vazio_

## 7. Requisitos não-funcionais ⚙️ derivado

- `throttle:120,1` por token nas rotas Delphi (`Modules/Connector/Routes/api.php:10`).
- Os dois endpoints de registro são embrulhados em span OTel (`OtelHelper::spanBiz`, `connector.delphi.processa_dados_cliente` e `connector.delphi.oimpresso.registrar`).
- Tudo síncrono: nenhum `ShouldQueue` no Officeimpresso. O listener do Passport roda dentro da emissão do token (3 a 4 queries antes do insert).
- Nenhum comando do Officeimpresso está agendado: `officeimpresso:health`, `licenca-log:parse`, `officeimpresso:import`, `officeimpresso:inspect-api` e `officeimpresso:alinhar-pacotes-catalogo` rodam à mão.

## 8. Estratégia de qualidade e rollout ⚙️ derivado

- O dono de "este teste roda?" é `node scripts/governance/test-lane-coverage.mjs --json`. Em `ec57208855`: Connector com 15 arquivos de teste e 0 fora de lane; Officeimpresso com 24 e 13 fora de lane; `tests/Feature/Officeimpresso/OfficeimpressoRoutesTest.php` também fora. O lote L4 da auditoria liga os que passam.
- Pest só no CT 100 (`memory/proibicoes.md` §Ambiente).
- Qualquer mudança no wire passa pela regra de `memory/reference/contrato-delphi-inviolavel.md`: o desktop em campo não é recompilado.

## 9. Riscos e dívidas 🖐 curado

| # | Pendência | Estado atual (código) | Quem decide |
|---|---|---|---|
| **B3** | ADR 0017 "superadmin exclusivo" × delegação ao suporte | O código delega desde 2026-07-30 (`AcessoOperador`, permissões graduadas). A ADR segue `aceito`/`ativo` e nenhuma ADR a supera | [W] |
| **B4** | Expurgo do `licenca_log` | `Config/retention.php` e `module.json` descrevem expurgo; nenhum comando existe; a lápide §5 de 2026-07-27 recusa expurgo por TTL em entidade do ERP. Se log técnico de acesso cai ou não na lápide é decisão aberta | [W] |
| — | Limite de máquinas não aplicado | `officeimpresso_numerodemaquinas` sem leitor no registro (§5.4) | [W]: é para aplicar? |
| — | Motivo mostrado ao cliente | O motivo do operador não chega ao `N;` (§5.4) | [W]: o cliente deve ver o motivo novo? |
| — | Bloqueio de empresa sem trilha | Nem o manual nem o da cobrança deixam linha em `licenca_log` | engenharia |
| — | Dependência não declarada | Connector usa `log.delphi` e escreve nas tabelas do Officeimpresso; `module.json` não declara | lote L3 |
| — | Listener do Passport grava todo token | Sem filtro de client em `ec57208855` | lote L3 |
| — | `POST /api/officeimpresso/audit` aceita `event` livre | `LicencaAuditService` usa o `event` do corpo | lote L3 |
| **B5** | Importador PHP não grava `products`, `transactions` e `licenca_computador` | Sem coluna `legacy_id` nessas tabelas core, as linhas saem como `Blocked` (#11158). Criar a coluna é decisão sobre tabela core | [W] |
| — | Mesma máquina em duas linhas | Gerações 1 e 3 usam `user_win` diferente | não medido |

**Perguntas abertas ao [W]** (sem fonte no código nem nas ADRs): o que o desktop faz depois de um `N` (fecha, avisa, segue em modo leitura); quem preenche `dt_validade` (o desktop manda `DT_VALIDADE` na geração 1; nenhuma tela edita); se a geração 1 ainda deve criar empresa a partir de CNPJ desconhecido.

## 10. Roadmap 🖐 [W] prioriza

Lotes L0–L4 da auditoria de 2026-11-05 mexem em §5.1, §5.3 F1, F7 e §9. Este SDD ganha linha no Changelog quando cada um mergear. B3 e B4 esperam decisão.

## 11. Referências

- [SPEC](SPEC.md) · [BRIEFING](BRIEFING.md) · [SCOPE](SCOPE.md) · [SUPERFICIE](SUPERFICIE.md)
- RUNBOOKs: [licencas](RUNBOOK-licencas.md) · [empresa](RUNBOOK-empresa.md) · [empresas](RUNBOOK-empresas.md) · [logs](RUNBOOK-logs.md) · [clientes](RUNBOOK-clientes.md)
- Esquema Firebird: [OFFICEIMPRESSO-FIREBIRD-SCHEMA](OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
- Connector: [SCOPE](../Connector/SCOPE.md) · [SPEC](../Connector/SPEC.md)
- [SDD do ciclo da assinatura (Superadmin)](../Superadmin/SDD-assinatura-ciclo-de-vida-v1.0.md)
- ADRs: [0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) · [0018](../../decisions/0018-officeimpresso-log-acesso-passivo.md) · [0019](../../decisions/0019-officeimpresso-delphi-nao-autentica.md) · [0021](../../decisions/0021-officeimpresso-contrato-api-delphi.md) · [0113](../../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) · [0203](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) · [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [`memory/reference/contrato-delphi-inviolavel.md`](../../reference/contrato-delphi-inviolavel.md)

## Changelog (append-only — correção não apaga, vira linha)

- 1.0.0 (2026-11-18) — criado no lote L5 da auditoria do Officeimpresso de 2026-11-05, lido de `origin/main` `ec57208855`, com os lotes L0–L3 ainda abertos.
- 1.0.1 (2026-11-18) — F7 e §9 descrevem o importador PHP depois do #11158 (lote L2): mock só com `--dry-run`, `Failed` com exit 1, `Blocked` sem `legacy_id`. A linha "Importador PHP grava fixtures sem fonte" do §9 saiu e entrou a decisão B5.
