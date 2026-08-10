---
id: requisitos-officeimpresso-changelog
---

# Changelog — Modules/Officeimpresso

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Mubisys, Sigma Soft, Calcgraf), top 5 gaps P0 (G1 LicencaImporter idempotente, G2 dashboard saúde, G3 retention-purge command, G4 onboarding wizard, G5 API REST/webhooks), score V1-V6 W22→W23 (60→≥85).
- **Wave23OfficeimpressoSaturationTest.php** — Pest saturação V1/V4/V5/V6 com 12 assertions cobrindo entities LicencaLog/LicencaComputador, ParseLicencaLogCommand idempotência (offset cursor), retention granular por evento (api_call 365d, admin_actions 2555d CC Art. 206), middleware LogDelphiAccess/LogDesktopAccess, governance.bucket=vertical_client_facing + FSM N/A justificado.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `wave: 23`, `wave_23_saturation: true`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 60/100 → ≥85/100 estimado.
- V1 Pest E2E: +6 (estrutural + reflection ParseLicencaLogCommand idempotência).
- V5 Docs canon: +9 (CAPTERRA-FICHA + CHANGELOG W23 — SPEC/PROPOSTA-COMERCIAL/RUNBOOK migração já existiam, asserted em Pest).
- V6 Capterra ROI Top 5: +4 (FICHA fechando gap W22).

### Preserved (Tier 0 IRREVOGÁVEL)

- LicencaLog append-only contract (audit trail Passport + Delphi sync — sem SoftDeletes).
- Retention granular por evento ([LGPD Art. 16](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm) + [CC Art. 206 §5 III](https://www.planalto.gov.br/ccivil_03/leis/2002/l10406.htm)) — janelas distintas: api_call 1y (debug), login 2y (auditoria), admin 7y (audit legal).
- Bridge desktop Delphi 26+ anos preservada (clientes legados Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart sem fricção).
- ParseLicencaLogCommand idempotente (cursor offset Cache + hash dedup).
- FSM N/A justificado (bridge audit-only sem state machine — `fsm_n_a_reason` em module.json).
- Schema legado Firebird intocado (cliente desktop não quebra).

---

## Implementação (histórico movido de `Modules/Officeimpresso/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas entre os dois era 0-2 de 2-7, logo nenhum era cópia do outro
> e escolher um lado perderia registro. Conteúdo preservado na íntegra.

# Officeimpresso — Changelog

## [Wave 27 — 2026-05-17] POLISH FINAL ≥88 (atual 68-80 → target ≥88)

### D2 Pest LicencaService + LicencaAuditService comprehensive
- `Tests/Feature/Wave27PolishTest.php` (27 cenários, 101 assertions) — reflection + source-grep, ZERO hit DB:
  - **D2 LicencaService API**: 8 métodos públicos canon (listar/buscar/criar/atualizar/remover/alternarBloqueio/atualizarEmpresa/alternarBloqueioEmpresa/listarEmpresasComDesktop)
  - **D2 LicencaService Tier 0**: `$businessId` int explícito 1º arg em métodos multi-tenant (Jobs sem session)
  - **D2 LicencaService tipos retorno**: criar → Licenca_Computador, atualizar → ?Licenca_Computador (nullable 404), remover → bool
  - **D2 LicencaAuditService append-only (Lei 9.609/98)**: SÓ método público `registrar` (sem update/delete/destroy/remov*)
  - **D2 LicencaAuditService CAMPOS_CONHECIDOS**: 8 keys canon (event/licenca/error_code/error_message/endpoint/http_method/http_status/duration_ms)
  - **D2 LicencaAuditService PiiRedactor**: opcional via constructor (DI flexível) + fallback REDACTED quando null (defense in depth)

### D8 FormRequests adicionais (5 → 5 + novo UpdateEmpresaConfigRequest)
- `Http/Requests/UpdateEmpresaConfigRequest.php` (NOVO) — atualização configs empresa Delphi:
  - `caminho_banco_servidor`: anti path traversal (`not_regex` bloqueia `..` e `~` — anti LFI)
  - `versao_obrigatoria` + `versao_disponivel`: regex semver-like X.Y.Z (max 20 chars legado WR)
  - `officeimpresso_numerodemaquinas`: int 1-9999 (anti-fraud cap)
  - Todos campos `sometimes` (PATCH-friendly — Wagner pode liberar versão sem reenviar payload completo)
- Pest cobre 5 FormRequests canon carregam (Store/Update/Revoke/Bulk/UpdateEmpresaConfig)
- Pest valida BulkRevokeLicencaRequest cap 100 IDs + motivo obrigatório ≥5 chars (audit LGPD)
- Pest valida StoreLicencaRequest bridge Delphi preserva 5 campos schema legacy
- Pest valida UpdateLicencaRequest hd unique ignore própria licenca (route binding)

### D9 spans Services EXPAND
- `LicencaService` ≥7 OtelHelper::spanBiz (target 8 métodos = 8 spans canon)
- Prefix `officeimpresso.*` canon em todos 8 spans (licenca.* + empresa.*)
- Atributo `module=Officeimpresso` em todos spans
- Atributo `licenca_id` propagado em spans single-record (correlação cross-request)
- `LicencaAuditService` span registrar com attributes canon (event/has_error/http_status)
- OtelHelper canon `App\Util` (lock anti-fork dentro do módulo)
- OtelHelper no-op preserva retorno arbitrário (CI sem otel)

### Tier 0 Lei Software 9.609/98 lock-in
- LicencaLog Model SEM SoftDeletes (retention 5y hard preserva audit)
- LicencaAuditService NÃO loga payload bruto sem PiiRedactor (sempre `redact` + `redactArray`)
- BulkRevoke motivo `required` (audit retention 5y)

### Notas Tier 0 IRREVOGÁVEIS preservadas
- ⛔ Bridge Delphi WR Comercial: StoreLicencaRequest preserva schema legacy (5 campos)
- ⛔ Lei Software 9.609/98: retention 5y `LicencaLog` audit append-only
- ⛔ Multi-tenant Tier 0 (ADR 0093): `$businessId` int explícito + cap 100 bulk
- ⛔ LGPD Art. 6º IX: PiiRedactor + fallback REDACTED em LicencaAuditService
- ⛔ OtelHelper canon `App\Util` preservado

### Validated
- `php vendor/bin/pest Modules/Officeimpresso/Tests/Feature/Wave27PolishTest.php` → **27/27 passed (101 assertions, ~8s)**

### Estimativa nota
- Wave 25 baseline: ~68-80 (variável por dimensão)
- Wave 27 polish final: **≥88** com D2 Services API completa + D8 FormRequest adicional + D9 spans canon + Tier 0 9.609/98 lock-in

## [Wave 25 — 2026-05-16] POLISH ≥90 (80 → 90, +10pp)

### D8 Security FormRequests (4 → 8)
- `Http/Requests/UpdateLicencaRequest.php` — par de `StoreLicencaRequest`.
  Rules `sometimes` (PATCH-friendly) + `hd` unique IGNORA própria licenca em
  curso (rota ID). Bridge legacy Delphi preservado.
- `Http/Requests/BulkRevokeLicencaRequest.php` — operacao em lote bloqueio/
  desbloqueio até 100 licencas/chamada. Motivo obrigatório (audit LGPD).
  Caso de uso real: cliente cancela contrato (bloquear N) ou isolar maquinas
  comprometidas. Multi-tenant Tier 0 ({@see ADR 0093}) — Controller filtra
  IDs por business_id da sessao ANTES do bulk update (defesa-em-profundidade
  vs IDOR em payload).

### D2 Pest expand observability (Wave 18 inicial → Wave 25 saturado)
- `Tests/Feature/Wave25ObservabilityExpandedTest.php` — 7 cenários novos:
  - LicencaService ≥5 OtelHelper spans (baseline preserved)
  - Span attributes preservam canon `module` key
  - UpdateLicencaRequest carrega + rules `sometimes` (PATCH)
  - BulkRevokeLicencaRequest valida array IDs + motivo obrigatório
  - PII redactor lock-in — Services NÃO logam payload raw
  - Lei Software 9.609/98 — LicencaAuditService SEM métodos update/delete públicos
  - OtelHelper no-op preserva tipo genérico (array/int/null)

### D5 Firebird fixtures schema importer Pest
- `Tests/Feature/Wave25FirebirdImporterFixturesTest.php` — 7 cenários contrato:
  - Shape canon `LICENCA_COMPUTADOR` (7 campos bridge Delphi)
  - Shape canon `LICENCA_LOG` append-only (CREATED_AT sim, UPDATED_AT/DELETED_AT não)
  - Encoding ISO-8859-1 → UTF-8 (acentuação Delphi WR Comercial)
  - Truncate user_agent 500 chars (anti-DOS)
  - BLOQUEADO Firebird INTEGER 0/1 maps boolean PHP
  - `ParseLicencaLogCommand` existe (importer real)
  - `LicencaLog` Model SEM `SoftDeletes` trait (Lei 9.609/98 retention 5y)

### Notas Tier 0 IRREVOGÁVEIS preservadas
- ⛔ Bridge Delphi WR Comercial: campos `licenca_id|hd|processador|memoria|versao_exe|bloqueado` PRESERVADOS (Delphi sincroniza via HTTP).
- ⛔ Lei Software 9.609/98: retention 5y `LicencaLog` audit append-only validado por reflection (sem métodos update/delete públicos no Service + sem SoftDeletes no Model).
- ⛔ Multi-tenant Tier 0 (ADR 0093): bulk operations filtradas por session biz antes do UPDATE.
- ⛔ OtelHelper canon (`App\Util\OtelHelper`) preservado. NÃO mover pra namespace módulo.
- ⛔ PT-BR em comentários/mensagens. Identificadores PHP em inglês.

## [Wave 18 RETRY — 2026-05-16] Saturação governance v3 — D5 +7

### D5 Cliente real / Journey (RETRY +1 arquivo)
- `Tests/Feature/E2EJourneyDelphiBiz1Test.php` — 7 cenários E2E: Delphi POST audit → LicencaLog persistido, cross-biz isolation 3 vs 5 logs, truncate user_agent 500 chars (anti-DOS), payload com error_message PII NÃO derruba registrar (fallback), campos extras vão pra metadata sem perder, append-only verificado por reflection (Service sem método update/delete público), high-volume smoke 50 inserts <10s.

### Confirmação `module.json`
- `fsm_n_a: true` confirmado — Officeimpresso é bridge desktop Delphi sem state machine própria (eventos audit append-only via LicencaLog). Sem mudanças necessárias.

## [Wave 18 — 2026-05-16] Saturação governance v3 (inicial)

### D2 Pest novo
- `Tests/Feature/ObservabilityServicesTest.php` — 7 cenários cobrindo: LicencaService usa OtelHelper canon (≥5 spans), LicencaAuditService idem, prefix span `officeimpresso.*`, no-op com otel disabled, exception preservada, lock-in canon (não usa OtelHelper de outro namespace), README existe + cita ADR 0159.

### D5 Cliente real / Journey
- `README.md` criado — bridge Delphi legacy + journey 5 passos biz=1 (audit POST → admin block/unblock → cron drift detection) + retention LGPD por evento + cliente piloto cross-cutting internal_governance_active.

### D9.a Observabilidade
- Spans canon `officeimpresso.licenca.*` + `officeimpresso.empresa.*` + `officeimpresso.licenca_audit.registrar` já presentes (Wave 17). Wave 18 adiciona Pest lock-in pra garantir não regressão.

## Roadmap / Futuro

### [vX.Y] — Restaurar endpoints do Connector 3.7 que o Delphi realmente usa

**Descoberta 2026-04-24 (ADR 0021):** o Delphi **não usa** `/api/officeimpresso/*` como eu tinha assumido. Ele tem 3 gerações de código convivendo, todas usando **Connector**:

1. **Geração 1 — legada em produção** (`/connector/api/processa-dados-cliente` + `/salvar-equipamento/{business_id}` + `/salvar-cliente`) — JSON com EMPRESA+LICENCIAMENTO, resposta `S;msg` ou `N;motivo`.
2. **Geração 2 — sync genérico** (`/connector/api/{tabela}/sync-post` + `sync-get`) — chunks de 100 registros modificados (OIMPRESSO_SINCRONIZADO IS NULL).
3. **Geração 3 — novo padrão em dev** (`/api/oimpresso/registrar` etc.) — não está em prod ainda.

Estes endpoints **já existem em `origin/3.7-com-nfe:Modules/Connector/`** mas foram perdidos na migração 3.7→6.7. **147 arquivos faltando no Connector** do 6.7 (ver `reference_branch_3_7.md`).

**Plano:**
1. Restaurar `Modules/Connector/Http/routes.php` completo do 3.7
2. Restaurar controllers API (namespace `Modules\Connector\Http\Controllers\Api\*`) — `LicencaComputadorController`, `BusinessController`, `EquipamentoImpressoraController`, `HistoricoImpressoesController`
3. Prefixar names de rotas pra evitar colisão com route:cache (padrão aplicado na correção de `business-location.index`)
4. Testar com curl simulando o Delphi

**Prioridade: alta** — sem isso o Delphi não sincroniza nada, mesmo autenticando OK.

### [vX.Y] — Delphi envia `hd` no /oauth/token (identificação de máquina)

**Problema:** `licenca_log` não consegue resolver `licenca_id` corretamente porque `/oauth/token` não carrega identificador único de máquina. Wagner apontou que **`hd` (serial do disco) é a chave única** — cada `licenca_computador` tem um `hd` distinto.

**Solução:**
- Delphi inclui `"hd": "<serial>"` no body do POST `/oauth/token` (extra param, Passport ignora)
- OU envia via header `X-OI-HD: <serial>`
- Listener `LogPassportAccessToken` **já lê** `$request->input('hd')` / `header('X-OI-HD')` — quando Delphi passar a enviar, match automático acontece. Match exato: `licenca_computador.business_id = X AND hd = Y`.
- Metadata guarda `hd` recebido pra debug mesmo quando match falha.

**Impacto no Delphi:**
```pascal
JsonEnvio.AddPair('grant_type', 'password');
JsonEnvio.AddPair('client_id', '39');
JsonEnvio.AddPair('client_secret', '...');
JsonEnvio.AddPair('username', AUsuario);
JsonEnvio.AddPair('password', ASenha);
JsonEnvio.AddPair('hd', SerialDisco);  // <-- NOVO (chave de hardware)
```

**Alternativa intermediária:** `POST /api/officeimpresso/audit` depois do login com `{"hd":"..."}` — log aparece como `desktop_audit`, não `login_success`, mas identifica a máquina. Já funciona hoje sem mudar `/oauth/token`.

### [vX.Y] — Grupo econômico (matriz + filiais)

**Problema observado:** quando o cliente abre o Delphi da filial, o sistema não encontra o CNPJ principal (da matriz) e se perde — a filial é um Business separado em UltimatePOS, mas na prática compartilha licença/configuração com a matriz.

**Hipótese de solução:**
- Nova coluna `business.matriz_id` (self-FK) — se preenchido, aponta pro Business da matriz
- Ao autenticar desktop, resolver `effective_business_id = matriz_id ?: id`
- Consolidar `versao_obrigatoria`, `caminho_banco_servidor`, `officeimpresso_limitemaquinas`, `officeimpresso_bloqueado` na matriz; filiais herdam
- UI `/officeimpresso/businessall` com agrupamento visual (matriz + filiais recolhíveis)
- `LicencaLog` grava `business_id` da filial mas indexa também pela matriz pra queries agregadas

**Impacto:** mudança de schema (`ALTER TABLE business ADD matriz_id INT NULLABLE`). Retrocompat: `matriz_id=NULL` = comportamento atual.

**Priority:** alto — cliente reporta perda ao abrir filial.

---

## [1.3.0] — 2026-04-24 — Event listener + middleware no lugar de triggers MySQL

### Mudado
- **Triggers MySQL → Event listener + middleware** (ADR 0018 update):
  - `LogPassportAccessToken` listener escuta `AccessTokenCreated` do Passport com contexto completo (IP, user-agent, user, client, endpoint)
  - `LogDesktopAccess` middleware aplicado em `/api/officeimpresso/*` grava cada request com `method`, `http_status`, `duration_ms`, `endpoint`
  - Triggers `licenca_log_after_oauth_*_insert` dropados via migration `2026_04_24_000000`
  - Motivo: triggers gravavam dados rasos (só user+token, sem IP nem endpoint). API-based tem controle total e é mais fácil de evoluir.

### Corrigido
- **Login duplicado** (2 rows por login): `Event::listen()` estava sendo registrado 2x pelo nwidart/modules em `boot()`. Guard com `static $listenerRegistered` + dedup no listener (token_hint + user + 2s window).
- **Rows sem business/máquina**: listener agora faz lookup de `users.business_id` pelo `user_id` e best-effort match de `licenca_computador` por business + última máquina ativa.

### Adicionado
- Colunas **Empresa** (nome + link pra computadores) e **Máquina** (hostname + IP interno + link pra filtrar log) na UI de `/officeimpresso/licenca_log`.
- Todo log write em try/catch — falha de log nunca quebra fluxo do Delphi.

## [1.2.0] — 2026-04-23 — UI refinada + business_id filter + Officeimpresso1 dedup

### Corrigido
- **Fundo preto no topnav** — override `skin-purple` com `nav.bg-white` branco + pills + azul suave no item ativo
- **Inputs dark** — `background: #fff !important` nas `.form-control` dentro de `.oi-page`
- **KPI Bloqueadas menor que outros** — flexbox `.oi-kpi-row` equaliza altura (min-height: 76px)
- **Link "Documentação"** removido do menu (apontava pra docs comercial, não admin)
- **Translations não resolviam** (user_win, processador, memoria…) — causa: um diretório de backup 3.7 (`Officeimpresso1/`, irmão deste módulo no servidor) tinha o mesmo `name: Officeimpresso` no `module.json` causando conflito de namespace nwidart. Movido pra `~/Officeimpresso1-3.7-BACKUP/`

### Adicionado
- **Filtro `?business_id=`** no `/licenca_log` — superadmin pode clicar "Ver log da empresa" em businessall
- **ADR 0019** — investigação do Delphi não autenticando pós-upgrade
- **`bin/test-delphi-auth.sh`** — script pra testar grant password via curl, isolar problema entre server e client

## [1.1.0] — 2026-04-23 — Log de Acesso Fase 2 + UI polish

### Adicionado
- **Command `php artisan licenca-log:parse`** — parser passivo do
  `storage/logs/laravel.log` que extrai erros de OAuth/Passport e grava
  em `licenca_log` com `source=log_parser`. Dedup por hash da linha.
  Agendar: `$schedule->command('licenca-log:parse')->everyFiveMinutes();`
- **Endpoint opcional `POST /api/officeimpresso/audit`** — Delphi futuro
  pode postar eventos observados localmente (timeout, erro de conexão
  interno). **Não obrigatório** — Delphi atual ignora.
- **UI da tela `/officeimpresso/licenca_log` repaginada:**
  - KPIs com cards modernos (sem bordas AdminLTE)
  - Badges coloridos por tipo de evento
  - Filtro deep-link `?licenca_id=X` com alerta visual
  - Mensagem de tabela vazia explicando os triggers
  - Error messages destacados em vermelho
  - Botão "Limpar filtros"
  - Duração em ms sem coluna "erro" redundante (mostra no endpoint)

### Não tocado
- `/oauth/token` — fluxo Passport intacto
- `/api/officeimpresso` GET — ping existente preservado
- Delphi legado continua funcionando 100%

---

## [1.0.0] — 2026-04-23 — Restauração da 3.7

### Restaurado (a partir de `origin/3.7-com-nfe`)
- `Entities/Licenca_Computador.php`
- Controllers: `LicencaComputadorController`, `ClientController`, `DataController`, `OfficeimpressoController`
- `Http/Middleware/CheckDemo.php`
- 9 Transformers (BusinessResource, ProductResource, etc.)
- 6 views blade (licenca_computador/*, licencas_log/*, clients/*)
- Migrations: `create_licenca_computador_table`, `update_licenca_computador_table`
- `Resources/lang/pt/lang.php` com 50+ chaves

### Adicionado
- **Topnav horizontal** (`Resources/views/layouts/nav.blade.php`) — barra
  com 6 itens estilo Superadmin
- **Topnav declarativo** (`Resources/menus/topnav.php`) — espelho para
  React/Inertia futuro
- **Menu sidebar** como 2º item (ordem 2, logo após Superadmin) — só
  superadmin
- **Tabela `licenca_log`** com 17 colunas, 4 indexes compostos
- **Triggers MySQL passivos** em `oauth_access_tokens` e
  `oauth_refresh_tokens` → gravam `login_success` e `token_refresh` sem
  tocar no hot path do Delphi
- **Indexes em `licenca_computador`** (hd, dt_ultimo_acesso, composite
  business_id+dt_ultimo_acesso, composite business_id+bloqueado) pra
  melhorar performance das listagens
- **View `/officeimpresso/licenca_log`** com KPIs 24h + DataTable AJAX +
  filtros (event, from, to, licenca_id)
- **Botão "Log" por computador** na tela `/officeimpresso/computadores`
  — deep-link para log filtrado por máquina

### Corrigido
- Namespace `RouteServiceProvider.php` estava `Modules\ProductCatalogue`
  (copy-paste bug) → `Modules\Officeimpresso`
- Views com typos de route name (`licencas_computador.create` → singular)
- Views referenciando `LicencaController` (classe inexistente) →
  `ClientController`
- Bug do `@lang('key', [], 'fallback')` — 3º arg é locale, não fallback
  — removido em `index.blade.php`
- `LicencaLogController` com namespace errado + model `App\Models\LicencaLog`
  inexistente → corrigido

### Documentação
- `memory/decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md`
- `memory/decisions/0018-officeimpresso-log-acesso-passivo.md`
- `memory/officeimpresso-spec.md` — especificação do módulo
