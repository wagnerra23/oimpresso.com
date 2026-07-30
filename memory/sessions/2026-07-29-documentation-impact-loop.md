---
date: "2026-07-29"
topic: "Ciclo documental por impacto — plano no dono e primeira fatia executada"
authors: [W, C]
---

# Ciclo documental por impacto

O plano revisado foi registrado no charter `scripts/governance/ZELADOR.md`, sem criar roadmap,
hook, ledger ou workflow paralelo. A primeira fatia estendeu `documentation-loop.mjs` com mapa
`base→head`, módulos diretos + vizinhos de 1 salto, inventário dos documentos donos e recibo que
recusa alvo apagado, conteúdo vazio ou BRIEFING alterado só no carimbo.

Provas: selftest 8/8; piloto real Financeiro encontrou 5 vizinhos e BRIEFING/README/ARCHITECTURE/
SDD/RUNBOOK/SPEC; `module-surface --all --check` validou 39 módulos + `_Geral`;
`deadlink-gate --check` preservou 1098/1098 grandfathered sem piora.

O CI permanece read-only/advisory. A correção é executada pelo agente ou acionada pelo ZELADOR;
merge e eventual promoção continuam decisão [W].
