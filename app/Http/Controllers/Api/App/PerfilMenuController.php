<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Perfil de menu do app das lojas (tela 30). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.3.
 * Decisão [W]: a escolha fica guardada NO ERP (tabela app_menu_preferencias, ADR 0426).
 *
 * A barra de baixo tem Início e Mais fixos; no meio, até 3 módulos (só os que têm tela de aba no
 * app). O usuário escolhe quais e em que ordem; o Início devolve `barra` = escolha ∩ `areas` (perdeu acesso a um módulo → ele some
 * da barra sem apagar a escolha). Sem escolha, ou se a interseção ficar vazia, vale o padrão do
 * ERP: Tarefas, Pedidos e Produção (§7.1), completado com as outras áreas do usuário.
 *
 * Tier 0 (ADR 0093): business_id e user_id do token em toda leitura e escrita.
 */
class PerfilMenuController extends Controller
{
    public const MAXIMO = 3;

    /** Ordem do padrão do ERP (§7.1); o resto das áreas entra depois, na ordem de `areas`. */
    private const PADRAO = ['tarefas', 'pedidos', 'producao'];

    /**
     * Módulos que têm tela de aba no app (lista da sessão do app, 2026-10-02). Equipe, validar
     * ponto e assistente ficam no Mais por enquanto. Início e Mais são fixos e nunca entram.
     */
    private const COM_TELA = [
        'tarefas', 'pedidos', 'producao', 'pessoas', 'orcamentos', 'produtos', 'estoque',
        'financeiro', 'fiscal', 'relatorios', 'dashboard', 'ponto',
    ];

    /**
     * PUT /api/app/perfil-menu { modulos:[≤3, em ordem] } → 200 { modulos, barra }.
     * `modulos: []` apaga a escolha e volta ao padrão.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $areas = app(InicioController::class)->areasPara($user);
        $escolhiveis = $this->escolhiveis($areas);

        $modulos = $request->input('modulos');
        $erro = null;
        if (! is_array($modulos) || ! array_is_list($modulos)) {
            $erro = 'Envie a lista de módulos.';
        } elseif (count($modulos) > self::MAXIMO) {
            $erro = 'Escolha no máximo ' . self::MAXIMO . ' módulos.';
        } elseif (count(array_unique(array_map('strval', $modulos))) !== count($modulos)) {
            $erro = 'Módulo repetido.';
        } else {
            $fora = array_values(array_diff(array_map('strval', $modulos), $escolhiveis));
            if ($fora !== []) {
                $erro = 'Módulo indisponível para você: ' . implode(', ', $fora) . '.';
            }
        }
        if ($erro !== null) {
            return response()->json([
                'erro' => 'validacao',
                'mensagem' => $erro,
                'campos' => ['modulos' => $erro],
            ], 422);
        }

        $modulos = array_map('strval', $modulos);
        $chave = ['business_id' => (int) $user->business_id, 'user_id' => (int) $user->id];
        if ($modulos === []) {
            DB::table('app_menu_preferencias')->where($chave)->delete();
        } elseif (DB::table('app_menu_preferencias')->where($chave)->exists()) {
            DB::table('app_menu_preferencias')->where($chave)->update(['modulos' => json_encode($modulos), 'updated_at' => now()]);
        } else {
            DB::table('app_menu_preferencias')->insert($chave + [
                'modulos' => json_encode($modulos), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return response()->json(['modulos' => $modulos, 'barra' => $this->barraPara($user, $areas)]);
    }

    /**
     * Os módulos do meio da barra para este usuário (usado pelo Início).
     *
     * @param  list<string>  $areas
     * @return list<string>
     */
    public function barraPara(User $user, array $areas): array
    {
        $escolhiveis = $this->escolhiveis($areas);

        $salvo = DB::table('app_menu_preferencias')
            ->where('business_id', (int) $user->business_id)
            ->where('user_id', (int) $user->id)
            ->value('modulos');
        $escolha = is_string($salvo) ? (array) json_decode($salvo, true) : [];
        $barra = array_values(array_filter($escolha, fn ($m) => in_array($m, $escolhiveis, true)));
        if ($barra !== []) {
            return array_slice($barra, 0, self::MAXIMO);
        }

        $padrao = array_values(array_intersect(self::PADRAO, $escolhiveis));
        $resto = array_values(array_diff($escolhiveis, $padrao));

        return array_slice(array_merge($padrao, $resto), 0, self::MAXIMO);
    }

    /**
     * @param  list<string>  $areas
     * @return list<string>
     */
    private function escolhiveis(array $areas): array
    {
        return array_values(array_intersect($areas, self::COM_TELA));
    }
}
