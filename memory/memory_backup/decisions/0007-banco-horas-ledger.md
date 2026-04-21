# ADR 0007 — Banco de horas como ledger append-only

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

Banco de horas precisa permitir: créditos (HE), débitos (compensação/falta), ajustes manuais, expiração automática (6 meses conforme Reforma Trabalhista), pagamento. O saldo pode ser discutido em auditoria ou tribunal. O gestor pode precisar justificar "como chegou a +42h".

Duas abordagens:

- **Saldo único atualizado in-place** — uma linha por colaborador, UPDATE toda vez. Simples mas destrói histórico
- **Ledger append-only** — uma linha por movimento, saldo calculado ou materializado. Cada evento preservado

## Decisão

**Adotar padrão ledger duplo:**

- `ponto_banco_horas_movimentos` — tabela append-only de movimentos, UUID, com triggers MySQL bloqueando UPDATE/DELETE (igual marcações, ADR 0003)
- `ponto_banco_horas_saldo` — materialização do saldo atual por colaborador, atualizado em transação junto com insert do movimento

Cada movimento tem: tipo (CREDITO/DEBITO/EXPIRACAO/PAGAMENTO/AJUSTE_MANUAL), minutos, origem, motivo, apuracao_dia_id opcional. Soma do ledger = saldo.

## Consequências

### Positivas

- Auditoria total: todo movimento rastreável
- Reconstrução do saldo em qualquer data é trivial (sum até essa data)
- Ajustes manuais são eventos visíveis, não edições invisíveis
- Expiração é um evento de débito, não "dados sumiram"
- Defende contra contestação: "o saldo em 15/03 era +12h, comprovado"

### Negativas

- Tabela de movimentos cresce indefinidamente
- Cálculo de saldo custa O(n) se não materializado. Mitigação: `ponto_banco_horas_saldo` mantém cache consistente
- Atualização de saldo precisa transação (insert movimento + update saldo atômico)

### Similar a

Plaid, Stripe Ledger, qualquer sistema financeiro sério. É padrão em domínios que exigem auditabilidade.
