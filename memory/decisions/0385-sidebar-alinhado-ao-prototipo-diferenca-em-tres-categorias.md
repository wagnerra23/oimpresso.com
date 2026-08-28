---
slug: 0385-sidebar-alinhado-ao-prototipo-diferenca-em-tres-categorias
number: 385
title: "Sidebar alinhado ao protótipo — e a diferença classificada em três categorias"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-28"
module: design-system
tags: [design, shell, sidebar, fundacoes, cowork, medicao, deriva]
supersedes: []
superseded_by: []
related:
  - 0039-ui-chat-cockpit-padrao
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0224-hooks-block-vs-advisory-claude-4.8-aware
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0282-protocolo-v2-colapso-ratificacao
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0344-two-strikes-cobre-processo
  - 0367-cockpit-unico-forja-project-mgmt-morre
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
---

# Sidebar alinhado ao protótipo — e a diferença classificada em três categorias

> Nasce `proposto`. [W] pediu o alinhamento nesta sessão, em palavras próprias, e a execução
> começou com essa autorização. O merge desta ADR por [W] é o ato formal de ratificação
> ([ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md)) — não escreva "aceito" antes dele.

## Contexto

A camada **Fundações** da Constituição UI v2 ([UI-0013](../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md))
é imutável a não ser por ADR: tokens de cor, tipo e espaço não mudam no calado. O **Shell** —
`AppShellV2` + sidebar — existe uma vez para o app inteiro. Logo, alinhar o sidebar ao protótipo
**não é mexer numa tela**: é mexer no que toda tela herda.

O sidebar já tem duas leis próprias, e nenhuma delas está sendo contrariada aqui: ele é
**dark-fixo (preto)** nos dois modos ([UI-0023](../requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md))
e o dark do app foi reconciliado em superfícies hue 240 com textos hue 90
([UI-0027](../requisitos/_DesignSystem/adr/ui/0027-dark-hue-240-supersede-0020-0022.md)). Esta ADR
trata de **métricas e tokens do shell** medidos contra a fonte de design; não reabre aquelas duas.

Sem registro, a próxima sessão desfaz o alinhamento sem saber que ele foi decisão do dono — e o
desfazimento vai parecer conserto, porque é exatamente assim que a deriva se apresenta.

## O fato medido

Sonda idêntica injetada nos dois lados, mesmo tema (**dark**), design servido do espelho fresco
`prototipo-ui/cowork/` — espelho de leitura, na forma prevista pela
[ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md) —, reimportado do Cowork em
27–28/ago. Rodada de frescor de 2026-08-27: **251 sync · 0 stale · 3 unchecked**; o único arquivo
só-no-vivo é um `.thumbnail`. **19 divergências** enumeradas entre design e produção:

| Elemento | Propriedade | Design | Produção |
|---|---|---|---|
| Atalho de topo | `height` | 34 | 30 |
| Atalho de topo | `font-size` | 13 | 13.5 |
| Atalho de topo | `font-weight` | 400 | 500 |
| Atalho de topo | cor (hue) | 295 | 90 |
| Atalho de topo | ícone | 16 / stroke 1.6 | 13 / stroke 2 |
| Item de grupo | `font-size` | 13 | 13.5 |
| Item de grupo | cor (hue) | 295 | 90 |
| Item de grupo | ícone | 16 / 1.6 | 14 / 2 |
| Item de grupo | `padding-left` | 18 | 10 |
| Item de grupo | `border-left` | 2px | 0 |
| Header de grupo | `height` | 25 | 28 |
| Header de grupo | `font-size` | 10 | 10.5 |
| Header de grupo | cor | `oklch(0.8 .008 295)` | `oklch(0.58 .005 90)` |
| Header de grupo | `letter-spacing` | 0.6 | 0.84 |
| Header de grupo | `padding` | `6px 10px` | `8px 6px 4px 8px` |
| Header de grupo | `gap` | 8 | 6 |
| Header de grupo | `radius` | 4 | 6 |
| Header de grupo | `margin` | 0 | `0 4px` |
| Sequência do topo | ordem | `IA · Visão geral · Atendimento` | `IA · Forja · Atendimento` |

Tokens do design (todos em hue 295):

```
--sb-text     oklch(0.80 0.008 295)
--sb-text-hi  oklch(0.97 0.004 295)
--sb-active   oklch(0.34 0.05  295)
--sb-hover    oklch(0.28 0.035 295)
--sb-bg       oklch(0.21 0.025 295)
--sb-border   oklch(0.30 0.03  295)
```

> ### As 6 linhas de COR sao DECIDIDAS — e o protótipo NAO manda nelas
>
> A redacao anterior deste bloco dizia que o hue 90 de producao "coincide" com a UI-0027 e que a
> causa nao fora medida. **Foi medida depois, e o achado inverte a conclusao.**
>
> O protótipo carrega **DUAS** folhas declarando `--sb-*`, e elas DISCORDAM ENTRE SI (medido no
> render, 2026-08-28, via `document.styleSheets`):
>
> | Fonte | Seletor | `--sb-bg` | `--sb-text` |
> |---|---|---|---|
> | `colors_and_type.css` — projeto **Design System** | `.cockpit` | `oklch(0.18 0.006 240)` | `oklch(0.78 0.005 90)` |
> | `styles.css` — protótipo **de tela** | `:root` | `oklch(0.21 0.025 295)` | `oklch(0.80 0.008 295)` |
>
> **O Design System declara 240/90 — byte-idêntico ao que producao tem hoje.** O hue 295 e um
> override local do arquivo de tela, que vence a cascata no preview. A
> [UI-0027](../requisitos/_DesignSystem/adr/ui/0027-dark-hue-240-supersede-0020-0022.md) ja tinha
> nomeado esse split-brain e registrado que o bloco equivalente foi removido em 2026-07-10 por
> competir com o DS.
>
> A UI-0027 tambem nomeia os tokens: os `--sb-*` de superficie ficam em hue **240** e os
> `--sb-text*` em hue **90**, este ultimo escolhido por [W] **por imagem**.
>
> **Consequencia pela propria D-1 desta ADR:** as 6 linhas de cor da tabela acima sao **DECIDIDAS**,
> nao deriva. Ficam como estao em producao. Os 6 tokens em hue 295 listados abaixo sao **o que o
> arquivo de tela do espelho mede** — nunca um alvo.
>
> **O residual honesto, que esta ADR NAO resolve:** o canon diz que a fonte de design e "protótipo
> Cowork **+ Design System** + charter". Sao duas fontes, e aqui elas divergem entre si. Enquanto
> ninguem reconciliar, todo alinhamento de cor vai parecer errado — metade da fonte diz uma coisa,
> metade diz outra.
>
> ### A CAUSA, achada depois: uma ADR aplicada fora do escopo dela
>
> A divergencia entre as duas fontes **nao e acidente** — esta escrita no proprio arquivo.
> `prototipo-ui/cowork/styles.css:3`, comentario literal:
>
> ```
> /* Sidebar dark — espelho AppShell · tingido p/ a marca
>    (roxo canon hue 295, ADR 0235) em vez de preto neutro croma-0 */
> ```
>
> O prototipo tingiu os **neutros** do sidebar com o roxo da marca citando a
> [ADR 0235](0235-ds-v4-accent-roxo-universal.md). Mas a 0235 governa o **accent**: o titulo dela
> e *"accent oklch 0.55 0.15 295"*, croma **0.15** — cor de destaque. Os valores do prototipo tem
> croma 0.008–0.05: sao neutro tingido, nao accent.
>
> E os neutros do sidebar tem dono proprio e mais novo: a
> [UI-0027](../requisitos/_DesignSystem/adr/ui/0027-dark-hue-240-supersede-0020-0022.md)
> (2026-08-28) fixa superficies em **240** e textos em **90**, e registra que foi escolha de [W]
> **por imagem**. Ela e ~3 meses posterior a 0235.
>
> **Sao duas familias de token, e uma regra cruzou a fronteira da outra.** Por isso todo
> alinhamento de cor parecia errado pela metade: escolher um lado fazia o outro acusar.
>
> **O que isso torna decidivel** (e nao estava, antes de medir): so ha duas saidas coerentes —
> (a) o prototipo defere a UI-0027 e o bloco 295 sai da RAIZ (o projeto Cowork vivo, nao o
> espelho, que e read-only por [ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md)); ou
> (b) [W] decide que o sidebar E tingido de marca, e a UI-0027 e superseded por uma ADR nova.
> Nao ha terceira: manter as duas e manter o split-brain.
>
> ⚠️ Esta ADR **nao escolhe** — registra que a escolha existe, qual e o mecanismo de cada saida, e
> que enquanto ela nao for feita a producao segue a UI-0027 (que e o estado atual e o do DS).
>
> **Consolidacao: medida e ja OK.** Varredura dos docs vivos que falam de cor de sidebar
> (`sidebar-menu-arch/SKILL.md`, `PRE-MERGE-UI.md`, `PIPELINE-TOKENS.md`, `rules/css.md`): nenhum
> repete o VALOR — todos referenciam o token (`var(--sb-text)`). E a arquitetura certa, e e por
> isso que o conflito pode ser isolado em **um unico arquivo**. Nao ha o que consolidar; ha o que
> decidir.
>
> _(Este bloco corrige o corpo do rascunho antes do primeiro commit — o arquivo nunca foi canon:
> nasceu nesta sessao, `status: proposto`, e ainda estava untracked quando a correcao entrou.)_

## Decisão

### D-1 — Diferente não é erro. A regra é de TRÊS categorias, não binária

Toda divergência design × produção no shell é classificada antes de virar trabalho:

| Categoria | O que é | O que se faz |
|---|---|---|
| **DECIDIDA** | alguém escolheu e registrou | **mantém** — e o registro é citado |
| **DERIVA** | ninguém escolheu; acumulou | **conserta** |
| **DESIGN-ANDOU** | produção está numa versão *anterior* do certo | **seguir é decisão**, não conserto automático |

O caso que prova a necessidade das três categorias está na própria tabela acima: a sequência do
topo. Produção tem o atalho **Forja**, o design tem **Visão geral**. Forja é capacidade real e viva
([ADR 0367](0367-cockpit-unico-forja-project-mgmt-morre.md)); apagá-la para "ficar igual ao design"
**destruiria função**. Um gate que tratasse toda diferença como defeito seria **destrutivo**, e esta
linha é a prova concreta disso — não uma hipótese.

### D-2 — A acusação muda: `height: 30px` não é deriva, é versão velha do certo

`git log -S` no valor aponta o commit **`5a7074d93a`** — *"feat(cockpit): aplicar CSS completo do
prototipo ADR 0039"* (2026-04-30). O 30px **nasceu do protótipo daquela época**, aplicado
corretamente. O design mudou para 34 depois. A leitura honesta não é *"produção está errada"*, é
*"produção está numa versão anterior do certo"* — categoria **DESIGN-ANDOU**.

Corolário que fica registrado: **este alinhamento é o mesmo movimento da
[ADR 0039](0039-ui-chat-cockpit-padrao.md), repetido.** O que se decide aqui é repetir aquele
movimento sabendo que ele é periódico, em vez de redescobri-lo daqui a alguns meses sob o nome errado.

### D-3 — A causa sistêmica, dita sem suavizar

Cada peça nova foi medida **contra o vizinho** em vez de contra a fonte — e o vizinho já estava
defasado. Assim a deriva se fabrica sozinha a cada item novo: o erro se propaga por herança, e cada
passo parece razoável isoladamente.

Isso não é reconstituição histórica: **aconteceu nesta própria sessão** e está documentado no PR. Um
item novo foi feito `.sb-shortcut` (30px) *"pra não ficar 4px mais alto que o vizinho"* — com a
justificativa escrita como se fosse óbvia. Ela era óbvia; era também a reprodução do defeito.

A regra que fica: **a referência de uma peça nova do shell é a fonte de design, nunca a peça ao
lado.** Se o vizinho e a fonte discordam, isso é um achado a classificar por D-1 — não um alvo a copiar.

### D-4 — A defesa vira derivada e enforçada, e nasce advisory

A medição do shell passa a ser **produzida por máquina**, como extensão do dono que já mede
design × produção (`prototipo-ui/design-diff.mjs`, mesma sonda injetada nos dois lados), em vez de
depender de alguém lembrar de olhar — é a doutrina da
[ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md): *derivado+enforçado sobrevive;
escrito+lembrado apodrece*. **Estender o dono, não abrir um segundo medidor.**

A extensão **nasce advisory** ([ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md):
predicado que exige julgamento humano não bloqueia). Promover a required é **decisão [W]**, sujeita
à evidência de mordida exigida pela
[ADR 0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md); quem é required em
qualquer momento tem dono próprio e único — `governance/required-checks-baseline.json` — e esta ADR
aponta para ele em vez de repetir seu conteúdo.

Consequência direta de D-1 sobre o desenho da máquina: o relatório **classifica e reporta**, e a
saída para uma divergência DECIDIDA é dizer qual registro a decidiu — jamais propor apagá-la.

### D-5 — O que esta ADR autoriza, exatamente

Aplicar no shell as métricas e tokens medidos acima nas linhas classificadas como **DERIVA** e
**DESIGN-ANDOU**, mantendo intacta a linha **DECIDIDA** (o atalho Forja permanece). As duas leis
próprias do sidebar seguem valendo sem emenda: dark-fixo preto (UI-0023) e a reconciliação do dark
do app (UI-0027).

## Consequências

**Positivas**

- O alinhamento passa a ter dono e data: desfazê-lo vira decisão consciente, não conserto de rotina.
- "Diferente" deixa de ser sinônimo de "defeito" — e o `Forja` sobrevive a um gate que, na forma
  binária, o teria removido.
- A acusação sobre o `30px` fica correta no registro, o que muda a próxima pergunta: não é *"quem
  errou?"*, é *"com que cadência o shell reencontra a fonte?"*.
- A referência para peça nova fica escrita, então o defeito de medir pelo vizinho tem onde ser
  barrado em revisão.

**Custos**

- Alinhar o shell mexe no que **toda tela** herda: as baselines visuais se movem junto, e isso é
  trabalho, não efeito colateral esquecível.
- Classificar em três categorias custa julgamento humano por linha — é mais caro que um diff
  binário, e é justamente por isso que a máquina nasce advisory.
- A categoria **DESIGN-ANDOU** não se resolve sozinha: cada item dela é uma decisão pendente, e
  acumulá-las sem decidir recria a deriva com outro nome.

**Não decidido aqui**

- Cadência de reencontro entre shell e fonte (de quanto em quanto tempo, por qual gatilho).
- Promoção da extensão a required — flip de [W], com mordida provada.
- Qualquer mudança nas leis do sidebar cobertas por UI-0023 e UI-0027.
- A cadeia que produz hue 90 no texto do sidebar dentro do bloco fixo: **não medida** nesta sessão,
  logo não afirmada.

## Notas de método

Duas regras do repositório valeram durante a redação e explicam a forma do texto: **(a)** os números
acima vêm todos da medição desta sessão — nenhum foi estimado ou herdado de doc; e **(b)** por
[ADR 0344](0344-two-strikes-cobre-processo.md) (two-strikes), a 1ª ocorrência conserta e registra,
não codifica gate bloqueador — que é a razão de D-4 nascer advisory em vez de required.
