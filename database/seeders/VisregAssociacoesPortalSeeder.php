<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Associada de teste com link + PIN da área da associada, para o e2e-gate (US-ASSOC-005 · ADR 0451 D6).
 *
 * POR QUE EXISTE: o PIN é sorteado e devolvido UMA vez por `PortalAssociadaService::emitir()` (o banco
 * guarda só o hash). O spec `e2e/portal-associada/acesso.spec.ts` precisa do link e do PIN em claro,
 * então este seeder emite pelo Service real e grava os dois em `storage/app/e2e/portal-associada.json`
 * (gitignored, só existe no runner). Sem esse arquivo o spec se pula.
 *
 * SE A ÁREA AINDA NÃO EXISTE (antes do merge do #10880): não faz nada e não grava o arquivo.
 *
 * PACOTE: `hasThePermissionInSubscription` lê UMA assinatura ativa (`Subscription::active_subscription`)
 * e só aceita a chave se um módulo INSTALADO a declarar em `superadmin_package`. Por isso este seeder
 * registra a instalação (`system.associacoes_version`, o mesmo par do InstallController) e acrescenta
 * `associacoes_module` ao package_details da assinatura ativa do biz=1, em vez de criar outra (uma
 * segunda assinatura esconderia o `ponto_module` do VisregPontoSubscriptionSeeder). Rode depois dele.
 *
 * DOIS LINKS: `aberto` (válido) e `vencido` (expires_at no passado, gravado direto na linha — o Service
 * não emite link vencido). Cada um numa associada própria: emitir de novo revoga o link anterior da
 * mesma associada.
 *
 * Nunca roda em produção (mesma trava dos outros seeders Visreg).
 */
class VisregAssociacoesPortalSeeder extends Seeder
{
    private const SERVICO = 'Modules\\Associacoes\\Services\\PortalAssociadaService';

    private const ARQUIVO = 'e2e/portal-associada.json';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(static::class . ': seeder de fixture NAO roda em producao (APP_ENV=production).');
        }

        if (! class_exists(self::SERVICO) || ! Schema::hasTable('assoc_portal_tokens')) {
            $this->command?->info('Area da associada ausente (aguardando #10880): sem fixture.');

            return;
        }

        if (! DB::table('business')->where('id', 1)->exists()) {
            throw new RuntimeException(static::class . ': biz=1 ausente — rode o VisregTenantSeeder antes.');
        }

        $this->ligarPacote();

        $servico = app(self::SERVICO);
        $aberta = $this->associada('Associada E2E Aberta');
        $vencida = $this->associada('Associada E2E Vencida');

        $link = $servico->emitir($aberta, 1);
        $velho = $servico->emitir($vencida, 1);
        DB::table('assoc_portal_tokens')
            ->where('business_id', 1)
            ->where('associada_id', $vencida->id)
            ->whereNull('revogado_em')
            ->update(['expires_at' => now()->subDay()]);

        $destino = storage_path('app/' . self::ARQUIVO);
        File::ensureDirectoryExists(dirname($destino));
        File::put($destino, json_encode([
            'aberto' => ['token' => $link['token'], 'pin' => $link['pin'], 'nome' => $this->nome($aberta), 'associada_id' => (int) $aberta->id],
            'vencido' => ['token' => $velho['token']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function ligarPacote(): void
    {
        if (! DB::table('system')->where('key', 'associacoes_version')->exists()) {
            DB::table('system')->insert(['key' => 'associacoes_version', 'value' => '0.1.0']);
        }

        $assinatura = DB::table('subscriptions')
            ->where('business_id', 1)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($assinatura === null) {
            throw new RuntimeException(static::class . ': biz=1 sem assinatura ativa — rode o VisregPontoSubscriptionSeeder antes.');
        }

        $detalhes = (array) json_decode((string) $assinatura->package_details, true);
        $detalhes['associacoes_module'] = '1';
        DB::table('subscriptions')->where('id', $assinatura->id)->update(['package_details' => json_encode($detalhes)]);
    }

    private function associada(string $nome): object
    {
        $associacao = DB::table('assoc_associacoes')->insertGetId([
            'business_id' => 1, 'nome' => 'Associação E2E ' . uniqid(), 'cidade' => 'Cidade E2E',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $contato = DB::table('contacts')->insertGetId([
            'business_id' => 1, 'type' => 'customer', 'name' => $nome . ' ' . uniqid(),
            'mobile' => '48999990000', 'contact_status' => 'active', 'created_by' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $modelo = 'Modules\\Associacoes\\Entities\\Associada';
        // SUPERADMIN: fixture do CI grava no biz=1 do VisregTenantSeeder.
        $associada = $modelo::withoutGlobalScopes()->create([
            'business_id' => 1, 'associacao_id' => $associacao, 'contact_id' => $contato,
            'entrou_em' => now()->subYear()->toDateString(),
        ]);

        // SUPERADMIN: recarrega com a situação default do banco (ativa).
        return $modelo::withoutGlobalScopes()->findOrFail($associada->id);
    }

    private function nome(object $associada): string
    {
        return (string) DB::table('contacts')->where('id', $associada->contact_id)->value('name');
    }
}
