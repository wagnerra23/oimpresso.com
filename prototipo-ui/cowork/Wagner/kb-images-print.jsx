// kb-images-print.jsx — Bloco de imagem + Favoritos + Imprimir SOP
(() => {
const { useState, useEffect, useRef } = React;

// ─────────────────────────────────────────────────────────────────
// HOOK: FAVORITOS PESSOAIS
// ─────────────────────────────────────────────────────────────────
function loadFavs() {
  try { return JSON.parse(localStorage.getItem("oimpresso.kb.favs") || "[]"); }
  catch (e) { return []; }
}
function saveFavs(arr) {
  try { localStorage.setItem("oimpresso.kb.favs", JSON.stringify(arr)); } catch (e) {}
}

function useKBFavorites() {
  const [favs, setFavs] = useState(loadFavs);
  useEffect(() => { saveFavs(favs); }, [favs]);

  const isFav = (id) => favs.includes(id);
  const toggleFav = (id) => {
    setFavs(f => f.includes(id) ? f.filter(x => x !== id) : [id, ...f]);
  };
  return { favs, isFav, toggleFav };
}
window.useKBFavorites = useKBFavorites;

// ─────────────────────────────────────────────────────────────────
// COMPONENTE: ESTRELA DE FAVORITO
// ─────────────────────────────────────────────────────────────────
function KBFavStar({ active, onClick, size = 16 }) {
  return (
    <button
      className={"kb-fav-star" + (active ? " on" : "")}
      onClick={(e) => { e.stopPropagation(); onClick(); }}
      title={active ? "Remover dos favoritos (B)" : "Adicionar aos favoritos (B)"}
      aria-pressed={active}
      style={{ width: size + 12, height: size + 12 }}>
      <svg width={size} height={size} viewBox="0 0 24 24" fill={active ? "currentColor" : "none"} stroke="currentColor" strokeWidth="2" strokeLinejoin="round">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
      </svg>
    </button>
  );
}
window.KBFavStar = KBFavStar;

// ─────────────────────────────────────────────────────────────────
// IMAGE BLOCK EDITOR — usado dentro do block editor
// ─────────────────────────────────────────────────────────────────
function KBImageBlockEditor({ block, onChange }) {
  const fileRef = useRef(null);
  const [urlInput, setUrlInput] = useState(block.src || "");

  const setSrc = (src) => {
    onChange({ ...block, src });
    setUrlInput(src);
  };

  const onFile = (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => setSrc(reader.result);
    reader.readAsDataURL(file);
  };

  const onPaste = (e) => {
    const items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    for (const it of items) {
      if (it.type && it.type.startsWith("image/")) {
        const file = it.getAsFile();
        if (file) {
          e.preventDefault();
          const reader = new FileReader();
          reader.onload = () => setSrc(reader.result);
          reader.readAsDataURL(file);
          return;
        }
      }
    }
  };

  return (
    <div className="kb-block-image" onPaste={onPaste}>
      {block.src ? (
        <div className="kb-block-image-preview">
          <img src={block.src} alt={block.alt || ""}/>
          <button className="kb-block-image-clear" onClick={() => setSrc("")} title="Remover imagem">×</button>
        </div>
      ) : (
        <div className="kb-block-image-empty">
          <span className="kb-block-image-icon">▢</span>
          <div className="kb-block-image-actions">
            <button onClick={() => fileRef.current && fileRef.current.click()}>Anexar arquivo</button>
            <input ref={fileRef} type="file" accept="image/*" style={{display:"none"}} onChange={onFile}/>
            <span className="kb-block-image-or">ou</span>
            <input
              type="url"
              className="kb-block-image-url"
              placeholder="Cole URL ou print (Ctrl+V)"
              value={urlInput}
              onChange={e => setUrlInput(e.target.value)}
              onBlur={() => urlInput.trim() && setSrc(urlInput.trim())}
              onKeyDown={e => { if (e.key === "Enter") { e.preventDefault(); setSrc(urlInput.trim()); }}}/>
          </div>
        </div>
      )}
      <div className="kb-block-image-meta">
        <input
          className="kb-block-image-alt"
          placeholder="Descrição (alt — acessibilidade)"
          value={block.alt || ""}
          onChange={e => onChange({ ...block, alt: e.target.value })}/>
        <input
          className="kb-block-image-cap"
          placeholder="Legenda (opcional, aparece sob a imagem)"
          value={block.caption || ""}
          onChange={e => onChange({ ...block, caption: e.target.value })}/>
      </div>
    </div>
  );
}
window.KBImageBlockEditor = KBImageBlockEditor;

// ─────────────────────────────────────────────────────────────────
// IMAGE BLOCK RENDER — usado no leitor
// ─────────────────────────────────────────────────────────────────
function KBImageBlockView({ block }) {
  if (!block.src) {
    return (
      <div className="kb-art-image kb-art-image-placeholder">
        <span>imagem (sem fonte)</span>
      </div>
    );
  }
  return (
    <figure className="kb-art-image">
      <img src={block.src} alt={block.alt || ""}/>
      {block.caption && <figcaption>{block.caption}</figcaption>}
    </figure>
  );
}
window.KBImageBlockView = KBImageBlockView;

// ─────────────────────────────────────────────────────────────────
// IMPRIMIR SOP — modal de preview + impressão
// ─────────────────────────────────────────────────────────────────
function KBPrintSOP({ article, onClose }) {
  const today = new Date().toLocaleDateString("pt-BR", { day:"2-digit", month:"2-digit", year:"numeric" });

  useEffect(() => {
    document.body.classList.add("kb-printing");
    return () => document.body.classList.remove("kb-printing");
  }, []);

  const doPrint = () => {
    window.print();
  };

  return (
    <React.Fragment>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-print-modal" role="dialog">
        <header className="kb-modal-h kb-no-print">
          <div>
            <small>Imprimir SOP — modo balcão</small>
            <h3>Pré-visualização</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>

        <div className="kb-print-body">
          {/* HEADER OFICIAL — aparece tanto na tela quanto na impressão */}
          <div className="kb-print-sheet">
            <header className="kb-print-head">
              <div className="kb-print-brand">
                <div className="kb-print-logo">OI</div>
                <div>
                  <b>Oimpresso ERP</b>
                  <small>Procedimento Operacional Padrão</small>
                </div>
              </div>
              <div className="kb-print-id">
                <small>Artigo</small>
                <b className="mono">#{article.id}</b>
              </div>
            </header>

            <div className="kb-print-title-wrap">
              <h1 className="kb-print-title">{article.title}</h1>
              <p className="kb-print-excerpt">{article.excerpt}</p>
              <div className="kb-print-meta">
                <span><b>Categoria:</b> {(window.kbCategoryLabel || (c=>c))(article.cat)}</span>
                <span><b>Equipamento:</b> {article.equip}</span>
                <span><b>Tempo:</b> {article.readTime} min</span>
                <span><b>Autor:</b> {article.author}</span>
                <span><b>Atualizado:</b> {article.updated}</span>
              </div>
            </div>

            <div className="kb-print-body-content">
              {article.body.map((b, i) => {
                if (b.kind === "h2") return <h2 key={i}>{b.t}</h2>;
                if (b.kind === "para") return <p key={i}>{b.t}</p>;
                if (b.kind === "list") return (
                  <ol key={i}>{b.items.map((it, j) => <li key={j}>{it}</li>)}</ol>
                );
                if (b.kind === "callout") return (
                  <div key={i} className={"kb-print-callout " + (b.tone || "info")}>
                    <strong>{b.tone === "bad" ? "ATENÇÃO" : b.tone === "warn" ? "Aviso" : b.tone === "ok" ? "Boa prática" : "Nota"}:</strong> {b.t}
                  </div>
                );
                if (b.kind === "image" && window.KBImageBlockView) {
                  return <window.KBImageBlockView key={i} block={b}/>;
                }
                return null;
              })}
            </div>

            {article.tags && article.tags.length > 0 && (
              <div className="kb-print-tags">
                <small>Etiquetas:</small>
                {article.tags.map(t => <span key={t}>{t}</span>)}
              </div>
            )}

            <footer className="kb-print-foot">
              <span>Impresso em {today}</span>
              <span>Oimpresso ERP · Base de Conhecimento</span>
              <span>Página <span className="mono">1</span></span>
            </footer>
          </div>
        </div>

        <footer className="kb-print-actions kb-no-print">
          <small>O documento será impresso com header e footer da Oimpresso. Cole o impresso ao lado do equipamento.</small>
          <div style={{display:"flex", gap:8}}>
            <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
            <button className="os-btn primary" onClick={doPrint}>Imprimir agora</button>
          </div>
        </footer>
      </div>
    </React.Fragment>
  );
}
window.KBPrintSOP = KBPrintSOP;

// Helper pra label da categoria (usado no print header)
window.kbCategoryLabel = (catId) => {
  const map = {
    producao: "Produção", equip: "Equipamentos", arte: "Pré-impressão",
    atendim: "Atendimento", fiscal: "Fiscal & financeiro",
    sistema: "Sistema (ERP)", rh: "Pessoas",
  };
  return map[catId] || catId;
};

})();
