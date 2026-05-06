<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;
use Throwable;

/**
 * ADR 0090 — Fase 1 da migração legada → NfeBrasil.
 *
 * Lê `business.certificado` (BLOB) + `business.senha_certificado` (base64) e
 * grava em `nfe_certificados` com encryption real (Crypt::encrypt do .pfx +
 * Crypt::encryptString da senha).
 *
 * Idempotente: se já existe row ativo em `nfe_certificados` para o business,
 * não cria duplicado (a menos que `--force` seja passado pra sobrescrever).
 *
 * Uso:
 *   php artisan nfe:migrate-cert-business 4               # migra apenas biz=4
 *   php artisan nfe:migrate-cert-business --all           # migra todos com cert legado
 *   php artisan nfe:migrate-cert-business --all --dry-run # mostra plano sem escrever
 *   php artisan nfe:migrate-cert-business 4 --force       # sobrescreve (desativa anterior)
 */
class MigrateCertFromBusiness extends Command
{
    protected $signature = 'nfe:migrate-cert-business
                            {business? : ID do business específico (omite com --all)}
                            {--all : Migra todos os businesses com cert legado}
                            {--force : Sobrescreve cert ativo existente}
                            {--dry-run : Mostra plano sem escrever}';

    protected $description = 'Migra certificado legado de business.certificado pra nfe_certificados (ADR 0090)';

    public function handle(CertificadoService $service): int
    {
        $bizId = $this->argument('business');
        $all   = (bool) $this->option('all');
        $force = (bool) $this->option('force');
        $dry   = (bool) $this->option('dry-run');

        if (! $all && ! $bizId) {
            $this->error('Informe business_id ou use --all.');
            return self::FAILURE;
        }

        $query = DB::table('business')
            ->whereNotNull('certificado')
            ->whereNotNull('senha_certificado')
            ->where('certificado', '!=', '')
            ->where('senha_certificado', '!=', '');

        if (! $all) {
            $query->where('id', (int) $bizId);
        }

        $businesses = $query->select('id', 'name', 'certificado', 'senha_certificado')->get();

        if ($businesses->isEmpty()) {
            $this->info('Nenhum business com cert legado encontrado.');
            return self::SUCCESS;
        }

        $this->line(sprintf('Encontrados <info>%d</info> business(es) com cert legado.', $businesses->count()));
        if ($dry) $this->warn('🟡 DRY-RUN — nada será escrito');

        $migrados = 0; $pulados = 0; $erros = 0;

        foreach ($businesses as $biz) {
            $this->line('');
            $this->line(sprintf('→ business %d (%s)', $biz->id, $biz->name));

            $existente = NfeCertificado::where('business_id', $biz->id)
                ->where('ativo', true)
                ->first();

            if ($existente && ! $force) {
                $this->warn("  ⏭  já tem cert ativo em nfe_certificados ({$existente->cnpj_titular}) — use --force pra sobrescrever");
                $pulados++;
                continue;
            }

            try {
                $senhaPlain = base64_decode((string) $biz->senha_certificado, true);
                if ($senhaPlain === false) {
                    $senhaPlain = (string) $biz->senha_certificado;
                }

                // Valida o .pfx do legado antes de gravar
                $pfxBase64 = base64_encode((string) $biz->certificado);
                $meta = $service->validar($pfxBase64, $senhaPlain);

                $this->line("  ✔ cert válido — CNPJ {$meta['cnpj_titular']} · vence {$meta['valido_ate']->format('Y-m-d')}");

                if ($dry) {
                    $migrados++;
                    continue;
                }

                if ($existente) {
                    $existente->update(['ativo' => false]);
                    $this->line("  ⏏ cert ativo anterior desativado");
                }

                $uuid = (string) Str::uuid();
                $diskPath = sprintf('nfe-brasil/%d/cert/%s.pfx.enc', $biz->id, $uuid);
                Storage::put($diskPath, Crypt::encrypt((string) $biz->certificado));

                NfeCertificado::create([
                    'business_id'        => (int) $biz->id,
                    'uuid'               => $uuid,
                    'cnpj_titular'       => $meta['cnpj_titular'],
                    'valido_ate'         => $meta['valido_ate']->format('Y-m-d'),
                    'encrypted_password' => Crypt::encryptString($senhaPlain),
                    'ativo'              => true,
                ]);

                $migrados++;
                $this->info("  ✅ migrado pra nfe_certificados (encrypted)");
            } catch (Throwable $e) {
                $erros++;
                $this->error("  ❌ erro: " . $e->getMessage());
            }
        }

        $this->line('');
        $this->line(sprintf(
            '<info>Resumo:</info> %d migrado(s) · %d pulado(s) · %d erro(s)%s',
            $migrados, $pulados, $erros, $dry ? ' (dry-run)' : '',
        ));

        if ($migrados > 0 && ! $dry) {
            $this->line('');
            $this->line('<comment>⚠️  Próximo passo:</comment> testar emissão NFe via Modules/NfeBrasil — o legado business.certificado fica como backup até Fase 4 da ADR 0090.');
        }

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }
}
