/*
 * _oimpresso-bridge-conferido.js — Integração #3 / Onda 5 Wagner 2026-05-18
 *
 * Bridge: toggle "Conferido" do mock Cowork canon (FinConferidoToggle em
 * financeiro-curation.jsx) dispara POST/DELETE real Laravel
 *   POST   /financeiro/unificado/{id}/conferir   → marca conferido_by/at
 *   DELETE /financeiro/unificado/{id}/conferir   → limpa conferido_by/at
 *
 * Mecanismo (event delegation, sem modificar JSX):
 *   1. Listener click no document, filtra alvos com class .fin-conferido-toggle
 *   2. Lê estado ATUAL (presença de .on) — usuário vai INVERTER esse estado
 *      - currentlyOn=true  → ação = unmarcar → DELETE
 *      - currentlyOn=false → ação = marcar   → POST
 *   3. Extrai row id do drawer ancestral (`.fin-drawer-wide` header tem "R-N" ou "P-N")
 *      - Match /^[RP]-(\d+)$/ → id Laravel numérico (dados reais Eloquent)
 *      - Mock template tem IDs tipo "R-2641a" (não-numéricos) → graceful skip
 *   4. Fetch com CSRF + credentials same-origin (Tier 0 multi-tenant
 *      preservado: controller filtra por session('user.business_id'))
 *   5. localStorage local (oimpresso.financeiro.conferido) continua sendo
 *      atualizado pelo useFinConferido — bridge é OVERLAY de persistência,
 *      não substitui o estado React local
 *
 * Tier 0:
 *   - CSRF token via meta tag (injetada pelo trait RendersMockCowork)
 *   - credentials: same-origin (cookie session)
 *   - business_id filtrado no controller (NUNCA trafega no payload)
 *   - findOrFail garante que titulo pertence ao business da session
 *
 * Reversibilidade: remover script tag inject no trait — comportamento volta
 * a só localStorage local (sem persistência DB).
 *
 * Gotchas conhecidos:
 *   - ID não-numérico (mock template "R-2641a") → loga e segue só localStorage
 *   - Sem CSRF token → warn e segue só localStorage
 *   - 419 (token expirado) / 404 (id não pertence ao business) → warn não-fatal
 *   - Endpoint retorna RedirectResponse (302) — tratamos como sucesso
 */
(function () {
  'use strict';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  function extractLaravelId(rowId) {
    // Formato CoworkDataMapper: "R-123" / "P-123" (123 = Titulo.id Eloquent)
    // Formato mock template: "R-2641a" / "R-2641c" (não-numérico — NÃO é DB id)
    var match = /^[RP]-(\d+)$/.exec(String(rowId));
    return match ? parseInt(match[1], 10) : null;
  }

  // Heurística pra ler o row.id da drawer aberta:
  // O header do drawer tem texto "A receber · R-123" ou "A pagar · P-45".
  // Pegamos o último token tipo /[RP]-\w+/ visível dentro do drawer ancestral.
  function findRowIdFromDom(button) {
    var drawer = button.closest('.fin-drawer-wide, aside[class*="drawer-shown"]');
    if (!drawer) return null;
    var headerText = (drawer.textContent || '').slice(0, 400); // só o topo, evita falsos
    // Procura o primeiro padrão R-N ou P-N (alfanumérico) no header
    var match = /\b([RP]-[A-Za-z0-9]+)\b/.exec(headerText);
    return match ? match[1] : null;
  }

  function syncConferido(rowId, action) {
    // action: 'marcar' (POST) ou 'desmarcar' (DELETE)
    var laravelId = extractLaravelId(rowId);
    if (laravelId === null) {
      console.log('[oimpresso] Conferido toggle SKIP (não-numérico, mock template): id=%s, action=%s', rowId, action);
      return;
    }

    var csrf = getCsrfToken();
    if (!csrf) {
      console.warn('[oimpresso] Conferido toggle FALHOU: CSRF token ausente (id=%s)', rowId);
      return;
    }

    var method = action === 'marcar' ? 'POST' : 'DELETE';

    fetch('/financeiro/unificado/' + laravelId + '/conferir', {
      method: method,
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
        // Controller retorna RedirectResponse (302) em sucesso;
        // 200/204 também tratados como ok.
        if (resp.ok || resp.status === 302) {
          console.log('[oimpresso] Conferido toggle synced: id=%d, action=%s', laravelId, action);
        } else {
          console.warn('[oimpresso] Conferido toggle FALHOU: HTTP %d (id=%d, action=%s)', resp.status, laravelId, action);
        }
      })
      .catch(function (err) {
        console.warn('[oimpresso] Conferido toggle ERROR:', err && err.message ? err.message : err);
      });
  }

  // Event delegation: captura click ANTES do React processar (capture: true).
  // Estado ATUAL .on reflete pre-click; ação do usuário inverte esse estado.
  document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || typeof target.closest !== 'function') return;

    var button = target.closest('.fin-conferido-toggle');
    if (!button) return;

    var currentlyOn = button.classList.contains('on');
    var action = currentlyOn ? 'desmarcar' : 'marcar';

    var rowId = findRowIdFromDom(button);
    if (!rowId) {
      console.log('[oimpresso] Conferido toggle SKIP: row.id não encontrado no drawer DOM');
      return;
    }

    syncConferido(rowId, action);
  }, true);

  console.log('[oimpresso] Mock Cowork bridge-conferido.js carregado (Integração #3 / Onda 5)');
})();
