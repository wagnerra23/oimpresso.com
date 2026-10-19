<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lint de linguagem Anvisa (etapas/01-biblioteca.md B-06 · UC-FBI-12; ADR 0437 §5).
 *
 * O Fisio é apoio ao fisioterapeuta e não pode prometer resultado clínico: texto que a clínica
 * salva e que o paciente pode ler (exercício próprio, protocolo, instruções do programa) é recusado
 * com 422 se contiver um termo da lista `config('fisio.linguagem_anvisa.termos_proibidos')`, que é a
 * cópia literal da ADR. A mensagem diz qual termo foi barrado e por quê.
 *
 * Comparação: sem maiúscula, sem acento e por palavra inteira. "Trata" barra; "tratamento" passa.
 * Termo de duas palavras aceita qualquer espaço entre elas ("corrige   postura").
 */
final class LinguagemAnvisa
{
    /**
     * Recusa (422) o primeiro termo proibido encontrado em cada campo.
     *
     * @param  array<string, mixed>  $campos  campo do request => texto, lista de textos ou nulo
     *
     * @throws ValidationException
     */
    public static function exigirLimpo(array $campos): void
    {
        $erros = [];
        foreach ($campos as $campo => $valor) {
            $termo = self::termoProibido($valor);
            if ($termo !== null) {
                $erros[$campo] = self::mensagem($termo);
            }
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }
    }

    /** Primeiro termo proibido presente no valor (texto ou lista de textos), ou null. */
    public static function termoProibido(mixed $valor): ?string
    {
        $textos = is_array($valor) ? $valor : [$valor];
        foreach ($textos as $texto) {
            if (is_array($texto)) {
                $achado = self::termoProibido($texto);
                if ($achado !== null) {
                    return $achado;
                }

                continue;
            }
            if (! is_string($texto) || trim($texto) === '') {
                continue;
            }
            $normalizado = self::normalizar($texto);
            foreach (self::termos() as $termo) {
                $padrao = '/(?<![a-z0-9])'.str_replace(' ', '\s+', preg_quote(self::normalizar($termo), '/')).'(?![a-z0-9])/';
                if (preg_match($padrao, $normalizado) === 1) {
                    return $termo;
                }
            }
        }

        return null;
    }

    public static function mensagem(string $termo): string
    {
        return "O texto usa \"{$termo}\", que promete resultado clínico. Pela regra da Anvisa (RDC 657/2022), "
            .'o Fisio é apoio ao fisioterapeuta e a decisão clínica é do profissional. Reescreva sem essa palavra.';
    }

    /** @return list<string> */
    private static function termos(): array
    {
        return array_values(array_filter(
            (array) config('fisio.linguagem_anvisa.termos_proibidos', []),
            fn ($t) => is_string($t) && trim($t) !== '',
        ));
    }

    private static function normalizar(string $texto): string
    {
        return Str::ascii(mb_strtolower(trim($texto)));
    }
}
