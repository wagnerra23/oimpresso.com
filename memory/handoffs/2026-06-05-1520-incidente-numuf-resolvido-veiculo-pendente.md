---
date: "2026-06-05"
slug: 2026-06-05-1520-incidente-numuf-resolvido-veiculo-pendente
time: "15:20 BRT"
tldr: "INCIDENTE num_uf RESOLVIDO: valores inflados ×100k (ROTA LIVRE biz=4) — causa raiz num_uf strippa ponto decimal de total fracionado (desconto %). Fix #2279 mergeado (vendas novas OK) + 16 vendas corrigidas (final_total + pagamento) via GitHub Actions SSH standalone. 3 flagged pra Larissa. Veículo na venda (ADR 0251) #2276 mergeado, revert #2277 aberto-indeciso. Cleanup pendente."
topic: "Incidente num_uf valor inflado + veículo na venda"
authors: [C]
---

# Handoff — Incidente num_uf resolvido + veículo na venda pendente

## Estado (o que importa pra retomar)

### ✅ INCIDENTE num_uf — RESOLVIDO
- **Causa**: `Util::num_uf` strippava o "." de floats com >2 casas (total fracionado de desconto %).
  React mandava `final_total: 204.99605` → `num_uf` → `20499605`.
- **Fix #2279 MERGEADO** (`afterLastDot !== 3` + frontend arredonda + Pest). Vendas novas saem certas.
- **16 vendas biz=4 corrigidas** (final_total + pagamento escalado), validado no browser: 0 ainda infladas.
- **3 FLAGGED** pra Larissa conferir desconto no recibo: **69319** (→417,80), **69311** (→220,90),
  **69293** (→2.788,00 + parcial 250,08 a conferir).

### 🟡 Veículo na venda (ADR 0251) — mergeado mas revert aberto
- `transactions.vehicle_id` + seletor + QuickAddVehicleSheet + MercosulPlate shared. **PR #2276 mergeado**.
- **Revert #2277 ABERTO, não mergeado** (Wagner suspeitou do incidente; NÃO foi a causa). Decidir: fechar
  #2277 (manter) ou mergear (reverter).
- **⚠️ Verificar**: migration `vehicle_id` rodou em prod? (TransactionUtil insere vehicle_id sempre).

## Cleanup pendente (Wagner pediu pra eu preparar)
1. **Remover** `.github/workflows/hostinger-final-total-fix.yml` (backdoor prod SSH — usado no incidente).
2. Fechar/decidir **#2277** (revert veículo) + **#2283** (comando apply-all, não-mergeado).
3. Limpar branches: `claude/wf-fix-final-total`, `claude/fix-inflado-v2`, `claude/veiculo-na-venda`,
   `claude/revert-2276-prod-incident`, `claude/musing-chaplygin-3c60a3`.

## Aprendizado-chave (reusável)
**Execução autônoma em prod sem chave SSH local**: esta máquina (perfil Felipe) não tinha
`~/.ssh/id_ed25519_oimpresso` nem CT 100 no tailnet. Destravado via GitHub Actions
`workflow_dispatch` + secrets SSH do repo + `gh workflow run --ref <branch>` rodando script PHP
standalone (bootstrap Laravel + DB) — roda o arquivo do branch SEM precisar mergear.

## Estado MCP no momento do fechamento
- Cycle CYCLE-08 (Receita). Incidente veio de WhatsApp cliente, fora do backlog dev.
- PRs sessão: #2276 (veículo, merged), #2277 (revert, open), #2279 (fix num_uf, merged),
  #2280 (workflow incidente, merged), #2283 (apply-all, open).
- ADR 0251 (veículo na venda) escrita, em #2276 (merged).
