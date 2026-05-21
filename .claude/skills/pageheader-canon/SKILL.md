---
name: pageheader-canon
description: ATIVAR quando agente vai aplicar o PageHeader canon (ADR 0180/0182) em módulo novo — user pede "aplicar pageheader canon no módulo X", "padronizar header do <Modulo>", "/pageheader-canon <Modulo>", "header v3 nas telas Y", OU em Edit/Write em `resources/js/Pages/<Mod>/<Tela>/Index.tsx` que tenha sub-navegação (sub-views no DataController). Skill carrega — (1) **algoritmo de descoberta** do módulo (cruzar DataController.php + Pages/<Mod>/**/*.tsx pra mapear sub-views + primary contextual + ações features), (2) **tabela de decisão** dos botões (duplicado-com-ghost REMOVE / features → extraOverflowItems / primary "Nova X" → Zona R / per-linha INTACTO), (3) **naming convention** de labels (≤2 palavras, sem repetir nome do módulo), (4) **hue OKLCH per-grupo** canon (financas=145, vender=60, operar=350, pessoas=295, sistema=200, ia=220, atendimento=30, equipe=270), (5) **validação POST-implementação OBRIGATÓRIA** via browser MCP (script JS valida labels curtos + auto-promove + cor primary correta + bg NÃO magenta 330) e (6) **gate ✓/⚠️** que FALHA o PR se alguma tela não passar. Tier B auto-trigger por description.
---

# Skill `pageheader-canon` — Protocolo do agente pra aplicar header v3

> Skill canon pra agente (sub-agent ou parent Claude) propagar o pattern PageHeader (ADR 0180/0182) em módulo. Cobre **descoberta → decisão → implementação → validação visual obrigatória**.

## Quando ativar

- User pede: "aplicar pageheader canon no <Modulo>", "padronizar header do X", "/pageheader-canon <Modulo>"
- Edit em `Pages/<Modulo>/<Tela>/Index.tsx` com sub-navegação (DataController declara ghosts)
- Refactor de tela legacy com `<button class="os-btn primary">` magenta inline
- Tela nova sendo criada num módulo já canonizado (Financeiro/futuro Vendas/etc)

---

## Fase 1 — **DESCOBERTA do módulo** (algoritmo obrigatório)

Agente precisa **entender o módulo** antes de propor mudanças. 5 passos:

### 1.1 Identificar grupo canônico v3 do módulo

Lê `Modules/<Modulo>/Http/Controllers/DataController.php` e procura:
- `'group' => '<key>'` declarado nas entries `Menu::modify(...)`
- Match com tabela canon:

| Módulo (exemplos) | Grupo v3 | Hue OKLCH |
|---|---|---|
| Sells / Crm / ProductCatalogue / Vestuario / Woocommerce | `vender` | **60** (amarelo) |
| Repair / OficinaAuto / Manufacturing / Compras / AssetManagement | `operar` | **350** (magenta) |
| Financeiro / NfeBrasil / NFSe / PaymentGateway / RecurringBilling / Fiscal | `financas` | **145** (verde) |
| Essentials / Ponto | `pessoas` | **295** (roxo claro) |
| Governance / ADS / Auditoria / Cms / Connector / Officeimpresso / Superadmin | `sistema` | **200** (azul-acinzentado) |
| Jana / KB / Brief / SRS | `ia` | **220** (azul) — TOPO |
| Whatsapp / ConsultaOs | `atendimento` | **30** (laranja) — TOPO |
| TeamMcp / ProjectMgmt | `equipe` | **270** (roxo) — TOPO |

Se o módulo NÃO tem `group` declarado, agente PARA e pede aprovação de qual grupo aplicar.

### 1.2 Mapear sub-views canônicas (ghosts)

Lê `Menu::modify('admin-sidebar-menu', ...)` no DataController e lista cada `$menu->url(URL, LABEL, [...])` como **candidato a ghost**:

```
Sub-view 1: URL=/x/y, LABEL="Nome longo legacy"  → ghost key='y', label='Nome curto'
Sub-view 2: URL=/x/z, LABEL="Outra coisa"        → ghost key='z', label='Outra'
...
```

Cada sub-view vira 1 ghost no `data['ghosts']` da entry principal. Limite recomendado: 13 ghosts max (5 visíveis + 8 no `⋯ Mais`).

### 1.3 Identificar entry principal do módulo

Geralmente é o `$menu->url(...)` com `order()` mais BAIXO ou que aponta pra rota "dashboard"/"index"/"unificado" do módulo. Essa entry recebe os atributos `shortcut`, `primary`, `ghosts[]`.

### 1.4 Mapear primary action contextual por tela

Pra cada tela `Pages/<Modulo>/<SubView>/Index.tsx`, descobrir a **ação primária** (verbo de criação dominante). Regras de naming:

| Tela | Primary label sugerido | Anti-pattern |
|---|---|---|
| Lista de entidades CRUD | "Nova X" / "Novo Y" (singular) | "Adicionar nova X" (verboso) |
| Workflow (upload/import) | Verbo "Importar X" / "Enviar Y" | "Importação de X" (substantivo) |
| Detalhe/edição | (sem primary — read-mostly) | Forçar "Salvar" inline |
| Read-only (relatório/dashboard) | (omitir primary — OK) | Inventar "Atualizar" |
| Workflow create-flow | Verbo ação contextual ("Receber"/"Pagar"/"Vender") | Genérico "Nova entrada" |

### 1.5 Mapear ações features-específicas (extraOverflowItems)

Pra cada tela, identificar botões inline atuais (`<button class="os-btn ...">` no `os-page-h-r` ou similar). Pra cada um, classificar via **tabela de decisão** (Fase 2).

---

## Fase 2 — **TABELA DE DECISÃO** dos botões (obrigatória)

Pra CADA botão do header pre-pattern, decidir destino:

| Tipo | Critério | Destino canon | Exemplo Financeiro |
|---|---|---|---|
| **Duplicado-com-ghost** | Botão navega pra outra tela que JÁ está como ghost (mesmo destino) | **REMOVER** — ghost cobre | `<button>Conciliar</button>` removido (ghost `conciliacao` cobre) |
| **Ação features** | Abre dialog/sheet/modal (não-navegacional) | **`extraOverflowItems[]`** com onClick | Resumir mês / Apresentar / OCR boleto / Fechamento |
| **Primary action única** | "Nova X" / "Novo Y" / "Emitir Z" — UMA ação dominante de criação | **Zona R** via `<{Modulo}PrimaryButton>` | "Nova categoria" / "Novo recebimento" |
| **Primary multi-tipo** | UMA tela onde primary pode ter 2-3 escolhas (ex Unificado Financeiro: Receber OU Pagar OU OCR) | **`<DropdownMenu>` split-button** com items | Unificado Financeiro: `+ Novo título ▾` dropdown |
| **Botão per-linha tabela** | "Pagar"/"Editar"/"Excluir" em cada row da tabela | **INTACTO** — não é header | Botões `Pagar` no Contas a Pagar row-level |
| **Link external/admin** | Abre nova rota config (gateways/settings) | `extraOverflowItems` com onClick `window.location.href=...` | "Gateways" → `/settings/payment-gateways` |

**Anti-padrões obrigatoriamente removidos:**
- 2+ primary buttons inline (só pode 1 — escolher o dominante OU usar dropdown)
- Botão duplicado-com-ghost (Sherwin's Razor: navegação UMA vez por tela)
- Magenta `oklch(0.58 0.12 330)` no primary (canon UPOS default — usar hue do grupo)
- Labels longos (`Contas a X`, `Plano de Y`) — encurtar pra Receber/Pagar/Plano

---

## Fase 3 — **NAMING CONVENTION** (labels)

### Ghosts (sidebar/SubNav)

| Regra | Exemplo CERTO | Exemplo ERRADO |
|---|---|---|
| ≤2 palavras | `Receber` · `Bancos` · `Plano` | `Contas a Receber` · `Contas Bancárias` |
| Sem repetir nome do módulo | (em Financeiro) `Receber` | (em Financeiro) `Contas Financeiras a Receber` |
| Substantivo OU verbo (UM) | `Receber` (verbo) · `Categorias` (substantivo) | `Recebimentos e Pagamentos` (composto) |
| Português PT-BR explícito | `Cobrança` · `Bancos` | `Billing` · `Banks` |
| Sem acrônimo proprietário | `Plano` (de contas) | `DCASP` (siglas obscuras) |

**Tooltip pode carregar label completa** (F3 PageHeader futuro). Label visível = curta.

### Primary action

| Regra | Exemplo CERTO | Exemplo ERRADO |
|---|---|---|
| Verbo + objeto OU "Novo X" | `Receber` · `Nova categoria` | `Adicionar nova categoria de despesa` |
| Singular | `Novo título` | `Novos títulos` |
| Contextual ao domínio | (Vendas) `Vender` · (Repair) `Nova OS` | `Criar entidade` (genérico) |
| ≤3 palavras | `Nova conta no POS` | `Adicionar nova conta bancária ao POS legacy` |

---

## Fase 4 — **IMPLEMENTAÇÃO** (7 passos obrigatórios)

1. **Backend** (`Modules/<Modulo>/Http/Controllers/DataController.php`):
   - Entry principal declara `shortcut` + `primary{label,href,shortcut}` + `ghosts[{key,label,href}]`
   - Labels curtos conforme Fase 3

2. **Frontend wrapper** (`Pages/<Modulo>/_shared/<Modulo>SubNav.tsx`):
   - Template = `Pages/Financeiro/_shared/FinanceiroSubNav.tsx`
   - Lê `shell.menu` via `usePage()`, procura entry com `group === '<grupo_v3>'`
   - Retorna `null` se módulo desinstalado (Tier 0)

3. **Frontend primary** (`Pages/<Modulo>/_shared/<Modulo>PrimaryButton.tsx`):
   - Template = `Pages/Financeiro/_shared/FinanceiroPrimaryButton.tsx`
   - Background `oklch(0.55 0.15 <hue_do_grupo>)` — NUNCA magenta 330
   - Default ícone `<Plus/>`, override `hideIcon` se workflow não-create

4. **Telas Pages/<Modulo>/<X>/Index.tsx**:
   - Import `<{Modulo}SubNav>` + `<{Modulo}PrimaryButton>`
   - Header em 3 zonas (`os-page-h` + `os-page-h-l` + `os-page-h-r`)
   - `<{Modulo}SubNav active="<key>" hidePrimary extraOverflowItems={[...]}/>`
   - `<{Modulo}PrimaryButton onClick={...}>Verbo+X</{Modulo}PrimaryButton>` (omitir se read-only)
   - Botões legacy: aplicar Fase 2 tabela de decisão

5. **Split-button** (apenas onde primary tem multi-tipo, ex Unificado Financeiro):
   - `<DropdownMenu>` shadcn com trigger custom estilizado verde do grupo
   - 2-3 items + `<DropdownMenuSeparator>` opcional

6. **Charter da tela** (`*.charter.md`):
   - Atualizar com nota "header pattern ADR 0182 OK + skill pageheader-canon aplicada"

7. **PR atômico** (commit-discipline ≤300 LOC por wave de 3-4 telas):
   - Body com matriz por tela (labels curtos + primary + ghosts + extraOverflowItems)
   - Label `module-grades-new-module-allowed` aplicada

---

## Fase 5 — **VALIDAÇÃO VISUAL OBRIGATÓRIA** (browser MCP)

⚠️ **OBRIGATÓRIO**: agente NÃO PODE encerrar tarefa sem validar visualmente. Wagner regra 2026-05-21: *"vai ter que olhar depois de fazer. e vai ter que testar para ver se o header esta no padrão"*.

### 5.1 Pré-requisito: deploy completo

Aguardar ~90-120s após merge pra Vite/Hostinger rebuildar assets. Sinal de deploy completo: cor primary muda de magenta default pra hue do grupo.

### 5.2 Script JS canon pra validação (rodar no browser MCP)

```javascript
JSON.stringify({
  url: location.pathname,
  module: '<MODULO_AQUI>',
  expected_hue: 145, // 145 financas | 60 vender | 350 operar | 295 pessoas | 200 sistema | 220 ia | 30 atendimento | 270 equipe

  // 1. Tabs ARIA renderizam
  tabs: [...document.querySelectorAll('[role="tab"]')].map(t => ({
    label: t.textContent.trim(),
    active: t.getAttribute('aria-selected') === 'true',
  })),

  // 2. Active sempre visível inline
  active_inline: !!document.querySelector('[role="tab"][aria-selected="true"]'),

  // 3. Labels curtos (≤2 palavras)
  labels_short: [...document.querySelectorAll('[role="tab"]')]
    .every(t => t.textContent.trim().split(' ').length <= 2),

  // 4. Primary tem hue do grupo (NÃO magenta canon UPOS default)
  primary: [...document.querySelectorAll('.os-btn.primary')].map(b => {
    const bg = getComputedStyle(b).backgroundColor;
    return {
      label: b.textContent.trim().slice(0,30),
      bg,
      hue_correct: bg.includes('145') || bg.includes('60') || bg.includes('350')
                 || bg.includes('295') || bg.includes('200') || bg.includes('220')
                 || bg.includes('30 ') || bg.includes('270'),
      not_magenta: !bg.includes('0.58 0.12 330'),
    };
  }),

  // 5. Overflow ⋯ Mais existe se ghosts > 5 OU extraOverflowItems > 0
  overflow_present: !!document.querySelector('button[aria-label*="Mais"]'),
})
```

### 5.3 Gate de aprovação canon (✓/⚠️ por tela)

Pra cada tela do módulo, agente DEVE confirmar:

| Check | Critério | ✓ |
|---|---|---|
| C1 Tabs renderizam | `tabs.length > 0` | |
| C2 Active visible | `active_inline === true` | |
| C3 Labels curtos | `labels_short === true` | |
| C4 Primary hue correto | TODO primary tem `hue_correct=true` E `not_magenta=true` | |
| C5 Sem 500 server error | `document.querySelector('h1')?.textContent !== 'Server Error'` | |
| C6 Overflow funcional | `overflow_present === true` (se >5 ghosts ou extras) | |

**Se qualquer C falhar**: agente reporta tabela ⚠️ + propõe fix + abre novo PR pequeno. NÃO encerra "OK" se há ⚠️.

### 5.4 Output esperado do agente

```
## Validação visual pós-implementação (Módulo X)

| Tela | C1 | C2 | C3 | C4 | C5 | C6 |
|---|---|---|---|---|---|---|
| /x/dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| /x/sub1     | ✅ | ✅ | ✅ | ⚠️ magenta | ✅ | ✅ |
...

⚠️ 1 tela com primary magenta → criando PR fix...
```

---

## Anti-padrões catalogados (evitar repetir)

| Anti-padrão | Causa | Fix canon |
|---|---|---|
| Primary magenta `oklch(0.58 0.12 330)` | CSS canon UPOS `.os-btn.primary` legacy | `<{Modulo}PrimaryButton>` com hue do grupo |
| Ghost ativo invisível no overflow | `activeGhostKey` index >= maxVisible | `PageHeaderTabs` auto-promoção (já implementado) |
| Botão duplicado-com-ghost | Refactor incompleto (tela tinha botão + DataController declarou ghost) | REMOVER botão inline — ghost cobre |
| Label longo (`Contas a Receber`) | DataController copiou label do UPOS legacy | Encurtar (`Receber`) — label completa vira tooltip futuro |
| Primary multi-tipo único | Tela mostra ambos types mas só 1 primary genérico | `<DropdownMenu>` split-button (caso Unificado Financeiro) |
| Tela read-only com primary forçado | Pattern aplicado cegamente | OK omitir primary (Fluxo/Relatórios) |
| Cor hue invertida entre módulos | Sub-agent não conferiu tabela 1.1 | Validar via Fase 5 — gate barra ⚠️ |

---

## Estado de propagação por módulo (atualizar a cada wave)

| Módulo | Grupo v3 | Hue | SubNav | PrimaryButton | Telas migradas |
|---|---|---|---|---|---|
| Financeiro | financas | 145 | ✅ | ✅ | 11/12 (Caixa 500 prod) |
| Sells / Vendas | vender | 60 | ❌ | ❌ | 0 |
| Crm | vender | 60 | ❌ | ❌ | 0 |
| ProductCatalogue | vender | 60 | ❌ | ❌ | 0 |
| Repair / OficinaAuto | operar | 350 | ❌ | ❌ | 0 |
| Manufacturing | operar | 350 | ❌ | ❌ | 0 |
| Compras | operar | 350 | ❌ | ❌ | 0 |
| AssetManagement | operar | 350 | ❌ | ❌ | 0 |
| NfeBrasil / NFSe | financas | 145 | ❌ | ❌ | 0 |
| Essentials / Ponto | pessoas | 295 | ❌ | ❌ | 0 |
| Governance / ADS / Auditoria | sistema | 200 | ❌ | ❌ | 0 |
| Cms / Connector / Officeimpresso | sistema | 200 | ❌ | ❌ | 0 |

Próximas waves (sub-agents paralelos, ~1.5-2 dias):
1. Wave Vender (Sells/Crm/ProductCatalogue)
2. Wave Operar (Repair/Compras/Manufacturing/AssetManagement)
3. Wave Fiscal (NfeBrasil/NFSe — mesmo grupo financas)
4. Wave Pessoas+Sistema (Essentials/Ponto + Governance/Cms/Connector)

---

## Refs

- [ADR 0180](../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) — Sidebar v3 5 grupos
- [ADR 0182](../../../memory/decisions/0182-pageheadertabs-canon-pattern-telas.md) — PageHeaderTabs canon
- [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant
- [ADR 0110](../../../memory/decisions/0110-tipografia-canon-h1-subtitle.md) — Tipografia canon
- [Matriz diferenças](../../../memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md) — F1-F12 fixas + V1-V9 variáveis
- Templates Financeiro:
  - `resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx`
  - `resources/js/Pages/Financeiro/_shared/FinanceiroPrimaryButton.tsx`
  - `resources/js/Pages/Financeiro/Unificado/Index.tsx` (caso especial split-button dropdown)
- PRs Fase 5 Financeiro: #1363 → #1371 (sequência canônica completa)
- Wagner reviews 2026-05-21 que originaram a skill (smoke prod das 12 telas + tabela diferenças + matriz + protocolo de agente)
