---
id: requisitos-crm-briefing
module: Crm
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Crm (verdade destilada)

## Estado atual
`Modules/Crm` é módulo SPLIT: parte A = cadastro de Cliente (canon em `memory/requisitos/Cliente/`, KEEP intocável — hospeda os endpoints do drawer, o autosave, o `BrLookupService` e a auditoria LGPD); parte B = pipeline pré-venda herdado do UltimatePOS (leads, campanhas, propostas e follow-ups; o portal `/contact/*` é zona cinza, fora do escopo do plano), em depreciação sem sucessor (ADR 0301, `DEPRECATION-PLAN-pipeline.md`, com decisão por tabela). Silenciado desde 2026-06-08: sem investimento novo; entram só correções. Este arquivo é a casa única do BRIEFING (a lápide-ponteiro `Modules/Crm/BRIEFING.md` foi deletada por [W] em 2026-07-30). O carimbo de 2026-07-30 foi manual e o schedule do distiller segue desligado (ADR 0291, kill-switch no Kernel); o changelog legado foi desambiguado para CRM em 2026-07-22.

## Capacidades
- Cadastro com dados pessoais e comerciais (abas Identificação, Contato, Endereço, Comercial e Classificação do drawer).
- Drawer com autosave (`ClienteAutosaveController`).
- Score de risco do cliente — endpoint determinístico (zero LLM, sinais fixos), no drawer desde 2026-05.
- Exportação CSV e auditoria LGPD (`ClienteAuditoriaController`; o CSV nunca exporta `tax_number` em claro).
- API externa `/connector/api/crm/*` (módulo Connector) e portal do contato `/contact/*` (zona cinza; uso real medido — ver `DEPRECATION-PLAN-pipeline.md` §Recibo do portal, 2026-09-04).

## Gaps
- BLOQUEIO 1 do plano de depreciação — row count por business pagante nas tabelas `crm_*`: rodado no CT 100 em 2026-09-04, mas nenhum banco de lá é réplica de prod; o zero não autoriza DROP.
- BLOQUEIO 2 — consumidor externo `Connector/api/crm`: oráculo rodado em 2026-09-04, resultado no plano (§Recibo, #6804); fechar é ato [W]+[F].
- Zona cinza — portal `/contact/*`: uso real medido em 2026-09-04 com controle positivo (`DEPRECATION-PLAN-pipeline.md` §Recibo do portal); decisão [W].

## Última mudança
2026-09-04/05 — os bloqueios do plano de depreciação passam a ter medição: BLOQUEIO 3 fechado (#6774, 2026-09-04), uso do portal medido em zero com controle positivo (#6804) e bloqueios 1 e 2 medidos no CT 100 (#6791), ambos 2026-09-05. Antes: gate de módulo real no endpoint de veículos (#5725/#5729, 2026-08-13) e o fix "salvar a tela apagava o CRM em silêncio" (#6302, 2026-08-26).

## Proveniência (destilado de)

- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- session `sessions/2026-08-12-fronteiras-ciclo-fechado-e-a-premissa-derrubada.md` (2026-08-12) — 2026-08-12-fronteiras-ciclo-fechado-e-a-premissa-derrubada.md
- handoff `handoffs/2026-08-12-1617-arquitetura-react-modulos-e-as-3-claims-derrubadas.md` (2026-08-12) — 2026-08-12-1617-arquitetura-react-modulos-e-as-3-claims-derrubadas.md
- handoff `handoffs/2026-08-12-2115-fronteiras-3-eixos-e-a-mesa-refutada.md` (2026-08-12) — 2026-08-12-2115-fronteiras-3-eixos-e-a-mesa-refutada.md
