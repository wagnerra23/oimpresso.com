/*
 * _oimpresso-bridge-edit.js — Integração #6 / Onda 6 Wagner 2026-05-18
 *
 * Bridge: Drawer "Editar campos" (FinEditPanel em financeiro-curation.jsx)
 * dispara PUT real Laravel ao clicar "✓ Salvar":
 *   PUT /financeiro/unificado/{id}   → atualiza Titulo via UpdateTituloRequest
 *
 * Mecanismo:
 *   1. financeiro-curation.jsx (FinEditPanel) dispatch CustomEvent
 *      'oimpresso:fin-edit' com detail = { id, fields, original } no Save
 *      (modificação mínima de 1 linha — try/catch silencioso)
 *   2. Este bridge escuta o evento, extrai id Laravel via /^[RP]-(\d+)$/
 *   3. Se id numérico (dados reais Eloquent) + há campos alterados → PUT
 *   4. Se id não-numérico (mock template "R-2641a") → loga e segue só localStorage
 *
 * Mapping fields (mock JSX → UpdateTituloRequest):
 *   - party    → cliente_descricao (nullable string)
 *   - dueISO   → vencimento (required date YYYY-MM-DD)
 *   - amount   → valor_total (sometimes, numeric, min 0.01)
 *                Backend guarda imutabilidade pós-baixa via assertValorMutavel()
 *   - category → categoria_id  (nullable integer — UI tem NOME, backend espera ID)
 *                Bridge NÃO envia category por padrão (gotcha resolvido no Onda 7).
 *                Mapping nome→id exigiria endpoint extra ou injectar lista no window.
 *   - channel  → ignorado (UpdateTituloRequest não aceita; canal é metadata,
 *                guarda em localStorage local até Onda futura migrar.)
 *
 * Tier 0:
 *   - CSRF token via meta tag (injetada pelo trait RendersMockCowork)
 *   - credentials: same-origin (cookie session)
 *   - business_id filtrado no controller (NUNCA trafega no payload)
 *   - findOrFail garante que titulo pertence ao business da session
 *
 * Reversibilidade: remover script tag inject no trait — comportamento volta
 * a só localStorage local (sem persistência DB). useFinEdits.applied() continua
 * funcionando como overlay visual mesmo sem bridge.
 *
 * Gotchas conhecidos:
 *   - ID não-numérico (mock template "R-2641a") → loga e segue só localStorage
 *   - Sem CSRF token → warn e segue só localStorage
 *   - 419 (token expirado) / 404 (id não pertence ao business) → warn não-fatal
 *   - 422 valor imutável (status quitado/cancelado) → warn não-fatal
 *   - Endpoint retorna RedirectResponse (302) — tratamos como sucesso
 *   - Categoria por nome NÃO sincroniza (Onda 7 — exigirá mapping)
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

  // Constrói payload pra PUT /financeiro/unificado/{id} a partir do detail
  // dispatched pelo JSX. Só inclui campo se VALOR ALTERADO vs original
  // (minimiza writes; respeita imutabilidade no backend).
  function buildPayload(fields, original) {
    var payload = {};

    // vencimento — required, YYYY-MM-DD
    if (fields.dueISO && /^\d{4}-\d{2}-\d{2}$/.test(fields.dueISO)) {
      payload.vencimento = fields.dueISO;
    } else if (original && original.due instanceof Date) {
      // Required: se user não editou, manda original (UpdateTituloRequest exige)
      payload.vencimento = original.due.toISOString().slice(0, 10);
    }

    // cliente_descricao — opcional
    if (fields.party !== undefined && fields.party !== null) {
      payload.cliente_descricao = String(fields.party);
    }

    // valor_total — sometimes, só se alterado (preserva guard de imutabilidade)
    if (fields.amount !== undefined && fields.amount !== null) {
      var amount = parseFloat(fields.amount);
      if (!isNaN(amount) && amount > 0 && (!original || amount !== original.amount)) {
        payload.valor_total = amount;
      }
    }

    // observacoes — opcional (não vem do mock JSX hoje, mas reservado pra evolução)
    if (fields.observacoes !== undefined && fields.observacoes !== null) {
      payload.observacoes = String(fields.observacoes);
    }

    // categoria_id NÃO incluído: mock tem nome ("Banner"), backend exige ID.
    // Mapping nome→id seria gotcha futuro (Onda 7).

    return payload;
  }

  function putEdit(rowId, fields, original) {
    var laravelId = extractLaravelId(rowId);
    if (laravelId === null) {
      console.log('[oimpresso] Edit bridge SKIP (não-numérico, mock template): id=%s', rowId);
      return;
    }

    var csrf = getCsrfToken();
    if (!csrf) {
      console.warn('[oimpresso] Edit bridge FALHOU: CSRF token ausente (id=%s)', rowId);
      return;
    }

    var payload = buildPayload(fields || {}, original || null);
    // Sanity: vencimento é required no backend — se faltou, abortar pra evitar 422 ruidoso
    if (!payload.vencimento) {
      console.warn('[oimpresso] Edit bridge SKIP: vencimento ausente (id=%s)', rowId);
      return;
    }

    fetch('/financeiro/unificado/' + laravelId, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    })
      .then(function (resp) {
        // Controller retorna RedirectResponse (302) em sucesso;
        // 200/204 também tratados como ok.
        if (resp.ok || resp.status === 302) {
          console.log('[oimpresso] Edit bridge synced: id=%d, fields=%o', laravelId, Object.keys(payload));
        } else if (resp.status === 422) {
          console.warn('[oimpresso] Edit bridge 422 (validação ou imutabilidade): id=%d', laravelId);
        } else {
          console.warn('[oimpresso] Edit bridge FALHOU: HTTP %d (id=%d)', resp.status, laravelId);
        }
      })
      .catch(function (err) {
        console.warn('[oimpresso] Edit bridge ERROR:', err && err.message ? err.message : err);
      });
  }

  // Listener event canon dispatchado pelo FinEditPanel
  window.addEventListener('oimpresso:fin-edit', function (e) {
    var detail = e && e.detail;
    if (!detail || !detail.id) return;
    putEdit(detail.id, detail.fields || {}, detail.original || null);
  });

  console.log('[oimpresso] Mock Cowork bridge-edit.js carregado (Integração #6 / Onda 6)');
})();
