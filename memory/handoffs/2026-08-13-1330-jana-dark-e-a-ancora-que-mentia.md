---
date: "2026-08-13"
time: "13:30 BRT"
slug: jana-dark-e-a-ancora-que-mentia
tldr: "Paridade de tema escuro no Painel /ia entregou o que pedia, mas o achado grande foi a ÂNCORA: o protótipo que o ancora.mjs carimbava com ✓ tinha 3 defeitos medidos. Somaram 3 PRs — o de tema, o crash do PAGES_NS 1:N (cedido a um PR paralelo melhor) e a data fabricada por clone raso."
prs: [5719, 5727]
decided_by: [W]
next_steps:
  - "[W] apagar a pasta prototipo-ui/ no projeto Cowork (10 arquivos de eco; a raiz é a fonte e fica)"
  - "Conferir amanhã 09:30Z: mv-metabolismo verde + last_commit com VÁRIAS datas distintas (uma só = fetch-depth não pegou)"
  - "Espelho prototipo-ui/cowork/ no git está ATRASADO vs Cowork vivo — dívida real, não medida por ninguém"
---

# Jana no escuro — e a âncora que carimbava ✓ mentindo

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO** em COPI
- `my-work` → **8 tasks** em REVIEW (US-TR-305/306/309/310/311, US-PROD-027, US-INFRA-023/048) — nenhuma tocada aqui
- handoffs irmãos de hoje: `0750-smoke-real-pages-no-modulo-dono`, `1230-ct100-atualizado`

## O que aconteceu

Entrou um pedido [CC] de paridade de tema escuro no Painel `/ia`. A medição confirmou a tese dele
— **duas famílias de token colidem no escuro**, e `--accent` significa **duas coisas na mesma
página**: o roxo da marca no cockpit (`oklch(.55 .15 295)`) e um **cinza de hover** no Tailwind
(`--color-accent: oklch(.235 .010 240)`). Todo `hover:bg-accent` escrito pensando "accent = roxo"
entrega cinza.

Sete itens locais entraram; o `#7`/`#8` (token global) [W] adiou pra PR próprio.

**O achado maior não estava no pedido.** [W] apontou *"está ancorado em versão antiga da jana"* e
tinha razão: a âncora oficial (`jana-merge.jsx`) carrega **3 defeitos medidos** — 6 serviços que
não existem no repo, `frota` 8× e `caçamba` 7× (termos que o `dominio-gate` proíbe), e a regra de
contraste pré-AA. O `ancora.mjs` devolvia `✓` e calava sobre todos.

Dois defeitos de governança apareceram investigando o CI vermelho: o cron `mv-metabolismo`
quebrado por `PAGES_NS` 1:N, e — pior, calado — `last_commit` gravando **a data da run** em vez da
data do arquivo, porque o checkout é raso.

## Artefatos gerados

| PR | o que entrou |
|---|---|
| [#5719](https://github.com/wagnerra23/oimpresso.com/pull/5719) | 7 itens de tema escuro em `JanaCockpit` + 5 em `Index.tsx` (6 violações de lint → **0**) · charter **v5** (revoga a prescrição de cor do "Demo polish v2") · `ancora.mjs` passa a acusar âncora defeituosa |
| [#5727](https://github.com/wagnerra23/oimpresso.com/pull/5727) | `fetch-depth: 0` no `mv-metabolismo` + guard `gitLastDate` (reusa `isShallowHistory` do `sdd-scorecard`) + 3 bite-tests |
| [#5728](https://github.com/wagnerra23/oimpresso.com/pull/5728) | **não é meu** — PR paralelo, melhor; cedi a metade duplicada |

## Persistência

- **git:** 3 PRs mergeados no `main` (5719 `c96808bdc8d`, 5727, 5728)
- **MCP:** nenhuma task tocada — o trabalho nasceu de pedido [CC], não de US
- **BRIEFING:** não atualizado — mudança de tema/gate, não de capacidade de módulo

## Próximos passos pra retomar

```bash
node prototipo-ui/ancora.mjs Jana/Index    # agora acusa os 6 símbolos fantasma
```

## Lições catalogadas

**LC-19 (máquina paralela):** abri o #5727 sem rodar `whats-active`. O #5728 já existia, feito por
quem causou o bug, e era mais completo — achou **15** máquinas e **2** consumidores, e pegou a
metade *silenciosa* que me escapou (`vitalByNs.get(<array>)` devolve `undefined` sem erro →
`PaymentGateway` publicado como "sem telas" tendo 2; Whatsapp 0→11, Forja 0→17). Quem pegou foi o
`dup-detector`, não eu. Cedi e reescopei.

**LC-08 (afirmar da fonte errada):** disse duas vezes — a [W] e no corpo do PR — que o `watchdog
G6` era **required** e travava todo PR do repo. **Não é.** Não está no baseline nem na união
classic+rulesets (44 contexts). Deduzi de "watchdog cobra run verde" e vendi como fato. Errata
registrada no corpo do #5727.

**LC-15 (escape anunciado que não existe):** `cron-watchdog.mjs:580` oferece *"ou registre por que
o vermelho é esperado"* — não há allowlist, arquivo de ack nem env. Só o texto. Não mexi: dar valve
a vigia de governança é política, decisão [W].

**Medição furada 5×, todas pegas antes de virar dano:** `innerText` (depende de layout) lido como
"não renderizou" · `oklch()` parseado como RGB (contraste 1.97 falso; o controle positivo
branco-sobre-preto = 21 salvou) · `grep -c` em CSS minificado de 1 linha · `rc` vindo do `head` em
vez do `grep` · `check-runs?per_page=100` num PR de **119** checks, anunciando "6 required
ausentes" que estavam na página 2. **O padrão:** consulto o oráculo quando me sinto inseguro e
pulo quando me sinto confiante. O gatilho é a certeza, não a ignorância.

## Pointers detalhados

- Session log: [`memory/sessions/2026-08-13-jana-dark-ancora-defeituosa.md`](../sessions/2026-08-13-jana-dark-ancora-defeituosa.md)
- Charter: [`resources/js/Pages/Jana/Index.charter.md`](../../resources/js/Pages/Jana/Index.charter.md) v5
- Âncora: `node prototipo-ui/ancora.mjs Jana/Index`
