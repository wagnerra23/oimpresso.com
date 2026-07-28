---
date: "2026-07-27"
time: "17:54 BRT"
slug: sells-show-trio-tier0
tldr: "Sells/Show sai do topo do débito Tier-0: trio fechado (casos.md + 7 UC + contrato Pest) e lane sells-pest.yml nasce porque NENHUM dos 72 testes de Sells rodava em CI. PR #4877 mergeado, CI 97/97."
prs: [4877]
decided_by: [W]
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0093-multi-tenant-isolation-tier-0, 0143-fsm-pipeline-live-prod-marco-2026-05-12]
next_steps:
  - "Avisar a sessão paralela do PR #4868 — herda conflito em sells-pest.yml + gates-registry.json"
  - "Decidir o vencedor entre charter (vendas-page.jsx) e RUNBOOK (vendas-cockpit) — Wave1ShowInertiaTest está vermelho por isso"
  - "Próximo alvo do débito: NfeBrasil/Manifestacao/Index.tsx (score 9)"
---

# Handoff — `Sells/Show` fecha a cobertura de comportamento (alvo #1 Tier-0)

## Estado MCP no momento do fechamento

```
cycles-active   → Nenhum cycle ATIVO em COPI
my-work         → 8 tasks em REVIEW (US-TR-309/310/305/306/311, US-PG-008,
                  US-PROD-027, US-PROD-025) — nenhuma tocada nesta sessão
handoffs irmãos → 2026-07-27-1445-orfaos-ligados-elo-hitl
                  2026-07-27-1135-produto-3-achados-tier0-fechados
                  2026-07-27-0905-sdd-produto-fechado-cadeia-requisitos
```

## O que aconteceu

`Sells/Show.tsx` era o **topo** do `exposicao-tier0.mjs` (score 11, `dinheiro,estoque,fiscal`)
sem `.casos.md`. Os 3 testes que pareciam cobri-la são **estruturais** (`file_get_contents`
+ match de string) — provam que o código está escrito, não que a resposta cumpre o charter.

Entrou o trio: 7 UC (`UC-VSHOW-01..07`) derivados do **contrato** (charter §Goals/§Non-Goals,
RUNBOOK-show, §CU-07, ADR 0093/0143/0101 — o Controller só confirmou) + `SellsShowContratoTest`
com pré-condição anti-vácuo em cada caso que afirma ausência.

**O achado que ampliou o escopo:** varredura contada mostrou **0 dos 214** alvos sqlite e
**0 das 9** lanes MySQL cobrindo `tests/Feature/Sells/` — os 72 arquivos de teste de Sells
**não rodavam em CI nenhum**. Daí `sells-pest.yml`.

## Artefatos gerados

| Arquivo | O que é |
|---|---|
| `resources/js/Pages/Sells/Show.casos.md` | 7 UC + backlog sem id + recibo do run |
| `tests/Feature/Sells/SellsShowContratoTest.php` | 7 casos HTTP citando o UC no nome (G-2) |
| `.github/workflows/sells-pest.yml` | lane MySQL (biz=1 × biz=2), advisory + skip-as-pass |
| `resources/js/Pages/Sells/Show.charter.md` | liga o trio, marca os estruturais, `related_us: [US-SELL-014]` |
| `scripts/governance/gates-registry.json` | censo (`terminal`/`promote_by`/`anchor` com custo medido) |
| `.github/workflows/casos-results-publish.yml` | colhe `pest-sells-junit` (senão os UC ficam 🧪 pra sempre) |
| `memory/requisitos/Sells/SUPERFICIE.md` | regenerado por comando (Casos 2 → 3) |

## Persistência

- **git:** PR [#4877](https://github.com/wagnerra23/oimpresso.com/pull/4877) mergeado (squash `0570e3c8d4`) — CI **97 success · 3 skipped · 0 failure**
- **MCP:** nada a mutar — nenhuma US tocada; `US-SELL-014` só foi **citada** como âncora do charter
- **BRIEFING:** não alterado — o PR não muda capacidade do módulo, só cobertura

## Resultado medido no `main`

```
piso Tier-0 coberto   29 → 32
débito quente         89 → 86
Sells/Show.tsx        FORA do debt_ranked · DENTRO de covered_hot_screens
topo do débito        NfeBrasil/Manifestacao/Index.tsx (9)
lane sells-pest       7 passed, 37 assertions — MySQL real, zero skip
```

Delta próprio do PR: **−1** (o resto veio de #4878/#4879 vizinhos).

## Caveats honestos

- **Os 7 UC estão `🧪`, não `✅`.** O `casos-results-publish` colhe do último run verde de
  `main`; o manifesto G-7 os promove no próximo ciclo. Não marquei à mão.
- **`Wave1ShowInertiaTest` segue vermelho** — assere `vendas-cockpit` num charter que diz
  `vendas-page.jsx`. Não consertei: escolher entre charter e RUNBOOK exige fonte que não tenho.
- **PR #4868 (sessão paralela) não mergeou** e herda o conflito. `Dedup-ack` no #4877 registra
  #4868 como canônico da lane; reconciliação = adotar a lane de `main` e só somar
  `SellsIndexTenantContratoTest` à allowlist (as allowlists são aditivas).
- **CT 100 não roda estes contratos** — `oimpresso_staging` tem 15 tabelas (sem schema
  UltimatePOS); os 7 casos dão `markTestSkipped` lá. Gate real = lane MySQL do CI.

## Próximos passos pra retomar

```bash
node scripts/qa/exposicao-tier0.mjs
```

Topo atual: `NfeBrasil/Manifestacao/Index.tsx` (9) · `Fiscal/Nfe` · `Fiscal/Sped` — todos
`dinheiro/pii/fiscal`. Mesmo padrão: trio + contrato + entrar na allowlist de uma lane MySQL.

## Lições catalogadas

- **Verde de monitor pode ser erro de API.** `gh pr checks` estourou o rate-limit de GraphQL,
  imprimiu a mensagem de erro com **exit 0**, e o `awk` leu "sem pending" — 2 falsos
  "CI completo". Família do `crontab -l ||` ([proibicoes §5 2026-07-17](../proibicoes.md)).
  Corrigido pra REST + separar `failure` de `cancelled` (o filtro velho escondeu a 3ª falha).
- **Vermelho de gate ≠ autoria.** 2 dos 3 vermelhos vinham de branch 8 commits atrás;
  a correção foi `git merge origin/main`, não editar o baseline — que seria o afrouxamento
  que o guard existe pra impedir.

## Pointers detalhados

- Session log: [2026-07-27-sells-show-trio-tier0.md](../sessions/2026-07-27-sells-show-trio-tier0.md)
- [Show.casos.md](../../resources/js/Pages/Sells/Show.casos.md) · [sells-pest.yml](../../.github/workflows/sells-pest.yml)
