// prototipo-html-page.jsx — põe dentro do shell os protótipos que nasceram como .html solto
// (lacunas de 27/10/2026). Cada tela tem o próprio *-page.jsx, que registra o HTML dela em
// window.PROTO_HTML[<rota>]; esta página o monta numa shadow root, sem iframe. Como o HTML vem num
// .jsx citado pelo host, ele entra no build do Artefato (build-artefato-prototipo.mjs) como qualquer tela.
//
// O que ela faz com o HTML:
//   · CSS: `:root`/`html` viram `:host` e `body` vira `.proto-body`, então o tema escuro/claro do
//     protótipo segue o `data-theme` do shell e nenhuma regra vaza pro resto da tela.
//   · JS: os <script> inline rodam com um `document` que procura dentro da shadow root
//     (getElementById/querySelector*), e os `onclick` inline passam a enxergar as funções do script.
// Expõe window.ProtoHtmlPage e window.ProtoPublicasBar.
(() => {
const { useEffect, useRef, useState } = React;


if (!document.getElementById("proto-html-page-css")) {
  const s = document.createElement("style");
  s.id = "proto-html-page-css";
  s.textContent = `
.proto-html-page { height: 100%; overflow: auto; }
.proto-html-host { min-height: 100%; }
.proto-html-erro { margin: 16px; padding: 12px; border: 1px solid var(--border); border-radius: 6px; color: var(--text-dim); }
.proto-publicas-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 16px;
  border-bottom: 1px solid var(--border); background: var(--bg-2, var(--surface)); font-size: 12.5px; }
.proto-publicas-l { color: var(--text-dim); margin-right: 4px; }
.proto-publicas-b { font: inherit; padding: 4px 10px; min-height: 28px; border-radius: 6px; cursor: pointer;
  border: 1px solid var(--border); background: transparent; color: var(--text); }
.proto-publicas-b:hover { border-color: var(--accent); color: var(--accent); }
.proto-publicas-b:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }`;
  document.head.appendChild(s);
}

function escopar(css) {
  return css
    .replace(/:root:not\(\[data-theme="light"\]\)/g, ':host(:not([data-theme="light"]))')
    .replace(/:root\[data-theme="(dark|light)"\]/g, ':host([data-theme="$1"])')
    .replace(/:root/g, ":host")
    .replace(/(^|[\s,{}>])(html|body)(?=\s*[{,.:\[>~+]|\s+[^\s{])/gm, "$1.proto-body");
}

function nomesChamados(html) {
  const nomes = new Set();
  html.replace(/\son[a-z]+="([^"]*)"/g, (m, code) => {
    code.replace(/([A-Za-z_$][\w$]*)\s*\(/g, (mm, n) => { nomes.add(n); return mm; });
    return m;
  });
  return [...nomes];
}

function montar(host, texto) {
  const parsed = new DOMParser().parseFromString(texto, "text/html");
  const root = host.shadowRoot || host.attachShadow({ mode: "open" });
  root.innerHTML = "";
  const style = document.createElement("style");
  style.textContent = ":host{display:block;min-height:100%}.proto-body{min-height:100%}\n" +
    [...parsed.querySelectorAll("style")].map((s) => escopar(s.textContent)).join("\n");
  root.appendChild(style);
  const corpo = document.createElement("div");
  corpo.className = ("proto-body " + (parsed.body.className || "")).trim();
  [...parsed.body.attributes].forEach((a) => { if (a.name !== "class") corpo.setAttribute(a.name, a.value); });
  const scripts = [];
  [...parsed.body.childNodes].forEach((n) => {
    if (n.nodeName === "SCRIPT") { if (!n.src) scripts.push(n.textContent); return; }
    corpo.appendChild(document.importNode(n, true));
  });
  [...parsed.head.querySelectorAll("script:not([src])")].forEach((s) => scripts.unshift(s.textContent));
  root.appendChild(corpo);

  const doc = new Proxy(document, {
    get(t, k) {
      if (k === "getElementById") return (id) => root.getElementById(id);
      if (k === "querySelector") return (s) => root.querySelector(s);
      if (k === "querySelectorAll") return (s) => root.querySelectorAll(s);
      if (k === "activeElement") return root.activeElement;
      if (k === "body") return corpo;
      const v = Reflect.get(t, k);
      return typeof v === "function" ? v.bind(t) : v;
    },
  });
  const nomes = nomesChamados(texto);
  const exporta = "\n;return {" + nomes.map((n) => `${JSON.stringify(n)}: typeof ${n} !== "undefined" ? ${n} : undefined`).join(",") + "};";
  let ns = {};
  try { ns = new Function("document", scripts.join("\n;\n") + exporta)(doc) || {}; }
  catch (e) { console.warn("[ProtoHtmlPage] script do protótipo falhou:", e); }
  root.querySelectorAll("*").forEach((el) => {
    [...el.attributes].filter((a) => /^on[a-z]+$/.test(a.name)).forEach((a) => {
      const code = a.value;
      el.removeAttribute(a.name);
      const fn = new Function("ns", "event", "with (ns) { " + code + " }");
      el.addEventListener(a.name.slice(2), function (ev) { return fn.call(el, ns, ev); });
    });
  });
}

// `chave` = id da rota; o HTML vem de window.PROTO_HTML[chave], registrado pelo *-page.jsx da tela.
function ProtoHtmlPage({ chave, titulo }) {
  const host = useRef(null);
  const [erro, setErro] = useState(null);
  useEffect(() => {
    let vivo = true, timer = null;
    setErro(null);
    // O *-page.jsx da tela pode chegar depois desta página na fila de carga: espera o registro,
    // e só acusa erro quando a fila inteira terminou sem ele.
    const tentar = () => {
      if (!vivo) return;
      const t = (window.PROTO_HTML || {})[chave];
      if (t != null) { if (host.current) montar(host.current, t); return; }
      if (window.__oiLazyDone) { setErro("nenhum *-page.jsx registrou a tela " + chave); return; }
      timer = setTimeout(tentar, 150);
    };
    tentar();
    return () => { vivo = false; clearTimeout(timer); };
  }, [chave]);
  useEffect(() => {
    const sync = () => host.current && host.current.setAttribute("data-theme", document.documentElement.dataset.theme || "dark");
    sync();
    const mo = new MutationObserver(sync);
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
    return () => mo.disconnect();
  }, []);
  return (
    <div className="proto-html-page" data-proto-chave={chave} aria-label={titulo}>
      {erro && <div className="proto-html-erro" role="alert">Não abriu o protótipo: {erro}</div>}
      <div ref={host} className="proto-html-host" />
    </div>);
}

// "Ver como o cliente vê" — liga a tela interna às páginas públicas que ela gera.
// Item com `route` abre dentro do shell; item com `href` abre a página pública em outra aba.
function ProtoPublicasBar({ itens }) {
  const ir = (it) => {
    if (it.href) { window.open(it.href, "_blank", "noopener"); return; }
    window.dispatchEvent(new CustomEvent("pg:goto-route", { detail: it.route }));
  };
  return (
    <div className="proto-publicas-bar" role="region" aria-label="Páginas públicas desta tela">
      <span className="proto-publicas-l">Ver como o cliente vê</span>
      {itens.map((it) =>
        <button key={it.label} type="button" className="proto-publicas-b" onClick={() => ir(it)}>
          {it.label}{it.href ? " ↗" : ""}
        </button>)}
    </div>);
}

window.ProtoHtmlPage = ProtoHtmlPage;
window.ProtoPublicasBar = ProtoPublicasBar;
})();
