---
data: 2026-06-04
hora: "16:30 BRT (aprox)"
autor: Claude (sessao felipewr2-cell)
slug: venda-fix-silenciosa-padrao-aviso-test-first
tipo: handoff
modulo: Sells
status: aberto
related_prs: [2208, 2213, 2214, 2220, 2222, 2229, 2230, 2236]
related_branches: [feat/sells-cancel-confirm, feat/sells-feature-test-specs]
related_adrs: [0093, 0101, 0104, 0143]
---

# Handoff — Venda: fim da falha silenciosa + padrao global de aviso + test-first das features pendentes

## O que foi feito (sessao longa, 8 PRs)

| PR | Tema | Estado |
|---|---|---|
| #2208 | data da venda automatica (hoje) — venda sem data sumia | merged + live |
| #2213 | parse transaction_date d/m/Y do Inertia | merged + live |
| #2214 | auto-heal de indice git corrompido no quick-sync | merged |
| #2220 | **tela de venda React voltou** — GrowthBook flag vazio caia no fallback errado (FeatureFlagService array_key_exists) | merged + live |
| #2229 | limpa 6 erros PHPStan do main (debito de PRs Financeiro) — destrava CI do time | merged |
| #2222 | **padrao global de alerta** (flash->toast em app.tsx) + HandleInertiaRequests surface status.msg | merged + live + smoke OK |
| #2230 | **venda bloqueada fica na tela** (back()->withErrors, nao perde carrinho) + **contorna o item** com problema (estoque) | merged (ou aguardando) |
| #2236 | **cancel-confirm** — Cancelar com venda montada pede confirmacao | verde, aguardando merge |

### Causa-raiz do "venda sumia" (resolvida)
Produto `enable_stock=1` qty 0 dispara `PurchaseSellMismatch` (TransactionUtil:3382, `allow_overselling` off) -> store reverte e retornava 200 mas o React nao mostrava nada (msg vinha em `status.msg`, mas HandleInertiaRequests lia `status.error`). Provado em prod via log do CT 100 (workflow diag SSH). Wagner decidiu: manter o bloqueio, so AVISAR. #2222 (avisar global) + #2230 (fica na tela + contorna item).

## Test-first das features pendentes da venda (workflow 13 agentes)

Workflow `sells-pest-per-feature`: 1 agente especializado por feature escreveu 1 teste Pest (define o "pronto"). Os 13 testes estao em `tests/Feature/Sells/Create*Test.php` (commit local 65d90142e na branch `feat/sells-feature-test-specs` — **push bloqueado por OOM da maquina, ver caveat**).

**Achado: 4 "gaps" da lista ja estavam FEITOS** (doc auditoria 2026-05-15 estava stale):
- a11y-buttons (ja usa `<Button>` shadcn) · atalho-foco (`/` ja funciona) · subtitle (ja generico) · commission-agent (existe, so refinar paridade c/ Edit).

**8 gaps REAIS (teste vermelho, a produzir):**
- defer (perf — RISCO: precisa backend `Inertia::defer` + `<Deferred>` no front, senao quebra) — DEIXAR POR ULTIMO
- kpis-skeleton, is-recurring, staff-note (seguros/auto-contidos)
- imei, discount-type R$/%, doc-upload, secondary-addr (paridade Create<-Edit — espelhar codigo que ja existe no Edit.tsx)

## Proxima acao
Produzir feature por feature: teste vermelho -> impl -> verde -> PR (1 por feature, mirror Edit). `cancel-confirm` ja saiu (#2236). Recomendado a seguir: is-recurring + staff-note (baixo risco), depois imei/discount/upload/secondary, defer por ultimo com cuidado.

## Caveats
- **OOM da maquina bloqueando `git push`** (malloc 500MB falha — pack thin contra remote gigante). Commits estao SALVOS no disco. As branches `feat/sells-feature-test-specs` (65d90142e, 12 testes) e o handoff podem nao ter chegado ao remote. Re-push de outra maquina ou apos reboot. Todos os PRs (#2208..#2236) JA estao no remote.
- **MCP indisponivel** nesta sessao (token settings.local.json fallback) — sem snapshot cycles-active/my-work.
- `feat/sells-feature-test-specs` NAO mergear sozinha (testes vermelhos falham CI de proposito) — cada feature emparelha teste+impl.

## Estado MCP no momento do fechamento
MCP indisponivel (fallback) — snapshot nao coletado. Fonte de verdade: PRs no GitHub + branches locais acima.
