---
date: "2026-08-12"
time: "09:34 BRT"
slug: constituicao-v130-e-a-recusa-que-se-pagou
tldr: "Constituição em v1.3.0 — append-only de ADR passa a admitir exceção por label (ADR 0377) + audit §10.4 + cascata reconciliada. 8 ponteiros de path consertados em 3 tratamentos distintos. E a recusa de bumpar data pra ficar verde se pagou: outra sessão re-curou o alias-map 30min depois, provando que o defeito era conteúdo."
prs: [5630, 5644, 5654]
decided_by: [W]
related_adrs:
  - 0377-append-only-adr-excecao-por-label-emenda-0094
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0375-scope-md-sai-de-modules-para-memory-requisitos
next_steps:
  - "ENFORCEMENT.md:349 declara CONSTITUTION v1.1.0 — agora TRES versoes atras (v1.3.0). Bookkeeping, decisao [W]"
  - "As 8 ocorrencias de path antigo em PROSA de ADR seguem preservadas de proposito (fatos datados) — trocar e uma linha de script, se [W] quiser"
  - "G1 da 0377: hook block-memory-drift nao le label e nao pode (roda antes de existir PR) — editar ADR exige 2 atos"
---

# Handoff — a Constituição em v1.3.0, e a recusa que se pagou

> **Cumprindo R12** via skill `encerrar-sessao` (ativação lazy via hook `UserPromptSubmit`).
> **MCP VOLTOU** nesta metade da sessão — checklist do passo 1 cumprido de verdade, não por fallback.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI** (resposta real, não timeout).
- `my-work` → **8 tasks**, todas em `REVIEW` (7×`p1` + 1×`p2`): `US-TR-309/310/311/305/306`, `US-PROD-027`, `US-INFRA-023/048`. **Nenhuma tocada nesta sessão.**
- PRs: **`#5630` `#5644` MERGED** · `#5654` **CLOSED** (no-op) · `#5602` `#5608` `#5624` MERGED na metade anterior.
- Handoffs irmãos hoje: `2026-08-12-1043`, `-0740`, `-0749`.

## O que fechou

**[#5630](https://github.com/wagnerra23/oimpresso.com/pull/5630) — os ponteiros de path.** [W]: *"pode trocar todos"*. O "todos" resolveu em **três** tratamentos, e a medição mudou dois deles:

| tratamento | n | por quê |
|---|---|---|
| **repath** | 6 | módulo com sucessor que **contém** a citação |
| **de-link** | 2 | ADS removido e `SCOPE.md` **não** está entre os 18 docs preservados ⇒ repathar criaria 2º link morto que *aparenta* verificado |
| **preservado** | 8 | prosa sem link = fato datado. Repathar diria que o arquivo foi criado num path que não existia |

Dois que eu ia tratar como mortos **não eram**: `ProjectMgmt` é **rename classe A curado** no `ghost-rename-map` (decisão [W] 30/07 + ADR 0088 + chave no baseline), e a citação *"Fase 3.8 — DELETE Project legado"* **está na linha 3** do `SCOPE.md` do Forja. Sem medir o destino, dois ponteiros legítimos teriam virado citação morta.

**[#5644](https://github.com/wagnerra23/oimpresso.com/pull/5644) — Constituição v1.3.0.** [ADR 0377](../decisions/0377-append-only-adr-excecao-por-label-emenda-0094.md) formaliza a exceção; Artigo 3 registra as **três** (0257 frontmatter · 0297 legacy · 0377 corpo); [`audit-2026-08-11-v1.3.md`](../governance/audit-2026-08-11-v1.3.md) cumpre a §10.4 (*"sem audit report, o amendment não é ratificado"*) com L2→L7 medidos por comando; `CLAUDE.md:90` e `proibicoes.md:197` reconciliados.

**Achado não-solicitado, dentro da linha que eu já ia emendar:** a Verificação do Artigo 3 citava um *"pre-commit hook"* com flag **`--amend-charter`**. Medido com `rg --hidden`: **1 ocorrência no repo, e é a própria linha.** O documento supremo anunciava escape que não implementa (LC-15). Substituída pelo mecanismo real.

## A recusa que se pagou (é o que vale carregar)

O watchdog G6 avermelhava por `adr-alias-map.json` *"parado há 61d"*. O caminho de 1 comando era bumpar a data interna e ficar verde. **Recusei** — e medi por quê: o arquivo listava **14 colisões** contra **13** do gerador, com o próprio `_meta` apontando pra `0274-…-13-colisoes`. Era **divergência de conteúdo**, não data velha.

**Trinta minutos depois, outra sessão re-curou o arquivo:** `1c546d6c1b5` — *"re-cura do alias-map (**0101 deixou de colidir**) + índice de hooks"*. A causa real era exatamente a que eu não tinha como saber sozinho, e agora `13 = 13`, watchdog `rc=0`, `0 🔴 parado(s)`. Se eu tivesse maquiado a data, teria **escondido uma re-cura legítima** e o número errado seguiria no arquivo.

Corolário: o mesmo commit também regenerou o `_HOOKS-INDEX`, então o **[#5654](https://github.com/wagnerra23/oimpresso.com/pull/5654) virou no-op** — `git diff origin/main...HEAD` **vazio**. Fechado em vez de mergeado; PR de diff vazio é ruído no histórico.

## Lições catalogadas

1. **Guard de reescrita mordeu 4×, e as 4 estavam certas** — `version: 1.2.0` casava 2× (frontmatter + lista `amendments`); `dívida` com acento; o slug da 0257 **inventado** (derivei do nome do label em vez de medir o arquivo — LC-08); e a prova de identidade **por reversão** acusou ambiguidade porque o texto certo já aparecia 2× ⇒ troquei por **corte posicional**, que é a própria lição LC-16.
2. **Não escolher lado em conflito de índice.** `08-handoff.md` conflitou **2×** (12 sessões concorrentes). Resolvi mantendo todas as linhas, provando com contagem (`main=556 → 557`, +1). `_INDEX-GENERATED.md` conflitou e foi **regenerado da árvore** — 382 ADRs, com a `0376` de outra sessão e a `0377` minha ambas presentes.
3. **Meu grep mediu a forma errada e pareceu perda de dado:** procurei `0377-` (com hífen) no índice → `0`. O formato real é tabela `| 0377 |`. Refeito: presente.
4. **Susto do `vendor` foi instrumento, não dano.** Após `git worktree remove`, `ls vendor/laravel` voltou vazio. Medido: repo principal **intacto, 19 pacotes, 350M**; meu worktree nunca teve junction. O erro foi `ls <absoluto> || ls <relativo>` — o `||` esconde qual respondeu (família do `crontab -l`, §5 2026-07-17).
5. **Não chamei TLS de sistêmico sem base.** 2 required falharam por certificado (`curl error 60`, `self-signed`). Medi as 6 falhas do repo desde 11:00Z: **nenhuma** de certificado (`module-surface`, `Varre linhas ADICIONADAS`, **4× `Preflight de base`**). Transiente isolado — e o `Preflight de base` 4× me apontou defeito real meu: base **14 commits atrás**.

## Próximos passos pra retomar

```
/continuar
```

Os 3 pendentes estão no `next_steps` do frontmatter. O mais concreto: `ENFORCEMENT.md:349` diz `CONSTITUTION v1.1.0` — **três** versões atrás.

## Pointers detalhados

- [`#5644`](https://github.com/wagnerra23/oimpresso.com/pull/5644) — o amendment + o `--amend-charter` inexistente
- [`#5630`](https://github.com/wagnerra23/oimpresso.com/pull/5630) — os 3 tratamentos, com a matriz por ocorrência
- [handoff 16:15](2026-08-11-1615-o-label-que-nao-existia-e-a-camada-que-ninguem-atualizou.md) — a metade anterior (o label que não existia)
- [ADR 0377](../decisions/0377-append-only-adr-excecao-por-label-emenda-0094.md) · [audit v1.3](../governance/audit-2026-08-11-v1.3.md)
