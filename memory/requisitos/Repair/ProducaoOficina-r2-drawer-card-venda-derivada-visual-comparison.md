---
tela: /repair/producao-oficina (drawer · adição cirúrgica)
componente: resources/js/Pages/Repair/ProducaoOficina/Index.tsx (JobDrawer)
onda: Onda 5 — Integração Vendas × Oficina (A1 KB-9.75)
status: aprovado-cowork — mapeamento direto F1 protótipo
fonte_cowork: prototipo-ui/oficina-page.jsx (linhas 392-458) + prototipo-ui/oficina-page.css (linhas 465-598)
adr: 0192 (auto-faturar OS→Venda) · 0121 §P8 (vocabulário shared) · 0093 (multi-tenant Tier 0)
data: 2026-05-25
worker: B (sub-agent paralelo)
---

# Visual Comparison — Drawer card "Esta OS gerou venda"

> **Escopo:** adição cirúrgica ao drawer existente. NÃO toca kanban, drag-and-drop, filtros, mock data, ou demais sections do drawer. Renderiza UM card novo no topo do drawer body **quando** `card.approved === true` (= coluna `pronto` = FSM `entregue_completo`) AND `card.venda_derivada !== null`.

## Mapeamento Cowork → Inertia React

### Fonte Cowork (`prototipo-ui/oficina-page.jsx` linhas 392-458)

Drawer body do `oficina-page.jsx` tem `{showVendaCard && (...)}` que renderiza `.ofc-venda-card` com:

- `.ofc-venda-flag` — pill canto superior esquerdo "✎ Integração Vendas×Oficina"
- `.ofc-venda-head` — grid 2-col: título "Esta OS gerou a venda #V-NNNN" + small descritivo · estágio à direita
- `.ofc-venda-grid` — 3-col com `Total venda` / `Peças (NF-e)` / `Mão-de-obra (NFS-e)` (CONDICIONAL — descartado nesta onda · charter Non-Goal por ora)
- `.ofc-venda-fiscal` — badges NF-e / NFS-e (CONDICIONAL — descartado nesta onda · charter Non-Goal por ora)
- `.ofc-venda-actions` — 4 botões: `Abrir #ID ↗` primary + DANFE NF-e + DANFS-e + Ver no Caixa

### Adaptação Inertia (Onda 5)

Payload **real** do Controller (Onda 2 ADR 0192) entrega APENAS 4 fields em `venda_derivada`:

```json
{
  "venda_derivada": {
    "id": 456,
    "invoice_no": "V-456",
    "final_total": 150.00,
    "transaction_date": "2026-05-25"
  }
}
```

Peças/serviço breakdown e fiscal NF-e/NFS-e **não chegam ainda** — adiada pra wave futura (charter Non-Goal). Renderizamos o que temos + placeholder TODO no charter pra evoluir.

## Dimensões avaliadas

### 1. Hierarquia visual

- **Cowork:** card destacado verde (oklch emerald 155° hue) com flag pill canto superior + título bold + estágio capsule à direita
- **Inertia:** mesma hierarquia preservada — card `.ofc-venda-card` highlight verde + flag pill + título com `#V-NNNN` em `<code>` mono + total + data

### 2. Tokens cor/tipo (oklch)

| Token Cowork | Valor | Uso |
|---|---|---|
| Card bg | `linear-gradient(135deg, oklch(0.96 0.05 155), oklch(0.985 0.003 90))` | gradient verde-suave |
| Card border | `oklch(0.50 0.13 155)` | verde sólido |
| Flag pill bg | `oklch(0.50 0.13 155)` | verde sólido · texto branco |
| Code mono | `oklch(0.50 0.13 155)` | identificador venda |
| CTA primary | `oklch(0.50 0.13 155)` bg + white text | botão "Abrir" |
| CTA secondary | white bg + verde border `oklch(0.94 0.06 155)` | "Imprimir recibo" / "Compartilhar" |

Preservados verbatim. Sem hue shift — verde 155° (success/emerald).

### 3. Layout

- Card no **topo** do drawer body (antes de outras sections)
- Width herda do drawer (480px)
- Padding `18px 20px 16px` (Cowork)
- Border-radius 10px (Cowork)

### 4. Vocabulário shared (CRÍTICO — CI guard)

✅ **Não usa termos automotivos** — apenas `venda_derivada`, `invoice_no`, `final_total`, `transaction_date`, `os_ref` (genérico cross-vertical conforme ADR 0121 §P8).

✅ Card label PT-BR neutro: **"Esta OS gerou a venda #V-NNNN"** — funciona pra OficinaAuto (carro), ComunicacaoVisual (arte), Vestuario (peça reparada).

### 5. Acessibilidade

- Botões `<button type="button">` (não `<a>`)
- `aria-label="Abrir venda V-NNNN"` no CTA primary (anti-screen-reader noise)
- Contraste verde/branco WCAG AA (oklch 0.50 vs 1.0 = ~4.7:1 ratio)
- Foco visível keyboard (`focus:ring-2 ring-emerald-500/40`)

### 6. Dispatch CustomEvent (cross-módulo)

```ts
window.dispatchEvent(
  new CustomEvent('oimpresso:open-venda', {
    detail: { venda_id: card.venda_derivada.id },
  }),
);
```

Worker A em Onda 4 registra listener em `Sells/Index.tsx`. Esta Onda 5 **só dispara** — não precisa garantir handler exist (graceful degradation).

### 7. Estados condicionais

| Condição | Render |
|---|---|
| `!card.approved` | Não renderiza (card só na coluna `pronto`) |
| `card.approved && !card.venda_derivada` | Não renderiza (Observer ADR 0192 ainda não criou Transaction — OS pré-existente pré-deploy) |
| `card.approved && card.venda_derivada` | Renderiza card highlight verde com 3 CTAs |

### 8. Atalhos (3 botões)

1. **"Abrir #V-{invoice_no}"** (primary verde) → dispatch event
2. **"Imprimir recibo"** (secondary outline verde) → `window.open('/sells/' + venda_id + '/print', '_blank')`
3. **"Compartilhar"** (secondary outline verde) → **placeholder TODO** · charter Non-Goal por ora (botão visível mas `onClick` no-op)

### 9. Non-Goals desta onda (Wagner aprovou plano F3)

- ❌ Breakdown peças/serviço (Cowork tem mas payload backend não entrega · charter Non-Goal · wave futura)
- ❌ Badges fiscais NF-e/NFS-e (charter Non-Goal · wave futura)
- ❌ Botão "Ver no Caixa do dia" (Sells/Caixa.tsx é Onda 6 wave separada · charter Non-Goal)
- ❌ Vocabulário automotivo (`placa`, `box`, `mecanico`) — CI guard `repair-shared-vocab.yml` bloqueia
- ❌ Acoplamento direto Sells/Index — usa CustomEvent (loose coupling)

### 10. Anti-padrões UI evitados

- ❌ Modal (drawer continua sendo único container)
- ❌ Loading skeleton (payload vem inline)
- ❌ Toast/snackbar
- ❌ Cores berrantes (verde oklch 155° conservador)
- ❌ Animação decorativa (só hover transition Cowork)

### 11. Multi-tenant Tier 0 (ADR 0093)

- ✅ `venda_derivada` payload já vem scopado pelo Controller (Onda 2)
- ✅ Frontend só renderiza — não dispara queries adicionais
- ✅ `id` da venda é o ID interno do business correto (lookup batch já scopado)

### 12. Performance

- Zero queries adicionais (payload vem com `venda_derivada` já hidratado · Onda 2)
- Card renderiza condicional — não custa nada se OS não tem venda
- 3 CTAs sem fetch (dispatch event + open + no-op)

### 13. Anti-hooks Cowork preservados

✅ Sem CTA WhatsApp · sem emoji em UI (só símbolos canônicos ↗) · UI 100% português · sem rounded-xl+ (radius 6-10px) · tokens oklch dentro paleta.

### 14. Charter compliance

Adicionar à `Goals`:
- Card "Esta OS gerou a venda #V-NNNN" no drawer quando OS está em coluna `pronto` AND tem `venda_derivada !== null` (ADR 0192 · Onda 5)
- 3 CTAs: Abrir (dispatch `oimpresso:open-venda` → Worker A Sells/Index listener) · Imprimir recibo (`/sells/{id}/print`) · Compartilhar (placeholder TODO)

Adicionar à `Non-Goals`:
- Botão "Compartilhar" sem ação por ora (placeholder visual · backlog wave futura)
- Breakdown peças/serviço no card (Cowork tem mas payload backend não entrega · wave futura)
- Badges fiscais NF-e/NFS-e (wave futura)
- Edição inline da venda derivada (drawer só lê)

### 15. Pest GUARD (apêndice)

Test novo em `Modules/Repair/Tests/Feature/ProducaoOficinaTest.php`:

```php
it('Onda 5: payload cards contém field venda_derivada (null OR shape correto)', function () {
    // Valida que controller payload tem shape compatível com Index.tsx Onda 5
    // (frontend espera: id INT · invoice_no STRING · final_total FLOAT · transaction_date STRING)
});
```

---

## Aprovação Wagner

Wagner aprovou screenshot F1 do protótipo Cowork em PR #1493 (`b2fcabbf2`) 2026-05-25. ADR 0192 (Onda 0) aceito · Onda 1 + Onda 2 mergeadas. Esta Onda 5 é mapeamento direto do `.ofc-venda-card` Cowork pra Inertia React preservando tokens + hierarquia + vocabulário shared.

Sem screenshot novo necessário — adição cirúrgica reproduz protótipo já aprovado.
