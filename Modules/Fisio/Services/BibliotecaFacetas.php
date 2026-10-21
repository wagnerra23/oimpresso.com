<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\ExerciseFavorite;

/**
 * Facetas da biblioteca de exercícios (US-FISIO-004): posição, equipamento e "só favoritos".
 *
 * Os valores vêm SÓ das listas fechadas de `config('fisio.biblioteca.*')` (`etapas/01-biblioteca.md`
 * §3.3). Valor fora da lista é ignorado no filtro (a tela mostra tudo, como sem filtro) e recusado
 * no cadastro (422). Os filtros se somam à região e à busca por texto do `BibliotecaController`.
 *
 * Favorito é do profissional DENTRO da empresa da sessão (`fisio_exercise_favorites`): um colega da
 * mesma clínica não vê os favoritos do outro, e outra clínica não vê nada. `business_id` e `user_id`
 * vêm sempre da sessão (ADR 0093).
 */
final class BibliotecaFacetas
{
    /** @var list<int>|null */
    private ?array $favoritos = null;

    private function __construct(
        public readonly string $pose,
        public readonly string $equipment,
        public readonly bool $soFavoritos,
        private readonly int $businessId,
        private readonly int $userId,
    ) {}

    public static function doRequest(Request $request): self
    {
        $pose = trim((string) $request->query('pose', ''));
        $equipamento = trim((string) $request->query('equipment', ''));

        return new self(
            array_key_exists($pose, self::posicoes()) ? $pose : '',
            array_key_exists($equipamento, self::equipamentos()) ? $equipamento : '',
            $request->boolean('fav'),
            (int) $request->session()->get('user.business_id'),
            (int) $request->user()?->getAuthIdentifier(),
        );
    }

    /** @return array<string, string> chave => rótulo */
    public static function posicoes(): array
    {
        return (array) config('fisio.biblioteca.posicoes', []);
    }

    /** @return array<string, string> chave => rótulo */
    public static function equipamentos(): array
    {
        return (array) config('fisio.biblioteca.equipamentos', []);
    }

    /**
     * Listas fechadas para a tela, na ordem do config.
     *
     * @return array{posicoes: list<array{value: string, label: string}>, equipamentos: list<array{value: string, label: string}>}
     */
    public static function opcoes(): array
    {
        $lista = fn (array $mapa): array => array_map(
            fn (string $valor, string $rotulo): array => ['value' => $valor, 'label' => $rotulo],
            array_keys($mapa),
            array_values($mapa),
        );

        return ['posicoes' => $lista(self::posicoes()), 'equipamentos' => $lista(self::equipamentos())];
    }

    /**
     * Estado dos filtros devolvido à tela. Valor inválido na URL volta vazio.
     *
     * @return array{pose: string, equipment: string, fav: bool}
     */
    public function paraTela(): array
    {
        return ['pose' => $this->pose, 'equipment' => $this->equipment, 'fav' => $this->soFavoritos];
    }

    /** @param  Builder<Exercise>  $query @return Builder<Exercise> */
    public function aplicar(Builder $query): Builder
    {
        return $query
            ->when($this->pose !== '', fn (Builder $q) => $q->where('pose', $this->pose))
            ->when($this->equipment !== '', fn (Builder $q) => $q->whereJsonContains('equipment', $this->equipment))
            ->when($this->soFavoritos, fn (Builder $q) => $q->whereIn('id', $this->idsFavoritos()));
    }

    /**
     * Campos que a grade precisa além da linha base do exercício.
     *
     * @return array{equipment: list<string>, favorito: bool}
     */
    public function marcar(Model $exercicio): array
    {
        assert($exercicio instanceof Exercise);

        return [
            'equipment' => array_values((array) ($exercicio->equipment ?? [])),
            'favorito' => in_array((int) $exercicio->id, $this->idsFavoritos(), true),
        ];
    }

    /** @return list<int> exercícios favoritos do usuário da sessão, na empresa da sessão */
    public function idsFavoritos(): array
    {
        if ($this->favoritos === null) {
            // O escopo do model já filtra a empresa; o where explícito é a segunda camada.
            $this->favoritos = ExerciseFavorite::query()
                ->where('business_id', $this->businessId)
                ->where('user_id', $this->userId)
                ->pluck('exercise_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->favoritos;
    }

    /**
     * Regras de posição e equipamento no cadastro do exercício próprio. Somadas às do controller
     * (e sobrepondo a regra de texto livre que `pose` tinha).
     *
     * @return array<string, mixed>
     */
    public static function regrasCadastro(): array
    {
        return [
            'pose' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(self::posicoes()))],
            'equipment' => ['sometimes', 'nullable', 'array', 'max:'.count(self::equipamentos()),
                function (string $atributo, mixed $valor, \Closure $falhar): void {
                    if (is_array($valor) && in_array('nenhum', $valor, true) && count($valor) > 1) {
                        $falhar('"Nenhum" não se combina com outro equipamento.');
                    }
                },
            ],
            'equipment.*' => ['string', 'distinct', Rule::in(array_keys(self::equipamentos()))],
        ];
    }

    /** @return array<string, string> */
    public static function mensagensCadastro(): array
    {
        return [
            'pose.in' => 'Escolha uma posição da lista.',
            'equipment.*.in' => 'Escolha equipamentos da lista.',
            'equipment.*.distinct' => 'Equipamento repetido.',
        ];
    }

    /**
     * Lista vazia de equipamento vira nula (o exercício "ainda não declarou").
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function normalizarCadastro(array $dados): array
    {
        if (array_key_exists('equipment', $dados)) {
            $lista = array_values(array_unique((array) ($dados['equipment'] ?? [])));
            $dados['equipment'] = $lista === [] ? null : $lista;
        }
        if (array_key_exists('pose', $dados) && $dados['pose'] === '') {
            $dados['pose'] = null;
        }

        return $dados;
    }
}
