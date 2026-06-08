---
component: resources/js/Pages/Sells/_components/PaymentRow.tsx
status: test-pending
reason: Vitest não configurado no projeto (package.json sem vitest/jest scripts);
        infra de teste JS pendente — escrever Pest viewport com Playwright ou
        Vitest quando ADR de design system (P0-1/P0-2/P0-3) for aceita.
related_session: memory/sessions/2026-05-17-tela-venda-arte-responsivo.md
related_gap: P1-2 (PaymentRow cartão = avalanche em mobile)
date: 2026-05-17
---

# Casos de teste manuais — PaymentRow refatorado

Documento append-only. Quando Vitest/Playwright entrar, transformar em
`PaymentRow.test.tsx` (Vitest + @testing-library/react) OU
`tests/Browser/Sells/PaymentRowMobileTest.php` (Pest 5+ Playwright).

## Casos cobertos pela refatoração 2026-05-17 (P1-2 dossier responsivo)

### 1. Render mobile (<768px) — stack vertical em card

**Setup:** viewport 375×667 (iPhone SE), `payment.method = 'cash'`.

**Asserts:**
- `<div class="rounded-md border border-border p-4 ...">` envelopa o componente
- Header mobile visível: `<h3>Pagamento 1</h3>` com class `text-sm font-semibold`
- Botão delete (Trash2) no header com `h-11 w-11` (44×44px Apple HIG)
- Grid principal `grid-cols-1` ativo (não `lg:grid-cols-4`)
- Input "Valor" tem class `text-lg font-semibold` (destaque mobile)
- Inputs de Método/Pago em col-span-2 (sm:) → ocupam 2 colunas em sm 640+

### 2. Render desktop (≥1024px) — paridade visual com layout legacy

**Setup:** viewport 1280×800 (Larissa baseline), `payment.method = 'cash'`.

**Asserts:**
- Header mobile escondido: `<h3>Pagamento 1</h3>` com class `md:sr-only`
- Botão delete no header escondido (sr-only inclui o botão)
- Botão delete versão desktop visível: `hidden md:inline-flex absolute top-2 right-2`
- Grid principal `lg:grid-cols-4` ativo (Valor/Método/Pago/Conta em linha)
- Input "Valor" perde destaque mobile: `md:text-sm md:font-normal`
- Inputs altura desktop: `md:h-9` (36px — padrão Input shadcn)

### 3. Cartão: `<details>` colapsado em mobile, aberto em desktop

**Setup A (mobile):** viewport 375px, `payment.method = 'card'` (selecionar método cartão).

**Asserts mobile:**
- `<details>` ref aciona `open=false` via `useEffect` ao mount em viewport <768
- `<summary>` "Detalhes do cartão" visível (`md:hidden` permite mostrar em mobile)
- Os 7 inputs cartão (`card_number`, `card_holder_name`, `card_transaction_number`,
  `card_type`, `card_month`, `card_year`, `card_security`) ESCONDIDOS por padrão
  (até usuário expandir o details)
- Click no summary expande → todos 7 inputs visíveis

**Setup B (desktop):** viewport 1280px, `payment.method = 'card'`.

**Asserts desktop:**
- `<details open>` permanece open (SSR-default — useEffect NÃO fecha em md+)
- `<summary>` escondido (`md:hidden`) — usuário desktop nunca vê o toggle
- 7 inputs cartão visíveis em grid `md:grid-cols-3` (paridade visual atual)
- Sem flash de conteúdo escondido no first paint (SSR open default)

### 4. onChange dispara com `field` + `value` corretos

**Setup:** mock `onChange = vi.fn()`.

**Casos:**
- Mudar Valor → `onChange(index, 'amount', Number(value))` chamado
- Mudar Método via Select → `onChange(index, 'method', 'card')`
- Mudar Conta (Select) → `onChange(index, 'account_id', 5)` quando valor=5
- Mudar Conta pra vazio → `onChange(index, 'account_id', null)` (não NaN, não 0)
- Mudar Nota → `onChange(index, 'note', 'parcela 1/3')`
- Mudar card_security maxLength → input não aceita >4 chars

### 5. onRemove dispara com `index` correto

**Casos:**
- `removable=true` + click delete mobile (header) → `onRemove(0)` quando index=0
- `removable=true` + click delete desktop (absolute) → `onRemove(2)` quando index=2
- `removable=false` → ambos botões delete NÃO renderizam (`{removable && (...)}`)

### 6. Touch targets ≥44px em mobile

**Asserts (computed style ou class assertion):**
- Todos `<input>` têm classe `h-11` ativa em viewport <768
- Todos `<SelectTrigger>` têm classe `h-11` ativa em viewport <768
- Botão delete mobile (header): `h-11 w-11`
- Em desktop ≥768px: `md:h-9` (36px) sobrescreve — touch target relaxado OK
  (desktop usa mouse, não toque)

### 7. A11y: labels + aria

**Asserts:**
- Cada `<Input>` tem `<Label htmlFor="payment-{N}-{field}">` adjacente
- `<Input id="payment-{N}-{field}">` matching → click no label foca o input
- Botão delete tem `aria-label="Remover pagamento {N+1}"` (1-indexed)
- Quando `errors={amount: 'Valor obrigatório'}` passado como prop:
  - Input recebe `aria-invalid={true}`
  - Input recebe `aria-describedby="payment-{N}-amount-error"`
  - `<p role="alert" id="payment-{N}-amount-error">Valor obrigatório</p>` renderiza
  - `<p>` tem class `text-destructive` (não cor crua)
- Quando `errors` undefined ou prop ausente: nenhum `<p role="alert">` renderiza

### 8. Cheque / TED / Custom: condicional render

**Casos:**
- `method='cheque'` → bloco com `cheque_number` renderiza, cartão NÃO renderiza
- `method='bank_transfer'` → `bank_account_number` renderiza
- `method='custom_pay_1'` → `transaction_no` renderiza
- `method='cash'` → nenhum dos 4 blocos extras renderiza

### 9. Interface `Payment` intocada (não-breaking)

**Asserts (TypeScript compile-time, não runtime):**
- `import PaymentRow, { type Payment } from './PaymentRow'` continua funcionando
- Todos os 18 fields originais de `Payment` permanecem (amount, method, paid_on,
  account_id, note, card_*, cheque_number, bank_account_number, transaction_no)
- Nenhum field renomeado nem removido
- Novo type `PaymentErrors` exportado SEPARADAMENTE — não invade `Payment`

## Como rodar quando Vitest entrar

```bash
npm install --save-dev vitest @testing-library/react @testing-library/jest-dom \
  @testing-library/user-event jsdom @vitest/coverage-v8

# vitest.config.ts:
#   test: { environment: 'jsdom', globals: true, setupFiles: 'tests/setup.ts' }

# tests/setup.ts:
#   import '@testing-library/jest-dom'
#   window.matchMedia = vi.fn().mockImplementation(query => ({ matches: false, ... }))

npm test resources/js/Pages/Sells/_components/PaymentRow.test.tsx
```

## Validação manual recomendada antes do merge

1. Abrir `/sells/create` no Chrome DevTools com device mode iPhone SE 375×667
2. Adicionar pagamento método = cartão → verificar `<details>` fechado, summary "Detalhes do cartão" clicável
3. Expandir details → 7 campos cartão visíveis em stack 1-col (até sm:) ou 2-col (sm+)
4. Mudar viewport pra 1280px → 7 campos aparecem expandidos automaticamente em grid 3-col, summary escondido
5. Tocar inputs com Touch emulation → cada input >44px (medir via DevTools)
6. Click delete → `onRemove` dispara, linha removida da lista
