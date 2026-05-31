---
date: 2026-05-31
hour: 12:20 BRT
topic: Gerência de crescimento — estratégia receita + recalibração de norte (CYCLE-08) + loop anti-drift + dossiê de mercado 28-streams
duration: ~sessão estratégica (paralela à frota de design do dia)
authors: [Wagner, Claude (frosty-greider-83ab2f)]
session_type: estratégia/management (ZERO código de produto, ZERO PR — artefatos canon + 1 hook + recalibração MCP)
---

# Handoff — Gerência de crescimento, loop anti-drift e dossiê de mercado

> Sessão de **gerência**, não de código. Wagner: *"seja o gerente e reestruture como vender mais / lucrar mais... use os tokens em 2h, pesquise na internet, direcione o projeto."* Distinta da frota de design (handoffs 13:54/14:34/15:20 mesmo worktree).

## Estado MCP no momento
- **Cycle:** `CYCLE-08 — Receita — Onda A` (criado nesta sessão, 2026-05-31→06-28, 0% decorrido). 5 goals de negócio (pricing público, 5 migrações+Martinho, MRR R$2k, ComVis V1 m², Agrosys de-risk). **CYCLE-07 "Fundações" fechado com rollover** (0 tasks rolaram = não tinha task rastreada = confirma o drift).
- **my-work:** 30 tasks. P0 já alinhadas ao novo norte: **US-OFICINA-026** (fechar Martinho), **US-FISCAL-018** (Larissa biz=4), **US-SELL-009** (cutover ROTA LIVRE).

## O que aconteceu
1. **Diagnóstico cru:** 56 businesses, 7 com venda, **1 real** (Larissa=99%). Mas 3 ativos fortes: carteira morna 50 (Firebird/Delphi), deal Agrosys (R$2,65M), Jana (IA que age — fosso). Brief mostrou **drift 104/104** (commits 7d fora do cycle) = construindo em vez de vender.
2. **Pesquisa de mercado:** 4 streams iniciais (pricing/legacy/IA/WhatsApp) → depois Wagner liberou orçamento ("muito limite, use") → **workflow 28-streams** (38 agentes, ~2M tokens **no Sonnet** — capacidade livre; weekly all-models estava 81%) com **síntese + verificação adversarial**.
3. **Recalibração de norte (Wagner: "o norte está adequado?"):** veredito = destino certo, **bússola apontada pra engenharia, não receita**. Fechei CYCLE-07 + criei CYCLE-08 "Receita" (métrica-mãe = receita).
4. **Loop anti-drift (Wagner: "vai evitar se perder de novo? o loop completo"):** construído hook forçador + ADR canon; sensor + brief RECEITA especificados (deploy servidor = Wagner).
5. **Revelação financeira (no fim):** Wagner — **R$60k receita/mês, R$40k despesa = ~R$20k lucro**. Muda diagnóstico de "sobrevivência" → **"transicionar cash cow lucrativo sem canibalizar"**. Runway deixou de ser restrição. **Composição dos R$60k = INCÓGNITA pendente** (legado vs oimpresso? quantos clientes? ARPU?).

## Artefatos gerados (canon)
- `memory/sessions/2026-05-31-plano-crescimento-oimpresso.md` — estratégia (4 alavancas, pricing, playbook 90d, Agrosys de-risk)
- `memory/clientes/_pipeline-migracao-legacy.md` — placar 50 clientes em ondas + scripts WhatsApp + scoring
- `memory/sessions/2026-05-31-pricing-landing-copy.md` — hero/3 tiers/comparativo Mubisys/oferta Migração Branca (revisar antes de publicar)
- `memory/sessions/2026-05-31-dossie-mercado-consolidado.md` — síntese 28 streams + **correções da verificação** (TAM oficina R$60bi não R$256bi; ContaAzul R$1,66bi não R$2bi; gatilho = Nuvem Fiscal off 31/07/2026 não multa; Certtus não HiSoft)
- `memory/decisions/proposals/receita-metrica-mae-loop-fechado.md` — **ADR proposta** (ratificar → ~0241): receita=métrica-mãe + loop 5 camadas + SQL sensor + patches brief
- `.claude/hooks/receita-loop-check.ps1` — hook SessionStart forçador (ASCII-puro, **testado: dispara**) + registrado em `.claude/settings.json`

## Persistência
- **Git:** estes arquivos estão no checkout MAIN (D:/oimpresso.com), não no worktree. Commit+push neste fechamento → webhook/cron MCP propaga.
- **MCP:** CYCLE-07 fechado + CYCLE-08 criado já estão no servidor (mutações diretas).
- **BRIEFING:** n/a (sessão não tocou módulo específico).

## Próximos passos pra retomar
1. **🔴 PRIMEIRO — Wagner responde a composição dos R$60k** (legado WR vs oimpresso · quantos clientes · ARPU maior/menor · despesa fixa vs variável). Sem isso a matemática de canibalização fica no escuro.
2. Recalibrar **CYCLE-08 + plano** com a realidade lucrativa: métrica vira **NRR≥100% na base + ARPU igual-ou-maior**, não "12 logos novos".
3. **Construir o teste de desvios** do loop (Pest em `renderCycleDriftAlert` + sensor com fixture `novos_7d=0`/cycle 50% → assert dispara). **Gap admitido: o hook foi verificado que DISPARA, mas não testado que PEGA um desvio real.**
4. Deploy servidor das camadas 1+2 do loop (sensor `revenue-pulse` + seção RECEITA no brief) — Wagner aprova.
5. Ratificar a ADR proposta → vira ~0241.
6. Segunda: snapshot Firebird dos 5 mais quentes + 1ª call (gancho Nuvem Fiscal 31/07).

## Lições catalogadas
- **Estrategizei sobre 3 incógnitas load-bearing** (caixa, MRR real, WTP validada) sem sinalizar cedo. Regra: **perguntar financials ANTES** de montar estratégia. (Wagner expôs ao dar R$60k/R$40k no fim.)
- **Mecanismo ≠ testado:** construí o loop anti-drift mas não o testei contra a falha que ele deveria pegar. "Existe e dispara" ≠ "pega o desvio". Teste de desvios é a peça que falta.
- **PowerShell 5.1 lê .ps1 como Windows-1252** → em-dash "—" (byte 0x94=aspas) e emoji quebram o parser. **Hooks devem ser ASCII-only** (pego e corrigido na hora).
- **Sonnet pra fan-out** quando weekly all-models está alto e Sonnet está 0% — usa a capacidade livre, mais rápido.
- **Verificação adversarial paga:** caçou erros reais de ±20% (oficina TAM 4×, ContaAzul 20%, Bling GMV, gatilho fiscal). Validou o design do workflow.
- **Canibalização é o risco nº1 agora** (R$60k legado = exatamente quem eu mando migrar). Âncora: SaaS anual ≥ gasto legado; 4 módulos/cliente.

## Pointers detalhados
- Output bruto do workflow (5 clusters + verificação + URLs): `tasks/wn2saophr.output` (session temp)
- Frota de design paralela: handoffs 2026-05-31 13:54/14:34/15:20
- ADR 0022 (meta R$5M) · 0026 (posicionamento) · 0094 (Princípio duro 4) · 0105 (cliente-como-sinal) · 0140 (Jana Pro)
