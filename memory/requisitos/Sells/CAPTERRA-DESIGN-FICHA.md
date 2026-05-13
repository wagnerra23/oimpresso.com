# CAPTERRA-DESIGN-FICHA — Sells/Create (UX/UI)

> **Cruzamento gerado:** 2026-05-13
> **Skill aplicada:** `design-arte` (input pra CAPTERRA-DESIGN-INVENTARIO.md futuro)
> **Alvo:** `resources/js/Pages/Sells/Create.tsx` (form Inertia/MWART, ~1160 LOC, live em biz=1 via feature flag `useV2SellsCreate`, OFF em biz=4 ROTA LIVRE — canary Wagner 7d, US-SELL-008)
> **Persona:** Larissa @ ROTA LIVRE biz=4 (vestuário Termas do Gravatal/SC, 1280px, ~5 vendas/dia, atende telefone no meio, **não-técnica**, 99% volume oimpresso)
> **Charter:** [`Create.charter.md`](../../../resources/js/Pages/Sells/Create.charter.md) (status: live, charter_version: 1)
> **Visual-comparison prévio:** [`sells-create-visual-comparison.md`](sells-create-visual-comparison.md) (approved 2026-05-08, gerou 5 gaps P0/P1 sendo trabalhados)
> **RUNBOOK:** [`RUNBOOK-create.md`](RUNBOOK-create.md)
> **SPEC:** [`SPEC.md`](SPEC.md) US-SELL-001..009

> ⚠️ **Nota mãe:** este é o **2º artefato canônico de UX** desta tela. O 1º (visual-comparison) usou 8 dimensões e canon interno (`os-page.jsx` Cowork). Este usa 15 dimensões e benchmark externo SOTA 2026 (Linear / Shopify Polaris / Stripe / Notion / Bling / Tiny / Omie / Conta Azul). Complementar, não substituto — visual-comparison fica como referência canon→Inertia; este aponta gaps com mercado global.

---

## 1. Players UX avaliados (referência 2026)

### 1.1 UX leaders globais

| # | Player | Tipo | Site | Especialidade observável |
|---|---|---|---|---|
| 1 | **Linear** | SaaS B2B issue tracker | linear.app | Keyboard-first (Cmd+K para todas ações), strategic minimalism, command palette gold-standard |
| 2 | **Shopify Polaris** | Admin varejo (web components 2026) | polaris-react.shopify.com | Picker experiences padronizadas, intentional loading states, pattern library documentada (empty / error / multi-step / picker) |
| 3 | **Stripe Dashboard** | Pagamentos B2B | stripe.com/payments | Form complexo com inline validation, Payment Element handle automático de erros + 100+ métodos pgto |
| 4 | **Notion / Airtable** | Knowledge / DB SaaS | notion.so / airtable.com | Progressive disclosure (revela complexidade conforme user evolui), modular blocks |
| 5 | **Apple HIG / Material 3** | Design system base | — | Tokens, focus states, contrast WCAG, mobile-first |

### 1.2 Concorrentes BR (UX PME baseline)

| # | Player | Tipo | Observação |
|---|---|---|---|
| 6 | **Bling** | ERP PME (300k+ users) | "Gerenciador de transições" customizável (estados de pedido), dashboard consolidado em 3 contas. UI ~Bootstrap legacy, planos R$ 39-499/mês |
| 7 | **Tiny ERP (Olist)** | ERP PME | "Visual limpo intuitivo, sem excesso de botões" (autodeclarado). Reclame Aqui 76.3% resolvido — reclamações pós-aquisição Olist citam suporte > UI |
| 8 | **Omie** | ERP médio porte | NF-e/NFS-e + conta digital + CRM funil — UI mais densa, foco estrutura empresa média |
| 9 | **Conta Azul Pro** | ERP financeiro PME | Mais simples / direto que Omie, perfil serviço + pequeno comércio |

---

## 2. Dimensões UX P0-P3 — tabela comparativa (estado atual oimpresso)

Legenda: ✅ pareio SOTA · 🟡 parcial (gap conhecido / mitigado) · ❌ ausente · ⚪ N/A

| ID | Dimensão | Peso | Linear/Shopify | Bling/Tiny | oimpresso Sells/Create | Distância | Nota /10 |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| **D-01 (P0)** | Hierarquia visual | 3 | ✅ h1+p+ação direita | 🟡 título simples | ✅ h1+p+pills+4 KPIs | curta | **8** |
| **D-02 (P0)** | Densidade informacional | 3 | ✅ minimalismo estratégico | ❌ campos demais | ✅ 8 visíveis + 10 colapsáveis | curta | **8** |
| **D-03 (P0)** | Navegação primária | 3 | ✅ sidebar fina + breadcrumb | 🟡 sidebar genérico | 🟡 AppShellV2 sem topnav módulo | média | **6** |
| **D-04 (P1)** | Sistema de design | 2 | ✅ Polaris tokens | 🟡 Bootstrap legacy | ✅ Tailwind 4 + shadcn tokens | curta | **8** |
| **D-05 (P1)** | Microcopy PT-BR | 2 | ⚪ EN nativo | ✅ PT-BR nativo | ✅ PT-BR consistente, validação inline footer | curta | **8** |
| **D-06 (P1)** | Empty states | 2 | ✅ ícone+CTA | ❌ vazio sem CTA | ✅ EmptyState shared + CTA "Buscar produto" | curta | **9** |
| **D-07 (P1)** | Loading + skeleton | 2 | ✅ skeleton inteligente | 🟡 spinner global | 🟡 Loader2 só no botão submit, sem skeleton inicial | média | **5** |
| **D-08 (P1)** | Error UX | 2 | ✅ inline + recovery | 🟡 toast genérico | 🟡 1 erro inline no footer (apenas o primeiro), sem `<FormError>` por campo | média | **6** |
| **D-09 (P2)** | Atalhos teclado | 1 | ✅ Cmd+K + tudo | ❌ ausente | 🟡 só Cmd/Ctrl+Enter; `/` e `Esc` faltam (US-SELL-007 backlog) | longa | **4** |
| **D-10 (P2)** | Mobile/touch 1280px | 1 | ✅ responsive | 🟡 quebra em mobile | ✅ 1280px-first (KPIs grid-cols-2 md:4, pills wrap, max-w-7xl) | curta | **8** |
| **D-11 (P2)** | A11y WCAG 2.1 AA | 1 | ✅ certificado | 🟡 contraste OK, focus inconsistente | 🟡 aria-labels OK, focus shadcn herdado, mas `aria-current` só pills | média | **6** |
| **D-12 (P2)** | Feedback ações | 1 | ✅ otimistic + undo | 🟡 toast tardio | 🟡 sem toast pós-save, scroll-to-error OK, sem otimistic UI | média | **5** |
| **D-13 (P2)** | Formulários | 1 | ✅ inline validation + autosave | ❌ sem autosave | 🟡 inline footer sim, **autosave draft AUSENTE** (US-SELL-007 backlog) | longa | **5** |
| **D-14 (P2)** | Dataviz | 1 | ✅ KPIs ricos | 🟡 dashboard separado | ✅ 4 KPIs tabular-nums com semantic color (amber/blue/emerald) | curta | **8** |
| **D-15 (P3)** | Onboarding | 1 | ✅ tooltips contextuais | ❌ vídeo na home só | ❌ sem tooltips, sem tour primeira venda | longa | **3** |

---

## 3. Cálculo da nota ponderada

```
Σ (nota_i × peso_i):
  D-01 (8×3) + D-02 (8×3) + D-03 (6×3) = 24 + 24 + 18 = 66
  D-04 (8×2) + D-05 (8×2) + D-06 (9×2) + D-07 (5×2) + D-08 (6×2) = 16+16+18+10+12 = 72
  D-09..D-15 (peso 1): 4 + 8 + 6 + 5 + 5 + 8 + 3 = 39

  Total: 66 + 72 + 39 = 177

Σ pesos: (3×3) + (5×2) + (7×1) = 9 + 10 + 7 = 26

nota_final = 177 / 26 × 10 = 68.08 → arredondado 68
```

```
NOTA OIMPRESSO ATUAL (Sells/Create.tsx): 68/100
NOTA REFERÊNCIA TOP (Linear, hipotética se fizesse ERP): 92/100
NOTA REFERÊNCIA BR (Bling, ponderada mesma persona PME): 52/100

Gap pro topo: -24 pts. Causa principal: atalhos pobres + sem autosave draft + onboarding zero.
Gap pro BR: +16 pts a favor. oimpresso já bate Bling/Tiny em hierarquia, empty states, microcopy e tokens.
```

**Leitura honesta:**
- O cenário "fica entre Bling e Linear" é **realista pra um form que cuida de 99% do volume de um cliente real**. Não é teatro.
- Os 3 P0 (D-01 a D-03, peso 3) já estão pareando o SOTA — onde o oimpresso perde é em **polish secundário** (peso 1).
- O charter é honesto: marca US-SELL-007 (Esc + autosave) como **Non-Goal explícito do estado live atual**. Não é "esqueceram"; é backlog assumido.

---

## 4. Top 5 gaps acionáveis (priorizados por dor real Larissa × esforço)

| # | Gap | Severidade | Esforço | Tem sinal Larissa? | ADR 0105 |
|---|---|---|---|---|---|
| **G-DESIGN-01** | Sem `Esc` listener (drawer/modal não fecha por teclado) | P1 a11y | XS (≤30min) | ⚪ não-observado mas WCAG 2.1.1 obriga | ✅ execute (a11y é dever, não wishlist) |
| **G-DESIGN-02** | Sem autosave draft `localStorage.oimpresso.sells.create.draft.{biz}.{user}` debounced | **P0** UX real | S (~2h) | ✅ **observado**: cliente_rotalivre.md "Larissa atende telefone no meio" + charter Non-Goal admite gap | ✅ execute (sinal qualificado) |
| **G-DESIGN-03** | `<FormError>` por campo ausente (só footer mostra 1ª msg de erro do useForm) | P1 error UX | S (~1.5h) | 🟡 inferido — se o erro for em campo dentro de `<details>` fechado, Larissa não vê | ✅ execute |
| **G-DESIGN-04** | Sem skeleton no inicial render (KPIs/Cards aparecem zerados, parecem broken) | P2 perceived perf | S (~2h) | ⚪ não-observado direto, mas Larissa em conexão lenta SC pode ver pisca | 🟡 condicional (medir Web Vitals primeiro) |
| **G-DESIGN-05** | Sem tour/tooltip "primeira venda" — bloco "Mais opções" pode confundir não-técnica | P3 onboarding | M (~4h) | ❌ Larissa já usa há 4+ anos; **sem sinal**. Vira ADR feature-wish | ❌ NÃO criar US ativa (ADR 0105) |

**Atalho `/` para focar busca de produto (US-SELL-007)** — fica em P3 por enquanto: Larissa não é power-user de atalhos. Mantém no backlog mas não sobe prioridade. **NÃO é gap.**

---

## 5. Decisão / Nota / Recomendação

### Nota final
**68/100** — entre Bling (~52) e Linear (~92). Honesto: o `Create.tsx` atual é melhor que o concorrente BR direto, ainda longe do estado-da-arte global no polish (autosave + atalhos + a11y).

### Causa principal do gap (1 frase)
**Polish de form longo (autosave draft + Esc + FormError por campo) ficou como Non-Goal explícito no charter, mas o autosave especificamente tem sinal qualificado de cliente real (Larissa atende telefone).**

### Top 3 P0/P1 pra fechar (executável esta semana)

1. **Autosave draft localStorage debounced 500ms** — fechar G-DESIGN-02. Esforço S (~2h). **ROI altíssimo** (Larissa atende telefone diariamente, charter+ROTA LIVRE memory confirmam). Já está sendo implementado em paralelo pelo parent — confirmar persistência por `{biz}.{user}` (Tier 0 multi-tenant não pode vazar entre tenants).

2. **Esc listener** — fechar G-DESIGN-01. Esforço XS (≤30min). WCAG 2.1.1 obriga. Também em implementação paralela. **Verificar:** scope do listener — bloqueia se `e.target instanceof HTMLInputElement/Textarea` (não roubar digitação) e respeita Cmd+Enter ainda funcionando.

3. **`<FormError>` por campo (errors do useForm)** — fechar G-DESIGN-03. Esforço S (~1.5h). Hoje o footer só mostra `Object.values(errors)[0]` — se a venda falhar em `payments.0.account_id`, Larissa vê "campo inválido" sem saber qual e tem que abrir `<details>` fechado pra adivinhar. Não está nas 2 features paralelas — **proposta nova esta sessão**.

---

## 6. Restrições Tier 0 que o redesign respeita

- **Multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):** chave de autosave **PRECISA** incluir `{businessId}.{userId}` — vazar draft entre tenants = pior bug possível. Charter Non-Goal cita `oimpresso.sells.create.draft.{biz}.{user}`.
- **MWART canon ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)):** todo Edit/Write em `Pages/Sells/Create.tsx` tem RUNBOOK + visual-comparison ao lado (✅ os 2 existem). Não viola.
- **Charter > Spec (Constituição v2):** charter declara `status: live, charter_version: 1`. Adicionar autosave + Esc **muda Non-Goal pra Goal** — exige bump pra `charter_version: 2` + Wagner aprovar mudança de contrato.
- **Cliente como sinal ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):** autosave tem sinal (Larissa atende telefone, doc explícito). Esc tem dever a11y (não wishlist Linear-influence). FormError tem sinal inferido (erros em campo colapsado). Tour/onboarding (D-15) **NÃO tem sinal** — não criar US ativa.
- **biz=1 em smoke ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)):** qualquer Pest novo de autosave roda em biz=1, nunca biz=4.

---

## 7. O que NÃO mudar (pontos OK do design atual)

| Item | Por quê |
|---|---|
| Triagem 18→8 visíveis + 10 colapsáveis | Princípio "progressive disclosure" Notion/Linear; reduziu scroll 3 telas→1 (US-SELL-004 done) |
| Pills com scroll-spy IntersectionObserver | Pareia Linear nav lateral; descobribilidade alta |
| 4 KPIs tabular-nums com semantic color | Stripe/Polaris pattern para clarity em valor monetário |
| Sticky header (h1+pills) + sticky footer (Cancelar+Salvar) | UX research diz: form longo precisa CTA sempre visível; charter UX target "footer sticky permanece visível" |
| ProductSearchAutocomplete + CustomerSearchAutocomplete debounced | Pareia Linear's instant search; substituiu Select2 legacy |
| Status default `final` + `format_now_local` | Defaults inteligentes ROTA LIVRE (cliente_rotalivre.md); evita regressão "vendas mudaram 3h" |
| Validação inline no footer (`Adicione produto / Selecione local / Falta fechar`) | Diz POR QUÊ botão tá disabled — pareia Stripe "no mystery"  |
| dropdownEntries helper + Tailwind tokens (sem cor crua) | Skill `multi-tenant-patterns` + R-DS-002, evita 6 gotchas catalogados |

---

## 8. Referências externas (Fase 2)

- [Linear SaaS design — strategic minimalism + Cmd+K (LogRocket)](https://blog.logrocket.com/ux-design/linear-design/)
- [Shopify Polaris — patterns library 2026 (web components)](https://polaris-react.shopify.com/patterns)
- [Stripe Payment Element — auto error handling, 100+ methods](https://docs.stripe.com/payments/payment-element)
- [B2B SaaS UX 2026 — progressive disclosure + predictive autocomplete (Onething)](https://www.onething.design/post/b2b-saas-ux-design)
- [Bling ERP 2026 — gerenciador de transições + dashboard 3 contas](https://www.bling.com.br/)
- [Tiny ERP / Olist — visual limpo intuitivo (Reclame Aqui 76.3% resolvido)](https://www.reclameaqui.com.br/empresa/tiny-erp/)
- [Sticky CTA patterns em forms longos (Eleken)](https://www.eleken.co/blog-posts/footer-ux)

---

**Última atualização:** 2026-05-13
**Aprovado por:** — (pendente Wagner)
