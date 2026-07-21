---
date: 2026-07-21
hour: "20:30 BRT"
topic: "Arquitetura de conhecimento por módulo — catálogo + descritor schema'd + opinião ancorada a rubrica (IDP model) + hook derive-não-escreve. Pesquisa profunda + avaliação franca da proposta [W]."
authors: ["C"]
tags: [estado-da-arte, catalogo-modulo, scorecard, rubrica, idp, backstage, cortex, opslevel, swimm, diataxis, codescene, anti-apodrecimento, opiniao-ia]
outcomes:
  - "Veredito: a proposta [W] reinventa o modelo IDP (catálogo + scorecard). O oimpresso JÁ tem versão caseira de CADA camada, espalhada. Trabalho = COERÊNCIA + 3 disciplinas, não invenção."
  - "3 pesquisas (Backstage/Cortex/OpsLevel/Port · Swimm/Diátaxis/epistemics · manutenção agêntica) + repo verificado."
  - "Avaliação franca da proposta [W]: decomposição ✅ · derivar+opinião ⚠️(rubrica, não vibe) · hook-escreve ⚠️(derive+draft+gate) · chat→edita ✅. NÃO implementado — decisão [W]."
---

# Arquitetura de conhecimento por módulo — catálogo + opinião ancorada (2026)

**Data:** 2026-07-21 · **Agente:** sessão dedicada estado-da-arte (Opus 4.8) · **Pedido [W]:** decodificado abaixo.
**Método:** 3 pesquisas paralelas profundas (~20 WebSearch/Fetch) + cada afirmação sobre o repo verificada em `origin/main`. Fontes ao fim.
**Continuação de:** [2026-07-21 contexto-vivo + Gap 2](2026-07-21-arte-contexto-vivo-descoberta.md) (PR #4605) + o gerador `module-surface` (PR #4607). Este doc responde o pedido MAIOR do [W].

## Pedido [W] decodificado (o que ele pediu, em 1 bloco)

> Derivar do código + **escrever se concordo ou não com a função** (lado positivo/negativo, fazer/não-fazer) — em todos os módulos. Cada **tópico do BRIEFING vem de 1 arquivo único, tema único**; o BRIEFING vira o **resumo/índice**. Usar **schemas** pra IA escrever no padrão. Um **hook** mantém tudo fresco escrevendo o briefing. **Reclamei no chat → a IA acha o doc da tela/model e edita.** "Alguém já fez melhor? pesquise profundamente."

## TL;DR / Resumo executivo — o veredito em 1 parágrafo

**Sua intuição reinventou, quase item por item, o modelo que a indústria chama de IDP (Internal Developer Portal): descritor schema-conformante por entidade + opinião compilada em REGRAS (não vibe) + frescor derivado + dono em git.** É o que Backstage, Cortex, OpsLevel e Port fazem — e o oimpresso **já tem uma versão caseira de CADA camada**, só que **espalhada em 4-6 tipos de arquivo por módulo com fronteiras difusas**. O 2026 VALIDA sua direção. O trabalho não é inventar — é **(1) dar coerência** (cada arquivo = 1 propósito, Diátaxis; o BRIEFING vira índice gerado, não cópia), **(2) generalizar a opinião-ancorada-a-rubrica** que você já tem por tela (`screen-grade`) pro nível de função/model — **NUNCA opinião livre de IA** (a IA "concordo com essa função" é carimbo, provado abaixo), **(3) manter a disciplina do hook**: hook **DERIVA os fatos + RASCUNHA a prosa + humano/rubrica LIBERA o canon** — nunca hook escrevendo prosa canônica sozinho.

---

## O que você JÁ TEM (a base de ~80% — verificado no repo)

Seu pedido não é um sonho distante; é quase a arquitetura atual, incompleta:

| Camada IDP | Você já tem | Contagem | = análogo externo |
|---|---|---|---|
| **Descritor schema'd por módulo** (contains/não-contains) | `Modules/<X>/SCOPE.md` (`module`/`purpose`/`contains`/`not_contains`/`owner`/`permission_prefix`/`related_adrs`) | **36** | Backstage `catalog-info.yaml` |
| **Resumo por módulo** | `memory/requisitos/<X>/BRIEFING.md` | 77 | — (o "índice") |
| **Superfície de arquivos (derivada)** | `SUPERFICIE.md` (gerado, PR #4607) | 1 (Financeiro) | Backstage discovery |
| **Requisitos por módulo** | `SPEC.md` (US) | 59 | — |
| **Contrato por tela** (fazer/não-fazer) | `<Tela>.charter.md` (`goals`/`non_goals`/`anti_hooks`) | **238** | Backstage TechDocs `dir:.` |
| **Contrato de caso de uso por tela** | `<Tela>.casos.md` (UC) | 39 | — |
| **Opinião ancorada a rubrica** (por tela) | `screen-grade` (16-dim, LLM-as-judge + **ratchet** + drift, método `SCREEN-GRADE-METODO`) | vivo | **Cortex/OpsLevel Scorecard** |
| **Schemas (o "padrão pra IA escrever")** | `scripts/memory-schemas/*.schema.json` | **8** | Port **blueprints** |
| **Frescor derivado (drift = gate)** | `briefing-code-staleness.mjs`, `screen-coverage-map.mjs`, `charter-refs.mjs` | vivo | **Swimm auto-sync** |
| **IA escreve no padrão** | skills `charter-write` · `criar-tela.mjs` · distiller `jana:distill-module-truth` | vivo | Dosu/Mintlify draft |
| **Chat → acha o doc → edita** | `KbAnswerTool` (filtra `module`+`categoria`) + co-locação | vivo | Dosu/Letta routing |
| **Fazer/não-fazer (fronteira)** | `SCOPE.not_contains` (módulo) + `charter.non_goals`/`anti_hooks` (tela) | vivo | Cortex `failureMessage` |

**Tradução honesta:** das ~11 peças do modelo IDP, você tem ~10 em forma caseira. O que falta é **coerência** (fronteiras difusas entre SCOPE/BRIEFING/SPEC/SUPERFICIE) e **profundidade da opinião** (existe por tela, não por função/model).

---

## "Alguém já fez melhor?" — sim, e tem nome (o modelo, sem o peso)

Os 4 líderes convergem na MESMA forma de 3 camadas: **descritor schema'd → regra que compila a opinião → escada de níveis + campanha datada.** O detalhe que importa pra você:

- **Backstage** (`catalog-info.yaml`): descritor k8s-style + **TechDocs `dir:.`** (doc viaja junto da entidade — é seu charter/casos ao lado do `.tsx`). Freshness por **discovery crawl + processing loop** (git é a verdade, o catálogo reconcilia). A opinião NÃO está no open-source — é o **Soundcheck** pago (Check→Level→Track→Certification).
- **Cortex** (`cortex.yaml` + scorecard-as-code): **o melhor modelo pra estudar.** Cada regra é `{expression (CQL booleana), weight, level, failureMessage}`. Ex: `git.hasFile("SECURITY.md")`, `oncall.exists()`. **A opinião do dono vive em QUAIS regras existem + peso + nível — nunca num "eu acho".** Initiatives = regra + **prazo** + tickets.
- **OpsLevel** (`opslevel.yml`): a distinção mais clara — **Rubric** (1 padrão org-wide canônico) vs **Scorecards** (leves, por-time, NÃO-autoritativos). Checks tipados (Repo File exists, jq sobre evento, Manual com auto-expiry). **YAML tranca a UI** (arquivo = verdade, UI só adiciona).
- **Port** (blueprints): você **define seus próprios tipos** como JSON Schema (um módulo ≠ um microserviço). Regra = query `{operator, property, value}`. Scorecard/regra/veredito são **eles mesmos entidades** (verdito-como-dado — sua intuição de "gate verdict as data").

**A ideia mais forte pra roubar (todos os 4):** a opinião é **compilada em regra** `{expressão, peso, nível, mensagem}` — **nunca prosa livre**. É exatamente o antídoto pro tom-inflado que o seu §5 já caça.

**Veredito de escala (honesto):** essas ferramentas existem pra domar **centenas-a-milhares de microserviços / dezenas de times**. Você é **5 pessoas, 1 monolito modular git-canon, sem k8s.** **NÃO adote nenhuma delas — não compre nada.** O *produto* (portal hospedado, RBAC, discovery cloud/k8s, badges, leaderboards) é peso morto no N=5. **Roube o MODELO** (forma-de-regra + frescor-derivado), que você já tem 80%.

---

## Minha avaliação FRANCA da sua proposta (concordo/não — você pediu isto)

### 1. Decomposição "1 tema = 1 arquivo, BRIEFING = resumo" → ✅ **CONCORDO forte**
É Diátaxis (*"borrar as fronteiras entre tipos de doc está no coração de um número enorme de problemas de documentação"*) + o índice-gerado do Docusaurus (docs-as-data) + a lei ADR 0256. Já é sua trajetória. **Refinamento:** o BRIEFING deve ser um **índice GERADO que APONTA** pros arquivos-tema (não uma cópia à mão) — como a sidebar auto-gerada. E **resolver a fronteira difusa**: hoje você tem 4 docs por módulo (SCOPE/BRIEFING/SPEC/SUPERFICIE) com papéis que se sobrepõem. Diátaxis manda: cada um recebe **UM** propósito e não invade o do outro.

### 2. "Derivar do código + escrever se concordo (opinião por função)" → ⚠️ **CONCORDO nos FATOS, ATENÇÃO DURA na OPINIÃO**
Os **fatos** derivam limpo (é o `module-surface`). A **opinião livre é o maior risco da sua proposta** — e a literatura 2026 é categórica: **uma IA que lê uma função e diz "concordo com ela" está carimbando.** Motivos estruturais (3 papers):
- **Sicofância é propriedade do RLHF** — o modelo otimiza pra resposta que *parece* concordante; humanos preferem respostas agradáveis a verdadeiras, e o RLHF amplifica isso.
- **Viés de leniência** — sem **critério NEGATIVO explícito**, o juiz-LLM marca "atende" pra respostas de qualidade variada. Concordar é o atrator default.
- **Viés de auto-preferência** — o modelo nota mais alto o que ele mesmo gerou/derivou.

**Isso é o §5 2026-06-05 ("teste que deriva do código é tautológico") aplicado à OPINIÃO.** O jeito defensável (CodeScene + fitness functions + o paper "From Rubrics to Reliable Scores"):
> A opinião só vale se **(a)** julgada contra uma **RUBRICA externa declarada ANTES** (lista de best-practice / anti-padrão / contrato / ADR), **(b)** cada critério vira **veredito por-critério com uma CITAÇÃO EXTRATIVA do código** que satisfaz/viola, **(c)** a rubrica é **validada por resultado** (CodeScene: "Code Red" liga saúde baixa a 2× tempo / 15× defeitos — não é gosto), **(d)** a rubrica **NÃO é retro-derivada da função julgada** (senão a rubrica vaza o veredito = tautologia — a armadilha "rubric artifact").

**Você JÁ FAZ isso certo no `screen-grade`** (16 dimensões definidas + ratchet + drift + método). **Fazer:** generalizar o `screen-grade` pro nível de função/model. **NÃO fazer:** deixar a IA escrever "eu acho essa função boa" livre — é carimbo, e o seu próprio §5 já te vacinou contra isso.

### 3. "Um hook escreve o briefing (sempre atualizado)" → ⚠️ **CUIDADO — separe em duas metades**
A pesquisa de manutenção agêntica é unânime: **ninguém sério deixa um hook escrever PROSA CANÔNICA sozinho.** Dosu/Mintlify/Swimm todos **derivam os fatos + rascunham + humano LIBERA** ("nada vai pro ar até o mantenedor clicar Publish"). O único que auto-edita sem trava (Letta) **documenta que NÃO tem salvaguarda contra escrita errada** — e só serve pra **memória-rascunho**, não pra doc de time.

A fronteira que o mercado desenha (e que casa com seu §5 — "presence ≠ correção", "campo auto-declarado apodrece"):
- ✅ **Seguro:** hook **DERIVA** os fatos-máquina (SUPERFICIE, contagens, status computado de git/CI/DB, "implementado em" âncora) + **DETECTA** drift + **RASCUNHA** uma proposta de prosa.
- ⛔ **Arriscado:** hook **commita prosa LLM** direto no canon.

**Seu instinto de desligar o schedule do distiller estava CERTO** (ele é `jana:distill-module-truth`, schedule comentado, manual). **Fazer:** hook regenera o bloco DERIVADO e falha alto se não conseguir; a IA no máximo **abre um diff proposto** da prosa e **sinaliza pra revisão**. **NÃO fazer:** hook reescrevendo a narrativa canônica calado.

### 4. "Reclamei no chat → a IA acha o doc da tela/model → edita" → ✅ **CONCORDO**
A descoberta já funciona (`KbAnswerTool` filtra module/categoria; a decomposição deixa o alvo cirúrgico — o primitivo de roteamento de todos é frontmatter+pasta+nome, que você tem). **Ressalva:** a edição do CANON ainda passa pela mesma trava (PR/revisão) — a IA acha e RASCUNHA a mudança; você libera. Pro seu N=5, a "trava" é uma mensagem no chat + merge, não um portal.

---

## A arquitetura proposta (COERÊNCIA, não invenção)

Não é sistema novo — é uma passada de coerência sobre o que existe:

1. **Taxonomia de arquivos-tema por módulo (cada um = 1 propósito, Diátaxis):**
   - `SCOPE.md` = **descritor/fronteira** (contains/not_contains) — mantém. É seu catalog-info.
   - `SUPERFICIE.md` = **superfície de arquivos DERIVADA** (gerado) — espalhar (PR #4607 começou).
   - `BRIEFING.md` = **o ÍNDICE/resumo** — deve APONTAR pros arquivos-tema + carregar só o status-derivado, não duplicar. (Rebaixar o papel de prosa-livre do distiller.)
   - `SPEC.md` = **requisitos** (US).
   - por tela: `charter` (goals/non_goals/anti_hooks) + `casos` (UC) + `scorecard` (screen-grade).
2. **A opinião generalizada — regra-forma `{expressão, peso, nível, mensagem}`** contra rubrica declarada, com citação extrativa do código. Generalizar `screen-grade` de tela → função/model. É aqui que "concordo/não com a função" vira defensável.
3. **A disciplina do hook:** derive fatos + rascunhe prosa + trava humana no canon.
4. **O "fazer/não-fazer"** já mora em `SCOPE.not_contains` (módulo) + `charter.non_goals`/`anti_hooks` (tela); estende pro nível de função via a rubrica (regra violada = "não faça isso", com a `failureMessage` = o porquê).

---

## As armadilhas (alinhadas ao seu §5 — pra não regredir)

- **Opinião tautológica** (IA concorda com o código que leu) — a MAIOR. Antídoto: rubrica externa + citação extrativa; nunca vibe. (§5 2026-06-05)
- **Rubric-artifact trap:** rubrica retro-derivada da função vaza o veredito. Rubrica tem que ser best-practice geral + validada por resultado. (§5 2026-07-16 "traduzir premissa, não copiar solução")
- **Scorecard decorativo:** regra que só fica verde = teatro. Bite-test obrigatório. (§5 2026-07-17 drift-sentinel tautológico + foundation-ratchet "0 falhas em 300 runs")
- **Hook auto-escrevendo canon** — trava sempre. (agente 3 + §5 L-24 campo auto-declarado)
- **Big-bang nos 36 módulos** — oportunístico, 1 por vez. (§5 2026-07-12 tocar legado em massa acorda gates)
- **Criar sistema PARALELO** quando você tem 80% — estender SCOPE/BRIEFING/screen-grade/schemas, nunca abrir concorrente. (§5 2026-07-09 "duplica régua consolidada")

---

## Próximos passos (PROPOSTAS — não implementado, decisão [W])

1. **[W] decide o escopo.** Recomendação de ordem barata→cara: **(a)** espalhar `SUPERFICIE.md` (já pronto, `--write` por módulo); **(b)** ADR curta definindo a **taxonomia de arquivos-tema por módulo** (resolver a fronteira SCOPE/BRIEFING/SPEC/SUPERFICIE — cada um 1 propósito); **(c)** piloto do **scorecard de função com regra-forma** num módulo (generalizar screen-grade) — este é o item com risco epistêmico, merece ADR + bite-test antes de espalhar.
2. Cada peça = ADR proposta própria (mudança de arquitetura de conhecimento = decisão canon).
3. **Nada de comprar ferramenta.** Roubar o modelo, estender o caseiro.

## Fontes (estado-da-arte 2026)

**Catálogo + scorecard:** [Backstage descriptor](https://backstage.io/docs/features/software-catalog/descriptor-format/) · [Backstage TechDocs](https://backstage.io/docs/features/techdocs/how-to-guides/) · [Soundcheck](https://backstage.spotify.com/docs/plugins/soundcheck) · [Cortex scorecards-as-code](https://docs.cortex.io/standardize/scorecards/scorecards-as-code) · [Cortex Initiatives](https://docs.cortex.io/improve/initiatives) · [OpsLevel scorecards](https://docs.opslevel.com/docs/scorecards) · [OpsLevel rubric](https://www.opslevel.com/resources/how-to-set-up-your-service-maturity-rubric) · [Port scorecards](https://docs.port.io/promote-scorecards/) · [Port blueprints](https://docs.port.io/build-your-software-catalog/customize-integrations/configure-data-model/setup-blueprint/)
**Docs decompostos + epistemics:** [Diátaxis](https://diataxis.fr/) · [Swimm continuous docs](https://docs.swimm.io/new-to-swimm/continuous-documentation/) · [Swimm auto-sync](https://swimm.io/blog/how-does-swimm-s-auto-sync-feature-work) · [Docusaurus markdown](https://docusaurus.io/docs/markdown-features) · [CodeScene Code Health](https://codescene.io/docs/guides/technical/code-health.html) · [Thoughtworks fitness functions](https://www.thoughtworks.com/en-us/radar/techniques/architectural-fitness-function) · [Anthropic sycophancy (arXiv 2310.13548)](https://arxiv.org/pdf/2310.13548) · [From Rubrics to Reliable Scores (arXiv 2601.08654)](https://arxiv.org/abs/2601.08654) · [Rubric Artifacts (OpenReview)](https://openreview.net/forum?id=jBcsGPKNeV)
**Manutenção agêntica:** [Dosu](https://dosu.dev/blog/using-ai-to-generate-and-maintain-documentation) · [Mintlify Agent](https://www.mintlify.com/blog/agents-launch) · [Letta Context Repositories](https://www.letta.com/blog/context-repositories/) · [Kiro hooks](https://kiro.dev/docs/hooks/) · [doc-drift](https://github.com/jbrockSTL/doc-drift)
