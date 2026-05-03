/**
 * RemoteLogWatcher (v2) — substitui LogWatcher v1.
 *
 * Não lê laravel.log direto. Faz HTTP poll em GET /api/ads/recent-errors com
 * paginação por byte offset. Inicializa offset = tamanho atual ao boot (não
 * reprocessa erros antigos).
 *
 * Topologia: ARQ-0011.
 */

export class RemoteLogWatcher {
  constructor({ apiUrl, apiKey, intervalMs = 5000, onError }) {
    if (!apiUrl) throw new Error('RemoteLogWatcher: apiUrl ausente');
    if (!apiKey) throw new Error('RemoteLogWatcher: apiKey ausente');
    this.apiUrl   = apiUrl.replace(/\/+$/, '');
    this.apiKey   = apiKey;
    this.intervalMs = intervalMs;
    this.onError  = onError;
    this.offset   = null;
    this.timer    = null;
  }

  async start() {
    // Bootstrap: primeira chamada com since=999999999 retorna offset atual com errors=[]
    // Como não temos endpoint dedicado de "tamanho", usamos resposta da primeira chamada.
    // Trick: chamamos com since=0 limit=1 só pra capturar offset atual; descartamos erros.
    const probe = await this.fetch(0, 1);
    this.offset = probe?.offset ?? 0;
    console.log(`[log-remote] watcher inicializado @ offset ${this.offset}`);
    this.timer = setInterval(() => this.tick().catch(e => console.error('[log-remote] tick:', e.message)), this.intervalMs);
  }

  stop() {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
  }

  async tick() {
    if (this.offset === null) {
      const probe = await this.fetch(0, 1);
      this.offset = probe?.offset ?? 0;
      return;
    }

    const result = await this.fetch(this.offset, 50);
    if (!result || !Array.isArray(result.errors)) return;

    for (const err of result.errors) {
      try {
        await this.onError({
          line: `[${err.datetime || ''}] ${err.level}: ${err.message}`,
        });
      } catch (e) {
        console.error('[log-remote] onError falhou:', e.message);
      }
    }
    this.offset = result.offset ?? this.offset;
  }

  async fetch(since, limit) {
    const url = `${this.apiUrl}?since=${since}&limit=${limit}`;
    const r = await fetch(url, {
      headers: {
        'Accept':        'application/json',
        'Authorization': `Bearer ${this.apiKey}`,
      },
    });
    if (!r.ok) {
      console.error('[log-remote] HTTP', r.status, 'em', url);
      return null;
    }
    return r.json();
  }
}
