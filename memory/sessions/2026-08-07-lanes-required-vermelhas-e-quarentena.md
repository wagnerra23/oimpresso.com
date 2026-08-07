---
date: "2026-08-07"
topic: "Lane de Estoque destravada, e o radar achou 2 lanes required permanentemente vermelhas que ninguém tinha visto — mais 3 defeitos no reporter de quarentena"
authors: [C, W]
prs: [5387, 5395, 5399]
us: [US-COM-022, US-PONTO-014]
outcomes:
  - "Lane `PHP / Pest (Estoque · MySQL)` (required) saiu de 3 failure seguidas no main para 3 success seguidos (888db02a6c4 → 0c3146e0941 → df303aa7346), trocando allowlist inline por árvore-menos-quarentena."
  - "Medição das 5 lanes Pest required revelou Compras e Ponto em failure 5/5 no main — promovidas na MESMA ADR 0369, no mesmo dia, e permanentemente vermelhas. Viraram US-COM-022 e US-PONTO-014."
  - "Ponto tem 27 de 38 testes fora da allowlist da lane required (71%), incluindo os 3 que defendem o append-only Tier 0 da Portaria 671/2021 — e o BRIEFING os listava com ✅, que era presença e não execução."
  - "O reporter test-lane-coverage.mjs não enxergava NENHUMA das 45 linhas de quarentena (0/45 casavam path real; agora 45/45) porque duas leituras do formato .list não removiam comentário inline — 45 decisões conscientes eram contadas como órfãs."
  - "Adversário (testador-de-maquinas) deu veredito MORDE com ressalva: 91 assertions e 0 skipped provam que o run-set não é vácuo, mas as assertions caíram 213 → 91 e o mecanismo novo tem poucos runs, todos verdes — sem recibo de mordida própria."
  - "LC-08 ocorrência 58: esperei um run de CI passando um id que nunca medi; o gh errou em silêncio e reportei 'aguardei' tendo esperado zero."
related_adrs: [0369-tres-lanes-pest-required-emenda-0314, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0344-two-strikes-cobre-processo]
---

# Sessão 2026-08-07 — lanes Pest required: o vermelho que escondia, e o que a quarentena não conta

> Começou com uma pergunta de 6 palavras do [W] — *"o que tenho que aprovar aqui?"* sobre o
> [#5387](https://github.com/wagnerra23/oimpresso.com/pull/5387) — e terminou com 3 PRs mergeados,
> 2 US novas no radar e 3 defeitos consertados que ninguém tinha pedido pra procurar.

## 1. O ponto de partida (#5387)

A lane `PHP / Pest (Estoque · MySQL)` é **required** desde 2026-08-05 (ADR 0369) e estava em
`failure` nos 5 últimos runs do `main`. O PR trocou a seleção de **allowlist inline** por
**árvore-menos-quarentena** — o mesmo mecanismo do irmão `financeiro-pest.yml`.

O que o [W] aprovava, na prática, eram **duas** decisões: o mecanismo, e as **21 entradas** da
quarentena (6 failing-first + 14 `Wave2*` de status desconhecido + 1 com lane própria). Aprovado
e mergeado em `888db02a6c4`. Recibo: a lane saiu de 3 `failure` seguidas para `success`.

## 2. O radar (#5395) — o achado que ninguém procurou

Fechado o #5387, medi as **5 lanes Pest required** no `main` (últimos 5 runs cada):

| lane required | main |
|---|---|
| Estoque · Financeiro · NfeBrasil | ✅ |
| **Compras** | ❌ ❌ ❌ ❌ ❌ |
| **Ponto** | ❌ ❌ ❌ ❌ ❌ |

Compras e Ponto foram promovidas **na mesma ADR 0369, no mesmo dia** — e seguem permanentemente
vermelhas. Nas duas o step que falha é o `Run Pest — ALLOWLIST VERDE (catraca)`: falha de teste,
não de infra (a própria 0369 classificou assim ao promover).

Árvore vs allowlist inline, medido em `origin/main`:

| módulo | árvore | allowlist | fora |
|---|---|---|---|
| Compras | 10 | 7 | 3 |
| **Ponto** | 38 | 11 | **27 (71%)** |

Viraram **US-COM-022** e **US-PONTO-014**, `p0` sem dono. A prioridade é **do gate, não do módulo**:
o Ponto não tem uso em prod (0 marcações, medido 08-03 na US-PONTO-013); o que é p0 é a lane
required vermelha travando merge de todo mundo.

### 2.1 O que apareceu ao consertar o CI do próprio PR

O #5395 abriu vermelho em 2 gates — e **nenhum era bug do PR**. É o caso da lápide §5 2026-07-12
(+ emenda 07-27): tocar SPEC acorda gate diff-aware.

- `Governance Gate` → `_BACKLOG-GENERATED.md` fora de sincronia. Regenerado.
- `SDD scorecard ratchet` → `distiller_freshness: 0 → 1` (SPEC de Ponto mais novo que o BRIEFING).

**O conserto do 2º não foi bumpar a data.** Fui ler o BRIEFING e a §Cobertura de teste listava
**7 testes com `✅ existente`** — e os 7 estão entre os 27 que a lane required **não executa**,
incluindo `MultiTenantIsolationTest`, `MultiTenantAppendOnlyTest` e `CrossTenantMarcacaoTest`,
que são os que defendem o append-only Tier 0 da Portaria 671/2021. O `✅` era **presença, não
execução** — LC-11 escrito num doc executivo. A seção ganhou a ressalva com o número medido, e
**só então** a data subiu; o `distilled_by` declara que a redestilação foi PARCIAL e diz qual
seção foi re-lida.

## 3. O adversário (`testador-de-maquinas`)

[W] pediu adversário sobre a lane recém-mergeada. Pergunta: **morde, ou virou verde-que-não-pode-
ficar-vermelho?** (§5 2026-08-04, o `shipped-log-gate` que nunca teve como reprovar.)

**Veredito: MORDE — com ressalvas.** O run-set não é vácuo (19 arquivos · 51 tests · **91 assertions**
· 0 skipped) e contém um sobrevivente com histórico vermelho (`EstoqueInicialContratoTest`, quebrou
em 27 e 29/07). Mas o delta é grande e fica registrado:

| run no main | mecanismo | arquivos | assertions | failed |
|---|---|---|---|---|
| 08-04 | allowlist inline | 25 | **213** | 11 |
| 08-07 pós-merge | árvore−quarentena | 19 | **91** | 0 |

E a ressalva que **não** foi consertada: sob o mecanismo novo há poucos runs, todos verdes —
população insuficiente pra afirmar que **ele** morde. O que morde hoje é o run-set herdado.
(É a lição do `drift-sentinel`: olhar a distribuição antes de confiar no medidor.)

Ele também mediu que a lane é **skip-as-pass em 55 de 60 PR runs** (8,3% executam Pest) — desenho
declarado, não defeito escondido, mas calibra quanto ela cobre.

## 4. O conserto (#5399) — os 3 achados acionáveis

Verifiquei os três por conta própria antes de aceitar.

**(a) O reporter não enxergava NENHUMA das 45 linhas de quarentena.**
`test-lane-coverage.mjs` tinha **duas** leituras do formato `.list`, ambas `trim()` +
`startsWith('#')` — remove a linha inteira de comentário e deixa o **inline** colado no path.
As duas listas exigem motivo por linha (é o desenho):

```
regra ANTIGA: 45 entradas · casam path real =  0
regra NOVA  : 45 entradas · casam path real = 45
```

45 decisões conscientes eram lidas como **órfãs** — apagando a única distinção que o script existe
pra mostrar. `orfaos_totais` 956 → 936 (os outros 25 já estavam cobertos por alguma lane, então o
misparse mudava só o rótulo deles). Conserto: **uma regra só** (`entradasDeLista`), com paridade
explícita ao `sed` das lanes. Selftest **19 → 26**, com bite do inline, 2 controles negativos e um
ponta-a-ponta provando que a entrada parseada **casa** o path.

**(b) `push` ≠ `paths-filter` interno**, ao contrário do irmão Financeiro, onde os dois são idênticos.
Merge que tocasse só `tests/Feature/Produto/**` não disparava a lane no main — e é onde mora 100% da
dívida quarentenada. E a assimetria **se reproduz sozinha**: o [#5378](https://github.com/wagnerra23/oimpresso.com/pull/5378),
mergeado às 17:38Z, recriou o buraco com mais 2 entradas. Agora: 0 só-no-filtro, 0 só-no-push.

**(c) Errata na própria lista.** O cabeçalho dizia *"não rodavam em lane NENHUMA"* / *"nunca foram
executados"*. O basename-grep cobre **lane de PR**; não enxerga a nightly do CT 100, que seleciona
por **diretório**. O próprio `test-lane-coverage.mjs` avisa em prosa que chamar isso de "não roda"
*"mediria a fonte errada"*. Corrigido pra **"nenhum gate de merge os executa"**, com a errata
registrada — não apagada.

**Não entrou:** o 4º achado — nada limita o crescimento da quarentena
(`git grep -l 'estoque-pest-quarantine' origin/main` → **n=2**, sem baseline nem catraca). Catraca
sobre tamanho de quarentena exige **FP medido antes**, e o §5 tem 4 lápides de guard sintático que
reprovava o legítimo. Decisão [W].

## 5. Erros meus nesta sessão

- **LC-08 (ocorrência 58):** esperei um run de CI passando um id que **nunca medi**
  (`gh run watch 31192226527`, inventado). O `gh` errou em silêncio e eu reportei "aguardei" tendo
  esperado zero. Corrigido com o id real, medido por `gh run list --json databaseId`.
- **Near-miss irmão, não publicado:** `git show "origin/main:...$f.yml"` sem `MSYS_NO_PATHCONV=1`
  → MSYS mangleia o `:` → o loop devolveu *"0 arquivos · 0 na allowlist · quarentena NAO existe"*
  pros dois módulos, tudo falso. Peguei porque o número **contradizia o que eu já sabia**, não
  porque medi. (§5 2026-07-31 / 08-01.)
- **Processo:** troquei de branch (`git checkout -B`) no **mesmo worktree** onde o agente
  adversário estava lendo. Ele contornou medindo tudo contra `origin/main` via `git show`, mas foi
  disciplina dele, não desenho meu. Agente de leitura e parent que troca de branch não deviam
  dividir worktree. **1ª ocorrência — conserta, não codifica** (ADR 0344).
- **Sintaxe:** `*/` dentro de bloco `/** */` (o `sed` citado no docblock) fechou o comentário cedo.
  Pego por `node --check`, não por leitura.

## 6. Estado final (recibos)

```
LANE ESTOQUE NO MAIN: success · 888db02a6c4 → 0c3146e0941 → df303aa7346   (3 failure antes)
reporter de quarentena:  0/45 → 45/45 entradas casando path real
órfãos:                  956 → 936
selftest:                19 → 26
push × paths-filter:     0 só-no-filtro · 0 só-no-push
```

PRs: [#5387](https://github.com/wagnerra23/oimpresso.com/pull/5387) `888db02a6c4` ·
[#5395](https://github.com/wagnerra23/oimpresso.com/pull/5395) `f8794b9d3c4` ·
[#5399](https://github.com/wagnerra23/oimpresso.com/pull/5399) `df303aa7346`.

## 7. O que fica aberto

- `US-COM-022` / `US-PONTO-014` — as 2 lanes required ainda vermelhas.
- Os 6 contratos do bloco A na quarentena do Estoque; 2 são cross-tenant Tier 0, 1 é eixo ESTOQUE,
  1 é eixo VALOR — os 4 são decisão [W] por regra-mestre.
- Os 14 `Wave2*` de status desconhecido — precisam ser rodados um a um.
- Achado 1 do adversário: nada limita o crescimento da quarentena.
- A ressalva do mecanismo novo: poucos runs, todos verdes — sem recibo de mordida ainda.
