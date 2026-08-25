// repair-forms.jsx — O2 do refino: formulários de verdade do Repair, portados dos blades
// job_sheet/create.blade.php + edit + add_parts.blade.php + upload_doc.blade.php.
// Mesma ordem de campos e mesmas obrigatoriedades do legado (location*, contact*,
// service_type*, serial_no*), checklist que aparece ao escolher o device_model, e
// peças buscadas no estoque do local. Expõe window.RepForms.
(() => {
const { useState, useMemo } = React;
const R = () => window.RepData;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const val = (e) => (e && e.target ? e.target.value : e);

// Campo de lista com sugestões do settings — o legado usa tagify (vírgula separa).
function Etiquetas({ label, help, sugestoes, valores, onChange }) {
  const { Input, TagChip } = DS();
  const [txt, setTxt] = useState("");
  const add = (v) => { const t = v.trim(); if (t && !valores.includes(t)) onChange([...valores, t]); setTxt(""); };
  return (
    <div className="rf-tags">
      {Input && <Input label={label} help={help} value={txt} placeholder="Digite e pressione Enter"
        onChange={(e) => setTxt(val(e))}
        onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); add(txt); } }} />}
      {valores.length > 0 && <div className="rf-tags-sel">
        {valores.map((v) => TagChip
          ? <TagChip key={v} label={v.toLowerCase()} removable onRemove={() => onChange(valores.filter((x) => x !== v))} />
          : <span key={v}>{v}</span>)}
      </div>}
      <div className="rf-tags-sug">
        {sugestoes.filter((s) => !valores.includes(s)).slice(0, 6).map((s) =>
          <button key={s} type="button" onClick={() => add(s)}>+ {s}</button>)}
      </div>
    </div>
  );
}

// ══════════ Folha de OS · criar / editar ══════════
function FolhaForm({ modo = "novo", folha, folhas, papel, onClose, onSalvar }) {
  const D = R();
  const { Drawer, DrawerSection, Input, Select, Textarea, RadioGroup, Switch, Checkbox, DatePicker, Button, Alert } = DS();
  const base = folha || {
    os: D.proximoNumero(folhas), cliente: "", tipoPf: "pj", servico: "carry_in", status: D.CONFIG.statusPadrao,
    tecnico: "Não atribuído", local: 0, modelo: 1, serie: "", custo: 0, entrega: D.iso(D.HOJE), criado: D.iso(D.HOJE),
    prior: "p2", defeitos: [], configuracao: "", condicao: "", senha: "", checklist: [], pecas: [], notificar: true,
    endereco: "", comentario: "", custom: ["", ""],
  };
  const [f, setF] = useState({ ...base, custom: base.custom || ["", ""], comentario: base.comentario || "" });
  const [erros, setErros] = useState({});
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const m = D.modeloDe(f.modelo);
  const checklist = m.checklist.split("|");
  const modelosDoFiltro = D.MODELOS;
  if (!Drawer) return null;

  const salvar = () => {
    const e = {};
    if (!f.cliente) e.cliente = "Cliente é obrigatório — a folha é dele.";
    if (!f.serie) e.serie = "Número de série é obrigatório no legado (serial_no*).";
    if (f.servico !== "carry_in" && !f.endereco) e.endereco = "Coleta e atendimento no local exigem endereço.";
    setErros(e);
    if (Object.keys(e).length) return;
    onSalvar({ ...f, custo: Number(f.custo) || 0 }, modo === "novo");
  };

  return (
    <Drawer open onClose={onClose} width={720}
      title={modo === "novo" ? "Nova folha de OS" : "Editar " + f.os}
      subtitle={modo === "novo" ? "Recebimento no balcão · " + f.os : f.cliente}
      footer={Button && <>
        <Button variant="ghost" onClick={onClose}>Cancelar</Button>
        <Button variant="ghost" onClick={salvar}>Salvar e adicionar peças</Button>
        <Button variant="primary" onClick={salvar}>{modo === "novo" ? "Salvar e imprimir etiqueta" : "Salvar folha"}</Button>
      </>}>
      <DrawerSection title="Recebimento">
        <div className="rf-grid">
          {Select && <Select label="Local do negócio *" value={String(f.local)} onChange={(e) => set("local", Number(val(e)))}
            options={D.LOCAIS.map((l, i) => ({ value: String(i), label: l }))} />}
          {Select && <Select label="Cliente *" value={f.cliente} error={erros.cliente} onChange={(e) => set("cliente", val(e))}
            options={[{ value: "", label: "Selecione o cliente" }, ...D.CLIENTES.map((c) => ({ value: c, label: c }))]} />}
        </div>
        <div className="rf-linha">
          {RadioGroup && <RadioGroup name="servico" label="Tipo de serviço *" direction="row" value={f.servico}
            onChange={(v) => set("servico", val(v))}
            options={Object.entries(D.SERVICO).map(([k, l]) => ({ value: k, label: l }))} />}
        </div>
        {f.servico !== "carry_in" && Input &&
          <Input label="Endereço de retirada / no local" value={f.endereco} error={erros.endereco}
            onChange={(e) => set("endereco", val(e))} placeholder="Rua, número, complemento" />}
      </DrawerSection>

      <DrawerSection title="Equipamento">
        <div className="rf-grid">
          {Select && <Select label="Modelo do equipamento" value={String(f.modelo)} onChange={(e) => set("modelo", Number(val(e)))}
            options={modelosDoFiltro.map((x) => ({ value: String(x.id), label: x.marca + " " + x.nome }))} />}
          {Input && <Input label="Marca" value={m.marca} readOnly help="Vem do cadastro do modelo" />}
          {Input && <Input label="Equipamento" value={m.dispositivo} readOnly />}
          {Input && <Input label="Número de série *" value={f.serie} error={erros.serie} onChange={(e) => set("serie", val(e))} placeholder="Como está na etiqueta do equipamento" />}
          {Input && <Input label="Senha / padrão de bloqueio" value={f.senha} onChange={(e) => set("senha", val(e))} help="Guardado com a folha, nunca no recibo do cliente" />}
        </div>
        <Etiquetas label="Configuração do produto" help="Sugestões vêm das configurações do negócio"
          sugestoes={D.SUGESTOES.configuracoes} valores={f.configuracao ? f.configuracao.split(" · ") : []}
          onChange={(v) => set("configuracao", v.join(" · "))} />
      </DrawerSection>

      <DrawerSection title="Checklist de pré-reparo">
        {checklist.length ? <>
          <p className="rf-help">Com base no modelo do dispositivo, o checklist aparece aqui — marque o que já foi conferido na entrada.</p>
          <div className="rf-check">
            {checklist.map((c) => Checkbox &&
              <Checkbox key={c} checked={f.checklist.includes(c)} label={c}
                onChange={() => set("checklist", f.checklist.includes(c) ? f.checklist.filter((x) => x !== c) : [...f.checklist, c])} />)}
          </div>
        </> : Alert && <Alert tone="info" title="Modelo sem checklist">Cadastre o checklist no modelo do equipamento e ele passa a aparecer aqui.</Alert>}
      </DrawerSection>

      <DrawerSection title="Problema e condição">
        <Etiquetas label="Problema relatado pelo cliente" sugestoes={D.SUGESTOES.defeitos} valores={f.defeitos} onChange={(v) => set("defeitos", v)} />
        <Etiquetas label="Condição do produto na entrada" sugestoes={D.SUGESTOES.condicoes}
          valores={f.condicao ? f.condicao.split(" · ") : []} onChange={(v) => set("condicao", v.join(" · "))} />
      </DrawerSection>

      <DrawerSection title="Atendimento e prazo">
        <div className="rf-grid">
          {Select && <Select label="Técnico responsável" value={f.tecnico} onChange={(e) => set("tecnico", val(e))}
            options={D.TECNICOS.map((t) => ({ value: t, label: t }))} />}
          {Select && <Select label="Status inicial" value={String(f.status)} onChange={(e) => set("status", Number(val(e)))}
            options={D.STATUS.map((s) => ({ value: String(s.id), label: s.nome }))} />}
          {Input && <Input label="Custo estimado (R$)" value={String(f.custo)} onChange={(e) => set("custo", val(e))} help="0 = ainda sem orçamento" />}
          {DatePicker && <DatePicker label="Entrega prevista" value={f.entrega} onChange={(d) => set("entrega", d ? D.iso(new Date(d)) : f.entrega)} />}
          {Select && <Select label="Prioridade" value={f.prior} onChange={(e) => set("prior", val(e))}
            options={[{ value: "p0", label: "Urgente" }, { value: "p1", label: "Alta" }, { value: "p2", label: "Normal" }, { value: "p3", label: "Baixa" }]} />}
        </div>
        {Textarea && <Textarea label="Comentário do técnico" value={f.comentario} onChange={(e) => set("comentario", val(e))} rows={3}
          help="Fica na folha, não vai no recibo do cliente" />}
      </DrawerSection>

      <DrawerSection title="Campos personalizados e notificação">
        <div className="rf-grid">
          {D.CONFIG.camposCustom.filter(Boolean).map((rot, i) => Input &&
            <Input key={rot} label={rot} value={f.custom[i] || ""}
              onChange={(e) => { const c = [...f.custom]; c[i] = val(e); set("custom", c); }} />)}
        </div>
        {Switch && <Switch checked={f.notificar} onChange={(v) => set("notificar", v)}
          label="Notificar o cliente a cada mudança de status"
          sublabel={D.can(papel, "send_notification") ? "Usa o texto de SMS/e-mail do status" : "Seu papel não dispara notificação (send_notification)"} />}
      </DrawerSection>
    </Drawer>
  );
}

// ══════════ Peças (add_parts.blade.php) ══════════
function PecasDrawer({ folha, onClose, onSalvar, avisar }) {
  const D = R();
  const { Drawer, DrawerSection, Input, Button, Alert, StatusBadge } = DS();
  const [itens, setItens] = useState(folha.pecas || []);
  const [busca, setBusca] = useState("");
  if (!Drawer) return null;
  const achados = D.PECAS_ESTOQUE.filter((p) => !busca || (p.nome + " " + p.sku).toLowerCase().includes(busca.toLowerCase()));
  const total = itens.reduce((s, p) => s + p.valor * p.qtd, 0);
  const add = (p) => {
    if (itens.some((x) => x.nome === p.nome)) return avisar && avisar("Peça já lançada nesta folha.", "warn");
    setItens((l) => [...l, { nome: p.nome, qtd: 1, valor: p.valor, situacao: p.saldo === 0 ? "encomendado" : "ok" }]);
  };
  return (
    <Drawer open onClose={onClose} width={620} title={"Adicionar peças · " + folha.os} subtitle={folha.cliente}
      footer={Button && <>
        <Button variant="ghost" onClick={onClose}>Cancelar</Button>
        <Button variant="ghost" onClick={() => onSalvar(folha, itens, true)}>Salvar e marcar como concluída</Button>
        <Button variant="primary" onClick={() => onSalvar(folha, itens, false)}>Salvar peças</Button>
      </>}>
      <DrawerSection title="Buscar no estoque">
        {Input && <Input label="Peça" value={busca} onChange={(e) => setBusca(val(e))} placeholder="Nome ou SKU" />}
        <div className="rf-estoque">
          {achados.map((p) =>
            <button key={p.sku} type="button" className="rf-estoque-row" onClick={() => add(p)}>
              <span><b>{p.nome}</b><small className="mono">{p.sku} · {D.fmt(p.valor)}</small></span>
              <span className={"rf-saldo" + (p.saldo === 0 ? " zero" : "")}>
                {p.saldo == null ? "serviço" : p.saldo === 0 ? "sem saldo — encomenda" : p.saldo + " em estoque"}
              </span>
            </button>)}
          {achados.length === 0 && <p className="rf-help">Nada com esse termo no estoque deste local.</p>}
        </div>
      </DrawerSection>
      <DrawerSection title="Peças desta folha">
        {itens.length ? <>
          {itens.map((p, i) =>
            <div key={p.nome} className="rf-item">
              <b>{p.nome}</b>
              <input aria-label={"Quantidade de " + p.nome} type="number" min="1" value={p.qtd}
                onChange={(e) => { const l = [...itens]; l[i] = { ...p, qtd: Math.max(1, Number(e.target.value) || 1) }; setItens(l); }} />
              <span className="mono">{D.fmt(p.valor * p.qtd)}</span>
              {StatusBadge && <StatusBadge kind="documento" value={p.situacao === "ok" ? "aprovado" : "pendente"}
                label={p.situacao === "ok" ? "no balcão" : "encomendada"} />}
              <button type="button" className="rf-x" aria-label={"Remover " + p.nome} onClick={() => setItens(itens.filter((x) => x.nome !== p.nome))}>✕</button>
            </div>)}
          <div className="rf-item total"><b>Total em peças</b><span className="mono">{D.fmt(total)}</span></div>
        </> : Alert && <Alert tone="info" title="Nenhuma peça lançada">Peça lançada baixa estoque e entra na venda derivada do reparo.</Alert>}
      </DrawerSection>
    </Drawer>
  );
}

// ══════════ Documentos (upload_doc.blade.php) ══════════
function DocsDrawer({ folha, onClose, avisar }) {
  const D = R();
  const { Drawer, DrawerSection, Button, Alert } = DS();
  const docs = D.DOCS[folha.id] || [];
  if (!Drawer) return null;
  return (
    <Drawer open onClose={onClose} width={520} title={"Documentos · " + folha.os} subtitle={folha.cliente}
      footer={Button && <Button variant="primary" onClick={() => { avisar("Upload de documento (PDF, JPG até 5 MB)."); onClose(); }}>Enviar documento</Button>}>
      <DrawerSection title="Anexos da folha">
        {docs.length ? docs.map((d) =>
          <div key={d.nome} className="rf-item">
            <b>{d.nome}</b><span className="mono">{d.tam}</span><span className="mono">{D.d2(d.em)}</span>
            <button type="button" className="rf-x" aria-label={"Remover " + d.nome} onClick={() => avisar("Excluir anexo pede confirmação.", "warn")}>✕</button>
          </div>) : Alert && <Alert tone="info" title="Sem documentos anexados">Laudo, foto do defeito e nota da peça vivem aqui — é o que sustenta a conversa com o cliente depois.</Alert>}
      </DrawerSection>
    </Drawer>
  );
}

// ══════════ Excluir folha (job_sheet.delete) ══════════
function ExcluirModal({ folha, onClose, onConfirmar }) {
  const { Modal, Button, Alert } = DS();
  if (!Modal) return null;
  return (
    <Modal open onClose={onClose} width={460} title={"Excluir " + folha.os}
      footer={Button && <>
        <Button variant="ghost" onClick={onClose}>Cancelar</Button>
        <Button variant="danger" onClick={() => onConfirmar(folha)}>Excluir folha</Button>
      </>}>
      {Alert && <Alert tone="danger" title="A folha some, o reparo faturado não">
        Se já existe reparo/fatura ligado a {folha.os}, ele continua no Financeiro — some só o registro de serviço e a trilha de atividades.
      </Alert>}
    </Modal>
  );
}

window.RepForms = { FolhaForm, PecasDrawer, DocsDrawer, ExcluirModal };
})();
