---
id: resources-js-pages-fiscal-cockpit-charter
page: /fiscal
component: resources/js/Pages/Fiscal/Cockpit.tsx
related_prototype: prototipo-ui/cowork/fiscal-page.jsx
bundle_source: fiscal-page.jsx
page_id: fiscal-cockpit
url: /fiscal
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-002]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0358-doutrina-de-teste-tenant-98-supersede-0101, 0104-processo-mwart-canonico-unico-caminho, 0114-prototipo-ui-cowork-loop-formalizado]
prototypes: [prototipo-ui/cowork/fiscal-page.jsx]
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

## Contrato da fila de alertas (Onda 1 Cowork · 2026-09-03)

Destilado do alvo `prototipo-ui/cowork/fiscal-page.jsx:125-137` (`FxAlerts`) e do que a
Onda 1 entregou. Descreve o que a seção **é**; a regra de negócio segue sendo do
`computeAlerts()` (determinístico, anti-hook abaixo).

| Item | Contrato |
|---|---|
| Âncora | `data-contract="alertas-fiscais"` na raiz da lista |
| Posição | entre o `.fx-ribbon` e o `WriteOffAuditoriaCard` |
| Estado vazio | **nó ausente** — zero alertas não desenha contêiner nem mensagem |
| Item | `.fx-alert` com `data-level` ∈ `crit` · `warn` · `info`; flex, `gap:10px`, `padding:10px 14px`, `radius:10px` |
| Ordem dos filhos | ícone (`.fx-alert-ic`) → texto (`.fx-alert-t` com `<b>`+`<small>`) → botão |
| Tinta do nível | fundo a 6% e borda a ~30% do tom sobre a superfície neutra; `info` fica **sem** tinta |
| Ícone | `aria-hidden` (redundante com o nível, que já está no texto e na moldura) |
| Botão | `<Button>` do DS via `btnProps('ghost')`, alinhado à direita |
| Ordem da lista | a que o backend mandou — a tela não ordena, filtra nem agrupa |

**Dois vocabulários cruzam a fronteira PHP → TSX, e os dois falham em silêncio:**
`goto` é o `id` de uma sub-página (`nfe` · `fiscal_config` · `dfe`), resolvido pelo
`_lib/paginas-fiscais.tsx` — **nunca** um caminho; e `icon` é o vocabulário do protótipo
(`audit` · `shield` · `receipt`), traduzido em `_lib/icones-alerta.ts`. Valor fora do mapa
não levanta erro: o botão ou o ícone apenas não são desenhados. Quem defende os dois é o
`UC-FCKP-08` do [`Cockpit.casos.md`](./Cockpit.casos.md).

## Anti-hooks

- 🚫 Não fazer N+1 query nos sparklines — agrupar com `selectRaw('DATE(emitido_em)...')` 1× e iterar em PHP
- 🚫 Não fazer Inertia::defer nos KPIs — first paint cockpit deve mostrar números (sparklines aceitam ms de delay)
- 🚫 Não cachear KPI cross-tenant/agregado — a chave de cache DEVE incluir o `business_id` (Tier 0 ADR 0093; canon no código: `CockpitController` usa `fiscal:cockpit:kpis:biz:{id}`, provado por `CockpitCacheTest`). ⚠️ Corrigido 2026-07-06: o anti-hook anterior mandava o OPOSTO ("cache só agregado") — obedecê-lo criaria vazamento cross-tenant. Achado do adversário de arquitetura (V1, session 2026-07-06).
- 🚫 Não usar LLM pra gerar alertas — receita determinística por estado (cstat/dias/pendentes)
- 🚫 Não exibir PII (CPF/CNPJ destinatário) em KPI/alerta — usar referências abstratas ("2 rejeições", "5 DF-e")
