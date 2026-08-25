// vestuario-page.jsx — Vestuário · Etiquetas TAG (Modules/Vestuario · /vestuario/etiquetas).
// Espelho do vivo resources/js/Pages/Vestuario/Etiquetas/Index.tsx (US-VEST-020): configuração
// vinda de vestuario_settings, lote editável linha a linha (produto/variação, tamanho, cor,
// coleção, preço, SKU, EAN-13), cópias 1..100 e saída ZPL (Argox/Zebra) ou PDF.
// EAN-13 com dígito verificador calculado de verdade (GradeCurvaService/EtiquetaTagService).
// Divergência ABERTA no charter (§UX targets): a tela entrega EDIÇÃO, não preview — aqui a prévia
// da etiqueta existe como prova visual do F1, marcada como proposta pendente de [W].
// Expõe window.VestuarioPage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const A = () => window.AcessosDS || {};

const CONFIG = { width_dots: 400, height_dots: 240, dpi: 203, margin_dots: 16, qr_enabled: true };
const TAMANHOS = ["PP", "P", "M", "G", "GG", "XGG"];
const BRL = (n) => "R$ " + (Number(n) || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// EAN-13: 12 dígitos + verificador (pesos 1/3 alternados)
const ean13 = (base12) => {
  const d = String(base12).replace(/\D/g, "").padStart(12, "0").slice(0, 12);
  let s = 0;
  for (let i = 0; i < 12; i++) s += Number(d[i]) * (i % 2 ? 3 : 1);
  return d + String((10 - (s % 10)) % 10);
};

let seq = 0;
const novoItem = (p) => ({ id: "v" + ++seq, product_id: 0, nome: "", tamanho: "M", cor: "", colecao: "Verão 26", preco: 0, sku: "", ...p });

const INICIAIS = [
  novoItem({ product_id: 4471, nome: "Camiseta algodão penteado", tamanho: "M", cor: "Preto", colecao: "Verão 26", preco: 69.9, sku: "CAM-ALG-PRT-M" }),
  novoItem({ product_id: 4472, nome: "Camiseta algodão penteado", tamanho: "G", cor: "Branco", colecao: "Verão 26", preco: 69.9, sku: "CAM-ALG-BRC-G" }),
  novoItem({ product_id: 4510, nome: "Moletom canguru flanelado", tamanho: "GG", cor: "Chumbo", colecao: "Inverno 26", preco: 189.9, sku: "MOL-CNG-CHB-GG" }),
];

const IcPlus = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>;
const IcTrash = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>;
const IcDown = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3v13M7 12l5 5 5-5M4 21h16"/></svg>;
const IcPrint = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M7 9V3h10v6"/><rect x="4" y="9" width="16" height="7" rx="2"/><path d="M7 16h10v5H7z"/></svg>;

function Btn({ children, ...p }) {
  const { Button } = DS();
  // O Button do DS não repassa aria-label: para ação só-ícone o nome vai num
  // <span class="sr-only"> dentro do children, senão o leitor de tela diz só "botão".
  const rotulo = p["aria-label"];
  const conteudo = rotulo ? <>{children}<span className="sr-only">{rotulo}</span></> : children;
  if (!Button) return <button className={"os-btn " + (p.variant === "primary" ? "primary" : "ghost")} aria-label={rotulo} disabled={p.disabled} onClick={p.onClick}>{conteudo}</button>;
  return <Button {...p} title={p.title || rotulo}>{conteudo}</Button>;
}

// Barras do EAN-13 — larguras derivadas dos dígitos (prova visual, não leitura óptica)
function Barras({ code }) {
  const barras = useMemo(() => {
    const out = [];
    for (let i = 0; i < code.length; i++) {
      const d = Number(code[i]);
      out.push(1 + (d % 3), 1 + ((d + 1) % 3));
    }
    return out;
  }, [code]);
  return (
    <div className="vst-barras" aria-hidden="true">
      {barras.map((w, i) => <i key={i} style={{ width: w + "px", opacity: i % 2 ? 0 : 1 }} />)}
    </div>
  );
}

// Prévia do QR — grade determinística a partir do SKU (o real sai do easy.qrcode no ZPL/PDF)
function QrPrevia({ seed }) {
  const cells = useMemo(() => {
    let h = 2166136261;
    for (let i = 0; i < seed.length; i++) { h ^= seed.charCodeAt(i); h = Math.imul(h, 16777619); }
    const out = [];
    for (let i = 0; i < 121; i++) { h = Math.imul(h ^ i, 16777619); out.push(((h >>> 7) & 3) === 0 ? 0 : ((h >>> 3) & 1)); }
    const finder = (r, c) => (r < 3 && c < 3) || (r < 3 && c > 7) || (r > 7 && c < 3);
    return out.map((v, i) => { const r = Math.floor(i / 11), c = i % 11; return finder(r, c) ? ((r === 1 && c % 10 === 1) || r === 0 || r === 2 || c === 0 || c === 2 || c === 8 || c === 10 ? 1 : 0) : v; });
  }, [seed]);
  return <div className="vst-qr" aria-hidden="true">{cells.map((v, i) => <i key={i} className={v ? "on" : ""} />)}</div>;
}

function Etiqueta({ item, config }) {
  const code = ean13(String(item.product_id || 0) + String(item.sku || "").replace(/\D/g, "").slice(0, 4));
  return (
    <div className="vst-tag">
      <div className="vst-tag-top">
        <div className="vst-tag-nome">
          <b>{item.nome || "Produto sem nome"}</b>
          <small>{[item.cor, item.colecao].filter(Boolean).join(" · ") || "—"}</small>
        </div>
        <span className="vst-tag-tam">{item.tamanho || "—"}</span>
      </div>
      <div className="vst-tag-mid">
        <div className="vst-tag-preco"><small>preço</small><b>{BRL(item.preco)}</b></div>
        {config.qr_enabled && <QrPrevia seed={item.sku || String(item.product_id)} />}
      </div>
      <div className="vst-tag-bot">
        <Barras code={code} />
        <span className="mono">{code}</span>
      </div>
    </div>
  );
}

function VestuarioPage({ view = "etiquetas", config = CONFIG, estado = "dados", papel = "operador", dense = false, toque = "mouse", previa = true, hardBlock = false }) {
  const { PageHeader, StatusBadge, Tooltip, Skeleton } = DS();
  const { Nota, Vazio } = A();
  const [itens, setItens] = useState(estado === "vazio" ? [] : INICIAIS);
  const [copias, setCopias] = useState(1);
  const [baixando, setBaixando] = useState(null);
  const [aviso, setAviso] = useState(null);

  React.useEffect(() => { setItens(estado === "vazio" ? [] : INICIAIS); }, [estado]);
  const semPerm = papel === "sem-acesso";
  const cls = "os-page vst-page" + (dense ? " dense" : "") + (toque === "tablet" ? " toque" : "");
  const patch = (id, p) => setItens((s) => s.map((i) => i.id === id ? { ...i, ...p } : i));
  const total = itens.length * Math.max(1, Math.min(100, copias));
  const semNome = itens.filter((i) => !i.nome.trim()).length;

  const baixar = (fmt) => {
    setBaixando(fmt);
    setTimeout(() => {
      setBaixando(null);
      setAviso(`${total} etiquetas geradas · etiquetas-2026-08-24.${fmt} baixado (POST /vestuario/etiquetas/lote/${fmt})`);
      setTimeout(() => setAviso(null), 5000);
    }, 1000);
  };

  const acoes = (
    <div className="vst-h-acts">
      <Btn disabled={!!baixando} onClick={() => baixar("zpl")}><IcPrint /> {baixando === "zpl" ? "Gerando…" : "Baixar ZPL"}</Btn>
      <Btn variant="primary" disabled={!!baixando} onClick={() => baixar("pdf")}><IcDown /> {baixando === "pdf" ? "Gerando…" : "Baixar PDF"}</Btn>
    </div>
  );
  const sub = `ZPL Argox/Zebra + PDF · ${itens.length} itens × ${Math.max(1, copias)} cópias = ${total} etiquetas`;

  // ── estados de exceção (O2) e a divergência de permissão do charter (O1) ──
  if (semPerm && hardBlock) {
    return (
      <div className={cls} data-screen-label="Comercial · Vestuário · etiquetas">
        {PageHeader && <PageHeader title="Etiquetas TAG vestuário" subtitle="acesso negado" />}
        {Vazio && <Vazio variant="no-perm" title="Sua função não gera etiquetas."
          description="Falta vestuario.etiqueta.create. Com o hard-block ligado (SDD §9 D-1), o endpoint devolve 403 e a tela nem monta o lote — etiqueta térmica gerada por engano é papel jogado fora." />}
      </div>
    );
  }
  if (estado === "carregando") {
    return (
      <div className={cls} data-screen-label="Comercial · Vestuário · etiquetas">
        {PageHeader && <PageHeader title="Etiquetas TAG vestuário" subtitle="carregando a configuração da impressora" />}
        <div className="vst-lote">{Skeleton ? <Skeleton variant="row" count={4} /> : <p>Carregando…</p>}</div>
      </div>
    );
  }
  if (estado === "erro") {
    return (
      <div className={cls} data-screen-label="Comercial · Vestuário · etiquetas">
        {PageHeader && <PageHeader title="Etiquetas TAG vestuário" subtitle="falha ao gerar" />}
        {Vazio && <Vazio variant="error" title="A impressão não foi gerada."
          description="O servidor recusou o lote (POST /vestuario/etiquetas/lote/zpl). Nenhuma etiqueta saiu e nada mudou no produto — confira os itens e tente de novo."
          action={<Btn variant="primary" onClick={() => window.location.reload()}>Recarregar</Btn>} />}
      </div>
    );
  }

  return (
    <div className={cls} data-screen-label="Comercial · Vestuário · etiquetas">
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Etiquetas TAG vestuário" subtitle={sub} actions={acoes} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Etiquetas TAG vestuário</h1><p>{sub}</p></div><div className="os-page-h-r">{acoes}</div></header>}
      </div>

      <section className="vst-cfg" data-contract="config">
        <span className="vst-cfg-l">Configuração atual <code>vestuario_settings</code></span>
        <div className="vst-cfg-chips">
          {StatusBadge ? <>
            <StatusBadge tone="neutral" label={`${config.width_dots}×${config.height_dots} dots`} />
            <StatusBadge tone="neutral" label={`${config.dpi} dpi`} />
            <StatusBadge tone="neutral" label={`margem ${config.margin_dots} dots`} />
            <StatusBadge tone={config.qr_enabled ? "success" : "neutral"} label={config.qr_enabled ? "QR ativo" : "QR desligado"} />
          </> : <span className="mono">{config.width_dots}×{config.height_dots} dots · {config.dpi} dpi</span>}
        </div>
        <span className="vst-cfg-fine">≈ {(config.width_dots / config.dpi * 25.4).toFixed(0)}×{(config.height_dots / config.dpi * 25.4).toFixed(0)} mm — o modelo e o driver da impressora se configuram fora desta tela.</span>
      </section>

      {semPerm && !hardBlock && Nota &&
        <div className="vst-nota">
          <Nota tone="danger" title="Sem permissão — e o servidor deixa passar">
            Sua função não tem <code>vestuario.etiqueta.create</code>, mas o <code>EtiquetaTagController::authorizeAccess()</code> só grava
            <code>Log::warning('vestuario.etiqueta.permission_check_missing')</code> e segue. O hard-block é decisão de [W] (SDD §9 D-1) — ligue no Tweak pra ver a tela bloqueada.
          </Nota>
        </div>}

      {semNome > 0 && Nota &&
        <div className="vst-nota">
          <Nota tone="warn" title={semNome === 1 ? "1 item sem nome" : `${semNome} itens sem nome`}>
            A etiqueta sai impressa com o campo vazio — e etiqueta térmica errada é papel perdido. Preencha o nome antes de mandar pra impressora.
          </Nota>
        </div>}

      <section className="vst-lote" data-contract="lote">
        <header className="vst-lote-h">
          <h2>Lote ({itens.length} {itens.length === 1 ? "item" : "itens"})</h2>
          <div className="vst-lote-a">
            <label className="vst-copias"><small>cópias ×</small>
              <input className="mono" type="number" min="1" max="100" value={copias} onChange={(e) => setCopias(Math.max(1, Math.min(100, Number(e.target.value) || 1)))} />
            </label>
            <Btn onClick={() => setItens((s) => [...s, novoItem()])}><IcPlus /> Item</Btn>
          </div>
        </header>

        {itens.length === 0
          ? (Vazio ? <Vazio variant="first" title="Nenhum item no lote." description="Adicione o primeiro item — produto, tamanho e cor — pra gerar as etiquetas." /> : <p>Nenhum item.</p>)
          : <>
            <div className="vst-grid vst-grid-head" aria-hidden="true">
              <span>Produto ID</span><span>Nome</span><span>Tam.</span><span>Cor</span><span>Coleção</span><span>Preço</span><span>SKU</span><span>EAN-13</span><span/>
            </div>
            {itens.map((i) => (
              <div className="vst-grid vst-row" key={i.id}>
                <div className="vst-c" data-l="Produto ID"><input className="mono" type="number" min="0" value={i.product_id} onChange={(e) => patch(i.id, { product_id: Number(e.target.value) || 0 })} /></div>
                <div className="vst-c" data-l="Nome"><input value={i.nome} placeholder="Ex: Camiseta algodão penteado" onChange={(e) => patch(i.id, { nome: e.target.value })} /></div>
                <div className="vst-c" data-l="Tamanho">
                  {/* select nativo: célula de grid densa do lote — igual ao vivo */}
                  <select value={i.tamanho} aria-label="Tamanho" onChange={(e) => patch(i.id, { tamanho: e.target.value })}>{TAMANHOS.map((t) => <option key={t} value={t}>{t}</option>)}</select>
                </div>
                <div className="vst-c" data-l="Cor"><input value={i.cor} placeholder="Preto" onChange={(e) => patch(i.id, { cor: e.target.value })} /></div>
                <div className="vst-c" data-l="Coleção"><input value={i.colecao} placeholder="Verão 26" onChange={(e) => patch(i.id, { colecao: e.target.value })} /></div>
                <div className="vst-c" data-l="Preço"><input className="mono" type="number" min="0" step="0.01" value={i.preco} onChange={(e) => patch(i.id, { preco: Number(e.target.value) || 0 })} /></div>
                <div className="vst-c" data-l="SKU"><input className="mono" value={i.sku} placeholder="CAM-ALG-PRT-M" onChange={(e) => patch(i.id, { sku: e.target.value })} /></div>
                <div className="vst-c vst-ean mono" data-l="EAN-13">{ean13(String(i.product_id) + String(i.sku).replace(/\D/g, "").slice(0, 4))}</div>
                <div className="vst-c vst-del">
                  <Btn size="sm" icon disabled={itens.length === 1} onClick={() => setItens((s) => s.filter((x) => x.id !== i.id))} aria-label="Remover item"><IcTrash /></Btn>
                </div>
              </div>
            ))}
          </>}
      </section>

      {previa && <section className="vst-previa" data-contract="previa">
        <header className="vst-previa-h">
          <h2>Prévia da etiqueta</h2>
          {Tooltip
            ? <Tooltip content="O charter registra a divergência: hoje o vivo baixa .zpl/.pdf direto, sem prévia. Construir a prévia é decisão de [W]."><span className="vst-tag-prop">proposta F1 · pendente [W]</span></Tooltip>
            : <span className="vst-tag-prop">proposta F1 · pendente [W]</span>}
        </header>
        <div className="vst-previa-g">
          {itens.slice(0, 3).map((i) => <Etiqueta key={i.id} item={i} config={config} />)}
        </div>
        <p className="vst-fine">A prévia é desenho da tela. O que sai na térmica é o ZPL gerado no servidor — mesma origem do EAN-13 e do QR.</p>
      </section>}

      {!previa && Nota &&
        <div className="vst-nota">
          <Nota tone="info" title="Sem prévia, como no vivo hoje">
            Os botões baixam <code>.zpl</code>/<code>.pdf</code> direto — é o comportamento atual do repo. O charter promete “preview antes de imprimir”: podar a promessa ou construir a prévia é decisão de [W] (SDD §9 D-2).
          </Nota>
        </div>}

      {aviso && <div className="vst-toast"><b>Pronto.</b> {aviso}</div>}
    </div>
  );
}

window.VestuarioPage = VestuarioPage;
})();
