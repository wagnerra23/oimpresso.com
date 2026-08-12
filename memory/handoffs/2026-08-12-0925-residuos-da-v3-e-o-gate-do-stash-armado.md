---
date: "2026-08-12"
slug: residuos-da-v3-e-o-gate-do-stash-armado
hour: "09:25 BRT (12:25 UTC)"
topic: "Os 2 resíduos da V3 fechados, e o gate do `stash pop` armado na 2ª ocorrência"
authors: [C, W]
prs: [5656]
us: [US-SELL-058, US-GOV-052]
tldr: "#5656 mergeado. Removidas 17 classes que o `.cw-input` ignora (provado inócuo por medição) e armado o `avisoStashPop` no hook que já existia, na 2ª ocorrência do LC-12. O item 2 (alinhar o `.cw-input` GLOBAL) foi verificado e NÃO estava feito — segue decisão de produto."
outcomes:
  - "#5656 MERGED — itens 1 e 3 dos resíduos do #5613"
  - "LC-12 sai de `Gate: none` — `avisoStashPop` armado, bite-test real no main"
  - "Item 2 verificado em origin/main: NÃO foi feito (.cw-input global segue 30px)"
  - "Achado do pr-critic refutado com evidência: contrato de outra tela + premissa medida como falsa"
---

# Os 2 resíduos da V3, e o gate do `stash pop`

Continuação de [17:43](2026-08-11-1743-venda-v3-mergeada-e-o-smoke-que-nao-fechou.md)
e [18:16](2026-08-11-1816-venda-v3-o-smoke-fechou-r1-cumprido.md). [W] pediu os
resíduos **1 e 3** e perguntou se o **2** já estava feito.

## Item 2 — a pergunta, respondida por medição

**Não estava feito.** Medido em `origin/main` fresco (meu checkout estava 5
commits atrás — o guard avisou e eu não validei pelo working tree): o
`.cw-input` global segue `height:30px`, `font-size:12.5px`, `radius:5px`, e o
último commit no arquivo é antigo (`10944f406ad`, sobre a lupa da busca).

A confusão provável é minha herança do #5613: eu **escopei**
`.venda-v3 .cw-input` para 34,19px — muda a V3 e só ela. O item 2 é mudar o
**global**, ~120 telas, e continua sendo decisão de produto.

## Item 1 — 17 classes que não faziam nada

`h-8`/`text-[12.5px]` em `<Input>`/`<SelectTrigger>`. **Prova de inocuidade**,
medida na prod que já tinha o CSS deployado:

```
cw-input h-8 text-[12.5px]  ->  34,19px f13
cw-input                    ->  34,19px f13     IDÊNTICO
```

Escopo delimitado por **medição, não por texto**: o inventário separou 17
inertes de **1 ATIVO** — `ComissaoDrawer:215`, um `<span>` onde `h-8` funciona.

**E esse controle achou defeito MEU.** Aquele span é um dos 3 ramos do MESMO
`<Campo>`, alternando com dois `<Input>`. Como os inputs foram a 34,19px no
#5613, ele ficou **2,19px mais baixo**. Recebeu a caixa dos irmãos (não altura
fixa, que descolaria de novo). Junto: o `<textarea>` CRU de `ItemDetalhe:351`
não passa por `.cw-input`, ali as classes VALEM — recebeu a caixa do Textarea do
DS vivo na mão.

⚠️ **A 1ª tentativa do sweep foi REVERTIDA.** Um `.replace(/  +/g,' ')` colapsou
espaços e **destruiu a indentação** de toda tag multi-linha, além de esvaziar
classNames e tocar 2 tags que não eram alvo. É a **LC-16**. Quem pegou foi o
teste de identidade no diff — nada foi commitado. Refeito linha a linha, sem
tocar espaço fora do valor da className.

## Item 3 — o gate do `stash pop`, armado

2ª ocorrência do LC-12 (a 1ª em 07-27, a 2ª ontem, medindo se um vermelho era
meu). ADR 0344 two-strikes ⇒ vira defesa. O par candidato **já vinha medido** na
lápide; isto o arma na forma que ela prescreveu.

**Estendeu o dono** (`block-destructive.mjs`, que já intercepta comando
destrutivo e já está wirado) — não hook novo. LC-19 está no alarme com 3
ocorrências; não somei a 4ª.

**Advisory por decisão medida, não por timidez:** `stash push` em A →
`checkout B` → `pop` é legítimo e tem topo de outra branch sempre. Bloquear
puniria o uso correto — a doença dos guards sintáticos que o §5 matou 5×.

Provas: **55 asserts** (4 BITE + 8 controles negativos) · **2 mutações** que
derrubam o teste (remover a perna "topo é seu" → falha; aviso virar `exit 2` →
falha o E2E "jamais bloqueia") · **E2E contra a pilha real**. Verificado no main
pós-merge: ele avisa, nomeia `forja-trabalho-6a`, e sai **0**.

⚠️ **População medida** (653 transcripts, só `tool_use`): **84** `pop|apply`,
**79 (94%) sem entry explícita**. Isso é a população do **gatilho**, não FP — o
filtro "topo de outra branch" estreita e **não é medível retroativamente**.
Declarado, não estimado.

## O achado do `pr-critic` — refutado com evidência

Ele apontou que remover `h-8` quebraria *"8 campos sempre visíveis"*. Falso por
duas razões independentes, ambas verificadas contra `origin/main`:

1. **Contrato de outra tela.** Cruzou o diff com `Sells/Create.charter.md` — a
   tela **viva** (ROTA LIVRE). `ComissaoDrawer` é importado **só** por
   `CreateV3.tsx`; zero referências em `Create.tsx`. O critic declara esse limite
   ("contexto zero").
2. **Premissa medida como falsa.** `h-8` não "garantia altura": era ignorado.

**Crédito onde é devido:** ele olhou o **arquivo certo**. Havia defeito de altura
no `ComissaoDrawer` — o **oposto** do apontado (o `<span>`). Advisory de contexto
zero apontando o arquivo onde de fato havia problema é sinal mal calibrado, não
ruído. Respondido no PR, sem push de fix.

## O loop do `preflight`, e um número meu que estava errado

**8 reconciliações** nesta sessão pelo mesmo motivo: o gate exige descendência
estrita de `origin/main`, e o repo merga muito. Eu sincronizava, o gate rodava
minutos depois, o main já tinha andado.

⚠️ **Errei um número e corrijo aqui.** Afirmei que a corrida era
*"aritmeticamente impossível"* apoiado em "~50min de CI" — número que **nunca
medi**, herdei de impressão. Medido: **14 min** para os 118 checks concluídos,
contra **6 commits/hora** no main. Apertado, não impossível. A conclusão que tirei
dali estava errada; é LC-08 e fica registrado.

O que resolveu no #5636 foi **auto-merge** (o GitHub fecha no instante em que os
required passam). Aqui não liguei — merge é soberania [W] — e o #5656 acabou
mergeado por [W] às `12:17:51Z`.

Corolário barato pro próximo: **evento de CI pode estar obsoleto**. Duas vezes
reagi a um alerta que já tinha sido resolvido pelo push anterior; medir antes de
consertar evitou trabalho fantasma.

## Estado

- `main` tem tudo: `03fcd6151e0` (#5656). Verificado por conteúdo, não pelo
  state do PR: `avisoStashPop` no hook · LC-12 com gate · span realinhado.
- **Aberto, e é o único trabalho real que sobra:** o **item 2** — alinhar o
  `.cw-input` global de 30 → 34,19px. Atinge ~120 telas, incluindo o drawer
  Cliente que a Larissa opera. Decisão de produto.
- Dívida alheia que segue vermelha em qualquer PR: `governance/adr-alias-map.json`
  parado há 61d (advisory, provado pré-existente contra o `main` puro).

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `sessions-recent limit:3` → as 3 mais recentes indexadas são de **2026-08-05**
  (`maquinas-que-existiam-e-nao-avisavam`, `duplicacao-roadmap-forja`,
  `plano-documentacao-tecnica-operacional`) — o índice do MCP está **atrás** do
  git, que tem sessões de 08-11. Registrado como observação, não como pendência.
- MCP esteve **fora** durante quase toda a sessão anterior; voltou nesta.
