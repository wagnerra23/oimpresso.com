---
title: "VRT × ADR 0409 — o modo update contraria a 0409, e o mecanismo do §3 já existe (decisão [W])"
status: proposta
date: "2026-09-21"
owners: [W]
parent_module: governance
related_adrs: [108, 275, 290, 314, 336, 390, 408, 409]
related_specs: []
related_charters: []
---

# VRT × ADR 0409 — medição, classificação e recomendação

> **Escopo:** medir o conflito entre a [ADR 0409](../0409-zero-baseline-de-tolerancia-conformidade-absoluta.md)
> e o modo update do `visual-regression.yml`; classificar os `.snap` pela taxonomia da própria
> 0409; recomendar a reconciliação. **Nada foi executado** — não desarmei gate, não mexi em
> branch protection, não toquei o workflow. O flip é [W].

---

## 0. A 0409 NÃO foi ratificada — e três outras coisas do enunciado caducaram

| afirmação | medido | recibo |
|---|---|---|
| 0409 `status: proposto` | ✅ **confirmado** | `git show origin/main:memory/decisions/0409-*.md` → `status: proposto` |
| lápide do episódio está em `memory/licoes-rejeitadas.md` | ⚠️ **estava em PR aberto às 18:4xZ; mergeou às 18:53:55Z** — ver errata abaixo | [#7662](https://github.com/wagnerra23/oimpresso.com/pull/7662) |
| "dois PRs (#7649, #7657), ambos fechados" | ⚠️ **são três, e um segue ABERTO** | #7621 (run `35610507942`, criado 14:16:08Z) está `open` — [W] fechou #7649/#7657 às 18:16:46Z/18:16:49Z e esse ficou |
| "#7599 foi baseline mergeada hoje" | ⚠️ **é PRÉ-0409 por 24 min** | 0409 entrou em `main` **12:38:58Z** (`27d4ba4ef7c`); #7599 mergeou **12:14:34Z** |

A última linha importa para não acusar o inocente: **nenhuma baseline entrou em `main` depois da
0409** — filtrando os 91 PRs `vrt/baselines-*` por `merged_at > 2026-09-21T12:38:58Z` o resultado
é **0**. A contenção existiu, mas foi **humana** ([W] fechando PR), não mecânica; e ela vazou uma
vez, no #7621.

> ⚠️ **ERRATA DO PRÓPRIO AUTOR (mesmo dia, ~35 min depois), e ela é da classe que este doc mede.**
> A linha 2 da tabela nasceu **medida e correta** — quando rodei, o #7662 estava `OPEN` e a
> entrada não existia em `main`. Ele **mergeou às 18:53:55Z**, e a afirmação virou falsa com o
> documento já publicado no [#7665](https://github.com/wagnerra23/oimpresso.com/pull/7665).
> Fica registrada, não apagada: é exatamente a §5 2026-09-03 (*"lápide que declara um GAP tem
> prazo de validade implícito"*) aplicada a mim — eu abri este doc corrigindo quatro afirmações
> caducas do enunciado e produzi a quinta em meia hora. **A lápide ESTÁ em `main`**
> (`git show origin/main:memory/licoes-rejeitadas.md | grep -c '^### 2026-09-21 — Disparar a
> regeneração de baseline do VRT'` → `1`). Quem citar esta tabela, cite a errata.

---

## 1. Alcance medido

### 1.1 Os artefatos

**104 `.snap`**, todos sob `tests/.pest/snapshots/Browser/CoreScreens/`, em 5 suítes:

| suíte | `.snap` |
|---|---|
| `PixelBaselineTest` | 52 |
| `IsolatedStatesBaselineTest` | 19 |
| `SellsCreateFlowBaselineTest` | 12 |
| `FinanceiroFlowBaselineTest` | 12 |
| `ComprasFlowBaselineTest` | 9 |

`git ls-files '*.snap' | wc -l` = **104** — não há `.snap` fora desse diretório.

### 1.2 Os steps

`visual-regression.yml` tem **1.611 linhas**. Sete steps tocam `.snap` — **nenhum** tem
`continue-on-error`, logo todos reprovam o job:

| step | linha | papel |
|---|---|---|
| Pixel-diff afetadas/núcleo-6 | 562 | CONSOME |
| Estados isolados matriz | 618 | CONSOME |
| Fluxos visuais Financeiro | 645 | CONSOME |
| Fluxos visuais Compras | 669 | CONSOME |
| Fluxos visuais Sells/Create | 697 | CONSOME |
| Regenerar baselines — modo update | 1288 | **REGRAVA** |
| Abrir PR com as baselines — modo update | 1342 | **REGRAVA** |

### 1.3 Os dispatches

**154 dispatches**, o mais antigo de **2026-07-07** (nascimento do modo update, ADR UI-0020 §4) —
ou seja, a janela de 90 dias contém **todos**. Cruzando por `vrt/baselines-<run_id>`:

| | n |
|---|---|
| dispatches | **154** |
| geraram PR | **90** (58,4%) |
| PRs `vrt/baselines-*` no total | **91** (+1 manual, `vrt/baselines-relogio-congelado` → #5852) |
| **mergeados** | **19** (20,9%) |
| não mergeados | 72 |

**Os merges são constantes, não legado em declínio:** 6 em julho · 6 em agosto · **7 em setembro**.
Isto é rotina ativa.

⚠️ **A conclusão do run NÃO prediz se houve PR** — #7649 e #7657 vieram de runs marcados
`cancelled`. O step de abrir PR roda antes de o timeout de 15 min matar o job, então `cancelled`
com PR criado é normal. Cruzar por conclusão daria número errado.

### 1.4 Outros workflows: nenhum

Dos 7 workflows que casam `.snap|snapshot` (`rg --hidden`, contagem idêntica com e sem a flag —
controle da §5 2026-07-30), **só o `visual-regression.yml` consome `.snap` de pixel como
autoridade**. Os outros 6 usam "snapshot" em sentido diferente: custo (`agent-cost-per-pr`),
bundle (`cowork-bundle`), handoff temporal (`governance-gate`), scorecard
(`governance-script-tests`), markdown (`jana-logica-pura-pest`), branch órfã (`mv-metabolismo`).
O `design-smoke-ci.yml` **não** entra na lista — e a razão disso é a seção 3.

---

## 2. Classificação pela taxonomia da 0409

A 0409 separa três objetos: **tolerância** (mascara conformidade) · **contrato** (inventário
deliberado) · **evidência** (vinculada a SHA e execução).

**Os `.snap` não são nenhum dos três de forma limpa, e essa é a resposta.** O inventário
deliberado existe e é outro arquivo (`tests/Browser/visreg-screens.json`,
`tests/Browser/visreg-states.json`) — esses **são** contrato. O `.snap` é a *referência de
comparação*: a unidade de medida, não a lista de erros conhecidos.

Mas a 0409 já descreve o que acontece com ele, na frase que fecha o caso:

> *"snapshots congelados também não provam paridade com o protótipo vivo. Eles provam paridade
> com a captura escolhida."*

Daí a classificação honesta, que é **por estado, não por natureza**:

| estado do `.snap` | objeto da 0409 | quando |
|---|---|---|
| recém-gerado de um render revisado | **evidência** | o que a 0409 quer |
| referência estável contra a qual se mede | **contrato** *de facto* | o que o workflow assume |
| regravado para fechar divergência não diagnosticada | **tolerância** | o que o modo update produz |

**O modo update é o mecanismo que move o artefato da terceira linha para a primeira sem que nada
registre a travessia.** Depois de regravado, o verde seguinte não diz "a tela está certa" — diz
"a tela está igual ao que eu fotografei". É exatamente o que a 0409 chama de mascarar
conformidade, só que por *banda de foto* em vez de *lista de erro*.

Isso não é hipótese: a lápide §5 de **2026-09-02** (`memory/licoes-rejeitadas.md:1428-1429`) já
mediu o dano concreto — 6 dispatches em ~6h, os 6 PRs reprovando, e a causa sendo uma foto de UI
velha aterrissando em `main`. O conserto dela (step *"Alinhar com origin/main"*, linha 173)
endereçou **aquele** vetor (branch atrasada), não a classe.

---

## 3. O §3 do plano de migração já está implementado — e a 0408 proíbe o passo seguinte

O §3 da 0409 pede *"substituir autoridade de snapshot pela comparação protótipo vivo versus
aplicação na mesma execução"*. **Esse mecanismo existe, funciona e roda agendado:**

- `scripts/design/design-diff.mjs` — mesma sonda injetada nos dois lados, veredito por dimensão
  (D2 layout · D4 tipografia · D6 cor · D8 alinhamento · D9 texto);
- `scripts/design/design-diff-lote.mjs` — o mesmo gesto em lote;
- `.github/workflows/design-smoke-ci.yml` — sobe o app efêmero e roda o lote, **semanal**
  (`cron: "40 4 * * 1"`), ligado pela [ADR 0408](../0408-medicao-de-paridade-agendada-advisory-emenda-0290.md).

⚠️ **E aqui está a trava que muda a recomendação inteira.** A 0408 (`status: aceito`, decidida
pelo [W] **no mesmo 2026-09-21**) diz, com todas as letras:

> **Medição de paridade protótipo×prod pode rodar agendada, desde que seja ADVISORY.** O gate
> continua recusado, com o critério de reabertura da 0290 intacto.

E explica por que o motivo endereçado não basta: *"motivo endereçado libera **medir**; critério
cumprido liberaria **bloquear**. Só o primeiro aconteceu."* O critério da
[0290](../0290-fidelity-lock-v0-recusado.md) é **hermetismo** (render-free, sem auth/CDN) — e o
lote é não-hermético por construção.

**Consequência direta:** ler o §3 da 0409 como "promover o `design-diff` a gate" **reabre a
lápide §5 2026-07-09** (*"qualquer comparação visual por render pareado não-hermético, CI ou
agendado, sob qualquer nome"*) e contraria uma ADR `aceito` do mesmo dia. Quem tentar isso vai
gastar a rodada e ser barrado.

Some-se o residual que a própria 0408 declara: das 68 telas `anchored`, **23 são executáveis** e
**58 não têm contrato D0**. O substituto não tem cobertura para assumir a autoridade hoje.

---

## 4. As 5 condições de conformidade × a zona cinza pré-aprovada

A 0409 lista cinco condições para um gate declarar conformidade. Aplicadas ao caminho
`VISREG_GRAY_APPROVED=1` (ligado por decisão [W] de **2026-09-04**, `visual-regression.yml:120`):

| # | condição | veredito | recibo |
|---|---|---|---|
| 1 | executou o detector sobre o escopo declarado | ✅ | o pixel-diff roda e mede; a cinza vai ao step summary + artifact `pixel-diff-views` |
| 2 | controle positivo contra verde por não-execução | ⚠️ **só no L7** | step *"Canário anti-verde-vazio"* (L1420) → `ui-impact.mjs --assert-execution` cruzando `expected`/`executed`/`compared`. **Ele lê só `steps.pixel-diff.outputs.*`** — a suíte de estados tem `id: matriz-states` e o canário **não a consulta**. Ver §4.1 |
| 3 | bite-test que prova que a falha real reprova | ✅ | `tests/Unit/VisregGrayApprovalTest.php` + `scripts/tests/visreg-clock-bite.mjs` (pré-condição do update, L1283) |
| 4 | **não descontou violações por constarem de lista histórica** | ❌ **falha** | a banda do meio não bloqueia: `grayZoneRequiresApproval()` devolve `false` sempre que `VISREG_GRAY_APPROVED=1`, e o env é fixo em `'1'` para todo `pull_request` |
| 5 | publicou evidência reproduzível | ✅ | artifact + step summary + `scripts/tests/snap-diff.mjs` decodifica o `.snap` |

**4 de 5.** A falha é na condição 4 — precisamente a que a 0409 diz ser a que mascara
conformidade.

Duas ressalvas de honestidade, porque mudam o peso do achado:

1. **Tecnicamente a zona cinza é um limiar, não uma lista histórica.** A condição 4 fala de
   "lista"; aqui é banda. O efeito é o mesmo (violação real não reprova), a forma não.
2. **O mecanismo não mente sobre si.** `VisregThreshold.php:651` imprime
   *"Não bloqueou: VISREG_GRAY_APPROVED=1 … Isto NÃO afirma revisão humana — registrado só para
   ficar no log"* — e o comentário ao lado explica que a frase antiga foi trocada justamente por
   anunciar aprovação que não ocorreu. Isso é o oposto da LC-10: é um artefato que se corrigiu.

E a decisão [W] que a ligou tem razão medida: *"todos são aprovados agora, vou usar isso quando o
software estiver maduro"* — com o recibo de que as 3 telas que travavam o #6753 tinham 5 PRs
abertos mexendo nelas. **Não recomendo religar**; recomendo que a 0409, ao ser ratificada, diga
se essa decisão de 09-04 sobrevive a ela ou é superseded, porque hoje as duas coexistem sem
hierarquia declarada.

### 4.1 O buraco da condição 2: BOOTSTRAP silencioso no L2 (estados isolados)

> **Crédito:** achado da sessão `local_76fa61b7` (*"Semear meta no fixture do VRT da Jana"*) —
> a mesma que disparou o modo update hoje e escreveu a lápide do #7662. Ela parou ao entrar
> neste escopo em vez de tocar o arquivo. **Reproduzi independentemente antes de registrar**
> (§5 2026-07-26: relatório de peer é hipótese a testar, não fato a copiar) — confere em tudo.

`VisregThreshold::assertBandedScreenshot()` tem dois ramos quando a baseline não existe
(`tests/Browser/Support/VisregThreshold.php:190-219`):

| ramo | comportamento | veredito |
|---|---|---|
| `$baselineFile !== null` (baseline **contratada**) | `test()->fail("baseline contratada ausente…")` | ✅ correto |
| `$baselineFile === null` | tira screenshot, **grava como baseline** e passa | ⚠️ bootstrap silencioso |

O segundo ramo é deliberado e documentado — *"Suítes sem manifesto: a primeira execução
materializa o snapshot e o publica no artifact para versionamento"*. **Mas a
`IsolatedStatesBaselineTest` TEM manifesto** (`tests/Browser/visreg-states.json`) e mesmo assim
cai nele, porque não contrata `baselineFile` — e ela própria declara isso, em
`IsolatedStatesBaselineTest.php:243`: *"Esta suíte NÃO passa `baselineFile`"*. O passo seguinte
(versionar o `.snap`) nunca teve cobrador.

⚠️ **CORREÇÃO (minha, apontada pelo peer): não é uma suíte — são QUATRO das cinco.** A primeira
redação desta seção tratava o bootstrap como particularidade da suíte de estados. **Falso.**
`assertBandedScreenshot()` tem **5 chamadores** e o parâmetro tem default `?string $baselineFile = null`;
as chamadas são todas por **argumento nomeado**, e só o `PixelBaselineTest` o passa:

| suíte | contrata `baselineFile`? | `.snap` |
|---|---|---|
| `PixelBaselineTest` | ✅ sim | 52 |
| `IsolatedStatesBaselineTest` | ❌ não | 19 |
| `FinanceiroFlowBaselineTest` | ❌ não | 12 |
| `SellsCreateFlowBaselineTest` | ❌ não | 12 |
| `ComprasFlowBaselineTest` | ❌ não | 9 |

**Superfície exposta: 52 de 104 `.snap` — exatamente metade.** O raio *hoje* continua sendo 2
(as fotos das outras suítes existem), mas a distinção importa: **raio ≠ superfície**. Qualquer
`.snap` que suma dessas 4 suítes, e qualquer tela/estado/fluxo novo que entre nelas, bootstrapa
em silêncio — não é condição excepcional da Jana, é o default de metade do acervo.

E o próprio código já dizia, em `VisregThreshold.php:146`: *"quando `$baselineFile` era null
(as 4 suítes de estados/fluxos = 53 dos 59 `.snap`)"*. O fato estava escrito desde 2026-07-16
— só nunca tinha sido ligado à condição 2 da 0409, que nem existia ainda. (O `53 de 59` é o
retrato daquela data; hoje é `52 de 104`.)

**Raio medido em `origin/main` (2026-09-21):** cruzando `screens[*].states[]` do manifesto com o
diretório de snapshots — **21 pares declarados × 19 `.snap` = 2 sem foto**, e são exatamente
`jana · dark` e `jana · empty`. **Zero colateral fora da Jana** (controle positivo:
`sells-index · default` TEM foto).

```bash
node -e "
const m=require('./tests/Browser/visreg-states.json'), fs=require('fs');
const dir='tests/.pest/snapshots/Browser/CoreScreens/IsolatedStatesBaselineTest';
const snaps=new Set(fs.readdirSync(dir));
for(const [t,c] of Object.entries(m.screens)) for(const s of (c.states||[])) {
  const n='it_'+t.replace(/-/g,'_')+'_·_estado_'+s+'_bate_com_a_baseline_isolada.snap';
  if(!snaps.has(n)) console.log('SEM FOTO:', t, '·', s);
}"
```

**Consequência:** no run `35632076822` (PR #7645) os dois saíram `✓ PASS` e não apareceram na
lista de zona cinza — porque não havia contra o que comparar. É verde que **não podia** ficar
vermelho: a [LC-13](../../LICOES_CODE.md) na camada L2, e o lado de *acusação* dela é o eixo que
o §5 2026-07-29 nomeia — colapsar *"não consegui medir"* num estado do objeto medido.

**Recibo por COMPORTAMENTO, não por inventário** (medição do peer, baixando o artifact
`pixel-snapshots` do run `35632076822` e comparando contra o git nas 5 suítes): Compras 9/9 ·
Financeiro 12/12 · Sells 12/12 · Pixel 52/52 → **0 bootstrapados**; `IsolatedStates` **21 no
artifact × 19 no git → 2**. O número bate com o do inventário, agora provado pelo que o runner
de fato produziu — e cobrindo as cinco suítes, não uma.

**Por que isso não se conserta sozinho, e por que entra AQUI:** fazer o ramo reprovar cria um
vermelho cuja única cura é **criar baseline** — que é precisamente o ato que a 0409 restringe e
que o modo update executa. O conserto de forma (*skip explícito* em vez de *pass*, que é o que
o §5 2026-07-29 pede) é decisão técnica; **criar as duas fotos da Jana é decisão [W]**. Os dois
caem no mesmo nó que esta proposta submete.

**Isto NÃO altera a recomendação (B)** — reforça-a: se o `.snap` ausente já produz conformidade
declarada sem medição, a porta [W] no dispatch importa mais, não menos.

**Estado (2026-09-21):** o conserto de forma está no
[#7668](https://github.com/wagnerra23/oimpresso.com/pull/7668) (mesma sessão do achado):
`pass` → `skipped` no ramo, **preservando a escrita do snapshot** — porque a rampa de
versionamento é humana (`ui-impact.mjs:431` instrui a pessoa a copiar o `.snap` do artifact;
nenhum `download-artifact` o consome), e remover a escrita junto trocaria um verde mudo por um
vermelho mudo. O bite-test que prova o conserto está declarado lá: o step tem de sair de
`21 passed` para `19 passed, 2 skipped` — se sair `21 passed` de novo, o conserto é decorativo.
**Os 2 pares seguem sem decisão: criar baseline ou remover do manifesto é [W]** (item 5 da §6).

---

## 5. Achado colateral: o YAML afirma ser required, e não é (LC-10 em produção)

`visual-regression` **não está** entre os 47 contexts required (união
`classic_protection.contexts ∪ rulesets.contexts` — são dois, §5 2026-08-08; controle positivo:
`business_id` casa 1×). Foi demovido por [W] em **2026-08-26**.

O próprio workflow registra isso corretamente **uma vez** (L816, fato datado: *"foi DEMOVIDO de
required em 2026-08-26 … medido aqui em 2026-09-11: 0 de 45+1"*) e aponta ao dono outra vez
(L1185). Mas **sete** afirmações em tempo presente dizem o contrário:

| linha | texto | efeito |
|---|---|---|
| 210 | "o check `visual-regression` já é required" | instrução falsa |
| 558 | "o check `visual-regression` já é required" | instrução falsa |
| 739 | "o job `visual-regression` é required" | instrução falsa |
| 1381 | PR body: *"O gate required deste PR re-roda os testes"* | **sai em todo PR de baseline** |
| 1492 | "O job `visual-regression` JÁ é required" | instrução falsa |
| 1505 | "o check `visual-regression` já é required" | instrução falsa |
| 1511 | "(que já é required)" | instrução falsa |

Não é cosmético. Cinco delas ensinam que *"promover a ENFORCING dispensa clique de branch
protection porque o check já é required"* — e isso é **falso desde 2026-08-26**: remover o
`continue-on-error` de um step faz o **job** reprovar, mas o job não bloqueia merge nenhum. Um
agente que siga essa instrução acredita estar armando um gate e não arma nada. É LC-10 pelo lado
que a lápide §5 2026-08-11 já nomeou, agravado por ser **instrução acionável**, não descrição.

⚠️ **E há um fio solto anterior a tudo isto:** a demoção de 2026-08-26 foi justificada com
*"o render dessas telas não é determinístico"* — e a lápide §5 de **2026-09-02** mediu que essa
inferência estava **invertida** (spread 0,0000% em 16 branches ⇒ determinístico). O gate está
advisory por um diagnóstico depois refutado, e ninguém reabriu a questão. Isso é decisão [W], não
conserto: registro para não virar "descoberta" daqui a três meses.

---

## 6. Recomendação

**Opção recomendada: (B) manter o modo update atrás de porta [W] explícita, e NÃO reescrever pelo §3 agora.**

| opção | veredito | por quê |
|---|---|---|
| **(A) desarmar o modo update** | ❌ não recomendo | ele tem uso legítimo declarado e insubstituível hoje: mudança de **Fundação** aprovada ([W]) — token, dark, shell — em que *toda* baseline fica velha de uma vez e o render canônico só existe no runner. Desarmar sem substituto deixa 104 `.snap` envelhecendo contra `main` e o gate reprovando todo PR de UI |
| **(B) manter atrás de porta [W] explícita** | ✅ **recomendado** | o custo é baixo, o mecanismo continua disponível para a Fundação, e fecha exatamente o vetor que a 0409 nomeia — regravar para fazer passar |
| **(C) reescrever pelo §3** | ❌ **bloqueado por ADR** | a 0408 (`aceito`, mesmo dia) proíbe o gate; a 0290 exige hermetismo, não cumprido; e o substituto cobre 23 de 68 telas, com 58 sem contrato D0 |

### O que (B) significa, concretamente

Sem propor implementação — é [W] quem decide se e como:

1. **O dispatch exige declaração de motivo.** Hoje `inputs` tem só `screens`. Um input
   obrigatório `motivo` (Fundação aprovada / tela nova / outro) que vá para o **corpo do PR**
   transforma o ato numa decisão registrada em vez de um clique. Custo: uma entrada em `inputs`.
2. **O PR de baseline diz o que mudou, não só que mudou.** `scripts/tests/snap-diff.mjs` já
   devolve px alterados · Δmax · células da grade, e a assinatura separa rasterização (Δ≤3) de
   conteúdo (Δ≥200). Rodá-lo sobre o diff e colar no corpo do PR faz a revisão do [W] ser sobre
   **a mudança**, não sobre a palavra "regeneradas".
3. **Corrigir as 7 afirmações da seção 5.** Isso é higiene, não governança — mas a linha 1381
   sai em PR público a cada dispatch.
4. **Fechar o #7621** (ou mergeá-lo deliberadamente). É baseline pós-0409 pendente.
5. **Decidir os 2 pares sem foto da §4.1** (`jana · dark`, `jana · empty`): criar as baselines,
   ou tirar os estados do manifesto, ou fazer o ramo virar *skip explícito*. As três são
   possíveis; as duas primeiras são [W], a terceira é técnica — e a sessão que achou já se
   ofereceu para executá-la.

### O que NÃO recomendo, e por quê

- **Não promover `design-diff` a gate** — reabre §5 2026-07-09 + contraria a 0408 `aceito`.
- **Não religar a zona cinza** — a decisão [W] de 09-04 tem recibo medido; o que falta é a 0409
  dizer se a supersede.
- **Não criar gate que exija `.snap` no diff quando o PR toca tela** — já foi **medido e
  recusado** em §5 2026-08-20 (78%/92% de disparo), e a razão foi de **fase**, não de mérito:
  *"esse gateway vai servir depois das telas já ter meio que se estabelecido. agora eu estou
  criando tudo então muda muito"* ([W]). Nada nesta medição é sinal novo a favor.

### A pergunta que só [W] responde

A 0409 foi escrita para **arquivos de tolerância** (PHPStan, ESLint, grandfathered). O `.snap`
é outro objeto — referência de comparação, não lista de erro. **O §3 dela aplica ao `.snap` com
a mesma força que o §1 aplica ao multi-tenant?** Se sim, (B) é etapa e não destino, e o destino
depende de a 0290 ser reaberta. Se não, (B) é o desenho final e vale dizer isso na ratificação —
porque hoje a 0409 §3 e a 0408 apontam para lados opostos, e a próxima sessão vai ler a que
encontrar primeiro.

---

## Comandos para reproduzir

```bash
R=$(gh repo view --json nameWithOwner --jq .nameWithOwner)

# 1.1 artefatos
git ls-files -- ':(glob)tests/.pest/snapshots/**' | sed 's|/[^/]*$||' | sort | uniq -c | sort -rn

# 1.3 dispatches e PRs
gh api --paginate "repos/$R/actions/workflows/visual-regression.yml/runs?event=workflow_dispatch&per_page=100" \
  --jq '.workflow_runs[] | [.id,.created_at,.conclusion] | @tsv' > d.tsv
gh api --paginate "repos/$R/pulls?state=all&per_page=100" \
  --jq '.[] | select(.head.ref | startswith("vrt/baselines-")) | [.number,.head.ref,.state,(.merged_at // "NAO-MERGEADO")] | @tsv' > p.tsv
awk -F'\t' '$4!="NAO-MERGEADO"' p.tsv | wc -l

# 1.4 workflows (controle: idêntico com e sem --hidden)
rg --hidden -l -e '\.snap' -e 'snapshot' -g '!.git/**' .github/workflows/

# 5. required (união classic ∪ rulesets — são DOIS)
gh api "repos/$R/branches/main/protection" --jq '.required_status_checks.contexts[]' > c1
gh api "repos/$R/rules/branches/main" --jq '.[] | select(.type=="required_status_checks") | .parameters.required_status_checks[].context' > c2
cat c1 c2 | sort -u | grep -i visual
```
