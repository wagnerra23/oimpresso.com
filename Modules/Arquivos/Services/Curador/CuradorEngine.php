<?php

namespace Modules\Arquivos\Services\Curador;

use Modules\Arquivos\Entities\Arquivo;

/**
 * CuradorEngine — port server-side das 15+ regras de scripts/curador/lib/rules.mjs.
 *
 * ADR 0123 §5 + ADR 0124 (Curador). ParityTest obrigatório (US-ARQ-007)
 * compara JS×PHP com mesmas fixtures — divergência > 5% trava merge.
 *
 * Sprint 1 dia 3 — 18 regras portadas:
 *   SENSITIVE: 0 .env_real, 1 by_extension, 2 ssh_key, 3 pii_xml_cliente,
 *              3b credentials_json
 *   DISCARD:   1 oss_clone_path, 1b oss_software_folder, 1c docs_git_internals,
 *              18 oss_readme_large, 11 old_meeting_notes
 *   MEMORY:    R2 branding_jana, R3 branding_office_impresso, R4 kb_legacy_faq,
 *              R5 infra_portainer_stack, R6 infra_evolution_api,
 *              R7 atas_historicas_docs, R8 kpis_historicos, 9 cnab_<banco>,
 *              10 fiscal_sped, 8 office_comercial_legacy_<mod>,
 *              7 large_binary_indexed
 *   FALLBACK:  active (anexo comum NFe/ticket)
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

    /** Módulos canônicos REAIS (Agent B 2026-05-10 — não slugs PT-BR). */
    private const MODULE_BY_KEYWORD = [
        ['mod' => 'Manufacturing',     're' => '/\b(produ[cç][aã]o|fabrica[cç][aã]o|apontamento|composi[cç][aã]o)\b/i'],
        ['mod' => 'NfeBrasil',         're' => '/\b(nfe|nfc-?e|sefaz|sintegra|sped|efd|icms|cfop|ncm|tipi|regime[ _]?tribut[aá]rio|consumidor|inscri[cç][aã]o[ _]estadual|cest|conv[eê]nio[ _]?icms)\b/i'],
        ['mod' => 'Financeiro',        're' => '/\b(financeiro|fr0090|conta[ _]?banc[aá]ria|caixa|boleto|cnab|concilia[cç][aã]o|plano[ _]de[ _]conta)\b/i'],
        ['mod' => 'ProductCatalogue',  're' => '/\b(produto|tabela[ _]de[ _]pre[cç]o|varia[cç][aã]o|grade|m[oó]dulo[ _]produto)\b/i'],
        ['mod' => 'Officeimpresso',    're' => '/\b(venda|or[cç]amento|proposta|faturamento|compra|caixa fechado|m[oó]dulo[ _]?venda|m[oó]dulo[ _]?compra)\b/i'],
        ['mod' => 'Cms',               're' => '/\b(landing|blog|chatwoot|evolution[ _]?api|chatwr2)\b/i'],
        ['mod' => 'KB',                're' => '/\b(suporte|atendimento|chamado|faq|kb|knowledge[ _]?base|artigo[ _]?suporte)\b/i'],
        ['mod' => 'Crm',               're' => '/\b(crm|cliente|fornecedor|contato|lead|pipeline)\b/i'],
        ['mod' => 'Jana',              're' => '/\b(prompts?[ _]?jana|jana[ _]?ai|copiloto|janaai|dify|llm)\b/i'],
        ['mod' => 'Officeimpresso',    're' => '/\b(office[ _-]?impresso|relat[oó]rio|kpi|fastreport)\b/i'],
    ];

    private const TWELVE_MONTHS_SECS = 31_536_000;

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

        // === DISCARD ===

        // Regra 1: Clones OSS path (node_modules / .git/objects / .git/refs)
        if (
            preg_match('#[\\\\/]node_modules[\\\\/]#', $path) ||
            preg_match('#[\\\\/]\.git[\\\\/]objects[\\\\/]#', $path) ||
            preg_match('#[\\\\/]\.git[\\\\/]refs[\\\\/]#', $path)
        ) {
            return $this->result('discard', '_DESCARTADO/oss-clones/', [], 'oss_clone_path', 1.0);
        }

        // Regra 1b (Agent A): pasta D:\Conhecimento\Software\ é convenção Wagner pra clones
        if (preg_match('#[\\\\/]Conhecimento[\\\\/]Software[\\\\/]#i', $path)) {
            return $this->result('discard', '_DESCARTADO/oss-software-folder/', [], 'oss_software_folder', 1.0);
        }

        // Regra 1c (Agent A): Docs/.git/* internals
        if (preg_match('#[\\\\/]Docs[\\\\/]\.git[\\\\/]#i', $path)) {
            return $this->result('discard', '_DESCARTADO/oss-clones/', [], 'docs_git_internals', 1.0);
        }

        // Regra 18: README/CHANGELOG OSS gigante (>50KB fora memory/)
        $sizeBytes = (int) ($arquivo->size_bytes ?? 0);
        if (
            in_array($lower, ['readme.md', 'changelog.md', 'security.md', 'code_of_conduct.md', 'contributing.md'], true) &&
            $sizeBytes > 50 * 1024 &&
            ! preg_match('#[\\\\/]memory[\\\\/]#', $path)
        ) {
            return $this->result('discard', '_DESCARTADO/oss-docs/', [], 'oss_readme_large', 0.9);
        }

        // Regra 11: atas antigas (mtime > 12 meses)
        if (preg_match('/\b(ata|pauta)[ _]?(de[ _]?)?(reuni[aã]o)?\b/i', $basename)) {
            $mtime = $arquivo->updated_at ?? $arquivo->created_at;
            if ($mtime && $mtime->diffInSeconds(now()) > self::TWELVE_MONTHS_SECS) {
                return $this->result('discard', '_DESCARTADO/atas-antigas/', [], 'old_meeting_notes', 0.85);
            }
        }

        // === MEMORY (positive matches) ===

        // (Agent A R2) Imagens/Jana — branding
        if (preg_match('#[\\\\/]Imagens[\\\\/]Jana[\\\\/]#i', $path)) {
            return $this->result('memory', 'memory/branding/jana/', [], 'branding_jana', 0.9);
        }

        // (Agent A R3) Imagens/Office Impresso — branding produto
        if (preg_match('#[\\\\/]Imagens[\\\\/]Office[ _]?Impresso[\\\\/]#i', $path)) {
            return $this->result('memory', 'memory/branding/office-impresso/', [], 'branding_office_impresso', 0.9);
        }

        // (Agent A R4) Suporte/Base de Conhecimento — KB FAQs
        if (preg_match('#[\\\\/]Suporte ao Cliente[\\\\/]Base de Conhecimento#i', $path)) {
            return $this->result('memory', 'memory/requisitos/KB/legacy-faqs/', [], 'kb_legacy_faq', 0.9);
        }

        // (Agent A R5) Infraestrutura/Portainer Docker stacks
        if (preg_match('#[\\\\/]Infraestrutura[ _&-]+Opera[cç][oõ]es[\\\\/]Portainer[\\\\/]Docker[\\\\/].*\.ya?ml$#i', $path)) {
            return $this->result('memory', 'memory/requisitos/Infra/portainer-stacks/', [], 'infra_portainer_stack', 0.95);
        }

        // (Agent A R6) Infraestrutura/Evolution API yamls
        if (preg_match('#[\\\\/]Infraestrutura[ _&-]+Opera[cç][oõ]es[\\\\/]Evolution[ _]?API#i', $path)) {
            return $this->result('memory', 'memory/requisitos/Infra/evolution-api/', [], 'infra_evolution_api', 0.95);
        }

        // (Agent A R7) Docs/Atas — atas históricas
        if (preg_match('#[\\\\/]Docs[\\\\/]Atas#i', $path)) {
            return $this->result('memory', 'memory/sessions/atas-historicas/', [], 'atas_historicas_docs', 0.7);
        }

        // (Agent A R8) Docs/Projeto/KPIS — KPIs export Notion
        if (preg_match('#[\\\\/]Docs[\\\\/]Projeto[\\\\/]KPIS[\\\\/]#i', $path)) {
            return $this->result('memory', 'memory/requisitos/Officeimpresso/kpis-historicos/', [], 'kpis_historicos', 0.85);
        }

        // Regra 9: CNAB bancos (regex sem boundary — pega Cnab400, CNAB_240)
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

        // Regra 10: SPED/EFD/SEFAZ
        if (preg_match('/\b(sped|efd|sefaz|nfe|sintegra|icms[ _]?ipi)\b/i', $basename)) {
            return $this->result('memory', 'memory/requisitos/NfeBrasil/', [], 'fiscal_sped', 0.85);
        }

        // Regra 8: Office Comercial Delphi legacy
        if (
            preg_match('#[\\\\/]Manuais[ _]T[eé]cnicos[\\\\/]#', $path) ||
            preg_match('#[\\\\/]TelasDoSistema[\\\\/]#', $path) ||
            preg_match('#[\\\\/]Manuais_de_Usu[aá]rio[\\\\/]#', $path)
        ) {
            $mod = $this->inferModule($path . ' ' . $basename);
            return $this->result(
                'memory',
                "memory/requisitos/{$mod}/legacy-spec/",
                [],
                "office_comercial_legacy_{$mod}",
                0.7,
            );
        }

        // Regra 7: PDF/DOCX grande → INDEX-no-git
        if (in_array($ext, ['.pdf', '.docx', '.xlsx'], true) && $sizeBytes > 1024 * 1024) {
            $mod = $this->inferModule($path . ' ' . $basename);
            return $this->result(
                'memory',
                "memory/requisitos/{$mod}/INDEX/",
                ['large_binary_index_only'],
                'large_binary_indexed',
                0.7,
            );
        }

        // Fallback: bucket=active (caso comum de upload normal — NFe XML, ticket attach)
        return $this->result('active', null, [], 'no_rule_matched', 0.1);
    }

    /**
     * Match path/basename contra MODULE_BY_KEYWORD (módulos canon Modules/).
     * Fallback `_inbox` (não Officeimpresso — Agent B 2026-05-10: poluiria canon).
     */
    private function inferModule(string $haystack): string
    {
        foreach (self::MODULE_BY_KEYWORD as $entry) {
            if (preg_match($entry['re'], $haystack)) {
                return $entry['mod'];
            }
        }
        return '_inbox';
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
