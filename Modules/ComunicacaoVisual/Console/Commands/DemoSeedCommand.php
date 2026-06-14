<?php

namespace Modules\ComunicacaoVisual\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Database\Seeders\MaterialSeeder;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;

/**
 * comvis:demo-seed — semente de dados demo end-to-end para ComunicacaoVisual.
 *
 * Cria fluxo completo: Orçamento (3 itens m²) → OS (em produção) → Apontamento (90 min)
 * pra Wagner mostrar ao cliente piloto o fluxo gráfica sem precisar de dados reais.
 *
 * Idempotente via marker `[CV-DEMO]` em observacoes — use `--clean` pra resetar.
 * Multi-tenant Tier 0 ([ADR 0093]): business_id SEMPRE via --business (sem session CLI).
 *
 * Uso:
 *   php artisan comvis:demo-seed --business=1
 *   php artisan comvis:demo-seed --business=1 --clean
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class DemoSeedCommand extends Command
{
    protected $signature = 'comvis:demo-seed
        {--business= : business_id obrigatório (CLI sem session)}
        {--clean : Limpa dados demo anteriores (marker [CV-DEMO]) antes de recriar}';

    protected $description = 'Cria dados demo end-to-end: Orçamento (3 itens) + OS + Apontamento pra ComunicacaoVisual.';

    /** Marker em observacoes pra identificar e limpar dados de demo. */
    private const DEMO_MARKER = '[CV-DEMO]';

    /**
     * Tabelas necessárias para o command funcionar.
     * Verificadas com Schema::hasTable antes de qualquer operação.
     */
    private const TABELAS_OBRIGATORIAS = [
        'comvis_materiais',
        'comvis_orcamentos',
        'comvis_orcamento_itens',
        'comvis_os',
        'comvis_apontamentos',
        'business',
        'users',
    ];

    public function handle(): int
    {
        // ----------------------------------------------------------------
        // 1. Validar --business obrigatório
        // ----------------------------------------------------------------
        $bizId = $this->option('business');

        if ($bizId === null || $bizId === '') {
            $this->error('--business é obrigatório. Informe o business_id (ex: --business=1).');
            return 1;
        }

        $bizId = (int) $bizId;

        if ($bizId < 1) {
            $this->error("--business deve ser >= 1 (recebido: {$bizId}).");
            return 1;
        }

        // ----------------------------------------------------------------
        // 2. Verificar que todas as tabelas necessárias existem
        // ----------------------------------------------------------------
        foreach (self::TABELAS_OBRIGATORIAS as $tabela) {
            if (! Schema::hasTable($tabela)) {
                $this->error("Tabela '{$tabela}' não encontrada. Rode as migrations antes: php artisan migrate.");
                return 1;
            }
        }

        // Verificar que o business existe
        $business = DB::table('business')->where('id', $bizId)->first();
        if (! $business) {
            $this->error("business_id={$bizId} não encontrado na tabela 'business'.");
            return 1;
        }

        $this->info("=== comvis:demo-seed — business_id={$bizId} ===");

        // ----------------------------------------------------------------
        // 3. Garantir materiais seeded (≥ 5 para este business)
        // ----------------------------------------------------------------
        // SUPERADMIN: CLI sem session HTTP; $bizId vem de --business validado (linhas 63-92).
        $totalMateriais = Material::withoutGlobalScopes()
            ->where('business_id', $bizId)
            ->count();

        if ($totalMateriais < 5) {
            $this->line('  → Menos de 5 materiais encontrados. Executando MaterialSeeder...');
            $seeder = new MaterialSeeder();
            $seeder->setCommand($this);
            $seeder->run($bizId);
        } else {
            $this->line("  → {$totalMateriais} materiais já seeded (ok).");
        }

        // ----------------------------------------------------------------
        // 4. Buscar ou criar contato dummy "Cliente Demo CV"
        // ----------------------------------------------------------------
        $contatoId = $this->resolverContatoDemo($bizId);

        // ----------------------------------------------------------------
        // 5. Buscar primeiro user do business como vendedor
        // ----------------------------------------------------------------
        $userId = DB::table('users')
            ->where('business_id', $bizId)
            ->orderBy('id')
            ->value('id');

        if ($userId === null) {
            // Fallback pra ID=1 se não há user no business
            $userId = 1;
            $this->warn('  → Nenhum user em business_id=' . $bizId . '. Usando fallback user_id=1 como vendedor.');
        }

        // ----------------------------------------------------------------
        // 6. Limpar dados demo anteriores se --clean
        // ----------------------------------------------------------------
        if ($this->option('clean')) {
            $this->limparDemo($bizId);
        }

        // ----------------------------------------------------------------
        // 7. Criar Orçamento com 3 itens via OrcamentoCalculator
        // ----------------------------------------------------------------
        $ano       = now()->year;
        $numOrc    = "ORC-{$ano}-DEMO-{$bizId}";
        $numOs     = "OS-{$ano}-DEMO-{$bizId}";

        // Resolver material_ids via withoutGlobalScopes (CLI sem session)
        $materialLona  = $this->buscarMaterial($bizId, 'Lona Front 280g');
        $materialVinil = $this->buscarMaterial($bizId, 'Vinil Adesivo Brilho Branco');
        $materialAcm   = $this->buscarMaterial($bizId, 'ACM 3mm Branco');

        /** @var OrcamentoCalculator $calc */
        $calc = app(OrcamentoCalculator::class);

        // Simular sessão pra OrcamentoCalculator::resolverPreco() buscar material com global scope
        // SUPERADMIN: necessário pra Calculator::resolverPreco() funcionar em contexto CLI
        session(['user.business_id' => $bizId, 'business.id' => $bizId]);

        $payload = [
            'data_emissao'     => now()->toDateString(),
            'data_validade'    => now()->addDays(15)->toDateString(),
            'contato_id'       => $contatoId,
            'vendedor_id'      => $userId,
            'desconto'         => 0.00,
            'extras'           => 80.00,    // arte + edição
            'custo_instalacao' => 200.00,
            'custo_entrega'    => 80.00,
            'observacoes'      => self::DEMO_MARKER . ' Orçamento exemplo: banner + adesivos + fachada ACM',
            'itens'            => [
                [
                    // Banner 3.0×1.5m lona front — area=4.500 m²
                    'material_id'      => $materialLona,
                    'descricao'        => 'Banner lona front 280g — fachada externa',
                    'largura_m'        => 3.0,
                    'altura_m'         => 1.5,
                    'quantidade'       => 1,
                    'preco_unitario_m2' => $materialLona ? null : 35.00,
                    'observacoes'      => null,
                ],
                [
                    // Adesivos vitrine 2.0×0.5m vinil — area=5.000 m² (5 unidades)
                    'material_id'      => $materialVinil,
                    'descricao'        => 'Adesivo vitrine vinil brilho — lateral loja',
                    'largura_m'        => 2.0,
                    'altura_m'         => 0.5,
                    'quantidade'       => 5,
                    'preco_unitario_m2' => $materialVinil ? null : 60.00,
                    'observacoes'      => null,
                ],
                [
                    // Placa ACM 1.0×0.8m fachada — area=0.800 m²
                    'material_id'      => $materialAcm,
                    'descricao'        => 'Placa ACM 3mm branco — identificação fachada',
                    'largura_m'        => 1.0,
                    'altura_m'         => 0.8,
                    'quantidade'       => 1,
                    'preco_unitario_m2' => $materialAcm ? null : 180.00,
                    'observacoes'      => null,
                ],
            ],
        ];

        $calculado = $calc->calcular($payload);

        // Criar Orcamento via withoutGlobalScopes (CLI sem session real de HTTP)
        // SUPERADMIN: CLI sem session; business_id => $bizId explícito (--business validado).
        $orcamento = Orcamento::withoutGlobalScopes()->create([
            'business_id'      => $bizId,
            'numero'           => $numOrc,
            'contato_id'       => $contatoId,
            'vendedor_id'      => $userId,
            'data_emissao'     => $calculado['data_emissao'],
            'data_validade'    => $calculado['data_validade'],
            'status'           => 'aprovado',
            'subtotal'         => $calculado['subtotal'],
            'desconto'         => $calculado['desconto'],
            'extras'           => $calculado['extras'],
            'custo_instalacao' => $calculado['custo_instalacao'],
            'custo_entrega'    => $calculado['custo_entrega'],
            'total'            => $calculado['total'],
            'observacoes'      => $calculado['observacoes'],
        ]);

        // Criar OrcamentoItens
        foreach ($calculado['itens'] as $ordem => $itemCalc) {
            // SUPERADMIN: CLI sem session; business_id => $bizId explícito (--business validado).
            OrcamentoItem::withoutGlobalScopes()->create([
                'orcamento_id'     => $orcamento->id,
                'business_id'      => $bizId,
                'material_id'      => $itemCalc['material_id'],
                'descricao'        => $itemCalc['descricao'],
                'largura_m'        => $itemCalc['largura_m'],
                'altura_m'         => $itemCalc['altura_m'],
                'quantidade'       => $itemCalc['quantidade'],
                'area_m2'          => $itemCalc['area_m2'],
                'preco_unitario_m2' => $itemCalc['preco_unitario_m2'],
                'subtotal'         => $itemCalc['subtotal'],
                'observacoes'      => $itemCalc['observacoes'],
                'ordem'            => $ordem + 1,
            ]);
        }

        // ----------------------------------------------------------------
        // 8. Criar OS vinculada ao orçamento
        // ----------------------------------------------------------------
        // SUPERADMIN: CLI sem session; business_id => $bizId explícito (--business validado).
        $os = Os::withoutGlobalScopes()->create([
            'business_id'  => $bizId,
            'orcamento_id' => $orcamento->id,
            'numero'       => $numOs,
            'status_etapa' => 'producao',
            'data_inicio'  => now()->toDateString(),
            'data_prazo'   => now()->addDays(7)->toDateString(),
            'vendedor_id'  => $userId,
            'valor_total'  => $orcamento->total,
            'observacoes'  => self::DEMO_MARKER . ' OS gerada pelo demo-seed — fluxo completo orçamento→produção',
        ]);

        // ----------------------------------------------------------------
        // 9. Criar Apontamento via ApontamentoTracker (90 min de produção)
        //    m2_produzido=4.5 (banner 3×1.5×1) — drift=0% contra m2_orcado=4.5
        // ----------------------------------------------------------------
        // SUPERADMIN: CLI sem session; $orcamento já criado neste $bizId acima.
        $primeiroItem = OrcamentoItem::withoutGlobalScopes()
            ->where('orcamento_id', $orcamento->id)
            ->orderBy('ordem')
            ->first();

        // Timestamps controlados: iniciou 90 min atrás, finalizou agora
        $iniciouEm    = now()->subMinutes(90);
        $finalizouEm  = now();
        $duracaoSeg   = (int) $finalizouEm->diffInSeconds($iniciouEm); // ~5400

        // m2_orcado = area_m2 do primeiro item (banner 3×1.5 = 4.500 m²)
        $m2Orcado    = $primeiroItem ? (float) $primeiroItem->area_m2 : 4.5;
        $m2Produzido = 4.5; // drift=0 (produção exata = orçado)

        // drift = ((prod - orc) / orc) × 100 = ((4.5 - 4.5) / 4.5) × 100 = 0.00
        $driftPercent = $m2Orcado > 0
            ? round((($m2Produzido - $m2Orcado) / $m2Orcado) * 100, 2)
            : null;

        // Inserção direta (withoutGlobalScopes) pra timestamps controlados em contexto CLI
        // SUPERADMIN: bypassa global scope pois não há session HTTP em console
        $apontamento = Apontamento::withoutGlobalScopes()->create([
            'business_id'      => $bizId,
            'os_id'            => $os->id,
            'orcamento_item_id' => $primeiroItem?->id,
            'operador_id'      => $userId,
            'maquina'          => 'Plotter1',
            'iniciado_em'      => $iniciouEm,
            'finalizado_em'    => $finalizouEm,
            'duracao_segundos' => $duracaoSeg,
            'm2_produzido'     => $m2Produzido,
            'm2_orcado'        => $m2Orcado,
            'drift_percent'    => $driftPercent,
            'observacoes'      => self::DEMO_MARKER . ' Apontamento exemplo — banner lona front 3×1.5m, 90min, drift=0%',
        ]);

        // ----------------------------------------------------------------
        // 10. Sumário
        // ----------------------------------------------------------------
        $this->newLine();
        $this->info('=== Demo criado com sucesso! ===');
        $this->line('');
        $this->line("  Materiais seeded    : 5 (Lona Front, Lona Back, Vinil, ACM, Plotter)");
        $this->line(sprintf(
            "  Orçamento           : %s  R$ %s  (3 itens)",
            $numOrc,
            number_format((float) $orcamento->total, 2, ',', '.')
        ));
        $this->line("  OS                  : {$numOs}  status=producao");
        $this->line(sprintf(
            "  Apontamento         : 90min  m²=%.1f  drift=%.0f%%",
            $m2Produzido,
            $driftPercent ?? 0.0
        ));
        $this->line('');
        $this->info('Próximos passos:');
        $this->line("  1. php artisan serve (ou acessar oimpresso.test no Herd)");
        $this->line("  2. Login com business_id={$bizId}");
        $this->line("  3. Visitar /admin/comunicacao-visual/orcamentos/{$orcamento->id}");
        $this->line("  4. Para limpar: php artisan comvis:demo-seed --business={$bizId} --clean");
        $this->newLine();

        return 0;
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    /**
     * Busca ou cria contato dummy "Cliente Demo CV" para o business.
     *
     * Tenta criar com campos mínimos do schema UltimatePOS. Se falhar por
     * constraint (schema variante), usa fallback para o primeiro customer disponível.
     * Nunca bloqueia o command — retorna null em último caso.
     */
    private function resolverContatoDemo(int $bizId): ?int
    {
        // Tenta encontrar contato demo existente
        $contato = DB::table('contacts')
            ->where('business_id', $bizId)
            ->where('name', 'Cliente Demo CV')
            ->whereNull('deleted_at')
            ->first();

        if ($contato) {
            return $contato->id;
        }

        // Tentar criar contato demo com campos mínimos UltimatePOS
        try {
            $contatoId = DB::table('contacts')->insertGetId([
                'business_id' => $bizId,
                'type'        => 'customer',
                'name'        => 'Cliente Demo CV',
                'contact_id'  => "CLI-DEMO-CV-{$bizId}",
                'mobile'      => '47900000000',  // mobile é NOT NULL no schema UltimatePOS
                'created_by'  => DB::table('users')->where('business_id', $bizId)->orderBy('id')->value('id') ?? 1,
                'is_default'  => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $this->line("  → Contato demo criado: 'Cliente Demo CV' (id={$contatoId})");
            return $contatoId;
        } catch (\Throwable $e) {
            // Fallback: usar primeiro customer existente no business
            $fallback = DB::table('contacts')
                ->where('business_id', $bizId)
                ->where('type', 'customer')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->value('id');

            if ($fallback) {
                $this->warn("  → Contato demo não pôde ser criado ({$e->getMessage()}). Usando contact_id={$fallback} como fallback.");
                return $fallback;
            }

            $this->warn('  → Sem contato disponível — criando orçamento sem contato_id (null).');
            return null;
        }
    }

    /**
     * Busca material por nome no business (withoutGlobalScopes — CLI).
     * Retorna o ID do material ou null se não encontrado.
     */
    private function buscarMaterial(int $bizId, string $nome): ?int
    {
        // SUPERADMIN: CLI sem session; filtra por $bizId explícito (--business validado).
        return Material::withoutGlobalScopes()
            ->where('business_id', $bizId)
            ->where('nome', $nome)
            ->value('id');
    }

    /**
     * Remove dados demo anteriores (marker [CV-DEMO] em observacoes).
     *
     * Ordem: apontamentos → itens → OS → orçamentos (respeita FK).
     */
    private function limparDemo(int $bizId): void
    {
        $this->line('  → --clean: removendo dados demo anteriores...');

        // IDs de OSes demo
        $osIds = DB::table('comvis_os')
            ->where('business_id', $bizId)
            ->where('observacoes', 'like', '%' . self::DEMO_MARKER . '%')
            ->pluck('id');

        if ($osIds->isNotEmpty()) {
            DB::table('comvis_apontamentos')
                ->whereIn('os_id', $osIds)
                ->delete();
        }

        // IDs de Orçamentos demo (DB::table ignora soft_delete — traz todos incluindo soft-deleted)
        $orcIds = DB::table('comvis_orcamentos')
            ->where('business_id', $bizId)
            ->where('observacoes', 'like', '%' . self::DEMO_MARKER . '%')
            ->pluck('id');

        if ($orcIds->isNotEmpty()) {
            DB::table('comvis_orcamento_itens')
                ->whereIn('orcamento_id', $orcIds)
                ->delete();
        }

        // Deletar OSes demo (forceDelete — sem SoftDelete no comvis_os)
        DB::table('comvis_os')
            ->where('business_id', $bizId)
            ->where('observacoes', 'like', '%' . self::DEMO_MARKER . '%')
            ->delete();

        // Deletar Orçamentos demo (hard delete ignora soft_delete — limpeza de demo)
        DB::table('comvis_orcamentos')
            ->where('business_id', $bizId)
            ->where('observacoes', 'like', '%' . self::DEMO_MARKER . '%')
            ->delete();

        $this->line('  → Dados demo anteriores removidos.');
    }
}
