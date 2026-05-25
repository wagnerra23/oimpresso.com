---
slug: 0151-modules-comissao-feature-wish
number: 151
title: "Modules/Comissao como feature-wish — aguarda cliente que reporta dor real"
type: adr
status: proposto
authority: canonical
lifecycle: feature_wish
decided_by: [W]
decided_at: "2026-05-15"
module: Comissao
quarter: 2026-Q4
tags: [arquitetura, modular, cross-vertical, comissao, feature-wish, sinal-qualificado, adr-0105]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: ["0105-cliente-como-sinal-guiar-sem-mandar", "0121-oimpresso-modular-especializado-por-vertical", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios", "0125-modules-autopecas-feature-wish", "0143-fsm-pipeline-live-prod-marco-2026-05-12", "0106-recalibracao-velocidade-fator-10x-ia-pair"]
pii: false
review_triggers:
  - "Larissa (ROTA LIVRE biz=4) reportar que planilha paralela de comissão Eliana[E] está custando >1h/mês OU pedir explicitamente automação"
  - "Vargas assinar (ADR 0125) e demandar policy multi-papel autopeças (vendedor balcão + comprador)"
  - "ComVis ativar primeiro piloto (Vargas/Extreme/Gold) com US-COMVIS-011 ressuscitada (multi-papel banner) como gap operacional medido"
  - "OficinaAuto ativar com Martinho e demandar split multi-mecânico (US-AUTO-011)"
  - "12 meses sem sinal qualificado de NENHUM dos 4 verticais → arquivar `historical` (ADR 0095 lifecycle)"
---

# ADR 0151 — Modules/Comissao como feature-wish (aguarda cliente que reporta dor real)

## Contexto

Em 2026-05-12, durante o discovery de oportunidades cross-vertical, foi proposto criar `Modules/Comissao` ([SPEC](../requisitos/Comissao/SPEC.md)) como módulo cross-vertical com 14 US (US-COMM-001 a US-COMM-014) endereçando 5 modelos de comissão (Sells single-tier, ComVis multi-papel, OficinaAuto multi-mecânico, Autopecas balcão, Marketplaces líquido).

O SPEC foi escrito antecipadamente como base de comparação com o mercado (Bling, Tiny, Conta Azul, Omie, Spiff/CaptivateIQ) e prova que o oimpresso hoje tem ~30-40% das capacidades via UltimatePOS legacy (`transactions.commission_agent`, `users.cmmsn_percent`, `essentials_user_sales_targets`, `ReportController::salesRepresentativeTotalCommission`). Gap real é multi-papel + tier escalonado + accelerator + clawback + side-effects FSM + UI Inertia.

**Problema:** as 14 US foram parseadas pro MCP server em 2026-05-12 e aparecem hoje na triage como P0/P1 sem owner, criando ruído pro time. Mas NENHUMA das US tem cliente pagante reportando a dor — o trigger ADR 0105 ("cliente paga + reporta OU métrica detecta drift").

Estado de cada vertical hoje:

| Vertical | Cliente operando? | Reporta dor de comissão? | Decisão |
|---|---|---|---|
| **Modules/Vestuario (biz=4)** | ✅ ROTA LIVRE prod 2+ anos | ❌ Larissa usa `commission_agent` legacy + planilha Eliana[E] mensal — NÃO pediu automação | Vertical funcional sem automação |
| **Modules/ComunicacaoVisual** | 🟡 em construção (Sprint 1) | ❌ nenhum piloto fechado ainda | Sem vertical ativo = sem demanda real |
| **Modules/OficinaAuto** | ⏸️ aguarda Martinho | ❌ ainda nem ativo | Bloqueado |
| **Modules/Autopecas** | ⏸️ aguarda Vargas (ADR 0125) | ❌ ainda nem ativo | Bloqueado |
| **Modules/Marketplaces** | ❌ não existe | n/a | Não-existente |

Nenhum dos 5 verticais hoje tem cliente pagante que reporta dor explícita de comissão. Larissa (única produção real) opera com sistema legacy + Eliana[E] manual.

## Decisão

**Modules/Comissao permanece como feature-wish** (status `feature-wish`, lifecycle `aguarda-sinal-qualificado`), subordinado a [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) e [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md). SPEC permanece como documentação de referência, mas:

1. **As 14 US (US-COMM-001 a US-COMM-014) NÃO entram em backlog ativo.** Aparecem na triage MCP apenas pra rastreio histórico — não devem ser atribuídas owner nem priorizadas.
2. **Nenhum scaffolding de código novo** em `Modules/Comissao/` até trigger de ativação ser disparado.
3. **SPEC ganha aviso destacado no topo** ("⛔ DORMENTE — ver ADR 0151") pra evitar que dev (incluindo Claude) interprete erroneamente como roadmap ativo.

Razões pra status feature-wish, não ativação imediata:

1. **Larissa (ROTA LIVRE, 99% volume oimpresso) não reportou dor** — `commission_agent` legacy + planilha mensal Eliana[E] funciona. Ativar contra vontade dela viola ADR 0105 ("guiar sem mandar")
2. **ComVis multi-papel (US-COMM-009) é o caso mais forte**, mas depende de ComVis ter 1º piloto vivo. Hoje ComVis está em Sprint 1 sem piloto fechado. Antecipar = código sem cliente que valida
3. **Side-effects FSM (US-COMM-002/003)** dependem de `marcar_pago` / `cancelar_venda` rodarem com volume real. FSM canon ADR 0143 está LIVE biz=1 mas só 14 vendas migradas — volume baixo demais pra validar cálculo automatizado de comissão sem regressão
4. **Risco de drift cálculo** (R1 do SPEC §6) — se a UI atual de comissão (`ReportController::salesRepresentativeTotalCommission`) e a nova divergirem, Eliana[E] precisaria de horas pra conciliar. Sem cliente pedindo, custo>>benefício
5. **Time pequeno (5 pessoas)** já com 4 verticais + Jana V2 demo + FSM rollout + Martinho prod no CYCLE-06. SoC brutal (ADR 0094 §5)

## Princípios derivados

### P1 — Comissão é dor latente, não dor reportada
Comissão automatizada é um dos features mais pedidos em pesquisa de ERP BR-PME (Bling, Tiny vendem como diferencial), mas no oimpresso é dor de mercado ≠ dor de cliente próprio. Esperar cliente próprio reportar antes de codar.

### P2 — Cada vertical decide se PRECISA antes de Comissão ativar
ComVis Sprint 1 pode entregar tudo sem multi-papel automático (US-COMVIS-011 fica `parked`). Se o 1º cliente ComVis pedir cálculo, aí sim ativa Comissao com US-COMM-009 como driver. Não fazer pré-ativação por especulação.

### P3 — Fallback legacy single-tier é suficiente pra biz=4
Larissa segue com `cmmsn_percent` + planilha Eliana[E]. Quando Eliana[E] reportar que planilha custa >1h/mês (métrica detecta drift — ADR 0105 critério 3), aí o ROI de automação justifica.

### P4 — SPEC sobrevive intacto como blueprint quando ativar
SPEC `Modules/Comissao/SPEC.md` é trabalho pré-pago. Quando ativação rolar, dev abre o SPEC, lê os 5 cenários peculiares (A-F), os 6 schema tables, os 14 US Stories e tem ~2 semanas de design já feito. Custo de manter = zero. Benefício pré-pago.

## Trigger condições pra ativar (`feature-wish` → `em_construcao`)

**Pelo menos UM dos abaixo precisa ser satisfeito**, com Wagner [W] aprovando ADR de ativação:

1. **Eliana[E] reporta dor** — planilha mensal de comissão Larissa custa >1h/mês OU comissão calculada errada gerou conflito com vendedor 1+ vezes
2. **Larissa pede automação explicitamente** — não interpretação ("ela vai gostar"), mas pedido textual ("Wagner, calcular comissão sozinho fica melhor pra Eliana")
3. **ComVis 1º piloto ativo demanda multi-papel** — Vargas/Extreme/Gold (depois de ComVis Sprint 1 entregue) pede policy banner R$ 800 split vendedor+designer+instalador (US-COMM-009 é o gateway)
4. **OficinaAuto Martinho ativo demanda multi-mecânico** — Martinho com 2+ mecânicos no balcão e OS com apontamentos por % mão-de-obra (US-COMM-010)
5. **Wagner sinal exploratório** (raro, exceção): Wagner escolhe codar Comissao mesmo sem cliente pra capturar diferencial competitivo Marketplaces (US-COMM-011 base líquida pós-taxa) — exige ADR formal justificando especulação contra ADR 0105

## Alternativas consideradas

### A — Codar US-COMM-001 (schema) + US-COMM-002 (side-effect single-tier) já em CYCLE-06 ✗ rejeitada
- Bate goal "FSM rollout biz=1" do CYCLE-06 (cycle ativo até 2026-05-28)
- TAM: 0 clientes pagantes adicionais (Larissa não pediu)
- ❌ rejeitada: time pequeno + 4 goals zerados no cycle (Martinho, Inter PJ, FSM 14 vendas, Jana V2 demo). Adicionar Comissao = mais dispersão. Wagner regra 2026-05-15: focar.

### B — Mover SPEC pra `proposals/` e remover do parser MCP ✗ rejeitada
- SPEC visível no proposals/ vira "esquecido" — ninguém revisa
- Quando ativação for legítima, dev novo tem que descobrir que existe SPEC
- ❌ rejeitada: SPEC.md no path canônico `memory/requisitos/Comissao/` é a casa correta. Frontmatter `status: dormente` + aviso topo + ADR 0151 dão a governança certa.

### C — Modules/Comissao como feature-wish + SPEC com aviso DORMENTE ✅ ESCOLHIDA
- Status: feature-wish; lifecycle: aguarda-sinal-qualificado
- SPEC fica no path canônico com header destacado
- ADR 0151 (este) registra decisão + triggers de ativação
- Triage MCP pode continuar listando US-* como rastreio histórico, mas guidance documentada é "não atribuir owner"

### D — Deletar SPEC inteiro ✗ rejeitada
- Perde ~2 semanas de design já feito (mapping mercado, 6 schema tables, 5 cenários peculiares)
- Custo de manter ~10KB markdown = zero
- ❌ rejeitada: blueprint pré-pago não deve ser destruído

## Riscos

### 1. Triage MCP continua mostrando 14 US como sem-owner
- Parser MCP atual (em `mcp:tasks:sync`) extrai US-COMM-* do SPEC. Mesmo com aviso topo, podem aparecer
- **Mitigação imediata:** após esta ADR ser merged + push, o time enxerga via triage o aviso "DORMENTE" e o ADR linkado → não pega
- **Mitigação evolutiva:** se time confunde, segunda PR faz cirurgia nos headers `### US-COMM-NNN` (prefix `(DORMENTE)`) pra quebrar parser. Mas custo de cirurgia é maior que benefício hoje (5 pessoas, ninguém vai pegar US-COMM sem perguntar)

### 2. Larissa pode pedir automação enquanto Wagner está focado em outro vertical
- Demanda real pode chegar fora do timing planejado
- **Mitigação:** trigger #1 e #2 desta ADR cobrem; basta criar ADR de promoção quando rolar

### 3. ComVis ativa sem demandar Comissao → US-COMVIS-011 vira tech debt
- ComVis Sprint 1 entrega tudo manual; cliente nunca pede multi-papel
- **Mitigação:** US-COMVIS-011 já está como "parked" no SPEC ComVis (verificar). Se Comissao nunca ativar, US-COMVIS-011 vira `historical`

### 4. Vargas autopecas (ADR 0125) ativa e demanda policy balcão sem invocar Comissao
- Autopecas pode resolver comissão localmente em `Modules/Autopecas` sem invocar shared Comissao
- **Mitigação:** ADR de promoção Vargas decide. Se Vargas demanda multi-papel/tier, ativa Comissao. Se só single-tier, fica em Autopecas standalone

## Consequências

### Positivas
- **Foco preservado** no CYCLE-06 (Martinho prod + FSM rollout + Jana V2 demo) sem dispersão
- **SPEC blueprint pré-pago** intacto pra futuro
- **ADR 0105 respeitado** — sem cliente que reporta = sem código
- **Time MCP** (Felipe/Maiara/Eliana[E]/Luiz) entrando, não precisa entender 14 US órfãs

### Negativas
- **Tech debt latente** — quando ativação rolar, ainda haverá ~6-8 semanas pra implementar P0-P1 (estimate SPEC §5)
- **Concorrentes (Bling/Tiny) vendem comissão automatizada como diferencial agora** — oimpresso fica atrás em pitch de vendas até ativar

### Mitigações
- Pitch comercial oimpresso pode mencionar "comissão automatizada — roadmap Q1/27 com 4 modelos cross-vertical" (transparência radical ADR 0094 §7) — mostra que existe blueprint, não vaporware
- Quando trigger ativar, ADR de promoção referencia este SPEC como `inheritance` — código sai 2x mais rápido (skill `mwart-process` + SPEC pronto)

## Alinhamento com ADRs canon

- **[ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)** — guiar sem mandar; 4 verticais sem sinal qualificado de comissão = não ativar
- **[ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md)** — módulo cross-vertical (transversal aos 4 verticais), respeita §P3 (sinal qualificado)
- **[ADR 0093](0093-multi-tenant-isolation-tier-0.md)** — todas tabelas `commission_*` terão `business_id` global scope quando ativar
- **[ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)** — SoC brutal §5; foco no CYCLE-06
- **[ADR 0125](0125-modules-autopecas-feature-wish.md)** — precedente de feature-wish (Vargas é gatilho)
- **[ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)** — FSM canon side-effects (slot pra Comissao quando ativar)
- **[ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)** — estimates do SPEC já em fator 10x; quando ativar, mantém

## Decisão pendente subordinada

- **Cirurgia parser SPEC** (prefix headers US-COMM-* com `(DORMENTE)`) — deferida até primeiro caso de confusão de dev real. Hoje custo>>benefício
- **Pricing tier Comissao** — quando ativar, calibrar contra Spiff/CaptivateIQ Brasil pricing (research pré-ativação)

## Referências

- [SPEC Modules/Comissao](../requisitos/Comissao/SPEC.md) — blueprint funcional preservado
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — gatilho de ADR (cliente paga + reporta)
- [ADR 0125](0125-modules-autopecas-feature-wish.md) — template ADR feature-wish (Vargas autopecas)
- [SPEC ComVis §14 — US-COMVIS-011](../requisitos/ComunicacaoVisual/SPEC.md) — gateway candidato pra ativação
- [SPEC OficinaAuto US-AUTO-011](../requisitos/OficinaAuto/SPEC.md) — gateway alternativo via Martinho

---

**Última atualização:** 2026-05-15 — ADR criada como parte de organização de tarefas no inventário pós-limpeza (stashes órfãs + branches em PR + triage 30 sem owner). Status `proposed` aguardando review humano. Quando Wagner promover pra `accepted`, próximo `mcp:tasks:sync` deve respeitar aviso DORMENTE no SPEC. Sem trigger satisfeito, código permanece zero.
