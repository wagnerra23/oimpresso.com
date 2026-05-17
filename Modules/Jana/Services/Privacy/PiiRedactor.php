<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Privacy;

use App\Util\OtelHelper;

/**
 * PII Redactor BR — Constituição Art. 4 (Compliance LGPD Art. 7º).
 *
 * Redaciona PII brasileiro em strings antes de:
 * - Enviar pra LLMs externos (OpenAI, Anthropic — provedores fora BR)
 * - Logar em arquivos
 * - Persistir em audit log
 * - Exibir em UI compartilhada (cross-tenant)
 *
 * Coberto:
 * - CPF (000.000.000-00 ou 00000000000)
 * - CNPJ (00.000.000/0000-00 ou 00000000000000)
 * - Email
 * - Telefone BR (com ou sem DDD, com ou sem +55)
 * - CEP (00000-000 ou 00000000)
 *
 * Não-coberto (TODO próxima iteração):
 * - RG (formatos variam por estado)
 * - Cartão de crédito (PCI-DSS exige solução dedicada)
 * - Endereço completo
 *
 * Estratégia:
 * - Default: substitui por placeholder com tipo ([REDACTED:CPF], [REDACTED:EMAIL])
 * - Modo hash: substitui por hash determinístico curto (pra cross-reference sem revelar)
 *
 * NÃO usa este service pra dados que vão ficar em DB do PRÓPRIO tenant
 * (esses são dados legítimos do business). Apenas pra outputs externos.
 */
class PiiRedactor
{
    public const PLACEHOLDER_FORMAT = '[REDACTED:%s]';

    /**
     * Padrões regex em ordem de aplicação. Ordem importa: emails antes de
     * telefone (email pode conter dígitos), CPF/CNPJ antes de números genéricos.
     */
    private const PATTERNS = [
        'EMAIL' => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        'CNPJ'  => '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/',
        'CPF'   => '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
        'CEP'   => '/\b\d{5}-?\d{3}\b/',
        // Telefone BR: opcional +55, opcional DDD (XX), 8-9 dígitos
        'PHONE' => '/(?:\+?55\s?)?\(?\d{2}\)?\s?9?\d{4}-?\d{4}/',
    ];

    /**
     * Redaciona PII na string fornecida.
     *
     * @param  string  $input  Texto possivelmente contendo PII
     * @param  string  $mode   'placeholder' (default) | 'hash' | 'remove'
     * @return string  Texto redactado
     */
    public function redact(string $input, string $mode = 'placeholder'): string
    {
        return OtelHelper::spanBiz('jana.privacy.pii_redact', function () use ($input, $mode) {
            if ($input === '') return $input;

            $output = $input;
            foreach (self::PATTERNS as $type => $regex) {
                $output = preg_replace_callback($regex, function ($matches) use ($type, $mode) {
                    return $this->makeReplacement($type, $mode, $matches[0]);
                }, $output);
            }

            return $output;
        }, ['input_chars' => strlen($input), 'mode' => $mode]);
    }

    /**
     * Detecta se string contém PII (sem redactar). Útil pra alertas + audit.
     *
     * @return array<string,int> Map de tipo → contagem de matches
     */
    public function detect(string $input): array
    {
        $found = [];
        foreach (self::PATTERNS as $type => $regex) {
            if (preg_match_all($regex, $input, $matches)) {
                $found[$type] = count($matches[0]);
            }
        }
        return $found;
    }

    /**
     * Redaciona array recursivamente. Útil pra arrays JSON antes de log.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function redactArray(array $data, string $mode = 'placeholder'): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $out[$key] = $this->redact($value, $mode);
            } elseif (is_array($value)) {
                $out[$key] = $this->redactArray($value, $mode);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private function makeReplacement(string $type, string $mode, ?string $original = null): string
    {
        if ($mode === 'remove') {
            return '';
        }
        if ($mode === 'hash' && $original !== null) {
            $short = substr(hash('sha256', $original), 0, 8);
            return sprintf('[REDACTED:%s:%s]', $type, $short);
        }
        return sprintf(self::PLACEHOLDER_FORMAT, $type);
    }
}
