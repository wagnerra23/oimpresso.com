/**
 * Cliente HTTP para POST /api/ads/route.
 * Sem dependências externas — usa fetch nativo do Node 18+.
 */

export class AdsClient {
  constructor({ apiUrl, apiKey, healthUrl, allowInsecureTls = false }) {
    if (!apiUrl)  throw new Error('ADS_API_URL ausente');
    if (!apiKey)  throw new Error('ADS_API_KEY ausente');
    this.apiUrl    = apiUrl;
    this.apiKey    = apiKey;
    this.healthUrl = healthUrl;

    // Dev local com Herd usa cert self-signed — aceitar se solicitado explicitamente
    if (allowInsecureTls) {
      process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
    }
  }

  async health() {
    if (!this.healthUrl) return { ok: false, reason: 'health_url_missing' };
    try {
      const r = await fetch(this.healthUrl, { method: 'GET' });
      return { ok: r.ok, status: r.status };
    } catch (e) {
      return { ok: false, reason: e.message };
    }
  }

  /**
   * @param {object} event
   * @param {string} event.eventType
   * @param {string} event.domain
   * @param {'brain_a'|'evolution_agent'|'wagner'|'scheduler'} event.eventSource
   * @param {number} event.businessId
   * @param {string[]} [event.filesAffected]
   * @param {object}   [event.metadata]
   */
  async route(event) {
    const body = {
      event_type:     event.eventType,
      domain:         event.domain,
      event_source:   event.eventSource,
      business_id:    event.businessId,
      files_affected: event.filesAffected ?? [],
      metadata:       event.metadata ?? {},
    };

    const response = await fetch(this.apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type':  'application/json',
        'Accept':        'application/json',
        'Authorization': `Bearer ${this.apiKey}`,
      },
      body: JSON.stringify(body),
    });

    const text = await response.text();
    let data;
    try { data = JSON.parse(text); } catch { data = { raw: text }; }

    if (!response.ok) {
      throw new Error(`ADS route failed (${response.status}): ${JSON.stringify(data)}`);
    }
    return data;
  }
}
