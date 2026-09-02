# Handoff 2026-09-02 19:30 BRT — Zona cinza do visreg: não era render não-determinístico, era baseline de código velho

> Sessão `forja-onda10-integrador-tabs-627749` · [C] sob decisão [W] *"apenas faça"* (decisão pendente nº 2 do [handoff 13:50](2026-09-02-1350-forja-onda2-replica-primeiro-no-ar.md)).
> Session log: [2026-09-02-visreg-zona-cinza-baseline-de-codigo-velho.md](../sessions/2026-09-02-visreg-zona-cinza-baseline-de-codigo-velho.md).

## Estado em uma frase

A zona cinza herdada do `visual-regression` está **zerada** — mas o plano aprovado (rebake + quarentena) só se cumpriu pela metade, porque a **quarentena não tinha população**: medição de 28 runs em 16 branches deu **spread 0.0000%** em toda tela, ou seja **nenhuma é não-determinística**. A causa era a baseline de `main` ser foto de código velho, e o conserto foi no mecanismo, não na foto.

## O que mudou (PR [#6579](https://github.com/wagnerra23/oimpresso.com/pull/6579), aguarda merge [W])

1. **Mecanismo** — no modo `update` (dispatch), o workflow passa a alinhar o ref com `origin/main` **antes** de fotografar. Roda só em `workflow_dispatch`; `pull_request` intocado.
2. **Rebake** — **90 baselines** regeneradas de um ref alinhado (dispatch [33669306245](https://github.com/wagnerra23/oimpresso.com/actions/runs/33669306245)).
3. **Registro** — §5 2026-09-02 + LC-08 124→125 + nota datada em `_meta.correcoes` do `required-checks-baseline.json` (**zero mudança em contexts**).

## A premissa que caiu

A demoção de 2026-08-26 tirou o gate de required concluindo *"o número NÃO muda depois de regenerar a baseline ⇒ o render não é determinístico"*. **Invertido:** ratio bit-idêntico é assinatura de **determinismo**.

| tela | runs | branches | spread |
|---|---|---|---|
| `financeiro-unificado · selecionar-lote` (3 viewports) | 27 | 16 | **0.0000%** |
| `Jana` | 20 | 13 | **0.0000%** |
| `Ponto/Dashboard` | 20 | 13 | **0.0000%** |
| `Forja/Aprovacoes` | 12 | 8 | **0.0000%** |

**Causa real** (já escrita no próprio workflow, L118-129): `pull_request` faz checkout do **merge ref**, `workflow_dispatch` do **ref cru**. O update fotografa `ref`, o verify compara contra `merge(ref, main)`; e como `vrt/baselines-*` nasce do ref do dispatch e vai `--base main`, a foto velha **aterrissa em main**. Os 6 rebakes do dia saíram todos de branch de feature atrasada.

## Recibos (não afirmação)

- **Alinhamento, caminho no-op:** `0 atrás · 1 à frente → ✅ Já alinhado` (run 33669306245).
- **Alinhamento, caminho merge — bite-testado de propósito** (o dispatch principal não exercitou esse ramo): branch criada 12 commits atrás de main carregando o fix → `12 atrás · 1 à frente` → `Merge made by the 'ort' strategy` → `✅ 12 commit(s) integrados antes de fotografar` (run 33670902786). PR do bite-test [#6583](https://github.com/wagnerra23/oimpresso.com/pull/6583) **fechado** e branch deletada.
- **Foto nova pousou no render atual:** `snap-diff` antes×depois = `Jana` 293082 px / Δmax 253 e `Ponto/Dashboard` 269768 px / Δmax 194 — **exatamente** o delta que o gate vinha acusando. PNG novo da Jana aberto: contadores `CADASTRO 2 / COMERCIAL 1 / SISTEMA 2` presentes, 3 cards de KPI.
- **Gate verde nas 3 famílias que estavam cinza** (run 33670313000, PR de baselines): `Pixel-diff` ✅ · `Estados isolados matriz` ✅ · `Fluxos visuais Financeiro` ✅.

## Decisões que ficam com [W]

1. **Repromover `visual-regression` a required.** A demoção de 08-26 se apoiava numa premissa agora refutada por medição — mas o flip é branch protection, soberania [W] (ADR 0275 §5 / R10). **Não foi tocado.** A nota em `_meta.correcoes` registra a refutação sem mexer em contexts.
2. **Race residual.** O alinhamento fecha o skew ref↔main, mas se um PR de UI mergear entre o dispatch e o verify, a baseline nasce atrás outra vez. A janela caiu de dias para minutos; fechá-la de vez seria outro desenho (regenerar no merge).

## Fora de escopo, de propósito

`Fiscal/Cockpit` (0.849%) e `Forja/Aprovacoes` saíram da zona cinza **sozinhos** após o hotfix [#6559](https://github.com/wagnerra23/oimpresso.com/pull/6559) (merge 15:33) — eram bug real de tela, já consertado upstream. Não rebakei nada por causa deles.

## Estado MCP no momento do fechamento

⚠️ **Sem snapshot MCP.** O servidor não respondeu a sessão inteira (o hook `brief-fetch` do SessionStart caiu em fallback por timeout), então `cycles-active` / `my-work` / `sessions-recent` / `decisions-search` / `whats-active` **não foram consultados** — [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) não cumprida, declarado em vez de omitido.

Como proxy do `whats-active` usei a listagem de sessões locais: **nenhuma outra sessão** tocando `visual-regression.yml` ou `tests/.pest/snapshots` (várias em Forja/UI, que só se beneficiam). Registro honesto: proxy não é o oráculo.

## Armadilhas que esta sessão pagou

- **Backtick dentro de `node -e "..."` no bash** — o shell comeu 3 termos por command substitution e gravou a nota mutilada no JSON. Conserto: texto num arquivo, `readFileSync` no node, e **controle** conferindo que os termos sobreviveram. Vale pra qualquer conteúdo com crase passado por string dupla.
- **`/c/Users/...` (POSIX) não resolve no node do Windows** — é a §5 2026-08-21, e caí nela ao decodificar PNG. Caminho `C:/...`.
- **O `snap-diff` mede Δ≥1 e o gate mede com tolerância** — 15,18% × 0,287% na mesma tela não é contradição; são réguas diferentes. Não confundir ao comparar.
