---
id: requisitos-oficina-auto-briefing
module: OficinaAuto
status: piloto
status_nota: "piloto LIVE em produção (Martinho, mecânica pesada)"
updated_at: "2026-11-05"
distilled_at: "2026-11-05"
distilled_by: "auditoria do módulo 2026-11-05 (lote 5, docs)"
related_adrs:
  - "0171-oficinaauto-ativacao-piloto-martinho-faseada"
  - "0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"
  - "0265-oficina-reparo-erradica-locacao"
  - "0264-governanca-executavel-trio-dominio-e2e"
---

# BRIEFING — OficinaAuto

## Estado atual
Módulo vertical de oficina de reparo de veículos pesados (CNAE 4520-0/01). Piloto LIVE em produção
para o Martinho desde 2026-05-13 (import do Firebird; ativação formal faseada em 2026-05-20, ADR 0171).
O domínio é reparo (`order_type ∈ {manutencao, mecanica}`); locação foi erradicada (ADR 0265; gate
`dominio:check`, ADR 0264 G-4). O resíduo de schema que sobrou está medido em
[`RUNBOOK-erradicacao-locacao.md`](RUNBOOK-erradicacao-locacao.md).

Auditoria completa em 2026-11-05 (6 eixos, base `origin/main` `46a6f44d22`):
[página da auditoria](https://claude.ai/artifact/8ofoqHQrYaEc9Xpak7tSrd). Os consertos correm em
lotes separados; este BRIEFING descreve o código de `origin/main`, não o que os lotes ainda vão fazer.

Contagens vivas não se restateiam aqui: US e casos em
`node scripts/governance/requisitos-status.mjs OficinaAuto` (`_STATUS-GENERATED.md`); âncoras do
SPEC em `node scripts/governance/anchor-lint.mjs`; superfície HTTP em `SUPERFICIE.md`.

## Capacidades
- **OS no quadro Kanban** (`ServiceOrders/Board.tsx`, processo FSM `oficina_mecanica_os`, recepção →
  … → entregue). A transição passa por `ExecuteStageActionService`; o `ServiceOrder` usa o trait
  `GuardsFsmTransitions` (#9840), então UPDATE direto em `current_stage_id` é barrado no Model.
- **Entrega com evidência**: a ação de entregar exige foto do serviço feito (#9660) e, por empresa,
  assinatura do cliente (#9711; liga/desliga em `php artisan oficina:entrega-assinatura`).
- **DVI (vistoria digital)**: itens, decisão do cliente e foto por item; item reprovado vira linha de
  orçamento (`DviInspectionController@toOrcamento`).
- **Fotos e laudo A4** da OS (`ServiceOrderPhotoController`, `ServiceOrderController@printInvoice`);
  itens de OS (peça, mão de obra, serviço) em `oficina_service_order_items`.
- **Aprovação pública** por link com token HMAC + PIN de 4 dígitos com lockout (`/aprovar-os/{token}`,
  `Public/AprovacaoOsController`, `AprovacaoPublica.tsx`, `EnviarLinkAprovacaoWhatsappJob`; US-OFICINA-014).
- **Venda derivada da OS**: o `ServiceOrderObserver` cria a venda e baixa o estoque quando a coluna
  `status` vira `concluida` (ver o gap D1 abaixo). A OS não devolve venda de outra empresa (#10146).
- **Veículos** com placa Mercosul e lookup de placa pluggable (`Services/PlacaLookup/*`).
- **Lembrete de revisão** por km estimado e por data (#9888; `oficinaauto:lembretes-revisao`,
  atrás de `OFICINA_LEMBRETE_REVISAO_ENABLED`, desligado por padrão).
- **Catálogo de peças compatíveis** por veículo, marca e modelo em texto
  (`ordens-servico/{order}/pecas-compativeis`, `PecaCompativelController`, #9979), atrás de
  `OFICINA_CATALOGO_PECAS_ENABLED`, desligado por padrão. Sem busca OEM.
- **API do app mobile**: OS, veículos, placa, garantia e agenda de revisão em
  `routes/api/app/oficina.php` e `routes/api/app/os-arquivos.php`. O contrato é do módulo AppMobile
  (`memory/requisitos/AppMobile/API-CONTRATO-v1.md`).
- **Abrir OS a partir da venda** (`POST /sells/{id}/create-os`, `SellController@createOs`).
- Importer Firebird do Martinho + comandos de limpeza, relatório e sanity-check da migração.

## Gaps
- **D1 — dois donos do estado da OS (decisão [W] aberta).** A venda derivada e a baixa de estoque
  disparam pela coluna `status === 'concluida'` (`ServiceOrderObserver.php:72-75`); o pipeline FSM
  `oficina_mecanica_os` termina em `entregue` e nenhuma ação escreve `status`. O `status` é gravado pelo
  select do `Edit.tsx`. Levar a OS a `entregue` pelo quadro, pelo código, não gera venda nem baixa
  estoque (não medido em produção). Descrição em
  [`RUNBOOK-fsm-pipeline.md`](RUNBOOK-fsm-pipeline.md); o SDD do fluxo espera a decisão.
- **Resíduo de locação no schema**: `vehicles.current_status` (`disponivel|locada|…`),
  `vehicles.vehicle_type` com valores de caçamba e o processo FSM `cacamba_locacao` ainda seedado.
  Aguarda ADR própria ([W]); medição no RUNBOOK de erradicação.
- **Apontamento multi-mecânico**: cada OS tem um único `assigned_user_id` (US-AUTO-006).
- **NF-e/NFS-e da OS** é presentacional; a venda derivada é só `Transaction` (SDD D-2).
- **Documentação**: RUNBOOKs de tela antigos (V0) e CAPTERRA-FICHA com diferencial "dual mode
  locação" contrário à ADR 0265 — em revisão no lote 5 da auditoria.

## Última mudança
Out–nov/2026: trava FSM no `ServiceOrder` (#9840), entrega exige foto (#9660) e assinatura (#9711),
lembrete de revisão (#9888), catálogo de peças atrás de flag (#9979), API do app (#8607…#8784),
correção Tier 0 da venda de outra empresa no Show (#10146) e saída dos testes da quarentena da lane
Pest MySQL (#10118–#10124).

## Proveniência (destilado de)

- auditoria 2026-11-05, eixo 3 (requisitos/docs/fluxo), base `46a6f44d22`
- `git log -- Modules/OficinaAuto resources/js/Pages/OficinaAuto` (2026-08-01 → 2026-11-05)
- código conferido em `origin/main` `7d997591d7`: `ServiceOrder.php:62`, `ServiceOrderObserver.php:72-75`,
  `Config/config.php:66,72`, `Routes/web.php:130-136`
