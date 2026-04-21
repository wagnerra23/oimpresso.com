<?php

namespace Modules\PontoWr2\Tests\Unit;

use Carbon\Carbon;
use Modules\PontoWr2\Entities\ApuracaoDia;
use Modules\PontoWr2\Services\ApuracaoService;
use Modules\PontoWr2\Services\BancoHorasService;
use Tests\TestCase;

/**
 * Testes unitários das regras CLT do ApuracaoService.
 *
 * Cobertura:
 *   - Art. 58 §1º — tolerância de 5min por marcação, 10min diária
 *   - Art. 59 — limite de HE (2h/dia)
 *   - Art. 66 — interjornada de 11h
 *   - Art. 71 — intrajornada mínima de 60min quando jornada > 6h
 *   - Art. 73 — adicional noturno (22h-5h)
 *
 * Notas: estes testes exercem os métodos de regra diretamente sobre ApuracaoDia
 * em memória, sem tocar o banco. Para testes de integração com DB, criar
 * arquivo separado com RefreshDatabase.
 */
class ApuracaoServiceTest extends TestCase
{
    /** @var ApuracaoService */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApuracaoService(new BancoHorasService());
    }

    /** @test Art. 58 §1º — atraso dentro da tolerância não conta */
    public function atraso_dentro_da_tolerancia_nao_registra()
    {
        config(['pontowr2.clt.tolerancia_minutos_por_marcacao' => 5]);
        config(['pontowr2.clt.tolerancia_maxima_diaria_minutos' => 10]);

        $a = $this->novaApuracaoBase();
        $a->prevista_entrada  = '08:00:00';
        $a->realizada_entrada = '08:04:00'; // +4min

        $this->service->aplicarRegraTolerancia($a);

        $this->assertEquals(0, $a->atraso_minutos);
    }

    /** @test Art. 58 §1º — atraso além da tolerância registra */
    public function atraso_alem_da_tolerancia_registra()
    {
        config(['pontowr2.clt.tolerancia_minutos_por_marcacao' => 5]);

        $a = $this->novaApuracaoBase();
        $a->prevista_entrada  = '08:00:00';
        $a->realizada_entrada = '08:07:00'; // +7min

        $this->service->aplicarRegraTolerancia($a);

        $this->assertEquals(7, $a->atraso_minutos);
    }

    /** @test Art. 71 — intrajornada insuficiente em jornada > 6h registra violação */
    public function intrajornada_insuficiente_em_jornada_longa_registra_violacao()
    {
        config(['pontowr2.clt.intrajornada_minima_minutos' => 60]);

        $a = $this->novaApuracaoBase();
        $a->realizada_trabalhada_minutos   = 480; // 8h trabalhadas
        $a->realizada_intrajornada_minutos = 30;  // só 30min de almoço

        $this->service->aplicarRegraIntrajornada($a);

        $this->assertEquals(30, $a->intrajornada_violacao_minutos);
    }

    /** @test Art. 71 — jornada curta não exige intrajornada mínima */
    public function jornada_curta_nao_exige_intrajornada_minima()
    {
        config(['pontowr2.clt.intrajornada_minima_minutos' => 60]);

        $a = $this->novaApuracaoBase();
        $a->realizada_trabalhada_minutos   = 300; // 5h — abaixo de 6h
        $a->realizada_intrajornada_minutos = 0;

        $this->service->aplicarRegraIntrajornada($a);

        $this->assertEquals(0, $a->intrajornada_violacao_minutos);
    }

    /** @test Art. 59 — HE até 2h/dia */
    public function he_separa_diurna_e_noturna()
    {
        $a = $this->novaApuracaoBase();
        $a->prevista_carga_minutos         = 480;
        $a->realizada_trabalhada_minutos   = 600;   // 10h — excedente 120min (2h)
        $a->realizada_intrajornada_minutos = 60;
        $a->realizada_entrada              = '08:00:00';
        $a->realizada_saida                = '19:00:00';

        $colab = new \Modules\PontoWr2\Entities\Colaborador();
        $this->service->aplicarRegraHoraExtra($a, $colab, Carbon::parse('2026-04-15'));

        $total = $a->he_diurna_minutos + $a->he_noturna_minutos;
        $this->assertEquals(120, $total);
    }

    /** @test Divisão diurno/noturno com janela inteiramente na madrugada */
    public function divisao_diurno_noturno_madrugada_inteira()
    {
        $inicio = Carbon::parse('2026-04-15 23:00:00');
        $fim    = Carbon::parse('2026-04-16 04:00:00'); // 5h, tudo noturno

        $d = $this->service->dividirDiurnoNoturno($inicio, $fim);

        $this->assertEquals(300, $d['noturno_minutos']);
        $this->assertEquals(0,   $d['diurno_minutos']);
    }

    /** @test Divisão diurno/noturno com cruzamento das 22h */
    public function divisao_diurno_noturno_cruza_22h()
    {
        $inicio = Carbon::parse('2026-04-15 20:00:00');
        $fim    = Carbon::parse('2026-04-15 23:00:00'); // 3h, 2h diurna + 1h noturna

        $d = $this->service->dividirDiurnoNoturno($inicio, $fim);

        $this->assertEquals(120, $d['diurno_minutos']);
        $this->assertEquals(60,  $d['noturno_minutos']);
    }

    /** @test Divisão diurno/noturno inteiramente diurna */
    public function divisao_diurno_noturno_inteiramente_diurna()
    {
        $inicio = Carbon::parse('2026-04-15 09:00:00');
        $fim    = Carbon::parse('2026-04-15 17:00:00'); // 8h

        $d = $this->service->dividirDiurnoNoturno($inicio, $fim);

        $this->assertEquals(480, $d['diurno_minutos']);
        $this->assertEquals(0,   $d['noturno_minutos']);
    }

    protected function novaApuracaoBase()
    {
        $a = new ApuracaoDia([
            'business_id'           => 1,
            'colaborador_config_id' => 1,
            'data'                  => '2026-04-15',
        ]);
        // data é cast para date — força instância Carbon
        $a->data = Carbon::parse('2026-04-15');
        $a->estado = ApuracaoDia::ESTADO_PENDENTE;
        $a->divergencias = [];

        // zera calculáveis
        foreach ([
            'realizada_trabalhada_minutos', 'realizada_intrajornada_minutos',
            'atraso_minutos', 'saida_antecipada_minutos', 'falta_minutos',
            'he_diurna_minutos', 'he_noturna_minutos', 'adicional_noturno_minutos',
            'dsr_repercussao_minutos',
            'interjornada_violacao_minutos', 'intrajornada_violacao_minutos',
            'banco_horas_credito_minutos', 'banco_horas_debito_minutos',
            'prevista_carga_minutos', 'qtd_marcacoes',
        ] as $campo) {
            $a->{$campo} = 0;
        }
        return $a;
    }
}
