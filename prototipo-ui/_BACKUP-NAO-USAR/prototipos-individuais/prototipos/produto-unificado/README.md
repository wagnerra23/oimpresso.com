# Protótipo F1 — produto-unificado

**Status:** 🟡 PINO F1 HISTÓRICO + Charter draft.
**Aprovado por:** [W] 2026-05-09 (Cowork export incluído no zip canon)
**Stories:** sem story em SPEC ainda — backlog
**Charter draft:** [`Pages/Produto/Unificado/Index.charter.md`](../../../resources/js/Pages/Produto/Unificado/Index.charter.md)

> ⚠️ **Antes de F3:** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight checklist obrigatório (T-AP-1 a T-AP-15) + 6 meta-anti-padrões + convenções pt-BR herdadas UPOS. Sem ele, mesmo erro do batch [#352](https://github.com/wagnerra23/oimpresso.com/pull/352) repete.

## Histórico — por que esse pino existe

Originalmente esse material vinha em [PR #352](https://github.com/wagnerra23/oimpresso.com/pull/352) (`feat(prototipo-produto): F1 + F3 Catálogo Unificado`) que **misturava**:

- ✅ 4 arquivos visuais (.jsx + .html) → corretos como pino F1 — extraídos pra cá
- ❌ 3 arquivos código produção (`app/Http/Controllers/ProdutoUnificadoController.php`, `routes/web.php`, `Pages/Produto/Unificado/Index.tsx`) → bloqueados por 4 anti-padrões catalogados em [LICOES](../../LICOES_F3_FINANCEIRO_REJEITADO.md):
  - **T-AP-9** sem `__construct` middleware (auth + can:product.view)
  - **M-AP-1** 9 `TODO [CL]` admitindo coisa não conferida
  - **M-AP-3** HANDOFF diz "não mergeie" mas gates protocolo não atendidos
  - **M-AP-4** `SellingPriceGroup.multiplier` schema novo em TODO sem ADR

PR #352 segue aberto com [comentário de bloqueio](https://github.com/wagnerra23/oimpresso.com/pull/352#issuecomment-4413977584) — pode ser fechado quando charter virar `live` + ADR multiplier mergear (esse PR resolve a parte aproveitável).

## O que está aqui

| Arquivo | Tamanho | Função |
|---|---|---|
| `produto-app.jsx` | 60 KB | Catálogo unificado completo (Sidebar 220px + 5 sub-views: Produtos / Categorias / Insumos·BOM / Tabelas de preço / Histórico) |
| `produto-data.jsx` | 13.6 KB | Mock data (catálogo + categorias + insumos + tabelas + histórico) |
| `produto-icons.jsx` | 6 KB | Ícones lucide-style customizados |
| `Produto Unificado.html` | 2.9 KB | Entry shell pra rodar offline |

Material é **idêntico** ao já em [`memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/) (canon imutável). Esse pino é referência **operacional** pra F3 quando charter virar `live`.

## Como rodar offline (smoke visual)

1. `python -m http.server 8080` ou Live Server na pasta
2. Abrir `Produto Unificado.html` no navegador
3. Babel-standalone transpila .jsx em runtime — sem build

## Decisões pendentes (charter draft)

3 decisões em aberto antes do charter virar `status: live`:

1. **`SellingPriceGroup.multiplier`** — UPOS não tem nativamente. Opções (a) adicionar coluna `multiplier`, (b) calcular via `VariationGroupPrice`, (c) dropar conceito → **ADR `arq/0001-selling-price-multiplier.md`** em PR separado pra decidir
2. **`MfgRecipe` namespace** — Cowork admite "TODO confirmar nomes Mfg*". Real é `Modules\Manufacturing\Entities\MfgRecipe` (controller candidato Cowork tinha certo, mas precisa confirmação)
3. **Cache strategy KPIs** — agregação `TransactionSellLine` 30d é pesada. Job diário cron vs `Cache::remember`?

## Pré-requisitos pra F3 (quando charter virar live + ADR multiplier mergear)

1. Charter `status: live` (Wagner aprova Non-Goals + Anti-hooks)
2. ADR `arq/0001-selling-price-multiplier` mergeada (decisão schema)
3. Confirmar `Modules\Manufacturing\Entities\MfgRecipe` existe (`Glob`)
4. Service `Modules/Produto/Services/ProdutoUnificadoService.php` (a criar — UPOS não tem) ou agregação inline no Controller
5. Permission seeder confirmar: `product.view`, `product.create`, `product.update`

## Próximo passo

1. Wagner aprova [charter draft](../../../resources/js/Pages/Produto/Unificado/Index.charter.md) (Non-Goals + Anti-hooks)
2. Wagner aprova ADR `arq/0001-selling-price-multiplier` (PR separado)
3. F0 abre em [`COWORK_NOTES.md`](../../COWORK_NOTES.md) apontando pra esse pino
4. Loop F1.5 → F3 normal
