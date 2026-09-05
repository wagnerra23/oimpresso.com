---
date: "2026-09-05"
time: "10:02 BRT"
slug: runbook-purchase-show-e-errata-lc10
tldr: "RUNBOOK da tela Purchase/Show criado e mergeado (#6807) — a 2ª âncora que o Show.casos.md pedia. Follow-up #6837 põe o recibo do smoke no §7 e vira ERRATA o §8, cuja frase apodreceu em horas quando uma sessão irmã criou o teste de comportamento que ele dizia não existir."
prs: [6807, 6837]
decided_by: [W]
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0141-skill-migracao-blade-react]
next_steps:
  - "Mergear #6837 (§7 recibo + §8 errata) — 1 arquivo, +50/−14"
  - "Decidir o CheckUserLogin ausente na rota do show: deliberado ou dívida (observação medida, sem teste vermelho)"
  - "uc-lane-baseline: UC-PURCRE-* e UC-PUREDT-* seguem sem lane (Create/Edit) — dono é [W], gate proíbe mexer na allowlist sozinho"
---

# Handoff — RUNBOOK do Purchase/Show + a errata que o próprio arquivo pediu

## Estado MCP no momento do fechamento

⚠️ **Não consultado — MCP indisponível nesta sessão.** Não há tools `mcp__oimpresso__*` no toolset;
o Daily Brief chegou pelo hook `SessionStart` (via curl), não por tool. Registro a ausência em vez
de colar um snapshot que eu não medi (é o fail-open da §5 2026-07-29: instrumento que não conseguiu
medir não afirma estado).

Substituto medido, por git:

- `origin/main` @ `aa47f84` no início do fechamento; **6 PRs de Purchase mergeados em ~2 dias** por
  sessões paralelas (#6795, #6807, #6808, #6811, #6824, #6826).
- Meu #6807 foi **squash-merged** em `9c40c02` às 10:20 UTC.

## O que aconteceu

O pedido era estreito: `memory/requisitos/Compras/_telas/` tinha RUNBOOK de Index, Create e Edit e
**não** tinha do Show, o que deixava o `Show.charter.md` (`status: draft`, três Non-Goals marcados
*"inferência pendente de Wagner"*) como fonte única — e por isso o `Show.casos.md` recebeu menos UC
que as telas irmãs, declarando a dívida em vez de disfarçá-la.

Escrevi o RUNBOOK (212 ln) espelhando os irmãos, **medindo cada afirmação no código** em vez de
herdá-la dos docs. Isso rendeu três precisões que os docs não tinham:

1. **O barcode está em `partials/show_details.blade.php:430`** — charter e casos diziam
   `show_details.blade.php`, sem o `partials/`.
2. **O "404 vs 403" que o charter deixou pendente se dissolve pela ORDEM dos gates:** 403 é sem
   permissão (1ª linha do método), 404 é id de outro tenant (`firstOrFail` sobre query já escopada).
   Três fontes concordavam com o 404; hoje há uma quarta — teste verde.
3. **A rota do `show` não está no `Route::resource`** — cai num grupo com middleware menor, e é
   sistêmico (`/sells/{id}` incluso). Medi as duas consequências em vez de deduzir: a sidebar do
   React **não** quebra (menu vem do `share()`, chave `shell.menu`); o **`CheckUserLogin` ausente
   muda a superfície de autorização**, e isso ficou como *observação medida, não achado* — não
   varri exploitabilidade nem escrevi teste vermelho.

## A errata — e é a parte que vale mais que o RUNBOOK

O §8 que eu escrevi afirmava, em presente: *"nenhum UC desta tela tem prova de comportamento; não
existe teste que crie a compra no tenant vizinho e prove o 404"*. Era **verdade em 2026-09-04** e
morreu no dia seguinte: uma sessão irmã criou `PurchaseShowTenantContratoTest` (request real, 404
cross-tenant **com controle positivo 200**) e a lane `purchase-pest.yml` que o roda (allowlist 1→2).

Ou seja: cometi **LC-10** (afirmação em presente sobre estado medido) dentro do arquivo em que eu
mesmo pregava o contrário. O #6837 registra como **errata datada, não apagamento**, e faz o §8
*apontar* para a tabela do `Show.casos.md` — dona do status por UC e cobrada por gate — em vez de
restatear. O segundo lugar drifa; foi assim que a errata nasceu.

## Artefatos

| Arquivo | Δ | Onde |
|---|---|---|
| `memory/requisitos/Compras/_telas/RUNBOOK-purchase-show.md` | +212 (novo) | mergeado `9c40c02` |
| idem — §7 recibo + §8 errata | +50/−14 | **#6837 aberto** |
| `resources/js/Pages/Purchase/Show.casos.md` | +14/−10 | mergeado (parou de dizer que falta o RUNBOOK) |

## Persistência

- **git:** #6807 merged · #6837 aberto.
- **MCP:** não registrado (indisponível — ver topo).
- **BRIEFING:** não tocado; mudança é doc-de-tela, não capacidade de módulo.

## Lições catalogadas (todas de método, todas minhas)

1. **LC-10 no próprio arquivo** — ver §errata acima. A defesa que funcionou não foi gate: foi
   reconferir o mundo no fechamento, quando o `git log` mostrou o teste irmão.
2. **Sonda que mentiu duas vezes.** `gh pr checks --watch` polou até estourar o rate limit; o `gh`
   devolveu `GraphQL: API rate limit already exceeded`, meu `JSON.parse` quebrou e o `exit 1`
   resultante **parecia CI vermelho**. Antes disso, um `grep "fail"` casou o *nome* do check
   `"fail-class bloqueia"`. Refeito pelo campo `bucket`, com parser que separa *não medi* de *medi*.
   Regra prática: **não use `--watch` neste repo** (112 checks queimam a cota) — consulta única.
3. **Controle negativo que não discrimina.** Meu 1º controle do smoke foi
   `/purchases/nao-existe-xyz`, que casa `{id}` e dá 302 igual às outras — quase entrou como prova.
   Controle precisa de path que **não casa rota nenhuma**.
4. **`rc` de pipeline é do último comando.** `node ... | tail; echo $?` me deu `rc=0` de um `node`
   que saiu 1.
5. **Vermelho herdado ≠ vermelho meu, e prova-se com controle.** Rodei o comando exato do CI
   (`uc-lane-coverage`) na branch e em `origin/main`: mesmos 12 UC, saída byte-a-byte idêntica
   exceto o `run-set` (469×468, porque o main avançou).

## Próximos passos pra retomar

```
gh pr view 6837 --json state,mergeStateStatus
```

## Pointers

- PR [#6807](https://github.com/wagnerra23/oimpresso.com/pull/6807) (merged) · [#6837](https://github.com/wagnerra23/oimpresso.com/pull/6837) (aberto)
- [`RUNBOOK-purchase-show.md`](../requisitos/Compras/_telas/RUNBOOK-purchase-show.md) · [`Show.casos.md`](../../resources/js/Pages/Purchase/Show.casos.md)
- Irmãs em voo no mesmo módulo: #6824 (lane Purchase), #6826 (errata Index/Show casos — ausência de lane), #6811 (RUNBOOK de Create no charter)
