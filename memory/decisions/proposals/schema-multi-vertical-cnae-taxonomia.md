# Schema Multi-Vertical CNAE / Taxonomia oimpresso

> **Status:** PROPOSAL (rascunho técnico — não modifica nada, não commitar)
> **Autor:** Claude (data-modeler senior agent)
> **Data:** 2026-05-09
> **Contexto:** SaaS modular especializado por vertical ([ADR 0121](../0121-oimpresso-modular-especializado-por-vertical.md)) — núcleo + Modules/<Vertical>. Cliente piloto atual: ROTA LIVRE biz=4 = Modules/Vestuario (loja de roupa Gravatal/SC). Outros módulos: ComunicacaoVisual em construção, OficinaAuto aguardando sinal qualificado.
> **Restrição Tier 0:** `business_id` global scope IRREVOGÁVEL ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md))

---

## 1. Sumário executivo

oimpresso hoje serve **41 businesses** (7 com vendas reais, 99% volume = ROTA LIVRE biz=4) — todos comunicação visual.
Pra virar plataforma multi-vertical, precisa:

1. **Classificar cada `business`** com CNAE oficial (Receita Federal) + `vertical_id` interno (taxonomia oimpresso)
2. **Atributos custom por vertical** (m² produzidos pra gráfica; # boxes pra oficina; etc) — schema flexible JSON
3. **Benchmark cross-cliente** com k-anonymity ≥5 — sem expor PII, com percentis P25/P50/P75 mensais
4. **Backfill defensivo** dos 41 atuais → default `comunicacao_visual` + opt-in pra preencher CNPJ → CNAE via API Receita

Output: 1 migration core + 4 migrations satélite + seeder com **52 verticais BR** (Tier 1 + Tier 2).

---

## 2. Hierarquia CNAE (referência IBGE/Receita Federal)

> Fonte canônica: **CNAE 2.3** (revisão 2022, vigente até pelo menos 2027).
> CONCLA/IBGE: https://cnae.ibge.gov.br
> Receita Federal API pública (CNPJ → CNAE): https://www.receitaws.com.br/v1/cnpj/{cnpj} (rate limit 3 req/min) ou **BrasilAPI** https://brasilapi.com.br/api/cnpj/v1/{cnpj} (sem rate limit, recomendado).

5 níveis hierárquicos:

| Nível | Exemplo | Total BR |
|---|---|---|
| Seção (1 letra) | `C` Indústrias de transformação | 21 |
| Divisão (2 dig) | `18` Impressão e reprodução | 87 |
| Grupo (3 dig) | `181` Atividade de impressão | 285 |
| Classe (4 dig) | `1813-0` Serviço de pré-impressão | 673 |
| Subclasse (7 dig) | `1813-0/01` Impressão de material publicitário | ~1.330 |

**Regra:** CNPJ tem 1 CNAE primário + N secundários. oimpresso vai usar **só o primário** pra `vertical_id` default; secundários ficam em `business.cnae_secondary` (JSON).

---

## 3. Taxonomia oimpresso (3 níveis)

```
SETOR (5)         → SUB-SETOR (~15)         → NICHO (~52, mapeia 1+ CNAE)
─────────────────────────────────────────────────────────────────────────
Industria         → Grafica                 → comunicacao_visual ← biz=4 ROTA LIVRE
                                            → grafica_offset
                                            → grafica_digital
                                            → grafica_serigrafia
                                            → embalagens
                  → Metalurgia              → fundicao
                                            → usinagem
                  → Textil                  → confeccao
                                            → malharia
                  → Alimenticio             → padaria_industrial
                                            → bebidas
Servicos          → Automotivo              → oficina_auto ← target US-REPAIR
                                            → autoeletrica
                                            → funilaria_pintura
                                            → lava_jato
                  → Manutencao              → eletronica
                                            → eletrodomesticos
                                            → relojoaria
                  → Profissional            → contabilidade
                                            → advocacia
                                            → arquitetura
Comercio          → Varejo                  → comercio_geral
                                            → vestuario
                                            → calcados
                                            → eletronicos_varejo
                  → Atacado                 → atacado_alimentos
                                            → atacado_industrial
                  → Especializado           → autopecas
                                            → materiais_construcao
                                            → farmacia
Construcao        → Obra                    → construcao_civil
                                            → reformas
                  → Acabamento              → marcenaria
                                            → vidracaria
Saude_Beleza      → Estetica                → salao_beleza
                                            → barbearia
                                            → estetica_corporal
                  → Saude                   → clinica_geral
                                            → odontologia
```

**Total:** 5 setores + 15 sub-setores + ~52 nichos cobrindo CNAEs mais relevantes pro target oimpresso.

---

## 4. Schema SQL (Laravel migrations)

### 4.1 Migration core: `verticals` + `business.vertical_id`

```php
// database/migrations/2026_05_15_000001_create_verticals_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('verticals', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();              // 'comunicacao_visual'
            $table->string('name', 120);                        // 'Comunicação Visual'
            $table->foreignId('parent_id')                      // hierarquia setor→subsetor→nicho
                ->nullable()
                ->constrained('verticals')
                ->nullOnDelete();
            $table->enum('level', ['setor', 'subsetor', 'nicho']);
            $table->json('cnae_codes')->nullable();             // ["1813-0/01", "1813-0/99"]
            $table->json('attributes_schema')->nullable();      // JSONSchema dos atributos custom
            $table->json('benchmark_metrics')->nullable();      // ["receita_por_m2", "ticket_medio"]
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('cnae_version')->default(23); // CNAE 2.3
            $table->timestamps();

            $table->index(['level', 'active']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verticals');
    }
};
```

```php
// database/migrations/2026_05_15_000002_add_vertical_to_business_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->foreignId('vertical_id')
                ->nullable()
                ->after('id')
                ->constrained('verticals')
                ->nullOnDelete();
            $table->string('cnae_primary', 10)->nullable()->after('vertical_id');   // '1813-0/01'
            $table->json('cnae_secondary')->nullable()->after('cnae_primary');      // ["4520-0/01"]
            $table->json('custom_attributes')->nullable()->after('cnae_secondary'); // ad-hoc fast path
            $table->timestamp('cnae_synced_at')->nullable();                        // última sync Receita
            $table->index('vertical_id');
            $table->index('cnae_primary');
        });
    }

    public function down(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $table->dropForeign(['vertical_id']);
            $table->dropColumn(['vertical_id', 'cnae_primary', 'cnae_secondary', 'custom_attributes', 'cnae_synced_at']);
        });
    }
};
```

### 4.2 Migration: `business_attributes` (chave-valor governado)

```php
// database/migrations/2026_05_15_000003_create_business_attributes_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')
                ->constrained('business')
                ->cascadeOnDelete();
            $table->foreignId('vertical_id')
                ->constrained('verticals')
                ->cascadeOnDelete();
            $table->string('attr_key', 80);                     // 'm2_produzidos_mes'
            $table->json('attr_value');                         // {"value": 1200, "unit": "m2", "currency": null}
            $table->date('reference_period')->nullable();       // mês/ano da medição
            $table->enum('source', ['self_declared', 'system_auto', 'admin_filled'])->default('self_declared');
            $table->timestamps();

            $table->unique(['business_id', 'vertical_id', 'attr_key', 'reference_period'],
                'biz_attr_unique');
            $table->index(['vertical_id', 'attr_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_attributes');
    }
};
```

> **Nota:** mantemos **dual-storage** — `business.custom_attributes` JSON (fast path, leitura rápida sem join) + `business_attributes` (auditoria, histórico, benchmark). Trigger MySQL OU Observer Eloquent sincroniza.

### 4.3 Migration: `benchmark_aggregates` (snapshot mensal anonimizado)

```php
// database/migrations/2026_05_15_000004_create_benchmark_aggregates_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('benchmark_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vertical_id')
                ->constrained('verticals')
                ->cascadeOnDelete();
            $table->string('metric_key', 80);                   // 'receita_por_m2'
            $table->date('period_month');                       // 1º dia do mês
            $table->string('region_uf', 2)->nullable();         // 'SP' (NULL = nacional)
            $table->string('size_bucket', 20)->nullable();      // 'pequeno' (faturamento <100k/mês)
            $table->unsignedInteger('sample_size');             // n businesses (≥5 obrigatório)
            $table->decimal('p25', 18, 4)->nullable();
            $table->decimal('p50', 18, 4)->nullable();
            $table->decimal('p75', 18, 4)->nullable();
            $table->decimal('avg', 18, 4)->nullable();
            $table->decimal('stddev', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['vertical_id', 'metric_key', 'period_month', 'region_uf', 'size_bucket'],
                'bench_unique');
            $table->index(['vertical_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_aggregates');
    }
};
```

> **Constraint k-anonymity:** Job `BenchmarkAggregator` SÓ insere row se `sample_size >= 5`. Caso contrário, descarta silenciosamente (LGPD safety).

### 4.4 Migration: `cnae_codes` (dimensão Receita Federal)

```php
// database/migrations/2026_05_15_000005_create_cnae_codes_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cnae_codes', function (Blueprint $table) {
            $table->string('code', 10)->primary();              // '1813-0/01'
            $table->string('description', 255);
            $table->string('section', 1);                       // 'C'
            $table->string('division', 2);                      // '18'
            $table->string('group', 3);                         // '181'
            $table->string('class_code', 6);                    // '1813-0'
            $table->foreignId('vertical_id')
                ->nullable()
                ->constrained('verticals')
                ->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('cnae_version')->default(23);
            $table->timestamps();

            $table->index('section');
            $table->index('vertical_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnae_codes');
    }
};
```

---

## 5. Mapeamento CNAE → Vertical (top 50 BR)

> Fonte: CNAE 2.3 + análise mercado SaaS BR — focado nos verticais com **>10k empresas ativas** e ticket compatível com oimpresso (R$ 200-2k/mês).

### Indústria (Seção C)

| CNAE | Descrição | vertical_oimpresso | Volume BR |
|---|---|---|---|
| 1813-0/01 | Impressão material publicitário | comunicacao_visual | ~25k |
| 1813-0/99 | Outros serviços impressão | comunicacao_visual | ~8k |
| 1811-3/01 | Impressão de jornais | grafica_offset | ~2k |
| 1812-1/00 | Impressão de material para uso publicitário | grafica_offset | ~12k |
| 1822-9/01 | Serviços de acabamento gráfico | grafica_offset | ~6k |
| 1813-0/02 | Impressão de material de segurança | grafica_serigrafia | ~1k |
| 1731-7/00 | Fab. de embalagens de papel | embalagens | ~3k |
| 2229-3/02 | Fab. embalagens plástico | embalagens | ~5k |
| 2511-0/00 | Fab. estruturas metálicas | usinagem | ~7k |
| 2542-0/00 | Fab. artigos cutelaria | usinagem | ~2k |
| 2451-2/00 | Fundição ferro/aço | fundicao | ~1k |
| 1411-8/01 | Confecção peças vestuário | confeccao | ~30k |
| 1422-3/00 | Fab. artigos confeccionados | confeccao | ~10k |
| 1321-9/00 | Tecelagem fios algodão | malharia | ~1k |
| 1091-1/02 | Fab. produtos panificação industrial | padaria_industrial | ~8k |
| 1122-4/01 | Fab. refrigerantes | bebidas | ~1k |

### Serviços (Seções M/N/S)

| CNAE | Descrição | vertical_oimpresso | Volume BR |
|---|---|---|---|
| 4520-0/01 | Manutenção/reparação automóveis | oficina_auto | ~120k |
| 4520-0/02 | Serviços lanternagem/funilaria | funilaria_pintura | ~25k |
| 4520-0/05 | Serviços lavagem/lubrificação | lava_jato | ~30k |
| 4520-0/06 | Serviços borracharia | oficina_auto | ~40k |
| 4520-0/07 | Serviços alinhamento/balanceamento | oficina_auto | ~20k |
| 4520-0/03 | Serviços manutenção elétrica | autoeletrica | ~15k |
| 9521-5/00 | Reparação eletrodomésticos | eletrodomesticos | ~12k |
| 9512-6/00 | Reparação equip. comunicação | eletronica | ~8k |
| 9529-1/02 | Chaveiros | manutencao | ~5k |
| 9529-1/04 | Reparação relógios | relojoaria | ~2k |
| 6920-6/01 | Atividades contabilidade | contabilidade | ~80k |
| 6911-7/01 | Serviços advocatícios | advocacia | ~150k |
| 7111-1/00 | Serviços arquitetura | arquitetura | ~30k |
| 9602-5/01 | Cabeleireiros/manicure | salao_beleza | ~200k |
| 9602-5/02 | Atividades estética/cuidados | estetica_corporal | ~50k |

### Comércio (Seção G)

| CNAE | Descrição | vertical_oimpresso | Volume BR |
|---|---|---|---|
| 4781-4/00 | Comércio varejo vestuário | vestuario | ~180k |
| 4782-2/01 | Comércio varejo calçados | calcados | ~50k |
| 4754-7/01 | Comércio varejo móveis | comercio_geral | ~30k |
| 4744-0/01 | Comércio varejo ferragens | materiais_construcao | ~25k |
| 4744-0/05 | Comércio varejo materiais construção | materiais_construcao | ~80k |
| 4530-7/03 | Comércio varejo peças/acessórios auto | autopecas | ~60k |
| 4771-7/01 | Comércio varejo farmácia/drogaria | farmacia | ~80k |
| 4753-9/00 | Comércio varejo eletrodomésticos | eletronicos_varejo | ~25k |
| 4729-6/02 | Comércio varejo mercearias/empórios | comercio_geral | ~150k |
| 4639-7/01 | Comércio atacadista alimentos | atacado_alimentos | ~20k |
| 4646-0/02 | Comércio atacadista cosméticos | atacado_industrial | ~10k |

### Construção (Seção F)

| CNAE | Descrição | vertical_oimpresso | Volume BR |
|---|---|---|---|
| 4399-1/03 | Obras alvenaria | construcao_civil | ~50k |
| 4399-1/99 | Serviços especializados construção | reformas | ~30k |
| 4330-4/02 | Instalação portas/janelas | reformas | ~15k |
| 4330-4/04 | Serviços pintura | reformas | ~20k |
| 1622-6/02 | Fab. esquadrias madeira | marcenaria | ~10k |
| 2319-2/00 | Fab. artefatos vidro | vidracaria | ~3k |

### Saúde (Seção Q)

| CNAE | Descrição | vertical_oimpresso | Volume BR |
|---|---|---|---|
| 8630-5/03 | Atividade médica clínica | clinica_geral | ~40k |
| 8630-5/04 | Atividade odontológica | odontologia | ~80k |

**Total mapeado:** 50 CNAEs cobrindo ~1.6M empresas BR (TAM endereçável).

---

## 6. JSONSchema dos atributos custom (exemplo gráfica)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "AtributosGrafica",
  "type": "object",
  "properties": {
    "maquinas_instaladas": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "tipo": { "enum": ["offset", "digital", "serigrafia", "plotter", "laser"] },
          "marca": { "type": "string" },
          "modelo": { "type": "string" },
          "ano": { "type": "integer", "minimum": 1980, "maximum": 2030 },
          "ativa": { "type": "boolean" }
        },
        "required": ["tipo", "ativa"]
      }
    },
    "m2_produzidos_mes": {
      "type": "number",
      "minimum": 0,
      "description": "Metros quadrados de impressão produzidos no mês de referência"
    },
    "tipos_impressao_oferecidos": {
      "type": "array",
      "items": { "enum": ["lona", "adesivo", "banner", "cartaz", "panfleto", "rotulo", "embalagem"] }
    },
    "ticket_medio_mes": {
      "type": "number",
      "minimum": 0
    },
    "colaboradores_total": {
      "type": "integer",
      "minimum": 1
    }
  },
  "required": ["m2_produzidos_mes", "tipos_impressao_oferecidos"]
}
```

Atributos similares por vertical (oficina_auto, comercio_geral, etc) ficam no seeder `VerticalsSeeder` em `attributes_schema` JSON.

---

## 7. Backfill plan

### Fase 1: seeder vertical + CNAE codes (T+0, ~2h dev)

```bash
php artisan db:seed --class=VerticalsSeeder      # ~52 verticais (5 setores → 15 sub → 52 nichos)
php artisan db:seed --class=CnaeCodesSeeder      # ~1.330 CNAEs CNAE 2.3 + mapping pra vertical
```

### Fase 2: backfill 41 businesses atuais (T+1, ~4h dev + 1h human review)

```php
// Modules/Officeimpresso/Console/BackfillVerticalCommand.php
$businesses = Business::whereNull('vertical_id')->get();
foreach ($businesses as $biz) {
    if ($biz->tax_number_1) {  // CNPJ
        $cnae = $this->brasilApi->fetchCnae($biz->tax_number_1);  // BrasilAPI
        if ($cnae) {
            $vertical = CnaeCode::where('code', $cnae)->first()?->vertical;
            $biz->update([
                'cnae_primary' => $cnae,
                'vertical_id' => $vertical?->id ?? Vertical::slug('comunicacao_visual')->id,
                'cnae_synced_at' => now(),
            ]);
            continue;
        }
    }
    // Fallback: comunicacao_visual (default histórico oimpresso)
    $biz->update(['vertical_id' => Vertical::slug('comunicacao_visual')->id]);
}
```

**Esforço total backfill:** ~7-9h (2h schema + 4h seeders + 2h script + 1h validação).

### Fase 3: opt-in atributos custom (T+30d, async)

UI em `/copiloto/admin/business/{id}/vertical` permite owner preencher `business_attributes`. Gamificação: "Complete 80% dos atributos pra desbloquear benchmark cross-cliente".

### Fase 4: benchmark job mensal (T+60d)

```php
// Modules/Copiloto/Jobs/BenchmarkAggregatorJob.php
// Agendado cron 1º dia mês 03:00 BRT
// Para cada (vertical_id, metric_key, region_uf, size_bucket):
//   - SELECT percentil P25/P50/P75 de business_attributes JOIN transactions
//   - WHERE COUNT(DISTINCT business_id) >= 5  (k-anonymity)
//   - INSERT benchmark_aggregates
```

---

## 8. Casos de uso end-to-end

### CU-1: Cliente novo (gráfica) preenche perfil

1. Wagner adiciona business novo via `/business/create`
2. Sistema chama BrasilAPI: CNPJ → CNAE `1813-0/01` → `vertical_id = comunicacao_visual`
3. Larissa (owner) entra em `/copiloto/admin/business/{id}/vertical` → wizard mostra `attributes_schema` da gráfica
4. Preenche `m2_produzidos_mes: 1200`, `maquinas_instaladas: [...]`, etc
5. `business_attributes` populado + `business.custom_attributes` JSON sincronizado

### CU-2: Comparativo cross-cliente

1. Larissa abre dashboard Copiloto → "Como minha gráfica está vs mercado?"
2. Query: `benchmark_aggregates WHERE vertical_id = comunicacao_visual AND region_uf = 'SP' AND period_month = '2026-04-01'`
3. Sistema retorna: "Gráficas SP do seu porte cobram **R$ 18/m²** em lona (P50) — você cobra R$ 14/m² (P25 do mercado)"
4. Garantia LGPD: `sample_size >= 5` → nenhum business identificável

### CU-3: Alerta proativo Jana IA

1. Job mensal compara `transactions` do business vs `benchmark_aggregates`
2. Detecta: margem business = 12%, P50 vertical = 22%, drift = -45%
3. Jana cria task no Copiloto: "Sua margem está 45% abaixo do P50 do seu vertical. Quer um diagnóstico?"

### CU-4: Recomendação de feature

1. Telemetria detecta: 5 businesses CNAE `1813-0/01` adotaram NFC-e nos últimos 6m
2. Larissa (mesmo vertical, sem NFC-e) recebe banner: "Outras gráficas SP estão emitindo NFC-e — economiza R$ 2k/mês em ICMS-ST"
3. Link direto pro fluxo NfeBrasil onboarding

---

## 9. LGPD strategy

**Base legal:** Art. 7º X LGPD (legítimo interesse) + Art. 11 §1º (dados anonimizados não são pessoais).

**Garantias técnicas:**

1. **k-anonymity ≥5** hardcoded no `BenchmarkAggregatorJob` — nunca expõe row com `sample_size < 5`
2. **Anonimização:** `benchmark_aggregates` NUNCA referencia `business_id` — só `vertical_id + region_uf + size_bucket + period_month`
3. **PII em `business_attributes`** (ex: nome cliente em campo livre) → bloqueado por validação Laravel + `PiiRedactor` middleware (já existe pro Jana — ADR 0093)
4. **Direito de exclusão (Art. 18 LGPD):** business pode pedir `vertical_id = NULL` + `business_attributes` purge → benchmark recalcula sem ele no próximo job
5. **Consentimento opt-in:** flag `business.benchmark_opt_in` (default `false`) — sem consentimento, business **consume** benchmark mas não **contribui** com dados

---

## 10. Riscos e mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| **Cliente não preenche atributos** ⭐ TOP | Alta | Benchmark inútil | Gamificação + onboarding wizard guided + score "Quão completo está seu perfil?" + Jana proativa |
| Cliente preenche atributos errados | Média | Benchmark distorcido | Validação JSONSchema + sanity checks (ex: m²/mês > 0 e < 1M) + cross-check com `transactions` |
| CNAE muda (Receita atualiza) | Baixa | Schema desatualiza | `cnae_codes.cnae_version` + script anual `php artisan cnae:sync` lê nova lista IBGE |
| Benchmark com n<5 (vertical raro) | Alta nos primeiros 6m | Sem comparativo exibido | Mostrar fallback nacional (sem region_uf) ou mensagem "ainda não há dados suficientes" |
| LGPD em atributos custom | Baixa | Multa ANPD | k-anonymity + consentimento opt-in + auditoria PiiRedactor |
| BrasilAPI cair / rate limit | Média | Backfill trava | Fallback: ReceitaWS (3 req/min) + queue job retry exponencial |
| Mapping CNAE → vertical errado | Média (curadoria humana) | UX ruim | PR review obrigatório no `VerticalsSeeder` + ADR per-vertical-novo |
| Migração quebra biz=4 (ROTA LIVRE) | Baixa | Crítico (99% volume) | Backfill com `vertical_id = comunicacao_visual` default + Pest test multi-tenant + canary 7d |

---

## 11. Próximos passos (checklist humano)

- [ ] **Wagner aprova** taxonomia 3 níveis (5 setores / 15 sub / 52 nichos)
- [ ] **Wagner valida** mapping top 50 CNAEs (especialmente fronteira gráfica vs comércio_papel)
- [ ] **Felipe revisa** schema SQL (FK cascade, índices, JSON columns size limits MySQL)
- [ ] **Eliana valida** estratégia LGPD (k-anonymity + opt-in)
- [ ] Criar ADR formal `memory/decisions/NNNN-multi-vertical-cnae-taxonomia.md` (status: accepted)
- [ ] Abrir tasks MCP: `tasks-create` US-VERTICAL-001 (schema) US-VERTICAL-002 (seeder) US-VERTICAL-003 (backfill) US-VERTICAL-004 (UI atributos) US-VERTICAL-005 (benchmark job)
- [ ] Pest tests: isolamento multi-tenant + k-anonymity + backfill idempotente

---

## 12. Referências

- **CNAE 2.3** (CONCLA/IBGE): https://cnae.ibge.gov.br/?view=download&tipo=download
- **BrasilAPI CNPJ** (recomendada): https://brasilapi.com.br/docs#tag/CNPJ
- **ReceitaWS** (fallback): https://receitaws.com.br/
- **LGPD Art. 11** (anonimização): http://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
- **k-anonymity** (Sweeney 2002): https://epic.org/wp-content/uploads/privacy/reidentification/Sweeney_Article.pdf
- **JSONSchema draft-07**: https://json-schema.org/draft-07/json-schema-release-notes.html
- ADR oimpresso relacionadas: [0093](../0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 · [0094](../0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 · [0070](../0070-jira-style-task-management-current-md-removed.md) Jira-style tasks
