# Recomendações por módulo — decisões 2026-04-22

Revisão dos 29 módulos encontrados em todas as branches (atual, `main-wip-2026-04-22`, `origin/3.7-com-nfe`, `origin/6.7-bootstrap`). Um resumo por módulo com ação recomendada.

## Legenda
- ⭐ **Prioridade alta** — Wagner marcou ou é bloqueador
- ✅ **Manter** — módulo OK, não precisa mexer (ou já migrar no futuro)
- 🔄 **Substituir** — descartar código atual e usar pacote padrão do mercado
- ❓ **Investigar** — precisa mais dados antes de decidir
- ❌ **Descartar** — não restaurar nem migrar; funcionalidade redundante ou legacy

## 🟢 Ativos no branch atual (15)

| Módulo | Ação | Justificativa |
|--------|------|---------------|
| **Accounting** | ✅ Manter | Contabilidade Brasil — 91 views, módulo grande. Avaliar migração gradual para React |
| **AiAssistance** | ❌ **Desativar** | Wagner declarou "não útil". Overlap com plano IA-first novo |
| **AssetManagement** | ✅ Manter | Gestão de ativos — 17 views, escopo médio |
| **Cms** | ✅ Manter | Conteúdo do site — 45 views. Baixa urgência de migrar |
| **Connector** | ✅ Manter | API core — 55 rotas. Crítico para integrações |
| **Crm** | ✅ Manter | CRM — 68 views, 13 permissões. Módulo grande, migrar por áreas |
| **Essentials** | ✅ Manter | HRM — 87 views, 23 permissões, 36 migrations. Maior módulo, priorizar fatiamento |
| **Manufacturing** | ✅ Manter | Fabricação — 20 views |
| **PontoWr2** | ✅ Manter (em migração) | Módulo novo WR2 — 40 rotas, migrando telas para React |
| **ProductCatalogue** | ✅ Manter | Catalogue QR — 8 views, pequeno |
| **Project** | ✅ Manter | Gestão de projetos — 43 views |
| **Repair** | ✅ Manter | Assistência técnica — 52 views, 12 permissões, 7 hooks UltimatePOS |
| **Spreadsheet** | ✅ Manter | Planilhas — 7 views, pequeno |
| **Superadmin** | ✅ Manter | Admin SaaS — crítico para licenciamento |
| **Woocommerce** | ✅ Manter | Integração WC — 19 rotas |

## ⚪ Inativos no branch atual (5)

| Módulo | Ação | Justificativa |
|--------|------|---------------|
| **Officeimpresso** | ❌ **Descartar** | Licenciamento desktop legado. Funcionalidade coberta por Superadmin + Connector |
| **Officeimpresso1** | 🗑️ **APAGAR AGORA** | Mesmo namespace e provider que Officeimpresso → bug de colisão garantido. Resquício de tentativa antiga |
| **IProduction** | ❓ Investigar overlap com Grow | Ambos são "gestão de produção". Wagner priorizou Grow → IProduction provavelmente redundante |
| **Writebot** | ❌ **Desativar** | Overlap com AiAssistance + plano IA novo. Manter UMA estratégia de IA (OpenAI direto via `openai-php/laravel` já no composer) |
| **Grow** | ⭐ **Manter + avaliar** | Wagner: prioridade produção. 797 rotas + 957 views (mini-ERP CodeCanyon Perfect Support). Decisão pendente: manter PHP, migrar gradual, ou reescrever |

## ❌ Perdidos na migração 3.7 → 6.7 (9)

| Módulo | Ação | Justificativa |
|--------|------|---------------|
| **BI** | ❌ Descartar | Business Intelligence. Substituir por Metabase/Superset externo se precisar |
| **Boleto** | 🔄 Substituir (F15) | Pacote `eduardokum/laravel-boleto` — decisão prévia do Wagner |
| **Chat** | ❓ Investigar | WhatsApp/Telegram/Email. Perguntar se backend era Z-API/Evolution (valioso) ou stub (descartar) |
| **Dashboard** | 🔄 Substituir | Nosso Dashboard React novo já vai cobrir. Descartar o antigo |
| **Fiscal** | 🔄 Substituir (F15) | Pacote `nfephp-org/sped-nfe` — decisão prévia do Wagner |
| **Help** | ❌ Descartar | Docs externas (Notion/Gitbook/ReadTheDocs) servem melhor |
| **Jana** | ❌ Descartar | Overlap com IA-first novo + OpenAI. **Branding "Jana" pode virar o nome da nova IA** se Wagner gostar |
| **Knowledgebase** | ❌ Descartar | Mesmo destino do Help |
| **codecanyon-ticketing** | ❓ Investigar | Ver se é mesmo base code do Grow (renomeado). Se sim, Grow substitui |

## Perguntas em aberto para o Wagner
1. **Chat** funcionava? Tinha Z-API/Evolution real?
2. **IProduction** vs **Grow** — eram complementares (Grow de suporte + IProduction de fábrica) ou redundantes?
3. **codecanyon-ticketing** pode ser o predecessor do Grow? (mesmo ID 32094844 no nome do diretório)
4. Manter branding "Jana" como nome da nova IA ou criar identidade nova?

## Ações executadas em 2026-04-22 (limpeza imediata)
- 🗑️ Apagado `Modules/Officeimpresso1/` (colisão de namespace resolvida)
- ❌ `Writebot` e `AiAssistance` removidos de `modules_statuses.json`
- Caches do Laravel limpos

## Próximas ações
1. **Dashboard React** (próxima tela do roadmap Fase 13.2)
2. Investigar 3 pendências acima com Wagner
3. Spec do Grow mais detalhada (é prioridade declarada)

---

**Última atualização:** 2026-04-22
