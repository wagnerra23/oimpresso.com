---
id: requisitos-design-system-adr-ui-0031-fundacao-dark-adota-o-accent-do-prototipo
---

# ADR UI-0031 · A fundação no ESCURO adota a família accent do protótipo (0,55 → 0,70) e a Forja devolve o override

- **Status**: accepted
- **Data**: 2026-09-02
- **Aprovado em**: 2026-09-02 — [W], textual: *"não me importo com a decisão que vai escolher (…) apenas faça"*.
  A escolha entre "fundação adota o protótipo" e "protótipo cede" coube ao agente; ela caiu para o protótipo por
  [UI-0029](0029-prototipo-soberano-sobre-adr-ui.md) (protótipo é soberano na FORMA).
- **Decisores**: Wagner (autorização de escopo + merge), Claude Code (medição + execução)
- **Categoria**: ui · fundações · tokens
- **Fecha o residual de**: [UI-0021](0021-primary-dark-clareado-0190.md) — ela clareou o `--color-primary`
  no escuro (0,62 → 0,70) e **nomeou por escrito** o que ficava de fora: *"o token legado `--accent` (…) sem
  override dark (…) é um roxo SEPARADO do `--color-primary` (…) Alinhá-lo ao 0,7 é uma varredura própria
  (…) catalogado para decisão futura"*. Esta ADR é essa varredura. Não reabre a UI-0021: continua.
- **Refs**: [UI-0029](0029-prototipo-soberano-sobre-adr-ui.md) (protótipo soberano na forma) ·
  [ADR 0190](../../../../decisions/0190-primary-button-roxo-universal-295.md) (roxo 295) ·
  [ADR 0239](../../../../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md) (DS em git é SSOT) ·
  [ADR 0388](../../../../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md) (réplica primeiro)

## Contexto

O protótipo Cowork (`prototipo-ui/cowork/styles.css`, bloco `[data-theme="dark"]{…}` marcado **"VIDA 06-11 [W]"**)
pinta a família accent do escuro em `oklch(0.70 …)`. A fundação de produção seguia em **0,55** — o mesmo valor do
claro, porque no DTCG os tokens eram `dark_absent` (*"não é redefinido no dark — herda light"*).

Essa é a divergência **"0,55 × 0,70"** que apareceu em **toda** rodada de comparação desta área (Forja, Jana,
Financeiro). Ela foi contornada duas vezes sem ser resolvida: a UI-0021 clareou o `--color-primary` e declarou o
`--accent` fora de escopo; a Onda 2.1 da Forja ([#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563))
copiou os valores do protótipo **escopados** a `.fj-hub`/`.fj-page`, deixando a reconciliação global para [W].

## A medição que mudou o desenho — são QUATRO camadas, e o CSS é a mais fraca

Antes de aplicar, mediu-se **quem decide `--accent` no escuro em runtime**. Em ordem de precedência:

| # | camada | valor antes | vence? |
|---|---|---|---|
| 1 | `_generated-cockpit-light.css` `.cockpit` | 0,55 | — |
| 2 | `_generated-cockpit-dark.css` `.cockpit[data-theme="dark"]` | *não declarava* | > 1 |
| 3 | wrapper de módulo descendente (`.fj-hub`, `.fin-cowork`, `.sells-cowork*`) | 0,70 (Forja) / 0,55 (demais) | > 2 e > 4 |
| 4 | **`style` INLINE do `AppShellV2` no `<div class="cockpit">`** | **0,55** | **> 1 e > 2** |

A camada 4 é a que decide, e o próprio código já avisava: *"Estilo INLINE vence QUALQUER seletor — inclusive
`.cockpit[data-theme="dark"]`, que é ESTE MESMO elemento"* (comentário deixado pelo
[#6306](https://github.com/wagnerra23/oimpresso.com/pull/6306), que consertou o mesmo defeito no `--accent-soft`).

**Consequência de desenho:** mexer só no DTCG/CSS teria produzido um PR verde em todos os gates e **zero mudança
no browser** — o inline continuaria cravando 0,55 por cima. A mudança no `AppShellV2` não é acessório desta onda;
é o seu centro.

Prova (sonda `getComputedStyle`, CSS reais, tema escuro), com **controle positivo** — um `.cockpit[data-theme="dark"]`
recebendo `--accent: 0.55` inline por cima do CSS que diz 0,70 computa **0,55**, confirmando que a sonda discrimina:

| nó medido | `--accent` |
|---|---|
| `.cockpit[dark]` com o inline novo | `oklch(0.70 0.15 295)` |
| `.cockpit[dark]` **sem** inline (só CSS) | `oklch(0.70 0.15 295)` |
| **controle**: inline cravando 0,55 sobre o CSS 0,70 | `oklch(0.55 0.15 295)` ← inline vence |

## Decisão

**No tema ESCURO, a fundação passa a usar os valores do protótipo.** Editado na fonte DTCG
(`resources/css/tokens/semantic.tokens.json`, grupos `cockpit.accent` e `cockpit.semantic`), regenerado por
`npm run tokens:build`, e **espelhado no estilo inline do `AppShellV2`** — sem isso o token não chega ao browser.

| token | escuro ANTES | escuro AGORA |
|---|---|---|
| `--accent` | 0,55 0,15 295 *(herdava o claro)* | **0,70 0,15 295** |
| `--accent-2` | 0,62 0,15 295 *(herdava o claro)* | **0,76 0,15 295** |
| `--accent-soft` | 0,32 0,06 295 | **0,33 0,09 295** |
| `--accent-fg` | `#ffffff` *(herdava o claro)* | **0,14 0,02 295** |
| `--pos` / `--pos-soft` | 0,74 0,14 / 0,30 0,085 | **0,76 0,18 / 0,34 0,11** |
| `--neg` / `--neg-soft` | 0,72 0,16 / 0,32 0,09 | **0,74 0,19 / 0,36 0,12** |
| `--warn` / `--warn-soft` | 0,80 0,13 / 0,32 0,085 | **0,82 0,16 / 0,37 0,11** |

O **claro não muda em nada** — a divergência sempre foi só do escuro. O `hue` continua vindo do tweak do usuário
(`accentHue`, default 295): a onda troca L/C, nunca o matiz.

Dois pontos de leitura que valem registrar:

- **`--accent-2` recebe o `--accent-hi` do protótipo** (0,76). Não é invenção de correspondência: o próprio shell
  do protótipo declara `--accent-2: var(--accent-hi)` no bloco COMPAT de fusão v4→v5 (`styles.css`). Sem isso o
  `--accent-2` ficaria em 0,62 — **mais escuro** que o resting 0,70 — e todo hover de primary no escuro escureceria.
- **`--accent-fg` inverte de propósito.** Com o accent em 0,70, texto branco por cima fica ilegível; o protótipo
  usa tinta escura (0,14). Isso **melhora** consumidores que já pareavam `background: var(--accent)` com
  `color: var(--accent-fg)` — incluindo `cowork-compras-bundle.css`, que usa `--accent-fg` sobre `--cmp-warn`.

### A Forja devolve o override — e o que sobra ali não é teimosia

O bloco `[data-theme="dark"] .fj-hub, .fj-page` da Onda 2.1 tinha **4** definições. Saem **2** — `--accent` e
`--accent-soft` —, porque a fundação passou a dá-las (mantê-las **anularia** o token da fundação em vez de
consumi-lo, já que o wrapper é descendente do `.cockpit`). O ratchet `.foundation-guard-baseline.json` desce de
**4 → 2**, medido.

**Ficam `--accent-hi` e `--accent-line`**, e o motivo é de vocabulário, não de valor: a fundação **não tem esses
dois tokens em tema nenhum** (medido: 0 definições em `resources/`, e este bundle é o **único** consumidor — 25
usos de `--accent-line`, 1 de `--accent-hi`, todos com fallback `var(--accent)`). Removê-los sem substituto
trocaria a borda sutil 0,47 pelo accent cheio 0,70 em 25 sítios do escuro — **divergiria mais** do protótipo, não
menos. Promovê-los à fundação é **token novo no DS = soberania [W]**; os valores do protótipo (claro 0,50 0,16 /
0,80 0,09; escuro 0,76 0,15 / 0,47 0,13) ficam registrados em
[`INCONSISTENCIAS-replica.md`](../../../Forja/INCONSISTENCIAS-replica.md) para essa decisão.

## Não entrou nesta onda (e por quê)

| candidato do bloco dark do protótipo | veredito |
|---|---|
| `--bubble-me` | fica em **0,55 nos dois temas**, e isso é decisão, não esquecimento. O DTCG o declara como **alias** (`var(--accent)`), então a partir desta ADR o CSS o resolveria em 0,70 no escuro — o inline o segura em 0,55 porque **(a)** o protótipo faz o mesmo (0,55 no `:root`, sem redeclarar no bloco escuro) e **(b)** o par dele, `--bubble-me-fg`, é `#ffffff` **fixo, sem par de tema**: branco sobre 0,70 perde contraste nos **14** sítios de bolha (medido). Alinhar os dois é decisão de design, não limpeza de alias — está travado por comentário no `AppShellV2`. |
| `--focus` | a fundação **não declara** `--focus` em lugar nenhum de `resources/` (medido). Adicionar é token novo = [W]. |
| `--sh-1` / `--sh-2` | a fundação declara os dois. **Mas o protótipo os declara DUAS vezes** dentro do mesmo bloco escuro, com valores diferentes — a fundação tem a 1ª, e a 2ª (que vence no protótipo) parece resíduo da fusão v4→v5, não decisão. Importar sombra do app inteiro a partir de uma redeclaração ambígua seria trocar decisão por ruído. Fica medido, não aplicado. |
| `--av-*`, `--atmo`, origens/etapas | fora do escopo declarado (accent + semânticas). |

## Residual honesto — os 5 wrappers de módulo ainda vencem a fundação no escuro

Registrar isto é parte da decisão: sem ele, a próxima sessão mede o Financeiro no escuro, vê 0,55 e conclui que a
ADR não pegou.

`.fin-cowork`, `.sells-cowork`, `.sells-cowork-edit`, `.sells-cowork-show` e `[role="dialog"].fin-cowork` **redefinem
a família accent no próprio wrapper**. Como o wrapper é descendente do `.cockpit`, ele vence a fundação. Medido com
a mesma sonda, no escuro, **depois** desta onda:

| nó | `--accent` | `--accent-soft` | `--accent-fg` |
|---|---|---|---|
| `.cockpit[dark]` puro | 0,70 ✅ | 0,33 0,09 ✅ | 0,14 ✅ |
| `.fin-cowork` | **0,55** | **0,95 0,04** | `#ffffff` |
| `.sells-cowork` | **0,55** | **0,95 0,04** | `#ffffff` |
| `[role="dialog"].fin-cowork` (portal) | 0,55 | 0,95 0,04 | `#ffffff` |

Duas notas de precisão, porque as duas foram **medidas contra a premissa com que esta onda começou**:

1. **Não é `:root`.** A suspeita inicial era que esses bundles definissem `--accent` em `:root` (global). Não
   definem — todos definem no **wrapper**. O efeito prático é parecido, o mecanismo é outro, e isso importa pro
   conserto: escopar mais não resolve, porque já está escopado.
2. **O `--accent-soft: 0,95` no escuro é dívida ANTERIOR a esta onda** — é o mesmo defeito que o #6306 consertou no
   inline, sobrevivendo dentro dos wrappers. Esta ADR não o cria nem o piora.

### O segundo residual: dois componentes que IGNORAM o token e fixam 0,55 no inline

Medido no repo inteiro (`git grep "0.55 0.15 295" -- '*.tsx' '*.ts'`), fora de comentário e de protótipo,
**duas** superfícies vivas cravam o roxo claro por literal, então não seguem nem `--accent` nem `--color-primary`:

| arquivo | o que fixa | efeito no escuro |
|---|---|---|
| [`PageHeaderPrimary.tsx:70`](../../../../../resources/js/Components/PageHeader/PageHeaderPrimary.tsx) | `backgroundColor` 0,55 · `borderColor` 0,45 · `color` 0,99 | o botão primário de **todos** os módulos ("Novo issue", "Novo título"…) segue 0,55 |
| [`Financeiro/Unificado/Index.tsx:1600`](../../../../../resources/js/Pages/Financeiro/Unificado/Index.tsx) | `backgroundColor` 0,55 | idem, escopado à tela |

Isso **não é novo e não é desta onda** — o `forja-cockpit-visual-comparison.md` já o registrava: *"a divergência D6
dos 5 pares é o componente ignorando o token, não o protótipo fora do canon"*. Também não é o mesmo defeito que
esta ADR fecha: aqui o token estava certo e ninguém o lia; lá o token é que estava errado.

**Fica fora de propósito.** O conserto certo é o componente passar a **consumir** o token
(`var(--color-primary)`, já em 0,70 no escuro desde a UI-0021) em vez de trocar um literal por outro — e isso é
mudança do componente, com blast radius de todas as telas e VRT próprio. Nenhum gate trava esses literais hoje
(medido: 0 testes citam `PageHeaderPrimary`), então o conserto não está bloqueado — está só fora de "1 PR = 1 intent".

**Por que os wrappers não foram resolvidos aqui:** são 5 arquivos, e um deles é `[role="dialog"].fin-cowork` — a re-aplicação do
wrapper no portal Radix, que existe justamente porque o portal renderiza no `<body>`, fora do `.cockpit`, e não
herda nada. Remover a definição ali mataria o accent do drawer. A lápide §5 2026-07-10 proíbe exatamente esse
movimento sem provar o consumidor. Além disso Financeiro e Sells são as telas de maior volume (ROTA LIVRE, 99%),
e misturá-las aqui quebraria "1 PR = 1 intent". **Fica nomeado, como a UI-0021 nomeou este.**

## Consequências

- **Blast radius:** toda tela em escuro que use `var(--accent*)` ou `var(--pos|neg|warn*)` **fora** dos 5 wrappers
  acima muda de pixel → baselines de regressão visual regeneradas.
- **Contraste melhora** onde `--accent-fg` era pareado com `--accent`: antes era branco sobre 0,55 no escuro;
  agora é tinta escura sobre 0,70.
- **A Forja para de precisar de override de valor** — o que ela mantém é vocabulário que o DS ainda não tem.
- **Gate A2 atualizado** (`CockpitAccentCanonTest`): ele casava a string inteira `oklch(0.55 0.15 ${accentHue})`,
  que deixou de existir quando o L/C passou a sair de variável. Passou a cobrar os **quatro** L/C canônicos
  (0,55 / 0,62 claros · 0,70 / 0,76 escuros) e segue barrando o azul 220 e o 0,58 0,12 off-canon. O gate A3
  (cruza inline × DTCG) passou a **cobrar** `--accent` e `--accent-2`, que antes ele ignorava por serem
  `dark_absent` — a cobertura dele cresceu de graça com esta mudança.
- **Espelho do DS reconciliado.** `scripts/design-sync/mirror-snapshot/colors_and_type.css` é derivado do git
  (*"copied verbatim from that generated output"*), então ficou atrás e foi regerado: `ds-token-diff` no escopo
  `cockpit-dark` saiu de **diverge:4** para **diverge:0**. Os 3 tokens novos entraram à mão porque o
  `ds-mirror-build.mjs` só reconcilia valor — acrescentar token ele delega ao humano, de propósito.

## Uma exceção que esta ADR APAGA — a paleta da documentação

A página `/documentacao` é editorial e standalone: não carrega o CSS do app, então espelha os tokens do
DS no `:root` do próprio layout, com um teste (`DocumentacaoRouteTest`) travando o espelho contra
`_generated-cockpit-*.css`. No escuro ela mantinha uma **divergência declarada**: `--accent` local em
`oklch(0.74 0.13 295)` em vez do valor do DS. O comentário dela dizia por quê — *"o DS não redeclara
`--accent` no dark, então ele herda oklch(0.55 …) (…) 0.55 sobre papel escuro fica abaixo do contraste de
leitura"* — e o teste terminava com um lembrete literal:

> `expect($dsEscuro)->not->toHaveKey('--accent');` — *"Se um dia o DS passar a declarar accent no dark,
> esta linha vira o lembrete de reconciliar conscientemente."*

**Esta ADR fez esse dia chegar, e o lembrete disparou** (o teste caiu no CI, que foi como se soube).
A reconciliação: **a causa da exceção acabou**, então a exceção acabou junto. Medido nesta sessão sobre o
papel da página (`oklch(0.26 0.006 240)`), pela fórmula de contraste WCAG:

| `--accent` no escuro | contraste sobre o papel | veredito |
|---|---|---|
| herdado antigo, `0.55` | **3,02** | reprova AA (4,5) — **era exatamente o problema** |
| exceção local, `0.74` | 6,49 | passava |
| **DS novo (UI-0031), `0.70`** | **5,55** | **passa AA** |

Como o par escuro novo já é calibrado para fundo escuro, o layout voltou a ser **paridade pura** com o DS
nos dois temas, e o teste passou a cobrar `--accent` junto dos outros (com uma mensagem que avisa se o DS
um dia parar de declará-lo). Um caso concreto do que a UI-0021 pagou adiantado: exceção local existe
enquanto a fundação não resolve; resolvida a fundação, a exceção sai.

## Correção de premissa (registrada porque o plano desta onda a carregava)

O plano previa que remover o override da Forja faria **o item `PALETA` sumir** de
`governance/replica-inconsistencias/forja.json`. **Não faz, e nunca faria:** o `PALETA` é sobre a família
`--dev-*(4)`, não sobre `--accent-*`. Medido antes→depois com o comando canônico: **101 → 101 itens**, `PALETA`
**1 → 1**, mesmo exemplo `--dev-*(4)`. O que de fato melhorou no relatório foi o **R1** (cor crua) do bundle da
Forja: **335 → 333**, pelas 2 declarações removidas.

Segunda correção, menor mas com custo de tempo: a UI-0021 cita *"ADR 0300 (DTCG SSOT)"* nas suas Refs. **Essa ADR
não existe** — são 557 ADRs em `memory/decisions/` e a faixa pula o 0300 (a citação é texto solto, sem link, então
nenhum deadlink-gate a pegou). Quem procurar o contrato do DTCG deve ler
[ADR 0239](../../../../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md) (DS em git é SSOT) e
[ADR 0328](../../../../decisions/0328-ds-transicao-congelado-para-vivo-git-ssot.md). A UI-0021 é append-only e
fica como está; o ponteiro morto não se propaga daqui.
