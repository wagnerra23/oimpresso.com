# Visual Comparison — Cliente/Ledger (W1-B3)

## Divergência (ADR 0149)
**Tabela financeira densa** — layout extrato (débito/crédito/saldo) divergente do Index card list.

## Justificativa
- Foco em densidade dados (tabela 6 colunas) — diferente de Index list-detail
- Background blanco puro (sem `bg-muted/30`) pra contraste em PDF print
- Cores semânticas obrigatórias: débito rose / crédito emerald / saldo neutro

## Layout
- Header 7xl + breadcrumb voltar + 3 KPIs
- Filter bar: 2 datas + format select + location select + apply button
- Table 6 cols: Data | Documento | Descrição | Débito | Crédito | Saldo
- Empty state mensagem PT-BR

## Cores semânticas
- Débito: `text-rose-700` (saída)
- Crédito: `text-emerald-700` (entrada)
- Saldo positivo: vermelho rose (a receber)
- Saldo zero: neutro foreground

## Action exports
- PDF (abre nova aba — fluxo legacy mPDF preservado)
- Excel (download direto)

## Gate F1.5
✅ Divergência aprovada via ADR 0149 (utility report genérico)
