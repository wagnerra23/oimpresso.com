<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Protocol;
use Modules\Fisio\Services\ConfiguracaoClinica;
use Modules\Fisio\Services\Whatsapp\CanalDaClinica;

/**
 * Primeira configuração da clínica (protótipo `Onboarding.dc.html`, 5 passos). Cada "Continuar"
 * grava o passo dele em `fisio_settings`; nada é obrigatório para usar o módulo.
 *
 *  1. perfil   — nome que o paciente vê e CREFITO, por profissional (`user_id`).
 *  2. atendimento — `pf` (CPF) ou `pj` (CNPJ). Só guardado: quem usa é o fiscal (Lote 4).
 *  3. canal    — a mesma regra de Configurações › WhatsApp (QR exige o aceite de risco).
 *  4. protocolos — os que a clínica mais usa (ids de protocolos visíveis para ela).
 *  5. concluir — marca `onboarding.concluido_em`; o botão leva ao cadastro de paciente.
 *
 * Ler exige `fisio.access` (grupo de rotas); gravar, `fisio.editar`.
 */
class OnboardingController extends Controller
{
    public const PASSOS = ['perfil', 'atendimento', 'canal', 'protocolos', 'concluir'];

    public function show(Request $request, ConfiguracaoClinica $config, CanalDaClinica $canal): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $uid = (int) $request->user()->id;
        $clinica = $config->varias($biz, ['atendimento.tipo', 'protocolos.favoritos', 'onboarding.concluido_em']);
        $perfil = $config->varias($biz, ['perfil.nome_exibicao', 'perfil.crefito'], $uid);

        return Inertia::render('Fisio/Onboarding', [
            'perfil' => [
                'nome_exibicao' => $perfil['perfil.nome_exibicao'] ?? (string) $request->user()->first_name,
                'crefito' => $perfil['perfil.crefito'] ?? '',
            ],
            'atendimento' => $clinica['atendimento.tipo'] ?? null,
            'canal' => $canal->escolha($biz),
            'aceite_qr' => $canal->aceiteQr($biz) !== null,
            'protocolos' => Protocol::query()
                ->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => (int) $p->id, 'nome' => (string) $p->name])->all(),
            'favoritos' => array_map('intval', (array) ($clinica['protocolos.favoritos'] ?? [])),
            'concluido' => isset($clinica['onboarding.concluido_em']),
            'pode_editar' => (bool) $request->user()?->can('fisio.editar'),
        ]);
    }

    public function salvar(Request $request, string $passo, ConfiguracaoClinica $config, CanalDaClinica $canal): RedirectResponse
    {
        abort_unless(in_array($passo, self::PASSOS, true), 404);
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $biz = (int) $request->session()->get('user.business_id');
        $uid = (int) $request->user()->id;

        if ($passo === 'perfil') {
            $d = $request->validate(['nome_exibicao' => ['required', 'string', 'max:80'], 'crefito' => ['nullable', 'string', 'max:40']]);
            $config->gravar($biz, 'perfil.nome_exibicao', trim($d['nome_exibicao']), $uid, $uid);
            $config->gravar($biz, 'perfil.crefito', trim((string) ($d['crefito'] ?? '')), $uid, $uid);
        } elseif ($passo === 'atendimento') {
            $d = $request->validate(['atendimento' => ['required', Rule::in(['pf', 'pj'])]]);
            $config->gravar($biz, 'atendimento.tipo', $d['atendimento'], $uid);
        } elseif ($passo === 'canal') {
            $d = $request->validate(['canal' => ['required', Rule::in(CanalDaClinica::CANAIS)], 'aceite_qr' => ['nullable', 'boolean']]);
            if ($d['canal'] === CanalDaClinica::QR && $canal->aceiteQr($biz) === null) {
                if (! ($d['aceite_qr'] ?? false)) {
                    return back()->withErrors(['aceite_qr' => 'Para usar o número conectado por QR, marque que entende o risco de bloqueio.']);
                }
                $canal->aceitarRiscoQr($biz, $uid);
            }
            $config->gravar($biz, 'whatsapp.canal', $d['canal'], $uid);
        } elseif ($passo === 'protocolos') {
            $d = $request->validate(['favoritos' => ['present', 'array', 'max:50'], 'favoritos.*' => ['integer']]);
            // Só ids que esta clínica enxerga: id de protocolo de outra empresa é descartado.
            $validos = Protocol::query()
                ->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))
                ->whereIn('id', $d['favoritos'])->pluck('id')->map(fn ($i) => (int) $i)->values()->all();
            $config->gravar($biz, 'protocolos.favoritos', $validos, $uid);
        } else {
            $config->gravar($biz, 'onboarding.concluido_em', now()->toIso8601String(), $uid);
        }

        return back();
    }
}
