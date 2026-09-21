// fiscal-subpages.jsx — Fiscal · sub-páginas 4/5/6/7 (Eventos · DF-e · Certificado · SPED).
// Vivo: Pages/Fiscal/{Eventos,Dfe,Config,Sped}.tsx + Modules/Fiscal/*Controller.
// Ondas 1–5: procedência por superfície · abas que faltavam · lote/export · SPED com prévia · débitos declarados.
const { useState: useStateFs, useMemo: useMemoFs } = React;
const fsU = () => window.FxUI;
const FsI = ({ name, size = 13 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const FsProc = ({ k }) => <window.FxProc k={k} />;

// Onda 5 · débitos declarados no vivo, por tela
function FsDebitos({ tela }) {
  const itens = (window.FISCAL_DATA.DEBITOS || []).filter(d => d.tela === tela);
  if (!itens.length) return null;
  return (
    <div className="fx-debitos">
      <h3>Débitos conhecidos desta tela</h3>
      {itens.map((d, i) => (
        <div className="fx-debito" data-tom={d.tom} key={i}>
          <b>{d.titulo}</b>
          <small>{d.texto}</small>
        </div>
      ))}
    </div>
  );
}

// ─────────── Sub-página 5 · Eventos (timeline append-only) ───────────
window.FxEventosPage = function FxEventosPage() {
  const U = fsU(), st = window.useFiscalStore();
  const [kind, setKind] = useStateFs("todos");
  const [periodo, setPeriodo] = useStateFs("30");
  const tipos = [
    { id: "todos", label: "Todos" }, { id: "cce", label: "CC-e (110110)" }, { id: "cancel", label: "Cancelamento (110111)" },
    { id: "inutilizacao", label: "Inutilização (102)" }, { id: "manifest", label: "Manifesto destinatário" },
  ];
  const conta = (id) => (id === "todos" ? st.eventos.length : st.eventos.filter(e => e.kind === id).length);
  const rows = st.eventos.filter(e => kind === "todos" || e.kind === kind);
  const exportar = () => window.fxExportCsv("eventos-fiscais-" + periodo + "d.csv", [
    { label: "Quando", get: e => e.emit }, { label: "Tipo", get: e => e.tipo }, { label: "Sequência", get: e => e.sequencia || "" },
    { label: "Documento", get: e => e.nota }, { label: "Justificativa", get: e => e.descricao }, { label: "Autor", get: e => e.autor }, { label: "cstat", get: e => e.sefaz },
  ], rows);
  return (
    <div className="fx-page">
      <U.Header title="Eventos fiscais" crumb={`Timeline append-only · ${rows.length} eventos nos últimos ${periodo} dias · auditoria LGPD Art. 37`}>
        <select className="fx-select" value={periodo} onChange={e => setPeriodo(e.target.value)} aria-label="Período">
          <option value="7">7 dias</option><option value="30">30 dias</option><option value="90">90 dias</option>
        </select>
        <button className="fx-btn" onClick={exportar}><FsI name="upload" /> Exportar CSV</button>
      </U.Header>
      <U.Subnav current="fiscal-eventos" />
      <div className="fx-chips" role="group" aria-label="Tipo de evento">
        {tipos.map(t => <button key={t.id} className="fx-chip" aria-pressed={kind === t.id} onClick={() => setKind(t.id)}>{t.label} <b>{conta(t.id)}</b></button>)}
      </div>
      {rows.length === 0 ? <div className="fx-empty"><b>Nenhum evento desse tipo no período</b><small>Troque o filtro ou amplie a janela.</small></div> : (
        <div className="fx-card">
          <div className="fx-card-h"><span>Eventos aplicados <FsProc k="eventos" /></span><span>{rows.length}</span></div>
          <div className="fx-tl">
            {rows.map(e => (
              <div className="fx-tl-i" data-kind={e.kind} key={e.id}>
                <span className="fx-tl-when">{e.emit}</span>
                <span className="fx-tl-dot"><i></i></span>
                <span className="fx-tl-b">
                  <b>{e.tipo}{e.sequencia ? " #" + e.sequencia : ""} · {e.nota}</b>
                  <small>{e.descricao}</small>
                </span>
                <span style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 3 }}>
                  <span className={"fx-sefaz " + ([100, 101, 102, 135, 136].indexOf(e.sefaz) >= 0 ? "ok" : "warn")}>cstat {e.sefaz}</span>
                  <code style={{ fontFamily: "var(--font-mono)", fontSize: "var(--fs-1)", color: "var(--text-mute)" }}>{e.autor}</code>
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
      <p className="fx-nota-rodape">Eventos são append-only (NfeEvento sem updated_at) — a tela não permite edição. Emissão de CC-e, cancelamento e inutilização acontece nas telas de notas.</p>
      <FsDebitos tela="fiscal-eventos" />
      <window.FxPalette />
      <window.FxToasts />
    </div>
  );
};

// ─────────── Sub-página 4 · Manifesto DF-e (pendências + histórico + lote) ───────────
window.FxDfePage = function FxDfePage() {
  const U = fsU(), st = window.useFiscalStore();
  const [aba, setAba] = useStateFs("pendencias");
  const [sit, setSit] = useStateFs("pendente");
  const [busca, setBusca] = useStateFs("");
  const [modal, setModal] = useStateFs(null);
  const [sel, setSel] = useStateFs(new Set());
  const rows = st.dfe.filter(d => (sit === "todas" || d.status === sit) && (!busca.trim() || (d.emitente + d.cnpj + d.chave).toLowerCase().indexOf(busca.toLowerCase()) >= 0));
  const conta = (id) => (id === "todas" ? st.dfe.length : st.dfe.filter(d => d.status === id).length);
  const LBL = { pendente: "Pendente", ciencia: "Ciência dada", confirmada: "Confirmada", desconhecida: "Desconhecida", nao_realizada: "Não realizada" };
  const tone = (s) => (s === "confirmada" ? "ok" : s === "pendente" || s === "ciencia" ? "warn" : "bad");
  const rot = { cienciar: "Dar ciência", confirmar: "Confirmar operação", desconhecer: "Desconhecer operação", nao_realizada: "Declarar não realizada" };

  const manifestar = (d, acao) => setModal({
    title: rot[acao] + " · NF-e " + d.num, confirmLabel: rot[acao], tone: acao === "confirmar" ? null : "danger",
    sub: d.emitente + " · " + d.cnpj + " · " + U.brl(d.valor) + ". Manifestação vai pro ambiente nacional da SEFAZ e é definitiva.",
    fields: acao === "confirmar" ? [] : [{ id: "just", label: "Justificativa", type: "textarea", min: 15, placeholder: "Ex.: mercadoria nunca chegou ao endereço da empresa" }],
    onConfirm: () => window.FxActions.manifestar(d.id, acao),
  });

  const manifestarLote = (acao) => {
    const ids = Array.from(sel);
    setModal({
      title: rot[acao] + " · " + ids.length + " DF-e", confirmLabel: rot[acao] + " em lote", tone: acao === "confirmar" ? null : "danger",
      sub: "A mesma manifestação vai pras " + ids.length + " notas selecionadas, uma requisição por nota.",
      fields: acao === "confirmar" ? [] : [{ id: "just", label: "Justificativa (vale pra todas)", type: "textarea", min: 15, placeholder: "Ex.: cargas recusadas na portaria no mesmo dia" }],
      aviso: "Manifestação é definitiva por nota — não há desfazer em lote.",
      onConfirm: () => { window.FxActions.manifestarLote(ids, acao); setSel(new Set()); },
    });
  };

  const toggle = (id) => setSel(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const manifestaveis = rows.filter(d => d.status === "pendente" || d.status === "ciencia");

  return (
    <div className="fx-page">
      <U.Header title="Manifesto DF-e" crumb={`NF-e emitidas contra o CNPJ · ${conta("pendente")} aguardando manifestação · prazo legal 90 dias`} />
      <U.Subnav current="fiscal-dfe" />
      <div className="fx-chips" data-contract="abas-dfe" role="tablist" aria-label="Abas do manifesto">
        <button className="fx-chip" aria-pressed={aba === "pendencias"} onClick={() => setAba("pendencias")}>Pendências <b>{conta("pendente")}</b></button>
        <button className="fx-chip" aria-pressed={aba === "historico"} onClick={() => setAba("historico")}>Histórico <b>{st.hist.length}</b></button>
      </div>
      {aba === "pendencias" ? (
        <>
          <div className="fx-toolbar" data-contract="filtros-dfe">
            <div className="fx-search"><FsI name="search" /><input type="search" placeholder="Buscar chave 44d, CNPJ ou emitente…" value={busca} onChange={e => setBusca(e.target.value)} /></div>
            <FsProc k="dfe" />
          </div>
          <div className="fx-chips" role="group" aria-label="Status de manifestação">
            {U.D().DFE_STATUS.map(s => <button key={s.id} className="fx-chip" aria-pressed={sit === s.id} onClick={() => setSit(s.id)}>{s.label} <b>{conta(s.id)}</b></button>)}
          </div>
          {sel.size > 0 && (
            <div className="fx-bulk" data-contract="lote-dfe" role="region" aria-label="Manifestação em lote">
              <span><b>{sel.size}</b> DF-e selecionada{sel.size > 1 ? "s" : ""}</span>
              <button className="fx-btn" onClick={() => manifestarLote("cienciar")}>Dar ciência</button>
              <button className="fx-btn" onClick={() => manifestarLote("confirmar")}>Confirmar operação</button>
              <button className="fx-btn danger" onClick={() => manifestarLote("desconhecer")}>Desconhecer</button>
              <button className="fx-btn" onClick={() => setSel(new Set())}>Limpar seleção</button>
            </div>
          )}
          {rows.length === 0 ? <div className="fx-empty"><b>Nada pra manifestar aqui</b><small>Troque o filtro de status ou limpe a busca.</small></div> : (
            <div className="fx-table fx-d-comfort" data-contract="tabela-dfe">
              <table>
                <thead><tr>
                  <th style={{ width: 34 }}><input type="checkbox" checked={sel.size === manifestaveis.length && manifestaveis.length > 0} onChange={() => setSel(s => (s.size === manifestaveis.length ? new Set() : new Set(manifestaveis.map(d => d.id))))} aria-label="Selecionar manifestáveis" /></th>
                  <th style={{ width: 74 }}>Nº</th><th>Emitente / chave</th><th style={{ width: 110 }}>Emitido</th><th style={{ width: 130 }}>Situação</th><th style={{ width: 92 }}>Prazo</th><th style={{ width: 300, textAlign: "right" }}>Valor</th>
                </tr></thead>
                <tbody>
                  {rows.map(d => (
                    <tr key={d.id}>
                      <td>{(d.status === "pendente" || d.status === "ciencia") && <input type="checkbox" checked={sel.has(d.id)} onChange={() => toggle(d.id)} aria-label={"Selecionar DF-e " + d.num} />}</td>
                      <td className="num"><b>{d.num}</b></td>
                      <td className="cli"><b>{d.emitente}</b><div>{d.cnpj}</div><code className="fx-key" title={d.chave}>{U.key(d.chave)}</code></td>
                      <td>{d.emitido}</td>
                      <td><span className={"fx-sefaz " + tone(d.status)}>{LBL[d.status]}</span></td>
                      <td>{d.prazo ? <span className={"fx-timepill u-" + d.prazo.urgency}>{d.prazo.label}</span> : <span style={{ color: "var(--text-mute)" }}>—</span>}</td>
                      <td className="val">
                        {(d.status === "pendente" || d.status === "ciencia") && (
                          <span className="fx-row-acts always">
                            {d.status === "pendente" && <button className="fx-row-act" onClick={() => manifestar(d, "cienciar")}>ciência</button>}
                            <button className="fx-row-act" onClick={() => manifestar(d, "confirmar")}>confirmar</button>
                            <button className="fx-row-act danger" onClick={() => manifestar(d, "desconhecer")}>desconhecer</button>
                            <button className="fx-row-act danger" onClick={() => manifestar(d, "nao_realizada")}>não realizada</button>
                          </span>
                        )}
                        {U.brl(d.valor)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <p className="fx-nota-rodape">O prazo vem do valor que a SEFAZ calculou, não de 90 dias fixos no código.</p>
        </>
      ) : (
        <>
          <div className="fx-card" data-contract="historico-dfe">
            <div className="fx-card-h"><span>Manifestações realizadas <FsProc k="dfeHist" /></span><span>{st.hist.length}</span></div>
            <div className="fx-tl">
              {st.hist.map(h => (
                <div className="fx-tl-i" data-kind="manifest" key={h.id}>
                  <span className="fx-tl-when">{h.quando}</span>
                  <span className="fx-tl-dot"><i></i></span>
                  <span className="fx-tl-b"><b>{h.acao} · NF-e {h.num}</b><small>{h.emitente} — {h.obs}</small></span>
                  <span style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 3 }}>
                    <span className="fx-sefaz ok">cstat {h.sefaz}</span>
                    <code style={{ fontFamily: "var(--font-mono)", fontSize: "var(--fs-1)", color: "var(--text-mute)" }}>{h.autor}</code>
                  </span>
                </div>
              ))}
            </div>
          </div>
          <div className="fx-decisao">
            <b>Decisão [W] pendente no vivo</b>
            <small>Esta aba é servida por dado de demonstração — ator e observação são inventados. O repo pede escolha: marcar procedência, esconder atrás de flag ou declarar Non-Goal.</small>
          </div>
        </>
      )}
      <window.FxActionModal spec={modal} onClose={() => setModal(null)} />
      <window.FxPalette />
      <window.FxToasts />
    </div>
  );
};

// ─────────── Sub-página 6 · Certificado e configuração (abas do vivo) ───────────
window.FxConfigPage = function FxConfigPage() {
  const U = fsU(), st = window.useFiscalStore();
  const c = st.config || U.D().CONFIG;
  const [aba, setAba] = useStateFs("certificado");
  const [modal, setModal] = useStateFs(null);
  const urg = c.cert.dias <= 7 ? "crit" : c.cert.dias <= 60 ? "warn" : "ok";
  const gate = st.gate;
  const destino = c.cert.ambiente === "Produção" ? "Homologação" : "Produção";
  const abas = [
    { id: "certificado", label: "Certificado e regime" },
    { id: "series", label: "Séries", n: U.D().SERIES.length },
    { id: "ambiente", label: "Ambiente e certificado" },
    { id: "sped", label: "SPED" },
  ];
  const trocar = () => setModal({
    title: "Trocar ambiente de emissão", confirmLabel: "Trocar para " + destino, tone: "danger",
    sub: c.cert.ambiente + " → " + destino + ". Em homologação nenhuma nota tem valor fiscal; em produção toda nota emitida vai pro Fisco.",
    fields: [
      { id: "confirma", label: 'Digite ' + destino.toUpperCase() + " para confirmar", placeholder: destino.toUpperCase() },
      { id: "just", label: "Motivo", type: "textarea", min: 15, placeholder: "Ex.: homologando série nova antes de emitir em produção" },
    ],
    aviso: "Ação de superadmin, registrada na auditoria com autor e horário.",
    onConfirm: (v) => {
      if (String(v.confirma).trim().toUpperCase() !== destino.toUpperCase()) { window.fxToast("Confirmação não bateu — ambiente inalterado", "warn"); return; }
      window.FxActions.trocarAmbiente(destino, v.just.trim());
    },
  });
  const enviarCert = () => setModal({
    title: "Enviar certificado A1", confirmLabel: "Enviar certificado",
    sub: "O arquivo substitui o certificado vigente do CNPJ " + c.cert.cnpj + ". A senha nunca é gravada em log nem devolvida em payload.",
    fields: [
      { id: "arquivo", label: "Nome do arquivo .pfx", placeholder: "office-impresso-2026.pfx" },
      { id: "senha", label: "Senha do certificado", type: "password", min: 4, placeholder: "não é exibida depois de salva" },
    ],
    aviso: "Titular e CNPJ do certificado são validados no emissor — arquivo de outro CNPJ é recusado lá.",
    onConfirm: (v) => window.FxActions.enviarCertificado(v.arquivo.trim() || "certificado.pfx"),
  });
  return (
    <div className="fx-page">
      <U.Header title="Certificado e configuração fiscal" crumb="Séries e envio de documentos editáveis · ambiente e certificado atrás de gate próprio" />
      <U.Subnav current="fiscal-config" />
      <div className="fx-chips" data-contract="abas-config" role="tablist" aria-label="Abas de configuração">
        {abas.map(a => <button key={a.id} className="fx-chip" aria-pressed={aba === a.id} onClick={() => setAba(a.id)}>{a.label}{a.n != null && <b>{a.n}</b>}</button>)}
      </div>

      {aba === "certificado" && (
        <>
          <div className="fx-grid" data-contract="cert-regime">
            <div className="fx-card">
              <div className="fx-card-h"><span>Certificado digital <FsProc k="config" /></span><span className={"fx-timepill u-" + urg}>vence em {c.cert.dias}d</span></div>
              <div className="fx-row"><span className="fx-row-l">Tipo</span><span className="fx-row-v">{c.cert.tipo}</span></div>
              <div className="fx-row"><span className="fx-row-l">Titular</span><span className="fx-row-v txt">{c.cert.titular}</span></div>
              <div className="fx-row"><span className="fx-row-l">CNPJ</span><span className="fx-row-v">{c.cert.cnpj}</span></div>
              <div className="fx-row"><span className="fx-row-l">Válido até</span><span className="fx-row-v">{c.cert.validoAte}</span></div>
              <div className="fx-row"><span className="fx-row-l">Ambiente</span><span className="fx-row-v txt">{c.cert.ambiente} · série {c.cert.serie}</span></div>
              {c.cert.pendente && <div className="fx-row"><span className="fx-row-l">Certificado enviado nesta sessão</span><span className="fx-row-v txt">{c.cert.pendente} · aguardando validação no emissor</span></div>}
            </div>
            <div className="fx-card">
              <div className="fx-card-h"><span>Regime tributário</span></div>
              <div className="fx-row"><span className="fx-row-l">Regime</span><span className="fx-row-v txt">{c.regime.nome}</span></div>
              <div className="fx-row"><span className="fx-row-l">Anexo</span><span className="fx-row-v txt">{c.regime.anexo}</span></div>
              <div className="fx-row"><span className="fx-row-l">CRT</span><span className="fx-row-v">{c.regime.crt}</span></div>
              <div className="fx-row"><span className="fx-row-l">CFOP interno / interestadual</span><span className="fx-row-v">{c.regime.cfopInterno} / {c.regime.cfopInterestadual}</span></div>
            </div>
            <div className="fx-card">
              <div className="fx-card-h"><span>Tributação default</span></div>
              {c.tributacao.map((t, i) => <div className="fx-row" key={i}><span className="fx-row-l">{t.label}</span><span className="fx-row-v txt">{t.valor}</span></div>)}
            </div>
            <div className="fx-card">
              <div className="fx-card-h"><span>Envio de documentos</span></div>
              <div className="fx-row"><span className="fx-row-l">Contador</span><span className="fx-row-v txt">{c.emails.contador}</span></div>
              <div className="fx-row"><span className="fx-row-l">Envio automático ao autorizar</span><span className="fx-row-v txt">{c.emails.envioAutomatico ? "Ativo" : "Desligado"}</span></div>
              <div className="fx-row"><span className="fx-row-l">Cópia pro cliente</span><span className="fx-row-v txt">{c.emails.copiaCliente ? "Ativo" : "Desligado"}</span></div>
            </div>
          </div>
        </>
      )}

      {aba === "series" && (
        <>
          <div className="fx-table fx-d-comfort" data-contract="series-config">
            <table>
              <thead><tr><th>Local <FsProc k="series" /></th><th style={{ width: 140 }}>Modelo</th><th style={{ width: 80 }}>Série</th><th style={{ width: 150 }}>Ambiente</th><th style={{ width: 160, textAlign: "right" }}>Próximo número</th></tr></thead>
              <tbody>
                {U.D().SERIES.map(s => (
                  <tr key={s.id}>
                    <td className="cli"><b>{s.local}</b></td>
                    <td>{s.modelo}</td>
                    <td className="num"><b>{s.serie}</b></td>
                    <td><span className={"fx-sefaz " + (s.ambiente === "Produção" ? "ok" : "warn")}>{s.ambiente}</span></td>
                    <td className="val">{s.proximo}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="fx-decisao">
            <b>Proposta [CC] — ratificação [W] pendente</b>
            <small>Séries lidas de verdade do emissor, uma linha por modelo da matriz; a filial inventada do mock saiu. Se existir filial real, ela entra aqui pelo mesmo caminho — não por dado fixo no código.</small>
          </div>
        </>
      )}

      {aba === "ambiente" && (
        <>
          <div className="fx-decisao">
            <b>Proposta [CC] — a tela é editável, com gate próprio</b>
            <small>Os formulários existem no código do vivo e o charter dizia read-only. Proposta: o charter cede, e as duas ações de risco fiscal ficam atrás de <code>fiscal.config.ambiente</code> — separado de <code>fiscal.config.edit</code>, porque trocar ambiente e substituir certificado param a emissão da empresa inteira. Ratificação [W] pendente.</small>
          </div>
          <div className="fx-validacao" data-contract="gate-ambiente" data-ok={gate ? "true" : "false"}>
            <b>Permissão fiscal.config.ambiente</b>
            <span>{gate ? "✓ concedida (superadmin)" : "✕ ausente — campos travados"}</span>
            <span>✓ senha do certificado em $hidden, nunca no payload</span>
            <span>✓ toda troca vira evento na auditoria</span>
            <button className="fx-btn" onClick={window.FxActions.toggleGate}>{gate ? "Revogar permissão" : "Assumir como superadmin"}</button>
          </div>
          <div className="fx-grid" data-contract="troca-ambiente">
            <div className="fx-card">
              <div className="fx-card-h"><span>Ambiente de emissão <FsProc k="ambiente" /></span><span className={"fx-sefaz " + (c.cert.ambiente === "Produção" ? "ok" : "warn")}>{c.cert.ambiente}</span></div>
              <div className="fx-form">
                <label className="fx-field"><span>Ambiente vigente</span><input value={c.cert.ambiente} readOnly /></label>
                <button className="fx-btn danger" disabled={!gate} title={gate ? "" : "Exige fiscal.config.ambiente"} onClick={trocar}>Trocar para {destino}</button>
              </div>
            </div>
            <div className="fx-card">
              <div className="fx-card-h"><span>Certificado A1</span><span className={"fx-timepill u-" + urg}>vence em {c.cert.dias}d</span></div>
              <div className="fx-form">
                <label className="fx-field"><span>Certificado vigente</span><input value={c.cert.titular} readOnly /></label>
                <button className="fx-btn" disabled={!gate} title={gate ? "" : "Exige fiscal.config.ambiente"} onClick={enviarCert}>Enviar novo certificado</button>
              </div>
            </div>
          </div>
          <p className="fx-nota-rodape">Trocar ambiente pede o nome do destino digitado à mão, além do motivo — confirmação de uma palavra não segura uma ação que muda o valor fiscal de toda nota emitida depois dela.</p>
        </>
      )}

      {aba === "sped" && (
        <div className="fx-card">
          <div className="fx-card-h"><span>SPED</span></div>
          <div className="fx-row"><span className="fx-row-l">Esta aba aponta pra tela dona</span><button className="fx-btn" onClick={() => U.go("fiscal-sped")}>Abrir SPED e livros</button></div>
          <div className="fx-row"><span className="fx-row-l">Trava do download</span><span className="fx-row-v txt">sped_simples_only_lock (fail-secure) <FsProc k="spedGerador" /></span></div>
        </div>
      )}
      <window.FxActionModal spec={modal} onClose={() => setModal(null)} />
      <window.FxPalette />
      <window.FxToasts />
    </div>
  );
};

// ─────────── Sub-página 7 · SPED e livros (gerador + prévia + smoke) ───────────
window.FxSpedPage = function FxSpedPage() {
  const U = fsU();
  const [comp, setComp] = useStateFs("04/2026");
  const [lock, setLock] = useStateFs(true);
  const [gerando, setGerando] = useStateFs(false);
  const [previa, setPrevia] = useStateFs(false);
  const sel = U.D().SPED.find(p => p.comp === comp) || U.D().SPED[0];
  const LBL = { aberto: "Aberto", pronto: "Pronto pra gerar", entregue: "Entregue" };
  const ano = sel.comp.split("/")[1];
  const anoOk = Number(ano) >= 2020, aberto = sel.status === "aberto";
  const bloqueado = aberto || !anoOk || lock;
  const registros = U.D().SPED_BLOCOS.reduce((a, b) => a + b.registros, 0);
  const gerar = () => { setGerando(true); setTimeout(() => { setGerando(false); setPrevia(true); window.fxToast("EFD-ICMS/IPI " + sel.label + " gerado · " + registros + " registros · perfil A"); }, 900); };

  return (
    <div className="fx-page">
      <U.Header title="SPED e livros fiscais" crumb="EFD-ICMS/IPI · layout CONFAZ Guia Prático v3.1.1, perfil A">
        <button className="fx-btn" onClick={() => setPrevia(v => !v)}>{previa ? "Fechar prévia" : "Ver prévia do TXT"}</button>
        <button className="fx-btn primary" disabled={bloqueado || gerando} onClick={gerar}><FsI name="upload" /> {gerando ? "Gerando…" : "Gerar TXT " + sel.label}</button>
      </U.Header>
      <U.Subnav current="fiscal-sped" />
      <div className="fx-validacao" data-contract="validacao-competencia" data-ok={bloqueado ? "false" : "true"}>
        <b>Competência {sel.comp}</b>
        <span>{anoOk ? "✓ ano ≥ 2020" : "✕ ano inválido"}</span>
        <span>✓ não-futura</span>
        <span>{aberto ? "✕ mês em aberto — fecha em " + sel.entrega : "✓ competência fechada"}</span>
        <span>{lock ? "✕ trava sped_simples_only_lock ativa" : "✓ trava liberada (superadmin)"}</span>
        <button className="fx-btn" onClick={() => setLock(v => !v)}>{lock ? "Liberar como superadmin" : "Reativar trava"}</button>
      </div>
      <div className="fx-table fx-d-comfort" data-contract="panorama-sped">
        <table>
          <thead><tr><th>Competência <FsProc k="sped" /></th><th style={{ width: 110 }}>Notas</th><th style={{ width: 160 }}>Situação</th><th style={{ width: 150 }}>Prazo (heurística)</th><th style={{ width: 200, textAlign: "right" }}>Valor autorizado</th></tr></thead>
          <tbody>
            {U.D().SPED.map(p => (
              <tr key={p.comp} className={comp === p.comp ? "sel" : ""} onClick={() => setComp(p.comp)}>
                <td className="cli"><b>{p.label}</b><div>competência {p.comp}</div></td>
                <td className="num"><b>{p.notas}</b></td>
                <td><span className={"fx-sefaz " + (p.status === "entregue" ? "ok" : p.status === "pronto" ? "warn" : "")}>{LBL[p.status]}</span></td>
                <td>{p.entrega}</td>
                <td className="val">{U.brl(p.valor)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {previa && (
        <div className="fx-card" data-contract="previa-txt">
          <div className="fx-card-h"><span>Prévia do arquivo · {sel.label}</span><span>{registros} registros · perfil A</span></div>
          <pre className="fx-txt">{U.D().SPED_TXT.join("\n")}</pre>
          <div className="fx-row"><span className="fx-row-l">Amostra dos registros canônicos dos blocos 0 · C · E · H · 9 (linhas encurtadas para leitura)</span></div>
        </div>
      )}
      <div className="fx-grid">
        <div className="fx-card" data-contract="blocos-arquivo">
          <div className="fx-card-h"><span>Blocos do arquivo · {sel.label}</span><span>{registros} registros</span></div>
          {U.D().SPED_BLOCOS.map(b => (
            <div className="fx-row" key={b.id}>
              <span className="fx-row-l"><b style={{ fontFamily: "var(--font-mono)", color: "var(--text)" }}>{b.id}</b> — {b.nome}<br /><code style={{ fontFamily: "var(--font-mono)", fontSize: "var(--fs-1)", color: "var(--text-mute)" }}>{b.registrosIds}</code></span>
              <span className="fx-row-v">{b.registros}</span>
            </div>
          ))}
        </div>
        <div className="fx-card" data-contract="validacao-externa">
          <div className="fx-card-h"><span>Validação e livros</span></div>
          <div className="fx-row"><span className="fx-row-l">Smoke no PVA-EFD (validador CONFAZ)</span><span className="fx-row-v txt" data-tone="warn">nunca executado</span></div>
          <div className="fx-row"><span className="fx-row-l">Golden file do TXT</span><span className="fx-row-v txt">não existe</span></div>
          <div className="fx-row"><span className="fx-row-l">Apuração ICMS</span><span className="fx-row-v txt">no arquivo (Bloco E)</span></div>
          <div className="fx-row"><span className="fx-row-l">Apuração ISS · EFD-Contribuições · Conciliação SEFAZ × ERP</span><span className="fx-row-v txt">backlog</span></div>
        </div>
      </div>
      <p className="fx-nota-rodape">Só saídas por ora; CFOP sai da UF origem × destino (5xxx interno, 6xxx interestadual) e o tributo por item vem do motor tributário — com fallback Simples Nacional (CSOSN 102) enquanto a trava estiver ativa.</p>
      <FsDebitos tela="fiscal-sped" />
      <window.FxPalette />
      <window.FxToasts />
    </div>
  );
};
