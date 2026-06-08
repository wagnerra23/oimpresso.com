# Gap analysis — schema oimpresso multi-cliente / multi-vertical — 2026-05-09

> **Pergunta do Wagner:** "Minha memória já está preparada pra ramos de atividade, análise de cada cliente? Vai encaixar no meu modelo ou tenho que melhorar?"
>
> **Resposta curta:** **encaixa, mas falta uma camada fina de classificação vertical** (`vertical_id` em `business` + tabela `verticals` + `business_attributes` JSON). Multi-tenant Tier 0, time-series, memória Jana e governança MCP **já estão prontos** — cobertura estimada **~70%**. Os 30% que faltam são incrementais (4 migrations small/medium), **sem refactor estrutural**.

---

## O que já existe

| Capacidade | Status | Onde vive |
|---|---|---|
| **Multi-tenant `business_id` Tier 0** | ✅ Sim | `business` + global scope obrigatório (ADR 0093) — toda Eloquent Model carrega; auditado em `jana:health-check` (`multi_tenant_isolation` SQL diário) |
| **`business.type` ou similar (vertical)** | ❌ **NÃO existe** | `business` table tem 60+ colunas (currency, tax, accounting_method, locale, modules ativos, settings JSON) — **nenhuma é "ramo/vertical"**. Apenas `is_officeimpresso` boolean (legacy WR Sistemas) e `versao_obrigatoria` (deploy versioning) |
| **CNAE / segmentação setorial** | 🟡 Parcial — fora do business | `nfse_provider_configs.cnae` e `nfse_emissoes.cnae` (Modules/NFSe) carregam CNAE **por nota fiscal**, não por business. Não há `business.cnae`, nem tabela `cnae_codigos`. Memória legacy WR Comercial tem schema `NF_CNAE.md` mas é só doc |
| **Jana memória persistente** | ✅ Sim | `copiloto_memoria_facts` (business_id + user_id + fato + JSON metadata + valid_from/until + hits_count + core_memory) — promotion automática + soft-delete LGPD. `copiloto_business_profile` (1 row/biz, narrativa destilada ~200 tokens, refresh diário) |
| **Memória escopada por business** | ✅ Sim | Todos os índices de `copiloto_memoria_facts` começam em `business_id`. Cross-tenant violation é métrica monitorada (`copiloto_memoria_metricas.cross_tenant_violations` meta = 0) |
| **MCP governance** | ✅ Sim | 14+ tabelas `mcp_*`: `mcp_memory_documents` (com `business_id` adicionado em 2026-04-30, NULL = global), `mcp_audit_log` (immutable triggers), `mcp_tokens`, `mcp_quotas`, `mcp_scopes`, `mcp_skills`, `mcp_cycles`, `mcp_tasks` etc |
| **Time-series receita** | ✅ Sim — granularidade fina | `transactions` (UltimatePOS core) tem `business_id + type=sell + transaction_date + final_total` indexados. Permite agregação diária/mensal/anual por business sem refactor. `copiloto_metas` + `copiloto_meta_apuracoes` já fazem time-series de metas vs realizado |
| **Tag/category system genérico** | 🟡 Parcial | `categories` (polimórfica desde 2019) e `customer_groups` existem, mas escopo é produto/cliente — **não classificam o business em si** |
| **Localização** | ✅ Sim | `business.time_zone`, `cities` table (com bridge officeimpresso 2024). Estado/cidade vem de `business_locations` |
| **Métricas RAGAS-aligned por business** | ✅ Sim | `copiloto_memoria_metricas` carrega 8 métricas obrigatórias + faithfulness/answer_relevancy/context_precision por business_id por dia |
| **Anonimização nativa (k-anonymity ≥5)** | ❌ Não existe | `mcp_memory_documents.pii_redactions_count` redige PII em strings (regex CPF/CNPJ), mas **não há k-anonymity check** ao agregar receita cross-cliente. PiiRedactor protege texto, não estatísticas |

---

## Gaps identificados (priorizar)

### Crítico (bloqueia DaaS — sem isso, "análise por ramo" é impossível)

1. **`business.vertical_id` + tabela `verticals`** — a única coisa que **não dá pra contornar**. Hoje todos 56 businesses são iguais aos olhos do schema. Sem isso, o Jana não consegue comparar "gráficas rápidas" vs "oficinas auto" vs "fachadas". Tabela `verticals` deve ter hierarquia (setor → sub-setor → nicho) — exemplo: comunicação_visual → grafica_rapida → impressao_digital_grandes_formatos.

2. **`business.cnae_principal` (string 10) + tabela `cnae_codigos` (catálogo IBGE)** — fonte oficial de classificação. CNAE é proxy regulatório (ANVISA, vigilância, MEI/Simples) e benchmark contra IBGE/RAIS. CNAE em `nfse_emissoes` é **por nota** (varia por serviço prestado), não substitui CNAE principal do estabelecimento.

3. **k-anonymity guard em queries cross-cliente** — DaaS exige que qualquer agregado por vertical retorne ≥5 businesses ou seja **bloqueado** (LGPD Art. 7º + boas práticas). Hoje qualquer query agregada vaza receita individual se vertical tem só 2 businesses.

### Importante (degrada UX — DaaS lança sem isso, mas vira tech debt)

1. **`business_attributes` (JSON metadata flexível por vertical)** — gráfica precisa armazenar `m2_mensal`, `equipamentos_ativos`; oficina auto precisa `marcas_atendidas`, `box_count`; brindes precisa `volume_min_pedido`. Schema fixo não cabe — solução pragmática: `business_attributes` JSON column em `business`, indexado via generated columns por vertical quando necessário.

2. **`vertical_kpi_definitions`** — cada ramo tem KPIs próprios (gráfica: m²/mês, refazimento %; auto: ticket médio OS, tempo box; brindes: pedido mínimo, prazo entrega). Sem catálogo formal, dashboards setoriais viram hardcode.

3. **`benchmark_snapshots` (cache de agregados anonimizados por vertical/mês)** — calcular percentil cross-cliente em runtime mata banco. Mat-view ou snapshot diário em job.

### Nice-to-have (otimização)

1. **`vertical_id` propagado em `copiloto_memoria_facts`** (denormalizado) — pra recall filtrar "todas memórias de gráficas" sem JOIN. Hoje JOIN business → fact funciona, mas é mais lento em recall hot path.

2. **`business.porte` enum (MEI/ME/EPP/Médio/Grande)** — derivável de receita anual (já temos via transactions), mas cachear ajuda dashboards.

3. **`business.regiao_ibge`** — derivável de city/UF, mas pré-computado acelera filtros geográficos.

---

## Esforço pra fechar gaps críticos

| Gap | Migration size | Esforço h (IA-pair) | Risco |
|-----|----------------|---------------------:|-------|
| `verticals` table + seed (~30 ramos do oimpresso/auto/comercio) | small | 3h | baixo |
| `business.vertical_id` (FK nullable) + UI dropdown wizard | small | 4h | baixo (nullable = backwards compat) |
| `cnae_codigos` table + seed CSV IBGE (1300 entradas) + `business.cnae_principal` | medium | 6h | baixo |
| k-anonymity guard (Service `BenchmarkAggregator` com check `count(distinct business_id) >= 5` + Pest) | small | 5h | médio (regra LGPD — auditoria precisa) |
| `business_attributes` JSON + helpers Eloquent | small | 3h | baixo |
| `vertical_kpi_definitions` + seed 3 verticais piloto | small | 4h | baixo |
| `benchmark_snapshots` + job diário | medium | 8h | médio (job roda em CT 100, não Hostinger — ADR 0062) |
| **Crítico subtotal** | — | **~18h** | — |
| **Importante subtotal** | — | **~15h** | — |
| **Total (críticos + importantes)** | — | **~33h IA-pair = ~4-5 dias Felipe** | — |

> Recalibração 2026 (ADR 0106 fator 10x): **18h IA-pair ≈ 2-3 dias do Felipe** pros gaps críticos. Margem 2x = 4-6 dias. Tarefas humano-limitadas (revisão LGPD do k-anonymity guard pelo Wagner) ficam fora dessa conta.

---

## Migração sem disruption

### Backwards compatibility
- `business.vertical_id` **nullable** — 56 businesses existentes continuam funcionando NULL. UI lista "Não classificado" e prompt suave no admin pra preencher.
- `business.cnae_principal` **nullable** — não bloqueia emissão NFC-e (Modules/NfeBrasil já tem CNAE por nota).
- `business_attributes` JSON default `{}` — features que dependem testam `array_key_exists`.
- `verticals` seed inclui "outros" como fallback.

### Deploy plan (5 passos)
1. **PR 1 (small):** `verticals` + seed 30 ramos + `business.vertical_id` nullable. Deploy Hostinger via `migrate --force`. UI continua exibindo lista plana de businesses.
2. **PR 2 (small):** UI wizard `/business/{id}/classify` + "Não classificado" badge no admin. Larissa+Wagner+Felipe classificam manualmente os 7 businesses ativos (≤5min cada).
3. **PR 3 (medium):** `cnae_codigos` + seed IBGE + `business.cnae_principal`. UI aproveita classificação CNAE→Vertical sugerida (LLM helper opcional).
4. **PR 4 (small):** `business_attributes` + `vertical_kpi_definitions` (3 verticais piloto: gráfica, auto, brindes).
5. **PR 5 (medium):** `benchmark_snapshots` + job diário em CT 100 + k-anonymity guard.

### Rollback plan
- Cada migration tem `down()` reversível (drop column + drop table). Sem perda de dado existente — todas as colunas novas são nullable/default.
- Em caso de bug grave: `php artisan migrate:rollback --step=N`. Dados em `business_attributes` JSON são opcionais; perdê-los ≠ corromper UltimatePOS core.

### Testing plan (Pest, biz=1 nunca cliente — ADR 0101)
- `VerticalSeederTest` — 30 ramos seeded com `code` UNIQUE.
- `BusinessVerticalAssignmentTest` — assign + reassign + null fallback.
- `KAnonymityGuardTest` — `count <5` retorna `null` ou throw `KAnonymityViolationException`. **Crítico: 5+ cenários** (vertical com 1, 4, 5, 10 businesses + cross-vertical).
- `BenchmarkSnapshotJobTest` — job idempotente, reruns mesmo dia atualizam (não duplicam).
- `MultiTenantBenchmarkScopeTest` — query agregada **nunca** retorna business_id individual ao tenant não-superadmin.

---

## Esforço total

- **Crítico (bloqueia DaaS):** ~18h IA-pair = **~2-3 dias Felipe** (margem 2x = 4-6 dias)
- **Importante:** ~15h = **~2 dias Felipe**
- **Crítico + Importante:** ~33h = **~4-5 dias Felipe** (1 sprint piloto)
- **Nice-to-have:** backlog (~10h adicionais — denormalizações + porte + região)

---

## Recomendação

**Schema dá conta. Não precisa de refactor estrutural.** O que falta é **uma camada fina de classificação vertical** sobre o que já existe.

1. **Antes do DaaS launch (não negociável):**
   - `verticals` + `business.vertical_id`
   - `cnae_codigos` + `business.cnae_principal`
   - **k-anonymity guard** — sem isso, DaaS vaza receita do cliente individual = quebra LGPD + confiança Larissa = **kill switch**.

2. **Em paralelo com DaaS rollout (primeiras 4 semanas):**
   - `business_attributes` JSON
   - `vertical_kpi_definitions`
   - `benchmark_snapshots` job

3. **Backlog (após sinal qualificado de cliente — ADR 0105):**
   - Denormalização `vertical_id` em `copiloto_memoria_facts`
   - `porte`, `regiao_ibge` cache columns

### Por que dá certo

- **Multi-tenant Tier 0** já é princípio constitucional (Princípio 6, ADR 0094) — `business_id` global scope cobre isolamento por cliente sem trabalho novo.
- **Jana memória + métricas** já segmentam por `business_id` — basta plugar `vertical_id` no recall hybrid quando quiser comparar "memória da gráfica X vs gráficas em geral".
- **Time-series fiscalmente sólida** — `transactions.transaction_date + final_total + business_id` indexados desde 2017. Não precisa nova fact table; agregação por mês × vertical funciona com SQL window.
- **MCP governance** já tem o padrão pra adicionar tabela nova governada (`mcp_memory_documents` recebeu `business_id` em migration de 4 linhas em 2026-04-30 — referência viva pra como adicionar `vertical_id` em `copiloto_memoria_facts` se quiser).

### Onde tem que ter cuidado

- **k-anonymity ≥5** não é detalhe — Wagner precisa decidir o número (5 é mínimo prudente, 10 é mais conservador) **antes** de lançar DaaS. Recomenda formalizar em ADR canon (`Princípio: agregados cross-cliente exigem k≥N`).
- **Wagner exige Pest local antes de PR em mudança de tenancy** (auto-mem `feedback_tenancy_changes_require_pest_local.md` 2026-05-09). k-anonymity guard mexe em scope — **sem Pest verde rodado pelo Felipe localmente, não merge.**
- **CNAE seed IBGE** tem ~1300 entradas — verificar licença do CSV IBGE (CC-BY 3.0 IBGE — ok pra uso interno + DaaS comercial com atribuição).

---

**Conclusão executiva:** schema atual é uma fundação sólida. Adicionar classificação vertical é **incremental, não disruptivo**, e cabe em 1 sprint piloto pra críticos + 1 sprint pra importantes. **DaaS pode lançar em ≤2 sprints com schema completo.**
