---
date: "2026-09-08"
hour: "12:00 BRT"
duration: "2.3h"
topic: "Onda 7 · paridade protótipo↔produção no Financeiro + RecurringBilling: vincular, medir no runtime e corrigir os 2 defeitos reais"
authors: ["C"]
outcomes:
  - "6 vínculos de paridade + 2 âncoras n/a declaradas (parityLinked 18→24 no meu escopo; 66 no main com as sessões irmãs)"
  - "5 telas medidas no runtime com sonda canônica, mesmo tema e mesma viewport"
  - "2 defeitos corrigidos: text-align dos KPIs do Unificado e 518 cores cruas do RecurringBilling"
  - "12 suspeitas → 2 defeitos reais; 3 das 10 descartadas foram erro próprio de medição (LC-08, +1 no ledger)"
prs: [6975, 6982, 6989, 6993]
us: []
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0190-pageheader-primary-universal-roxo", "0236-extrato-conciliacao-modelo-unificado"]
---

# 2026-09-08 — Onda 7: o que a medição achou, e o que ela desmentiu

## TL;DR

Escopo Financeiro + RecurringBilling fechado em 4 PRs mergeados. O resultado que vale registrar
não é a cobertura — é a **razão de acerto**: **12 suspeitas levantadas, 2 defeitos reais**.

## O que foi feito

**Vínculo por conteúdo, não por nome.** Os 9 inventários do escopo foram mapeados lendo
frontmatter/título/`inertia_target`. Isso mordeu no `index-visual-comparison.md`, que pelo nome
seria do `Financeiro/Index` e pelo conteúdo é da **Conciliação**. Casar por nome teria vinculado
a tela errada — que é exatamente o guard sintático da lápide §5 2026-06-30.

**Dois órfãos ficaram órfãos, de propósito.** `boletos-*` descreve uma tela **deletada** (#1142,
`/boletos` → 301 → `/financeiro/cobranca`); o sucessor tem âncora e doc próprios, e vincular
seria forçar por tema. `unificado-3-lentes-*` disputa alvo com o inventário vivo, e o parser
(`vinculoDoCharter`) aceita **um** vínculo por charter.

**Duas silenciosas resolvidas sem inventar âncora.** `Planos/Create` e `/Edit` receberam
`n/a (herda PT-02)`. Recusei promover `cobranca-recorrente-page.jsx`: o cabeçalho dele se declara
porte reverso (*"Reescreve a RecurringBilling do git"*) e, no design **vivo** baixado na sessão, a
aba Planos é um `Placeholder` que diz *"Espelha /recurring-billing/planos do git"*. Promovê-lo
ancoraria a tela nela mesma (§5 2026-08-28 + 2026-06-05).

**Medição no runtime.** Mesma sonda (`design-diff --probe`) nos dois lados, `data-theme=dark`
medido nos dois, viewport idêntica medida nos dois, ambos estabilizados por duas leituras iguais
de `querySelectorAll('*')`, e a âncora provada **SYNC** contra o Cowork vivo antes de comparar.

| Tela | Veredito |
|---|---|
| DRE | paridade boa — h1 idêntico, mesma estrutura de colunas; prod tem `12m` a mais |
| Conciliação | divergência que o **charter já declarava** (evoluiu além do protótipo, ADR 0236) |
| Unificado | 1 defeito: `text-align: center` nos 5 KPIs |
| Fluxo | estrutural (4 KPIs strip × 3 cards) — papéis não 1:1, `--compare` não foi rodado |
| RecurringBilling | 1 defeito: 12 `bg-white` hardcoded em tema dark |

## O que a verificação desmentiu (o valor real do passo 7)

Dez suspeitas caíram. Quatro eram **artefato de índice** (o comparador casa coluna por índice, e o
`<thead>` está deslocado em 1 — logo comparava colunas diferentes). Três eram **comportamento já
documentado**: o `30d × 35 dias` do Fluxo está no charter; a prod **tem** Balanço/Balancete (meu
seletor pegou a subnav); a Conciliação tem divergência declarada.

E **três eram erro meu de medição** — todas LC-08, todas na mesma família:

1. Li `getComputedStyle(card).color` — a cor **herdada no container**, não a renderizada. Anunciei
   "0.94 sobre branco = ilegível"; os textos-folha mediam 19-20:1.
2. Medi o fundo do card **ancestral** ignorando `background-image`, e acusei "53 avatares reprovam
   AA". Cada avatar tem `linear-gradient` próprio.
3. Minha função de luminância fazia match de dígitos e lia `oklch(0.965 0.004 240)` como
   `rgb(0.965, 0.004, 240)`. Com isso **publiquei ao [W]** que "447 de 500 (89%) ficariam
   ilegíveis" e classifiquei a onda como decisão de produto por causa desse número. Refeito com o
   browser convertendo a cor e **controle positivo** (preto/branco = 20.8, esperado 21): baseline e
   tokenizado dão **idêntico** — 425 passam AA, 27 reprovam, pior 1.00. O ganho da onda nunca foi
   legibilidade; é a tela deixar de ser ilha clara no app escuro.

A quarta veio ao **registrar** a terceira: o script do ledger pegou o primeiro campo `Ocorrências`
do arquivo (de outra classe) em vez do campo dentro do bloco `## LC-08`. Revertido e refeito com o
bloco delimitado. Cometer a classe dentro do registro dela é o tipo de coisa que só aparece se a
gente contar depois — e por isso está aqui.

## O que os gates ensinaram

- **`charter-us-lint`** passou local e falhou no CI: rodei sem `BASE_REF`, o CI roda com. Um
  script com N modos é N gates (§5 2026-07-28).
- **`CSS size ratchet`**: rebaseline é o caminho que o próprio gate prescreve; o diff ficou
  cirúrgico e nenhum dos outros 38 arquivos entrou de carona.
- **`casos-gate` G-6**: tocar `.tsx` acorda o stale mesmo com mudança inerte — o gate mede data
  (§5 2026-07-27). Revalidei por análise com grep contado nos 14 testes citados (0 asserts de
  aparência, controle positivo de 8-33 asserts por arquivo) e provei que as cores de **status**
  ficaram intactas, mantendo o UC-RBSUB-07 válido.

## Fica aberto

Classificação da divergência estrutural do Fluxo e do `<thead>` deslocado (julgamento [W]);
handoff pro Cowork da deriva `--accent 0.70` × `0.55` canon (a **prod** está certa); os 27 textos
pré-existentes que reprovam AA; ProvaViva e Caixa não medidas; e o frescor do
`cobranca-recorrente-page.jsx`, sem prova por hash porque volta inline no `get_file`.
