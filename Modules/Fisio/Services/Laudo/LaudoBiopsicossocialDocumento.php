<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Laudo;

use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialEquipe as Equipe;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\IdentidadeClinica;

/**
 * Dados do laudo biopsicossocial para o PDF (layout do modelo SES-SC, Deliberação 500/CIB/2026)
 * e para a ficha do paciente (US-FISIO-060).
 *
 * O PDF segue o formulário: identificação, matriz IFBr-M com a pontuação por domínio e o total
 * calculado, fatores contextuais, conclusão da equipe, as cinco áreas da equipe e local e data.
 * O que não foi preenchido sai em branco, para escrever à mão. Laudo em rascunho sai marcado como
 * documento de apoio, não finalizado.
 *
 * O cabeçalho é o comum dos impressos do Fisio (pdf/_base.blade.php): a clínica vem de
 * IdentidadeClinica, sempre da empresa da sessão — nunca de outra.
 */
final class LaudoBiopsicossocialDocumento
{
    public function __construct(private readonly IdentidadeClinica $identidade) {}

    /** @return array<string, mixed> */
    public function montar(LaudoBiopsicossocial $laudo, int $biz): array
    {
        $laudo->loadMissing(['patient.contact:id,name', 'equipe']);
        $dados = (array) $laudo->dados;
        $matriz = (array) ($dados['matriz'] ?? []);

        $areas = [];
        foreach (Equipe::AREAS as $codigo => $rotulo) {
            $membros = $laudo->equipe->where('area', $codigo)->values();
            $areas[] = ['codigo' => $codigo, 'rotulo' => $rotulo, 'membros' => $membros->map(fn (Equipe $m) => [
                'nome' => $m->nome, 'registro' => $m->registro, 'externa' => $m->assinatura === Equipe::ASSINATURA_EXTERNA,
            ])->all()];
        }

        return [
            'clinica' => $this->identidade->montar($biz),
            'emitido_em' => now()->format('d/m/Y H:i'),
            'laudo_id' => $laudo->id,
            'versao_modelo' => 'Versão 1.0 (Data 26/10/2026)',
            'paciente' => $laudo->patient?->contact?->name,
            'identificacao' => (array) ($dados['identificacao'] ?? []),
            'dominios' => MatrizIfbrm::DOMINIOS,
            'matriz' => $matriz,
            'pontuacao' => MatrizIfbrm::pontuar($matriz),
            'fatores' => (array) ($dados['fatores'] ?? []),
            'conclusao' => (array) ($dados['conclusao'] ?? []),
            'areas' => $areas,
            'local' => $dados['local'] ?? null,
            'data' => isset($dados['data']) ? date('d/m/Y', strtotime((string) $dados['data'])) : null,
            'finalizado' => $laudo->estaFinalizado(),
            'finalizado_em' => $laudo->finalizado_em?->format('d/m/Y H:i'),
            'corrige' => $laudo->amends_id,
        ];
    }

    /**
     * Laudos do paciente para a ficha, o mais recente primeiro.
     *
     * @return list<array<string, mixed>>
     */
    public function doPaciente(Patient $paciente, int $biz): array
    {
        return LaudoBiopsicossocial::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->with('equipe')->orderByDesc('id')->get()
            ->map(fn (LaudoBiopsicossocial $l) => [
                'id' => $l->id,
                'criado_em' => $l->created_at->format('d/m/Y'),
                'finalizado_em' => $l->finalizado_em?->format('d/m/Y H:i'),
                'corrige' => $l->amends_id,
                'dados' => (array) $l->dados,
                'pontuacao' => MatrizIfbrm::pontuar((array) (($l->dados ?? [])['matriz'] ?? [])),
                'equipe' => $l->equipe->map(fn (Equipe $m) => $m->only(['id', 'area', 'nome', 'registro', 'assinatura']))->values()->all(),
            ])->values()->all();
    }

    /**
     * Laudos que o paciente pode baixar no portal (pedido do [W] em 2026-11-12; UC-FBP-16): só os
     * FINALIZADOS e, numa cadeia de correções, só o mais recente (o laudo que outro finalizado
     * corrige sai da lista). Rascunho nunca aparece. O portal não tem sessão de usuário, então o
     * filtro de empresa é o do paciente, que veio assinado no token.
     *
     * @return list<array{id:int, finalizado_em:string}>
     */
    public function finalizadosDoPortal(Patient $paciente): array
    {
        $finalizados = LaudoBiopsicossocial::withoutGlobalScopes() // SUPERADMIN: portal sem sessão; filtra pela empresa do paciente.
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', (int) $paciente->id)
            ->whereNotNull('finalizado_em')
            ->orderByDesc('finalizado_em')->orderByDesc('id')
            ->get(['id', 'amends_id', 'finalizado_em']);
        $corrigidos = $finalizados->pluck('amends_id')->filter()->map(fn ($v) => (int) $v)->all();

        return $finalizados
            ->reject(fn (LaudoBiopsicossocial $l) => in_array((int) $l->id, $corrigidos, true))
            ->map(fn (LaudoBiopsicossocial $l) => ['id' => (int) $l->id, 'finalizado_em' => $l->finalizado_em->format('Y-m-d')])
            ->values()->all();
    }
}
