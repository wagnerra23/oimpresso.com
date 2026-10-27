<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use App\Scopes\ScopeByBusiness;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Canais\MensagemRecebida;
use Modules\Whatsapp\Services\Canais\RecebimentoNoCanal;
use Symfony\Component\HttpFoundation\Response;

/**
 * E-mail que chega na caixa da empresa entra na Caixa Unificada (US-WA-329 fatia 4).
 *
 * Entrada por ENCAMINHAMENTO HTTP, não por IMAP: o PHP 8.4 não traz mais a extensão
 * `imap` e o projeto não tem biblioteca IMAP — ler IMAP exigiria dependência nova
 * (ADR). O provedor de entrada (Postmark Inbound, ou Cloudflare Email Routing com um
 * Worker que poste o mesmo formato) recebe o e-mail e faz POST aqui, no formato
 * JSON do Postmark Inbound: `FromFull{Email,Name}`, `Subject`, `TextBody`, `MessageID`.
 *
 * Autenticação: cada canal tem `config_json.inbound_token`, enviado como senha do
 * Basic Auth (como o Postmark faz com a URL `https://x:TOKEN@host/...`) ou no header
 * `X-Inbound-Token`. O tenant sai do canal da URL, nunca do e-mail.
 *
 * Atrás de `whatsapp.omnicanal.email_enabled` (desligado = 404).
 */
class EmailEntradaController extends Controller
{
    public function __construct(private readonly RecebimentoNoCanal $recebimento)
    {
    }

    public function handle(Request $request, string $channel_uuid): Response
    {
        if (! config('whatsapp.omnicanal.email_enabled', false)) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // SUPERADMIN: webhook público pré-auth — o tenant é o do canal endereçado na URL.
        $canal = Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('channel_uuid', $channel_uuid)
            ->where('type', Channel::TYPE_EMAIL_IMAP)
            ->where('status', 'active')
            ->first();
        if ($canal === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $esperado = (string) ($canal->config_json['inbound_token'] ?? '');
        $recebido = (string) ($request->getPassword() ?: $request->header('X-Inbound-Token', ''));

        if ($esperado === '' || ! hash_equals($esperado, $recebido)) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $de = strtolower(trim((string) ($request->input('FromFull.Email') ?? $request->input('From', ''))));
        $idMensagem = trim((string) $request->input('MessageID', ''));
        if (! filter_var($de, FILTER_VALIDATE_EMAIL) || $idMensagem === '') {
            return response()->json(['ok' => true, 'resultado' => 'ignorada']);
        }

        $resultado = $this->recebimento->receber($canal, new MensagemRecebida(
            remetenteExterno: $de,
            idNoProvedor: $idMensagem,
            texto: (string) ($request->input('StrippedTextReply') ?: $request->input('TextBody', '')),
            tipo: 'email',
            nomeDoContato: ((string) $request->input('FromFull.Name', '')) ?: null,
            assunto: mb_substr((string) $request->input('Subject', ''), 0, 255) ?: null,
            payloadBruto: $request->only(['From', 'FromFull', 'To', 'Subject', 'MessageID', 'Date']),
        ));

        return response()->json(['ok' => true, 'resultado' => $resultado]);
    }
}
