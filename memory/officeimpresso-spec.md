# Módulo Officeimpresso — Especificação

> Ferramenta interna WR2 para gestão de licenças de desktop Delphi.
> **Exclusivo superadmin.** API não pode mudar — Delphi legado tem que continuar funcionando.

## Propósito

Gerenciar **instalações Windows específicas** (máquina por máquina, não usuário por usuário) do software Delphi da WR2. Serve 3 funções:

1. Autenticar/autorizar cada desktop
2. Controlar o que cada cliente contratou (máquinas, versão, validade)
3. Auditar o uso (quem logou, quando, de onde, sucesso ou erro)

## Domínio

### Entidades

- **`Business`** — cliente da WR2. Campos custom: `caminho_banco_servidor`, `versao_obrigatoria`, `versao_disponivel`, `officeimpresso_bloqueado`, `officeimpresso_numerodemaquinas`
- **`Package` / `Subscription`** (Superadmin) — define `officeimpresso_limitemaquinas`
- **`Licenca_Computador`** — máquina registrada. Hardware (hd, hostname, user_win, processador, memoria), instalação (pasta_instalacao, caminho_banco, versao_exe, token), controle (bloqueado, dt_validade, valor, motivo), rastreamento (dt_cadastro, dt_ultimo_acesso, ip_interno)
- **`LicencaLog`** — append-only, populada passivamente. Tipos de evento: `login_success`, `login_error`, `token_refresh`, `api_call`, `block`, `unblock`, `create_licenca`, `update_licenca`, `businessupdate`, `heartbeat`
- **`Client`** (Passport OAuth) — identidade do app Delphi

### Relações

```
Package 1─N Subscription ─ 1 Business ─ 1─N Licenca_Computador ─ 1─N LicencaLog
```

## Arquitetura (não-negociável)

**Hot path Delphi = intocável.** Nenhum middleware, listener ou observer no caminho `/oauth/token` ou `/api/officeimpresso/*`.

Observação é **aditiva**, via 4 fontes:

1. **Triggers MySQL** → captura login_success, token_refresh (source=`trigger_mysql`)
2. **Log parser** → command agendado lê `storage/logs/laravel.log`, extrai login_error (source=`log_parser`)
3. **Admin actions** → controllers gravam direto em block/unblock/businessupdate (source=`admin_action`)
4. **Desktop audit endpoint** → rota opcional que Delphi futuro pode chamar (source=`desktop_audit`)

## Telas

Todas Blade/AdminLTE, com `layouts/nav.blade.php` (topnav horizontal).

- `/officeimpresso/businessall` — todos os clientes
- `/officeimpresso/computadores` (ou `/licencas/{business_id}`) — empresa + máquinas
- `/officeimpresso/licenca_computador` — CRUD direto
- `/officeimpresso/licenca_log` — timeline + KPIs (filtro deep-link `?licenca_id=X`)
- `/officeimpresso/client` — OAuth clients CRUD
- `/officeimpresso/docs` — iframe docs

## API (contrato público do Delphi — imutável)

- `GET /api/officeimpresso` — ping + user (existe hoje, mantém)
- `POST /api/officeimpresso/audit` — opcional, Delphi futuro pode chamar

Ponto crítico: **qualquer novo endpoint é aditivo**. Nenhum é obrigatório pro Delphi atual.

## Permissões

- Telas web: `can('superadmin')` em todas
- API Bearer: `auth:api` (Passport) — qualquer user do business autorizado

## Regras de negócio

1. Limite de máquinas = `officeimpresso_limitemaquinas` do pacote ou `officeimpresso_numerodemaquinas` do business (override)
2. `versao_exe < versao_obrigatoria` → heartbeat retorna `force_update`
3. Bloqueio cascata: `business.officeimpresso_bloqueado=1` → todas as máquinas bloqueadas
4. `licenca_log` é **append-only** (sem UPDATE/DELETE via código)
5. Senha jamais logada. Token truncado (primeiros 8 + últimos 4). IP mascarado após 90d

## Não-requisitos

- ❌ Billing (fica no Superadmin/Subscription)
- ❌ CRM (sem notas, tarefas)
- ❌ Gerenciamento de upgrade do Delphi (só diz a versão; distribuição em outro pipeline)
- ❌ Alertas automáticos email/SMS
- ❌ Dashboard analytics pesado

## Descobertas de produção (2026-04-23)

1. **Servidor tinha `Modules/Officeimpresso1/`** — backup 3.7 não removido pela migração. Causava conflito de namespace (mesmo `name: Officeimpresso`) que impedia carregamento de novas chaves de tradução. Movido pra `~/Officeimpresso1-3.7-BACKUP/`.

2. **`oauth_clients.id` continua INT** (não UUID) — stack Passport v13 convive porque os registros existentes funcionam, mas criação de novos clients via `passport:client` falha. Workaround: usar clients existentes.

3. **Delphi não autentica pós-upgrade** (ADR 0019 aberto) — nenhum hit em `/oauth/token` nem `/api/officeimpresso/*` observado quando cliente abre o app. Hipóteses em investigação.

## Links

- ADR 0017 — Restauração Officeimpresso 3.7 → 6.7
- ADR 0018 — Log acesso via triggers MySQL (passivo)
- ADR 0019 — Delphi legado não autentica (aberto)
- `Modules/Officeimpresso/CHANGELOG.md` — histórico de mudanças
- `bin/test-delphi-auth.sh` — script de diagnóstico do password grant
- `origin/3.7-com-nfe:Modules/Officeimpresso/` — fonte da restauração
- `~/Officeimpresso1-3.7-BACKUP/` (servidor) — backup do código 3.7 original
