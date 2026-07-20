---
slug: 0344-two-strikes-cobre-processo
number: 344
title: "Loop two-strikes cobre erro de PROCESSO (não só de código); cobertura só-advisory conta como 'sem defesa mecânica'"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-20"
module: governance
quarter: 2026-Q3
tags: [governanca, aprendizado, two-strikes, ledger, advisory, processo, reincidencia]
supersedes: []
superseded_by: []
related: [0256-knowledge-survival-meia-vida-catraca-sentinela, 0224-hooks-block-vs-advisory-claude-4.8-aware, 0094-constituicao-v2-7-camadas-8-principios, 0257-adr-status-lifecycle-kind-modelo-canonico]
pii: false
review_triggers: []
---

# ADR 0344 — Loop two-strikes cobre erro de PROCESSO (não só de código); advisory ≠ defesa mecânica

> **Status:** `aceito` (2026-07-20, redação [CC]; decisão [W]). **O merge deste PR (#4589) = ratificação formal [W]** (R10 — CLAUDE.md §"Como propor mudança"; mesma mecânica das promoções 0327/0336). Origem: [W] 2026-07-20 — *"dá pra ler os últimos erros e ver se viraram aprendizado?"* → raio-X medido → *"promova sim"*. Append-only: **não edito** a [0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) nem o §5 do `proibicoes.md`; esta ADR só **estende o dono do tema** (o ledger two-strikes) pra cobrir uma classe que já existia como prosa.

## Contexto — o raio-X de 2026-07-20

[W] relatou sensação de "sistema instável, erros recorrentes, esquecimentos". Rodei os mecanismos vivos pra **medir** (evidência, não narração):

| Sinal | Medido |
|---|---|
| `gate-selftest.mjs` (as catracas mordem, GT-G6, `required`) | **68/68 mordem** ✔ |
| CI vermelho (últimos 40 runs) | **1 falha** (39/40 verde) |
| reverts / hotfix / incidentes em prod com marcador no git (60d) | **0** |
| ledger de código `LICOES_CODE.md` | 7 lições, todas com gate ou Ocorrências 0–1 |
| aprendizado registrado no §5 do `proibicoes.md` | 1 (mai) → 5 (jun) → **28 (jul)** |

**Diagnóstico:** o loop de **código** está saudável e fechado (CI verde, quase zero revert, lições de código mecanizadas). A dor não é bug de código escapando — é o loop de **processo/comportamento** (os "esquecimentos"). O §5 do `proibicoes.md` registra as lições feito metralhadora (28 num mês), mas **não tem contador de reincidência**. Então uma classe pode reincidir e ninguém vê.

**Prova concreta:** a família **"afirmar/derivar/medir a partir da fonte ou medida errada, sem provar"** aparece como **5 lápides separadas em 3 dias** no §5 (07-15 achado-sem-varredura-contada · 07-16 medir-a-propriedade-errada `.hidden`/`offsetTop` · 07-17 oráculo-errado-restatear-número-de-banco · 07-17 deduzir-quem-roda-parseando-o-Kernel · 07-17 `crontab -l` falso-negativo em host gerenciado). Cada uma virou prosa; **nenhuma acionou o `two-strikes`** — porque o §5 não tem campo `Ocorrências` pra o hook contar.

### Assimetria a corrigir

| Lado | Ledger | Contador + alarme? |
|---|---|---|
| Código / infra | `LICOES_CODE.md` + hook `licoes-code-two-strikes.mjs` | **Sim** (Ocorrências ≥ threshold + `Gate: none` → alarma no SessionStart) |
| Processo / comportamento | §5 `proibicoes.md` (só prosa) | **Não** — reincidência invisível |

O tema do `two-strikes` é *reincidência → defesa mecânica* ([0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md): *derivado+enforçado sobrevive; escrito+lembrado apodrece*). Um erro de processo é instância disso **igual** a um erro de código. Logo o ledger do two-strikes deve cobrir os dois — não é régua nova (o §5 proíbe régua duplicada), é **estender o dono do tema**.

## Decisão

Três partes, todas já implementadas na branch `claude/two-strikes-processo` (diff em `.claude/hooks/licoes-code-two-strikes.mjs` + `memory/LICOES_CODE.md`):

1. **O ledger cobre PROCESSO, não só código.** O `LICOES_CODE.md` (lido pelo hook no SessionStart) passa a admitir classes de **processo/comportamento de agente** (medição, derivação, oráculo, varredura), além de código backend/infra. O §5 do `proibicoes.md` continua sendo a **prosa-evidência** (append-only, intocado); o `LICOES_CODE.md` é o **contador** que torna a reincidência visível pro hook. São **camadas diferentes** (prosa vs contador), não duplicação.

2. **Cobertura só-`advisory` conta como "sem defesa mecânica".** A doutrina two-strikes exige defesa **MECÂNICA** (bloqueia/morde). Um `nudge`/`warn` advisory que a classe atravessa 5× não é defesa mecânica — é evidência de que *falta* uma. O `semGate()` do hook passa a tratar o prefixo declarado `Gate: advisory|parcial|insuficiente <...>` como "sem gate" → **segue alarmando** até virar sonda que morde. Duas guardas contra falso-positivo: (a) um **nome de gate real** que apenas *menciona* "advisory" entre parênteses (ex.: `mutation-gate (advisory, ...)`) **NÃO casa** — só o prefixo no início da string; (b) um advisory que é a decisão **FINAL by-design** (ver §Reconciliação 0224) declara `Gate: advisory-terminal (0224) — <hook>` e o marcador `terminal`/`by-design`/`0224` **sai do alarme** — mecânico, não por convenção lembrada.

3. **Backfill forward-only + oportunístico.** A lápide 2026-07-12 do §5 proíbe big-bang de backfill de legado. Só a classe que está **gritando** entra agora — **LC-08 `afirmar-sem-medir-fonte-certa` (Ocorrências: 5, Gate: advisory)** → dispara o alarme no próximo SessionStart. As demais classes de processo viram `LC-NN` **quando reincidirem**, nunca por varredura em massa.

### Efeito imediato

No próximo início de sessão o banner passa a gritar:

```
=== LICOES [CODE] - gatilho two-strikes (audit loop de aprendizado) ===
  [!] 1 classe(s) repetiram (>= 2x) e NAO tem gate. PROMOVER A DEFESA MECANICA:
      LC-08 - Afirmar/derivar/medir a partir da FONTE ou MEDIDA errada ...  (5x, sem gate)
```

Ou seja: a resposta à pergunta do [W] — *"dá pra ler os últimos erros e ver se viraram aprendizado?"* — passa a ser **sim, e o sistema cobra** quando um erro reincidiu e ainda não virou defesa.

## Reconciliação com a ADR 0224 (advisory NÃO é inútil)

A [0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) estabeleceu — corretamente — que **hook só bloqueia o determinístico-obrigatório** (path match, schema, BOM, PII, `rm -rf`), e que **comportamento/lembrete deve ser advisory** (~80% de aderência, suficiente pro Opus 4.8). Esta ADR **não contradiz** aquilo, e a doutrina aqui **não é** um blanket "advisory vale nada".

As duas são **ortogonais** — regem eixos diferentes:

- **0224 rege a classificação em DESIGN-TIME** de um controle: advisory ~80% é uma decisão TERMINAL válida pra controle comportamental. Onde a 0224 decidiu "isto é advisory por design", isto **permanece** advisory — e por isso o `semGate()` tem o escape `advisory-terminal (0224)` que sai do alarme.
- **0344 rege a contabilidade do LOOP DE APRENDIZADO em EVIDENCE-TIME**: uma classe **provada-reincidente ≥2× sob advisory-só** ainda não chegou à defesa mecânica terminal *daquela classe específica*. Aí a doutrina two-strikes manda **escalar aquela classe** — não porque advisory seja ruim, mas porque **aquele** advisory, **naquela** classe, virou evidência empírica de furo.

**0344 NÃO re-promove nenhum hook que a 0224 tornou advisory**, nem afirma que advisory é errado. 0224 governa a escolha inicial; 0344 governa a exceção guiada por dado, marcável e reversível. O `Gate: advisory` tratado como "sem defesa mecânica" **para fins do contador** é exatamente esse gatilho de escalonamento.

## Consequências

**Melhora:**
- O loop de aprendizado deixa de ser cego a reincidência de **processo** — a assimetria código-tem-contador / processo-só-prosa fecha.
- A pergunta recorrente do [W] (*"os erros viraram aprendizado?"*) ganha resposta mecânica no SessionStart, sem depender de alguém lembrar de auditar o §5.
- O critério "advisory que uma classe atravessa N× precisa escalar" fica explícito e enforçável pelo próprio hook, alinhado à doutrina [0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md).

**Piora / custo honesto:**
- **O elo AINDA é manual:** alguém precisa registrar a `Ocorrência` no ledger. O alarme cobra a promoção, mas não detecta a reincidência sozinho (ver §Escopo não incluído).
- **Ruído potencial no banner:** classes advisory legítimas que ainda não merecem mecânica podem acender o alarme; a mitigação é o threshold (≥2), o escape `advisory-terminal (0224)`, e a curadoria de quando declarar `Gate: advisory` vs um nome de gate real. LC-08 é uma **flag advisory-permanente-by-design até a sonda shipar** — não se espera que fique verde antes disso; desescala sub-comportamento a sub-comportamento.
- **Risco de má-leitura da doutrina:** alguém pode ler "advisory = sem defesa" como blanket e querer bloquear tudo — a §Reconciliação acima existe justamente pra travar essa leitura.

### Guarda-corpos (o cético adversarial exigiu — 2026-07-20)

- **NUNCA promover este contador a gate/catraca `required`** sem antes resolver o auto-feed (§Escopo). O `Ocorrências` é campo mantido À MÃO — a mesma fraqueza que o §5 rejeitou pro `last_validated` (2026-07-01) e `verificado_em` (2026-07-09). É aceitável **aqui** só porque é **advisory** (exit 0, SessionStart) e está honestamente divulgado como elo manual. Como gate bloqueante, cairia direto nessas lápides.
- **Caminho único de atualização do count** (evita drift de duas fontes): quando o §5 do `proibicoes.md` ganhar um 6º tombstone **desta** classe, o `Ocorrências` do LC-08 sobe em lockstep. O LC-08 lista os tombstones **datados** (recibo, não número atemporal) — a regra do próprio LC-08 aplicada a si mesmo.

## Alternativas consideradas

- **Arquivo novo pro contador de processo (vs estender `LICOES_CODE.md`).** Recusada: o §5 proíbe régua/ledger duplicada; um segundo contador criaria dois juízes pro mesmo tema. O caminho canônico é **estender o dono do tema**. O `LICOES_CODE.md` já é lido pelo hook; adicionar escopo de processo é 1 bloco de cabeçalho + entradas `LC-NN`, não um motor novo.
- **`Gate: advisory` conta como "tem gate" (vs conta como "sem gate").** Recusada: seria o oposto da doutrina — um nudge que a classe atravessa 5× marcado como "coberto" silenciaria exatamente o caso que precisa gritar.
- **Feed automático dos "últimos erros" → ledger (vs contador manual agora).** Adiada, não recusada: o loop 100% fechado seria um script que lê CI vermelho / incidentes / degradação de sessão e cruza com o ledger, alarmando classe recorrente sem lição. É a peça grande — fica pra **ADR própria**. Fazer o contador manual primeiro entrega valor hoje sem bloquear o feed automático depois.
- **Backfill em massa das classes de processo do §5 (vs forward-only + oportunístico).** Recusada: colide com a lápide 2026-07-12 do §5 (proíbe big-bang de backfill de legado). Só a classe que grita entra (LC-08); as demais entram quando reincidirem.
- **Dividir LC-08 em 5 sub-classes estreitas silenciáveis (vs 1 família).** Considerada (o cético apontou que ≥2 dos 5 sub-comportamentos já têm defesa mecânica alcançável). Mantida como 1 família porque **o sinal é o padrão de família** (5× a mesma raiz auto-declarada nos próprios tombstones), não 5 defeitos isolados — e os 5 remédios são ortogonais (não se anulam), então NÃO é a falácia "achar a raiz" da lápide 07-15. A desescala é registrada como caminho (Gate desescala quando cada sonda shipar).

## Escopo NÃO incluído (follow-ups honestos)

- **Estado "gateado-mas-vazando" rico:** hoje o contador não distingue "ocorreu antes do gate" de "reincidiu DEPOIS do gate". A família fonte-errada foi modelada como `Gate: advisory` (verdade: os hooks existentes não bloqueiam esta classe) — mas um dia o ledger pode ganhar `Ocorrências-pós-gate` pra medir vazamento com gate bloqueante já no lugar. É também o caminho de partial-credit que **silencia** LC-08 sub-comportamento a sub-comportamento.
- **Feed automático dos "últimos erros" → ledger** (o elo manual descrito acima) — ADR própria.
- **A sonda que MORDE a classe fonte-errada.** O alarme diz "promova a defesa mecânica" — a defesa em si (ex.: bloquear "a raiz é X" sem varredura contada citada; ou o Check T `fact-anchor` pro oráculo-número) é trabalho separado, guiado pelo próprio alarme.

## Recibo de validação (honesto — o cético exigiu)

A **própria proposal** (pré-promoção) dizia *"[W] promove APÓS validar o alarme na prática"*. [W] promoveu na **mesma janela**, sem validação in-session viva — decisão soberana ([W] é dono do domínio, R10). Registro datado, não silêncio: o `selftest` prova a **lógica** (caso `LC-90` advisory-5× → alarme, `licoes-code-two-strikes.test.mjs`); o **não-validado** é a utilidade viva do banner ao longo de sessões reais. Há uma ironia recursiva honesta — ratificar a ADR do LC-08 antes de ver o alarme "medir ao vivo" é a versão-meta do próprio erro que o LC-08 cataloga ("não declarar verificado antes de rodar o oráculo certo"). Fica logado, não apagado.

## Reversão

Trivial: remover LC-08 do `LICOES_CODE.md` e reverter o `semGate()` do hook. Advisory por natureza (exit 0 sempre); **nunca bloqueia merge nem sessão**. Se o alarme provar ruidoso demais, rebaixa-se a classe (ou marca-se `advisory-terminal`) sem tocar o §5 (que preserva a prosa-evidência independentemente).

## Ratificação (R10)

Merge do PR #4589 por [W] = ato de ratificação formal desta decisão (CLAUDE.md §"Como propor mudança" · PROTOCOLO-WAGNER-SEMPRE R10). A implementação (hook + LC-08 + esta ADR) já viaja no mesmo PR; o flip `proposal → aceito` é o próprio merge.
