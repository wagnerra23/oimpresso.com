<?php

declare(strict_types=1);

namespace Modules\Ponto\Console\Commands;

use App\Business;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\Marcacao;

/**
 * ponto:demo-smoke — smoke AUTOMÁTICO da conta demo da revisão das lojas, sem senha e sem humano.
 *
 * [W] 2026-10-01: "automatize, não dependa de mim para teste". O agente não digita senha num site
 * de produção, então o smoke não passa pelo formulário de login: ele monta as requisições pela
 * pilha HTTP INTEIRA do Laravel (middlewares web/auth/SetSessionData/CheckUserLogin, rotas e
 * controllers) autenticado como `revisor.ponto` — o mesmo caminho que o navegador percorre
 * depois do login. Fica de fora só o formulário de login e o CSRF (desligado neste processo).
 *
 * Confere, no business demo (achado pelo nome, nunca 1/4/98):
 *  1. GET /ponto/mobile → Ponto/Mobile/Index, colaborador DEMO-0001, sem as abas do módulo;
 *  2. GET /ponto/colaboradores e /contacts?type=customer → não abrem (302/403/404);
 *  3. o caminho do APP pela API Passport (guard api, token emitido em processo):
 *     GET /ponto/api/marcacoes/hoje e /ponto/api/saldo → 200; /ponto/api/me traz a matrícula
 *     DEMO-0001; /ponto/api/espelho?mes=<mês atual> → 200 com as linhas, mês futuro → 422;
 *  4. POST /ponto/api/marcar com GPS da Califórnia → 201 com NSR (revisor fora do Brasil não é
 *     recusado) e a marcação aparece em hoje. Pule com --sem-marcar: marcação é append-only.
 *
 * Uso:
 *   php artisan ponto:demo-smoke               # inclui 1 marcação real no business demo
 *   php artisan ponto:demo-smoke --sem-marcar  # só leitura
 */
class DemoSmokeCommand extends Command
{
    protected $signature = 'ponto:demo-smoke
        {--sem-marcar : Não bate ponto (só confere as telas e os bloqueios)}';

    protected $description = 'Smoke automático da conta demo (revisor.ponto) pela pilha HTTP completa, sem senha.';

    public function handle(): int
    {
        $business = Business::query()->where('name', DemoRevisorCommand::BUSINESS_NOME)->first();
        if (! $business || in_array((int) $business->id, DemoRevisorCommand::BIZ_PROIBIDOS, true)) {
            $this->error('Business demo não encontrado (ou id protegido). Rode ponto:demo-revisor antes.');

            return 1;
        }
        $revisor = User::query()->where('username', DemoRevisorCommand::REVISOR_USERNAME)
            ->where('business_id', $business->id)->first();
        if (! $revisor) {
            $this->error('revisor.ponto não existe no business demo.');

            return 1;
        }

        $http = new DemoSmokeHttp(app());
        $http->entrarComo($revisor);
        $falhas = 0;
        $ok = function (bool $cond, string $msg) use (&$falhas) {
            $this->line(($cond ? '  ✓ ' : '  ✗ ') . $msg);
            $falhas += $cond ? 0 : 1;
        };

        $this->info("Smoke da conta demo — business {$business->id}, revisor user {$revisor->id}");

        // 1. A tela do ponto.
        $r = $http->get('/ponto/mobile');
        $page = self::paginaInertia((string) $r->getContent());
        $ok($r->getStatusCode() === 200, "GET /ponto/mobile → HTTP {$r->getStatusCode()}");
        $ok(($page['component'] ?? null) === 'Ponto/Mobile/Index', 'componente ' . ($page['component'] ?? '—'));
        $ok((int) ($page['props']['auth']['user']['id'] ?? 0) === (int) $revisor->id, 'usuário logado na tela = ' . ($page['props']['auth']['user']['id'] ?? '—'));
        $ok(($page['props']['colaborador']['matricula'] ?? null) === 'DEMO-0001', 'cadastro de ponto ativo (matrícula ' . ($page['props']['colaborador']['matricula'] ?? '—') . ')');
        $ok(($page['props']['pode_ver_modulo'] ?? null) === false, 'sem as abas do módulo Ponto (pode_ver_modulo=' . var_export($page['props']['pode_ver_modulo'] ?? null, true) . ')');

        // 2. O que o revisor NÃO abre.
        foreach (['/ponto/colaboradores', '/contacts?type=customer'] as $rota) {
            $s = $http->get($rota)->getStatusCode();
            $ok(in_array($s, [302, 403, 404], true), "GET {$rota} bloqueado → HTTP {$s}");
        }

        // 3. O caminho do APP (telas próprias falando com o ERP pela API Passport — [W] 2026-10-01).
        //    Token de acesso emitido em processo para o revisor, sem senha e sem client OAuth.
        $http->entrarComoApi($revisor);
        $h0 = $http->getJson('/ponto/api/marcacoes/hoje');
        $ok($h0->getStatusCode() === 200, "GET /ponto/api/marcacoes/hoje → HTTP {$h0->getStatusCode()}");
        $hoje0 = count((json_decode((string) $h0->getContent(), true) ?: [])['marcacoes'] ?? []);
        $sd = $http->getJson('/ponto/api/saldo');
        $ok($sd->getStatusCode() === 200, "GET /ponto/api/saldo → HTTP {$sd->getStatusCode()}");
        $me = $http->getJson('/ponto/api/me');
        $meDados = json_decode((string) $me->getContent(), true) ?: [];
        $ok($me->getStatusCode() === 200 && ($meDados['matricula'] ?? null) === 'DEMO-0001',
            "GET /ponto/api/me → HTTP {$me->getStatusCode()} · matrícula " . ($meDados['matricula'] ?? '—'));
        $mes = now()->format('Y-m');
        $esp = $http->getJson('/ponto/api/espelho?mes=' . $mes);
        $espDados = json_decode((string) $esp->getContent(), true) ?: [];
        $ok($esp->getStatusCode() === 200 && ($espDados['mes'] ?? null) === $mes && is_array($espDados['linhas'] ?? null),
            "GET /ponto/api/espelho?mes={$mes} → HTTP {$esp->getStatusCode()} · " . count($espDados['linhas'] ?? []) . ' linha(s)');
        $futuro = $http->getJson('/ponto/api/espelho?mes=' . now()->addMonthNoOverflow()->format('Y-m'));
        $ok($futuro->getStatusCode() === 422, "GET /ponto/api/espelho (mês futuro) recusado → HTTP {$futuro->getStatusCode()}");

        // 4. Bater ponto de fora do Brasil, pela API (é o que o app faz).
        if (! $this->option('sem-marcar')) {
            $antes = DB::table('ponto_marcacoes')->where('business_id', $business->id)->count();
            $m = $http->postJson('/ponto/api/marcar', [
                'tipo' => Marcacao::TIPO_ENTRADA, 'lat' => 37.3349, 'lng' => -122.0090, 'accuracy' => 30,
                'device_uuid' => 'demo-smoke', 'timestamp_device' => now()->toIso8601String(),
            ]);
            $ok($m->getStatusCode() === 201, "POST /ponto/api/marcar (GPS Califórnia) → HTTP {$m->getStatusCode()}");
            $dados = json_decode((string) $m->getContent(), true) ?: [];
            $ok((int) ($dados['marcacao']['nsr'] ?? 0) > 0, 'NSR do servidor = ' . ($dados['marcacao']['nsr'] ?? '—') . ' · revisar=' . var_export($dados['marcacao']['revisar'] ?? null, true));
            $depois = DB::table('ponto_marcacoes')->where('business_id', $business->id)->count();
            $ok($depois === $antes + 1, "marcações no business demo: {$antes} → {$depois}");

            $h1 = $http->getJson('/ponto/api/marcacoes/hoje');
            $hoje1 = count((json_decode((string) $h1->getContent(), true) ?: [])['marcacoes'] ?? []);
            $ok($hoje1 === $hoje0 + 1, "aparece em hoje (API): {$hoje0} → {$hoje1}");
        }

        if ($falhas > 0) {
            $this->error("FALHOU: {$falhas} verificação(ões).");

            return 1;
        }
        $this->info('OK — todas as verificações passaram.');

        return 0;
    }

    /** O `data-page` do Inertia, venha em <script data-page> (v3) ou no atributo do #app. */
    public static function paginaInertia(string $html): array
    {
        if (preg_match('#<script[^>]*data-page[^>]*>(.*?)</script>#s', $html, $mm)) {
            return json_decode(html_entity_decode($mm[1]), true) ?: [];
        }
        if (preg_match('#data-page="([^"]*)"#', $html, $mm)) {
            return json_decode(html_entity_decode($mm[1], ENT_QUOTES), true) ?: [];
        }

        return [];
    }
}
