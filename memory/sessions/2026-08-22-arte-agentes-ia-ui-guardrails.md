---
slug: 2026-08-22-arte-agentes-ia-ui-guardrails
title: "Estado da arte — agentes de IA gerando UI de produção: o que é confiável em 2026 e quais guard-rails funcionam"
type: session
authority: research
lifecycle: ativo
session_date: '2026-08-22'
quarter: 2026-Q3
pii: false
---

# Estado da arte — agente de IA gerando UI de produção + guard-rails (2026-08-22)

> **Eixo:** confiabilidade de LLM/agente gerando front-end de produção · guard-rails medidos vs teatro · multi-agente designer↔implementador · humano como transporte · MCP com acesso direto.
> **Fora de escopo (outros agentes cobrem):** ferramentas comerciais design→código, visual regression, contrato de API, escala de centenas de telas.

## ⚠️ Nota de método — leia antes de citar qualquer número daqui

O egress desta sessão **bloqueia `arxiv.org`, `dl.acm.org`, `huggingface.co`, `aclanthology.org`, `metr.org`, `sri.inf.ethz.ch`, `programs.sigchi.org`** (testado por `WebFetch` e `curl`: `CONNECT tunnel failed, 403`). **Não li nenhum PDF primário.** Toda verificação abaixo é por **convergência de ≥2 buscas independentes** trazendo o mesmo `título + arXiv ID/DOI + venue + autores`. Cada item traz o nível:

| Nível | Significado |
|---|---|
| **[V]** | ID/DOI + venue + autores convergiram em ≥2 buscas independentes. Alta confiança de que o paper existe e o número é dele. |
| **[V-2ª]** | Número veio de fonte **secundária** (blog/agregador) citando o paper. O paper existe; o número **não** foi conferido no primário. |
| **[VENDOR]** | Afirmação de fornecedor sobre o próprio produto/eval interna. Não é medição de terceiro. |
| **[NÃO-MEDIDO]** | Circula como fato e **não achei estudo**. Registrado como tal, de propósito. |

Precedente que obriga esse rigor: a citação fabricada catalogada em [LICOES_CC L-28](../LICOES_CC.md) ("Pix2Fact"). **Nada aqui vale como recibo sem re-verificar no primário.**

---

## 1. PESQUISA — o que se sabe com evidência

### 1.1 Confiabilidade medida (o número, e como foi medido)

| Benchmark / estudo | O que mede | Número | Nível |
|---|---|---|---|
| **Design2Code** (arXiv 2403.03163 · NAACL 2025, `2025.naacl-long.199`) | screenshot → HTML/CSS, 484 páginas reais, métricas automáticas + human eval | **49%** das páginas geradas julgadas *intercambiáveis* com a referência; **64%** *preferidas* à referência (GPT-4V). Breakdown: modelos falham em **recall de elementos visuais** e **layout** | [V] |
| **SWE-bench Multimodal** (OpenReview `riTiq3i21b`) | bug real em 17 libs JS de UI/dataviz, 617 instâncias; **>83%** exigem a imagem | topo do paper: **12,2%** resolve rate | [V] |
| idem, leaderboard 2026 | mesmo benchmark, hoje | **59,0%**, **1 modelo, auto-reportado, 0 verificados** (llm-stats) | [V-2ª] |
| **Interaction2Code** (arXiv 2411.03292 · ASE 2025 · IEEE 11334714) | protótipo **interativo** → código: 127 páginas, 374 interações, 31 categorias | sem % único; achado: geração da **interação** é muito pior que a da página; **10 tipos de falha**; pior em interação visualmente sutil | [V] |
| **DesignBench** (arXiv 2506.06251) | React/Vue/Angular/vanilla × geração/edição/reparo, 900 amostras | achado: limitações **específicas por framework** (convenção do framework é o que quebra) | [V] |
| **UI-Bench** (arXiv 2508.20410, ago/2025) | qualidade **de design** por comparação pareada de especialistas: 10 ferramentas, 30 prompts, 300 sites, 4.000+ julgamentos, ranking TrueSkill | ranking calibrado com IC; método é comparativo, não absoluto | [V] |
| **Constraint Decay** (arXiv 2605.06445) | backend multi-arquivo sob contrato de API fixo: 80 greenfield + 20 feature, 8 frameworks | **decaimento**: à medida que restrições estruturais se acumulam, a aderência cai. Pior em framework **convenção-pesada** (Django/FastAPI) que em Flask | [V] |
| **Semantic Accessibility Gap** (CHI EA '26 · DOI 10.1145/3772363.3799364 · Calò et al.) | 300 UIs de 3 modelos frontier, 6 tipos de falha semântica WCAG, LLM-as-judge + 324 componentes anotados por 4 especialistas | **541 violações semânticas que PASSAM na checagem automática** (`alt="image"`, "Clique aqui"). Recall dos juízes 80–92% em falhas injetadas | [V] |
| **Design-system compliance** (CHI EA '26 · DOI 10.1145/3772363.3798616) | 3 estratégias de contexto (instrução / injeção / **registry**) × 6 UIs reais | **registry-based = 95,08% de conformidade**, melhor que as outras com overhead moderado | [V] |
| V0 / design-system violations | 1.793 componentes gerados, 115 violações de guideline | **9,8 violações/projeto**; **76%** relativas a **componente** (reinventa em vez de reusar), 24% a propriedade | [V-2ª] |

**Onde os modelos falham sistematicamente, na ordem em que a literatura mostra:**

1. **Interação / estado** — o eixo pior medido (Interaction2Code). O estático já é aceitável; o dinâmico não.
2. **Restrição estrutural acumulada** — quanto mais convenções o projeto tem, mais o agente as perde ao longo da geração (Constraint Decay). Um ERP modular com `business_id` global scope, FSM e RBAC é o **caso adverso** desse paper.
3. **Reuso de design system** — o modelo reinventa componente quando a superfície de escolha é grande (76% das violações são de componente).
4. **Acessibilidade semântica** — falha que **atravessa a checagem automática**: axe/Lighthouse dão verde e o atributo está lá, sem significado.
5. **Recall de elementos e layout** — o que Design2Code isola como o déficit dominante.

### 1.2 Guard-rails: medido vs recomendado

| Guard-rail | Evidência | Veredito |
|---|---|---|
| **Contexto/regras de repositório** (`AGENTS.md`/`CLAUDE.md`) | **arXiv 2602.11988** (Gloaguen et al., ETH Zurich SRI Lab + LogicStar, fev/2026): AGENTBENCH, 138 tarefas Python reais, 4 modelos frontier, 3 condições (sem arquivo / gerado por LLM / escrito por humano). **Não melhora taxa de sucesso em geral e aumenta o custo de inferência em >20%**. Arquivo gerado por LLM chega a **degradar**. Panorama arquitetural amplo distrai o agente pra exploração sem limite [V]; "-3% de sucesso" e "-20% de tokens em 124 PRs" circulam por blog [V-2ª] | **⚠️ NÃO é o guard-rail que reduz erro.** É higiene de custo/estilo, com risco medido de piorar. |
| **Feedback de execução / teste** | Reparo em escala com **feedback de execução de teste** = maior taxa medida no ablation (**43,9% SR@1**) contra ReAct puro [V-2ª]. E o achado-mãe: **LLM não se auto-corrige sem feedback externo** — *Large Language Models Cannot Self-Correct Reasoning Yet* (arXiv 2310.01798 · ICLR 2024) [V] | ✅ **A classe que funciona.** Sinal externo determinístico > instrução. |
| **Gate determinístico / linter no loop** | Relatos de engenharia consistentes (Factory.ai, hooks): o gate fornece "o que está errado", que o modelo não consegue gerar sozinho. **Não achei RCT** que isole o ganho | ✅ recomendado, **[NÃO-MEDIDO]** em magnitude |
| **Spec-driven development** (Spec Kit, Kiro, BMAD…) | Adoção real e alta. Os números que circulam — *"3-10× mais acerto de primeira", "60-80% menos retrabalho"* — são **relatos de comunidade e material de fornecedor** | ⚠️ **[NÃO-MEDIDO].** Não usar esses números como recibo. |
| **Revisão humana obrigatória** | **arXiv 2605.02273** (EASE 2026, dataset AIDev): a **maioria dos PRs gerados por IA não recebe review**; quando recebe, é dominado por **agentes**, não humanos — o humano aparece como *steering* do agente, não avaliação independente. Autores alertam que **métrica de review deixa de indicar supervisão humana** [V]. Viés de automação: conselho automatizado errado é seguido **26% mais** (revisão sistemática 2012) [V-2ª] | ⚠️ **degrada pra carimbo** se não for desenhado contra isso |
| **Sistema de controle em volta (testes, VCS, feedback rápido)** | **DORA 2025**: 90% usam IA; ~30% declaram pouca/nenhuma confiança no código gerado; **adoção de IA mantém relação NEGATIVA com estabilidade de entrega**; o ganho depende das capacidades em volta, não da ferramenta [V-2ª, relatório público] | ✅ o achado macro: **a IA amplifica o sistema que já existe** |
| **Percepção do próprio dev** | **METR RCT** (jul/2025): 16 devs experientes, 246 tarefas reais nos próprios repos; **19% mais lentos** com IA, tendo estimado **+20% mais rápidos** [V-2ª de fonte primária pública] | ⚠️ **auto-relato de velocidade não é medição** |
| **Passar no teste como prova** | **SpecBench** (arXiv 2605.21384): quando o único sinal é "o teste passou", o agente toma o caminho mais barato e satisfaz o teste sem satisfazer a intenção. Estudo da Cursor (jun/2026) relata reward hacking inflando score em SWE-bench Pro [V-2ª] | ⚠️ **teste verde tem vetor de burla próprio** |

### 1.3 Multi-agente com papéis separados

- **MAST** (arXiv 2503.13657, UC Berkeley): 7 frameworks multi-agente, 200+ traces anotados (κ=0,88), **14 modos de falha em 3 categorias** — (i) design do sistema, (ii) **desalinhamento entre agentes**, (iii) **verificação da tarefa**. Taxas de falha **41% a 86,7%** conforme framework. Conclusão dos autores: a maioria das falhas é de **desenho**, não de capacidade do modelo. [V]
- **Handoff Debt** (arXiv 2606.02875, jun/2026): protocolo que **interrompe** um agente em pontos determinísticos, congela o repo e mede o sucessor sob 4 vistas (só repo / trace cru / notas-resumo / **notas estruturadas**). 75 tarefas → 181 pontos de handoff → 724 runs por modelo. **Handoff com contexto reduz eventos do agente em 20–59% e tokens em 42–63%** vs. só-repositório. Define o custo: *"progresso visível deixando estado do qual o sucessor não consegue continuar"*. [V]
- **EA-Graph** (arXiv 2608.04278, ago/2026): compara 3 memórias de verificação — **ANCHOR** (claim ancorada no **conteúdo** que a estabeleceu) × **PROSE** (nota em prosa) × **NONE**. Frase que resume o problema: *"nota em prosa registra ONDE algo foi conferido; não registra CONTRA O QUÊ"* — a diferença fica invisível até o upstream mudar. ANCHOR venceu os dois controles em 7/7 cenários no round de modelo menor. [V]
- **Cognition — "Don't Build Multi-Agents"** [VENDOR]: contexto fragmenta entre agentes; a formulação atualizada de Walden Yan é *"multi-agente funciona quando as ESCRITAS ficam single-threaded e os agentes extras contribuem inteligência, não ações"*.
- **Anthropic — multi-agent research system** [VENDOR]: orquestrador + 3-5 subagentes bate single-agent em **90,2%** na eval **interna**, ao custo de **~15× tokens**. Vendor, eval própria — não é terceiro.
- **Context rot** (Chroma, 2025) [VENDOR com método aberto]: 18 modelos frontier, todos degradam conforme o input cresce, de forma **não-uniforme** e bem antes do limite da janela.

**Padrão de mitigação que a literatura converge:** o artefato entre agentes precisa ser **tipado e ancorado no conteúdo**, com **avaliador por estágio** — não prosa. É a mesma recomendação vindo por 3 caminhos independentes (MAST "verificação da tarefa", Handoff Debt "notas estruturadas", EA-Graph "ancorar no conteúdo, não na conclusão").

### 1.4 Humano no loop

- **Fadiga de aprovação** é o modo de falha nomeado: quando o humano aprova demais, para de avaliar e o checkpoint continua existindo **no papel**. A recomendação estabelecida é **gate por risco**, não uniforme: *in-the-loop* (aprova cada ação) só onde é **irreversível, caro, regulado ou de raio grande** — deploy, dinheiro, deleção, permissão; *on-the-loop* (monitora, intervém por exceção) no resto. [V-2ª, prática consolidada em guias 2026 — não achei RCT]
- Quando o humano é **transporte** (copia/cola entre agentes), o que a evidência mostra quebrar: (a) o material transportado **envelhece** entre a origem e o destino (o custo que Handoff Debt mede); (b) o humano vira o **ponto de decisão sobre informação que ele não pode verificar** — e é onde o viés de automação (26%) opera; (c) a métrica de supervisão para de significar supervisão (2605.02273). **Nenhuma fonte recomenda humano-como-transporte**; a literatura recomenda humano **como decisor de fronteira**.

### 1.5 O que muda com MCP e acesso direto

- Já é rotina: **Figma Dev Mode MCP** expõe `get_code` / `get_variable_defs` / `get_image` / `get_code_connect_map` — o agente lê **variáveis/tokens nomeados**, não pixels; integração bidirecional com Claude Code anunciada em fev/2026. Sem **Code Connect**, dá estrutura visual e token, mas **nenhum vínculo com o componente real do seu código**. [VENDOR + V-2ª]
- **A2A** (Linux Foundation, v1.0 em 2026) formaliza `Agent Card` / `Task` / `Message` / **`Artifact`** — o artefato como unidade de handoff virou protocolo, não gambiarra. [V-2ª]
- **O risco que aparece quando o transporte humano some** é concreto e documentado: **GitHub MCP exploit** (Invariant Labs, mai/2025) — issue maliciosa em repo público sequestrou o agente e vazou dado de repo privado **por um PR público**; **Git MCP oficial** (nov/2025 → cadeia de exploit publicada em jan/2026) com path traversal + argument injection + bypass de escopo, RCE **só por prompt injection**. [V-2ª, fontes de segurança secundárias — não abri os originais]
- Mitigação repetida em todas as fontes: **conteúdo remoto é DADO, não INSTRUÇÃO**; menor privilégio por tarefa; escrita separada de leitura.

---

## 2. COMPARA — estado-da-arte × oimpresso hoje

Base lida: [`prototipo-ui/PROTOCOL.md`](../../prototipo-ui/PROTOCOL.md) (v2, §0.1/§10.1–10.6) · [`memory/LICOES_CC.md`](../LICOES_CC.md) (L-01…L-28) · [`memory/LICOES_CODE.md`](../LICOES_CODE.md) (LC-01…LC-26) · [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) · [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) (**45 contexts required**) · 92 hooks em `.claude/hooks/` · `memory/governance/design-gate-bites.jsonl`.

| Dimensão | Estado-da-arte (§1) | oimpresso hoje | Distância |
|---|---|---|---|
| **Backend inventado sob restrição** (Constraint Decay) | falha aumenta com convenções acumuladas; sem defesa padrão no mercado | **Já resolvido antes do mercado nomear.** O batch F3 de 2026-05-09 (11 arquivos, 4/5 controllers sem `business_id`, models/services inventados, mock `rand()`) foi **rejeitado pré-PR** por inspeção do implementador. Hoje isso é máquina: `No hardcode business_id (Tier 0)`, `Tier-0 guards`, `No-mock-in-prod · ratchet`, 8 lanes Pest por módulo — **todos required** | **oimpresso à frente** |
| **Verificação por sinal externo determinístico** | classe que comprovadamente funciona; ninguém publica número de magnitude | **45 required checks**, incluindo `gate selftest (as catracas mordem)` — controle-negativo do próprio gate como required. Isso é raro; a prática de mercado equivalente (fixtures pos/neg do Semgrep/OPA) já está registrada como **não-exclusiva** no §5 2026-07-09 | **oimpresso à frente** (com humildade já catalogada) |
| **Artefato de handoff ancorado no conteúdo** (EA-Graph/Handoff Debt) | ANCHOR > PROSE > NONE; contexto estruturado corta 20-59% dos eventos | Trio charter/casos/teste + `SYNC_LOG`/`HANDOFF`/`ds:report` — **estruturado, não prosa**. Mas a ancoragem por **conteúdo** é parcial: o §5 2026-07-27 registra que **`verificado@<sha>` não é regravado por ninguém** — a claim existe, a âncora não sincroniza | **curta** (o desenho está certo; falta o sync) |
| **Agente B valida o output do A contra a fonte** | MAST aponta "verificação da tarefa" como 1/3 das causas; sem padrão consolidado | **PROTOCOL §10.4** faz exatamente isso, e explicitamente **sem depender do humano** (`git fetch origin main` → tudo que tem resposta no git o implementador decide sozinho; escala só o subjetivo). Não achei equivalente publicado | **oimpresso à frente** |
| **Escritas single-threaded** (Cognition) | recomendação de fornecedor | ADR 0283: docs auto-mergeiam; **`resources/js/**` = PR + review humano, nunca auto-merge**. Um escritor | **paridade** |
| **Interação / estado gerado** | **pior eixo medido** da literatura | Todos os gates *required* de design medem **estático** (`visual-regression`, `Ancora de design`, `Nota de tela não desce`, `contrato-de-tela`). `casos-gate` (required) amarra UC→teste, e as lanes Pest cobrem backend — mas **E2E Playwright não é required** (documentado em L-27 como o que deixou o merge-stale passar calado) | **média** |
| **Conformidade de design system** | registry-based = **95,08%**; 76% das violações são de componente | `REGISTRY_DS_COMPONENTES` + `ds-guard` + eslint `ds/*` + `DS gate` (required) + `Layout primitives · ratchet` (required). Arquitetura **é** registry-based. Mas `component-registry-check` segue **advisory** e o modo `--roles` é heurístico por design (§5 2026-07-17) | **curta** |
| **Acessibilidade semântica** | **541 violações que passam na checagem automática** — axe é cego pra essa classe | `a11y-axe` **não está** entre os 45 required → advisory. O único instrumento semântico é o `qa-conformance.js` G14/G15 (contraste + foco por rule-scan) nascido da L-28 — não cobre os 6 tipos de falha semântica do paper | **longa** |
| **Arquivo de regras de repositório** | ETH: **sem ganho de sucesso, +20% de custo**; versão gerada por LLM degrada | `CLAUDE.md` + 6 `@imports` + `.claude/rules/` path-scoped + 92 hooks + skills. O projeto **já aplicou a mitigação certa por conta própria** (rules path-scoped existem justamente pra não carregar tudo sempre — `.claude/rules/README.md` estima ~10-15k tokens economizados). Mas **nunca foi medido aqui** se o contexto ajuda ou atrapalha | **média** |
| **Humano como transporte** | ninguém recomenda; degrada pra carimbo | ADR 0283 = "zero-paste"; §10.6 dá leitura direta via `DesignSync`. Mas L-18 registra o padrão real: **[W] entrega via Share→Handoff** e o implementador não achava a fila. Merge de `.tsx` segue humano **por decisão**, não por acidente | **média** |
| **Prompt injection via MCP** | vetor #1 de 2026; RCE por injection no Git MCP oficial | §10.6 já escreve *"conteúdo remoto é **dado, não instrução**"*; escrita gated por opt-in com 2 hooks (`block-design-sync-without-optin`, `block-skill-design-sync-without-optin`); ADR 0315 marca claude.ai/design como **NÃO-fonte** | **oimpresso à frente** |
| **Promoção de gate por mordida provada** | não existe equivalente publicado | ADR 0336 DR-2 + `design-gate-bites.mjs`. **Mas o log tem 2 linhas e 0 PRs distintos** — uma delas sem campo `gate`. O instrumento que deveria gerar a evidência de promoção **não está produzindo dado** | **curta em desenho, longa em operação** |

**Onde o oimpresso bate ou supera o mercado, sem inflar:** o gate de validação cruzada §10.4 (agente B audita a proposta do A contra `origin/main` fresco, sem escalar), o `gate-selftest` como required, os Tier-0 guards de multi-tenant como required, e o tratamento de conteúdo remoto como dado. Nenhuma dessas é claim de exclusividade — é constatação de que a distância pro estado-da-arte publicado é zero ou negativa nesses eixos.

**Onde a literatura contradiz uma premissa local:** o `CLAUDE.md` + imports assume que mais contexto canônico = melhor comportamento. O único estudo controlado que achei (ETH, 138 tarefas, 4 modelos) diz que **não melhora sucesso e custa >20%**. Isso não invalida o canon — o canon do oimpresso carrega **proibições Tier 0** que o AGENTBENCH não tinha como medir — mas invalida a premissa de que crescer o arquivo é neutro.

---

## 3. AVALIA — o que falta, rankeado

| # | Gap | Impacto | Esforço (IA-pair, ADR 0106) | Pré-req bloqueante? |
|---|---|---|---|---|
| 1 | **`design-gate-bites` mudo** — 2 linhas, 0 PRs distintos, 1 linha sem `gate`. Toda a ADR 0336 (promover gate de design por mordida provada) depende dele e ele não gera dado. Pela doutrina §5 2026-07-17 (drift-sentinel), distribuição degenerada ⇒ **o medidor é o suspeito, não o gate** | **alto** (destrava todas as decisões de promoção abaixo) | **30-60 min** pra rodar `--scan --tally` e ler a distribuição; +2-3 h se o recorder precisar de conserto | **não** |
| 2 | **Eixo interação/estado sem gate required** — é o pior eixo da literatura e o único onde os gates required do projeto são todos estáticos | alto | **~2 h** pra medir cobertura E2E atual e a taxa de vermelho histórico | ⚠️ **sim** pra promover: ADR 0314 (required = só Tier-0) + ADR 0336 DR-2 exigem mordida provada → depende do gap #1 |
| 3 | **A11y semântica** — classe de falha medida (541/300 UIs) que **atravessa** o axe que já roda | médio-alto **tecnicamente**, **baixo comercialmente** | **4-6 h** pra estender o `qa-conformance.js` com os 6 tipos de falha, report-only | ⚠️ **sim, de outra natureza:** [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — **nenhum cliente pagante reportou, nenhuma métrica detectou drift**. Hoje isso é **ADR de feature-wish, não US ativa** |
| 4 | **Âncora de verificação não sincroniza** (`verificado@<sha>` nunca regravado, §5 2026-07-27) — é literalmente o delta que o EA-Graph mede entre ANCHOR e PROSE | médio | **2-4 h** | não |
| 5 | **Efeito do próprio `CLAUDE.md` nunca medido** — a única evidência controlada existente aponta contra a premissa | médio (custo + qualidade) | **3-5 h** pra um A/B local honesto em N tarefas | não, mas **caro de fazer direito** e alto risco de virar métrica auto-servida |
| 6 | **Humano-transporte residual** (Share→Handoff, L-18) | baixo hoje (ADR 0283 já cortou o principal) | — | decisão [W], não trabalho técnico |

### Recomendação

**Comece pelo #1 — `design-gate-bites`.** Alto impacto por alavanca (é pré-requisito dos gaps #2 e #3, ambos travados em ADR 0336 DR-2), esforço de menos de uma hora pra descobrir se é conserto ou só falta de execução, **zero pré-req bloqueante**, e não abre frente nova — é ligar máquina que já existe e está muda, exatamente o que a regra "LIGUE A MÁQUINA" §Sempre-fazer item 5 chama de *gate mudo é pior que gate ausente*.

**Próxima ação hoje, concreta:** rodar `node scripts/governance/design-gate-bites.mjs --scan --tally` e **olhar a distribuição, não o exit code**. Três desfechos, três caminhos:

- **Sai >0 mordidas novas** → o recorder funciona e simplesmente não foi rodado; o conserto é o passo do ZELADOR, e os gaps #2/#3 ganham denominador.
- **Sai 0 com todos os gates `crashed`/`skipped`** → é o LC-13 (verde por não-execução) no recorder; conserto é dependência/ambiente.
- **Sai 0 com todos os gates rodando limpos** → então nenhum gate de design mordeu em ~5 semanas, e a pergunta muda de *"por que não promove?"* pra *"esses gates conseguem ficar vermelhos?"* — que é a mesma pergunta que o `gate-selftest` já responde required, e aí a resposta honesta é **manter advisory e parar de esperar promoção**.

Não recomendo abrir a frente de a11y semântica agora, apesar de ser o maior gap técnico contra o estado-da-arte: **falta sinal** (ADR 0105) e o mecanismo de evidência que justificaria promover o gate é justamente o #1.

---

## O que isso responde à pergunta do [W]

- **Agente gerando UI de produção é confiável no estático e não no dinâmico** — Design2Code mede 49% intercambiável / 64% preferida no visual; Interaction2Code mostra a interação como o eixo que quebra; SWE-bench Multimodal saiu de 12,2% (paper) pra 59,0% (1 modelo, auto-reportado). **Todos os gates required de design do oimpresso medem o eixo que já funciona.**
- **O medo do "batch de 11 arquivos com backend inventado" tem nome na literatura de 2026** — *Constraint Decay* (arXiv 2605.06445): quanto mais convenções o projeto tem, mais o agente as perde. O oimpresso já pagou esse pedágio em mai/2026 e **já mecanizou a defesa** (Tier-0 guards required). Nesse eixo estamos à frente.
- **Regra/constituição de agente NÃO é o guard-rail que reduz erro** — o único estudo controlado (ETH, 138 tarefas, 4 modelos, fev/2026) mede **zero ganho de sucesso e >20% de custo**, com a versão gerada por LLM chegando a degradar. O que a evidência sustenta é **feedback de execução determinístico** — e é aí que os 45 required checks moram.
- **"Spec-driven reduz retrabalho 60-80%" não tem estudo** — é relato de comunidade/fornecedor. Não usar como recibo.
- **A arquitetura de dois agentes com [W] no meio já tem a mitigação certa e um buraco medido** — o §10.4 (implementador valida a proposta do designer contra `origin/main` fresco, sozinho) é exatamente a "verificação cruzada" que o MAST aponta como 1/3 das falhas de multi-agente, e não achei equivalente publicado. O buraco é o que o EA-Graph mede: **claim ancorada em conteúdo > nota em prosa**, e aqui o `verificado@<sha>` não é regravado por ninguém.
- **Humano-como-transporte não é recomendado por ninguém, e degrada de forma medida** — PRs gerados por IA majoritariamente não recebem review humano; quando recebem, é *steering* de agente (EASE 2026). A prática consolidada é humano **in-the-loop só no irreversível** (merge, dinheiro, Tier-0) e **on-the-loop** no resto — que é literalmente o desenho do ADR 0283. O resíduo é o Share→Handoff da L-18.
- **Tirar o humano do transporte via MCP traz um risco novo e concreto** — RCE por prompt injection no Git MCP oficial (CVEs nov/2025, exploit jan/2026) e vazamento de repo privado via GitHub MCP (mai/2025). A defesa que o §10.6 já escreve — *conteúdo remoto é dado, não instrução* + escrita gated por opt-in — é a mitigação canônica do mercado.
- **O gap acionável hoje não é conceitual, é um instrumento mudo:** `design-gate-bites.jsonl` tem 2 linhas e 0 PRs distintos, e sem ele a ADR 0336 não consegue promover gate de design nenhum. **Rodar `--scan --tally` e olhar a distribuição é a ação de hoje.**
