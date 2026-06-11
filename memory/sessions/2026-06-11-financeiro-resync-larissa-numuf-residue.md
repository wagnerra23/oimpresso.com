---
date: "2026-06-11"
topic: "Financeiro ROTA LIVRE mostrava R$ 50,8M a receber (impossível) — resíduo do incidente num_uf corrigido em prod + comando canônico financeiro:resync-from-core"
authors: [W, C]
related_adrs: ["0093-multi-tenant-isolation-tier-0"]
prs: [2576, 2579]
---

# Sessão 2026-06-11 — Financeiro mentindo R$ 50,8M (resíduo num_uf)

## TL;DR

Wagner mostrou o Financeiro → Visão Unificada da ROTA LIVRE (biz=4, Larissa) e disse "tem diferença do real". O screenshot era do **protótipo Cowork** (dado mock), mas ao auditar a prod o número real estava **muito errado mesmo**: **A RECEBER R$ 50,8M** numa gráfica pequena. Causa-raiz: **resíduo do incidente `num_uf`** (#2279 corrigiu o parser pt-BR que inflava `transactions.final_total`; #2280 corrigiu o core via `UPDATE` direto — que **não dispara o Observer**), deixando o espelho `fin_titulos` congelado no valor-lixo. **17 títulos** concentravam R$ 367,9M (98,6% da base); os outros 18.117 batiam centavo-a-centavo com o core. **Fix aplicado em prod**: A RECEBER **R$ 50,8M → R$ 424.608,35 real**. Append-only, reversível, Tier 0 verificado.

## Cronologia (e os erros do caminho)

1. Cheguei achando "a tela mostra dado de DEMO em biz=4" → **refutado** pelo auditor (prod biz=4 tinha 18.141 títulos reais, zero SEEDER_DEMO). O screenshot era protótipo.
2. Wagner: "o financeiro está muito errado mesmo, não dá pra confiar". Tinha razão — o auditor reportou R$ 50,8M a receber e eu li isso e chamei de "saudável". Erro meu: não gritei no número impossível.
3. Auditoria focada em corrupção → **17 vendas com valor-lixo** (ex venda R$ 220,90 gravada R$ 209.004.535). Não é escala ×100 limpa, não é duplicação, não é Delphi.
4. Achei a causa no código: `TituloAutoService::sincronizarDeTransacao` só **copia** `final_total` (`valor_total = (float) $tx->final_total`). O lixo entra com `final_total` já podre do core.
5. Git confirmou: incidente **num_uf** conhecido — #2279 (fix `app/Utils/Util.php@num_uf`), #2280 (workflow corrigiu 16 vendas ROTA LIVRE na prod), #2285 (REGRA MESTRE), #2286 (Pest guard). O `final_total` do core JÁ está corrigido (< R$ 10k). Mas a correção foi `UPDATE` direto → Observer não disparou → `fin_titulos` ficou stale.

## O que foi feito

- **PR #2576 (mergeado)** — comando `financeiro:resync-from-core` (Modules/Financeiro): re-espelha o core já corrigido nos títulos divergentes (estorna baixas-lixo append-only, `valor_total = final_total`, recalc via `TituloAutoService::recalcularTitulo`, trilha `activity_log`). **DRY-RUN default**, `--business` Tier 0, idempotente, reversível (`metadata.valor_total_antigo`). Pest com 5 invariantes.
- **PR #2579 (draft, parado)** — `run-financeiro-resync.yml`: workflow_dispatch pra rodar o comando real na prod via SSH (modelado em `run-financeiro-demo-seeder.yml`). **Bloqueado** pelo Governance Gate que o #2578 apertou (dívida pré-existente de 56 ADRs com status fora do enum — NÃO relacionada). Volta quando o gate destravar.
- **Correção aplicada em prod via SQL revisado** (canal workflow bloqueado → fallback RUNBOOK com aprovação parent+Wagner): 17 títulos corrigidos, 11 baixas-lixo estornadas (R$ −317,3M), 17 trilhas `activity_log`. Validado dry-run-com-rollback antes do COMMIT + re-verificação em conexão nova. Tier 0 leak check 0/0/0. **A RECEBER R$ 50.823.223 → R$ 424.608,35.**

## Follow-ups (não perder)

1. **[SEGURANÇA — Wagner] Rotacionar/sincronizar `.env` do CT 100.** Os `.env` em disco (`oimpresso-mcp`, `whatsapp-baileys`) têm senha de banco **stale/rotacionada** (Access denied); a viva só está no container em execução. Redeploy/restart quebra a conexão. Sincronizar disco + Vaultwarden (`hostinger-mysql-oimpresso`).
2. **[Dado — Larissa] Saldo de abertura.** Card "Saldo Previsto" mostra R$ 0 porque `fin_contas_bancarias.saldo_cached` é NULL nas 2 contas. Core não guarda saldo, só fluxo. Precisa input dela (ou sync de banco Inter/Asaas).
3. **[Marginal] Drift residual ~R$ 25k.** A receber real R$ 424,6k vs core-devido ~R$ 396k — resto da carteira tem drift abaixo do corte 1,5× (órfãos + incoerências). Audit complementar quando der.
4. **[Governança — time] 56 ADRs com status/lifecycle fora do enum** (`adr.schema.json`) bloqueando o Governance Gate desde o #2578. Append-only impede editar in-place → normalizar no leitor (`decisions-search`) ou override. Bloqueia TODOS os PRs até resolver.

## Refs

- `Modules/Financeiro/Console/Commands/ResyncFromCoreCommand.php` (#2576)
- `Modules/Financeiro/Services/TituloAutoService.php::recalcularTitulo` (~L438-467)
- `app/Console/Commands/SellsFinalTotalAuditCommand.php` (corrige o core; rodar ANTES do resync)
- `app/Utils/Util.php::num_uf` (fix #2279)
- `memory/requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md`
- `.claude/agents/financeiro-bridge-auditor.md` · ADR 0093 (Tier 0)
