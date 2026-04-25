# LaravelAI — Comparativo Concorrência (estilo Capterra)

> Módulo técnico — adapter unified IA pros outros módulos consumirem.

**Última atualização:** 2026-04-25 | **Próx. revisão:** 2026-07-25

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "Outros módulos do oimpresso que precisam de IA sem amarrar provedor" |
| **Setor** | Infrastructure (não cara-final) — adapter LLM |
| **Stage** | Spec-ready, scaffold mínimo |
| **Persona** | Dev (Wagner + futuras IAs do projeto) |
| **JTBD** | "Camada que abstrai OpenAI/Claude/Gemini/local-LLM via interface única" |

## Cards comparados (libs/frameworks)

### 🟢 LaravelAI (oimpresso)
- ⭐ **Score:** 0/100 (não implementado)
- 💰 Bundled (interno)
- 🎯 **Best for:** Módulos oimpresso (Copiloto, Chat IA contextual, etc.)
- ✨ **Diferencial planejado:** Tipos PHP fortes + cache + LGPD masking + retry idempotent

### 🔴 Vizra ADK + Prisma
- ⭐ **GitHub:** ⭐ ~2k stars
- 💰 Open source MIT
- 🎯 **Best for:** PHP devs que querem framework agentic moderno
- ✨ **Diferencial:** Agentic patterns + tool calling + memory

### 🔴 Spatie laravel-data + spatie/llm-bridge
- ⭐ **GitHub:** ⭐ Spatie ecosystem (~10k seguidores)
- 💰 Open source MIT
- 🎯 **Best for:** PHP devs que já usam ecossistema Spatie
- ✨ **Diferencial:** Maturidade + comunidade BR ativa

### 🟡 Prism PHP (Spatie + comunidade)
- ⭐ **GitHub:** ⭐ ~500 stars
- 💰 Open source MIT
- 🎯 **Best for:** Laravel devs que querem multi-provider PHP
- ✨ **Diferencial:** Multi-provider (OpenAI, Anthropic, Gemini) com 1 API
- ✨ Maturando rápido em 2026

### 🟡 OpenAI PHP / Anthropic PHP SDK
- ⭐ **Manutenidos pelos vendors**
- 💰 Open source
- 🎯 **Best for:** Projeto que decide ir 1 provedor só
- ✨ **Diferencial:** Suporte oficial primeiro

## Matriz de features

| Feature | 🟢 Nós planejado | Vizra | Spatie | Prism | OpenAI direct | Importância |
|---|---|---|---|---|---|---|
| Multi-provider (OpenAI+Claude+Gemini) | ✅ | ✅ | ✅ | ✅ | ❌ | **P0** |
| LGPD masking PII pre-call | ✅ planejado | ❌ | ❌ | ❌ | ❌ | **diferencial** |
| Cache responses idempotente | ✅ planejado | ⚠ | ✅ | ⚠ | ❌ | P1 |
| Token counting + cost tracking | ✅ planejado | ⚠ | ⚠ | ⚠ | ⚠ | P1 |
| Streaming responses | ⚠ | ✅ | ✅ | ✅ | ✅ | P1 |
| Tool calling | ⚠ | ✅ killer | ✅ | ✅ | ✅ | **P0** |
| Embeddings | ⚠ | ⚠ | ✅ | ✅ | ✅ | P1 |
| Local LLM support (Ollama) | ⚠ | ❌ | ❌ | ✅ killer | ❌ | P2 |
| Tipos PHP fortes (DTO) | ✅ planejado | ⚠ | ✅ | ✅ | ⚠ | P1 |
| Job queue native (background) | ✅ planejado | ⚠ | ✅ | ⚠ | ❌ | P0 |

## Score (técnico)

| Critério | 🟢 Nós | Vizra | Spatie+bridge | Prism | OpenAI direct |
|---|---|---|---|---|---|
| Maturity | 0 | 6 | **9** | 7 | **9** |
| BR community | 0 | 5 | **9** | 6 | 6 |
| Multi-provider | 0 | **9** | **9** | **9** | 0 |
| LGPD/privacy | 0 | 4 | 5 | 5 | 4 |
| Tool calling | 0 | **9** | 8 | 8 | 8 |
| Local LLM | 0 | 0 | 0 | **8** | 0 |
| Cost (FOSS) | **10** | **10** | **10** | **10** | **10** |
| **Total /70** | **10** | **43** | **50** | **53** | **37** |
| **Score /100** | **14** | **61** | **71** | **76** | **53** |

## Estratégia

### Decisão pendente
**Não construir do zero.** Adotar lib FOSS + adicionar camada fina específica do oimpresso (LGPD masking + business_id scope + UPos hooks).

### Track recomendado
1. **Avaliar Prism PHP** (multi-provider + local LLM + maturando)
2. **Fallback Spatie ecosystem** se Prism for muito jovem
3. **Adicionar wrapper oimpresso** com:
   - Mascaramento CPF/CNPJ pre-call
   - Cache idempotente por business_id
   - Job queue background
   - Cost tracking por tenant

### Decisão a tomar (próxima sessão)
- Prism vs Spatie ecosystem vs Vizra
- POC com 1 caso de uso real (Copiloto sugerir metas)

## Refs

- [Prism PHP](https://github.com/prism-php/prism)
- [Vizra ADK](https://github.com/vizra-ai/adk)
- [Spatie laravel-data](https://github.com/spatie/laravel-data)
- [openai-php/laravel](https://github.com/openai-php/laravel)
- ADR _Ideias/LaravelAI + memória `project_modulos_promovidos`
