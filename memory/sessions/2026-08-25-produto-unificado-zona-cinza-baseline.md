---
date: "2026-08-25"
hour: "18:20 BRT"
topic: "Zona cinza recorrente de Produto/Unificado no gate visual — causa medida, hipótese de fonte externa refutada"
authors: [W, C]
outcomes:
  - "A divergência foi medida na imagem e casa byte-a-byte com o patch de cor do PR #6204 — não é dado nem fonte externa"
  - "A hipótese de que a tela renderiza a partir de fonte fora do repo foi refutada por três medições independentes"
  - "Estado final confirmado por run global sem label: Produto/Unificado bate com a baseline"
prs: []
us: []
related_adrs:
  - "0190-primary-button-roxo-universal-295"
---

# Session log 2026-08-25 — Produto/Unificado na zona cinza: a baseline estava velha, a tela estava certa

## TL;DR

A tela `Produto/Unificado` caía na **zona cinza** do `visual-regression` (0,1704%) e bloqueava PRs que
não a tocavam — **três**, todos do módulo Arquivos ([#6243](https://github.com/wagnerra23/oimpresso.com/pull/6243),
[#6244](https://github.com/wagnerra23/oimpresso.com/pull/6244), [#6250](https://github.com/wagnerra23/oimpresso.com/pull/6250)),
com **zero** arquivos de Produto no diff (medido por `gh pr view --json files`).

A causa não era a tela. Era a **baseline**, congelada nas cores anteriores ao patch de cor de
[#6204](https://github.com/wagnerra23/oimpresso.com/pull/6204). O código estava correto.

## O que foi medido (não deduzido)

Decodifiquei as 3 imagens embutidas no `produto-unificado.html` do artifact `pixel-diff-views`
(PNG RGB8, 1728×1117, decoder próprio com `zlib`, controle positivo baseline×baseline = 0 bytes
diferentes). Isso segue a [§5 de 2026-08-15](../proibicoes.md) — diff de baseline visual se
**decodifica**, não se explica por dedução.

Os dois lados mostram **os mesmos dados** ("1 cadastrados", "Todos 1", "0 registros", tabela vazia).
O que muda é cor:

| ponto | baseline | atual | token correspondente |
|---|---|---|---|
| aba ativa (y=136) | `rgb(231,248,253)` | roxo | `--idx-tab-ativa-bg` |
| header/footer da tabela (y=300/520) | `rgb(250,249,248)` | `rgb(244,243,240)` | `--idx-grid-head-bg` |
| badge das abas | `rgb(46,52,55)` escuro | claro | `--idx-badge-cont-bg` |
| **controle y=700 (fora da bbox)** | `rgb(251,250,248)` | **idêntico** | — |

Os três valores da coluna "baseline" são **exatamente** os literais que o #6204 removeu de
[`resources/css/cockpit.css`](../../resources/css/cockpit.css), ao trocar cor literal por derivação
de `--color-primary` (roxo hue 295, [ADR 0190](../decisions/0190-primary-button-roxo-universal-295.md)).
O casamento é o recibo: a divergência **é** o patch de cor.

## A hipótese de fonte externa, refutada

Vigorava a leitura de que a tela renderizaria a partir de uma fonte fora do repo, e que por isso
regenerar a baseline não estabilizaria. Três medições independentes derrubam isso:

1. **Sem fonte externa no controller.** `ProdutoUnificadoController.php` (1.088 linhas):
   `grep -nE "Http::|file_get_contents|curl_|Storage::|fopen|Cache::|Redis::|guzzle"` → **rc=1**
   (ausência real), com controle positivo `business_id|function` → 71 matches, rc=0. Tudo é Eloquent
   escopado por `business_id`.
2. **Render determinístico.** Dois runs distintos, SHAs distintos (`f8e03da08` 16:39Z e `7307454fb`
   16:48Z), produziram a captura de Produto **byte-a-byte idêntica** (`sha256 6a7bbc5af16ad892`).
3. **Relógio já congelado.** `Carbon::setTestNow` no `PixelBaselineTest` mais o `VISREG_FREEZE_CLOCK`
   do workflow — o precedente do JanaCockpit já cobria esse eixo.

O que produzia a impressão de "não estabiliza" é **cadência**, não instabilidade: a tela recebeu
**9 commits que mudam pixel em 8 dias** (18/08 a 25/08), quase todos por [M]. Cada regeneração de
baseline era seguida, em um ou dois dias, por outro pacote visual legítimo.

## A cadeia que fez a dívida cair em terceiros

1. #6204 (25/08 10:04) troca tokens de cor em `cockpit.css` — mudança intencional, "o DS sempre ganha".
2. O gate mede 0,1704% e classifica como zona cinza (τ 0,1%..2%). **O gate acertou** — havia mudança real.
3. O PR passa com o label `visreg-gray-approved`, que aprova o PR e **não regrava a referência**.
4. A divergência fica órfã na `main`.
5. PRs seguintes que atualizam algum `.snap` caem em `raio_confiavel: false` (`reason: contrato-visual`),
   o *fail-closed* reclassifica dívida herdada como própria, e o PR de terceiro é bloqueado.

O passo 5 é **comportamento declarado**, não defeito: a [§5 de 2026-08-24](../proibicoes.md) nomeia
`contrato-visual` como caso em que o bloqueio absoluto continua certo. A separação
dívida própria × herdada já existia (`VisregThreshold::particionaGrayZone`) e documenta um caso
gêmeo anterior (#6175).

## Estado final — verificado, não inferido

[#6253](https://github.com/wagnerra23/oimpresso.com/pull/6253) (modo update) mergeou às 17:40:45Z e a
baseline em `main` é byte-a-byte a captura atual. Nenhum commit tocou a tela ou o CSS depois.

A confirmação veio do run `32881715957` ([#6251](https://github.com/wagnerra23/oimpresso.com/pull/6251)):

- `VISREG_SCOPE: global` — as telas do manifesto comparadas, Produto incluído
- `VISREG_GRAY_APPROVED: 0` — sem label de aprovação
- `✓ it Produto/Unificado bate com a baseline de pixel (núcleo-6)`, sem linha de ratio
- artifact `pixel-diff-views` **não gerado** — nenhuma tela em zona cinza

⚠️ Duas leituras foram descartadas no caminho, e ficam registradas porque são a armadilha desta
investigação: um run com `Skip-as-pass` e outro com `VISREG_SCOPE: targeted` **não** servem de
controle — no primeiro o pixel-diff nem executou, no segundo Produto não foi comparado
([§5 de 2026-08-15](../proibicoes.md)). E o `✓` sozinho não prova ausência de zona cinza: no run
de 16:48 o Produto exibia `✓` **com** 0,1704%, porque zona cinza coleta sem falhar o teste.

## O que NÃO fazer

- **Não criar gate de "baseline junto do código".** A [§5 de 2026-08-20](../proibicoes.md) já mediu
  (78%/92% de disparo) e proíbe re-propor sem re-rodar a contagem — e proíbe nominalmente citar
  "main está vermelha de novo" como motivo.
- **Não tratar o modo update como remédio errado.** Ele funcionou: a baseline que ele assou é a
  captura correta, provada byte-a-byte. O que faltava não era o remédio, era o diagnóstico.

## Nota de método

Nenhum arquivo de código ou de tela foi alterado nesta investigação (`git status --short` vazio,
`rev-list --left-right --count` = `0 0`). [W] pediu explicitamente que o visual da tela não fosse
mudado; a medição já apontava no mesmo sentido — o lado "atual" é o correto.

Três near-misses foram barrados pelas lápides existentes e valem registro por mostrarem que elas
mordem: `grep --hidden` inexistente devolvendo vazio lido como ausência (§5 2026-07-30/07-31),
run com step `skipped` quase usado como controle, e run `targeted` quase lido como prova global.
