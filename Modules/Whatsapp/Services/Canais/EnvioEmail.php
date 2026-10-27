<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Canais;

use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Mail\RespostaDeAtendimentoMail;

/**
 * Resposta do atendente a uma conversa de e-mail, pelo SMTP da caixa da empresa
 * (US-WA-329 fatia 4). Chamado pelo `EnvioDeTextoNoCanal`, como os outros canais.
 *
 * O SMTP é o do canal (`config_json.smtp_host/smtp_port/smtp_username/smtp_password/
 * smtp_encryption`), registrado como mailer `omnicanal_{id}` só durante o envio —
 * cada empresa responde pela própria caixa, nunca pelo e-mail do sistema. O remetente
 * é o `display_identifier` do canal. Assunto: "Re: " + o assunto do último e-mail
 * recebido na conversa.
 *
 * Atrás de `whatsapp.omnicanal.email_enabled`.
 */
final class EnvioEmail
{
    /** @return array{ok: bool, motivo: string|null, erro: string|null} */
    public function despachar(Message $message, Conversation $conversation, Channel $channel, ?string $texto): array
    {
        if (! config('whatsapp.omnicanal.email_enabled', false)) {
            return $this->falha($message, 'tipo_nao_suportado', 'O e-mail ainda não está ligado nesta instalação.');
        }

        $cfg = (array) ($channel->config_json ?? []);
        $de = (string) $channel->display_identifier;
        if (empty($cfg['smtp_host']) || ! filter_var($de, FILTER_VALIDATE_EMAIL)) {
            return $this->falha($message, 'sem_token', 'Canal de e-mail sem SMTP configurado.');
        }

        // SUPERADMIN: a conversa já veio escopada pelo chamador; o filtro por conversation_id + business_id mantém o tenant.
        $ultimoAssunto = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $channel->business_id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')->whereNotNull('subject')
            ->latest('id')->value('subject');
        $assunto = $ultimoAssunto ? (Str::startsWith($ultimoAssunto, 'Re:') ? $ultimoAssunto : 'Re: '.$ultimoAssunto) : 'Atendimento';

        $dominio = Str::after($de, '@');
        $idDaMensagem = Str::uuid()->toString().'@'.$dominio;
        $mailer = "omnicanal_{$channel->id}";

        config(["mail.mailers.{$mailer}" => [
            'transport' => 'smtp',
            'host' => $cfg['smtp_host'],
            'port' => (int) ($cfg['smtp_port'] ?? 587),
            'username' => $cfg['smtp_username'] ?? null,
            'password' => $cfg['smtp_password'] ?? null,
            'encryption' => $cfg['smtp_encryption'] ?? 'tls',
            'timeout' => 15,
        ]]);

        try {
            Mail::purge($mailer); // worker de fila longo: não reaproveitar SMTP com configuração antiga.
            Mail::mailer($mailer)->to($conversation->customer_external_id)->send(
                (new RespostaDeAtendimentoMail($channel->business_id, $conversation->id, $assunto, (string) $texto, $idDaMensagem))
                    ->from($de, $channel->label)
            );
        } catch (\Throwable $e) {
            return $this->falha($message, 'rede', 'Falha ao enviar o e-mail. Veja status da mensagem.', mb_substr($e->getMessage(), 0, 240));
        }

        $message->forceFill([
            'status' => 'sent',
            'type' => 'email',
            'subject' => $assunto,
            'provider_message_id' => RecebimentoNoCanal::idNoBanco($channel, "<{$idDaMensagem}>"),
        ])->save();

        return ['ok' => true, 'motivo' => null, 'erro' => null];
    }

    /** @return array{ok: false, motivo: string, erro: string} */
    private function falha(Message $message, string $motivo, string $erro, ?string $detalhe = null): array
    {
        $message->forceFill(['status' => 'failed', 'failed_reason' => $detalhe ?? $erro])->save();

        return ['ok' => false, 'motivo' => $motivo, 'erro' => $erro];
    }
}
