/*
 * _oimpresso-bridge-comments.js — Onda Comments DB Wagner 2026-05-18
 *
 * Bridge: FinCommentsThread do mock Cowork canon (financeiro-curation.jsx)
 * persiste comments em DB real via:
 *   GET  /financeiro/unificado/{id}/comments   → carrega histórico
 *   POST /financeiro/unificado/{id}/comments   → cria comment (body)
 *
 * Mecanismo (event-driven, sem modificar lógica do hook useFinComments):
 *   1. JSX dispatch CustomEvent('oimpresso:fin-comment-add', { detail: { rowId, text } })
 *      no submit do textarea (modificação mínima de 1 linha, try/catch silencioso).
 *   2. Bridge escuta, extrai N de "R-N"/"P-N" (CoworkDataMapper format).
 *   3. Se N é numérico (dados reais Eloquent) → fetch POST + console log success.
 *   4. Se N é não-numérico (mock template "R-2641a") → só loga, não faz POST.
 *   5. localStorage local (oimpresso.financeiro.comments) continua sendo
 *      atualizado pelo useFinComments — bridge é OVERLAY de persistência.
 *
 * Carrega histórico:
 *   Quando drawer abre (event 'oimpresso:fin-drawer-open' OU MutationObserver),
 *   bridge faz GET pra hidratar a thread visualmente com comments do DB.
 *   Implementação inicial: só persiste novos comments (GET requer integração
 *   profunda com useState do hook React, mantém localStorage como fonte
 *   primária de exibição até iteração 2 — overlay-first é o padrão dos outros
 *   bridges #2 e #5).
 *
 * Tier 0:
 *   - CSRF token via meta tag (injetada pelo trait RendersMockCowork)
 *   - credentials: same-origin (cookie session)
 *   - business_id filtrado no controller (NUNCA trafega no payload)
 *   - findOrFail/404 garantem isolamento por tenant
 *
 * Reversibilidade: remover <script src="..."> do trait — comportamento volta
 * a só localStorage local.
 *
 * Gotchas conhecidos:
 *   - ID não-numérico (mock template "R-2641a") → SKIP graceful
 *   - Sem CSRF token → warn e segue só localStorage
 *   - 419 (token expirado) / 404 (id não pertence) → warn não-fatal
 *   - 422 (validation body vazio) → warn (UI já bloqueia textarea vazio)
 */
(function () {
  'use strict';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  function extractLaravelId(rowId) {
    // Formato CoworkDataMapper: "R-123"/"P-123" (123 = Titulo.id Eloquent)
    // Formato mock template: "R-2641a"/"R-2641c" (não-numérico — NÃO é DB id)
    var match = /^[RP]-(\d+)$/.exec(String(rowId));
    return match ? parseInt(match[1], 10) : null;
  }

  function postComment(rowId, text) {
    var laravelId = extractLaravelId(rowId);
    if (laravelId === null) {
      console.log('[oimpresso] Comments POST SKIP (não-numérico, mock template): id=%s', rowId);
      return;
    }

    if (!text || !text.trim()) {
      console.warn('[oimpresso] Comments POST SKIP: body vazio (id=%s)', rowId);
      return;
    }

    var csrf = getCsrfToken();
    if (!csrf) {
      console.warn('[oimpresso] Comments POST FALHOU: CSRF token ausente (id=%s)', rowId);
      return;
    }

    fetch('/financeiro/unificado/' + laravelId + '/comments', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ body: text }),
    })
      .then(function (resp) {
        if (resp.ok) {
          console.log('[oimpresso] Comments POST synced: id=%d (Laravel id), body length=%d', laravelId, text.length);
        } else {
          console.warn('[oimpresso] Comments POST FALHOU: HTTP %d (id=%d)', resp.status, laravelId);
        }
      })
      .catch(function (err) {
        console.warn('[oimpresso] Comments POST ERROR:', err && err.message ? err.message : err);
      });
  }

  // GET histórico do DB — hidrata window.__OIMPRESSO_COMMENTS_DB__[rowId] pra
  // iteração futura do JSX consultar (overlay-first design).
  function fetchComments(rowId) {
    var laravelId = extractLaravelId(rowId);
    if (laravelId === null) return;

    fetch('/financeiro/unificado/' + laravelId + '/comments', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    })
      .then(function (resp) {
        if (!resp.ok) {
          console.warn('[oimpresso] Comments GET FALHOU: HTTP %d (id=%d)', resp.status, laravelId);
          return null;
        }
        return resp.json();
      })
      .then(function (data) {
        if (!data || !Array.isArray(data.comments)) return;
        window.__OIMPRESSO_COMMENTS_DB__ = window.__OIMPRESSO_COMMENTS_DB__ || {};
        window.__OIMPRESSO_COMMENTS_DB__[rowId] = data.comments;
        console.log('[oimpresso] Comments GET synced: id=%d, count=%d', laravelId, data.total);
        // Dispatch pra JSX poder reagir se quiser (iteração futura)
        try {
          window.dispatchEvent(new CustomEvent('oimpresso:fin-comments-loaded', {
            detail: { rowId: rowId, comments: data.comments },
          }));
        } catch (e) {}
      })
      .catch(function (err) {
        console.warn('[oimpresso] Comments GET ERROR:', err && err.message ? err.message : err);
      });
  }

  // Listener event canon dispatchado pelo financeiro-curation.jsx submit()
  window.addEventListener('oimpresso:fin-comment-add', function (e) {
    var detail = e && e.detail;
    if (!detail || !detail.rowId) return;
    postComment(detail.rowId, detail.text || '');
  });

  // Listener pra drawer open — pre-fetch comments do DB. JSX pode dispatchar
  // este event quando o drawer abrir; sem ele, GET só roda sob demanda (iteração 2).
  window.addEventListener('oimpresso:fin-drawer-open', function (e) {
    var detail = e && e.detail;
    if (!detail || !detail.rowId) return;
    fetchComments(detail.rowId);
  });

  console.log('[oimpresso] Mock Cowork bridge-comments.js carregado (Onda Comments DB)');
})();
