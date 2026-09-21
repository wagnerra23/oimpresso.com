/* ═══════════════════════════════════════════════════════════════════
   ds-behavior.js — comportamento + ARIA dos componentes do DS (v3)
   Vanilla JS, zero deps. Auto-inicializa no DOMContentLoaded.
   No repo React, o equivalente vem do Radix (ver Design System v3.html §E2).
   Cobre: combobox, popover/menu, toggle, tabs, cell-edit, ⌘K, saved-views.

   Uso (HTML/Blade): <script src="ds-behavior.js" defer></script>
   Marque os componentes com data-attrs:
     [data-live-combobox] [data-live-popover] [data-live-toggle]
     [data-live-tabs] [data-cmdk-open] .saved-views .cell-edit
   ═══════════════════════════════════════════════════════════════════ */
(function(){
  function init(){
/* ── Combobox: filtra ao digitar, ARIA, teclado ── */
  document.querySelectorAll("[data-live-combobox]").forEach(function(cb){
    var input = cb.querySelector("input");
    var list  = cb.querySelector(".combobox-list");
    var items = Array.prototype.slice.call(cb.querySelectorAll(".combobox-item"));
    var active = -1;

    function open(){ list.hidden = false; input.setAttribute("aria-expanded","true"); }
    function close(){ list.hidden = true; input.setAttribute("aria-expanded","false"); active = -1; paint(); }
    function visibleItems(){ return items.filter(function(i){ return !i.hidden; }); }
    function paint(){
      items.forEach(function(i){ i.setAttribute("aria-selected","false"); });
      var vis = visibleItems();
      if(active >= 0 && vis[active]) vis[active].setAttribute("aria-selected","true");
    }
    function filter(){
      var q = input.value.trim().toLowerCase();
      items.forEach(function(i){
        var t = i.textContent.toLowerCase();
        i.hidden = q.length > 0 && t.indexOf(q) === -1;
      });
      active = -1; open(); paint();
    }
    input.addEventListener("input", filter);
    input.addEventListener("focus", open);
    input.addEventListener("keydown", function(e){
      var vis = visibleItems();
      if(e.key === "ArrowDown"){ e.preventDefault(); active = Math.min(active+1, vis.length-1); paint(); }
      else if(e.key === "ArrowUp"){ e.preventDefault(); active = Math.max(active-1, 0); paint(); }
      else if(e.key === "Enter" && vis[active]){ e.preventDefault(); input.value = vis[active].querySelector("span").textContent; close(); }
      else if(e.key === "Escape"){ close(); input.blur(); }
    });
    items.forEach(function(i){ i.addEventListener("click", function(){ input.value = i.querySelector("span").textContent; close(); }); });
    document.addEventListener("click", function(e){ if(!cb.contains(e.target)) close(); });
  });

  /* ── Popover / Menu: toggle, ARIA, Esc, click-outside ── */
  document.querySelectorAll("[data-live-popover]").forEach(function(wrap){
    var trigger = wrap.querySelector("button");
    var pop = wrap.querySelector(".popover");
    function open(){ pop.hidden = false; trigger.setAttribute("aria-expanded","true"); }
    function close(){ pop.hidden = true; trigger.setAttribute("aria-expanded","false"); }
    trigger.addEventListener("click", function(e){ e.stopPropagation(); pop.hidden ? open() : close(); });
    pop.querySelectorAll(".menu-item").forEach(function(mi){ mi.addEventListener("click", close); });
    document.addEventListener("click", function(e){ if(!wrap.contains(e.target)) close(); });
    document.addEventListener("keydown", function(e){ if(e.key === "Escape") close(); });
  });

  /* ── Toggle: aria-checked espelha o input ── */
  document.querySelectorAll("[data-live-toggle] input").forEach(function(inp){
    function sync(){ inp.setAttribute("aria-checked", inp.checked ? "true" : "false"); }
    inp.addEventListener("change", sync); sync();
  });

  /* ── Tabs: troca painel, roving, setas ── */
  document.querySelectorAll("[data-live-tabs]").forEach(function(wrap){
    var tabs = Array.prototype.slice.call(wrap.querySelectorAll(".tab"));
    function select(idx){
      tabs.forEach(function(t,i){
        var on = i === idx;
        t.setAttribute("aria-selected", on ? "true" : "false");
        t.tabIndex = on ? 0 : -1;
        var panel = wrap.querySelector('[data-tab-panel="'+t.dataset.panel+'"]');
        if(panel) panel.hidden = !on;
      });
    }
    tabs.forEach(function(t,i){
      t.addEventListener("click", function(){ select(i); });
      t.addEventListener("keydown", function(e){
        if(e.key === "ArrowRight"){ e.preventDefault(); var n=(i+1)%tabs.length; select(n); tabs[n].focus(); }
        else if(e.key === "ArrowLeft"){ e.preventDefault(); var p=(i-1+tabs.length)%tabs.length; select(p); tabs[p].focus(); }
      });
    });
  });

  /* ── Cell-edit: click ativa input inline ── */
  document.querySelectorAll(".cell-edit:not(.editing)").forEach(function(cell){
    cell.addEventListener("click", function(){
      if(cell.classList.contains("editing")) return;
      var txt = cell.childNodes[0].textContent.trim();
      cell.classList.add("editing");
      cell.innerHTML = '<input value="'+txt.replace(/"/g,"&quot;")+'"/>';
      var inp = cell.querySelector("input"); inp.focus(); inp.select();
      function commit(restore){
        var v = restore ? txt : inp.value;
        cell.classList.remove("editing");
        cell.innerHTML = v + ' <span class="edit-hint"><svg class="ic-svg" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></span>';
        bindCellEdit(cell);
      }
      inp.addEventListener("keydown", function(e){ if(e.key==="Enter") commit(false); else if(e.key==="Escape") commit(true); });
      inp.addEventListener("blur", function(){ commit(false); });
    }, { once: true });
  });
  function bindCellEdit(cell){
    cell.addEventListener("click", function handler(){
      cell.removeEventListener("click", handler);
      cell.click();
    }, { once: true });
  }

  /* ── Command palette ⌘K: abre por tecla/botão, filtra, ↑↓ navega, Esc fecha ── */
  var cmdkData = [
    { group:"Ordens de Serviço", items:[
      { label:"#4821 · Banner Lona 3×2m", meta:"Acme · aprovação" },
      { label:"#4820 · Adesivos 200un", meta:"TechPro · acabamento" },
      { label:"#4813 · Cartões 1000un", meta:"Acme · produção" },
    ]},
    { group:"Clientes", items:[
      { label:"Acme Comércio Ltda", meta:"12 OS · R$ 28k" },
      { label:"Bravo Sinalização", meta:"7 OS" },
      { label:"TechPro Soluções", meta:"9 OS" },
    ]},
    { group:"Ações", items:[
      { label:"Nova OS", meta:"⌘N" },
      { label:"Novo cliente", meta:"" },
      { label:"Exportar relatório", meta:"" },
    ]},
  ];
  var cmdkBack = null, cmdkFlat = [], cmdkActive = 0;

  function buildCmdk(){
    cmdkBack = document.createElement("div");
    cmdkBack.className = "cmdk-back";
    cmdkBack.innerHTML =
      '<div class="cmdk" role="dialog" aria-modal="true" aria-label="Busca global">'+
        '<div class="cmdk-search"><svg class="ic-svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>'+
        '<input placeholder="Buscar OS, cliente, ação..." aria-label="Buscar"/><span class="esc">Esc</span></div>'+
        '<div class="cmdk-list" role="listbox"></div>'+
        '<div class="cmdk-foot"><span>↑↓ navegar</span><span>↵ abrir</span><span style="margin-left:auto">⌘K busca global</span></div>'+
      '</div>';
    document.body.appendChild(cmdkBack);
    var input = cmdkBack.querySelector("input");
    input.addEventListener("input", function(){ renderCmdk(input.value); });
    input.addEventListener("keydown", function(e){
      if(e.key === "ArrowDown"){ e.preventDefault(); cmdkActive = Math.min(cmdkActive+1, cmdkFlat.length-1); paintCmdk(); }
      else if(e.key === "ArrowUp"){ e.preventDefault(); cmdkActive = Math.max(cmdkActive-1, 0); paintCmdk(); }
      else if(e.key === "Enter"){ e.preventDefault(); closeCmdk(); }
      else if(e.key === "Escape"){ closeCmdk(); }
    });
    cmdkBack.addEventListener("click", function(e){ if(e.target === cmdkBack) closeCmdk(); });
  }
  function renderCmdk(q){
    q = (q||"").trim().toLowerCase();
    var listEl = cmdkBack.querySelector(".cmdk-list");
    listEl.innerHTML = ""; cmdkFlat = []; cmdkActive = 0;
    cmdkData.forEach(function(grp){
      var matches = grp.items.filter(function(it){ return q === "" || it.label.toLowerCase().indexOf(q) !== -1; });
      if(!matches.length) return;
      var gh = document.createElement("div"); gh.className = "cmdk-group";
      gh.innerHTML = '<div class="cmdk-group-h">'+grp.group+'</div>';
      matches.forEach(function(it){
        var el = document.createElement("div");
        el.className = "cmdk-item"; el.setAttribute("role","option");
        el.innerHTML = '<svg class="ic-svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg><span class="label">'+it.label+'</span>'+(it.meta?'<span class="meta">'+it.meta+'</span>':'');
        gh.appendChild(el); cmdkFlat.push(el);
        el.addEventListener("click", closeCmdk);
      });
      listEl.appendChild(gh);
    });
    paintCmdk();
  }
  function paintCmdk(){
    cmdkFlat.forEach(function(el,i){ el.setAttribute("aria-selected", i===cmdkActive ? "true":"false"); });
    if(cmdkFlat[cmdkActive]) cmdkFlat[cmdkActive].scrollIntoView({ block:"nearest" });
  }
  function openCmdk(){ if(!cmdkBack) buildCmdk(); cmdkBack.style.display = "flex"; renderCmdk(""); var i = cmdkBack.querySelector("input"); i.value=""; setTimeout(function(){ i.focus(); }, 30); }
  function closeCmdk(){ if(cmdkBack) cmdkBack.style.display = "none"; }
  document.querySelectorAll("[data-cmdk-open]").forEach(function(b){ b.addEventListener("click", openCmdk); });
  document.addEventListener("keydown", function(e){
    if((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k"){ e.preventDefault(); openCmdk(); }
  });

  /* ── Saved views: toggle menu ── */
  document.querySelectorAll(".saved-views").forEach(function(sv){
    var trig = sv.querySelector(".saved-views-trigger");
    var menu = sv.querySelector(".saved-views-menu");
    if(!trig || !menu) return;
    menu.style.display = "none";
    trig.addEventListener("click", function(e){ e.stopPropagation(); menu.style.display = menu.style.display === "none" ? "block" : "none"; });
    document.addEventListener("click", function(e){ if(!sv.contains(e.target)) menu.style.display = "none"; });
    menu.querySelectorAll(".saved-view-item").forEach(function(it){
      it.addEventListener("click", function(){
        menu.querySelectorAll(".saved-view-item").forEach(function(x){ x.classList.remove("active"); });
        it.classList.add("active");
        menu.style.display = "none";
      });
    });
  });


  }
  if(document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
