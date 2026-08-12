<?php

declare(strict_types=1);

namespace App\Support\Privacy;

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
 * - CPF (000.000.000-00 ou 00000000000)  // pii-allowlist (formato na doc, não é dado)
 * - CNPJ (00.000.000/0000-00 ou 00000000000000)  // pii-allowlist (formato na doc, não é dado)
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
        // Telefone BR: opcional +55, opcional DDD (XX), 8-9 dígitos.
        // Os lookarounds `(?<!\d)`/`(?!\d)` são o que impede casar um PEDAÇO de
        // número maior — os irmãos acima usam `\b`, este não usava. Sem eles,
        // liberar o CNPJ cru fazia o telefone herdar a colisão: em
        // `articles/21830391097367` o match virava `2183039109`, e o DDD 21
        // (Rio) EXISTE, então passava pelo desempate e era redigido.
        // Pego pelo teste desta emenda em 2026-08-03. O mesmo já quase
        // aconteceu no #5169 com o CPF — lá escapou por sorte, porque o run id
        // começava com `30`, DDD que não existe.
        'PHONE' => '/(?<!\d)(?:\+?55\s?)?\(?\d{2}\)?\s?9?\d{4}-?\d{4}(?!\d)/',
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
                    if (! $this->deveRedigir($type, $matches[0])) {
                        return $matches[0];
                    }

                    return $this->makeReplacement($type, $mode, $matches[0]);
                }, $output);
            }

            return $output;
        }, ['input_chars' => strlen($input), 'mode' => $mode]);
    }

    /**
     * Segundo filtro: o regex casou, mas isto é MESMO PII?
     *
     * Só o CPF CRU (11 dígitos sem pontuação) precisa deste desempate — e ele existe
     * porque `\d{11}` colide com QUALQUER número de 11 dígitos. Medido em produção
     * 2026-08-02, ao indexar o trio de tela: 32 "PII" redigidos em 8 `casos.md` eram
     * **run id do GitHub Actions** (`30366164436`), e o efeito foi apagar do índice
     * justamente o recibo de CI que o projeto exige como prova. Não era vazamento —
     * era o oposto, e degradava a rastreabilidade.
     *
     * O CPF tem dígito verificador; run id não. Validá-lo separa os dois sem afrouxar
     * nada: CPF real cru (`11144477735`) continua redigido.
     *
     * **CPF PONTUADO segue redigido SEMPRE**, com DV válido ou não — quem escreve
     * um CPF pontuado está declarando o que é, e formato explícito não pede prova.
     * Isso mantém coberto o caso de CPF digitado errado, que é dado inválido mas
     * ainda é tentativa de PII.
     */
    private function deveRedigir(string $type, string $match): bool
    {
        // Formatação é DECLARAÇÃO: quem escreve CPF pontuado ou telefone com DDD entre parênteses
        // está dizendo que aquilo é CPF/telefone. Formato explícito não pede prova —
        // inclusive porque CPF digitado errado ainda é tentativa de PII.
        if (preg_match('/[.\-()\s\/+]/', $match)) {
            return true;
        }

        // Daqui pra baixo o match é DÍGITO CRU, e é onde mora a colisão: qualquer
        // número comprido casa. O desempate é a regra de formação de cada tipo.
        return match ($type) {
            'CPF'   => $this->cpfTemDvValido($match),
            'CNPJ'  => $this->cnpjTemDvValido($match),
            'PHONE' => $this->temDddBrasileiro($match),
            default => true,
        };
    }

    /**
     * Dígito verificador do CNPJ (módulo 11). Mesma emenda do CPF, um nível acima:
     * `\d{14}` cru colide com QUALQUER número de 14 dígitos.
     *
     * Medido no corpus real 2026-08-03 (3.652 `.md`, 197 matches de CNPJ):
     *   - 92 formatados        -> seguem redigidos (declaração, não pedem prova)
     *   - 38 crus com DV VÁLIDO -> seguem redigidos (inclui CNPJ real de cliente)
     *   - 67 crus com DV inválido, em 22 docs -> LIBERADOS, e **todos** eram
     *     falso-positivo: `14628809617558@lid` (WhatsApp Multi-Device), id de
     *     artigo em URL de doc externa (`21830391097367`) e placeholder de exemplo.
     *
     * Assim como no CPF, o risco aceito é CNPJ real digitado CRU com DV errado
     * deixar de ser redigido — mitigado por qualquer formatação (ponto/barra/hífen)
     * devolver `true` acima.
     */
    private function cnpjTemDvValido(string $digitos): bool
    {
        if (strlen($digitos) !== 14 || preg_match('/^(\d)\1{13}$/', $digitos)) {
            return false;
        }

        foreach ([12, 13] as $posicao) {
            $peso = $posicao - 7;
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * $peso--;
                if ($peso < 2) {
                    $peso = 9;
                }
            }
            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;
            if ($dv !== (int) $digitos[$posicao]) {
                return false;
            }
        }

        return true;
    }

    /**
     * DDD que existe no Brasil. É o que separa `3036616443` (run id do GitHub, DDD
     * "30" inexistente) de `11987654321` (celular de São Paulo).
     *
     * Fonte: plano de numeração ANATEL — as faixas não usadas nunca foram alocadas.
     */
    private function temDddBrasileiro(string $digitos): bool
    {
        $ddd = (int) substr($digitos, 0, 2);

        return in_array($ddd, [
            11, 12, 13, 14, 15, 16, 17, 18, 19,
            21, 22, 24, 27, 28,
            31, 32, 33, 34, 35, 37, 38,
            41, 42, 43, 44, 45, 46, 47, 48, 49,
            51, 53, 54, 55,
            61, 62, 63, 64, 65, 66, 67, 68, 69,
            71, 73, 74, 75, 77, 79,
            81, 82, 83, 84, 85, 86, 87, 88, 89,
            91, 92, 93, 94, 95, 96, 97, 98, 99,
        ], true);
    }

    /** Dígito verificador do CPF (módulo 11). Repetidos (111...) são inválidos por definição. */
    private function cpfTemDvValido(string $digitos): bool
    {
        if (strlen($digitos) !== 11 || preg_match('/^(\d)\1{10}$/', $digitos)) {
            return false;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * (($posicao + 1) - $i);
            }
            $dv = ($soma * 10) % 11;
            if ($dv === 10) {
                $dv = 0;
            }
            if ($dv !== (int) $digitos[$posicao]) {
                return false;
            }
        }

        return true;
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
                // mesmo desempate do redact(): detect() e redact() não podem divergir,
                // senão o has_pii marca doc que o redact deixou intacto.
                $reais = array_filter($matches[0], fn (string $m) => $this->deveRedigir($type, $m));
                if ($reais !== []) {
                    $found[$type] = count($reais);
                }
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
