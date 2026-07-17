---
date: "2026-07-17"
topic: "Contrafactual de corpus (C1) + AGENTS.md (F2) — o chip que se auto-refutou pela aritmética, e a porta que serviu stack rejeitada por 71 dias"
authors: [C]
outcomes:
  - "ACHADO PRINCIPAL: replicar o achado do paper custa dezenas a centenas de milhares de runs — o chip, como briefado, é aritmeticamente inviável (rode --power)"
  - "ERRATA no briefing do chip: o paper mede 0,5–2pp, não ~3pp — efeito ~2× MENOR ⇒ replicação 2-4× MAIS CARA"
  - "PODA autorizada [W]: harness de 3 arms apagado (-258 linhas, -41%) — zero invocador + experimento não autorizado (ADR 0105/0334)"
  - "O Monte Carlo do adversário virou guarda permanente de CI (determinístico, ~170ms): prova que a fórmula cumpre o poder que promete"
  - "Desenho DESCARTADO com razão registrada: contrafactual observacional sobre PRs históricos = confundidor (skills disparam por path)"
  - "F2: AGENTS.md serviu stack REJEITADA (Vizra) entre 2026-04-29 e 2026-07-09 — medido em git, não estimado"
  - "F2: eixo 5 de staleness (AGENTS.md × CLAUDE.md ∪ @imports), núcleo reusado, controle-negativo rodado no caminho vivo"
  - "Revisão adversarial derrubou 4 afirmações minhas (fixture fabricado, 2 contagens, 1 apodrecida) — erratas visíveis no corpo"
related_adrs:
  - 0048-framework-agentes-laravel-ai-vizra-rejeitada
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio
---

# Contrafactual de corpus (C1) + AGENTS.md (F2)

Sessão da fraqueza **F1 "corpus de contexto nunca medido" (3/10)** da grade
[2026-07-17](2026-07-17-reguas-grade-truncagem-silenciosa.md). Terminou provando que o
chip, **como foi briefado, não pode ser feito** — e que isso é a entrega, não a falha.

## Diagnóstico verificado (não herdado)

Recibo: sistema **git em `origin/main`**, medido **2026-07-17** (as queries estão no
header do `agent-corpus-counterfactual.mjs`; re-rode em vez de editar os números aqui —
lei §5 2026-07-17).

| alegação do briefing | verificado | resultado |
|---|---|---|
| "zero contrafactual de corpus" | `git grep -ril` contado, sem `head_limit` | ✅ **16 hits, 16/16 são counterfactual DE GATE** ("o gate morde?"), 0 de corpus |
| "75 skills + 11 rules + ~55 hooks" | contado | 🟡 **74** skills (não 75) · 11 rules ✓ · **64** hooks (não ~55) |
| "AGENTS.md sem gate de frescor" | `git grep AGENTS` em `scripts/governance/` + `.github/workflows/` | ✅ **vazio** |
| "ETH derruba ~3%" | WebSearch do arXiv 2602.11988 | 🔴 **ERRADO — ver abaixo** |

> ⚠️ **ERRATA (revisão adversarial, mesmo dia).** Este log nasceu com 4 defeitos, todos
> pegos ao rodar o adversário contra ele. Ficam à vista porque o §5 vive de erro
> catalogado, não de log limpo:
> 1. **"75 skills"** — `ls | wc -l` contou o `_SKILLS-INDEX.md` (o índice **gerado**) como
>    skill. Real: **74**. Corrigi o hook-count do briefing na mesma tabela onde errei o meu.
> 2. **"103 workflows"** — medido em árvore **6 commits atrás** de `origin/main`. Real: **105**.
> 3. **Fixture fabricado** no self-test do eixo 5 (`codeDate: 2026-07-06` **não existe**) →
>    afirmava 71d/28d. Real medido: **73d/30d**, e o fallback escapa por **1 dia exato**.
> 4. **Afirmação em presente que apodreceu em horas** — ver "Anti-fantasma" abaixo.
>
> Causa comum de (2) e (4): **validei canon contra o working tree stale**, que é o
> incidente que o próprio `git-base-freshness-guard` cita (F0 em checkout −46).

## Errata que muda a conta — o efeito é 0,5–2pp, não 3pp

O paper existe e é real: **"Evaluating AGENTS.md: Are Repository-Level Context Files
Helpful for Coding Agents?"** (arXiv 2602.11988, fev/2026 · 438 tarefas: SWE-bench Lite +
AGENTbench 138/12 repos). Mas o número propagou torto:

- **real:** LLM-generated **derruba 0,5–2pp** (0,5 SWE-bench Lite · 2 AGENTbench)
- briefing dizia: "~3%"
- confirmados: developer-written **+4pp** · ambos **+20% de custo**

Não é preciosismo. **n cresce com 1/δ²** — errar 3pp→2pp já multiplica o custo por ~2,25×,
e o piso de 0,5pp por 36×. O briefing fazia o experimento parecer viável.

## O ACHADO — o chip se auto-refuta pela aritmética (`--power`, custo zero)

`node scripts/governance/agent-corpus-counterfactual.mjs --power` (α=5%, poder=80%, p̄=0.5):

| δ alvo | n/braço | runs totais (×3) | o que é |
|---|---|---|---|
| **0,5pp** | 156.978 | **470.934** | piso do paper |
| **2pp** | 9.812 | **29.436** | topo do paper ← o efeito real do nosso arm |
| 5pp | 1.570 | 4.710 | moderado |
| 10pp | 393 | 1.179 | grande |
| **20pp** | **99** | **297** | efeito que sozinho justifica poda ← **CABE** |
| 30pp | 44 | 132 | catastrófico |

E o inverso: **n=5 → MDE 88,6pp · n=10 → 62,6pp · n=100 → 19,8pp.**

**A leitura que decide:** o chip dizia *"se replicar o achado da ETH, a poda vira
obrigação"*. **Replicar custa 29k–471k runs de agente.** Não é falta de disciplina — é
aritmética. O chip, na forma briefada, está morto.

**Mas a decisão não morre — ela muda de forma.** Três caminhos, e nenhum precisa de
replicação:

1. **Aceitar o prior publicado.** O paper já estabeleceu o achado com poder adequado
   (438 tarefas). Nós somos *exatamente* o arm que ele mede: **74 skills · 11 rules · 64
   hooks** (recibo na tabela acima), quase tudo gerado por agente. Replicar seria
   re-provar o provado, caro.
2. **Medir só o que cabe:** "o nosso corpus é *pior que a média estudada* — nocivo o
   bastante pra podar já?" é ≥20pp ⇒ **297 runs**. Caro, mas conceptível.
3. **Não medir e não podar** — mas aí a fraqueza F1 é escolha consciente, não cegueira.

Isso é [W], não meu. O harness existe pra que a escolha seja informada.

## Desenho DESCARTADO — contrafactual observacional sobre PRs históricos

O caminho tentador (e que o briefing sugere ao dizer "estender com dimensão de corpus"):
ler PRs históricos e comparar CFR entre "a sessão carregou a skill X" e "não carregou".

**Não é contrafactual — é confundidor pintado de número.** As skills auto-disparam por
**path** (`preflight-modulo` em `Modules/`, `charter-first` em `.tsx` com charter). PR que
carrega a skill **é** PR de módulo/tela. O contraste mediria **tipo de tarefa**, não efeito
de corpus — e sairia com cara de evidência. É a lápide §5 2026-07-15 (achado derivado de
leitura, sem arm) + a de 2026-06-05 (métrica derivada do que o sistema FAZ, não de
contrato). Por isso o arm é **atribuído**, e a mesma tarefa roda nos 3 braços.

→ **Candidato a §5** (não adicionei por conta própria: o §5 se alimenta de veredito
adversarial/[W], não de raciocínio meu). [W] decide se entra.

## O harness — e por que o veredito se cala

`scripts/governance/agent-corpus-counterfactual.mjs` (advisory, **nunca gate**).

O fio condutor da grade de ontem foi *"o dado está certo, o veredito mente"* (3×: `✅ all
clear` com 123pts de folga · `⛔ bloqueada` com bloqueador fechado · `Crítico` no presente
com o real Bom). Um harness de arms que reportasse *"arm B 80% vs A 60% — B ganha!"* com
n=5 cometeria **exatamente esse pecado, agora com cara de ciência**. Defesas:

- `wilsonCI` por braço (n pequeno quebra o Wald: 0/5 viraria [0,0])
- `newcombeDiffCI` — IC **da diferença**, porque "os 2 ICs se tocam?" erra nos dois sentidos
- `classificarContraste` **só** diz melhor/pior se o IC **exclui 0**; senão `indistinguivel` + o MDE
- fail-safe: `pass` não-booleano → `invalidos`, **nunca** crédito pro corpus

**Teste-âncora (verde):** `n=5, atual 100% vs sem 60% → indistinguivel`, não "atual ganha".
**Teste que prova que não finge poder (verde):** o efeito **real** do paper (2pp) com n=100
→ `indistinguivel` (MDE 19,8pp).

38/38 selftest verde. Wilson conferido contra tabela publicada (5/5 → [0,566, 1,0]).

**Motor reusado, não reescrito:** importa `median` do `agent-pr-outcomes` e
`parseUsageLine`/`custoUSD`/`aggregatePorModelo` do `agent-cost-per-pr`. Arquivo próprio
segue o **precedente vivo** — o `agent-cost-per-pr.mjs` é, ele mesmo, um eixo novo em
arquivo próprio importando do `agent-pr-outcomes.mjs`.

## F2 — AGENTS.md: o incidente é MAIOR e mais preciso que o briefing dizia

Medido em git (não estimado):

| evento | data | commit |
|---|---|---|
| AGENTS.md declara a stack IA "verdade canonica" **com Vizra ADK** | 2026-04-26 | `28549a4819` |
| **ADR 0048 REJEITA a Vizra** | **2026-04-29** | `defc3fc4a6` |
| AGENTS.md corrigido — por **auditoria humana** | 2026-07-09 | `#4017` |

⇒ a porta serviu stack **rejeitada** por **71 dias**. Nada alertou.

E o incidente **prova a escolha do sinal**, replicando a assimetria que o
`briefing-code-staleness` já catalogou: os toques de **2026-06-08 foram MECÂNICOS**
("restaura codebase apagado pelo squash" + MapaTelas gerado) — não refrescaram nada, o
texto seguia de 04-26. Logo:

- sentinela por **data-git** (06-08): gap **28d** < 30 → **passava batido**
- sentinela por **data declarada** (04-26): gap **71d** > 30 → **mordia**

Por isso o `**Atualizado:**` no rodapé do AGENTS.md, e por isso `declaredDoorDate` (reusado).

### A tensão que resolvi — o paper do C1 é *sobre* o AGENTS.md

O bônus pedia "engrossar" o AGENTS.md. Mas o paper do C1 diz que context file **gerado por
LLM derruba desempenho e custa +20%**. Engrossar com prosa de agente é *exatamente* a
intervenção que o paper reprova.

**Resolução:** o AGENTS.md não apodreceu por ser fino — apodreceu **porque RESTATAVA o
canon** (a stack copiada virou mentira 3 dias depois). Cópia apodrece; ponteiro não. Então:
**ponteiro, não prosa.** Adicionei os 3 ponteiros Tier 0 que faltavam (`proibicoes.md`, o
§5, multi-tenant ADR 0093) + `@` não é expandido por Codex/Cursor + regras de escopo da
página. Zero restatement novo. Alinhado com o paper: developer-written **curto e correto**
é o único arm positivo (+4pp).

### O que foi DESCARTADO no F2

Checar por **grep** se o AGENTS.md cita `proibicoes.md`/§5/Tier 0. Dupla falha: (1) é
**presence-gate sobre TEXTO** — família do `last_validated`/§-não-vazio rejeitados em §5
2026-07-09; (2) **não teria pego o incidente-âncora** — a Vizra passou por *conteúdo
errado*, não por ponteiro ausente; um grep de "proibicoes" sai verde com a stack rejeitada
dentro. Ficou a **derivada temporal**, que É o formato do incidente real.

## Anti-fantasma — provado, não narrado

A lápide do "chokepoint fantasma" (guard que o fluxo real não atravessa) é o vetor. Então:

- `selftest-registry-check.mjs` **pegou meu teste como órfão** antes do wiring 🔴→ wirei →
  **0 órfãos** 🟢 (o mecanismo do repo funcionou contra mim)
- **controle-negativo no caminho vivo** (não só no núcleo puro):
  `OIMPRESSO_AGENTSMD_STALE_DAYS=-5` → 🟡 STALE + `::warning` emitido
- contrato advisory provado: `--strict` → exit **1** · sem flag → exit **0** mesmo stale
- regressão dos **4 irmãos** cujos núcleos importei: todos verdes
- `memory-health` exit 0 · `gates-registry` JSON válido

### ⚠️ …e o anti-fantasma pegou A MIM (revisão adversarial)

Duas correções ao que este log afirmava:

**(1) Eu escrevi que o `negocio-vs-governanca-ratio.mjs` tem "zero cron, alarme nunca
dispara".** Isso era verdade quando a grade mediu, e ficou **falso em 2026-07-17 10:35**
([#4410](https://github.com/wagnerra23/oimpresso.com/pull/4410) ligou o alarme com cron
semanal) — horas antes deste log. Afirmação **em presente** sobre estado alheio apodrece
no primeiro flip: é a lápide §5 2026-07-16, que eu **citei no AGENTS.md** e violei aqui.
A forma certa era passado datado: *"na medição da grade (17/07) o script não tinha cron"*.

**(2) O `agent-corpus-counterfactual` É um fantasma parcial — e eu declarei o contrário.**
Medido (`grep -rn -- "--power" .github/ .claude/ package.json app/Console/`): **zero
invocador** pra `--power` e pra `--runs`; só o `--selftest` está wireado. Eu escrevi no
header *"--power: VIVO HOJE"* — "vivo" ali significava "eu consigo digitar", não "algum
caminho real executa". O PR irmão [#4409](https://github.com/wagnerra23/oimpresso.com/pull/4409)
(C11), mergeado no mesmo dia, estabeleceu o padrão que eu não cumpri: mediu o próprio
chokepoint (*"30/30 dos últimos commits tocando .claude/hooks/ vieram por PR"*) pra provar
que o path-filter casa o caminho vivo. O `agents-md-staleness` cumpre (path-filter + cron
do agregador); o `agent-corpus-counterfactual` **não tem chokepoint nenhum**.

Disposição honesta de cada modo:
- `--power`: one-shot legítimo. É aritmética pura (não lê o mundo) — cron repetiria a
  mesma tabela pra sempre. Rodou 1×, entregou o achado, o self-test defende a matemática.
- `--runs`: **especulativo.** Nada o invoca, nada produz `runs.json`, e o experimento que
  ele serve não foi autorizado. ~60% do arquivo (`armStats`/`classificarContraste`/
  `buildReport`/renders/custo) existe pra ele. Pela ADR 0105 (*"backlog só recebe item se
  cliente paga + reporta OU métrica detecta drift"*) isso não deveria ter nascido antes do
  sinal. **Se [W] não autorizar o experimento, o certo é PODAR essa metade** — ver Pendências.

## Isto engordou os 77% de governança? (a resposta honesta é: em parte, sim)

A [ADR 0334](../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)
mede o ratio negócio×governança com `alarme: true`. Esta sessão **é** governança — sem
desculpa. A versão original desta seção se intitulava *"Por que isto NÃO engorda os 77%"* e
se defendia dizendo *"é a única frente cujo produto é subtrair"*. **O adversário derrubou
metade dessa defesa**, e a régua era minha própria frase seguinte: *"Se virar mais uma
régua que só soma, falhou."*

Placar honesto por peça:

| peça | subtrai ou soma? |
|---|---|
| `--power` (a aritmética) | **subtrai** — entregou "não gaste dezenas de milhares de runs" sem gastar nada, e informa a poda do corpus |
| errata do 0,5–2pp | **subtrai** — mata a versão cara do experimento antes dela nascer |
| desenho observacional descartado | **subtrai** — evitou um confundidor com cara de evidência |
| `agents-md-staleness` | **soma**, mas com chokepoint provado (path-filter + cron) e sinal de custo medido |
| ~~metade `--runs` do C1~~ | ~~**só soma**~~ → **PODADA em 2026-07-17** (ver abaixo) |

A última linha falhava o meu próprio critério — e [W] mandou executar a poda no mesmo dia.

### A poda (autorizada por [W], 2026-07-17)

Apagados: `armStats` · `classificarContraste` · `buildReport` · `renderHuman` ·
`renderBriefMd` · agregação de custo via JSONL · modo `--runs` · imports do
`agent-pr-outcomes`/`agent-cost-per-pr`. **−258 linhas (−41%)** no par script+teste.

**Sobreviveu** a aritmética de viabilidade (`minDetectableEffect`/`nNeededFor`/
`renderPower`) — que é o que entregou o achado — mais o decisor (`wilsonCI`/
`newcombeDiffCI`). Julgamento explícito sobre o decisor, porque uma poda literal o
mataria junto: **`nNeededFor` promete "80% de poder" — poder DE QUAL TESTE?** Sem decisor
no repo a promessa é infalsificável e o número vira artigo de fé. Ele é a definição
operacional da promessa, e agora tem consumidor real: o self-test.

**O upgrade que a poda pagou:** o Monte Carlo que o adversário rodou 1× à mão virou
**guarda permanente de CI** — PRNG semeado (mulberry32), determinístico, ~170ms. O
self-test não confere mais a fórmula contra si mesma (tautologia, §5 2026-06-05): ele
**simula**. Prova que `nNeededFor` cumpre os 80% que promete (empírico 79,5%/82,6%/86,1%)
e que o decisor não grita vencedor sob H0 nem em n=5 (4,6–5,7% ≈ α). Isso importa porque
**fórmula que mentisse SUBESTIMARIA o custo do experimento** — exatamente o número que [W]
usa pra decidir se gasta.

O código podado **não se perdeu**: vive no git (`git log --diff-filter=D`). Se [W]
autorizar os ~297 runs, recuperar é barato. Reconstruir antes do sinal é que era caro
(ADR 0105).

**Nomes corrigidos junto** (§5 "o nome mentia"): o step do CI dizia "3 arms · veredito
morde/se-cala" e a entrada do `gates-registry` descrevia o veredito — ambos passaram a
descrever o que o arquivo É, não o que ele foi.

Uma defesa **sobrevive** inteira: por construção nada disso **pode virar gate** — vira
presence-gate proibido se alguém exigir `context_helped` em frontmatter ou grep de skill no
diff. Está escrito no header dos dois scripts.

## Não fiz de propósito

- **Não** criei US em `Governance/SPEC.md` — é o bloqueador que a grade registrou (tocar lá
  sobe `distiller_freshness` e reprova o ratchet required). Construí o mecanismo direto.
  Nenhum path meu está em `memory/requisitos/`.
- **Não** promovi nada a required (§5 2026-07-01 + ADR 0314). Ambos nascem advisory e ficam.
- **Não** renomeei o `name:` do workflow (renomear contexto de check é o vetor do mojibake
  que deadlockou o main em 02/jul) — só o `nome` do registry, que é texto interno.
- **Não** adicionei ao §5 por conta própria (ver "Desenho DESCARTADO" acima).
- **Não** commitei nem abri PR — R10.

## Pendências pra [W]

1. **A decisão de fundo:** aceitar o prior publicado e podar corpus · gastar ~297 runs pro
   ≥20pp · ou não medir conscientemente. O `--power` informa; não decide.
2. ~~PODA da metade `--runs`~~ **FEITA 2026-07-17** ([W] "1 faça"): −258 linhas. Se a
   decisão 1 for pelo caminho dos ~297 runs, o harness volta do git (`--diff-filter=D`).
3. **Task-set**: se for pro caminho dos ~297 runs, as N tarefas + o check de `pass`
   precisam de ratificação — é escopo de negócio, não meu.
4. **§5 — 2 candidatos** (não adicionei: o §5 se alimenta de veredito adversarial/[W], e
   agora o adversário rodou; ainda assim a entrada é sua):
   - o desenho **observacional** descartado (skills disparam por path ⇒ confundidor);
   - **"fixture escolhido pra fechar a narrativa"** — o `codeDate: 2026-07-06` que inventei
     e que só não passou porque o adversário refez a medição. É primo do "oráculo errado"
     (§5 2026-07-17) e de "medir a propriedade errada" (§5 2026-07-16), mas o vetor é
     próprio: **quando dois números coincidem (71 e 71), a coincidência anestesia a
     conferência**. Sinal de alerta: número redondo/coincidente que "fecha" bonito demais.
5. **Errata do 3pp**: se o "~3%" estiver em outro doc, propaga o 0,5–2pp.
6. **Limiar de 30d do eixo 5 é frágil**: o incidente-âncora fica **em cima da linha** (o
   fallback escapa por 1 dia). Se quiser folga: `OIMPRESSO_AGENTSMD_STALE_DAYS=21`.

## O que o adversário mudou neste log

Rodei o adversário contra o meu próprio trabalho (pedido [W], mesmo dia). Ele derrubou 4
afirmações minhas (2 por medição errada, 1 por fabricação de fixture, 1 por apodrecimento
em horas) e **não derrubou** o achado principal: a aritmética do poder foi atacada por
Monte Carlo (4.000 réplicas/célula, o próprio `newcombeDiffCI` decidindo) e sobreviveu —
poder empírico 79,9%/83,7%/87,2% contra os 80% prometidos, e falso-positivo sob H0 entre
3,9% e 5,7% (≈α), inclusive em n=5. Ou seja: **o veredito realmente não grita vencedor no
ruído** — a propriedade central do harness é empírica, não só alegada.

Causa-raiz comum de 2 dos 4: **medi canon contra working tree stale** (−6 commits). O
`git-base-freshness-guard` avisa exatamente isso no SessionStart e eu não obedeci.
