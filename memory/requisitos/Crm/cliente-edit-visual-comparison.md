# Visual Comparison — Cliente/Edit (W1-B3)

## Pattern reuse (ADR 0149)
Family visual idêntica a Create — mesma estrutura 4 sections 3xl + Field/Section/Input pattern.

## Diff vs Create

| Dimensão | Create | Edit |
|---|---|---|
| Submit | POST | PUT |
| Breadcrumb | "voltar pra lista" | "voltar pra detalhe" |
| Title | "Novo cliente" | "Editar cliente" + nome |
| Subtitle | "preencha…" | nome do cliente |
| Prefill | `?prefill_name=` | Eloquent contact |

## Gate F1.5
✅ Aprovado via ADR 0149 (pattern reuse Index, family Create)

## Hidden behaviors
- Não muda `type` (customer→supplier) durante edição: dropdown disabled
- `opening_balance` já vem ajustado (descontado paid amount)
- `customer_group_id` mostra dropdown só pra `type=customer/both`
