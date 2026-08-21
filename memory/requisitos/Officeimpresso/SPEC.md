---
id: requisitos-officeimpresso-spec
module: Officeimpresso
version: "1.2"
last_updated: "2026-08-20"
owner: wagner
status: ativo
related_adrs:
  - 0136-sells-grade-avancada-modo-toggle
  - 0137-modules-oficinaauto-qualificada
  - 0153-module-grade-rubrica-v1
  - 0154-module-grade-v2-na-justificado
na_justified:
  D3.b: "Officeimpresso é bridge legacy Delphi WR Sistemas → oimpresso Laravel. Não é módulo de produto novo, é ponte de migração. BRIEFING canônico vive no projeto principal (Connector + Officeimpresso são pareados). ADR 0136+0137 documentam migração legacy."
  D6.b: "Bridge legacy Firebird → Laravel via Connector REST. p99 OTel <500ms ainda não exportado (instrumentação pendente infra OTel project-wide). Performance é dominada pelo Firebird remoto do cliente — fora do controle do oimpresso. ADR 0136+0137."
  D8.b: "REVOGADO em 2026-08-19 — a justificativa dizia 'backend-only, sem views Blade legacy próprias, CSRF N/A'. É falso e era falso quando foi escrita: o módulo tem 18 arquivos .blade.php em Modules/Officeimpresso/Resources/views/ (contados com `git ls-files ':(glob)Modules/Officeimpresso/Resources/views/**/*.blade.php' | wc -l` — o `:(glob)` é obrigatório: sem ele o pathspec devolve 17, porque perde o `views/index.blade.php` que está na raiz da pasta), com layout próprio e rotas web sob o middleware 'web'. A parte verdadeira é que a bridge REST pro Delphi usa Passport; ela não cobre a superfície web, que existe e é justamente o alvo da migração React (Onda 1)."
---

<!-- schema-allowlist: até 2026-08-19 este SPEC era `arquivado` e sem seção US por design (bridge legacy a descomissionar). [W] priorizou a migração React do módulo em 2026-08-19, então ele voltou a `ativo` e ganhou a seção US da Onda 1. O racional de descomissionamento segue válido pro RESTO do módulo — a Onda 1 é trabalho de shell, não capacidade nova. -->

# SPEC — Modules/Officeimpresso

## Visão

Bridge legacy entre o ERP Delphi histórico **WR Comercial / WR Sistemas (OfficeImpresso)** e o oimpresso Laravel moderno. Não é módulo de produto novo — é **ponte de migração** que expõe dados Firebird legacy via endpoints autenticados para o app Laravel consumir durante a transição de clientes gráficos.

## Arquitetura atual

- 7 Controllers expondo recursos do legacy (clientes, vendas, NFe, financeiro, licenças, etc) pra polling/sync
- 2 entidades Eloquent próprias: `Licenca_Computador` + `LicencaLog` (controle de licenças WR Comercial Desktop)
- 3 tests Pest cobertura básica (Wave A 2026-05-12 — smoke endpoints + isolation business_id + license guard)
- Pareado com **`Modules/Connector`** — Connector consome Officeimpresso pra migrar cliente legacy → Laravel passo a passo
- Schema bridge consultado em `OFFICEIMPRESSO-FIREBIRD-SCHEMA.md` (skill `officeimpresso-source-analysis`)

## Roadmap

- Migração efetiva por cliente é decidida no projeto principal (Wagner approva por business_id elegível)
- Módulo **será descomissionado** quando o último cliente Delphi sair do legacy (sem ETA — depende de sinal qualificado por cliente, ADR 0105)

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
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: US-OI-002, US-OI-003
- PT-01 (Header, Toolbar, Table, EmptyState); `StatusBadge kind="licenca"` novo; sem cor crua
- 1280px com sidebar aberta sem scroll horizontal na página

### US-OI-005 — F3: tela `Logs/Timeline` (acessos por máquina) em PT-07
**Como** suporte, **quero** o histórico de acesso de uma máquina, **pra** diagnosticar bloqueio de cliente.
**Implementado em:** _pendente_
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
**Implementado em:** _pendente_
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
> | 4 | `licenca_computador/computadores.blade.php` | `/officeimpresso/computadores` | `Officeimpresso/Empresa/Show` | PT-03 Detalhe | `RUNBOOK-empresa.md` (chega no PR da tela #4) |
> | 5 | `licenca_computador/businessall.blade.php` | `/officeimpresso/businessall` | `Officeimpresso/Empresas/Index` | PT-01 Lista | `RUNBOOK-empresas.md` (chega no PR da tela #5) |
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
**Implementado em:** _pendente_
**Aceite:**
- Pest baseline no CT 100, tenant 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)): 403 sem permissão, 200 com `officeimpresso.access`, os 3 KPIs e — a fixture que só o teste pega — **máquina de outro `business_id` não aparece na lista**
- `buildLicencasPayload()` devolvendo DTO de 7 campos (`id, hd, user_win, processador, memoria, versao_exe, bloqueado`). **Nunca o model:** `Licenca_Computador` não tem `$hidden` e o `$fillable` lista `senha`, `contra_senha`, `serial` e `token`
- Flag `useV2OfficeimpressoLicencas` default OFF via `FeatureFlagService`/GrowthBook. **Não** há comando `enable-v2` — esse padrão não existe no projeto (`git grep -lE 'enable-v2|enableV2' -- '*.php'` = zero)
- **Só a flag decide** o caminho dual — condicionar ao header `X-Inertia` quebraria o first load, que não manda esse header (medido na Onda 1)
- O `Inertia::render` **não** entra aqui: render apontando pra page inexistente é 500 esperando a flag ligar, e o `OrphanRenderGateTest` (required) reprova. Ele viaja com a tela, na US-OI-009
- `licencas-parity.md` completo (29 itens, 9 de severidade `alta`) — já entregue na F1

### US-OI-009 — F3: tela `Licencas/Index` (Computadores Cadastrados) em PT-01
**Como** suporte, **quero** a lista de máquinas licenciadas no shell React, **pra** operar licença sem sair do cockpit.
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: US-OI-008
- PT-01 (Header, Toolbar, Table, EmptyState); `StatusBadge kind="licenca"`; sem cor crua
- Props caras (`licencas`, `kpis`) em `Inertia::defer`; `permissions` eager
- Traz o `Inertia::render` + o dual da action, no mesmo PR do `.tsx`
- 1280px com sidebar aberta sem scroll horizontal na página

### US-OI-010 — F4: QA da `Licencas/Index` — os itens `alta` viram teste
**Como** revisor, **quero** que cada item de severidade `alta` quebre um teste se sumir, **pra** que a paridade seja enforcement de comportamento, não papel.
**Implementado em:** _pendente_
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
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: **decisão [W] sobre o escopo do `viewLicencas`** (D1 do `empresa-parity.md`) — ela precede a F2 porque define o que a fixture assere
- Pest baseline no CT 100, tenant 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)): 403 sem permissão e 200 com `access` (já cobertos), os dois no-leak de escrita (já cobertos), a ficha abrindo **sem** `package` e **sem** `active` (os dois blocos são condicionais), e o escopo das **duas** rotas
- `buildEmpresaPayload()` (10 campos) + `buildComputadoresPayload()` (9 campos) devolvendo DTO. **Nunca os models** — `Licenca_Computador` não tem `$hidden` e o `$fillable` lista `senha`, `contra_senha`, `serial` e `token`; `Business` é um model gordo do ERP inteiro
- Flag `useV2OfficeimpressoEmpresa` default OFF, valendo para as **duas** rotas — ligar só uma faz o suporte trocar de shell ao clicar "Ver computadores desta empresa" no `businessall`
- **Só a flag decide** o dual; o `Inertia::render` viaja com a tela (US-OI-012), não aqui
- `empresa-parity.md` completo (45 itens, 23 de severidade `alta`) — já entregue na F1

### US-OI-012 — F3: tela `Empresa/Show` (ficha + computadores) em PT-03
**Como** suporte, **quero** a ficha da empresa licenciada no shell React, **pra** bloquear cliente, ajustar versão obrigatória e chegar no log sem sair do cockpit.
**Implementado em:** _pendente_
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
**Implementado em:** _pendente_
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
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: **decisão [W] sobre as 4 colunas mortas** (D1 do `empresas-parity.md`) — precede a F2 porque define o payload e o número de colunas, e o número de colunas decide se a tela cabe a 1280
- Pest baseline no CT 100, tenant 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)): 403 sem permissão e 200 com `access` (já cobertos), **empresa sem `is_officeimpresso` não aparece** (a fixture que trava a regra da tela), e os 3 KPIs
- `buildEmpresasPayload()` devolvendo DTO. **Nunca o model `Business`** — ele carrega o cadastro inteiro do ERP, e como esta lista é **global**, mandar o model cru publicaria o cadastro completo de todas as empresas no `data-page` do HTML
- Flag `useV2OfficeimpressoEmpresas` default OFF; **só a flag decide** o dual; o `Inertia::render` viaja com a tela (US-OI-015)
- `empresas-parity.md` completo (28 itens, 11 de severidade `alta`) — já entregue na F1

### US-OI-015 — F3: tela `Empresas/Index` (Empresas Licenciadas) em PT-01
**Como** suporte, **quero** a lista de empresas licenciadas no shell React, **pra** chegar na ficha e no log de qualquer cliente sem sair do cockpit.
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: US-OI-014
- PT-01 (Header, Toolbar, Table, EmptyState); `StatusBadge kind="empresa"` — o **mesmo** `kind` da tela #4
- Os dois botões de ação ganham `aria-label` — hoje são ícone sem rótulo, com o texto só no `title`
- Props caras (`empresas`, `kpis`) em `Inertia::defer`
- Traz o `Inertia::render` + o dual da action, no mesmo PR do `.tsx`
- 1280px com sidebar aberta sem scroll horizontal — é a tela com **mais colunas** do módulo (11), e a decisão D1 pode reduzi-la a 7

### US-OI-016 — F4: QA da `Empresas/Index` — os itens `alta` viram teste
**Como** revisor, **quero** que cada item de severidade `alta` quebre um teste se sumir, **pra** que a paridade seja enforcement de comportamento, não papel.
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: US-OI-015
- Os **9 itens `alta` ainda sem teste** do `empresas-parity.md` com teste citando o id do UC. Os outros 2 (24, 25) já são cobertos por `LicencasAcessoPermissionTest`
- Inclui o **assert negativo** do item 28 (o model `Business` cru não vai na prop)
- **Presença do `-parity.md` não conta**; smoke com screenshot 1280 + 1440

### US-OI-017 — F5: cutover das 3 telas da Onda 2 e sunset dos Blades
**Como** dono do módulo, **quero** os Blades removidos depois da prova, **pra** não manter dois caminhos.
**Implementado em:** _pendente_
**Aceite:**
- `blocked_by`: US-OI-010, US-OI-013, US-OI-016
- **Uma US só pras três telas:** as rotas se linkam entre si (a lista leva à ficha, as duas levam ao log), então flag ligada em metade da onda faz o suporte trocar de shell no meio da navegação
- Módulo interno (WR2 + suporte), sem cliente externo — não precisa de janela de aviso; flag ON, período de observação, então remove os 3 Blades e as 3 flags
- **É aqui que as 3 telas entram no gate visual** — antes é impossível: com a flag OFF a rota devolve o Blade, o `assertInertia(component: ...)` não acha nada e o `visual-regression` (required) fica vermelho pro repo inteiro. Os 4 pré-requisitos estão em [RUNBOOK-logs §F5 item 11](RUNBOOK-logs.md)
- **Débito que vence aqui:** `LicencasAcessoPermissionTest:290` assere os links de `layouts/nav.blade.php` no HTML. Não quebra antes (a flag está OFF), quebra quando o Blade sair — a asserção migra pro `Resources/menus/topnav.php` neste PR
- **Portão:** não entra antes das 3 US de F4 verdes. Enquanto os Blades existem, eles são a rota de fuga

## N/A justificado

- **D3.b BRIEFING.md** — Briefing canônico de capacidade vive no projeto principal (não dentro do módulo bridge). Officeimpresso é meio, não fim. ADR 0136 + 0137 documentam estratégia de migração legacy e substituem BRIEFING.md tradicional pra este módulo.

## Referências

- ADR 0136 — Officeimpresso bridge legacy WR Comercial
- ADR 0137 — Connector + Officeimpresso pareados pra migração
- ADR 0153 — Module grade rubric v1
- ADR 0154 — Module grade v2 N/A justificado
- Skill `officeimpresso-source-analysis` — leitura código fonte Delphi
- Skill `officeimpresso-financial-snapshot` — extração receita Firebird
