// catalogo-qr-page.jsx — Catálogo QR (Modules/ProductCatalogue · /product-catalogue/catalogue-qr).
// Espelho do blade vivo catalogue/generate_qr.blade.php: escolher local comercial + cor do QR,
// título/subtítulo, mostrar ou não o logo do negócio, gerar o QR e baixar a imagem. O link gerado
// é /catalogue/{business_id}/{location_id} — o catálogo público que o cliente abre no celular.
// No vivo o item é GHOST do hub Vendas (ADR 0180): o DataController do módulo é NO-OP e a entry
// nasce nos ghosts de __('sale.sale') no core. Aqui a rota é catalogo-qr.
// Expõe window.CatalogoQrPage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const A = () => window.AcessosDS || {};

const LOCAIS = [
  { id: 1, nome: "Loja Centro — balcão" },
  { id: 2, nome: "Loja Norte Shopping" },
  { id: 3, nome: "Depósito / retirada" },
];
const CORES = ["#111111", "oklch(0.55 0.15 295)", "#235EA9", "#EB3088"];
const BIZ = 4;
const INSTRUCOES = [
  "Escolha o local comercial e a cor do QR code",
  "Defina título, subtítulo e se o logo do negócio aparece",
  "Clique em gerar QR code",
];

const IcQr = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM19 19h2v2h-2z"/></svg>;
const IcDown = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3v13M7 12l5 5 5-5M4 21h16"/></svg>;
const IcLink = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/></svg>;

function Btn({ children, ...p }) {
  const { Button } = DS();
  if (!Button) return <button className={"os-btn " + (p.variant === "primary" ? "primary" : "ghost")} disabled={p.disabled} onClick={p.onClick}>{children}</button>;
  return <Button {...p}>{children}</Button>;
}
function Campo({ label, help, ...p }) {
  const { Input } = DS();
  if (Input) return <Input label={label} help={help} {...p} />;
  return <label className="cqr-fld"><small>{label}</small><input {...p} />{help && <em>{help}</em>}</label>;
}

// Grade determinística do QR (o real sai do easy.qrcode.min.js do módulo)
function QrArt({ text, cor, size = 232 }) {
  const N = 25;
  const cells = useMemo(() => {
    let h = 2166136261;
    for (let i = 0; i < text.length; i++) { h ^= text.charCodeAt(i); h = Math.imul(h, 16777619); }
    const out = new Array(N * N);
    for (let i = 0; i < N * N; i++) { h = Math.imul(h ^ i, 16777619); out[i] = (h >>> 9) & 1; }
    const olho = (r, c) => {
      const inBox = (r0, c0) => r >= r0 && r < r0 + 7 && c >= c0 && c < c0 + 7;
      if (!(inBox(0, 0) || inBox(0, N - 7) || inBox(N - 7, 0))) return null;
      const r0 = r < 7 ? 0 : N - 7, c0 = c < 7 ? (r < 7 && c >= N - 7 ? N - 7 : 0) : N - 7;
      const dr = r - r0, dc = c - c0;
      const borda = dr === 0 || dr === 6 || dc === 0 || dc === 6;
      const centro = dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4;
      return borda || centro ? 1 : 0;
    };
    return out.map((v, i) => { const o = olho(Math.floor(i / N), i % N); return o === null ? v : o; });
  }, [text]);
  const p = size / N;
  return (
    <svg className="cqr-art" width={size} height={size} viewBox={`0 0 ${N} ${N}`} role="img" aria-label="Prévia do QR code do catálogo">
      <rect width={N} height={N} fill="#fff" />
      {cells.map((v, i) => v ? <rect key={i} x={i % N} y={Math.floor(i / N)} width="1" height="1" fill={cor} /> : null)}
    </svg>
  );
}

function CatalogoQrPage({ estado = "dados", papel = "gerente", dense = false, toque = "mouse", logoCadastrado = true }) {
  const { PageHeader, Select, Switch, StatusBadge, Skeleton } = DS();
  const { Nota, Vazio } = A();
  const [local, setLocal] = useState("");
  const [cor, setCor] = useState(CORES[1]);
  const [titulo, setTitulo] = useState("ROTA LIVRE Comunicação Visual");
  const [subtitulo, setSubtitulo] = useState("Catálogo de produtos");
  const [logo, setLogo] = useState(logoCadastrado);
  const [gerado, setGerado] = useState(null);
  const [copiado, setCopiado] = useState(false);

  const link = local ? `/catalogue/${BIZ}/${local}` : "";
  const gerar = () => setGerado({ link, cor, titulo, subtitulo, logo: logo && logoCadastrado, local: LOCAIS.find((l) => String(l.id) === String(local)) });

  const sub = "O QR que o cliente aponta no balcão e abre o seu catálogo no celular — um por local comercial.";
  const locais = estado === "vazio" ? [] : LOCAIS;
  const cls = "os-page cqr-page" + (dense ? " dense" : "") + (toque === "tablet" ? " toque" : "");

  if (papel === "sem-acesso") {
    return (
      <div className={cls} data-screen-label="Comercial · Catálogo QR">
        {PageHeader && <PageHeader title="Catálogo QR" subtitle="acesso negado" />}
        {Vazio && <Vazio variant="no-perm" title="Sua função não publica o catálogo."
          description="O QR expõe produtos e preços de um local sem login. Publicar é decisão de quem responde pelo preço — não do balcão." />}
      </div>
    );
  }
  if (estado === "carregando") {
    return (
      <div className={cls} data-screen-label="Comercial · Catálogo QR">
        {PageHeader && <PageHeader title="Catálogo QR" subtitle={sub} />}
        <div className="cqr-form">{Skeleton ? <Skeleton variant="row" count={4} /> : <p>Carregando…</p>}</div>
      </div>
    );
  }
  if (estado === "erro") {
    return (
      <div className={cls} data-screen-label="Comercial · Catálogo QR">
        {PageHeader && <PageHeader title="Catálogo QR" subtitle={sub} />}
        {Vazio && <Vazio variant="error" title="Não foi possível montar o QR."
          description="A lista de locais comerciais não respondeu. Nenhum catálogo foi publicado — recarregue a tela." />}
      </div>
    );
  }
  if (locais.length === 0) {
    return (
      <div className={cls} data-screen-label="Comercial · Catálogo QR">
        {PageHeader && <PageHeader title="Catálogo QR" subtitle={sub} />}
        {Vazio && <Vazio variant="first" title="Nenhum local comercial cadastrado."
          description="O catálogo é por local — é dele que saem preço e estoque. Cadastre o primeiro local em Configurações › Locais comerciais e volte aqui."
          action={<Btn variant="primary" onClick={() => window.__selectRoute?.("cfg-locais")}>Abrir locais comerciais</Btn>} />}
      </div>
    );
  }

  return (
    <div className={cls} data-screen-label="Comercial · Catálogo QR">
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Catálogo QR" subtitle={sub} actions={<Btn onClick={() => window.__selectRoute?.("produtos")}>Produtos</Btn>} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Catálogo QR</h1><p>{sub}</p></div></header>}
      </div>

      <div className="cqr-cols">
        <section className="cqr-form" data-contract="formulario">
          <h2>Como o QR vai sair</h2>
          <div className="cqr-fields">
            <div className="cqr-fld-w">
              <small className="cqr-lbl">Local comercial</small>
              {Select
                ? <Select value={local} onChange={(e) => { setLocal(e.target.value); setGerado(null); }}
                    options={[{ value: "", label: "Selecione…" }, ...locais.map((l) => ({ value: l.id, label: l.nome }))]} />
                : <select value={local} onChange={(e) => { setLocal(e.target.value); setGerado(null); }}>
                    <option value="">Selecione…</option>
                    {locais.map((l) => <option key={l.id} value={l.id}>{l.nome}</option>)}
                  </select>}
              <em className="cqr-help">Cada local tem catálogo próprio — o preço e o estoque saem do local escolhido.</em>
            </div>

            <div className="cqr-fld-w">
              <small className="cqr-lbl">Cor do QR code</small>
              <div className="cqr-swatches">
                {CORES.map((c) => (
                  <button key={c} className={"cqr-sw" + (cor === c ? " active" : "")} style={{ background: c }}
                    aria-label={"Cor " + c} onClick={() => { setCor(c); setGerado(null); }} />
                ))}
              </div>
              <em className="cqr-help">Contraste é leitura: cor clara em fundo branco o celular não lê.</em>
            </div>

            <Campo label="Título" value={titulo} onChange={(e) => { setTitulo(e.target.value); setGerado(null); }} />
            <Campo label="Subtítulo" value={subtitulo} onChange={(e) => { setSubtitulo(e.target.value); setGerado(null); }} />

            <div className="cqr-fld-w">
              {Switch
                ? <Switch checked={logo && logoCadastrado} disabled={!logoCadastrado} onChange={(v) => { setLogo(typeof v === "boolean" ? v : !logo); setGerado(null); }}
                    label="Mostrar o logo do negócio no QR code"
                    sublabel={logoCadastrado ? "uploads/business_logos — o logo entra no meio do QR" : "sem logo cadastrado no negócio: o QR sai limpo"} />
                : <label className="cqr-chk"><input type="checkbox" checked={logo} onChange={() => { setLogo(!logo); setGerado(null); }} /> Mostrar o logo do negócio no QR code</label>}
            </div>
          </div>

          <div className="cqr-acoes">
            <Btn variant="primary" disabled={!local} onClick={gerar}><IcQr /> Gerar QR code</Btn>
            {!local && <span className="cqr-req">Escolha o local comercial primeiro.</span>}
          </div>

          <div className="cqr-instr" data-contract="instrucoes">
            <b>Instruções</b>
            <ol>{INSTRUCOES.map((t, i) => <li key={i}><span className="mono">{i + 1}</span>{t}</li>)}</ol>
          </div>
        </section>

        <section className="cqr-out" data-contract="saida">
          {!gerado
            ? (Vazio
              ? <Vazio variant="first" title="Nenhum QR gerado ainda."
                  description="Escolha o local, ajuste título e cor, e clique em gerar — o QR aparece aqui pronto pra baixar e colar no balcão." />
              : <p>Nenhum QR gerado ainda.</p>)
            : <>
              <div className="cqr-card">
                <div className="cqr-card-h">
                  {gerado.logo && <span className="cqr-logo" aria-hidden="true">OI</span>}
                  <b>{gerado.titulo || "\u00a0"}</b>
                  <small>{gerado.subtitulo}</small>
                </div>
                <QrArt text={gerado.link} cor={gerado.cor} />
                <code className="cqr-link"><IcLink /> {gerado.link}</code>
              </div>
              <div className="cqr-out-acts">
                <Btn variant="primary" onClick={() => {}}><IcDown /> Baixar imagem</Btn>
                <Btn onClick={() => { navigator.clipboard?.writeText(gerado.link); setCopiado(true); setTimeout(() => setCopiado(false), 1600); }}>{copiado ? "Link copiado" : "Copiar link"}</Btn>
              </div>
              {StatusBadge && <div className="cqr-meta"><StatusBadge tone="neutral" label={gerado.local.nome} /><StatusBadge tone="success" label="256 × 256 px · PNG" /></div>}
              {Nota &&
                <Nota tone="info" title="O catálogo é público">
                  Quem tiver o link vê os produtos e os preços daquele local, sem login. Trocar de local muda o link — o QR antigo continua valendo pro local antigo.
                </Nota>}
            </>}
        </section>
      </div>
    </div>
  );
}

window.CatalogoQrPage = CatalogoQrPage;
})();
