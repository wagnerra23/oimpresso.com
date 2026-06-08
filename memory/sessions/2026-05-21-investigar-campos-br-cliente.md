# Investigação — campos BR perdidos no cadastro de Cliente (v3.7 → HEAD)

**Data:** 2026-05-21
**Quem perguntou:** Wagner
**Contexto:** Larissa (biz=4 ROTA LIVRE) precisa de campos BR completos. Hipótese Wagner — "v3.7 do git tinha eles, foi perdido em update".

## 1. Versão base UPOS — v3.7 referenciável

| Commit | Mensagem | Papel |
|---|---|---|
| `7ab688162` | "Versão 3.7 Original com as modificações brasil" (2025-06-09) | **Baseline v3.7 BR canon** — fonte autoritativa dos campos perdidos |
| `f9930aa37` | "Atualizado 6.7 funcionando, o outra versão era 6.4 do ultimatepos" | Upgrade UPOS 6.4 → 6.7 (suspeito da regressão) |
| `ad58b9907` | "Atualização 6.7 funcionando" | Upgrade UPOS 6.7 |
| `62e66cad7` | "atualizazação 6.7" | Upgrade UPOS 6.7 |
| HEAD `0a7a4c018` | Compras Wave 3+4+4.5 | Estado atual |

**Confirmação:** v3.7 tinha customizações BR nativas na migration `2017_07_27_075706_create_contacts_table.php`. Após upgrade UPOS 6.7, a `create_contacts_table` foi **sobrescrita** pela versão upstream sem os campos BR. Tags git: nenhuma `v3.7` formal — só commit acima.

## 2. Tabela comparativa "Campos BR"

| Campo | Existia v3.7? (`7ab688162`) | Existe HOJE (HEAD)? |
|---|---|---|
| `cpf_cnpj` (string 20) | ✅ `create_contacts_table` linha 21 | ❌ **REMOVIDO** — só `tax_number` upstream genérico |
| `ie_rg` (string 18) | ✅ `create_contacts_table` linha 22 | ❌ **REMOVIDO** |
| `consumidor_final` (int default 1) | ✅ `create_contacts_table` linha 24 | ❌ **REMOVIDO** |
| `contribuinte` (int default 1) | ✅ `create_contacts_table` linha 25 | ❌ **REMOVIDO** |
| `rua` (string 80) | ✅ `create_contacts_table` linha 27 | ❌ **REMOVIDO** (substituído por `address_line_1`) |
| `numero` (string 10) | ✅ `create_contacts_table` linha 28 | ❌ **REMOVIDO** |
| `bairro` (string 40) | ✅ `create_contacts_table` linha 29 | ❌ **REMOVIDO** |
| `cep` (string 10) | ✅ `create_contacts_table` linha 30 | ❌ **REMOVIDO** (substituído por `zip_code`) |
| `regime` (string nullable) | ✅ migration `2021_01_26_155423_add_regime_table_contacts` | 🟡 **MIGRATION EXISTE** mas falha ao rodar (coluna `contribuinte` ausente — `after('contribuinte')`) |
| `is_sincronizado` (bool) | ✅ migration `2022_12_23_150311_is_sincronizado_contacts` | ❌ **MIGRATION REMOVIDA** do tree HEAD |
| `tax_number` (string nullable) | ✅ (upstream + v3.7) | ✅ campo genérico UPOS — Modelo + Controller usam |
| `is_export` (bool default false) | ❌ não tinha | ✅ migration `2021_03_24_183132` (upgrade trouxe) |
| `export_custom_field_1..6` | ❌ não tinha | ✅ migration `2021_03_24_183132` |
| `custom_field1..10` | ✅ | ✅ |
| `shipping_address` + `shipping_custom_field_details` JSON | ❌ | ✅ |
| `prefix/first_name/middle_name/last_name` | ❌ | ✅ migration `2020_06_12_162245` |
| `nome_fantasia` (em **contacts**) | ❌ (existe em `business_locations` via migration `2026_04_24_100000`) | ❌ NÃO em contacts |
| `inscricao_estadual` / `inscricao_municipal` (em contacts) | ❌ (existe só em `business_locations`) | ❌ NÃO em contacts |
| `suframa` | ❌ nunca existiu | ❌ |
| `indicador_ie` (1/2/9 NFe) | ❌ nunca existiu | ❌ |

**Conclusão:** 8 campos BR perdidos no upgrade UPOS 6.7. 4 campos BR nunca existiram (`suframa`, `indicador_ie`, `inscricao_estadual/municipal` em contacts, `nome_fantasia` em contacts).

## 3. Validators BR

| Validator | Existe? | Onde |
|---|---|---|
| Mod-11 CPF | ✅ `validarCpf($cpf)` | `lib-custom/laravel-boleto/src/Util.php:1162` |
| Mod-11 CNPJ | ✅ `validarCnpj($cnpj)` | `lib-custom/laravel-boleto/src/Util.php:1186` |
| CPF/CNPJ auto-detect | ✅ `validarCnpjCpf($doc)` | `lib-custom/laravel-boleto/src/Util.php:1211` |
| FormRequest BR (rules) | ❌ não existe | — |
| Máscara CPF/CNPJ frontend | ❌ não existe | `Cliente/Create.tsx:154` é `<Input type="text">` puro sem mask |
| Composer package BR canon | ❌ não usado | composer.json tem `nfephp-org/sped-*` mas nada pra CPF/CNPJ |

**Gap:** validators existem dentro de pacote `lib-custom/laravel-boleto` mas **zero uso em `app/` ou `Modules/`** (grep retornou 0 matches fora do próprio arquivo). Não há FormRequest BR nem máscara frontend.

## 4. Form de cadastro HOJE

**Blade legacy `resources/views/contact/create.blade.php`:**
- linha 243-248: 1 campo único `tax_number` rotulado como `__('contact.tax_no')`. Nada mais BR.

**React `resources/js/Pages/Cliente/Create.tsx`:**
- linha 151-156: 1 campo `tax_number` rotulado "CNPJ / CPF" — `<Input type="text">` sem máscara, sem validação mod-11.
- Charter `cliente-create-visual-comparison.md:24`: "Tax number client-side opcional (regex CNPJ/CPF **futuro**)" — explicitamente reconhecido como gap.

**`ContactController@store`** (`app/Http/Controllers/ContactController.php:1358`):
```
$request->only([..., 'tax_number', ..., 'export_custom_field_1..6'])
```
Não valida CPF/CNPJ, não persiste IE/IM/regime/consumidor_final/contribuinte.

## 5. Recomendações

### Top 5 campos a ressuscitar (migration sketch pronta)

```php
// 2026_05_21_140000_restore_br_fields_to_contacts.php
return new class extends Migration {
    public function up(): void {
        Schema::table('contacts', function (Blueprint $t) {
            $t->string('cpf_cnpj', 20)->nullable()->after('tax_number')->index();
            $t->string('inscricao_estadual', 30)->nullable()->after('cpf_cnpj');
            $t->string('inscricao_municipal', 30)->nullable()->after('inscricao_estadual');
            $t->unsignedTinyInteger('indicador_ie')->nullable()->after('inscricao_municipal'); // 1=contribuinte, 2=isento, 9=não contribuinte
            $t->string('nome_fantasia', 150)->nullable()->after('supplier_business_name');
            $t->boolean('consumidor_final')->default(false)->after('indicador_ie');
            $t->string('rg', 20)->nullable()->after('cpf_cnpj');
            $t->string('suframa', 20)->nullable()->after('inscricao_municipal');
        });
    }
    public function down(): void {
        Schema::table('contacts', fn($t) => $t->dropColumn(['cpf_cnpj','inscricao_estadual','inscricao_municipal','indicador_ie','nome_fantasia','consumidor_final','rg','suframa']));
    }
};
```

**Prioridade:** `cpf_cnpj` (#1, identidade fiscal), `inscricao_estadual` (#2, NFe), `indicador_ie` (#3, obrigatório NFe), `nome_fantasia` (#4, comum), `consumidor_final` (#5, flag de regime).

### Top 3 validators a portar

1. Criar `app/Rules/BR/CpfCnpj.php` que delega a `Util::validarCnpjCpf()` (move do laravel-boleto pra rule reutilizável).
2. Criar `app/Rules/BR/InscricaoEstadual.php` (mod-11 per-UF — algoritmo SEFAZ).
3. Máscara frontend: hook `useCpfCnpjMask()` em `resources/js/Pages/Cliente/_create/` que aplica formato dinâmico (11 → CPF, 14 → CNPJ).

## 6. Próximo passo sugerido

**Opção recomendada: US-CRM-NOVA "Restaurar campos BR perdidos no upgrade UPOS 6.7"**

- Slice 1: migration restore (8 campos) + Contact model fillable + ContactController@store/@update validation
- Slice 2: React Create/Edit — Field BR (CPF/CNPJ, IE, IM, nome_fantasia, indicador_ie dropdown) + máscara + Rules
- Slice 3: Show — exibir bloco "Dados Fiscais BR" no tab info
- Slice 4: Backfill — copiar `tax_number` → `cpf_cnpj` quando looks-like-cpf-cnpj (mod-11 válido)
- Slice 5: Import (CSV/legacy Delphi `RotaLivre`) — mapear colunas BR

**ADR pequena recomendada:** "ADR XXXX — Campos fiscais BR canon em contacts (post UPOS 6.7 regression)" — documenta o que upstream removeu, o que voltamos a ter, e separação `tax_number` (genérico UPOS, mantém compat) vs `cpf_cnpj` (canon BR).

**NÃO recomendado** — amendment a Slice A em PR aberto: escopo separado, merece US própria + ADR pra time enxergar.

## Anexos

- Commit baseline v3.7 BR: `7ab688162` (`git show 7ab688162:database/migrations/2017_07_27_075706_create_contacts_table.php`)
- Util validators mod-11: `lib-custom/laravel-boleto/src/Util.php:1162-1219`
- Migration órfã `is_sincronizado` (existe em v3.7, sumiu em HEAD): `git show 7ab688162:database/migrations/2022_12_23_150311_is_sincronizado_contacts.php`
- Migration `regime` (existe em HEAD mas quebrada — referencia coluna `contribuinte` que não existe): `database/migrations/2021_01_26_155423_add_regime_table_contacts.php`
