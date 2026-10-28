// repair-status-form.jsx — formulário de cadastro e edição de STATUS do reparo.
// Porta de status/create.blade.php + status/edit.blade.php (RepairStatusController@create/edit).
//
// Por que PÁGINA e não modal/drawer: o charter de Repair/Status/Index proíbe "Modal pra editar
// (rota dedicada existe)" e "CRUD inline". Criar e editar têm rota própria; esta vista é a página
// dessas duas rotas. Rotas pedidas ao shell (o shell é que registra, não este arquivo):
//   rep-status-novo     → { page: "Repair/Status/Create", aba: "status" }
//   rep-status-editar   → { page: "Repair/Status/Edit",   aba: "status", status: <id> }
//
// Campos e ordem = a Blade de hoje: nome*, cor, ordem, "conclui a folha", aviso pelo WhatsApp
// (Não avisar · Mensagem de pronto · Mensagem de aguardando peças — UC-RSTIDX-10: só esses dois
// templates existem por número), modelo de SMS, assunto e corpo do e-mail.
// Etiquetas = RepairUtil::getRepairStatusTemplateTags() (as mesmas que o backend substitui).
//
// Expõe window.RepairStatusForm = { StatusPagina }.
(() => {
const { useState, useMemo } = React;
const R = () => window.RepData;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const val = (e) => (e && e.target ? (e.target.type === "checkbox" ? e.target.checked : e.target.value) : e);

const TAGS = ["{customer_name}", "{job_sheet_no}", "{status}", "{serial_number}", "{delivery_date}",
  "{service_staff}", "{brand}", "{device}", "{device_model}", "{business_name}"];

const AVISOS = [
  { value: "", label: "Não avisar" },
  { value: "ready", label: "Mensagem de pronto" },
  { value: "waiting_parts", label: "Mensagem de aguardando peças" },
];

// Exemplo pra pré-visualização — uma OS fictícia, nunca dado de cliente real.
const EXEMPLO = {
  "{customer_name}": "Cliente Exemplo", "{job_sheet_no}": "OS-0042", "{serial_number}": "SN-000123",
  "{delivery_date}": "30/10/2026", "{service_staff}": "Técnico Exemplo", "{brand}": "Epson",
  "{device}": "Impressora", "{device_model}": "L3250", "{business_name}": "Sua empresa",
};

function preencher(texto, nome) {
  let s = String(texto || "");
  Object.entries({ ...EXEMPLO, "{status}": nome || "Status" }).forEach(([k, v]) => { s = s.split(k).join(v); });
  return s;
}

// Status vindo do protótipo (repair-data.jsx usa :os/:cliente) → forma do formulário.
function doStatus(s) {
  if (!s) return { nome: "", cor: "#2563eb", ordem: "", concluido: false, aviso: "", sms: "", assunto: "", corpo: "" };
  const t = (x) => String(x || "").replace(":cliente", "{customer_name}").replace(":os", "{job_sheet_no}");
  return {
    nome: s.nome, cor: s.cor, ordem: String(s.ordem ?? ""), concluido: !!s.concluido,
    aviso: s.aviso || (s.coluna === "pronto" ? "ready" : s.coluna === "aguardando-pecas" ? "waiting_parts" : ""),
    sms: t(s.sms), assunto: t(s.assunto), corpo: t(s.corpo || ""),
  };
}

function StatusPagina({ modo, statusId, folhas, papel, avisar, onVoltar }) {
  const D = R();
  const { Input, Select, Textarea, Checkbox, Button, Alert } = DS();
  const atual = modo === "editar" ? D.STATUS.find((s) => s.id === statusId) : null;
  const [f, setF] = useState(() => doStatus(atual));
  const [erros, setErros] = useState({});
  const [campoAlvo, setCampoAlvo] = useState("sms");
  const pode = D.can(papel, "repair_status.access");
  const set = (k, v) => setF((x) => ({ ...x, [k]: val(v) }));
  const nFolhas = useMemo(() => (atual ? (folhas || []).filter((x) => x.status === atual.id).length : 0), [atual, folhas]);

  if (modo === "editar" && !atual) {
    return Alert ? <Alert tone="warn" title="Status não encontrado">Ele pode ter sido excluído ou ser de outra empresa.</Alert> : null;
  }
  if (!pode) {
    return Alert ? <Alert tone="warn" title="Sem permissão">Seu papel não cadastra status (<span className="mono">access_job_sheet_status</span>).</Alert> : null;
  }

  const inserir = (tag) => set(campoAlvo, (f[campoAlvo] || "") + tag);
  const salvar = () => {
    const e = {};
    if (!f.nome.trim()) e.nome = "Nome do status é obrigatório.";
    if (f.ordem !== "" && !/^\d+$/.test(f.ordem)) e.ordem = "Ordem é um número inteiro.";
    setErros(e);
    if (Object.keys(e).length) return;
    avisar(modo === "novo" ? "Status “" + f.nome + "” criado." : "Status “" + f.nome + "” atualizado.", "ok");
    onVoltar();
  };

  return (
    <div className="rep-cfg rep-stf">
      <div className="rep-stf-head">
        {Button && <Button size="sm" variant="ghost" onClick={onVoltar}>← Status do reparo</Button>}
        <h3>{modo === "novo" ? "Novo status" : "Editar status: " + atual.nome}</h3>
        <p>{modo === "novo"
          ? "Nome, cor e ordem definem a coluna no kanban. Os modelos de mensagem saem quando a OS entra neste status."
          : nFolhas + " folha(s) estão neste status agora. Mudar o nome ou a cor muda o que elas mostram."}</p>
      </div>

      <section className="rep-stf-sec">
        <h4>Identificação</h4>
        <div className="rep-fields">
          {Input && <Input label="Nome do status *" value={f.nome} error={erros.nome} placeholder="Ex.: Aguardando aprovação"
            onChange={(e) => set("nome", e)} />}
          <div className="rep-stf-cor">
            {Input && <Input label="Cor" value={f.cor} placeholder="#2563eb" onChange={(e) => set("cor", e)} />}
            <span className="rep-st rep-stf-swatch" style={{ "--st": f.cor }} aria-label={"Amostra da cor " + f.cor}><i />{f.nome || "Amostra"}</span>
          </div>
          {Input && <Input label="Ordem" value={f.ordem} error={erros.ordem} placeholder="1" inputMode="numeric"
            onChange={(e) => set("ordem", e)} help="Posição no kanban e na lista. Número menor aparece antes." />}
        </div>
        {Checkbox && <Checkbox checked={f.concluido} onChange={(e) => set("concluido", e)}
          label="Marcar este status como conclusão da folha"
          sublabel="Folha neste status sai de “Pendentes” e conta como concluída." />}
      </section>

      <section className="rep-stf-sec">
        <h4>Aviso ao cliente</h4>
        {Select && <Select label="Avisar o cliente quando a OS entrar aqui" value={f.aviso}
          onChange={(e) => set("aviso", e)} options={AVISOS} />}
        <p className="rep-nota">Pelo WhatsApp, só para cliente que não recusou receber mensagens. Cada número tem dois
          modelos aprovados — pronto e aguardando peças —, por isso são as duas únicas opções.</p>
      </section>

      <section className="rep-stf-sec">
        <h4>Modelos de mensagem</h4>
        <div className="rep-stf-tags" role="group" aria-label="Etiquetas disponíveis">
          <span>Etiquetas (inserem no campo “{campoAlvo === "sms" ? "SMS" : campoAlvo === "assunto" ? "Assunto" : "Corpo"}”):</span>
          {/* TagChip do DS só exibe (prop label, sem clique) — inserir pede botão. */}
          {TAGS.map((t) => <button key={t} type="button" className="mono" onClick={() => inserir(t)}
            aria-label={"Inserir etiqueta " + t}>{t}</button>)}
        </div>
        {Textarea && <Textarea label="Modelo de SMS" rows={3} value={f.sms} onFocus={() => setCampoAlvo("sms")}
          onChange={(e) => set("sms", e)} placeholder="Olá {customer_name}, sua OS {job_sheet_no} está em {status}." />}
        {Input && <Input label="Assunto do e-mail" value={f.assunto} onFocus={() => setCampoAlvo("assunto")}
          onChange={(e) => set("assunto", e)} placeholder="{status} — OS {job_sheet_no}" />}
        {Textarea && <Textarea label="Corpo do e-mail" rows={5} value={f.corpo} onFocus={() => setCampoAlvo("corpo")}
          onChange={(e) => set("corpo", e)} />}

        <div className="rep-stf-prev" aria-live="polite">
          <b>Como o cliente lê (OS de exemplo)</b>
          <p><span className="rep-stf-canal">SMS</span>{preencher(f.sms, f.nome) || <i>sem modelo de SMS</i>}</p>
          <p><span className="rep-stf-canal">E-mail</span><b>{preencher(f.assunto, f.nome) || "sem assunto"}</b></p>
          {f.corpo && <p className="rep-stf-corpo">{preencher(f.corpo, f.nome)}</p>}
        </div>
      </section>

      <div className="rep-cfg-acoes">
        {Button && <Button variant="ghost" onClick={onVoltar}>Cancelar</Button>}
        {Button && <Button variant="primary" onClick={salvar}>{modo === "novo" ? "Salvar status" : "Atualizar status"}</Button>}
      </div>
    </div>
  );
}

window.RepairStatusForm = { StatusPagina };
})();
