---
date: "2026-09-15"
time: "19:15 UTC"
slug: distiller-freshness-uniao-e-floor-zero
tldr: "distiller_freshness passou a somar a data-git do CODIGO a do doc (uniao, nao troca - a troca foi REFUTADA: os dois eixos sao cegos em lugares opostos). Jana e Fiscal destiladas pela maquina e floor de volta a 0, fechando a cadeia de FOLLOW-UP aberta em 2026-08-10. Achado: a triade de defeitos do distiller reapareceu nas DUAS portas isoladas, logo e sistematica dele, nao degradacao de lote."
prs: [7326, 7328, 7330, 7339]
decided_by: [W]
related_adrs: [0291-distiller-modulo-verdade-contrato-emenda-0270-f3, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0344-two-strikes-cobre-processo]
next_steps:
  - "[W] decidir o destino do #7335 (absorve 1->10 citando main=7; main media 1, hoje 0; rebaseado mediria 8, todas causadas por ele) - re-destilar as 8, re-absorver 0->8 com BASELINE-ABSORB, ou separar os toques de memory/requisitos/"
  - "[W] decidir se a triade sistematica do distiller vira nota no PROTOCOLO-REFUTADOR-BACKFILL (o §1 escopa a lote >10 arquivos, mas o defeito nao depende do tamanho do lote)"
  - "Clone do CT 100 em /root/distill-fresh/oimpresso-20260915 segue montado, em main e nao-shallow, sem .env - reaproveitavel"
---

# distiller_freshness soma o CODIGO, e o floor volta a 0

## O que fechou

A proposta do #7302 executada no caminho **(a)**, confirmado por [W] nesta sessao
(*"sim, pode destilar a Jana"*). A metrica deixou de ser cega no eixo do codigo, as duas
portas atrasadas foram destiladas, e a **cadeia de FOLLOW-UP OBRIGATORIO aberta em
2026-08-10 esta fechada**.

| PR | O que |
|---|---|
| [#7326](https://github.com/wagnerra23/oimpresso.com/pull/7326) | destila a porta da **Jana** |
| [#7328](https://github.com/wagnerra23/oimpresso.com/pull/7328) | `distiller_freshness` = doc **OU** codigo (uniao, nao troca) |
| [#7330](https://github.com/wagnerra23/oimpresso.com/pull/7330) | ledger: LC-26 (11a), LC-23 (5a), LC-08 (n+15) |
| [#7339](https://github.com/wagnerra23/oimpresso.com/pull/7339) | destila a **Fiscal** + re-aperta o floor **1 -> 0** |

## Estado MCP no momento do fechamento

Medido em `main` (`8257b6f1337`), **worktree isolado**, repo nao-shallow:

```
distiller_freshness -> value = 0 | stale = 0 | carimbadas = 14 | datadas_por_codigo = 14
baseline floor = 0 (armed: true, direction: down)
Jana e Fiscal: distilled_at "2026-09-15"
```

`gate-selftest` 82/82. Os 46 required nasceram e passaram no head de cada PR (uniao
`classic_protection` U `rulesets`, conferida no head SHA antes de cada merge).

## Por que SOMAR e nao TROCAR (a troca foi REFUTADA por medicao)

Os dois eixos sao cegos em lugares **diferentes** e os flips vao pra lados **opostos**:

- a fonte do **doc** perde a Jana, porque exclui `authority: generated` e o unico doc novo
  dela era justamente o `ARCHITECTURE.md` gerado - **o mesmo arquivo do #5298**, que a
  exclusao foi criada pra silenciar;
- a fonte do **codigo** perderia a Fiscal, cujo evento foi a reescrita de um VEREDITO em
  `fiscal-config-gap.md` (de `**Decidir.**` pra `**FECHADO em 2026-09-04**`) - conhecimento
  novo que mora SO no doc.

**Custo medido, que a proposta nao quantificava:** o doc churna MAIS que o codigo em **13 das
14** portas (30d; Ponto empata 11/11, Jana e outlier 29x18). A uniao somou **1** porta, nao um
multiplicador de ruido. A exclusao `authority: generated` ficou **intacta**, e foi medido que a
uniao nao ressuscita o #5298 (o commit de regeneracao toca so docs; zero sob `Modules/Jana`).

## O que eu NAO fiz, e por que

- **NAO forjei carimbo**: `distilled_at` saiu da maquina nas duas portas. Unico escritor
  legitimo e `DistillerModuloVerdade.php`.
- **NAO usei o caminho (b)**: o baseline nao foi afrouxado. O unico toque nele foi o
  **aperto** 1->0, e o `baseline-tamper-guard` confirma (*"nenhum baseline afrouxado"*), por
  isso nao exige `BASELINE-ABSORB`.
- **NAO toquei o `oimpresso-staging`** (segue em `755f6de79`, 7 sujos de outra sessao).

## Divida DECLARADA que nao e minha

O **#7335** segue aberto absorvendo o floor **1 -> 10** citando `main = 7`. Medido: `main`
media **1** (agora 0), e a branch dele esta **12 commits atras**, sem os merges de hoje.
Rebaseado, ele mediria **8** - e **as 8 sao causadas por ele proprio** (todas 09-06 -> 09-15,
por doc), nao "6 herdadas" como a nota afirma. Comentei la com a medicao e os 3 caminhos
([comentario](https://github.com/wagnerra23/oimpresso.com/pull/7335#issuecomment-5686657194));
**nao toquei na branch**. Os dois PRs alteram a mesma linha, entao o git vai exigir resolucao
humana - **decisao de [W]**.

## Achado que vale alem deste trabalho

A **triade de defeitos do distiller** - `id` derrubado + H1 duplicada + secao "Ultima mudanca"
ATRAS dos proprios eventos - reapareceu nas **duas** portas destiladas isoladamente, e e a
mesma que a `nota_restauracao_2026_09_06` catalogou num lote de 13. Duas portas isoladas
reproduzindo a triade inteira **refuta** a leitura de que era degradacao do lote: **e o
distiller**. Isso importa porque o `PROTOCOLO-REFUTADOR-BACKFILL` §1 escopa a refutacao a
lote >10 arquivos - o defeito nao depende do tamanho do lote. Na Fiscal apareceu um 4o:
a prosa chamava **`biz=4` de "modulo"** (e TENANT - ROTA LIVRE, Larissa). Formalizar no
protocolo e decisao de [W].

## Ledger - 3 classes minhas, registradas no #7330

- **LC-26 (11a)**: a 1a manifestacao em que **as duas verificacoes prescritas sao cegas** - o
  par colapsou dentro de uma classe de caracteres e produziu regex **VALIDA** de semantica
  trocada. Contar barras engana (sobrou uma, plausivel); varrer bytes de controle devolve
  limpo. Quem pegou foi **controle positivo sobre a SAIDA** (`datou N/N`), que virou campo
  permanente `detail.datadas_por_codigo`.
- **LC-23 (5a)**: `git checkout <minha-branch> -- <path>` sem ter commitado apagou a edicao -
  branch sem commit nao guarda nada.
- **LC-08 (n+15)**: rodei o `gate-selftest`, vi **82/82**, e o verde era de uma arvore que nao
  carregava a mudanca. **Regra operativa:** antes de confiar no verde de um gate,
  `grep -c <simbolo-novo>` no arquivo sob teste.

Saneamento junto: 4 sites com dano LC-26 **pre-existente** em canon (uma entrada que
DESCREVIA um path mangleado foi ela mesma mangleada) - `memory/LICOES_CODE.md` agora tem
**zero** bytes de controle.

## Proxima acao

1. **[W]**: decidir o destino do **#7335** (re-destilar as 8 · re-absorver `0 -> 8` com
   `BASELINE-ABSORB` · separar os toques de `memory/requisitos/`).
2. **[W]**: decidir se a triade sistematica do distiller vira nota no
   `PROTOCOLO-REFUTADOR-BACKFILL`.
3. O clone do CT 100 segue montado em `/root/distill-fresh/oimpresso-20260915`, em `main` e
   nao-shallow, **sem `.env`** (esvaziado apos uso) - reaproveitavel pra proxima destilacao.
