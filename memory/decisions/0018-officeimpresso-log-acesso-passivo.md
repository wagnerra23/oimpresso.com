# ADR 0018 — Log de acesso do desktop via triggers MySQL (passivo)

**Status:** Aceito
**Data:** 2026-04-23

## Contexto

Wagner precisa de **trilha de auditoria** de quando/como os desktops Delphi autenticam no sistema para:
- Responder "o que aconteceu com o desktop X entre 14h e 15h" quando cliente liga
- Detectar login com credenciais erradas (brute force, cliente bloqueado indevidamente)
- Medir latência do `/api/officeimpresso` percebida pelo desktop
- Ver quando licença foi bloqueada/desbloqueada e por quem
- Detectar clonagem (mudança súbita de `hd` ou `hostname`)

**Restrição crítica:** não alterar o fluxo de autenticação existente. O Delphi legado bate em `/oauth/token` e não pode ser tocado.

## Decisão

### Arquitetura — captura passiva, escrita append-only

**Onde os logs são gerados (3 fontes):**

1. **Triggers MySQL (`trigger_mysql`)** — captura 100% dos tokens emitidos/refreshados sem tocar em PHP.
   - `AFTER INSERT ON oauth_access_tokens` → grava `login_success` em `licenca_log`
   - `AFTER INSERT ON oauth_refresh_tokens` → grava `token_refresh`
   - Tokens ficam armazenados truncados: primeiros 8 + últimos 4 chars (correlação sem exposição)

2. **Parser do `laravel.log` (`log_parser`)** — *fase 2, não implementada ainda*. Command `php artisan licenca-log:parse` extrai erros de Passport do arquivo e grava `login_error` com `error_code`.

3. **Ações admin (`admin_action`)** — controllers gravam direto ao fazer `toggleBlock`, `businessupdate`, `create/update licenca`. *Fase 3, instrumentação manual conforme demanda.*

4. **Endpoint opcional Delphi futuro (`desktop_audit`)** — rota `POST /api/officeimpresso/audit` **criada mas não obrigatória**. Delphi pode evoluir pra enviar contexto rico (hostname, serial, erro percebido) quando time Delphi tiver disponibilidade. *Fase 4.*

### Schema da tabela `licenca_log`

Append-only (sem UPDATE/DELETE por código), JSON em `metadata` pra flexibilidade por evento. Indexes compostos `(business_id, created_at)`, `(licenca_id, created_at)`, `(event, created_at)` pra DataTables filtrada.

### UI `/officeimpresso/licenca_log`

- 4 KPIs das últimas 24h: sucessos, erros, chamadas API, bloqueios
- DataTable AJAX com filtros (evento, licença, intervalo)
- Deep-link: `?licenca_id=X` filtra logs de um computador específico (chamado do botão "Log" na tela de Computadores)

### LGPD

- Senha **nunca logada** — trigger nem vê
- Token truncado (`eyJ0eXAi…m9hM`)
- IP completo por 90d, depois octetos finais mascarados via job `PruneLicencaLogs` (TBD)
- Metadata de hardware (hd, serial, hostname) guardado 1 ano

## Consequências

### Positivas
- **Zero risco ao Delphi** — nenhum middleware, listener ou observer no hot path
- Login bem-sucedido capturado em ~0ms (trigger transacional)
- Schema pensado pra agregar, não só listar (KPIs são cheap com os indexes)
- Extensível — se decidirmos evoluir, basta instrumentar mais pontos gravando em `licenca_log` com `source` diferente

### Negativas
- **Falta contexto nos triggers** — MySQL não vê IP/user-agent/endpoint. Só captura user_id, client_id e token. IP precisa vir de parser de log ou do endpoint opcional.
- **Triggers ≠ portável** — se migrarmos pra Postgres, reescrever. Risco baixo (projeto em MySQL há anos).
- **Delay fora de transação** não existe — trigger roda no mesmo commit. Se DB cair, log cai junto (aceitável).

### Volume esperado
- 500 desktops × login cada 30min = ~24k logins/dia
- 90d retenção → ~2.2M rows
- Com indexes compostos, queries típicas em <100ms

## Alternativas consideradas

- **Middleware Laravel interceptando `/oauth/token`** — rejeitado. Qualquer bug quebra auth do Delphi.
- **Event listener em `Passport\AccessTokenCreated`** — rejeitado. Se listener lança exception, Passport fica inconsistente.
- **Observer no model `Licenca_Computador`** — rejeitado. Delphi pode INSERT/UPDATE via caminhos que desconhecemos (3.7 tinha muito código direto).
- **Laravel Pulse** — rejeitado. Agrega métricas mas não grava eventos discretos auditáveis.
- **MySQL general query log** — rejeitado. Volume gigante, lê tudo, não só tokens. Caro no Hostinger compartilhado.

## Fase 2 — parser do laravel.log (pendente)

Command agendado que lê linhas novas do `storage/logs/laravel.log`, extrai:
- `Invalid credentials`, `Client authentication failed`, `The authorization grant type is not supported` → `login_error` com `error_code`
- Correlaciona com tentativas via timestamp + IP se disponível
- Dedup por hash da linha

## Links
- `Modules/Officeimpresso/Database/Migrations/2026_04_23_200000_create_licenca_log_table.php`
- `Modules/Officeimpresso/Database/Migrations/2026_04_23_200100_create_licenca_log_triggers.php`
- `Modules/Officeimpresso/Entities/LicencaLog.php`
- `Modules/Officeimpresso/Http/Controllers/LicencaLogController.php`
- `Modules/Officeimpresso/Resources/views/licenca_log/index.blade.php`
- ADR 0017 (restauração Officeimpresso)
