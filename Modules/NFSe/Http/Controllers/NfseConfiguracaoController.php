<?php

namespace Modules\NFSe\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * POST /nfse/configuracao — ambiente da NFS-e e dados do prestador, por empresa.
 *
 * A tela é a aba "Ambiente e certificado" de /fiscal/config. Até aqui estes campos só
 * mudavam no banco (achado B4 da auditoria NFSe, 2026-11-18).
 *
 * Campos expostos = só os que a emissão LÊ (NfseEmissaoService::montarPayload/emitir):
 * ambiente, municipio_codigo_ibge, prestador_cnpj, prestador_im, serie_default, cnae.
 * `lc116_codigo_default` e `aliquota_iss` ficam fora: são padrão de VALOR do imposto e
 * pedem o regime de cálculo de valor (proibicoes.md §CÁLCULO DE VALOR).
 *
 * Empresa = a da sessão. Nenhum business_id do request é lido (ADR 0093).
 *
 * Trocar o ambiente segue a mesma trava da NF-e (CertificadoController::updateAmbiente):
 * permissão `fiscal.config.ambiente` + o nome do destino digitado + motivo de 15+
 * caracteres, e a troca vira evento de auditoria. Editar só os dados do prestador pede
 * `fiscal.config.edit`, o mesmo gate da tela.
 */
class NfseConfiguracaoController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $superadmin = $user && $user->can('superadmin');

        if (! $superadmin && ! ($user && $user->can('fiscal.config.edit'))) {
            abort(403, 'Exige fiscal.config.edit');
        }

        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return back()->withErrors(['nfse_ambiente' => 'Sessão sem empresa.']);
        }

        $request->merge([
            'prestador_cnpj' => $request->filled('prestador_cnpj')
                ? preg_replace('/\D/', '', (string) $request->input('prestador_cnpj'))
                : null,
        ]);

        $dados = $request->validate([
            'ambiente'              => 'required|in:homologacao,producao',
            'municipio_codigo_ibge' => 'required|digits:7',
            'prestador_cnpj'        => 'nullable|digits:14',
            'prestador_im'          => 'nullable|string|max:20',
            // Série da DPS é numérica (TSSerieDPS); fora disso a emissão manda '1'.
            'serie_default'         => 'nullable|digits_between:1,5',
            'cnae'                  => 'nullable|string|max:10',
        ], [
            'municipio_codigo_ibge.digits' => 'O código IBGE do município tem 7 dígitos.',
            'prestador_cnpj.digits'        => 'O CNPJ do prestador tem 14 dígitos.',
            'serie_default.digits_between' => 'A série da DPS é numérica (até 5 dígitos).',
        ]);

        // SUPERADMIN: business_id explícito da sessão; o escopo global não acrescenta nada
        // e no modo `aberto` do ContextoEmpresa ele não filtra fora de request.
        $config = NfseProviderConfig::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->first();

        $ambienteAntes = $config?->ambiente ?? 'homologacao';
        $ambienteNovo = $dados['ambiente'];
        $trocaAmbiente = $ambienteAntes !== $ambienteNovo;

        $motivo = null;
        if ($trocaAmbiente) {
            if (! $superadmin && ! $user->can('fiscal.config.ambiente')) {
                abort(403, 'Exige fiscal.config.ambiente');
            }

            $label = $ambienteNovo === 'producao' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO';
            $cerimonia = $request->validate([
                'motivo'      => 'required|string|min:15|max:255',
                'confirmacao' => 'required|string|max:40',
            ], [
                'motivo.required'      => 'Escreva o motivo da troca — ele fica registrado na auditoria.',
                'motivo.min'           => 'O motivo precisa de pelo menos 15 caracteres.',
                'confirmacao.required' => "Digite {$label} para confirmar.",
            ]);

            // Case- e acento-insensível, como na NF-e. Str::ascii usa mapa fixo, não locale.
            if (mb_strtoupper(Str::ascii(trim($cerimonia['confirmacao']))) !== mb_strtoupper(Str::ascii($label))) {
                return back()->withInput()->withErrors([
                    'confirmacao' => "A confirmação não bate: digite {$label} para trocar. Nada foi alterado.",
                ]);
            }
            $motivo = $cerimonia['motivo'];
        }

        $config ??= new NfseProviderConfig(['business_id' => $businessId]);
        $config->business_id = $businessId;
        $config->forceFill([
            'ambiente'              => $ambienteNovo,
            'municipio_codigo_ibge' => $dados['municipio_codigo_ibge'],
            'prestador_cnpj'        => $dados['prestador_cnpj'] ?? null,
            'prestador_im'          => $dados['prestador_im'] ?? null,
            'serie_default'         => $dados['serie_default'] ?? '1',
            'cnae'                  => $dados['cnae'] ?? null,
        ]);

        // A emissão usa o certificado do `cert_id` (o mesmo A1 da NF-e). Config criada por
        // esta tela nasceria sem ele e toda emissão falharia em "Nenhum certificado A1".
        // Só preenche quando vazio: troca de certificado não é assunto deste endpoint.
        if (! $config->cert_id) {
            // SUPERADMIN: business_id explícito da sessão.
            $config->cert_id = NfeCertificado::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('ativo', true)
                ->orderByDesc('valido_ate')
                ->value('id');
        }

        $config->save();

        if ($trocaAmbiente) {
            activity('nfse.config')
                ->causedBy($user)
                ->withProperties([
                    'business_id'   => $businessId,
                    'ambiente_de'   => $ambienteAntes,
                    'ambiente_para' => $ambienteNovo,
                    'motivo'        => $motivo,
                ])
                ->log('nfse.ambiente_alterado');
        }

        return back()->with('success', $trocaAmbiente
            ? 'Ambiente da NFS-e alterado para ' . ($ambienteNovo === 'producao' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO') . '.'
            : 'Dados da NFS-e salvos.');
    }
}
