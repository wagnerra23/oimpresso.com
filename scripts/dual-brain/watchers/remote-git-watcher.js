/**
 * RemoteGitWatcher (v2) — substitui GitWatcher v1.
 *
 * Não lê filesystem local. Faz HTTP poll em GET /api/ads/recent-commits do app
 * Hostinger. Mantém último SHA visto em memória (perde ao restart, mas o boot
 * inicializa com HEAD atual via primeira chamada — não reprocessa histórico).
 *
 * Topologia: ARQ-0011.
 */

export class RemoteGitWatcher {
  constructor({ apiUrl, apiKey, intervalMs = 30000, onCommit }) {
    if (!apiUrl) throw new Error('RemoteGitWatcher: apiUrl ausente');
    if (!apiKey) throw new Error('RemoteGitWatcher: apiKey ausente');
    this.apiUrl   = apiUrl.replace(/\/+$/, '');
    this.apiKey   = apiKey;
    this.intervalMs = intervalMs;
    this.onCommit = onCommit;
    this.lastSha  = null;
    this.timer    = null;
  }

  async start() {
    // Inicializa lastSha com HEAD atual; primeira tick não reprocessa histórico
    const initial = await this.fetchCommits(null);
    this.lastSha = initial?.head ?? null;
    console.log(`[git-remote] watcher inicializado @ ${this.lastSha ? this.lastSha.slice(0, 8) : 'unknown'}`);
    this.timer = setInterval(() => this.tick().catch(e => console.error('[git-remote] tick:', e.message)), this.intervalMs);
  }

  stop() {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
  }

  async tick() {
    if (!this.lastSha) {
      const init = await this.fetchCommits(null);
      this.lastSha = init?.head ?? null;
      return;
    }

    const result = await this.fetchCommits(this.lastSha);
    if (!result || !Array.isArray(result.commits)) return;
    if (result.commits.length === 0) return;

    for (const c of result.commits) {
      try {
        await this.onCommit({
          sha:     c.sha,
          subject: c.subject,
          files:   Array.isArray(c.files) ? c.files : [],
        });
      } catch (e) {
        console.error('[git-remote] onCommit falhou para', c.sha?.slice(0,8), e.message);
      }
    }
    this.lastSha = result.head;
  }

  async fetchCommits(since) {
    const url = since
      ? `${this.apiUrl}?since=${encodeURIComponent(since)}&limit=20`
      : `${this.apiUrl}?limit=1`;

    const r = await fetch(url, {
      headers: {
        'Accept':        'application/json',
        'Authorization': `Bearer ${this.apiKey}`,
      },
    });
    if (!r.ok) {
      console.error('[git-remote] HTTP', r.status, 'em', url);
      return null;
    }
    return r.json();
  }
}
