/*
 * _oimpresso-bridge-audit.js — Onda Audit DB Wagner 2026-05-18
 *
 * Bridge: FinAuditTrail do mock Cowork canon (financeiro-curation.jsx) puxa
 * dados reais do Spatie ActivityLog via:
 *   GET /financeiro/unificado/{id}/audit  → últimas 50 activities formatadas
 *
 * Mecanismo (overlay-first, mesmo padrão de bridges #2 e #5):
 *   1. JSX dispatch CustomEvent('oimpresso:fin-drawer-open', { detail: { rowId } })
 *      quando o drawer abre (modificação mínima — try/catch silencioso).
 *   2. Bridge escuta, extrai N de "R-N"/"P-N" (CoworkDataMapper format).
 *   3. Se N é numérico → fetch GET → popula window.__OIMPRESSO_AUDIT_DB__[rowId].
 *   4. Dispatch event 'oimpresso:fin-audit-loaded' pro JSX poder substituir
 *      finAuditTrail() mock pelos dados reais (iteração futura, opt-in).
 *   5. Por enquanto JSX continua usando o mock determinístico (finAuditTrail) —
 *      este bridge é INFRA + log, ainda não substitui visualmente.
 *
 * Shape do response (espelha contrato JSX):
 *   { entries: [{ id, when, who, action, event, diff?: { field, from, to } }], total }
 *
 * Tier 0:
 *   - credentials: same-origin (cookie session)
 *   - business_id filtrado no controller via Activity::where('business_id', X)
 *   - find/404 antes de buscar audit (defesa-em-profundidade IDOR)
 *
 * Reversibilidade: remover <script src="..."> do trait — sem efeitos colaterais
 * (read-only, mock visual continua intacto).
 *
 * Gotchas conhecidos:
 *   - Sem auth → 401 (warn não-fatal)
 *   - Titulo não pertence ao business → 404 (warn não-fatal)
 *   - Activity vazia (model sem LogsActivity) → entries:[] graceful
 *   - ID não-numérico (mock template "R-2641a") → SKIP graceful
 */
(function () {
  'use strict';

  function extractLaravelId(rowId) {
    var match = /^[RP]-(\d+)$/.exec(String(rowId));
    return match ? parseInt(match[1], 10) : null;
  }

  function fetchAudit(rowId) {
    var laravelId = extractLaravelId(rowId);
    if (laravelId === null) {
      console.log('[oimpresso] Audit GET SKIP (não-numérico, mock template): id=%s', rowId);
      return;
    }

    fetch('/financeiro/unificado/' + laravelId + '/audit', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    })
      .then(function (resp) {
        if (!resp.ok) {
          console.warn('[oimpresso] Audit GET FALHOU: HTTP %d (id=%d)', resp.status, laravelId);
          return null;
        }
        return resp.json();
      })
      .then(function (data) {
        if (!data || !Array.isArray(data.entries)) return;
        window.__OIMPRESSO_AUDIT_DB__ = window.__OIMPRESSO_AUDIT_DB__ || {};
        window.__OIMPRESSO_AUDIT_DB__[rowId] = data.entries;
        console.log('[oimpresso] Audit GET synced: id=%d, entries=%d', laravelId, data.total);
        // Dispatch pra JSX reagir (iteração futura — substituir finAuditTrail mock).
        try {
          window.dispatchEvent(new CustomEvent('oimpresso:fin-audit-loaded', {
            detail: { rowId: rowId, entries: data.entries },
          }));
        } catch (e) {}
      })
      .catch(function (err) {
        console.warn('[oimpresso] Audit GET ERROR:', err && err.message ? err.message : err);
      });
  }

  // Listener event canon dispatchado pelo JSX quando drawer abre
  window.addEventListener('oimpresso:fin-drawer-open', function (e) {
    var detail = e && e.detail;
    if (!detail || !detail.rowId) return;
    fetchAudit(detail.rowId);
  });

  console.log('[oimpresso] Mock Cowork bridge-audit.js carregado (Onda Audit DB)');
})();
