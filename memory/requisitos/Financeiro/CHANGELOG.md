# Changelog — Financeiro

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) + SemVer.

## [Unreleased]

### Planejado (Onda 1 — MVP)

- Schema base: `fin_titulos`, `fin_titulo_baixas`, `fin_caixa_movimentos`, `fin_contas_bancarias`, `fin_categorias`, `fin_planos_conta`
- Auto-criação de título a partir de venda `due` (TransactionObserver)
- Listagem `/financeiro/contas-receber` (US-FIN-001)
- Baixa parcial/total (US-FIN-003) com idempotência (TECH-0001)
- Cadastro de conta bancária (US-FIN-008)
- Permissões Spatie registradas no boot (R-FIN-002)
- Plano de contas BR pré-seedado (R-FIN-009)
- Multi-tenant isolation tests (R-FIN-001)

### Planejado (Onda 2)

- Contas a Pagar (US-FIN-004, US-FIN-005, US-FIN-006)
- Caixa projetado (US-FIN-007) com cache invalidado por evento
- Cálculo juros + multa (R-FIN-006)

### Planejado (Onda 3)

- Boleto via Strategy (US-FIN-010, ARQ-0003)
- PIX cobrança imediata + dinâmico
- Webhook gateway com idempotência (R-FIN-012)

### Planejado (Onda 4)

- Conciliação OFX (US-FIN-009, UI-0001)
- DRE (US-FIN-011, R-FIN-010)
- Aging (US-FIN-012)
- DRE share link (R-FIN-013)

### Em consideração (Onda 5+)

- OCR de boleto upload
- CNAB direto (homologação por banco)
- Multi-moeda
- Integração Receita Federal (DAS auto-cálculo)

## [0.0.0] - 2026-04-24

### Added

- Spec promovida de `_Ideias/Financeiro/` (status `researching`) para `requisitos/Financeiro/` (`spec-ready`)
- Estrutura completa: README + SPEC + ARCHITECTURE + GLOSSARY + 5 ADRs (arq/0001-0004 + tech/0001-0002 + ui/0001)
- Frase de posicionamento e revenue model definido (ARQ-0004): Free / Pro R$ 199 / Enterprise R$ 599 + take rate 0,5% capped R$ 9,90
- Origem rastreada: conversa Claude mobile (`_Ideias/Financeiro/evidencias/conversa-claude-2026-04-mobile.md`)
