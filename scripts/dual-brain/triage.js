/**
 * Triage rule-based v1 — sem LLM ainda.
 * Mapeia padrões observados (commit message, log entry) para event_type canônico do RiskEngine.
 *
 * V2 (futuro): substituir por chamada Ollama qwen2.5-coder com prompt de classificação.
 */

const COMMIT_PATTERNS = [
  // [regex, event_type, domain inference function]
  [/^migrate\(.*\):/i,                'db_schema_change',     m => inferDomain(m)],
  [/migration|create.*table|alter.*table/i, 'db_schema_change', m => inferDomain(m)],
  [/composer\.json|composer\.lock/i,  'composer_json_change', () => 'Infra'],
  [/auth|middleware.*auth/i,          'auth_middleware',      () => 'Security'],
  [/billing|cobranç|recurring/i,      'billing_financial_flow', () => 'RecurringBilling'],
  [/nfse|nfe|fiscal|iss/i,            'nfse_fiscal_logic',    () => 'NFSe'],
  [/lgpd|pii|cpf|cnpj/i,              'lgpd_data_handling',   () => 'Security'],
  [/^docs?\(/i,                       'md_link_fix',          m => inferDomain(m)],
  [/typo|typof?ix/i,                  'comment_typo',         m => inferDomain(m)],
  [/lang|tradu/i,                     'lang_file_pt_br',      m => inferDomain(m)],
  [/^test\(/i,                        'test_only_change',     m => inferDomain(m)],
  [/^refactor.*service/i,             'service_layer_refactor', m => inferDomain(m)],
  [/blade|view|inertia|react component/i, 'blade_view_ui_only', m => inferDomain(m)],
  [/adr-?\d+|frontmatter/i,           'adr_frontmatter_fix',  () => 'Memory'],
  [/session.*log|memory\/sessions/i,  'session_log_creation', () => 'Memory'],
  [/mcp.*sync|sync-memory/i,          'mcp_sync_memory',      () => 'MCP'],
];

const LOG_PATTERNS = [
  [/PDOException|SQLSTATE|database/i, 'db_schema_change',  () => 'Infra'],
  [/Auth.*Exception|Unauthenticated/i, 'auth_middleware',  () => 'Security'],
  [/CertificadoNaoEncontrado|nfse|nfe.*error/i, 'nfse_fiscal_logic', () => 'NFSe'],
  [/billing|payment|cobranç/i,        'billing_financial_flow', () => 'RecurringBilling'],
];

function inferDomain(message) {
  const map = [
    [/copiloto/i,         'Copiloto'],
    [/financeiro/i,       'Financeiro'],
    [/nfse|nfe/i,         'NFSe'],
    [/ponto|wr2/i,        'PontoWr2'],
    [/cms|landing/i,      'Cms'],
    [/repair/i,           'Repair'],
    [/manufacturing/i,    'Manufacturing'],
    [/grow/i,             'Grow'],
    [/memcofre/i,         'MemCofre'],
    [/recurring|billing/i, 'RecurringBilling'],
    [/officeimpresso/i,   'Officeimpresso'],
    [/ads|adaptive/i,     'ADS'],
    [/mcp/i,              'MCP'],
    [/memory|adr/i,       'Memory'],
  ];
  for (const [re, dom] of map) if (re.test(message)) return dom;
  return 'Unknown';
}

export function triageCommit({ subject, files }) {
  for (const [re, eventType, domainFn] of COMMIT_PATTERNS) {
    if (re.test(subject)) {
      return { eventType, domain: domainFn(subject) };
    }
  }
  return { eventType: 'unknown_commit', domain: inferDomain(subject) };
}

export function triageLogEntry({ line }) {
  for (const [re, eventType, domainFn] of LOG_PATTERNS) {
    if (re.test(line)) {
      return { eventType, domain: domainFn(line) };
    }
  }
  return null; // log não classificável vira ruído, não evento
}
