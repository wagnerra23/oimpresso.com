---
slug: 0237-norte-fluxo-caminhao-north-star
number: 237
title: "Norte — Fluxo do Caminhão é o North Star canônico do projeto (visão, não Design System)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: "2026-06-03"
module: null
quarter: 2026-Q2
supersedes: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0061-conhecimento-canonico-git-mcp-zero-automem
tags: [north-star, visao, design, showcase, prioridade, anti-poluicao]
pii: false
review_triggers:
  - fluxo_real_do_caminhao_diverge_das_7_cenas
  - modulo_novo_nao_mapeia_em_nenhuma_costura
  - wagner_decide_evoluir_visao_estruturalmente
---

# ADR 237 — Norte — Fluxo do Caminhão é o North Star canônico do projeto

## Contexto

Em 2026-06-03 implementamos, a partir de um handoff bundle do **Claude Design**
(`claude.ai/design`, origem no `chat40` da sessão de dark theme + DS-tempero), a peça
**"Norte — Fluxo do Caminhão"** em [`resources/js/Pages/_Showcase/Norte.tsx`](../../resources/js/Pages/_Showcase/Norte.tsx)
(rota `/showcase/norte`, `superadmin`), deployada no staging CT100.

Não é tela CRUD: é uma **apresentação navegável de 7 cenas** contando a jornada de um caminhão
de ponta a ponta pelo ERP (Recepção → Diagnóstico → Aprovação → Execução → Venda → Nota →
Financeiro → volta pro CRM), com a **costura** (a passagem automática entre módulos) como herói
de cada cena.

Wagner decidiu (textual, 2026-06-03): *"não acho que o branco fica padrão. mas quero esse como
norte para o projeto"* — i.e. **manter light como padrão do DS** e **adotar o Norte como o North
Star** que guia o projeto. E perguntou como fazer isso **sem repoluir** o conhecimento canônico.

## Decisão

1. **O "Norte — Fluxo do Caminhão" é o North Star canônico do projeto.** Lar único: a página
   `_Showcase/Norte.tsx`. Este ADR é o **registro da decisão + a regra**; **não duplica** o conteúdo
   da página (1 conceito = 1 lar + ponteiro, nunca cópia — [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md)).

2. **Regra de priorização (o anti-poluição).** Toda feature/módulo novo passa pelo crivo:
   *"qual das 7 costuras isto resolve?"*. Se não encaixa no fluxo, **não entra** — filtra escopo na
   entrada (casa com [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md), cliente-como-sinal).
   O Norte para de poluir o **backlog**, não o gera.

3. **NÃO muda o Design System.** Light/branco **continua o padrão** (Wagner explícito). O dark do
   Norte é **estilo de showcase escopado** (`.nx-root`, tokens via `var()`) — aditivo, não contradiz
   `UI-0009` (sidebar light) nem `UI-0013` (Constituição UI v2). Dark global + `--stage-*` seguem
   **proposta separada**, fora deste ADR.

4. **Evolução sem poluir.** O Norte evolui editando **a mesma** página — nunca um `Norte-v2`
   paralelo. Se a visão mudar de forma estrutural, este ADR é **superseded** por um novo
   (append-only), não editado.

## Justificativa

- **Por que North Star e não "mais uma tela":** dá ao time um eixo único de prioridade — o brief de
  qualquer módulo vira *"como esta peça se encaixa no fluxo e qual costura resolve?"*.
- **Por que reduz poluição:** concentra a visão em **1 artefato visual + 1 ADR-ponteiro**, e
  transforma a própria visão num **filtro de escopo** (menos backlog inflado, menos protótipo órfão).
- **Por que não mexer no DS:** trocar o padrão claro→escuro seria mudança Tier-0 do DS sem sinal de
  cliente; Wagner optou por manter light. O Norte entrega a visão **sem** esse custo.

## Consequências

- ✅ Eixo de prioridade explícito; 1 fonte da verdade visual; backlog filtrado pela costura.
- ⚠️ A página showcase precisa acompanhar o fluxo real conforme os módulos evoluem (ver
  `review_triggers`). `--stage-*` fica escopado no Norte até o PR de tokens entrar no main.
- ◻️ Acesso interno (`superadmin`) — é régua de produto, não tela de cliente.

## Referências

- Página: [`resources/js/Pages/_Showcase/Norte.tsx`](../../resources/js/Pages/_Showcase/Norte.tsx) · rota `/showcase/norte` · staging: `https://staging.oimpresso.com/showcase/norte`
- Handoff: [`memory/handoffs/2026-06-03-1416-norte-fluxo-caminhao-showcase-staging.md`](../handoffs/2026-06-03-1416-norte-fluxo-caminhao-showcase-staging.md)
- Design loop: `prototipo-ui/SYNC_LOG.md` (entradas 2026-06-03 [CD]/[CL])
- Origem: handoff bundle Claude Design `oimpresso-erp-comunicacao-visual` (`chat40`)
- Constituição: [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) · DS UI: `UI-0009`, `UI-0013` (light default, não contradito)
