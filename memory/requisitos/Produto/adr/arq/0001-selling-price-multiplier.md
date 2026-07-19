# ADR ARQ-0001 (Produto) · `SellingPriceGroup` multiplier: estratégia de preço por tabela

- **Status**: proposed
- **Data**: 2026-05-09
- **Proposto por**: [CL] (Claude Code)
- **Decisão pendente**: Wagner escolhe entre 3 alternativas
- **Categoria**: arq · estruturante
- **Relacionado**: [`Produto/Unificado/Index.charter.md`](../../../../../resources/js/Pages/Produto/Unificado/Index.charter.md) (charter draft, PR #369), [`prototipo-ui/prototipos/produto-unificado/`](../../../../../prototipo-ui/prototipos/produto-unificado/) (pino F1, PR #370), [PR #352](https://github.com/wagnerra23/oimpresso.com/pull/352) (batch Cowork bloqueado)
- **Bloqueia**: F3 da tela `/produto/unificado`

## Contexto

Charter draft `Pages/Produto/Unificado/Index.charter.md` (PR #369 mergeado) e protótipo Cowork (`produto-app.jsx` no canon ui_kit cowork-2026-05-09) propõem sub-view "**Tabelas de preço**" mostrando lista com coluna `mult` (multiplicador) por tabela:

```jsx
private function tabelas(int $business_id): array
{
    return SellingPriceGroup::where('business_id', $business_id)
        ->orderBy('name')->get()
        ->map(fn ($g) => [
            'id'    => (string) $g->id,
            'label' => $g->name,
            'desc'  => $g->description ?? '',
            'mult'  => 1.00, // TODO [CL]: ver decisão acima
        ])->all();
}
```

(extraído de `prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php` no PR #352 bloqueado)

**Problema:** `App\SellingPriceGroup` UPOS canon **não tem coluna `multiplier`**. Schema atual:

```php
// database/migrations/2018_..._create_selling_price_groups_table.php (UPOS upstream)
Schema::create('selling_price_groups', function (Blueprint $table) {
    $table->id();
    $table->string('name', 256);
    $table->string('description', 256)->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('business_id');
    $table->softDeletes();
    $table->timestamps();
});
```

Preço por variação × tabela vive em **`variation_group_prices`** (tabela separada UPOS canon):

```php
// database/migrations/..._create_variation_group_prices_table.php
Schema::create('variation_group_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('variation_id');
    $table->foreignId('price_group_id'); // → selling_price_groups
    $table->decimal('price_inc_tax', 22, 4);
    $table->decimal('price_exc_tax', 22, 4); // ← preço POR PRODUTO POR TABELA
    $table->timestamps();
});
```

Logo, UPOS é **explícito** (preço diferente por produto+tabela) enquanto Cowork sugere **multiplicador** (preço base × fator único por tabela). São modelos conceituais diferentes.

## Decisão pendente — 3 alternativas

### Alternativa (a) — Adicionar coluna `multiplier` em `selling_price_groups`

```sql
ALTER TABLE selling_price_groups ADD COLUMN multiplier DECIMAL(10,4) DEFAULT 1.0000 AFTER description;
```

**Semântica:** preço de venda por tabela = `Variation.default_sell_price_inc_tax * SellingPriceGroup.multiplier`. Usado quando `variation_group_prices` não tem registro pra par (variation × group).

**Prós:**
- Simplicidade visual (UI Cowork — 1 número por tabela)
- Ergonomia Larissa: cadastra produto 1× com preço base, define multiplicador por tabela ("atacado 0.85, varejo 1.0, premium 1.20") — pronto
- Tela `/produto/unificado` sub-view "Tabelas" mostra `mult` direto da query (sem agregação)
- Migration aditiva (não-destrutiva — produtos existentes ficam `1.0`)

**Cons:**
- **Conflito conceitual com UPOS canon** — `variation_group_prices` continua sendo fonte autoritária quando existe; `multiplier` é fallback. Dois caminhos pra mesma resposta = ambiguidade
- Precedência: `variation_group_prices.price_inc_tax` vence ou `multiplier` vence? Se vence o explícito, Larissa muda multiplicador e produtos com preço explícito não atualizam (surpresa)
- UPOS upstream não conhece a coluna — futura migration do upstream pode colidir
- Reportes contábeis (`Modules/Accounting`) podem assumir `variation_group_prices` como fonte e ignorar `multiplier` — divergência silenciosa

**Migration risk:** baixo (coluna adicional, não muda existente). PR isolado pra ADR + migration + Model field cast.

### Alternativa (b) — Calcular multiplicador na query (sem schema novo)

Manter UPOS canon (`variation_group_prices` como fonte). Sub-view "Tabelas" mostra **multiplicador derivado** = média (`variation_group_prices.price_inc_tax / Variation.default_sell_price_inc_tax`) OU produto representativo (mais vendido naquela tabela últimos 30d).

**Prós:**
- Zero mudança de schema
- Respeita UPOS canon
- Nenhum risco de conflito upstream
- Reportes contábeis continuam usando única fonte

**Cons:**
- Multiplicador deixa de ser configurável — vira leitura derivada
- Larissa não pode "ajustar atacado pra 0.80 todos os produtos de uma vez" via tela única — precisa editar cada `variation_group_prices` (rota Blade legacy `/products/{id}/edit`)
- Sub-view "Tabelas" mostra "fator médio observado" (info diagnóstica, não controle)
- Perde feature do protótipo Cowork

### Alternativa (c) — Dropar conceito de multiplicador

Sub-view "Tabelas de preço" mostra apenas: nome + descrição + `is_active` + count de produtos com preço definido. Sem coluna `multiplier`. Edição de preços por tabela vai por rota Blade legacy.

**Prós:**
- Mais conservador
- Zero mudança schema
- Zero ambiguidade (UPOS canon vence sempre)
- Tela é puramente listagem/visualização

**Cons:**
- Diverge significativamente do protótipo Cowork (Wagner exportou esse design intencionalmente)
- Não destrava a UX prometida ("ajustar atacado em massa")
- Sub-view fica magrinha (3 colunas) — perde valor

## Recomendação [CL]

Inclino pra **(a) com regra clara de precedência:**

- `variation_group_prices.price_inc_tax` **vence sempre** quando existe registro pra par (variation × group)
- `selling_price_groups.multiplier` é **fallback** apenas quando `variation_group_prices` não tem registro
- UI deixa explícito ("multiplicador padrão — produtos com preço explícito não usam")
- Pest GUARD: teste assertando precedência

Custo: 1 migration aditiva + 2 helpers no Model + Pest. Destravar UX que motivou o protótipo.

Mas é **decisão de produto**, não técnica. Wagner decide considerando:
- (a) Quanto Larissa edita preço por tabela hoje? Se raramente, (b) ou (c) ok
- (b) Se ROTA LIVRE ou outros tenants pediram "ajustar atacado em massa" como feature → (a) é a única que atende
- (c) Risco de divergência com Accounting: aceitável se documentado

## Consequências

### Se (a) — schema novo

- PR isolado: migration + Model `multiplier` field + cast `decimal:4`
- Pest cobre precedência (variation_group_prices vence)
- Doc atualizado (`memory/requisitos/Produto/`)
- Charter `Produto/Unificado/Index.charter.md` flip pra `status: live` (decisão multiplier resolvida)
- `Modules/Accounting` doc adiciona nota "ignora `multiplier`, usa `variation_group_prices`"

### Se (b) — derivar

- Charter atualiza Goals: "sub-view Tabelas mostra multiplicador médio observado (read-only diagnóstico)"
- Sem migration
- Sub-view "Tabelas" perde feature de edição em massa
- Charter flip pra `status: live`

### Se (c) — dropar

- Charter atualiza Non-Goals: "sub-view Tabelas NÃO mostra multiplicador (apenas listagem)"
- Sem migration
- Protótipo Cowork desvia do produto final (registrar em `Histórico` do charter)
- Charter flip pra `status: live`

## Validação pendente

- [ ] Wagner escolhe (a), (b) ou (c)
- [ ] Se (a): PR isolado de migration + Model field + Pest precedência
- [ ] Charter `Produto/Unificado/Index.charter.md` atualiza Goals/Non-Goals refletindo decisão
- [ ] Charter flip `status: draft` → `status: live`
- [ ] F3 destravado pra `/produto/unificado`

## Refs

- [Charter draft Produto/Unificado/Index](../../../../../resources/js/Pages/Produto/Unificado/Index.charter.md) — esse ADR destrava
- [Pino F1 produto-unificado](../../../../../prototipo-ui/prototipos/produto-unificado/) — material visual original
- [`ui_kits/cowork-2026-05-09/produto-app.jsx`](../../../_DesignSystem/ui_kits/cowork-2026-05-09/produto-app.jsx) — código que motivou a pergunta
- [LICOES_F3_FINANCEIRO_REJEITADO.md M-AP-4](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — esquema novo precisa ADR antes de Controller usar (esse ADR resolve esse anti-padrão)
- [PR #352](https://github.com/wagnerra23/oimpresso.com/pull/352) — batch Cowork original que foi bloqueado em parte por essa decisão pendente
- UPOS canon: `App\SellingPriceGroup`, `App\VariationGroupPrice` (tabela `variation_group_prices`)
- Documentação UPOS upstream: https://ultimatefosters.com/docs/ (referência tabelas de preço)

---

## Errata (2026-07-17) — a premissa "modelos conceituais diferentes" está FALSA

> Append-only: não reescrevo o §Contexto acima (é o registro do que foi proposto em maio). Esta
> errata corrige a **premissa**, achada por [F] (sessão 2026-07-16) e confirmada por dois
> adversários independentes (2026-07-17). Fonte: **código executável em `origin/main`**, não leitura.

O §Contexto conclui *"UPOS é explícito … enquanto Cowork sugere multiplicador … modelos conceituais
diferentes"*. **Não são diferentes — o "multiplicador" já existe no UPOS desde 2023, um ano antes
desta ADR, e o §Contexto não o cita:** a coluna `variation_group_prices.price_type` aceita
`'percentage'`, e um preço percentual **É** um multiplicador sobre o preço base da variação.

**Evidência (medida em 2026-07-17, contra `origin/main`, medindo o código):**
- `app/Utils/ProductUtil.php:1064-1069` — `if ($price_group->price_type == 'percentage') { … calc_percentage(variation->sell_price_inc_tax, price_inc_tax) }` — o ramo percentual, aplicado na venda.
- `app/VariationGroupPrice.php` — accessor `getCalculatedPriceAttribute()`, mesma lógica.
- `app/Utils/ProductUtil.php:1729` — `IF(VGP.price_type="fixed", …, VGP.price_inc_tax * variations.sell_price_inc_tax / 100)` em SQL.
- `tests/Feature/Calculo/CalculoValorProdutoTest.php:232` — golden DB-backed do caso percentual.

**Consequência pras 3 alternativas:** a decisão que o Wagner precisa tomar **muda de forma**. Não é
mais *"criar um conceito de multiplicador que não existe"* (Alternativa a/b) vs *"dropar"* (c). O
multiplicador **por célula** já existe e funciona. O gap real é **granularidade**: hoje o percentual
é declarado célula a célula (produto × tabela), e o que falta é a **regra de tabela inteira**
("Atacado = −15% em tudo") — um DEFAULT no nível `selling_price_groups` que a célula sobrescreve.
Isso é uma **generalização** do que já existe, não um conceito novo.

**O `mult => 1.00` nunca foi um multiplicador neutralizado.** É prop **cosmético** fabricado só pro
protótipo `/unificado` (`ProdutoUnificadoController::tabelas()`), como o próprio autor comentou no
código (*"Multiplicador NÃO existe nativamente — o protótipo Cowork usa como simplificação visual"*).
A coluna `mult`/`multiplier` **não existe** em `selling_price_groups` (schema: `name`, `description`,
`business_id`, `is_active`, timestamps). Logo **"preço por tabela é 1:1 / aparenta funcionar mas não
funciona"** — a leitura que se propagou pra SDD/FICHA/INVENTARIO/BRIEFING — é **falsa**: o preço por
(variação × tabela) funciona, `fixed` e `percentage`, e chega na venda (`SellPosController.php:1790`).

**Status:** segue `proposed`. Esta errata **não decide** — só corrige a premissa pra que a decisão do
Wagner (a/b/c, e a nova granularidade) parta do estado real. Re-enquadrar a US-PROD-022 / o gap G-02 /
a nota C02 da FICHA é **decisão [W]** (a nota foi calculada sobre a premissa falsa).

---

**Decisão pendente desde:** 2026-05-09. Bloqueador de F3 `/produto/unificado` até resolução.
