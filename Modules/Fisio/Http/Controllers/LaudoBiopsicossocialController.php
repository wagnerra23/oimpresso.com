<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialSecao as Secao;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\ConfiguracaoClinica;
use Modules\Fisio\Services\DiaLocal;
use Modules\Fisio\Services\LaudoBiopsicossocialDocumento;
use Modules\Fisio\Services\LaudoBiopsicossocialFisio;
use Symfony\Component\HttpFoundation\Response;

/**
 * Escrita do laudo biopsicossocial (US-FISIO-060; Lei 15.176/2025; LBI art. 2º, §1º).
 *
 * Três passos: criar o laudo, salvar o rascunho de uma seção (editável) e registrar a seção
 * (a partir daí ela não muda). Toda escrita exige `fisio.editar`. Paciente e laudo de outra
 * empresa: 404 pelo escopo e pelo filtro explícito de `business_id`, antes de validar.
 *
 * Carimbo: `registrado_por` e `registrado_em` são o usuário logado e o relógio do servidor;
 * nunca vêm do corpo. Na assinatura `sistema`, autor, conselho e número também são montados
 * aqui: nome do usuário e CREFITO do perfil (o mesmo do prontuário, RegistrosController).
 * Sem CREFITO no perfil, não assina pelo sistema. Na assinatura `externa` o autor é de fora da
 * clínica e não tem usuário: nome, conselho e número vêm do formulário, e o PDF reserva espaço
 * para a assinatura dele à mão ou por certificado externo.
 *
 * Corrigir seção registrada: o rascunho novo do mesmo item aponta a última registrada
 * (`amends_id`); a original fica intacta.
 */
class LaudoBiopsicossocialController extends Controller
{
    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $biz = $this->empresa($request);
        $paciente = Patient::query()->where('business_id', $biz)->findOrFail($id);
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['paciente' => 'Paciente bloqueado: não recebe documento novo.']);
        }

        $laudo = LaudoBiopsicossocial::query()->create([
            'business_id' => $biz, 'patient_id' => $paciente->id, 'created_by' => $request->user()->id,
        ]);

        return $this->resposta($request, ['id' => $laudo->id], 'Laudo criado.', 201);
    }

    /**
     * Sugestão da seção de atividades com o que o Fisio mede (dor, crises, adesão, questionários).
     * Leitura: basta `fisio.access`. Nada é gravado; o profissional revisa e salva como rascunho.
     */
    public function sugestaoFisio(Request $request, int $laudo, LaudoBiopsicossocialFisio $sugestao): JsonResponse
    {
        $biz = $this->empresa($request);
        $doc = LaudoBiopsicossocial::query()->where('business_id', $biz)->with('patient')->findOrFail($laudo);

        return response()->json($sugestao->montar($doc->patient, $biz, DiaLocal::de(now())));
    }

    /**
     * PDF do laudo: só seção registrada; item sem registro sai em branco para assinatura à mão.
     * Leitura (basta `fisio.access`). Cada geração grava uma linha no activity_log com ids e a
     * contagem de seções — nunca nome nem texto clínico (LGPD art. 11), como o laudo de evolução.
     */
    public function pdf(Request $request, int $laudo, LaudoBiopsicossocialDocumento $documento): Response
    {
        $biz = $this->empresa($request);
        $doc = LaudoBiopsicossocial::query()->where('business_id', $biz)->with('patient')->findOrFail($laudo);

        $dados = $documento->montar($doc, $biz);
        $pdf = Pdf::loadView('fisio::pdf.laudo-biopsicossocial', $dados)->setPaper('a4', 'portrait');

        activity('fisio')
            ->performedOn($doc->patient)
            ->causedBy($request->user())
            ->withProperties([
                'business_id' => $biz, 'patient_id' => (int) $doc->patient_id, 'laudo_bps_id' => (int) $doc->id,
                'secoes_registradas' => count(array_filter($dados['secoes'], fn (array $s) => $s['registrada'] !== null)),
            ])
            ->log('laudo_bps_exportado');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laudo-biopsicossocial-paciente-'.$doc->patient_id.'-'.now()->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Salva o rascunho da seção do item (cria se não houver). Seção registrada não é tocada. */
    public function salvar(Request $request, int $laudo, string $item): JsonResponse|RedirectResponse
    {
        [$biz, $doc] = $this->laudo($request, $laudo);
        abort_unless(in_array($item, Secao::ITENS, true), 404);

        $dados = $request->validate([
            'texto' => ['nullable', 'string', 'max:10000'],
            'assinatura' => ['required', Rule::in([Secao::ASSINATURA_SISTEMA, Secao::ASSINATURA_EXTERNA])],
            'autor_nome' => ['nullable', 'string', 'max:120'],
            'conselho' => ['nullable', Rule::in(Secao::CONSELHOS)],
            'registro_conselho' => ['nullable', 'string', 'max:40'],
        ]);

        $rascunho = $this->rascunho($biz, $doc->id, $item) ?? new Secao([
            'business_id' => $biz, 'laudo_id' => $doc->id, 'item' => $item,
            'amends_id' => $this->ultimaRegistrada($biz, $doc->id, $item)?->id,
        ]);
        $rascunho->fill([
            'texto' => $dados['texto'] ?? null,
            'assinatura' => $dados['assinatura'],
            // Na assinatura pelo sistema o autor é o usuário; o que veio no corpo é ignorado.
            'autor_nome' => $dados['assinatura'] === Secao::ASSINATURA_EXTERNA ? ($dados['autor_nome'] ?? null) : null,
            'conselho' => $dados['assinatura'] === Secao::ASSINATURA_EXTERNA ? ($dados['conselho'] ?? null) : null,
            'registro_conselho' => $dados['assinatura'] === Secao::ASSINATURA_EXTERNA ? ($dados['registro_conselho'] ?? null) : null,
        ])->save();

        return $this->resposta($request, ['id' => $rascunho->id], 'Rascunho salvo.');
    }

    /** Registra o rascunho do item: a partir daqui a seção não muda. */
    public function registrar(Request $request, int $laudo, string $item): JsonResponse|RedirectResponse
    {
        [$biz, $doc] = $this->laudo($request, $laudo);
        abort_unless(in_array($item, Secao::ITENS, true), 404);

        $secao = $this->rascunho($biz, $doc->id, $item);
        if ($secao === null) {
            throw ValidationException::withMessages(['secao' => 'Não há rascunho desta seção para registrar.']);
        }
        if (trim((string) $secao->texto) === '') {
            throw ValidationException::withMessages(['texto' => 'A seção precisa de texto para ser registrada.']);
        }

        $userId = (int) $request->user()->id;
        if ($secao->assinatura === Secao::ASSINATURA_SISTEMA) {
            $crefito = $this->crefitoDoPerfil($biz, $userId);
            if ($crefito === null) {
                throw ValidationException::withMessages(['crefito' => 'Cadastre seu CREFITO no perfil antes de assinar.']);
            }
            $secao->fill(['autor_nome' => $this->nomeDoUsuario($userId), 'conselho' => 'CREFITO', 'registro_conselho' => $crefito]);
        } elseif (blank($secao->autor_nome) || blank($secao->conselho) || blank($secao->registro_conselho)) {
            throw ValidationException::withMessages(['autor_nome' => 'Informe nome, conselho e número do registro de quem escreveu a seção.']);
        }

        $secao->fill(['registrado_por' => $userId, 'registrado_em' => now()])->save();

        return $this->resposta($request, ['id' => $secao->id], 'Seção registrada.');
    }

    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    /** @return array{0: int, 1: LaudoBiopsicossocial} */
    private function laudo(Request $request, int $id): array
    {
        $biz = $this->empresa($request);
        $doc = LaudoBiopsicossocial::query()->where('business_id', $biz)->findOrFail($id);
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);

        return [$biz, $doc];
    }

    private function rascunho(int $biz, int $laudoId, string $item): ?Secao
    {
        return Secao::query()->where('business_id', $biz)->where('laudo_id', $laudoId)->where('item', $item)
            ->whereNull('registrado_em')->latest('id')->first();
    }

    private function ultimaRegistrada(int $biz, int $laudoId, string $item): ?Secao
    {
        return Secao::query()->where('business_id', $biz)->where('laudo_id', $laudoId)->where('item', $item)
            ->whereNotNull('registrado_em')->latest('id')->first();
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
