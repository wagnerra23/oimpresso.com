<?php

declare(strict_types=1);

namespace Modules\Ponto\Console\Commands;

use App\Business;
use App\User;
use App\Utils\BusinessUtil;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Escala;
use Modules\Ponto\Entities\EscalaTurno;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\IntercorrenciaService;
use Modules\Ponto\Services\MarcacaoService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ponto:demo-revisor — conta de DEMONSTRAÇÃO para a revisão das lojas (Apple/Google).
 *
 * O app das lojas abre `/ponto/mobile` numa webview ([W] 2026-10-01, opção a). O revisor
 * precisa de um login do ERP com cadastro de ponto ativo — e NADA além disso.
 *
 * Monta, num business PRÓPRIO e isolado (nunca biz 1, 4 ou 98):
 *  - business "Demo Ponto — revisão das lojas" pelo MESMO caminho do cadastro pelo site
 *    (BusinessUtil::createNewBusiness + newBusinessDefaultResources + addLocation);
 *  - um dono técnico SEM login (allow_login=0) — o Admin#<biz> fica com ele, não com o revisor;
 *  - o usuário revisor com papel `Revisor#<biz>` SEM nenhuma permissão: só alcança
 *    `/ponto/mobile`, que não pede `ponto.access` ([W] 2026-09-29);
 *  - cadastro de ponto (controla_ponto) + escala seg–sex 08–12 / 13–17.
 *
 * Idempotente: acha o business pelo nome e o revisor pelo username; rodar 2× não duplica.
 *
 * SENHA (nunca no git, nunca no stdout):
 *  - se a env `PONTO_DEMO_REVISOR_SENHA` existir, usa ela;
 *  - senão GERA uma e grava SÓ em `--senha-arquivo` (chmod 600). O dono move pro Vaultwarden
 *    (item `ponto-demo-revisor`) e apaga o arquivo.
 *  A senha só é (re)definida na criação do revisor ou com `--redefinir-senha`.
 *
 * `--com-historico` (só fora de produção — [W] 2026-10-01 "marcações só no staging"): marcações
 * dos dias úteis do mês, batidas de hoje e 1 justificativa PENDENTE, para as screenshots.
 * Em produção é recusado: marcação é append-only por lei (Portaria 671/2021) — não sai mais.
 *
 * Multi-tenant Tier 0 (ADR 0093): toda escrita leva business_id explícito; CLI não tem sessão.
 *
 * Uso:
 *   php artisan ponto:demo-revisor --dry-run
 *   php artisan ponto:demo-revisor --senha-arquivo=/home/<conta>/ponto-demo-revisor.txt
 *   php artisan ponto:demo-revisor --com-historico --senha-arquivo=...   # staging
 *
 * @see memory/requisitos/Ponto/RUNBOOK-mobile.md
 * @see memory/requisitos/Ponto/REVISAO-LOJAS-NOTAS.md
 */
class DemoRevisorCommand extends Command
{
    protected $signature = 'ponto:demo-revisor
        {--dry-run : Mostra o que seria feito, sem escrever}
        {--com-historico : Marcações do mês + hoje + 1 justificativa pendente (recusado em produção)}
        {--senha-arquivo= : Arquivo (chmod 600) onde gravar a senha gerada}
        {--redefinir-senha : Troca a senha de um revisor que já existe}';

    protected $description = 'Cria/atualiza a conta demo do REP-P (/ponto/mobile) para a revisão das lojas, num business isolado.';

    public const BUSINESS_NOME = 'Demo Ponto — revisão das lojas';
    public const REVISOR_USERNAME = 'revisor.ponto';
    public const DONO_USERNAME = 'demo.ponto.dono';
    public const ESCALA_CODIGO = 'DEMO-5X2';

    /** Tenants que este comando NUNCA toca: 1 (empresa), 4 (ROTA LIVRE, cliente real), 98 (testes). */
    public const BIZ_PROIBIDOS = [1, 4, 98];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($this->option('com-historico') && app()->isProduction()) {
            $this->error('--com-historico é recusado em produção: marcação é append-only e ficaria para sempre ([W] 2026-10-01 "marcações só no staging").');

            return 1;
        }

        $business = Business::query()->where('name', self::BUSINESS_NOME)->first();
        if ($business && in_array((int) $business->id, self::BIZ_PROIBIDOS, true)) {
            $this->error("Business demo resolveu para id {$business->id}, que é protegido. Abortado.");

            return 1;
        }

        $revisor = User::query()->where('username', self::REVISOR_USERNAME)->first();
        if ($revisor && (! $business || (int) $revisor->business_id !== (int) $business->id)) {
            $this->error('O username ' . self::REVISOR_USERNAME . ' já existe FORA do business demo. Abortado — não sequestro usuário alheio.');

            return 1;
        }

        $vaiGerarSenha = ! $revisor || $this->option('redefinir-senha');
        $senhaEnv = (string) env('PONTO_DEMO_REVISOR_SENHA', '');
        if ($vaiGerarSenha && $senhaEnv === '' && ! $this->option('senha-arquivo') && ! $dry) {
            $this->error('Defina --senha-arquivo=<caminho> (ou a env PONTO_DEMO_REVISOR_SENHA). A senha nunca sai no terminal.');

            return 1;
        }

        if ($dry) {
            $this->info('[dry-run] nada será escrito.');
            $this->line('business demo: ' . ($business ? "existe (id {$business->id})" : 'será criado'));
            $this->line('revisor ' . self::REVISOR_USERNAME . ': ' . ($revisor ? "existe (id {$revisor->id})" : 'será criado'));
            $this->line('senha: ' . ($vaiGerarSenha ? ($senhaEnv !== '' ? 'da env PONTO_DEMO_REVISOR_SENHA' : 'gerada → --senha-arquivo') : 'mantida'));
            $this->line('histórico: ' . ($this->option('com-historico') ? 'sim' : 'não'));

            return 0;
        }

        $senha = null;
        if ($vaiGerarSenha) {
            $senha = $senhaEnv !== '' ? $senhaEnv : Str::password(20, true, true, false);
        }

        [$business, $revisor, $colab] = DB::transaction(function () use ($business, $revisor, $senha) {
            $business ??= $this->criarBusiness();
            $bizId = (int) $business->id;
            if (in_array($bizId, self::BIZ_PROIBIDOS, true)) {
                throw new \RuntimeException("Business demo criado com id protegido {$bizId}.");
            }

            $role = Role::firstOrCreate(['name' => 'Revisor#' . $bizId, 'business_id' => $bizId, 'guard_name' => 'web']);
            $role->syncPermissions([]);

            if (! $revisor) {
                $revisor = User::create_user([
                    'surname' => '', 'first_name' => 'Revisor', 'last_name' => 'Demo',
                    'username' => self::REVISOR_USERNAME, 'email' => null,
                    'password' => $senha, 'language' => 'pt',
                ]);
            } elseif ($senha !== null) {
                $revisor->password = bcrypt($senha);
            }
            $revisor->business_id = $bizId;
            $revisor->user_type = 'user';
            $revisor->allow_login = 1;
            $revisor->save();
            $revisor->syncRoles([$role->name]);
            $revisor->syncPermissions([]);

            $escala = Escala::withoutGlobalScopes() // SUPERADMIN: CLI sem sessão; filtro explícito por business_id
                ->firstOrCreate(
                    ['business_id' => $bizId, 'codigo' => self::ESCALA_CODIGO],
                    ['nome' => 'Comercial seg–sex', 'tipo' => Escala::TIPO_FIXA, 'carga_diaria_minutos' => 480,
                        'carga_semanal_minutos' => 2400, 'permite_banco_horas' => false, 'ativo' => true]
                );
            foreach ([1, 2, 3, 4, 5] as $dia) { // Carbon::dayOfWeek (dom=0)
                EscalaTurno::firstOrCreate(
                    ['escala_id' => $escala->id, 'dia_semana' => $dia],
                    ['hora_entrada' => '08:00', 'hora_almoco_inicio' => '12:00', 'hora_almoco_fim' => '13:00', 'hora_saida' => '17:00']
                );
            }

            $colab = Colaborador::withoutGlobalScopes() // SUPERADMIN: CLI sem sessão; filtro explícito por business_id
                ->updateOrCreate(
                    ['business_id' => $bizId, 'user_id' => $revisor->id],
                    ['matricula' => 'DEMO-0001', 'controla_ponto' => true, 'usa_banco_horas' => false,
                        'escala_atual_id' => $escala->id, 'admissao' => now()->startOfYear()->toDateString()]
                );

            return [$business, $revisor, $colab];
        });

        if ($senha !== null && $senhaEnv === '') {
            $arquivo = (string) $this->option('senha-arquivo');
            file_put_contents($arquivo, $senha . PHP_EOL);
            @chmod($arquivo, 0600);
            $this->info("Senha gravada em {$arquivo} (chmod 600). Mova para o Vaultwarden (item ponto-demo-revisor) e apague o arquivo.");
        }

        if ($this->option('com-historico')) {
            $this->semearHistorico($colab, $revisor);
        }

        $this->info("OK — business demo id {$business->id} · revisor " . self::REVISOR_USERNAME . " (user id {$revisor->id}) · colaborador id {$colab->id}.");

        return 0;
    }

    private function criarBusiness(): Business
    {
        /** @var BusinessUtil $util */
        $util = app(BusinessUtil::class);

        $dono = User::create_user([
            'surname' => '', 'first_name' => 'Dono', 'last_name' => 'Demo Ponto',
            'username' => self::DONO_USERNAME, 'email' => null,
            'password' => Str::password(40), 'language' => 'pt',
        ]);
        $dono->allow_login = 0; // ninguém entra como dono: o Admin#<biz> não fica acessível
        $dono->save();

        $currencyId = DB::table('currencies')->where('code', 'BRL')->value('id')
            ?? DB::table('currencies')->value('id');

        $business = $util->createNewBusiness([
            'name' => self::BUSINESS_NOME, 'currency_id' => $currencyId, 'owner_id' => $dono->id,
            'time_zone' => 'America/Sao_Paulo', 'fy_start_month' => 1, 'accounting_method' => 'fifo',
            'start_date' => now()->toDateString(), 'enabled_modules' => [],
        ]);
        $dono->business_id = $business->id;
        $dono->save();

        $util->newBusinessDefaultResources($business->id, $dono->id);
        $local = $util->addLocation($business->id, [
            'name' => 'Loja Demo', 'landmark' => '', 'city' => 'Demo', 'state' => 'SC',
            'zip_code' => '00000-000', 'country' => 'Brasil',
        ]);
        Permission::firstOrCreate(['name' => 'location.' . $local->id, 'guard_name' => 'web']);

        return $business;
    }

    /** Só fora de produção. Idempotente por dia (pula dia que já tem marcação) e pela pendência. */
    private function semearHistorico(Colaborador $colab, User $revisor): void
    {
        $marcacoes = app(MarcacaoService::class);
        $bizId = (int) $colab->business_id;
        $agora = now();
        $grade = [
            [Marcacao::TIPO_ENTRADA, '08:0'], [Marcacao::TIPO_ALMOCO_INICIO, '12:0'],
            [Marcacao::TIPO_ALMOCO_FIM, '13:0'], [Marcacao::TIPO_SAIDA, '17:0'],
        ];

        $criadas = 0;
        for ($dia = $agora->copy()->startOfMonth(); $dia->lte($agora); $dia->addDay()) {
            if ($dia->isWeekend()) {
                continue;
            }
            $jaTem = Marcacao::withoutGlobalScopes() // SUPERADMIN: CLI sem sessão; filtro explícito
                ->where('business_id', $bizId)->where('colaborador_config_id', $colab->id)
                ->whereDate('momento', $dia->toDateString())->exists();
            if ($jaTem) {
                continue;
            }
            $hoje = $dia->isSameDay($agora);
            foreach ($grade as $i => [$tipo, $hora]) {
                if ($hoje && $i >= 3) {
                    break; // hoje: no máximo 3 batidas (entrada, almoço, retorno)
                }
                $momento = Carbon::parse($dia->toDateString() . ' ' . $hora . random_int(0, 9), $agora->timezone);
                if ($momento->gt($agora)) {
                    break;
                }
                $marcacoes->registrar([
                    'business_id' => $bizId, 'colaborador_config_id' => $colab->id, 'momento' => $momento,
                    'origem' => Marcacao::ORIGEM_MANUAL, 'tipo' => $tipo,
                    'usuario_criador_id' => $revisor->id, 'dispositivo_id' => 'demo:historico',
                ]);
                $criadas++;
            }
        }

        $temPendente = Intercorrencia::withoutGlobalScopes() // SUPERADMIN: CLI sem sessão; filtro explícito
            ->where('business_id', $bizId)->where('colaborador_config_id', $colab->id)
            ->where('estado', Intercorrencia::ESTADO_PENDENTE)->exists();
        if (! $temPendente) {
            $svc = app(IntercorrenciaService::class);
            $i = $svc->criar([
                'business_id' => $bizId, 'colaborador_config_id' => $colab->id,
                'tipo' => 'ESQUECIMENTO_MARCACAO', 'data' => $agora->copy()->subWeekday()->toDateString(),
                'dia_todo' => false, 'intervalo_inicio' => '17:00', 'intervalo_fim' => '17:10',
                'justificativa' => 'Esqueci de bater a saída; saí às 17h depois de fechar o caixa.',
                'prioridade' => 'NORMAL', 'impacta_apuracao' => true,
            ], (int) $revisor->id);
            $svc->submeter($i);
        }

        $this->info("Histórico: {$criadas} marcação(ões) nova(s); justificativa pendente " . ($temPendente ? 'já existia' : 'criada') . '.');
    }
}
