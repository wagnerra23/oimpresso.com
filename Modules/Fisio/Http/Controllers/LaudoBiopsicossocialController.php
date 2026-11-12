<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialEquipe as Equipe;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\ConfiguracaoClinica;
use Modules\Fisio\Services\DiaLocal;
use Modules\Fisio\Services\ImpressoPdf;
use Modules\Fisio\Services\Laudo\ApoioFisioterapia;
use Modules\Fisio\Services\Laudo\LaudoBiopsicossocialDocumento;
use Modules\Fisio\Services\Laudo\MatrizIfbrm;
use Symfony\Component\HttpFoundation\Response;

/**
 * Escrita do laudo biopsicossocial no modelo SES-SC (US-FISIO-060; Deliberação 500/CIB/2026).
 *
 * Rascunho: o formulário e a equipe mudam à vontade. Finalizar valida as regras do modelo e
 * congela o laudo; correção é nova versão (`amends_id`). Toda escrita exige `fisio.editar`.
 * Paciente e laudo de outra empresa: 404 pelo escopo e por `where business_id`, antes de validar.
 *
 * A conclusão (três Sim/Não) só muda pelo que a equipe marcou na tela: nenhum código aqui a
 * preenche a partir da pontuação. Quem finaliza é registrado com usuário e hora do servidor.
 */
class LaudoBiopsicossocialController extends Controller
{
    private const NATUREZAS = ['fisica', 'mental', 'intelectual', 'sensorial'];

    private const PERGUNTAS = ['impedimento_longo_prazo', 'barreiras', 'equiparacao'];

    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $biz = $this->empresa($request);
        $paciente = Patient::query()->where('business_id', $biz)->findOrFail($id);
        $this->exigirEdicao($request);
        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['paciente' => 'Paciente bloqueado: não recebe documento novo.']);
        }

        $laudo = LaudoBiopsicossocial::query()->create([
            'business_id' => $biz, 'patient_id' => $paciente->id, 'modelo' => MatrizIfbrm::MODELO,
            'dados' => [], 'created_by' => $request->user()->id,
        ]);

        return $this->resposta($request, ['id' => $laudo->id], 'Laudo criado.', 201);
    }

    /** Grava as partes do formulário que vieram; o resto fica como estava. */
    public function update(Request $request, int $laudo): JsonResponse|RedirectResponse
    {
        $doc = $this->rascunho($request, $laudo);
        $v = $request->validate([
            'identificacao' => ['sometimes', 'array'],
            'identificacao.nascimento' => ['nullable', 'date'],
            'identificacao.diagnostico_acr_data' => ['nullable', 'date'],
            'identificacao.unidade_aps' => ['nullable', 'string', 'max:150'],
            'identificacao.impedimentos' => ['nullable', 'string', 'max:5000'],
            'identificacao.cid' => ['nullable', 'string', 'max:20'],
            'identificacao.natureza' => ['nullable', 'array'],
            'identificacao.natureza.*' => [Rule::in(self::NATUREZAS)],
            'matriz' => ['sometimes', 'array'],
            'fatores' => ['sometimes', 'array'],
            'fatores.ambientais' => ['nullable', 'string', 'max:5000'],
            'fatores.pessoais' => ['nullable', 'string', 'max:5000'],
            'conclusao' => ['sometimes', 'array'],
            'conclusao.impedimento_longo_prazo' => ['nullable', 'boolean'],
            'conclusao.barreiras' => ['nullable', 'boolean'],
            'conclusao.equiparacao' => ['nullable', 'boolean'],
            'conclusao.obs' => ['nullable', 'string', 'max:2000'],
            'local' => ['sometimes', 'nullable', 'string', 'max:120'],
            'data' => ['sometimes', 'nullable', 'date'],
        ]);

        $dados = (array) $doc->dados;
        foreach (['identificacao', 'fatores', 'conclusao'] as $bloco) {
            if (array_key_exists($bloco, $v)) {
                $dados[$bloco] = array_merge((array) ($dados[$bloco] ?? []), (array) $v[$bloco]);
            }
        }
        // Sim/Não guardado como booleano de verdade ("1"/"0" do formulário viram true/false); vazio = não respondida.
        foreach (self::PERGUNTAS as $p) {
            if (array_key_exists($p, (array) ($dados['conclusao'] ?? []))) {
                $resp = $dados['conclusao'][$p];
                $dados['conclusao'][$p] = $resp === null || $resp === '' ? null : filter_var($resp, FILTER_VALIDATE_BOOLEAN);
            }
        }
        if (array_key_exists('matriz', $v)) {
            try {
                $dados['matriz'] = array_merge((array) ($dados['matriz'] ?? []), MatrizIfbrm::validar((array) $v['matriz']));
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['matriz' => $e->getMessage()]);
            }
            // Item limpo na tela (valor vazio) sai da matriz.
            foreach ((array) $v['matriz'] as $item => $valor) {
                if ($valor === null || $valor === '') {
                    unset($dados['matriz'][(string) $item]);
                }
            }
        }
        foreach (['local', 'data'] as $campo) {
            if (array_key_exists($campo, $v)) {
                $dados[$campo] = $v[$campo];
            }
        }
        $doc->update(['dados' => $dados]);

        return $this->resposta($request, ['id' => $doc->id, 'pontuacao' => MatrizIfbrm::pontuar((array) ($dados['matriz'] ?? []))], 'Laudo salvo.');
    }

    public function incluirMembro(Request $request, int $laudo): JsonResponse|RedirectResponse
    {
        $doc = $this->rascunho($request, $laudo);
        $v = $request->validate([
            'area' => ['required', Rule::in(array_keys(Equipe::AREAS))],
            'assinatura' => ['required', Rule::in([Equipe::ASSINATURA_SISTEMA, Equipe::ASSINATURA_EXTERNA])],
            'nome' => ['exclude_if:assinatura,sistema', 'required', 'string', 'max:120'],
            'registro' => ['exclude_if:assinatura,sistema', 'required', 'string', 'max:40'],
        ]);

        if ($v['assinatura'] === Equipe::ASSINATURA_SISTEMA) {
            // Assinar pelo sistema é do fisioterapeuta da clínica: nome do usuário, CREFITO do perfil.
            if ($v['area'] !== 'fisioterapia_to') {
                throw ValidationException::withMessages(['area' => 'Pelo sistema só assina a área de Fisioterapia/TO, com o CREFITO do perfil.']);
            }
            $crefito = $this->crefitoDoPerfil($doc->business_id, (int) $request->user()->id);
            if ($crefito === null) {
                throw ValidationException::withMessages(['registro' => 'Cadastre seu CREFITO no perfil antes de assinar.']);
            }
            $v['nome'] = $this->nomeDoUsuario((int) $request->user()->id);
            $v['registro'] = $crefito;
        }

        $membro = Equipe::query()->create([
            'business_id' => $doc->business_id, 'laudo_id' => $doc->id, 'area' => $v['area'], 'nome' => $v['nome'],
            'registro' => $v['registro'], 'assinatura' => $v['assinatura'], 'incluido_por' => $request->user()->id,
        ]);

        return $this->resposta($request, ['id' => $membro->id], 'Profissional incluído.', 201);
    }

    public function removerMembro(Request $request, int $laudo, int $membro): JsonResponse|RedirectResponse
    {
        $doc = $this->rascunho($request, $laudo);
        Equipe::query()->where('business_id', $doc->business_id)->where('laudo_id', $doc->id)->findOrFail($membro)->delete();

        return $this->resposta($request, ['id' => $membro], 'Profissional retirado.');
    }

    /** Valida as regras do modelo SES-SC e congela o laudo. */
    public function finalizar(Request $request, int $laudo): JsonResponse|RedirectResponse
    {
        $doc = $this->rascunho($request, $laudo);
        $dados = (array) $doc->dados;
        $erros = [];

        $pontuacao = MatrizIfbrm::pontuar((array) ($dados['matriz'] ?? []));
        if (! $pontuacao['completo']) {
            $erros['matriz'] = 'Pontue os 30 itens da matriz. Faltam: '.implode(', ', $pontuacao['faltando']).'.';
        }
        foreach (self::PERGUNTAS as $p) {
            if (! isset($dados['conclusao'][$p]) || ! is_bool($dados['conclusao'][$p])) {
                $erros['conclusao'] = 'A equipe precisa responder as três perguntas da conclusão.';
            }
        }
        if (blank($dados['local'] ?? null) || blank($dados['data'] ?? null)) {
            $erros['local'] = 'Local e data são obrigatórios.';
        }
        $areas = $doc->equipe()->pluck('area')->unique();
        if ($areas->count() < 2 || ! $areas->contains('medicina')) {
            $erros['equipe'] = 'A equipe precisa de pelo menos dois profissionais de áreas distintas, um deles médico.';
        }
        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        $doc->update(['finalizado_por' => $request->user()->id, 'finalizado_em' => now()]);

        return $this->resposta($request, ['id' => $doc->id, 'total' => $pontuacao['total']], 'Laudo finalizado.');
    }

    /** Correção de laudo finalizado: cópia em rascunho, com o formulário e a equipe, apontando o original. */
    public function novaVersao(Request $request, int $laudo): JsonResponse|RedirectResponse
    {
        $biz = $this->empresa($request);
        $original = LaudoBiopsicossocial::query()->where('business_id', $biz)->findOrFail($laudo);
        $this->exigirEdicao($request);
        if (! $original->estaFinalizado()) {
            throw ValidationException::withMessages(['laudo' => 'O laudo ainda é rascunho: edite-o direto.']);
        }

        $novo = DB::transaction(function () use ($original, $request) {
            $novo = LaudoBiopsicossocial::query()->create([
                'business_id' => $original->business_id, 'patient_id' => $original->patient_id, 'modelo' => $original->modelo,
                'dados' => $original->dados, 'created_by' => $request->user()->id, 'amends_id' => $original->id,
            ]);
            foreach ($original->equipe()->get() as $m) {
                Equipe::query()->create($m->only(['business_id', 'area', 'nome', 'registro', 'assinatura']) + [
                    'laudo_id' => $novo->id, 'incluido_por' => $request->user()->id,
                ]);
            }

            return $novo;
        });

        return $this->resposta($request, ['id' => $novo->id], 'Nova versão aberta.', 201);
    }

    /** Apoio da fisioterapia (dor, crises, adesão, questionários). Leitura com `fisio.access`; nada é gravado. */
    public function apoioFisio(Request $request, int $laudo, ApoioFisioterapia $apoio): JsonResponse
    {
        $biz = $this->empresa($request);
        $doc = LaudoBiopsicossocial::query()->where('business_id', $biz)->with('patient')->findOrFail($laudo);

        return response()->json($apoio->montar($doc->patient, $biz, DiaLocal::de(now())));
    }

    /**
     * PDF no layout oficial. Leitura (basta `fisio.access`). Cada geração grava uma linha no
     * activity_log só com ids, o estado e o total — nunca nome nem texto clínico (LGPD art. 11).
     */
    public function pdf(Request $request, int $laudo, LaudoBiopsicossocialDocumento $documento, ImpressoPdf $impresso): Response
    {
        $biz = $this->empresa($request);
        $doc = LaudoBiopsicossocial::query()->where('business_id', $biz)->with('patient')->findOrFail($laudo);

        $dados = $documento->montar($doc, $biz);
        $pdf = $impresso->gerar('fisio::pdf.laudo-biopsicossocial', $dados);

        activity('fisio')
            ->performedOn($doc->patient)
            ->causedBy($request->user())
            ->withProperties(['business_id' => $biz, 'patient_id' => (int) $doc->patient_id, 'laudo_bps_id' => (int) $doc->id,
                'finalizado' => $dados['finalizado'], 'total' => $dados['pontuacao']['total']])
            ->log('laudo_bps_exportado');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laudo-biopsicossocial-paciente-'.$doc->patient_id.'-'.now()->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }

    /** Laudo da empresa, ainda em rascunho, com `fisio.editar`. */
    private function rascunho(Request $request, int $id): LaudoBiopsicossocial
    {
        $doc = LaudoBiopsicossocial::query()->where('business_id', $this->empresa($request))->findOrFail($id);
        $this->exigirEdicao($request);
        if ($doc->estaFinalizado()) {
            throw ValidationException::withMessages(['laudo' => 'Laudo finalizado não muda. Abra uma nova versão para corrigir.']);
        }

        return $doc;
    }

    private function crefitoDoPerfil(int $biz, int $userId): ?string
    {
        $valor = app(ConfiguracaoClinica::class)->ler($biz, 'perfil.crefito', null, $userId);
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor === '' ? null : mb_substr($valor, 0, 40);
    }

    private function nomeDoUsuario(int $userId): string
    {
        $u = DB::table('users')->where('id', $userId)->first(['first_name', 'last_name']);

        return mb_substr(trim(($u->first_name ?? '').' '.($u->last_name ?? '')), 0, 120);
    }

    /** @param array<string, mixed> $json */
    private function resposta(Request $request, array $json, string $mensagem, int $status = 200): JsonResponse|RedirectResponse
    {
        return $request->wantsJson() ? response()->json($json, $status) : back()->with('success', $mensagem);
    }
}
