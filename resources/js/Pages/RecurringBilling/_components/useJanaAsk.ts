// Onda 14/15 v9,75 — Helper Jana IA com fallback graceful.

export async function askJana(query: string, _context?: string): Promise<{ ok: boolean; text: string }> {
  const csrf = (typeof document !== 'undefined'
    ? (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content
    : '') || '';
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrf,
  };
  const body = JSON.stringify({ query, context: _context || '' });

  for (const url of ['/api/ia/ask', '/ia/ask']) {
    try {
      const res = await fetch(url, { method: 'POST', headers, body, credentials: 'same-origin' });
      if (!res.ok) continue;
      const data = await res.json().catch(() => null);
      if (typeof data === 'string') return { ok: true, text: data };
      const text = data?.text || data?.answer || data?.message || '';
      if (text) return { ok: true, text };
    } catch {
      /* fallback */
    }
  }
  return { ok: false, text: '(Jana não disponível neste momento. Tente novamente em alguns minutos.)' };
}
