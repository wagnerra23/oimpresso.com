---
date: "2026-09-13"
topic: "ds-atomos thread 01 — ui/card ganha badge/note/flush sob a lei ADITIVO OU NADA, com guarda que morde"
authors: ["C"]
prs: [7253]
outcomes:
  - "ui/card.tsx: 3 props opcionais (badge · note · flush); default byte-idêntico a 733033864088"
  - "tests/js/card-anatomia.test.tsx: guarda dos 7 defaults + controle negativo do comparador (13 asserts)"
  - "card-anatomia-gate.yml (advisory, promote_by 2026-09-27) + entry no gates-registry — spec sem lane nunca executa"
  - "raio de explosão CONTADO: 139 de 139 consumidores de ui/card (o playbook declarava um piso parcial)"
  - "_saida-01.md ficou SEM ENDEREÇO no repo — decisão [W] em aberto, bloqueia o placar-indice das threads 02/03"
---

# ds-atomos · thread 01 — a anatomia do Widget entra no primitivo, não na tela

## TL;DR

O protótipo do Ponto monta um Widget com três slots que o `ui/card` não tem. Mexer nas 21 Pages
do Ponto seria pintar 21 vezes por cima do mesmo primitivo; mexer no primitivo é um PR e as telas
herdam. As três props entram **opcionais** e, ausentes, não emitem nó nem classe — e isso não é
promessa, é um teste que congela o markup dos 7 defaults contra o sha `733033864088` e que foi
visto **falhar** em duas mutações antes de ser visto passar.
[PR #7253](https://github.com/wagnerra23/oimpresso.com/pull/7253).

## Contexto

Thread **01** do playbook `ds-atomos`, gerado pelo lado design (Cowork) em 2026-09-09 sobre a base
`2b4a3ec3b48a`. O playbook não está no working tree — foi removido do `main` pela
[#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) (commit `4f51a9ec78`, ADR 0397 D5) — e
foi recuperado por `git show 4f51a9ec78^:prototipo-ui/design-docs/cowork-inbox/ds-atomos/playbook/...`.

Os caminhos do playbook são **pré-#7224** e foram traduzidos antes de qualquer leitura: o alvo
mudou de `prototipo-ui/cowork/` para `prototipo-ui/cowork/Wagner/`, os contratos e o
`component-registry.json` migraram para `governance/design/`. `${UI}` e `${SH}` seguem válidos.

## O que foi medido antes de escrever

| pergunta | resposta | como |
|---|---|---|
| a âncora do playbook ainda vale? | **sim, sem remedir** | blob de `card.tsx` em `origin/main` = `73303386408800a84a2363ce0c01bb5c20e455f3`, **1.987 B**. O parágrafo Frescor do índice manda remedir se o sha divergir; não divergiu. |
| quem consome o `Card`? | **139 de 139** | `git grep -l "ui/card"` (índice inteiro, sem cegueira de dotfile). Ponto 21 · Whatsapp 17 · Financeiro 16 · Essentials 15 · governance 9 · Forja 7 · Superadmin 6 · +19 áreas. |
| alguma sessão irmã no alvo? | **não** | `gh pr list --state open` cruzado com `card.tsx`/`KpiCard`/`Toolbar` = 0 de 9. `git log --remotes="origin/claude/*" --not origin/main --since=3.days -- card.tsx` = vazio. |
| algum lane roda a suíte vitest inteira? | **nenhum** | 63 specs vitest · 46 citados em workflow · **17 órfãos**. |

## Decisões tomadas nesta sessão (e por quê)

**A repartição das props.** O playbook deixou explícito que a distribuição era minha, desde que a
guarda passasse. Ficou `Card{flush}` · `CardHeader{note}` · `CardTitle{badge}`. O `note` **não**
podia morar no `CardTitle`: o diagnóstico do playbook é exatamente que frase de apoio e contagem
disputam o `h3` e o truncam — pôr o `note` dentro dele repetiria o defeito com outro nome.

**`flush` por variante arbitrária no pai.** `[&_[data-slot=card-content]]:px-0` mantém a assinatura
do `CardContent` intacta (ele continua `px-6`, e o teste compara byte-a-byte **com `flush` ligado**).
O idioma não foi inventado: `command.tsx` já usa `[&_[cmdk-group-heading]]:px-2`, a mesma forma
(descendente + seletor de atributo aninhado), e o repo já usa `data-*=valor` dentro de colchete.
Compilar era premissa verificável, e foi verificada por precedente no próprio repo.

**Uma lane nova, e não estender um dono.** A regra do projeto é estender o dono do tema. Medi: o
único workflow cujo `paths:` já casa `resources/js/Components/ui/**` é o `a11y-axe-gate`, e o tema
dele é axe/ARIA. Como nenhuma lane roda `vitest run` sem argumento, um spec novo sem lane seria o
18º órfão — verde por não-execução, que parece cobertura (LC-13). A lane nasce no formato dos dois
irmãos que já existem para isto (`status-badge-fidelity-gate`, `pageheader-tabs-fidelity-gate`):
guarda de fidelidade **por primitivo do DS**, advisory com `promote_by`, per ADR 0298.

## A guarda foi vista falhar antes de ser vista passar

Sandbox isolado no CT 100 (`/tmp/ds-atomos-01`), `node_modules` do `oimpresso-staging` por symlink —
**sem escrever no checkout do staging**, que carrega trabalho não-commitado de outra sessão.

```
baseline ...................................... 13 passed (13)
M1  py-6 -> py-4 no default do Card ........... 1 failed | 12 passed
M2  note sempre renderizando (nao-aditivo) .... 3 failed | 10 passed
restaurado .................................... 13 passed (13)
```

O `tsc` recebeu o mesmo tratamento: erro injetado no `card.tsx` → `TS2322` reportado; restaurado →
silêncio. A primeira tentativa de controle positivo **não errou** — eu tinha mutado a *assinatura*
num sandbox sem chamadores, então não havia como errar. O controle só vale quando a mutação produz
o erro no arquivo que a sonda de fato vê.

## Tropeços do caminho (ferramenta, não código)

Três lápides do §5 morderam nesta sessão, e as três já estavam escritas:

- **`/tmp` não é o mesmo diretório para o Bash e para o Node no Windows** (§5 2026-08-21): um
  `gh pr list > /tmp/prs.json` seguido de leitura pelo Node devolveu um arquivo **de maio**, com
  outros campos. Resolvido movendo para o scratchpad da sessão.
- **MSYS mangleia `<ref>:<path>` quando o path começa com ponto** (§5 2026-08-23): um `git rev-parse`
  de `origin/main:` + path sob `.claude/` devolveu a string mangleada como se fosse um sha, e eu
  cheguei a imprimir um "DIFERE" falso sobre dois arquivos idênticos. Errata publicada no mesmo
  turno, com `MSYS_NO_PATHCONV=1`.
- **`cmd || echo` mente quando o cmd nem roda** (§5 2026-07-17): `grep -P` falhou por locale e o
  fallback imprimiu "(limpo)" sobre uma varredura que não aconteceu. Refeito em Node.

Nenhum dos três chegou a PR. Ficam registrados porque o valor deles está em terem sido pegos, e
porque a lápide só paga se alguém disser que ela pagou.

## O que este PR NÃO afirma

- **Nada aqui diz "igual ao protótipo".** jsdom não calcula layout: "não trunca", "quebra em 2
  linhas" e "sangra até a borda" são assertados **estruturalmente**. Pixel é o T7 do
  `PROTOCOLO-COMPARACAO-RUNTIME` e não roda daqui.
- **O contraste do badge não foi medido.** O parágrafo Defeitos do playbook corrigiu `--text-mute`
  (3,18, reprova AA) para `--text-dim` (5,49) **no CSS do protótipo**. Aqui o token é
  `text-muted-foreground`, sancionado nominalmente pela regra B e já usado pelo `CardDescription` —
  mas o mapeamento token-do-app ↔ var-do-protótipo **não foi medido**, e medi-lo seria tocar CSS,
  fora do prefixo autorizado.
- **Adoção nas telas é outra onda.** Este PR entrega o primitivo, não o consumo.

## Pendência que é decisão [W], não conserto meu

O playbook manda escrever o recibo em `_saida-01.md`, derivado pelo `placar-indice.mjs`. **Esse
endereço não existe mais**: o `cowork-ssot-guard` R3 só admite `.md` flat em
`prototipo-ui/cowork/<dono>/handoffs/`, e a `design-docs/` inteira saiu na #7224. O recibo desta
thread mora no corpo do [PR #7253](https://github.com/wagnerra23/oimpresso.com/pull/7253) e aqui.

Enquanto não houver endereço, **o `placar-indice.mjs` não tem de onde derivar o placar** — e as
threads 02 (`shared/KpiCard.tsx` com `variant="filter"`) e 03 (`shared/Toolbar.tsx`, criar) vão
esbarrar no mesmo vão. Onde o `_saida-NN.md` passa a morar é decisão do [W].

Segue aberto do próprio playbook, e não afeta a 01: **`D-KPI-LABEL`** (o canon do label do
KPI-filtro — accent 13.3px/400 do bundle × 11px/600 uppercase da ADR 0110) e **`D-GRADE`**
(DataGrid no cliente × paginator no servidor), que bloqueia a thread 04.
