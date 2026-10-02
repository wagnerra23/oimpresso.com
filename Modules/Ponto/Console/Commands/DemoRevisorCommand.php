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
 * ponto:demo-revisor — contas de DEMONSTRAÇÃO para a revisão das lojas (Apple/Google).
 *
 * Duas contas ([W] 2026-10-01 "ok duas contas, colaborador e gestor"):
 *  - `revisor.ponto` (colaborador) — papel sem permissão, abre só no ponto;
 *  - `gestor.demo` — Admin#<biz> DO business demo: ERP inteiro, só deste business.
 *
 * O app das lojas (Capacitor sobre o ERP web) abre `/ponto/mobile` numa webview
 * ([W] 2026-10-01, opção a).
 *
 * Monta, num business PRÓPRIO e isolado (nunca biz 1, 4 ou 98):
 *  - business "Demo Ponto — revisão das lojas" pelo MESMO caminho do cadastro pelo site
 *    (BusinessUtil::createNewBusiness + newBusinessDefaultResources + addLocation);
 *  - um dono técnico SEM login (allow_login=0);
 *  - o colaborador com papel `Revisor#<biz>` SEM nenhuma permissão: só alcança
 *    `/ponto/mobile`, que não pede `ponto.access` ([W] 2026-09-29);
 *  - o gestor com `Admin#<biz>` deste business (o escopo business_id o prende aqui);
 *  - cadastro de ponto (controla_ponto) do colaborador (DEMO-0001) e do gestor (DEMO-0002, para a
 *    área Ponto aparecer no app — D7) + escala seg–sex 08–12 / 13–17. Nenhuma marcação.
 *
 * Idempotente: acha o business pelo nome e as contas pelo username; rodar 2× não duplica.
 *
 * SENHAS (nunca no git, nunca no stdout):
 *  - se as envs `PONTO_DEMO_REVISOR_SENHA` / `PONTO_DEMO_GESTOR_SENHA` existirem, usa elas;
 *  - senão GERA e grava SÓ em `--senha-arquivo` (chmod 600), uma linha `<username> <senha>`.
 *    O dono move pro Vaultwarden (itens `ponto-demo-revisor` / `ponto-demo-gestor`) e apaga o arquivo.
 *  A senha só é (re)definida na criação da conta ou com `--redefinir-senha`.
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
        {--senha-arquivo= : Arquivo (chmod 600) onde gravar as senhas geradas}
        {--redefinir-senha : Troca a senha das contas que já existem}';

    protected $description = 'Cria/atualiza as contas demo (colaborador e gestor) da revisão das lojas, num business isolado.';

    public const BUSINESS_NOME = 'Demo Ponto — revisão das lojas';
    public const REVISOR_USERNAME = 'revisor.ponto';
    public const GESTOR_USERNAME = 'gestor.demo';
    public const DONO_USERNAME = 'demo.ponto.dono';
    public const ESCALA_CODIGO = 'DEMO-5X2';

    /** username => env que pode trazer a senha pronta (opção b). */
    private const CONTAS = [
        self::REVISOR_USERNAME => 'PONTO_DEMO_REVISOR_SENHA',
        self::GESTOR_USERNAME  => 'PONTO_DEMO_GESTOR_SENHA',
    ];

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

        // Para cada conta: existe? a senha nova vem de onde?
        $usuarios = [];
        $senhas = [];          // username => senha nova (só as que mudam); null = gerar
        $precisaArquivo = false;
        foreach (self::CONTAS as $username => $env) {
            $u = User::query()->where('username', $username)->first();
            if ($u && (! $business || (int) $u->business_id !== (int) $business->id)) {
                $this->error("O username {$username} já existe FORA do business demo. Abortado — não sequestro usuário alheio.");

                return 1;
            }
            $usuarios[$username] = $u;
            if (! $u || $this->option('redefinir-senha')) {
                $daEnv = (string) env($env, '');
                $senhas[$username] = $daEnv !== '' ? $daEnv : null;
                $precisaArquivo = $precisaArquivo || $daEnv === '';
            }
        }

        if ($precisaArquivo && ! $this->option('senha-arquivo') && ! $dry) {
            $this->error('Defina --senha-arquivo=<caminho> (ou as envs ' . implode('/', self::CONTAS) . '). A senha nunca sai no terminal.');

            return 1;
        }

        if ($dry) {
            $this->info('[dry-run] nada será escrito.');
            $this->line('business demo: ' . ($business ? "existe (id {$business->id})" : 'será criado'));
            foreach ($usuarios as $username => $u) {
                $origem = array_key_exists($username, $senhas) ? ($senhas[$username] === null ? 'gerada → --senha-arquivo' : 'da env') : 'mantida';
                $this->line("{$username}: " . ($u ? "existe (id {$u->id})" : 'será criado') . " · senha {$origem}");
            }
            $this->line('histórico: ' . ($this->option('com-historico') ? 'sim' : 'não'));

            return 0;
        }

        $geradas = [];
        foreach ($senhas as $username => $senha) {
            if ($senha === null) {
                $senhas[$username] = $geradas[$username] = Str::password(20, true, true, false);
            }
        }

        [$business, $revisor, $gestor, $colab] = DB::transaction(function () use ($business, $usuarios, $senhas) {
            $business ??= $this->criarBusiness();
            $bizId = (int) $business->id;
            if (in_array($bizId, self::BIZ_PROIBIDOS, true)) {
                throw new \RuntimeException("Business demo criado com id protegido {$bizId}.");
            }

            // Colaborador: papel SEM permissão — só alcança /ponto/mobile ([W] "perfil colaborador abre no ponto").
            $papelRevisor = Role::firstOrCreate(['name' => 'Revisor#' . $bizId, 'business_id' => $bizId, 'guard_name' => 'web']);
            $papelRevisor->syncPermissions([]);
            $revisor = $this->garantirUsuario($usuarios[self::REVISOR_USERNAME], self::REVISOR_USERNAME, 'Revisor', $bizId, $senhas[self::REVISOR_USERNAME] ?? null);
            $revisor->syncRoles([$papelRevisor->name]);
            $revisor->syncPermissions([]);

            // Gestor: Admin#<biz> DO BUSINESS DEMO — vê o ERP inteiro, mas só deste business (escopo business_id).
            $gestor = $this->garantirUsuario($usuarios[self::GESTOR_USERNAME], self::GESTOR_USERNAME, 'Gestor', $bizId, $senhas[self::GESTOR_USERNAME] ?? null);
            $gestor->syncRoles(['Admin#' . $bizId]);

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

            // Gestor também com cadastro de ponto (DEMO-0002): a área "Ponto" do app só aparece para
            // quem tem cadastro ativo (InicioController::perfil), e a D7 [W] pede as 7 áreas ao
            // gestor. Só o cadastro — nenhuma marcação (D8 proíbe marcação de exemplo em produção).
            Colaborador::withoutGlobalScopes() // SUPERADMIN: CLI sem sessão; filtro explícito por business_id
                ->updateOrCreate(
                    ['business_id' => $bizId, 'user_id' => $gestor->id],
                    ['matricula' => 'DEMO-0002', 'controla_ponto' => true, 'usa_banco_horas' => false,
                        'escala_atual_id' => $escala->id, 'admissao' => now()->startOfYear()->toDateString()]
                );

            return [$business, $revisor, $gestor, $colab];
        });

        if ($geradas !== []) {
            $arquivo = (string) $this->option('senha-arquivo');
            $linhas = '';
            foreach ($geradas as $username => $senha) {
                $linhas .= "{$username} {$senha}" . PHP_EOL;
            }
            file_put_contents($arquivo, $linhas);
            @chmod($arquivo, 0600);
            $this->info('Senha(s) de ' . implode(', ', array_keys($geradas)) . " gravada(s) em {$arquivo} (chmod 600). Mova para o Vaultwarden (itens ponto-demo-revisor / ponto-demo-gestor) e apague o arquivo.");
        }

        if ($this->option('com-historico')) {
            $this->semearHistorico($colab, $revisor);
        }

        $integracoes = self::integracoesExternas((int) $business->id);
        if ($integracoes !== []) {
            foreach ($integracoes as $tabela => $n) {
                $this->error("Business demo tem integração externa configurada: {$tabela} ({$n} linha(s)).");
            }
            $this->error('O gestor é Admin do business demo: com integração externa, o que o revisor fizer pode sair para fora. Remova a configuração antes de entregar a conta.');

            return 1;
        }

        $this->info("OK — business demo id {$business->id} · " . self::REVISOR_USERNAME . " (user id {$revisor->id}) · " . self::GESTOR_USERNAME . " (user id {$gestor->id}) · colaborador id {$colab->id}.");

        return 0;
    }

    /**
     * Tabelas que, com linha no business demo, ligariam o gestor a um sistema EXTERNO
     * (SEFAZ, banco/gateway, WhatsApp). Decisão [W] 2026-10-01 (opção A): o gestor é Admin do
     * business demo justamente porque nada ali sai para fora — então isto tem de ficar vazio.
     */
    public const TABELAS_INTEGRACAO = [
        'nfe_certificados',
        'payment_gateway_credentials',
        'whatsapp_business_configs',
        'whatsapp_business_phones',
    ];

    /** @return array<string,int> tabela => linhas do business (só as que têm alguma) */
    public static function integracoesExternas(int $bizId): array
    {
        $achadas = [];
        foreach (self::TABELAS_INTEGRACAO as $tabela) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($tabela)) {
                continue;
            }
            $n = DB::table($tabela)->where('business_id', $bizId)->count();
            if ($n > 0) {
                $achadas[$tabela] = $n;
            }
        }

        return $achadas;
    }

    private function garantirUsuario(?User $u, string $username, string $nome, int $bizId, ?string $senha): User
    {
        if (! $u) {
            $u = User::create_user([
                'surname' => '', 'first_name' => $nome, 'last_name' => 'Demo',
                'username' => $username, 'email' => null, 'password' => $senha, 'language' => 'pt',
            ]);
        } elseif ($senha !== null) {
            $u->password = bcrypt($senha);
        }
        $u->business_id = $bizId;
        $u->user_type = 'user';
        $u->allow_login = 1;
        $u->save();

        return $u;
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
