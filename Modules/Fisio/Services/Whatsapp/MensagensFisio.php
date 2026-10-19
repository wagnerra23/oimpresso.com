<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Whatsapp;

/**
 * Textos das mensagens do Fisio ao paciente (ADR 0437 §13.1; etapa 02 §3.1 "política de conteúdo").
 *
 * Sem dado clínico no corpo: nada de diagnóstico, condição ou dor — só "seus exercícios". O PIN
 * nunca vai na mesma mensagem do link (precedente OficinaAuto).
 *
 * ⚠️ Texto ao paciente: revisão da Dra. Dejeane (responsável técnica) antes de produção (ADR 0437).
 */
final class MensagensFisio
{
    public static function link(string $nome, string $clinica, string $url): string
    {
        $ola = $nome !== '' ? "Olá, {$nome}!" : 'Olá!';

        return "{$ola} {$clinica} preparou seus exercícios de casa.\n\nAbra pelo link:\n{$url}\n\nO código de acesso chega na próxima mensagem.";
    }

    public static function pin(string $pin): string
    {
        return "Seu código de acesso aos exercícios: {$pin}\n\nDigite no link que enviamos. Não compartilhe este código.";
    }

    public static function lembrete(string $nome, string $clinica): string
    {
        $ola = $nome !== '' ? "Oi, {$nome}!" : 'Oi!';

        return "{$ola} Hoje é dia dos seus exercícios com {$clinica}. Quando terminar, registre como foi pelo link que você recebeu.\n\nPara não receber mais lembretes, responda PARAR.";
    }

    /** Link `wa.me` para o profissional enviar do próprio aparelho (canal manual, ADR 0437 §13.1 (c)). */
    public static function waMe(string $telefone, string $texto): string
    {
        $digitos = preg_replace('/\D/', '', $telefone) ?? '';
        if ($digitos !== '' && strlen($digitos) <= 11) {
            $digitos = '55'.$digitos;
        }

        return 'https://wa.me/'.$digitos.'?text='.rawurlencode($texto);
    }
}