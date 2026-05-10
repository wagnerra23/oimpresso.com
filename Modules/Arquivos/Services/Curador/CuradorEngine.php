<?php

namespace Modules\Arquivos\Services\Curador;

use Modules\Arquivos\Entities\Arquivo;

/**
 * CuradorEngine — port server-side das 15+ regras de scripts/curador/lib/rules.mjs.
 *
 * ADR 0123 §5 + ADR 0124 (Curador). ParityTest obrigatório (US-ARQ-007)
 * compara JS×PHP com mesmas fixtures — divergência > 5% trava merge.
 *
 * Sprint 1 MVP: 6 regras críticas portadas (sensitive .env, .pfx/.rdp/.pem,
 * SSH keys, PII XML, credentials.json, CNAB bancos). Demais (memory, discard
 * Agent A, etc) viram Sprint 1 dia 3 — bastam pra fluxo NFe XML primeiro.
 *
 * Atenção (Agent C 2026-05-10 timezone drift): Date.now() JS vs time() PHP
 * podem divergir. Rules.mjs usa mtime do file system; aqui usamos $arquivo->created_at
 * pra auditoria — testes de mtime relative (regra 16 cert antigo, 11 atas) ficam
 * pra Sprint 1 dia 3 com fixtures de mtime fixo.
 *
 * @see scripts/curador/lib/rules.mjs (fonte de verdade JS)
 * @see memory/decisions/0124-curador-conhecimento-pipeline.md
 */
class CuradorEngine
{
    private const SENSITIVE_EXT = ['.pfx', '.p12', '.pem', '.key', '.crt', '.rdp', '.kdbx'];

    private const SSH_KEY_PREFIXES = ['id_rsa', 'id_ed25519', 'id_dsa', 'id_ecdsa'];

    private const ENV_TEMPLATE_RE = '/\.(example|sample|dist|template|test|local\.example)$/i';

    private const BANCO_KEYWORDS = [
        'BancoDoBrasil' => '/banco[ _]?do[ _]?brasil|bancodobrasil|\bbb\b/i',
        'Bradesco'      => '/bradesco/i',
        'CEF'           => '/\b(cef|caixa[ _]?economica)\b/i',
        'Itau'          => '/ita[uú]/i',
        'Santander'     => '/santander/i',
        'Sicoob'        => '/sicoob/i',
        'Sicred'        => '/sicred/i',
        'Unicred'       => '/unicred/i',
        'Banrisul'      => '/banrisul/i',
        'Cresol'        => '/cresol/i',
    ];

    /**
     * Classifica um Arquivo. Retorna array com bucket + metadata.
     * Espelha estrutura do classifyFile() em rules.mjs.
     */
    public function classify(Arquivo $arquivo): array
    {
        $basename = $arquivo->original_name;
        $lower    = strtolower($basename);
        $ext      = '.' . strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        // Regra 0: sensitive .env real (não example/sample/dist)
        if (preg_match('/^\.env(\b|\.|$)/', $lower) && ! preg_match(self::ENV_TEMPLATE_RE, $lower)) {
            return $this->result('sensitive', '_VAULT-PENDING/env-files/', ['env_secrets'], 'sensitive_env_real', 1.0);
        }

        // Regra 1: sensitive por extensão
        if (in_array($ext, self::SENSITIVE_EXT, true)) {
            return $this->result(
                'sensitive',
                '_VAULT-PENDING/by-extension/',
                [ltrim($ext, '.')],
                'sensitive_by_extension',
                1.0,
            );
        }

        // Regra 2: SSH keys
        foreach (self::SSH_KEY_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return $this->result('sensitive', '_VAULT-PENDING/ssh-keys/', ['ssh_key'], 'sensitive_ssh_key', 1.0);
            }
        }

        // Regra 3: PII XML cliente (path-based; storage_path do Arquivo)
        $path = $arquivo->storage_path ?? '';
        if (
            $ext === '.xml' &&
            preg_match('#[\\\\/](XML[ _-]?Clientes?|Clientes?)[\\\\/][^\\\\/]+\.xml$#i', $path)
        ) {
            return $this->result('sensitive', '_VAULT-PENDING/xml-clientes/', ['pii_nfe'], 'sensitive_pii_xml_cliente', 0.95);
        }

        // Regra 3b: credentials*.json
        if (preg_match('/credentials?.*\.json$/i', $basename)) {
            return $this->result(
                'sensitive',
                '_VAULT-PENDING/credentials-json/',
                ['credentials_json'],
                'sensitive_credentials_json',
                0.85,
            );
        }

        // Regra 9 (parcial): CNAB bancos
        if (preg_match('/cnab/i', $path) || preg_match('/cnab/i', $basename)) {
            foreach (self::BANCO_KEYWORDS as $banco => $re) {
                if (preg_match($re, $path) || preg_match($re, $basename)) {
                    return $this->result(
                        'memory',
                        "memory/requisitos/Financeiro/CNAB-{$banco}/",
                        [],
                        "cnab_{$banco}",
                        0.95,
                    );
                }
            }
        }

        // Fallback: bucket=active (caso comum de upload normal — NFe XML, ticket attach)
        return $this->result('active', null, [], 'no_rule_matched', 0.1);
    }

    private function result(
        string $bucket,
        ?string $subDestination,
        array $sensitiveFlags,
        string $ruleMatched,
        float $confidence,
    ): array {
        return [
            'bucket'           => $bucket,
            'sub_destination'  => $subDestination,
            'sensitive_flags'  => $sensitiveFlags,
            'rule_matched'     => $ruleMatched,
            'confidence'       => $confidence,
        ];
    }
}
