<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture mínima e determinística do Ponto para o gate visual (ADR 0108).
 *
 * POR QUE EXISTE: o `visual-regression.yml` NÃO usa a action `pest-mysql-setup`
 * (essa serve as lanes de Pest e semeia `ponto_colaborador_config`). Ele roda
 * `db:seed` + os seeders `Visreg*`, e nenhum deles tocava o Ponto — a tabela
 * ficava vazia, `EspelhoController@show` fazia `findOrFail` e devolvia 404.
 * Medido no run 33107869086: `Expected response status code [200] but received
 * 404` em `Ponto/Espelho/Show`.
 *
 * ID EXPLÍCITO (900001), não auto-increment: a rota do manifesto precisa do id
 * no path (`/ponto/espelho/{colaborador}`) e derivar "vai ser 1 porque a tabela
 * está vazia" é inferência, não contrato — foi exatamente o que produziu o 404
 * acima. Com o id fixo, manifesto e fixture concordam por construção. A faixa
 * 900k não colide com dado real.
 *
 * ESTABILIDADE DA BASELINE: o `EspelhoController` usa `$request->input('mes',
 * now()->format('Y-m'))`. `Carbon::setTestNow` do teste NÃO alcança o processo
 * do browser (mesma pegadinha documentada no VisregComprasFlowSeeder), então o
 * mês default seria o do runner e a baseline quebraria na virada de mês. Por
 * isso o manifesto fixa `?mes=2026-06` na rota — o seeder não tem como resolver
 * isso sozinho.
 *
 * SEM MARCAÇÕES, DE PROPÓSITO: o espelho monta linha para todos os dias do mês
 * mesmo sem marcação (é o próprio UC-ESPSH-02). Uma fixture de marcação/apuração
 * agregaria superfície e risco sem mudar o que a baseline precisa fotografar —
 * o layout. Se um dia a baseline precisar exercitar divergência (UC-ESPSH-01),
 * isso é fixture nova e decisão própria, não um `if` a mais aqui.
 *
 * IDEMPOTENTE: guarda por id e por user_id (que é UNIQUE no schema — um insert
 * cego quebraria o seed inteiro na segunda execução, e com ele as lanes que o
 * compartilham).
 *
 * @see tests/Browser/visreg-screens.json (contrato — rota e âncora)
 * @see tests/Browser/CoreScreens/PixelBaselineTest.php
 * @see Modules/Ponto/Http/Controllers/EspelhoController.php::show
 */
class VisregPontoSeeder extends Seeder
{
    /** Id fixo do colaborador — o manifesto cita este número na rota. */
    public const COLABORADOR_ID = 900001;

    public function run(): void
    {
        // A tabela só existe depois das migrations do Modules/Ponto. Sem o guard,
        // um ambiente sem o módulo quebraria o seed compartilhado.
        if (! Schema::hasTable('ponto_colaborador_config')) {
            return;
        }

        if (DB::table('ponto_colaborador_config')->where('id', self::COLABORADOR_ID)->exists()) {
            return;
        }

        $userId = DB::table('users')->where('business_id', 1)->orderBy('id')->value('id');

        if (! $userId) {
            return; // sem tenant semeado não há o que ancorar; o teste já falha alto.
        }

        // `user_id` é UNIQUE: se o user já tem config (outro seeder, outra lane),
        // não há colaborador novo a criar e o id do manifesto não existiria.
        // Falhar alto aqui é melhor que gerar baseline de uma tela 404.
        if (DB::table('ponto_colaborador_config')->where('user_id', $userId)->exists()) {
            return;
        }

        DB::table('ponto_colaborador_config')->insert([
            'id'              => self::COLABORADOR_ID,
            'business_id'     => 1,
            'user_id'         => $userId,
            'matricula'       => 'VISREG-001',
            'cpf'             => '00000000191',
            'pis'             => '12345678919',
            'controla_ponto'  => true,
            'usa_banco_horas' => false,
            'admissao'        => '2026-01-05', // literal fixo: data relativa drifaria a baseline
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
