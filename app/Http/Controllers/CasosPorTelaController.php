<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Documentacao\CasosPorTela;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Documentação › Casos de uso por tela — os pedidos de clientes e o contrato de cada tela, lidos
 * dos `*.casos.md` a cada acesso (cache de 5 min). Substitui o artefato publicado à mão, que só
 * mudava quando alguém rodava `npm run casos:pagina` e republicava (ADR 0256).
 *
 * MULTI-TENANT (ADR 0093): conteúdo da PLATAFORMA, não da empresa. Nada aqui lê ou filtra por
 * business_id, e por isso o acesso é da equipe, não do dono de cada empresa: as permissões são
 * checadas com `checkPermissionTo` (Spatie direto), não com `can()`, que o `Gate::before` libera
 * para qualquer `Admin#{biz}`. Charter: resources/js/Pages/Documentacao/CasosPorTela.charter.md.
 */
class CasosPorTelaController extends Controller
{
    public const PERMISSAO_VER = 'documentacao.casos_tela.ver';

    public const PERMISSAO_CONFERIR = 'documentacao.casos_tela.conferir';

    public const PERMISSAO_DECIDIR = 'documentacao.casos_tela.decidir';

    private const CACHE = 'doc:casos-por-tela:v1';

    private const TTL = 300;

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless(self::pode($user, self::PERMISSAO_VER), 403);

        return Inertia::render('Documentacao/CasosPorTela', [
            // Varre ~350 casos.md: chega logo depois do primeiro render.
            // Um P-NNN repetido em dois casos.md é erro de DADO: a página diz qual, em vez de 500.
            'dados' => Inertia::defer(function (): array {
                try {
                    return Cache::remember(self::CACHE, self::TTL, fn () => (new CasosPorTela(base_path()))->dados());
                } catch (\RuntimeException $e) {
                    return ['erro' => $e->getMessage()];
                }
            }),
            'marcas' => Inertia::defer(fn () => $this->marcas()),
            'podeConferir' => self::pode($user, self::PERMISSAO_CONFERIR),
            'podeDecidir' => self::pode($user, self::PERMISSAO_DECIDIR),
        ]);
    }

    /** Marca ou desmarca "Conferido contra as regras" num pedido. */
    public function conferir(Request $request, string $pedido): RedirectResponse
    {
        abort_unless(self::pode($request->user(), self::PERMISSAO_CONFERIR), 403);
        abort_unless((bool) preg_match('/^P-\d{3}$/', $pedido), 404);
        $dados = $request->validate(['conferido' => ['required', 'boolean']]);

        if ($dados['conferido']) {
            DB::table('doc_casos_marcas')->upsert(
                [['tipo' => 'conferencia', 'chave' => $pedido, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]],
                ['tipo', 'chave'], ['user_id', 'updated_at'],
            );
        } else {
            DB::table('doc_casos_marcas')->where('tipo', 'conferencia')->where('chave', $pedido)->delete();
        }

        return back();
    }

    /** Registra (ou atualiza) a resposta do [W] a uma decisão D-NN. */
    public function responder(Request $request, string $decisao): RedirectResponse
    {
        abort_unless(self::pode($request->user(), self::PERMISSAO_DECIDIR), 403);
        abort_unless((bool) preg_match('/^D-\d{2}$/', $decisao), 404);
        $dados = $request->validate([
            'opcao' => ['nullable', 'string', 'max:120'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ]);
        if (trim((string) ($dados['opcao'] ?? '')) === '' && trim((string) ($dados['nota'] ?? '')) === '') {
            return back()->withErrors(['resposta' => 'Escolha uma opção ou escreva a resposta antes de registrar.']);
        }

        DB::table('doc_casos_marcas')->upsert(
            [['tipo' => 'decisao', 'chave' => $decisao, 'opcao' => $dados['opcao'] ?? null, 'nota' => trim((string) ($dados['nota'] ?? '')),
                'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]],
            ['tipo', 'chave'], ['opcao', 'nota', 'user_id', 'updated_at'],
        );

        return back();
    }

    /**
     * Quem conferiu e quem respondeu. `null` = tabela ausente (NÃO MEDIDO), diferente de "nada
     * marcado" — a tela mostra os dois de jeito diferente.
     *
     * @return array{conferencia: array<string, array{por:string, em:string}>, decisoes: array<string, array{opcao:?string, nota:?string, por:string, em:string}>}|null
     */
    private function marcas(): ?array
    {
        if (! Schema::hasTable('doc_casos_marcas')) {
            return null;
        }
        $saida = ['conferencia' => [], 'decisoes' => []];
        $linhas = DB::table('doc_casos_marcas')->leftJoin('users', 'users.id', '=', 'doc_casos_marcas.user_id')
            ->get(['doc_casos_marcas.*', 'users.first_name', 'users.surname']);
        foreach ($linhas as $l) {
            $base = ['por' => trim(($l->first_name ?? '') . ' ' . ($l->surname ?? '')) ?: 'Alguém', 'em' => (string) $l->updated_at];
            if ($l->tipo === 'conferencia') {
                $saida['conferencia'][$l->chave] = $base;
            } elseif ($l->tipo === 'decisao') {
                $saida['decisoes'][$l->chave] = $base + ['opcao' => $l->opcao, 'nota' => $l->nota];
            }
        }

        return $saida;
    }

    /** Equipe da plataforma: administrator_usernames ou a permissão de verdade (sem o bypass do Admin#{biz}). */
    public static function pode(?User $user, string $permissao): bool
    {
        if ($user === null) {
            return false;
        }
        $admins = explode(',', strtolower((string) config('constants.administrator_usernames')));
        if (in_array(strtolower((string) $user->username), array_map('trim', $admins), true)) {
            return true;
        }

        return $user->checkPermissionTo($permissao);
    }
}
