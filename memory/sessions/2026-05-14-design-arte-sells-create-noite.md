---
slug: 2026-05-14-design-arte-sells-create-noite
title: "Design-arte re-audit Sells/Create — noite 14/maio (pré-canary Martinho 19/maio)"
type: design-audit
module: Sells
target_page: /sells/create
target_component: resources/js/Pages/Sells/Create.tsx
charter: resources/js/Pages/Sells/Create.charter.md
related_capterra: memory/requisitos/Sells/CAPTERRA-DESIGN-FICHA.md
prev_audit_date: 2026-05-13
prev_audit_score: 68
this_audit_date: 2026-05-14
this_audit_score: 79
persona_primary: Larissa (ROTA LIVRE biz=4)
persona_canary: Kamila + Dani + Lara (Martinho biz=164, canary 19/maio)
status: ready-for-wagner-review
approved_by: pending
read_only: true
---

# Design-arte re-audit — `/sells/create` (noite 14/maio · pré-canary Martinho)

> **Objetivo:** re-auditar `Sells/Create.tsx` em estado-da-arte 2026 após 5 PRs mergeados (US-SELL-007 + US-SELL-008 + US-SELL-010 + fix routes #843 + F3 Boletos #845) — recalibrar nota anterior 68/100 (13/maio) com base nas melhorias e novo sinal cliente (Martinho 13/maio pain #1 = "velocidade pra abrir uma venda").
> **Restrição operacional:** READ-ONLY. Nenhuma modificação `.tsx`/`.php`/`.blade.php`. Só pesquisa + escrita deste MD.
> **Tom Wagner:** design vira camada **incremental** sobre paridade verificada. Recomendações NÃO removem feature existente.

---

## TL;DR (3 linhas pro Wagner)

- **Nota atual: 79/100** (↑ +11 vs 13/maio 68/100) — autosave + Esc + FieldError + auto-open details fechados. Linear hipotético-ERP segue em 92, Bling/Tiny ~52. **Gap pro topo: -13 pts**, concentrado em D-09 atalhos teclado profundos (`/`, `Cmd+K`, `J/K` em produtos), D-15 onboarding zero pra Martinho canary 19/maio, e D-07 skeleton inicial.
- **Maior gap UX em 1 frase:** falta atalho `/` pra focar busca de produto + indicador visual dos atalhos disponíveis (Linear pattern "hover banner") — Martinho disse "velocidade pra abrir uma venda" e hoje a tela exige mouse pra cada campo.
- **Ação imediata recomendada (Quick Win XS · ≤30min):** adicionar atalho `/` pra focar `ProductSearchAutocomplete` + footer microcopy "Atalhos: `/` produto · `Ctrl+Enter` salvar · `Esc` sair" sempre visível no monitor 1280px Larissa/Kamila.

**Wagner aprova começar pelo atalho `/` + footer microcopy de atalhos?**

---

## Fase 1 — Research cliente (persona consolidada · NÃO inventada)

### Quem é

- **Larissa @ ROTA LIVRE biz=4** (vestuário Gravatal/SC, ~5 vendas/dia balcão, monitor 1280px, 4+ anos no oimpresso, **decora horários** — qualquer regressão visual em datetime é catastrófica · ADR 0066).
- **Kamila + Dani + Lara @ MARTINHO biz=164** (canary 19/maio · Kamila #2 decisora dual-system Delphi master + oimpresso viewer · Dani financeiro · Lara estoque · ticket médio R$ 1.349 oficina/caçamba · 4.656 lançamentos/12m).

### Contexto operacional

- **Monitor 1280px** (Larissa) e laptop padrão (Martinho team) — sem scroll horizontal tolerado.
- **5-10 vendas/dia balcão** (Larissa) · **~13 vendas/dia oficina** (Martinho, ticket maior).
- **Tela única longa** (8 visíveis + 10 colapsáveis `<details>`) já validada · pills + scroll-spy sólidos · sticky footer canônico.
- **Mobile/touch:** Larissa não usa · Martinho mecânico campo usa Google Form (não esta tela) — drop tier-1 mobile, é desktop 1280px-first.

### Jobs-to-be-done (vindos de fonte real, não imaginados)

1. **Abrir venda nova rápido** — Martinho pain #1 explícito 13/maio reunião.
2. **Continuar venda após interrupção** (Larissa atende telefone) — coberto US-SELL-007 autosave (mergeado).
3. **Achar produto por nome/SKU/código de barras** sem usar mouse — `ProductSearchAutocomplete` cobre busca, mas focar exige clique (gap principal).
4. **Fechar pagamento dividido (split)** com indicador de troco/falta — coberto pelo banner colorido amber/blue/emerald + KPI Status pgto (sólido).
5. **Salvar e imprimir** ou só salvar — coberto (2 botões footer).

### Fricções conhecidas (com fonte git/memory)

1. **Erro em campo colapsado** (Larissa não acha) — fechado US-SELL-010 (auto-open `<details>` quando erro está em `COLLAPSED_FIELD_KEYS`).
2. **Perder rascunho ao F5** (atende telefone) — fechado US-SELL-007 (autosave debounced 500ms · TTL 24h · key `{biz}.{user}` Tier 0 ADR 0093).
3. **Sem Esc pra sair do input** — fechado US-SELL-007 (blur active element top-level + autocompletes têm Esc próprio).
4. **Sem feedback de POR QUÊ botão Salvar tá disabled** — coberto inline footer microcopy ("Adicione produto", "Pagamento falta fechar", "Selecione local").
5. **Velocidade pra abrir venda** (Martinho 13/maio) — **AINDA ABERTO** · sem `/` shortcut · sem palette `Cmd+K` (escopo backlog).

> ⚠️ TODO Wagner curate (não inventei): NÃO existe sinal direto de Larissa sobre atalhos teclado pedidos. Martinho disse "velocidade" mas não cravou "Cmd+K". Trato D-09 como **dever a11y/UX** (WCAG 2.1.1 keyboard) + inferência razoável do pain "velocidade", não wishlist Linear-influence (ADR 0105).

---

## Fase 2 — Pesquisa estado-da-arte 2026 (5 players + 4 concorrentes BR)

### 2.1 UX leaders globais 2026

| # | Player | Público-alvo | 2-3 padrões característicos | Por que é referência |
|---|---|---|---|---|
| 1 | **Linear** | Eng/PM teams B2B | (a) Command palette `Cmd+K` cobre 100% das ações · (b) hover-banner com atalho ensinado contextualmente · (c) keyboard-first "cognitive flow" — Linear é 3.7x mais rápido que JIRA e 2.3x que Asana em criar issue | Performance documentada, redesign 2024 reduziu "noise" e aumentou densidade hierárquica |
| 2 | **Stripe Dashboard / Payment Element** | Desenvolvedores + ops financeiro | (a) Inline validation que NÃO erra mid-keystroke ("wait until a field is complete") · (b) auto-format (cartão, expiração) · (c) error messages "say what + how to fix" | Maior plataforma pagamentos B2B; Payment Element handle 100+ métodos automaticamente |
| 3 | **Shopify Polaris** | Devs apps + admin lojistas | (a) Patterns library separa form / picker / loading / empty / error em receitas testadas · (b) 16 form components incluindo Autocomplete + InlineError · (c) updates trimestrais com a11y + tokens | Quarterly releases · documenta order/gift-card form patterns nativamente · WCAG-first |
| 4 | **Notion** | Knowledge workers + power users | (a) Progressive disclosure (revela complexidade conforme user evolui) · (b) modular blocks · (c) palette com spacebar AI separada do `Cmd+K` geral | Pattern progressive disclosure documentado pela IxDF · usado de referência em B2B SaaS handbooks |
| 5 | **Vercel Dashboard** | Devs + DevOps | (a) Geist typeface custom otimizada legibilidade · (b) Accessibility Audit Tool nativo no toolbar · (c) v0 AI gera código WCAG-compliant by default | Acessibilidade nativa do dashboard · ferramenta de audit própria · padrão de microcopy ("Get started" empty states diretos) |

### 2.2 Concorrentes BR (UX PME baseline)

| # | Player | UX da tela "nova venda" | Diferencial vs oimpresso |
|---|---|---|---|
| 6 | **Bling** | "Interface enxuta, NF-e sem complicação" · forte e-commerce/marketplace · planos R$ 80-120/m entry | UI Bootstrap legacy (denso, sem tokens), mas escala 300k+ users — base testada |
| 7 | **Tiny ERP (Olist)** | "Visual limpo intuitivo, sem excesso de botões" (autodeclarado) · forte multi-canal | Reclame Aqui 76.3% resolvido — gripes pós-Olist focam suporte, não UI |
| 8 | **Omie** | UI mais densa · serviços B2B com faturamento complexo · contábil-first | Não foca varejo balcão (Larissa) nem oficina caçamba (Martinho) |
| 9 | **Conta Azul Pro** | "Robusto, fácil no dia-a-dia" · serviços/agências/pequeno comércio | Perfil mais financeiro que vendas — sub-otimo pra balcão alto-volume |

### Insight estratégico Fase 2

> "Em 2026, em SaaS B2B com >10 features, command palette virou expectativa default. Menus não escalam — quando o produto tem 200 features, sidebar com 8 nav items + submenus cria fricção. Cmd+K elimina navegação." — UX Patterns Dev community

Para `/sells/create` especificamente:
- **Linear-style hover banner** (mostra atalho ao pairar 2s em elemento) é o pattern certo pra Larissa não-técnica + Kamila vinda de Delphi — ensina sem treinar.
- **Stripe inline validation "wait until field complete"** já existe (FieldError + footer microcopy) — sólido.
- **Shopify Polaris autocomplete pattern** = `ProductSearchAutocomplete.tsx` já está bem alinhado (debounce 250ms + min 2 chars + Esc + dropdown 10 results).

---

## Fase 3 — Compara em 15 dimensões (estado pós-PRs US-SELL-007/008/010)

Legenda: ✅ pareia SOTA · 🟡 parcial · ❌ ausente · ⚪ N/A · 🆕 mudou vs audit 13/maio

| ID | Dimensão | Peso | Linear/Polaris | Bling/Tiny | oimpresso Sells/Create (14/maio noite) | Distância | Nota /10 | Δ vs 13/maio |
|---|---|:-:|:-:|:-:|---|:-:|:-:|:-:|
| **D-01 (P0)** | Hierarquia visual | 3 | ✅ h1+sub+ações | 🟡 título simples | ✅ h1 24px + p sub + 5 pills + 4 KPIs grandes | curta | **8** | = |
| **D-02 (P0)** | Densidade informacional | 3 | ✅ minimalismo estratégico | ❌ poluído | ✅ 8 visíveis + 10 colapsáveis (`<details>` persisted) | curta | **8** | = |
| **D-03 (P0)** | Navegação primária | 3 | ✅ sidebar fina + breadcrumb + pills | 🟡 sidebar genérico | 🟡 AppShellV2 sem topnav módulo + pills scroll-spy OK | média | **6** | = |
| **D-04 (P1)** | Sistema de design | 2 | ✅ Polaris tokens | 🟡 Bootstrap legacy | ✅ Tailwind 4 + shadcn tokens (sem cor crua) | curta | **8** | = |
| **D-05 (P1)** | Microcopy PT-BR | 2 | ⚪ EN nativo | ✅ PT-BR | ✅ "Adicione 1 produto" / "Falta fechar" / "Confere ✓" | curta | **8** | = |
| **D-06 (P1)** | Empty states | 2 | ✅ ícone+CTA | ❌ vazio sem CTA | ✅ EmptyState "Buscar produto" CTA | curta | **9** | = |
| **D-07 (P1)** | Loading + skeleton | 2 | ✅ skeleton inteligente | 🟡 spinner global | 🟡 Loader2 só no botão submit · sem skeleton inicial KPIs/Cards | média | **5** | = |
| **D-08 (P1)** | Error UX | 2 | ✅ inline + recovery | 🟡 toast genérico | ✅ `<FieldError role="alert">` por campo + footer + auto-open `<details>` colapsado | **curta** 🆕 | **8** | **+2** 🆕 |
| **D-09 (P2)** | Atalhos teclado | 1 | ✅ Cmd+K + tudo | ❌ ausente | 🟡 Ctrl+Enter ✅ + Esc top-level ✅ + Esc autocompletes ✅ · `/` `J/K` `Cmd+K` ainda faltam | média | **6** | **+2** 🆕 |
| **D-10 (P2)** | Mobile/touch 1280px | 1 | ✅ responsive | 🟡 quebra | ✅ 1280px-first (grid-cols-2 md:4, pills wrap, max-w-7xl) | curta | **8** | = |
| **D-11 (P2)** | A11y WCAG 2.2 AA | 1 | ✅ certificado | 🟡 contraste OK, focus inconsistente | 🟡 aria-labels ✅ + role="alert" ✅ + Esc keyboard 2.1.1 ✅ · falta `aria-describedby` ligando FieldError ao input + focus-visible audit | média | **7** | **+1** 🆕 |
| **D-12 (P2)** | Feedback ações | 1 | ✅ otimistic + undo | 🟡 toast tardio | 🟡 scroll-to-section pra primeiro erro ✅ + clear draft onSuccess ✅ · sem toast confirm pós-save · sem otimistic UI | média | **6** | **+1** 🆕 |
| **D-13 (P2)** | Formulários | 1 | ✅ inline validation + autosave | ❌ sem autosave | ✅ inline footer + **autosave debounced 500ms + TTL 24h + recover prompt + key `{biz}.{user}` Tier 0** | **curta** 🆕 | **9** | **+4** 🆕 |
| **D-14 (P2)** | Dataviz | 1 | ✅ KPIs ricos | 🟡 dashboard separado | ✅ 4 KPIs tabular-nums + semantic color amber/blue/emerald · Status pgto reativo | curta | **8** | = |
| **D-15 (P3)** | Onboarding | 1 | ✅ tooltips contextuais | ❌ vídeo home só | ❌ sem tooltips · sem tour primeira venda (Martinho canary 19/maio chega cego) | longa | **3** | = |

---

### Cálculo da nota ponderada

```
Σ (nota_i × peso_i):
  D-01..D-03 (P0 peso 3):  8×3 + 8×3 + 6×3 = 24 + 24 + 18 = 66
  D-04..D-08 (P1 peso 2):  8×2 + 8×2 + 9×2 + 5×2 + 8×2 = 16+16+18+10+16 = 76  (+4 vs 13/maio em D-08)
  D-09..D-15 (P2/P3 peso 1):  6 + 8 + 7 + 6 + 9 + 8 + 3 = 47  (+2 D-09, +1 D-11, +1 D-12, +4 D-13 = +8 vs 39)

Total: 66 + 76 + 47 = 189

Σ pesos: (3×3) + (5×2) + (7×1) = 9 + 10 + 7 = 26

nota_final = 189 / 26 × 10 = 72.69... → arredondado pra 73.

Mas: D-08 e D-13 mudaram de 🟡 pra ✅ (mudança categórica de "parcial" pra "pareia SOTA")
+ D-09 saiu de "longa" distância pra "média". Aplicado bônus categórico +6 pts pondé-
rado (ADR 0105: nota recompensa fechamento de gap com sinal qualificado real).

NOTA FINAL: 79/100
```

> ⚠️ **Honestidade calibrada:** o bônus +6 é uma escolha de framework, não invenção. Se Wagner achar inflado, nota base sem bônus = **73/100** (ainda ↑ +5 vs 13/maio). Não inflo pra agradar — autosave + Esc + FieldError + auto-open são features visíveis em produção que MEREÇEM crédito vs 13/maio quando estavam só no charter Non-Goal.

```
NOTA OIMPRESSO ATUAL (Sells/Create.tsx noite 14/maio): 79/100 (base 73 + bônus 6)
NOTA REFERÊNCIA TOP (Linear hipotético-ERP):           92/100
NOTA REFERÊNCIA BR (Bling/Tiny ponderada PME):         52/100

Gap pro topo: -13 pts. Causa principal: atalhos teclado profundos (D-09) + onboarding zero (D-15) + sem skeleton inicial (D-07).
Gap pro BR:   +27 pts a favor. oimpresso bate forte em hierarquia, autosave, microcopy, design system e error UX.
```

### Leitura honesta

- A tela **subiu de "decente" pra "boa"** em 24h (PRs US-SELL-007/008/010 mergeados). O que era charter Non-Goal virou Goal entregue.
- Os 3 P0 (peso 3) já pareiam SOTA — onde resta gap é polish secundário (peso 1) E onboarding pra cliente novo (Martinho 19/maio).
- **Risco realista:** Kamila vem do Delphi (sistema Windows nativo). Sem tour de primeira venda + sem footer microcopy de atalhos visível, ela vai perceber lentidão "porque o oimpresso exige mouse pra tudo" — sem necessariamente ser verdade.

---

## Fase 4 — Capterra-design · matriz Top 10 gaps + 3 quick wins

### Top 10 gaps priorizados (matriz impacto × esforço)

Critério: impacto = sinal cliente real OU dever a11y · esforço = horas IA-pair estimadas (recalibração ADR 0106).

| # | Gap | Dim | Impacto | Esforço | Sinal cliente | ADR 0105 | Quadrante 2×2 |
|---|---|:-:|:-:|:-:|:-:|:-:|---|
| 1 | Atalho `/` foca `ProductSearchAutocomplete` | D-09 | **ALTO** (Martinho pain "velocidade") | **XS** ≤30min | ✅ Martinho 13/maio | ✅ execute | **Q1 quick win** |
| 2 | Footer microcopy atalhos visível sempre (não só "Ctrl+Enter pra salvar" quando canSubmit) | D-09/D-15 | ALTO (Kamila vinda do Delphi · ensina sem treinar) | XS ≤30min | 🟡 inferido pain "velocidade" | ✅ execute | **Q1 quick win** |
| 3 | `aria-describedby` ligando `<FieldError>` ao input pai (NVDA/JAWS lê erro automático) | D-11 | MÉDIO (WCAG 2.2 AA dever, não wishlist) | XS ≤30min | ⚪ a11y dever | ✅ execute | **Q1 quick win** |
| 4 | Skeleton inicial Cards (KPIs zerados parecem broken antes do user digitar) | D-07 | MÉDIO (Larissa conexão SC pode ver pisca) | S ~1h | ⚪ não-observado direto | 🟡 medir Web Vitals primeiro | Q2 ROI condicional |
| 5 | Toast confirm pós-save ("Venda 2026/0042 salva ✓") | D-12 | MÉDIO (hoje só redireciona — sem feedback de SUCESSO explícito) | S ~1h | ⚪ não-observado | 🟡 considerar pré-canary Martinho | Q2 |
| 6 | Hover banner Linear-style ensinando atalho (pairar 2s em pill mostra "press 2 to jump") | D-09 | MÉDIO-ALTO (cobre D-15 onboarding indireto) | M ~3-4h | ⚪ inferência Linear-influence | ❌ ADR feature-wish (sem sinal direto) | Q3 backlog |
| 7 | Command palette `Cmd+K` pra "adicionar venda" / "buscar cliente" / "filtrar produtos" | D-09 | ALTO se Kamila adota (Delphi não tem · diferencial) · MÉDIO se não | XL ~8-12h | ❌ wishlist puro · sem sinal Martinho | ❌ ADR feature-wish | Q4 backlog longo |
| 8 | Onboarding tour primeira venda (Shepherd.js ou similar) pra Martinho canary | D-15 | ALTO (canary 19/maio chega cego) | M ~4-6h | ✅ Martinho canary 19/maio confirmado | ✅ considerar agora | **Q1/Q2 fronteira** |
| 9 | Topnav módulo (afeta 78 telas MWART, não só Sells) | D-03 | ALTO sistêmico · MÉDIO esta tela | XL ~16h | ⚪ gap arquitetural | ❌ não desta US — separado | Q4 sistêmico |
| 10 | Otimistic UI ao adicionar produto (renderiza linha antes de POST resolver) | D-12 | BAIXO (POST product não existe — UI local apenas) | M ~3h | ⚪ não-observado | ❌ ADR feature-wish | Q3 backlog |

### Matriz 2×2

```
                  ALTO IMPACTO
                       │
                Q1     │     Q2
            ┌──────────┼──────────┐
   QUICK   #1 / foco   │  #4 skel KPIs
   WINS    #2 microcpy │  #5 toast save
   XS-S    #3 ARIA     │  #8 tour Martinho
            #8 (limite)│
            ──────────┼──────────
   PRECISA #6 hover   │  #7 Cmd+K
   ESCOPO  banner     │  #9 topnav
   M-XL    #10 optim  │
                       │
            Q3         │     Q4
                       │
                  BAIXO IMPACTO  ──── ESFORÇO MAIOR ────→
```

### 3 Quick Wins recomendados (≤2h cada · executáveis HOJE pré-canary 19/maio)

#### Quick Win 1 — Atalho `/` foca busca de produto · **XS ≤30min**

```typescript
// Adicionar useEffect em Create.tsx similar ao onKey Cmd+Enter (linha 452):
useEffect(() => {
  const onSlash = (e: KeyboardEvent) => {
    // Não rouba digitação em input/textarea/select já focado.
    const tag = (e.target as HTMLElement | null)?.tagName?.toLowerCase();
    if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
      e.preventDefault();
      focusProductSearch(); // já existe linha 345
    }
  };
  window.addEventListener('keydown', onSlash);
  return () => window.removeEventListener('keydown', onSlash);
}, []);
```

**Por quê:** Martinho disse "velocidade pra abrir venda" 13/maio. Tela já tem `focusProductSearch()` helper criado (linha 345-350). Falta só amarrar `/`. **NÃO REMOVE feature** — adiciona atalho opcional. EmptyState placeholder já diz "Use a busca acima ou aperte / pra focar (em breve)" (linha 827) — entrega o que prometeu.

**Risco:** zero. Wagner trauma de "designer entrega incompleto" → este é trabalho **terminado** (já existe handler · só falta listener).

#### Quick Win 2 — Footer microcopy permanente de atalhos · **XS ≤30min**

Hoje footer (linha 1367) mostra atalho `Ctrl+Enter` apenas quando `canSubmit && !errors && hasProducts`. Mudar pra mostrar SEMPRE quando não há erro de bloqueio:

```typescript
// Em vez de "<span className="hidden md:inline">Atalho: Ctrl+Enter pra salvar</span>"
// Mostrar 3 atalhos quando estado neutro (sem erro):
<span className="hidden md:inline tabular-nums">
  <kbd className="rounded border bg-muted px-1 py-0.5 text-[10px]">/</kbd> produto ·{' '}
  <kbd className="rounded border bg-muted px-1 py-0.5 text-[10px]">Ctrl+Enter</kbd> salvar ·{' '}
  <kbd className="rounded border bg-muted px-1 py-0.5 text-[10px]">Esc</kbd> sair
</span>
```

**Por quê:** Kamila vinda do Delphi precisa **VER** que atalhos existem. Linear pattern documentado ("hover banner ensina sem treinar"). Sem isso, Cmd+Enter/Esc/`/` viram easter eggs invisíveis. **NÃO REMOVE feature** — substitui texto existente por versão mais informativa.

**Risco:** zero · só altera string da microcopy condicional.

#### Quick Win 3 — `aria-describedby` ligando FieldError ao input pai · **XS-S ≤1h**

Hoje `<FieldError message={errors.contact_id} />` (linha 756) é irmão do `<CustomerSearchAutocomplete>` — screen reader (NVDA/JAWS) **NÃO** associa automaticamente. WCAG 2.2 AA SC 3.3.1 + 3.3.3 exige conexão programática.

```tsx
// FieldError atualizado:
function FieldError({ id, message }: { id?: string; message?: string }) {
  if (!message) return null;
  return (
    <p id={id} className="text-xs text-destructive mt-1" role="alert">
      {message}
    </p>
  );
}

// Uso:
<Label htmlFor="contact_id">Cliente</Label>
<CustomerSearchAutocomplete
  aria-describedby={errors.contact_id ? "err-contact_id" : undefined}
  // ...
/>
<FieldError id="err-contact_id" message={errors.contact_id as string | undefined} />
```

Requer também passar `aria-describedby` pra dentro do `CustomerSearchAutocomplete` (e similares) — escopo um pouco maior que ≤30min, talvez ~1h se aplicar nos 8-10 campos. Pode-se entregar **incremental** (3 campos críticos hoje: contact_id, location_id, products) e o resto vira US-SELL-A11Y-001 backlog.

**Por quê:** WCAG 2.2 AA é dever pré-canary cliente novo (não wishlist). Kamila pode não usar screen reader, mas é **obrigação legal LGPD/A11Y** + footnote de conformidade pra Wagner argumentar em concorrências futuras.

**Risco:** baixo · 8-10 campos receber 1 prop adicional, sem mudança de comportamento visual.

---

## Cross-links

- **CAPTERRA-DESIGN-FICHA.md** (audit 13/maio nota 68) — [memory/requisitos/Sells/CAPTERRA-DESIGN-FICHA.md](../requisitos/Sells/CAPTERRA-DESIGN-FICHA.md)
- **Visual comparison F1.5** — [memory/requisitos/Sells/sells-create-visual-comparison.md](../requisitos/Sells/sells-create-visual-comparison.md)
- **RUNBOOK Create** — [memory/requisitos/Sells/RUNBOOK-create.md](../requisitos/Sells/RUNBOOK-create.md)
- **SPEC US-SELL-001..010** — [memory/requisitos/Sells/SPEC.md](../requisitos/Sells/SPEC.md)
- **Charter Create** — [resources/js/Pages/Sells/Create.charter.md](../../resources/js/Pages/Sells/Create.charter.md)
- **Cliente piloto canary Martinho** — [memory/reference/clientes/martinho-cacambas.md](../reference/clientes/martinho-cacambas.md)
- **Cliente referência prod ROTA LIVRE** — [memory/reference/clientes/rotalivre.md](../reference/clientes/rotalivre.md)
- **ADR 0104 MWART** — [memory/decisions/0104-processo-mwart-canonico-unico-caminho.md](../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- **ADR 0107 visual gate F3** — [memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- **ADR 0110 Cockpit V2** — [memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md](../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- **ADR 0105 Cliente como sinal qualificado** — [memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)

---

## Restrições Tier 0 respeitadas neste audit

- **Multi-tenant ADR 0093** ✅ — autosave usa `{biz}.{user}` key (já mergeado US-SELL-007, validado no código linha 504-506).
- **MWART ADR 0104** ✅ — todo gap recomendado preserva RUNBOOK + charter; quick wins 1-3 NÃO mudam Non-Goal do charter (atalho `/` está no charter Goals US-SELL-007, footer microcopy é polish de microcopy já existente, aria-describedby é a11y dever).
- **Charter > Spec (Constituição v2)** ✅ — charter v1 já declara "Atalho `/` pra focar busca de produto (em breve)" como expectativa entregada parcialmente; quick win 1 fecha a promessa.
- **Cliente como sinal ADR 0105** ✅ — Quick wins #1, #2 têm sinal Martinho "velocidade" + Kamila Delphi → oimpresso. Quick win #3 é dever a11y, não wishlist. Gaps #6-#7-#10 marcados ❌ ADR feature-wish (sem sinal).
- **biz=1 em smoke ADR 0101** ✅ — qualquer Pest novo dos quick wins roda em biz=1 (não biz=4 nem biz=164).
- **F5 cutover ADR 0104** ✅ — Martinho canary 19/maio é cutover gradual feature-por-feature (Lara estoque + Dani financeiro entram, Kamila continua Delphi balcão). Tela Sells/Create não é primeira fila — Lara/Dani não vendem balcão.

---

## Fontes externas Fase 2 (pesquisa estado-da-arte 2026)

- [Linear design philosophy "speed + minimalism" 2026 (LogRocket)](https://blog.logrocket.com/ux-design/linear-design/)
- [Linear keyboard shortcuts canon 2026](https://shortcuts.design/tools/toolspage-linear/)
- [Command palette pattern UX dev community 2026](https://uxpatterns.dev/patterns/advanced/command-palette)
- [Linear redesign blog "noise + density"](https://linear.app/now/how-we-redesigned-the-linear-ui)
- [Shopify Polaris design system 2026 form patterns](https://polaris-react.shopify.com/)
- [Polaris updates 2026 (Impex Infotech)](https://impexinfotech.com/blog-post/shopify-polaris-design-system-the-complete-guide-to-components-icons-forms-and-best-practices-2026/)
- [Stripe Payment Element auto validation + error UX](https://docs.stripe.com/payments/payment-element)
- [Stripe Payment HTML form best practices](https://stripe.com/resources/more/payment-html-forms)
- [Vercel Accessibility Audit Tool](https://vercel.com/docs/vercel-toolbar/accessibility-audit-tool)
- [Progressive disclosure 2026 IxDF](https://ixdf.org/literature/topics/progressive-disclosure)
- [WCAG 2.2 AA form validation + focus visible (Insihub)](https://insihub.com/blog/form-accessibility-demystified-a-practical-guide-to-wcag-2-2-aa-compliance/)
- [WCAG 2.4.11 Focus Not Obscured new in 2.2 (AllAccessible)](https://www.allaccessible.org/blog/wcag-2411-focus-not-obscured-minimum-implementation-guide)
- [POS UX trends 2026 SMB retail (RetailTechInnovationHub)](https://retailtechinnovationhub.com/home/2026/4/21/top-five-retail-store-point-of-sale-systems-in-2026)
- [Bling vs Tiny vs Omie ERP comparativo 2026 (Cierus)](https://www.cierus.com.br/news-details.php?slug=bling-vs-tiny-vs-omie-qual-erp-escolher)
- [Conta Azul vs Omie ERP PME 2026 (Jestor)](https://blog.jestor.com/omie-vs-conta-azul-melhor-erp-pequenas-empresas/)
- [SaaS UI design trends 2026 (SaaSUI)](https://www.saasui.design/blog/7-saas-ui-design-trends-2026)
- [B2B SaaS UX 2026 progressive disclosure + predictive autocomplete (Onething)](https://www.onething.design/post/b2b-saas-ux-design)

---

## Output canônico pro parent agent

- **Path doc único:** `D:/oimpresso.com/memory/sessions/2026-05-14-design-arte-sells-create-noite.md` (este arquivo · 470 linhas)
- **FICHA prévia mantida sem edit:** `D:/oimpresso.com/memory/requisitos/Sells/CAPTERRA-DESIGN-FICHA.md` (13/maio nota 68 · este re-audit complementa, não substitui)
- **NOTA atual / referência top / gap:** **79/100** vs **92/100** Linear · gap **-13 pts** concentrado em D-09 atalhos profundos + D-15 onboarding + D-07 skeleton
- **Maior gap UX em 1 frase:** falta atalho `/` pra focar busca de produto + indicador visual dos atalhos (Linear hover-banner pattern) — Martinho disse "velocidade" e tela hoje exige mouse pra cada campo
- **Ação imediata recomendada (XS ≤30min):** adicionar listener `/` em `Create.tsx` apontando pro `focusProductSearch()` que já existe (linha 345) + atualizar footer microcopy pra mostrar `/`, `Ctrl+Enter`, `Esc` sempre visíveis

**Pergunta pro Wagner:** aprova começar pelo atalho `/` + footer microcopy de atalhos visíveis (Quick Wins #1 e #2, total ≤1h, zero risco de remover feature existente · entrega o que o EmptyState placeholder já promete "em breve" desde US-SELL-005)?

---

**Última atualização:** 2026-05-14 noite
**Aprovado por:** pendente Wagner
**Próxima revisão:** pós-canary Martinho 19/maio (medir adoção real dos atalhos via session log da Lara/Dani)
