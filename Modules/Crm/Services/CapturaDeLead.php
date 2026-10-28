<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Contact;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\Deal;
use Modules\Crm\Entities\FormularioCaptura;

/**
 * Captura de lead pelo formulário público (US-CRM-096).
 *
 * A empresa vem SEMPRE do formulário (resolvido pela chave), nunca do visitante (Tier 0, ADR
 * 0093). O contato é procurado só dentro dessa empresa, por e-mail ou celular; se não existir,
 * nasce como cliente do cadastro — o negócio do funil aponta para cliente, não para
 * `contacts.type='lead'`, que segue em depreciação (ADR 0441 §2).
 */
class CapturaDeLead
{
    public const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * @param  array{nome: string, email?: ?string, telefone?: ?string, mensagem?: ?string}  $dados
     * @param  array<string, ?string>  $utm
     */
    public function capturar(FormularioCaptura $form, array $dados, array $utm): Deal
    {
        $biz = (int) $form->business_id;

        return DB::transaction(function () use ($form, $biz, $dados, $utm) {
            $contato = $this->contato($biz, (int) $form->owner_user_id, $dados);

            $titulo = mb_substr('Lead: '.$dados['nome'].' ('.$form->nome.')', 0, 191);
            $mensagem = trim((string) ($dados['mensagem'] ?? ''));

            return Deal::query()->create(array_merge([
                'business_id' => $biz,
                'contact_id' => $contato->id,
                'titulo' => $titulo,
                'stage' => Deal::STAGE_LEAD,
                'origem' => Deal::ORIGEM_FORMULARIO,
                'valor_estimado' => 0,
                'owner_user_id' => (int) $form->owner_user_id,
                'metadata' => array_filter([
                    'formulario_id' => (int) $form->id,
                    'mensagem' => $mensagem !== '' ? mb_substr($mensagem, 0, 1000) : null,
                ]),
            ], self::utmLimpo($utm)));
        });
    }

    /** @return array<string, ?string> só as 5 chaves UTM, aparadas a 120 caracteres */
    public static function utmLimpo(array $utm): array
    {
        $r = [];
        foreach (self::UTM as $c) {
            $v = isset($utm[$c]) && is_string($utm[$c]) ? trim($utm[$c]) : '';
            $r[$c] = $v === '' ? null : mb_substr($v, 0, 120);
        }

        return $r;
    }

    private function contato(int $biz, int $dono, array $dados): Contact
    {
        $email = mb_strtolower(trim((string) ($dados['email'] ?? '')));
        $celular = preg_replace('/\D+/', '', (string) ($dados['telefone'] ?? '')) ?? '';

        // SUPERADMIN: rota pública sem sessão — o filtro de empresa é o where explícito abaixo.
        $existente = Contact::withoutGlobalScopes()
            ->where('business_id', $biz)
            ->whereIn('type', ['customer', 'both'])
            ->where(function ($q) use ($email, $celular) {
                if ($email !== '') {
                    $q->orWhere('email', $email);
                }
                if ($celular !== '') {
                    $q->orWhere('mobile', $celular);
                }
            })
            ->orderBy('id')
            ->first();
        if ($existente) {
            return $existente;
        }

        $novo = new Contact;
        $novo->forceFill([
            'business_id' => $biz,
            'type' => 'customer',
            'name' => mb_substr($dados['nome'], 0, 191),
            'email' => $email !== '' ? $email : null,
            'mobile' => $celular !== '' ? $celular : '0',
            'contact_id' => 'CAP'.random_int(100000, 999999),
            'created_by' => $dono,
        ])->save();

        return $novo;
    }
}
