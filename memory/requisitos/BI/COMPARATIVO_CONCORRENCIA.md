# BI — Comparativo Concorrência (estilo Capterra)

> Módulo perdido na migração 6.x; revisitar.

**Última atualização:** 2026-04-25 | **Próx. revisão:** 2026-07-25

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "PMEs que querem dashboards customizados sobre dados do POS sem exportar pra Excel" |
| **Setor** | Business Intelligence + Data viz |
| **Stage** | Perdido na migração; baixa prioridade vs Copiloto que já entrega insights |
| **Persona** | Gestor analítico + Wagner |
| **JTBD** | "Cruzar venda × ponto × financeiro × estoque sem virar planilha gigante" |

## Cards comparados

### 🟢 BI (oimpresso)
- ⭐ **Score:** 0/100 (não-existente)
- 💰 Bundled UPos (planejado)
- 🎯 **Best for:** Tenant UPos quer dashboard custom sem learning Power BI
- ✨ **Diferencial planejado:** Pré-built sobre tabelas UPos + sem ETL

### 🔴 Metabase
- ⭐ **Capterra:** 4,5/5 (~500 reviews)
- 💰 Free Self-hosted / US$ 85+ Cloud Pro
- 🎯 **Best for:** Dev/data team que prefere SQL+drag-drop
- ✨ **Diferencial:** Open source + UI limpa
- ☁️ Cloud + on-prem (Docker)

### 🔴 Microsoft Power BI
- ⭐ **Capterra:** 4,6/5 (~10k reviews global)
- 💰 US$ 10-20 Pro por user / US$ 4995 Premium per capacity
- 🎯 **Best for:** Empresa que usa MS 365
- ✨ **Diferencial:** Maturidade + DAX + ecossistema MS
- ☁️ Cloud

### 🟡 Looker Studio (Google)
- ⭐ **Capterra:** 4,4/5 (~5k reviews)
- 💰 Free
- 🎯 **Best for:** Quem usa Google Workspace + GA
- ✨ **Diferencial:** Free + integração Google profunda
- ☁️ Cloud

### 🟡 Tableau
- ⭐ **Capterra:** 4,5/5 (~2k reviews)
- 💰 US$ 70-115 por user
- 🎯 **Best for:** Mid-large enterprise, analista dedicado
- ✨ **Diferencial:** Visualizações poderosas + comunidade
- ☁️ Cloud + on-prem

## Matriz de features

| Feature | 🟢 Nós | Metabase | PowerBI | Looker | Tableau | Importância |
|---|---|---|---|---|---|---|
| Drag-drop charts | ❌ | ✅ | ✅ killer | ✅ | ✅ killer | **P0** |
| SQL custom | ❌ | ✅ killer | ✅ DAX | ⚠ | ✅ | P1 |
| Dashboards | ❌ | ✅ | ✅ | ✅ | ✅ | **P0** |
| Filtros interativos | ❌ | ✅ | ✅ | ✅ | ✅ | P1 |
| Embedding | ❌ | ✅ killer | ✅ | ⚠ | ✅ | P2 |
| Schedule + email | ❌ | ✅ | ✅ | ⚠ | ✅ | P1 |
| Pré-built UPos templates | ✅ planejado | ❌ | ❌ | ❌ | ❌ | **diferencial** |
| Sem ETL (lê direto MySQL UPos) | ✅ planejado | ⚠ | ❌ | ⚠ | ⚠ | **diferencial** |

## Score (Capterra-style)

| Critério | 🟢 Nós | Metabase | PowerBI | Looker | Tableau |
|---|---|---|---|---|---|
| Easy of use | 0 | 7 | 7 | **9** | 6 |
| Features | 0 | 8 | **10** | 7 | **10** |
| Value for money | 0 | **9** (FOSS) | 7 | **10** | 5 |
| Performance | 0 | 8 | **9** | 8 | **9** |
| Mobile | 0 | 7 | 8 | 8 | 8 |
| **Total /50** | **0** | **39** | **41** | **42** | **38** |
| **Score /100** | **0** | **78** | **82** | **84** | **76** |

## Estratégia

### Posicionamento (planejado)
> _"Dashboard pré-pronto sobre seu POS — sem ETL, sem aprender Power BI."_

### Recomendação
**NÃO COMPETIR HEAD-TO-HEAD** — mercado dominado, ROI baixo pra reimplementar. Em vez disso:

### Alternativas
1. **Embeddar Metabase** (FOSS) com templates pré-built UPos — 1 sprint
2. **Templates Looker Studio** com conector MySQL — 1 sprint, dev externo
3. **Migrar pra Copiloto** — chat IA responde "quanto vendi mês passado?" sem precisar dashboard

### Decisão a tomar
Probably matar BI standalone e investir tudo em **Copiloto** — IA conversational mata a necessidade de drag-drop dashboard pra PME média.

## Refs

- [Metabase — Capterra](https://www.capterra.com.br/.../metabase) (4,5/5)
- [Power BI — Capterra](https://www.capterra.com.br/.../powerbi)
- [Looker Studio — Google](https://lookerstudio.google.com)
