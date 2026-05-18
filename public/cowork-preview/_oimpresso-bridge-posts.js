/*
 * _oimpresso-bridge-posts.js — Integração #2 Wagner 2026-05-18
 *
 * Bridge: botões "Recebi"/"Paguei" do mock Cowork canon disparam POST real
 * Laravel /financeiro/unificado/{id}/baixar (cria TituloBaixa em DB).
 *
 * Mecanismo:
 *   1. financeiro-app.jsx handleMark dispatch CustomEvent('oimpresso:fin-mark', { detail: { id } })
 *      (modificação mínima de 1 linha — fallback try/catch silencioso)
 *   2. Este bridge escuta o evento, extrai N de "R-N"/"P-N" (CoworkDataMapper format)
 *   3. Se N é numérico (dados reais Eloquent) → fetch POST /financeiro/unificado/{N}/baixar
 *   4. Se N é não-numérico (mock template "R-2641a") → só loga, não faz POST
 *
 * Tier 0:
 *   - CSRF token incluso via meta tag
 *   - Cookie session segue browser default (credentials: same-origin)
 *   - business_id é session-derived no controller (não trafega)
 *   - Optimistic UI mantida (mock já atualiza visual via setRows local)
 *
 * Reversibilidade: remover script tag inject no trait, comportamento volta
 * a só localStorage local (sem persistência DB).
 */
(function () {
  'use strict';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  function extractLaravelId(rowId) {
    // Formato CoworkDataMapper: "R-123" ou "P-123" (123 = Titulo.id Eloquent)
    // Formato mock template: "R-2641a" ou "R-2641c" (não-numérico — não é DB id)
    var match = /^[RP]-(\d+)$/.exec(String(rowId));
    return match ? parseInt(match[1], 10) : null;
  }

  function postBaixar(rowId) {
    var laravelId = extractLaravelId(rowId);
    if (laravelId === null) {
      console.log('[oimpresso] Mock Cowork baixar SKIP (não-numérico, mock template): id=%s', rowId);
      return;
    }

    var csrf = getCsrfToken();
    if (!csrf) {
      console.warn('[oimpresso] Mock Cowork baixar FALHOU: CSRF token ausente');
      return;
    }

    fetch('/financeiro/unificado/' + laravelId + '/baixar', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    })
    .then(function (resp) {
      if (resp.ok || resp.status === 302) {
        console.log('[oimpresso] Mock Cowork baixar OK: id=%s (Laravel id=%d)', rowId, laravelId);
      } else {
        console.warn('[oimpresso] Mock Cowork baixar FALHOU: HTTP %d (id=%s)', resp.status, rowId);
      }
    })
    .catch(function (err) {
      console.warn('[oimpresso] Mock Cowork baixar ERROR:', err && err.message ? err.message : err);
    });
  }

  // Listener event canon dispatchado pelo financeiro-app.jsx
  window.addEventListener('oimpresso:fin-mark', function (e) {
    var id = e && e.detail && e.detail.id;
    if (!id) return;
    postBaixar(id);
  });

  console.log('[oimpresso] Mock Cowork bridge-posts.js carregado (Integração #2)');
})();
