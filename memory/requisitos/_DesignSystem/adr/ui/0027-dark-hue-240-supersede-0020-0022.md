---
id: requisitos-design-system-adr-ui-0027-dark-hue-240-supersede-0020-0022
---

# ADR UI-0027 · Dark em hue 240 (superfícies) + textos hue 90 — supersede UI-0022 e, em parte, UI-0020; numera a decisão D-3 de 2026-07-08

- **Status**: accepted
- **Data**: 2026-08-28
- **Aprovado em**: 2026-08-28 — [W] autorizou escrever esta ADR (*"faz"*), em resposta ao veredito medido de que UI-0020 e UI-0022 descrevem um dark que o código abandonou. ⚠️ **A decisão de fundo NÃO é de hoje:** é de **2026-07-08**, [W] verbatim no corpo do commit `6d00ed0258` ([#3981](https://github.com/wagnerra23/oimpresso.com/pull/3981)) — *"Wagner escolheu POR IMAGEM a opção C (espelho): mais claro, cinza azul-frio hue 240"*. Esta ADR **numera** aquela decisão; não cria uma nova.
- **Decisores**: Wagner (decisão de 2026-07-08 por imagem + autorização de hoje), Claude Code (medição + redação)
- **Categoria**: ui · fundações · tokens · governança
- **Supersede**: [UI-0022](0022-border-dark-clareado-fidelidade.md) — **integral** (0 de 3 tokens dela sobrevive)
- **Supersede parcial**: [UI-0020](0020-dark-warm-ds-v6-tokens.md) — só o **§Decisão item 1** ("o dark é WARM, hue 282"). Ver §"O que permanece vigente da UI-0020", que é normativo: **3 dos 37 tokens dela seguem em vigor e sem sucessor**
- **Refs**:
  - **Fonte da verdade (código):** [`resources/css/tokens/semantic.tokens.json`](../../../../../resources/css/tokens/semantic.tokens.json) → `npm run tokens:build` → [`_generated-cockpit-dark.css`](../../../../../resources/css/tokens/_generated-cockpit-dark.css) + [`_generated-inertia-dark.css`](../../../../../resources/css/tokens/_generated-inertia-dark.css)
  - [`proposals/2026-07-08-profissionalizar-ds-sync-git-espelho.md §P0`](../../../../decisions/proposals/2026-07-08-profissionalizar-ds-sync-git-espelho.md) — a tabela dos candidatos que [W] julgou por imagem (opções A/B/C/D) e o passo que manda gravar o escolhido
  - [`proposals/2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md §D-3`](../../../../decisions/proposals/2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md) — a decisão que esta ADR numera, rascunhada e **nunca numerada** (a dívida que esta ADR paga)
  - [ADR 0328](../../../../decisions/0328-ds-transicao-congelado-para-vivo-git-ssot.md) — linha 63: *"D-2 (sidebar preto-fixa) e **D-3 (valores dark reconciliados)** são UI-ADR/PR à parte — não entram aqui"*
  - [UI-0023](0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) — a **irmã**: pagou a D-2 em 2026-07-16, com a mesma forma. Esta paga a D-3
  - [UI-0021](0021-primary-dark-clareado-0190.md) — primary dark `oklch(0.7 0.15 295)`: **vigente, não afetado** (cor de marca, não neutro de tema)
  - [ADR 0300](../../../../decisions/0300-errata-0239-nome-real-fonte-design-system.md) (DTCG SSOT) · [UI-0013](0013-constituicao-ui-v2-camadas.md) (Constituição UI v2, camada Fundações)
  - [`PARIDADE-area-jana §9.6`](../../../Jana/PARIDADE-area-jana-diagnostico-e-ondas.md) — a medição que separou as hipóteses ([#6382](https://github.com/wagnerra23/oimpresso.com/pull/6382))

## Contexto

**O dark do oimpresso está em hue 240 desde 2026-07-08 e nenhuma ADR registrava isso.** Duas ADRs `accepted` seguiram descrevendo hue 282 por ~7 semanas — e a mais nova das duas nasceu já contrariada no mesmo dia.

### Linha do tempo (medida por `git log -L` na linha do token, não por leitura)

| Data/hora | Evento | Estado do dark |
|---|---|---|
| 2026-06-22 | [#3220](https://github.com/wagnerra23/oimpresso.com/pull/3220) — DTCG vira SSOT, espelhando o `cockpit.css` de então | superfícies 282 · **textos hue 90** |
| **07-07 17:25** | [#3932](https://github.com/wagnerra23/oimpresso.com/pull/3932) — **UI-0020**: retune WARM, 37 tokens | tudo **282** |
| 07-08 11:23 | [#3958](https://github.com/wagnerra23/oimpresso.com/pull/3958) — **UI-0022**: borda `0.30 → 0.335` | ainda **282** |
| **07-08 18:43** | [#3981](https://github.com/wagnerra23/oimpresso.com/pull/3981) — FASE 1, decisão [W] por imagem | 15 tokens do cockpit saem de 282: **12 → 240**, 3 de texto → **90** |
| **07-08 18:56** | [#3982](https://github.com/wagnerra23/oimpresso.com/pull/3982) — FASE 2 | `@theme` shadcn dark → **240** |
| 2026-07-16 | **UI-0023** paga a **D-2** (sidebar) da mesma proposta | D-3 segue sem número |
| 2026-08-27 | Medição da área Jana separa as hipóteses ([#6382](https://github.com/wagnerra23/oimpresso.com/pull/6382)) | esta ADR |

**A UI-0020 valeu ~25 horas.** O que a derrubou não foi drift silencioso: foi decisão [W] explícita, tomada **por imagem** entre quatro candidatos renderizados (A = manter git `0.165/282`; B = snapshot de junho `0.205/282`; **C = espelho `0.26/240`**; D = um ponto novo). O que faltou foi o número.

### Por que as duas ficaram para trás (a causa, medida — não suposta)

| hipótese | veredito | recibo |
|---|---|---|
| build DTCG defasado | ❌ **REFUTADA** | `ds-tokens-build-sync --check` → rc **0** (*"6 arquivos `_generated` em sincronia"*); `dtcg-equivalence` → rc **0**, **308/308 fiéis, 0 divergências**; workflow success em 2026-08-26. O CSS gerado é fiel ao SSOT — **é o SSOT que está em 240/90** |
| decisão posterior não registrada | ✅ **CONFIRMADA — é a causa** | as duas propostas de 07-08 se declaram *"PROPOSTA. NÃO é lei, NÃO é ADR numerado"*; a ADR 0328 (aceita 07-09) as remete a "UI-ADR à parte"; **413 de 413** ADRs varridas (387 `memory/decisions/` + 26 `adr/ui/`) — nenhuma supersede a UI-0020 |
| duas fontes por desenho | 🟡 verdadeira, **não explica o hue** | `PIPELINE-TOKENS.md:54-55` declara as duas camadas de propósito (Tailwind `@theme` × shell `.cockpit`); consumidores contados: cockpit **43 arquivos / 1.540 ocorrências**, shadcn **319 `.tsx` / 4.078**. Mas **elas convergem em 240** nas superfícies |

Aplica-se a **REGRA DE PRECEDÊNCIA** de [`memory/proibicoes.md`](../../../../proibicoes.md): *"o charter pode estar ERRADO e ainda é lei — 'lei' significa autoridade de intenção, não garantia de correção"*. Aqui o perdedor é a ADR: o código seguiu o dono; o registro ficou para trás.

### O detalhe mecânico que reenquadra tudo: a opção C era um retrato do git PRÉ-UI-0020

O espelho que [W] escolheu se declara, no próprio README, *"derived from wagnerra23/oimpresso.com @ commit `5390c5a2cd8f`"* — um retrato do git **anterior** ao retune da UI-0020. Por isso **14 dos 37 tokens** voltaram a valores **byte-idênticos ao pré-UI-0020**, incluindo os três de texto em hue 90.

Ou seja: no grupo `cockpit`, a decisão de 07-08 é **a reversão da UI-0020**, não um terceiro desenho. No grupo `@theme` (shadcn), é alinhamento novo ao canvas do cockpit — antes ele estava no default azul-saturado do shadcn (`0.137 0.036 258.5`), que nunca foi decisão de ninguém. Registrar isso importa porque, sem ele, o "byte-idêntico ao pré-0020" parece resíduo acidental — e não é.

## Decisão

**1 · No dark, os neutros de superfície são hue 240, nas duas camadas.** É o estado vigente em produção desde 2026-07-08; esta ADR o torna lei em vez de fato não-registrado.

| camada | tokens | valor |
|---|---|---|
| shell `.cockpit` | `--bg` · `--bg-2` · `--surface` · `--border` · `--border-2` | `0.26` · `0.23` · `0.30` · `0.34` · `0.31`, todos `0.006–0.008 · 240` |
| shell `.cockpit` (sidebar) | os 7 `--sb-*`: `sb-bg` · `sb-bg-2` · `sb-border` · `sb-hover` · `sb-active` · `sb-scroll` · `sb-bullet-out` | hue **240** (coerente com [UI-0023](0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md), que fixa a sidebar preta nos dois modos) |
| Tailwind `@theme` | `--color-background` · `--color-card` · `--color-popover` · `--color-border` · `--color-input` · `--color-foreground` · neutros `secondary/muted/accent/ring/*-foreground` | hue **240** |

**2 · Os textos do shell são hue 90 (warm), sobre superfície fria — e isso é decisão, não resíduo.** `--text` `oklch(0.94 0.005 90)` · `--text-dim` `0.72 0.005 90` · `--text-mute` `0.58 0.005 90`, mais os três `--sb-text*`.

O contraste com o item 1 é intencional e foi julgado: a tabela de candidatos da proposta P0 lista `--text` explicitamente entre os cinco tokens comparados (git `0.965 0.004 282` × **espelho `0.94 0.005 90`**), e o passo 3 manda *"gravar o escolhido em `cockpit.surface.bg` + companheiros `--surface`/`--border`/**`--text`**/`--sb-bg`"*. [W] escolheu a coluna do espelho **por imagem**, com o texto warm dentro dela. Os `--sb-text*` nunca passaram por 282 em commit nenhum — nasceram 90 em [#3220](https://github.com/wagnerra23/oimpresso.com/pull/3220) e continuam.

**3 · Cores funcionais seguem intocadas** — herdado da UI-0020 §Decisão item 2, que **permanece vigente**: `info*`, charts, personas (`customer/supplier/employee`), `stage-*`, `origin-*`, `kpi-feature`, `canal-fb`, e o `--color-primary` dark `oklch(0.7 0.15 295)` da [UI-0021](0021-primary-dark-clareado-0190.md). Azul ali é significado, não tema.

**4 · Light inalterado** — UI-0020 §Decisão item 3, também **vigente**. Nenhum `$value` light é tocado.

**5 · Zero mudança de pixel.** Esta ADR é documental: alinha o registro ao código. Nenhum token muda, nenhuma baseline de regressão visual é regenerada.

### O que permanece vigente da UI-0020 (normativo — o supersede é parcial por isto)

Dos 37 tokens que a UI-0020 mudou, medidos em `120ffcebed^` × `120ffcebed` × `HEAD`:

| classe | n | destino |
|---|---|---|
| **sobrevivem no valor da UI-0020** | **3** | `--bubble-them` · `--thread-bg-from` · `--thread-bg-to` |
| revertidos byte-idênticos ao pré-UI-0020 | 14 | grupo `cockpit.surface.*` + `accent-soft` |
| valor terceiro | 20 | grupo shadcn `color.*` + `sb-hover`/`sb-active` — dos quais **12 preservam L e C exatos da UI-0020**, trocando só o hue |

Os **3 tokens de bolha/thread do chat continuam regidos pela UI-0020**, e não por acidente: eles **não existem no DS vivo** — 0 hits em `colors_and_type.css`, `cockpit_domains.css` e `_ds_bundle.js`. O DS não é dono deles, logo não há sucessor a invocar. Alinhá-los ao canvas 240 é decisão [W] em aberto, **não resíduo a limpar** — e superseder a UI-0020 inteira os deixaria sem lei nenhuma.

### Por que o `ds-v6` ainda dizer 282 não contradiz esta ADR

Quem abrir [`prototipo-ui/cowork/ds-v6/gabarito-vendas.html`](../../../../../prototipo-ui/cowork/ds-v6/gabarito-vendas.html) — a fonte que a UI-0020 chama de *"fonte de TODOS os valores novos, verbatim"* — vai ler `--text: oklch(0.965 0.004 282)` e concluir que esta ADR está errada. Não está:

- **o shell do protótipo não carrega o `ds-v6`.** Medido: os 69 `<link rel=stylesheet>` de `prototipo-ui/cowork/oimpresso.com.html` incluem `_ds/office-impresso-design-system-019dd02f-…/colors_and_type.css` e **nenhum** aponta para `ds-v6/tokens.css`. As duas menções a `ds-v6` no shell são **comentários**, não links;
- os dois arquivos são **fósseis pré-decisão**: `gabarito-vendas.html` não é tocado desde **2026-06-23**, `ds-v6/tokens.css` desde **2026-07-01** — ambos anteriores a 07-08. O resync do espelho de 2026-08-27 ([#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379), 55 arquivos) não tocou nenhum dos dois;
- **o DS que o shell de fato carrega concorda com esta ADR.** Em `.cockpit[data-theme="dark"]`: `--bg 0.26 0.006 240` · `--surface 0.30 0.008 240` · `--border 0.34 0.008 240` · `--text 0.94 0.005 90` — byte-idêntico à produção. Medido no snapshot `scripts/design-sync/mirror-snapshot/colors_and_type.css` (retrato de **2026-07-09**, reconferido 11/11 contra o vivo em **2026-08-13**, [#5751](https://github.com/wagnerra23/oimpresso.com/pull/5751)). ⚠️ **Frescor declarado, não presumido:** esse snapshot não é reconferido há 15 dias; esta ADR afirma o que ele dizia **naquelas datas**, não um "o DS pede 240" atemporal.

### E o mandato [W] de 2026-07-10 no `styles.css` do espelho

[`prototipo-ui/cowork/styles.css:55-60`](../../../../../prototipo-ui/cowork/styles.css) traz, em texto vindo do Cowork vivo, um bloco `[data-theme="dark"]` **removido em 2026-07-10** sob *"mandato [W] 'mesmas cores'"*, justificando que ele *"redefinia os neutros dark em hue 240, competindo com o `ds-v6/tokens.css` (hue 282) = o split-brain"*.

Lido isolado, parece um [W] de 07-10 mandando ir para 282, **dois dias depois** da decisão de produção. Não é: o que aquela remoção fez foi **deferir ao DS** em vez de redefinir localmente — e o DS que o shell carrega é o `colors_and_type.css` (240/90), não o `ds-v6/tokens.css`. O alvo do "uma fonte, um hue" foi eliminar a redefinição concorrente, não escolher 282. Fica registrado aqui porque essa é a leitura que derrubaria esta ADR se ninguém a tivesse medido.

## Consequências

- **A leitura de "qual é o dark" para de mentir.** Enquanto UI-0020 e UI-0022 estavam integralmente `accepted`, qualquer sessão que as abrisse lia *"o dark é 282"* como lei vigente e a saída natural era propor reverter o app inteiro — contra decisão [W] tomada por imagem. Esse era o vetor, e ele já tinha produzido um achado aberto em [`PARIDADE-area-jana`](../../../Jana/PARIDADE-area-jana-diagnostico-e-ondas.md).
- **A dívida da sessão de 2026-07-08 fecha.** D-1 (direção design→git) virou [ADR 0328](../../../../decisions/0328-ds-transicao-congelado-para-vivo-git-ssot.md); D-2 (sidebar) virou [UI-0023](0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md); **D-3 é esta**. As três passam a ter número.
- **Ponteiros vivos corrigidos no mesmo PR** (o precedente é a UI-0023, que consertou os seus): o comentário do [`PageHeader.tsx`](../../../../../resources/js/Components/PageHeader/PageHeader.tsx), que afirmava em presente *"a ADR UI-0020 pede hue 282 e o gerado hoje está em 90"* — falso a partir daqui, e já LC-10 antes; os rótulos de [`visual-regression.yml`](../../../../../.github/workflows/visual-regression.yml) que atribuem o modo update à "UI-0020 §4 (dark warm)"; e o [`README.md`](../../README.md) do Design System, que não tinha linha alguma sobre o hue do dark.
- **Nada a fazer no código de tokens.** `ds-tokens-build-sync --check` e `dtcg-equivalence` já estão verdes contra o estado que esta ADR carimba.

## O que esta ADR NÃO decide

**Os 3 tokens de chat em 282.** `--bubble-them` · `--thread-bg-from` · `--thread-bg-to` seguem sob a UI-0020 (ver §"O que permanece vigente"). Se destoam do canvas 240 no render é pergunta de olho, não de token — e a resposta é [W], depois de ver a tela do chat em dark.

**Se o texto warm sobre superfície fria deve continuar.** O item 2 da Decisão registra que **foi** escolhido em 2026-07-08 e é o que está em produção. Não afirma que é o ideal estético — só que tem dono e data. Revisitar é ADR nova, não conserto silencioso.
