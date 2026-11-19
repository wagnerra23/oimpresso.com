---
id: requisitos-officeimpresso-spec
module: Officeimpresso
version: "1.3"
last_updated: "2026-11-18"
owner: wagner
status: ativo
related_adrs:
  - 0017-officeimpresso-restaurado-superadmin-exclusivo
  - 0018-officeimpresso-log-acesso-passivo
  - 0019-officeimpresso-delphi-nao-autentica
  - 0021-officeimpresso-contrato-api-delphi
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0113-integracao-delphi-laravel-ads-3-caminhos
  - 0203-legacy-migration-pipeline-firebird-oimpresso-w29
---

<!-- schema-allowlist: até 2026-08-19 este SPEC era `arquivado` e sem seção US por design (bridge legacy a descomissionar). [W] priorizou a migração React do módulo em 2026-08-19, então ele voltou a `ativo` e ganhou a seção US da Onda 1. O racional de descomissionamento segue válido pro RESTO do módulo — a Onda 1 é trabalho de shell, não capacidade nova. -->

# SPEC — Modules/Officeimpresso

## Visão

Administração do licenciamento do desktop legado **WR Comercial / WR Sistemas (OfficeImpresso, Delphi)**: quais máquinas de cada cliente podem rodar o executável, quais empresas estão bloqueadas, que versão o desktop deve ter e o histórico de cada contato do desktop com o servidor. Mais o ramo de saída: trazer os dados do Firebird do cliente para o oimpresso.

Não é módulo de produto novo. O fluxo ponta a ponta (token → registro → liberação → `S`/`N` → bloqueio → log → importação) está no [SDD do licenciamento](SDD-licenciamento-delphi-v1.0.md).

## Arquitetura atual

Contado em `origin/main` `5c7e0fb280` (2026-11-18). Os números se refazem com os comandos ao lado; não os copie para outro doc.

- **Controllers** (`Http/Controllers/`): `AuditController` (API do desktop), `ClientController` (clients OAuth), `DataController` (menu e permissões), `InstallController`, `LicencaComputadorController` (máquinas e empresas), `LicencaLogController` (log) e `OfficeimpressoController` (home e redirect do catálogo para o `ProductCatalogue`). Lista: `git ls-files 'Modules/Officeimpresso/Http/Controllers/*.php'`.
- **Entidades:** `Licenca_Computador` (uma linha por máquina e empresa) e `LicencaLog` (append-only por uso).
- **Telas React** (`Resources/js/Pages/Officeimpresso/`): `Logs/Index`, `Logs/Timeline`, `Licencas/Index`, `Empresa/Show`, `Empresas/Index`, `Clientes/Index`. Cada uma com charter, casos e RUNBOOK. Lista: `git ls-files 'Modules/Officeimpresso/Resources/js/Pages/**/*.tsx'`.
- **Testes:** `git ls-files 'Modules/Officeimpresso/Tests/Feature/*.php' | wc -l`. Quais rodam em lane de PR: `node scripts/governance/test-lane-coverage.mjs --json` (chave `por_modulo.Officeimpresso`).
- **Quem consome quem:** o **desktop Delphi** consome a API do `Modules/Connector` (`/connector/api/processa-dados-cliente`, `/connector/api/oimpresso/registrar`, `/connector/api/check-update`). O Connector grava em `licenca_computador` e `licenca_log`, tabelas criadas pelas migrations deste módulo, e usa o middleware `log.delphi`, que só o `OfficeimpressoServiceProvider` registra. A API própria deste módulo é pequena: `GET /api/officeimpresso` (ping) e `POST /api/officeimpresso/audit`.
- **Bloqueio por cobrança:** os listeners do `Modules/Superadmin` (`OnCobrancaVencidaBloqueaSubscription`, `OnCobrancaPagaUpdateSubscription`) ligam e desligam `business.officeimpresso_bloqueado`, que o desktop lê no próximo registro.
- **Acesso:** `superadmin` ou as permissões delegáveis `officeimpresso.*`, que só valem para usuário da empresa operadora (`Services/AcessoOperador.php`). Essa delegação, de 2026-07-30, convive com a [ADR 0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) ("superadmin exclusivo"), que segue aceita e sem sucessora; a reconciliação é decisão [W] pendente.
- **Esquema Firebird:** [OFFICEIMPRESSO-FIREBIRD-SCHEMA.md](OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) (skill `officeimpresso-source-analysis`).

## Roadmap

- Migração efetiva por cliente é decidida no projeto principal (Wagner aprova por business_id elegível).
- Módulo **será descomissionado** quando o último cliente Delphi sair do legacy (sem ETA — depende de sinal qualificado por cliente, ADR 0105).

## Epic — Onda 1 da migração React (telas `Logs/`)

> **Origem:** [W] priorizou "fazer o módulo OfficeImpresso" em 2026-08-19 e escolheu o escopo
> **Onda 1: as 2 telas P0**. Isso satisfaz o critério verde que o
> [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) §"Quando NÃO migrar" exige
> (*"Wagner explicitamente prioriza"*).
>
> **F1 PLAN (feita):** o plano de tela é o [RUNBOOK-logs.md](RUNBOOK-logs.md) — 11 seções, mapa
> `oi-*` → canon DS, contrato de props das 2 telas, 9 pegadinhas. Ele não é US: é o artefato de
> planejamento que a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) exige
> ANTES da F2, e não tem comportamento pra testar. As US abaixo são F2→F5.
>
> **Escopo negativo:** as outras 12 telas do módulo NÃO entram nesta onda. Nenhuma capacidade
> de negócio nova — é troca de shell, campo a campo.

### US-OI-001 — F2: Pest baseline do comportamento atual
**Como** dono do módulo, **quero** o comportamento de hoje travado em teste ANTES de mexer, **pra** que a migração não perca regra em silêncio.
**Implementado em:** `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php`
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php`
**Aceite:**
- ≥5 fixtures — guarda 403 sem permissão; escopo por business de quem não é `podeVerTodasEmpresas()`; os 5 filtros (`q`, `estado_atual`, `business_id`, `licenca_id`, `hd`); os 4 KPIs; 404 do `timeline()` com id inexistente
- Rodado no CT 100 (nunca local), tenant 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md))

### US-OI-002 — F2: action dual + feature flag `useV2OfficeimpressoLogs`
**Como** dono do módulo, **quero** as duas telas atrás de flag com o Blade intacto, **pra** ter rota de fuga se a React falhar em produção.
**Implementado em:** `Modules/Officeimpresso/Http/Controllers/LicencaLogController.php` — flag + `Inertia::render` das **duas** telas, cada um entregue no PR da sua page (render antes da page é órfão; ver RUNBOOK-logs §F2).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php`
**Aceite:**
- `blocked_by`: US-OI-001
- **A troca de caminho mora com a TELA, não na F2.** O `Inertia::render` só entra no PR que traz o `.tsx` correspondente: render apontando pra page inexistente é 500 esperando a flag ligar, e o `OrphanRenderGateTest` (required) reprova — corretamente. Esta US fecha quando as duas telas da F3 tiverem o seu render
- `Inertia::render` quando a flag está ligada; senão `view()` como hoje. **Só a flag decide** — condicionar ao header `X-Inertia` (como o DoD da skill `mwart-process` pede) quebraria o first load, que não manda esse header; o único dual em produção decide só pela flag
- Flag via `FeatureFlagService`/GrowthBook, default OFF pelo `fallbackDefaults` não listá-la. **Não** há comando `enable-v2` — esse padrão não existe no projeto (`git grep -lE 'enable-v2|enableV2' -- '*.php'` = zero)
- Props caras (lista de máquinas, KPIs, logs da timeline) em `Inertia::defer`
- A extração de `buildMaquinasPayload()`/`buildKpisPayload()` (essa sim já feita na F2) não muda uma linha da consulta: quando o render entrar, ele consome o MESMO payload que o Blade

### US-OI-003 — F2: mapa de paridade Blade↔React
**Como** revisor, **quero** todo campo do Blade mapeado com severidade, **pra** que "some um campo" seja detectável, não descoberto pelo usuário.
**Implementado em:** `memory/requisitos/Officeimpresso/logs-parity.md` — o mapa está completo (54 itens, 21 de severidade `alta`) e os **21 de 21** têm teste de comportamento citando o id do UC (F4, US-OI-006). O template é explícito: *"um `-parity.md` sem teste pros itens `alta` é débito, não conclusão"* — a dívida foi paga.
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php`
**Aceite:**
- `logs-parity.md` no [template](../_DesignSystem/PARITY-TEMPLATE.md), cobrindo as 10 colunas da tabela, os 4 KPIs, os 5 filtros, os 3 chips de filtro ativo, os 2 empty states e as 2 ações de bloqueio
- Divergências deliberadas declaradas — a principal é `toggle-block` sair de `GET` pra `POST`
- A coluna "Está no React?" nasce `⏳` e é a F3 que a preenche, tela por tela

### US-OI-004 — F3: tela `Logs/Index` (Máquinas Cadastradas) em PT-01
**Como** suporte, **quero** a lista de máquinas no shell React, **pra** operar licença sem sair do cockpit.
**Implementado em:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx` · `Modules/Officeimpresso/Http/Controllers/LicencaLogController.php` — a tela nasceu em 2026-08-20; a flag `useV2OfficeimpressoLogs` está com padrão `true` em `app/Services/FeatureFlagService.php` desde a decisão D-LOGS [W] de 2026-10-14 (#9412).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php`
**Aceite:**
- `blocked_by`: US-OI-002, US-OI-003
- PT-01 (Header, Toolbar, Table, EmptyState); `StatusBadge kind="licenca"` novo; sem cor crua
- 1280px com sidebar aberta sem scroll horizontal na página

### US-OI-005 — F3: tela `Logs/Timeline` (acessos por máquina) em PT-07
**Como** suporte, **quero** o histórico de acesso de uma máquina, **pra** diagnosticar bloqueio de cliente.
**Implementado em:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Timeline.tsx` · `Modules/Officeimpresso/Http/Controllers/LicencaLogController.php` — mesma flag da US-OI-004.
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php`
**Aceite:**
- `blocked_by`: US-OI-002, US-OI-003
- PT-07; preserva o tri-estado de "Estado no Login" e o 404 de máquina inexistente

### US-OI-006 — F4: QA — os itens `alta` da paridade viram teste
**Como** revisor, **quero** que cada item de severidade `alta` quebre um teste se sumir, **pra** que a paridade seja enforcement de comportamento, não papel.
**Implementado em:** _parcial_ · `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php` — os **21 de 21** itens `alta` têm teste citando o UC (32 passed · 123 assertions no CT 100, tenant 98). Falta só o smoke com screenshot 1280/1440 do aceite abaixo, que depende da flag ligada (F5).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LogsBaselineTest.php`
**Aceite:**
- `blocked_by`: US-OI-004, US-OI-005
- Cada item `alta` com teste que cita o id do UC; **presença do `-parity.md` não conta**
- Smoke com screenshot 1280 + 1440

### US-OI-007 — F5: cutover e sunset do Blade
**Como** dono do módulo, **quero** o Blade removido depois da prova, **pra** não manter dois caminhos.
**Implementado em:** _pendente_ — a flag `useV2OfficeimpressoLogs` está ligada para todas as empresas desde 2026-10-14 (#9412), mas a flag e os Blades `licenca_log/index` e `licenca_log/timeline` seguem no repo como rota de fuga. O aceite (remover os dois e a flag) não foi cumprido.
**Aceite:**
- `blocked_by`: US-OI-006
- Flag ON, período de observação, então remove `licenca_log/*.blade.php`, a flag e o comando
- **Portão:** não entra antes de US-OI-006 verde. Enquanto o Blade existe, ele é a rota de fuga.

## Epic — Onda 2 da migração React (as 3 telas P1)

> **Origem:** [W] mandou seguir da Onda 1 (telas `Logs/`, F1→F4 mergeadas) para as **3 telas P1** do
> inventário de 14 do [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md). Mesmo critério verde
> que a Onda 1 satisfez (*"Wagner explicitamente prioriza"*).
>
> | # | Blade | Rota | Page alvo | Padrão de Tela | F1 PLAN |
> |---|---|---|---|---|---|
> | 3 | `licenca_computador/index.blade.php` | `/officeimpresso/licenca_computador` | `Officeimpresso/Licencas/Index` | PT-01 Lista | [RUNBOOK-licencas.md](RUNBOOK-licencas.md) |
> | 4 | `licenca_computador/computadores.blade.php` | `/officeimpresso/computadores` | `Officeimpresso/Empresa/Show` | PT-03 Detalhe | [RUNBOOK-empresa.md](RUNBOOK-empresa.md) |
> | 5 | `licenca_computador/businessall.blade.php` | `/officeimpresso/businessall` | `Officeimpresso/Empresas/Index` | PT-01 Lista | [RUNBOOK-empresas.md](RUNBOOK-empresas.md) |
>
> **F1 PLAN (feita):** um RUNBOOK de 11 seções por tela + um `-parity.md` por tela. Não são US: são
> os artefatos que a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) exige
> ANTES da F2, e não têm comportamento pra testar. As US abaixo são F2→F5.
>
> **Escopo negativo:** as outras 9 telas do módulo NÃO entram. Nenhuma capacidade de negócio nova —
> é troca de shell, campo a campo. As duas únicas mudanças de comportamento são de **segurança** e
> estão declaradas como divergência deliberada em cada `-parity.md`: `GET` que muda estado vira
> `POST` com confirmação, e o payload passa a ser DTO explícito (o model carrega `senha` e
> `contra_senha`, e prop de Inertia é serializada no HTML da página — ver
> [RUNBOOK-licencas §10.1](RUNBOOK-licencas.md)).

### US-OI-008 — F2: baseline, payload seguro e flag da `Licencas/Index`
**Como** dono do módulo, **quero** o comportamento de hoje travado em teste e o payload extraído ANTES de mexer, **pra** que a migração não perca regra nem publique credencial.
**Implementado em:** `Modules/Officeimpresso/Http/Controllers/LicencaComputadorController.php` · `Modules/Officeimpresso/Tests/Feature/LicencasIndexContratoTest.php` — F2 entregue; a flag `useV2OfficeimpressoLicencas` já saiu no cutover de 2026-10-06 (#8783).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LicencasIndexContratoTest.php`
**Aceite:**
- Pest baseline no CT 100, tenant 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)): 403 sem permissão, 200 com `officeimpresso.access`, os 3 KPIs e — a fixture que só o teste pega — **máquina de outro `business_id` não aparece na lista**
- `buildLicencasPayload()` devolvendo DTO de 7 campos (`id, hd, user_win, processador, memoria, versao_exe, bloqueado`). **Nunca o model:** `Licenca_Computador` não tem `$hidden` e o `$fillable` lista `senha`, `contra_senha`, `serial` e `token`
- Flag `useV2OfficeimpressoLicencas` default OFF via `FeatureFlagService`/GrowthBook. **Não** há comando `enable-v2` — esse padrão não existe no projeto (`git grep -lE 'enable-v2|enableV2' -- '*.php'` = zero)
- **Só a flag decide** o caminho dual — condicionar ao header `X-Inertia` quebraria o first load, que não manda esse header (medido na Onda 1)
- O `Inertia::render` **não** entra aqui: render apontando pra page inexistente é 500 esperando a flag ligar, e o `OrphanRenderGateTest` (required) reprova. Ele viaja com a tela, na US-OI-009
- `licencas-parity.md` completo (29 itens, 9 de severidade `alta`) — já entregue na F1

### US-OI-009 — F3: tela `Licencas/Index` (Computadores Cadastrados) em PT-01
**Como** suporte, **quero** a lista de máquinas licenciadas no shell React, **pra** operar licença sem sair do cockpit.
**Implementado em:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Licencas/Index.tsx` · `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Licencas/_components/LicencaDrawer.tsx` — a lista nasceu em 2026-10-01 (#8372); desde o cutover de 2026-10-06 (#8783) o `index` só renderiza Inertia e o Blade da lista de licenças foi apagado.
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LicencasIndexContratoTest.php`
**Aceite:**
- `blocked_by`: US-OI-008
- PT-01 (Header, Toolbar, Table, EmptyState); `StatusBadge kind="licenca"`; sem cor crua
- Props caras (`licencas`, `kpis`) em `Inertia::defer`; `permissions` eager
- Traz o `Inertia::render` + o dual da action, no mesmo PR do `.tsx`
- 1280px com sidebar aberta sem scroll horizontal na página

### US-OI-010 — F4: QA da `Licencas/Index` — os itens `alta` viram teste
**Como** revisor, **quero** que cada item de severidade `alta` quebre um teste se sumir, **pra** que a paridade seja enforcement de comportamento, não papel.
**Implementado em:** _pendente_ — o contrato da tela (`LicencasIndexContratoTest`, cita `UC-OILIC-*`) cobre a US-OI-008/009 e roda em lane. Se os 9 itens `alta` do `licencas-parity.md` têm todos teste **não foi recontado** depois do cutover de 2026-10-06, e o smoke 1280/1440 não tem recibo.
**Aceite:**
- `blocked_by`: US-OI-009
- Os **6 itens `alta` ainda sem teste** (12, 13, 19, 22, 27, 29 do `licencas-parity.md`) com teste citando o id do UC. Os outros 3 (25, 26, 28) já são cobertos por `LicencasAcessoPermissionTest`
- Inclui o **assert negativo** do item 29: `senha`, `contra_senha`, `serial` e `token` ausentes da prop
- **Presença do `-parity.md` não conta**; smoke com screenshot 1280 + 1440

## Onda 2 — telas P1 · US da tela #4 (`Empresa/Show`)

> Continuação do **epic da Onda 2** logo acima. Esta seção traz as US da tela **#4**, cujo F1 PLAN é o [RUNBOOK-empresa.md](RUNBOOK-empresa.md)
> e cujo contrato campo-a-campo é o [empresa-parity.md](empresa-parity.md).

### US-OI-011 — F2: baseline, payload seguro e flag da `Empresa/Show`
**Como** dono do módulo, **quero** o comportamento de hoje travado em teste e o payload extraído ANTES de mexer, **pra** que a migração não perca regra nem publique credencial.
**Implementado em:** `Modules/Officeimpresso/Http/Controllers/LicencaComputadorController.php` · `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaShowContratoTest.php` — a flag `useV2OfficeimpressoEmpresa` está com padrão `true` em `app/Services/FeatureFlagService.php`.
**Testado em:** `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaShowContratoTest.php`
**Aceite:**
- ~~`blocked_by`: decisão [W] sobre o escopo do `viewLicencas`~~ — respondida por [W] em 2026-10-12 (D1 do `empresa-parity.md`: manter e nomear)
- Pest baseline no CT 100, tenant 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)): 403 sem permissão e 200 com `access` (já cobertos), os dois no-leak de escrita (já cobertos), a ficha abrindo **sem** `package` e **sem** `active` (os dois blocos são condicionais), e o escopo das **duas** rotas
- `buildEmpresaPayload()` (10 campos) + `buildComputadoresPayload()` (9 campos) devolvendo DTO. **Nunca os models** — `Licenca_Computador` não tem `$hidden` e o `$fillable` lista `senha`, `contra_senha`, `serial` e `token`; `Business` é um model gordo do ERP inteiro
- Flag `useV2OfficeimpressoEmpresa` default OFF, valendo para as **duas** rotas — ligar só uma faz o suporte trocar de shell ao clicar "Ver computadores desta empresa" no `businessall`
- **Só a flag decide** o dual; o `Inertia::render` viaja com a tela (US-OI-012), não aqui
- `empresa-parity.md` completo (45 itens, 23 de severidade `alta`) — já entregue na F1

### US-OI-012 — F3: tela `Empresa/Show` (ficha + computadores) em PT-03
**Como** suporte, **quero** a ficha da empresa licenciada no shell React, **pra** bloquear cliente, ajustar versão obrigatória e chegar no log sem sair do cockpit.
**Implementado em:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresa/Show.tsx` · `Modules/Officeimpresso/Http/Controllers/LicencaComputadorController.php` — a ficha edita a configuração e bloqueia a empresa (#9178, 2026-10-13) e bloqueia e libera máquina com motivo (#9182, 2026-10-13).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresaShowContratoTest.php`
**Aceite:**
- `blocked_by`: US-OI-011
- PT-03 (header-identidade, resumo, seções, ações contextuais); `StatusBadge kind="empresa"` **novo** e distinto do `kind="licenca"` — o sujeito aqui é a empresa, não a máquina
- Modal de configuração vira `<Dialog>` do DS + `useForm`. **Não** é drawer 760 ([ADR 0185](../../decisions/0185-drawer-760-canon-entidades-cadastrais.md) é pra entidade cadastral; aqui são 3 campos de config)
- `computadores` e `assinatura` em `Inertia::defer`; `empresa` e `permissions` eager
- Preserva o que é intencional: `@format_date` com o shift +3h ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)), `limite == 0` = "Ilimitado", e os blocos condicionais **ausentes** quando não há `package`/`active`
- Traz o `Inertia::render` + o dual das duas actions, no mesmo PR do `.tsx`
- 1280px com sidebar aberta sem scroll horizontal — são 10 colunas, o mesmo número que estoura na `Logs/Index`

### US-OI-013 — F4: QA da `Empresa/Show` — os itens `alta` viram teste
**Como** revisor, **quero** que cada item de severidade `alta` quebre um teste se sumir, **pra** que a paridade seja enforcement de comportamento, não papel.
**Implementado em:** _pendente_ — os contratos da ficha e das ações (`ComputadoresEmpresaShowContratoTest`, `ComputadoresEmpresaAcoesContratoTest`) cobrem a US-OI-011/012. Se os 23 itens `alta` do `empresa-parity.md` têm todos teste **não foi recontado**, e o smoke 1280/1440 nas duas rotas não tem recibo.
**Aceite:**
- `blocked_by`: US-OI-012
- Os **18 itens `alta` ainda sem teste** do `empresa-parity.md` com teste citando o id do UC. Os outros 5 (38, 39, 40, 41, 42) já são cobertos por `LicencasAcessoPermissionTest`
- Inclui o **assert negativo** do item 45 (`senha`, `contra_senha`, `serial`, `token` ausentes da prop) e a fixture de escopo do item 44, conforme a decisão [W]
- **Presença do `-parity.md` não conta**; smoke com screenshot 1280 + 1440 **nas duas rotas**

## Onda 2 — telas P1 · US da tela #5 (`Empresas/Index`) e o cutover da onda

> Continuação do **epic da Onda 2** acima. Esta seção traz as US da tela **#5**, cujo F1 PLAN é o [RUNBOOK-empresas.md](RUNBOOK-empresas.md) e
> cujo contrato campo-a-campo é o [empresas-parity.md](empresas-parity.md), mais a **US-OI-017**, que
> é o cutover das **três** telas — uma só, porque flag ligada em metade da onda faz o suporte trocar
> de shell no meio da navegação.

### US-OI-014 — F2: baseline, payload seguro e flag da `Empresas/Index`
**Como** dono do módulo, **quero** o comportamento de hoje travado em teste e o payload extraído ANTES de mexer, **pra** que a migração não perca regra nem publique cadastro.
**Implementado em:** `Modules/Officeimpresso/Http/Controllers/LicencaComputadorController.php` · `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresasIndexContratoTest.php` — F2 entregue; a flag `useV2OfficeimpressoEmpresas` saiu no cutover de 2026-10-27 (#10035).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresasIndexContratoTest.php`
**Aceite:**
- ~~`blocked_by`: decisão [W] sobre as 4 colunas mortas~~ — respondida por [W] em 2026-10-12 (D1 do `empresas-parity.md`)
- Pest baseline no CT 100, tenant 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)): 403 sem permissão e 200 com `access` (já cobertos), **empresa sem `is_officeimpresso` não aparece** (a fixture que trava a regra da tela), e os 3 KPIs
- `buildEmpresasPayload()` devolvendo DTO. **Nunca o model `Business`** — ele carrega o cadastro inteiro do ERP, e como esta lista é **global**, mandar o model cru publicaria o cadastro completo de todas as empresas no `data-page` do HTML
- Flag `useV2OfficeimpressoEmpresas` default OFF; **só a flag decide** o dual; o `Inertia::render` viaja com a tela (US-OI-015)
- `empresas-parity.md` completo (28 itens, 11 de severidade `alta`) — já entregue na F1

### US-OI-015 — F3: tela `Empresas/Index` (Empresas Licenciadas) em PT-01
**Como** suporte, **quero** a lista de empresas licenciadas no shell React, **pra** chegar na ficha e no log de qualquer cliente sem sair do cockpit.
**Implementado em:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresas/Index.tsx` — lista com busca (nome, razão social, CNPJ, versão, #); cutover em 2026-10-27 (#10035), sem flag e sem Blade. O drawer do protótipo não foi feito.
**Testado em:** `Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresasIndexContratoTest.php`
**Aceite:**
- `blocked_by`: US-OI-014
- PT-01 (Header, Toolbar, Table, EmptyState); `StatusBadge kind="empresa"` — o **mesmo** `kind` da tela #4
- Os dois botões de ação ganham `aria-label` — hoje são ícone sem rótulo, com o texto só no `title`
- Props caras (`empresas`, `kpis`) em `Inertia::defer`
- Traz o `Inertia::render` + o dual da action, no mesmo PR do `.tsx`
- 1280px com sidebar aberta sem scroll horizontal — é a tela com **mais colunas** do módulo (11), e a decisão D1 pode reduzi-la a 7

### US-OI-016 — F4: QA da `Empresas/Index` — os itens `alta` viram teste
**Como** revisor, **quero** que cada item de severidade `alta` quebre um teste se sumir, **pra** que a paridade seja enforcement de comportamento, não papel.
**Implementado em:** _pendente_ — o contrato da lista (`ComputadoresEmpresasIndexContratoTest`) cobre a US-OI-014/015. Se os 11 itens `alta` do `empresas-parity.md` têm todos teste **não foi recontado** depois do cutover de 2026-10-27, e o smoke 1280/1440 não tem recibo.
**Aceite:**
- `blocked_by`: US-OI-015
- Os **9 itens `alta` ainda sem teste** do `empresas-parity.md` com teste citando o id do UC. Os outros 2 (24, 25) já são cobertos por `LicencasAcessoPermissionTest`
- Inclui o **assert negativo** do item 28 (o model `Business` cru não vai na prop)
- **Presença do `-parity.md` não conta**; smoke com screenshot 1280 + 1440

### US-OI-017 — F5: cutover das 3 telas da Onda 2 e sunset dos Blades
**Como** dono do módulo, **quero** os Blades removidos depois da prova, **pra** não manter dois caminhos.
**Implementado em:** _pendente_ — 2 das 3 telas tiveram cutover (Licenças 2026-10-06, #8783; Empresas 2026-10-27, #10035). Falta a ficha da empresa: a flag `useV2OfficeimpressoEmpresa` está ligada, mas a flag e o Blade `licenca_computador/computadores` seguem no repo.
**Aceite:**
- `blocked_by`: US-OI-010, US-OI-013, US-OI-016
- **Uma US só pras três telas:** as rotas se linkam entre si (a lista leva à ficha, as duas levam ao log), então flag ligada em metade da onda faz o suporte trocar de shell no meio da navegação
- Módulo interno (WR2 + suporte), sem cliente externo — não precisa de janela de aviso; flag ON, período de observação, então remove os 3 Blades e as 3 flags
- **É aqui que as 3 telas entram no gate visual** — antes é impossível: com a flag OFF a rota devolve o Blade, o `assertInertia(component: ...)` não acha nada e o `visual-regression` (required) fica vermelho pro repo inteiro. Os 4 pré-requisitos estão em [RUNBOOK-logs §F5 item 11](RUNBOOK-logs.md)
- **Débito que vence aqui:** `LicencasAcessoPermissionTest:290` assere os links de `layouts/nav.blade.php` no HTML. Não quebra antes (a flag está OFF), quebra quando o Blade sair — a asserção migra pro `Resources/menus/topnav.php` neste PR
- **Portão:** não entra antes das 3 US de F4 verdes. Enquanto os Blades existem, eles são a rota de fuga

## Epic — Capacidades já entregues sem US (registradas em 2026-11-18)

> **Origem:** a auditoria do módulo de 2026-11-05 (eixo 3) achou código em `main` sem nenhuma US. As US abaixo registram o que existe e o que falta, com o código como âncora. Não são pedidos novos: são o contrato do que já foi feito. Itens marcados como lote L1/L2/L3 estavam em correção por outras sessões quando este SPEC foi atualizado.

### US-OI-018 — Tela `Clientes/Index` (clients OAuth do desktop)
**Como** operador, **quero** listar, criar e revogar os clients OAuth que o desktop usa para pegar token, **pra** liberar instalação nova sem mexer no banco.
**Implementado em:** _parcial_ · `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Clientes/Index.tsx` · `Modules/Officeimpresso/Http/Controllers/ClientController.php` — a tela nasceu em 2026-10-09 (#9081) atrás da flag `useV2OfficeimpressoClientes`, que não está nos padrões do `app/Services/FeatureFlagService.php`; sem ligar a flag, a Blade `Modules/Officeimpresso/Resources/views/clients/index.blade.php` serve. O `regenerate` por `GET` e as rotas `show`/`edit` que apontam para views inexistentes são do lote L0/L1.
**Testado em:** `Modules/Officeimpresso/Tests/Feature/ClientesIndexContratoTest.php`
**Aceite:**
- O segredo do client aparece uma vez, na criação (`ClientesSegredoUmaVezTest`)
- O id do client é inteiro, não UUID truncado (`OauthClientIdTest`)
- Liberar client exige a permissão delegada, e só na empresa operadora (`ClientesLiberarPermissionTest`)
- RUNBOOK: [RUNBOOK-clientes.md](RUNBOOK-clientes.md)

### US-OI-019 — Delegação da gestão de licenças ao suporte
**Como** dono do módulo, **quero** que o suporte opere licença sem ser superadmin, **pra** não concentrar o atendimento em uma pessoa.
**Implementado em:** `Modules/Officeimpresso/Services/AcessoOperador.php` · `Modules/Officeimpresso/Http/Controllers/DataController.php` — permissões `officeimpresso.access`, `officeimpresso.licencas.gerenciar`, `officeimpresso.empresa.gerenciar` e `officeimpresso.licencas.excluir`, delegadas por decisão [W] de 2026-07-30 e restritas à empresa operadora desde 2026-10-01 (#8388, #8389).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/LicencasAcessoPermissionTest.php`
**Aceite:**
- `[T0]` papel de empresa cliente com a permissão não opera licença de ninguém
- `superadmin` segue com acesso total
- **Pendente [W]:** a [ADR 0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) ("superadmin exclusivo") segue aceita e sem sucessora; a delegação vive só em código e no BRIEFING

### US-OI-020 — Comandos de operação do módulo
**Como** operador, **quero** comandos para checar a saúde do licenciamento, ler o log do Laravel, inspecionar a API do Delphi e alinhar pacotes, **pra** diagnosticar sem abrir o banco.
**Implementado em:** _parcial_ · `Modules/Officeimpresso/Console/Commands/OfficeimpressoHealthCommand.php` · `Modules/Officeimpresso/Console/ParseLicencaLogCommand.php` · `Modules/Officeimpresso/Console/InspectDelphiApiCommand.php` · `Modules/Officeimpresso/Console/Commands/AlinharPacotesCatalogoCommand.php` — registrados em `OfficeimpressoServiceProvider::boot`; nenhum está agendado. O `officeimpresso:health` consulta nomes de tabela no plural que não existem e por isso nunca passa de WARN (lote L1).
**Testado em:** `Modules/Officeimpresso/Tests/Feature/AlinharPacotesCatalogoCommandTest.php`
**Aceite:**
- `officeimpresso:health` lê `licenca_computador` e `licenca_log` e consegue reportar OK e FAIL
- `licenca-log:parse` é idempotente por hash de linha
- `officeimpresso:alinhar-pacotes-catalogo` tem `--dry-run` e não duplica

### US-OI-021 — Importação Firebird de um cliente (`officeimpresso:import`)
**Como** operador, **quero** importar o Firebird de um cliente para o business dele, **pra** migrar sem SQL à mão.
**Implementado em:** _pendente_ — o comando existe (`officeimpresso:import`, `ImportOfficeimpressoCommand` + `OfficeimpressoImporterService`) com `{biz}` obrigatório, `--dry-run` e Firebird só leitura, mas não cumpre o aceite: sem fonte real cai em modo mock e grava fixtures fora do `--dry-run`, e o contador soma insert que falhou (lote L2). Os testes dele estão fora de lane de PR. O caminho usado nas migrações de cliente é o Python de `scripts/legacy-migration/` ([ADR 0203](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)).
**Aceite:**
- `[T0]` grava só no `{biz}` informado
- Sem fonte real, não grava nada
- Segunda execução não duplica
- O teste está fora de lane de PR em 2026-11-18 (`test-lane-coverage --json`); entra no lote L4

### US-OI-022 — `POST /api/officeimpresso/audit` (evento opt-in do desktop)
**Como** dono do módulo, **quero** que o desktop possa mandar um evento próprio para o log, **pra** registrar erro do lado do cliente sem depender do log do servidor.
**Implementado em:** _pendente_ — a rota existe (`AuditController` + `LicencaAuditService`, sob `auth:api` + `log.desktop`, grava `source = desktop_audit` com PII redigida), mas não cumpre o aceite: o `event` vem livre do corpo, e o único teste em lane só confere o 401 sem token (lote L3).
**Aceite:**
- Só eventos de uma lista aceita; o resto é recusado
- O `source` é fixado pelo servidor
- Nenhuma versão do Delphi em campo usa este endpoint até 2026-11-18 (ADR 0021: geração nova não está em produção); ele é aditivo

## Referências

- [SDD do licenciamento Delphi](SDD-licenciamento-delphi-v1.0.md) — o fluxo ponta a ponta
- ADRs do tema: [0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) · [0018](../../decisions/0018-officeimpresso-log-acesso-passivo.md) · [0019](../../decisions/0019-officeimpresso-delphi-nao-autentica.md) · [0020](../../decisions/0020-officeimpresso-grupo-economico.md) · [0021](../../decisions/0021-officeimpresso-contrato-api-delphi.md) · [0113](../../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) · [0203](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)
- Processo de migração de tela: [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md)
- Skill `officeimpresso-source-analysis` — leitura do código fonte Delphi
- Skill `officeimpresso-financial-snapshot` — extração de receita do Firebird
- _Até 2026-08-20 este SPEC citava as ADRs 0136 e 0137 como sendo do tema; elas são de outros módulos (Sells e OficinaAuto). Também carregava a seção "N/A justificado" das rubricas 0153/0154, aposentadas pela [ADR 0399](../../decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md); a seção saiu em 2026-11-18._

## Testes fora da lane `officeimpresso-pest` (auditoria 2026-11-05, Lote 4)

Medidos na CI (run 44383147672): falham no MySQL da lane e por isso não entram na allowlist até o conserto. Nenhum foi desligado nem marcado como skip.

### US-OI-023 — Audit do desktop redige a metadata vinda do Delphi (LGPD D7.a)
**Como** dona do dado, **quero** que o que o desktop manda no audit não grave dado pessoal em claro, **pra** cumprir a LGPD também nos campos livres.
**Implementado em:** _pendente_
**Aceite:**
- `LicencaAuditService::registrar` passa só `error_message` pelo `PiiRedactor`; os campos desconhecidos do payload do Delphi vão para `metadata` sem redação
- `LgpdComplianceTest` "D7.a — AuditController redaciona metadata vindo do Delphi" falha por isso e o arquivo fica fora da lane até o conserto
- Pronto: `metadata` gravada em `licenca_log` sai redigida (teste de comportamento, não leitura do arquivo-fonte), `LgpdComplianceTest` verde e ligado na lane
- Conferir antes o #11157 (Lote 3), que mexe no mesmo endpoint

### US-OI-024 — `Wave28FirebirdImporterTest` alinhado ao contrato novo e ao ambiente da CI
**Como** dono do importador, **quero** o teste dele rodando na lane, **pra** o importador Firebird ter alguma cobertura em CI.
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: #11158 (Lote 2)
- O caso "isMock() retorna true quando ext pdo_firebird ausente (CI default)" falha: a CI tem a extensão. O teste força o modo (`forceMock`) ou lê `extension_loaded`, em vez de supor o ambiente
- O #11158 muda o contrato: stats com 5 chaves (read, migrated, skipped, failed, blocked); products/transactions/licenca_computador saem como `blocked`; `licencas.migrated` deixa de ser 1
- Pronto: arquivo verde na lane com as expectativas novas

