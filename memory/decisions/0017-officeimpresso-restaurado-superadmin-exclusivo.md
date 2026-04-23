# ADR 0017 — Officeimpresso restaurado da 3.7 como módulo Superadmin exclusivo

**Status:** Aceito
**Data:** 2026-04-23

## Contexto

A migração 3.7→6.7 executada no servidor de produção em 2026-04-21 perdeu código e views do módulo `Officeimpresso`. Sobrou apenas o stub de Catálogo QR (resíduo de ProductCatalogue). O módulo é interno da WR2 — usado pela Eliana/equipe superadmin para **gerenciar licenças de desktop** (cada instalação Delphi) e dar visibilidade sobre qual cliente está usando o sistema.

A tabela `licenca_computador` no DB de produção foi preservada (não sofreu DROP), mas sem o código PHP ninguém conseguia consultá-la pela interface. Cliente precisava urgente recuperar a gestão.

## Decisão

1. **Restaurar** o módulo a partir de `origin/3.7-com-nfe` — Entity, 3 Controllers (LicencaComputador, Client, LicencaLog), Middleware CheckDemo, 9 Transformers, 6 views blade, 14 locales, migrations originais.

2. **Módulo exclusivo Superadmin** — todas as ações precisam `auth()->user()->can('superadmin')`:
   - Menu sidebar: renderiza só para superadmin (ordem 2, logo após Superadmin)
   - Rotas web: middleware `auth` filtra sessão, mas **checks adicionais em cada controller**
   - `viewLicencas($id)` aceita `business_id` arbitrário (superadmin pode abrir computadores de qualquer cliente) — não trava no business logado como faz pra user comum

3. **Rotas mantêm typos do 3.7** — views chamam `route('business.bloqueado')`, `route('empresa.licencas')`, `licenca_computado/licencas/{id}` (faltando R). Preservar os nomes pra não quebrar as views copiadas. Típico do Officeimpresso.

4. **Topnav em dois formatos:**
   - `Resources/views/layouts/nav.blade.php` — barra AdminLTE/Bootstrap 3 (convenção UPOS)
   - `Resources/menus/topnav.php` — declarativo pra futuro React/Inertia (convenção DocVault)

5. **Log de Acesso do Desktop (fase 1 — passivo):** `licenca_log` alimentada por **triggers MySQL** em `oauth_access_tokens` / `oauth_refresh_tokens`, sem middleware no request path. **Zero risco de quebrar auth do Delphi legado.**

## Consequências

### Positivas
- Gestão de licença restaurada em ~2 horas (vs. re-escrever do zero: dias)
- Tabela `licenca_computador` com dados históricos continua acessível
- Delphi não precisa mudar nada — autenticação existente permanece intacta
- Log de acesso dá observabilidade que nunca existiu no 3.7

### Negativas
- Views com typos (licencas_computador vs licenca_computador, LicencaController vs ClientController) — 3.7 tinha bugs que só aparecem quando você navega. Correção por view, on demand.
- Namespace `Modules\ProductCatalogue\Providers` estava misturado no `RouteServiceProvider` do Officeimpresso — fix em a5f2198.
- `@lang('xxx', [], 'fallback')` NÃO é fallback — 3º arg é locale. Trecho corrigido em 570d66b0.

### Performance — adicionada migration de índices (2026-04-23)
- `licenca_computador` tinha só PK + FK business_id
- Adicionados: `hd`, `dt_ultimo_acesso`, `(business_id, dt_ultimo_acesso)`, `(business_id, bloqueado)`
- Reduz filesort em listagens e lookup por hardware ID

## Alternativas consideradas

- **Reescrever do zero em React/Inertia (como PontoWR2).** Descartado — tempo estimado 2-3 dias, e Wagner precisa do módulo funcionando imediatamente. Pode ser feito depois, incremental.
- **Deixar Officeimpresso morto.** Descartado — é ferramenta crítica de gestão de cliente.
- **Middleware em `/oauth/token` para log.** Descartado — risco de quebrar Delphi legado. ADR 0018 (Log passivo) cobre.

## Links
- `memory/sessions/2026-04-23-session-XX.md` (TBD — próxima sessão log)
- Commits: ec8d88f, 08b1857, 6e93196, 44a0886, 2905f57, ae72505, ce0ef90, 570d66b0, ...
- `Modules/Officeimpresso/` — código atual
- `origin/3.7-com-nfe:Modules/Officeimpresso/` — fonte da restauração
