---
sessao: "08"
titulo: Aprovar ou recusar orçamento pelo link
dono: "[CL]"
base: 93ff8a960b74
---
# 08 · Aprovar orçamento pelo link

**Só com a D-APROVAR respondida.** Caso: UC-COS-28 em `cowork-inbox/repair/ConsultaOs-link.casos.md`. Forma: `os-acompanhamento.jsx` bloco `os-orcamento` (prévia "Orçamento aguardando aprovação" em `repair-portal.jsx`).

- Mantém a ADR ARQ-0002: a página **não** mostra valor; só registra a decisão.
- Grava pela mesma transição de status que o balcão usa (`JobSheetController` / FSM do Repair), com origem "link do cliente" no `activity_log`. Uma decisão por orçamento.
