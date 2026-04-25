# MemCofre — Comparativo Concorrência (estilo Capterra)

**Última atualização:** 2026-04-25 | **Próx. revisão:** 2026-07-25

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "PMEs que precisam guardar evidências (NF-e, contratos, fotos, conversas) com rastreabilidade" |
| **Setor** | Knowledge management + DMS (Document Management System) |
| **Stage** | Renomeado DocVault → MemCofre 2026-04-24; tabelas docs_* legacy |
| **Persona** | Larissa-operadora (upload) + Wagner (conhecimento do projeto) |
| **JTBD** | "Cofre de evidências e conhecimento — não-operacional + auditoria + IA reusa" |

## Cards comparados

### 🟢 MemCofre (oimpresso)
- ⭐ **Score:** 45/100 (em uso, falta polish)
- 💰 Bundled UPos (sem extra)
- 🎯 **Best for:** Tenant UPos quer cofre simples sem nova licença
- ✨ **Diferencial:** Cofre integrado ao ERP + IA contextual (lê pra Copiloto)
- ☁️ Cloud

### 🔴 Notion
- ⭐ **Capterra:** 4,7/5 (~5000 reviews)
- 💰 Free / US$ 8 Plus / US$ 15 Business
- 🎯 **Best for:** Equipes pequenas, knowledge base + docs
- ✨ **Diferencial:** UX killer + database flexível
- ☁️ Cloud

### 🔴 Confluence (Atlassian)
- ⭐ **Capterra:** 4,4/5 (~3000 reviews)
- 💰 US$ 5,75 Standard / US$ 11 Premium por user
- 🎯 **Best for:** Engenharia, dev teams
- ✨ **Diferencial:** Integração Jira + spaces estruturados
- ☁️ Cloud + on-prem

### 🟡 Obsidian
- ⭐ **Capterra:** 4,8/5 (~500 reviews)
- 💰 Free uso pessoal / US$ 50/ano sync
- 🎯 **Best for:** Knowledge worker individual
- ✨ **Diferencial:** Local-first + markdown + grafo conexões
- ☁️ Local + sync paid

### 🟡 Google Drive / SharePoint
- ⭐ **Capterra:** 4,7/5
- 💰 Bundled Google Workspace / MS 365
- 🎯 **Best for:** Empresa que já usa Google/Microsoft
- ✨ **Diferencial:** Ecosistema completo
- ☁️ Cloud

## Matriz de features

| Feature | 🟢 Nós | Notion | Confluence | Obsidian | GDrive | Importância |
|---|---|---|---|---|---|---|
| Upload arquivos | ✅ | ✅ | ✅ | ⚠ | ✅ | **P0** |
| Tags/categorias | ⚠ | ✅ killer | ✅ | ✅ | ⚠ | **P0** |
| Search full-text | ⚠ | ✅ | ✅ | ✅ | ✅ | **P0** |
| OCR PDF/imagem | ❌ | ⚠ | ⚠ | ❌ | ✅ killer | P1 |
| Versionamento | ❌ | ✅ | ✅ | ⚠ | ✅ | P1 |
| Compartilhamento | ⚠ | ✅ | ✅ | ❌ | ✅ killer | P1 |
| API REST | ⚠ | ✅ | ✅ | ⚠ | ✅ | P2 |
| Integração ERP | ✅ nativo UPos | ❌ | ⚠ | ❌ | ⚠ | **diferencial** |
| IA reuso (Copiloto) | ✅ planejado | ⚠ Notion AI | ⚠ Atlassian AI | ❌ | ⚠ | **diferencial** |
| Bundled (sem fee) | ✅ | ❌ | ❌ | ⚠ | ❌ | **diferencial** |

## Score (Capterra-style)

| Critério | 🟢 Nós | Notion | Confluence | Obsidian | GDrive |
|---|---|---|---|---|---|
| Easy of use | 6 | **9** | 7 | 7 | **9** |
| Features | 4 | **9** | **9** | 7 | 8 |
| Value for money | **9** | 7 | 6 | 8 | 8 |
| Performance | 7 | 8 | 7 | **9** | 8 |
| Mobile | 5 | 8 | 6 | ⚠ 5 | **9** |
| Integrations | 6 | 8 | **9** | 7 | 8 |
| **Total /60** | **37** | **49** | **44** | **43** | **50** |
| **Score /100** | **62** | **82** | **73** | **72** | **83** |

## Estratégia

### Posicionamento
> _"Cofre integrado ao seu ERP — evidências fiscais + conhecimento + IA reusa, tudo sem nova licença."_

### Track imitar
- **MVP:** OCR PDF/imagem (matar P1 #1)
- **Onda 2:** Versionamento + share link
- **Onda 3:** Tags hierárquicas + busca semântica

### Track diferenciar
- **Bundled UPos** (Notion/Confluence cobram à parte)
- **Integração nativa Financeiro/Ponto** (NF-e da venda vai pro cofre auto)
- **Copiloto lê do cofre** (LLM grounding com docs do tenant)

### Preço
- Bundled UPos (zero extra) — mata Notion R$ 40/mês

## Refs

- [Notion — Capterra](https://www.capterra.com.br/.../notion) (4,7/5)
- [Confluence — Capterra](https://www.capterra.com.br/.../confluence)
- ADR DocVault → MemCofre rename 2026-04-24
