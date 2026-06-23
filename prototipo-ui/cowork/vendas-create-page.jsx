// vendas-create-page.jsx — Vendas/Create · venda COMPLETA vertical-aware
// Verticais: COMUNICAÇÃO VISUAL (m²/acabamento) · OFICINA (veículo + MO + peças + aprovação)
// Canon ADR 0110 + domínio do vendas-create-completo.jsx. Valores em CENTAVOS.
const { useState: useStV, useMemo: useMemoV, useEffect: useEffV, useRef: useRefV } = React;
const VCIco = window.I || {};

const centsToBRL = (c) => (Math.round(c) / 100).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const centsToNum = (c) => (Math.round(c) / 100).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const parseCents = (s) => parseInt(String(s).replace(/\D/g, "") || "0", 10);
const realToCents = (r) => Math.round((Number(r) || 0) * 100);
const eanOf = (id, i) => ("789" + String(1000000000 + i * 13731)).slice(0, 13);

// ── Catálogo COMUNICAÇÃO VISUAL ──
const VC_SERVICOS = [
  { id: "sv-instal", name: "Instalação no local", calc: "servico", price: 240, unit: "diária", codServ: "7.05" },
  { id: "sv-projeto", name: "Projeto / arte final", calc: "servico", price: 80, unit: "hora", codServ: "17.06" },
];
const NCM_BY_CAT = { Banner: "3919.10", Adesivo: "3919.10", Sinalização: "7610.90", Impressão: "4911.10", Tecido: "5407.61", Veicular: "3919.10", Embalagem: "4819.40" };

// ── Catálogo OFICINA (mão de obra + peças) — espelha CAT_OS do completo ──
const OFC_MO = [
  { id: "mo-oleo", name: "Troca de óleo + filtro", price: 60, unit: "serviço", codServ: "14.01" },
  { id: "mo-rev20", name: "Revisão 20.000 km", price: 380, unit: "serviço", codServ: "14.01" },
  { id: "mo-freio", name: "Troca de pastilhas (mão de obra)", price: 120, unit: "serviço", codServ: "14.01" },
  { id: "mo-alinh", name: "Alinhamento + balanceamento", price: 90, unit: "serviço", codServ: "14.01" },
  { id: "mo-diag", name: "Diagnóstico com scanner", price: 90, unit: "serviço", codServ: "14.01" },
  { id: "mo-susp", name: "Suspensão dianteira (mão de obra)", price: 280, unit: "serviço", codServ: "14.01" },
];
const OFC_PECAS = [
  { id: "pc-oleo", name: "Óleo 5W30 sintético (L)", price: 42, ncm: "2710.19" },
  { id: "pc-filtro", name: "Filtro de óleo", price: 28, ncm: "8421.23" },
  { id: "pc-pastilha", name: "Pastilha de freio dianteira", price: 180, ncm: "8708.30" },
  { id: "pc-disco", name: "Disco de freio (par)", price: 320, ncm: "8708.30" },
  { id: "pc-amort", name: "Amortecedor dianteiro", price: 240, ncm: "8708.80" },
  { id: "pc-bateria", name: "Bateria 60Ah", price: 380, ncm: "8507.10" },
];
const MECANICOS = [{ id: "m-jose", n: "José" }, { id: "m-paulo", n: "Paulo" }, { id: "m-rafa", n: "Rafael" }];

function VendasCreatePage({ onDone }) {
  const { OS_CLIENTS, OS_PRODUCTS, OS_MATRIZ_CIDADE, cliEntregaAddr, fmtAddrLinha } = window.OS_DATA;
  const { VENDAS_PAYMENTS } = window.VENDAS_DATA;
  const Ic = (n, s = 13) => (VCIco[n] ? VCIco[n]({ size: s }) : null);

  const [vertical, setVertical] = useStV(() => { try { return localStorage.getItem("oimpresso.sells.vertical") || "comvis"; } catch (e) { return "comvis"; } });
  useEffV(() => { try { localStorage.setItem("oimpresso.sells.vertical", vertical); } catch (e) {} }, [vertical]);
  const isOfc = vertical === "oficina";

  // catálogo COMVIS (produtos + serviços)
  const catComvis = useMemoV(() => {
    const prods = OS_PRODUCTS.map((p, i) => ({ id: p.id, name: p.name, cat: p.cat, unit: p.unit, calc: p.unit === "m²" ? "m2" : "un", unitCents: realToCents(p.price), ean: eanOf(p.id, i), ncm: NCM_BY_CAT[p.cat] || "0000.00", cfop: "5101" }));
    const servs = VC_SERVICOS.map((s, i) => ({ id: s.id, name: s.name, cat: "Serviço", unit: s.unit, calc: "servico", unitCents: realToCents(s.price), ean: eanOf(s.id, 900 + i), codServ: s.codServ }));
    return [...prods, ...servs];
  }, [OS_PRODUCTS]);
  // catálogo OFICINA (MO + peças)
  const catOfc = useMemoV(() => [
    ...OFC_MO.map((s, i) => ({ id: s.id, name: s.name, cat: "Mão de obra", unit: s.unit, calc: "mo", unitCents: realToCents(s.price), ean: eanOf(s.id, 700 + i), codServ: s.codServ })),
    ...OFC_PECAS.map((p, i) => ({ id: p.id, name: p.name, cat: "Peça", unit: "un", calc: "peca", unitCents: realToCents(p.price), ean: eanOf(p.id, 800 + i), ncm: p.ncm, cfop: "5102" })),
  ], []);
  const catalog = isOfc ? catOfc : catComvis;

  // ── estado comum ──
  const [client, setClient] = useStV(null);
  const [clientQuery, setClientQuery] = useStV("");
  const [newForm, setNewForm] = useStV(null);
  const [seller, setSeller] = useStV("bruna");
  const [status, setStatus] = useStV("final");
  const [local, setLocal] = useStV("matriz");
  // veículo (oficina)
  const [veic, setVeic] = useStV({ placa: "", modelo: "", ano: "", km: "" });
  const [mecanico, setMecanico] = useStV("m-jose");

  const [items, setItems] = useStV([]);
  const [prodQuery, setProdQuery] = useStV("");
  const [prodHl, setProdHl] = useStV(0);

  const [freteModo, setFreteModo] = useStV("retirada");
  const [transportadora, setTransportadora] = useStV("");
  const [freteCents, setFreteCents] = useStV(0);
  // entrega: qual endereço do cliente usar (id) ou "__outro" (digitar)
  const [entregaAddrId, setEntregaAddrId] = useStV("");
  const [entregaForm, setEntregaForm] = useStV({ cep: "", logradouro: "", numero: "", complemento: "", bairro: "", cidade: "", uf: "SP" });

  const [payment, setPayment] = useStV("pix");
  const [installments, setInstallments] = useStV(1);
  const [discountCents, setDiscountCents] = useStV(0);
  const [fiscalManual, setFiscalManual] = useStV(null);

  const [active, setActive] = useStV("dados");
  const bodyRef = useRefV(null);
  const prodRef = useRefV(null);
  const secs = { dados: useRefV(null), produtos: useRefV(null), frete: useRefV(null), pagamento: useRefV(null), fiscal: useRefV(null) };

  // troca de vertical limpa itens (catálogo muda)
  useEffV(() => { setItems([]); setProdQuery(""); setFiscalManual(null); }, [vertical]);
  // troca de cliente reseta o endereço de entrega escolhido (volta ao padrão do novo cliente)
  useEffV(() => { setEntregaAddrId(""); }, [client]);

  // ── endereço de entrega (deriva do cliente; "__outro" = digitado à mão) ──
  const clientAddrs = client?.addresses || [];
  const defaultEntrega = client ? cliEntregaAddr(client) : null;
  const usandoOutro = entregaAddrId === "__outro" || (freteModo === "entrega" && clientAddrs.length === 0);
  const entregaAddr = usandoOutro ? entregaForm : (clientAddrs.find((a) => a.id === entregaAddrId) || defaultEntrega);
  const entregaCidade = (entregaAddr?.cidade || "").trim();
  const municipioOutro = freteModo === "entrega" && !!entregaCidade && entregaCidade.toLowerCase() !== (OS_MATRIZ_CIDADE || "").toLowerCase();

  // cliente
  const clientMatches = useMemoV(() => { const q = clientQuery.trim().toLowerCase(); if (q.length < 2) return []; return OS_CLIENTS.filter(c => c.name.toLowerCase().includes(q) || (c.cnpj || "").includes(q)).slice(0, 6); }, [clientQuery, OS_CLIENTS]);
  const saveNewClient = () => { if (!newForm?.name?.trim()) return; setClient({ name: newForm.name.trim(), cnpj: newForm.doc || "", contact: newForm.phone || "—", isNew: true }); setNewForm(null); setClientQuery(""); };

  // produtos / bip
  const digits = prodQuery.replace(/\D/g, "");
  const prodMatches = useMemoV(() => { const q = prodQuery.trim().toLowerCase(); if (!q) return []; return catalog.filter(p => p.name.toLowerCase().includes(q) || p.cat.toLowerCase().includes(q) || (digits.length >= 3 && p.ean.includes(digits))).slice(0, 8); }, [prodQuery, catalog, digits]);
  const exactEan = useMemoV(() => digits.length >= 8 ? catalog.find(p => p.ean === digits) : null, [digits, catalog]);
  useEffV(() => { setProdHl(0); }, [prodQuery]);

  const addItem = (p) => {
    if (!p) return;
    setItems(prev => [...prev, {
      key: Date.now() + Math.random(), id: p.id, name: p.name, cat: p.cat, unit: p.unit, calc: p.calc,
      largura: p.calc === "m2" ? 1 : null, altura: p.calc === "m2" ? 1 : null,
      qty: 1, unitCents: p.unitCents, ncm: p.ncm, cfop: p.cfop, codServ: p.codServ,
      generatesOs: !isOfc && (p.calc === "m2" || p.cat === "Sinalização"),
      aprovado: true,
    }]);
    setProdQuery(""); requestAnimationFrame(() => prodRef.current?.focus());
  };
  const onProdKey = (e) => {
    if (e.key === "Enter") { e.preventDefault(); if (exactEan) return addItem(exactEan); if (prodMatches.length) return addItem(prodMatches[prodHl] || prodMatches[0]); }
    else if (e.key === "ArrowDown") { e.preventDefault(); setProdHl(h => Math.min(prodMatches.length - 1, h + 1)); }
    else if (e.key === "ArrowUp") { e.preventDefault(); setProdHl(h => Math.max(0, h - 1)); }
  };
  const upd = (key, patch) => setItems(prev => prev.map(it => it.key === key ? { ...it, ...patch } : it));
  const rm = (key) => setItems(prev => prev.filter(it => it.key !== key));

  const lineCents = (it) => {
    const q = parseInt(it.qty) || 0;
    if (it.calc === "m2") return Math.round((Number(it.largura) || 0) * (Number(it.altura) || 0) * q * it.unitCents);
    return q * it.unitCents;
  };

  // totais (centavos)
  const aprItems = items.filter(it => it.aprovado);
  const produtosCents = useMemoV(() => items.reduce((s, it) => s + lineCents(it), 0), [items]);
  const aprovadoCents = useMemoV(() => aprItems.reduce((s, it) => s + lineCents(it), 0), [items]);
  const efFreteCents = freteModo === "entrega" ? freteCents : 0;
  const baseCents = isOfc ? aprovadoCents : produtosCents;
  const totalCents = Math.max(0, baseCents + efFreteCents - discountCents);
  const itemCount = items.reduce((s, it) => s + (parseInt(it.qty) || 0), 0);
  const pendentes = items.length - aprItems.length;

  // fiscal
  const hasProduto = items.some(it => it.calc === "m2" || it.calc === "un" || it.calc === "peca");
  const hasServico = items.some(it => it.calc === "servico" || it.calc === "mo");
  const hasCnpj = !!(client?.cnpj && client.cnpj.replace(/\D/g, "").length === 14);
  const autoDocs = useMemoV(() => { const a = new Set(); if (hasProduto) a.add(hasCnpj ? "nfe" : "nfce"); if (hasServico) a.add("nfse"); if (hasProduto && municipioOutro) a.add("mdfe"); return a; }, [hasProduto, hasServico, hasCnpj, municipioOutro]);
  const docs = fiscalManual || autoDocs;
  const FISCAL = [
    { id: "nfce", tag: "NFC-e · 65", name: "Cupom consumidor", desc: "Balcão · CPF opcional", avail: hasProduto, why: !hasProduto ? "exige produto/peça" : null },
    { id: "nfe", tag: "NF-e · 55", name: "NF eletrônica", desc: "B2B · DANFE + XML", avail: hasProduto && hasCnpj, why: !hasProduto ? "exige produto/peça" : !hasCnpj ? "cliente sem CNPJ" : null },
    { id: "nfse", tag: "NFS-e", name: "NF de serviço", desc: isOfc ? "mão de obra · ISS" : "ISS municipal", avail: hasServico, why: !hasServico ? "exige serviço/MO" : null },
    { id: "mdfe", tag: "MDF-e · 58", name: "Manifesto transp.", desc: "Carga interestadual", avail: hasProduto && municipioOutro, why: !municipioOutro ? "só p/ entrega fora do município" : null },
  ];
  const toggleDoc = (id) => { const base = new Set(fiscalManual || autoDocs); if (base.has(id)) base.delete(id); else base.add(id); setFiscalManual(base); };

  const isParcelado = payment === "cartao" || String(payment).startsWith("boleto") || payment === "faturado";
  const gerarCobranca = payment === "pix" || String(payment).startsWith("boleto");
  const canSave = items.length > 0;

  const jump = (id) => { const el = secs[id]?.current, c = bodyRef.current; if (el && c) c.scrollTo({ top: el.offsetTop - 8, behavior: "smooth" }); setActive(id); };
  useEffV(() => { const c = bodyRef.current; if (!c) return; const on = () => { const y = c.scrollTop + 60; let cur = "dados"; for (const id of ["dados", "produtos", "frete", "pagamento", "fiscal"]) { const el = secs[id].current; if (el && el.offsetTop <= y) cur = id; } setActive(cur); }; c.addEventListener("scroll", on, { passive: true }); return () => c.removeEventListener("scroll", on); }, []);
  useEffV(() => { requestAnimationFrame(() => prodRef.current?.focus()); }, []);

  const finish = (print) => {
    if (!canSave) return;
    const emit = [...docs].map(d => d.toUpperCase()).join(" + ") || "nenhum doc";
    // COSTURA ([W] "roda o fluxo" 2026-06-10): a venda PROPAGA — Produção e Caixa ouvem este evento.
    const vendaId = "V-" + (7840 + ((window.__vendaSeq = (window.__vendaSeq || 0) + 1)));
    const firstItem = items[0] ? (items[0].name + (items.length > 1 ? " +" + (items.length - 1) : "")) : "Venda balcão";
    const geraProducao = !isOfc && items.some(it => it.generatesOs || it.calc === "m2");
    window.dispatchEvent(new CustomEvent("oimpresso:venda-created", { detail: {
      vendaId, vertical, itemCount, totalCents, docs: [...docs],
      clientName: client?.name || "Consumidor Final",
      firstItem, geraProducao,
      channel: payment === "pix" ? "PIX" : String(payment).startsWith("boleto") ? "Boleto" : payment === "cartao" ? "Cartão" : "Dinheiro",
    }}));
    window.vdToast?.(
      "Venda " + vendaId + " salva · " + emit
      + (geraProducao ? " · OS na fila de produção" : (isOfc ? " · gera OS oficina" : ""))
      + " · R$ " + (totalCents / 100).toLocaleString("pt-BR", { minimumFractionDigits: 2 }) + " a receber no caixa"
      + (print ? " · imprimindo" : ""), "ok", 5200);
    onDone?.();
  };
  useEffV(() => { const onKey = (e) => { if (e.key === "F2") { e.preventDefault(); if (canSave) finish(false); } else if (e.key === "Escape") { if (prodQuery) setProdQuery(""); else if (newForm) setNewForm(null); else onDone?.(); } }; window.addEventListener("keydown", onKey); return () => window.removeEventListener("keydown", onKey); }, [canSave, prodQuery, newForm, items, totalCents, docs]);

  const PILLS = isOfc
    ? [{ id: "dados", label: "Veículo", ic: "doc" }, { id: "produtos", label: "Serviços e peças", ic: "archive", ct: items.length || null }, { id: "pagamento", label: "Pagamento", ic: "receipt" }, { id: "fiscal", label: "Fiscal", ic: "check", ct: docs.size || null }]
    : [{ id: "dados", label: "Dados", ic: "doc" }, { id: "produtos", label: "Produtos", ic: "archive", ct: items.length || null }, { id: "frete", label: "Frete", ic: "upload" }, { id: "pagamento", label: "Pagamento", ic: "receipt" }, { id: "fiscal", label: "Fiscal", ic: "check", ct: docs.size || null }];
  const SELLERS = [{ id: "bruna", n: "Bruna" }, { id: "larissa", n: "Larissa" }, { id: "wagner", n: "Wagner" }];

  // grupos de itens (oficina = MO / Peças)
  const moItems = items.filter(it => it.calc === "mo");
  const pecaItems = items.filter(it => it.calc === "peca");

  const renderItemRow = (it) => {
    const area = (Number(it.largura) || 0) * (Number(it.altura) || 0);
    return (
      <div className={"vc-item" + (isOfc && !it.aprovado ? " pend" : "")} key={it.key}>
        <div className="vc-item-info">
          <b>{it.name}</b>
          <div className="meta">{it.ncm && <span>NCM {it.ncm}</span>}{it.cfop && <span>CFOP {it.cfop}</span>}{it.codServ && <span>cód.serv {it.codServ}</span>}</div>
          <div className="vc-item-calc">
            {it.calc === "m2" && <React.Fragment>
              <div className="vc-mini"><label>Largura m</label><input type="number" step="0.01" value={it.largura} onChange={e => upd(it.key, { largura: e.target.value })}/></div>
              <div className="vc-mini"><label>Altura m</label><input type="number" step="0.01" value={it.altura} onChange={e => upd(it.key, { altura: e.target.value })}/></div>
              <div className="vc-mini"><label>m²</label><span className="calc-out">{area.toFixed(2)}</span></div>
            </React.Fragment>}
            <div className="vc-mini"><label>{it.calc === "mo" ? "Qtd" : it.calc === "servico" ? "Qtd " + it.unit : "Qtd"}</label><input type="number" min="1" value={it.qty} onChange={e => upd(it.key, { qty: e.target.value })}/></div>
            <div className="vc-mini wide"><label>{it.calc === "m2" ? "R$/m²" : it.calc === "mo" ? "R$ MO" : "R$ unit."}</label><input inputMode="numeric" value={centsToNum(it.unitCents)} onChange={e => upd(it.key, { unitCents: parseCents(e.target.value) })}/></div>
          </div>
        </div>
        <div className="vc-item-r">
          <span className="sub">{centsToBRL(lineCents(it))}</span>
          {isOfc
            ? <button className={"vc-apr" + (it.aprovado ? " on" : "")} onClick={() => upd(it.key, { aprovado: !it.aprovado })}>{it.aprovado ? "✓ aprovado" : "pendente"}</button>
            : <label className="vc-item-os"><input type="checkbox" checked={it.generatesOs} onChange={e => upd(it.key, { generatesOs: e.target.checked })}/> gera OS</label>}
          <button className="btn ghost icon sm" title="Remover" onClick={() => rm(it.key)}>{Ic("close", 13) || "✕"}</button>
        </div>
      </div>
    );
  };

  return (
    <div className="vc-form" data-screen-label="Vendas/Create">
      <div className="vc-form-head">
        <div className="vc-head-row">
          <button className="vc-back" onClick={() => onDone?.()} title="Voltar pra lista de vendas sem salvar">{Ic("chevR", 14)}<span>Vendas</span><kbd>esc</kbd></button>
          <h1>Adicionar venda</h1>
          <div className="vc-vert" role="group" aria-label="Tipo de venda">
            <button className={!isOfc ? "on" : ""} onClick={() => setVertical("comvis")}>Comunicação visual</button>
            <button className={isOfc ? "on" : ""} onClick={() => setVertical("oficina")}>Oficina</button>
          </div>
          <div className="vc-head-sum">
            <div className="m"><span className="k">Itens</span><span className="v">{itemCount}</span></div>
            <span className="sep"/>
            <div className="m"><span className="k">{isOfc ? "Aprovado" : "Total"}</span><span className="v grand">{centsToBRL(totalCents)}</span></div>
          </div>
        </div>
        <div className="vc-pills">
          {PILLS.map(p => <button key={p.id} className={"vc-pill" + (active === p.id ? " active" : "")} onClick={() => jump(p.id)}>{Ic(p.ic, 13)}{p.label}{p.ct ? <span className="ct">{p.ct}</span> : null}</button>)}
        </div>
      </div>

      <div className="vc-body" ref={bodyRef}>
        <div className="vc-inner">

          {/* ── DADOS / VEÍCULO ── */}
          <section className="vc-sec" ref={secs.dados}>
            <div className="vc-sec-h"><span className="vc-sec-ic">{Ic("doc", 13)}</span><h2>{isOfc ? "Veículo e cliente" : "Dados da venda"}</h2></div>

            {isOfc && (
              <div className="vc-grid c4" style={{ marginBottom: 14 }}>
                <div className="field"><label>Placa</label><input className="input vc-placa" value={veic.placa} onChange={e => setVeic({ ...veic, placa: e.target.value.toUpperCase() })} placeholder="ABC1D23" maxLength={8}/></div>
                <div className="field"><label>Modelo</label><input className="input" value={veic.modelo} onChange={e => setVeic({ ...veic, modelo: e.target.value })} placeholder="Ex: Honda Civic"/></div>
                <div className="field"><label>Ano</label><input className="input" value={veic.ano} onChange={e => setVeic({ ...veic, ano: e.target.value })} placeholder="2021"/></div>
                <div className="field"><label>KM atual</label><input className="input" inputMode="numeric" value={veic.km} onChange={e => setVeic({ ...veic, km: e.target.value.replace(/\D/g, "") })} placeholder="62140"/></div>
              </div>
            )}

            <div className={"vc-grid " + (isOfc ? "c3" : "c4")}>
              <div className="field">
                <label>{isOfc ? "Cliente (dono)" : "Cliente"}</label>
                {client ? (
                  <div className="vc-cli-chosen"><div><b>{client.name}{client.isNew && <span className="new-tag">novo</span>}</b><small>{client.cnpj || "sem CNPJ — NFC-e"} · {client.contact || "—"}</small></div><button className="btn ghost sm" onClick={() => { setClient(null); setClientQuery(""); }}>Trocar</button></div>
                ) : newForm ? (
                  <div className="vc-cli-new">
                    <div className="field"><label>Nome</label><input className="input" autoFocus value={newForm.name} onChange={e => setNewForm({ ...newForm, name: e.target.value })}/></div>
                    <div className="field"><label>Telefone</label><input className="input" value={newForm.phone} onChange={e => setNewForm({ ...newForm, phone: e.target.value })}/></div>
                    <div className="field"><label>CPF/CNPJ</label><input className="input" value={newForm.doc} onChange={e => setNewForm({ ...newForm, doc: e.target.value })}/></div>
                    <button className="btn primary sm" onClick={saveNewClient}>{Ic("check", 12)} Salvar</button>
                  </div>
                ) : (
                  <React.Fragment>
                    <div className="search">{Ic("search", 14)}<input value={clientQuery} onChange={e => setClientQuery(e.target.value)} placeholder="Consumidor Final · busque…"/></div>
                    {clientQuery.trim().length >= 2 && (
                      <div className="vc-cli-pop">
                        {clientMatches.map(c => <button key={c.id} className="vc-cli-opt" onClick={() => { setClient(c); setClientQuery(""); }}><b>{c.name}</b><small>{c.cnpj || "sem CNPJ"} · {c.contact || "—"}</small></button>)}
                        <button className="vc-cli-new-btn" onClick={() => setNewForm({ name: clientQuery.trim(), phone: "", doc: "" })}>{Ic("plus", 13)} Cadastrar “{clientQuery.trim()}”</button>
                      </div>
                    )}
                  </React.Fragment>
                )}
              </div>
              {isOfc
                ? <div className="field"><label>Mecânico responsável</label><select className="select" value={mecanico} onChange={e => setMecanico(e.target.value)}>{MECANICOS.map(m => <option key={m.id} value={m.id}>{m.n}</option>)}</select></div>
                : <div className="field"><label>Vendedor</label><select className="select" value={seller} onChange={e => setSeller(e.target.value)}>{SELLERS.map(s => <option key={s.id} value={s.id}>{s.n}</option>)}</select></div>}
              <div className="field"><label>Status</label><select className="select" value={status} onChange={e => setStatus(e.target.value)}><option value="final">Final</option><option value="orcamento">Orçamento</option><option value="rascunho">Rascunho</option></select></div>
              {!isOfc && <div className="field"><label>Local</label><select className="select" value={local} onChange={e => setLocal(e.target.value)}><option value="matriz">Matriz (BL0001)</option><option value="filial">Filial Centro (BL0002)</option></select></div>}
            </div>
          </section>

          {/* ── PRODUTOS / SERVIÇOS+PEÇAS ── */}
          <section className="vc-sec" ref={secs.produtos}>
            <div className="vc-sec-h"><span className="vc-sec-ic">{Ic("archive", 13)}</span><h2>{isOfc ? "Serviços e peças" : "Produtos"}</h2><span className="hint">{isOfc ? "Bipe ou busque · mão de obra e peças separadas" : "Bipe o código ou busque · banner/placa calculam m²"}</span></div>
            <div className="vc-prod-search">
              <div className="search">{Ic("search", 15)}<input ref={prodRef} value={prodQuery} onChange={e => setProdQuery(e.target.value)} onKeyDown={onProdKey} placeholder={isOfc ? "Busque serviço ou peça (óleo, freio, revisão…)" : "Bipe o código de barras ou busque (banner, ACM, instalação…)"}/></div>
              {prodQuery && (
                <div className="vc-prod-pop">
                  {prodMatches.map((p, i) => <button key={p.id} className={"vc-prod-row" + (i === prodHl ? " hl" : "")} onMouseEnter={() => setProdHl(i)} onClick={() => addItem(p)}><span className="nm"><b>{p.name}</b><small>{p.cat} · {p.ean}</small></span><span className="tag">{p.calc === "m2" ? "m²" : p.calc === "mo" ? "MO" : p.calc === "peca" ? "peça" : p.calc === "servico" ? "serviço" : "unidade"}</span><span className="px">{centsToBRL(p.unitCents)}/{p.unit}</span></button>)}
                  {prodMatches.length === 0 && <div className="vc-prod-row" style={{ cursor: "default", color: "var(--text-mute)" }}>Nenhum item para “{prodQuery}”.</div>}
                </div>
              )}
            </div>

            {items.length === 0 ? (
              <div className="empty-state" style={{ marginTop: 12 }}><div className="ico">{Ic("archive", 18)}</div><b>Nenhum item</b><small>{isOfc ? "Busque a mão de obra e as peças do serviço." : "Banner, lona e fachada calculam m² automático."}</small></div>
            ) : isOfc ? (
              <div className="vc-items">
                <div className="vc-grp-h">{Ic("cog", 12) || null} Mão de obra <span className="ct">{moItems.length}</span></div>
                {moItems.length ? moItems.map(renderItemRow) : <div className="vc-grp-empty">Sem serviço — busque a mão de obra acima.</div>}
                <div className="vc-grp-h" style={{ marginTop: 14 }}>{Ic("archive", 12) || null} Peças <span className="ct">{pecaItems.length}</span></div>
                {pecaItems.length ? pecaItems.map(renderItemRow) : <div className="vc-grp-empty">Sem peças (opcional).</div>}
              </div>
            ) : (
              <div className="vc-items">{items.map(renderItemRow)}</div>
            )}
          </section>

          {/* ── FRETE (só comvis) ── */}
          {!isOfc && (
            <section className="vc-sec" ref={secs.frete}>
              <div className="vc-sec-h"><span className="vc-sec-ic">{Ic("truck", 13)}</span><h2>Frete / entrega</h2></div>
              <div className="vc-radio-row" style={{ maxWidth: 420 }}>
                <button className={"vc-radio" + (freteModo === "retirada" ? " active" : "")} onClick={() => setFreteModo("retirada")}><b>Retirada na loja</b><small>sem frete</small></button>
                <button className={"vc-radio" + (freteModo === "entrega" ? " active" : "")} onClick={() => setFreteModo("entrega")}><b>Entrega</b><small>transportadora + frete</small></button>
              </div>
              {freteModo === "entrega" && (
                <div className="vc-entrega">
                  <div className="vc-entrega-h">
                    <span className="vc-entrega-t">Endereço de entrega</span>
                    {client ? <span className="vc-entrega-from">de {client.name}</span> : <span className="vc-entrega-from warn">selecione o cliente para puxar o endereço</span>}
                  </div>

                  {clientAddrs.length > 0 && (
                    <div className="vc-addr-pick">
                      {clientAddrs.map((a) => {
                        const sel = !usandoOutro && entregaAddr && entregaAddr.id === a.id;
                        return (
                          <button key={a.id} type="button" className={"vc-addr-opt" + (sel ? " active" : "")} onClick={() => setEntregaAddrId(a.id)}>
                            <span className="vc-addr-opt-h">{Ic("mapPin", 11)} {a.label}{a.entrega && <em>padrão</em>}</span>
                            <span className="vc-addr-opt-l">{fmtAddrLinha(a)}</span>
                            <span className="vc-addr-opt-c">{a.bairro} · {a.cidade}/{a.uf} · {a.cep}</span>
                          </button>
                        );
                      })}
                      <button type="button" className={"vc-addr-opt outro" + (usandoOutro ? " active" : "")} onClick={() => setEntregaAddrId("__outro")}>
                        {Ic("plus", 13)} Outro endereço
                      </button>
                    </div>
                  )}

                  {(usandoOutro || (freteModo === "entrega" && clientAddrs.length === 0)) && (
                    <div className="vc-addr-fields">
                      <div className="field cep"><label>CEP</label><input className="input" value={entregaForm.cep} onChange={e => setEntregaForm({ ...entregaForm, cep: e.target.value })} placeholder="00000-000"/></div>
                      <div className="field log"><label>Logradouro</label><input className="input" value={entregaForm.logradouro} onChange={e => setEntregaForm({ ...entregaForm, logradouro: e.target.value })} placeholder="Rua / Avenida"/></div>
                      <div className="field num"><label>Número</label><input className="input" value={entregaForm.numero} onChange={e => setEntregaForm({ ...entregaForm, numero: e.target.value })} placeholder="nº"/></div>
                      <div className="field comp"><label>Complemento</label><input className="input" value={entregaForm.complemento} onChange={e => setEntregaForm({ ...entregaForm, complemento: e.target.value })} placeholder="Sala, bloco…"/></div>
                      <div className="field bairro"><label>Bairro</label><input className="input" value={entregaForm.bairro} onChange={e => setEntregaForm({ ...entregaForm, bairro: e.target.value })} placeholder="Bairro"/></div>
                      <div className="field cidade"><label>Cidade</label><input className="input" value={entregaForm.cidade} onChange={e => setEntregaForm({ ...entregaForm, cidade: e.target.value })} placeholder="Cidade"/></div>
                      <div className="field uf"><label>UF</label><input className="input vc-uf" maxLength={2} value={entregaForm.uf} onChange={e => setEntregaForm({ ...entregaForm, uf: e.target.value.toUpperCase() })} placeholder="UF"/></div>
                    </div>
                  )}

                  {entregaCidade && (
                    <div className={"vc-entrega-dest" + (municipioOutro ? " outro" : "")}>
                      {Ic("truck", 13)}
                      <span>Entregar em <b>{entregaCidade}/{entregaAddr?.uf || ""}</b></span>
                      <span className="vc-entrega-mun">{municipioOutro ? "outro município → habilita MDF-e" : "mesmo município → MDF-e dispensado"}</span>
                    </div>
                  )}

                  <div className="vc-grid c2" style={{ marginTop: 14, maxWidth: 540 }}>
                    <div className="field"><label>Transportadora</label><input className="input" value={transportadora} onChange={e => setTransportadora(e.target.value)} placeholder="Nome / própria"/></div>
                    <div className="field"><label>Valor do frete</label><input className="input" inputMode="numeric" style={{ textAlign: "right", fontFamily: "var(--font-mono)" }} value={centsToNum(freteCents)} onChange={e => setFreteCents(parseCents(e.target.value))}/></div>
                  </div>
                </div>
              )}
            </section>
          )}

          {/* ── PAGAMENTO ── */}
          <section className="vc-sec" ref={secs.pagamento}>
            <div className="vc-sec-h"><span className="vc-sec-ic">{Ic("receipt", 13)}</span><h2>Pagamento</h2>{isOfc && pendentes > 0 && <span className="hint">cobra só o aprovado · {pendentes} item(ns) pendente(s)</span>}</div>
            <div className="vc-pay-grid">{VENDAS_PAYMENTS.map(p => <button key={p.id} className={"vc-pay-card" + (payment === p.id ? " active" : "")} onClick={() => setPayment(p.id)}><span className="lbl">{p.label}</span><span className="clr">{p.clearing}</span></button>)}</div>
            {isParcelado && <div className="field" style={{ marginTop: 12, maxWidth: 240 }}><label>Parcelas</label><select className="select" value={installments} onChange={e => setInstallments(parseInt(e.target.value))}>{[1,2,3,4,5,6,10,12].map(n => <option key={n} value={n}>{n}× de {centsToBRL(totalCents / n)}</option>)}</select></div>}
            {gerarCobranca && <div className="vc-cobranca">{Ic("receipt", 15)}<span>Ao salvar, gera <b>cobrança {payment === "pix" ? "PIX" : "boleto"}</b> no módulo Cobrança (Inter/BCB) — <code>idempotency sale:&#123;id&#125;</code>.</span></div>}
          </section>

          {/* ── FISCAL ── */}
          <section className="vc-sec" ref={secs.fiscal}>
            <div className="vc-sec-h"><span className="vc-sec-ic">{Ic("check", 13)}</span><h2>Documentos fiscais</h2><span className="hint">{fiscalManual ? "ajustado manual" : "inferido pela composição"}</span></div>
            <div className="vc-fis-ctx">
              <div><span className="k">Cliente</span><span className="v">{client?.name || "Consumidor Final"}</span></div>
              <div><span className="k">Composição</span><span className="v">{hasProduto && hasServico ? (isOfc ? "MO + peças" : "produto + serviço") : hasProduto ? (isOfc ? "só peças" : "só produto") : hasServico ? (isOfc ? "só MO" : "só serviço") : "—"}</span></div>
              <div><span className="k">CNPJ</span><span className="v">{hasCnpj ? "sim → NF-e" : "não → NFC-e"}</span></div>
            </div>
            <div className="vc-fis-grid">
              {FISCAL.map(d => { const on = docs.has(d.id); return (
                <button key={d.id} className={"vc-fis" + (on ? " active" : "") + (!d.avail ? " off" : "")} disabled={!d.avail} onClick={() => d.avail && toggleDoc(d.id)}>
                  <div className="vc-fis-top"><span className="vc-fis-tag">{d.tag}</span><span className={"vc-fis-state " + (on ? "on" : "na")}>{on ? "✓ vai emitir" : d.avail ? "off" : "n/d"}</span></div>
                  <b>{d.name}</b><small>{d.desc}</small>{d.why && <span className="why">⚠ {d.why}</span>}
                </button>
              ); })}
            </div>
          </section>
        </div>
      </div>

      {/* ── FOOTER STICKY ── */}
      <div className="vc-savebar">
        <div className="totals">
          <div className="m"><span className="k">{isOfc ? "Aprovado" : "Produtos"}</span><span className="v">{centsToBRL(baseCents)}</span></div>
          {isOfc && pendentes > 0 && <div className="m"><span className="k">Pendente</span><span className="v" style={{ color: "var(--warn-fg)" }}>{centsToBRL(produtosCents - aprovadoCents)}</span></div>}
          {efFreteCents > 0 && <div className="m"><span className="k">Frete</span><span className="v">{centsToBRL(efFreteCents)}</span></div>}
          <div className="m desc-in"><span className="k">Desconto</span><input inputMode="numeric" value={centsToNum(discountCents)} onChange={e => setDiscountCents(parseCents(e.target.value))}/></div>
          <span style={{ width: 1, height: 28, background: "var(--border)", flex: "0 0 auto" }}></span>
          <div className="m"><span className="k">Total</span><span className="v grand">{centsToBRL(totalCents)}</span></div>
          {!canSave && <span className="badge warn">adicione 1 item</span>}
        </div>
        <div className="acts">
          <button className="btn ghost" onClick={() => onDone?.()}>Cancelar <kbd>esc</kbd></button>
          <button className="btn ghost" disabled={!canSave} onClick={() => finish(true)}>{Ic("printer", 13)} Salvar e imprimir</button>
          <button className="btn primary-page big" disabled={!canSave} onClick={() => finish(false)}>{Ic("check", 14)} {isOfc ? "Salvar e gerar OS" : "Salvar e emitir"} <kbd style={{ marginLeft: 6, font: "600 10px/1 var(--font-mono)", opacity: .8 }}>F2</kbd></button>
        </div>
      </div>
    </div>
  );
}

window.VendasCreatePage = VendasCreatePage;
