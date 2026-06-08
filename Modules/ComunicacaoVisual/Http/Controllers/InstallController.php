<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use Illuminate\Support\Facades\Log;
use Modules\ComunicacaoVisual\Database\Seeders\MaterialSeeder;

/**
 * InstallController — Modules/ComunicacaoVisual (CNAE 1813-0/01).
 *
 * Estende BaseModuleInstallController (ADR 0024).
 * Acesso: /comunicacao-visual/install (superadmin → Manage Modules)
 *
 * Sprint 1: scaffold + MaterialSeeder (5 materiais default pra demo out-of-the-box).
 * Sprint 2+: migrations de schema adicionais.
 *
 * @see app/Http/Controllers/BaseModuleInstallController.php
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'ComunicacaoVisual';
    }

    protected function moduleSystemKey(): string
    {
        return 'comunicacaovisual';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Comunicação Visual instalado. Vertical CNAE 1813 — gráfica/com.visual. 5 materiais default adicionados ao catálogo.';
    }

    /**
     * Hook pós-migração — semente o catálogo de materiais default pro business atual.
     *
     * Roda dentro da transação principal (BaseModuleInstallController::index).
     * Se o seeder falhar, logamos warning mas NÃO revertemos as migrations —
     * instalação parcial é preferível a rollback total (user pode re-rodar Install).
     *
     * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
     * NÃO usamos session() no Seeder — passamos o businessId explicitamente via
     * session da request HTTP (seguro pra controller web, unsafe pra CLI/queue).
     */
    protected function postMigrationSteps(): void
    {
        // Sessão de request HTTP — superadmin autenticado garante que business_id existe
        $businessId = session('user.business_id') ?? session('business.id');

        if (! $businessId) {
            Log::warning('ComunicacaoVisual/InstallController: business_id ausente na sessão — MaterialSeeder ignorado.');
            return;
        }

        try {
            (new MaterialSeeder())->run((int) $businessId);
        } catch (\Throwable $e) {
            // Seeder falhou — log warning mas não propaga (migrations já commitadas)
            Log::warning('ComunicacaoVisual/InstallController: MaterialSeeder falhou (não crítico).', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
