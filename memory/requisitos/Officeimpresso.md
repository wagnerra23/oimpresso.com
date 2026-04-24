---
module: Officeimpresso
alias: officeimpresso
status: ativo
migration_target: blade-polido (react futuro)
migration_priority: alta
risk: baixo
areas: [Licenciamento, Auditoria, OAuth]
last_generated: 2026-04-24
version: 1.3.0
scale:
  routes: 22
  controllers: 6
  views: 9
  entities: 2
  permissions: 1
---

# Requisitos funcionais — Officeimpresso

> **Módulo interno WR2** pra gerenciar licenças de desktop Delphi dos clientes
> finais. Exclusivo superadmin — ferramenta da equipe WR2, não do business owner.
>
> API consumida pelo Delphi **não pode mudar** — cliente legado em produção.

## 1. Objetivo

Autenticar e autorizar cada instalação Windows específica do software Delphi
da WR2, controlar o que cada cliente contratou (máquinas, versão, validade),
e auditar cada acesso (quem logou, quando, de onde, sucesso/erro, se estava
bloqueado naquele momento).

## 2. Áreas funcionais

### 2.1. Licenciamento de máquinas

**Controller(s):** `LicencaComputadorController`
**Entity:** `Licenca_Computador` (tabela `licenca_computador`)
**Ações:** `index`, `computadores`, `viewLicencas($id)`, `businessall`,
`create`, `edit`, `store`, `update`, `destroy`, `toggleBlock`,
`businessupdate`, `businessbloqueado`

Cada máquina é identificada por `hd` (serial do disco). Cada `Licenca_Computador`
pertence a um `business_id`. Bloqueio em 2 níveis:
- **`business.officeimpresso_bloqueado = 1`** — corta TODAS as máquinas do grupo
- **`licenca_computador.bloqueado = 1`** — corta apenas aquela máquina

### 2.2. OAuth clients (Passport)

**Controller:** `ClientController`
**Ações:** `index`, `store`, `destroy`, `regenerate`

CRUD de credenciais OAuth (client_id + secret) que o Delphi usa pra autenticar
via password grant. Secrets são **hashed via Eloquent cast** (Passport v12+).

### 2.3. Audit log

**Controller:** `LicencaLogController`
**Entity:** `LicencaLog` (tabela `licenca_log`, append-only)
**Ações:** `index` (UI + KPIs 24h), `show` (JSON completo)

Populada **passivamente** por:
- `LogPassportAccessToken` listener (evento `AccessTokenCreated`) → `login_success`
- `LogDesktopAccess` middleware (rotas `/api/officeimpresso/*`) → `api_call`
- `ParseLicencaLogCommand` (schedule 5min) → `login_error` do laravel.log
- `AuditController::store` endpoint opt-in → `desktop_audit`

**Regra**: só loga quando Delphi enviar `hd` (serial). Sem hd = sem registro.

### 2.4. Catálogo QR (legado)

**Controller:** `OfficeimpressoController`
**Ações:** `index`, `show`, `generateQr`

Resíduo do ProductCatalogue. Mantido mas **removido do submenu** (não pertence
conceitualmente ao módulo de licença).

## 3. User stories

> Convenção: `US-OFFI-NNN`. Campo `implementado_em` linka com a view/rota.

### US-OFFI-001 · Ver todas as empresas licenciadas
**Como** operador superadmin WR2
**Quero** ver a lista de todos os clientes com módulo ativo
**Para** auditar uso, identificar quem precisa suporte
**Implementado em:** `/officeimpresso/businessall`
**DoD:** KPIs (total/ativas/bloqueadas) + tabela com busca + link pra computadores por empresa.

### US-OFFI-002 · Bloquear/desbloquear máquina individual
**Como** operador superadmin
**Quero** cortar acesso de 1 desktop específico (fora do pagamento, migração)
**Para** gerir risco sem afetar o resto do cliente
**Implementado em:** `GET /officeimpresso/licenca_computador/{id}/toggle-block`
**DoD:** Toggle persistido em `licenca_computador.bloqueado` + registro em `licenca_log` com `business_id` do admin.

### US-OFFI-003 · Bloquear empresa inteira
**Como** operador superadmin
**Quero** suspender TODAS as máquinas do cliente (ex. atraso pagamento)
**Para** força reação comercial sem deletar dados
**Implementado em:** `GET /officeimpresso/licenca_computador/businessbloqueado/{id}`
**DoD:** `business.officeimpresso_bloqueado = 1` + status visível em todas as telas.

### US-OFFI-004 · Auditar acesso de uma máquina
**Como** suporte WR2
**Quero** filtrar logs de uma máquina específica (cliente reclama)
**Para** entender o que aconteceu (logou? erro? bloqueada?)
**Implementado em:** `/officeimpresso/licenca_log?licenca_id=X` ou `?business_id=Y`
**DoD:** DataTable + coluna "Bloqueado?" (pill vermelho se era bloqueada naquele acesso) + link a partir do botão "Log" na tela de Computadores.

### US-OFFI-005 · Criar credenciais OAuth pro Delphi
**Como** operador superadmin
**Quero** gerar `client_id` + `secret` pra fornecer ao Delphi de novo cliente
**Para** permitir que o desktop dele autentique
**Implementado em:** `/officeimpresso/client` (modal "Criar Cliente")
**DoD:** Row em `oauth_clients` com `password_client=1`, `provider='users'`, `secret` hashed; Secret visível via toggle olho na UI.

## 4. Regras de negócio (Gherkin)

### R-OFFI-001 · Password grant exige `hd` pra logar (roadmap)
```gherkin
Dado que Delphi envia POST /oauth/token sem "hd"
Quando o access_token é emitido
Então `licenca_log` NÃO é gravado (log fica limpo)
```
Status: **parcial** — hoje só filtra no write do log; futuro: pode ser usado pra rejeitar auth.

### R-OFFI-002 · Listener não registra 2x
```gherkin
Dado que o mesmo user+ip+event ocorreu há menos de 1 minuto
Quando um novo AccessTokenCreated dispara
Então `licenca_log` faz skip (dedup)
```
**Implementação:** `LogPassportAccessToken::handle` checa `LicencaLog::where(...)->exists()` antes do INSERT.

### R-OFFI-003 · Log captura estado de bloqueio no momento
```gherkin
Dado que `business.officeimpresso_bloqueado = 1` OU `licenca.bloqueado = 1`
Quando Delphi autentica com sucesso
Então `licenca_log.metadata` guarda `{business_blocked, licenca_blocked, was_blocked}`
```
Permite auditoria mesmo se desbloquear depois.

### R-OFFI-004 · API intocável
```gherkin
Dado que o Delphi legado chama POST /oauth/token com grant=password
Quando qualquer mudança é feita no módulo
Então `/oauth/token` deve continuar emitindo access_token corretamente
```
**Why:** cliente em produção há 3 anos, não pode quebrar.

### R-OFFI-005 · Isolamento multi-tenant
```gherkin
Dado que user pertence a business A
Quando ele chama /api/officeimpresso (exceto superadmin)
Então só vê licenças de `business_id = A`
```

## 5. Integrações

### 5.1. Hooks UltimatePOS
- **`DataController::modifyAdminMenu()`** — injeta dropdown "Office Impresso" na sidebar (só superadmin, `order(2)`)
- **`DataController::superadmin_package()`** — registra feature flag `officeimpresso_module` no Superadmin

### 5.2. Eventos Laravel
- **Listener** `Laravel\Passport\Events\AccessTokenCreated` → `LogPassportAccessToken`

### 5.3. Middleware
- **`log.desktop`** alias em `/api/officeimpresso/*` (via group `auth:api, log.desktop`)

### 5.4. API externa consumida pelo Delphi
- `POST /oauth/token` (Passport v13, password grant) — intocável
- `GET /api/officeimpresso` — ping + user autenticado
- `POST /api/officeimpresso/audit` — opt-in, Delphi futuro pode postar eventos

### 5.5. Triggers MySQL (removidos)
- `licenca_log_after_oauth_access_token_insert` — dropado em 2026-04-24
- `licenca_log_after_oauth_refresh_token_insert` — dropado em 2026-04-24

## 6. Dados e entidades

### 6.1. `Licenca_Computador`
```
id bigint PK
business_id int FK → business
hd varchar(50) · chave única de hardware (serial do disco)
user_win, hostname, sistema_operacional, ip_interno, processador, memoria
pasta_instalacao, caminho_banco, versao_exe, versao_banco
bloqueado bool · controle individual
liberado, dt_validade, serial, valor, motivo
dt_cadastro, dt_ultimo_acesso
```

### 6.2. `LicencaLog` (append-only)
```
id, licenca_id, business_id, user_id
event · login_success | login_error | token_refresh | api_call | block | unblock | create_licenca | update_licenca | businessupdate | desktop_audit
client_id, token_hint, ip, user_agent, endpoint, http_method, http_status
error_code, error_message, duration_ms, metadata (JSON), source
created_at
```

## 7. Decisões em aberto

- [ ] **ADR 0020 — Grupo econômico:** filial abre sistema, não acha CNPJ matriz, se perde. Precisa `business.matriz_id` + resolução `effective_business_id = matriz_id ?? id`.
- [ ] **Delphi enviar `hd`** em `/oauth/token` (extra body param ou header `X-OI-HD`) — desbloqueia identificação de máquina no log. Depende de atualização do Delphi.
- [ ] **Rejeitar auth** quando `business.officeimpresso_bloqueado = 1` — hoje apenas loga, não bloqueia o token. Precisa middleware adicional em `/oauth/token`.
- [ ] **Retenção de logs** — hoje sem policy de purge. Considerar job `PruneLicencaLogs` (>90d mascara IP, >2 anos deleta).

## 8. Histórico e notas

- **2026-04-23 · v1.0.0** — Restauração completa da 3.7 (ADR 0017). 24 arquivos: Entity, 3 controllers, 2 migrations, 9 Transformers, 6 views, lang PT.
- **2026-04-23 · v1.1.0** — Log fase 2 (parser + audit endpoint) + design system.
- **2026-04-23 · v1.2.0** — UI polish (navtop branco, inputs claros, KPI equal heights), remove `css/1E202D.css` inexistente, filtro `?business_id=`.
- **2026-04-24 · v1.3.0** — Log: triggers MySQL → event listener + middleware (`LogPassportAccessToken` + `LogDesktopAccess`). Snapshot `was_blocked`. Dedup 1min. Só loga quando tem `hd`.
- **2026-04-24 · ADR 0019 RESOLVIDO** — Passport v10→v13 auth Delphi. 3 fixes: `Passport::enablePasswordGrant()`, re-hash secrets, `provider='users'`.

---

**Ver também:**
- `memory/decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md`
- `memory/decisions/0018-officeimpresso-log-acesso-passivo.md`
- `memory/decisions/0019-officeimpresso-delphi-nao-autentica.md`
- `memory/decisions/0020-officeimpresso-grupo-economico.md`
- `Modules/Officeimpresso/CHANGELOG.md` — histórico versionado
- `memory/officeimpresso-spec.md` — spec curta (referência rápida)

_Última atualização: 2026-04-24_
_Regerar dados estruturais: `php artisan module:requirements Officeimpresso`_
_Ver no DocVault: `/docs/modulos/Officeimpresso`_
