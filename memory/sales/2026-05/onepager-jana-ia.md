# oimpresso · Jana IA — chat com seu próprio negócio

## Problema
Dono de gráfica pergunta: "quanto fechei esse mês?", "qual cliente atrasa mais?", "tem alguma OS travada?". Resposta hoje: abrir relatório, filtrar, exportar Excel, ou perguntar pro funcionário que perdeu 20 min levantando.

## Solução
**Jana** é uma IA com **memória persistente do seu negócio** que responde no chat:
- "qual cliente atrasou mais nos últimos 90 dias?" → lista vem com nome, valor, dias
- "quanto fechei em adesivo esse mês comparado com o anterior?" → número + delta + gráfico inline
- "tem fatura grande vencendo essa semana?" → top 5 com data e cliente

Ela usa **recall híbrido Meilisearch** (busca semântica + texto) sobre os dados reais do seu ERP, não invenção de IA genérica.

## Diferenciais únicos
- **Memória do negócio versionada** (tabela `copiloto_memoria_facts` com `business_id` global scope — Jana de gráfica A nunca vê dados da gráfica B)
- **3 ângulos de faturamento** (vendas confirmadas, faturadas, pagas) — Jana sabe diferenciar quando você pergunta
- **Stack canônica laravel/ai oficial** (Anthropic + OpenAI), não wrapper amador
- **Governança formal:** ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL, PII redactor automático, observabilidade OTel GenAI

## 3 features-killer
1. **Custo controlado** — Brain A (gpt-4o-mini) atende ~85% das perguntas baratas; Brain B (Sonnet/Opus) só quando complexo. Sem queimar conta de IA à toa.
2. **Memória persistente** — Jana lembra que você prefere "faturamento = vendas confirmadas" (não pagas) e responde nesse ângulo das próximas vezes
3. **Hybrid recall** (Meilisearch + HyDE + reranker) — encontra resposta certa mesmo se você perguntar com palavra diferente da que está no banco

## Pricing tier proposto
- **Starter:** sem Jana (foco em ERP)
- **Pro:** Jana inclusa, limite 500 perguntas/mês
- **Enterprise:** Jana ilimitada + memória cross-business + custom skills

`[draft — Wagner valida]`

## CTA
"Manda uma pergunta que você gostaria de fazer pro seu sistema hoje — devolvo print da Jana respondendo (com dados de demo)."

---

**Refs internas:** `Modules/Jana/`, ADR 0035 (stack IA canônica), ADR 0048 (Vizra rejeitada — somos próprio framework), ADR 0093 (multi-tenant Tier 0).
