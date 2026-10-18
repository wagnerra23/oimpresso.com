<?php

declare(strict_types=1);

namespace Modules\Fisio\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semeia a biblioteca GLOBAL do Fisio (US-FISIO-004 · T-04 do HANDOFF-F1 · lote L0 da etapa 01).
 *
 * Fonte: `biblioteca-l0.php` (38 exercícios). Global = `business_id` nulo: visível a todo
 * business com o módulo e editável por nenhum (o controller recusa com 403).
 *
 * ⚠️ PENDENTE DA REVISÃO CLÍNICA DA DRA. DEJEANE (ADR 0437 §12; etapa 01 §3.8). Todo exercício
 * nasce `review_status = em_revisao`, sem revisor, e só aparece para as clínicas depois de
 * `publicado` (ou com `fisio.biblioteca.mostrar_em_revisao` ligado em demo/homologação).
 *
 * Idempotente por `code`:
 *  - exercício que não existe é criado `em_revisao`;
 *  - exercício que existe e ainda NÃO foi revisado (`rascunho`/`em_revisao`) tem o conteúdo
 *    atualizado com o arquivo — o arquivo é a fonte até a revisão;
 *  - exercício já `publicado` ou `retirado` NÃO é tocado: o conteúdo revisado não é sobrescrito
 *    por um seed. Mudança depois da revisão é nova revisão, não re-seed.
 *
 * Grava pelo query builder (não pelo model) de propósito: o escopo da biblioteca lê a sessão e
 * o seed roda sem sessão (CLI / instalação). Não toca exercício de clínica (business_id preenchido).
 */
class FisioBibliotecaSeeder extends Seeder
{
    public const ESTADO_INICIAL = 'em_revisao';

    /** @var list<string> */
    private const JSON = ['steps', 'equipment', 'safety_notes', 'contraindication_flags'];

    public function run(): void
    {
        $r = $this->semear();
        $this->command?->info("Biblioteca Fisio: {$r['criados']} criado(s), {$r['atualizados']} atualizado(s), {$r['preservados']} já revisado(s) preservado(s).");
    }

    /** @return array{criados: int, atualizados: int, preservados: int} */
    public function semear(): array
    {
        $r = ['criados' => 0, 'atualizados' => 0, 'preservados' => 0];
        $agora = now();

        foreach (self::exercicios() as $ex) {
            $linha = $this->linha($ex);
            $atual = DB::table('fisio_exercises')->whereNull('business_id')->where('code', $ex['code'])->first(['id', 'review_status']);

            if ($atual === null) {
                DB::table('fisio_exercises')->insert($linha + [
                    'business_id' => null,
                    'is_custom' => false,
                    'review_status' => self::ESTADO_INICIAL,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
                $r['criados']++;

                continue;
            }

            if (in_array($atual->review_status, ['publicado', 'retirado'], true)) {
                $r['preservados']++;

                continue;
            }

            DB::table('fisio_exercises')->where('id', $atual->id)->update($linha + ['updated_at' => $agora]);
            $r['atualizados']++;
        }

        return $r;
    }

    /** @return list<array<string, mixed>> */
    public static function exercicios(): array
    {
        return require __DIR__.'/biblioteca-l0.php';
    }

    /** @param  array<string, mixed>  $ex @return array<string, mixed> */
    private function linha(array $ex): array
    {
        $linha = [
            'code' => $ex['code'],
            'name' => $ex['name'],
            'region' => $ex['region'],
            'type' => $ex['type'],
            'pose' => $ex['pose'],
            'default_sets' => $ex['default_sets'],
            'default_reps' => $ex['default_reps'],
            'default_hold_s' => $ex['default_hold_s'],
            'times_per_day' => $ex['times_per_day'],
            'needs_support' => (bool) $ex['needs_support'],
        ];
        foreach (self::JSON as $campo) {
            $linha[$campo] = json_encode(array_values($ex[$campo]), JSON_UNESCAPED_UNICODE);
        }

        return $linha;
    }
}
