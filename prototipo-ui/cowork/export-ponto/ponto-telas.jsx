// ponto-telas.jsx — telas do módulo Ponto vindas do Blade (Modules/Ponto/Resources/views):
// aprovacoes/index+_tabela · intercorrencias/index+_form+show · banco-horas/index+show ·
// escalas/index+_form · colaboradores/index+edit · importacoes/index+create+show ·
// relatorios/index · configuracoes/index+reps. Mesmos campos, mesmos enums, mesmas regras
// (rascunho edita/submete; aprovada não; ajuste de BH exige observação; config é read-only).
// Expõe window.PontoTelas.
(() => {
const { useState, useMemo, useEffect } = React;
const P = () => window.PONTO;
const U = () => window.PontoUI;

// ═══════════════════════════ 1. APROVAÇÕES ═══════════════════════════
function Aprovacoes({ avisar, onVerIntercorrencia, rows, setRows }) {
  const { Card, Tabela, Vazio, PillIntercorrencia, PillPrioridade, Legal, usePagina, Pager, Ic } = U();
  const D = P();
  const [estado, setEstado] = useState("PENDENTE");
  const [tipo, setTipo] = useState("");
  const [marcadas, setMarcadas] = useState([]);
  const [motivoLote, setMotivoLote] = useState("");

  const lista = rows.filter((i) => (!estado || i.estado === estado) && (!tipo || i.tipo === tipo));
  const pg = usePagina(lista.length, 15);
  const pagina = pg.fatia(lista);
  const selecionaveis = pagina.filter((i) => i.estado === "PENDENTE").map((i) => i.id);
  const todasMarcadas = selecionaveis.length > 0 && selecionaveis.every((id) => marcadas.includes(id));
  const toggle = (id) => setMarcadas((m) => m.includes(id) ? m.filter((x) => x !== id) : [...m, id]);
  const decidirLote = (ok) => {
    if (!ok && !motivoLote.trim()) { avisar("Rejeição em lote exige um motivo único — ele vai para todos os solicitantes.", "warn"); return; }
    setRows((rs) => rs.map((r) => marcadas.includes(r.id) ? { ...r, estado: ok ? "APROVADA" : "REJEITADA", aprovador: "Wagner Ramos", aprovado_em: "20/08/2026 09:12", motivo_rejeicao: ok ? null : motivoLote.trim() } : r));
    avisar(marcadas.length + (ok ? " intercorrências aprovadas em lote — apuração será reprocessada." : " intercorrências rejeitadas com o mesmo motivo."), ok ? "ok" : "warn");
    setMarcadas([]); setMotivoLote("");
  };
  const decidir = (id, ok) => {
    if (!ok) {
      const motivo = window.prompt("Motivo da rejeição:");
      if (!motivo) return;
      setRows((rs) => rs.map((r) => r.id === id ? { ...r, estado: "REJEITADA", motivo_rejeicao: motivo, aprovador: "Wagner Ramos", aprovado_em: "20/08/2026 09:12" } : r));
      avisar("Intercorrência rejeitada — o solicitante recebe o motivo.", "warn");
      return;
    }
    setRows((rs) => rs.map((r) => r.id === id ? { ...r, estado: "APROVADA", aprovador: "Wagner Ramos", aprovado_em: "20/08/2026 09:12" } : r));
    avisar("Intercorrência aprovada — a apuração do dia será reprocessada.", "ok");
  };

  return (
    <>
      <div className="pt-toolbar">
        <div className="pt-fld"><label htmlFor="ap-estado">Estado</label>
          <select id="ap-estado" value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todos</option>
            {Object.entries(D.ESTADOS_INTERC).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </div>
        <div className="pt-fld"><label htmlFor="ap-tipo">Tipo</label>
          <select id="ap-tipo" value={tipo} onChange={(e) => setTipo(e.target.value)}>
            <option value="">Todos</option>
            {Object.entries(D.TIPOS_INTERC).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </div>
        <button className="pt-btn" onClick={() => { setEstado(""); setTipo(""); }}>Limpar</button>
        <span className="pt-sp" />
        <span style={{ fontSize: 12, color: "var(--text-dim)" }}>{lista.filter((i) => i.estado === "PENDENTE").length} pendentes no filtro · selecione para decidir em lote</span>
      </div>

      <Card icon="check" titulo="Fila de aprovações" sub={"(" + lista.length + (lista.length === 1 ? " item" : " itens") + ")"}>
        <Tabela cols={[{ l: <input type="checkbox" checked={todasMarcadas} disabled={selecionaveis.length === 0} title="Selecionar os pendentes desta página"
            onChange={(e) => setMarcadas(e.target.checked ? [...new Set([...marcadas, ...selecionaveis])] : marcadas.filter((id) => !selecionaveis.includes(id)))} />, w: "34px" },
          { l: "Colaborador" }, { l: "Tipo" }, { l: "Data / intervalo" }, { l: "Estado" }, { l: "Prioridade" }, { l: "Ação", num: true, w: "196px" }]}>
          {lista.length === 0 && <Vazio icon="check" colSpan={7}>Nenhuma intercorrência encontrada com esse filtro.</Vazio>}
          {pagina.map((a) => {
            const c = D.colab(a.colaborador_config_id);
            const marc = marcadas.includes(a.id);
            return (
              <tr key={a.id} className={marc ? "marcada" : ""}>
                <td className="sel">
                  <input type="checkbox" checked={marc} disabled={a.estado !== "PENDENTE"} onChange={() => toggle(a.id)}
                    title={a.estado === "PENDENTE" ? "Selecionar" : "Só pendentes entram no lote"} />
                </td>
                <td><b>{c.nome}</b><small>{c.matricula} · {c.cargo}</small></td>
                <td>{D.TIPOS_INTERC[a.tipo]}</td>
                <td><span className="mono">{a.data}</span><small>{a.dia_todo ? "Dia todo" : a.intervalo_inicio ? a.intervalo_inicio + " – " + a.intervalo_fim : "—"}</small></td>
                <td><PillIntercorrencia estado={a.estado} /></td>
                <td><PillPrioridade p={a.prioridade} /></td>
                <td className="num">
                  {a.estado === "PENDENTE" ? (
                    <span style={{ display: "inline-flex", gap: 6 }}>
                      <button className="pt-btn primary" onClick={() => decidir(a.id, true)}>Aprovar</button>
                      <button className="pt-btn danger" onClick={() => decidir(a.id, false)}>Rejeitar</button>
                    </span>
                  ) : (
                    <button className="pt-btn" onClick={() => onVerIntercorrencia(a.id)}>Ver</button>
                  )}
                </td>
              </tr>
            );
          })}
        </Tabela>
        <Pager p={pg} rotulo="itens" />
      </Card>
      {marcadas.length > 0 &&
        <div className="pt-bulk">
          <b>{marcadas.length}</b><span className="lbl">{marcadas.length === 1 ? "intercorrência selecionada" : "intercorrências selecionadas"}</span>
          <input className="pt-bulk-motivo" value={motivoLote} onChange={(e) => setMotivoLote(e.target.value)}
            placeholder="Motivo único (obrigatório só para rejeitar)…" />
          <button className="pt-btn primary" onClick={() => decidirLote(true)}><Ic name="check" />Aprovar {marcadas.length}</button>
          <button className="pt-btn danger" onClick={() => decidirLote(false)}>Rejeitar {marcadas.length}</button>
          <button className="pt-btn" onClick={() => { setMarcadas([]); setMotivoLote(""); }}>Limpar seleção</button>
        </div>}
      <Legal />
    </>
  );
}

// ═══════════════════════════ 2. INTERCORRÊNCIAS ═══════════════════════════
function FormIntercorrencia({ registro, onSalvar, onCancelar }) {
  const D = P();
  const { Nota } = U();
  const [f, setF] = useState(() => registro || {
    colaborador_config_id: "", tipo: "", data: "", dia_todo: false, intervalo_inicio: "", intervalo_fim: "",
    justificativa: "", prioridade: "NORMAL", impacta_apuracao: true, descontar_banco_horas: false,
  });
  const set = (k) => (e) => setF((o) => ({ ...o, [k]: e.target.type === "checkbox" ? e.target.checked : e.target.value }));
  const erro = !f.colaborador_config_id ? "Selecione o colaborador."
    : !f.tipo ? "Selecione o tipo da intercorrência."
    : !f.data ? "Informe a data."
    : (f.justificativa || "").trim().length < 10 ? "A justificativa precisa de no mínimo 10 caracteres."
    : null;
  const elegiveis = D.COLABORADORES.filter((c) => c.controla_ponto && !c.desligamento);

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
      <div className="pt-cols">
        <div className="pt-fld"><label htmlFor="ic-colab">Colaborador <span className="pt-req">*</span></label>
          <select id="ic-colab" value={f.colaborador_config_id} onChange={set("colaborador_config_id")}>
            <option value="">Selecione…</option>
            {elegiveis.map((c) => <option key={c.id} value={c.id}>[{c.matricula}] {c.nome}</option>)}
          </select>
        </div>
        <div className="pt-fld"><label htmlFor="ic-tipo">Tipo <span className="pt-req">*</span></label>
          <select id="ic-tipo" value={f.tipo} onChange={set("tipo")}>
            <option value="">Selecione…</option>
            {Object.entries(D.TIPOS_INTERC).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </div>
      </div>
      <div className="pt-cols">
        <div className="pt-fld"><label htmlFor="ic-data">Data <span className="pt-req">*</span></label>
          <input id="ic-data" type="date" max="2026-08-20" value={f.data} onChange={set("data")} />
        </div>
        <label className="pt-check"><input type="checkbox" checked={!!f.dia_todo} onChange={set("dia_todo")} />Dia todo</label>
        <div className="pt-fld"><label htmlFor="ic-ini">Início</label>
          <input id="ic-ini" type="time" value={f.intervalo_inicio || ""} disabled={f.dia_todo} onChange={set("intervalo_inicio")} />
        </div>
        <div className="pt-fld"><label htmlFor="ic-fim">Fim</label>
          <input id="ic-fim" type="time" value={f.intervalo_fim || ""} disabled={f.dia_todo} onChange={set("intervalo_fim")} />
        </div>
      </div>
      <div className="pt-fld wide"><label htmlFor="ic-just">Justificativa <span className="pt-req">*</span></label>
        <textarea id="ic-just" maxLength={2000} value={f.justificativa} onChange={set("justificativa")}
          placeholder="Descreva o motivo da intercorrência (mín. 10 caracteres)…" />
        <small>{(f.justificativa || "").length}/2000 — mínimo 10 caracteres.</small>
      </div>
      <div className="pt-cols">
        <div className="pt-fld"><label htmlFor="ic-prio">Prioridade</label>
          <select id="ic-prio" value={f.prioridade} onChange={set("prioridade")}>
            <option value="NORMAL">Normal</option><option value="URGENTE">Urgente</option>
          </select>
        </div>
        <label className="pt-check"><input type="checkbox" checked={!!f.impacta_apuracao} onChange={set("impacta_apuracao")} />Impacta apuração</label>
        <label className="pt-check"><input type="checkbox" checked={!!f.descontar_banco_horas} onChange={set("descontar_banco_horas")} />Descontar do banco de horas</label>
        <div className="pt-fld"><label htmlFor="ic-anexo">Anexo (PDF, JPG, PNG — máx 5 MB)</label>
          <input id="ic-anexo" type="file" accept=".pdf,.jpg,.jpeg,.png" />
        </div>
      </div>
      <Nota tom="info">A intercorrência nasce como <b>rascunho</b>: nada é aplicado na apuração até você submeter e um aprovador decidir. A marcação original nunca é alterada — a correção entra como lançamento novo (append-only).</Nota>
      <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
        <button className="pt-btn" onClick={onCancelar}>Cancelar</button>
        <button className="pt-btn primary" disabled={!!erro} title={erro || ""} onClick={() => onSalvar(f)}>
          {registro ? "Atualizar" : "Salvar rascunho"}
        </button>
      </div>
    </div>
  );
}

function Intercorrencias({ avisar, foco, onFoco, rows, setRows }) {
  const D = P();
  const { Card, Tabela, Vazio, PillIntercorrencia, PillPrioridade, Nota, usePagina, Pager, Ic } = U();
  const ds = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const pg = usePagina(rows.length, 15);
  const [nova, setNova] = useState(false);
  const [editando, setEditando] = useState(null);
  const sel = rows.find((r) => r.id === foco) || null;

  const salvar = (f) => {
    if (editando) {
      setRows((rs) => rs.map((r) => r.id === editando.id ? { ...r, ...f } : r));
      avisar("Rascunho atualizado.", "ok");
      setEditando(null);
    } else {
      const id = "n" + Date.now().toString(16).slice(-7);
      setRows((rs) => [{ ...f, id, codigo: null, estado: "RASCUNHO", colaborador_config_id: Number(f.colaborador_config_id), data: f.data.split("-").reverse().join("/"), anexo_path: null, solicitante: "Wagner Ramos", created_at: "20/08/2026 09:20", aprovador: null, aprovado_em: null, motivo_rejeicao: null }, ...rs]);
      avisar("Rascunho salvo — submeta para entrar na fila de aprovação.", "ok");
      setNova(false);
    }
  };
  const mudarEstado = (id, estado, msg, tom) => { setRows((rs) => rs.map((r) => r.id === id ? { ...r, estado } : r)); avisar(msg, tom); };

  const Drawer = ds.Drawer, DrawerSection = ds.DrawerSection;
  const detalhe = sel && (() => {
    const c = D.colab(sel.colaborador_config_id);
    const Sec = DrawerSection || (({ title, children }) => <div style={{ padding: "12px 16px" }}>{title && <div style={{ fontSize: 10, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", marginBottom: 6 }}>{title}</div>}{children}</div>);
    const corpo = (
      <>
        <Sec title="Dados da intercorrência">
          <div className="pt-ficha">
            <div>
              <p><b>Colaborador:</b> {c.nome}</p>
              <p><b>Matrícula:</b> {c.matricula}</p>
              <p><b>Tipo:</b> {D.TIPOS_INTERC[sel.tipo]}</p>
              <p><b>Data:</b> {sel.data}</p>
              <p><b>Intervalo:</b> {sel.dia_todo ? "Dia todo" : sel.intervalo_inicio ? sel.intervalo_inicio + " – " + sel.intervalo_fim : "—"}</p>
            </div>
            <div>
              <p><b>Estado:</b> <PillIntercorrencia estado={sel.estado} /></p>
              <p><b>Prioridade:</b> <PillPrioridade p={sel.prioridade} /></p>
              <p><b>Impacta apuração:</b> {sel.impacta_apuracao ? "Sim" : "Não"}</p>
              <p><b>Descontar banco de horas:</b> {sel.descontar_banco_horas ? "Sim" : "Não"}</p>
              <p><b>Anexo:</b> {sel.anexo_path ? sel.anexo_path.split("/").pop() : "—"}</p>
            </div>
          </div>
        </Sec>
        <Sec title="Justificativa">
          <div className="pt-just">{sel.justificativa}</div>
        </Sec>
        <Sec title="Rastreio">
          <div className="pt-ficha">
            <div><p><b>Solicitante:</b> {sel.solicitante}</p><p className="pt-dim">Criada em {sel.created_at}</p></div>
            {sel.aprovador && <div><p><b>Aprovador:</b> {sel.aprovador}</p><p className="pt-dim">Decisão em {sel.aprovado_em}</p></div>}
          </div>
          {sel.motivo_rejeicao && <div style={{ marginTop: 10 }}><Nota tom="danger" titulo="Motivo da rejeição">{sel.motivo_rejeicao}</Nota></div>}
        </Sec>
      </>
    );
    const foot = (
      <>
        {sel.estado === "RASCUNHO" && <>
          <button className="pt-btn" onClick={() => { setEditando(sel); onFoco(null); }}>Editar</button>
          <button className="pt-btn primary" onClick={() => { mudarEstado(sel.id, "PENDENTE", "Submetida — está na fila de aprovações.", "ok"); onFoco(null); }}>Submeter para aprovação</button>
        </>}
        {(sel.estado === "RASCUNHO" || sel.estado === "PENDENTE") &&
          <button className="pt-btn danger" onClick={() => { mudarEstado(sel.id, "CANCELADA", "Intercorrência cancelada.", "warn"); onFoco(null); }}>Cancelar</button>}
      </>
    );
    if (!Drawer) return null;
    return <Drawer open={!!sel} onClose={() => onFoco(null)} width={620}
      title={sel.codigo || sel.id.slice(0, 8)} subtitle={D.TIPOS_INTERC[sel.tipo] + " · " + sel.data} footer={foot}>{corpo}</Drawer>;
  })();

  return (
    <>
      <div className="pt-toolbar">
        <span style={{ fontSize: 12, color: "var(--text-dim)" }}>{rows.length} registros no business · rascunho edita e submete; aprovada não volta atrás.</span>
        <span className="pt-sp" />
        <button className="pt-btn primary" onClick={() => setNova(true)}><Ic name="plus" />Nova intercorrência</button>
      </div>

      {(nova || editando) &&
        <Card icon="plus" titulo={editando ? "Editar rascunho " + (editando.codigo || editando.id.slice(0, 8)) : "Nova intercorrência"}>
          <FormIntercorrencia registro={editando} onSalvar={salvar} onCancelar={() => { setNova(false); setEditando(null); }} />
        </Card>}

      <Card icon="alert" titulo="Intercorrências" sub={"(" + rows.length + (rows.length === 1 ? " item" : " itens") + ")"}>
        <Tabela cols={[{ l: "Código", w: "128px" }, { l: "Colaborador" }, { l: "Tipo" }, { l: "Data" }, { l: "Estado" }, { l: "Prioridade" }, { l: "Ação", num: true, w: "170px" }]}>
          {rows.length === 0 && <Vazio colSpan={7}>Nenhuma intercorrência registrada ainda.</Vazio>}
          {pg.fatia(rows).map((i) => {
            const c = D.colab(i.colaborador_config_id);
            return (
              <tr key={i.id} className="hit" onClick={() => onFoco(i.id)}>
                <td className="mono">{i.codigo || i.id.slice(0, 8)}</td>
                <td><b>{c.nome}</b><small>{c.matricula}</small></td>
                <td>{D.TIPOS_INTERC[i.tipo]}</td>
                <td><span className="mono">{i.data}</span><small>{i.dia_todo ? "Dia todo" : (i.intervalo_inicio || "—") + "–" + (i.intervalo_fim || "—")}</small></td>
                <td><PillIntercorrencia estado={i.estado} /></td>
                <td><PillPrioridade p={i.prioridade} /></td>
                <td className="num" onClick={(e) => e.stopPropagation()}>
                  <span style={{ display: "inline-flex", gap: 6 }}>
                    <button className="pt-btn" onClick={() => onFoco(i.id)}>Ver</button>
                    {i.estado === "RASCUNHO" && <>
                      <button className="pt-btn" onClick={() => setEditando(i)}>Editar</button>
                      <button className="pt-btn primary" onClick={() => mudarEstado(i.id, "PENDENTE", "Submetida para aprovação.", "ok")}>Submeter</button>
                    </>}
                  </span>
                </td>
              </tr>
            );
          })}
        </Tabela>
        <Pager p={pg} rotulo="itens" />
      </Card>
      {detalhe}
    </>
  );
}

// ═══════════════════════════ 3. BANCO DE HORAS ═══════════════════════════
function BancoHoras({ avisar }) {
  const D = P();
  const { Card, Kpi, Tabela, Vazio, Nota, Legal, Pill, Voltar, Min, usePagina, Pager, Ic } = U();
  const [saldos, setSaldos] = useState(D.BH_SALDOS);
  const [movs, setMovs] = useState(D.BH_MOVIMENTOS);
  const [sel, setSel] = useState(null);
  const [minutos, setMinutos] = useState("");
  const [obs, setObs] = useState("");
  const pg = usePagina(saldos.length, 15);

  const totais = useMemo(() => ({
    credito: saldos.filter((s) => s.saldo_minutos > 0).reduce((a, s) => a + s.saldo_minutos, 0),
    debito: saldos.filter((s) => s.saldo_minutos < 0).reduce((a, s) => a + s.saldo_minutos, 0),
    comCredito: saldos.filter((s) => s.saldo_minutos > 0).length,
    comDebito: saldos.filter((s) => s.saldo_minutos < 0).length,
  }), [saldos]);

  if (sel) {
    const s = saldos.find((x) => x.colaborador_config_id === sel);
    const c = D.colab(sel);
    const lista = movs[sel] || [];
    const tomSaldo = s.saldo_minutos > 0 ? "ok" : s.saldo_minutos < 0 ? "neg" : "";
    const registrar = () => {
      const m = parseInt(minutos, 10);
      if (!m || !obs.trim()) { avisar("Minutos e observação são obrigatórios no ajuste manual.", "warn"); return; }
      setMovs((o) => ({ ...o, [sel]: [{ created_at: "20/08/2026 09:24", data_referencia: "20/08/2026", origem: "AJUSTE_MANUAL", minutos: m, observacao: obs.trim() }, ...(o[sel] || [])] }));
      setSaldos((ss) => ss.map((x) => x.colaborador_config_id === sel ? { ...x, saldo_minutos: x.saldo_minutos + m, updated_at: "20/08/2026 09:24" } : x));
      setMinutos(""); setObs("");
      avisar("Ajuste registrado no ledger — lançamento imutável.", "ok");
    };
    return (
      <>
        <div className="pt-sub">
          <Voltar onClick={() => setSel(null)}>Voltar aos saldos</Voltar>
          <div><h2>{c.nome}</h2><span className="pt-sub-sub">{c.matricula} · {c.cargo} · escala {D.escala(c.escala_atual_id)?.nome || "—"}</span></div>
        </div>
        <div className="pt-cols-2">
          <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <div className="pt-kpis">
              <Kpi label="Saldo atual" valor={D.fmtMin(s.saldo_minutos)} tom={tomSaldo} ln={"atualizado " + s.updated_at} />
              <Kpi label="Lançamentos" valor={lista.length} ln="append-only" />
              <Kpi label="Teto do acordo" valor={D.CONFIG.banco_horas.saldo_maximo_horas + "h"} ln={"piso " + D.CONFIG.banco_horas.saldo_minimo_horas + "h"} />
              <Kpi label="Prazo de compensação" valor={D.CONFIG.banco_horas.prazo_compensacao_meses + " meses"} ln="acordo individual" />
            </div>
            <Card icon="list" titulo="Histórico de movimentos" sub={"(" + lista.length + " lançamentos)"}>
              <Tabela cols={[{ l: "Data" }, { l: "Referência" }, { l: "Origem" }, { l: "Minutos", num: true }, { l: "Observação" }]}>
                {lista.length === 0 && <Vazio colSpan={5}>Nenhuma movimentação registrada.</Vazio>}
                {lista.map((m, i) => (
                  <tr key={i}>
                    <td className="mono">{m.created_at}</td>
                    <td className="mono">{m.data_referencia || "—"}</td>
                    <td><Pill tom="neutral" mono>{m.origem}</Pill></td>
                    <td className="num"><Min v={m.minutos} sinal /></td>
                    <td><small style={{ color: "var(--text-dim)" }}>{m.observacao}</small></td>
                  </tr>
                ))}
              </Tabela>
            </Card>
          </div>
          <Card icon="settings" titulo="Ajuste manual" sub="— registra lançamento no ledger (imutável)">
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              <div className="pt-fld"><label htmlFor="bh-min">Minutos <span className="pt-req">*</span></label>
                <input id="bh-min" type="number" value={minutos} onChange={(e) => setMinutos(e.target.value)} placeholder="Use negativo para débito" />
                <small>Ex.: 60 (crédito 1h), −30 (débito 30 min).</small>
              </div>
              <div className="pt-fld"><label htmlFor="bh-obs">Observação <span className="pt-req">*</span></label>
                <textarea id="bh-obs" maxLength={500} value={obs} onChange={(e) => setObs(e.target.value)} placeholder="Motivo do ajuste (obrigatório)…" />
              </div>
              <button className="pt-btn primary" onClick={registrar}><Ic name="check" />Registrar ajuste</button>
              <Nota tom="warn">O ajuste não apaga nem edita movimento anterior: entra como lançamento novo com o seu nome. É assim que a auditoria reconstrói o saldo.</Nota>
            </div>
          </Card>
        </div>
        <Legal>Movimentos de banco de horas são append-only e imutáveis (Portaria MTP 671/2021).</Legal>
      </>
    );
  }

  return (
    <>
      <div className="pt-kpis">
        <Kpi label="Crédito total" valor={D.fmtMin(totais.credito)} tom="ok" ln={totais.comCredito + " colaboradores com crédito"} />
        <Kpi label="Débito total" valor={D.fmtMin(totais.debito)} tom="neg" ln={totais.comDebito + " colaboradores com débito"} />
        <Kpi label="Colaboradores no banco" valor={saldos.length} ln="com escala que permite acúmulo" />
        <Kpi label="Multiplicadores" valor={D.CONFIG.banco_horas.multiplicador_credito + "x / " + D.CONFIG.banco_horas.multiplicador_debito + "x"} ln="crédito / débito" />
      </div>
      <Card icon="coins" titulo="Saldos por colaborador" sub={"(" + saldos.length + " registros)"}>
        <Tabela cols={[{ l: "Colaborador" }, { l: "Matrícula" }, { l: "Escala" }, { l: "Saldo", num: true }, { l: "Última movimentação" }, { l: "Ação", num: true, w: "108px" }]}>
          {saldos.length === 0 && <Vazio colSpan={6}>Nenhum saldo registrado ainda.</Vazio>}
          {pg.fatia(saldos).map((s) => {
            const c = D.colab(s.colaborador_config_id);
            return (
              <tr key={s.colaborador_config_id} className="hit" onClick={() => setSel(s.colaborador_config_id)}>
                <td><b>{c.nome}</b><small>{c.cargo}</small></td>
                <td className="mono">{c.matricula}</td>
                <td>{D.escala(c.escala_atual_id)?.nome || <span className="pt-dim">—</span>}</td>
                <td className="num"><b><Min v={s.saldo_minutos} /></b></td>
                <td className="mono">{s.updated_at}</td>
                <td className="num" onClick={(e) => e.stopPropagation()}><button className="pt-btn" onClick={() => setSel(s.colaborador_config_id)}>Detalhes</button></td>
              </tr>
            );
          })}
        </Tabela>
        <Pager p={pg} rotulo="saldos" />
      </Card>
      <Legal>Movimentos de banco de horas são append-only e imutáveis (Portaria MTP 671/2021).</Legal>
    </>
  );
}

// ═══════════════════════════ 4. ESCALAS ═══════════════════════════
function Escalas({ avisar }) {
  const D = P();
  const { Card, Tabela, Vazio, Pill, PillSimNao, Nota, usePagina, Pager, Ic } = U();
  const [rows, setRows] = useState(D.ESCALAS);
  const [form, setForm] = useState(null); // {escala|null}
  const pg = usePagina(rows.length, 15);

  const salvar = (f) => {
    if (f.id) { setRows((rs) => rs.map((r) => r.id === f.id ? { ...r, ...f } : r)); avisar("Escala atualizada.", "ok"); }
    else { setRows((rs) => [...rs, { ...f, id: Date.now(), turnos: [] }]); avisar("Escala criada — vincule colaboradores na tela de Colaboradores.", "ok"); }
    setForm(null);
  };

  if (form) return <EscalaForm escala={form.escala} onSalvar={salvar} onCancelar={() => setForm(null)} />;

  return (
    <>
      <div className="pt-toolbar">
        <span style={{ fontSize: 12, color: "var(--text-dim)" }}>Carga diária e semanal em minutos — 480 = 8h, 2.640 = 44h (CLT padrão).</span>
        <span className="pt-sp" />
        <button className="pt-btn primary" onClick={() => setForm({ escala: null })}><Ic name="plus" />Nova escala</button>
      </div>
      <Card icon="calendar" titulo="Escalas cadastradas" sub={"(" + rows.length + " no business)"}>
        <Tabela cols={[{ l: "Código", w: "110px" }, { l: "Nome" }, { l: "Tipo" }, { l: "Carga diária", num: true }, { l: "Carga semanal", num: true }, { l: "Turnos", num: true }, { l: "Banco de horas" }, { l: "Ação", num: true, w: "150px" }]}>
          {rows.length === 0 && <Vazio icon="calendar" colSpan={8}>Nenhuma escala cadastrada.</Vazio>}
          {pg.fatia(rows).map((e) => (
            <tr key={e.id}>
              <td className="mono">{e.codigo || "—"}</td>
              <td><b>{e.nome}</b><small>{(e.turnos[0] && e.turnos[0].entrada + "–" + e.turnos[0].saida) || "sem turno configurado"}</small></td>
              <td><Pill tom="info">{D.TIPOS_ESCALA[e.tipo] || e.tipo}</Pill></td>
              <td className="num">{D.fmtMin(e.carga_diaria_minutos)}</td>
              <td className="num">{D.fmtMin(e.carga_semanal_minutos)}</td>
              <td className="num">{e.turnos.length}</td>
              <td><PillSimNao v={e.permite_banco_horas} sim="Permite" /></td>
              <td className="num">
                <span style={{ display: "inline-flex", gap: 6 }}>
                  <button className="pt-btn" onClick={() => setForm({ escala: e })}>Editar</button>
                  <button className="pt-btn danger" onClick={() => { if (window.confirm("Remover esta escala? Colaboradores vinculados perderão a referência.")) { setRows((rs) => rs.filter((r) => r.id !== e.id)); avisar("Escala removida.", "warn"); } }}>Remover</button>
                </span>
              </td>
            </tr>
          ))}
        </Tabela>
        <Pager p={pg} rotulo="escalas" />
      </Card>
      <Nota tom="info">A gestão detalhada de turnos por dia da semana (entrada, saída para almoço, retorno, saída) é leitura aqui e edição em fase posterior — igual ao Blade de origem.</Nota>
    </>
  );
}

function EscalaForm({ escala, onSalvar, onCancelar }) {
  const D = P();
  const { Card, Tabela, Nota, Voltar } = U();
  const [f, setF] = useState(() => escala || { nome: "", codigo: "", tipo: "FIXA", carga_diaria_minutos: 480, carga_semanal_minutos: 2640, permite_banco_horas: true });
  const set = (k) => (e) => setF((o) => ({ ...o, [k]: e.target.type === "checkbox" ? e.target.checked : e.target.type === "number" ? Number(e.target.value) : e.target.value }));
  const erro = !f.nome.trim() ? "O nome da escala é obrigatório."
    : f.carga_diaria_minutos < 60 || f.carga_diaria_minutos > 600 ? "Carga diária deve ficar entre 60 e 600 minutos."
    : f.carga_semanal_minutos < 0 || f.carga_semanal_minutos > 3600 ? "Carga semanal deve ficar entre 0 e 3.600 minutos." : null;
  return (
    <>
      <div className="pt-sub"><Voltar onClick={onCancelar}>Voltar às escalas</Voltar>
        <div><h2>{escala ? "Editar escala" : "Nova escala"}</h2><span className="pt-sub-sub">{escala ? escala.nome : "cadastro de jornada padrão do business"}</span></div></div>
      <Card icon="calendar" titulo={escala ? escala.nome : "Dados da escala"}>
        <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
          <div className="pt-cols">
            <div className="pt-fld wide"><label htmlFor="es-nome">Nome <span className="pt-req">*</span></label>
              <input id="es-nome" maxLength={120} value={f.nome} onChange={set("nome")} /></div>
            <div className="pt-fld"><label htmlFor="es-cod">Código</label>
              <input id="es-cod" maxLength={30} value={f.codigo || ""} onChange={set("codigo")} /></div>
            <div className="pt-fld"><label htmlFor="es-tipo">Tipo <span className="pt-req">*</span></label>
              <select id="es-tipo" value={f.tipo} onChange={set("tipo")}>
                {Object.entries(D.TIPOS_ESCALA).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select></div>
          </div>
          <div className="pt-cols">
            <div className="pt-fld"><label htmlFor="es-cd">Carga diária (minutos) <span className="pt-req">*</span></label>
              <input id="es-cd" type="number" min={60} max={600} value={f.carga_diaria_minutos} onChange={set("carga_diaria_minutos")} />
              <small>{D.fmtMin(f.carga_diaria_minutos)} por dia. Entre 60 e 600.</small></div>
            <div className="pt-fld"><label htmlFor="es-cs">Carga semanal (minutos) <span className="pt-req">*</span></label>
              <input id="es-cs" type="number" min={0} max={3600} value={f.carga_semanal_minutos} onChange={set("carga_semanal_minutos")} />
              <small>{D.fmtMin(f.carga_semanal_minutos)} por semana. 2.640 = 44h (CLT padrão).</small></div>
            <label className="pt-check"><input type="checkbox" checked={!!f.permite_banco_horas} onChange={set("permite_banco_horas")} />Permite acúmulo em banco de horas</label>
          </div>
          {escala && escala.turnos.length > 0 &&
            <div>
              <div style={{ fontSize: 10, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", margin: "4px 0 6px" }}>Turnos configurados</div>
              <Tabela cols={[{ l: "Dia da semana" }, { l: "Entrada" }, { l: "Saída almoço" }, { l: "Retorno almoço" }, { l: "Saída" }]}>
                {escala.turnos.map((t, i) => (
                  <tr key={i}><td>{t.dia_semana}</td><td className="mono">{t.entrada}</td><td className="mono">{t.saida_almoco}</td><td className="mono">{t.retorno_almoco}</td><td className="mono">{t.saida}</td></tr>
                ))}
              </Tabela>
            </div>}
          <Nota tom="info">Interjornada mínima de {D.CONFIG.clt.interjornada_minima_horas}h (Art. 66 CLT) e intrajornada de {D.CONFIG.clt.intrajornada_minima_minutos} min (Art. 71) são validadas na apuração, não aqui.</Nota>
          <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
            <button className="pt-btn" onClick={onCancelar}>Cancelar</button>
            <button className="pt-btn primary" disabled={!!erro} title={erro || ""} onClick={() => onSalvar(f)}>{escala ? "Atualizar" : "Criar escala"}</button>
          </div>
        </div>
      </Card>
    </>
  );
}

// ═══════════════════════════ 5. COLABORADORES ═══════════════════════════
function Colaboradores({ avisar, onVerEspelho }) {
  const D = P();
  const { Card, Tabela, Vazio, Pill, PillSimNao, Nota, usePagina, Pager, Ic } = U();
  const [rows, setRows] = useState(D.COLABORADORES);
  const [q, setQ] = useState("");
  const [edit, setEdit] = useState(null);

  const busca = q.trim().toLowerCase();
  const [escala, setEscala] = useState("");
  const [status, setStatus] = useState("ativos");
  const lista = rows.filter((c) => {
    if (busca && !(c.nome + " " + c.matricula + " " + (c.cpf || "")).toLowerCase().includes(busca)) return false;
    if (escala && String(c.escala_atual_id) !== escala) return false;
    if (status === "ativos" && c.desligamento) return false;
    if (status === "desligados" && !c.desligamento) return false;
    if (status === "ponto" && !c.controla_ponto) return false;
    if (status === "sem-pis" && c.pis) return false;
    return true;
  });
  const pg = usePagina(lista.length, 15);
  const ultimoPonto = (id) => {
    const dias = D.dias(D.MES, id).filter((d) => d.marcacoes.length);
    const d = dias[dias.length - 1];
    if (!d) return null;
    const m = d.marcacoes[d.marcacoes.length - 1];
    return String(d.dia).padStart(2, "0") + "/08 " + m.hora;
  };

  if (edit) {
    const c = rows.find((x) => x.id === edit) || rows[0];
    return <ColaboradorForm colaborador={c} onCancelar={() => setEdit(null)}
      onSalvar={(f) => { setRows((rs) => rs.map((r) => r.id === c.id ? { ...r, ...f } : r)); setEdit(null); avisar("Configuração de ponto salva.", "ok"); }} />;
  }

  return (
    <>
      <div className="pt-toolbar">
        <div className="pt-fld wide"><label htmlFor="cl-q">Buscar</label>
          <input id="cl-q" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nome, matrícula ou CPF" /></div>
        {q && <button className="pt-btn" onClick={() => setQ("")}>Limpar</button>}
        <div className="pt-fld"><label htmlFor="cl-esc">Escala</label>
          <select id="cl-esc" value={escala} onChange={(e) => setEscala(e.target.value)}>
            <option value="">Todas</option>
            {D.ESCALAS.map((e) => <option key={e.id} value={e.id}>{e.nome}</option>)}
          </select></div>
        <div className="pt-fld"><label htmlFor="cl-st">Situação</label>
          <select id="cl-st" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="ativos">Ativos</option>
            <option value="ponto">Só quem controla ponto</option>
            <option value="sem-pis">Sem PIS cadastrado</option>
            <option value="desligados">Desligados</option>
            <option value="todos">Todos</option>
          </select></div>
        <span className="pt-sp" />
        <span style={{ fontSize: 12, color: "var(--text-dim)" }}>{lista.length} de {rows.length} colaboradores</span>
      </div>
      <Card icon="database" titulo="Colaboradores" sub={"(" + lista.length + " encontrados)"}>
        <Tabela cols={[{ l: "Matrícula", w: "92px" }, { l: "Nome" }, { l: "CPF / PIS" }, { l: "Escala" }, { l: "Último ponto" }, { l: "Saldo BH", num: true }, { l: "Controla ponto" }, { l: "Banco de horas" }, { l: "Ação", num: true, w: "180px" }]}>
          {lista.length === 0 && <Vazio icon="search" colSpan={9}>{busca ? <>Nenhum colaborador encontrado para “{q}”.</> : "Nenhum colaborador com esse filtro."}</Vazio>}
          {pg.fatia(lista).map((c) => {
            const bh = (D.BH_SALDOS.find((s) => s.colaborador_config_id === c.id) || {}).saldo_minutos;
            const up = c.controla_ponto ? ultimoPonto(c.id) : null;
            return (
            <tr key={c.id} className={c.desligamento ? "folga" : ""}>
              <td className="mono">{c.matricula || "—"}</td>
              <td><b>{c.nome}</b><small>{c.email || "sem e-mail"} · {c.cargo}</small></td>
              <td className="mono">{c.cpf || "—"}<small>{c.pis ? "PIS " + c.pis : <span className="pt-warnt">PIS não cadastrado</span>}</small></td>
              <td>{c.escala_atual_id ? <Pill tom="info">{D.escala(c.escala_atual_id).nome}</Pill> : <span className="pt-dim">—</span>}</td>
              <td className="mono">{up || <span className="pt-dim">—</span>}</td>
              <td className="num">{bh == null ? <span className="pt-dim">—</span> : <span className={bh > 0 ? "pt-pos" : bh < 0 ? "pt-neg" : "pt-dim"}>{D.fmtMin(bh)}</span>}</td>
              <td><PillSimNao v={c.controla_ponto} /></td>
              <td><PillSimNao v={c.usa_banco_horas} /></td>
              <td className="num">
                <span style={{ display: "inline-flex", gap: 6 }}>
                  {c.controla_ponto && <button className="pt-btn" onClick={() => onVerEspelho(c.id)}>Espelho</button>}
                  <button className="pt-btn" onClick={() => setEdit(c.id)}>Configurar</button>
                </span>
              </td>
            </tr>
            );
          })}
        </Tabela>
        <Pager p={pg} rotulo="colaboradores" />
      </Card>
      <Nota tom="info" titulo="Sobre a vinculação">
        Os colaboradores são mantidos pelo módulo <b>Essentials/HRM</b> do UltimatePOS. Aqui você configura só os parâmetros de ponto (matrícula, CPF/PIS, escala, flags). Nome e e-mail se editam na tela de funcionários do HRM.
      </Nota>
    </>
  );
}

function ColaboradorForm({ colaborador, onSalvar, onCancelar }) {
  const D = P();
  const { Card, Nota, Voltar } = U();
  const c = colaborador;
  const [f, setF] = useState({ matricula: c.matricula || "", cpf: c.cpf || "", pis: c.pis || "", escala_atual_id: c.escala_atual_id || "", admissao: c.admissao, desligamento: c.desligamento || "", controla_ponto: c.controla_ponto, usa_banco_horas: c.usa_banco_horas });
  const set = (k) => (e) => setF((o) => ({ ...o, [k]: e.target.type === "checkbox" ? e.target.checked : e.target.value }));
  return (
    <>
      <div className="pt-sub"><Voltar onClick={onCancelar}>Voltar aos colaboradores</Voltar>
        <div><h2>{c.nome}</h2><span className="pt-sub-sub">{c.cargo} · matrícula {c.matricula || "—"}</span></div></div>
      <div className="pt-cols-2">
        <Card icon="settings" titulo="Configuração de ponto">
          <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
            <div className="pt-cols">
              <div className="pt-fld"><label htmlFor="cf-mat">Matrícula</label><input id="cf-mat" maxLength={30} value={f.matricula} onChange={set("matricula")} /></div>
              <div className="pt-fld"><label htmlFor="cf-cpf">CPF</label><input id="cf-cpf" maxLength={14} placeholder="000.000.000-00" value={f.cpf} onChange={set("cpf")} /></div>
              <div className="pt-fld"><label htmlFor="cf-pis">PIS</label><input id="cf-pis" maxLength={14} value={f.pis} onChange={set("pis")} />
                <small>Sem PIS, a marcação do AFD é rejeitada na importação.</small></div>
            </div>
            <div className="pt-fld wide"><label htmlFor="cf-esc">Escala atual</label>
              <select id="cf-esc" value={f.escala_atual_id} onChange={set("escala_atual_id")}>
                <option value="">— Sem escala vinculada —</option>
                {D.ESCALAS.map((e) => <option key={e.id} value={e.id}>{e.nome} ({D.TIPOS_ESCALA[e.tipo]})</option>)}
              </select></div>
            <div className="pt-cols">
              <div className="pt-fld"><label htmlFor="cf-adm">Admissão <span className="pt-req">*</span></label><input id="cf-adm" value={f.admissao} onChange={set("admissao")} /></div>
              <div className="pt-fld"><label htmlFor="cf-des">Desligamento</label><input id="cf-des" value={f.desligamento} onChange={set("desligamento")} placeholder="dd/mm/aaaa" />
                <small>Deixar em branco se ativo.</small></div>
            </div>
            <div className="pt-cols">
              <label className="pt-check"><input type="checkbox" checked={!!f.controla_ponto} onChange={set("controla_ponto")} /><b>Controla ponto</b> — registra marcações</label>
              <label className="pt-check"><input type="checkbox" checked={!!f.usa_banco_horas} onChange={set("usa_banco_horas")} /><b>Usa banco de horas</b></label>
            </div>
            <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
              <button className="pt-btn" onClick={onCancelar}>Cancelar</button>
              <button className="pt-btn primary" onClick={() => onSalvar(f)}>Salvar configuração</button>
            </div>
          </div>
        </Card>
        <Card icon="database" titulo="Dados do HRM">
          <div className="pt-ficha" style={{ gridTemplateColumns: "1fr" }}>
            <div>
              <p><b>Nome:</b> {c.nome}</p>
              <p><b>E-mail:</b> {c.email || "—"}</p>
              <p><b>Cargo:</b> {c.cargo}</p>
              <p><b>ID HRM:</b> <span className="mono">{c.user_id}</span></p>
            </div>
          </div>
          <div style={{ marginTop: 12 }}>
            <Nota tom="info">Estes dados vêm do Essentials/HRM. Para alterar, vá em <b>Funcionários</b> no menu do UltimatePOS.</Nota>
          </div>
        </Card>
      </div>
    </>
  );
}

// ═══════════════════════════ 6. IMPORTAÇÕES ═══════════════════════════
function Importacoes({ avisar }) {
  const D = P();
  const { Card, Kpi, Tabela, Vazio, Nota, PillImportacao, Pill, Voltar, usePagina, Pager, Ic } = U();
  const [rows, setRows] = useState(D.IMPORTACOES);
  const [sel, setSel] = useState(null);
  const [nova, setNova] = useState(false);
  const [tipo, setTipo] = useState("AFD");
  const pg = usePagina(rows.length, 15);
  const fmtBytes = (b) => b < 1024 ? b + " B" : b < 1048576 ? (b / 1024).toFixed(1) + " KB" : (b / 1048576).toFixed(1) + " MB";

  if (sel) {
    const imp = rows.find((r) => r.id === sel);
    const pct = imp.linhas_total ? Math.round(imp.linhas_processadas / imp.linhas_total * 100) : 0;
    return (
      <>
        <div className="pt-sub">
          <Voltar onClick={() => setSel(null)}>Voltar às importações</Voltar>
          <div><h2>Importação #{imp.id}</h2><span className="pt-sub-sub">{imp.nome_arquivo}</span></div>
          <span className="pt-sp" />
          <button className="pt-btn" onClick={() => avisar("Download do arquivo original — fora deste protótipo.")}><Ic name="download" />Baixar original</button>
        </div>
        <div className="pt-cols-2">
          <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <Card icon="receipt" titulo="Dados do arquivo">
              <div className="pt-ficha">
                <div>
                  <p><b>ID:</b> <span className="mono">#{imp.id}</span></p>
                  <p><b>Nome:</b> {imp.nome_arquivo}</p>
                  <p><b>Tipo:</b> <Pill tom="info" mono>{imp.tipo}</Pill></p>
                  <p><b>Tamanho:</b> {fmtBytes(imp.tamanho_bytes)}</p>
                </div>
                <div>
                  <p><b>Estado:</b> <PillImportacao estado={imp.estado} /></p>
                  <p><b>Usuário:</b> {imp.usuario}</p>
                  <p><b>Importado em:</b> {imp.created_at}</p>
                  <p><b>Iniciado em:</b> {imp.iniciado_em || "—"}</p>
                  <p><b>Concluído em:</b> {imp.concluido_em || "—"}</p>
                </div>
              </div>
              <div style={{ marginTop: 12 }}>
                <div style={{ fontSize: 10, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", marginBottom: 4 }}>Hash SHA-256</div>
                <div className="pt-hash">{imp.hash_arquivo}</div>
              </div>
            </Card>
            {imp.log && <Card icon="list" titulo="Diagnóstico do processamento"><pre className="pt-log">{imp.log}</pre></Card>}
            {imp.erros_amostra.length > 0 &&
              <Card icon="alert" titulo="Amostra de erros" sub={"(" + imp.erros_amostra.length + " primeiros)"}>
                <Tabela cols={[{ l: "Linha", w: "80px" }, { l: "NSR", w: "96px" }, { l: "Tipo", w: "60px" }, { l: "Mensagem" }]}>
                  {imp.erros_amostra.map((e, i) => (
                    <tr key={i}><td className="mono">{e.linha}</td><td className="mono">{e.nsr}</td><td className="mono">{e.tipo}</td><td><small style={{ color: "var(--text-dim)" }}>{e.erro}</small></td></tr>
                  ))}
                </Tabela>
              </Card>}
          </div>
          <Card icon="chart" titulo="Resumo do processamento">
            <div className="pt-kpis" style={{ gridTemplateColumns: "1fr 1fr" }}>
              <Kpi label="Linhas totais" valor={imp.linhas_total.toLocaleString("pt-BR")} />
              <Kpi label="Processadas" valor={imp.linhas_processadas.toLocaleString("pt-BR")} />
              <Kpi label="Marcações criadas" valor={imp.linhas_sucesso.toLocaleString("pt-BR")} tom="ok" />
              <Kpi label="Erros" valor={imp.linhas_erro} tom={imp.linhas_erro ? "neg" : ""} />
            </div>
            {imp.linhas_total > 0 &&
              <div style={{ marginTop: 12 }}>
                <div className="pt-bar"><i style={{ width: pct + "%" }} /></div>
                <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 5, fontFamily: "var(--mono)" }}>{pct}% processado</div>
              </div>}
            <div style={{ marginTop: 12 }}>
              <Nota tom="info">Encoding esperado <b>{D.CONFIG.afd.encoding}</b>, até {D.CONFIG.afd.max_filesize_mb} MB, chunk de {D.CONFIG.afd.chunk_size_linhas.toLocaleString("pt-BR")} linhas.</Nota>
            </div>
          </Card>
        </div>
      </>
    );
  }

  return (
    <>
      <div className="pt-toolbar">
        <span style={{ fontSize: 12, color: "var(--text-dim)" }}>Arquivos duplicados (mesmo hash SHA-256) são rejeitados automaticamente.</span>
        <span className="pt-sp" />
        <button className="pt-btn primary" onClick={() => setNova((v) => !v)}><Ic name="download" />Nova importação AFD</button>
      </div>

      {nova &&
        <Card icon="download" titulo="Upload do arquivo" sub="— Portaria MTP 671/2021">
          <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
            <div className="pt-cols">
              <div className="pt-fld"><label htmlFor="im-tipo">Tipo de arquivo <span className="pt-req">*</span></label>
                <select id="im-tipo" value={tipo} onChange={(e) => setTipo(e.target.value)}>
                  <option value="AFD">AFD — Arquivo Fonte de Dados</option>
                  <option value="AFDT">AFDT — Arquivo Fonte de Dados Tratados</option>
                </select></div>
              <div className="pt-fld wide"><label htmlFor="im-arq">Arquivo <span className="pt-req">*</span></label>
                <input id="im-arq" type="file" />
                <small>Formato texto conforme layout Portaria 671/2021.</small></div>
            </div>
            <Nota tom="info" titulo="Processamento assíncrono">
              O arquivo entra na fila em <b>ProcessarImportacaoAfdJob</b> e é processado em segundo plano — você acompanha o estado na tela de detalhes.
            </Nota>
            <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
              <button className="pt-btn" onClick={() => setNova(false)}>Cancelar</button>
              <button className="pt-btn primary" onClick={() => {
                const id = Math.max(...rows.map((r) => r.id)) + 1;
                setRows((rs) => [{ id, nome_arquivo: "AFD_00000000000191_20260820.txt", tipo, tamanho_bytes: 1490233, estado: "PENDENTE", usuario: "Wagner Ramos", created_at: "20/08/2026 09:26", iniciado_em: null, concluido_em: null, hash_arquivo: "novo-hash-calculado-no-upload", linhas_total: 0, linhas_processadas: 0, linhas_sucesso: 0, linhas_erro: 0, log: "Enfileirado — aguardando worker.", erros_amostra: [] }, ...rs]);
                setNova(false); avisar("Arquivo enfileirado para processamento.", "ok");
              }}>Enviar para processamento</button>
            </div>
          </div>
        </Card>}

      <Card icon="download" titulo="Histórico de importações" sub={"(" + rows.length + " arquivos)"}>
        <Tabela cols={[{ l: "ID", w: "62px" }, { l: "Arquivo" }, { l: "Tipo", w: "70px" }, { l: "Tamanho", num: true }, { l: "Estado" }, { l: "Linhas", num: true }, { l: "Usuário" }, { l: "Importado em" }, { l: "Ação", num: true, w: "94px" }]}>
          {rows.length === 0 && <Vazio colSpan={9}>Nenhuma importação AFD realizada ainda.</Vazio>}
          {pg.fatia(rows).map((imp) => (
            <tr key={imp.id} className="hit" onClick={() => setSel(imp.id)}>
              <td className="mono">#{imp.id}</td>
              <td><b style={{ fontFamily: "var(--mono)", fontSize: 11.5 }}>{imp.nome_arquivo}</b><small>{imp.linhas_erro > 0 ? imp.linhas_erro + " linhas com erro" : "sem erros"}</small></td>
              <td><Pill tom="info" mono>{imp.tipo}</Pill></td>
              <td className="num">{fmtBytes(imp.tamanho_bytes)}</td>
              <td><PillImportacao estado={imp.estado} /></td>
              <td className="num">{imp.linhas_processadas.toLocaleString("pt-BR")}<small>de {imp.linhas_total.toLocaleString("pt-BR")}</small></td>
              <td>{imp.usuario}</td>
              <td className="mono">{imp.created_at}</td>
              <td className="num" onClick={(e) => e.stopPropagation()}><button className="pt-btn" onClick={() => setSel(imp.id)}>Ver</button></td>
            </tr>
          ))}
        </Tabela>
        <Pager p={pg} rotulo="arquivos" />
      </Card>
    </>
  );
}

// ═══════════════════════════ 7. RELATÓRIOS ═══════════════════════════
function Relatorios({ avisar }) {
  const D = P();
  const { Card, Nota, Legal, Pill, Tabela, Vazio, Ic } = U();
  const [alvo, setAlvo] = useState(null); // relatório escolhido → wizard de filtros
  const [f, setF] = useState({ mes: D.MES, colaborador: "", formato: "pdf", incluir_anuladas: false });
  const set = (k) => (e) => setF((o) => ({ ...o, [k]: e.target.type === "checkbox" ? e.target.checked : e.target.value }));
  const [fila, setFila] = useState([]);

  const gerar = () => {
    const comp = D.comp(f.mes);
    const quem = f.colaborador ? D.colab(Number(f.colaborador)).nome : "todos os colaboradores";
    const legal = ["afd", "afdt", "aej"].includes(alvo.chave);
    setFila((q) => [{ id: Date.now(), chave: alvo.chave, titulo: alvo.titulo, comp: comp.extenso, quem, formato: legal ? "txt" : f.formato, estado: alvo.disponivel ? "GERADO" : "NAO_IMPLEMENTADO" }, ...q]);
    avisar(alvo.disponivel
      ? alvo.titulo + " gerado para " + comp.extenso + " (" + quem + ")."
      : alvo.titulo + " ainda não tem geração em ReportService — o pedido fica registrado.", alvo.disponivel ? "ok" : "warn");
    setAlvo(null);
  };

  return (
    <>
      <Nota tom="info" titulo="Relatórios disponíveis">
        Escolha o tipo, confira os filtros (competência, colaborador, formato) e gere. AFD/AFDT/AEJ saem em texto conforme <b>Portaria MTP 671/2021 Anexo I</b>; espelho e gerenciais saem em PDF/CSV.
      </Nota>

      {alvo &&
        <Card icon="settings" titulo={"Gerar: " + alvo.titulo} sub={alvo.descricao}>
          <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
            <div className="pt-cols">
              <div className="pt-fld"><label htmlFor="rl-mes">Competência <span className="pt-req">*</span></label>
                <select id="rl-mes" value={f.mes} onChange={set("mes")}>
                  {D.MESES.map((m) => <option key={m.key} value={m.key}>{m.extenso}</option>)}
                </select></div>
              <div className="pt-fld wide"><label htmlFor="rl-colab">Colaborador</label>
                <select id="rl-colab" value={f.colaborador} onChange={set("colaborador")}>
                  <option value="">Todos que controlam ponto</option>
                  {D.COLABORADORES.filter((c) => c.controla_ponto).map((c) => <option key={c.id} value={c.id}>[{c.matricula}] {c.nome}</option>)}
                </select></div>
              <div className="pt-fld"><label htmlFor="rl-fmt">Formato</label>
                {["afd", "afdt", "aej"].includes(alvo.chave)
                  ? <><input id="rl-fmt" value="TXT (posicional)" readOnly />
                      <small>Arquivo legal: texto {D.CONFIG.afd.encoding}, formato fixo do Anexo I.</small></>
                  : <><select id="rl-fmt" value={f.formato} onChange={set("formato")}>
                      <option value="pdf">PDF</option><option value="csv">CSV</option><option value="xlsx">XLSX</option>
                    </select>
                    <small>Gerenciais aceitam PDF, CSV ou XLSX.</small></>}</div>
            </div>
            <label className="pt-check"><input type="checkbox" checked={f.incluir_anuladas} onChange={set("incluir_anuladas")} />Incluir marcações anuladas (exigido no AFD — a anulação também é registro)</label>
            {!alvo.disponivel &&
              <Nota tom="warn">Hoje só o <b>Espelho de Ponto</b> tem geração implementada em <span className="mono">ReportService</span>; os demais retornam HTTP 501 no vivo. O pedido entra na fila abaixo para não sumir.</Nota>}
            <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
              <button className="pt-btn" onClick={() => setAlvo(null)}>Cancelar</button>
              <button className="pt-btn primary" onClick={gerar}><Ic name="download" />Gerar</button>
            </div>
          </div>
        </Card>}

      <div className="pt-relgrid">
        {D.RELATORIOS.map((r) => (
          <div className="pt-rel" key={r.chave}>
            <span className="pt-rel-ic"><Ic name={r.icone} /></span>
            <div style={{ minWidth: 0 }}>
              <b>{r.titulo}</b>
              <small>{r.descricao}</small>
              <span style={{ display: "inline-flex", gap: 6, alignItems: "center" }}>
                <button className={"pt-btn" + (r.disponivel ? " primary" : "")} onClick={() => { setAlvo(r); setF((o) => ({ ...o, formato: ["afd", "afdt", "aej"].includes(r.chave) ? "txt" : "pdf" })); }}>Gerar</button>
                {!r.disponivel && <Pill tom="warn">em implementação</Pill>}
              </span>
            </div>
          </div>
        ))}
      </div>

      {fila.length > 0 &&
        <Card icon="list" titulo="Pedidos desta sessão" sub={"(" + fila.length + ")"}>
          <Tabela cols={[{ l: "Relatório" }, { l: "Competência" }, { l: "Escopo" }, { l: "Formato", w: "80px" }, { l: "Estado", w: "150px" }]}>
            {fila.map((q) => (
              <tr key={q.id}>
                <td><b>{q.titulo}</b></td>
                <td>{q.comp}</td>
                <td><small style={{ color: "var(--text-dim)" }}>{q.quem}</small></td>
                <td><Pill tom="neutral" mono>{q.formato}</Pill></td>
                <td><Pill tom={q.estado === "GERADO" ? "ok" : "warn"} mono>{q.estado}</Pill></td>
              </tr>
            ))}
          </Tabela>
        </Card>}

      <Legal>Relatórios legais (AFD/AFDT/AEJ) seguem o layout da Portaria 671/2021 Anexo I. Hoje só o Espelho está implementado em ReportService.</Legal>
    </>
  );
}

// ═══════════════════════════ 8. CONFIGURAÇÕES ═══════════════════════════
function Dl({ children }) { return <dl className="pt-dl">{children}</dl>; }
function Li({ t, children, lei }) { return <><dt>{t}</dt><dd>{children}{lei && <span className="lei">{lei}</span>}</dd></>; }

function Configuracoes({ avisar }) {
  const D = P();
  const { Card, Nota, Tabela, Vazio, Pill, PillSimNao, Voltar, Ic } = U();
  const c = D.CONFIG;
  const [tela, setTela] = useState("config");
  const [reps, setReps] = useState(D.REPS);
  const [f, setF] = useState({ tipo: "REP_P", identificador: "", descricao: "", local: "", cnpj: "" });
  const set = (k) => (e) => setF((o) => ({ ...o, [k]: e.target.value }));

  if (tela === "reps") {
    const erro = f.identificador.length !== 17 ? "O identificador tem exatamente 17 caracteres (AAAAMMDDHHMMSSNNN)."
      : !f.descricao.trim() ? "A descrição é obrigatória." : null;
    return (
      <>
        <div className="pt-sub"><Voltar onClick={() => setTela("config")}>Voltar às configurações</Voltar>
          <div><h2>Dispositivos REP</h2><span className="pt-sub-sub">Registrador Eletrônico de Ponto · {reps.length} cadastrados</span></div></div>
        <div className="pt-cols-2">
          <Card icon="list" titulo="REPs cadastrados" sub={"(" + reps.length + ")"}>
            <Tabela cols={[{ l: "Tipo", w: "84px" }, { l: "Identificador" }, { l: "Descrição" }, { l: "Local" }, { l: "CNPJ" }]}>
              {reps.length === 0 && <Vazio colSpan={5}>Nenhum REP cadastrado ainda.</Vazio>}
              {reps.map((r) => (
                <tr key={r.id}>
                  <td><Pill tom="info" mono>{r.tipo.replace("_", "-")}</Pill></td>
                  <td className="mono">{r.identificador}</td>
                  <td>{r.descricao}</td>
                  <td><small style={{ color: "var(--text-mute)" }}>{r.local || "—"}</small></td>
                  <td className="mono">{r.cnpj || "—"}</td>
                </tr>
              ))}
            </Tabela>
          </Card>
          <Card icon="plus" titulo="Cadastrar novo REP">
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              <div className="pt-fld"><label htmlFor="rp-tipo">Tipo <span className="pt-req">*</span></label>
                <select id="rp-tipo" value={f.tipo} onChange={set("tipo")}>
                  <option value="REP_P">REP-P (Programa/mobile)</option>
                  <option value="REP_C">REP-C (Convencional)</option>
                  <option value="REP_A">REP-A (Alternativo)</option>
                </select></div>
              <div className="pt-fld"><label htmlFor="rp-id">Identificador (17 caracteres) <span className="pt-req">*</span></label>
                <input id="rp-id" maxLength={17} value={f.identificador} onChange={set("identificador")} placeholder="AAAAMMDDHHMMSSNNN" />
                <small>Formato conforme Portaria 671/2021 Anexo I. {f.identificador.length}/17</small></div>
              <div className="pt-fld"><label htmlFor="rp-desc">Descrição <span className="pt-req">*</span></label>
                <input id="rp-desc" maxLength={120} value={f.descricao} onChange={set("descricao")} /></div>
              <div className="pt-fld"><label htmlFor="rp-loc">Local</label>
                <input id="rp-loc" maxLength={120} value={f.local} onChange={set("local")} placeholder="Ex.: Recepção matriz" /></div>
              <div className="pt-fld"><label htmlFor="rp-cnpj">CNPJ</label>
                <input id="rp-cnpj" maxLength={14} value={f.cnpj} onChange={set("cnpj")} placeholder="Somente números (14 dígitos)" /></div>
              <button className="pt-btn primary" disabled={!!erro} title={erro || ""} onClick={() => {
                setReps((rs) => [...rs, { ...f, id: Date.now() }]);
                setF({ tipo: "REP_P", identificador: "", descricao: "", local: "", cnpj: "" });
                avisar("REP cadastrado — as marcações passam a aceitar o identificador.", "ok");
              }}><Ic name="check" />Cadastrar REP</button>
            </div>
          </Card>
        </div>
      </>
    );
  }

  return (
    <>
      <Nota tom="warn" titulo="Somente leitura">
        Estas configurações vêm de <b>Modules/Ponto/Config/config.php</b>. A edição pela UI não está implementada — para alterar, edite o arquivo e rode <span className="mono">php artisan config:clear</span>. Para os dispositivos, use o <button className="pt-btn" style={{ padding: "1px 7px", minHeight: 0 }} onClick={() => setTela("reps")}>cadastro de REPs</button>.
      </Nota>
      <div className="pt-cols">
        <Card icon="shield" titulo="Regras CLT / Reforma Trabalhista">
          <Dl>
            <Li t="Tolerância por marcação" lei="Art. 58 §1º CLT">{c.clt.tolerancia_minutos_por_marcacao} min</Li>
            <Li t="Tolerância máxima diária" lei="Art. 58 §1º CLT">{c.clt.tolerancia_maxima_diaria_minutos} min</Li>
            <Li t="Interjornada mínima" lei="Art. 66 CLT">{c.clt.interjornada_minima_horas} h</Li>
            <Li t="Intrajornada mínima" lei="Art. 71 CLT">{c.clt.intrajornada_minima_minutos} min</Li>
            <Li t="Hora noturna ficta" lei="Art. 73 §1º">{c.clt.hora_noturna_ficta_segundos} s</Li>
            <Li t="Adicional noturno" lei="Art. 73 CLT">{c.clt.adicional_noturno_percentual}%</Li>
            <Li t="Limite HE diária" lei="Art. 59 CLT">{c.clt.limite_he_diaria_horas} h</Li>
            <Li t="Adicional HE" lei="Art. 7º XVI CF/88">{c.clt.adicional_he_percentual}%</Li>
            <Li t="Adicional DSR" lei="Lei 605/49">{c.clt.adicional_dsr_percentual}%</Li>
          </Dl>
        </Card>
        <Card icon="coins" titulo="Banco de Horas">
          <Dl>
            <Li t="Habilitado"><PillSimNao v={c.banco_horas.habilitado} /></Li>
            <Li t="Prazo de compensação" lei="Reforma Trabalhista — acordo individual">{c.banco_horas.prazo_compensacao_meses} meses</Li>
            <Li t="Saldo máximo">{c.banco_horas.saldo_maximo_horas} h</Li>
            <Li t="Saldo mínimo">{c.banco_horas.saldo_minimo_horas} h</Li>
            <Li t="Multiplicador crédito">{c.banco_horas.multiplicador_credito}x</Li>
            <Li t="Multiplicador débito">{c.banco_horas.multiplicador_debito}x</Li>
            <Li t="Converter HE em BH automaticamente"><PillSimNao v={c.banco_horas.converter_he_em_bh_default} /></Li>
          </Dl>
        </Card>
        <Card icon="clock" titulo="REP e imutabilidade de marcações">
          <Dl>
            <Li t="Tipos de REP permitidos">{c.rep.tipos_permitidos.map((t) => <Pill key={t} tom="info" mono>{t.replace("_", "-")}</Pill>)}</Li>
            <Li t="Verificar sequência NSR">{c.rep.nsr_verificar_sequencia ? "Sim" : "Não"}</Li>
            <Li t="Assinar marcações (ICP-Brasil)">{c.rep.assinar_marcacoes ? "Sim" : "Não"}</Li>
            <Li t="Certificado ICP configurado" lei={c.rep.certificado_icp_path ? null : "setar PONTO_CERT_ICP_PATH no .env"}>
              <Pill tom={c.rep.certificado_icp_path ? "ok" : "warn"}>{c.rep.certificado_icp_path ? "Sim" : "Não"}</Pill></Li>
            <Li t="Janela de correção">{c.marcacao.janela_correcao_minutos} min</Li>
            <Li t="Append-only forçado">{c.marcacao.forcar_append_only ? "Sim" : "Não"}</Li>
            <Li t="Hash"><span className="mono">{c.marcacao.hash_algoritmo}</span></Li>
          </Dl>
          <div style={{ marginTop: 12 }}>
            <button className="pt-btn" onClick={() => setTela("reps")}><Ic name="list" />Gerenciar REPs cadastrados</button>
          </div>
        </Card>
        <Card icon="download" titulo="AFD / Importação · eSocial">
          <Dl>
            <Li t="Encoding"><span className="mono">{c.afd.encoding}</span></Li>
            <Li t="Tamanho máximo">{c.afd.max_filesize_mb} MB</Li>
            <Li t="Chunk de processamento">{c.afd.chunk_size_linhas.toLocaleString("pt-BR")} linhas</Li>
            <Li t="Validar hash de registros">{c.afd.validar_hash_registros ? "Sim" : "Não"}</Li>
            <Li t="Ambiente eSocial"><Pill tom={c.esocial.ambiente === "producao" ? "danger" : "warn"} mono>{c.esocial.ambiente}</Pill></Li>
            <Li t="Eventos">{c.esocial.eventos.map((e) => <Pill key={e} tom="neutral" mono>{e}</Pill>)}</Li>
            <Li t="tpAmb">{c.esocial.tp_amb}</Li>
          </Dl>
        </Card>
        <Card icon="sparkles" titulo="IA do Ponto" sub="— flags do config, nascem desligadas">
          <Dl>
            <Li t="Master switch"><PillSimNao v={c.ai.enabled} sim="Ligado" nao="Desligado" /></Li>
            <Li t="Classificação de intercorrência"><PillSimNao v={c.ai.classificacao_intercorrencia} sim="Ligado" nao="Desligado" /></Li>
            <Li t="Explicação de divergência"><PillSimNao v={c.ai.explicacao_divergencia} sim="Ligado" nao="Desligado" /></Li>
            <Li t="Geração de justificativa"><PillSimNao v={c.ai.geracao_justificativa} sim="Ligado" nao="Desligado" /></Li>
            <Li t="Modelo"><span className="mono">{c.ai.model}</span></Li>
          </Dl>
        </Card>
      </div>
    </>
  );
}

window.PontoTelas = { Aprovacoes, Intercorrencias, BancoHoras, Escalas, Colaboradores, Importacoes, Relatorios, Configuracoes };
})();
