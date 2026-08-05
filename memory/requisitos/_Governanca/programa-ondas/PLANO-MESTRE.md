---
id: requisitos-governanca-programa-ondas-plano-mestre
titulo: Programa de Ondas com Adversário por Módulo + Régua de Correção por Tela
status: ativo
owner: W
criado: '2026-07-02'
related_adrs:
  - '0256-knowledge-survival-meia-vida-catraca-sentinela'
  - '0264-governanca-executavel-trio-dominio-e2e'
  - '0271-revisao-gates-ci-estado-real-required-e-subtracao-segura'
  - '0105-cliente-como-sinal-guiar-sem-mandar'
---

# PLANO MESTRE — Programa de Ondas com Adversário por Módulo

> **Este é o plano mestre do sistema inteiro.** Índice das etapas (cada uma em seu arquivo).
> Origem: sessão 2026-07-02 — diagnóstico de que módulos de nota alta escondem cálculo de
> valor indefeso (a camada do incidente `num_uf`, R$ inflado ×100k).

## Status vivo

<!-- catraca: não regride sem mudar status conscientemente · ADR 0294 -->
- **status:** ativo  <!-- proposto→ativo 2026-07-03: [W] aprovou ADR 0320 (Onda 0a) via "aprovado merge" (#3694) -->
- **owner:** W
- **criado:** 2026-07-02 · **reviewed_at:** 2026-08-05 · **próxima-revisão:** 2026-09-04
- **cycle:** off-cycle (programa transversal) · **execução:** `parent_plan=programa-ondas` — **Ondas 0+1+2+3 LANDADAS em paralelo 2026-07-03** (0a-0d, Sells 1.x, Compras 2.x, Financeiro ✅; ~24 PRs #3694-#3726) + **dente Produto** (#3730) e **dente Cliente** (#3731) + **Onda 5 Cliente completa** (4 passos: FICHA 65 + INVENTARIO + 7 US MCP + régua + catraca — #3732/#3742/#3745) + **Onda 6 Fiscal completa** (4 passos: FICHA 75 config/orquestração + INVENTARIO ✅12🟡4❌5 + US-FISCAL-021/022 MCP + régua + catraca — #3738/#3753/#3761). A **Trilha D — documentação técnica e operacional** foi autorizada por [W] em 2026-08-05; sua execução permanece ligada a este mesmo `parent_plan` e às tasks MCP, nunca a status duplicado em Markdown.
- **gate-de-saída (DoD):** ✅ **BATIDO 2026-07-03** — dente de cálculo red/green no CT100 (15 passed, #3695) + `sells-create.yaml` exibindo UX 88 **e** `casos_coverage 0%/🔴` + template calibrado. Ondas seguintes: Produto → Cliente (com OK [W])
- **kill-condition:** ADR 0a rejeitada por [W], OU 2 cycles sem nenhuma etapa executada → status `abandonado` (não zumbi)
- **verdade-viva:** este doc (etapas na tabela abaixo; os arquivos-etapa detalham, o status vive AQUI — 1 plano = 1 registro no índice)

| Etapa | Arquivo | Task MCP | Status | Esforço (ADR 0106) |
|---|---|---|---|---|
| 0a ADR-proposta | onda-0-fundacao/0a | — | ✅ ADR 0320 (#3694) | ~2h |
| 0b Extensão da régua | onda-0-fundacao/0b | — | ✅ (#3698) | ~4h |
| 0c Sentinela de cadência | onda-0-fundacao/0c | — | ✅ (#3697) | ~4h |
| 0d Paridade de migração | onda-0-fundacao/0d | — | ✅ (#3696) | ~6h |
| 1.1 Adversário Sells | onda-1-sells/1.1 | — | ✅ CAPTERRA-FICHA nota 60 (#3699) | ~3h (agent) |
| 1.2 Gaps+backlog Sells | onda-1-sells/1.2 | ⚠️ tasks a criar (OK [W]) | ✅ US-SELL-054..057 no SPEC (#3702) | ~2h + OK [W] |
| 1.3 Régua nas telas Sells | onda-1-sells/1.3 | — | ✅ 8 scorecards (sells-create UX 88 · casos 0%🔴) | ~4h |
| 1.4 Dente de cálculo | onda-1-sells/1.4 | — | ✅ (#3695) | ~6h (CT100) — red/green CT100 (15 passed) |
| 1.5 Catraca+sentinela Sells | onda-1-sells/1.5 | — | ✅ (#3700) | ~2h — emergente de 0c+1.3+1.4 · verificado 2026-07-03 (sem gate novo) |
| **Onda 2 — Compras** (OK [W] 2026-07-03) | — | ⚠️ tasks a criar | ✅ ciclo completo | — |
| 2.1 Adversário Compras | (template) | — | ✅ CAPTERRA-FICHA capacidade **nota 34** + BRIEFING (#3719/#3714) | ~3h |
| 2.2 Gaps+backlog Compras | (template) | ⚠️ 10 US no INVENTARIO (#3717) | ✅ backlog materializado | ~2h |
| 2.3 Régua Compras/Index | (template) | — | ✅ charter + `compras-index.yaml` | ~2h |
| 2.4 Dente de cálculo Compras | (template) | — | ✅ `CalculoValorComprasTest` E2E valor+estoque `POST /purchases` (#3722) + lane MySQL (#3723) | ~4h (CT100) |
| **Onda 3 — Financeiro** (OK [W] 2026-07-03) | — | ⚠️ tasks a criar | ✅ camada de correção | — |
| 3.dente Financeiro | ancorado em [`_Roadmap_Faturamento.md`](../../_Roadmap_Faturamento.md#camada-de-correção-contínua-dente-de-cálculo--programa-de-ondas) (ADR 0320 — encaixe T6) | — | ✅ `calculatePaymentStatus`+`updateGroupTaxAmount` red/green CT100 (#3710) | ~6h |
| 3.régua Financeiro | ancorado no roadmap (ADR 0320) | — | ✅ charter+casos+régua CR/CP (#3712) → **decisão [W]: deprecar CR/CP → Unificado** (#3718) | ~4h |
| **Onda 4 — Produto** (OK [W] 2026-07-03) | — | ⚠️ tasks a criar | 🟡 só o dente por ora | — |
| 4.dente Produto | (template 1.4) | — | ✅ `CalculoValorProdutoTest` — motor preço/margem indefeso: markup/`calc_percentage`+`get_percent`+`getVariationGroupPrice`+combo, 21 passed CT100 (#3730) | ~4h (CT100) |
| **Onda 5 — Cliente** (OK [W] 2026-07-03) | — | ✅ 7 US no MCP | ✅ ciclo completo (4 passos) | — |
| 5.1 Adversário Cliente | (template) | — | ✅ CAPTERRA-FICHA capacidade **nota 65** (10 concorrentes, foco LGPD) (#3732) | ~3h (agent) |
| 5.2 Gaps+backlog Cliente | (template) | ✅ US-CRM-079..085 (7) | ✅ INVENTARIO (✅7·🟡11·❌1) + §3-bis SPEC (#3742) | ~2h |
| 5.3 Régua telas Cliente | (template) | — | ✅ 7 scorecards (Show UX 86 · casos 0% · Ledger d1 🟡) (#3745) | ~3h |
| 5.4 Catraca+sentinela Cliente | (template) | — | ✅ emergente — verificado 2026-07-03: ratchet bloqueia `cliente-show` 86→70 (exit 1); sentinela `exposicao-tier0` cobre 7 telas PII-Tier0 (sem gate novo) | ~1h |
| **Onda 6 — Fiscal** (OK [W] 2026-07-03 · camada fiscal · sinal Larissa biz=4 pre-canary) | — | ✅ 2 US no MCP | ✅ ciclo completo (4 passos) | — |
| 6.1 Adversário Fiscal | (template) | — | ✅ CAPTERRA-FICHA capacidade **nota 75** (config/orquestração: motor tributário/regras ICMS-ISS/DF-e/eventos/SPED; 8 concorrentes — TecnoSpeed/PlugNotas/Nuvem/Focus + Bling/Tiny/Omie) (#3738) | ~3h (3 agents) |
| 6.2 Gaps+backlog Fiscal | (template) | ✅ US-FISCAL-021 (P0 IBS/CBS) + 022 (P1 health-check cert) | ✅ INVENTARIO (✅12·🟡4·❌5) + SPEC; dedup pegou US-FISCAL-019 (cache já done) (#3753) | ~2h |
| 6.3 Régua telas Fiscal | (template) | — | ✅ 7 scorecards (Nfe UX 84 · Sped UX 68 **d1 aplica ✔** cross_check/golden✘ · casos 0% G-2; 4 agents) (#3761) | ~3h (4 agents) |
| 6.4 Catraca+sentinela Fiscal | (template) | — | ✅ emergente — verificado 2026-07-03: ratchet bloqueia `fiscal-sped` 68→50 (exit 1 · "PR bloqueado"); sentinela `exposicao-tier0` cobre telas fiscal-Tier0 (peso 3); casos-gate vê 7 casos.md (débito −13) (sem gate novo) | ~1h |
| **Passo 5 — SDD por módulo** (transversal · [W] "pode fazer" 2026-07-27) | [passo-5-sdd-por-modulo.md](passo-5-sdd-por-modulo.md) | — | 🔜 Onda 1 = Fiscal · Compras · Ponto (3 sessões paralelas) | custo do chip **não medido** (a Onda 1 é a medição) |
| **Trilha D — documentação técnica e operacional** ([W] 2026-08-05) | § Trilha D deste plano | [US-INFRA-048](../../Infra/SPEC.md#us-infra-048--ativar-a-documentação-técnica-e-operacional-ponta-a-ponta) · `parent_plan=programa-ondas` | 🟡 D0 em execução; merge ratifica | cadência contínua, 1 achado acionável por vez |

> Onda 3 (Financeiro) **encaixa no `_Roadmap_Faturamento.md`** por [ADR 0320](../../../decisions/0320-programa-ondas-regua-correcao.md) (T6 — Faturamento é canon macro; correção transversal ancora lá, status vivo aqui). Não é doc paralelo. Mesmo padrão valerá pra NfeBrasil/RecurringBilling.

> Estimativas em horas-agente IA-pair (fator 10x ADR 0106); tarefas humano-limitadas (OK [W], canary) seguem relógio real.

## Por que existe

Telas migradas (`/perfil`) e módulos de nota alta (`Financeiro` 82) escondem **cálculo de
valor indefeso**. Verificado em `origin/main` (2026-07-02):

- **6/6 métodos de cálculo core sem teste** (`calculateInvoiceTotal`, `getTotalPaid`≠`getTotalAmountPaid`, `calculatePaymentStatus`, `updateGroupTaxAmount`, `recalculateSellLineTotals`).
- **211 telas Tier-0 sem teste de comportamento** (E2E=4 de 242 telas).
- **31 migrações Blade→React sem nenhuma verificação de paridade**, 0 gate.

A causa raiz: as **3 réguas do projeto não se sobrepõem** e deixam um buraco no meio —
`screen-grade` (UX), `module-grade` (estrutura), `.casos.md` (comportamento, **ortogonal, fora
das notas**). Ninguém liga "a tela funciona" à foto por tela. Nota de garantia ≈ **28/100**
ponderada por risco Tier-0.

## Princípio: reusar + plugar + encaixar (não construir paralelo)

O projeto **já tem todas as peças**. Este programa:
- **Reusa** — `capterra-senior` (adversário), `/comparativo` (gaps+backlog+changelog), `screen-grade`, a catraca/sentinela da [ADR 0256](../../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md).
- **Pluga** — a dimensão de comportamento/valor que falta na régua por tela (não funde — funde destruiria a clareza "tela bonita ≠ tela testada").
- **Encaixa** — respeita a regra T6: roadmaps ativos seguem (OficinaAuto Fase 3, PaymentGateway), e Financeiro/NfeBrasil/RecurringBilling entram no `_Roadmap_Faturamento.md` existente, não em paralelo.

## Régua + âncoras de estado-da-arte 2026 (nota atual)

| Âncora (o "topo" 2026) | Mede | oimpresso |
|---|---|---|
| Property-based + golden money datasets (fintech QA) | Cálculo de valor correto | **15** |
| Infection PHP (`min-msi 85 / covered 95`) | Testes pegam bug injetado | **20** (mutation-gate advisory, não roda c/ Pest) |
| Pact consumer-driven (interaction-level) | Contrato defendido | **25** (casos-gate required, 8% coberto) |
| Parallel-run / GitHub Scientist / strangler fig | Migração preservou função | **8** (zero mecanismo) |
| Coverage ratchet + Google Test Certified | Cobertura sobe até um piso | **40** (catraca sem piso) |
| Enforcement/durabilidade ([ADR 0256](../../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)) | Máquina que faz durar | **70 — mas guarda a porta errada** |

O insight: **D8=70 vs D1=15**. A máquina de governança é de classe mundial, mas protege
segurança (multi-tenant/PII/secrets) e é cega no cálculo de dinheiro. O caminho é
**reapontar**, não reconstruir — coerente com a fase de subtração da [ADR 0271](../../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md).

## O ciclo-padrão de UMA onda (4 passos + passo 5 desde 2026-07-27)

Cada onda de módulo roda estes 4 passos, reusando ferramentas que já existem:

1. **Adversário concorrente** — agente `capterra-senior` → `CAPTERRA-FICHA.md` (nota 0-100 vs 10-15 concorrentes, P0-P3).
2. **Gaps + backlog + changelog** — skill `/comparativo <Mod>` → `CAPTERRA-INVENTARIO.md` (3 buckets ✅🟡❌) + batch `tasks-create` (MCP) + US no SPEC + changelog.
3. **Régua por tela (com a dimensão que falta plugada)** — `screen-grade` (UX) **+ `casos_coverage`** (UCs que defendem + status) **+ dente de cálculo** (D1) se toca valor.
4. **Catraca** — trava nota + `casos-gate` + a sentinela de cadência reporta o débito das 3 camadas.
5. **SDD derivado do fonte** — agent [`sdd-from-source`](../../../../.claude/agents/sdd-from-source.md) → `SDD-tela-*.md` (§5 fluxo + §6 CU) + `<Tela>.casos.md` + contrato Pest + `Implementado em:` no SPEC. **Passo novo** (o programa é de 2026-07-02; o agent só existe desde a [ADR 0351](../../../decisions/0351-sdd-from-source.md), 2026-07-24). Plano de execução em sessões paralelas — chip = **módulo**, telas seriais dentro dele: **[passo-5-sdd-por-modulo.md](passo-5-sdd-por-modulo.md)**.

## Fila de ondas (T6 — encaixar, não duplicar)

- **Roadmaps ativos seguem intactos:** OficinaAuto Fase 3 (canary Martinho), PaymentGateway (smoke/canary).
- **Faturamento é o canon macro:** ondas de Financeiro / NfeBrasil / RecurringBilling **encaixam** em `_Roadmap_Faturamento.md`.
- **Novas ondas (operacionais sem programa), por exposição×débito:** **Sells (piloto) → Compras (nota 59) → Produto → Cliente**. Cada uma exige OK [W] antes de abrir.

## Encaixe na governança de planos existente (anti-paralelo)

- Este programa vive ao lado de [`_Governanca/roadmap/`](../roadmap/) (P01-P10, etapas SDD) **sem sobrepor**: aquele roadmap cuida da suíte/gates de Governance; este cuida do ciclo adversário+régua **por módulo de negócio**. Se qualquer etapa daqui colidir com um P-item de lá, o P-item vence e esta etapa vira referência a ele.
- Registro no índice de planos vivos: **só este arquivo** carrega o bloco `## Status vivo` (ADR 0294 — 1 plano = 1 registro no [`PLANS-INDEX-GENERATED.md`](../../_processo/PLANS-INDEX-GENERATED.md)); os arquivos-etapa apontam pra cá.
- Execução rastreada no MCP (`tasks-create` com `parent_plan=programa-ondas`, ADR 0070) — nunca status em markdown de etapa.

## Trilha D — documentação técnica e operacional

> [W] autorizou esta trilha em 2026-08-05 a partir das **máquinas que já existiam**. Ela não
> cria índice, roadmap, agente, gate ou cópia HTML. Reusa o inventário derivado, os donos
> documentais, o MCP, o workflow `documentacao-tecnica` e a rota humana
> [`/documentacao`](https://oimpresso.com/documentacao).

### D.1 Objetivo e fronteira

Fechar o ciclo **medir → traduzir → publicar → operar → detectar drift → aprender → medir de
novo** para quatro frentes inseparáveis:

| Frente | Inclui | Dono principal |
|---|---|---|
| **Infraestrutura** | Hostinger, Proxmox, CT 100, GitHub Actions, Windows/Firebird, rede, PBX, SVN e dispositivos | `memory/reference/infra-*.md` + `memory/requisitos/Infra/RUNBOOK-*.md` |
| **Plataforma** | hooks, MCP, CI, skills, agents, scripts, baselines e observabilidade | índices gerados + arquitetura/Runbooks Jana, Forja e Infra |
| **Aplicação** | kernel, módulos transversais, verticais e integrações | `SCOPE.md` + `BRIEFING.md` + `SPEC.md` + `ARCHITECTURE.md` + `RUNBOOK-*.md` |
| **Fluxos** | venda, estoque, financeiro, fiscal, WhatsApp, Jana, migração, deploy e recuperação | `GUIA-DO-SISTEMA.md` aponta; detalhe permanece no dono do fluxo |

**Fora de escopo:** documentação de produto por tela, cópia manual de inventário, reescrita de
ADRs aceitas, criação de máquina de governança e correção de achado adjacente durante outra etapa.

### D.2 Onde cada estado vive

| Estado | Fonte única | Regra |
|---|---|---|
| Intenção, ondas e DoD | este plano | um único `## Status vivo` |
| Execução | tasks MCP | `parent_plan=programa-ondas`; `todo/doing/done` nunca duplicado aqui |
| Fatos técnicos e procedimentos | documentos donos no Git | ponteiro > cópia; segredo só por referência ao Vaultwarden |
| Inventários | `PAINEL-SISTEMA.md` + `MAQUINAS-INVENTARIO.md` | sempre derivados; nunca editar à mão |
| Visão humana | [`oimpresso.com/documentacao`](https://oimpresso.com/documentacao) | renderiza `memory/GUIA-DO-SISTEMA.md`; exige autenticação |
| Prova de correção | `documentation-loop.mjs` | o mesmo ID precisa desaparecer no recibo antes→depois |

### D.3 Ondas executáveis

| Onda | Escopo | Saída no dono existente | Gate de saída |
|---|---|---|---|
| **D0 · ativar e medir** | inventários, donos, criticidade e gaps | esta seção + navegação do Guia + tasks MCP | plano ligado ao MCP; inventários `--check`; baseline documental registrada |
| **D1 · infraestrutura crítica** | Hostinger, CT 100, Proxmox e GitHub Actions | referências de infra + runbooks de acesso/deploy/rollback/saúde | operador identifica onde roda, valida saúde e recupera sem editar servidor |
| **D2 · hooks e governança** | hooks, CI, skills, agents, scripts e baselines | índice derivado + explicação humana por família | cada família declara gatilho, bloqueio/advisory, risco, bite/release e diagnóstico |
| **D3 · MCP ponta a ponta** | Git→sync→cache→servidor→tool→audit | arquitetura Jana/MCP + runbooks de acesso, deploy, drift e recovery | auth, `business_id`, 401/403/404, reindexação e auditoria reproduzíveis |
| **D4 · módulos críticos** | Sells, Estoque, Financeiro, Fiscal, Repair e Jana | cinco portas aplicáveis por módulo | responsabilidade, requisito, arquitetura, superfície e operação alcançáveis |
| **D5 · verticais, integrações e legado** | Vestuario, ComunicacaoVisual, OficinaAuto, WhatsApp, NFe/NFSe, gateways, Officeimpresso/Firebird | mesmos donos modulares | integrações e recuperação documentadas sem misturar produto com sistema |
| **D6 · fluxos ponta a ponta** | venda, cancelamento, fiscal, WhatsApp, IA, migração e deploy | diagramas Mermaid e ponteiros no Guia/donos | ator, máquina, módulo, dado, auth, tenant, retry, falha parcial e rollback explícitos |
| **D7 · continuidade** | backup, restore, segredos, perda de host e incidentes | auditoria Ops/DR + runbooks | RPO/RTO medidos, drill seguro, responsável e evidência datada |
| **D8 · publicação e manutenção** | `/documentacao`, onboarding e ciclo recorrente | Guia + donos corrigidos | navegação humana alcança todas as frentes; detectores reexecutados; tasks fechadas |

Ordem interna das ondas modulares: **kernel/transversais críticos → plataforma → verticais →
integrações → legado**. Uma onda pode avançar só até o próximo bloqueio humano; não abre trabalho
paralelo para esconder dependência.

### D.4 Ciclo de uma unidade de trabalho

1. **Selecionar:** uma task MCP e exatamente um achado acionável.
2. **Medir antes:** abrir fonte/configuração real, executar inventário/probe e guardar o ID estável.
3. **Localizar o dono:** corrigir o arquivo que já responde pelo assunto; nunca criar resumo paralelo.
4. **Traduzir:** explicar para humano sem copiar tabela gerada nem congelar contagem em prosa.
5. **Validar tecnicamente:** fonte, links, diagrama, dependências, tenant, PII e ausência de segredo.
6. **Validar operacionalmente:** executar o runbook no ambiente correto e atualizar `last_validated`
   somente quando o resultado real bateu.
7. **Provar:** comparar `origin/main` com o trabalho pelo `documentation-loop`; alteração de data não fecha.
8. **Entregar:** PR de uma intenção; [W] ratifica pelo merge.
9. **Publicar:** confirmar a rota humana no próximo deploy de código ou `quick-sync` manual.
10. **Fechar e aprender:** task `done`, registro de sessão/handoff e lição quando houve erro ou incidente.

### D.5 Batimento que mantém a trilha ativa

| Momento | Máquina existente | Efeito |
|---|---|---|
| Mudança em PR | staleness/impacto documental | mostra módulos e donos afetados; não edita automaticamente |
| Batimento agendado | `system-map` + `memory-health` | atualiza retratos derivados e acusa integridade/fato quebrado |
| Revisão semanal | `briefing-code-staleness` + `documentation-loop --snapshot` | oferece a fila de drift; o ZELADOR escolhe um item |
| Execução | workflow `.claude/workflows/documentacao-tecnica.js` | snapshot → correção no dono → recibo → PR, exatamente um item |
| Revisão do plano | `plan-health` + `jana:plan-drift` | acusa plano velho, ligação fantasma ou status divergente das tasks MCP |
| Incidente | runbook + `LICOES_CODE.md`/`proibicoes.md` quando aplicável | devolve o aprendizado ao próximo ciclo de medição |

O batimento é deliberadamente **advisory**: detecta e oferece trabalho, mas não decide conteúdo nem
merge. Manter ativo significa haver task aberta, revisão fresca e consumo regular dos achados — não
adicionar outro gate.

### D.6 Definição de pronto da trilha

- toda máquina crítica tem dono técnico, probe e runbook validado;
- hooks e tools MCP são inventariados por máquina e explicados por família para humanos;
- cada módulo ativo alcança suas portas documentais aplicáveis sem lista manual concorrente;
- cada fluxo crítico declara auth, `business_id`, dado, observabilidade, falha e recuperação;
- runbooks críticos carregam `owner` e `last_validated` sustentados por execução;
- `/documentacao` navega por infraestrutura, plataforma, módulos, fluxos e operação;
- os detectores do escopo foram reexecutados e todo resíduo ficou fechado ou explicitamente justificado;
- as tasks MCP do `parent_plan=programa-ondas` não deixam trabalho concluído marcado como aberto.

## Índice das etapas (arquivos)

| Etapa | Arquivo | O que entrega |
|---|---|---|
| Onda 0a | [onda-0-fundacao/0a-adr-proposta.md](onda-0-fundacao/0a-adr-proposta.md) | ADR que trava o mecanismo |
| Onda 0b | [onda-0-fundacao/0b-extensao-regua.md](onda-0-fundacao/0b-extensao-regua.md) | `casos_coverage` + dente de cálculo na régua |
| Onda 0c | [onda-0-fundacao/0c-sentinela-cadencia.md](onda-0-fundacao/0c-sentinela-cadencia.md) | sentinela `exposicao-tier0.mjs` + cron |
| Onda 0d | [onda-0-fundacao/0d-paridade-migracao.md](onda-0-fundacao/0d-paridade-migracao.md) | artefato+gate de paridade Blade↔React (a pior dimensão: 8/100) |
| Onda 1.1 | [onda-1-sells/1.1-adversario-capterra.md](onda-1-sells/1.1-adversario-capterra.md) | ficha de mercado de Sells |
| Onda 1.2 | [onda-1-sells/1.2-gaps-backlog-changelog.md](onda-1-sells/1.2-gaps-backlog-changelog.md) | inventário + backlog + changelog |
| Onda 1.3 | [onda-1-sells/1.3-regua-por-tela.md](onda-1-sells/1.3-regua-por-tela.md) | telas Sells gradeadas c/ comportamento |
| Onda 1.4 | [onda-1-sells/1.4-dente-calculo.md](onda-1-sells/1.4-dente-calculo.md) | teste que pega o `num_uf` |
| Onda 1.5 | [onda-1-sells/1.5-catraca-sentinela.md](onda-1-sells/1.5-catraca-sentinela.md) | trava os ganhos |
| Template | [template-onda-modulo.md](template-onda-modulo.md) | gabarito p/ próximos módulos |

## Sequência de execução

**Onda 0 primeiro** (a máquina) → **Onda 1 Sells** (calibra + prova) → **template** pronto → próximos módulos com OK [W]. A ADR-proposta (0a) abre tudo; nada de código antes dela.
