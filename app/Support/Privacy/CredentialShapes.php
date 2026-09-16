<?php

declare(strict_types=1);

namespace App\Support\Privacy;

/**
 * Shapes de CREDENCIAL (token, chave, senha) — o eixo que o {@see PiiRedactor}
 * NÃO cobre. Ele redige PII BR (CPF/CNPJ/EMAIL/CEP/telefone); aqui é segredo.
 *
 * ── POR QUE CLASSE IRMÃ, E NÃO MAIS ENTRADAS EM PiiRedactor::PATTERNS ────────
 * O `PiiRedactor` roda em caminho quente (log, resposta de assistente, audit).
 * Somar 11 regexes lá muda o comportamento de TODOS os consumidores dele, e o
 * FP nesses consumidores não foi medido — medir é a perna servidor da
 * US-FORJA-011, com decisão própria. Esta classe é a costura: quando aquela
 * perna for feita, o `PiiRedactor` consome ESTE vocabulário em vez de duplicá-lo.
 *
 * ── PARIDADE COM O LADO CLIENTE (obrigatória) ────────────────────────────────
 * Estes shapes são os MESMOS de `scripts/cc-watcher/redact.mjs` (Node, roda na
 * máquina do dev, redige antes do `fetch`). Runtimes diferentes obrigam duas
 * implementações; a duplicação é segura porque os dois lados rodam o MESMO
 * conjunto de vetores — `tests/fixtures/credential-shapes-vectors.json` —, o
 * PHP em `CredentialShapesTest` e o JS em `redact.test.mjs`. Mexeu num lado sem
 * o outro, o vetor quebra.
 *
 * ⚠️ `mcp_token` é `mcp_` + 64 HEX, DERIVADO DO GERADOR
 * ({@see \Modules\Jana\Entities\Mcp\McpToken::gerar} — `bin2hex(random_bytes(32))`,
 * tamanho fixado por teste em 68). A forma frouxa `mcp_[A-Za-z0-9_-]{16,}` dá
 * ~99,97% de falso-positivo porque casa NOME DE TOOL (`mcp__ccd_session__x`) —
 * medida e enterrada no §5 de `memory/proibicoes.md` (2026-09-16). Não afrouxe
 * sem re-medir.
 */
final class CredentialShapes
{
    /** [nome, regex, grupo a redigir (0 = casamento inteiro)] — ordem importa: específico antes de genérico. */
    public const SHAPES = [
        ['mcp_token',      '/\bmcp_[0-9a-f]{64}\b/', 0],
        ['anthropic_key',  '/\bsk-ant-[A-Za-z0-9_-]{20,}/', 0],
        ['openai_key',     '/\bsk-(?:proj-)?[A-Za-z0-9_-]{32,}/', 0],
        ['github_pat',     '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{36}\b/', 0],
        ['github_fine',    '/\bgithub_pat_[A-Za-z0-9_]{60,}/', 0],
        ['aws_akia',       '/\bAKIA[0-9A-Z]{16}\b/', 0],
        ['slack_token',    '/\bxox[baprs]-[A-Za-z0-9-]{10,}/', 0],
        ['private_key',    '/-----BEGIN [A-Z ]*PRIVATE KEY-----/', 0],
        ['jwt',            '/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/', 0],
        ['bearer',         '/(Bearer\s+)([A-Za-z0-9_.\-]{20,})/', 2],
        ['assign_generic', '/([A-Z][A-Z0-9_]*(?:TOKEN|SECRET|APIKEY|API_KEY|PASSWORD|PASSWD|PWD|ACCESS_KEY|PRIVATE_KEY)\s*["\']?\s*[=:]\s*["\']?)([A-Za-z0-9_\-\/+=.]{20,})/', 2],
    ];

    /**
     * Marcadores de valor fake/template. Vocabulário herdado do allowlist do
     * `.gitleaks.toml`, que já é tunado contra os falso-positivos DESTE repo.
     */
    private const PLACEHOLDER = '/(example|exemplo|fake|dummy|placeholder|changeme|your[-_]?token|xxx+|cole_seu|seu[-_]?token|\$\{|process\.env|getenv|env\(|config\(|\[REDACTED|redacted|fixture|<[a-z_]+>)/i';

    /** true se o valor é template/fixture — redigir só perderia sinal. */
    public static function ehPlaceholder(string $valor): bool
    {
        return (bool) preg_match(self::PLACEHOLDER, $valor);
    }

    /**
     * Redige shapes de credencial. Preserva o RÓTULO quando existe
     * (`DB_PASSWORD=[REDACTED:assign_generic]` ainda diz qual variável era).
     *
     * @param  array<string,int>  $hits  contagem por shape (saída, por referência)
     */
    public static function redigir(string $texto, array &$hits = []): string
    {
        if ($texto === '') {
            return $texto;
        }

        foreach (self::SHAPES as [$nome, $re, $grupo]) {
            $texto = preg_replace_callback($re, function (array $m) use ($nome, $grupo, &$hits): string {
                $valor = $grupo === 0 ? $m[0] : ($m[$grupo] ?? null);
                if ($valor === null || self::ehPlaceholder($valor)) {
                    return $m[0];
                }
                $hits[$nome] = ($hits[$nome] ?? 0) + 1;
                $prefixo = $grupo === 0 ? '' : substr($m[0], 0, strlen($m[0]) - strlen($valor));

                return $prefixo.'[REDACTED:'.$nome.']';
            }, $texto) ?? $texto;
        }

        return $texto;
    }
}
