---
name: pageheader-canon
description: ATIVAR quando user pedir "aplicar pageheader canon", "padronizar header da tela X", "/pageheader-canon <tela>", "header v3 na tela Y", OU em Edit/Write em qualquer `resources/js/Pages/<Mod>/<Tela>/Index.tsx` que tenha sub-navegação (sub-views/ghosts/abas no DataController correspondente). Carrega pattern canônico ADR 0180/0182 (header 3 zonas — título esquerda + ghost tabs ARIA centro + primary direita hue do grupo), matriz de diferenças permitidas, e checklist de 8 pontos pra reviewer validar PR. Cobre — (1) labels CURTOS nos ghosts (≤2 palavras), (2) auto-promove ghost ativo inline mesmo se >maxVisible, (3) primary obrigatório (exceto read-only) com cor hue do grupo via componente `{Modulo}PrimaryButton`, (4) botões features → `extraOverflowItems` do ⋯ Mais, (5) duplicados-com-ghost REMOVE, (6) ARIA tablist + keyboard nav, (7) Multi-tenant Tier 0 (SubNav retorna null se tenant sem módulo), (8) tipografia ADR 0110. Tier B auto-trigger por description.
---

# Skill `pageheader-canon` — Aplicação do header v3 canônico

> Skill ATIVADA por descrição quando Claude vai editar tela Inertia com sub-navegação. Garante aderência ao pattern ADR 0180 + ADR 0182 + matriz de diferenças `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md`.

## Quando ativar

- Edit/Write em `resources/js/Pages/<Mod>/<Tela>/Index.tsx` que tem sub-navegação
- User pede: "aplicar pageheader canon", "padronizar header da tela X", "/pageheader-canon <tela>"
- Refactor de tela legacy que ainda tem `<button class="os-btn primary">` magenta inline
- Tela nova sendo criada com sub-views (ghost candidates) no DataController

## 8 pontos canônicos (validação obrigatória)

### 1. Estrutura 3 zonas (`os-page-h`)

```tsx
<header className="os-page-h fin-page-h">
  <div className="os-page-h-l fin-page-h-l">
    <h1>{Tela} <span className="fin-hero-title-sub">· {subtitle}</span></h1>
    <p>{periodo/business/contexto}</p>
  </div>
  <div className="os-page-h-r fin-page-h-r">
    {/* ZONA C: ghosts + overflow */}
    <{Modulo}SubNav active="<key>" hidePrimary extraOverflowItems={[]}/>
    {/* ZONA R: primary direita */}
    <{Modulo}PrimaryButton onClick={...}>Nova X</{Modulo}PrimaryButton>
  </div>
</header>
```

### 2. Componente SubNav por módulo

Cada módulo (Financeiro/Vendas/OS/Compras/etc) DEVE ter `Pages/<Modulo>/_shared/<Modulo>SubNav.tsx` que:
- Lê `shell.menu` via `usePage()`
- Procura entry com `group === '<grupo_v3>'` ou label canon
- Retorna `null` se módulo não declarado (multi-tenant Tier 0)
- Renderiza `<PageHeaderTabs primary={hidePrimary?undefined:item.primary} ghosts={...} extraOverflowItems={...}/>`

Template canon: `resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx`

### 3. Componente PrimaryButton por módulo (cor harmônica)

Cada módulo DEVE ter `Pages/<Modulo>/_shared/<Modulo>PrimaryButton.tsx` que:
- Background `oklch(0.55 0.15 {hue_do_grupo})` — NÃO magenta 330 canon UPOS
- Default ícone `<Plus/>` (override `hideIcon` se workflow não-create)
- Aceita `onClick`/`href` (button vs anchor)

Template canon: `resources/js/Pages/Financeiro/_shared/FinanceiroPrimaryButton.tsx`

### 4. Labels CURTOS nos ghosts (DataController)

❌ Errado: `'Contas a Receber'` / `'Fluxo de Caixa'` / `'Contas Bancárias'` / `'Plano de Contas'`
✅ Certo: `'Receber'` / `'Fluxo'` / `'Bancos'` / `'Plano'`

Verbo+substantivo único OU substantivo único. ≤2 palavras.

### 5. Auto-promoção do ghost ativo (PageHeaderTabs.tsx)

`PageHeaderTabs` PRECISA ter logic que detecta `activeGhostKey` cuja posição > `maxVisible` e MOVE pra última posição visível. Sem isso, telas como Conciliação/DRE/Bancos têm active escondido no overflow `⋯ Mais` — usuário não sabe onde está.

### 6. Botões action features → `extraOverflowItems`

Botões inline do header pré-pattern devem ser classificados:

| Tipo | Destino |
|---|---|
| Duplicado-com-ghost (navega pra tela que já é ghost) | **REMOVER** |
| Ação features (abre dialog/sheet/modal) | **`extraOverflowItems[]`** |
| Primary "Nova X" | **Zona R** via `{Modulo}PrimaryButton` |
| Botão per-linha tabela | **INTACTO** (não é header) |

### 7. ARIA tablist + keyboard nav

`PageHeaderTabs` já garante (não tocar). Validar:
- `role="tablist"` no wrapper de ghosts
- Cada ghost `role="tab"` + `aria-selected` + `tabindex` 0/-1
- ArrowLeft/Right/Home/End funcionam

### 8. Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

- `SubNav` retorna `null` se `finItem === undefined` (tenant sem módulo)
- `extraOverflowItems` que abrem dialog checam `auth()->user()->can(...)` no handler
- `shell.menu` já filtra por `business_id` (HandleInertiaRequests)

## Workflow exato pra aplicar em tela nova

1. **Pré-flight**: ler `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md` (matriz F1-F12 fixas + V1-V9 variáveis)
2. **Backend**: DataController declara `ghosts[]` com labels curtos + `primary{label,href,shortcut}` na entry principal do módulo
3. **Frontend wrapper**: criar `Pages/<Modulo>/_shared/<Modulo>SubNav.tsx` se não existe (template = FinanceiroSubNav)
4. **Frontend primary**: criar `Pages/<Modulo>/_shared/<Modulo>PrimaryButton.tsx` se não existe (hue do grupo)
5. **Tela**: 
   - Import `<{Modulo}SubNav>` + `<{Modulo}PrimaryButton>`
   - Header em 3 zonas (`os-page-h` + `os-page-h-l` + `os-page-h-r`)
   - `<{Modulo}SubNav active="<key>" hidePrimary extraOverflowItems={[...]}/>`
   - `<{Modulo}PrimaryButton onClick={...}>Verbo+X</{Modulo}PrimaryButton>` (omitir se read-only)
   - Botões legacy: classificar por tabela 6 (remover/overflow/primary/intacto)
6. **Charter da tela**: atualizar `*.charter.md` com nota "header pattern ADR 0182 OK"
7. **Pest test** (opcional): valida shape do shell.menu + click ghost ativo

## Checklist de revisão de PR (8 pontos)

Reviewer checa cada PR que toca tela com sub-navegação:

- [ ] F1 header tem 3 zonas (`os-page-h-l` + ghosts + primary)?
- [ ] F2 usa `<{Modulo}SubNav>` (não JSX inline)?
- [ ] F6 ghost ativo aparece inline (auto-promoção funciona)?
- [ ] F8 primary cor harmônica (hue grupo, não magenta canon UPOS)?
- [ ] F9 labels dos ghosts curtos (≤2 palavras)?
- [ ] F12 sem botões duplicados-com-ghost inline?
- [ ] V1 label primary contextual (verbo de ação)?
- [ ] F11 SubNav retorna null se módulo desinstalado?

## Anti-padrões conhecidos (evitar)

| Anti-padrão | Causa | Fix |
|---|---|---|
| Primary magenta `oklch(0.58 0.12 330)` | CSS canon UPOS legacy `os-btn primary` | Usar `{Modulo}PrimaryButton` com hue do grupo |
| Ghost ativo invisível no overflow | `activeGhostKey` index >= maxVisible | PageHeaderTabs auto-promoção (já implementado) |
| Botão duplicado-com-ghost (ex `Conciliar` inline + ghost `conciliacao`) | Refactor incompleto | REMOVER botão inline — ghost cobre |
| Label longo (`'Contas a Receber'`) | DataController copia do UPOS legacy | Encurtar (`'Receber'`) — preservar label longo no tooltip |
| Primary inline antes dos ghosts | PageHeaderTabs default render primary à esquerda | Passar `hidePrimary` + renderizar primary separado direita |
| Tela read-only com primary forçado | Pattern aplicado cegamente | OK omitir primary em Fluxo/Relatórios |

## Estado atual (ADR 0180/0182 propagação)

| Módulo | SubNav existe? | PrimaryButton existe? | Telas migradas |
|---|---|---|---|
| Financeiro | ✅ | ✅ | 11/13 (Caixa 500 prod; Contador config) |
| Vendas (Sells) | ❌ | ❌ | 0 |
| CRM | ❌ | ❌ | 0 |
| OS (Repair/OficinaAuto) | ❌ | ❌ | 0 |
| Compras | ❌ | ❌ | 0 |
| Fiscal (NfeBrasil) | ❌ | ❌ | 0 |
| RH (Essentials/Ponto) | ❌ | ❌ | 0 |
| Governance | ❌ | ❌ | 0 |
| Plataforma | ❌ | ❌ | 0 |

Próximas Ondas: aplicar em outros 8 módulos (sub-agents paralelos, ~1.5-2 dias).

## Refs

- [ADR 0180](../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) — Sidebar v3
- [ADR 0182](../../../memory/decisions/0182-pageheadertabs-canon-pattern-telas.md) — PageHeaderTabs canon
- [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0
- [ADR 0110](../../../memory/decisions/0110-tipografia-canon-h1-subtitle.md) — Tipografia
- [Matriz diferenças](../../../memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md) — F1-F12 + V1-V9
- Templates: `resources/js/Pages/Financeiro/_shared/{FinanceiroSubNav,FinanceiroPrimaryButton}.tsx`
- Skill `cockpit-runbook` (Tier B, complementa este pattern com receita completa)
