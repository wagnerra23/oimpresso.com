# Visual Comparison — Cliente/Create (W1-B3)

## Pattern reuse (ADR 0149)
Deriva blueprint Cowork Index — mesma família visual (AppShellV2, OKLCH tokens, header pattern).

## Dimensões diff vs Index

| Dimensão | Index | Create |
|---|---|---|
| Layout | list 7xl | form 3xl |
| Header | h1 + KPIs | h1 + breadcrumb |
| Body | table + drawer | 4 sections |
| Action | row click | submit + cancel |

## 4 seções
1. Identificação (tipo + pessoa + nome + doc)
2. Contato (celular + fixo + email)
3. Endereço (linha + cidade + UF + CEP)
4. Financeiro (saldo inicial + crédito + grupo)

## Validação
- HTML5 `required` em campos *
- Server errors display via `useForm.errors`
- Tax number client-side opcional (regex CNPJ/CPF futuro)

## Acessibilidade
- `<Label>` shadcn pra cada Field
- Error text com `text-rose-600` semântico
- Radio person/business com keyboard nav

## Gate F1.5
✅ Aprovado via ADR 0149 (pattern reuse Index canon)
