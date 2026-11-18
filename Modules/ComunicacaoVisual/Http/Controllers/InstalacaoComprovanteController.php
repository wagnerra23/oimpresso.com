<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Services\ComprovanteInstalacao;

/**
 * Comprovante da instalação de campo — US-COMVIS-007 · UC-PCV-25 (1a: só a API; a tela do instalador vem depois).
 *
 *   GET  /comunicacao-visual/api/instalacoes/{id}/comprovante  → o que já foi colhido e se pode concluir
 *   POST /comunicacao-visual/api/instalacoes/{id}/fotos        → `tipo` (foto_antes|foto_depois) + `arquivo`
 *   POST /comunicacao-visual/api/instalacoes/{id}/assinatura   → `png` (data URL) + `assinado_por` + `consentimento`
 *   POST /comunicacao-visual/api/instalacoes/{id}/concluir     → só com foto antes, foto depois e assinatura
 *
 * 200/201 = feito · 422 = regra (falta comprovante, sem consentimento, assinatura repetida, instalação fechada)
 * 404 = instalação de outra empresa (não confirma que o id existe) · 403 = sem permissão.
 * Permissão: a mesma de agendar (`comvis.os.update_status`). Tier 0 (ADR 0093): empresa da sessão.
 */
class InstalacaoComprovanteController extends Controller
{
    public function __construct(private readonly ComprovanteInstalacao $comprovante) {}

    public function show(int $id): JsonResponse
    {
        return $this->executar(fn () => $this->comprovante->estado($this->businessId(), $id));
    }

    public function foto(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:foto_antes,foto_depois'],
            'arquivo' => ['required', 'file', 'max:'.(int) floor(((int) config('constants.document_size_limit', 5000000)) / 1024)],
        ], ['arquivo.required' => 'Tire ou escolha a foto.', 'arquivo.max' => 'A foto passou do tamanho máximo.']);

        return $this->executar(function () use ($request, $dados, $id) {
            $this->comprovante->foto($this->businessId(), $id, $dados['tipo'], $request->file('arquivo'), auth()->id());

            return $this->comprovante->estado($this->businessId(), $id);
        }, 201);
    }

    public function assinatura(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'png' => ['required', 'string'],
            'assinado_por' => ['required', 'string', 'max:120'],
            'consentimento' => ['required', 'boolean'],
        ], ['assinado_por.required' => 'Informe o nome de quem assinou.']);

        return $this->executar(function () use ($dados, $id) {
            $this->comprovante->assinatura($this->businessId(), $id, $dados['png'], $dados['assinado_por'],
                (bool) $dados['consentimento'], auth()->id());

            return $this->comprovante->estado($this->businessId(), $id);
        }, 201);
    }

    public function concluir(int $id): JsonResponse
    {
        return $this->executar(function () use ($id) {
            $this->comprovante->concluir($this->businessId(), $id);

            return $this->comprovante->estado($this->businessId(), $id);
        });
    }

    private function executar(\Closure $acao, int $status = 200): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user !== null && ($user->can('superadmin') || $user->can('comvis.os.update_status')), 403,
            'Sem permissão para registrar a instalação (comvis.os.update_status).');
        try {
            return response()->json($acao(), $status);
        } catch (ModelNotFoundException) {
            abort(404, 'Instalação não encontrada.');
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    private function businessId(): int
    {
        return (int) (session('user.business_id') ?? session('business.id'));
    }
}
