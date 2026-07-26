# Buckets Governance v4 — catálogo canônico

> **Última atualização:** 2026-05-17 (Wave 27 governance saturate ≥95)
> **Fonte da rubrica:** [ADR 0160 — Governance v4 Scoped Scorecards](../../decisions/0160-governance-v4-scoped-scorecards-bucket-aware.md)
> **Aposentadoria 3/4 hacks v3:** [ADR 0161](../../decisions/0161-governance-v4-aposentar-hacks-0159-redundantes.md)

## Por que buckets

Módulos do oimpresso têm naturezas distintas — `Vestuario` é produto vendável com cliente real (`business_id=4`), `Governance` é meta-módulo que audita os outros, `Jana` é IA central. Aplicar a mesma rubrica D1..D9 com pesos iguais castiga injustamente os meta-módulos (que por design não têm FSM nem cliente externo).

Solução **Scoped Scorecards bucket-aware** (ADR 0160): cada módulo é classificado num bucket, e o evaluator (`Modules\Governance\Services\ScopedScorecardEvaluator`) aplica pesos D1..D9 + dimensões extras específicas do bucket.

## Buckets ativos

> ⚠️ **Fonte da verdade = `governance.bucket` no `Modules/<X>/module.json`** (declarado e datado por `bucket_assigned_by`), **não** esta tabela. Ela é um retrato; para recontar, rode:
>
> ```bash
> for f in Modules/*/module.json; do node -e "const j=require('./$f');if(j.governance?.bucket)console.log(j.governance.bucket)"; done | sort | uniq -c
> ```

Retrato medido em **2026-07-26** (36 módulos, 36 declarando bucket):

| Bucket | Módulos | YAML de rubrica | Target |
|---|--:|---|--:|
| `functional_horizontal` | 21 | ❌ **ausente** | 80 (fallback no comando) |
| `cross_cutting_infra` | 7 | ✅ [cross_cutting_infra.yaml](./cross_cutting_infra.yaml) | 80 |
| `vertical_client_facing` | 5 | ✅ [vertical_client_facing.yaml](./vertical_client_facing.yaml) | 85 |
| `ai_central` | 2 | ❌ **ausente** | 85 (fallback no comando) |
| `process_horizontal` | 1 | ❌ **ausente** | — (sem fallback) |
| `meta_governance` | **0** | ⚠️ [meta_governance.yaml](./meta_governance.yaml) — **órfão** | 90 |

### O que "YAML ausente" custa

`ScopedScorecardEvaluator::loadBucketConfig()` retorna `[]` quando o arquivo não existe. Sem `core`, o loop de dimensões não roda: o módulo é avaliado com **zero dimensões**, não com pesos padrão. O `BUCKET_META_FALLBACK` do `ModuleGradeV4Command` cobre só o *threshold* (a meta), nunca as dimensões.

Na prática o dano hoje é limitado, porque `module:grade-v4` pula módulo sem scorecard próprio — e existem **5** scorecards em [`../scorecards/`](../scorecards/): `admin`, `auditoria`, `comunicacaovisual`, `governance`, `vestuario`. Antes desta reconciliação, **3 dos 5** (admin/auditoria/governance) avaliavam com `core` vazio. Agora os 5 têm rubrica.

### `meta_governance` — por que está órfão

Escrito na W24 (2026-05-16) para "Governance, Auditoria, Admin". Na W27 (2026-05-17) o [W] atribuiu esses mesmos 3 módulos a `cross_cutting_infra` no `module.json`, com justificativa por módulo. O nome mudou nos `module.json` e não no arquivo de rubrica. A reconciliação de 2026-07-26 levou a rubrica pro nome declarado (`cross_cutting_infra.yaml`, pesos core copiados sem alteração) e **manteve** `meta_governance.yaml` no lugar — ele é referenciado por [`Wave27GovernanceSaturateTest.php`](../../../Modules/Governance/Tests/Feature/Wave27GovernanceSaturateTest.php). Deletar é decisão do [W], e exige ajustar o teste no mesmo PR.

### Rubricas faltantes

`functional_horizontal` (21 módulos), `ai_central` (2) e `process_horizontal` (1) seguem sem YAML. Nenhum deles tem scorecard hoje, então nada é silenciosamente mal-avaliado — mas qualquer scorecard novo nesses buckets nasce sem dimensões até a rubrica existir.

## Paired indicators (cap 50% canônico W24)

Toda dimensão extra do bucket pode declarar pares (`velocidade`/`qualidade`). Se velocidade alta (≥75% peso) mas qualidade baixa (<50% peso), o **score da velocidade é capado em 50%** — penaliza gaming "ship fast / break quality".

Exemplos hoje cadastrados:

- `vertical_client_facing.F1_pest_e2e`:
  - velocidade `F1_b` (Pest <30s) × qualidade `F1_c` (≥3 assertions por teste)
  - rule: "Testes rápidos sem asserts robustas viram smoke vazio — D6 performance ilusória"
- `vertical_client_facing.F2_inertia_defer`:
  - velocidade `F2_a` (carga <500ms p95) × qualidade `F2_b` (skeleton + partial reload)
  - rule: "Defer aplicado sem skeleton = UX confusa (blank flicker)"
- `meta_governance.C_reflexividade`:
  - velocidade `C_a` (audit <1s) × qualidade `C_b` (link ADR + artefato)
  - rule: "Audit veloz sem evidências robustas = teatro"

Implementação canônica em `ScopedScorecardEvaluator::checkPairedViolation()`.

## Como adicionar bucket novo

1. Criar YAML novo aqui (espelho de [vertical_client_facing.yaml](./vertical_client_facing.yaml) como template)
2. Declarar `bucket`, `target_score`, `core{D1..D9}`, `bucket_dimensions{X_dim}`, opcional `paired[]`
3. Apontar módulos via `governance.bucket: "<slug>"` em cada `Modules/<X>/module.json`
4. Validar via `php artisan module:grade-v4 <X> --detail` (output mostra bucket detectado + breakdown)
5. Registrar ADR `accepted` se bucket introduz nova semântica (não basta cópia)
6. Atualizar este `_INDEX.md` (regra "mexeu, registra")

## Wave 27 deltas

- Catálogo formalizado neste `_INDEX.md` (antes só YAMLs soltos sem índice)
- `config/retention.governance.php` shim canônico (espelho `retention.ads.php` + `retention.whatsapp.php`)
- Pest `Wave27GovernanceSaturateTest.php` cobre D7 shim + D9 OTel span + D6 Inertia::defer + C3 BRIEFING

## Referências

- [ADR 0160 Scoped Scorecards](../../decisions/0160-governance-v4-scoped-scorecards-bucket-aware.md)
- [ADR 0161 Aposentar hacks v3](../../decisions/0161-governance-v4-aposentar-hacks-0159-redundantes.md)
- [Modules/Governance/Services/ScopedScorecardEvaluator.php](../../../Modules/Governance/Services/ScopedScorecardEvaluator.php)
- [Modules/Governance/Console/Commands/ModuleGradeV4Command.php](../../../Modules/Governance/Console/Commands/ModuleGradeV4Command.php)
