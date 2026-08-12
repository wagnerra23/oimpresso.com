---
date: "2026-08-11"
time: "16:15 BRT"
slug: o-label-que-nao-existia-e-a-camada-que-ninguem-atualizou
tldr: "O append-only de ADR foi suspenso por label no CI (#5602), mas o label NÃO EXISTIA no repo e o hook local não o conhece — a autorização de [W] ficou inexercível. Label criado; as 16 ocorrências de path antigo classificadas (#5608). Os 13 ADRs seguem NÃO editados, por bloqueio, não por esquecimento."
prs: [5602, 5608]
decided_by: [W]
related_adrs:
  - 0375-scope-md-sai-de-modules-para-memory-requisitos
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
next_steps:
  - "Decidir a rota dos 4 repaths de modulo vivo: OIMPRESSO_MEMORY_OVERRIDE=1 uma vez e declarado, ou mecanismo novo no hook"
  - "Decidir os 5 de modulo morto: deixar, de-linkar como citacao datada, ou apontar pra ADR que matou o modulo"
  - "Cascata LC-10: CONSTITUTION Art. 3 + CLAUDE.md + proibicoes.md ainda dizem append-only ABSOLUTO em presente — exige label constitution-amendment + audit, PR proprio"
---

# Handoff — o label que não existia, e a camada que ninguém atualizou

> **Cumprindo R12** via skill `encerrar-sessao` (ativação lazy).
> **MCP indisponível** (`Bridge fetch error` no `cycles-active`, `Server unavailable` no `my-work`) —
> checklist do passo 1 por **fallback filesystem**, declarado em vez de omitido. Mesmo sintoma do handoff 18:10.

## Estado MCP no momento do fechamento

- **MCP: FORA.** Duas tools testadas, as duas falharam na bridge — logo é a bridge, não uma tool.
- Fallback: `ls -t memory/handoffs/2026-08-11-*` → **8 handoffs hoje** (este é o 9º); `git log origin/main --since` → 5 commits em `memory/decisions/` hoje.
- **`#5602` MERGED** 17:58Z · **`#5608` MERGED** 19:09Z (por [W], 15s depois do último check ficar verde).
- Branch final: `claude/handoff-append-only-por-label`, de `origin/main` fresco (0/0).

## O que aconteceu

A sessão começou com a caveat dizendo *"próxima ação: abrir o PR da branch"*. **O PR já existia** — o `#5602` foi aberto 17:50Z por sessão irmã, no mesmo SHA que eu tinha, e mergeou 17:58Z. Não sobrescrevi o corpo dele; acrescentei comentário com o delta. Lição de operação: `gh pr list --author @me` no início da sessão **não é** estado permanente num repo com sessões paralelas — o meu `gh pr create` só descobriu o PR ao colidir.

**O achado principal:** a autorização de [W] (*"remover o append-only para mim"*) entrou em **uma** das duas camadas.

| camada | mecanismo | conhece o label? |
|---|---|---|
| CI pré-merge | `governance-gate` *Append-only canon* | ✅ desde o `#5602` |
| **Local pré-escrita** | hook PreToolUse `block-memory-drift.mjs` | ❌ **zero** (`grep -c` → 0) |

Os dois casam o mesmo alvo, então **todo `Edit` em ADR canon morre antes de chegar ao CI** — o meu primeiro morreu assim. Não burlei: o hook oferece `OIMPRESSO_MEMORY_OVERRIDE=1`, mas usar escape *emergencial Tier 0* por conta própria é decisão [W]. E um hook PreToolUse **não pode** consultar label (roda antes de existir PR), então "ensinar o label ao hook" não é opção literal.

**E o label `adr-body-edit-W` NÃO EXISTIA no repositório.** O gate o exigia desde o merge e ninguém podia aplicá-lo. Medido com controle positivo (`adr-metadata-normalization` rc=0 × `adr-body-edit-W` rc=1) e criado. Mesmo vão do `constitution-amendment`, exigido ~85 dias sem existir (§5 2026-08-08).

## A classificação — o corte não é o que se espera

**16 ocorrências em 9 ADRs.** O critério que decide não é *ponteiro × prosa*, é **o módulo ainda existe?**: **11 de módulo vivo** (4 delas links repathaveis) e **5 de módulo morto** (ADS · ProjectMgmt · Admin), onde repathar **cria um segundo link morto** que *aparenta* verificado. As 9 do padrão largo (README · CONTRACTS · SPEC de feature-wish · charter · checklist) a ADR 0375 **nunca moveu**. Detalhe em [`proposals/2026-08-11-adr-paths-0375-...`](../decisions/proposals/2026-08-11-adr-paths-0375-classificacao-e-o-hook-sem-label.md).

## Artefatos gerados

| artefato | onde | linhas |
|---|---|---|
| Proposal da classificação + do bloqueador | `memory/decisions/proposals/2026-08-11-adr-paths-0375-classificacao-e-o-hook-sem-label.md` | 103 |
| Label `adr-body-edit-W` | GitHub (não versionado — não há manifesto de labels) | — |
| Comentário de verificação no `#5602` | `issuecomment-5256906681` | — |
| Este handoff + índice | `memory/handoffs/` + `memory/08-handoff.md` | ~70 |

**Nenhuma ADR editada** — declarado por bloqueio, não por esquecimento.

## Lições catalogadas

1. **Duas correções minhas, as duas por medição.** (a) Ia tratar `0363:239` como claim presente-podre; **13 linhas adiante a própria ADR resolve** (*"só a linha 15 do `not_contains` sai, no PR que executar a incorporação"*) — a ref estava certa, eu não (§5 2026-08-10). (b) Ia publicar que o fail-loud do `#5569` tornou o vermelho visível, **duvidei** achando que `bash -e` abortaria igual, e o diff provou que a **dúvida** era o erro: a linha antiga tinha `2>/dev/null || true` no fetch, então sob o código velho a falha daria `Total changed: 0` e **gate verde sem validar nada**.
2. **O `#5569` se pagou no mesmo dia.** O vermelho do `#5608` foi `git fetch` → `server certificate verification failed` (exit 128). Antes do fail-loud, essa exata falha passava verde. Rerun → `success`: transiente **medido** (mesmo commit, outro resultado), não adjetivado.
3. **Nome de check é label, não mecanismo.** O job chama-se `ADR (memory/decisions/*.md)` e `*` de pathspec atravessa `/` — suspeitei que rotearia `decisions/proposals/`. O matcher real é a regex ancorada `^memory/decisions/\d{4}-.*\.md$`: não casa. **Não renomeei** — renomear context required deadlocka PR aberto (§5 2026-08-08).
4. **Especulação retirada por medição.** Ia flagar o `myfatoorah` (dep composer de host externo) como fragilidade recorrente; `ds-gate.yml` últimos 40 runs = **24 success · 1 failure · 9 cancelled**, e a única falha passou no retry. 1-em-40 não sustenta "sistêmico" — claim descartada.

## Próximos passos pra retomar

```
/continuar
```

As 3 decisões [W] estão no frontmatter `next_steps` e na §4 do proposal. A **mais urgente é a cascata**: com o label em `main`, `CONSTITUTION.md` Art. 3 · `CLAUDE.md` · `proibicoes.md` afirmam append-only **absoluto em presente** — LC-10 vivo.

## Pointers detalhados (consultar on-demand)

- [`#5602`](https://github.com/wagnerra23/oimpresso.com/pull/5602) — a exceção por label no gate + 4 proposals aceitas
- [`#5608`](https://github.com/wagnerra23/oimpresso.com/pull/5608) — a classificação + o bloqueador das duas camadas
- [handoff 18:10](2026-08-11-1810-scope-sai-de-modules-e-o-append-only-suspenso.md) — de onde este item veio
- [ADR 0375](../decisions/0375-scope-md-sai-de-modules-para-memory-requisitos.md) — o move que originou os paths antigos
