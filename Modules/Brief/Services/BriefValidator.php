<?php

namespace Modules\Brief\Services;

use App\Util\OtelHelper;

/**
 * Validador do markdown gerado pelo Brain B.
 *
 * Garante 4 invariantes (ver ADR 0091):
 * 1. 7 headers exatos, na ordem correta
 * 2. Termina com \n---END---
 * 3. ≤3500 tokens estimados (~4 chars/token PT-BR)
 * 4. Sem PII de cliente final (CPF/CNPJ)
 *
 * Ver memory/sprints/s1-daily-brief/03-prompt-generator.md.
 *
 * D9 observabilidade (Wave 17): validação wrapped em OtelHelper::spanBiz
 * pra medir custo da regex de PII em produção.
 */
final class BriefValidator
{
    public const REQUIRED_HEADERS = [
        '## ESTADO MACRO',
        '## EM VOO AGORA',
        '## DECISÕES RECENTES (24h)',
        '## SKILLS USO 7d',
        '## CHARTERS APODRECENDO',
        '## FLAGS',
        '## METADATA',
    ];

    public const MAX_TOKENS = 3500;

    public function validate(string $content): ValidationResult
    {
        return OtelHelper::spanBiz('brief.validate', fn () => $this->doValidate($content), [
            'content_length' => mb_strlen($content),
        ]);
    }

    private function doValidate(string $content): ValidationResult
    {
        // 1) Headers presentes e na ordem exata
        $lastPos = -1;
        foreach (self::REQUIRED_HEADERS as $h) {
            $pos = strpos($content, $h);
            if ($pos === false || $pos <= $lastPos) {
                return ValidationResult::fail("missing_or_misordered: {$h}");
            }
            $lastPos = $pos;
        }

        // 2) Termina com sentinela ---END---
        if (!str_ends_with(trim($content), '---END---')) {
            return ValidationResult::fail('missing_end_sentinel');
        }

        // 3) Token count <= 3500 (estimativa: ~4 chars/token PT-BR)
        $estimatedTokens = (int) ceil(mb_strlen($content) / 4);
        if ($estimatedTokens > self::MAX_TOKENS) {
            return ValidationResult::fail("token_overflow:{$estimatedTokens}");
        }

        // 4) Sem PII de cliente final (CPF/CNPJ)
        if (preg_match('/\d{3}\.\d{3}\.\d{3}-\d{2}/', $content)
            || preg_match('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', $content)) {
            return ValidationResult::fail('pii_leaked');
        }

        return ValidationResult::ok($estimatedTokens);
    }
}
