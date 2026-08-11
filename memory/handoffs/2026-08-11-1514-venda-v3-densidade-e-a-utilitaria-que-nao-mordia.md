---
date: "2026-08-11"
slug: venda-v3-densidade-e-a-utilitaria-que-nao-mordia
hour: "15:14 BRT"
topic: "Venda V3 — a densidade do campo, e a utilitária Tailwind que não mordia"
authors: [C, W]
prs: []
us: [US-SELL-058]
tldr: "As 3 réguas de campo da V3 (30/32/36px) convergiram nos 34,19px do protótipo. A causa não era a tela — era o `.cw-input` do DS, unlayered, que ignora toda utilitária Tailwind: os 14 `h-8` da tela já eram letra morta. Escopado em `.venda-v3` por decisão [W]. Não commitado em main; PR pendente."
outcomes:
  - "3 réguas de campo (30/32/36px) viraram 1 (34,19px) — 16 campos visíveis medidos"
  - "Provado que `h-8`/`h-9`/`text-[13px]` são IGNORADOS sobre `.cw-input` (controle + e −)"
  - "8 portais da V3 cobertos pelo wrapper, incl. 2 que o merge do main trouxe"
  - "Controle negativo: fora do escopo o DS segue 30px — não vazou pras ~120 telas"
---

# Venda V3 — a densidade do campo, e a utilitária que não mordia

Continuação direta do handoff das [03:00](2026-08-11-0300-venda-v3-seis-ondas-e-a-ancora-fantasma.md)
(as 6 ondas). [W] reportou que `/sells/create-v3` estava *"muito feia comparada com o
protótipo"* e trouxe uma medição prévia dizendo que o eixo era **densidade, não cor**,
com o pedido explícito de **re-medir antes de agir**.

## O que a re-medição achou — e por que ela mudou o trabalho

A tabela prévia estava certa nos números e **errada na causa atribuída**. A hipótese era
*"os componentes do DS foram usados com tamanhos menores, e isso se acumulou nas 6 ondas"*.
Medido em produção, com controle positivo e negativo:

| classe aplicada | altura | veredito |
|---|---|---|
| `.cw-input` | 30px | baseline do DS |
| `.cw-input h-8` | **30px** | utilitária IGNORADA |
| `.cw-input h-9` | **30px** | utilitária IGNORADA |
| `.cw-input text-[13px]` | font **12,5px** | utilitária IGNORADA |
| `h-9` sozinho (controle +) | 36px | a utilitária funciona quando não há `.cw-input` |

A causa é o **`.cw-input`** (`cowork-fields.css`, `height: 30px`), default de ~120 telas pela
[ADR UI-0015](../requisitos/_DesignSystem/adr/ui/0015-padrao-cowork-default-forms.md). Ele mora
**UNLAYERED** (`@import` sem `@layer` em `inertia.css:56`) e, no Tailwind v4, unlayered vence
`@layer utilities` independente de especificidade — o próprio arquivo documenta isso na
linha ~122, a propósito de `pl-9`/`pr-9`.

**O corolário é o achado que paga a sessão:** os 14 `className="h-8 text-[12.5px]"` espalhados
pela V3 **já eram letra morta**. O conserto óbvio — trocar por `h-9` — teria produzido 14
edições que não movem um pixel, com o PR inteiro parecendo um conserto de densidade. Só a
medição com controle pegou isso; leitura de código não pega.

## A tela tinha TRÊS réguas, não uma

Medido em prod (dark, mesma sonda dos dois lados):

| tipo | antes | protótipo |
|---|---|---|
| `<Input>`/`<SelectTrigger>`/`<Textarea>` (26 pontos) | 30px | 34,19px |
| `<input>` cru com `h-8` (grade de itens) | 32px | 34,19px |
| `MoneyInput` com `h-9` (primitivo local) | 36px | 34,19px |
| `h1` (`text-xl`) | 20px | 22px/1.3 + tracking |

O resto do porte **já estava fiel** e não foi tocado: header de seção (`12px 16px`), raio do
cartão (12px), `Lbl` (10,5px / lh 15,75), e o padding da célula editável da tabela
(`px-2 py-1` = os `4px 8px` do protótipo).

## O fork que foi a [W], e por quê

O escopo do pedido era *"ajuste de uso, sem token nem componente novo"*. Medido, **os 26
campos não cabiam nele**: `.cw-input` é DS global, e ajustar só os 17 restantes deixaria a
tela pior (17 a 34,19px ao lado de 26 a 30px). Levei 4 caminhos medidos; [W] escolheu
**escopar em `.venda-v3`** — que é o padrão que o repo já usa (`.fin-cowork`, `.sells-cowork`)
e o que a Fronteira do [charter](../../resources/js/Pages/Sells/CreateV3.charter.md) manda
(*"variação nasce cópia local em `v3/`, nunca edição do original"*).

**Ressalva registrada no cabeçalho do CSS, não escondida:** a camada 4 do protótipo
(`venda.css`) declara *"nenhum tamanho de fonte novo — tudo vem das camadas 1–3"*, e a caixa
do campo é camada 3. O arquivo respeita o canon do REPO e fura a disciplina de camada do
PROTÓTIPO. O conserto na origem seria alinhar o `.cw-input` ao DS de onde ele diz ter sido
portado — atinge ~120 telas, é decisão de produto e **não foi tomada**.

## Onde os números vieram

Não de leitura minha. `controlStyle()` de `components/Input/Input.jsx` no **DS vivo**
(DesignSync `019dd02f`, `truncated:false`) traz `font: '13px/1.4'`, `padding: '7px 10px'`,
`borderRadius: 'var(--radius-md, 6px)'`, `border: 1px` — os mesmos quatro valores que o
`03-padroes-tela/css/campos.css` do espelho versionado. Altura derivada: 13 × 1,4 + 14 + 2 =
**34,2px**. O h1 22px/1.3 veio do `PageHeader.jsx` do mesmo projeto.

## Prova

Injetando o CSS **já compilado** na prod real, com o wrapper aplicado:

```
dentro de .venda-v3  ->  34,19px  f13  r6px   (16 campos, 3 tipos, convergidos)
fora, no <body>      ->  30px     f12,5 r5px  (DS intacto — NÃO vazou)
```

O controle negativo importa: a primeira versão dele que escrevi (`body :not(.venda-v3) input`)
estava **mal construída** — casava os mesmos elementos, porque qualquer ancestral serve. Refeito
injetando um `.cw-input` direto no `<body>`.

## O gate de frescor NÃO passou — e não foi contornado

`cowork-mirror-freshness --compare` deu **0 SYNC · 107 UNCHECKED**. O `venda-v3` veio do projeto
`019e2365` (handoff `design_handoff_cadastro_venda`), que **não aparece** em
`DesignSync.list_projects`; o projeto que o script vigia (`019dcfd3`) também não. A fonte viva
dele é inacessível nesta sessão. O que substituiu, parcialmente: a peça que este trabalho toca
(a caixa do campo) foi conferida contra a fonte viva do **DS**, que está acessível.

⚠️ Nota de instrumento: `--compare` **sem** `--check` sai `rc=0` e imprime
*"✓ sem espelho STALE"* mesmo com 107 UNCHECKED. A contagem ao lado é honesta (`⬜ 107`), mas a
linha final, lida sozinha, passa por verde.

## O merge do main achou um buraco real

A branch começou a sessão em `0/0` e estava **28 commits atrás** no fechamento — o `main` andou
sozinho. Mergeei **antes** de fechar, e o merge trouxe `ConsultaCliente.tsx` (novo, de outra
sessão) com **2 `DialogContent` sem wrapper** e 5 campos. Sem isso o PR nasceria com 5 campos a
30px no meio de uma tela a 34,19px — exatamente o bug de portal ([§5 2026-07-10](../proibicoes.md))
contra o qual eu tinha me prevenido nos outros 6. Total: **8 portais cobertos**, controle vazio.

## Erros meus nesta sessão

- **Porta 5601 tinha servidor de OUTRA sessão** servindo o `index.html` original do protótipo. O
  `HTTP=200` que li **não era o meu harness**. Quem denunciou foi o `404` do CSS ao lado — se os
  dois tivessem respondido, eu teria medido a página errada. Refeito em porta limpa **com prova
  de identidade** (marcador no HTML), não só com o status code.
- **`grep -c` com escapes errados** reportou *"0 utilitárias compiladas"*. Era falso: o controle
  positivo (uma classe que eu sabia existir) também deu 0 — o instrumento é que estava errado.
  Com `grep -F`, todas as 6 estavam lá. Sem o controle positivo eu teria ido caçar bug inexistente.
- **`grep -c -o`** contando linhas em vez de ocorrências (CSS minificado = 1 linha) fez as 4
  regras aparecerem como "1".
- **Comentário `//` dentro de `return (`** em JSX — vira texto literal. Pego na hora.
- O **canário do harness reprovou 2×** antes de passar (tokens só-dark sem `--font-sans`, o que
  invalida a shorthand `font:` inteira e cai pra 16px). Fez o trabalho dele.

Todos da família **LC-08** (medir com a fonte/instrumento errado). Nenhum chegou a virar
afirmação publicada — em todos, o que pegou foi controle positivo ou canário, nunca releitura.

## Estado

- **NÃO commitado em `main`.** Branch `claude/quirky-lichterman-0e6bb2`, 2 commits à frente,
  0 atrás. PR não aberto — R10.
- Gates locais verdes: `tsc` 372 = baseline (0 nos meus arquivos) · `build:inertia` rc=0 ·
  `lint:baseline` (−185) · `layout` · `a11y` (275/294) · `casos-gate` **nos dois modos**
  (o `--check-baseline-shrink` inclusive) · `ds:report` · `screen-coverage`.
- **Pest e `visual-regression` NÃO rodaram**: Pest é CT 100 por regra Tier 0
  ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)) e o visual-regression é
  Playwright no CI.
- **MCP indisponível** nesta sessão — as tools nem aparecem no registro (`ToolSearch` vazio).
  Passo 1 do R12 feito por fallback filesystem. Declarado, não inventado.

## Próximo passo

1. **[W]**: abrir PR → merge → **smoke pós-deploy** (R1). O que existe hoje é simulação do CSS
   compilado na prod, que prova o efeito e **não substitui** o smoke real.
2. **Resíduo declarado**: os 14 `h-8 text-[12.5px]` seguem no código, agora comprovadamente
   inertes. Removê-los é higiene, não densidade — não misturei para não inflar o diff. PR
   separado se [W] quiser.
3. **Aberto, decisão de produto**: alinhar o `.cw-input` global (30 → 34,19px) ao DS de onde ele
   diz ter sido portado. É a opção 2 do fork; atinge ~120 telas, incluindo o drawer Cliente que
   a Larissa opera.
