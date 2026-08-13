# Réguas: a evidência que o ledger jogava fora — e o denominador que ninguém declarava

**TL;DR:** três correções de fidelidade de escrita na grade de réguas ([#5619](https://github.com/wagnerra23/oimpresso.com/pull/5619), mergeado `c380d92886b`): a evidência passa a ser gravada inteira (200→700 chars medido), o `incremento` do veredito de integração chega ao ledger (era 0 de 51 claims) e o caveat de denominador vira derivado. Três premissas do pedido estavam erradas e foram corrigidas medindo; o bônus foi descartado porque outra sessão já o fizera melhor.

---

## O problema, medido antes de tocar

O pedido trazia números. Não herdei nenhum — cada um foi remedido, e três não bateram.

| Premissa do pedido | Medido | Veredito |
|---|---|---|
| corte veio do delta (250) | rodada 08-11 foi **`full-parcial`** ⇒ cap **200** | corrigido |
| "7,7 com **3** fraquezas" (08-08) | **2** com data 08-08 no ledger | corrigido |
| interseção de ids = 1 | **vazia (0)** | corrigido |
| 8 entradas cortadas em 08-11 | 202,203,203,203,203,205,210,249 — todas no meio da palavra | ✅ |
| 13 fraquezas na dimensão, média 7,4 | 13, média 7,42 | ✅ |
| 0 claims com `incremento` | **0 de 51** | ✅ |

A distribuição decidiu o desenho: `max=335 · p50=139 · p90=235` (n=63), com **11 evidências empilhadas em 195–215** — a assinatura do corte. Por isso o teto novo (2000) é ~6× o maior valor real: não morde na prática, e **loga** se morder.

## O que entrou

1. **`evid()`** nos 3 sites de persistência (`:529`, `:551` delta · `:688` full). Os 2 `.slice()` que sobraram são de **contexto** (prompt de re-verificação e prosa), nunca de gravação.
2. **`incremento`** na instrução do `promptClaims` — o dado já viajava no payload, faltava mandar gravar.
3. **`caveatDenominador`**, função pura. No delta compara ids de verdade (nomeia quem entrou/saiu); no full declara não-comparabilidade, porque ali as fraquezas vêm da pesquisa do dia, não do ledger. Grava `cobertura.denominador` forward-only.

**Limite honesto declarado:** o JS do Workflow não tem filesystem e, no full, as fraquezas não têm id — logo comparar ids com o retrato anterior não é possível hoje nesse modo.

## A prova

Bite-test por mutação, revertendo cada mudança: **M1** 3 falhas · **M2** 3 · **M3** 6 · **M4** 2 — todas `rc=1`, e o arquivo restaurado byte-idêntico (sha256 conferido). Antes→depois com o mesmo dublê: **200 → 700 chars** (perda 71% → 0). Selftest 45 → 93 asserções no main.

## O que eu errei

- **Meu monitor de CI declarou `0 checks, TODOS VERDES`.** Cobri falha e sucesso; esqueci o conjunto vazio. É a lápide §5 2026-07-29 reproduzida na ferramenta montada para vigiar isso. Corrigido para exigir `total > 0`.
- **Selftest saiu `rc=1` com 0 asserções** ao resolver o conflito com o #5634 — o `}` compartilhado deixou um bloco aberto. Crash, não falha: quem lê só "0 falhas" conclui o oposto.
- **Quase editei a árvore de outra sessão** (`D:\oimpresso.com\` em vez do worktree). O tool barrou por eu não ter lido o arquivo — sorte, não mecanismo.
- **Dois comandos falharam devolvendo vazio/zero** (`git show` manglado pelo MSYS; `node -e` com erro de sintaxe) que eu quase li como ausência.
- Escrevi que os marcadores `DENOMINADOR-INI/FIM` serviam a um harness de extração — **medido: zero consumidores**. Corrigi o meu comentário; os dois pré-existentes com a mesma imprecisão ficaram para [W].

## Convivência com as sessões paralelas

Três PRs no mesmo arquivo, no mesmo dia, e nenhum perdeu conteúdo:

- **#5607** (dossiê ← inventário) — fez o meu bônus antes e melhor; **descartei o meu**.
- **#5634** (rubrica da nota) — mergeou primeiro; complementar por construção: ele define como a nota **nasce**, eu o que é **gravado**. `nota_sugerida: null` sai da média pelos filtros `typeof === 'number'` em vez de virar `NaN`.
- **resolução remota de [W]** no GitHub — juntei em vez de sobrescrever; as duas resoluções coincidiram.

Ordem final dos blocos no selftest: `[9]` dossiê · `[10]` evidência · `[11]` incremento · `[12]` caveat · `[13]` rubrica · `[14]` outcome.

## Aberto (decisão [W])

- Proveniência do retrato 08-08 diz "3 fraquezas"; o ledger tem 2 — bookkeeping do campo `data` no upsert.
- Dois comentários pré-existentes afirmam extração por marcadores que não existe.
