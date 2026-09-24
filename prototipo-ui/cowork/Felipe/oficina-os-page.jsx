// oficina-os-page.jsx — Nova Ordem de Serviço (Oficina) · documento vivo
// window.OficinaOSPage · ref: Shopmonkey (calma) × Tekmetric (fluxo) × Shop-Ware (DVI)
// F1.5→F2: interações conectadas (DVI→orçamento, "/", gate bloqueia), estados, tablet, foco/contraste.
const { useState: useStateOfx, useRef: useRefOfx, useEffect: useEffectOfx } = React;

const OFX_DATA = {
  os: "OS #4821",
  veh: { plate: "RBA-2H78", model: "Honda Civic EXL", year: "2019", km: "84.220", color: "Prata", fuel: 35 },
  cust: { name: "Marcos Aleixo", phone: "(11) 9 8821-4490" },
  steps: ["Recepção", "Diagnóstico", "Orçamento", "Aprovação", "Execução", "Pronto"],
  active: 2,
  damages: [
    { t: "Risco porta dir.", on: true },
    { t: "Amassado para-choque", on: true },
    { t: "Para-brisa trincado", on: false },
  ],
  relato: "Cliente relata barulho metálico na frente ao frear e pedal de freio baixo. Pediu também revisão de óleo.",
  dvi: [
    { nm: "Pastilhas dianteiras", sub: "espessura 2mm — abaixo do mínimo", s: "r", photo: true, sug: 120 },
    { nm: "Disco dianteiro", sub: "sulco perceptível", s: "y", photo: true, sug: 90 },
    { nm: "Óleo do motor", sub: "vencido / nível baixo", s: "r", photo: false, sug: 60 },
    { nm: "Pneus", sub: "sulco ok, 4mm", s: "g", photo: false, sug: 0 },
    { nm: "Suspensão dianteira", sub: "sem folga", s: "g", photo: false, sug: 0 },
    { nm: "Bateria", sub: "12.3V — observar", s: "y", photo: false, sug: 0 },
  ],
  servicos: [
    { nm: "Troca de pastilhas + disco dianteiro", mech: "João Lima", ab: "JL", h: "1,5h", v: 180 },
    { nm: "Troca de óleo + filtro", mech: "Pedro Souza", ab: "PS", h: "0,5h", v: 60 },
  ],
  pecas: [
    { nm: "Jogo pastilhas dianteiras Bosch", sku: "BRP-001", qty: "1 un", stock: "ok", v: 280 },
    { nm: "Disco de freio dianteiro (par)", sku: "DSC-118", qty: "1 par", stock: "res", v: 420 },
    { nm: "Óleo 5W30 sintético", sku: "OLE-5W30", qty: "4 L", stock: "ok", v: 180 },
    { nm: "Filtro de óleo", sku: "FLT-220", qty: "1 un", stock: "ok", v: 45 },
  ],
};

const ofxBRL = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function OfxCamera(p) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}>
      <path d="M3 8a2 2 0 0 1 2-2h2l1.5-2h7L17 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>
      <circle cx="12" cy="12.5" r="3.2"/>
    </svg>
  );
}

function OfxStepper({ steps, active }) {
  return (
    <div className="ofx-fsm">
      {steps.map((s, i) => (
        <React.Fragment key={s}>
          {i > 0 && <span className={"ofx-step-line" + (i <= active ? " done" : "")} />}
          <div className={"ofx-step " + (i < active ? "done" : i === active ? "active" : "")}>
            <span className="dot">{i < active ? <I.check size={12} /> : i + 1}</span>
            <span className="lbl">{s}</span>
          </div>
        </React.Fragment>
      ))}
    </div>
  );
}

function OfxDviRow({ item, idx, onStatus, onAdd, added }) {
  const s = item.s;
  return (
    <div className={"ofx-dvi-item" + (s === "r" ? " is-r" : "")}>
      <div className="ofx-traffic" role="radiogroup" aria-label={"Estado de " + item.nm}>
        {["g", "y", "r"].map((c) => (
          <button key={c} type="button" className={"ofx-tl " + c + (s === c ? " on" : "")}
            aria-pressed={s === c} aria-label={c === "g" ? "OK" : c === "y" ? "Atenção" : "Reprovado"}
            onClick={() => onStatus(idx, c)} />
        ))}
      </div>
      <div className="nm">{item.nm}<small>{item.sub}</small></div>
      <span className={"ofx-photo" + (item.photo ? " has" : "")}><OfxCamera style={{ width: 13, height: 13 }} />{item.photo ? "2 fotos" : "foto"}</span>
      {s === "r" && (added
        ? <span className="ofx-added"><I.check size={12} />no orçamento</span>
        : <button type="button" className="ofx-addbtn" onClick={() => onAdd(item)}>+ orçamento</button>)}
    </div>
  );
}

function OfxSection({ icon, title, desc, count, children, cls }) {
  return (
    <div className={"ofx-card " + (cls || "")}>
      <div className="ofx-sec-h">
        <span className="ic">{icon}</span>
        <div>
          <h3>{title}</h3>
          {desc && <div className="desc">{desc}</div>}
        </div>
        {count != null && <span className="count">{count}</span>}
      </div>
      <div className="ofx-sec-b">{children}</div>
    </div>
  );
}

function OficinaOSPage() {
  const d = OFX_DATA;
  const [vert, setVert] = useStateOfx("oficina");
  const [tab, setTab] = useStateOfx("servicos");
  const [dvi, setDvi] = useStateOfx(d.dvi.map((x) => ({ ...x })));
  const [servicos, setServicos] = useStateOfx(d.servicos.map((x) => ({ ...x })));
  const [pecas, setPecas] = useStateOfx(d.pecas.map((x) => ({ ...x })));
  const [addedDvi, setAddedDvi] = useStateOfx({});
  const [approval, setApproval] = useStateOfx("aguardando"); // aguardando · enviado · aprovado
  const [toast, setToast] = useStateOfx(null);
  const searchRef = useRefOfx(null);
  const isOfi = vert === "oficina";

  useEffectOfx(() => {
    const h = (e) => {
      if (e.key === "/" && !/^(input|textarea)$/i.test(e.target.tagName)) {
        e.preventDefault();
        if (searchRef.current) searchRef.current.focus();
      }
    };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, []);

  const showToast = (msg) => { setToast(msg); window.clearTimeout(showToast._t); showToast._t = window.setTimeout(() => setToast(null), 2600); };
  const setDviStatus = (i, s) => setDvi((p) => p.map((x, j) => (j === i ? { ...x, s } : x)));
  const addReprov = (item) => {
    setServicos((p) => [...p, { nm: "Verificar/trocar — " + item.nm, mech: "A definir", ab: "?", h: "a orçar", v: item.sug || 0, fromDvi: true }]);
    setAddedDvi((p) => ({ ...p, [item.nm]: true }));
    setTab("servicos");
    showToast(item.nm + " adicionado ao orçamento");
  };
  const sendWhats = () => { setApproval("enviado"); showToast("Orçamento enviado por WhatsApp ao cliente"); };
  const markApproved = () => { setApproval("aprovado"); showToast("Cliente aprovou o orçamento"); };

  const totPecas = pecas.reduce((a, p) => a + p.v, 0);
  const totServ = servicos.reduce((a, s) => a + s.v, 0);
  const total = totPecas + totServ;
  const reprov = dvi.filter((x) => x.s === "r").length;
  const aten = dvi.filter((x) => x.s === "y").length;
  const list = tab === "servicos" ? servicos : pecas;

  return (
    <div className="ofx">
      <div className="ofx-top">
        <button className="ofx-back" title="Voltar" aria-label="Voltar"><I.chev size={16} style={{ transform: "rotate(90deg)" }} /></button>
        <div className="ofx-title">
          <h1>Nova Ordem de Serviço</h1>
          <span className="os-no">{d.os} · aberta hoje 09:14</span>
        </div>
        <OfxStepper steps={d.steps} active={d.active} />
        <div className="ofx-top-r">
          <div className="ofx-vsw" role="tablist" aria-label="Vertical">
            {[["oficina", "Oficina"], ["cv", "Com. Visual"], ["roupa", "Vestuário"]].map(([k, l]) => (
              <button key={k} role="tab" aria-selected={vert === k} className={vert === k ? "on" : ""} onClick={() => setVert(k)}>{l}</button>
            ))}
          </div>
        </div>
      </div>

      <div className="ofx-scroll">
        <div className="ofx-grid">

          {isOfi ? (
            <div className="ofx-hero">
              <div className="ofx-plate">
                <div className="br">BRASIL</div>
                <div className="num">{d.veh.plate}</div>
              </div>
              <div className="ofx-veh">
                <h2>{d.veh.model} <span className="yr">{d.veh.year}</span></h2>
                <div className="sub">{d.veh.color} · chassi 9BWZZZ377VT004251</div>
                <div className="specs">
                  <div className="ofx-spec"><div className="k">Quilometragem</div><div className="v">{d.veh.km} km</div></div>
                  <div className="ofx-spec"><div className="k">Última revisão</div><div className="v">72.000 km</div></div>
                  <div className="ofx-spec"><div className="k">Mecânico</div><div className="v">João Lima</div></div>
                </div>
              </div>
              <div className="ofx-fuel">
                <div className="k">Combustível</div>
                <div className="bar"><i style={{ width: d.veh.fuel + "%" }} /></div>
                <div className="lvl">{d.veh.fuel}% — ~1/3 tanque</div>
              </div>
              <div className="ofx-cust">
                <span className="nm">{d.cust.name}</span>
                <span className="row"><I.phone size={13} />{d.cust.phone}</span>
                <span className="row"><I.clock size={13} />Cliente desde 2021 · 6 OS</span>
              </div>
            </div>
          ) : (
            <div className="ofx-hero ofx-hero-alt">
              <span className="ofx-hero-ic">{vert === "cv" ? <I.layers size={22} /> : <I.product size={22} />}</span>
              <div className="ofx-veh">
                <h2>{vert === "cv" ? "Comunicação Visual" : "Vestuário"}</h2>
                <div className="sub">{vert === "cv"
                  ? "Venda por arte/medidas (m²) — veículo e inspeção ocultos; entra arte, prova e local de instalação."
                  : "Venda por grade (tamanho × cor) — veículo e inspeção ocultos; entra grade e produção sob encomenda."}</div>
              </div>
            </div>
          )}

          <div className="ofx-main">

            {isOfi && (
              <OfxSection icon={<I.info size={16} />} title="Check-in do veículo" desc="Estado de entrada — protege a oficina e o cliente">
                <div className="ofx-checkin">
                  <div className="ofx-field" style={{ gridColumn: "1 / -1" }}>
                    <label className="k">Relato do cliente</label>
                    <textarea className="ofx-ta" rows="2" defaultValue={d.relato} />
                  </div>
                  <div className="ofx-field">
                    <span className="k">Avarias na entrada</span>
                    <div className="ofx-damage">
                      {d.damages.map((dm, i) => (
                        <button type="button" key={i} className={"ofx-dmg" + (dm.on ? " on" : "")}><span className="d" />{dm.t}</button>
                      ))}
                      <button type="button" className="ofx-dmg"><I.plus size={12} />marcar</button>
                    </div>
                  </div>
                  <div className="ofx-field">
                    <span className="k">Fotos de entrada</span>
                    <div className="ofx-damage">
                      <button type="button" className="ofx-photo has"><OfxCamera style={{ width: 13, height: 13 }} />Frente</button>
                      <button type="button" className="ofx-photo has"><OfxCamera style={{ width: 13, height: 13 }} />Lateral</button>
                      <button type="button" className="ofx-photo"><OfxCamera style={{ width: 13, height: 13 }} />+ adicionar</button>
                    </div>
                  </div>
                </div>
              </OfxSection>
            )}

            {isOfi && (
              <OfxSection icon={<I.search size={16} />} title="Inspeção" desc="Diagnóstico item a item — reprovado vira orçamento"
                count={reprov + " reprovados · " + aten + " atenção"}>
                {dvi.map((it, i) => (
                  <OfxDviRow key={i} item={it} idx={i} onStatus={setDviStatus} onAdd={addReprov} added={!!addedDvi[it.nm]} />
                ))}
              </OfxSection>
            )}

            {!isOfi && (
              <OfxSection icon={<I.layers size={16} />} title={vert === "cv" ? "Arte & medidas" : "Grade do pedido"}
                desc={vert === "cv" ? "Material, m² e acabamento" : "Tamanho × cor × quantidade"}>
                <p className="ofx-vert-note">Seção condicional da vertical <b>{vert === "cv" ? "Comunicação Visual" : "Vestuário"}</b> — o mesmo documento, com a entrada adequada ao que se vende. (Foco deste protótipo: Oficina.)</p>
              </OfxSection>
            )}

            <OfxSection icon={<I.orders size={16} />} title="Itens da OS" desc="Serviço (mão de obra) e peças — naturezas fiscais distintas">
              <div className="ofx-tabs" role="tablist">
                <button role="tab" aria-selected={tab === "servicos"} className={"ofx-tab" + (tab === "servicos" ? " on" : "")} onClick={() => setTab("servicos")}>Serviços · {servicos.length}</button>
                <button role="tab" aria-selected={tab === "pecas"} className={"ofx-tab" + (tab === "pecas" ? " on" : "")} onClick={() => setTab("pecas")}>Peças · {pecas.length}</button>
              </div>
              <div className="ofx-search">
                <I.search />
                <input ref={searchRef} placeholder={tab === "servicos" ? "Buscar serviço ou tabela de mão de obra…" : "Buscar peça por nome, código ou aplicação…"} />
                <span className="kbd">/</span>
              </div>
              {list.length === 0 ? (
                <div className="ofx-empty">
                  <I.orders size={22} />
                  <p>Nenhum {tab === "servicos" ? "serviço" : "peça"} ainda.</p>
                  <span>Use a busca acima ou adicione um item reprovado da inspeção.</span>
                </div>
              ) : tab === "servicos" ? servicos.map((s, i) => (
                <div className="ofx-line" key={i}>
                  <div className="nm">{s.nm}
                    <span className="ofx-mech"><span className="ofx-av">{s.ab}</span>{s.mech}{s.fromDvi && <span className="ofx-frominsp">da inspeção</span>}</span>
                  </div>
                  <span className="ofx-qty">{s.h}</span>
                  <span className="ofx-qty">{s.v ? ofxBRL(s.v) : "—"}</span>
                  <span className="tot">{s.v ? ofxBRL(s.v) : "a orçar"}</span>
                  <button type="button" className="ofx-rm" aria-label="Remover" onClick={() => setServicos((p) => p.filter((_, j) => j !== i))}>×</button>
                </div>
              )) : pecas.map((p, i) => (
                <div className="ofx-line" key={i}>
                  <div className="nm">{p.nm}<small>{p.sku}</small></div>
                  <span className="ofx-qty">{p.qty}</span>
                  <span className={"ofx-stock " + p.stock}>{p.stock === "ok" ? "em estoque" : p.stock === "res" ? "reservar" : "sem estoque"}</span>
                  <span className="tot">{ofxBRL(p.v)}</span>
                  <button type="button" className="ofx-rm" aria-label="Remover" onClick={() => setPecas((pp) => pp.filter((_, j) => j !== i))}>×</button>
                </div>
              ))}
            </OfxSection>
          </div>

          <div className="ofx-rail">
            <div className="ofx-card">
              <div className="ofx-sec-b ofx-sum">
                <div className="row"><span>Mão de obra</span><span className="v">{ofxBRL(totServ)}</span></div>
                <div className="row"><span>Peças</span><span className="v">{ofxBRL(totPecas)}</span></div>
                <div className="row sub"><span>Desconto</span><span className="v">R$ 0,00</span></div>
                <div className="total"><span className="k">Total da OS</span><span className="v">{ofxBRL(total)}</span></div>
              </div>
            </div>

            <div className={"ofx-card ofx-gate ofx-gate-" + approval}>
              <div className="ofx-sec-h"><span className="ic">{approval === "aprovado" ? <I.check size={16} /> : <I.clock size={16} />}</span><div><h3>Aprovação do cliente</h3></div></div>
              <div className="ofx-sec-b">
                {approval === "aguardando" && <>
                  <div className="ofx-gate-status"><span className="pulse" />Aguardando aprovação</div>
                  <p>A execução não inicia sem o cliente autorizar. Envie o orçamento com as fotos da inspeção.</p>
                  <button className="ofx-wpp" onClick={sendWhats}><I.send size={16} />Enviar orçamento por WhatsApp</button>
                </>}
                {approval === "enviado" && <>
                  <div className="ofx-gate-status enviado"><I.send size={13} />Enviado · aguardando resposta</div>
                  <p>Orçamento enviado ao cliente. Quando ele responder, registre a decisão.</p>
                  <div className="ofx-gate-acts">
                    <button className="ofx-wpp ghost" onClick={() => showToast("Reenviado")}>Reenviar</button>
                    <button className="ofx-wpp" onClick={markApproved}><I.check size={16} />Registrar aprovação</button>
                  </div>
                </>}
                {approval === "aprovado" && <>
                  <div className="ofx-gate-status aprovado"><I.check size={13} />Aprovado pelo cliente</div>
                  <p>Liberado para execução. O mecânico já pode iniciar o serviço.</p>
                </>}
              </div>
            </div>

            <div className="ofx-card">
              <div className="ofx-sec-h"><span className="ic"><I.receipt size={16} /></span><div><h3>Fiscal</h3></div></div>
              <div className="ofx-sec-b ofx-fiscal">
                <div className="ofx-doc"><span className="badge nfe">NF-e 55</span><span className="lbl">Peças<small>mercadoria</small></span><span className="amt">{ofxBRL(totPecas)}</span></div>
                <div className="ofx-doc"><span className="badge nfse">NFS-e</span><span className="lbl">Mão de obra<small>serviço</small></span><span className="amt">{ofxBRL(totServ)}</span></div>
                <div className="ofx-warranty"><I.shield size={15} />Garantia de 90 dias em serviço e peça</div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div className="ofx-foot">
        <div className="recap">
          <div><div className="k">Peças</div><div className="v">{ofxBRL(totPecas)}</div></div>
          <div><div className="k">Mão de obra</div><div className="v">{ofxBRL(totServ)}</div></div>
          <div><div className="k">Total</div><div className="v big">{ofxBRL(total)}</div></div>
        </div>
        <div className="sp" />
        <button className="ofx-btn">Cancelar</button>
        <button className="ofx-btn">Salvar rascunho</button>
        {approval === "aprovado"
          ? <button className="ofx-btn primary"><I.check size={15} />Iniciar execução</button>
          : <button className="ofx-btn primary is-locked" disabled title="Requer aprovação do cliente"><I.clock size={15} />Avançar p/ Aprovação</button>}
      </div>

      {toast && <div className="ofx-toast"><I.check size={14} />{toast}</div>}
    </div>
  );
}

window.OficinaOSPage = OficinaOSPage;
