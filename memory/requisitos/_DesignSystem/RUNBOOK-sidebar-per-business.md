# RUNBOOK — Sidebar per business (esconder módulos não usados)

> US-UI-SIDEBAR-001 · 2026-05-14 · Caso piloto: Martinho Caçambas

## Por que existe

Sidebar AppShellV2 mostra ~25 módulos (ACESSOS RÁPIDOS, OFICINA AUTO, FINANCEIRO, ESTOQUE, FISCAL, RH, CONHECIMENTO, RELATÓRIOS, IA & PRODUTIVIDADE, GOVERNANÇA, PLATAFORMA, MAIS).

Clientes não-técnicos (Martinho Caçambas — filha + Dani financeiro, oficina mecânica + aluguel caçambas, CNAE 4520) usam ~5 desses. Os outros 20 são distração e — pior — porta de entrada pra Blade legacy que destrói a impressão do produto.

Solução: **feature-flag config-driven por business** sem mexer em código pra cada cliente. Default safe: NULL = mostra tudo (back-compat).

## Como funciona

1. **Coluna `business.sidebar_hidden_groups`** (JSON nullable) — array de strings que podem ser:
   - **Chave de grupo SIDEBAR_GROUPS** (lowercase: `rh`, `fiscal`, `estoque`, `governanca`, `plataforma`, `conhecimento`, `office`, `oficina`, `fin`, `rel`, `ia`) — esconde TODO o grupo (todos os itens dentro)
   - **Label EXATO de item top-level** (preservar casing: `HRM`, `Reparar`, `Officeimpresso`, `Projeto`, `ADS`, `CRM`) — esconde só esse item

   Match **case-insensitive** em ambos os casos. Lookup table dos grupos canon vive em `resources/js/Components/cockpit/Sidebar.tsx` (constante `SIDEBAR_GROUPS`) e espelhada em `app/Services/LegacyMenuAdapter.php` (`sidebarGroupsMirror()`).

2. **`LegacyMenuAdapter::build()`** lê `business.sidebar_hidden_groups` do business do user autenticado (via `auth()->user()->business_id`) e filtra os items antes de devolver pro Inertia. Multi-tenant Tier 0 (ADR 0093) — scope explícito.

3. **Default safe** — qualquer falha (coluna ausente, JSON inválido, user anônimo, business inexistente) retorna lista vazia → sidebar mostra TUDO. Sem risco de regressão.

4. **Cache per-request** via `static $cache` dentro de `hiddenList()` — sobrevive a múltiplas chamadas no mesmo request (Inertia share, sidebar render etc).

## Caso piloto — Martinho Caçambas

Cadastrado no `BusinessSidebarConfigSeeder` por nome substring `'Martinho'`:

```php
'Martinho' => [
    // Grupos inteiros escondidos
    'rh',            // HRM, Essenciais, Ponto — sem folha CLT
    'estoque',       // Compras, Transferências, Ajuste, Ativos
    'conhecimento',  // Cofre, KB, Planilha — pra dev, não cliente
    'governanca',    // Governança, ADS, Team MCP — interno oimpresso
    'plataforma',    // CMS, Conector, Backup, Módulos — superadmin
    // Items específicos escondidos
    'Reparar',         // substituído por Oficina Auto V0
    'Officeimpresso',  // legacy WR Comercial Delphi — nunca usou
    'Office Impresso', // variação do label
    'Projeto',         // não relevante pra oficina
    'Project Mgmt',
    'ADS',
],
```

**Resultado esperado pra Martinho:** sidebar enxuto com apenas:
- ACESSOS RÁPIDOS (Contatos, Produtos, Vendas)
- OFICINA AUTO (Veículos, Ordens de Serviço)
- FINANCEIRO (Despesas, Contas de pagamento, Contabilidade, Financeiro)
- FISCAL (NFSe, NF-e Brasil)
- RELATÓRIOS (Dashboard, Relatórios)
- IA & PRODUTIVIDADE (Copiloto, Jana)
- Configurações (no user dropdown footer)

De ~25 itens pra ~10 → 60% de redução. Persona não-técnica não cai em Blade legacy.

## Como ativar pra próximo cliente

### Opção A — Via seeder (recomendado pra clientes canon)

Editar `database/seeders/BusinessSidebarConfigSeeder.php`, adicionar entry no array `SIDEBAR_CONFIGS`:

```php
'Vargas' => [
    'rh',
    'conhecimento',
    'governanca',
    // ... lista curada
],
```

Rodar `php artisan db:seed --class=BusinessSidebarConfigSeeder`. Idempotente — só marca quem está com `sidebar_hidden_groups IS NULL` (não sobrescreve config manual).

### Opção B — Via SQL direto (ad-hoc por cliente)

```sql
UPDATE business
SET sidebar_hidden_groups = JSON_ARRAY('rh', 'plataforma', 'Reparar')
WHERE id = 164;
```

Use a UI Admin (futuro) ou SQL puro em emergência. **Sempre** scope pelo `id` (Tier 0 — ADR 0093).

### Opção C — Reset pra mostrar tudo

```sql
UPDATE business SET sidebar_hidden_groups = NULL WHERE id = 164;
```

## Como auditar quem está com sidebar customizado

```sql
SELECT id, name, sidebar_hidden_groups
FROM business
WHERE sidebar_hidden_groups IS NOT NULL;
```

## Gotchas

1. **Drift backend↔frontend.** O array `sidebarGroupsMirror()` em `LegacyMenuAdapter.php` deve casar com `SIDEBAR_GROUPS` em `Sidebar.tsx`. Quando adicionar grupo novo no front, espelhe no backend. O test `Frontend Sidebar.tsx tem todas as keys de grupo declaradas no mirror backend` detecta drift.

2. **Cache per-request, NÃO global.** O `static $cache` vive na instância do `LegacyMenuAdapter` resolvida pelo container — sobrevive ao mesmo request, morre no fim. Se você fizer factory de instância manual no mesmo request, vai consultar de novo (não é bug, é desperdício).

3. **Labels com encoding (Português).** O match é `mb_strtolower($label, 'UTF-8')` em ambos os lados — `'Configurações'` casa case-insensitive. Mas se o seeder gravar `'configurações'` (sem maiúscula em "C") e o item top-level vier do legado como `'Configurações'`, ainda casa (normalização).

4. **Items que NÃO aparecem em SIDEBAR_GROUPS caem em "MAIS" (fallback).** Se esconder pela chave de grupo, esses items de "MAIS" continuam aparecendo. Pra escondê-los, use o label exato. Ex: `'Modelos de notificação'`.

5. **NÃO esconde itens do user dropdown footer.** `Gerenciamento de usuários` e `Configurações` vão pro footer via filtro `isUserMenuItem` (em `shared.ts`), independente. Pra esconder também, precisa de outro mecanismo (não escopo deste).

6. **Superadmin/Backup/Módulos.** Grupo `plataforma` cobre. Esconder pra cliente não-superadmin é cosmético — guards de permissão já bloqueiam acesso. Default safe: se mostrar, cliente vê erro 403.

## Como reverter

```bash
php artisan migrate:rollback --step=1  # se for última migration
# OU
mysql> UPDATE business SET sidebar_hidden_groups = NULL WHERE id = 164;
```

Default safe garante que rollback parcial (coluna some sem código rollback) também funciona — try/catch em `hiddenList()` falha silenciosa.

## Próximos candidatos OfficeImpresso (sinal qualificado — ADR 0105)

Quando estes clientes pedirem onboarding no oimpresso novo (Laravel) — preparar config curada antes do canary:

- **Vargas** — gráfica SP, candidato Modules/ComunicacaoVisual
- **Extreme** — gráfica, candidato Modules/ComunicacaoVisual
- **Gold, Zoom, Fixar, Mhundo, Produart** — outros 5 saudáveis

Cada um vai ter perfil ligeiramente diferente. Manter `SIDEBAR_CONFIGS` como mapa: nome → array de hidden.

## Refs

- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0105 — Cliente como sinal qualificado
- ADR 0121 — Modular especializado por vertical (Modules/OficinaAuto V0)
- Skill `sidebar-menu-arch` (`.claude/skills/sidebar-menu-arch/SKILL.md`)
- Migration `database/migrations/2026_05_14_120000_add_sidebar_hidden_groups_to_business.php`
- Seeder `database/seeders/BusinessSidebarConfigSeeder.php`
- Adapter `app/Services/LegacyMenuAdapter.php` (`hiddenList()` + `applyHiddenGroupsFilter()`)
- Pest `tests/Feature/Sidebar/SidebarPerBusinessTest.php`
- Frontend (READ-ONLY mirror) `resources/js/Components/cockpit/Sidebar.tsx` (`SIDEBAR_GROUPS`)
