<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Services\ProntuarioRegras;

/**
 * Prontuário do Fisio — US-FISIO-005 (Res. COFFITO 414/2012; ADR 0437 §4).
 *
 * Só INCLUSÃO. Não há rota de PUT/PATCH/DELETE de registro (405): o registro assinado não
 * muda — o model (`Record::booted`) e o trigger MySQL da US-FISIO-003 recusam UPDATE/DELETE.
 * Correção é um novo registro `kind=adendo` com `amends_id` para o original (mesmo paciente,
 * mesma empresa), que fica intacto.
 *
 * Carimbo: `signed_by` = usuário logado, `signed_at` = relógio do servidor, `signed_crefito` =
 * o CREFITO informado na assinatura (obrigatório). Nada disso é lido do corpo do request.
 * `telemonitoramento` não é criável aqui — só o portal gera (US-FISIO-007).
 *
 * Contrato: Pacientes/Show.casos.md (UC-FPS-03, 04, 05).
 */
class ProntuarioController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('fisio.editar'), 403);
        $paciente = Patient::query()->findOrFail($id);

        $kind = is_string($request->input('kind')) ? $request->input('kind') : null;
        $regras = ProntuarioRegras::regras($kind);
        if ($kind === Record::KIND_ADENDO) {
            // O original tem de ser deste paciente E desta empresa (o paciente já é da sessão).
            $regras['amends_id'] = ['required', 'integer', Rule::exists('fisio_records', 'id')
                ->where('patient_id', $paciente->id)
                ->where('business_id', $paciente->business_id)];
        }

        $dados = Validator::make($request->all(), $regras, [
            'amends_id.exists' => __('fisio::fisio.adendo_invalido'),
        ])->validate();

        if ($paciente->estaBloqueado() && $dados['kind'] !== Record::KIND_ADENDO) {
            throw ValidationException::withMessages(['kind' => __('fisio::fisio.registro_paciente_bloqueado')]);
        }

        $registro = Record::query()->create([
            'business_id' => $paciente->business_id,
            'patient_id' => $paciente->id,
            'kind' => $dados['kind'],
            'body' => ProntuarioRegras::recortar($dados['kind'], (array) $dados['body']),
            'signed_by' => (int) $request->user()->id,
            'signed_crefito' => trim((string) $dados['crefito']),
            'signed_at' => now(),
            'amends_id' => $dados['kind'] === Record::KIND_ADENDO ? (int) $dados['amends_id'] : null,
        ]);

        return response()->json(['id' => (int) $registro->id, 'registro' => $this->linha($registro)], 201);
    }

    /**
     * Leitura de UM registro (com os adendos que o corrigem). É a única operação na URI do
     * registro além do POST da coleção — por isso PUT/PATCH/DELETE nela respondem 405.
     */
    public function show(int $id, int $registro): JsonResponse
    {
        $paciente = Patient::query()->findOrFail($id);
        $r = Record::query()->with('signer:id,first_name,last_name')
            ->where('patient_id', $paciente->id)->findOrFail($registro);
        $adendos = Record::query()->with('signer:id,first_name,last_name')
            ->where('patient_id', $paciente->id)->where('amends_id', $r->id)
            ->orderBy('signed_at')->get();

        return response()->json([
            'registro' => $this->linha($r),
            'adendos' => $adendos->map(fn (Record $a) => $this->linha($a))->all(),
        ]);
    }

    /**
     * Linha do tempo do prontuário (mais recente primeiro), com o nome do profissional.
     *
     * @return list<array<string, mixed>>
     */
    public function linhaDoTempo(Patient $paciente): array
    {
        return Record::query()->with('signer:id,first_name,last_name')
            ->where('patient_id', $paciente->id)
            ->orderByDesc('signed_at')->orderByDesc('id')
            ->get()
            ->map(fn (Record $r) => $this->linha($r))
            ->all();
    }

    /** @return array<string, mixed> */
    private function linha(Record $r): array
    {
        $assinante = $r->signer;

        return [
            'id' => (int) $r->id,
            'kind' => $r->kind,
            'body' => (array) $r->body,
            'modality' => $r->modality,
            'channel' => $r->channel,
            'amends_id' => $r->amends_id !== null ? (int) $r->amends_id : null,
            'signed_by' => $r->signed_by !== null ? (int) $r->signed_by : null,
            'signed_by_nome' => $assinante ? trim($assinante->first_name.' '.$assinante->last_name) : null,
            'signed_crefito' => $r->signed_crefito,
            'signed_at' => $r->signed_at?->toIso8601String(),
        ];
    }
}
