---
date: "2026-08-12"
time: "17:55 BRT"
slug: glob-inertia-e-as-duas-camadas-de-mudez
tldr: "Corrigida afirmação falsa em canon (o local das Pages é convenção do projeto, não imposição do Inertia) e o gate que a protegia, que estava mudo em DUAS camadas — trigger e quarentena. 4 PRs mergeados. Três erros meus da classe consertada, contados no ledger (LC-10, LC-11, LC-13)."
prs: [5679, 5681, 5689, 5696]
decided_by: [W]
next_steps:
  - "Nada bloqueado. Os 4 describes do bundle CSS seguem em quarentena — consertar ou deletar é decisão [W] já registrada lá."
---

# Glob do Inertia: a afirmação falsa e as duas camadas de mudez

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO** em COPI
- `my-work` → **8 tasks em REVIEW** (US-TR-309/310/311, US-PROD-027, US-INFRA-023/048, US-TR-305/306) — nenhuma tocada nesta sessão
- Handoffs irmãos de hoje: **6** (`0934`, `1026`, `1043`, `1231`, `1508`, `1617`) — inclui o `1508-lc15-mwart-override`, que é a sessão do chip que esta aqui gerou
- `memory/08-handoff.md` recebeu **11 commits só hoje** → índice tocado por último, com `fetch` imediatamente antes (passo 3.1)

## O que aconteceu

[W] trouxe uma afirmação falsa já em canon, com reincidência medida: um session log de 2026-05-15
afirma que *"Inertia resolve global resources path"*, e um agente repetiu isso a ele **como fato de
arquitetura**. Verdade medida: o `resolve` do `createInertiaApp` é **callback arbitrário** — o local
das Pages é **convenção nossa**, fixada em **dois** globs sincronizados à mão (`app.tsx:104` +
`ssr.tsx:17`) e cravada por teste.

Ao verificar isso, o escopo mudou: **o teste que cravava o glob não rodava quando o glob mudava.**
E depois do primeiro merge, o log do CI revelou uma **segunda** camada — o arquivo estava em
quarentena da lane por causa de *outros* describes quebrados.

## Artefatos gerados

| PR | O quê |
|---|---|
| [#5679](https://github.com/wagnerra23/oimpresso.com/pull/5679) | errata em `.claude/rules/pages.md` · trigger da lane (`app.tsx`+`ssr.tsx` em `push.paths` e no filtro `fin:`) · `components-tree-guard` para de prometer catraca inexistente |
| [#5681](https://github.com/wagnerra23/oimpresso.com/pull/5681) | contrato extraído p/ `InertiaPagesGlobContratoTest.php`, **fora** da quarentena · 4 UCs com matriz de mordida |
| [#5689](https://github.com/wagnerra23/oimpresso.com/pull/5689) | emenda §5 (fonte `licoes-rejeitadas.md`) + ledger LC-11 `8→9`, LC-13 `12→13` |
| [#5696](https://github.com/wagnerra23/oimpresso.com/pull/5696) | `pages.md` para de narrar o estado do hook mwart em **tempo presente** |

**Session log NÃO editado** (append-only) — errata mora na rule, que é o que carrega ao editar `.tsx`.

## Persistência

- **git** — 4 PRs mergeados em `main` (todos por [W]; eu executei só o merge do #5689)
- **MCP** — webhook GitHub→MCP propaga `memory/*` em ~2min
- **BRIEFING** — não aplicável: nenhuma capacidade de módulo mudou (o toque no `Modules/Financeiro`
  foi teste de contrato + `SUPERFICIE.md` regenerado, ambos derivados)

## Prova de que fechou (contador, não verde)

```
describe no log da lane   :  0  →  4 ocorrências
suíte Financeiro          : 348 → 352 passed
assertions                : 1488 → 1495       (delta +4/+7 = exatamente o arquivo novo)
```

## Lições catalogadas

**Emenda §5** (2026-08-12, estende a lápide de 2026-08-02 *"registrar no phpunit.xml ≠ roda"*):
o corolário dela — *"inclua o arquivo sob teste no trigger"* — foi seguido **à risca** e o teste
**ainda** não rodava. A mãe cobre as camadas que **incluem**; faltava a que **exclui**
(`quarantine.list`, allowlist per-lane, `--exclude`, `paths-ignore:`).

> **Rodar o arquivo direto prova que o assert MORDE — nunca que a LANE o executa.**

**Ledger (3 erros meus, todos da classe que a sessão consertava):**

| | Classe | O quê |
|---|---|---|
| LC-13 `12→13` | verde por não-execução | declarei *"ciclo completo provado"* medindo só o trigger; foi mergeado assim |
| LC-11 `8→9` | presence-gate | o UC anti-reincidência era ele mesmo um presence-gate (`toContain` em texto bruto) |
| LC-10 | afirmar enforcement em presente | escrevi na rule que *"o hook ainda oferece"* — o #5683 inverteu em **horas** |

**Não incrementei LC-08 de propósito:** é o mesmo evento do LC-13 por outro ângulo, e contar um
evento em duas classes infla o contador que decide promoção de gate.

**Regra nova, barata e reutilizável:** *boa e ruim empatadas no bite-test = não provou nada; abra o
detalhe por caso.* Foi o que pegou o LC-11.

## Próximos passos pra retomar

Nada bloqueado — a sessão fechou completa. Se algo voltar ao tema:

```
node scripts/governance/sec5-derive.mjs --check && node .claude/hooks/licoes-code-two-strikes.mjs
```

Decisão [W] em aberto, já registrada onde mora: os **4 describes do bundle CSS** em
`.github/financeiro-pest-quarantine.list` (CSS deletado no #2127) — consertar como teste de
comportamento ou deletar.

## Pointers detalhados

- Session log (narrativa + armadilhas de ambiente): [`2026-08-12-glob-inertia-e-as-duas-camadas-de-mudez.md`](../sessions/2026-08-12-glob-inertia-e-as-duas-camadas-de-mudez.md)
- Handoff irmão gerado por esta sessão (chip do hook): [`2026-08-12-1508-lc15-mwart-override-o-escape-impossivel.md`](2026-08-12-1508-lc15-mwart-override-o-escape-impossivel.md)
