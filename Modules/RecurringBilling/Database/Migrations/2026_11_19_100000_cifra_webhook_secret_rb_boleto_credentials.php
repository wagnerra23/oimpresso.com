<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria RecurringBilling d4 (2026-11-19): `config_json.webhook_secret` de
 * `rb_boleto_credentials` ficava em claro (client_secret/api_key/certificado já eram cifrados).
 * Daqui em diante o valor é Crypt::encryptString e quem compara é BoletoCredential::webhookSecret().
 *
 * Idempotente: valor que já decifra fica como está, então rodar de novo não cifra duas vezes.
 * Não escreve o segredo em log nem em saída.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->percorrer(function (string $valor): ?string {
            return $this->decifra($valor) === null ? Crypt::encryptString($valor) : null;
        });
    }

    public function down(): void
    {
        $this->percorrer(function (string $valor): ?string {
            return $this->decifra($valor);
        });
    }

    /** @param callable(string): ?string $novoValor devolve null quando a linha fica como está */
    private function percorrer(callable $novoValor): void
    {
        if (! Schema::hasTable('rb_boleto_credentials')) {
            return;
        }

        DB::table('rb_boleto_credentials')->orderBy('id')->select(['id', 'config_json'])
            ->chunkById(100, function ($linhas) use ($novoValor) {
                foreach ($linhas as $linha) {
                    $config = json_decode((string) $linha->config_json, true);
                    $valor = is_array($config) ? ($config['webhook_secret'] ?? null) : null;
                    if (! is_string($valor) || $valor === '') {
                        continue;
                    }

                    $novo = $novoValor($valor);
                    if ($novo === null) {
                        continue;
                    }

                    $config['webhook_secret'] = $novo;
                    DB::table('rb_boleto_credentials')->where('id', $linha->id)
                        ->update(['config_json' => json_encode($config)]);
                }
            });
    }

    private function decifra(string $valor): ?string
    {
        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            return null;
        }
    }
};
