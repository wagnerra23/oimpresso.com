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
 * SALDO DE BANCO DE HORAS (2026-09-28): `BancoHorasController@show` faz
 * `firstOrFail` no SALDO, não no colaborador — sem esta linha a rota
 * `/ponto/banco-horas/900001` do manifesto daria 404, o mesmo defeito do Espelho
 * acima.
 *
 * LANÇAMENTOS (2026-09-28, 2ª versão): 3 movimentos com `created_at` e
 * `data_referencia` LITERAIS. A 1ª versão deixava o ledger vazio porque a coluna
 * "Registrado" era `diffForHumans()` ("há 3 minutos") e drifaria a foto; depois
 * que a tela passou a mostrar data-hora ABSOLUTA, o histórico pode aparecer. O
 * saldo é a soma do ledger (+90 −30 +15 = 75) e cada `saldo_posterior_minutos`
 * é o acumulado — a foto não mostra saldo que o próprio ledger contradiz.
 * `id` UUID fixo: o ledger é append-only e o id não aparece, mas literal evita
 * que a ordem dependa de gerador. A paginação segue provada fora da foto
 * (Pest + vitest UC-BHSHOW-04): 3 linhas não abrem 2ª página.
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

        $this->garantirColaborador();
        $this->garantirSaldoBancoHoras();
        $this->garantirMovimentosBancoHoras();
    }

    /**
     * Lançamentos do extrato do 900001 (ver docblock "LANÇAMENTOS"), do mais antigo
     * ao mais recente. `saldo_posterior` é o acumulado; a soma fecha com o saldo.
     */
    private const MOVIMENTOS = [
        ['id' => '9a000001-0000-4000-8000-000000000001', 'created_at' => '2026-06-02 18:10:00', 'data_referencia' => '2026-06-02', 'tipo' => 'CREDITO', 'minutos' => 90,  'saldo_posterior' => 90, 'observacao' => 'Hora extra — fechamento de pedido'],
        ['id' => '9a000001-0000-4000-8000-000000000002', 'created_at' => '2026-06-09 17:45:00', 'data_referencia' => '2026-06-09', 'tipo' => 'DEBITO',  'minutos' => -30, 'saldo_posterior' => 60, 'observacao' => 'Saída antecipada compensada'],
        ['id' => '9a000001-0000-4000-8000-000000000003', 'created_at' => '2026-06-15 09:20:00', 'data_referencia' => '2026-06-12', 'tipo' => 'AJUSTE',  'minutos' => 15,  'saldo_posterior' => 75, 'observacao' => 'Ajuste manual — acordo com o colaborador'],
    ];

    /** Saldo = último `saldo_posterior` dos MOVIMENTOS. */
    private const SALDO_MINUTOS = 75;

    private function garantirColaborador(): void
    {
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

    /** Saldo do 900001 (ver docblock "SALDO DE BANCO DE HORAS"). Idempotente. */
    private function garantirSaldoBancoHoras(): void
    {
        if (! Schema::hasTable('ponto_banco_horas_saldo')) {
            return;
        }

        // Só ancora num colaborador que existe: a FK recusaria o insert e derrubaria
        // o seed compartilhado.
        if (! DB::table('ponto_colaborador_config')->where('id', self::COLABORADOR_ID)->exists()) {
            return;
        }

        if (DB::table('ponto_banco_horas_saldo')->where('colaborador_config_id', self::COLABORADOR_ID)->exists()) {
            return;
        }

        DB::table('ponto_banco_horas_saldo')->insert([
            'business_id'           => 1,
            'colaborador_config_id' => self::COLABORADOR_ID,
            'saldo_minutos'         => self::SALDO_MINUTOS,
            'ultima_movimentacao'   => '2026-06-15',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /** Idempotente: só semeia se o 900001 ainda não tem nenhum lançamento. */
    private function garantirMovimentosBancoHoras(): void
    {
        if (! Schema::hasTable('ponto_banco_horas_movimentos')) {
            return;
        }

        if (! DB::table('ponto_colaborador_config')->where('id', self::COLABORADOR_ID)->exists()) {
            return;
        }

        if (DB::table('ponto_banco_horas_movimentos')->where('colaborador_config_id', self::COLABORADOR_ID)->exists()) {
            return;
        }

        // `usuario_id` é FK NOT NULL pra users: o mesmo user do colaborador (business 1).
        $usuarioId = DB::table('ponto_colaborador_config')->where('id', self::COLABORADOR_ID)->value('user_id');

        foreach (self::MOVIMENTOS as $m) {
            DB::table('ponto_banco_horas_movimentos')->insert([
                'id'                      => $m['id'],
                'business_id'             => 1,
                'colaborador_config_id'   => self::COLABORADOR_ID,
                'data_referencia'         => $m['data_referencia'],
                'tipo'                    => $m['tipo'],
                'minutos'                 => $m['minutos'],
                'saldo_posterior_minutos' => $m['saldo_posterior'],
                'observacao'              => $m['observacao'],
                'usuario_id'              => $usuarioId,
                'created_at'              => $m['created_at'],
            ]);
        }
    }
}
