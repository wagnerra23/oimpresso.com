/**
 * OllamaClient — wrapper minimal para Ollama HTTP API.
 *
 * Usado para triage com qwen2.5-coder local em vez das regras regex.
 * Se OLLAMA_HOST não está setado ou o servidor não responde, o daemon
 * faz fallback para triage rule-based (triage.js).
 */

export class OllamaClient {
  constructor({ host = 'http://localhost:11434', model = 'qwen2.5-coder:14b', timeoutMs = 8000 }) {
    this.host    = host.replace(/\/+$/, '');
    this.model   = model;
    this.timeoutMs = timeoutMs;
  }

  async health() {
    try {
      const ctrl = new AbortController();
      const t = setTimeout(() => ctrl.abort(), 2000);
      const r = await fetch(`${this.host}/api/tags`, { signal: ctrl.signal });
      clearTimeout(t);
      return { ok: r.ok };
    } catch (e) {
      return { ok: false, reason: e.message };
    }
  }

  /**
   * Classifica um evento em event_type canônico do RiskEngine + domínio.
   *
   * @param {object} input
   * @param {'commit'|'log'} input.kind
   * @param {string} input.content
   * @returns {Promise<{eventType: string, domain: string, reasoning: string}|null>}
   */
  async classify({ kind, content }) {
    const prompt = this.buildPrompt(kind, content);

    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), this.timeoutMs);

    try {
      const response = await fetch(`${this.host}/api/generate`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        signal:  ctrl.signal,
        body: JSON.stringify({
          model:  this.model,
          prompt,
          stream: false,
          format: 'json',
          options: { temperature: 0.1, num_predict: 200 },
        }),
      });
      clearTimeout(t);

      if (!response.ok) return null;
      const data = await response.json();
      const parsed = JSON.parse(data.response);

      if (!parsed.event_type || !parsed.domain) return null;
      return {
        eventType: parsed.event_type,
        domain:    parsed.domain,
        reasoning: parsed.reasoning || '',
      };
    } catch (e) {
      return null; // qualquer falha → fallback rule-based no daemon
    }
  }

  buildPrompt(kind, content) {
    const eventTypes = [
      'env_production', 'append_only_table', 'auth_middleware', 'pii_direct_exposure',
      'delphi_contract', 'composer_production', 'db_trigger_removal', 'billing_financial_flow',
      'lgpd_data_handling', 'db_schema_change', 'composer_json_change', 'nfse_fiscal_logic',
      'security_rule_change', 'multi_tenant_scope', 'new_module_creation', 'service_layer_refactor',
      'blade_view_ui_only', 'migration_new_column', 'test_only_change', 'lang_file_pt_br',
      'adr_frontmatter_fix', 'md_link_fix', 'comment_typo', 'test_description_fix',
      'mcp_sync_memory', 'session_log_creation', 'unknown_commit',
    ];
    const domains = [
      'Copiloto', 'Financeiro', 'NFSe', 'PontoWr2', 'Cms', 'Repair', 'Manufacturing',
      'Grow', 'MemCofre', 'RecurringBilling', 'Officeimpresso', 'ADS', 'MCP', 'Memory',
      'Infra', 'Security', 'Unknown',
    ];

    return `Você é um classificador de eventos do projeto oimpresso ERP (Laravel).
Classifique o ${kind === 'commit' ? 'commit' : 'log de erro'} abaixo retornando JSON estrito:

{
  "event_type": "<um dos: ${eventTypes.join(', ')}>",
  "domain":     "<um dos: ${domains.join(', ')}>",
  "reasoning":  "<frase curta em PT-BR justificando>"
}

Conteúdo:
"""
${content.slice(0, 800)}
"""

Responda APENAS o JSON, sem markdown.`;
  }
}
