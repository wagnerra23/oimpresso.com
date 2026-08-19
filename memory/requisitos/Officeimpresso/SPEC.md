---
id: requisitos-officeimpresso-spec
module: Officeimpresso
version: "1.1"
last_updated: "2026-08-19"
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
**Implementado em:** _parcial_ · `Modules/Officeimpresso/Http/Controllers/LicencaLogController.php` — a flag e o `Inertia::render` da **lista** entraram com a tela dela; o render da **timeline** entra no PR da timeline.
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
**Implementado em:** _parcial_ · `memory/requisitos/Officeimpresso/logs-parity.md` — o mapa está completo (54 itens, 21 de severidade `alta`), mas a US só fecha quando os 21 tiverem teste de comportamento: hoje são **10 defendidos e 11 em aberto** (a lista nominal está no fim do mapa). O template é explícito: *"um `-parity.md` sem teste pros itens `alta` é débito, não conclusão"* — fechar os 11 é a US-OI-006.
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
**Implementado em:** _pendente_
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

## N/A justificado

- **D3.b BRIEFING.md** — Briefing canônico de capacidade vive no projeto principal (não dentro do módulo bridge). Officeimpresso é meio, não fim. ADR 0136 + 0137 documentam estratégia de migração legacy e substituem BRIEFING.md tradicional pra este módulo.

## Referências

- ADR 0136 — Officeimpresso bridge legacy WR Comercial
- ADR 0137 — Connector + Officeimpresso pareados pra migração
- ADR 0153 — Module grade rubric v1
- ADR 0154 — Module grade v2 N/A justificado
- Skill `officeimpresso-source-analysis` — leitura código fonte Delphi
- Skill `officeimpresso-financial-snapshot` — extração receita Firebird
