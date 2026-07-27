---
date: "2026-07-27"
hour: "17:54 BRT"
topic: "Trio de comportamento pra Sells/Show — alvo #1 do débito Tier-0 + lane MySQL nova pra Sells"
authors: [C]
prs: [4877]
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0093-multi-tenant-isolation-tier-0, 0143-fsm-pipeline-live-prod-marco-2026-05-12]
outcomes:
  - "piso Tier-0 coberto 29 → 32; débito quente 89 → 86; Sells/Show sai do debt_ranked"
  - "lane sells-pest.yml nasce: 0 dos 214 alvos sqlite e 0 das 9 lanes MySQL rodavam tests/Feature/Sells"
  - "3 correções factuais medidas: ?v=2 não existe no show(), CT100 não roda estes contratos, Wave1ShowInertiaTest está vermelho"
---

# Sessão — trio de comportamento pra `Sells/Show` (alvo #1 do débito Tier-0)

## O pedido

Fechar a cobertura de comportamento de `resources/js/Pages/Sells/Show.tsx` — o topo do
ranking de `node scripts/qa/exposicao-tier0.mjs` (`exposure_score 11`, categorias
`dinheiro,estoque,fiscal`), sem `.casos.md` e sem um único UC.

## O que a tela tinha (e por que não valia)

`npm run screen:files -- Sells/Show` mostrava trio incompleto: `.tsx` ✓, `.charter.md` ✓,
`.casos.md` ✗. Três arquivos de teste **pareciam** cobri-la —
`Wave1ShowBaselineTest`, `Wave1ShowInertiaTest`, `SellsShowCoworkTest` — mas os três são
**estruturais**: leem o `.tsx`/Controller com `file_get_contents` e casam string. Provam
que o código está *escrito*; nenhum prova que a resposta HTTP cumpre o charter.

## Os 7 UC (derivados do contrato, não do código)

Ordem de fonte canônica ([how-trabalhar §Pedido de tela](../how-trabalhar.md)): charter
§Goals/§Non-Goals · [RUNBOOK-show](../requisitos/Sells/RUNBOOK-show.md) §2/§9/§10 ·
[CASOS-USO-PIPELINE-VENDAS](../requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md) §CU-07 ·
ADR 0093/0143/0101. O `SellController@show` foi lido **só pra confirmar** — nenhum caso
deriva dele (teste derivado do código é tautológico, [proibicoes §5 2026-06-05](../proibicoes.md)).

| UC | Contrato | Prova |
|---|---|---|
| `UC-VSHOW-01` | charter §Goals Tier 0 + ADR 0093 | venda de outro business → 404 |
| `UC-VSHOW-02` | charter §Goals (gate das 3 permissões) | sem as 3 → nunca 200 |
| `UC-VSHOW-03` | charter §Goals + RUNBOOK §2 | só `view_own_sell_only` → venda de outro vendedor não abre |
| `UC-VSHOW-04` | charter §Goals (4 KPIs) + REGRA MESTRE valor | Pago = soma real dos pagamentos |
| `UC-VSHOW-05` | charter §Goals defer + §UX Targets | `detail` fora do 1º response **e** chega quando pedido |
| `UC-VSHOW-06` | charter §Non-Goals + ADR 0143 | GET não escreve na transação |
| `UC-VSHOW-07` | §CU-07 | trilha chega ao operador com autor e data |

Todo caso que afirma *"X não aparece / nada mudou"* carrega **pré-condição anti-vácuo**
provando que a operação aconteceu ([proibicoes §5 2026-07-24](../proibicoes.md)).

## O achado que mudou o escopo

Varredura contada: **0 dos 214** alvos de `.github/ci-sqlite-pest.list` e **0 das 9** lanes
`DB_CONNECTION: mysql` incluem Sells. Os **72 arquivos** de `tests/Feature/Sells/` **não
rodavam em CI nenhum**. Sem lane, o teste novo seria mais um arquivo mudo — daí
`sells-pest.yml` (espelho de `essentials-pest.yml`, reusa `pest-mysql-setup`).

Corolário: o `Wave1ShowInertiaTest` está **vermelho** e ninguém vê — assere `vendas-cockpit`
num charter que diz `vendas-page.jsx` (o RUNBOOK é que diz `vendas-cockpit`). **Não
consertado**: escolher entre charter e RUNBOOK exige fonte que não tenho, e inventar é pior.

## Correções factuais do briefing de entrada

1. **`?v=2` não existe no `SellController@show`** — o gate é só `request()->header('X-Inertia')`.
   O padrão `header || query('v')==='2'` é do `Purchase`/`StockAdjustment`/`StockTransfer`.
   A conclusão do briefing (a React é alcançável em prod) **continua de pé por outro caminho**:
   7 sites usam `<Link>`/`router.visit` pra `/sells/{id}` (`SaleSheet:855`, as 4 abas de
   `Cliente/_show`, `Fiscal/NotaDrawer:350`, `Nfse/Show:294`), todos mandando o header.
2. **O CT 100 não roda estes contratos.** Medido: `oimpresso_staging` tem **15 tabelas**
   (sem schema UltimatePOS) → `EstoqueFixture::schemaReady()` false → 7 `markTestSkipped`.
   O container também está ~30 commits atrás. O gate real é a lane MySQL do CI — mesma casa
   dos contratos irmãos de `Produto`.

## Os 3 vermelhos do CI — 2 não eram meus

| Falha | Causa | De quem |
|---|---|---|
| `Casos-coverage · ratchet` (required) | branch 8 commits atrás; `c15221f634` (#4879) removera 5 `UC-FORJA` do baseline | **não-minha** — merge com main |
| `baseline-tamper-guard` | mesma raiz | **não-minha** — merge com main |
| `SUPERFICIE.md == árvore` | `Show.casos.md` entrou na árvore (Casos 2 → 3) | **minha** — `module-surface --write` |
| `memory-health` Check G 🔴 | workflow novo fora do censo de gates | **minha** — registrado com `terminal`+`promote_by`+`anchor` |
| `dup-detector` | colisão de lane com #4868 | **real** — `Dedup-ack` com canônico + plano |

Padrão da rodada: **cada vermelho exigiu medir de quem era antes de corrigir**. Nos dois do
baseline, "consertar o sintoma" (editar o baseline) seria exatamente o afrouxamento que o
guard existe pra impedir.

## Resultado (medido no `main` pós-merge)

```
piso Tier-0 coberto   29 → 32
débito quente         89 → 86
Sells/Show.tsx        FORA do debt_ranked · DENTRO de covered_hot_screens
topo do débito        Sells/Show (11) → NfeBrasil/Manifestacao/Index (9)
lane sells-pest       7 passed, 37 assertions (MySQL real, biz=1 × biz=2, zero skip)
CI final              97 success · 3 skipped · 0 failure
```

Parte da queda veio de PRs vizinhos (#4878 Sped); o delta próprio deste PR é **−1**.

## Lições

- **Verde de monitor pode ser erro de API.** Dois monitores "concluíram" porque
  `gh pr checks` estourou o rate-limit de GraphQL e imprimiu a mensagem de erro com
  **exit 0** — o `awk` leu "sem pending". Mesma família do `crontab -l ||`
  ([proibicoes §5 2026-07-17](../proibicoes.md)). Troquei pra REST (que tinha 4955 chamadas
  contra 0 do GraphQL) e passei a separar `failure` de `cancelled` — o primeiro filtro
  misturava os dois e escondeu a 3ª falha.
- **Um vermelho de gate não implica autoria.** 2 dos 3 vermelhos vinham de branch
  desatualizado; a correção certa foi `git merge origin/main`, não tocar no baseline.
- **Índice derivado tem dono.** `SUPERFICIE.md` foi regenerado por comando
  (`module-surface.mjs Sells --write`), não editado à mão.

## Pointers

- PR [#4877](https://github.com/wagnerra23/oimpresso.com/pull/4877) (squash `0570e3c8d4`)
- [Show.casos.md](../../resources/js/Pages/Sells/Show.casos.md) · [SellsShowContratoTest](../../tests/Feature/Sells/SellsShowContratoTest.php) · [sells-pest.yml](../../.github/workflows/sells-pest.yml)
- Handoff: [2026-07-27-1754-sells-show-trio-tier0.md](../handoffs/2026-07-27-1754-sells-show-trio-tier0.md)
