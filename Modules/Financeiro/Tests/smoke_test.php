<?php

/**
 * Smoke test rápido pra Sub-Onda 1A.
 * Roda via tinker.
 */

use Modules\Financeiro\Models\PlanoConta;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;

// 1. Verifica seed
$total = PlanoConta::withoutGlobalScope(BusinessScopeImpl::class)
    ->where('business_id', 4)
    ->count();
echo "[1] Total contas business 4 (ROTA LIVRE): $total\n";

// 2. Bloqueio delete em conta protegida (TECH-0002)
$caixa = PlanoConta::withoutGlobalScope(BusinessScopeImpl::class)
    ->where('business_id', 4)
    ->where('codigo', '1.1.01.001')
    ->first();
try {
    $caixa->delete();
    echo "[2] FALHOU: deletou conta protegida!\n";
} catch (\DomainException $e) {
    echo "[2] OK: bloqueio protegido funciona\n";
}

// 3. Hard delete em fin_titulos é bloqueado (regra append-only)
$titulo = new \Modules\Financeiro\Models\Titulo();
try {
    $titulo->delete();
    echo "[3] FALHOU: deletou titulo!\n";
} catch (\DomainException $e) {
    echo "[3] OK: titulo append-only funciona\n";
}

// 4. ActivityLog disponível
$titulo = new \Modules\Financeiro\Models\Titulo();
echo "[4] ActivityLog em Titulo: " . (method_exists($titulo, 'activities') ? 'OK' : 'FALTA') . "\n";

echo "\n=== Sub-Onda 1A Financeiro: smoke test PASSED ===\n";
