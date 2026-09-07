---
id: requisitos-oficina-auto-briefing
module: OficinaAuto
status: piloto
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
related_adrs:
  - "0171-oficinaauto-ativacao-piloto-martinho-faseada"
  - "0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"
  - "0265-oficina-reparo-erradica-locacao"
  - "0264-governanca-executavel-trio-dominio-e2e"
---

# BRIEFING — OficinaAuto (verdade destilada)

## Estado atual
Módulo vertical de oficina de reparo de veículos pesados (CNAE 4520-0/01) — piloto LIVE em produção para Martinho (biz=164) desde 2026-05-13 (import; ativação formal faseada em 2026-05-20, ADR 0171), com veículos, OS e histórico legado importados do Firebird — estado do piloto medido em 2026-05-13 (via SSH) e não remedido desde então; data e fonte em `demo-martinho-2026-05-13/discovery-martinho.md` (veículos e OS) e no charter v3 do módulo (vendas e títulos); sem sinal de novo import pós-julho. Erratas que não voltam: a meta "2026-Q3 pra virar piloto" do CAPTERRA-FICHA está stale (o piloto já é LIVE) e gaps que destilados anteriores listavam como abertos já foram entregues (US-OFICINA-014 `done`; checklist visual com fotos). O domínio é reparo (`order_type ∈ {manutencao, mecanica}`); locação foi erradicada (ADR 0265; gate `dominio:check`, ADR 0264 G-4). Duas notas com donos e escalas diferentes — module-grade v3 (rubrica ADR 0155; `governance/module-grades-baseline.json`, recomputar com `php artisan module:grade OficinaAuto`) e Capterra scoped (`CAPTERRA-FICHA.md`, meta declarada lá); não se restateiam aqui e não se somam. Contrato de tela: SDD + `casos.md` (PR #4869); contagem viva em `node scripts/governance/requisitos-status.mjs OficinaAuto` (`_STATUS-GENERATED.md`).

## Capacidades
- OS via quadro Kanban FSM (`ServiceOrders/Board.tsx`; transição por arrasto executa `ExecuteStageActionService`, nunca UPDATE direto; processo `oficina_mecanica_os`, pipeline recepção → … → entregue).
- DVI (vistoria digital): itens de inspeção, decisão do cliente e foto por item; foto por item via `DviInspectionController@uploadPhoto`; item reprovado vira linha de orçamento (`DviInspectionController@toOrcamento`, sufixo de rota `dvi/{item}/to-orcamento`).
- Fotos e laudo A4 no nível da OS (CRUD por `ServiceOrderPhotoController`, impressão por `ServiceOrderController@printInvoice`); itens de OS (peça, mão-de-obra, serviço) em `oficina_service_order_items`.
- Aprovação pública via WhatsApp com token HMAC + PIN de 4 dígitos com lockout (rota real `/aprovar-os/{token}`, `Public/AprovacaoOsController`, `AprovacaoPublica.tsx`, `EnviarLinkAprovacaoWhatsappJob`; US-OFICINA-014, entregue).
- CRUD de veículo com lookup de placa pluggable (`Services/PlacaLookup/*`, stub ou HTTP via `.env`).
- Importer Firebird do Martinho + comandos de limpeza, relatório e sanity-check da migração.

## Gaps
- `ServiceOrder` sem o trait `GuardsFsmTransitions`: o gateway FSM é usado, mas atualização direta em `current_stage_id` não é barrada no Model (Tier 0; US-OFICINA-006 `_parcial_`, SPEC:377).
- Catálogo de peças OEM: rota `/oficina-auto/pecas` não implementada (US-AUTO-008, SPEC:766).
- Apontamento multi-mecânico: cada OS tem um único `assigned_user_id` (US-AUTO-006, SPEC:721).
- Dívida F3 no domínio (US-OFICINA-046, SPEC:1496): chaves FSM e status de veículo preservados aguardando ADR própria; accessors residuais inertes por decisão (`getIsOverdueAttribute` → `false`, `getValorReceberAttribute` → `0.0` — o KPI vivo do Board deriva de `total_items`, não deles); o P5 do `RUNBOOK-erradicacao-locacao.md` foi aplicado em prod pelo PR #2468 (2026-06-09, `'Caçambas'→'Veículos'` no topnav) — o RUNBOOK é que está stale; segue aberto o repontuar dos accessors. O texto "UI Locações ativas a remover" está podre (zero ocorrências em código de produção); o charter que a US cita existe, mas sob `Pages/Repair/ProducaoOficina/Index.charter.md` — o kanban mudou de dono, não sumiu.

## Última mudança
SDD + contratos de tela (PR #4869, 2026-07-27) — documentação/teste, não capacidade; nenhuma capacidade mudou desde 2026-07-09. Depois disso, só higiene cross-cutting (moves de docs, refactors, entre eles: as telas silenciosas passaram a declarar a fonte de design, #6458, 2026-08-31; o `SCOPE.md` ganhou `url_prefixes`, #6552, 2026-09-02); nenhuma capacidade ou gap mudou.

## Proveniência (destilado de)

- audit `requisitos/OficinaAuto/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- handoff `handoffs/2026-08-12-1231-o-adversario-derrubou-a-nota-e-a-grade-ganhou-regua.md` (2026-08-12) — 2026-08-12-1231-o-adversario-derrubou-a-nota-e-a-grade-ganhou-regua.md
- session `sessions/2026-08-11-memory-arrumada-e-a-nota-71-da-dimensao-memoria.md` (2026-08-11) — 2026-08-11-memory-arrumada-e-a-nota-71-da-dimensao-memoria.md
- handoff `handoffs/2026-08-11-1745-a-bagunca-que-era-decisao-e-a-nota-da-memoria.md` (2026-08-11) — 2026-08-11-1745-a-bagunca-que-era-decisao-e-a-nota-da-memoria.md
- session `sessions/2026-07-27-sdd-oficinaauto-os.md` (2026-07-27) — 2026-07-27-sdd-oficinaauto-os.md
