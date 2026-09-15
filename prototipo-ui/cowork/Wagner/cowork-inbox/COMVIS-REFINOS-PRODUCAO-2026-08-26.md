# Comunicação Visual — 7 refinos aplicáveis em produção

**Tela:** `resources/js/Pages/ComunicacaoVisual/Index.tsx` (+ rota do módulo)
**Lido no `main` neste turno (2026-08-26, árvore `6fa1d053f32c`):** `Index.tsx`, `Index.charter.md`, `Modules/ComunicacaoVisual/Routes/web.php`, `Http/Controllers/OrcamentoController.php`, `Services/OrcamentoCalculator.php`, `Http/Requests/CalcularOrcamentoRequest.php`, `Entities/Material.php`.
**Natureza:** todos são no vivo. Nenhum depende de tela nova, PCP ou API nova — as rotas e o Service já existem. R1/R2 são defeito, R3–R5 são divergência de contrato, R6 é a11y, R7 é dívida de doc.

---

## R1 — `materiais` nunca chega na tela: o catálogo está sempre vazio (P0)

`Routes/web.php` renderiza só `bizName`:

```php
return Inertia::render('ComunicacaoVisual/Index', [
    'bizName' => session('business.name', 'oimpresso'),
]);
```

`Index.tsx` recebe `materiais = []` e `podeCriar = false` **sempre**. Consequência no balcão: o select fica `disabled` com "Sem catálogo", e o aviso *"Você ainda não tem materiais cadastrados"* **mente** para quem rodou o `MaterialSeeder` — o gancho de valor da tela (preço/m² preenchendo sozinho) nunca dispara. `Material::ativos()` e `preco_venda_m2` já existem.

```diff
-            return Inertia::render('ComunicacaoVisual/Index', [
-                'bizName' => session('business.name', 'oimpresso'),
-            ]);
+            return Inertia::render('ComunicacaoVisual/Index', [
+                'bizName'   => session('business.name', 'oimpresso'),
+                // Catálogo do business (global scope Tier 0 filtra business_id).
+                // Inertia::defer por canon do charter (skill inertia-defer-default).
+                'materiais' => Inertia::defer(fn () => \Modules\ComunicacaoVisual\Entities\Material::ativos()
+                    ->orderBy('categoria')->orderBy('nome')
+                    ->get(['id', 'nome', 'categoria', 'unidade', 'preco_venda_m2'])),
+                'podeCriar' => auth()->user()->can('comvis.orcamento.create')
+                    || auth()->user()->can('superadmin'),
+            ]);
```

Com `defer`, o `semCatalogo` do front passa a ver `materiais === undefined` no primeiro paint: trocar a condição por `materiais !== undefined && materiais.length === 0` (ou renderizar `Skeleton` na primeira linha) pra não piscar o aviso falso.

## R2 — `crypto.randomUUID()` derruba a tela em contexto não-seguro (P0)

`novoItem()` chama `crypto.randomUUID()` no primeiro `useState` — em `http://` (o IP da rede do balcão, cenário Larissa) `randomUUID` é `undefined` fora de secure context: exceção no render inicial, tela branca, sem mensagem.

```diff
+let seqItem = 0;
 function novoItem(): ItemUI {
   return {
-    id: crypto.randomUUID(),
+    // Sem crypto.randomUUID: em http:// (balcão na rede local) ele não existe.
+    id: `i${++seqItem}`,
```

## R3 — "Conferir no servidor" libera com linha inválida (`some` deveria ser `every`)

```js
const temItemValido = itens.some(...)
```

Com uma peça boa + uma linha recém-adicionada (preço 0, sem material), o botão fica ativo e o servidor devolve 422 `"Item #1: preco_unitario_m2 é obrigatório…"` — mensagem com índice 0-based e jargão de campo, exatamente o que a Larissa não sabe ler.

```diff
-  const temItemValido = itens.some(
-    (i) => i.largura_m > 0 && i.altura_m > 0 && i.quantidade >= 1 && i.preco_unitario_m2 > 0,
-  );
+  const itemOk = (i: ItemUI) =>
+    i.largura_m > 0 && i.altura_m > 0 && i.quantidade >= 1 && i.preco_unitario_m2 > 0;
+  // Todas as linhas: o servidor recusa o orçamento inteiro por causa de uma.
+  const temItemValido = itens.length > 0 && itens.every(itemOk);
```

e marcar a linha que falta preço (`aria-invalid` no Input de R$/m² quando `!itemOk(item)`), pro operador ver **onde** em vez de ler o índice.

## R4 — Total da tela ≠ total do servidor em dois casos

`OrcamentoCalculator`: `total = subtotal - desconto + extras + custo_instalacao + custo_entrega`, **sem clamp**. A tela faz `Math.max(0, …)`.

1. **Desconto > subtotal:** tela mostra `R$ 0,00`, servidor devolve negativo → cai no ramo *"Servidor: -R$ 40,00 (vale este)"*. O Request só valida `desconto >= 0`. Tirar o clamp e avisar em PT-BR ("O desconto está maior que o subtotal") em vez de esconder.
2. **Arredondamento:** o servidor arredonda `area_m2` a 3 casas e o subtotal do item a 2, por item, `HALF_UP`; a prévia soma float bruto. Em qtd alta a diferença de centavo joga a badge no ramo "vale este" sem motivo. Espelhar os rounds na prévia:

```diff
-function areaDe(item: ItemUI): number {
-  return Math.max(0, item.largura_m) * Math.max(0, item.altura_m) * Math.max(0, item.quantidade);
-}
-function subtotalDe(item: ItemUI): number {
-  return areaDe(item) * Math.max(0, item.preco_unitario_m2);
-}
+/** Espelha o round do OrcamentoCalculator (HALF_UP, 3 casas na área / 2 no subtotal). */
+const r = (n: number, casas: number) => Math.round(n * 10 ** casas) / 10 ** casas;
+function areaDe(item: ItemUI): number {
+  return r(Math.max(0, item.largura_m) * Math.max(0, item.altura_m) * Math.max(0, item.quantidade), 3);
+}
+function subtotalDe(item: ItemUI): number {
+  return r(areaDe(item) * Math.max(0, item.preco_unitario_m2), 2);
+}
```

## R5 — `CalcularOrcamentoRequest` é uma armadilha: contrato divergente e (aparentemente) órfã

O `OrcamentoController` valida **inline** (`largura_m`, `altura_m`, `preco_unitario_m2`, `desconto`, `extras`). A Form Request que diz cobrir os mesmos endpoints valida outro contrato: `largura_mm`, `altura_mm`, `preco_m2` (required), `desconto_tipo`/`desconto_valor`, `acabamento_id`. Quem plugar a Request no controller "pra organizar" quebra a tela com 422 em todo cálculo.

Ação: `rg CalcularOrcamentoRequest` — se não houver uso (foi o que a leitura indicou, mas o meu grep foi limitado), **alinhar aos nomes canônicos e usar no controller** ou apagar o arquivo. Não deixar as duas verdades.

## R6 — A11y: os campos numéricos perdem o nome em ≥ md

Os `<Label>` das linhas são `md:hidden` e não têm `htmlFor`; o cabeçalho de colunas é `span` solto. No desktop (o monitor real da persona) largura, altura, qtd e R$/m² ficam **sem nome acessível** — só o `<select>` tem `aria-label`. Trocar `md:hidden` por `sr-only md:not-sr-only`… não resolve (inverte); o caminho certo é nome fixo no controle:

```diff
-                    <Label className="text-xs md:hidden">Largura (m)</Label>
+                    <Label htmlFor={`larg-${item.id}`} className="text-xs md:hidden">Largura (m)</Label>
                     <Input
+                      id={`larg-${item.id}`}
+                      aria-label="Largura em metros"
```

(idem altura / qtd / preço; e `aria-hidden="true"` na faixa de cabeçalho, que é decorativa quando os controles já têm nome.)

## R7 — Charter fora da realidade

`Index.charter.md` diz `status: draft`, *"🟡 Stub Sprint 2 — UI Inertia ainda não ativada"* e descreve **3 widgets que a tela não tem** (orçamentos pendentes, PCP miniatura, apontamentos do dia). A tela entrega calculadora m² + conferência authoritative + bloco "em breve". Reescrever "Objetivo/Estado atual" pro que existe e mover os 3 widgets pra "Próximo" (US-COMVIS-002/003/004), senão o próximo agente refaz a tela achando que é stub.

---

## Bônus barato (não é defeito, é valor de balcão)

Rodapé de totais mostra `Subtotal` e `Total estimado`; falta a linha que a Larissa usa pra comprar material: **`N peças · X m² no orçamento`** (soma de `areaDe`, tabular-nums). Uma linha de JSX no bloco de totais.

## Gates

`npm run lint` · `npm run typecheck` · `php artisan test --filter=ComunicacaoVisual` (`OrcamentoControllerTest`, `OrcamentoCalculatorTest`, `ContratoTelaOrcamentoTest`, `Tier0GuardTest`) · `node scripts/qa/prototipo-readiness.mjs`.
R1 pede caso novo em `Index.casos.md`: "catálogo com materiais → select habilitado e preço preenche" e "sem materiais → aviso honesto".
