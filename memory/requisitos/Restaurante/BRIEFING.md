---
id: requisitos-restaurante-briefing
module: Restaurante
status: em-construcao
status_nota: "telas Blade do restaurante migrando para React atrás de flags OFF (playbook restaurante, threads 01-05)"
updated_at: "2026-10-13"
owner: W
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# BRIEFING — `Restaurante`

> **Função única:** resumo executivo e índice. O BRIEFING aponta para os donos; não recopia RUNBOOK, charter, casos ou contratos de tela.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

As telas de operação de restaurante do núcleo (UltimatePOS): mesas, modificadores, reservas, cozinha e pedidos do
atendente. Não é módulo `Modules/<X>`: os controllers vivem em `app/Http/Controllers/Restaurant/` e a regra de pedido
aberto em `app/Utils/RestaurantUtil.php`.

## Estado atual

Cada tela tem a Blade como padrão e uma versão React atrás de uma flag `useV2Restaurante<Tela>` desligada; `?classico=1`
mantém a Blade alcançável com a flag ligada. Ligar a flag por negócio (F4) e o cutover (F5) são decisão [W]. O estado de
cada tela — qual PR, qual teste, o que diverge do protótipo — mora no RUNBOOK dela.

## Portas canônicas

- **Herança geral:** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Runbooks por tela:** [`RUNBOOK-mesas.md`](RUNBOOK-mesas.md) · [`RUNBOOK-reservas.md`](RUNBOOK-reservas.md) · [`RUNBOOK-cozinha.md`](RUNBOOK-cozinha.md) · [`RUNBOOK-pedidos.md`](RUNBOOK-pedidos.md)
- **Telas:** `resources/js/Pages/Restaurante/` + charters/casos ao lado das Pages
- **Playbook de migração:** `prototipo-ui/cowork/Wagner/cowork-inbox/restaurante/playbook/00-INDICE.md`

## Decisões e riscos que exigem atenção

- `markLineOrderAsServed` não filtra o negócio da sessão (Tier 0) — ver [`RUNBOOK-pedidos.md`](RUNBOOK-pedidos.md) §10.
- Divergências de comportamento entre protótipo e Blade, pendentes de [W], ficam no §10 de cada RUNBOOK.

## Próxima ação verificável

- Smoke F4 de cada tela em biz=1 com a flag ligada (`flag:set useV2Restaurante<Tela> --biz=1`) — dono [W]; evidência: screenshot no PR da tela.

## Regra de manutenção

1. Mudou comportamento de uma tela: atualize o RUNBOOK, o charter e os casos dela; aqui só o ponteiro.
2. Tela nova do restaurante: acrescente o RUNBOOK na lista de portas.
