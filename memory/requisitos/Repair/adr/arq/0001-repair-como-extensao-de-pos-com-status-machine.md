# ADR ARQ-0001 (Repair) · Repair como extensão de POS com state machine

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Reparo é venda de serviço com ciclo longo (dias/semanas). POS trata venda como atômica. Precisa de ciclo de vida rastreável sem modificar POS core.

## Decisão

`repair_jobs` tem FK pra `transactions` (POS) e state machine própria: `received → in_diagnosis → waiting_parts → in_progress → ready → delivered | canceled`. Cada transição gera entry em `repair_job_history` auditável.

## Consequências

**Positivas:**
- Venda fecha quando retirada (não quando recebida).
- Cliente acompanha status via portal (`/repair-status`).
- SLA mensurável por estágio.

**Negativas:**
- State machine estrita pode atrapalhar casos reais (pular etapas).

## Alternativas consideradas

- **Tags livres**: rejeitado — perde auditoria.
- **Vender na recepção**: rejeitado — complica estorno se não der certo o reparo.
