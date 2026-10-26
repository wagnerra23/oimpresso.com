<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\BotMenu;

use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;

/**
 * Bot de atendimento com menu — responde o cliente antes do humano (US-WA-324).
 *
 * Regra fechada, sem IA: saudação + opções numeradas; a opção escolhida põe a conversa na fila
 * dela (`queue_override`) e, se o menu pede, pergunta o nome. Resposta inválida repete o menu uma
 * vez; na segunda, passa para um atendente. Depois de concluído, o bot fica quieto até a conversa
 * ficar `HORAS_PARA_REINICIAR` horas sem mensagem do cliente.
 *
 * Nunca responde quando: a flag global está desligada; o canal não tem menu ativo; a conversa já
 * tem atendente; a mensagem veio da importação de histórico ou é antiga (o bot não pode falar
 * com quem escreveu ontem); a mensagem não é texto. Princípio 8 (fallback): se o envio falha, a
 * conversa segue na fila humana normal.
 *
 * Tier 0 (ADR 0093): tudo lido pela empresa da mensagem.
 */
final class MenuDeAtendimento
{
    public const HORAS_PARA_REINICIAR = 12;

    public const MAX_TENTATIVAS = 2;

    public const IDADE_MAXIMA_MIN = 10;

    public function __construct(private readonly EnvioDeTextoNoCanal $envio) {}

    public function processar(Message $inbound): void
    {
        if (! config('whatsapp.bot_menu.enabled', false)
            || $inbound->direction !== Message::DIRECTION_INBOUND
            || $inbound->type !== 'text'
            || ($inbound->payload['is_history_sync'] ?? false)
            || $inbound->created_at->lt(now()->subMinutes(self::IDADE_MAXIMA_MIN))) {
            return;
        }
        $biz = (int) $inbound->business_id;

        // SUPERADMIN: roda a partir do webhook/fila, sem sessão; filtro explícito por business_id.
        $conversa = Conversation::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $biz)->where('id', (int) $inbound->conversation_id)->first();
        if ($conversa === null || $conversa->assigned_user_id !== null || $conversa->is_blocked) {
            return;
        }
        $menu = DB::table('whatsapp_bot_menus')
            ->where('business_id', $biz)->where('channel_id', (int) $conversa->channel_id)->where('ativo', true)->first();
        if ($menu === null) {
            return;
        }
        $canal = Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $biz)->where('id', (int) $conversa->channel_id)->first();
        if ($canal === null) {
            return;
        }
        $opcoes = collect(json_decode((string) $menu->opcoes, true) ?: [])->keyBy(fn ($o) => trim((string) ($o['tecla'] ?? '')));

        $sessoes = DB::table('whatsapp_bot_menu_sessoes')->where('business_id', $biz)->where('conversation_id', $conversa->id);
        $sessao = (clone $sessoes)->first();
        $texto = trim((string) $inbound->body);

        // Sem sessão, ou concluída e parada há tempo: começa de novo.
        $ultimaAntes = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $biz)->where('conversation_id', $conversa->id)
            ->where('direction', Message::DIRECTION_INBOUND)->where('id', '<', $inbound->id)->max('created_at');
        $parada = $ultimaAntes === null || \Carbon\Carbon::parse($ultimaAntes)->lt(now()->subHours(self::HORAS_PARA_REINICIAR));
        if ($sessao === null || ($sessao->estado === 'concluido' && $parada)) {
            DB::table('whatsapp_bot_menu_sessoes')->upsert(
                [['business_id' => $biz, 'conversation_id' => $conversa->id, 'estado' => 'aguardando_opcao', 'tentativas' => 0, 'opcao' => null, 'created_at' => now(), 'updated_at' => now()]],
                ['business_id', 'conversation_id'],
                ['estado', 'tentativas', 'opcao', 'updated_at'],
            );
            $this->responder($conversa, $canal, $this->textoDoMenu((string) $menu->saudacao, $opcoes->all()));

            return;
        }

        if ($sessao->estado === 'aguardando_opcao') {
            $opcao = $opcoes->get($texto);
            if ($opcao === null) {
                $tentativas = (int) $sessao->tentativas + 1;
                if ($tentativas >= self::MAX_TENTATIVAS) {
                    (clone $sessoes)->update(['estado' => 'concluido', 'tentativas' => $tentativas, 'updated_at' => now()]);
                    $this->responder($conversa, $canal, 'Vou te passar para um atendente. Aguarde um instante, por favor.');
                } else {
                    (clone $sessoes)->update(['tentativas' => $tentativas, 'updated_at' => now()]);
                    $this->responder($conversa, $canal, $this->textoDoMenu('Não entendi. Responda só com o número da opção:', $opcoes->all()));
                }

                return;
            }
            // Só fila que existe nesta empresa: slug digitado errado não tira a conversa da fila automática.
            $fila = (string) ($opcao['fila'] ?? '');
            if ($fila !== '' && DB::table('whatsapp_queues')->where('business_id', $biz)->where('slug', $fila)->exists()) {
                $conversa->forceFill(['queue_override' => $fila])->save();
            }
            $semNome = (bool) $menu->pergunta_nome && $conversa->contact_id === null;
            (clone $sessoes)->update(['estado' => $semNome ? 'aguardando_nome' : 'concluido', 'opcao' => $texto, 'updated_at' => now()]);
            $resposta = trim((string) ($opcao['resposta'] ?? '')) ?: 'Certo! Já vou te encaminhar para '.$opcao['rotulo'].'.';
            $this->responder($conversa, $canal, $semNome ? $resposta.' Antes, qual é o seu nome?' : $resposta);

            return;
        }

        if ($sessao->estado === 'aguardando_nome') {
            $nome = mb_substr($texto, 0, 120);
            $conversa->forceFill(['contact_name' => $nome])->save();
            (clone $sessoes)->update(['estado' => 'concluido', 'updated_at' => now()]);
            $primeiro = trim(explode(' ', $nome)[0]);
            $this->responder($conversa, $canal, "Obrigado, {$primeiro}! Um atendente já vai falar com você.");
        }
    }

    /** @param array<string, array<string, mixed>> $opcoes */
    private function textoDoMenu(string $cabecalho, array $opcoes): string
    {
        $linhas = array_map(fn ($o) => trim((string) $o['tecla']).' - '.trim((string) $o['rotulo']), array_values($opcoes));

        return trim($cabecalho)."\n\n".implode("\n", $linhas);
    }

    private function responder(Conversation $conversa, Channel $canal, string $texto): void
    {
        try {
            $msg = Message::query()->create([
                'business_id' => $conversa->business_id,
                'conversation_id' => $conversa->id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'provider' => $canal->type,
                'type' => 'text',
                'body' => $texto,
                'status' => 'queued',
                'sender_kind' => 'bot',
                'is_internal_note' => false,
            ]);
            $this->envio->despachar($msg, $conversa, $canal, $texto);
        } catch (\Throwable $e) {
            Log::warning('[whatsapp.bot_menu] falha ao responder — segue na fila humana', [
                'business_id' => $conversa->business_id, 'conversation_id' => $conversa->id, 'erro' => $e->getMessage(),
            ]);
        }
    }
}
