---
slug: financeiro-avancado-dre-fluxo-conciliacao
number: TBD
title: "Modules/FinanceiroAvancado — DRE BR completa + Fluxo Projetado IA + Conciliação Automática"
type: adr
status: proposed
authority: canonical
lifecycle: canon
proposed_by: claude-research-agent
proposed_at: 2026-05-12
decided_by: []
decided_at: null
module: FinanceiroAvancado
tags: [financeiro, dre, fluxo-caixa, conciliacao, plano-contas, ia-jana, multi-tenant]
supersedes: []
amends: []
extends: ["financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo"]
related: [0035, 0093, 0094, 0104, 0106, 0143]
pii: false
---

# ADR proposed — Modules/FinanceiroAvancado canon

## Status

**Proposed 2026-05-12.** Aguarda Wagner aprovar D1-D6 antes de virar canon (`status: accepted` + número definitivo).

## Contexto

Stack financeiro oimpresso atual tem **3 módulos parallel** que cobrem operacional + contábil + cobrança:

- `Modules/Financeiro` — operacional (AR/AP + Dashboard 4-estados + Plano de Contas básico)
- `Modules/Accounting` (UPos upstream) — contábil formal (partida dobrada, DRE auditável)
- `Modules/RecurringBilling` — cobrança boleto/Pix (Asaas + Inter + C6 drivers prontos)

**SPEC.md US-FIN-001..014** specifica 14 stories operacionais; **9 já implementadas** (Dashboard, Título CRUD, Baixa, Plano de Contas Model, Strategy boleto CNAB, Extrato Inter sync, Relatorios gerencial). Lacuna competitiva vs **Conta Azul + Omie**:

1. Conciliação bancária **automática** com IA (Conta Azul/Omie têm; nós temos só ingestão Inter)
2. DRE BR **estrutura formal 10 linhas** com drill-down + token shareable contador (US-FIN-011 specced sem código)
3. Fluxo de caixa **projetado diário** 30/60/90d com cenários what-if (US-FIN-007 specced sem código)
4. Categorização **IA** de extrato (Conta Azul beta — diferencial possível Jana)
5. **Margem real per venda** (raro no mercado; diferencial verdadeiro cruzando Inventory+Comissão)
6. **Dunning automática** (Asaas tem nativo; nosso é manual CTA `wa.me`)

[_ANALISE-FINANCEIRA-CROSS-CLIENTE.md](../../research/clientes-legacy-officeimpresso/_ANALISE-FINANCEIRA-CROSS-CLIENTE.md) (2026-05-11): cliente Martinho (R$ [redacted Tier 0]M receita 12m) tem **R$ [redacted Tier 0]M em inadimplência** (76,7%) — sem dunning automática, migração isolada não agrega ROI.

## Decisões propostas

### D1 — Estender `Modules/Financeiro` vs criar `Modules/FinanceiroAvancado` separado?

**Proposta:** **criar `Modules/FinanceiroAvancado` separado**.

| Argumento | Estender Financeiro | FinanceiroAvancado separado |
|---|---|---|
| SoC brutal ADR 0094 §5 | ⚠ Financeiro vira gigante | ✅ camada analítica/IA isolada |
| Permission/license commercial | ⚠ tudo gratuito ou nada | ✅ upsell Pro/Enterprise (R$ [redacted Tier 0] Pro inclui Avançado) |
| Compreensão Larissa | ✅ 1 menu só | ⚠ 2 menus (mitigável topnav agrupado) |
| Disable independente | ❌ | ✅ tenant pequeno desabilita |
| Manutenção | ⚠ 100 arquivos num módulo | ✅ 30 arquivos novos, leitura cross |
| Histórico ARQ-0005 | — | ✅ mesmo padrão Accounting paralelo |

**Decisão:** separar. Topnav exibe "Financeiro" como entry point + tab "Avançado" pra quem tem permissão.

### D2 — DRE realtime cálculo vs snapshot mensal congelado?

**Proposta:** **híbrido**.

- **Exibição:** realtime sempre que Larissa abrir `/financeiro-avancado/dre?mes=2026-05` (queries com cache 60s + invalidação eventos `TituloBaixado`/`TituloCriado`/`NfeEmitida`)
- **Snapshot congelado:** quando contador clica "Fechar mês" → cria `fina_dre_snapshots` row imutável + `fina_dre_share_tokens` para auditoria/SPED Contábil futuro
- **Recompute histórico:** proibido após `congelado_em` (audit trail). Correção fiscal → cria nova revisão referenciando original.

### D3 — Horizonte fluxo de caixa projetado?

**Proposta:** **30/60/90d configurável tenant** com default **30d**.

| Horizonte | Caso de uso | Performance |
|---|---|---|
| 7d | overview semanal Larissa | < 50ms |
| 30d | default Wagner (decisão antecipar) | < 200ms |
| 60d | planejamento trimestre | < 500ms |
| 90d | planejamento estratégico | < 1s (cache 5min) |

Realm > 90d = especulação demais (LGPD: dados retentivos de cliente recorrente). Cap 90d hardcoded.

### D4 — Categorização IA Jana — confidence mínima auto-aceitar?

**Proposta:** **90% após N=3 confirmações histórico mesmo padrão `descricao_normalizada`**.

- **<70%:** rejeita silenciosa, vai pra fila manual
- **70-89%:** sugere com badge "Jana sugere" + Larissa confirma
- **≥90% + ≥3 confirmações histórico** mesma descrição: auto-aceita + log `fina_ia_categorization_log.accepted_by='auto'`
- **Reverter:** janela 24h sem auditoria; após 24h precisa motivo (LGPD audit).

Mitigação enviezamento R2 (SPEC §7): Larissa pode "treinar Jana" — corrigir categoria errada vira feedback few-shot no prompt do Agent.

### D5 — Plano de Contas — template padrão Simples Nacional vs custom per-business?

**Proposta:** **3 templates BR + custom per-business**.

- **Template 1 — Simples Nacional** (default; ~80% PMEs BR): 4 níveis, ~60 contas seed (Receita Vendas / Receita Serviços / Custo Mercadoria / Despesas Admin / Despesas Comercial / Despesas Financeiras / Imposto DAS)
- **Template 2 — Lucro Presumido** (~15%): 5 níveis, ~120 contas seed (separa PIS/COFINS/IRPJ/CSLL)
- **Template 3 — Lucro Real** (~5%): 5 níveis, ~200 contas seed (subcontas analíticas)
- **Custom:** Larissa importa CSV formato CRC ou clona template + edita árvore

Seed roda em `php artisan financeiro-avancado:install --template=simples_nacional` per business. Já aceita reordenar/inativar via UI (US-FINA-018).

### D6 — Conciliação manual vs auto-aceitar score >95%?

**Proposta:** **auto-aceitar score ≥95% com janela reverter 24h**.

- **≥95%:** auto-aceita (cria `fin_titulo_baixa` + `fina_bank_reconciliations.confirmed_by='auto'`)
- **80-94%:** sugere em painel `/financeiro-avancado/conciliacao` — Larissa aceita batch
- **<80%:** lança em "Não conciliado" — manual ou descarte com motivo
- **Reverter:** botão na tela "Conciliações últimas 24h" desfaz baixa + cria audit log
- **>24h:** precisa estorno via FSM action `estornar_baixa` (audit trail completo)

Mitigação R3 (SPEC §7): se Inter API down >2h → Centrifugo notifica + Larissa sobe OFX manual fallback (US-FINA-006).

## Consequências

**Positivas:**
- DRE BR formal **completa** competitiva com Conta Azul/Omie (atualmente score 53/80 → estimativa 68/80 após Onda 2)
- Conciliação **automática** elimina ~2h/dia Larissa (5 dias/sem × 10 min médio × 25 lançamentos)
- Diferencial verdadeiro **margem real per venda** raro no mercado BR
- Dunning automática **ataca direto** problema Martinho R$ [redacted Tier 0]M inadimplência (ADR feature wish — 0105 cliente como sinal)
- Bridge tables only — **zero duplicação** com Financeiro/Accounting/RecurringBilling (ADR 0094 §5)

**Negativas:**
- +30 arquivos novos (Models/Services/Controllers/Tests)
- IA categorização precisa **prompt engineering** + treino few-shot (custo Brain B/Sonnet ~$0.001/transação inicial; cai com cache embedding)
- DRE realtime cache invalidation **complexa** (4 eventos × N contas × cache invalidação) — risco bug L4 trust
- Risk Martinho 76% inadimplência: dunning automática **pode incomodar** clientes desorganizados (oportunidade ou rejeição?)

**Negativas mitigáveis:**
- Backfill `fina_margin_analysis` histórico 12m exige job batch (~5min biz=4 ROTA LIVRE 50k transactions); rodar OOH 03h BRT
- Categorização IA exige **embedding model** local Ollama (CT 100) ou OpenAI fallback ($0.0001 / 1k tokens) — escolha em D4 v2 futuro

## Alternativas consideradas

1. **Não construir nada — manter Financeiro só operacional:** rejeitada — score Capterra 53/80 vs Conta Azul 68/80, perde clientes Modules/Vestuario pra concorrente
2. **Comprar Accounting upstream e estender:** rejeitada — Accounting é partida dobrada formal; Larissa não entende; vira pesadelo UX
3. **Integrar API externa (Conta Azul/Omie):** rejeitada — vira white-label deles, perde controle dados + LGPD; viola Tier 0 multi-tenant ADR 0093
4. **Construir só conciliação (sem DRE/projeção/margem):** rejeitada — perde alavanca diferencial; dunning sem DRE não justifica preço Pro R$ [redacted Tier 0]

## Quando reavaliar

- Se mais de 5 clientes verticais ativarem FinanceiroAvancado e <30% usarem margem per venda → reavaliar US-FINA-023/024/025 retorno
- Se Jana categorização confidence média <80% após 3 meses → revisar D4 (threshold mais alto ou trocar model)
- Se Conta Azul lançar dunning IA agressivo BR → revisar D6 + US-FINA-026..028 prioridade
- Se Receita Federal mudar estrutura DRE BR (Reforma Tributária IBS/CBS 2027) → revisar D5 templates + US-FINA-007 estrutura

## Referências

- [SPEC.md](../../requisitos/FinanceiroAvancado/SPEC.md)
- [MATRIZ-ROI.md](../../requisitos/FinanceiroAvancado/MATRIZ-ROI.md)
- [ROADMAP.md](../../requisitos/FinanceiroAvancado/ROADMAP.md)
- ADR ARQ-0005 Financeiro `memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md`
- [ADR 0143](../../0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM canônico em prod (action `marcar_pago` integra)
- [ADR 0094](../../0094-constituicao-v2-7-camadas-8-principios.md) — princípio §5 SoC brutal
- [ADR 0106](../../0106-recalibracao-velocidade-fator-10x-ia-pair.md) — estimates IA-pair calibrados
