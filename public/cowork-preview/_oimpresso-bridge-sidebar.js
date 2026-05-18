/*
 * _oimpresso-bridge-sidebar.js — Integração #4 / Onda 4 Wagner 2026-05-18
 *
 * Bridge: injeta sidebar oimpresso REAL (com auth/logout/troca business)
 * AO REDOR do mock Cowork canon, sem alterar o main content.
 *
 * Mecanismo (DOM manipulation, sem iframe, sem modificar React Cowork):
 *   1. No DOMContentLoaded, lê dados shell via window.__OIMPRESSO_SHELL__
 *      (será injetado pelo trait RendersMockCowork em Onda futura — Wagner
 *      consolida bridges num PR só). Fallback: dados hardcoded mínimos
 *      pra Wagner validar visual antes da consolidação.
 *   2. Cria elemento <aside class="oim-sidebar"> com:
 *      - Header: business avatar + nome
 *      - Brand: "OIMPRESSO"
 *      - Nav: 6 items principais (Dashboard, Vendas, Compras, Financeiro
 *        [active], CRM, Cadastros)
 *      - Footer: user (avatar + nome + cargo) + Sair (logout via form POST)
 *   3. Insere <aside> como primeiro filho de <body>
 *   4. Marca <html> com classe `oim-sidebar-on` que ativa CSS wrapper
 *      (esconde .sb Cowork canon + empurra .app right)
 *
 * Approach C (menor blast radius):
 *   - NÃO toca AppShellV2.tsx, Sidebar.tsx oimpresso, ou trait RendersMockCowork
 *   - NÃO usa iframe (Wagner regra explícita)
 *   - NÃO toca main content do mock — visual 100% preservado
 *   - Kill-switch trivial: remover <link>/<script> inject volta ao estado
 *     anterior; ou setar window.__OIMPRESSO_SIDEBAR_OFF__ = true antes do bridge
 *
 * Tier 0:
 *   - business_id session preservado (sidebar lê via shared shell prop, não
 *     manipula session)
 *   - Logout aponta pra rota /logout canon (mesmo middleware auth)
 *   - Troca de business aponta pra /select-business/{id} canon
 *   - Permissions: items renderizados são apenas redirects pra rotas que JÁ
 *     têm middleware permission no backend (defense-in-depth)
 *
 * Reversibilidade total:
 *   - Remover <link> + <script> tags do trait → volta sidebar Cowork canon
 *   - Setar window.__OIMPRESSO_SIDEBAR_OFF__ = true (override runtime)
 *
 * Gotchas conhecidos:
 *   - z-index conflito com TweaksPanel Cowork (panel está 999, sidebar 1000)
 *     → sidebar fica em cima, OK
 *   - Em telas <768px o CSS esconde wrapper e mostra .sb Cowork (mobile fallback)
 *   - Wagner pode pedir revert — basta remover 2 lines do trait
 *
 * Wagner regra Onda 4: HTML do sidebar pode ser hardcoded simplificado
 * (6 items principais). Wagner valida visual e depois pediremos Onda 4b
 * pra puxar menu completo via window.__OIMPRESSO_SHELL__.menu.
 */
(function () {
  'use strict';

  // Kill-switch runtime: respeita override.
  if (window.__OIMPRESSO_SIDEBAR_OFF__) {
    console.log('[oimpresso] Sidebar wrapper SKIP: __OIMPRESSO_SIDEBAR_OFF__=true');
    return;
  }

  // ─── Helpers ────────────────────────────────────────────────────────

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  function initials(name) {
    if (!name) return '?';
    var parts = String(name).trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  function el(tag, props, children) {
    var node = document.createElement(tag);
    if (props) {
      Object.keys(props).forEach(function (k) {
        if (k === 'className') node.className = props[k];
        else if (k === 'text') node.textContent = props[k];
        else if (k === 'html') node.innerHTML = props[k];
        else node.setAttribute(k, props[k]);
      });
    }
    if (children) {
      children.forEach(function (c) {
        if (c) node.appendChild(c);
      });
    }
    return node;
  }

  // ─── Shell data: Onda #4b — fetch REAL respeitando 3 camadas ──────

  /**
   * Detecta ícone por keyword no label (heurística — Cowork tem ícones limitados).
   * Mapeia pros 6 SVG inline definidos abaixo. Default: folder.
   */
  function iconForLabel(label) {
    var l = String(label || '').toLowerCase();
    if (/financeiro|fluxo|dre|boleto|gateway|recorrent|despesa|conta/.test(l)) return 'wallet';
    if (/cart|vend|venda|orcament|pos/.test(l)) return 'cart';
    if (/compr|fornec|estoque/.test(l)) return 'truck';
    if (/cliente|contat|crm|lead/.test(l)) return 'users';
    if (/dashboard|home|inicio/.test(l)) return 'home';
    return 'folder';
  }

  /**
   * Mapeia ShellMenuItem (LegacyMenuAdapter) pro shape do bridge.
   * Item canon: { label, icon, href?, inertia, children? }
   * Vira: { label, href, icon, active }
   */
  function mapMenuItem(item, activeModule) {
    var label = item.label || '';
    var href = item.href || (item.children && item.children[0] && item.children[0].href) || '#';
    var icon = iconForLabel(label);
    var active = false;
    if (activeModule && /financeiro/i.test(label) && /financeiro/i.test(activeModule)) {
      active = true;
    }
    return { label: label, href: href, icon: icon, active: active };
  }

  /**
   * Onda #4b: fetch endpoint Laravel real que respeita 3 camadas:
   *   (1) subscription_package via ModuleUtil::hasThePermissionInSubscription
   *   (2) business.enabled_modules
   *   (3) Spatie permissions auth()->user()->can('X.access')
   *
   * CRÍTICO: precisa header `X-Inertia: true` senão middleware AdminSidebarMenu
   * pula criação do Menu (skill canon `sidebar-menu-arch` documenta pegadinha).
   *
   * Retorna Promise<{ business, user, items[] }>.
   * Em caso de erro, cai pra fallback hardcoded mínimo (não bloqueia render).
   */
  function fetchShellData() {
    return fetch('/financeiro/cowork-sidebar-data', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Inertia': 'true',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
    .then(function (resp) {
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      return resp.json();
    })
    .then(function (data) {
      var menu = Array.isArray(data.menu) ? data.menu : [];
      var items = menu.map(function (m) {
        return mapMenuItem(m, data.active_module);
      }).filter(function (i) { return i.label && i.href !== '#'; });
      return {
        business: { nome: data.business && data.business.name || 'Oimpresso', id: data.business && data.business.id || 0 },
        user: { nome: data.user && data.user.name || 'Usuário', cargo: data.user && data.user.role || 'Operador' },
        items: items.length > 0 ? items : fallbackItems(),
        fromServer: true,
      };
    })
    .catch(function (err) {
      console.warn('[oimpresso] Sidebar fetch FALHOU, usando fallback:', err && err.message ? err.message : err);
      return {
        business: { nome: 'Oimpresso', id: 0 },
        user: { nome: 'Usuário', cargo: 'Operador' },
        items: fallbackItems(),
        fromServer: false,
      };
    });
  }

  function fallbackItems() {
    return [
      { label: 'Dashboard', href: '/home', icon: 'home' },
      { label: 'Vendas',    href: '/sells', icon: 'cart' },
      { label: 'Financeiro', href: '/financeiro/unificado', icon: 'wallet', active: true },
    ];
  }

  // ─── Icons SVG inline (cinco ícones, simples) ─────────────────────

  var ICONS = {
    home: '<svg class="oim-sidebar__item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10a2 2 0 002 2h10a2 2 0 002-2V10"/></svg>',
    cart: '<svg class="oim-sidebar__item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/></svg>',
    truck: '<svg class="oim-sidebar__item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    wallet: '<svg class="oim-sidebar__item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 010-4h14v4"/><path d="M3 5v14a2 2 0 002 2h16v-5"/><path d="M18 12a2 2 0 100 4h4v-4z"/></svg>',
    users: '<svg class="oim-sidebar__item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
    folder: '<svg class="oim-sidebar__item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>',
    logout: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
  };

  // ─── Build sidebar DOM ─────────────────────────────────────────────

  function buildSidebar(data) {
    var aside = el('aside', { className: 'oim-sidebar', role: 'navigation', 'aria-label': 'Oimpresso sidebar' });

    // Header — business
    var businessNome = data.business.nome || 'Oimpresso';
    var header = el('div', { className: 'oim-sidebar__header' });
    header.appendChild(el('div', {
      className: 'oim-sidebar__header-avatar',
      text: initials(businessNome),
    }));
    header.appendChild(el('div', {
      className: 'oim-sidebar__header-name',
      text: businessNome,
      title: businessNome,
    }));
    aside.appendChild(header);

    // Brand strip
    aside.appendChild(el('div', { className: 'oim-sidebar__brand', text: 'OIMPRESSO' }));

    // Nav items
    var nav = el('nav', { className: 'oim-sidebar__nav' });
    data.items.forEach(function (it) {
      var link = el('a', {
        className: 'oim-sidebar__item' + (it.active ? ' oim-sidebar__item--active' : ''),
        href: it.href || '#',
      });
      // Ícone inline
      var iconHtml = ICONS[it.icon] || ICONS.folder;
      var iconWrap = el('span', { html: iconHtml });
      // Pega o <svg> filho (já tem className correta)
      if (iconWrap.firstChild) link.appendChild(iconWrap.firstChild);
      // Label
      link.appendChild(el('span', { className: 'oim-sidebar__item-label', text: it.label }));
      nav.appendChild(link);
    });
    aside.appendChild(nav);

    // Footer — user + logout
    var footer = el('div', { className: 'oim-sidebar__footer' });

    var userBox = el('div', { className: 'oim-sidebar__user' });
    userBox.appendChild(el('div', {
      className: 'oim-sidebar__user-avatar',
      text: initials(data.user.nome),
    }));
    var userMeta = el('div', { className: 'oim-sidebar__user-meta' });
    userMeta.appendChild(el('div', {
      className: 'oim-sidebar__user-name',
      text: data.user.nome || 'Usuário',
      title: data.user.nome,
    }));
    userMeta.appendChild(el('div', {
      className: 'oim-sidebar__user-role',
      text: data.user.cargo || 'Operador',
    }));
    userBox.appendChild(userMeta);
    footer.appendChild(userBox);

    // Logout — form POST com CSRF (rota Laravel /logout canon)
    var logoutForm = el('form', {
      method: 'POST',
      action: '/logout',
      style: 'margin: 0; padding: 0;',
    });
    var csrf = getCsrfToken();
    if (csrf) {
      var csrfInput = el('input', { type: 'hidden', name: '_token', value: csrf });
      logoutForm.appendChild(csrfInput);
    }
    var logoutBtn = el('button', {
      type: 'submit',
      className: 'oim-sidebar__logout',
      'aria-label': 'Sair',
    });
    var logoutIcon = el('span', { html: ICONS.logout });
    if (logoutIcon.firstChild) logoutBtn.appendChild(logoutIcon.firstChild);
    logoutBtn.appendChild(el('span', { text: 'Sair' }));
    logoutForm.appendChild(logoutBtn);
    footer.appendChild(logoutForm);

    aside.appendChild(footer);

    return aside;
  }

  // ─── Mount ────────────────────────────────────────────────────────

  function mountAsync() {
    // Defensive: já montado?
    if (document.querySelector('.oim-sidebar')) {
      console.log('[oimpresso] Sidebar wrapper já montado, skip duplicate');
      return;
    }

    // Onda #4b: fetch async dos dados REAIS respeitando 3 camadas
    fetchShellData().then(function (data) {
      // Re-check defensive (pode ter sido montado durante o fetch)
      if (document.querySelector('.oim-sidebar')) return;

      var aside = buildSidebar(data);

      // Insere como primeiro filho do body (antes do #app Cowork)
      if (document.body.firstChild) {
        document.body.insertBefore(aside, document.body.firstChild);
      } else {
        document.body.appendChild(aside);
      }

      // Ativa CSS wrapper — html.oim-sidebar-on
      document.documentElement.classList.add('oim-sidebar-on');

      console.log(
        '[oimpresso] Sidebar wrapper montado (Onda #4b): business=%s, items=%d, source=%s',
        data.business.nome,
        data.items.length,
        data.fromServer ? 'server' : 'fallback'
      );
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAsync);
  } else {
    mountAsync();
  }

  console.log('[oimpresso] Mock Cowork bridge-sidebar.js carregado (Onda #4b — fetch real 3 camadas)');
})();
