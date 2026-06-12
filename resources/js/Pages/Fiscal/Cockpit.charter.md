---
page: /fiscal
component: resources/js/Pages/Fiscal/Cockpit.tsx
page_id: fiscal-cockpit
url: /fiscal
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho, 0114-prototipo-ui-cowork-loop-formalizado]
prototypes:
  - "prototipo-ui/.../fiscal-page.jsx §8 FiscalCockpit"
---

# Charter — `Fiscal/Cockpit`

## Mission

Dar à pessoa fiscal (Eliana contadora + Wagner operador) **visão consolidada do estado fiscal do mês** em até **3 segundos** — KPIs de emissão (NF-e/NFC-e/NFS-e/faturamento), alertas determinísticos críticos (rejeições + cert vencendo + DF-e pending), e quick links pras 6 sub-páginas operacionais.

## Goals (Definition of Done PR #2)

1. **6 KPI cards eager** (não-deferred — first paint): emitidas mês, autorizadas + pct, rejeitadas (com pulse), faturamento, DF-e pending, cert vencimento dias
2. **Mini-sparklines SVG** nos 4 KPIs principais (últimos 14 dias)
3. **Alertas determinísticos** (3 níveis crit/warn/info) computados em PHP sem LLM — rejeições 7d + cert <60d + DF-e pending
4. **6 quick-link cards** pra sub-páginas (2 ativos sub-pages 2/3/5 + 3 disabled futuras 4/6/7)
5. **Multi-tenant Tier 0**: NfeEmissao + NfseEmissao + NfeDfeRecebido + NfeCertificado via HasBusinessScope (ADR 0093)
6. **Permissão**: `fiscal.access` gate
7. **Pest biz=1** (ADR 0101): KPIs isolation + alertas determinísticos + permission gate

## Non-Goals (PR #2)

- ❌ Drill-down via click no KPI (vai pra sub-página correspondente, sem filtros pré-aplicados)
- ❌ Período custom (mês corrente fixo — 14d sparkline default)
- ❌ Export PDF/Excel do cockpit (PR futuro)
- ❌ Alertas push (Whatsapp/email) — só visual no cockpit
- ❌ ⌘K palette (PR #3 do roadmap)
- ❌ Sub-páginas 4 (DF-e), 6 (Config), 7 (SPED) — apenas placeholders disabled

## Anti-hooks

- 🚫 Não fazer N+1 query nos sparklines — agrupar com `selectRaw('DATE(emitido_em)...')` 1× e iterar em PHP
- 🚫 Não fazer Inertia::defer nos KPIs — first paint cockpit deve mostrar números (sparklines aceitam ms de delay)
- 🚫 Não cachear KPIs por business (multi-tenant Tier 0 já garante scope — cache só agregado)
- 🚫 Não usar LLM pra gerar alertas — receita determinística por estado (cstat/dias/pendentes)
- 🚫 Não exibir PII (CPF/CNPJ destinatário) em KPI/alerta — usar referências abstratas ("2 rejeições", "5 DF-e")
