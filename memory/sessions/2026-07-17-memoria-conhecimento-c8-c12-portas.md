---
date: "2026-07-17"
topic: "memória-conhecimento: o SHA que ninguém lia (C8) e a flag que virou dado no banco (C12) — portas já redistiladas por sessão paralela"
authors: [C]
prs: [4429, 4431]
outcomes:
  - "C8 no ar (PR #4429): 5º eixo da âncora (temporal) — o verificado@sha que a gramática exigia e ninguém consumia, com guard anti-fabricação e teste e2e contra repo git real"
  - "C12 no ar (PR #4431): supersede detection ligado em staging, provado NO BANCO (event_valid_until 0→1) e não em arquivo, append-only preservado"
  - "Bloqueador herdado (distiller_freshness) já estava resolvido no main por sessão paralela — minha redestilação das 2 portas foi descartada (redundante + inferior à que já estava lá)"
  - "Pendência herdada corrigida em 2 pontos: Jana NÃO reprova hoje (é calendário, não PR) e OficinaAuto reprova igual ao Governance — não estava no handoff"
  - "C8 no ar: 5º eixo da âncora (temporal) — 21 stale · 123 frescas · 298 não-medíveis, com guard anti-fabricação e teste e2e contra repo git real"
  - "Achado maior que o chip: 277 de 442 âncoras (63%) carimbadas com sha que o squash-merge comeu — a convenção do verificado@sha está sistematicamente quebrada"
  - "C12 provado no banco: event_valid_until 0→1 + supersedes_id 0→1 em biz=1, append-only preservado (texto do antigo intacto)"
  - "RAGAS semanal do Jana nunca roda por agendamento: não há scheduler no container staging (chokepoint fantasma reincidindo)"
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0295-bitemporal-event-time-memoria-jana
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
---

# memória-conhecimento — 2026-07-17

Sessão de melhoria da dimensão **memoria-conhecimento** (nota 7,0/10 na
[grade de réguas 2026-07-17](2026-07-17-reguas-grade-truncagem-silenciosa.md)). A tese da grade era
*"os ganhos aqui são ligar o que já existe"* — e os dois chips confirmaram: nada foi construído do zero,
os dois mecanismos já estavam no repo, mudos.

> **Nota de reconciliação (na hora de empurrar):** o diagnóstico herdado mandava redistilar as portas
> Governance/Jana como pré-requisito do C8. Eu redistilei — mas ao subir os PRs descobri que **uma sessão
> paralela já tinha redistilado as duas no main**, com mais profundidade (tabelas de gap apontando pro dono,
> ActionGate fantasma, PRs citados). Minha versão foi **descartada** (redundante e inferior); o bloqueador
> `distiller_freshness` **já estava resolvido no main** quando fui empurrar. Sobraram os 2 PRs de código —
> [#4429](https://github.com/wagnerra23/oimpresso.com/pull/4429) (C8) e
> [#4431](https://github.com/wagnerra23/oimpresso.com/pull/4431) (C12) — e este log. O que segue sobre as
> portas fica como o **diagnóstico** que fiz (medição real, ainda válida); só a *escrita* das portas foi
> jogada fora. É a situação de "sessão paralela na mesma branch" que a memória alerta — pega git como ponte.

## O fio condutor — "o instrumento mente antes do sistema"

A sessão de ontem achou que **o dado estava certo e o veredito mentia**. Hoje a classe apareceu uma
camada abaixo: **o instrumento de medição mentia, e sempre para o lado verde**. Quatro vezes, todas
minhas, todas pegas por controle:

| onde | o que mentia | como pegou |
|---|---|---|
| contrafactual do `distiller_freshness` | `split(/[\\/]/)` colapsou pra `[\/]` no heredoc → a probe nunca casou módulo → devolveu `null` → `stale=0` **por não medir nada** | controle do instrumento (11/11 probes casam) + controle-negativo |
| viabilidade do C8 | `git cat-file -t $sha 2>/dev/null` no `execSync` → **cmd.exe** no Windows → 0/20 SHAs "não resolvem" | teste direto no git: `8af585a` resolvia perfeitamente |
| mutação do C8 | heredoc colapsou `\\n` → a mutação **nunca foi aplicada** → o mutante "passou" | conferir se o replace pegou, em vez de acreditar |
| eixo temporal | `classify()` devolve `sha`, mas `lintSpec` copia o resultado **campo a campo** e eu não copiei `sha`/`segs` | o unknown honesto denunciou (não virou "fresco") |

O padrão: **medição que não mede sai verde**, e verde é indistinguível de sucesso sem controle-negativo.
É a mesma lápide do §5 (2026-07-09 · *"a fase Integração / o gate que não morde"*), só que aplicada ao
próprio ferramental de quem audita. Corolário prático: **todo contrafactual precisa de um controle que
prove que o instrumento casa o alvo** — senão você mede o vazio e chama de evidência.

O heredoc do Bash colapsando `\\` me pegou **3×** na mesma sessão. Mitigação adotada: escrever script com
`Write`, nunca heredoc.

## Achado 1 — a pendência herdada estava certa no Governance e errada nos outros dois

Contrafactual **rodado** (`measureDistillerFreshness` com `newestDocDate` injetado + controle-negativo):

| módulo | `distilled_at` | doc mais novo | atraso | tocar o SPEC hoje |
|---|---|---|---|---|
| **Governance** | 2026-07-09 | 2026-07-15 | 6d | `stale 0→1` — **reprova** ✔ (handoff certo) |
| **Jana** | 2026-07-10 | 2026-07-17 | **7d** | `stale 0→0` — **passa** ✗ (handoff dizia que reprova) |
| **OficinaAuto** | 2026-07-09 | 2026-07-16 | 7d | `stale 0→1` — **reprova** 🔴 (**não estava no handoff**) |
| Sells | 2026-07-10 | 2026-07-16 | 6d | passa |

Jana passa porque `7 > 7` é falso **e** porque o doc mais novo dele já é de hoje — tocá-lo não move a data.
A fragilidade é real, mas o gatilho é **o calendário, não o PR**: a partir de 2026-07-18 qualquer doc do
Jana tocado o leva a 8d. O handoff acertou o risco e errou o mecanismo.

**OficinaAuto é o terceiro caso** e ninguém tinha visto — mesmo `distilled_at: 2026-07-09` do Governance.
Fica como pendência: quem for tocar `OficinaAuto/SPEC.md` reprova o ratchet até a porta ser redistilada.

## Achado 2 — as 2 portas afirmavam no presente um número de 2 meses atrás

> ⚠️ **A escrita desta seção foi descartada** (ver nota de reconciliação acima): a sessão paralela já tinha
> corrigido as duas portas no main. O que segue é o **diagnóstico medido** — que bate com o da sessão
> paralela e vale como registro independente da mesma verdade.

**Governance:** a porta dizia *"grade 49/100 com meta 84"*. O real, medido: **88** — o módulo **passou da
meta**, e o 88 nem é efeito do grader menos cego do #3833 (o Governance era um dos 8 módulos `eq`, já no
piso; `git show 6d7599ea5f` confirma 88 no baseline anterior). É a mesma classe do TeamMcp/Cms de ontem.
O gap real, constrangedor e verdadeiro: **cobertura de âncora 13%** — 40 das 46 US-GOV
sem o campo `**Implementado em:**`. O dono da régua é o pior aluno dela.

Verdade estrutural que faltava na porta: **o grosso da governança não mora em `Modules/Governance/`** —
são 96 `scripts/governance/*.mjs` + 103 workflows. Quem procura o mecanismo dentro do módulo não acha.

**Jana:** a porta (gerada pelo `jana:distill-module-truth`) dizia *"85% das funcionalidades operacionais"* —
número sem dono, sem data e sem régua — e resumia a última mudança como *"foram realizadas auditorias (…)
resultando em melhorias operacionais"*, que não diz nada. Tinha H1 duplicado. O retrato medido:
grade 73 · **cobertura de âncora 91.8%** (a melhor do projeto) · 519 PHP · 14 agents · 39 tools MCP ·
75 commits/30d (o módulo mais ativo).

## Achado 3 (C8) — o projeto cobrava um dado e o jogava fora

A gramática ([ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) §1) **exige**
`verificado@<sha7> (data)` e o `anchor-lint` **reprova quem não põe**. Varredura contada: o SHA é lido em
**2 arquivos / 5 sites** — `SpecAnchorClassifier::GRAMMAR_OK_RE` (que o captura) e
`TaskParserService::deveFecharPorAncora` — e o único uso é **presença** (`is_string($sha) && $sha !== ''`).
Ninguém comparava com o HEAD. Os 4 vereditos existentes (`dead`/`zombie`/`servido`) falam do **presente**;
faltava o passado.

**Medição de estreia (full-tree):** **21 stale · 123 frescas · 298 não-medíveis**.

**O achado é maior que o chip:** dos 298, **277 são `sha_fora_da_ancestralidade`** — ou seja, **63% das
âncoras foram carimbadas com o sha do HEAD da própria branch, e o squash-merge comeu o commit**. O sha
resolve no clone de quem abriu o PR e some no CI. Só sobrevive quem carimba sha **já na main** (medido:
8 dos 20 SHAs distintos do repo). A convenção está sistematicamente quebrada, e o `unknown` é o que
denuncia — por isso ele nunca pode virar "fresco".

Detalhe que fecha o argumento: `git log <sha>..HEAD` sobre sha **não-ancestral não erra — MENTE** (mede
desde o merge-base). Mutação verificada: desligar esse guard faz a âncora virar **`fresco`**. O falso-negativo
seria pior que não ter o eixo.

Bônus: 21 `sem_sha_verificado` são âncoras com **data no lugar do sha** (ex. a própria `US-GOV-045`:
`verificado@2026-07-02`) — fora da gramática, e o eixo as reporta como não-medíveis, não como frescas.

**Por que não é a lápide §5 de 2026-07-09** (*"frescor por `verificado_em` duplica o briefing-code-staleness"*):
não é motor novo (a lápide manda **estender o dono do tema** — e o dono da gramática é o próprio anchor-lint);
a granularidade é inédita (US-âncora × os paths concretos daquela US — `briefing-code-staleness` mede
porta×módulo, `doc-freshness-score` é score por doc, `distiller_freshness` é `distilled_at`×doc); e o que se
**mede** são commits reais, não o campo declarado. **Resíduo honesto:** re-carimbar o sha sem re-verificar
zera o sinal — isto detecta divergência, não desonestidade.

## Achado 4 (C12) — o event-time estava armado e vazio

`JANA_SUPERSEDE_DETECTION_ENABLED=false`. Consequência medida **no banco** antes de tocar em nada:
**0 de 31 fatos** com `event_valid_until` / `event_valid_from` / `supersedes_id`.

Ligado em staging (registrado em `docker/oimpresso-staging/.env.staging.example`, não só no `.env` vivo) e
exercitado pelo **caminho canônico** — `php artisan copiloto:backfill-fatos --business=1 --sync`, em
**biz=1** ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) / R6, nunca em cliente).

**Prova = dado no banco:**

| | antes | depois |
|---|---|---|
| `event_valid_until` (biz=1) | 0 | **1** |
| `event_valid_from` | 0 | **1** |
| `supersedes_id` | 0 | **1** |

Par: fato **#57** supersede o **#4**; janela do antigo fechada em `2026-07-17 11:37:37`. **Append-only
preservado** (o invariante que importa): o texto do #4 segue intacto (102 chars), `valid_from` original de
abril preservado — só as janelas fecharam. Multi-tenant preservado: o novo herdou `business_id=1, user_id=1`
do antigo.

Nota de ambiente: sem `ANTHROPIC_API_KEY` no staging, o primário (Haiku) não é tentado e o detector cai no
fallback `gpt-4o-mini` via `OPENAI_API_KEY` — funcionou.

## Pendências abertas (medidas nesta sessão, não resolvidas)

- **BRIEFING do OficinaAuto** — `distilled_at: 2026-07-09`; tocar `OficinaAuto/SPEC.md` reprova o ratchet
  GT-G3 (contrafactual rodado). Mesmo tratamento das outras duas: **redistilar, não bumpar a data**.
- **O carimbo do `verificado@sha` deve usar sha JÁ na main.** 277 âncoras estão não-medíveis por isso.
  O caminho é `git rev-parse --short=7 origin/main` (ou o merge-base), nunca o HEAD da branch. Vale
  emendar a skill `memory-schema-preflight`/`alinhar-tela`, que hoje só dizem `verificado@<sha7>`.
- **RAGAS semanal do Jana não roda por agendamento** — não existe processo `schedule:work`/`schedule:run`
  no container `oimpresso-staging` e nenhum cron do host o chama; o `jana:ragas-real-eval` "dom 07:00 via
  Kernel.php" nunca é invocado. As rodadas existentes são ad-hoc (última `ran_at 2026-07-12T21:48`), a órfã
  `governance/ragas-real-trend` tem **1 semana só** (2026-06-28) apesar de `first_scheduled: 2026-07-05`, e o
  report atual está em `gate_status: fail` (24/51). É a lápide do **chokepoint fantasma** reincidindo —
  irmã do `negocio-vs-governanca-ratio` "zero cron" de ontem.
- **`servido` (ledger `route-hits.json`) tem 2 páginas no total** (`Produto/Index`, `Site/Login`). Como o
  ledger carrega, toda Page fora dele é marcada "0 hits" — o lint acusa `Jana/Chat` de **não-servido**, o
  que é ausência de cobertura, não prova de não-uso. O eixo `--stale` tem guard pra `unknown`; o `servido`
  não tem o equivalente (o `JUNIT_PARTIAL` existe pro JUnit pela mesma razão). Vale um `sem_cobertura_no_ledger`.
- **`jana_memoria_facts` no staging tem nome de cliente real e valor em BRL** (o `anonymize.sql` aparentemente
  não cobre essa tabela). Não commitei nada disso; fica o ponteiro pro dono avaliar (LGPD).

## Ressalva de honestidade

Esta sessão é governança — entra nos 776, não nos 233 da
[ADR 0334](../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md). O C8 melhora
a régua que mede o trabalho; não move o `servir-o-negócio` (3,75). Os chips eram os pedidos, e a grade os
classificou como "ligar o que já existe" — foi literalmente isso: **zero mecanismo novo**, dois mecanismos
que já estavam pagos e mudos.
