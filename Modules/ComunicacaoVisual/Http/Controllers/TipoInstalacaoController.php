<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo;

/**
 * US-COMVIS-007 · UC-PCV-25 — cadastro dos tipos de instalação e dos itens que o instalador marca no celular.
 *
 *   GET  /comunicacao-visual/api/tipos-instalacao        → { tipos: [{id, nome, exige_nr35, itens, ativo}] }
 *   POST /comunicacao-visual/api/tipos-instalacao        { nome, exige_nr35?, itens? }   → 201 { tipo }
 *   PUT  /comunicacao-visual/api/tipos-instalacao/{id}   { nome?, exige_nr35?, itens?, ativo? } → { tipo }
 *
 * Grava quem cuida do catálogo (`comvis.material.manage`) ou superadmin. Lê também quem agenda
 * (`comvis.os.update_status`) ou vê OS (`comvis.os.view`), porque o agendamento escolhe o tipo.
 * Não apaga: tipo que não serve mais é desativado (`ativo: false`), e as instalações que já o usam ficam
 * como estão. Preço do catálogo não passa por aqui.
 *
 * Tier 0 (ADR 0093): `business_id` da sessão, explícito em toda consulta; tipo de outra empresa é 404.
 */
class TipoInstalacaoController extends Controller
{
    public const MAX_ITENS = 20;

    public function index(): JsonResponse
    {
        $biz = $this->empresa(gravar: false);
        $tipos = InstalacaoCatalogo::query()
            ->where('cv_instalacoes_catalogo.business_id', $biz)
            ->orderByDesc('ativo')->orderBy('nome')
            ->get(['id', 'nome', 'exige_nr35', 'itens_verificacao_json', 'ativo']);

        return response()->json(['tipos' => $tipos->map(fn (InstalacaoCatalogo $t) => $this->formato($t))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $biz = $this->empresa(gravar: true);
        $dados = $this->validar($request, $biz, null);
        $tipo = InstalacaoCatalogo::create([
            'business_id' => $biz,
            'nome' => $dados['nome'],
            'exige_nr35' => (bool) ($dados['exige_nr35'] ?? false),
            'itens_verificacao_json' => $dados['itens'] ?? [],
            'ativo' => true,
        ]);

        return response()->json(['tipo' => $this->formato($tipo)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa(gravar: true);
        $tipo = InstalacaoCatalogo::query()->where('cv_instalacoes_catalogo.business_id', $biz)->find($id)
            ?? abort(404, 'Tipo de instalação não encontrado.');
        $dados = $this->validar($request, $biz, $tipo->id);

        $mudancas = [];
        foreach (['nome' => 'nome', 'exige_nr35' => 'exige_nr35', 'ativo' => 'ativo', 'itens' => 'itens_verificacao_json'] as $campo => $coluna) {
            if (array_key_exists($campo, $dados)) {
                $mudancas[$coluna] = $dados[$campo];
            }
        }
        $tipo->update($mudancas);

        return response()->json(['tipo' => $this->formato($tipo->refresh())]);
    }

    /** @return array<string, mixed> chaves possíveis: nome, exige_nr35, ativo, itens (lista de textos) */
    private function validar(Request $request, int $biz, ?int $ignorar): array
    {
        $novo = $ignorar === null;
        $entrada = $request->all();
        if (array_key_exists('nome', $entrada) && is_string($entrada['nome'])) {
            $entrada['nome'] = trim($entrada['nome']);
        }
        if (isset($entrada['itens']) && is_array($entrada['itens'])) {
            $entrada['itens'] = array_map(fn ($i) => is_string($i) ? trim($i) : $i, $entrada['itens']);
        }

        $dados = validator($entrada, [
            'nome' => [$novo ? 'required' : 'sometimes', 'string', 'max:150'],
            'exige_nr35' => ['sometimes', 'boolean'],
            'ativo' => [$novo ? 'prohibited' : 'sometimes', 'boolean'],
            'itens' => ['sometimes', 'array', 'max:'.self::MAX_ITENS],
            'itens.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
        ], [
            'nome.required' => 'Dê um nome ao tipo de instalação.',
            'nome.max' => 'O nome tem no máximo 150 caracteres.',
            'itens.max' => 'Cada tipo tem no máximo '.self::MAX_ITENS.' itens.',
            'itens.*.required' => 'Item em branco: escreva o item ou tire a linha.',
            'itens.*.max' => 'Cada item tem no máximo 80 caracteres.',
            'itens.*.distinct' => 'O item ":input" aparece duas vezes.',
        ])->validate();

        if (isset($dados['nome'])) {
            $repetido = InstalacaoCatalogo::query()
                ->where('cv_instalacoes_catalogo.business_id', $biz)
                ->whereRaw('LOWER(nome) = ?', [mb_strtolower($dados['nome'])])
                ->when($ignorar !== null, fn ($q) => $q->where('id', '!=', $ignorar))
                ->exists();
            if ($repetido) {
                throw ValidationException::withMessages(['nome' => 'Já existe um tipo de instalação com esse nome.']);
            }
        }
        if (isset($dados['itens'])) {
            $dados['itens'] = array_values($dados['itens']);
        }

        return $dados;
    }

    /** @return array{id: int, nome: string, exige_nr35: bool, itens: list<string>, ativo: bool} */
    private function formato(InstalacaoCatalogo $t): array
    {
        return [
            'id' => (int) $t->id,
            'nome' => (string) $t->nome,
            'exige_nr35' => (bool) $t->exige_nr35,
            'itens' => $t->itens_verificacao_json ?? [],
            'ativo' => (bool) $t->ativo,
        ];
    }

    private function empresa(bool $gravar): int
    {
        $user = auth()->user();
        $permissoes = $gravar ? ['comvis.material.manage'] : ['comvis.material.manage', 'comvis.os.update_status', 'comvis.os.view'];
        $pode = $user->can('superadmin');
        foreach ($permissoes as $p) {
            $pode = $pode || $user->can($p);
        }
        if (! $pode) {
            abort(403, 'Sem permissão nos tipos de instalação ('.implode(' ou ', $permissoes).').');
        }

        return (int) (session('user.business_id') ?? session('business.id'));
    }
}
