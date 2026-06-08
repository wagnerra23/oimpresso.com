# 2026-05-13 — design-arte Sells/Create (benchmark estratégico UX/UI)

> **Agent:** `design-arte` (subagent Opus, definido em `.claude/agents/design-arte.md`)
> **Trigger:** Wagner pediu Capterra de design da tela `/sells/create` (Inertia MWART) em paralelo a 2 features sendo implementadas pelo parent (Esc listener + autosave draft).
> **Output:** 2 artefatos — `memory/requisitos/Sells/CAPTERRA-DESIGN-FICHA.md` + este session log.
> **Persona alvo:** Larissa @ ROTA LIVRE biz=4 (vestuário Termas do Gravatal/SC, 1280px, ~5 vendas/dia, atende telefone no meio, **não-técnica**, 99% volume oimpresso).
> **Charter:** [`resources/js/Pages/Sells/Create.charter.md`](../../resources/js/Pages/Sells/Create.charter.md) (status live, version 1, last_validated 2026-05-08).
> **Nota final:** **68/100** (entre Bling ~52 e Linear ~92).
> **NÃO commitou. NÃO editou código.**

---

## Fase 1 — RESEARCH CLIENTE (necessidade real)

### Persona enxuta

**Larissa Fernandes** (`larissa-04`, user_id 10, role `Admin#4`) é dona/operadora da ROTA LIVRE em Termas do Gravatal/SC — loja de vestuário CNAE 4781-4/00. Opera num **monitor 1280px** com 4 anos consecutivos de uso diário (cadastro 2021-02-01, 17.251+ vendas, 99% do volume oimpresso). **Não é técnica.** Confirmado em `cliente-rotalivre.md` (canônico):

- **Quem é:** Larissa, dona operacional, vendedora-balconista também. Suporta perfil "Admin#4" mas também "Vendas#4" e "Caixa#4" via outros users.
- **Dispositivo:** desktop 1280px (otimização obrigatória — `/sells` lista quebrou em abril/2026 com 21 colunas; teve fix `columnDefs targets [11,12,21,22,23] visible:false`).
- **Frequência:** **diária**. Pico 14h-17h SP. ~5 vendas/dia média, ticket R$ 100-500.
- **Volume:** 17.251+ vendas (~99% do sistema). Toda regressão impacta o **único cliente real**.

### Jobs-to-be-done (do canônico + RUNBOOK §1 + SPEC §2)

1. **Cadastrar venda rápido** com cliente walk-in (genérico "Cliente Balcão") + produtos buscados por SKU ou nome + 1 pagamento à vista ou split.
2. **Não perder rascunho** quando interrompida pelo telefone no meio do cadastro (RUNBOOK §6 explicitamente).
3. **Editar `transaction_date` retroativo** sem que sistema "conserte" — diferença até 17h entre `transaction_date` e `created_at` é fluxo dela (vendas balcão registradas em lote no fim do dia), não bug.
4. **Aceitar valor exato OU troco OU registro de "falta fechar"** — payment split com indicador visual.
5. **Não ver coluna nem campo que ela não usa** — 18 campos visíveis do Blade legacy eram 3 telas de scroll; ROTA LIVRE só usa 8 com frequência.

### 3 fricções conhecidas (vindas de session logs + memória cliente)

1. **Form Blade legacy** rendia `disabled=false` como ativo no shim `Form::` — 2026-04-24 fix `7fbfbdc7`. Larissa relatou campo busca produto travado.
2. **format_date +3h drift** — Larissa **decorou os horários errados**; correção visual = regressão percebida ([ADR 0066](../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)). Por isso o `defaultDatetime` da tela usa `format_now_local`, NÃO `format_date` (RUNBOOK §3 pegadinha #1).
3. **Localização disabled** — role `Vendas#4` sem `location.4` travou autocomplete produto. Fix dados 2026-04-24. Por isso `default_location` precisa estar preenchido antes da busca liberar.

**Nada de "Larissa quer X" inventado.** Todos os jobs/fricções têm fonte direta em `memory/reference/cliente-rotalivre.md` ou nos PRs do dia 2026-04-24.

---

## Fase 2 — PESQUISA SOTA 2026

### 5 leaders globais avaliados (1 parágrafo cada)

**Linear** (issue tracker SaaS B2B, $400M valuation). Padrão UX característico: **strategic minimalism** — "every element on screen must earn its place". Command palette **Cmd+K como gold-standard**: criar issue, mudar assignee, navegar — tudo via teclado, mouse opcional. Modals trap focus + Esc dismissível. Referência canon para "keyboard-first SaaS B2B". ([LogRocket — Linear design pattern](https://blog.logrocket.com/ux-design/linear-design/))

**Shopify Polaris** (admin varejo, 2026 unified web components). Padrão UX: **pattern library padronizada** — picker experiences pra browse+select multi-options (locations, customers), intentional loading states ("continuous flow"), standardized card layouts. Em abril/2026 toda Admin Polaris virou web components — menor + mais rápida + Custom Element Manifest pra IDE auto-complete. ([Polaris React docs](https://polaris-react.shopify.com/patterns), [Shopify partner blog 2025](https://www.shopify.com/partners/blog/polaris-unified-and-for-the-web))

**Stripe Dashboard / Payment Element** (B2B pagamentos). Padrão: **inline validation com error recovery automático**, 100+ payment methods unificados em 1 component. Forms financeiros complexos resolvidos com clarity em hierarquia (label + helper + erro visual semântico) + redução de cognitive load via Payment Element ser configurado no dashboard, não no código. ([Stripe Payment Element docs](https://docs.stripe.com/payments/payment-element))

**Notion / Airtable** (knowledge / DB SaaS). Padrão: **progressive disclosure** — esconder complexidade até user pedir. Forms revelam campos opcionais via accordion / "Show more" / blocks expansíveis. Drafts editáveis com autosave nativo. Modular blocks = user constrói próprio form. Referência pra `<details>` colapsável com persistência. ([Onething B2B SaaS UX 2026](https://www.onething.design/post/b2b-saas-ux-design))

**Apple HIG / Material Design 3**. Base de tokens, focus visible, contrast WCAG 4.5:1 mínimo, touch targets ≥44px, mobile-first. Shadcn/Radix (que oimpresso usa) já internaliza muito do Apple HIG via Radix primitives + `focus-visible:ring-2`.

### 4 concorrentes BR (UX padrão PME)

**Bling** (300k+ users, R$ 39-499/mês). Padrão observável: dashboard consolidado em **até 3 contas** (multi-business explícito na UI). Tela de cadastro de venda usa UI ~Bootstrap legacy. Forças: **gerenciador de transições customizável** (estados de pedido configuráveis "Open → Separating") + automações ("ao mudar pra Separating, baixar estoque"). UI honestamente não é estado-da-arte mas serve aos 300k. ([Bling main](https://www.bling.com.br/), [Bling 2026 review](https://www.compararsoftware.com.br/erp/articulos/bling-erp-o-que--como-funciona-preos-e-avaliao-2026))

**Tiny ERP (Olist)**. Autodescrito "visual limpo intuitivo, sem excesso de botões complexos". Reclame Aqui 2025-2026: **76.3% das reclamações resolvidas** (média 15d21h resposta). Reclamações majoritárias pós-aquisição Olist são sobre **suporte e mudanças unilaterais de plano**, não UI — UI é vista como aceitável. ([Olist Tiny recursos](https://tiny.com.br/recursos/), [Reclame Aqui Tiny](https://www.reclameaqui.com.br/empresa/tiny-erp/))

**Omie** vs **Conta Azul**: Omie pra estrutura média (ERP + CRM + conta digital integrada), Conta Azul pra serviço/pequeno comércio (financeiro-first). Ambos PT-BR nativos, fiscal BR forte. UI tende mais densa que Linear/Notion mas dentro do esperado pra ERP brasileiro. ([Jestor blog ERP comparison 2026](https://blog.jestor.com/omie-vs-conta-azul-melhor-erp-pequenas-empresas/))

### Especializado 2026 (não cobre Sells/Create direto, fica nota mental)

- **AI-native dashboards** (Linear AI, Notion Q&A, Stripe Atlas Agent) — futuro do "preencher form falando com agente". Charter Sells/Create não declara. Backlog ADR feature-wish.
- **Conversational UI emerging** — "chat-first vs form-first". oimpresso já tem Jana (Modules/Jana) que potencialmente conversaria criando venda. Fora do escopo desta análise.

---

## Fase 3 — COMPARA EM 15 DIMENSÕES UX/UI

### Material lido
- [`Pages/Sells/Create.tsx`](../../resources/js/Pages/Sells/Create.tsx) (1160 LOC inteiras).
- [`Create.charter.md`](../../resources/js/Pages/Sells/Create.charter.md) (Mission + Goals + 7 Non-Goals + 7 Anti-patterns + 3 UX targets).
- [`RUNBOOK-create.md`](../requisitos/Sells/RUNBOOK-create.md) (11 seções; descreve passos migração + pegadinhas).
- [`sells-create-visual-comparison.md`](../requisitos/Sells/sells-create-visual-comparison.md) (canon interno comparativo 8 dim, approved Wagner 2026-05-08).
- [`SPEC.md`](../requisitos/Sells/SPEC.md) US-SELL-001..009 (todas mergeadas exceto US-SELL-007 que é o backlog dos 2 features paralelas).
- [`cliente-rotalivre.md`](../reference/cliente-rotalivre.md) (canônico cliente).
- 14 componentes em `Pages/Sells/_components/` (CustomerSearchAutocomplete, ProductSearchAutocomplete, PaymentRow, FsmActionPanel, SaleSheet, etc).

### Avaliação dimensão a dimensão (cada nota justificada)

#### D-01 — Hierarquia visual · peso 3 · **8/10**

Charter declara tipografia canon `h1 24px, pill 12px, KPI value 36px`. Código (`Create.tsx:406`) usa `text-2xl font-semibold tracking-tight` no h1. KPIs (`l.477`) usam `text-4xl font-semibold tabular-nums`. Hierarquia bem implementada — h1 + subtitle (`text-sm text-muted-foreground`) + filter pills + KPIs gigantes + CardTitle `text-base` + Labels small. **Pareia Linear e Polaris.** Perde 2 pts: o `subtitle` repete "Local: X" que já está nos campos do form abaixo (ruído).

#### D-02 — Densidade informacional · peso 3 · **8/10**

Triagem 18→8 visíveis + 10 colapsáveis em `<details>` ([Create.tsx:878-1082](../../resources/js/Pages/Sells/Create.tsx)). Persiste `open` em `localStorage.oimpresso.sells.create.advanced.open`. **Pareia progressive disclosure Notion/Linear.** Cards `p-6 space-y-6` = 24px (mais espaçoso que canon `os-page.jsx` Cowork mas adequado pra cliente non-técnico). Perde 2 pts: o bloco "Debug · contract recebido do controller" ([l.1085-1120](../../resources/js/Pages/Sells/Create.tsx)) ainda está em produção — charter US-SELL-005 prometia remover. **Reduz percepção de qualidade.**

#### D-03 — Navegação primária · peso 3 · **6/10**

AppShellV2 sidebar light + breadcrumb inline (`SellsCreate.layout = page => <AppShellV2>{page}</AppShellV2>`). Pills com scroll-spy IntersectionObserver (`l.376-397`) marca pill ativa conforme scroll. **Excelente UX local.** Perde 4 pts pelo gap conhecido em `visual-comparison.md` §1: **sem topnav módulo horizontal** (afeta 78 telas MWART, não só Sells — backlog separado). Compara mal vs Linear que tem nav-tree visível sempre.

#### D-04 — Sistema de design · peso 2 · **8/10**

Tailwind 4 + shadcn primitives (Card, Input, Label, Select, Button, Textarea). `dropdownEntries` helper local evita gotcha de `Object.entries` em props UltimatePOS (auto-mem catalogado). Tokens `bg-background bg-muted/30 border-border text-foreground text-muted-foreground` usados consistentemente — **zero cor crua** detectada na varredura. Dark mode coberto por tokens. **Pareia Polaris tokens.** Perde 2 pts: cor blue/amber/emerald em KPI Status pgto e payment indicator é semântico mas direta (`bg-emerald-50 dark:bg-emerald-950/30` em vez de token `--success-bg`). Charter anti-pattern explicita "❌ Cor crua `bg-(gray|red|...)-N`" — `bg-blue-50` está no limite. Aceitável mas não ideal.

#### D-05 — Microcopy PT-BR · peso 2 · **8/10**

PT-BR consistente: "Adicionar venda" / "Dados da venda" / "Mais opções (frete, fatura, comissão, prazo, imposto)" / "Falta R$ X,XX pra fechar" / "Troco de R$ Y,YY" / "Confere ✓". Validação no footer fala humano: "Adicione pelo menos 1 produto" / "Selecione o local da venda" / "Pagamento falta fechar". **Pareia Stripe clarity.** Helper "Digite ≥2 caracteres pra buscar. Limpe pra voltar ao cliente padrão." é excelente. Perde 2 pts: "Atalho: Ctrl+Enter pra salvar" no footer está OK mas não diz como **acionar Cancelar** (Esc seria natural — voltar a D-09).

#### D-06 — Empty states · peso 2 · **9/10**

`<EmptyState icon="package" title="Nenhum produto adicionado" description="Use a busca acima ou aperte / pra focar (em breve)." action={<Button>Buscar produto</Button>}/>` ([l.620-629](../../resources/js/Pages/Sells/Create.tsx)). **Pareia Polaris empty state pattern exactly.** Componente shared, ícone semântico, CTA orientado. Perde 1 pt: o description menciona `aperte / pra focar (em breve)` — promessa quebrada porque US-SELL-007 (que implementa `/`) ainda é backlog. Diz "em breve" mas pode ser visto há meses. Trocar pra "Use a busca acima" e remover o aside até `/` existir.

#### D-07 — Loading + skeleton · peso 2 · **5/10**

Loader2 spinner só aparece no botão Submit (`{processing && <Loader2 className="mr-2 h-4 w-4 animate-spin"/>}` l.1148). **Nada de skeleton no inicial.** Larissa em conexão SC vê KPIs zerados (Itens 0, Total R$ 0,00) durante render antes do React hidratar — parece broken. Linear/Polaris fazem skeleton dos KPIs e do form pra parecer rápido. Perde 5 pts inteiros: gap real, fácil resolver (S, ~2h), valor moderado (não bloqueador mas polish).

#### D-08 — Error UX · peso 2 · **6/10**

Footer mostra `{Object.values(errors)[0] as string}` ([l.1131](../../resources/js/Pages/Sells/Create.tsx)) — só a primeira msg de erro do useForm. Função `onError` faz scroll-to-section da primeira chave de erro (`sectionMap` l.339-346) — boa heurística. **Problema:** se erro estiver em campo dentro de `<details>` fechado ("Mais opções"), Larissa scrola pra lá mas a seção está colapsada — não vê o campo. Perde 4 pts. Solução: `<FormError errors={errors.products}/>` por campo + auto-open do `<details>` quando há erro dentro. ROI alto.

#### D-09 — Atalhos teclado · peso 1 · **4/10**

Apenas `Cmd/Ctrl+Enter` implementado ([l.355-365](../../resources/js/Pages/Sells/Create.tsx)). **Sem `Esc`**, **sem `/`** pra focar busca produto. Linear é canon Cmd+K + 30+ shortcuts. Apple HIG: Esc dismiss é obrigação universal. Charter Non-Goals confirma `❌ Atalho Esc/Cmd+Enter (US-SELL-007 backlog)` — gap assumido. Perde 6 pts. **`/` não é P0 (Larissa não é power user)** mas **Esc é dever a11y** — está sendo implementado em paralelo pelo parent. Boa decisão.

#### D-10 — Mobile/touch 1280px · peso 1 · **8/10**

`container mx-auto py-6 px-8 max-w-7xl` ([l.469](../../resources/js/Pages/Sells/Create.tsx)) cabe em 1280px sem horizontal scroll. KPIs `grid-cols-2 md:grid-cols-4` empilha 2x2 em mobile. Inputs `h-8` (32px) na tabela produtos = abaixo dos 44px touch target Apple HIG, mas Larissa é desktop-only (mouse) então OK. Pills wrap em `flex-wrap`. **Pareia Polaris mobile-first** para o range que importa. Perde 2 pts pelos inputs h-8 na tabela (touch target abaixo do recomendado se algum dia Larissa usar tablet).

#### D-11 — A11y WCAG 2.1 AA · peso 1 · **6/10**

Pros: `aria-label="Seções do cadastro"` na nav (l.426), `aria-current={isActive}` nas pills, `aria-label={Quantidade de ${p.name}}` nos inputs editáveis da tabela produtos. Focus shadcn herdado (`focus-visible:ring-2` em Button/Input). Contrast OK tokens shadcn. **Contras:** sem Esc listener (WCAG 2.1.1 keyboard-only navigation viola); sem skip-link pra pular header sticky; `<details>` sem `aria-expanded` explícito (HTML5 fornece nativo mas screen readers variam). Perde 4 pts. Auditoria full com `design:accessibility-review` (skill Anthropic) recomendada.

#### D-12 — Feedback ações · peso 1 · **5/10**

`onError` scroll-to-section é bom feedback (l.335-350). Mas **sem toast pós-save** — Larissa salva, redireciona, e cadê confirmação? Sem otimistic UI. Sem inline confirm tipo Linear "✓ Saved" inline animado. Perde 5 pts. Solução: Inertia `useForm` tem `onSuccess` — disparar toast (já existe sistema Sonner em outras telas — confirmar e reusar).

#### D-13 — Formulários · peso 1 · **5/10**

Inline validation no footer footer ✓. `useForm` do Inertia gerencia state ✓. Cmd+Enter submete ✓. `<details>` com `localStorage.open` persiste estado UI ✓. **Mas:** **autosave draft AUSENTE**. Charter Non-Goal explícito. Larissa atende telefone, F5 perde tudo. Backlog US-SELL-007 mas **tem sinal qualificado real** (cliente_rotalivre.md). Já está sendo implementado em paralelo — confirmar chave `{biz}.{user}` Tier 0. Perde 5 pts até feature shipar.

#### D-14 — Dataviz · peso 1 · **8/10**

4 KPIs `text-4xl font-semibold tabular-nums` com formatação R$ via `Intl.NumberFormat pt-BR` (formatBRL helper). Cor semântica no Status pgto: amber (falta) / blue (troco) / emerald (exato). **Pareia Stripe Dashboard density financeira.** Total consolidado com Subtotal + Desconto + Frete + Total geral em card `bg-muted/30 p-4 space-y-1.5` (l.847-873). Pessoalmente excelente. Perde 2 pts: KPIs estáticos no scroll — Linear faria sticky discreto após scroll passa do header.

#### D-15 — Onboarding · peso 1 · **3/10**

**Zero tooltips, zero tour, zero "primeira vez do usuário".** Larissa já usa há 4 anos — sem sinal. Mas pra novos clientes (Modules/ComunicacaoVisual, Modules/OficinaAuto futuros) será problema. Por enquanto P3. Perde 7 pts mas tudo bem — **ADR 0105 não criar US ativa sem sinal**.

### Tabela compacta (mesma da CAPTERRA-DESIGN-FICHA seção 2)

| Dim | Peso | Nota | Σ |
|---|:-:|:-:|:-:|
| D-01 Hierarquia | 3 | 8 | 24 |
| D-02 Densidade | 3 | 8 | 24 |
| D-03 Navegação | 3 | 6 | 18 |
| D-04 Design system | 2 | 8 | 16 |
| D-05 Microcopy PT-BR | 2 | 8 | 16 |
| D-06 Empty states | 2 | 9 | 18 |
| D-07 Loading | 2 | 5 | 10 |
| D-08 Error UX | 2 | 6 | 12 |
| D-09 Atalhos teclado | 1 | 4 | 4 |
| D-10 Mobile 1280px | 1 | 8 | 8 |
| D-11 A11y WCAG | 1 | 6 | 6 |
| D-12 Feedback | 1 | 5 | 5 |
| D-13 Formulários | 1 | 5 | 5 |
| D-14 Dataviz | 1 | 8 | 8 |
| D-15 Onboarding | 1 | 3 | 3 |
| **TOTAL** | **26** | — | **177** |

`nota_final = 177 / 26 × 10 = 68.08 → 68/100`

---

## Fase 4 — NOTA + RECOMENDAÇÃO

### Resultado

```
NOTA OIMPRESSO ATUAL (Sells/Create.tsx): 68/100
NOTA REFERÊNCIA TOP (Linear, hipotética se fizesse ERP): 92/100
NOTA REFERÊNCIA BR (Bling, ponderada mesma persona PME): 52/100

Gap pro topo: -24 pts.
  Causa principal: atalhos pobres + sem autosave draft + onboarding zero.
Gap pro BR: +16 pts a favor.
  oimpresso já bate Bling/Tiny em hierarquia, empty states, microcopy e tokens shadcn.
```

### Leitura honesta

1. **68 não é teatro de "abaixo do esperado".** É uma tela live em produção, melhor que o concorrente BR direto (Bling/Tiny), longe do estado-da-arte global em **polish** (autosave + atalhos + skeleton).
2. **Os P0 (D-01/02/03 peso 3) já pareiam SOTA.** Hierarquia, densidade e navegação são fortes. Onde a tela perde é peso 1/2 — polish secundário.
3. **As 2 features que parent está implementando em paralelo (Esc listener + autosave draft) fecham 2 dos 5 gaps top.** Pós-merge, nota sobe pra ~74-75.
4. **Charter é honesto.** Declara como Non-Goal o que ainda falta. Não há "esqueceram" — é backlog assumido.

### Top 3 ações executáveis hoje/esta semana

1. **G-DESIGN-02 — Autosave draft localStorage debounced 500ms.** Esforço S (~2h). **ROI altíssimo, sinal qualificado real** (Larissa atende telefone). Chave `oimpresso.sells.create.draft.{businessId}.{userId}` — **multi-tenant Tier 0 obrigatório** ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)). Já em implementação parent — verificar isolation.

2. **G-DESIGN-01 — Esc listener.** Esforço XS (≤30min). **WCAG 2.1.1 obriga (dever, não wishlist).** Scope: ignorar se `e.target` é input/textarea (não bloquear digitação), respeitar Cmd+Enter, fechar `<details>` aberto ou navegar `router.visit('/sells')` se nada aberto. Já em implementação parent.

3. **G-DESIGN-03 — `<FormError>` por campo + auto-open `<details>` quando erro dentro.** Esforço S (~1.5h). **Proposta nova desta análise.** Hoje footer só mostra `Object.values(errors)[0]` — se erro estiver em `invoice_no` dentro do `<details>` colapsado, Larissa scrola e não vê. Implementar `<FormError errors={errors.X}/>` por campo + se erros incluem chave de campo colapsado, `setAdvancedOpen(true)`. ROI alto.

### O que NÃO virar US ativa (ADR 0105 cliente como sinal)

- **D-15 Onboarding** (tour/tooltips primeira venda): nota 3/10, mas Larissa já usa há 4 anos. Sem sinal. Vira ADR feature-wish caso Martinho Caçambas (Modules/OficinaAuto candidato) confirme e demande. Não criar US.
- **D-09 atalho `/` focar busca**: backlog ok, mas não subir prioridade — Larissa não é power user. Linear-influence, não ROTA LIVRE-pain.
- **AI-native dashboard / conversational form-fill**: fora do escopo desta análise. Backlog ADR.

### Restrições Tier 0 respeitadas

- **Multi-tenant Tier 0 ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)):** autosave key `{businessId}.{userId}` obrigatório. Sem isso, draft da Larissa (biz=4) vazaria pro Wagner (biz=1) se compartilhassem máquina.
- **Charter > Spec:** charter declara `status: live, charter_version: 1`. Adicionar autosave + Esc muda Non-Goal pra Goal — exige bump pra `charter_version: 2` + Wagner aprovar.
- **biz=1 em smoke ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md)):** Pest novo de autosave roda em biz=1, nunca biz=4.
- **Não comitar ([definição agent](../../.claude/agents/design-arte.md)):** este agent não toca código. Os 2 features paralelas são do parent.

---

## Limitações desta análise (honestas)

1. **Linear não faz ERP.** A "nota referência top 92" é uma extrapolação calibrada — se Linear desenhasse Sells/Create, baseando em padrões observáveis em Linear Projects/Issues. Não é benchmark direto.
2. **Bling/Tiny — não tive acesso a screenshots recentes da tela "nova venda" deles.** A "nota 52" é inferência baseada em (a) UI ~Bootstrap legacy citada em reviews, (b) reclamações UI conhecidas. Se Wagner quiser benchmark direto, abrir trial Bling e tirar screenshots da tela "Cadastrar pedido de venda".
3. **D-11 a11y nota 6/10 é estimativa.** Auditoria real WCAG exige `design:accessibility-review` (skill Anthropic) ou ferramenta automatizada (axe-core). Sugerido como follow-up.
4. **Persona com 1 cliente real.** Larissa é o único caso piloto. Outros verticais (Martinho/Modules/OficinaAuto, candidatos ComunicacaoVisual) não validaram a tela ainda. Generalizar exige sinal.

---

## Próximos passos sugeridos (Wagner aprovar)

| # | Ação | Owner | Esforço | Prioridade |
|---|---|---|---|---|
| 1 | Confirmar autosave key inclui `{biz}.{user}` no PR paralelo do parent | parent | XS | **P0 Tier 0** |
| 2 | Confirmar Esc listener bloqueia em input/textarea no PR paralelo do parent | parent | XS | **P0 a11y** |
| 3 | Criar US-SELL-010 (ou subtask US-SELL-007): `<FormError>` por campo + auto-open `<details>` em erro | Wagner cria | S 1.5h | **P1** |
| 4 | Remover bloco "Debug · contract recebido do controller" l.1085-1120 — promessa US-SELL-005 não cumprida | Wagner | XS 15min | **P2** |
| 5 | Bump `Create.charter.md` charter_version 1 → 2 quando autosave + Esc shipar (move Non-Goal → Goal) | Wagner | XS | **P1** |
| 6 | Skeleton inicial (KPIs/Cards) | backlog | S 2h | **P2** |
| 7 | Toast pós-save (reusar Sonner se já existe) | backlog | S 1h | **P2** |
| 8 | Auditoria full WCAG via `design:accessibility-review` antes de cutover ROTA LIVRE | parent | M | **P1 pré-canary** |

---

**Output final pro parent (parent vai relatar pro Wagner):**

- **Path dos 2 docs:** [`memory/requisitos/Sells/CAPTERRA-DESIGN-FICHA.md`](../requisitos/Sells/CAPTERRA-DESIGN-FICHA.md) + este session log.
- **Nota:** 68/100 atual · 92/100 topo hipotético · 52/100 BR Bling-like → **gap pro topo -24, a favor do BR +16**.
- **Maior gap UX em 1 frase:** falta `<FormError>` por campo + auto-open `<details>`, pq quando o backend rejeita um campo dentro de "Mais opções" colapsado, Larissa vê msg genérica no footer e não acha onde corrigir.
- **Ação imediata recomendada (executável hoje):** confirmar que os 2 PRs paralelos do parent (Esc + autosave) respeitam (a) Esc não rouba digitação de inputs e (b) autosave key inclui `{businessId}.{userId}` Tier 0 — ambos itens fácil de validar e altíssimo risco se ignorados.

**Pergunta pro Wagner:** Aprova subir US-SELL-010 (FormError por campo + auto-open details) pra próximo cycle, ou prefere consolidar com US-SELL-007 (que já cobre Esc+autosave) antes de criar US nova?
