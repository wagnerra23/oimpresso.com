// 18 heurísticas determinísticas pra classificar arquivos sem custar Claude.
// Aprendidas da triagem manual 2026-05-09 (ADR 0121).
//
// Cada regra recebe: { path, sizeBytes, mtime, extension, basename, dirname, md5 }
// Retorna: { bucket, subDestination, sensitiveFlags, ruleMatched, confidence } | null
// null = não bateu, próxima regra tenta.
//
// Ordem importa: SENSITIVE > DUPLICATE > DISCARD > MEMORY-MATCH > AMBIGUOUS-FALLBACK.

// Atenção: `.env` em Node retorna extname="" (dotfile sem extensão).
// Portanto sensitive .env é tratado por basename match (regra dedicada abaixo).
const SENSITIVE_EXT = new Set([
  '.pfx', '.p12', '.pem', '.key', '.crt', '.rdp', '.kdbx',
]);

const SENSITIVE_NAME_PREFIXES = ['id_rsa', 'id_ed25519', 'id_dsa', 'id_ecdsa'];

// .env templates públicos (NÃO sensíveis — são exemplos de OSS)
const ENV_TEMPLATE_SUFFIXES = /\.(example|sample|dist|template|test|local\.example)$/i;

const BANCO_KEYWORDS = {
  'BancoDoBrasil': /\b(banco[ _]?do[ _]?brasil|bb)\b/i,
  'Bradesco': /bradesco/i,
  'CEF': /\b(cef|caixa[ _]?economica)\b/i,
  'Itau': /ita[uú]/i,
  'Santander': /santander/i,
  'Sicoob': /sicoob/i,
  'Sicred': /sicred/i,
  'Unicred': /unicred/i,
  'Banrisul': /banrisul/i,
  'Cresol': /cresol/i,
};

const MODULE_BY_KEYWORD = [
  { mod: 'Producao', re: /\b(produ[cç][aã]o|fabrica[cç][aã]o|apontamento)\b/i },
  { mod: 'Venda', re: /\b(venda|or[cç]amento|proposta|faturamento)\b/i },
  { mod: 'Produto', re: /\b(produto|tabela[ _]de[ _]pre[cç]o|varia[cç][aã]o|grade)\b/i },
  { mod: 'NfeBrasil', re: /\b(nfe|nfc-?e|sefaz|sintegra|sped|efd|icms|cfop|ncm|tipi)\b/i },
  { mod: 'Financeiro', re: /\b(financeiro|fr0090|conta|caixa|banco|boleto|cnab|conciliacao)\b/i },
  { mod: 'Compra', re: /\bcompra\b/i },
  { mod: 'Cms', re: /\b(landing|blog|chatwoot|evolution[ _]?api)\b/i },
  { mod: 'Suporte', re: /\b(suporte|atendimento|chamado|faq|kb)\b/i },
  { mod: 'Officeimpresso', re: /\b(office[ _-]?impresso|relatorio|kpi|fastreport)\b/i },
];

const SIX_MONTHS_MS = 1000 * 60 * 60 * 24 * 30 * 6;
const TWELVE_MONTHS_MS = 1000 * 60 * 60 * 24 * 365;

function ageMs(mtime) {
  return Date.now() - new Date(mtime).getTime();
}

function inferModule(path) {
  for (const { mod, re } of MODULE_BY_KEYWORD) {
    if (re.test(path)) return mod;
  }
  return 'Officeimpresso'; // fallback
}

const RULES = [
  // === SENSITIVE ===

  // 0. Sensitive .env real (basename starts com ".env" e NÃO termina com .example/.sample/.dist/etc)
  // Ordem importa — esta regra precede sensitive_by_extension porque .env é dotfile sem extname em Node.
  (f) => {
    const lower = f.basename.toLowerCase();
    if (/^\.env(\b|\.|$)/.test(lower) && !ENV_TEMPLATE_SUFFIXES.test(lower)) {
      return {
        bucket: 'sensitive',
        subDestination: '_VAULT-PENDING/env-files/',
        sensitiveFlags: ['env_secrets'],
        ruleMatched: 'sensitive_env_real',
        confidence: 1.0,
      };
    }
    return null;
  },

  // 1. Sensitive por extensão
  (f) => {
    if (SENSITIVE_EXT.has(f.extension.toLowerCase())) {
      return {
        bucket: 'sensitive',
        subDestination: '_VAULT-PENDING/by-extension/',
        sensitiveFlags: [f.extension.replace('.', '')],
        ruleMatched: 'sensitive_by_extension',
        confidence: 1.0,
      };
    }
    return null;
  },

  // 2. SSH keys / KeePass
  (f) => {
    const lower = f.basename.toLowerCase();
    if (SENSITIVE_NAME_PREFIXES.some((p) => lower.startsWith(p))) {
      return {
        bucket: 'sensitive',
        subDestination: '_VAULT-PENDING/ssh-keys/',
        sensitiveFlags: ['ssh_key'],
        ruleMatched: 'sensitive_ssh_key',
        confidence: 1.0,
      };
    }
    return null;
  },

  // 3. PII NF-e (XML em pasta Cliente)
  (f) => {
    if (
      f.extension.toLowerCase() === '.xml' &&
      /[\\/]XML[ _-]?Clientes?[\\/]|[\\/]Clientes?[\\/].*\.xml$/i.test(f.path)
    ) {
      return {
        bucket: 'sensitive',
        subDestination: '_VAULT-PENDING/xml-clientes/',
        sensitiveFlags: ['pii_nfe'],
        ruleMatched: 'sensitive_pii_xml_cliente',
        confidence: 0.95,
      };
    }
    return null;
  },

  // 16. Cert/PFX antigo
  (f) => {
    const ext = f.extension.toLowerCase();
    if ((ext === '.pfx' || ext === '.p12') && ageMs(f.mtime) > TWELVE_MONTHS_MS) {
      return {
        bucket: 'sensitive',
        subDestination: '_VAULT-PENDING/certificates/',
        sensitiveFlags: ['certificate', 'expired_likely'],
        ruleMatched: 'sensitive_old_cert',
        confidence: 0.9,
      };
    }
    return null;
  },

  // === DISCARD ===

  // 4. Duplicata por hash (caller injeta `f.isDuplicate` + `f.duplicateOf`)
  (f) => {
    if (f.isDuplicate) {
      return {
        bucket: 'discard',
        subDestination: '_DESCARTADO/duplicates/',
        sensitiveFlags: [],
        ruleMatched: `duplicate_of:${f.duplicateOf}`,
        confidence: 1.0,
      };
    }
    return null;
  },

  // 1. Clones OSS
  (f) => {
    if (
      /[\\/]node_modules[\\/]/.test(f.path) ||
      /[\\/]\.git[\\/]objects[\\/]/.test(f.path) ||
      /[\\/]\.git[\\/]refs[\\/]/.test(f.path)
    ) {
      return {
        bucket: 'discard',
        subDestination: '_DESCARTADO/oss-clones/',
        sensitiveFlags: [],
        ruleMatched: 'oss_clone_path',
        confidence: 1.0,
      };
    }
    return null;
  },

  // 18. README/CHANGELOG OSS gigante
  (f) => {
    const lower = f.basename.toLowerCase();
    if (
      (lower === 'readme.md' || lower === 'changelog.md' || lower === 'security.md' ||
        lower === 'code_of_conduct.md' || lower === 'contributing.md') &&
      f.sizeBytes > 50 * 1024 &&
      !/[\\/]memory[\\/]/.test(f.path)
    ) {
      return {
        bucket: 'discard',
        subDestination: '_DESCARTADO/oss-docs/',
        sensitiveFlags: [],
        ruleMatched: 'oss_readme_large',
        confidence: 0.9,
      };
    }
    return null;
  },

  // 11. Atas/Pautas antigas
  (f) => {
    if (/\b(ata|pauta)[ _]?(de[ _]?)?(reuni[aã]o)?\b/i.test(f.basename) && ageMs(f.mtime) > TWELVE_MONTHS_MS) {
      return {
        bucket: 'discard',
        subDestination: '_DESCARTADO/atas-antigas/',
        sensitiveFlags: [],
        ruleMatched: 'old_meeting_notes',
        confidence: 0.85,
      };
    }
    return null;
  },

  // === MEMORY (positive matches) ===

  // 9. CNAB bancos
  (f) => {
    if (/\bcnab\b/i.test(f.path)) {
      for (const [banco, re] of Object.entries(BANCO_KEYWORDS)) {
        if (re.test(f.path)) {
          return {
            bucket: 'memory',
            subDestination: `memory/requisitos/Financeiro/CNAB-${banco}/`,
            sensitiveFlags: [],
            ruleMatched: `cnab_${banco}`,
            confidence: 0.95,
          };
        }
      }
    }
    return null;
  },

  // 10. SPED/EFD/SEFAZ
  (f) => {
    if (/\b(sped|efd|sefaz|nfe|sintegra|icms[ _]?ipi)\b/i.test(f.basename)) {
      return {
        bucket: 'memory',
        subDestination: 'memory/requisitos/NfeBrasil/',
        sensitiveFlags: [],
        ruleMatched: 'fiscal_sped',
        confidence: 0.85,
      };
    }
    return null;
  },

  // 8. Office Comercial Delphi legacy
  (f) => {
    if (
      /[\\/]Manuais[ _]T[eé]cnicos[\\/]/.test(f.path) ||
      /[\\/]TelasDoSistema[\\/]/.test(f.path) ||
      /[\\/]Manuais_de_Usu[aá]rio[\\/]/.test(f.path)
    ) {
      const mod = inferModule(f.path + ' ' + f.basename);
      return {
        bucket: 'memory',
        subDestination: `memory/requisitos/${mod}/legacy-spec/`,
        sensitiveFlags: [],
        ruleMatched: `office_comercial_legacy_${mod}`,
        confidence: 0.7,
      };
    }
    return null;
  },

  // 7. PDF/DOCX grande → INDEX-no-git
  (f) => {
    const ext = f.extension.toLowerCase();
    if (
      (ext === '.pdf' || ext === '.docx' || ext === '.xlsx') &&
      f.sizeBytes > 1024 * 1024
    ) {
      const mod = inferModule(f.path + ' ' + f.basename);
      return {
        bucket: 'memory',
        subDestination: `memory/requisitos/${mod}/INDEX/`,
        sensitiveFlags: ['large_binary_index_only'],
        ruleMatched: 'large_binary_indexed',
        confidence: 0.7,
      };
    }
    return null;
  },

  // === AMBIGUOUS (precisa Claude) ===

  // 6. Wishlist antiga
  (f) => {
    const lower = f.basename.toLowerCase();
    if ((lower === 'todo.md' || lower === 'todo.txt') && ageMs(f.mtime) > SIX_MONTHS_MS) {
      return {
        bucket: 'ambiguous',
        subDestination: null,
        sensitiveFlags: [],
        ruleMatched: 'old_todo_review_needed',
        confidence: 0.5,
      };
    }
    return null;
  },

  // 12. Tamanho zero
  (f) => {
    if (f.sizeBytes === 0) {
      return {
        bucket: 'ambiguous',
        subDestination: null,
        sensitiveFlags: [],
        ruleMatched: 'empty_file',
        confidence: 0.3,
      };
    }
    return null;
  },

  // 15. Texto curto
  (f) => {
    const ext = f.extension.toLowerCase();
    if ((ext === '.md' || ext === '.txt') && f.sizeBytes < 1024) {
      return {
        bucket: 'ambiguous',
        subDestination: null,
        sensitiveFlags: [],
        ruleMatched: 'short_scrap',
        confidence: 0.3,
      };
    }
    return null;
  },
];

// Default fallback: ambiguous.
const FALLBACK = (f) => ({
  bucket: 'ambiguous',
  subDestination: null,
  sensitiveFlags: [],
  ruleMatched: 'no_rule_matched',
  confidence: 0.1,
});

export function classifyFile(file) {
  for (const rule of RULES) {
    const result = rule(file);
    if (result) return result;
  }
  return FALLBACK(file);
}

export const RULE_COUNT = RULES.length;
