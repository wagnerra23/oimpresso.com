<?php

namespace Modules\PontoWr2\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Escala;
use Modules\PontoWr2\Entities\EscalaTurno;
use Modules\PontoWr2\Entities\Rep;

/**
 * Seeder de desenvolvimento — cria uma escala "44h", 5 turnos (seg-sex 08:00-17:00 c/ almoço),
 * um REP-P fake e vincula os primeiros 2 users com business_id=1 como colaboradores
 * com controla_ponto=true e escala_atual_id apontando para a escala criada.
 *
 * Idempotente: detecta existência por código/identificador e pula se já rodou.
 * NÃO rodar em produção.
 */
class DevPontoSeeder extends Seeder
{
    public function run()
    {
        $businessId = 1;

        if (app()->environment('production')) {
            $this->command->warn('DevPontoSeeder pulado em produção.');
            return;
        }

        // 1) Escala
        $escala = Escala::firstOrCreate(
            ['business_id' => $businessId, 'codigo' => 'DEV-44H'],
            [
                'nome'                  => '44h semanais — seg a sex',
                'tipo'                  => Escala::TIPO_FIXA,
                'carga_diaria_minutos'  => 480,
                'carga_semanal_minutos' => 2640,
                'permite_banco_horas'   => true,
                'ativo'                 => true,
            ]
        );

        // 2) Turnos de seg (1) a sex (5) — Carbon::dayOfWeek: dom=0..sab=6
        for ($diaSemana = 1; $diaSemana <= 5; $diaSemana++) {
            EscalaTurno::firstOrCreate(
                ['escala_id' => $escala->id, 'dia_semana' => $diaSemana],
                [
                    'hora_entrada'       => '08:00:00',
                    'hora_almoco_inicio' => '12:00:00',
                    'hora_almoco_fim'    => '13:00:00',
                    'hora_saida'         => '17:00:00',
                ]
            );
        }

        // 3) REP-P fake
        Rep::firstOrCreate(
            ['business_id' => $businessId, 'identificador' => 'DEV0000000000001'],
            [
                'tipo'        => Rep::TIPO_REP_P,
                'descricao'   => 'REP-P fake de desenvolvimento',
                'local'       => 'Escritório matriz (dev)',
                'cnpj'        => null,
                'ultimo_nsr'  => 0,
                'ativo'       => true,
            ]
        );

        // 4) Vincula até 2 users existentes como colaboradores
        $users = DB::table('users')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id');

        $i = 0;
        foreach ($users as $userId) {
            $i++;
            Colaborador::firstOrCreate(
                ['business_id' => $businessId, 'user_id' => $userId],
                [
                    'matricula'       => 'DEV-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                    'pis'             => str_pad((string) (10000000000 + (int) $userId), 12, '0', STR_PAD_LEFT),
                    'cpf'             => null,
                    'escala_atual_id' => $escala->id,
                    'controla_ponto'  => true,
                    'usa_banco_horas' => true,
                    'admissao'        => now()->subYear()->toDateString(),
                ]
            );
        }

        $this->command->info('DevPontoSeeder: escala DEV-44H + 5 turnos + REP-P fake + ' . $users->count() . ' colaborador(es) prontos.');
    }
}
