# Estado da arte — quais arquivos devem existir por TELA e por MÓDULO (2026)

**Data:** 2026-07-17 · **Agente:** `estado-da-arte` · **Pedido [W]:** *"pesquise quais arquivos deveriam existir"*
**Método:** Fase 1 pesquisa limpa (11 WebSearch/Fetch, sem ler o repo) → Fase 2 comparação com o repo → Fase 3 gaps.
**Caso-teste obrigatório:** o erro de hoje (`kb/Index.v2.charter.md` afirmou **3.016 documentos** medindo o disco; o banco tem **1.408**).

---

## Veredito em 1 parágrafo (leia só isto se for ler uma coisa)

**Os arquivos certos já existem. Não falta artefato — falta uma lei sobre o que vai DENTRO deles.**
O oimpresso tem hoje o conjunto que a indústria convergiu em 2026, e em 3 pontos tem mais do que os
líderes. O erro de hoje não foi um documento faltando: foi um documento **repetindo um número que
outro sistema já sabia melhor**. E o projeto **já aprendeu essa lei ontem** — a lápide de
[2026-07-16](../proibicoes.md) diz, sobre status de gate: *"quem precisar falar de enforcement
**aponta pro dono, não restateia**"*. O charter do KB cometeu a mesma doença 24h depois, no eixo
"dado" em vez de "enforcement". **A recomendação é generalizar a lei que já existe, não construir
máquina nova** — e isso é subtração, alinhado com ADR 0271/0314.

---

# 1. PESQUISA — quem é referência em 2026 e como resolve

| Player | Quem é | Mecanismo concreto | Por que é referência |
|---|---|---|---|
| **GitHub Spec Kit** | Toolkit OSS da GitHub p/ spec-driven development | 4 artefatos: `constitution.md` (princípios não-negociáveis do projeto) → `spec.md` (o quê) → `plan.md` (arquitetura) → `tasks.md` (unidades testáveis). Comandos `/specify`, `/plan`, `/tasks`, `/implement` | Define o vocabulário que o resto do mercado copiou. O `constitution.md` é o achado: princípios ficam FORA da spec, carregam sempre |
| **AWS Kiro** | IDE agêntico da Amazon, spec-first | 3 arquivos por feature: `requirements.md` (user stories + critério em notação **EARS**), `design.md` (arquitetura, contratos, sequência), `tasks.md` (tarefas atômicas, cada uma revertível). Mais **steering files** = conhecimento de longa vida (padrões, domínio) carregado em toda interação | Único que separa formalmente **conhecimento permanente** (steering) de **spec efêmera** (requirements/design/tasks descartáveis pós-merge) |
| **Tessl** (~US$125M) | Aposta mais radical: "spec-as-source", código vira artefato de build | Spec Registry (10k+ specs de libs OSS pra agente não alucinar API) + framework de regeneração | Referência pela **tese** e pelo **fracasso parcial honesto**: o motor de regeneração segue closed beta, JS-only, e [review 2026](https://codemyspec.com/blog/tessl-review) mede como **não-determinístico**. Prova que "código regenerável da spec" ainda não fecha |
| **Fiberplane Drift** + **Spec Growth Engine** ([arXiv 2606.27045](https://arxiv.org/html/2606.27045), jun/2026) | Estado da arte em **anti-apodrecimento** | Drift: o doc declara **âncoras** pra símbolos de código; tree-sitter faz fingerprint de AST normalizado; código muda → `drift check` marca o doc stale e **bloqueia merge**. Spec Growth: deriva **Intent Graph** (dos SPEC.md) vs **Evidence Graph** (imports/rotas/testes reais) e hard-fail se divergirem | É o mecanismo que resolve "doc apodrece" sem presence-gate — mede **derivada**, não presença. (Spec Growth é design paper: **zero validação empírica**, o próprio autor admite) |
| **Literatura de provenance** ([arXiv 2603.10060](https://arxiv.org/pdf/2603.10060) "Tool Receipts", [2606.04990](https://arxiv.org/html/2606.04990) survey) | Pesquisa 2026 sobre agente que afirma fato | Classifica **cada afirmação pela fonte epistêmica**: saída direta de tool · inferência · testemunho externo · ausência · **infundada**. "Alice te mandou 3 emails" = direto (tool rodou); "Alice parece preocupada" = inferência | **É a única literatura que descreve o erro de hoje.** Ver §4 |

**Convergência forte (o que todos fazem):** instruções do agente em arquivo versionado (`AGENTS.md` — 60k+ projetos, doado por OpenAI à **Agentic AI Foundation**/Linux Foundation junto do MCP da Anthropic); princípios não-negociáveis separados da spec; critério de aceite executável; ADR sobrevivendo (Nygard/MADR/Log4brains — MADR é "overhead pra time maduro, ajuda time imaturo").

**O que o mercado ABANDONOU** (fontes: [Augment Code](https://www.augmentcode.com/blog/what-spec-driven-development-gets-wrong), [Falconer](https://falconer.com/guides/replace-confluence-without-losing-docs), Atlassian Community): wiki/Confluence solto (*">94% do conteúdo não é tocado num mês; sem poda, o apodrecimento ganha"*); design doc manual; changelog e diagrama de arquitetura escritos à mão; onboarding wiki. Diagnóstico que todos repetem: **"manter artefato escrito em sincronia com sistema que muda é custo contínuo"**. E o agravante da era do agente: *"spec stale engana o agente, que não sabe. Ele executa o plano com confiança e não avisa que algo está errado."*

**BDD/Gherkin não morreu, mas mudou de dono:** mercado ~US$446M (2024) → projeção US$689M (2030). A lição que sobreviveu 15 anos: *"o valor do BDD é o entendimento compartilhado produzido na conversa, não o arquivo Gherkin"*. Em 2026 o agente **gera** o Gherkin; o humano define o **quê**.

---

# 2. COMPARA — a lista canônica vs o que o oimpresso tem

## 2.1 Por TELA

| Artefato | Pergunta que responde | Mercado | oimpresso |
|---|---|---|---|
| `<Tela>.tsx` | Como faz? | todos | **já temos** |
| `<Tela>.charter.md` | O que deve fazer, por quê, e **o que NÃO pode**? | Kiro `requirements.md` · Spec Kit `spec.md` | **já temos — e com Non-Goals + Anti-hooks, que o mercado não tem** (§3) |
| `<Tela>.casos.md` (UC + aceite) | Como sei que está pronto? | Kiro EARS · Gherkin · spec-as-test | **já temos** (e amarrado a teste por gate required) |
| Teste que cita o UC | Prova. | todos | **já temos** (G-2) |
| Âncora de design | Qual é o desenho? | Figma Code Connect · Storybook/Chromatic | **já temos** (`related_prototype` + `anchor-content-check` **required**) |
| `design.md` / `plan.md` por tela | Qual arquitetura? | Kiro · Spec Kit | **falta — e recomendo NÃO ter.** No Kiro/Spec Kit é **efêmero** (descartado pós-implement). Virar arquivo permanente = criar o "design doc manual" que o mercado abandonou |
| `tasks.md` por tela | Em que ordem? | Kiro · Spec Kit | **temos melhor**: vive no MCP (`tasks-*`), não vira arquivo que apodrece |
| **Contrato de dados da tela** | O que a tela recebe e **de ONDE**? | "deterministic render contract" ([laioutr 2026](https://www.laioutr.com/en/blog/autonomous-commerce-frontend-render-contract-2026)): *"todo componente precisa de contrato explícito: o que aceita, o que devolve, que invariantes valem"* | 🟡 **parcial** — o charter tem §"de onde vêm os dados" **em prosa**, sem tipo e sem oráculo. **É exatamente aqui que sangrou hoje** |

## 2.2 Por MÓDULO

| Artefato | Pergunta | Mercado | oimpresso |
|---|---|---|---|
| Instruções do agente | Como se trabalha aqui? | `AGENTS.md` (padrão AAIF) · Kiro steering | **já temos** (CLAUDE.md + `.claude/rules/` path-scoped + skills) |
| Princípios não-negociáveis | O que nunca pode? | Spec Kit `constitution.md` · Spec Growth `ARCHITECTURE.md` (invariantes L1) | **já temos** (Constituição v2 + `proibicoes.md`) |
| `SPEC.md` (US) | O que o módulo faz? | Kiro `requirements.md` · Spec Growth `SPEC.md` por nó | **já temos** |
| `BRIEFING.md` | Como está **hoje**? | — **o mercado não tem esse artefato** | **já temos** |
| `SCOPE.md` | Quem é dono de qual arquivo? | Spec Growth (spec por nó) · CODEOWNERS | **já temos** (36 módulos) |
| ADRs | Por que assim? | Nygard/MADR/Log4brains | **já temos** (353, com índice **gerado**) |
| `RUNBOOK*.md` | Como rodo/conserto? | padrão SRE | **já temos** |
| **Ledger de realidade** | O que está **realmente** rodando? | Coverband (cobertura em prod) | **já temos** (`route-hits.json` + `prod-flags.json`) |
| **Ledger de dado** | Quantos registros/tenants/estados **de verdade**? | — não achei peer | ❌ **falta** — é o buraco do erro de hoje |

## 2.3 As máquinas — o oimpresso está bem acima da média

Verificado no repo hoje: **27 checks required** + `enforce_admins`. Os que importam pra este tema:
`casos-coverage-guard` (G-1 trio · G-2 UC↔teste · G-5 metadata · G-6 frescor via commit do `.tsx` ·
G-7 status derivado do resultado real do teste) · `anchor-lint` · `anchor-content-check` ·
`charter-live-signal` (*"live = evidência, não palavra"*) · `Governance Gate (memory-health)`.
Advisory: `briefing-code-staleness` (porta × código), `doc-freshness-score` (score 0-100 por doc).

**Isto é mais máquina do que qualquer player da Fase 1 documenta ter.** O Spec Growth Engine (o
paper mais próximo) propõe Intent Graph × Evidence Graph — e **não tem implementação pública nem
um número medido**. O oimpresso tem o equivalente rodando em CI, mordendo, com selftest.

---

# 3. O que temos e o mercado NÃO tem (cético — só 2, com data e fonte)

A lápide de [2026-07-09](../proibicoes.md) refutou 4 claims de superioridade (fixture boa/ruim → Semgrep/OPA;
demoção numérica → Chromium/Meta; memória-git → Letta fev/2026; writes single-threaded → paridade Cognition).
Apliquei a mesma régua. Sobrevivem **2**:

1. **Non-Goals + Anti-hooks que viram teste.** Kiro/Spec Kit/Tessl especificam o que o software
   **deve** fazer. Nas 11 buscas de hoje **não achei** player que converta "o que a tela NÃO pode
   fazer" em teste executável (aqui: Pest GUARD, e `charter-write` é proibida de inferir — só [W]
   preenche). *Ressalva honesta: claim negativo de busca, não prova de ausência.*
2. **Registro persistido de refutações** (`proibicoes.md` §5 — "tentei, caiu, o limite"). Já
   reconhecido como raro na grade de 2026-07-09; nada nas fontes de hoje contradiz. É o que impede
   ideia morta de voltar — e **funcionou nesta pesquisa**: o §5 me barrou de propor presence-gate
   e gate redundante.

**NÃO alego superioridade em:** ledger de prod (Coverband é peer), doc gerado da fonte (padrão),
ADR (padrão), staleness (Fiberplane Drift é peer e é bom), spec por tela (Kiro/Spec Kit são peers).

---

# 4. O TESTE DO ERRO DE HOJE — cada artefato teria pego "3.016 vs 1.408"?

| Artefato / máquina | Pega? | Por quê |
|---|---|---|
| `.charter.md` (como existe hoje) | ❌ **NÃO** | Foi ele que **cometeu** o erro. Prosa não tem oráculo |
| `.casos.md` + teste (G-2) | ❌ **NÃO** | Blinda **comportamento da rota** (auth, render, read-only, Tier 0). Nenhum UC afirma contagem de acervo — e nem deveria |
| `anchor-lint` / `anchor-content-check` | ❌ **NÃO** | Ancoram spec↔**código** e charter↔**design**. Cegos a **dado** |
| `briefing-code-staleness` | ❌ **NÃO** | Mede porta × **código** (derivada temporal). O charter era **novo** — não era stale, era **falso** |
| `charter-live-signal` | 🟡 **metade** | Pegaria o *"ONDA LIVE"* sobre tela mock (`route-hits`/`prod-flags`), mas só olha `status: live` de **charter**, não BRIEFING |
| `casos-coverage-guard` G-7 (status derivado) | ❌ **NÃO** | Deriva status do resultado **do teste**. Não existe teste de "quantos documentos existem" |
| Fiberplane Drift (se adotássemos hoje) | ❌ **NÃO** | Fingerprint de **AST**. O código não mudou — o **dado** é que era outro |
| Spec Growth Engine (se existisse) | ❌ **NÃO** | Evidence Graph = imports/rotas/testes. **Não olha banco** |
| Kiro / Spec Kit / Tessl | ❌ **NÃO** | Nenhum verifica afirmação contra dado de runtime |
| **Check T (`fact-anchor`) do `memory-health.mjs`** | 🟡 **A FORMA CERTA, faltam 2 peças** | Já é literalmente isto: *"ancora o FATO afirmado numa FONTE-DE-VERDADE versionada e flagra CONTRADIÇÃO"*. Pega "React 18" (é 19) e `Modules/MemCofre` (virou SRS). **Faltam:** (a) corpus — só 6 docs de entrada, charter/BRIEFING de fora; (b) fonte "dado" — só conhece `package.json`/`composer.json`/árvore `Modules/` |
| **Ledger de dado** (`data-facts.json`, não existe) | ✅ **SIM** — com Check T | Irmão de `route-hits.json`/`prod-flags.json`: gerado por comando no host de prod, commitado, *"NAO editar a mao"*. Aí o número tem **dono** |
| **A lei "não restateia, aponta"** (não escrita p/ dado) | ✅ **SIM, e é a mais barata** | Se o charter não pode escrever "3.016" à mão, o erro é **impossível por construção** — não precisa de gate |

### O diagnóstico preciso (e é aqui que a literatura de 2026 ajuda)

O agente **não alucinou**. Ele **rodou uma tool de verdade** (`git ls-files`) e reportou a saída
com fidelidade. O defeito é **oráculo errado**: mediu o **disco** pra descrever uma tela que lê o
**banco**. Na taxonomia de [arXiv 2603.10060](https://arxiv.org/pdf/2603.10060) o número foi
apresentado como *saída direta de tool* — o grau epistêmico mais alto — quando era saída direta de
**outro sistema**. É o pior caso possível: **parece a evidência mais forte que existe.**

Os 3 documentos (charter, casos, BRIEFING) estavam **coerentes entre si**. Toda máquina de
consistência (doc↔doc, doc↔código, doc↔design) dá **verde** — porque a inconsistência não estava
entre eles, estava entre **todos eles e o mundo**. Quem pegou foi revisão adversarial + 1 SELECT.
**Isso não é acidente: é o limite estrutural de doc-vs-código, que é onde todo o mercado está.**

E o oimpresso **já tem a lei que resolve** — só não a generalizou:

> §"Claim sem evidência" (Tier 0, **2026-05-17**): *"funcionando" sem cole literal de `curl -sv`
> mostrando o status = banido.*

Essa lei cobre o eixo **infra/prod** (curl). O eixo **dado** (SELECT) nunca foi escrito. O charter
do KB é a mesma violação num eixo sem lei. E a lápide de **2026-07-16** (24h atrás!) já formulou o
princípio geral e o aplicou só a enforcement:

> *"Dono único de 'o que é required' = `required-checks-baseline.json`. Quem precisar falar de
> enforcement **aponta pro dono, não restateia**."*

**Trocar "enforcement" por "qualquer fato derivado" resolve o erro de hoje e ~toda a família.**

---

# 5. AVALIA — gaps rankeados

| # | Gap | Impacto | Esforço (IA-pair, [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) | Pré-req bloqueante? |
|---|---|---|---|---|
| 1 | **Lei "fato derivado não se restateia — aponta pro dono"** não existe pro eixo dado (só pra enforcement, 07-16, e pra prod/curl, 05-17) | **alto** — mata a classe inteira por construção | **~30min** (emenda em `proibicoes.md` §5 + `how-trabalhar.md`) | **não** |
| 2 | **Check T (`fact-anchor`) não vê charter/BRIEFING** — corpus = 6 docs de entrada | **alto** — é o dente, já vive dentro de job **required** | ~1h + calibragem de FP | não (mas ver risco abaixo) |
| 3 | **Não existe ledger de DADO** (`data-facts.json`): contagens que os docs citam não têm dono versionado | **alto** — é a única peça que pega o "3.016" de forma mecânica | ~2-4h + **comando no host de prod** | 🟡 **sim** — precisa de `php artisan` no Hostinger + commit do JSON (mesmo padrão de `prod-flags`) |
| 4 | **Contrato de dados da tela em prosa** (§"de onde vêm os dados" sem tipo/oráculo) vs "deterministic render contract" do mercado | médio | — (é consequência de 1+3) | depende de 3 |
| 5 | `charter-live-signal` não cobre **BRIEFING** (só charter `status: live`) — o *"ONDA LIVE"* sobre tela mock passou | médio | ~1-2h (estender, não criar) | não |
| 6 | **Dívida de trio**: 241/278 telas sem `casos.md`, 44 sem charter | alto (mas **já tratado**) | ratchet do `casos-coverage-guard` já morde; encolhe por toque | não — **não somar máquina aqui** |

**Risco honesto do #2:** expandir o corpus de 6 → 234 charters pode explodir falso-positivo (a 1ª
rodada do Check T deu 18 hits → ~2 reais). Mitigação: manter advisory, entrar só com os matchers
determinísticos (path de módulo, versão), medir e só depois considerar promoção via
[ADR 0327](../decisions/0327-anchor-content-required-emenda-0314.md)/[0336](../decisions/0336-gates-design-promocao-por-mordida-provada.md).

**Conformidade com o §5 (checado antes de propor):** nada aqui é presence-gate (o #2 compara
**conteúdo contra fonte-de-verdade**, não presença no diff — é a forma que o próprio Check T já usa
e que o `dominio-gate` required já usa pra enum⇔dicionário). Nada aqui é gate redundante (#1 é lei,
não gate; #2 e #5 **estendem** régua consolidada; #3 é **ledger**, não gate — 3º irmão de
`route-hits`/`prod-flags`). Nada aqui soma required (ADR 0271/0314) — o #1 é **subtração** pura.

---

# 6. RECOMENDAÇÃO

**Comece por #1 — a lei. Alto impacto, ~30min, sem pré-req, e é subtração (documento passa a
escrever MENOS).**

Não é o item mais impressionante — é o mais honesto. O #3 (ledger) é a peça que morde, mas depende
de comando em prod e só vale se a lei existir primeiro (senão você constrói oráculo pra número que
não deveria estar no doc). O #2 é o dente natural, mas o Check T só consegue ancorar fato que
**tem dono declarado** — e é a lei que cria essa disciplina.

A formulação (uma frase, legível pro [W]):

> **Documento canônico não repete número que outro sistema sabe melhor.** Ou aponta pro dono
> (`SCHEMA-DB-V1.md`, `route-hits.json`, o SELECT), ou carrega o **recibo** junto: a query literal +
> o resultado + a data. Número sem recibo e sem dono não entra em charter/BRIEFING/SPEC.
> **Corolário (o que sangrou hoje):** o recibo tem que declarar **qual sistema** foi medido — a
> tela lê o banco, então `git ls-files` não é recibo, é outro assunto.

### Próxima ação hoje (concreta, 20-30min)

**1 PR de subtração no caso que sangrou:** tirar os números escritos à mão da §3 do
`resources/js/Pages/kb/Index.v2.charter.md` e trocar por ponteiro pro dono + recibo datado
(`SELECT COUNT(*) FROM kb_nodes WHERE business_id=1` → 1.408 @ 2026-07-17). Se a lei sobreviver ao
caso real, aí ela vira emenda no `proibicoes.md` §5 no mesmo PR — com o caso concreto como âncora,
que é como todas as boas entradas do §5 nasceram.

**Depois (ordem):** #2 (Check T ganha charter/BRIEFING no corpus, advisory, medir FP) → #5
(charter-live-signal cobre BRIEFING) → #3 (ledger de dado, quando a lei provar que vale).

---

## Fontes (Fase 1, consultadas 2026-07-17)

- [GitHub Spec Kit](https://github.com/github/spec-kit) · [Spec-Driven Development (Microsoft Dev Blog)](https://developer.microsoft.com/blog/spec-driven-development-spec-kit/)
- [Kiro — Specs](https://kiro.dev/docs/specs/) (requirements/design/tasks + steering)
- [Tessl](https://tessl.io/blog/tessl-launches-spec-driven-framework-and-registry/) · [Tessl Review 2026 — a aposta spec-as-source](https://codemyspec.com/blog/tessl-review)
- [Fiberplane Drift — linter para apodrecimento de doc](https://fiberplane.com/blog/drift-documentation-linter/) · [repo](https://github.com/fiberplane/drift)
- [arXiv 2606.27045 — The Spec Growth Engine (spec-anchored, code-coupled, drift-enforced)](https://arxiv.org/html/2606.27045)
- [arXiv 2602.00180 — Spec-Driven Development: From Code to Contract](https://arxiv.org/html/2602.00180)
- [arXiv 2603.10060 — Tool Receipts, Not Zero-Knowledge Proofs](https://arxiv.org/pdf/2603.10060) · [arXiv 2606.04990 — From Agent Traces to Trust](https://arxiv.org/html/2606.04990)
- [Augment Code — What spec-driven development gets wrong](https://www.augmentcode.com/blog/what-spec-driven-development-gets-wrong)
- [Falconer — replace Confluence](https://falconer.com/guides/replace-confluence-without-losing-docs) · [Atlassian Community — seu wiki está dando informação errada agora](https://community.atlassian.com/forums/App-Central-articles/Your-Confluence-wiki-is-confidently-giving-people-wrong/ba-p/3192612)
- [OpenAI — Agentic AI Foundation](https://openai.com/index/agentic-ai-foundation/) (AGENTS.md + MCP sob Linux Foundation)
- [Gherkin/BDD 2026](https://testquality.com/gherkin-user-stories-acceptance-criteria-guide/) · [laioutr — deterministic render contract](https://www.laioutr.com/en/blog/autonomous-commerce-frontend-render-contract-2026)
- [Log4brains](https://github.com/thomvaill/log4brains) · [MADR](https://adr.github.io/madr/)
