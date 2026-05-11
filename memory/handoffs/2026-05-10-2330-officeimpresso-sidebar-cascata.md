# Handoff 2026-05-10 noite-3 — Officeimpresso clickável no sidebar + cascata Superadmin REMOVIDA + MWART knowledge

> **Conteúdo migrado durante o rebase do PR ADR 0130/0131.** Originalmente vivia em `memory/08-handoff.md` (sobrescrito pelo PR #517). Preservado integralmente neste arquivo append-only conforme [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md).

Sessão coding+governance. Wagner [W] pediu auditoria visual do módulo Officeimpresso ("olhe o blade e compare até melhorar") → fix de quick wins → debug "menu sem link pra abrir" → decisão arquitetural: **cascata "Superadmin" do user dropdown REMOVIDA**, módulos admin de plataforma vivem no sidebar como qualquer outro grupo. Office Impresso em ACESSOS RÁPIDOS, demais em novo grupo PLATAFORMA. Validado em prod via Chrome MCP.

### 4 PRs mergeados em main (sequência cronológica)

1. **[#505](https://github.com/wagnerra23/oimpresso.com/pull/505)** `fix(officeimpresso)` — UI polish Blade (3 quick wins: semântica botão Bloquear verde→laranja + ícone lock/unlock corrigido, AÇÕES truncate businessall icon-only, novo `.oi-btn-warning` no design-system). 4 files, +10/-7.

2. **[#511](https://github.com/wagnerra23/oimpresso.com/pull/511)** `docs(officeimpresso)` — RUNBOOK migração Blade→React (~211 linhas) em [`memory/requisitos/Officeimpresso/RUNBOOK-migracao-react.md`](../requisitos/Officeimpresso/RUNBOOK-migracao-react.md). Inventário 14 telas + ordem sugerida + estratégia teste multi-tela + 2 pegadinhas catalogadas (parent dropdown URL + Spatie perm). Atualizado pós-#516 pra refletir nova regra.

3. **[#512](https://github.com/wagnerra23/oimpresso.com/pull/512)** `docs(skills)` — mwart-process v1.0 → v1.1.1 (Tier A always-on): nova seção "F0 Adapção por tipo de módulo" com tabela 4 tipos (Operativo/Admin pesado/Admin esporádico/Público) + placement em `SIDEBAR_GROUPS`. mwart-quality (Tier B): Check 11 (parent dropdown URL) + Check 12 (Spatie perm `superadmin` existe + atribuída).

4. **[#516](https://github.com/wagnerra23/oimpresso.com/pull/516)** `feat(menu)` — **decisão arquitetural**: cascata Superadmin do user dropdown footer REMOVIDA. 4 files: `shared.ts` (SUPERADMIN_LABELS esvaziado, isSuperadminMenu retorna false), `Sidebar.tsx` (Office Impresso adicionado a `office`/ACESSOS RÁPIDOS, novo grupo `plataforma` no fim com CMS/Conector/Backup/Módulos), `MenuItem.php` (estende `::make()` pra promover `attributes.url` → `url` — beneficia qualquer dropdown), `DataController.php` (adiciona `'url' => '/officeimpresso/computadores'` no parent dropdown).

PR #515 (fix-only intermediário) **fechado** como suplantado por #516.

### Aplicado em prod (Hostinger)

```
PERM_CREATED id=329                  ← Spatie permission 'superadmin'
ROLE_GRANTED Admin#1                  ← role recebeu a perm
final_check=true                      ← User::find(1)->can('superadmin') = TRUE
Quick Sync workflow OK (12.57s)       ← npm run build:inertia rodou
app-B4KkSF3b.js servido em prod       ← assets novos confirmados via curl
```

Validado via Chrome MCP em https://oimpresso.com/governance:
- Sidebar mostra **Office Impresso** entre Crm/Reparar em ACESSOS RÁPIDOS ✓
- User dropdown footer **SEM cascata Superadmin** (só Meu perfil/Gerenciamento/Configurações/Disponível/Aparência/Modo de trabalho/Atalhos/Ajuda/Sair) ✓

### Decisão arquitetural ratificada (Wagner [W] 2026-05-10)

> "todos devem se mover, o outro não deve existir mais"

Cascata "Superadmin" do user dropdown footer existiu de 2026-04-27 a 2026-05-10 — REMOVIDA. Admin de plataforma é menu como qualquer outro. **`SIDEBAR_GROUPS` (`Sidebar.tsx`) é único mecanismo de placement**:
- Uso pesado pelo owner (Officeimpresso) → grupo `office` (ACESSOS RÁPIDOS)
- Uso esporádico (CMS, Conector, Backup, Módulos, Personalizar) → grupo `plataforma` (no fim, collapsed)

`SUPERADMIN_LABELS` em `shared.ts` mantido vazio + `isSuperadminMenu()` retorna false (deprecated, callers `SidebarFooter.hasSuperadmin` / `SidebarUserMenu` cascata ficam dormentes sem quebrar).

### Pegadinhas catalogadas (mwart-quality Check 11/12)

1. **Parent dropdown sem `'url'` cai em `href='#'`** — `Menu::dropdown(label, closure, ['url' => '/path', ...])` agora funciona graças ao promo `attributes.url` → `url` no `MenuItem::make` (PR #516).
2. **Spatie permission `superadmin` precisa existir + estar atribuída** ao role do user — sem isso, items dos DataControllers nunca chegam ao `shell.menu` (guards retornam early). Catalogado em local dev (`perm_exists=false` antes do fix) e aplicado em prod (id=329).

### Próximos passos imediatos

1. **Wagner** — quando priorizar (fora do Cycle 04 atual): iniciar migração da `licenca_log/index` (P0) seguindo [RUNBOOK Officeimpresso](../requisitos/Officeimpresso/RUNBOOK-migracao-react.md) F1→F5
2. **Cycle 04** — segue inalterado: Whatsapp múltiplos números + Manifestação NFe + Inter PJ saldo
3. **Próxima Claude** — se Wagner mencionar "tela X do Officeimpresso", usar RUNBOOK como pré-flight; se outro módulo admin de plataforma, lembrar grupo `plataforma` no `SIDEBAR_GROUPS`
