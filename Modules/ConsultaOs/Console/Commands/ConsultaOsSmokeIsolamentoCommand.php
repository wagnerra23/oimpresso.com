<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\ConsultaOs\Services\EmpresaPublica;

/**
 * consultaos:smoke-isolamento — prova em produção que o portal só vê a empresa do link.
 *
 * Semeia, em cada empresa de teste informada, uma OS com o MESMO nº (SMOKE-COS-0001) e status de
 * nome diferente por empresa, e imprime o link de cada uma. Com --limpar remove tudo que criou
 * (marcador SMOKE-COS em contato, OS e status). Decisão [W] 2026-11-17 ("empresa na URL").
 *
 * Grava pelo query builder de propósito: é fixture de smoke, não fluxo de negócio — sem evento,
 * sem aviso de WhatsApp, sem log de atividade. Recusa a empresa 4 (ROTA LIVRE, cliente real) e a 1
 * (WR2, real): ADR 0358.
 */
class ConsultaOsSmokeIsolamentoCommand extends Command
{
    private const MARCA = 'SMOKE-COS';

    private const NUMERO = 'SMOKE-COS-0001';

    private const PROIBIDAS = [1, 4];

    protected $signature = 'consultaos:smoke-isolamento
        {empresas* : ids das empresas de teste (pelo menos duas)}
        {--limpar : remove as OS, contatos e status de smoke dessas empresas}';

    protected $description = 'Semeia (ou limpa) a mesma OS em empresas de teste para o smoke de isolamento do /consulta-os.';

    public function handle(): int
    {
        $empresas = array_values(array_unique(array_map('intval', (array) $this->argument('empresas'))));

        if (array_intersect($empresas, self::PROIBIDAS) !== []) {
            $this->error('Empresas 1 e 4 são reais: use empresas de teste.');

            return self::FAILURE;
        }

        if ($this->option('limpar')) {
            foreach ($empresas as $biz) {
                $this->limpar($biz);
            }

            return self::SUCCESS;
        }

        if (count($empresas) < 2) {
            $this->error('Informe pelo menos duas empresas de teste.');

            return self::FAILURE;
        }

        foreach ($empresas as $biz) {
            if (! DB::table('business')->where('id', $biz)->exists()) {
                $this->error("Empresa {$biz} não existe.");

                return self::FAILURE;
            }
        }

        foreach ($empresas as $biz) {
            $usuario = DB::table('users')->where('business_id', $biz)->orderBy('id')->value('id');
            if ($usuario === null) {
                $this->error("Empresa {$biz} sem usuário.");

                return self::FAILURE;
            }

            $this->limpar($biz);
            $agora = now();
            $status = DB::table('repair_statuses')->insertGetId([
                'business_id' => $biz, 'name' => self::MARCA.' status da empresa '.$biz,
                'color' => '#888888', 'created_at' => $agora, 'updated_at' => $agora,
            ]);
            $contato = DB::table('contacts')->insertGetId([
                'business_id' => $biz, 'type' => 'customer', 'name' => self::MARCA,
                'mobile' => '0000000'.$biz, 'created_by' => $usuario,
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
            DB::table('repair_job_sheets')->insert([
                'business_id' => $biz, 'contact_id' => $contato, 'job_sheet_no' => self::NUMERO,
                'service_type' => 'carry_in', 'serial_no' => self::MARCA.'-'.$biz, 'status_id' => $status,
                'created_by' => $usuario, 'created_at' => $agora, 'updated_at' => $agora,
            ]);

            $link = route('consulta-os.empresa', ['empresa' => EmpresaPublica::codigoDe($biz)]);
            $this->line("empresa {$biz}: {$link} · OS ".self::NUMERO.' · série '.self::MARCA.'-'.$biz);
        }

        return self::SUCCESS;
    }

    private function limpar(int $biz): void
    {
        $contatos = DB::table('contacts')->where('business_id', $biz)->where('name', self::MARCA)->pluck('id');
        $os = DB::table('repair_job_sheets')->where('business_id', $biz)->where('job_sheet_no', self::NUMERO)
            ->whereIn('contact_id', $contatos)->delete();
        DB::table('contacts')->whereIn('id', $contatos)->delete();
        $status = DB::table('repair_statuses')->where('business_id', $biz)
            ->where('name', 'like', self::MARCA.' status%')->delete();

        $this->line("empresa {$biz}: removidas {$os} OS, {$contatos->count()} contato(s), {$status} status de smoke.");
    }
}
