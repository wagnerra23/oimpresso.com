---
name: reguas-do-sistema
description: >
  ATIVAR quando Wagner pedir "grade de réguas", "onde sou fraco vs mercado",
  "quais ideias estão acima do mercado", "reaplique a grade", "compare meu
  processo/IA OS com os melhores", OU em cadência: ao fechar uma leva de chips
  da grade anterior, trimestralmente, ou quando o mapa-dos-níveis ganhar
  sucessora. É o irmão do `capterra-senior` apontado pro PROCESSO/IA OS (não
  pra um módulo de produto): pesquisa quem põe a barra 2026 por dimensão,
  REFUTA toda claim de superioridade própria, VERIFICA cada fraqueza contra o
  repo VIVO antes de dar nota (lição 7/9 de 2026-07-09), e entrega grade com
  evidência + chips prontos + rejeitados→proibições §5. Dispara o workflow
  versionado `.claude/workflows/reguas-do-sistema.js`.
tier: B
status: active
version: "1.1"
authority: canonical
related_adrs: [0329-doutrina-documentacao-de-processo-executavel, 0330-mapa-dos-niveis-estado-real-2026-07-constituicao]
---

# Skill: reguas-do-sistema — medir o IA OS contra quem põe a barra

> **Origem (2026-07-09, Wagner):** *"tenho que me comparar a técnicas (réguas) acima do
> mercado… crie o método de como pesquisar e documentar as réguas, para deixar sempre
> arrumado o sistema."* Método destilado da rodada real daquele dia (27 agentes de pesquisa
> + refutação + o desafio do Wagner que derrubou 7/9 notas por mecanismo-já-existente).

## O ciclo que mantém o sistema arrumado (onde esta skill entra)

```
MEDIR (esta skill) → VERIFICAR no repo → CORRIGIR (chips) → TRAVAR (sentinela/gate)
      ↑                                                            ↓
      └────────── índices contínuos apontam o próximo ←── OPERAR ──┘
```

Duas fases do ciclo **já são máquina contínua** (TRAVAR: gate-selftest/sentinelas ·
APONTAR: doc-freshness-score, adr-proposto-parado, DORA/outcome-metrics). Esta skill
é a fase **MEDIR** — periódica, contra o mercado.

## Os três eixos que a grade mede (para não repetir o ponto cego)

O array `DIMS` cobre **três eixos** — não confundir:

1. **CONSTRUIR-E-GOVERNAR** (6 dims originais): spec/governança, design→código, memória/conhecimento, orquestração adversarial, evals-outcome (DORA/agente-DEV) e ERP-IA-produto.
2. **RODAR-E-OBSERVAR** (4 dims add 2026-07-10): `observabilidade-agente`, `qualidade-drift-ia-producao` (a Jana viva em prod — distinta do outcome do agente-DEV), `seguranca-do-agente`, `custo-eficiencia`.
3. **SERVIR-O-NEGÓCIO** (1 dim add 2026-07-10): `inteligencia-de-negocio` — o sistema serve o cliente/negócio (A+B) ou governa a si mesmo (C)? Inteligência de negócio embarcada (Jana-BI com dado real) + cliente-como-sinal (loop `client_signal→cycle_goal` vivo) + equilíbrio de fluxo negócio÷governança. **Foi o ponto cego** que o adversário `adversario-inteligencia-negocio` (2026-07-10) expôs — a grade media como se constrói e como se observa, mas não **pra quem** a energia trabalha. Fonte interna do equilíbrio: `scripts/governance/negocio-vs-governanca-ratio.mjs`. Doutrina anti-atrofia (ADR do modelo 3-camadas, em ratificação).

Até 2026-07-10 a grade só media o eixo 1 — o loop de RODAR-E-OBSERVAR a IA que o sistema produz (a Jana em produção) nunca virava régua, apesar de o rastreador "FECHAR O LOOP DO IA-OS" listar 2 desses como P0 pendentes. Ponto cego registrado na **[ADR 0333](../../../memory/decisions/0333-emenda-0330-eixo-rodar-e-observar-submedido.md)** (emenda à 0330 — Propriedade 5 da doutrina [0329] fechando sobre a própria grade). **Isto só adiciona a MEDIÇÃO** — construir observabilidade/drift/gate é trabalho Tier-0 à parte (decisão de custo do Wagner).

## Como rodar

```
Workflow({ scriptPath: ".claude/workflows/reguas-do-sistema.js",
           args: { base: "<worktree FRESCO do origin/main>" } })
```
Pré-requisito: `git worktree add --detach <path> origin/main` (nunca medir em checkout stale
— guard de base-freshness existe por isso). `args.dimensoes` opcional pra rodada parcial
(re-medir só a dimensão de um chip concluído).

## As 7 regras duras do método (violou = grade rejeitada)

1. **Dossiê do mapa VIVO, nunca de memória** — a Fase 0 lê o `mapa-dos-niveis` corrente
   (0330 ou sucessora) + proibições §5. Dossiê de cabeça = falso-negativo garantido.
2. **Só dimensão grade-ável entra** — critério objetivo (número, artefato verificável ou
   gate on/off). "Qualidade" não é régua; *change-failure-rate* e *% cross-platform* são.
3. **Toda claim "estou acima" passa pelo REFUTADOR** (contexto zero, default derrubar).
   Rodada de referência: 26 claims → 0 acima-puras. Sem refutação, a grade é ego.
   **Anti-Goodhart do próprio refuter** (chip `orq-anti-goodhart`): a fase Refutar injeta
   artefatos PLANTADOS — claims absurdamente falsas — que o refuter TEM que derrubar; se ele
   APROVA um plantado (ACIMA_CONFIRMADO), carimbou (One Token to Fool) e o disclosure avisa.
   É o análogo do gate-selftest no LAYER DE AGENTE (o gate-selftest prova que os SCRIPTS mordem;
   isto prova que o REFUTER discrimina). Contrato + selftest determinístico:
   `scripts/governance/refuter-canary-check.mjs` (catraca `refuter-canary` do gate-selftest).
4. **Nenhuma nota sem VERIFICAÇÃO no repo vivo** — toda fraqueza apontada pela pesquisa é
   caçada no repo (workflows/scripts/skills/hooks/registries) ANTES da nota. Lição 7/9:
   os mecanismos existiam, invisíveis. Achado existia-mas-invisível → **indexar no mapa**
   (é a Propriedade 5 da doutrina 0329 fechando o loop).
5. **Régua sempre com fonte** (produto/feature/prática publicada + link) e **nota sempre
   com evidência** (file:line ou prova de ausência). Retrato **datado** — envelhece por
   design; drift material → re-rodar, nunca editar o retrato velho.
6. **Saída vira ação ou lápide**: fraqueza real → chip (com as ressalvas do adversário
   embutidas no prompt); proposta rejeitada → proibições §5 (não re-propor); claim
   refutada → registrada (não re-alegar sem re-verificar).
7. **Teste de integração antes de "0 acima"** — a refutação é slice-a-slice por
   construção; antes de declarar um diferencial refutado (ou o placar "0 acima"), a Fase
   `Integração` pergunta *"algum peer monta o TODO integrado no mesmo contexto — ERP
   vertical multi-tenant + auto-aplicação recursiva + loop-que-fecha?"*. Sem peer do TODO
   → `DIFERENCIAL_SISTEMA` (instanciação/integração, **não** categoria — proibido re-inflar
   a peça isolada). E **credite o que já shipou** desde o último retrato antes de listar
   gaps. Origem: reanálise Wagner 2026-07-10 (*"foi perdido meus diferenciais"*) — proibições §5.
   Corolário de invocação (corrigido 2026-07-10): a fronteira do tool Workflow **serializa `args`
   pra string** — visto 2× (`args.base` chegava undefined → BASE caía no placeholder → os agentes
   tinham que se auto-curar lendo origin/main na mão). O script **já tolera as duas formas** (parse
   defensivo `typeof args === 'string' ? JSON.parse : args`), então passar objeto OU string funciona.
   Ainda assim confira o dossiê: se o prompt dele contém "AJUSTE: passe args.base", o `base` não chegou.

## Regras 8-15 — lições do adversário formal de 2026-07-12 (mesma força das 7 acima)

> **Origem:** workflow 4-atacantes→contraprojeto→juiz sobre a grade emitida em prosa no
> mesmo dia ([session](../../../memory/sessions/2026-07-12-reguas-adversario-grade.md)).
> Placar: 9/11 notas sobreviveram, mas os 3 números de máquina e o ranking inteiro caíram.
> Cada regra abaixo é um modo de falha REAL daquele dia — não hipótese.

8. **Reconciliação same-day OBRIGATÓRIA antes de emitir grade** — rodar
   `git log --since=<data do retrato-base>` e ler sessões/handoffs do PRÓPRIO dia.
   A grade omitiu o gargalo-mãe (OOM da nightly) cujo fix mergeou no mesmo 12/jul e citou
   US que o handoff do dia declarava consumida. Re-resolver IDs de US contra o estado do dia.
9. **Número de máquina só entra se (a) reproduzível do repo versionado OU (b) rotulado
   com proveniência e limitação** (`k=N`, snapshot de onde, staleness). "64,1" era snapshot
   CT100 parcial não-reproduzível (recompute versionado = 41,0); "Jana 73" era prosa de
   handoff com baseline em 71.
10. **Alerta = output literal do `summarize`/alerts da máquina, NUNCA paráfrase** —
    "alerta full_suite=291" era métrica que tinha MELHORADO (291<298); o alerta armado real
    (`distiller_freshness` 0→6) ficou invisível.
11. **Checar `computed_at`/staleness da fonte antes de tratar métrica como viva** — o 291
    vinha de nightly morta há 6 dias; valor E delta de fonte congelada não sustentam nem
    alerta nem celebração (modo de falha "congelado-e-verde").
12. **Δ por dimensão exige commit datado DENTRO do intervalo entre retratos** — senão
    rotular **"correção de retrato stale"**, não capacidade nova (o Δ+4 de observabilidade
    creditava commits 8 dias anteriores ao retrato-base; o Δ+1 de memória creditava código
    de maio). Corolário estrutural: as notas de cada retrato precisam de artefato
    **versionado no repo** (artifact privado = Δ inauditável) — formato pendente de decisão
    Wagner (proposta em aberto: `memory/reguas/YYYY-MM-DD-notas.json`).
13. **Evidência conta UMA vez e dentro do escopo da régua da dimensão** (o escopo está
    escrito em `reguas-do-sistema.js`) — a tag `business_id` foi dupla-contada em
    observabilidade E segurança, sendo fora do escopo da segunda.
14. **Item GATED exige prova do gate** — full_suite tinha autorização explícita do Wagner
    no dia; LGPD só tinha o FLIP gated (prep executável hoje); cycle em planning era decisão
    deliberada, não bloqueio. Separar sempre **prep agent-executável** de **flip HITL** — e
    listar o gated mais barato antes dos caros.
15. **Grade sem passe adversarial próprio = modo de falha** — rodar refutador interno
    ANTES de publicar. O sistema tem orquestração adversarial nota 8 que no mesmo dia achou
    o que a grade não achou; re-score manual em prosa com aparência do método é exatamente
    o que as regras 3 e 6 já proibiam.

## Onde registrar (fecha o protocolo)

- **Artifact** navegável da grade (padrão: tabela técnica × régua × nota × degrau).
- **Session log** `memory/sessions/YYYY-MM-DD-reguas-<escopo>.md` com placar + links.
- Achados "existia-mas-invisível" → **emenda/sucessora do mapa-dos-níveis** (indexar).
- Rejeitados → `memory/proibicoes.md` §5 (código) ou `PROCESSO_MEMORIA_CC §5` (design).
- Chips → `spawn_task` com verify-antes-de-construir + Tier-0 + para-no-PR.

## Anti-padrões (desta skill)

- ❌ Rodar sem refutação ("10 dimensões dizem que estou acima" — não valem sem cético).
- ❌ Nota por pesquisa-só (a pesquisa NÃO vê o repo; 7/9 provou).
- ❌ Perseguir a nota (Goodhart; errata 0159): a grade aponta ONDE trabalhar — o índice
  sobe como consequência de trabalho real, nunca como alvo.
- ❌ Re-medir tudo a cada chip: rodada parcial por dimensão (`args.dimensoes`) é mais barata.
- ❌ Re-score manual em prosa "porque o retrato é recente": reusar o LADO MERCADO é legítimo
  (regra 5), mas se o LADO PRÓPRIO mudou (merges novos), a rodada parcial `args.dimensoes`
  — que INCLUI refutação — é o caminho. Pular o workflow inteiro = atalho, não uso legítimo
  (adversário 2026-07-12: as 3 notas re-notadas à mão saíram todas com Δ mal-atribuído).
- ❌ Parafrasear alerta ou compor score fora do regime do gerador canônico (regras 9-10):
  se o `sdd-scorecard.mjs` se recusa a compor com `not_yet_measured`, a grade também se recusa.
- ❌ Tratar "GATED no Wagner" como categoria preguiçosa (regra 14): checar se a autorização
  já existe registrada e se só o flip é HITL antes de arquivar o item.
