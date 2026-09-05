---
id: requisitos-design-system-adr-ui-0032-alvo-de-toque-erp-denso-1280
---

# ADR UI-0032 · Alvo de toque no ERP denso a 1280 — qual é o piso, e contra qual norma

- **Status**: proposto
- **Data**: 2026-09-05
- **Decisores**: [W] (decide — é postura de conformidade e história de toque do produto), Claude Code (medição)
- **Categoria**: ui · fundações · acessibilidade
- **Fecha a pergunta aberta em**: [REPAIR-ONDAS-2026-09-04](../../../../../prototipo-ui/design-docs/cowork-inbox/REPAIR-ONDAS-2026-09-04.md)
  (linha A7 e item 6 do resíduo) · [handoff-crm/PEDIDO-CODE](../../../../../prototipo-ui/design-docs/handoff-crm/PEDIDO-CODE.md)
  (linha A7 e item 6) · [COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO](../../../../../prototipo-ui/design-docs/cowork-inbox/COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md)
  (lista de decisões [W])
- **Refs**: [UI-0013](0013-constituicao-ui-v2-camadas.md) (Constituição UI v2 — camada Fundações) ·
  [PRE-MERGE-UI](../../PRE-MERGE-UI.md) · [ADR 0109](../../../../decisions/0109-claude-design-plugin-integrado-processo-mwart.md) e
  [ADR 0241](../../../../decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md) (declaram o alvo **WCAG 2.1 AA**)
- **Supersedes**: nenhuma. Não reabre a [ADR 0165](../../../../decisions/0165-design-system-breakpoints-mobile-first-responsive.md)
  (que fixava ≥44px em `<md`): ela está `status: deprecated` / `lifecycle: arquivado` e não é canon vivo.

---

## 1 · Por que a pergunta reaparece a cada tela

Porque **não existe regra**. Medido em `origin/main` neste turno:

| onde alguém procuraria | ocorrências de alvo/toque/target/WCAG |
|---|---|
| [PRE-MERGE-UI.md](../../PRE-MERGE-UI.md) (a checagem que toda UI roda) | **0** |
| [UI-0013](0013-constituicao-ui-v2-camadas.md) (o documento mãe da UI) | **0** |

Os únicos pisos escritos no repo vivem fora do alcance do ERP desktop: o `≥44px` das personas
**tablet/celular** (`CLAUDE_DESIGN_BRIEFING.md` — *"Técnico Repair … touch targets ≥44px"*,
`Patrimonio.charter.md` — *"Marcos … alvo de toque ≥44px"*), o `min-height: 44px` do template
`PageHeader-canon-v3-1.md`, e o `44×44` do FAB no `CATALOGO_ACABAMENTOS.md`. Nenhum governa
uma grade densa a 1280 num monitor com mouse — que é exatamente o caso da Larissa.

Sem dono, cada auditoria de tela levanta a pergunta de novo e a devolve como "decisão [W]".
Foi o que aconteceu no Repair e no CRM.

---

## 2 · Correção da premissa: a pergunta vinha sendo feita contra a norma errada

| norma | critério | nível | tamanho |
|---|---|---|---|
| **WCAG 2.1** | 2.5.5 Target Size | **AAA** | 44×44 |
| **WCAG 2.2** | 2.5.8 Target Size (Minimum) | **AA** | **24×24**, com exceções |

O projeto declara **WCAG 2.1 AA** como alvo — [ADR 0109](../../../../decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
(F3.5 `design:accessibility-review`), [ADR 0114](../../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md),
[ADR 0241](../../../../decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md) e vários charters.

Consequência dura, e é o nó da questão: **sob 2.1 AA não há requisito nenhum de tamanho de alvo.**
O 24×24 só passa a existir se o projeto adotar **2.2 AA**. E o `44` que a auditoria pré-venda de
mai/2026 cobrou ([02-wcag-manual-5-telas.md](../../../../audits/2026-05-pre-sales/02-wcag-manual-5-telas.md))
está rotulado ali como *"AA (2.5.5 Target Size)"* — **2.5.5 é AAA**, não AA. Aquele rótulo está errado.

Ou seja: os três lugares que hoje cobram tamanho de alvo cobram coisas diferentes, e nenhum
deles corresponde à norma que o projeto diz seguir.

---

## 3 · O que foi medido (e o que explicitamente NÃO foi)

**Como**: `getBoundingClientRect()` no DOM renderizado — nunca classe nem CSS (§5 2026-07-16);
visibilidade por computed style; **duas leituras** com intervalo, aceitando só o que não mudou
(§5 2026-08-24); **controle positivo** antes de citar qualquer número (§5 2026-08-01: alvo isolado
de 20×20 tem de passar pela exceção de espaçamento, par de 16×16 colados tem de reprovar — passou
nos dois sentidos, nomeando o vizinho da colisão).

**Onde**: espelho do protótipo em `prototipo-ui/cowork/` no estado de `origin/main`, servido local,
em iframe com viewport **exatamente 1280×900**, depois do sinal `__oiLazyDone`.

**A exceção de ESPAÇAMENTO da 2.5.8 está implementada** — círculo de diâmetro 24 centrado no alvo
subdimensionado não pode intersectar outro alvo nem o círculo de outro subdimensionado. Sem ela o
número bruto superestima a violação em **ordem de grandeza**, e é o número bruto que vinha sendo
reportado.

| tela (protótipo, 1280×900) | alvos medidos | <24 | <32 | <44 | passam pela exceção | **reprovam a 2.5.8** |
|---|---:|---:|---:|---:|---:|---:|
| Produtos | 189 | 67 | 80 | 159 | 9 | **58** |
| Repair · Folhas de OS | 127 | 49 | 63 | 117 | 12 | **37** |
| Financeiro · Visão unificada | 153 | 29 | 97 | 147 | 28 | **1** |
| Vendas | 96 | 26 | 37 | 94 | 25 | **1** |
| CRM · Painel | 75 | 7 | 20 | 74 | 6 | **1** |
| Estoque · Gestor | 71 | 2 | 15 | 66 | 1 | **1** |
| Repair · Painel / Produção / Reparos | 73 / 63 / 68 | 13 / 3 / 3 | — | — | 12 / 2 / 2 | **1 / 1 / 1** |

Todas as leituras saíram **estáveis** (duas leituras idênticas). A rota `forja` do protótipo entrou na
varredura e saiu com 47 alvos / 0 abaixo de 24 — **mas é página-stub, não conta** (ver abaixo).

**NÃO medido, e dito como tal:**

- **10 das 39 telas do manifesto não renderaram** no harness (âncora ausente): as 4 de `superadmin`,
  `Cliente/Import`, `Cliente/Map`, `Backup`, `Ponto/Espelho/{Index,Show}` e `Sells/CreateV3`. São
  limitações de seed/permissão da lane, **não** resultado — e por isso entram como `NÃO MEDIDA`, nunca
  como zero. Gate mudo é pior que gate ausente.
- **Não medi pelo navegador contra produção**: não há sessão autenticada disponível e eu não digito
  senha. **Nem no CT 100**: o `oimpresso-staging` roda `APP_ENV=staging`, onde a rota de auth-bridge é
  **fail-closed por desenho** — flipar o env de um host público para medir seria trocar uma dúvida por
  um buraco de segurança.

### 3.1 · Produção (o ERP real) — run [33940260653](https://github.com/wagnerra23/oimpresso.com/actions/runs/33940260653), `success`

Lane `e2e-gate` em dispatch na branch `claude/medicao-alvo-de-toque-1280`: Playwright **autenticado**,
viewport 1280×900, tenant seedado biz=1, denominador derivado de `tests/Browser/visreg-screens.json`.
Canário no CI: `com canário: alvos=3 subdim=3 falham=2` — exatamente o esperado.

| | |
|---|---:|
| telas realmente medidas | **29 de 39** |
| alvos medidos | **819** |
| abaixo de 24 | **148** |
| abaixo de 32 | **299** |
| abaixo de 44 | **780** |
| passam pela exceção de espaçamento | **143** |
| **reprovam a 2.5.8 de fato** | **5** |

As 5, em 4 telas — e são **3 formas de componente**, não 5 problemas:

| tela | controle | tamanho |
|---|---|---|
| `Sells/Create` | `BUTTON.text-sm` | 85×20 |
| `Sells/Create` | `BUTTON.absolute` | 16×16 |
| `Jana/Pro` | `BUTTON.text-sm` (o mesmo de Sells/Create) | 85×20 |
| `Jana` | `A` (link) | 183×18 |
| `Modules` | `BUTTON.peer` | 32×18 |

**O número que decide contra o 44:** `780 de 819` alvos ficam abaixo de 44. Um piso de 44 no desktop
reprovaria **95% do ERP**. Ele não é candidato — e é o número que a auditoria de mai/2026 cobrava.

⚠️ **Limite honesto desta medição, e ele corta nos dois sentidos:** o tenant do CI é **magro** — 819
alvos em 29 telas dá ~28 por tela, contra 153 só na Financeiro do protótipo. Tabela com poucas linhas
tem poucos controles de linha, que é justamente onde o protótipo concentra as falhas. Ou seja: **a
produção foi medida com dado ralo e o protótipo com mock cheio**; nenhum dos dois é o volume da
Larissa. O que os dois concordam, e é o que sustenta a decisão, é a **estrutura**: a exceção de
espaçamento absorve 96–97% dos "abaixo de 24", e o resíduo é um punhado de componentes nomeáveis.
- **Forja**: **não existe medição**, nem aqui nem em lugar nenhum. Três documentos afirmam que a
  pergunta está *"aberta também na Forja"* — [handoff-crm/PEDIDO-CODE](../../../../../prototipo-ui/design-docs/handoff-crm/PEDIDO-CODE.md) (linha 213),
  [CODE_NOTES.md](../../../../../CODE_NOTES.md) (linha 150) e
  [REPAIR-ONDAS](../../../../../prototipo-ui/design-docs/cowork-inbox/REPAIR-ONDAS-2026-09-04.md) (linha 283) —
  e **nenhum deles mede a Forja**. Varredura contada de `alvo de toque` no repo inteiro (`git grep`,
  todos os tipos de arquivo): **18 arquivos**; destes, 5 citam "forja", e ao abrir os 5 nenhum traz
  medição — 3 são a mesma frase propagada de um para o outro, e 2 são o rótulo do Tweak *"Alvo de
  toque"* no `app.jsx` do protótipo. É a concordância medindo **cópia**, não realidade.

  A causa apareceu ao medir: a rota `forja` do protótipo é uma **página-stub** (`AÇÕES TÍPICAS` /
  `STACK ATUAL` / `ROADMAP MWART`), não uma tela densa — não havia o que medir. A Forja **tem** tela
  viva em produção (`/forja/aprovacoes`, no manifesto do `visual-regression`), e é só lá que a
  pergunta dela pode ser respondida.

**Duas correções às medições que originaram a pergunta**, para o registro:
1. O `37 de 55` do Repair e o `1 de 35` do CRM foram medidos **no protótipo**, não no ERP.
2. O do CRM não foi medido a 1280 — o próprio doc registra `.cb-root = 841px na janela do preview`.

---

## 4 · O achado que muda a forma da decisão

**(i) O número bruto é quase todo absorvido pela exceção da própria norma.** Fora as duas telas de
grade pesada, o resíduo real é **1 por tela** — e é sempre o mesmo controle: o `⋯ mais N` da sidebar,
com **225×23,95px**. Ele reprova por **cinco centésimos de pixel**.

**(ii) As telas que reprovam de verdade reprovam por CONTROLE DE LINHA, não por tela.** Os 37 do
Repair são `9 linhas × 3 botões de ação` + `9 checkboxes` + 1 do shell. Os 58 de Produtos são
`25 checkboxes` + `25 botões de código` + 4 + 3 + 1. São **duas famílias de componente repetidas**,
não 95 defeitos independentes.

**Corolário que invalida o número como métrica:** ele escala com a **quantidade de linhas do fixture**.
A mesma tela com 9 ou 25 linhas produz 27 ou 50 "violações". Qualquer meta numérica sobre esse número
mede o tamanho do mock.

**(ii-b) Parte do resíduo é REGISTRO, não conserto — a exceção de *controle equivalente*.** A 2.5.8
dispensa o alvo subdimensionado quando *"a função pode ser alcançada por outro controle na mesma
página que atende ao tamanho"*. Medido no Repair · Folhas: a **linha inteira** é alvo conforme
(`role="button"`, `tabindex="0"`, **63,9px**) e abre um drawer (`aria-modal="true"`) com **9 controles,
todos ≥24×24** (`menores24: []`), entre eles `Imprimir` **77×30** e `Alterar status` **105×30**.

| grupo que reprova (Repair · Folhas) | n | classificação |
|---|---:|---|
| `status da folha` 45×21 | 9 | **coberto** — equivalente `Alterar status` 105×30 no drawer da mesma página |
| `imprimir a folha` 56×21 | 9 | **coberto** — equivalente `Imprimir` 77×30 no drawer |
| `editar a folha` 44×21 | 9 | **não coberto** — o drawer não oferece edição (0 campos editáveis, sem menção a editar) |
| checkbox `Selecionar N` 14×14 | 9 | **não coberto** — seleção de linha não tem equivalente |
| `⋯ mais N` do shell 225×23,95 | 1 | **não coberto** — reprova por 0,05px |

Ou seja: dos 37, **18 pedem registro** e o resíduo que pede decisão é **19**, concentrado em
**dois controles** (o botão `editar` da linha e o checkbox de seleção) mais o arredondamento do shell.

Em **Produtos** a linha também é alvo conforme (`role="button"`, 52px), mas **não achei caminho
equivalente** — o clique na linha não abriu drawer. Os 58 de lá ficam **sem** essa dispensa até
alguém medir o contrário.

**(iii) O custo de densidade de subir para 24×24 é ZERO nas telas medidas.** Medido dentro da linha:

| tela | altura da linha | controle que reprova | folga vertical | largura da coluna | cabe 24 sem mexer no layout? |
|---|---:|---|---:|---:|---|
| Repair · Folhas | **63,9px** | botões de ação `45×21,2` | **42,7px** | — | **sim** |
| Repair · Folhas | 63,9px | checkbox `14×14` | 49,9px | — | **sim** |
| Produtos | **52,0px** | botão de código `28,8×15,6` | **36,4px** | 88px | **sim** |
| Produtos | 52,0px | checkbox `13×13` | 39,0px | 44px | **sim** |

Os controles são pequenos **dentro** de linhas que já são altas. Subi-los a 24×24 não muda altura de
linha nem largura de coluna — logo **não muda quantas linhas cabem na dobra**. O trade-off
"acessibilidade × densidade", que é o que fazia disto uma decisão difícil, **não existe no caso medido**.

---

## 5 · Por que o gate de a11y que já existe nunca pegou isto

Não é leniência, é cegueira estrutural — e vale registrar para ninguém procurar bug onde não há:

- `A11yAxeBrowserTest` roda `axe.run()` **com as regras padrão**. Em axe-core 4.12.1 a regra
  `target-size` (tags `wcag22aa`, `wcag258`) vem **`enabled: false`**. Nunca foi avaliada.
- Mesmo habilitada, a lane assere `level: 0` (**critical only**) e `target-size` não é critical.
- `a11y-axe-gate` roda em **jsdom**, que não tem layout engine — `getBoundingClientRect` ali é
  sempre zero; a regra é incomputável por construção.
- `a11y-ratchet` é `jsx-a11y` **estático**; não existe regra de tamanho de alvo nele.

---

## 6 · A escolha (e o que cada lado custa)

### (a) Adotar **WCAG 2.2 AA** para tamanho de alvo — piso **24×24**, com a exceção de espaçamento como regra de leitura

- **Custo de densidade**: **zero** nas telas medidas (§4-iii) — nenhuma linha encolhe, nenhuma coluna cresce.
- **Custo de trabalho em produção**: **5 ocorrências em 3 formas de componente**, em 4 telas
  (`BUTTON.text-sm` 85×20, que aparece em `Sells/Create` **e** `Jana/Pro`; `BUTTON.absolute` 16×16;
  `A` 183×18; `BUTTON.peer` 32×18). Nenhuma é uma tela para reformar — são alturas de 18–20px que
  precisam de 24. É trabalho **por componente**, e cabe num PR pequeno.
- **Custo de trabalho quando a tabela enche**: aí entram os controles de linha que o protótipo
  mostra (checkbox de seleção e botão `editar` de linha). Também por componente, não por tela.
- **O que se ganha**: a pergunta some do inventário de toda tela futura, e o ERP passa a poder
  afirmar 2.2 AA neste critério.
- **O que se aceita**: o alvo declarado do projeto sobe de 2.1 AA para 2.2 AA **neste critério** —
  e uma tela nova que queira alvo menor passa a precisar da exceção escrita, não do silêncio.

### (b) Declarar exceção fundamentada para o ERP desktop denso — nenhum piso

- **Custo de densidade**: zero (nada muda).
- **O que se ganha**: nada mensurável, dado que (a) também custa zero. O argumento de densidade que
  justificaria (b) **foi medido e não se sustenta**.
- **O que se aceita**: manter o `⋯ mais N` a 23,95px e os controles de linha a 13–21px por decisão
  explícita; e escrever o critério (quando vale, quando não) para que a exceção não vire licença geral.

### Recomendação

**(a)** — porque o único motivo para preferir (b) seria proteger densidade, e a densidade **não é
tocada**. Recomendar (b) exigiria eu afirmar um custo que medi e não encontrei.

### O resíduo que continua sendo decisão [W] em qualquer um dos dois casos

**Qual é o piso para tablet/toque?** As personas de toque já estão escritas com `≥44px`
(`CLAUDE_DESIGN_BRIEFING.md`, `Patrimonio.charter.md`), e o técnico do Repair opera em tablet. O
24×24 é piso de **desktop com mouse**; ele não responde pela história de toque do produto. Isto é
decisão de produto, não de técnica, e **não a tomo**.

---

## 7 · O que muda, e onde (só se [W] escolher (a))

1. **A regra passa a viver NESTA ADR**, na camada Fundações da Constituição UI v2. ⚠️ A
   [UI-0013](0013-constituicao-ui-v2-camadas.md) está `accepted` e **não se edita no corpo**
   (append-only, [ADR 0094](../../../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) /
   `proibicoes.md`): esta 0032 **acrescenta** o piso àquela camada por sucessão, sem tocar no
   documento mãe. Se [W] preferir que a 0013 passe a apontar para cá, isso é um `supersedes_partially`
   numa ADR própria — não um `Edit` nela.
2. **[PRE-MERGE-UI.md](../../PRE-MERGE-UI.md)** — um item de checagem, no idioma dos anti-padrões que
   já estão lá. Este **é** editável (é checklist, não ADR), e é o lugar que as telas de fato leem.
3. **Os dois controles** — checkbox de linha e botão `editar` de linha — em PR próprio, com
   antes→depois de altura de linha provando que a densidade não mudou.
4. **O `⋯ mais N` do shell** ([`Sidebar.tsx:616`](../../../../../resources/js/Components/cockpit/Sidebar.tsx)) —
   o mesmo componente existe em produção, e é 1 linha de CSS.

**O que NÃO muda agora: nada vira gate.** A regra canon do projeto é explícita — máquina nova exige
**FP medido antes** de instalar, e este §5 tem 7 lápides de guard sintático que reprovava o legítimo.
Se um dia [W] quiser enforcement, o caminho é **estender o dono do tema** (habilitar `target-size` no
`axe.run()` do `A11yAxeBrowserTest` e subir a lane de `level: 0` para `level: 1`), nunca abrir régua
paralela — e ainda assim medindo o falso-positivo antes, porque `target-size` é `serious` e subir o
nível traz junto tudo que hoje está filtrado.

---

## 8 · Como re-derivar os números (não confie nesta tabela, rode)

- **Lado design**: servir `prototipo-ui/cowork/` e medir em iframe de 1280×900 após `__oiLazyDone`,
  com a sonda de `getBoundingClientRect` + exceção de espaçamento e o controle positivo.
- **Lado produção**: a sonda é [`e2e/alvo-de-toque-medicao.probe.ts`](../../../../../e2e/alvo-de-toque-medicao.probe.ts)
  (report-only, nunca falha, nunca assere limiar). Ela **não roda sozinha**, de propósito: o
  `playwright.config.ts` casa `*.spec.ts`, e a extensão `.probe.ts` a mantém fora da suíte que a lane
  roda em todo PR.
  - **Local**, contra um app servido: `npx playwright test --testMatch="*.probe.ts" e2e/alvo-de-toque-medicao.probe.ts`
  - **No CI** (foi assim que os números de §3.1 saíram, e é o único jeito de ter o app autenticado):
    renomear para `.spec.ts` numa branch descartável, `gh workflow run e2e-gate.yml --ref <branch>`,
    ler o log, e apagar a branch. ⚠️ Registrado porque é um degrau real, não elegância: enquanto
    `e2e:check` não aceitar `--testMatch`, medir produção custa esse rename.

O denominador do lado produção é **derivado** de `tests/Browser/visreg-screens.json` — o mesmo
manifesto que o `visual-regression` já consome — e não uma amostra escolhida a dedo.

---

## 9 · Não-goals

- **Não** subir botão em tela nenhuma antes da decisão: mexer em densidade de tela viva sem aval é
  mudança de produto.
- **Não** criar gate/lint de tamanho de alvo — nem depois da decisão, sem FP medido no corpus real.
- **Não** tratar isto como bug do Repair: a resposta vale para o ERP inteiro, e as telas do Repair
  que não têm grade densa reprovam **1**, igual às demais.
- **Não** usar a contagem bruta de "abaixo de 24" como métrica de nada: ela mede o tamanho do fixture.

---

## 10 · Emenda de medição independente (2026-09-05, sessão paralela)

> Esta seção **não muda a decisão** de §6 — ela a reforça e corrige três coisas nos números.
> Origem: uma segunda sessão mediu o mesmo tema no mesmo dia, sem saber desta ADR (classe LC-19;
> o `whats-active` não foi rodado antes de abrir o trabalho — registrado como o custo real).
> Ao reconciliar as duas medições, apareceu um defeito que atinge **as duas**.

### 10.1 · O número por tela NÃO é estável — ele varia com o scroll

Medido em `Produtos` do protótipo, 1280×900, 25 linhas, mesmo DOM, só mudando `scrollTop` do
`.pd-scroll`:

| estado | alvos | bruto <24 | sonda de espaçamento própria | axe-core `target-size` |
|---|---:|---:|---:|---:|
| topo (`scrollTop=0`) | 191 | **67** | **58** | **22** |
| fundo (`scrollTop=max`) | 191 | **67** | **67** | **30** |

Só o **bruto** é estável. Os dois números de *violação* sobem ao rolar, porque a exceção de
espaçamento depende de **posição**, e o que é vizinho de um alvo muda quando a grade rola sob
cabeçalho fixo. Acumulando os violadores únicos do axe em 8 passos de scroll, o número converge em
**51** e para de crescer.

**Consequência para esta ADR:** os valores de §3 e §3.1 são fotos de **um** estado de rolagem, não
propriedades da tela — o `58` de Produtos e o `5` da produção são **piso**, não total. Isso é a mesma
família do corolário que §4 já registra (*"escala com a quantidade de linhas do fixture"*), com um
segundo mecanismo: **também escala com onde a página está rolada**. Quem re-medir precisa declarar o
estado de scroll, ou acumular rolando.

### 10.2 · A divergência entre as duas medições não é de método

A segunda sessão usou **axe-core** (regra `target-size`) em vez de sonda própria, e chegou a `22` em
Produtos contra os `58` desta ADR. Reconciliado, ponto a ponto:

- **não é versão do axe**: 4.10.2 e **4.12.0** (a do `package-lock.json`) dão **o mesmo 22** no mesmo DOM;
- **não é a regra estar off-by-default**: `runOnly` por id executa regra desabilitada;
- **é (a) o scroll** — 22 no topo, 51 acumulado rolando; e **(b) 7 `span[tabindex]`** que a sonda
  própria conta como alvo e o axe não trata como *target* (`.pd-est-locais` ×4, `.pd-obs` ×3).

`51 + 7 = 58` no estado do topo. **As duas medições concordam**; o que diferia era o estado e a
definição de "alvo". Fica registrado para ninguém tratar isto como contradição entre instrumentos.

### 10.3 · Censo por ÁTOMO compartilhado (37 rotas de sidebar do protótipo)

Complementa §3, que é por tela. Universo: 37 rotas de sidebar medidas (`Atendimento`/`inbox` não
renderizou), 1.237 alvos de conteúdo (shell separado), 340 abaixo de 24 no estado inicial.

Três famílias concentram **312 dos 340 (91,8%)**: botão com rótulo de altura baixa (137, 17 telas) ·
checkbox/radio de linha (108, 8 telas) · ícone-botão quadrado (67, 6 telas).

O que isso acrescenta à decisão — **o checkbox de linha é UM átomo sob SEIS chaves diferentes**
(`.os-cell-check > input`, `.vd-chk > input`, `.pb-chk > input`, `.mfg-row > input`,
`.accent-[…].h-3.5.w-3.5`, e `input` nu). Contado por chave, parece dívida espalhada; contado por
átomo, é **uma** correção. Idem `.jc-updated-b` (113,3×19,3), que aparece em **8 telas** e é o alvo
real do item 6 do pacote CRM.

### 10.4 · Correção factual: o `.pb-kebab` não é dívida de TAMANHO

O `.pb-kebab` vinha sendo citado como átomo compartilhado reincidente **neste** eixo. Medido: o
gatilho dele é `button.os-btn.sm` (`produto-blade.jsx:192` e `:722`), e `.os-btn` **não tem regra
sub-24** — 14 de 14 regras de `height`/`padding` no CSS do protótipo, menor altura declarada **26px**.
O `.pb-kebab` é só o wrapper (`produto-blade.css:160` → `position:relative`).

Ele **é** reincidente e **é** compartilhado, mas no eixo **nome acessível** — item **8** do bloco 7 do
[PEDIDO-CODE](../../../../../prototipo-ui/design-docs/handoff-crm/PEDIDO-CODE.md) (`:179`: svg do
gatilho sem `aria-hidden` + rótulo fixo *"Ações do produto"* aparecendo na grade de leads). O item de
tamanho é o **6** (`:177`), e o alvo dele é o `.jc-updated-b`. São dívidas distintas, com donos
distintos.

Nota de margem, medida junto: `.cli-kebab-btn` (7 telas) fica em **exatamente 24×24** (`padding:5px`
+ ícone 14×14, `clientes-page.css:879-891`). Passa, com folga zero — qualquer redução de padding o
derruba.

### 10.5 · O que a produção acrescenta ao resíduo de §3.1

Dois átomos de produção fora do manifesto medido, confirmados no fonte de `origin/main`:

| onde | tamanho | alcance |
|---|---|---|
| [`Components/ui/checkbox.tsx`](../../../../../resources/js/Components/ui/checkbox.tsx) — `h-4 w-4` | **16×16** | o checkbox do DS; conserto de **uma linha** |
| [`Pages/Site/Login.tsx:143`](../../../../../resources/js/Pages/Site/Login.tsx) — `h-3.5 w-3.5` | **14×14** | rota **pública** — é o achado `L-2` de [mai/2026](../../../../audits/2026-05-pre-sales/02-wcag-manual-5-telas.md), ainda aberto |

O do Login é mensurável **sem sessão autenticada** (rota pública), então não depende do degrau de
infra que §8 descreve.

### 10.6 · Limites desta emenda

1. O censo de 37 rotas cobre **rotas de sidebar**; **sub-views não foram visitadas** — inclusive
   `Repair · Folhas`, que é sub-aba e é justamente onde §3 mede 37. Os dois denominadores são
   parciais e **diferentes**.
2. `Atendimento` (`inbox`) não renderizou nem após retry: **NÃO MEDIDA**, nunca zero.
3. A contagem de `incomplete` do axe **não foi registrada** — o 22/51 é piso também por isso.
4. A exceção **Equivalent** não foi avaliada nesta medição (§4-ii-b a usa, e ela pode dispensar parte
   do resíduo).
5. Nada foi medido em runtime de produção por esta sessão: a linha 10.5 é **leitura de fonte**, e
   para classe Tailwind fixa isso é determinístico — mas o alvo efetivo de um `<label>` que embrulha
   o input **não** é, e exige `getBoundingClientRect`.
