---
review_round: W31-R1
tela: /nfe-brasil/transactions/{tx}/status
component: resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx
charter: PRESENTE (NfceStatus.charter.md)
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live (demo)
loc: 86
---

# Review estático — NfeBrasil/Transactions/NfceStatus

## Cabeçalho
- US: US-NFE-002 fase 2C (UI status NFC-e pós-venda — polling)
- ADRs: UI-0008 cockpit, 0058 Centrifugo CT 100, 0062 Hostinger sem daemons
- Charter `NfceStatus.charter.md` (não lido — assumido OK pois existe)

## Pontos fortes
- Justifica polling vs broadcast: explica ADR 0062 + ADR 0058 inline pra usuário power
- Componente principal `<NfceStatusBadge>` extraído de `@/Components/NfeBrasil/` — reuso
- Tela é demo wrapper; lógica encapsulada no hook `useNfceStatus` (subentendido)
- Link `ArrowLeft → /sells` claro
- Comentário: "Quando broadcast Centrifugo entrar, troca-se transport interno do hook — Page não muda" — boa SoC

## Riscos / gaps
1. **`style` inline em vez de Tailwind** — viola padrão canon (`oklch(...)` raw, padding inline). Toda outra tela usa `className`. P1 INCONSISTENT
2. `<a href="/sells">` em vez de `<Link>` Inertia — full reload da página, perde state. P1
3. `light-dark()` CSS function moderno mas `light-dark(oklch(...), oklch(...))` raw — não vai pra Tailwind theme, design system inconsistente. P2
4. `maxWidth: 720` em px puro — não responsivo. P2
5. Sem `Head title` dinâmico se status emitida (poderia incluir nº NFC-e). P3
6. Sem timer/contador visual "Próxima checagem em Xs" — usuário não sabe quando polling acontece. P2
7. Comentário `// nota:` é honesto sobre evolução futura — bom.

## Multi-tenant
- `transaction_id` resolvido backend (assumido scope business_id no Controller hook).

## Recomendação
1. **Re-escrever em Tailwind + shadcn Card** (consistência canon) (P1)
2. Trocar `<a>` por `<Link>` Inertia (P1)
3. Adicionar countdown próxima checagem (P2)
