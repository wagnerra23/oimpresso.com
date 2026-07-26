---
date: "2026-07-26"
hour: "16:49 BRT"
topic: "Fronteira de módulo + a classe \"obra parada\" — do bucket órfão à sentinela de entrega"
authors: [W, C]
outcomes:
  - "Whatsapp era 1 de 36 sem owner/trust_required — fechado"
  - "4 tabelas de dono duplo resolvidas (0 conflitos no grafo)"
  - "cron-watchdog ganha eixo 2: mede ENTREGA, não só liveness"
  - "Flag \"obra parada\" no Daily Brief"
  - "selftest-registry-check --scripts (12 de 88 scripts sem invocador)"
  - "Loop de charter destravado: route-hits.json 10 → 32 rotas"
  - "Premissa do Governance v4 REFUTADA por medição"
prs: [4795, 4798]
related_adrs:
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0275-calendario-promocao-gates-sdd
---

# Sessão 2026-07-26 — fronteira de módulo e a classe "obra parada"

## TL;DR

Começou em "como os módulos são divididos e quem manda neles" e terminou numa **classe de falha que o sistema inteiro não enxergava**: obra construída, aprovada por ADR, com cron e tela — e parada há 71 dias sem que nenhuma das 34 catracas required visse. Porque **gate roda sobre diff, e coisa parada não tem diff**.

3 PRs: [#4795](https://github.com/wagnerra23/oimpresso.com/pull/4795) (fronteira de módulo, mergeado) · [#4798](https://github.com/wagnerra23/oimpresso.com/pull/4798) (sentinela de entrega + destravamento do loop de charter) · auditoria adversarial em subagente.

## Parte 1 — fronteira de módulo (#4795, mergeado)

Três furos medidos nos 36 módulos:

| Furo | Antes | Depois |
|---|---|---|
| `Modules/Whatsapp/SCOPE.md` sem `owner` nem `trust_required` | 1 de 36 | **0** |
| Tabelas com dois donos declarados | 4 | **0** |
| Buckets declarados com rubrica | 2 de 5 | **3 de 5** |

**Como o empate de dono foi desfeito:** pela migration. Nenhum dos dois "donos" de `mcp_tasks`/`mcp_components`/`mcp_inbox_notifications` a criava — quem cria é `Modules/Jana`. Regra aplicada e escrita nos arquivos: **dono = quem define o schema; UI admin de outro módulo = consumidor.**

**Correção de rota importante:** eu ia reclassificar Governance/Auditoria/Admin para `meta_governance`. Ao abrir os `module.json`, achei `bucket_assigned_by: "[W]"` com justificativa datada — era **decisão do dono**, não erro. O que estava desalinhado era o arquivo de rubrica (escrito na W24 para esses 3 módulos, enquanto a W27 renomeou o bucket deles). A rubrica foi para o nome declarado, não o contrário.

## Parte 2 — a classe "obra parada" (#4798)

### O caso

`Governance v4 / Scoped Scorecards` (ADR 0160): ADR aceita, cron diário 07:00 (`governance:scorecard-snapshot --alert`), tela `/admin/governance/v4`, ~10 testes. E 5 de 36 scorecards, 2 de 5 rubricas, `ModuleGradeService::gradeV4()` com **zero call sites**, YAMLs congelados em `last_grade_at: 2026-05-16`.

Descoberto **por acaso** numa conversa, não por aviso.

### Por que a premissa do v4 é falsa (medido)

O `_INDEX.md` justificava buckets com *"a mesma rubrica castiga injustamente os meta-módulos"*. Os dados dizem o contrário:

| bucket | n | média v3 | `client_real` |
|---|--:|--:|--:|
| `cross_cutting_infra` | 7 | **82,1** ← maior | **100%** |
| `vertical_client_facing` | 5 | 81,6 | 68% |
| `functional_horizontal` | 21 | 80,0 | 75% |
| *(geral)* | 36 | 80,2 | — |

Infra vai **acima** da média, e gabarita justamente a dimensão que a rubrica v4 corta de 15 para 5 pontos. Simulando a rubrica v4 nos 3 módulos: **−2, −2, 0**. Todo o aparato move ≤2 pontos, para baixo.

E o v3 já resolve melhor: `fsm_n_a` em 16 módulos (13 com razão escrita) ajusta **por dimensão** — mais fino que por bucket.

### A máquina construída

**Eixo 2 do `cron-watchdog`** — "o cron roda" ≠ "o cron entrega". Liveness só cobre os 24 workflows do GitHub; os **76 schedules Laravel** não têm API de liveness, e o cron do caso *estava vivo*. Então mede a **consequência**: artefato de estado versionado que envelheceu.

Cobertura honesta: **14 de 290** arquivos declaram data interna. Os outros 276 não são acusados — não se mede idade do que não se data.

**Flag no Daily Brief** (`ObraParadaBriefLineService`, espelhando `AdrPendenteBriefLineService`):
`🟠 Obra parada: N artefato(s) sem atualizar — pior: <arquivo> (Nd)`

### O que a auditoria adversarial achou (sobre o próprio PR)

- **A sentinela nova era cega** para `governance/route-hits.json` — chave `ultima_data` faltando. Grave porque ele alimenta **dois** gates required, e é **aditivo**: dado velho deixa o gate mais **permissivo**, nunca mais vermelho.
- **`selftest-registry-check` estava verde** com 0 testes órfãos enquanto **12 de 88 scripts** não têm invocador. Regra certa, superfície errada.

## Parte 3 — o loop de charter destravado

Diagnóstico mudou **duas vezes**, e só a medição resolveu:

1. Supus que `ROUTE_HITS_ENABLED` (default `false` no config) nunca fora ligado. **Errado** — em prod: `ROUTE_HITS_ENABLED=true`, `APP_ENV="live"`.
2. O gargalo era o **elo 3**: `route-hits:export` é manual por design, e o último rodou em 11/07. Dry-run em prod: **32 rotas e 11 pages** até 25/07, contra 10 rotas e 2 pages no JSON commitado. Quinze dias de dado represado — a coleta funcionava, o transporte é que parou.

Atualizado o JSON (capturado por dry-run, gravado local — `--write` no host seria drift), o promotor passou de ~2 para **6 promovíveis**, aplicados e validados: `charter-live-signal --check` → **live 56 · com sinal 56 · SEM sinal 0**.

Os 179 drafts restantes **não são dívida** — são telas sem hit em prod.

## Erros meus nesta sessão (todos medidos, todos corrigidos)

| Erro | Como apareceu | Defesa que virou |
|---|---|---|
| `execSync` não importado → corpus vazio → **"88 de 88 órfãos"** | saída com cara de resultado legítimo | **guarda anti-vácuo**: corpus vazio sai com exit 2 dizendo *"é o instrumento quebrado, não achado"* + controle-negativo no selftest |
| Chave de data só em inglês → sentinela via 12 de 13 | `jana-ragas-real-baseline.json` usa `gerado_em` | chaves PT-BR + caso de selftest |
| `import` do watchdog disparava o script inteiro (incl. chamadas `gh`) | ao exportar o núcleo para testar | guarda `EH_MAIN` no entrypoint |
| Chamei de "heurística" o que era **zero dimensões** | li o `_INDEX.md` em vez do `loadBucketConfig()` | — |
| Afirmei "v4 não tem consumidor" | não perguntei ao scheduler | `schedule:list` mostrou o cron 07:00 usando o avaliador |
| Ia reclassificar 3 módulos | não tinha aberto os `module.json` | `bucket_assigned_by: "[W]"` — era decisão do dono |

Padrão comum: **deduzir de leitura quando havia oráculo à mão** (LC-08). Em todos, quem corrigiu foi rodar o instrumento certo.

## O que ficou aberto (decisão [W])

1. **v3 × v4** — a medição diz aposentar o v4 (premissa refutada, move ≤2pp, `gradeV4()` sem call sites). Mas blast radius real: tela `/admin/governance/v4`, 3 Requests, ~10 testes, **ADRs 0160/0161/0163 accepted** (append-only → exige ADR de supersede), 1 cron.
2. **Os 5 scorecards parados** — enquanto não forem atualizados ou o v4 aposentado, o check advisory `crons de governança vivos?` fica vermelho em todo PR.
3. **Agendar `route-hits:export`** — hoje manual **por design declarado** no Kernel. Mudar é decisão de design, não conserto.
4. **`meta_governance.yaml`** — órfão (0 módulos o declaram). Deletar exige ajustar `Wave27GovernanceSaturateTest` no mesmo PR.
5. **Os 12 scripts sem invocador** — o report-only lista; decidir caso a caso: ligar ou aposentar com lápide.

## Estado MCP no fechamento

- `cycles-active` → nenhum cycle ativo em COPI
- `my-work` → 6 tasks em REVIEW (US-TR-309, US-TR-310, US-PG-008, US-PROD-027, US-TR-305, US-TR-306)

---

## Continuação (sessão seguinte, mesmo dia) — o controle negativo que fecha o item 1

> Gatilho: o advisory `crons de governança vivos?` falhando em **100% dos PRs** (#4800, #4805,
> #4815). Escopo pedido: achar quem deveria escrever cada um dos 5 scorecards, por que pararam, e
> decidir consertar × aposentar. Um doc só pro tema (não abri session log paralelo — §5 2026-06-05).

**Os dois primeiros itens confirmaram o que #4798 já tinha corrigido no `_template.yaml`:**
varredura contada de escritores → **zero** para `memory/governance/scorecards/*.yaml`;
`schedule:list` no CT 100 → os crons de 06:05 e 07:00 são **leitores**. E `git log` (clone
desrasado antes de medir) mostra que os 5 **nunca andaram**: nasceram nas Waves 23-26 e o único
toque posterior foi a deleção pelo squash do #2413 + restauração.

**O terceiro item trouxe achado novo — o v4 não mede nada.** Rodado no CT 100 com **controle
negativo** (avaliar cada módulo com o YAML curado × com `[]`), depois de provar por `md5` que os
5 arquivos do container são idênticos aos do repo:

| módulo | bucket | COM o YAML | SEM o YAML |
|---|---|---:|---:|
| Admin · Auditoria · Governance | `cross_cutting_infra` | 17 | **17** |
| Vestuario | `vertical_client_facing` | 20 | **20** |
| ComunicacaoVisual | `vertical_client_facing` | 91 | 20 |

**4 dos 5 são irrelevantes** — apagar o arquivo daria o mesmo número. Vocabulário incompatível:
declaram as dimensões da rubrica **v3** (`D1_models`, `C1_coerencia`) e o `evaluateScorecard()`
itera as chaves do **bucket** (`multi_tenant`, `pest_coverage`) → cai em `?? 0` / `?? target`.

E o motor que mediria o código está desligado **nas duas pontas**: `detectRule()` (11 detectores)
tem **zero call sites em produção** (4 chamadas, todas em teste) e os 3 buckets declaram **zero**
blocos `detect`. Logo o score é constante → o `--alert` de drift `>=5pts` **nunca pode disparar**.
É a lápide do `jana:drift-sentinel` (§5 2026-07-17) de novo, em outro mecanismo: *todos os pontos
idênticos ⇒ o problema não é o baseline, é o medidor.* Efeito colateral que ninguém via: o cron
persiste **todo dia** `17/100` para Governance e `20/100` para Vestuario em `mcp_scorecard_runs`
(o v3 dá média 80,2) — número falso alimentando `/admin/governance/v4`, tela com **0 hits** no
ledger `route-hits` (janela até 25/07; export manual, então é sinal de rota fria, não prova).

**O que saiu daqui**

- [`proposals/2026-07-26-deprecar-governance-v4-scoped-scorecards.md`](../decisions/proposals/2026-07-26-deprecar-governance-v4-scoped-scorecards.md)
  — deprecação faseada F1→F4, alternativas (incl. *ligar o `detectRule`*, viável se [W] preferir),
  blast radius contado (13 arquivos de teste) e gate de reversão. **Ratificação é ato de [W]** —
  0160/0161/0163 estão `accepted`.
- **Errata no `cron-watchdog.mjs`**: a mensagem do eixo 2 dizia *"alguma automação deveria manter
  vivo"* e mandava *"ache quem deveria escrever"* — afirmação que o watchdog **não pode sustentar**
  (ele mede idade, e idade não revela autoria). Mandava caçar culpado inexistente. Agora nomeia os
  3 casos possíveis (cron que parou de entregar · curadoria envelhecida · mecanismo morto) e diz
  como distinguir (varrer escritores do path). Detecção **idêntica** — nada de allowlist por
  nome/pasta, que é o critério sintático já morto 4× no §5. Selftest segue 9/9.

**Correção de rota minha, registrada:** ia usar `git log` como recibo de datas num clone **shallow**
— o hook `block-instrumento-sem-porta-viva` (P3) mordeu e estava certo. Desrasei antes de medir.
E a 1ª rodada do controle negativo deu `score=0` para os 3 meta-módulos: era o container staging
em 23/07, **sem** o `cross_cutting_infra.yaml` (nasceu no #4795, hoje). Copiei o bucket, re-medi
(17), e restaurei o staging ao estado original. O número da 1ª rodada era do ambiente, não do
mecanismo — a mesma família de "medir a fonte errada" (LC-08).
