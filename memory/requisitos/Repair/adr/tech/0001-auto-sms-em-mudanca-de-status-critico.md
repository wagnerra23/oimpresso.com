# ADR TECH-0001 (Repair) · Auto-SMS em mudança de status crítico

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Cliente esquece do reparo. Quando fica pronto, técnico precisa ligar. Dispara fila de ligações que atrapalha operação.

## Decisão

Event `RepairStatusChanged`. Listener `NotifyCustomer` dispara SMS quando status muda pra `ready` ou `waiting_parts`. Template configurável por business.

## Consequências

**Positivas:**
- Auto-notifica — retirada mais rápida.
- Reduz ligações outbound.

**Negativas:**
- Custo de SMS. Mitigação: configurável desligar por business.

## Alternativas consideradas

- **WhatsApp via API oficial**: melhor UX, custo alto. Planejar pra quando volume justificar.
- **Email**: baixa open rate pro segmento de cliente de reparo.
