// patrimonio-forms.jsx — formulários do módulo Patrimônio, montados nos componentes do DS.
// Drawer/DrawerSection (PT-02), Modal (PT-04), Input/Select/Textarea/Switch/DatePicker,
// Button, Alert e StatusBadge vêm do bundle — zero chrome de formulário próprio.
// Regras do módulo real: Asset::forDropdown (saldo > 0), prefixos PAT-/ALO-/REV-,
// description fora da whitelist auditada (LGPD).
// Expõe window.PatForms.
(() => {
const { useState, useEffect, useMemo } = React;
const P = () => window.PatData;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Codigo = ({ children }) => <span style={{ fontFamily: "var(--font-mono)", fontSize: 11.5, letterSpacing: ".04em", color: "var(--text-mute)" }}>{children}</span>;
const Grade = ({ children, cols = 2 }) => <div className="pfm-grid" style={{ gridTemplateColumns: "repeat(" + cols + ",minmax(0,1fr))" }}>{children}</div>;
const Largo = ({ children }) => <div style={{ gridColumn: "1/-1" }}>{children}</div>;
const dataISO = (d) => (d ? P().iso(d) : "");
// leitura: o DatePicker recebe Date local, nunca a string ISO crua.
const dLocal = (v) => P().dataLocal(v);

function Rodape({ onClose, onSalvar, salvarLabel, perigo, desabilitado }) {
  const { Button } = DS();
  if (!Button) return null;
  return (
    <>
      <Button variant="ghost" onClick={onClose}>Cancelar</Button>
      <Button variant={perigo ? "danger" : "primary"} disabled={desabilitado} onClick={onSalvar}>{salvarLabel}</Button>
    </>
  );
}

// ═══════════ Bem — asset/create + asset/edit ═══════════
function BemForm({ modo, bem, bens, papel, onClose, onSalvar }) {
  const D = P();
  const { Drawer, DrawerSection, Input, Select, Textarea, Switch, DatePicker, Button, Alert } = DS();
  const novo = modo !== "editar";
  const [f, setF] = useState(() => bem
    ? { ...bem, garMeses: bem.garantia ? String(Math.round(D.dias(bem.garantia.ini, bem.garantia.fim) / 30.44)) : "", garIni: bem.garantia ? bem.garantia.ini : bem.compra, garFornec: bem.garantia ? bem.garantia.fornec : "" }
    : { id: D.proximoCodigo("PAT-", bens), nome: "", cat: "impressao", loc: "matriz", modelo: "", serie: "",
        compra: D.iso(D.HOJE), tipo: "owned", valor: "", qtd: "1", alocavel: false, img: false, dep: "10",
        garMeses: "", garIni: D.iso(D.HOJE), garFornec: "", desc: "" });
  const [erros, setErros] = useState({});
  const set = (k, v) => setF((o) => ({ ...o, [k]: v }));
  const locais = Object.keys(D.LOCAIS).filter((k) => D.podeVerLocal(papel, k));
  useEffect(() => { if (novo) set("dep", String(D.DEP_PADRAO[f.cat] || 10)); }, [f.cat]);
  if (!Drawer) return null;

  const salvar = () => {
    const e = {};
    if (!String(f.nome).trim()) e.nome = "O nome do recurso é obrigatório.";
    if (!f.compra) e.compra = "Informe a data da compra.";
    if (!(Number(f.valor) > 0)) e.valor = "Valor unitário precisa ser maior que zero.";
    if (!(Number(f.qtd) >= 1)) e.qtd = "Quantidade mínima é 1.";
    if (f.garMeses && !f.garIni) e.garIni = "Com período de garantia, informe o início.";
    setErros(e);
    if (Object.keys(e).length) return;
    const g = f.garMeses
      ? (() => { const d = new Date(f.garIni); d.setMonth(d.getMonth() + Number(f.garMeses)); return { ini: f.garIni, fim: D.iso(d), fornec: f.garFornec || "—" }; })()
      : null;
    onSalvar({ ...f, valor: Number(f.valor), qtd: Number(f.qtd), dep: Number(f.dep), garantia: g }, novo);
  };

  return (
    <Drawer open onClose={onClose} width={640} badge={<Codigo>{f.id}</Codigo>}
      title={novo ? "Adicionar recurso" : "Editar recurso"}
      subtitle={novo ? "Código gerado pelo prefixo do módulo — sequência por empresa." : "Mudança em campo auditado entra na trilha de auditoria."}
      footer={<Rodape onClose={onClose} onSalvar={salvar} salvarLabel={novo ? "Cadastrar bem" : "Salvar alterações"} />}>
      <DrawerSection title="Identificação">
        <Grade>
          <Largo><Input label="Nome do recurso" value={f.nome} error={erros.nome} placeholder="Ex.: Plotter de corte Summa D60" onChange={(e) => set("nome", e.target.value)} /></Largo>
          <Select label="Categoria" value={f.cat} onChange={(e) => set("cat", e.target.value)}
            options={Object.keys(D.CATEGORIAS).map((k) => ({ value: k, label: D.CATEGORIAS[k] }))} />
          <Select label="Local" value={f.loc} onChange={(e) => set("loc", e.target.value)}
            help={locais.length < 3 ? "Só os locais permitidos pro seu usuário." : undefined}
            options={locais.map((k) => ({ value: k, label: D.LOCAIS[k] }))} />
          <Input label="Série/Modelo" value={f.modelo} onChange={(e) => set("modelo", e.target.value)} />
          <Input label="Número de série" value={f.serie} onChange={(e) => set("serie", e.target.value)} />
        </Grade>
      </DrawerSection>
      <DrawerSection title="Compra e valores">
        <Grade>
          <div>{DatePicker
            ? <DatePicker label="Data da compra" value={dLocal(f.compra)} onChange={(d) => set("compra", dataISO(d))} />
            : <Input label="Data da compra" type="date" value={f.compra} error={erros.compra} onChange={(e) => set("compra", e.target.value)} />}</div>
          <Select label="Tipo de compra" value={f.tipo} onChange={(e) => set("tipo", e.target.value)}
            options={Object.keys(D.TIPOS).map((k) => ({ value: k, label: D.TIPOS[k] }))} />
          <Input label="Valor unitário (R$)" type="number" value={String(f.valor)} error={erros.valor} onChange={(e) => set("valor", e.target.value)} />
          <Input label="Quantidade" type="number" value={String(f.qtd)} error={erros.qtd} onChange={(e) => set("qtd", e.target.value)} />
          <Input label="Depreciação (anos)" type="number" value={String(f.dep)} help="Padrão da categoria — máquina 10, informática e veículo 5." onChange={(e) => set("dep", e.target.value)} />
          <div style={{ alignSelf: "end", paddingBottom: 4 }}>
            <Switch checked={!!f.alocavel} onChange={(v) => set("alocavel", v)}
              label="É atribuível?" sublabel="Se atribuível, o bem pode ser alocado a um colaborador." />
          </div>
        </Grade>
      </DrawerSection>
      <DrawerSection title="Garantia">
        <Grade>
          <Input label="Período de garantia (meses)" type="number" value={String(f.garMeses)} help="Vazio = bem sem garantia registrada." onChange={(e) => set("garMeses", e.target.value)} />
          <div>{DatePicker
            ? <DatePicker label="Início da garantia" value={dLocal(f.garIni)} onChange={(d) => set("garIni", dataISO(d))} />
            : <Input label="Início da garantia" type="date" value={f.garIni} error={erros.garIni} onChange={(e) => set("garIni", e.target.value)} />}</div>
          <Largo><Input label="Fornecedor / contrato" value={f.garFornec} placeholder="Ex.: Roland Care · contrato RC-8842" onChange={(e) => set("garFornec", e.target.value)} /></Largo>
        </Grade>
      </DrawerSection>
      <DrawerSection title="Imagem e descrição">
        <div className="pfm-midia">
          <span className="pfm-midia-plate">{f.img ? "1 imagem" : "sem imagem"}</span>
          <div>
            <Button variant="ghost" size="sm" onClick={() => set("img", !f.img)}>{f.img ? "Remover imagem" : "Anexar imagem"}</Button>
            <small style={{ display: "block", marginTop: 6, fontSize: 11, color: "var(--text-mute)" }}>A imagem anterior (se existir) será substituída.</small>
          </div>
        </div>
        <div style={{ marginTop: 12 }}>
          <Textarea label="Descrição" rows={3} value={f.desc} onChange={(e) => set("desc", e.target.value)}
            help="Campo não auditado — não registre dado pessoal aqui (LGPD)." />
        </div>
      </DrawerSection>
    </Drawer>
  );
}

// ═══════════ Alocação — asset_allocation/create ═══════════
function AlocarForm({ bemId, bens, alocacoes, papel, onClose, onSalvar }) {
  const D = P();
  const { Drawer, DrawerSection, Input, Select, DatePicker, Alert, Button } = DS();
  const disponiveis = useMemo(() => D.paraAlocar(bens, alocacoes, papel), [bens, alocacoes, papel]);
  const [f, setF] = useState(() => ({
    bem: bemId && disponiveis.some((b) => b.id === bemId) ? bemId : (disponiveis[0] ? disponiveis[0].id : ""),
    para: D.COLABORADORES[0].id, qtd: "1", em: D.iso(D.HOJE), ate: "", motivo: "",
  }));
  const [erros, setErros] = useState({});
  const set = (k, v) => setF((o) => ({ ...o, [k]: v }));
  const b = bens.find((x) => x.id === f.bem);
  const livre = b ? D.saldo(alocacoes, b) : 0;
  if (!Drawer) return null;

  if (!disponiveis.length) {
    return (
      <Drawer open onClose={onClose} width={520} title="Alocar recurso"
        footer={<Button variant="primary" onClick={onClose}>Entendi</Button>}>
        <DrawerSection>
          <Alert tone="info" title="Nenhum bem disponível pra alocação">
            Só entra na lista o que está marcado como atribuível, tem saldo livre e fica num local permitido pro seu usuário.
          </Alert>
        </DrawerSection>
      </Drawer>
    );
  }
  const salvar = () => {
    const e = {};
    if (!(Number(f.qtd) >= 1)) e.qtd = "Quantidade mínima é 1.";
    if (Number(f.qtd) > livre) e.qtd = "Só há " + livre + " unidade(s) livre(s) deste bem.";
    if (!f.em) e.em = "Informe a data da alocação.";
    if (f.ate && f.ate < f.em) e.ate = "A data final não pode ser antes do início.";
    if (!String(f.motivo).trim()) e.motivo = "A razão da alocação é obrigatória — é ela que dá rastro.";
    setErros(e);
    if (Object.keys(e).length) return;
    const c = D.COLABORADORES.find((x) => x.id === f.para);
    onSalvar({ bem: f.bem, para: c.nome, papel: c.papel, qtd: Number(f.qtd), em: f.em, ate: f.ate || null, motivo: f.motivo.trim() });
  };

  return (
    <Drawer open onClose={onClose} width={560} badge={<Codigo>{D.proximoCodigo("ALO-", alocacoes)}</Codigo>}
      title="Alocar recurso" subtitle="Só bem atribuível com saldo livre aparece na lista."
      footer={<Rodape onClose={onClose} onSalvar={salvar} salvarLabel="Alocar" />}>
      <DrawerSection>
        <Grade>
          <Largo><Select label="Bem" value={f.bem} onChange={(e) => set("bem", e.target.value)}
            options={disponiveis.map((x) => ({ value: x.id, label: x.nome + " (" + D.saldo(alocacoes, x) + " livre" + (D.saldo(alocacoes, x) > 1 ? "s" : "") + ")" }))} /></Largo>
          <Select label="Alocar para" value={f.para} onChange={(e) => set("para", e.target.value)}
            options={D.COLABORADORES.map((c) => ({ value: c.id, label: c.nome + " — " + c.papel }))} />
          <Input label="Quantidade alocada" type="number" value={String(f.qtd)} error={erros.qtd} help={"Saldo livre: " + livre} onChange={(e) => set("qtd", e.target.value)} />
          <div>{DatePicker
            ? <DatePicker label="Alocado de" value={dLocal(f.em)} onChange={(d) => set("em", dataISO(d))} />
            : <Input label="Alocado de" type="date" value={f.em} error={erros.em} onChange={(e) => set("em", e.target.value)} />}</div>
          <div>{DatePicker
            ? <DatePicker label="Alocado até" value={dLocal(f.ate)} onChange={(d) => set("ate", dataISO(d))} placeholder="indeterminado" />
            : <Input label="Alocado até" type="date" value={f.ate} error={erros.ate} onChange={(e) => set("ate", e.target.value)} />}</div>
          <Largo><Input label="Razão" value={f.motivo} error={erros.motivo} placeholder="Ex.: Estação de orçamento do balcão" onChange={(e) => set("motivo", e.target.value)} /></Largo>
        </Grade>
      </DrawerSection>
    </Drawer>
  );
}

// ═══════════ Revogação — asset_revocation/create ═══════════
function RevogarForm({ alocacao, bens, alocacoes, onClose, onSalvar }) {
  const D = P();
  const { Drawer, DrawerSection, Input, DatePicker, Alert } = DS();
  const b = bens.find((x) => x.id === alocacao.bem) || {};
  const [f, setF] = useState({ qtd: String(alocacao.qtd), em: D.iso(D.HOJE), motivo: "" });
  const [erros, setErros] = useState({});
  const set = (k, v) => setF((o) => ({ ...o, [k]: v }));
  if (!Drawer) return null;
  const salvar = () => {
    const e = {};
    if (!(Number(f.qtd) >= 1) || Number(f.qtd) > alocacao.qtd) e.qtd = "Entre 1 e " + alocacao.qtd + " unidade(s).";
    if (!f.em) e.em = "Informe a data da revogação.";
    if (!String(f.motivo).trim()) e.motivo = "Diga por que está revogando.";
    setErros(e);
    if (Object.keys(e).length) return;
    onSalvar(alocacao, { qtd: Number(f.qtd), em: f.em, motivo: f.motivo.trim() });
  };
  return (
    <Drawer open onClose={onClose} width={520} badge={<Codigo>{alocacao.id}</Codigo>}
      title={"Revogar " + alocacao.id} subtitle={b.nome + " · alocado a " + alocacao.para}
      footer={<Rodape onClose={onClose} onSalvar={salvar} salvarLabel="Revogar alocação" perigo />}>
      <DrawerSection>
        <Alert tone="warn" title="A unidade volta pro saldo livre">
          {alocacao.qtd} un. com {alocacao.para} desde {D.d2(alocacao.em)} — revogar encerra a responsabilidade dela sobre o bem.
        </Alert>
        <div style={{ marginTop: 12 }}>
          <Grade>
            <Input label="Quantidade revogada" type="number" value={f.qtd} error={erros.qtd} onChange={(e) => set("qtd", e.target.value)} />
            <div>{DatePicker
              ? <DatePicker label="Revogado em" value={dLocal(f.em)} onChange={(d) => set("em", dataISO(d))} />
              : <Input label="Revogado em" type="date" value={f.em} error={erros.em} onChange={(e) => set("em", e.target.value)} />}</div>
            <Largo><Input label="Razão" value={f.motivo} error={erros.motivo} placeholder="Ex.: Fim da obra do cliente" onChange={(e) => set("motivo", e.target.value)} /></Largo>
          </Grade>
        </div>
      </DrawerSection>
    </Drawer>
  );
}

// ═══════════ Manutenção — asset_maintenance/create ═══════════
function ManutencaoForm({ bemId, bemIds, bens, manutencoes, papel, onClose, onSalvar }) {
  const D = P();
  const { Drawer, DrawerSection, Input, Select, Textarea, DatePicker, Alert } = DS();
  const lista = bens.filter((b) => D.podeVerLocal(papel, b.loc));
  const lote = bemIds && bemIds.length > 1 ? bemIds : null;
  const [f, setF] = useState({ bem: bemId || (lista[0] && lista[0].id) || "", prestador: "", resp: D.PAPEIS[papel] ? D.PAPEIS[papel].quem : "Wagner", ini: D.iso(D.HOJE), fim: "", custo: "", notas: "" });
  const [erros, setErros] = useState({});
  const set = (k, v) => setF((o) => ({ ...o, [k]: v }));
  if (!Drawer) return null;
  const salvar = () => {
    const e = {};
    if (!String(f.prestador).trim()) e.prestador = "Informe quem vai fazer a manutenção.";
    if (!f.ini) e.ini = "Informe a data de envio.";
    if (f.fim && f.fim < f.ini) e.fim = "A devolução não pode ser antes do envio.";
    setErros(e);
    if (Object.keys(e).length) return;
    onSalvar({ bem: f.bem, prestador: f.prestador.trim(), resp: f.resp, ini: f.ini, fim: f.fim || null,
      custo: Number(f.custo || 0), notas: f.notas.trim(),
      status: f.fim ? "concluida" : f.ini > D.iso(D.HOJE) ? "agendada" : "andamento" });
  };
  return (
    <Drawer open onClose={onClose} width={560} badge={<Codigo>{D.proximoCodigo("MAN-", manutencoes)}</Codigo>}
      title={lote ? "Enviar " + lote.length + " bens pra manutenção" : "Enviar pra manutenção"}
      subtitle="Data futura entra como agendada; hoje ou passada, em andamento."
      footer={<Rodape onClose={onClose} onSalvar={salvar} salvarLabel="Registrar manutenção" />}>
      <DrawerSection>
        <Grade>
          <Largo>{lote
            ? <Alert tone="info" title={lote.length + " bens selecionados"}>
                {lote.join(" · ")} — um registro de manutenção por bem, com os mesmos dados abaixo. O custo informado vale para cada um.
              </Alert>
            : <Select label="Bem" value={f.bem} onChange={(e) => set("bem", e.target.value)}
                options={lista.map((b) => ({ value: b.id, label: b.id + " · " + b.nome }))} />}</Largo>
          <Input label="Prestador" value={f.prestador} error={erros.prestador} placeholder="Ex.: Roland Care" onChange={(e) => set("prestador", e.target.value)} />
          <Input label="Responsável interno" value={f.resp} onChange={(e) => set("resp", e.target.value)} />
          <div>{DatePicker
            ? <DatePicker label="Enviado em" value={dLocal(f.ini)} onChange={(d) => set("ini", dataISO(d))} />
            : <Input label="Enviado em" type="date" value={f.ini} error={erros.ini} onChange={(e) => set("ini", e.target.value)} />}</div>
          <div>{DatePicker
            ? <DatePicker label="Devolvido em" value={dLocal(f.fim)} onChange={(d) => set("fim", dataISO(d))} placeholder="ainda fora" />
            : <Input label="Devolvido em" type="date" value={f.fim} error={erros.fim} onChange={(e) => set("fim", e.target.value)} />}</div>
          <Largo><Input label="Custo adicional (R$)" type="number" value={f.custo}
            help="Vira título a pagar no Financeiro quando a manutenção fecha." onChange={(e) => set("custo", e.target.value)} /></Largo>
          <Largo><Textarea label="Nota adicional" rows={3} value={f.notas} placeholder="O que será feito e por quê." onChange={(e) => set("notas", e.target.value)} /></Largo>
        </Grade>
      </DrawerSection>
    </Drawer>
  );
}

// ═══════════ Exclusão — confirmação PT-04 ═══════════
function ExcluirModal({ bem, alocacoes, onClose, onConfirmar }) {
  const D = P();
  const { Modal, Alert, Button } = DS();
  const ativas = alocacoes.filter((a) => a.bem === bem.id && !a.revoke);
  if (!Modal) return null;
  return (
    <Modal open onClose={onClose} width={460} title={"Excluir " + bem.id + "?"}
      footer={<>
        <Button variant="ghost" onClick={onClose}>Cancelar</Button>
        <Button variant="danger" disabled={ativas.length > 0} onClick={() => onConfirmar(bem)}>Excluir bem</Button>
      </>}>
      <p style={{ margin: "0 0 12px", fontSize: 12.5, lineHeight: 1.55, color: "var(--text-dim)" }}>
        {bem.nome} · {D.fmt(bem.valor * bem.qtd)} de patrimônio. A exclusão tira o bem da lista, mas a trilha de auditoria continua registrada.
      </p>
      {ativas.length > 0 &&
        <Alert tone="danger" title={"Este bem tem " + ativas.length + " alocação(ões) ativa(s)"}>
          Revogue antes — senão a unidade fica na mão de alguém sem ficha que a acompanhe.
        </Alert>}
    </Modal>
  );
}

window.PatForms = { BemForm, AlocarForm, RevogarForm, ManutencaoForm, ExcluirModal };
})();
