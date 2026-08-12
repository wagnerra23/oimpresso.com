---
date: "2026-08-12"
time: "07:49 BRT"
slug: reguas-evidencia-integra-e-o-monitor-que-mentiu
tldr: "PR #5619 mergeado: a grade de réguas passa a gravar a evidência INTEIRA (200→700 chars medido), o incremento do veredito de integração e um caveat de denominador derivado. No caminho, meu próprio monitor de CI declarou 'TODOS VERDES' medindo 0 checks — a mesma classe que o PR conserta."
prs: [5619]
decided_by: [W]
related_adrs: [0353-maquina-evolucao-reguas-looping]
next_steps:
  - "Rodar a próxima grade (full) e conferir no ledger que a evidência gravada não está mais em ~200 chars e que as claims trazem `incremento`."
  - "Decisão [W] pendente: proveniência do retrato 2026-08-08 diz '3 fraquezas' mas o ledger tem 2 — bookkeeping do campo `data` no upsert."
---

# Réguas: a evidência íntegra, e o monitor que mentiu verde

## Estado MCP no momento do fechamento

⚠️ **Snapshot PARCIAL — declarado, não maquiado.** `cycles-active` deu **timeout**; `whats-active` retornou **CEGO** por conta própria (*"pipeline de ingest SEM heartbeat fresco — fresh=0 · stale=0 · dead=95 · NÃO assuma escopo livre"*). Ou seja: não há como afirmar por MCP que o escopo estava livre — e o próprio [W] avisou que outra sessão salvava em paralelo.

- `my-work`: **6 tasks em REVIEW** (US-TR-309, US-TR-310, US-PROD-027, US-INFRA-023, US-TR-305, US-TR-306) — nenhuma tocada aqui.
- Brief do SessionStart: 47 commits/24h · ADR mais recente 5275 (`§5 vira DERIVADO`).
- Colisão medida no índice de handoffs: **8 commits tocaram `memory/08-handoff.md` só em 11/08**.

## O que aconteceu

Pedido: 3 correções no `.claude/workflows/reguas-do-sistema.js`, agrupadas de propósito para não haver duas sessões no mesmo path.

1. **Evidência íntegra.** `evidencia` é `required` no schema `EXISTE` (é o que torna a nota auditável) e era descartada na persistência por 3 `.slice()` mudos. Antes→depois medido com o mesmo dublê: **200 → 700 chars** (perda 71% → 0). O teto novo (2000) é ~6× o maior valor do corpus (max=335, p50=139, n=63) e **loga** quando morde.
2. **`incremento`.** O dado já viajava no payload; faltava a instrução de gravação — por isso **0 de 51** claims tinham o campo.
3. **Caveat de denominador**, derivado (nunca escrito à mão), com controle negativo: conjunto idêntico **não** dispara.

**Três premissas do pedido não bateram, e foram corrigidas medindo:** a rodada de 08-11 foi `full-parcial` (não delta), logo o corte veio do cap **200**, não dos 250; "7,7 com 3 fraquezas" são **2** no ledger; e a interseção de ids é **vazia**, não 1.

O bônus (inventário no dossiê) foi **descartado**: o [#5607](https://github.com/wagnerra23/oimpresso.com/pull/5607) fez o mesmo antes e melhor (teto de 500 palavras, marcação `[ainda aberta]`/`[já coberta por]`, recibo 373/466 = 80%).

**Três reconciliações** até mergear: #5607 (dossiê), a resolução remota feita por [W] no GitHub (juntei, não sobrescrevi) e o #5634 (rubrica da nota) — que mergeou primeiro, como o `Dedup-ack` previa. Fechado com auto-merge squash em `c380d92886b`.

## Artefatos gerados

| Arquivo | O quê |
|---|---|
| `.claude/workflows/reguas-do-sistema.js` | helper `evid()` nos 3 sites de persistência · `incremento` no `promptClaims` · função pura `caveatDenominador` · `cobertura.denominador` forward-only |
| `scripts/governance/reguas-workflow.test.mjs` | blocos `[10]` evidência · `[11]` incremento · `[12]` caveat — bite-test + controles negativos |

## Persistência

- **git:** `c380d92886b` (squash) em `main`. Verificado no `origin/main` **atual** (pós +15 commits): `evid(` 3× · `caveatDenominador` 4× · `**incremento**` 1× · blocos `[10][11][12]` presentes.
- **CI:** selftest do workflow **93 asserções / 0 falhas** no main (convivem `[9]` #5607 · `[10-12]` este · `[13]` #5634 · `[14]` #5645); `reguas-ledger-check --check` `rc=0`.
- **ledger `memory/reguas/`:** intocado por este PR (o efeito aparece na PRÓXIMA rodada da grade).

## Próximos passos pra retomar

```
git worktree add -b <branch> <path> origin/main && node scripts/governance/reguas-workflow.test.mjs
```

## Lições catalogadas

- **LC-13 (verde por não-execução), cometida por mim, duas vezes.** (a) Meu monitor de CI declarou `0 checks, TODOS VERDES` — cobri falha e sucesso, esqueci o **conjunto vazio**: `pending=0` e `fail=0` num array vazio satisfazem "sucesso". (b) Ao resolver o conflito com o #5634, o `}` compartilhado deixou um bloco aberto e o selftest saiu **`rc=1` com 0 asserções** — crash, não falha. Nos dois casos quem salvou foi olhar o **denominador**, não o status. Monitor corrigido para exigir `total > 0` e reportar *"NÃO MEDIDO — isso não é verde"*.
- **LC-08 (fonte errada), 2×:** `git show origin/main:<path>` manglado pelo MSYS devolveu `0` ocorrências, que eu quase li como ausência; e um `node -e` com erro de sintaxe devolveu saída vazia. Vazio de comando que falhou nunca é evidência.
- **Vermelho de CI ≠ código:** dos 4 vermelhos da sessão, **nenhum** era do diff — SSL do runner (`curl error 60`), base atrasada (`baseline-tamper-guard` acusando a promoção do #5593 invertida), `dup-detector` (achado real, mas advisory) e o formato do meu próprio `Dedup-ack` (`^Dedup-ack:` exige início de linha).
- **Quase editei a árvore de outra sessão:** apontei o Edit para `D:\oimpresso.com\` em vez do worktree. O tool barrou por eu não ter lido aquele arquivo — sorte, não mecanismo.

## Pointers detalhados

- Session log: [`memory/sessions/2026-08-12-reguas-evidencia-integra.md`](../sessions/2026-08-12-reguas-evidencia-integra.md)
- PR: [#5619](https://github.com/wagnerra23/oimpresso.com/pull/5619) · vizinhos: [#5607](https://github.com/wagnerra23/oimpresso.com/pull/5607) · [#5634](https://github.com/wagnerra23/oimpresso.com/pull/5634) · [#5645](https://github.com/wagnerra23/oimpresso.com/pull/5645)
