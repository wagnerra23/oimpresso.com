/* ═══════════════════════════════════════════════════════════════════
   DS v5 — interações dos demos (sidebar + datatable)
   ═══════════════════════════════════════════════════════════════════ */
(function(){
  'use strict';

  /* ───────────── SIDEBAR ───────────── */
  var sb = document.getElementById('demoSidebar');
  if(sb){
    // grupos colapsáveis
    sb.querySelectorAll('.sb-grp').forEach(function(grp){
      grp.addEventListener('click', function(){
        grp.closest('.sb-group').classList.toggle('collapsed');
      });
    });
    // sub-itens (has-sub abre .sb-subitems irmão)
    sb.querySelectorAll('.sb-item.has-sub').forEach(function(it){
      it.addEventListener('click', function(){
        var open = it.getAttribute('aria-expanded') === 'true';
        it.setAttribute('aria-expanded', String(!open));
        var subs = it.nextElementSibling;
        if(subs && subs.classList.contains('sb-subitems')){ subs.hidden = open; }
      });
    });
    // item ativo (não sub, não has-sub)
    sb.querySelectorAll('.sb-item:not(.has-sub)').forEach(function(it){
      it.addEventListener('click', function(){
        sb.querySelectorAll('.sb-item').forEach(function(x){ x.classList.remove('active'); });
        it.classList.add('active');
      });
    });
    sb.querySelectorAll('.sb-subitem').forEach(function(it){
      it.addEventListener('click', function(){
        sb.querySelectorAll('.sb-subitem').forEach(function(x){ x.classList.remove('active'); });
        it.classList.add('active');
      });
    });
    // modo rail
    var railBtn = sb.querySelector('[data-rail]');
    function syncRail(){
      var on = sb.classList.contains('rail');
      var lbl = railBtn.querySelector('span');
      if(lbl){ lbl.textContent = on ? 'Expandir' : 'Recolher'; }
      // em rail, vira tooltip nos itens
      sb.querySelectorAll('.sb-item').forEach(function(it){
        var l = it.querySelector('.lbl');
        if(on && l){ it.setAttribute('data-tip', l.textContent); it.setAttribute('data-tip-pos','right'); }
        else { it.removeAttribute('data-tip'); }
      });
    }
    if(railBtn){ railBtn.addEventListener('click', function(){ sb.classList.toggle('rail'); syncRail(); }); }
    // ⌘\ alterna rail
    document.addEventListener('keydown', function(e){
      if((e.metaKey || e.ctrlKey) && e.key === '\\'){ e.preventDefault(); sb.classList.toggle('rail'); syncRail(); }
    });
  }

  /* ───────────── DATATABLE ───────────── */
  var dt = document.getElementById('demoTable');
  if(dt){
    var table = dt.querySelector('table');
    var tbody = table.querySelector('tbody');
    var bulk = document.getElementById('demoBulk');

    // pares linha+detalhe
    function pairs(){
      var out = [];
      tbody.querySelectorAll('tr:not(.dt-detail-row)').forEach(function(row){
        var detail = row.nextElementSibling;
        out.push({ row: row, detail: (detail && detail.classList.contains('dt-detail-row')) ? detail : null });
      });
      return out;
    }

    // ── expandir ──
    dt.querySelectorAll('.dt-expand').forEach(function(btn){
      btn.addEventListener('click', function(){
        var row = btn.closest('tr');
        var detail = row.nextElementSibling;
        if(!detail || !detail.classList.contains('dt-detail-row')) return;
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        detail.hidden = open;
      });
    });

    // ── seleção ──
    var allBox = dt.querySelector('[data-all]');
    var rowBoxes = function(){ return Array.prototype.slice.call(dt.querySelectorAll('[data-row]')); };
    function updateBulk(){
      var boxes = rowBoxes();
      var checked = boxes.filter(function(b){ return b.checked; });
      // marca linha selecionada
      boxes.forEach(function(b){ b.closest('tr').classList.toggle('selected', b.checked); });
      // estado do "selecionar tudo"
      if(allBox){
        allBox.checked = checked.length === boxes.length && boxes.length > 0;
        allBox.indeterminate = checked.length > 0 && checked.length < boxes.length;
      }
      // bulk bar
      if(bulk){
        bulk.classList.toggle('show', checked.length > 0);
        var n = bulk.querySelector('.n');
        if(n){ n.innerHTML = checked.length + ' <span>selecionada' + (checked.length === 1 ? '' : 's') + '</span>'; }
      }
    }
    rowBoxes().forEach(function(b){ b.addEventListener('change', updateBulk); });
    if(allBox){
      allBox.addEventListener('change', function(){
        rowBoxes().forEach(function(b){ b.checked = allBox.checked; });
        updateBulk();
      });
    }
    var clearBtn = bulk ? bulk.querySelector('[data-clear]') : null;
    if(clearBtn){
      clearBtn.addEventListener('click', function(){
        rowBoxes().forEach(function(b){ b.checked = false; });
        updateBulk();
      });
    }

    // ── ordenação ──
    table.querySelectorAll('th.sort').forEach(function(th){
      var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
      var type = th.getAttribute('data-type') || 'text';
      th.addEventListener('click', function(){
        var asc = !th.classList.contains('asc');
        table.querySelectorAll('th.sort').forEach(function(o){ o.classList.remove('asc','desc'); });
        th.classList.add(asc ? 'asc' : 'desc');
        var ps = pairs();
        ps.sort(function(a, b){
          var ca = a.row.children[idx], cb = b.row.children[idx];
          var va, vb;
          if(type === 'num'){
            va = parseFloat(ca.getAttribute('data-val') || ca.textContent.replace(/[^0-9,-]/g,'').replace(',','.')) || 0;
            vb = parseFloat(cb.getAttribute('data-val') || cb.textContent.replace(/[^0-9,-]/g,'').replace(',','.')) || 0;
          } else {
            va = ca.textContent.trim().toLowerCase();
            vb = cb.textContent.trim().toLowerCase();
          }
          if(va < vb) return asc ? -1 : 1;
          if(va > vb) return asc ? 1 : -1;
          return 0;
        });
        // re-append mantendo o par detalhe
        ps.forEach(function(p){
          tbody.appendChild(p.row);
          if(p.detail){ tbody.appendChild(p.detail); }
        });
      });
    });

    updateBulk();
  }

  /* ───────────── DRAWER (overlay) ───────────── */
  var dw = document.getElementById('demoDrawer');
  if(dw){
    function openDw(){ dw.classList.add('open'); }
    function closeDw(){ dw.classList.remove('open'); }
    dw.querySelectorAll('[data-open-drawer]').forEach(function(b){ b.addEventListener('click', openDw); });
    dw.querySelectorAll('[data-close-drawer]').forEach(function(b){ b.addEventListener('click', closeDw); });
    // tabs internas
    dw.querySelectorAll('.drawer-tabs .tab').forEach(function(tab){
      tab.addEventListener('click', function(){
        var key = tab.getAttribute('data-pane');
        dw.querySelectorAll('.drawer-tabs .tab').forEach(function(t){ t.setAttribute('aria-selected', String(t === tab)); });
        dw.querySelectorAll('.drawer-pane').forEach(function(p){ p.classList.toggle('on', p.getAttribute('data-pane') === key); });
      });
    });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && dw.classList.contains('open')){ closeDw(); } });
  }

  /* ───────────── COMMAND ⌘K ───────────── */
  var ck = document.getElementById('demoCmdk');
  if(ck){
    var input = ck.querySelector('#cmdkInput');
    var list = ck.querySelector('#cmdkList');
    var empty = ck.querySelector('#cmdkEmpty');
    var rows = function(){ return Array.prototype.slice.call(list.querySelectorAll('[data-row]')); };
    function visibleRows(){ return rows().filter(function(r){ return !r.classList.contains('hidden'); }); }
    function setActive(row){ rows().forEach(function(r){ r.classList.remove('on'); }); if(row){ row.classList.add('on'); row.scrollIntoViewIfNeeded ? row.scrollIntoViewIfNeeded() : null; } }
    function openCk(){ ck.classList.add('open'); input.value = ''; filter(); setTimeout(function(){ input.focus(); }, 60); }
    function closeCk(){ ck.classList.remove('open'); }
    function filter(){
      var q = input.value.trim().toLowerCase();
      var anyVisible = false;
      rows().forEach(function(r){
        var hit = !q || (r.getAttribute('data-label') || '').indexOf(q) > -1;
        r.classList.toggle('hidden', !hit);
        if(hit){ anyVisible = true; }
      });
      // esconde sub-cabeçalho de seção sem itens visíveis
      list.querySelectorAll('[data-sec]').forEach(function(sec){
        var has = false, n = sec.nextElementSibling;
        while(n && !n.hasAttribute('data-sec')){ if(n.hasAttribute('data-row') && !n.classList.contains('hidden')){ has = true; break; } n = n.nextElementSibling; }
        sec.classList.toggle('hidden', !has);
      });
      empty.classList.toggle('hidden', anyVisible);
      var vis = visibleRows();
      setActive(vis[0] || null);
    }
    ck.querySelectorAll('[data-open-cmdk]').forEach(function(b){ b.addEventListener('click', openCk); });
    ck.querySelectorAll('[data-close-cmdk]').forEach(function(b){ b.addEventListener('click', closeCk); });
    input.addEventListener('input', filter);
    rows().forEach(function(r){
      r.addEventListener('mousemove', function(){ setActive(r); });
      r.addEventListener('click', function(){ closeCk(); });
    });
    document.addEventListener('keydown', function(e){
      // ⌘K / Ctrl+K abre
      if((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')){ e.preventDefault(); ck.classList.contains('open') ? closeCk() : openCk(); return; }
      if(!ck.classList.contains('open')) return;
      var vis = visibleRows();
      var idx = vis.findIndex(function(r){ return r.classList.contains('on'); });
      if(e.key === 'ArrowDown'){ e.preventDefault(); setActive(vis[Math.min(idx + 1, vis.length - 1)] || vis[0]); }
      else if(e.key === 'ArrowUp'){ e.preventDefault(); setActive(vis[Math.max(idx - 1, 0)] || vis[0]); }
      else if(e.key === 'Enter'){ e.preventDefault(); if(vis[idx]){ vis[idx].click(); } }
      else if(e.key === 'Escape'){ e.preventDefault(); closeCk(); }
    });
  }

  /* ───────────── DATATABLE: saved views + busca + chips ───────────── */
  var views = document.getElementById('demoViews');
  if(views && dt){
    var tb2 = dt.querySelector('tbody');
    var tsearch = document.getElementById('demoTableSearch');
    var chips = document.getElementById('demoChips');
    var VLABEL = { atraso: 'Atrasadas', prod: 'Produção', fila: 'Na fila', pronto: 'Prontas' };
    var st = { view: 'all', q: '' };
    function rowStatus(row){
      var s = row.querySelector('.spill'); if(!s) return '';
      return ['atraso','prod','fila','pronto'].filter(function(c){ return s.classList.contains(c); })[0] || '';
    }
    function chip(label, val, onx){
      var c = document.createElement('span'); c.className = 'fchip';
      c.innerHTML = '<b>' + label + ':</b> ' + val + ' <button class="x" aria-label="Remover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';
      c.querySelector('.x').addEventListener('click', onx); return c;
    }
    function renderChips(){
      chips.innerHTML = '';
      if(st.view !== 'all'){ chips.appendChild(chip('Vista', VLABEL[st.view], function(){ setView('all'); })); }
      if(st.q){ chips.appendChild(chip('Busca', '"' + st.q + '"', function(){ if(tsearch) tsearch.value = ''; st.q = ''; apply(); })); }
    }
    function apply(){
      tb2.querySelectorAll('tr:not(.dt-detail-row)').forEach(function(row){
        var okView = st.view === 'all' || rowStatus(row) === st.view;
        var nameEl = row.querySelector('.cell-primary b');
        var name = nameEl ? nameEl.textContent.toLowerCase() : '';
        var okQ = !st.q || name.indexOf(st.q) > -1;
        var show = okView && okQ;
        row.hidden = !show;
        var d = row.nextElementSibling;
        if(d && d.classList.contains('dt-detail-row')){ if(!show) d.hidden = true; }
      });
      renderChips();
    }
    function setView(v){
      st.view = v;
      views.querySelectorAll('button').forEach(function(b){ b.classList.toggle('on', b.getAttribute('data-view') === v); });
      apply();
    }
    views.querySelectorAll('button').forEach(function(b){ b.addEventListener('click', function(){ setView(b.getAttribute('data-view')); }); });
    if(tsearch){ tsearch.addEventListener('input', function(){ st.q = tsearch.value.trim().toLowerCase(); apply(); }); }
  }

  /* ───────────── TOAST: stack + auto-dismiss + progresso ───────────── */
  var toastStack = document.getElementById('demoToasts');
  if(toastStack){
    var MSG = {
      ok:     { cls: 'ok',     txt: 'OS #4821 aprovada',            ico: '<path d="M20 6 9 17l-5-5"/>' },
      info:   { cls: '',       txt: 'Arte enviada ao cliente.',      ico: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>' },
      warn:   { cls: 'warn',   txt: 'NF-e em processamento na SEFAZ.',ico: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>' },
      danger: { cls: 'danger', txt: 'Falha ao salvar.',              ico: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>' }
    };
    document.querySelectorAll('[data-toast]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var m = MSG[btn.getAttribute('data-toast')] || MSG.info;
        var t = document.createElement('div');
        t.className = 'toast enter run ' + m.cls;
        t.style.setProperty('--toast-dur', '4s');
        t.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' + m.ico + '</svg>' + m.txt +
          '<button class="tx" aria-label="Dispensar"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button><span class="prog"></span>';
        toastStack.appendChild(t);
        var to = setTimeout(dismiss, 4000);
        function dismiss(){ clearTimeout(to); t.classList.add('leave'); setTimeout(function(){ t.remove(); }, 220); }
        t.querySelector('.tx').addEventListener('click', dismiss);
      });
    });
  }

  /* ───────────── MODAL (overlay) ───────────── */
  var md = document.getElementById('demoModal');
  if(md){
    var openM = function(){ md.classList.add('open'); };
    var closeM = function(){ md.classList.remove('open'); };
    md.querySelectorAll('[data-open-modal]').forEach(function(b){ b.addEventListener('click', openM); });
    md.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click', closeM); });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && md.classList.contains('open')){ closeM(); } });
  }

  /* ───────────── POPOVER navegável por teclado ───────────── */
  var pop = document.getElementById('demoPop');
  if(pop){
    var trig = pop.querySelector('.pop-trigger');
    var mitems = Array.prototype.slice.call(pop.querySelectorAll('.menu-item'));
    var pactive = -1;
    function pSet(i){ pactive = i; mitems.forEach(function(it, n){ it.classList.toggle('on', n === i); }); }
    function openP(){ pop.classList.add('open'); trig.setAttribute('aria-expanded', 'true'); pSet(0); }
    function closeP(){ pop.classList.remove('open'); trig.setAttribute('aria-expanded', 'false'); mitems.forEach(function(it){ it.classList.remove('on'); }); pactive = -1; }
    trig.addEventListener('click', function(e){ e.stopPropagation(); pop.classList.contains('open') ? closeP() : openP(); });
    mitems.forEach(function(it, n){ it.addEventListener('mousemove', function(){ pSet(n); }); it.addEventListener('click', closeP); });
    document.addEventListener('click', function(e){ if(pop.classList.contains('open') && !pop.contains(e.target)){ closeP(); } });
    document.addEventListener('keydown', function(e){
      if(!pop.classList.contains('open')) return;
      if(e.key === 'ArrowDown'){ e.preventDefault(); pSet(Math.min(pactive + 1, mitems.length - 1)); }
      else if(e.key === 'ArrowUp'){ e.preventDefault(); pSet(Math.max(pactive - 1, 0)); }
      else if(e.key === 'Enter'){ e.preventDefault(); if(mitems[pactive]){ mitems[pactive].click(); } }
      else if(e.key === 'Escape'){ e.preventDefault(); closeP(); trig.focus(); }
    });
  }

  /* ───────────── PAGINATION interativa ───────────── */
  var pager = document.getElementById('demoPager');
  if(pager){
    var total = +pager.getAttribute('data-total'), per = +pager.getAttribute('data-per');
    var page = +pager.getAttribute('data-page'), pages = Math.ceil(total / per);
    var nums = pager.querySelector('.pg-nums'), meta = pager.querySelector('.meta');
    var prevB = pager.querySelector('[data-pg="prev"]'), nextB = pager.querySelector('[data-pg="next"]');
    function pgRange(){
      var set = {}; var add = function(v){ set[v] = true; };
      add(1); add(pages);
      for(var i = page - 1; i <= page + 1; i++){ if(i >= 1 && i <= pages){ add(i); } }
      var sorted = Object.keys(set).map(Number).sort(function(a, b){ return a - b; });
      var out = [], prev = 0;
      sorted.forEach(function(v){ if(v - prev > 1){ out.push('…'); } out.push(v); prev = v; });
      return out;
    }
    function pgRender(){
      nums.innerHTML = '';
      pgRange().forEach(function(v){
        if(v === '…'){ var s = document.createElement('span'); s.className = 'pg ellipsis'; s.textContent = '…'; nums.appendChild(s); return; }
        var b = document.createElement('button'); b.className = 'pg'; b.textContent = v;
        if(v === page){ b.setAttribute('aria-current', 'page'); }
        b.addEventListener('click', function(){ page = v; pgRender(); });
        nums.appendChild(b);
      });
      prevB.disabled = page === 1; nextB.disabled = page === pages;
      meta.textContent = ((page - 1) * per + 1) + '–' + Math.min(page * per, total) + ' de ' + total;
    }
    prevB.addEventListener('click', function(){ if(page > 1){ page--; pgRender(); } });
    nextB.addEventListener('click', function(){ if(page < pages){ page++; pgRender(); } });
    pgRender();
  }

  /* ───────────── ADDRESS toggle ───────────── */
  var addrSame = document.getElementById('addrSame');
  var addrDeliv = document.getElementById('addrDeliv');
  if(addrSame && addrDeliv){
    function syncAddr(){ addrDeliv.classList.toggle('muted-card', addrSame.checked); }
    addrSame.addEventListener('change', syncAddr); syncAddr();
  }

  /* ───────────── FILE UPLOAD (simulação) ───────────── */
  var up = document.getElementById('demoUpload');
  if(up){
    var dz = up.querySelector('[data-drop]');
    var list = up.querySelector('.file-list');
    var n = 0;
    var NAMES = ['arte-cardapio-A4.pdf', 'flyer-promo-frente.png', 'cartao-visita-v2.ai', 'adesivo-vinil.pdf'];
    function addFile(){
      var name = NAMES[n % NAMES.length]; n++;
      var item = document.createElement('div'); item.className = 'file-item';
      item.innerHTML = '<div class="file-thumb">ARTE</div><div class="file-meta"><b>' + name + '</b><small>enviando…</small><div class="file-prog"><i style="width:0"></i></div></div><button class="btn icon sm rm" aria-label="Remover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';
      list.insertBefore(item, list.firstChild);
      var bar = item.querySelector('.file-prog i');
      var meta = item.querySelector('.file-meta small');
      requestAnimationFrame(function(){ bar.style.width = '100%'; });
      setTimeout(function(){ meta.textContent = (6 + Math.floor(Math.random() * 16)) + ',' + Math.floor(Math.random() * 9) + ' MB · CMYK'; var b = document.createElement('span'); b.className = 'badge warn'; b.textContent = 'em análise'; item.insertBefore(b, item.querySelector('.rm')); }, 700);
      item.querySelector('.rm').addEventListener('click', function(){ item.remove(); });
    }
    dz.addEventListener('click', addFile);
    dz.addEventListener('keydown', function(e){ if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); addFile(); } });
  }

  /* ───────────── QUICK ENTRY (POS) ───────────── */
  var qe = document.getElementById('demoQE');
  if(qe){
    var lines = qe.querySelector('#qeLines');
    var totalEl = qe.querySelector('#qeTotal');
    var countEl = qe.querySelector('#qeCount');
    var cart = {};
    function money(v){ return 'R$ ' + v.toFixed(2).replace('.', ','); }
    function render(){
      var keys = Object.keys(cart);
      lines.innerHTML = '';
      if(!keys.length){ lines.innerHTML = '<div class="qe-empty">Toque num produto para começar.</div>'; }
      var total = 0, count = 0;
      keys.forEach(function(k){
        var it = cart[k]; var lt = it.price * it.qty; total += lt; count += it.qty;
        var row = document.createElement('div'); row.className = 'qe-line';
        row.innerHTML = '<span class="nm">' + k + '<small>' + money(it.price) + '</small></span>' +
          '<span class="qe-qty"><button data-dec>−</button><span>' + it.qty + '</span><button data-inc>+</button></span>' +
          '<span class="lt">' + money(lt) + '</span>';
        row.querySelector('[data-inc]').addEventListener('click', function(){ it.qty++; render(); });
        row.querySelector('[data-dec]').addEventListener('click', function(){ it.qty--; if(it.qty <= 0){ delete cart[k]; } render(); });
        lines.appendChild(row);
      });
      totalEl.textContent = money(total);
      countEl.textContent = count + (count === 1 ? ' item' : ' itens');
    }
    qe.querySelectorAll('.qe-prod button').forEach(function(b){
      b.addEventListener('click', function(){
        var name = b.getAttribute('data-prod'), price = parseFloat(b.getAttribute('data-price'));
        if(!cart[name]){ cart[name] = { price: price, qty: 0 }; }
        cart[name].qty++; render();
      });
    });
  }

  /* ───────────── DATE PICKER ───────────── */
  var dpk = document.getElementById('demoDate');
  if(dpk){
    var MONTHS = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    var WD = ['D','S','T','Q','Q','S','S'];
    var field = dpk.querySelector('.dp-field'), valEl = dpk.querySelector('.val');
    var monthEl = dpk.querySelector('[data-month]'), gridEl = dpk.querySelector('[data-grid]');
    var today = new Date(); var view = new Date(today.getFullYear(), today.getMonth(), 1); var sel = null;
    function pad(n){ return (n < 10 ? '0' : '') + n; }
    function build(){
      monthEl.textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
      gridEl.innerHTML = '';
      WD.forEach(function(d){ var w = document.createElement('div'); w.className = 'dp-wd'; w.textContent = d; gridEl.appendChild(w); });
      var first = new Date(view.getFullYear(), view.getMonth(), 1);
      var start = first.getDay();
      var dim = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
      var prevDim = new Date(view.getFullYear(), view.getMonth(), 0).getDate();
      for(var i = 0; i < start; i++){ cell(prevDim - start + 1 + i, true, -1); }
      for(var d = 1; d <= dim; d++){ cell(d, false, 0); }
      var rest = (start + dim) % 7; if(rest){ for(var k = 1; k <= 7 - rest; k++){ cell(k, true, 1); } }
    }
    function cell(day, muted, mShift){
      var b = document.createElement('button'); b.className = 'dp-day' + (muted ? ' muted' : ''); b.textContent = day;
      var dt = new Date(view.getFullYear(), view.getMonth() + mShift, day);
      if(!muted){
        if(dt.toDateString() === today.toDateString()){ b.classList.add('today'); }
        if(sel && dt.toDateString() === sel.toDateString()){ b.classList.add('sel'); }
      }
      b.addEventListener('click', function(){
        sel = dt; valEl.textContent = pad(dt.getDate()) + '/' + pad(dt.getMonth() + 1) + '/' + dt.getFullYear(); valEl.classList.remove('empty');
        if(mShift !== 0){ view = new Date(dt.getFullYear(), dt.getMonth(), 1); }
        build(); dpk.classList.remove('open');
      });
      gridEl.appendChild(b);
    }
    field.addEventListener('click', function(e){ e.stopPropagation(); dpk.classList.toggle('open'); });
    dpk.querySelector('[data-prev]').addEventListener('click', function(){ view = new Date(view.getFullYear(), view.getMonth() - 1, 1); build(); });
    dpk.querySelector('[data-next]').addEventListener('click', function(){ view = new Date(view.getFullYear(), view.getMonth() + 1, 1); build(); });
    document.addEventListener('click', function(e){ if(!dpk.contains(e.target)){ dpk.classList.remove('open'); } });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ dpk.classList.remove('open'); } });
    build();
  }

  /* ───────────── COMBOBOX ───────────── */
  var cb = document.getElementById('demoCombo');
  if(cb){
    var cinput = cb.querySelector('input');
    var opts = Array.prototype.slice.call(cb.querySelectorAll('.combo-opt'));
    var cempty = cb.querySelector('.combo-empty');
    var cactive = -1;
    function visOpts(){ return opts.filter(function(o){ return !o.classList.contains('hidden'); }); }
    function cSet(i){ var v = visOpts(); cactive = i; opts.forEach(function(o){ o.classList.remove('on'); }); if(v[i]){ v[i].classList.add('on'); } }
    function openC(){ cb.classList.add('open'); }
    function closeC(){ cb.classList.remove('open'); }
    function esc(s){ return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    function cFilter(){
      var q = cinput.value.trim().toLowerCase(); var any = false;
      opts.forEach(function(o){
        var label = o.getAttribute('data-opt');
        var hit = !q || label.toLowerCase().indexOf(q) > -1;
        o.classList.toggle('hidden', !hit);
        var lbl = o.querySelector('.lbl');
        if(hit && q){ lbl.innerHTML = label.replace(new RegExp('(' + esc(q) + ')', 'i'), '<span class="hl">$1</span>'); }
        else { lbl.textContent = label; }
        if(hit) any = true;
      });
      cempty.classList.toggle('hidden', any);
      cSet(0);
    }
    cinput.addEventListener('focus', openC);
    cinput.addEventListener('input', function(){ openC(); cFilter(); });
    opts.forEach(function(o){
      o.addEventListener('mousemove', function(){ var v = visOpts(); cSet(v.indexOf(o)); });
      o.addEventListener('click', function(){ cinput.value = o.getAttribute('data-opt'); opts.forEach(function(x){ x.querySelector('.lbl').textContent = x.getAttribute('data-opt'); }); closeC(); });
    });
    cb.querySelector('.combo-field').addEventListener('click', function(){ cinput.focus(); });
    document.addEventListener('click', function(e){ if(!cb.contains(e.target)){ closeC(); } });
    cinput.addEventListener('keydown', function(e){
      if(e.key === 'ArrowDown'){ e.preventDefault(); openC(); cSet(Math.min(cactive + 1, visOpts().length - 1)); }
      else if(e.key === 'ArrowUp'){ e.preventDefault(); cSet(Math.max(cactive - 1, 0)); }
      else if(e.key === 'Enter'){ e.preventDefault(); var v = visOpts(); if(v[cactive]){ v[cactive].click(); } }
      else if(e.key === 'Escape'){ closeC(); }
    });
  }

  /* ───────────── INLINE EDIT ───────────── */
  document.querySelectorAll('.ie-val[data-edit]').forEach(function(val){
    val.addEventListener('click', function(){
      if(val.dataset.editing) return;
      var text = val.childNodes[0].nodeValue.trim();
      var input = document.createElement('input'); input.className = 'ie-input'; input.value = text;
      input.style.width = Math.max(80, text.length * 9) + 'px';
      val.dataset.editing = '1'; val.style.display = 'none';
      val.parentNode.insertBefore(input, val); input.focus(); input.select();
      var finished = false;
      function save(commit){
        if(finished) return; finished = true;
        if(commit && input.value.trim()){ val.childNodes[0].nodeValue = input.value.trim(); }
        input.remove(); val.style.display = ''; delete val.dataset.editing;
      }
      input.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ save(true); } else if(e.key === 'Escape'){ save(false); } });
      input.addEventListener('blur', function(){ save(true); });
    });
  });

  /* ───────────── KANBAN drag-and-drop + recolher + total + add ───────────── */
  var kb = document.getElementById('demoKanban');
  if(kb){
    var dragged = null;
    function fmt(n){ return 'R$ ' + n.toLocaleString('pt-BR'); }
    function recount(){
      kb.querySelectorAll('.kan-col').forEach(function(col){
        var cards = col.querySelectorAll('.kan-card');
        var cEl = col.querySelector('.c'); if(cEl){ cEl.textContent = cards.length; }
        var total = 0; cards.forEach(function(c){ total += +(c.getAttribute('data-val') || 0); });
        var v = col.querySelector('.kan-foot .v'); if(v){ v.textContent = fmt(total); }
        var wip = col.querySelector('.kan-wip');
        if(wip){ var lim = +col.getAttribute('data-wip'); wip.textContent = cards.length + '/' + lim; wip.classList.toggle('over', cards.length > lim); }
      });
    }
    function wireCard(card){
      card.addEventListener('dragstart', function(){ dragged = card; setTimeout(function(){ card.classList.add('dragging'); }, 0); });
      card.addEventListener('dragend', function(){ card.classList.remove('dragging'); dragged = null; kb.querySelectorAll('.kan-list').forEach(function(l){ l.classList.remove('drop'); }); });
    }
    function wireList(list){
      list.addEventListener('dragover', function(e){ e.preventDefault(); list.classList.add('drop'); });
      list.addEventListener('dragleave', function(e){ if(!list.contains(e.relatedTarget)){ list.classList.remove('drop'); } });
      list.addEventListener('drop', function(e){ e.preventDefault(); list.classList.remove('drop'); if(dragged && dragged.parentNode !== list){ list.appendChild(dragged); recount(); } });
    }
    function wireCol(col){
      var btn = col.querySelector('.collapse');
      if(btn){ btn.addEventListener('click', function(e){ e.stopPropagation(); col.classList.toggle('collapsed'); }); }
      col.addEventListener('click', function(){ if(col.classList.contains('collapsed')){ col.classList.remove('collapsed'); } });
      var list = col.querySelector('.kan-list'); if(list){ wireList(list); }
    }
    kb.querySelectorAll('.kan-card').forEach(wireCard);
    kb.querySelectorAll('.kan-col').forEach(wireCol);
    var addBtn = document.getElementById('kanAdd');
    if(addBtn){
      addBtn.addEventListener('click', function(){
        var col = document.createElement('div'); col.className = 'kan-col'; col.setAttribute('data-stage', 'custom');
        col.innerHTML = '<div class="kan-col-h"><button class="collapse" aria-label="Recolher"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="15 18 9 12 15 6"/></svg></button><span class="dot" style="background:var(--text-3)"></span><span class="lbl" contenteditable="true">Nova etapa</span><span class="c">0</span></div><div class="kan-list"></div><div class="kan-foot"><span>Total</span><span class="v">R$ 0</span></div>';
        kb.insertBefore(col, addBtn);
        wireCol(col); recount();
        var lbl = col.querySelector('.lbl');
        lbl.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); lbl.blur(); } });
        lbl.focus();
        var r = document.createRange(); r.selectNodeContents(lbl); var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
      });
    }
    recount();
  }
})();
