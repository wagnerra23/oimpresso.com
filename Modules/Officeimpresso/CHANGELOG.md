# Officeimpresso — Changelog

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
- **Translations não resolviam** (user_win, processador, memoria…) — causa: `Modules/Officeimpresso1/` no servidor era backup 3.7 com mesmo `name: Officeimpresso` no `module.json` causando conflito de namespace nwidart. Movido pra `~/Officeimpresso1-3.7-BACKUP/`

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
