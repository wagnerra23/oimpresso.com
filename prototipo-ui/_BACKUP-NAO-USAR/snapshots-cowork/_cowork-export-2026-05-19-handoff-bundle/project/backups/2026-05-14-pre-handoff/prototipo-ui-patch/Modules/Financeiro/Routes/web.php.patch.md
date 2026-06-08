# Patch — Routes/web.php (Modules/Financeiro)

> **Como aplicar:** abrir `Modules/Financeiro/Routes/web.php` no projeto, adicionar os imports e o bloco de rotas dentro do `Route::middleware(['web', 'auth', 'tenant'])->prefix('financeiro')->name('financeiro.')->group(function () { ... })` existente. Manter rotas anteriores intactas.

## Imports a adicionar (topo do arquivo)

```php
use Modules\Financeiro\Http\Controllers\UnificadoController;
use Modules\Financeiro\Http\Controllers\FluxoController;
use Modules\Financeiro\Http\Controllers\ConciliacaoController;
use Modules\Financeiro\Http\Controllers\DREController;
use Modules\Financeiro\Http\Controllers\PlanoContasController;
```

## Rotas a adicionar (dentro do grupo `financeiro.*` existente)

```php
// ── Visão Unificada (F3 PR atual)
Route::get('/unificado',            [UnificadoController::class, 'index'])->name('unificado.index');
Route::post('/unificado/{id}/baixar', [UnificadoController::class, 'baixar'])->name('unificado.baixar');

// ── Fluxo de caixa
Route::get('/fluxo-caixa',          [FluxoController::class, 'index'])->name('fluxo-caixa.index');

// ── Conciliação bancária
Route::get('/conciliacao',          [ConciliacaoController::class, 'index'])->name('conciliacao.index');
Route::post('/conciliacao/{id}/aceitar',  [ConciliacaoController::class, 'aceitar'])->name('conciliacao.aceitar');
Route::post('/conciliacao/{id}/desfazer', [ConciliacaoController::class, 'desfazer'])->name('conciliacao.desfazer');

// ── DRE / Relatórios
Route::get('/dre',                  [DREController::class, 'index'])->name('dre.index');
Route::get('/dre/export',           [DREController::class, 'export'])->name('dre.export');

// ── Plano de contas
Route::get('/plano-de-contas',           [PlanoContasController::class, 'index'])->name('plano-de-contas.index');
Route::get('/plano-de-contas/criar',     [PlanoContasController::class, 'create'])->name('plano-de-contas.create');
Route::post('/plano-de-contas',          [PlanoContasController::class, 'store'])->name('plano-de-contas.store');
Route::get('/plano-de-contas/importar',  [PlanoContasController::class, 'importar'])->name('plano-de-contas.importar');
Route::get('/plano-de-contas/{id}/edit', [PlanoContasController::class, 'edit'])->name('plano-de-contas.edit');
Route::put('/plano-de-contas/{id}',      [PlanoContasController::class, 'update'])->name('plano-de-contas.update');
Route::delete('/plano-de-contas/{id}',   [PlanoContasController::class, 'destroy'])->name('plano-de-contas.destroy');
```

## Sidebar — Modules/Financeiro/Resources/sidebar.php (ou config equivalente)

Sub-itens em "Financeiro":

```php
['label' => 'Visão unificada',  'route' => 'financeiro.unificado.index'],
['label' => 'Fluxo de caixa',   'route' => 'financeiro.fluxo-caixa.index'],
['label' => 'Conciliação',      'route' => 'financeiro.conciliacao.index'],
['label' => 'DRE / Relatórios', 'route' => 'financeiro.dre.index'],
['label' => 'Plano de contas',  'route' => 'financeiro.plano-de-contas.index'],
```
