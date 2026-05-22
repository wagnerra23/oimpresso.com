---
name: pageheader-canon
description: ATIVAR quando agente vai aplicar o PageHeader canon (ADR 0180/0182) em módulo novo — user pede "aplicar pageheader canon no módulo X", "padronizar header do <Modulo>", "/pageheader-canon <Modulo>", "header v3 nas telas Y", OU em Edit/Write em `resources/js/Pages/<Mod>/<Tela>/Index.tsx` que tenha sub-navegação (sub-views no DataController). **2 modos** — (A) **Index/Show modo NAV** com SubNav+PrimaryButton (3 zonas) e (B) **Edit/Create modo FOCO** sem SubNav (página de form sem distração lateral, pattern Notion/Linear, Fase 4-bis). Skill carrega — (1) **algoritmo de descoberta** do módulo (cruzar DataController.php + Pages/<Mod>/**/*.tsx pra mapear sub-views + primary contextual + ações features), (2) **tabela de decisão** dos botões (duplicado-com-ghost REMOVE / features → extraOverflowItems / primary "Nova X" → Zona R / per-linha INTACTO), (3) **naming convention** de labels (≤2 palavras, sem repetir nome do módulo), (4) **hue OKLCH per-grupo** canon (financas=145, vender=60, operar=350, pessoas=295, sistema=200, ia=220, atendimento=30, equipe=270), (5) **validação POST-implementação OBRIGATÓRIA** via browser MCP (script JS valida labels curtos + auto-promove + cor primary correta + bg NÃO magenta 330 — checks C2/C3/C6 NÃO se aplicam a Edit/Create FOCO) e (6) **gate ✓/⚠️** que FALHA o PR se alguma tela não passar. Tier B auto-trigger por description.
---

# Skill `pageheader-canon` — Protocolo do agente pra aplicar header v3

> Skill canon pra agente (sub-agent ou parent Claude) propagar o pattern PageHeader (ADR 0180/0182) em módulo. Cobre **descoberta → decisão → implementação → validação visual obrigatória**.

## Quando ativar

- User pede: "aplicar pageheader canon no <Modulo>", "padronizar header do X", "/pageheader-canon <Modulo>"
- Edit em `Pages/<Modulo>/<Tela>/Index.tsx` com sub-navegação (DataController declara ghosts)
- Refactor de tela legacy com `<button class="os-btn primary">` magenta inline
- Tela nova sendo criada num módulo já canonizado (Financeiro/futuro Vendas/etc)
- Edit em `Pages/<Modulo>/<X>/{Edit,Create,Form,Novo,Emitir}.tsx` — aplicar **modo FOCO** (Fase 4-bis, sem SubNav)

## 2 modos do PageHeader canon

| Modo | Quando | Zona L (esq) | Zona C (meio) | Zona R (dir) | Decisão Wagner |
|---|---|---|---|---|---|
| **NAV** (Index/Show) | Listagem + visualização | h1 + subtítulo + descrição | `<{Modulo}SubNav active="key" hidePrimary />` | `<{Modulo}PrimaryButton>+ Novo X</>` (Index) OR Voltar/Imprimir (Show) | ADR 0182 base |
| **FOCO** (Edit/Create/Form) | Preenchimento form | h1 contextual + subtítulo + descrição | **VAZIO** (sem SubNav) | **VAZIO** OR só `<Button outline>← Voltar</>` | Wagner 2026-05-22 |

**Regra de ouro**: se o arquivo é `Index.tsx` ou `Show.tsx` → modo NAV. Se é `Edit.tsx`/`Create.tsx`/`Form.tsx`/`Novo.tsx`/`Emitir.tsx` → modo FOCO. Ghost tabs ARIA são feature pra navegar entre SEÇÕES do módulo, não pra ficar visível enquanto usuário preenche form.

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

4. **Telas Pages/<Modulo>/<X>/Index.tsx** (LISTAGEM):
   - Import `<{Modulo}SubNav>` + `<{Modulo}PrimaryButton>`
   - Header em 3 zonas (`os-page-h` + `os-page-h-l` + `os-page-h-r`)
   - `<{Modulo}SubNav active="<key>" hidePrimary extraOverflowItems={[...]}/>`
   - `<{Modulo}PrimaryButton onClick={...}>Verbo+X</{Modulo}PrimaryButton>` (omitir se read-only)
   - Botões legacy: aplicar Fase 2 tabela de decisão

4-bis. **Telas Pages/<Modulo>/<X>/Edit.tsx + Create.tsx + Form.tsx** (FOCO mode — Wagner regra 2026-05-22):

   **NUNCA** importar `<{Modulo}SubNav>` em Edit/Create/Form. Edit/Create são páginas FOCO — usuário está preenchendo formulário, ghost tabs lateral viram distração (pattern Notion/Linear/Stripe forms 2026).

   **Pattern canon** — Zona L cheia + Zona R opcional com SECONDARY action:

   ```tsx
   <header className="os-page-h">
     <div className="os-page-h-l">
       <h1>{isEdit ? 'Editar escala' : 'Nova escala'} <span className="text-stone-400 font-normal">· {subtitulo_contextual}</span></h1>
       <p>{isEdit ? `Edição de ${entity.nome}` : 'Descrição da ação.'}</p>
     </div>
     <div className="os-page-h-r">
       {/* Zona R apenas SECONDARY (Voltar). Sem PrimaryButton (já estamos criando/editando). Sem SubNav. */}
       <Button variant="outline" size="sm" asChild>
         <a href="/{modulo}/{listagem}"><ArrowLeft size={14} className="mr-1.5" /> Voltar</a>
       </Button>
     </div>
   </header>
   ```

   **Variantes permitidas** (escolher 1 por tela):

   | Variante | Quando aplicar | Zona R |
   |---|---|---|
   | **V1 Voltar inline** | Caso default — Edit/Create com botão voltar acessível | `<Button outline>← Voltar</Button>` |
   | **V2 Sem Zona R** | Form longo (>10 campos) onde header limpo é prioridade. Voltar fica no rodapé do form. | (vazia) |
   | **V3 Voltar + Action secundária** | Show.tsx com ações Imprimir/Exportar/Duplicar relevantes (raro em Edit/Create puros) | `<Button outline>← Voltar</Button> <Button outline>Imprimir</Button>` |

   **PROIBIDO** em Edit/Create/Form (anti-pattern Tier 0):
   - ❌ `<{Modulo}SubNav>` — quebra modo FOCO, traz distração lateral
   - ❌ `<{Modulo}PrimaryButton>` "Nova X" — já estamos criando/editando UM X
   - ❌ Ghost tabs ARIA inline — desfocaliza usuário
   - ❌ Múltiplos botões action no header (>2) — campo do form é o foco
   - ❌ Tabs internas dentro do form sem indicação clara (use accordion ou wizard se precisar)

   **Show.tsx caso especial** (visualização read-only, não-form):
   - **PODE** incluir `<{Modulo}SubNav active="<key>" hidePrimary />` — Show é "navegação dentro do registro" não "preenchimento"
   - Pattern híbrido aceito: SubNav + ações Voltar/Imprimir/Duplicar
   - Exceção do FOCO mode porque Show é leitura, não ação focada

   **Razão pragmática:**
   - Hick's Law: form com 10+ campos + tabs laterais = >15 elementos competindo por atenção. FOCO mode reduz pra 1 form + 1 botão Voltar = 2 elementos.
   - Larissa @ ROTA LIVRE biz=4 1280×1024: tela apertada, ghost tabs ocupam viewport útil do form.
   - Pattern Notion/Linear/Stripe/Vercel 2026: pages de edição minimalistas, navegação fica fora.
   - Reduz cognitive load no momento crítico (preencher campo certo, validar dados).

   **Exemplos canônicos no repo:**
   - ✅ `resources/js/Pages/Financeiro/Unificado/Novo.tsx` (V2 sem Zona R)
   - ✅ `resources/js/Pages/Ponto/Escalas/Form.tsx` (V1 Voltar inline) — pós Wave Ponto 2026-05-22
   - ✅ `resources/js/Pages/Ponto/Colaboradores/Edit.tsx` (V1)
   - ✅ `resources/js/Pages/Ponto/Importacoes/Create.tsx` (V1)
   - ✅ `resources/js/Pages/Ponto/Intercorrencias/Create.tsx` (V1)

   **Backlog Edit/Create do projeto** (32 telas mapeadas 2026-05-22 — aplicar quando wave do módulo chegar):

   | Módulo | Tela | Pattern atual | Ação na próxima wave |
   |---|---|---|---|
   | Cliente | Create + Edit | sem header padrão | aplicar FOCO V1 |
   | Essentials | Knowledge Create+Edit, Todo Create+Edit | sem header padrão | FOCO V1 |
   | Financeiro | Unificado/Novo | ✅ FOCO V2 (canon) | manter |
   | NFSe | Emitir | PageHeader simples | FOCO V1 |
   | OficinaAuto | ServiceOrders Create+Edit, Vehicles Create+Edit | PageHeader simples | FOCO V1 |
   | Produto | Create + Edit | sem header padrão | FOCO V1 |
   | Purchase | Create + Edit | PageHeader simples | FOCO V1 (form fat — talvez V2) |
   | RecurringBilling | Planos Create + Edit | sem header padrão | FOCO V1 |
   | Repair | DeviceModels Create+Edit, JobSheet Create+Edit | PageHeader simples | FOCO V1 |
   | Sells | Create + Edit | PageHeader simples (Create) / sem (Edit) | FOCO V2 (POS fat) |
   | StockAdjustment | Create | PageHeader simples | FOCO V1 |
   | StockTransfer | Create | PageHeader simples | FOCO V1 |
   | TransactionPayment | Edit | sem header padrão | FOCO V1 |
   | ads | Skills/Edit | PageHeader simples | FOCO V1 |
   | Ponto | Colaboradores/Edit, Escalas/Form, Importacoes/Create, Intercorrencias/Create | ✅ FOCO V1 (Wave 22/05) | manter |

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

Pra cada tela do módulo, agente DEVE confirmar conforme o **modo** da tela:

#### Modo NAV (Index/Show) — 6 checks

| Check | Critério | Aplica em | ✓ |
|---|---|---|---|
| C1 Tabs renderizam | `tabs.length > 0` | NAV | |
| C2 Active visible | `active_inline === true` | NAV | |
| C3 Labels curtos | `labels_short === true` | NAV | |
| C4 Primary hue correto | TODO primary tem `hue_correct=true` E `not_magenta=true` | NAV (Index com PrimaryButton) | |
| C5 Sem 500 server error | `document.querySelector('h1')?.textContent !== 'Server Error'` | NAV + FOCO | |
| C6 Overflow funcional | `overflow_present === true` (se >5 ghosts ou extras) | NAV | |

#### Modo FOCO (Edit/Create/Form) — 4 checks (C2/C3/C6 não aplicam — não há SubNav)

| Check | Critério | ✓ |
|---|---|---|
| F1 Header renderiza | `document.querySelector('header.os-page-h')` existe | |
| F2 NÃO tem SubNav | `document.querySelectorAll('[role="tab"]').length === 0` (canon: Edit/Create sem ghost tabs) | |
| F3 NÃO tem PrimaryButton "+ Nova X" | `document.querySelector('.os-btn.primary')` ausente OR só botões secundários outline | |
| C5 Sem 500 server error | `document.querySelector('h1')?.textContent !== 'Server Error'` | |

Script JS adaptado pra FOCO:

```javascript
JSON.stringify({
  url: location.pathname,
  module: '<MODULO_AQUI>',
  mode: 'FOCO', // edit/create/form

  // F1 — Header canon presente
  header_present: !!document.querySelector('header.os-page-h'),

  // F2 — NÃO há SubNav (ghost tabs ARIA)
  no_subnav: document.querySelectorAll('[role="tab"]').length === 0,

  // F3 — NÃO há PrimaryButton "+ Nova X" (canon: já estamos criando X)
  no_primary_create: !document.querySelector('.os-btn.primary'),

  // C5 — Sem 500
  no_server_error: document.querySelector('h1')?.textContent !== 'Server Error',
})
```

**Se qualquer check falhar** (NAV ou FOCO): agente reporta tabela ⚠️ + propõe fix + abre novo PR pequeno. NÃO encerra "OK" se há ⚠️.

### 5.4 Output esperado do agente

```
## Validação visual pós-implementação (Módulo X)

### Modo NAV (Index/Show)
| Tela | Mode | C1 | C2 | C3 | C4 | C5 | C6 |
|---|---|---|---|---|---|---|---|
| /x/dashboard      | NAV | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| /x/sub1           | NAV | ✅ | ✅ | ✅ | ⚠️ magenta | ✅ | ✅ |

### Modo FOCO (Edit/Create/Form)
| Tela | Mode | F1 header | F2 sem SubNav | F3 sem PrimaryBtn | C5 |
|---|---|---|---|---|---|
| /x/sub1/create    | FOCO | ✅ | ✅ | ✅ | ✅ |
| /x/sub1/{id}/edit | FOCO | ✅ | ⚠️ tem SubNav errado | ✅ | ✅ |

⚠️ 1 tela NAV com primary magenta + 1 tela FOCO com SubNav indevido → criando PR fix...
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
| **SubNav em Edit/Create** (Wagner 2026-05-22) | Sub-agent aplicou pattern Index cegamente em Edit/Create | Modo FOCO Fase 4-bis: sem SubNav, sem PrimaryButton, só Voltar opcional |
| **PrimaryButton "+ Nova X" em Create** | Sub-agent não percebeu que já estamos criando X | Remover — Create já é a ação de criar UM X (PrimaryButton só faz sentido em Index) |
| **Ghost tabs ARIA visíveis durante preenchimento form** | Distrai usuário em campo crítico (Larissa 1280×1024 perde viewport útil) | Modo FOCO: Zona C vazia em Edit/Create |
| **Botão "Voltar" no rodapé do form em vez do header** | Pattern legacy UPOS — usuário precisa scroll pra cancelar | Voltar fica em Zona R do `os-page-h` (acessível imediatamente) |

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
