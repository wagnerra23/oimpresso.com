---
tela: /repair/producao-oficina (drawer · VendaDerivadaCard evolution)
componente: resources/js/Pages/Repair/ProducaoOficina/Index.tsx (VendaDerivadaCard)
fase: FASE B — pós Wave Z-2 backend W2 (`2f6f10fc8`)
status: aprovado-cowork — mapeamento direto F1 protótipo expandido
fonte_cowork: prototipo-ui/oficina-page.jsx (linhas 392-458) + prototipo-ui/oficina-page.css (linhas 526-598)
adr: 0192 (auto-faturar OS→Venda) · 0121 §P8 (vocabulário shared) · 0093 (multi-tenant Tier 0)
data: 2026-05-25
worker: B (sub-agent FASE B paralelo · pós Wave Z-2 merged)
backend_dep: PR #1510 (`2f6f10fc8`) — buildVendaDerivadaPayload expand items_list + items_summary + fiscal
---

# Visual Comparison — VendaDerivadaCard evolution (items_list + fiscal badge)

> **Escopo:** evolução cirúrgica do card existente. Adiciona breakdown peças/serviço + badge fiscal NF-e + lista items expandível. NÃO toca kanban, drag-and-drop, filtros, mock data, drawer sections legacy, handlers Onda 5/W3 (Abrir/Imprimir recibo/Compartilhar). Empty states tolerantes preservam backward compat com payload Onda 5 (apenas core fields).

## Mapeamento Cowork → Inertia React (delta vs r2)

### Antes (r2 · Onda 5 entregue 2026-05-25)

Card mostrava só:
- Header "Esta OS gerou a venda #V-NNNN" + flag pill
- Grid 2-col (Total + Data)
- 3 CTAs (Abrir / Imprimir recibo / Compartilhar W3)

### Agora (r3 · FASE B)

Card adiciona entre grid Total/Data e CTAs:

1. **Breakdown peças/serviço** — grid 2-col responsive (empilha em mobile) com `Peças · N itens · R$ X` + `Serviços · N itens · R$ Y` + linha `Subtotal · R$ Z` + (condicional) `Desconto · -R$ W` + (condicional) `Impostos · +R$ V`
2. **Badge fiscal NF-e** — 4 estados condicionais (autorizada verde + DANFE link · pendente amber · rejeitada rose · null slate "Sem nota fiscal")
3. **Lista items expandível** — disclosure `▸ Ver N itens da venda` (collapsed default) → `▾ Ocultar` expanded, com cap 10 items + "+ N adicionais"

## Dimensões avaliadas (15 dimensões)

### 1. Hierarquia visual

- **Cowork F1 mantida:** card destacado verde (oklch emerald 155° hue) com flag pill canto superior + título bold
- **Nova hierarquia interna:** Total/Data → Breakdown → Fiscal badge → Items list (collapsed) → 3 CTAs. Densidade cresce progressivamente.

### 2. Densidade informativa ⭐ (foco solicitado)

- **Antes:** ~80px altura útil do card (header + grid 2-col + 3 botões)
- **Depois:** ~160-240px collapsed / ~340-440px expanded (até 10 items)
- **Justificativa cap 10:** drawer body é 480px wide × ~600-800px viewport. Lista ilimitada quebraria scroll/UX. Sumário "+ N adicionais" sinaliza overflow sem custar densidade. Drilldown completo continua em `/sells/{id}` (CTA "Abrir #V-NNNN").
- **Empty states tolerantes** evitam vazamento de densidade desnecessária quando payload backend vem mínimo (sem items_list ou sem fiscal).

### 3. Hierarquia visual interna ⭐ (foco solicitado)

Pattern Linear/Notion: scan vertical top-down:

```
┌─ flag pill (9px mono uppercase)
├─ TÍTULO bold + invoice code mono (14px)
├─ subtítulo emerald-800/80 (11px) — origem
├─ Grid 2-col Total/Data (sm 14px)
├─ ─── Breakdown ─── (bg white/65 contained)
│  ├─ Peças (count + total) · Serviços (count + total)  ←  grid 2-col
│  └─ border-t emerald-100
│     ├─ Subtotal               R$ X     ←  sempre
│     ├─ Desconto              -R$ Y     ←  if > 0 (rose)
│     └─ Impostos              +R$ Z     ←  if > 0 (slate)
├─ Fiscal badge — pill 11px (autorizada/pendente/rejeitada/null)
├─ ▸/▾ Ver N itens (collapsed default)
│  └─ ul items (11.5px texto · prefix "Peça"/"Serviço")
└─ 3 CTAs (primary verde + 2 secondary outline)
```

Cada bloco tem subtítulo uppercase 9.5px tracking-wider · valor mono semibold pra leitura financeira rápida.

### 4. Tokens cor/tipo (oklch preservados Cowork verbatim)

| Token Cowork | Valor | Uso novo (FASE B) |
|---|---|---|
| `.ofc-fb-ok` | `color: oklch(0.50 0.13 155); border: oklch(0.94 0.06 155)` | badge `autorizada` verde |
| `.ofc-fb-wait` | `color: oklch(0.55 0.13 60); border: oklch(0.94 0.06 60)` | badge `pendente` amber |
| `.ofc-fb-bad` | `color: oklch(0.55 0.18 25); border: oklch(0.94 0.07 25)` | badge `rejeitada` rose |
| `.ofc-fb-na` | `color: var(--text-mute)` | badge `null` slate |
| `.ofc-vc` bg | `rgba(255,255,255,0.65)` | cards Peças/Serviços + items ul |
| `.ofc-vc` border-top | `oklch(emerald-100)` | divider subtotal/desconto/impostos |

Tailwind classes mapeadas: `border-emerald-200 text-emerald-700` (autorizada) · `border-amber-200 text-amber-700` (pendente) · `border-rose-200 text-rose-700` (rejeitada) · `border-slate-200 text-slate-500` (null).

### 5. Empty states tolerantes ⭐ (foco solicitado)

Backward compat com payload Onda 5 (apenas core fields) garantida via:

```ts
const itemsList = venda.items_list ?? [];
const summary = venda.items_summary;
const hasBreakdown = !!summary && itemsList.length > 0;
const fiscal = venda.fiscal ?? null;
```

| Cenário payload | Render |
|---|---|
| Onda 5 puro (sem `items_list`, sem `fiscal`) | header + Total/Data + badge "Sem nota fiscal" + 3 CTAs |
| W2 expand: `items_list=[]`, `fiscal=null` | mesmo do Onda 5 (graceful) |
| W2 expand: `items_list=[3]`, `fiscal=null` | + breakdown + disclosure + badge "Sem nota fiscal" |
| W2 expand: `items_list=[5]`, `fiscal.status='autorizada'` | + breakdown + disclosure + badge verde + DANFE link |
| W2 expand: `items_list=[12]`, `fiscal.status='pendente'` | + breakdown + disclosure (10 visíveis + "+2 adicionais") + badge amber |

Anti-regressão: Pest GUARD `FASE B: empty states tolerantes — hasBreakdown gateia render` valida que ambos os blocos breakdown e disclosure ficam fora do DOM quando `hasBreakdown=false`.

### 6. Vocabulário shared (CRÍTICO — CI guard `repair-shared-vocab.yml`)

✅ **Não usa termos automotivos** — `Peças` e `Serviços` são genéricos cross-vertical (Comunicação Visual: peça = lona/banner · serviço = instalação; Vestuário: peça = roupa reparada · serviço = ajuste).

✅ Pest GUARD `FASE B: vocabulário shared cross-vertical` extrai apenas o JSX dentro de `function VendaDerivadaCard` e proíbe `placa|vehicle|mecanico|elevador` (anti false-positive vs mock data automotivo).

### 7. Acessibilidade (WCAG AA)

- Disclosure button: `aria-expanded={itemsExpanded}` + `aria-controls={\`venda-items-${venda.id}\`}` (relacionamento semântico ARIA)
- DANFE button: `aria-label={\`Abrir DANFE da venda ${venda.invoice_no}\`}`
- Símbolos `▸/▾` marcados `aria-hidden="true"` (texto "Ver/Ocultar N itens" carrega significado)
- Contraste rose-700/white = ~5.2:1 · amber-700/white = ~4.8:1 · emerald-700/white = ~5.4:1 · todos AA
- Foco visível keyboard preservado (`focus:ring-2 ring-emerald-500/40` nos botões)

### 8. Disclosure pattern (collapsed por default — UX intencional)

**Por que collapsed:** drawer 480px wide é stack denso. OS pode ter 1-15 items. Lista sempre expanded explodiria scroll. Padrão Notion/Linear: detalhes ricos atrás de toggle.

**Por que ▸/▾ (não +/-):** símbolos canônicos Unicode triangle (`U+25B8` collapsed / `U+25BE` expanded) são WCAG-friendly e funcionam em qualquer font. ZERO emoji (ADR pageheader-canon).

### 9. Vocabulário "Peça" vs "Serviço" (prefix textual)

Cowork F1 protótipo usa emoji `📦 produto / 🛠 serviço`. **Substituído** por prefix textual `Peça · {name}` / `Serviço · {name}` per skill `pageheader-canon` (ZERO emoji em UI). Pest GUARD anti-regressão valida ausência dos 3 emojis comuns (📦 🛠 🔧).

### 10. Multi-tenant Tier 0 (ADR 0093)

- ✅ `items_list` + `items_summary` + `fiscal` vêm scoped pelo Controller (W2 backend · `Transaction::where('business_id', $businessId)` + `NfeEmissao::where('business_id', ...)`)
- ✅ Frontend só renderiza — Pest GUARD valida ausência de `router.get/post`, `axios`, `fetch` dentro do componente
- ✅ DANFE link aponta pra `/danfe/{id}` server-side (gated por business_id no NfeBrasil module)

### 11. Performance

- Zero queries adicionais (W2 backend já entregou anti-N+1 com eager-load + batch lookup)
- `useState(false)` único pro itemsExpanded — re-render barato
- `useMemo` desnecessário (cap 10 + slice O(1))
- Cards collapsed: ~0 custo render dos items
- Cards expanded: max 10 `<li>` simples sem componente filho

### 12. Anti-padrões UI evitados

- ❌ Modal (drawer continua sendo único container)
- ❌ Loading skeleton (payload inline)
- ❌ Toast/snackbar pra disclosure (operação client-side)
- ❌ Animação decorativa (só transition em hover/focus dos botões)
- ❌ Emoji em UI (prefix textual + símbolos Unicode triangle)
- ❌ Drilldown per-item dentro do card (CTA "Abrir #V-NNNN" cobre)
- ❌ Edição inline (drawer só lê)

### 13. Anti-hooks Cowork preservados

✅ Sem CTA WhatsApp · sem emoji em UI (só símbolos canônicos ↗ ▸ ▾) · UI 100% português · sem rounded-xl+ (radius 4-10px) · tokens oklch dentro paleta · vocabulário shared cross-vertical.

### 14. Charter compliance

Adicionado à `Goals`:
- Card mostra breakdown Peças vs Serviços + linha Subtotal/Desconto/Impostos
- Badge fiscal NF-e 4 estados (autorizada/pendente/rejeitada/null)
- Lista items expandível (collapsed default · cap 10 + "+ N adicionais")
- Prefix textual "Peça"/"Serviço" (ZERO emoji)
- Empty states tolerantes (backward compat Onda 5)

Mantido em `Non-Goals`:
- NFS-e badge (NfeBrasil não emite NFS-e ainda)
- Edição inline da venda derivada
- Items list ilimitada (cap 10 + sumário)
- Drilldown per-item
- Botão "Ver no Caixa do dia" (Onda 6 separada)

### 15. Pest GUARDs (file-content pattern W3)

Arquivo `Modules/Repair/Tests/Feature/ProducaoOficinaFaseBVendaDerivadaCardTest.php` com 14 GUARDs:

1. Interfaces TS (VendaItem/VendaItemsSummary/VendaFiscal) definidas
2. VendaDerivada com fields opcionais (backward compat)
3. useState(false) pro itemsExpanded (collapsed default) + aria-expanded
4. Breakdown renderiza count + total formatados BRL pra ambos types
5. Subtotal sempre + Desconto/Impostos condicionais
6. Badge fiscal 4 estados completos
7. Autorizada tem DANFE button com window.open + aria-label
8. Prefix textual "Peça"/"Serviço" (ZERO emoji 📦 🛠 🔧)
9. Cap 10 visíveis + "+ N adicionais"
10. Disclosure usa símbolos canônicos ▸/▾
11. Empty states gateados por `hasBreakdown`
12. Backward compat — keys core preservadas
13. Handlers Onda 5+W3 preservados (Abrir/Imprimir/Compartilhar)
14. Vocabulário shared (zero termos automotivos no card)
15. Multi-tenant Tier 0 (frontend NÃO dispara queries)

---

## Decisões UI tomadas (FASE B)

| Decisão | Alternativa rejeitada | Justificativa |
|---|---|---|
| Items list **collapsed** por default | Sempre expanded | Densidade drawer 480px · evita scroll explosivo · pattern Notion/Linear |
| Cap **10 items** + "+ N adicionais" | Lista ilimitada com scroll interno | UX simples sem scroll-in-scroll · drilldown completo via CTA "Abrir #V-NNNN" |
| Prefix textual "Peça"/"Serviço" | Emoji 📦 🛠 (Cowork F1) | skill `pageheader-canon` ZERO emoji em UI |
| Símbolos Unicode ▸/▾ | Chevron SVG ou +/- | Unicode canônico funciona em qualquer font · WCAG-friendly · zero deps |
| Badge "Sem nota fiscal" sutil slate | Não renderizar badge quando `fiscal=null` | Sinaliza intencionalidade (OS informal sem nota) vs ausência de feature |
| DANFE como link separado | Inline no badge "autorizada" | Click-target maior (44×44 mínimo) · aria-label dedicado · pattern accessibility |
| Subtotal **sempre** + Desconto/Impostos condicionais | Mostrar todos com R$ 0,00 | Reduz noise visual · clareza financeira |
| Grid 2-col responsive (`sm:grid-cols-2 → grid-cols-1`) | 2-col fixo | Drawer pode shrinkar em viewport mobile · mobile-first |
| Border-t emerald-100 antes do Subtotal | Sem divider | Hierarquia visual: "agregados" vs "componentes" |

---

## Aprovação Wagner

Wagner aprovou screenshot F1 do protótipo Cowork em PR #1493 (`b2fcabbf2`) 2026-05-25 — esta FASE B é mapeamento direto da seção `.ofc-venda-grid` + `.ofc-venda-fiscal` + items list Cowork pra Inertia React preservando tokens + hierarquia + vocabulário shared, agora com payload backend W2 (`2f6f10fc8`) entregando o expansão real.

Sem screenshot novo necessário — evolução cirúrgica reproduz protótipo já aprovado, adaptando empty states tolerantes pra backward compat Onda 5 e respeitando ZERO emoji em UI (skill pageheader-canon).
