# ADR ARQ-0006 (NfeBrasil) · Cascade tributário em 4 níveis (default → estado → NCM → exceção)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0004, ARQ-0005, US-NFE-010

## Contexto

Pequenas empresas BR têm 5-30 NCMs. Médias têm 100-500. Forçar cadastro de regra explícita por NCM × UF origem × UF destino = matriz combinatória inviável:

- 50 NCMs × 27 UFs origem × 27 UFs destino = **36.450 regras** se forçar granular
- Tenant pequeno desiste antes de configurar; tenant médio gasta semanas

Concorrentes BR (Tiny, Bling, Conta Azul) resolvem com **defaults inteligentes** + override por exceção. Tenant cadastra "regra default Simples Nacional" e só cria override pra NCMs específicos (ex: bebida ICMS-ST 30%).

## Decisão

**Cascade tributário em 4 níveis com fallback explícito (mais específico → menos específico).**

```
Item da venda (produto.ncm + UF cliente)
        ↓
┌─────────────────────────────────────────────────────────────────┐
│ Nível 1 — EXCEÇÃO POR PRODUTO                                   │
│   Se products.metadata.fiscal_rule_override_id != NULL:        │
│     usa fiscal_rule_id apontada                                 │
│     (caso raro: produto com tributação especial)                │
└─────────────────────────────────────────────────────────────────┘
        ↓ NÃO encontrou
┌─────────────────────────────────────────────────────────────────┐
│ Nível 2 — REGRA EXATA                                           │
│   Match: nfe_fiscal_rules WHERE                                 │
│     business_id = X AND ncm = Y AND                             │
│     uf_origem = Z AND uf_destino = W                            │
│     (cobertura total: cliente VIP em UF cara)                   │
└─────────────────────────────────────────────────────────────────┘
        ↓ NÃO encontrou
┌─────────────────────────────────────────────────────────────────┐
│ Nível 3 — REGRA NCM (UF destino "todos")                        │
│   Match: nfe_fiscal_rules WHERE                                 │
│     business_id = X AND ncm = Y AND                             │
│     uf_origem = Z AND uf_destino IS NULL                        │
│     (cobertura típica: regra estadual padrão pro NCM)           │
└─────────────────────────────────────────────────────────────────┘
        ↓ NÃO encontrou
┌─────────────────────────────────────────────────────────────────┐
│ Nível 4 — DEFAULTS BUSINESS                                     │
│   nfe_business_configs.tributacao_default = JSON {              │
│     csosn: '102',                                               │
│     cst: '040',                                                 │
│     aliquota_icms: 0.0,                                         │
│     aliquota_pis: 0.0,                                          │
│     aliquota_cofins: 0.0                                        │
│   }                                                             │
│   (Simples Nacional CSOSN 102 — sem ICMS, isento PIS/COFINS)    │
└─────────────────────────────────────────────────────────────────┘
        ↓ NÃO encontrou (ou config vazia)
┌─────────────────────────────────────────────────────────────────┐
│ ❌ ERROR — exige tenant cadastrar default mínimo                 │
│   Bloqueia emissão; UI mostra "Configure tributação default"   │
└─────────────────────────────────────────────────────────────────┘
```

## Onboarding inteligente

Wizard inicial pré-popula Nível 4 baseado em regime:

| Regime selecionado | Defaults pré-populados |
|---|---|
| **MEI** | CSOSN 102, ICMS 0%, PIS/COFINS isento |
| **Simples Nacional** | CSOSN 102, ICMS conforme alíquota anexo (DAS calc), PIS/COFINS isento |
| **Lucro Presumido** | CST 000, ICMS 18% (UF), PIS 0,65%, COFINS 3% |
| **Lucro Real** | CST 000, ICMS 18% (UF), PIS 1,65%, COFINS 7,6% |

Tenant aceita defaults → pode emitir imediato (Nível 4 cobre tudo). Refina depois com Nível 3 conforme aparece NCM com tributação atípica.

## Consequências

**Positivas:**
- **Onboarding 5 minutos** (vs semanas no modelo granular puro)
- Tenant pequeno (5 NCMs) emite com Nível 4 só
- Tenant médio crescendo: 70-80% das emissões cobertas por Nível 3 (NCM × UF padrão); raras com Nível 2 (cliente VIP de UF cara)
- Cobertura completa **sempre** garantida (nível 4 é seguro de fábrica)
- UI mostra **qual nível foi usado** ao emitir (debug-friendly: "Esta NFC-e usou regra NCM-22021000 default SP")
- Override por produto (Nível 1) = válvula de escape pra casos extremos

**Negativas:**
- Tenant pode **achar que defaults estão certos quando não estão** (ex: vende item com ICMS-ST e default não cobre → SEFAZ aceita mas autuação chega depois)
  - **Mitigação**: alerta no monitor "X% das suas emissões usam Nível 4 default — revise NCMs principais"
- Lookup é mais complexo no motor (4 queries vs 1)
  - **Mitigação**: cache em memória do worker durante venda
- Edge case: produto sem NCM cadastrado → forçar NCM obrigatório no produto

## Schema additions

```sql
ALTER TABLE nfe_business_configs ADD COLUMN tributacao_default JSON NOT NULL DEFAULT '{}';
ALTER TABLE products ADD COLUMN ncm CHAR(8) NULL;
ALTER TABLE products ADD COLUMN cest CHAR(7) NULL;
ALTER TABLE products ADD COLUMN fiscal_rule_override_id BIGINT UNSIGNED NULL;

ALTER TABLE products
  ADD FOREIGN KEY (fiscal_rule_override_id) REFERENCES nfe_fiscal_rules(id) ON DELETE SET NULL;
```

## Pattern obrigatório

```php
class MotorTributarioService {
    public function calcular(Product $produto, Business $business, string $ufDestino): TributoCalculado {
        // Nível 1: override por produto
        if ($produto->fiscal_rule_override_id) {
            return $this->aplicarRegra(FiscalRule::find($produto->fiscal_rule_override_id), $produto);
        }

        if (empty($produto->ncm)) {
            throw new NcmObrigatorioException("Produto {$produto->id} sem NCM cadastrado");
        }

        // Nível 2: regra exata
        $rule = FiscalRule::where([
            'business_id' => $business->id,
            'ncm' => $produto->ncm,
            'uf_origem' => $business->uf,
            'uf_destino' => $ufDestino,
        ])->first();
        if ($rule) return $this->aplicarRegra($rule, $produto, ['nivel' => 2]);

        // Nível 3: regra NCM padrão
        $rule = FiscalRule::where([
            'business_id' => $business->id,
            'ncm' => $produto->ncm,
            'uf_origem' => $business->uf,
        ])->whereNull('uf_destino')->first();
        if ($rule) return $this->aplicarRegra($rule, $produto, ['nivel' => 3]);

        // Nível 4: defaults business
        $defaults = $business->nfeConfig->tributacao_default;
        if (! empty($defaults)) {
            return $this->aplicarDefaults($defaults, $produto, ['nivel' => 4]);
        }

        throw new TributacaoNaoConfiguradaException(
            "Cadastre tributação default em /nfe-brasil/configuracao"
        );
    }
}
```

## Tests obrigatórios

- `Cascade_Nivel1_OverrideProdutoTest` — produto com `fiscal_rule_override_id` ignora cascade
- `Cascade_Nivel2_RegraExataTest` — regra (ncm, ufO, ufD) específica vence sobre Nível 3
- `Cascade_Nivel3_RegraNcmTest` — regra (ncm, ufO, NULL) aplica quando Nível 2 não match
- `Cascade_Nivel4_DefaultsTest` — defaults business aplicam quando NCM sem regra
- `Cascade_NcmAusenteTest` — produto sem NCM → exception explícita
- `Cascade_TributacaoVaziaTest` — business sem default → exception explícita
- `Cascade_NivelLogadoTest` — `TributoCalculado` retorna metadata.nivel_usado pra debug

## Métricas a observar

- % emissões por nível (1/2/3/4) — alerta se > 60% Nível 4 (tenant deveria refinar)
- Top NCMs com Nível 4 — sugestão proativa "Cadastre regra pra NCM X (você emite 50/dia)"
- Tempo médio motor (cascade overhead) — meta < 50ms p95

## Decisões em aberto

- [ ] Auto-criar `fiscal_rule` Nível 3 quando tenant edita override Nível 1 várias vezes pro mesmo NCM?
- [ ] Importação CSV: deve incluir Nível 2 (UF dest específica) ou só Nível 3?
- [ ] Cliente final em UF rara (ex: Acre) com NCM raro → cascade pula pro Nível 4 mas tenant queria Nível 3 default — interface deve sugerir?

## Alternativas consideradas

- **Sem cascade (granular obrigatório)** — rejeitado: barreira de entrada inviável
- **Cascade 2 níveis (regra exata + default)** — rejeitado: força tenant médio cadastrar UF×UF (combinatório)
- **Hierárquico por categoria de produto** (categoria → NCM) — futuro: pode agregar como Nível 2.5 se aparecer demanda
- **Machine learning sugerindo regra** — futuro: depois de 1k+ emissões, ML aprende padrões

## Referências

- ARQ-0004 (schema flexível CBS/IBS)
- ARQ-0005 (bridge tax_rates)
- US-NFE-010 (cadastro regra)
- `_Ideias/NfeBrasil/evidencias/conversa-claude-2026-04-mobile.md`
- Tiny / Bling / Conta Azul — todos usam cascade similar (default + override)
