---
id: requisitos-financeiro-briefing
module: Financeiro
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Financeiro (verdade destilada)

## Estado atual
Visão unificada de Contas a Receber, Contas a Pagar, Fluxo de Caixa, Cobrança (a tela Boletos foi aposentada por [W]; `GET /financeiro/boletos` responde 301 para `/financeiro/cobranca` e só o `POST /boletos/{remessaId}/cancelar` segue vivo), Conciliação OFX e workflow de aprovação, em produção. As telas vivem em `resources/js/Pages/Financeiro/` — a onda #5686 move `.tsx` pro módulo dono (quais já migraram é derivado: `git ls-files 'Modules/**/Resources/js/Pages/**/*.tsx'`) e o Financeiro ainda não migrou, por isso confirme o path antes de editar tela (não há `.tsx` dentro de `Modules/Financeiro/`). Cobertura funcional: `AUDIT-FUNCOES-2026-05-19.md` (snapshot datado, não porta viva — não copie o número). Paridade visual: gate `visual-regression` (baseline `financeiro-unificado`; o título `VISREG-FIN-001` tem 4 escritores com a mesma chave — seeder, closure de `routes/web.php`, `UnificadoController::ensureVisregFlowTitulo` e `FinanceiroFlowBaselineTest` — ver `memory/licoes-rejeitadas.md`). Contrato de tela: `SDD-tela-financeiro-v1.0.md` (ADR 0351, #4867) + contagem viva em `node scripts/governance/requisitos-status.mjs Financeiro` (`_STATUS-GENERATED.md`).

## Capacidades
- Boleto real via Banco Inter (ADR 0170 `paymentgateway-extracao-camada-cobranca`, arquivada — a extração já é fato no código; Inter/C6/Asaas/BcbPix já plugados no PaymentGateway; habilitação por business é decisão [W] — estado de flag em prod não é fato de repo, ver `memory/what-oimpresso.md` §Padrão arquitetural, linha `Modules/PaymentGateway`).
- Conciliação de extrato com match sugerido por score (`ConciliacaoController`, `POST /financeiro/conciliacao/{lineId}/match`). O evento `TituloCriado` tem listener de audit log (`OnTituloCriadoLog`) — log, não conciliação.
- Workflow de aprovação com visão AR/AP integrada (`aprovacao_status` em `fin_titulos`).
- Ações em lote na Visão Unificada (`POST /financeiro/unificado/bulk`) com confirmação e audit trail (até 500 títulos por chamada — limite de contrato do endpoint, US-FIN-031/#3905).
- Bridge de despesas → títulos (`financeiro:bridge-expense-to-titulos`), corrigida após quebrar em produção (US-FIN-068: filtrava `transactions.deleted_at`, coluna inexistente; o comando não é agendado, então a falha era silenciosa).

## Gaps
- Sicoob aguarda credenciais sandbox do cliente.
- Mobile/PWA e notificações de vencimento não implementados.
- Importação CSV pendente. O parser de retorno CNAB vive no `PaymentGateway` (`CnabRetornoProcessor` + tela `Settings/PaymentGateways/CnabRetorno`) desde a extração da ADR 0170 — o gap do Financeiro é só o CSV.
- Testes em quarentena (quarentenado não roda na lane e portanto não produz veredito): lista e razões em `.github/financeiro-pest-quarantine.list` (lane `financeiro-pest.yml`, cujo context `PHP / Pest (Financeiro · MySQL)` consta de `governance/required-checks-baseline.json`); a triagem de 2026-08-02/03 (buckets A–E da própria lista) separou teste podre, RefreshDatabase, DB-dependente falhando, skip total e flake por ordem — re-rode a lista, não copie a triagem.
- US-FIN-055 aberta (`total_remaining_amount`). Os demais defeitos vivos saem do bucket C de `.github/financeiro-pest-quarantine.list` — re-rode, não copie; em 2026-08-05 a triagem registrou uma suspeita não fechada em `aprovacao_status` (US-FIN-027/028), e nenhum dono a rastreia hoje.

## Última mudança
Desde 2026-08-18 o módulo recebeu mudança de segurança Tier 0 (#6335 — superadmin deixa de ver Financeiro/NFSe de todas as empresas, 2026-08-26), fix do `financeiro:install` que gravava fora do gate (#6305), saída de teste da quarentena (#6018) e E2E Browser novos — Conciliação (#6476), Caixa (#6631) e Cobrança (#6632), com fix de `SetSessionData` nas rotas de conciliação (#6629); Plano de Contas (#6457) e Impostos (#6466) ganharam contrato Pest Feature, não Browser. Antes disso, mudanças estruturais sem impacto em capacidade (#5686 telas no módulo dono, #5568 SCOPE fora de `Modules/` por ADR 0375, #5547 fusão dos CHANGELOG).

## Proveniência (destilado de)

- audit `requisitos/Financeiro/AUDIT-FUNCOES-2026-05-19.md` — AUDIT-FUNCOES-2026-05-19.md
- audit `requisitos/Financeiro/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-05-arte-folha-encargos-br.md` (2026-09-05) — 2026-09-05-arte-folha-encargos-br.md
- session `sessions/2026-08-20-visreg-narrativa-do-comentario.md` (2026-08-20) — 2026-08-20-visreg-narrativa-do-comentario.md
- session `sessions/2026-08-17-financeiro-prototipo-medido-e-o-boletos-aposentado.md` (2026-08-17) — 2026-08-17-financeiro-prototipo-medido-e-o-boletos-aposentado.md
- session `sessions/2026-08-17-visreg-relogios-divergentes-e-pedidos-cowork.md` (2026-08-17) — 2026-08-17-visreg-relogios-divergentes-e-pedidos-cowork.md
- handoff `handoffs/2026-08-17-1615-financeiro-prototipo-ja-aplicado-boletos-aposentado.md` (2026-08-17) — 2026-08-17-1615-financeiro-prototipo-ja-aplicado-boletos-aposentado.md
- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- session `sessions/2026-08-13-espelho-cowork-medir-vs-consertar.md` (2026-08-13) — 2026-08-13-espelho-cowork-medir-vs-consertar.md
- handoff `handoffs/2026-08-12-1617-arquitetura-react-modulos-e-as-3-claims-derrubadas.md` (2026-08-12) — 2026-08-12-1617-arquitetura-react-modulos-e-as-3-claims-derrubadas.md
- handoff `handoffs/2026-08-12-1755-glob-inertia-e-as-duas-camadas-de-mudez.md` (2026-08-12) — 2026-08-12-1755-glob-inertia-e-as-duas-camadas-de-mudez.md
- session `sessions/2026-08-11-contrato-fantasma-e-a-fronteira-de-modulo-morto.md` (2026-08-11) — 2026-08-11-contrato-fantasma-e-a-fronteira-de-modulo-morto.md
- session `sessions/2026-08-08-primary-os-btn-13-telas-e-o-override-fantasma.md` (2026-08-08) — 2026-08-08-primary-os-btn-13-telas-e-o-override-fantasma.md
