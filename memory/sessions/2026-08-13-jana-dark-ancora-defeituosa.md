# 2026-08-13 — Painel da Jana no escuro, e a âncora que carimbava ✓ mentindo

Sessão longa (~8h). Começou como paridade de tema escuro e virou três frentes: tema, âncora de
design defeituosa, e dois defeitos de governança achados no CI vermelho.

## 1. O pedido — paridade de tema escuro no `/ia`

Chegou um pedido [CC] (rev.2, com errata declarada). A rev.1 tinha acusado o `JanaCockpit` de usar
cor crua; **medido, o componente está limpo** — usa token semântico. A causa é outra e a rev.2
acertou:

**Duas famílias de token colidem no escuro.** O shell veste `--bg/--surface/--accent`; o Painel
veste `--color-*` (Tailwind/shadcn). No claro quase coincidem; no escuro divergem em três pontos:

| papel | cockpit | Tailwind | bate? |
|---|---|---|---|
| fundo / superfície | `.26` / `.30` | idêntico | ✅ |
| roxo da marca | `--accent` `oklch(.55 .15 295)`, **sem override no dark** | `--color-primary` `oklch(.70 .15 295)` | ❌ dois roxos |
| **`--accent`** | o **roxo** | um **cinza** de hover (`.235 .010 240`) | ❌ sentido oposto |
| muted | (n/a) | `.235` — **mais escuro que o card** `.30` | ⚠️ inverte |

O achado acima dos achados: **`--accent` significa duas coisas na mesma página.** Todo
`hover:bg-accent` escrito por quem pensava "accent = roxo da marca" entrega **cinza**. Não é
preferência estética — é armadilha de vocabulário, e reincide em toda tela nova.

Confirmei também o mecanismo que a rev.2 só afirmava: `cn` usa `twMerge`, então `bg-primary/5`
**substitui** o `bg-card` e compõe sobre `.cockpit { background-color: var(--bg) }` = `.26` — mais
escuro que um card. O bloco mais importante da tela afundava.

**Três coisas que o pedido não tinha visto:**

1. **O charter contradizia o item #10.** `Index.charter.md:79` declarava o gradiente
   `violet→fuchsia→pink` como **alvo de UX** (CYCLE-06 G3), enquanto o `eslint-baseline.json` já
   contava as mesmas cores como **6 violações**. Guard dizia dívida, charter dizia alvo. [W]
   decidiu revogar a cor → charter **v5**, com o fato datado preservado.
2. **O irmão do alerta ficou de fora** — o bloco de ticket médio tem o mesmo defeito e o pedido só
   listou o de inadimplência. Entraram os dois (§5 2026-08-03).
3. **O teste de fechamento proposto nunca daria rc=1**: `prose-` casa `rose-`.

## 2. A âncora — o achado que valeu mais que o tema

[W]: *"está ancorado em versão antiga da jana"*. Verdade, e medido:

`jana-merge.jsx` é a âncora oficial (charter + `ancora.mjs`), e carrega **3 defeitos**:

- **P-1** — cita `AnaliseInadimplenciaService` / `AnaliseFaturamentoService` e mais 4. **Nenhum
  existe** (`git grep` rc=1). Catalogado pelo próprio [CC] em 2026-08-09; o PR 0.5 de conserto
  nunca rodou.
- **P-2** — `frota` 8× e `caçamba` 7×. `forbidden_ui_terms: ["locacao","cacamba"]` proíbe os dois,
  e o `dominio-gate` (required) **não pega** porque não varre `prototipo-ui/`. Renderizado: KPI
  "FROTA UTILIZAÇÃO", meta "Utilização de frota", card com linha **`Locadas`**.
- **P-3** — o KPI enfatizado no escuro tem **contraste 2,19:1** (fundo `rgb(255,227,222)` quase
  branco, texto `rgb(249,119,112)`). Reprova AA até no critério de texto grande (3,0:1). Causa:
  `--neg-soft` sem override no dark. Medido com **controle positivo** (branco/preto = 21) — a
  primeira medição dava 1.97 e era lixo.

E o `ancora.mjs` devolvia **`✓`** sobre isso tudo. Estendi ele (não criei máquina paralela — o
arquivo já tratava o caso `n/a` como "sinal de saúde falso", LC-10 no eixo do output):

```
✔ 172 telas com charter · 18 com âncora resolvível
✔ 17 ficam QUIETAS · 1 acusa · 0 não-medido
✔ a única que acusa é /ia — o defeito real
✔ selftest 24/24, com controle negativo em cada perna
```

O bite do caminho inexistente **achou um fail-open meu**: o `read` do `_lib-charter` devolve
`null`, não lança — "arquivo ausente" viraria "0 fantasmas". Foi o teste que pegou.

## 3. Governança — dois defeitos achados no vermelho do CI

**Crash:** `PAGES_NS` virou 1:N em 2026-08-12 (`Whatsapp: ['Whatsapp','Atendimento']` etc). O dono
do mapa tratava array; o `service-scorecard` não. Derrubou o cron `mv-metabolismo` (verde 08→12,
vermelho 13).

**Abri o #5727 e o `dup-detector` acusou duplicação com o #5728** — e estava certo. O #5728 é
melhor: 15 máquinas, 2 consumidores, e pegou a metade **silenciosa** que me escapou
(`vitalByNs.get(<array>)` → `undefined` sem erro → `PaymentGateway` publicado como "sem telas"
tendo 2; Whatsapp 0→11, Forja 0→17). **Cedi**, reverti minha metade e reescopei o #5727.

**A data mentirosa** — esse ficou comigo, e é o mais interessante:

`actions/checkout@v4` sem `fetch-depth` é **raso (1 commit)**. O scorecard deriva frescor de
`git log -1 --format=%cs -- <path>`, e num clone raso o git só enxerga o commit da vez: **todo
arquivo volta datado do dia da run**. O artefato publicado provava — `last_commit: 2026-08-12` em
bloco, quando a verdade é 08-07 (Jana), 08-05 (Financeiro), 07-23… Não era frescor, era a data da
run, e o número saía sempre plausível.

**Sinal discriminante:** clone completo dá **13 datas distintas** em 38 de 39 serviços. Raso daria
**uma só**.

Varredura da classe: 10 scripts derivam data de `git log`; 3 pareciam expostos e **os 3 caíram** ao
abrir o *modo* invocado (`--strict-coverage` mede existência; `knowledge-drift --check` diz no
código *"sem git log"*). Zero exposições novas — mas é **sorte de fiação**: só 2 dos 10 se defendem.

## 4. As duplicatas do Cowork

[W] pediu pra apagar "as antigas". Medido antes:

- **"mesmo nome" ≠ duplicata** — 43 basenames repetidos, mas `Index.tsx` aparece 20× porque toda
  tela tem um
- o **git está limpo**; a duplicação existe só no projeto Cowork vivo — **10 pares**
- e o lado canônico é o **oposto** do que parece: o manifesto tem **187 entradas, ZERO** apontando
  pra `prototipo-ui/`. A **raiz é a fonte**; o eco sob `prototipo-ui/cowork/` é que é redundante

Conferi 3 de 10: `jana-merge.css` (eco mais velho — carrega a regra pré-AA), `forja-integra.jsx`
(coberto pelo git), `forja-data.jsx` (**eco == raiz**; quem está velho é o **git**, atrasado em 7
features). Em nenhum o eco é dono único.

⚠️ Também avisei: se "a antiga" fosse o `chat-jana`, **não apagar** — `jana-merge.jsx:725` importa
`BriefDiario`/`KPICard`/`AnaliseCard` dele via `window` e usa 11 classes `.jc-*`. Apagar mataria a
tela nova junto.

## 5. Render — o espelho incoerente

Pra mostrar a âncora renderizada, montei o shell canônico local. Deu trabalho e revelou a dívida:

- `app.jsx` no git é de **2026-07-07** e monta `window.JanaCockpit` (o componente **antigo**)
- `jana-merge.jsx` desceu em **2026-08-11** (#5572) — **chegou o arquivo, não chegou a fiação**
- **28 arquivos** do espelho são mais novos que o `app.jsx`: Sells (12, de 10/08), Inbox (12/08), Jana
- **13 arquivos** que o shell pede **não existem** no espelho

Por isso o render mostrava a Jana antiga. Não é o `jana-merge` que está velho — é o **espelho que
está incoerente**.

## 6. Pós-handoff — o alarme que eu ignorei 3× era 2/3 FALSO

> Registrado **depois** do handoff ([#5735](https://github.com/wagnerra23/oimpresso.com/pull/5735)),
> que é append-only. Aconteceu no fechamento, por cobrança do [W]: *"tem alguma coisa errada"*.

Durante a sessão inteira o check `crons de governança vivos? (watchdog G6)` ficou vermelho e eu o
dispensei **três vezes** com a mesma frase — *"é o `mv-metabolismo`, some amanhã"* — sem reler o
log. Quando [W] desconfiou e eu finalmente li, o watchdog reportava **quatro** problemas, não um:

```
24 crons agendados · 22 vivos · 2 🔴 MORTOS
🔴 gitleaks-history.yml   MORTO há 24d (limite 10d) — última agendada: 2026-07-20
🔴 governance-drift.yml   MORTO há 28d (limite  3d) — última agendada: 2026-07-16
2 DISPARAM mas FALHAM: governance-drift (2026-07-16) · mv-metabolismo (hoje 10:36Z)
```

Rodando **a query exata do watchdog** (`cron-watchdog.mjs:146`) localmente, minutos depois:

| workflow | watchdog diz | medido local |
|---|---|---|
| `gitleaks-history.yml` | 2026-07-20 · morto 24d | **2026-08-10 · success** (4 runs depois) |
| `governance-drift.yml` | 2026-07-16 · `failure` | **2026-08-13 10:35 · success** (hoje) |
| `mv-metabolismo.yml` | hoje 10:36 · `failure` | **confere** — é o único real |

**2 de 3 vereditos são falsos**, e os dois "mortos" congelaram em meados/fim de **julho**. Hipótese
não-medida: `gh run list --workflow <arquivo>` resolvendo workflow ID antigo em CI (rename/move por
volta daquela data) enquanto o `gh` local resolve o atual. Outras a descartar: token/permissão de
CI, paginação, ordenação não-garantida.

### Por que isso é o pior achado do dia

O watchdog é a sentinela que vigia as outras sentinelas ([ADR 0317](../decisions/0317-maquina-revisao-adr-quando-rever-gatilhos.md) §2 — é a ADR que o próprio script cita; o slug fala de gatilhos de revisão, mas o corpo cobre o heartbeat).
**Falso alarme nele é pior que alarme ausente** — e a prova é o que eu fiz: dispensei o vermelho 3×
como ruído conhecido. Dentro dele havia um alarme dizendo que a **varredura histórica de segredo**
(`gitleaks-history`) estava parada há 24 dias. Era falso, mas **eu não sabia disso: eu não tinha
lido**. Alarme ruidoso vira alarme ignorado, e quem quebrou o ciclo foi o [W], não eu.

Ironia registrada: o §5 já tem lápide de **2026-07-29** sobre este mesmo watchdog — *"instrumento
AFIRMAR verde quando não conseguiu MEDIR"* (fail-open). Agora ele erra pro **outro lado**: afirma
vermelho sobre dado velho. Vale avaliar se aquela lápide precisa de emenda.

**Nota de enforcement:** o check é **advisory** — não está em `governance/required-checks-baseline.json`
nem na união classic+rulesets (44 contexts, zero de cron/watchdog). Não bloqueia merge. Chip aberto
com a medição inteira.

### A forma final do padrão do dia

Todos os erros desta sessão têm a mesma assinatura, e este é o caso mais caro: **formei uma crença
cedo e parei de medir**. Nos outros a crença era sobre um número; aqui era sobre um **alarme** — e
crença sobre alarme se auto-confirma, porque quem decidiu que é ruído não lê mais.

## O que fica pra decidir

- **[W]:** apagar a pasta `prototipo-ui/` no Cowork (só os 10 do eco; a raiz fica)
- **[W]:** ampliar `forbidden_ui_paths` do `dominio-gate` pra varrer protótipo? Esbarra na exceção
  legítima "Caçambas" como razão social do cliente (§5 2026-06-09) — precisa FP medido antes
- **[W]:** valve pro `cron-watchdog` (hoje anuncia uma que não existe — LC-15)
- **aberto:** fechar a classe do clone raso num helper único que os 10 scripts importem
- **aberto:** por que o `cron-watchdog` vê run de 4 semanas atrás como a última (§6) — chip aberto
