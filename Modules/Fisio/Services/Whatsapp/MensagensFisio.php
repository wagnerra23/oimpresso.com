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

    /**
     * A mensagem gravada no módulo Whatsapp é deste envio do Fisio, PARA ESTE paciente? Usado pelo
     * fallback do painel (T-09e). O telefone sozinho não basta: outro módulo escreve para o mesmo
     * número, e dois pacientes podem ter o celular do mesmo responsável. Por isso o texto é casado
     * com o nome: exato no lembrete, pela saudação no link do programa. O código (PIN) não leva
     * nome (ver `ehCodigo`). Fica ao lado dos textos para os dois mudarem juntos.
     */
    public static function pertenceAo(string $tipo, string $body, string $nome, string $clinica): bool
    {
        $ola = $nome !== '' ? "Olá, {$nome}!" : 'Olá!';

        return match ($tipo) {
            EnvioWhatsappFisio::LEMBRETE => $body === self::lembrete($nome, $clinica),
            EnvioWhatsappFisio::PROGRAMA => str_starts_with($body, "{$ola} {$clinica} preparou seus exercícios de casa.")
                || self::ehCodigo($body),
            default => false,
        };
    }

    public static function ehCodigo(string $body): bool
    {
        return str_starts_with($body, 'Seu código de acesso aos exercícios');
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