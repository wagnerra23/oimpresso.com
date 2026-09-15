---
id: requisitos-fiscal-briefing
module: Fiscal
status: parcial
updated_at: "2026-09-15"
distilled_at: "2026-09-15"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Fiscal (verdade destilada)

## Estado atual
O módulo Fiscal atua como um cockpit unificado, consolidando nota fiscal eletrônica (NF-e) e nota fiscal de serviço eletrônica (NFS-e) em um único local. Atualmente em status parcial, o módulo está em piloto com a funcionalidade de NF-e já disponível ao público, enquanto o gerador do SPED aguarda finalizações de integração e configuração. Recentemente, foram implementadas ações de risco para configuração fiscal.

## Capacidades
- Painel de controle com KPIs e alertas (inclui certificados vencidos).
- Listagem de NF-e/NFC-e com suporte ao drawer SEFAZ; NFS-e com filtros e busca, sem ações disponíveis.
- Manifesto DF-e e prazos de confirmação gerados automaticamente.
- Eventos registrados em timeline, com opção de exportação.
- Controle de acesso e permissões granular para gerenciamento de certificado e ambiente.
- Geração de SPED EFD-ICMS/IPI com prévias limitadas.
- Ações de cancelamento e correção de notas monitoradas.

## Gaps
- O gerador do SPED apresenta hardcodes que impedem a exportação completa (GAP-FISCAL-003); depende de correções em configurações e permissões.
- O rollout para ROTA LIVRE (biz=4) está em pré-canary, aguardando testes manuais antes da promoção.

## Última mudança
Em 2026-09-09, as 7 abas do cockpit voltaram aos rótulos e à ordem do protótipo; antes disso, em 2026-09-08, os 8 inventários de paridade do Fiscal + NfeBrasil foram vinculados e as 2 telas silenciosas fechadas, e em 2026-09-06 a lane MySQL passou a EXECUTAR os 3 testes da tela Dfe. Em 2026-09-04 tinham saído as melhorias da onda 10 do SPED — prévia do arquivo de referência, correções das ações de manifestação e ajuste das permissões de operação administrativa.

## Proveniência (destilado de)

- audit `requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md` — AUDIT-SENIOR-2026-05-25.md
- audit `requisitos/Fiscal/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Fiscal/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-14-refutacao-gt-g5-lote-7262-r2.md` (2026-09-14) — 2026-09-14-refutacao-gt-g5-lote-7262-r2.md
- session `sessions/2026-09-13-fiscal-e2e-rede-thread01.md` (2026-09-13) — 2026-09-13-fiscal-e2e-rede-thread01.md
- session `sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md` (2026-09-04) — 2026-09-04-fiscal-onda10-sped-goals-cowork.md
- handoff `handoffs/2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md` (2026-09-04) — 2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md
- session `sessions/2026-09-03-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-03-2040-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-2040-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md` (2026-09-02) — 2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md
- session `sessions/2026-09-01-fiscal-f0-screen-coverage.md` (2026-09-01) — 2026-09-01-fiscal-f0-screen-coverage.md
- handoff `handoffs/2026-08-28-1040-design-sync-recibos-fiscal.md` (2026-08-28) — 2026-08-28-1040-design-sync-recibos-fiscal.md
