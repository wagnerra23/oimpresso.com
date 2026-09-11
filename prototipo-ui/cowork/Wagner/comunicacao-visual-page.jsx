// comunicacao-visual-page.jsx — Comunicação Visual (Modules/ComunicacaoVisual · /comunicacao-visual).
// Espelho do vivo resources/js/Pages/ComunicacaoVisual/Index.tsx: calculadora de orçamento por m²
// (US-COMVIS-001) + "Conferir no servidor" (POST /comunicacao-visual/api/calcular é a fonte de
// verdade). Fórmula canônica de Services/OrcamentoCalculator: area = larg × alt × qtd;
// subtotal = area × preço/m²; total = subtotal − desconto + extras.
// ONDA O5 (profundidade): salvar orçamento (POST /api/orcamentos) + PDF no WhatsApp e o PCP
// miniatura — as duas APIs já existem no vivo (US-COMVIS-002/003), a tela é que não mostrava.
// ONDA O2/O6: estado (dados/vazio/carregando/erro) · papel · densidade · alvo de toque por Tweak.
// Persona: Larissa, balcão 1280px — fecha orçamento em <2min sem abrir Excel.
// Expõe window.ComunicacaoVisualPage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const A = () => window.AcessosDS || {};

const BRL = (n) => "R$ " + (Number(n) || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const NUM = (n) => (Number(n) || 0).toLocaleString("pt-BR", { maximumFractionDigits: 3 });

// Catálogo de materiais do business (Entities/Material · preco_venda_m2)
const MATERIAIS = [
  { id: 1, nome: "Lona 440g brilho", categoria: "lona", preco: 26.9 },
  { id: 2, nome: "Lona blackout 680g", categoria: "lona", preco: 41.5 },
  { id: 3, nome: "Vinil adesivo branco", categoria: "vinil", preco: 38.5 },
  { id: 4, nome: "Vinil perfurado (microperfurado)", categoria: "vinil", preco: 62 },
  { id: 5, nome: "Adesivo transparente", categoria: "vinil", preco: 45 },
  { id: 6, nome: "Tela mesh", categoria: "lona", preco: 48 },
  { id: 7, nome: "ACM 3mm", categoria: "rígido", preco: 189 },
  { id: 8, nome: "PS 2mm", categoria: "rígido", preco: 96 },
];

// PCP (US-COMVIS-003) — OrdemProducao.etapa. As OS que já estão na fábrica.
const ETAPAS = [
  { id: "arte", label: "Arte" },
  { id: "impressao", label: "Impressão" },
  { id: "acabamento", label: "Acabamento" },
  { id: "instalacao", label: "Instalação" },
];
const OPS = [
  { id: "OP-2291", cliente: "Padaria Estrela", peca: "Fachada ACM 4,2×0,9 m", etapa: "arte", m2: 3.78, prazo: "hoje 17h", urgente: true },
  { id: "OP-2288", cliente: "Mercado União", peca: "Banner 3×1,2 m (2un)", etapa: "impressao", m2: 7.2, prazo: "amanhã" },
  { id: "OP-2285", cliente: "Auto Center Boa Vista", peca: "Adesivo perfurado vitrine", etapa: "impressao", m2: 5.4, prazo: "26/08" },
  { id: "OP-2280", cliente: "Clínica Vida", peca: "Placa PS 0,6×0,4 (6un)", etapa: "acabamento", m2: 1.44, prazo: "26/08" },
  { id: "OP-2274", cliente: "Posto BR Centro", peca: "Testeira lona 6×0,8 m", etapa: "instalacao", m2: 4.8, prazo: "27/08" },
];

let seq = 0;
const novoItem = (p) => ({ id: "i" + ++seq, material_id: null, descricao: "", largura: 1, altura: 1, qtd: 1, preco: 0, ...p });
const area = (i) => Math.max(0, i.largura) * Math.max(0, i.altura) * Math.max(0, i.qtd);
const sub = (i) => area(i) * Math.max(0, i.preco);

const IcPlus = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>;
const IcTrash = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>;
const IcServer = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/></svg>;
const IcSave = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M5 3h11l3 3v15H5z"/><path d="M8 3v6h8V3M8 21v-6h8v6"/></svg>;
const IcWa = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12a9 9 0 0 1-13.2 8L3 21l1.1-4.6A9 9 0 1 1 21 12z"/><path d="M8.5 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1c-1-.5-1.8-1.3-2.3-2.3l1-1-1-2z"/></svg>;
const IcRuler = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M3 15 15 3l6 6L9 21z"/><path d="M7 11l2 2M11 7l2 2"/></svg>;
const IcClock = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>;

function Btn({ children, ...p }) {
  const { Button } = DS();
  // O Button do DS não repassa aria-label: para ação só-ícone o nome vai num
  // <span class="sr-only"> dentro do children, senão o leitor de tela diz só "botão".
  const rotulo = p["aria-label"];
  const conteudo = rotulo ? <>{children}<span className="sr-only">{rotulo}</span></> : children;
  if (!Button) return <button className={"os-btn " + (p.variant === "primary" ? "primary" : "ghost")} aria-label={rotulo} disabled={p.disabled} onClick={p.onClick}>{conteudo}</button>;
  return <Button {...p} title={p.title || rotulo}>{conteudo}</Button>;
}
function Campo({ label, ...p }) {
  const { Input } = DS();
  if (Input) return <Input label={label} {...p} />;
  return <label className="cvi-fld"><small>{label}</small><input {...p} /></label>;
}

function Pcp({ ops }) {
  const { StatusBadge } = DS();
  return (
    <section className="cvi-pcp" data-contract="pcp">
      <header className="cvi-pcp-h">
        <h2>Na fábrica agora</h2>
        <small>OrdemProducao · etapa — o PCP completo vira tela própria (US-COMVIS-003)</small>
      </header>
      <div className="cvi-pcp-cols">
        {ETAPAS.map((et) => {
          const lista = ops.filter((o) => o.etapa === et.id);
          return (
            <div className="cvi-pcp-col" key={et.id}>
              <div className="cvi-pcp-col-h"><b>{et.label}</b><span className="mono">{lista.length}</span></div>
              {lista.length === 0
                ? <p className="cvi-pcp-vazio">vazio</p>
                : lista.map((o) => (
                  <article className={"cvi-op" + (o.urgente ? " urgente" : "")} key={o.id}>
                    <b className="mono">{o.id}</b>
                    <span className="cvi-op-cli">{o.cliente}</span>
                    <span className="cvi-op-peca">{o.peca}</span>
                    <footer><span className="mono">{NUM(o.m2)} m²</span>{StatusBadge
                      ? <StatusBadge tone={o.urgente ? "danger" : "neutral"} label={o.prazo} />
                      : <span>{o.prazo}</span>}</footer>
                  </article>
                ))}
            </div>
          );
        })}
      </div>
    </section>
  );
}

function ComunicacaoVisualPage({ estado = "dados", papel = "balcao", dense = false, toque = "mouse", pcp = true, salvar = true }) {
  const { PageHeader, StatusBadge, Skeleton } = DS();
  const { Nota, Vazio } = A();
  const [itens, setItens] = useState(() => [novoItem({ material_id: 1, descricao: "Banner fachada loja", largura: 3, altura: 1.2, preco: 26.9 })]);
  const [extras, setExtras] = useState(180);
  const [desconto, setDesconto] = useState(0);
  const [conferindo, setConferindo] = useState(false);
  const [conferido, setConferido] = useState(null);
  const [salvo, setSalvo] = useState(null);
  const [aviso, setAviso] = useState(null);

  const semCatalogo = estado === "vazio";
  const catalogo = semCatalogo ? [] : MATERIAIS;
  const zera = () => { setConferido(null); setSalvo(null); };
  const patch = (id, p) => { setItens((s) => s.map((i) => i.id === id ? { ...i, ...p } : i)); zera(); };
  const escolher = (id, mid) => { const m = catalogo.find((x) => x.id === mid); patch(id, { material_id: mid, preco: m ? m.preco : 0 }); };

  const subtotal = useMemo(() => itens.reduce((a, i) => a + sub(i), 0), [itens]);
  const total = Math.max(0, subtotal - Math.max(0, desconto) + Math.max(0, extras));
  const m2Total = useMemo(() => itens.reduce((a, i) => a + area(i), 0), [itens]);
  const valido = itens.some((i) => i.largura > 0 && i.altura > 0 && i.qtd >= 1 && i.preco > 0);
  const podeSalvar = papel !== "consulta";

  const fala = (t) => { setAviso(t); setTimeout(() => setAviso(null), 5000); };
  const conferir = () => { setConferindo(true); setConferido(null); setTimeout(() => { setConferindo(false); setConferido({ total: Math.round(total * 100) / 100 }); }, 900); };
  const salvarOrc = () => { const n = "ORC-" + (2400 + Math.floor(Math.random() * 90)); setSalvo({ n, total }); fala(`Orçamento ${n} salvo · ${BRL(total)} (POST /comunicacao-visual/api/orcamentos)`); };

  const acoes = (
    <div className="cvi-h-acts">
      <Btn onClick={() => window.__selectRoute?.("orcamentos")}>Orçamentos</Btn>
      <Btn variant="primary" onClick={() => { setItens((s) => [...s, novoItem()]); zera(); }}><IcPlus /> Adicionar peça</Btn>
    </div>
  );
  const subt = `Orçamento por m² — calcule na hora, sem abrir o Excel.`;
  const cls = "os-page cvi-page" + (dense ? " dense" : "") + (toque === "tablet" ? " toque" : "");

  // ── estados de exceção (O2) ──
  if (papel === "sem-acesso") {
    return (
      <div className={cls} data-screen-label="Produção · Comunicação Visual">
        {PageHeader && <PageHeader title="Comunicação Visual" subtitle="acesso negado" />}
        {Vazio && <Vazio variant="no-perm" title="Sua função não abre a Comunicação Visual."
          description="A permissão comunicacaovisual.access sai do pacote do negócio + função do usuário. Peça a um administrador — o endpoint devolve 403 do mesmo jeito." />}
      </div>
    );
  }
  if (estado === "carregando") {
    return (
      <div className={cls} data-screen-label="Produção · Comunicação Visual">
        {PageHeader && <PageHeader title="Comunicação Visual" subtitle={subt} />}
        <div className="cvi-calc">{Skeleton ? <Skeleton variant="row" count={5} /> : <p>Carregando…</p>}</div>
      </div>
    );
  }
  if (estado === "erro") {
    return (
      <div className={cls} data-screen-label="Produção · Comunicação Visual">
        {PageHeader && <PageHeader title="Comunicação Visual" subtitle={subt} />}
        {Vazio && <Vazio variant="error" title="Não foi possível abrir a calculadora."
          description="O catálogo de materiais não respondeu (GET /comunicacao-visual/api/materiais). Nada foi calculado nem salvo — recarregue; se persistir, é o módulo desabilitado no pacote."
          action={<Btn variant="primary" onClick={() => window.location.reload()}>Recarregar</Btn>} />}
      </div>
    );
  }

  return (
    <div className={cls} data-screen-label="Produção · Comunicação Visual">
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Comunicação Visual" subtitle={subt} actions={acoes} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Comunicação Visual</h1><p>{subt}</p></div><div className="os-page-h-r">{acoes}</div></header>}
      </div>

      {semCatalogo && Nota &&
        <div className="cvi-nota">
          <Nota tone="info" title="Sem materiais cadastrados">
            Você ainda não tem materiais cadastrados. Pode digitar o preço por m² na mão em cada peça — quando cadastrar o catálogo, ele aparece aqui pra escolher.
          </Nota>
        </div>}

      <section className="cvi-calc" data-contract="calculadora">
        <header className="cvi-calc-h">
          <h2>Calculadora de m²</h2>
          <small>largura × altura × qtd × preço/m² — a mesma conta do servidor</small>
        </header>

        <div className="cvi-grid cvi-grid-head" aria-hidden="true">
          <span>Material</span><span>Descrição</span><span>Larg. (m)</span><span>Alt. (m)</span><span>Qtd</span><span>R$/m²</span><span className="r">m²</span><span className="r">Subtotal</span><span/>
        </div>

        {itens.map((i) => (
          <div className="cvi-grid cvi-row" key={i.id}>
            <div className="cvi-c" data-l="Material">
              {/* select nativo: linha de grid densa (calculadora m²), igual ao vivo — Select do DS é pra formulário, não pra célula */}
              <select value={i.material_id || ""} disabled={semCatalogo} aria-label="Material" onChange={(e) => escolher(i.id, e.target.value ? Number(e.target.value) : null)}>
                <option value="">{semCatalogo ? "Sem catálogo" : "Preço avulso"}</option>
                {catalogo.map((m) => <option key={m.id} value={m.id}>{m.nome}</option>)}
              </select>
            </div>
            <div className="cvi-c" data-l="Descrição"><input value={i.descricao} placeholder="Ex: Banner fachada loja" onChange={(e) => patch(i.id, { descricao: e.target.value })} /></div>
            <div className="cvi-c" data-l="Largura (m)"><input className="mono" type="number" min="0" step="0.01" value={i.largura} onChange={(e) => patch(i.id, { largura: Number(e.target.value) || 0 })} /></div>
            <div className="cvi-c" data-l="Altura (m)"><input className="mono" type="number" min="0" step="0.01" value={i.altura} onChange={(e) => patch(i.id, { altura: Number(e.target.value) || 0 })} /></div>
            <div className="cvi-c" data-l="Qtd"><input className="mono" type="number" min="1" step="1" value={i.qtd} onChange={(e) => patch(i.id, { qtd: Math.max(1, Math.floor(Number(e.target.value) || 1)) })} /></div>
            <div className="cvi-c" data-l="R$/m²"><input className="mono" type="number" min="0" step="0.01" value={i.preco} onChange={(e) => patch(i.id, { preco: Number(e.target.value) || 0 })} /></div>
            <div className="cvi-c r mono cvi-area" data-l="Área">{NUM(area(i))} m²</div>
            <div className="cvi-c r mono cvi-sub" data-l="Subtotal">{BRL(sub(i))}</div>
            <div className="cvi-c cvi-del">
              <Btn size="sm" icon disabled={itens.length === 1} onClick={() => { setItens((s) => s.filter((x) => x.id !== i.id)); zera(); }} aria-label="Remover peça"><IcTrash /></Btn>
            </div>
          </div>
        ))}

        <div className="cvi-ajustes">
          <Campo label="Acabamento / instalação / entrega (R$)" type="number" min="0" step="0.01" value={extras} onChange={(e) => { setExtras(Number(e.target.value) || 0); zera(); }} />
          <Campo label="Desconto (R$)" type="number" min="0" step="0.01" value={desconto} onChange={(e) => { setDesconto(Number(e.target.value) || 0); zera(); }} />
        </div>

        <footer className="cvi-total" data-contract="total">
          <div className="cvi-total-l">
            <div className="cvi-total-sub"><span>{itens.length} {itens.length === 1 ? "peça" : "peças"} · <b className="mono">{NUM(m2Total)}</b> m² no orçamento</span></div>
            <div className="cvi-total-sub"><span>Subtotal</span><b className="mono">{BRL(subtotal)}</b></div>
            <div className="cvi-total-big"><span>Total estimado</span><b className="mono">{BRL(total)}</b></div>
            {conferido && (StatusBadge
              ? <div className="cvi-conf"><StatusBadge tone="success" label={"Conferido no servidor: " + BRL(conferido.total)} /></div>
              : <small className="cvi-conf-alt">Conferido no servidor: {BRL(conferido.total)}</small>)}
          </div>
          <div className="cvi-total-acts">
            <Btn disabled={!valido || conferindo} onClick={conferir}>{conferindo ? "Conferindo…" : <><IcServer /> Conferir no servidor</>}</Btn>
            {salvar && (salvo
              ? <Btn variant="primary" onClick={() => fala(`PDF do ${salvo.n} enviado no WhatsApp do cliente.`)}><IcWa /> Enviar PDF no WhatsApp</Btn>
              : <Btn variant="primary" disabled={!valido || !conferido || !podeSalvar} onClick={salvarOrc}><IcSave /> Salvar orçamento</Btn>)}
          </div>
        </footer>

        <p className="cvi-fine">
          O cálculo na tela é só uma prévia — o valor que vale é o conferido no servidor, com as regras da sua loja.
          {salvar && !conferido && " Salvar só libera depois de conferir: orçamento salvo com número errado vira retrabalho no balcão."}
          {salvar && salvo && ` Salvo como ${salvo.n} — o PDF sai do orçamento gravado, não desta tela.`}
        </p>
      </section>

      {pcp && <Pcp ops={OPS} />}

      <section className="cvi-proximas" data-contract="em-breve">
        <h2>Em breve nesta tela</h2>
        <div className="cvi-proximas-g">
          <div className="cvi-prox">
            <div className="cvi-prox-h"><span className="cvi-prox-ic" aria-hidden="true"><IcRuler /></span><h3>Materiais</h3><span className="cvi-prox-tag">em breve</span></div>
            <p>Cadastrar lona, vinil e ACM com o preço por m² sem sair daqui (US-COMVIS-002).</p>
          </div>
          <div className="cvi-prox">
            <div className="cvi-prox-h"><span className="cvi-prox-ic" aria-hidden="true"><IcClock /></span><h3>Apontamentos</h3><span className="cvi-prox-tag">em breve</span></div>
            <p>Registrar início e fim de cada impressão pra saber quanto cada peça custou de verdade (US-COMVIS-004).</p>
          </div>
        </div>
      </section>

      {aviso && <div className="cvi-toast"><b>Pronto.</b> {aviso}</div>}
    </div>
  );
}

window.ComunicacaoVisualPage = ComunicacaoVisualPage;
})();
