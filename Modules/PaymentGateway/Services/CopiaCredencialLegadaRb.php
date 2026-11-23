<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Copia as credenciais que só existem em `rb_boleto_credentials` (RecurringBilling) para
 * `payment_gateway_credentials` (dono: PaymentGateway, ADR 0170 · decisão b1 de 2026-11-23).
 *
 * Usada pela migration de dados `2026_11_23_120000_copia_credenciais_rb_para_payment_gateway` e
 * pelo `paymentgateway:migrate-credentials`. Uma regra só, nos dois.
 *
 * Por que não é cópia de coluna (o que o comando antigo fazia):
 *  1. SEGREDO — o RB cifra campo a campo (`Crypt::encryptString` em `client_secret`, `api_key`,
 *     `certificado_senha`, `certificado_key_b64`, `webhook_secret`); o PG cifra o `config_json`
 *     inteiro (`encrypted:array`) e os drivers leem os campos em claro. Copiar o texto do RB punha
 *     ciphertext onde o driver espera o valor (ex.: o `api_key` do Asaas). Aqui o campo do RB é
 *     decifrado em memória e gravado pelo cast do model — nunca sai em log, retorno ou exceção.
 *  2. CONTA — `rb_boleto_credentials.conta_bancaria_id` aponta para `fin_contas_bancarias.id`, e
 *     `payment_gateway_credentials.conta_bancaria_id` aponta para `accounts.id`. E credencial ligada
 *     à conta tem precedência na escolha de quem EMITE (`PaymentGatewayService::for`). A cópia nasce
 *     SEM ligação a conta: a emissão de hoje não muda. Ligar a conta é passo do wizard.
 *
 * Idempotente: chave (business_id, gateway_key, ambiente), a mesma UNIQUE da tabela do PG. Linha que
 * já existe no PG não é sobrescrita; com `$repararExistentes` (o comando), só os campos acima que
 * ainda estiverem como ciphertext do RB (sobra de execução antiga do comando) são decifrados. A
 * migration NÃO repara: ela roda sozinha no deploy, e mexer em credencial viva fica para quem roda
 * o comando com `--apply`. Valor em claro não decifra, então rodar de
 * novo não muda nada.
 */
final class CopiaCredencialLegadaRb
{
    /** Campos que o RB grava com `Crypt::encryptString` dentro do `config_json`. */
    public const CAMPOS_CIFRADOS_NO_RB = ['client_secret', 'api_key', 'certificado_senha', 'certificado_key_b64', 'webhook_secret'];

    private const GATEWAY_DO_BANCO = ['inter' => 'inter', 'c6' => 'c6', 'asaas' => 'asaas'];

    /**
     * @return list<array{rb_id:int, business_id:int, gateway_key:?string, ambiente:string, acao:string}>
     *         acao: criar | existe | decifrar_campos | existe_com_campo_cifrado | sem_mapeamento —
     *         sem nenhum valor de segredo. `$repararExistentes=false` (a migration) só relata a linha
     *         existente com ciphertext do RB, sem gravar nela.
     */
    public function executar(?int $businessId, bool $aplicar, bool $repararExistentes = true): array
    {
        if (! Schema::hasTable('rb_boleto_credentials') || ! Schema::hasTable('payment_gateway_credentials')) {
            return [];
        }

        $query = DB::table('rb_boleto_credentials')->orderBy('id');
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $relatorio = [];
        foreach ($query->get() as $rb) {
            $gatewayKey = self::GATEWAY_DO_BANCO[(string) $rb->banco] ?? null;
            $linha = [
                'rb_id' => (int) $rb->id, 'business_id' => (int) $rb->business_id,
                'gateway_key' => $gatewayKey, 'ambiente' => (string) $rb->ambiente, 'acao' => 'sem_mapeamento',
            ];
            if ($gatewayKey === null) {
                $relatorio[] = $linha;

                continue;
            }

            // SUPERADMIN: roda em migration/CLI, sem sessão; cada busca filtra pelo business_id da linha do RB.
            $pg = PaymentGatewayCredential::withoutGlobalScopes()
                ->where('business_id', (int) $rb->business_id)
                ->where('gateway_key', $gatewayKey)
                ->where('ambiente', (string) $rb->ambiente)
                ->first();

            if ($pg === null) {
                $linha['acao'] = 'criar';
                if ($aplicar) {
                    PaymentGatewayCredential::withoutGlobalScopes()->create([
                        'business_id' => (int) $rb->business_id,
                        'gateway_key' => $gatewayKey,
                        'ambiente' => (string) $rb->ambiente,
                        'ativo' => (bool) $rb->ativo,
                        'nome_display' => $rb->nome_display,
                        'config_json' => $this->emClaro(self::decodificar($rb->config_json)),
                        'conta_bancaria_id' => null,
                        'health_status' => 'unknown',
                    ]);
                }
                $relatorio[] = $linha;

                continue;
            }

            $atual = (array) ($pg->config_json ?? []);
            $claro = $this->emClaro($atual);
            if ($claro !== $atual && ! $repararExistentes) {
                $linha['acao'] = 'existe_com_campo_cifrado';
            } elseif ($claro !== $atual) {
                $linha['acao'] = 'decifrar_campos';
                if ($aplicar) {
                    $pg->forceFill(['config_json' => $claro])->save();
                }
            } else {
                $linha['acao'] = 'existe';
            }
            $relatorio[] = $linha;
        }

        return $relatorio;
    }

    /** Decifra os campos que o RB cifra; o que não decifra (já em claro) fica como está. */
    private function emClaro(array $config): array
    {
        foreach (self::CAMPOS_CIFRADOS_NO_RB as $campo) {
            $valor = $config[$campo] ?? null;
            if (! is_string($valor) || $valor === '') {
                continue;
            }
            try {
                $config[$campo] = Crypt::decryptString($valor);
            } catch (\Throwable) {
                // não é ciphertext desta APP_KEY: valor em claro, mantém
            }
        }

        return $config;
    }

    private static function decodificar(mixed $bruto): array
    {
        if (is_array($bruto)) {
            return $bruto;
        }
        $decodificado = is_string($bruto) ? json_decode($bruto, true) : null;

        return is_array($decodificado) ? $decodificado : [];
    }
}
