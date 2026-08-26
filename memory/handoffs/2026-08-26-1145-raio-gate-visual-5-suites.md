# Handoff 2026-08-26 11:45 BRT — Gate visual: o raio existia e alcançava 1 das 5 suítes

> Append-only ([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)). Não editar depois.

## Onde o trabalho parou

**[PR #6280](https://github.com/wagnerra23/oimpresso.com/pull/6280) mergeado** por [W] às 11:26Z
(`3ee2378d03` em main). Fecha a metade que faltava do conserto do #6188: o `source` — único campo
por onde a partição dívida PRÓPRIA × HERDADA decide — passou a ser declarado nos 3 manifestos
(`visreg-states.json`, `visreg-flows.json`, `visreg-flows-sells.json`) e passado pelas 4 suítes de
baseline que não o passavam. Narrativa completa + medição em
[`sessions/2026-08-26-raio-gate-visual-5-suites.md`](../sessions/2026-08-26-raio-gate-visual-5-suites.md).

## O que a próxima sessão precisa saber

1. **`visual-regression` NÃO é mais required** (decisão [W], [#6278](https://github.com/wagnerra23/oimpresso.com/pull/6278),
   já aplicada no vivo). União protection clássica ∪ ruleset = **45 contexts**, e ele não está em
   nenhum dos dois. O workflow continua rodando, advisory. Não trate vermelho dele como bloqueio de
   merge — mas também não o ignore: com o #6280, o vermelho dele voltou a ser informativo.

2. **Duas dívidas de baseline vivas em main, de naturezas diferentes:**
   - `Arquivos` (0.1047%) — baseline **velha**; o #6275 rebakeou 27 baselines e **não** incluiu
     essa tela. Dono natural: [#6283](https://github.com/wagnerra23/oimpresso.com/pull/6283), que
     está editando `Arquivos/Index.tsx`. **Não rebakeie de fora** — eu disparei e cancelei
     justamente por isso.
   - `financeiro-unificado · {default,loading,error}` (0.1199%) — render **não-determinístico**.
     Rebake **não resolve** (provado: #6275 regravou as 5 e voltou idêntico). Task local aberta pra
     achar o 2º escritor de `fin_titulos` com data relativa a `Carbon::now()`.

3. **Raiz ainda aberta, é decisão [W]:** `visual-regression` só dispara em `pull_request` — sem
   `push: main`, sem `schedule`. Dívida em main é invisível até um PR inocente ficar vermelho; foi
   assim 3× em 3 dias. Rodar o gate em main (nightly) é a peça que falta.

## Estado MCP no momento do fechamento

⚠️ **Honestidade sobre o checklist da ADR 0130:** as tools MCP do oimpresso
(`cycles-active`, `my-work`, `sessions-recent`, `decisions-search`, `whats-active`) **não estão
disponíveis nesta sessão** — procurei via `ToolSearch` e não há nenhuma registrada. O único estado
MCP que tenho é o snapshot que o hook `brief-fetch-curl` imprimiu no SessionStart, e ele é de lá:

- Brief **#573**, gerado ~37 min antes do início · cycle sem rótulo · HITL pendente [W]: **5**
- Flags: 🟠 690 US não atribuídas (533 sem dono) · 🟡 SDD composta 39,2 (Δ−0,1) · 🟢 migration
  aging, PRs aguardando review e visual regression sem nada crítico

Por isso **não** registrei task no MCP (`tasks-create`/`tasks-update`) — não consegui, não escolhi
não fazer. A próxima sessão que tiver MCP conectado deve criar a task da dívida do
`financeiro-unificado` e ligar ao #6280.

## Verificação por consequência (não por declaração)

- `3ee2378d03` está em `origin/main` (`git log origin/main`)
- #6280: 111/112 checks verdes · 45 required todos verdes · `Frontend / Vite build` pass
- Lints do contrato: `visreg-states-lint` 9/9 · `visreg-flows-lint` 7/7 · `visreg-sells-lint` 5/5
  (selftests mordendo dos dois lados) · `ui-impact --selftest` ok · `sec5-derive --check` em dia
- ⚠️ **O que NÃO foi exercitado no CI:** o step `Estados isolados matriz` foi **skipped** no run do
  #6280 (o step anterior falhou e o `if:` dele não tem `always()`), então a mudança em
  `IsolatedStatesBaselineTest` não rodou no CI naquele run. Foi provada localmente contra o raio
  real do #6008 (`propria=0 herdada=3`), e pelos 3 controles negativos do `VisregGrayApprovalTest`.
  O primeiro PR que tocar tela e passar por aquele step confirma no vivo.
