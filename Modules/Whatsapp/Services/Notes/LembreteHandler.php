<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappReminder;

/**
 * Observabilidade D9.a (ADR 0155): handler inline com webhook; Tracer pai
 * via `OtelHelper::span(` herda o span do SlashCommandHandler dispatcher.
 *
 * LembreteHandler — US-WA-076 (ADR 0142 §5).
 *
 * Atendente escreve em nota interna:
 *
 *   /lembrete 2026-05-20 cobrar boleto vencendo
 *   /lembrete amanhã pegar peça
 *   /lembrete daqui 3 dias retornar
 *
 * → Cria row em `whatsapp_reminders` com `due_at` parseada + `body`.
 *   Cron horário `ProcessRemindersJob` notifica atendente via Centrifugo
 *   quando `due_at <= now()`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - `business_id` resolvido da Message (já gateado pelo controller).
 *   - `atendente_user_id` = `sender_user_id` (futuro suporta `@user` opcional).
 *   - `created_by_user_id` = mesmo `sender_user_id` (audit).
 *
 * Parser de data — best-effort, sem dependência externa (chrono-php seria
 * mais robusto mas é depend nova; evita ADR pra coisa simples):
 *   1. ISO `YYYY-MM-DD` (com hora opcional)
 *   2. Frases comuns PT-BR (`amanhã`, `daqui N dias`, `próxima <weekday>`, `hoje`)
 *   3. Falha → SlashCommandResult::error com sugestão de sintaxe
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-076
 */
final class LembreteHandler implements SlashCommandHandler
{
    public function handle(Message $note, string $arguments): SlashCommandResult
    {
        $arguments = trim($arguments);

        if ($arguments === '') {
            return SlashCommandResult::unrecognized();
        }

        if (! $note->is_internal_note) {
            Log::warning('[whatsapp.slash.lembrete] handler invocado em mensagem NÃO nota interna — bloqueado', [
                'message_id' => $note->id,
                'business_id' => $note->business_id,
            ]);
            return SlashCommandResult::error('Comando /lembrete só funciona em nota interna.');
        }

        $parsed = $this->parseDateTime($arguments);
        if ($parsed === null) {
            return SlashCommandResult::error(
                'Data inválida. Use formato YYYY-MM-DD ou "amanhã" / "daqui N dias" / "próxima segunda".'
            );
        }

        [$dueAt, $body] = $parsed;
        $body = trim($body);
        if ($body === '') {
            return SlashCommandResult::error('Lembrete sem texto. Ex: /lembrete amanhã cobrar boleto.');
        }

        $businessId = (int) $note->business_id;
        $atendenteUserId = $note->sender_user_id !== null ? (int) $note->sender_user_id : 0;
        $conversation = $note->conversation;
        $contactId = $conversation?->contact_id !== null ? (int) $conversation->contact_id : null;

        try {
            $reminder = WhatsappReminder::create([
                'business_id' => $businessId,
                'conversation_id' => (int) $note->conversation_id,
                'contact_id' => $contactId,
                'atendente_user_id' => $atendenteUserId,
                'created_by_user_id' => $atendenteUserId,
                'due_at' => $dueAt,
                'body' => $body,
                'status' => WhatsappReminder::STATUS_PENDING,
            ]);

            Log::info('[whatsapp.slash.lembrete] reminder agendado', [
                'reminder_id' => $reminder->id,
                'business_id' => $businessId,
                'conversation_id' => $note->conversation_id,
                'message_id' => $note->id,
                'due_at' => $dueAt->toIso8601String(),
                'atendente_user_id' => $atendenteUserId,
            ]);

            return SlashCommandResult::success(
                '⏰ lembrete agendado',
                '/atendimento/lembretes?reminder_id=' . $reminder->id,
            );
        } catch (\Throwable $e) {
            Log::error('[whatsapp.slash.lembrete] falha ao gravar reminder', [
                'message_id' => $note->id,
                'business_id' => $businessId,
                'exception' => mb_substr($e->getMessage(), 0, 240),
            ]);

            return SlashCommandResult::error('Erro ao agendar lembrete — nota salva mas reminder não gravado.');
        }
    }

    /**
     * Parser best-effort de data + body.
     *
     * Retorna `[Carbon $dueAt, string $body]` ou null em falha.
     */
    private function parseDateTime(string $arguments): ?array
    {
        // 1. ISO YYYY-MM-DD (com opcional HH:MM)
        //    `2026-05-20 cobrar boleto`
        //    `2026-05-20 14:30 cobrar boleto`
        if (preg_match('/^(\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2})?)\s+(.+)$/u', $arguments, $m)) {
            try {
                $dueAt = Carbon::parse($m[1]);
                // Se for só data sem hora, default 09:00 (horário comercial)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $m[1])) {
                    $dueAt = $dueAt->setTime(9, 0, 0);
                }
                return [$dueAt, $m[2]];
            } catch (\Throwable $e) {
                return null;
            }
        }

        // 2. Frases humanas PT-BR.
        $lower = mb_strtolower($arguments);

        // "hoje <body>" → fim do dia (23:59)
        if (preg_match('/^hoje\s+(.+)$/u', $lower, $m)) {
            return [now()->endOfDay(), $this->extractOriginalCase($arguments, $m[1])];
        }

        // "amanhã <body>" / "amanha <body>" → +1d 09:00
        if (preg_match('/^(amanh[aã])\s+(.+)$/u', $lower, $m)) {
            return [now()->addDay()->setTime(9, 0, 0), $this->extractOriginalCase($arguments, $m[2])];
        }

        // "daqui N dia(s) <body>" / "daqui N hora(s) <body>"
        if (preg_match('/^daqui\s+(\d{1,3})\s+dias?\s+(.+)$/u', $lower, $m)) {
            return [now()->addDays((int) $m[1])->setTime(9, 0, 0), $this->extractOriginalCase($arguments, $m[2])];
        }
        if (preg_match('/^daqui\s+(\d{1,3})\s+horas?\s+(.+)$/u', $lower, $m)) {
            return [now()->addHours((int) $m[1]), $this->extractOriginalCase($arguments, $m[2])];
        }
        if (preg_match('/^daqui\s+(\d{1,3})\s+minutos?\s+(.+)$/u', $lower, $m)) {
            return [now()->addMinutes((int) $m[1]), $this->extractOriginalCase($arguments, $m[2])];
        }

        // "próxima <weekday> <body>" / "proxima <weekday> <body>"
        $weekdays = [
            'segunda' => Carbon::MONDAY,
            'terça' => Carbon::TUESDAY,
            'terca' => Carbon::TUESDAY,
            'quarta' => Carbon::WEDNESDAY,
            'quinta' => Carbon::THURSDAY,
            'sexta' => Carbon::FRIDAY,
            'sábado' => Carbon::SATURDAY,
            'sabado' => Carbon::SATURDAY,
            'domingo' => Carbon::SUNDAY,
        ];
        if (preg_match('/^pr[óo]xim[ao]\s+(\p{L}+)\s+(.+)$/u', $lower, $m)) {
            $day = $m[1];
            if (isset($weekdays[$day])) {
                $dueAt = now()->next($weekdays[$day])->setTime(9, 0, 0);
                return [$dueAt, $this->extractOriginalCase($arguments, $m[2])];
            }
        }

        return null;
    }

    /**
     * Recupera o body com case original — o regex foi feito em `mb_strtolower`
     * pra match, mas o body persistido preserva acentos/maiúsculas que o
     * atendente digitou.
     */
    private function extractOriginalCase(string $original, string $lowercasedMatch): string
    {
        $needle = mb_strtolower($original);
        $pos = mb_strpos($needle, $lowercasedMatch);
        if ($pos === false) {
            return $lowercasedMatch;
        }
        return mb_substr($original, $pos, mb_strlen($lowercasedMatch));
    }
}
