# oimpresso · Repair — Produção oficina drag-drop

## Problema
Gráfica com 5+ máquinas em produção paralela (corte, impressão eco-solvente, laminação, acabamento, CNC) não sabe onde cada OS está. WhatsApp do grupo vira fonte de verdade. Cliente liga: "minha placa tá pronta?" e ninguém sabe.

## Solução
Tela **Repair** estilo **Trello/Kanban** com colunas configuráveis (orçamento → produção → acabamento → embalagem → entrega → faturado). **Drag-and-drop entre colunas** move a OS por etapa. Cada card mostra: cliente, prazo, m², máquina, responsável.

Quem produz mexe. Quem vende vê. Quem cobra fatura no fim.

## Diferenciais únicos
- **Drag-drop entre colunas** já entregue (US-REPAIR-PROD-4) com mapping reverso pro DB (não é só visual — muda status de verdade)
- **Dual-mode legacy** — gráfica que usa nome de coluna velho (ex: "tá com o Zé") continua funcionando enquanto migra
- **Multi-tenant Tier 0** — cada gráfica define suas próprias colunas e prazos, não compartilha com ninguém
- **Integração nativa com NfeBrasil** — coluna "faturado" dispara NFe sem clique extra

## 3 features-killer
1. **Cor por prazo:** vermelho atrasou, amarelo vence hoje, verde no prazo. Bate o olho na tela e sabe o que tá pegando fogo.
2. **Filtro por máquina** — "mostra só o que tá na eco-solvente" pra operador focar no dele
3. **Auditoria de tempo por etapa** — quanto tempo a OS ficou parada em "acabamento"? Dado pra negociar prazo com cliente.

## Pricing tier proposto
- **Starter:** Repair com colunas fixas
- **Pro:** colunas customizáveis + drag-drop + filtros por máquina/operador
- **Enterprise:** auditoria de tempo + SLA configurável + alerta WhatsApp por atraso

`[draft — Wagner valida]`

## CTA
"Quantas OS abertas vocês têm em paralelo numa quinta-feira normal? E onde tá registrado isso hoje — sistema, planilha, ou grupo do WhatsApp? Devolvo plano de migração."

---

**Refs internas:** `Modules/Repair/`, US-REPAIR-PROD-4 (drag-drop), ADR 0011 (alinhamento padrão Jana — Repair é módulo referência canônica).
