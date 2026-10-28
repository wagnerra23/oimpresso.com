// canais-page.jsx — cadastro de canal de atendimento (/atendimento/canais) no app único.
// Espelha o que existe no main em Modules/Whatsapp/Resources/js/Pages/Atendimento/Channels/Index.tsx
// (lista de canais + diálogo "Adicionar canal" com Tipo, Apelido e campos por tipo) lido junto de
// ChannelsController::availableTypes e ChannelRequest. O que é NOVO aqui (US-WA-329): Instagram
// Direct, Facebook Messenger e E-mail deixam de ser "em breve" e ganham os campos de credencial
// que o backend usa (config_json do canal, cifrado):
//   · Instagram / Messenger → page_access_token, app_secret (opcional: vale o do app), account_id
//     (Page ID / IG ID), verify_token; depois de salvo mostra a URL do webhook para colar na Meta.
//   · E-mail → endereço da caixa (vira o remetente da resposta), SMTP (host, porta, usuário, senha,
//     criptografia) e o token de entrada; depois de salvo mostra a URL de encaminhamento.
// O WhatsApp segue como está no main (Whatsmeow / Meta Cloud / Z-API) e fica de fora do desenho.
// Cenários: normal · vazio · desligado (flag do sistema desligada: tipos novos voltam a "em breve").
// Expõe window.CanaisPage.
(() => {
const { useState, useEffect } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
// Campos lado a lado no e-mail (SMTP host/porta/segurança; usuário/senha). Só layout, sem cor.
const LINHA = { display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))", gap: "12px" };

const TIPOS = [
  { id:"whatsapp_whatsmeow", label:"WhatsApp (QR)", grupo:"WhatsApp", nota:"Escaneie o QR no celular. Já existe no sistema." },
  { id:"whatsapp_meta", label:"WhatsApp Meta Cloud", grupo:"WhatsApp", nota:"API oficial. Já existe no sistema." },
  { id:"instagram", label:"Instagram Direct", grupo:"Social", novo:true, nota:"Mensagens diretas do perfil comercial, pela conta da Meta ligada à página." },
  { id:"messenger", label:"Facebook Messenger", grupo:"Social", novo:true, nota:"Mensagens da página do Facebook." },
  { id:"email_imap", label:"E-mail", grupo:"E-mail", novo:true, nota:"A caixa da empresa: o e-mail chega encaminhado e a resposta sai pelo SMTP dela." },
];

const CANAIS0 = [
  { id:1, label:"Comercial", tipo:"whatsapp_whatsmeow", ident:"+55 47 3333-1010", saude:"conectado", conversas:418 },
  { id:4, label:"@rotalivre", tipo:"instagram", ident:"Conta 17841400000000000", saude:"aguardando 1ª mensagem", conversas:0 },
  { id:6, label:"Contato", tipo:"email_imap", ident:"contato@loja.com.br", saude:"recebendo", conversas:23 },
];

const rotulo = (id) => (TIPOS.find((t) => t.id === id) || {}).label || id;
const urlWebhook = (tipo, uuid) => tipo === "email_imap"
  ? `https://oimpresso.com/api/atendimento/channels/email/${uuid}`
  : `https://oimpresso.com/api/atendimento/channels/meta-graph/${uuid}`;

function Campo({ id, label, ajuda, children }) {
  return (
    <div className="cnl-campo" style={{ marginBottom: "12px" }}>
      <label className="cnx-lbl" htmlFor={id}>{label}</label>
      {children}
      {ajuda && <p className="cnx-help">{ajuda}</p>}
    </div>
  );
}

function Texto({ id, valor, onChange, tipo = "text", ph, obrig }) {
  return <input id={id} type={tipo} className="dsfa cnx-input" value={valor || ""} placeholder={ph}
    aria-required={obrig ? "true" : undefined} onChange={(e) => onChange(e.target.value)}/>;
}

function CamposMeta({ cfg, set, tipo }) {
  const conta = tipo === "instagram" ? "ID da conta do Instagram" : "ID da página";
  return (
    <>
      <Campo id="cnl-token" label="Token da página" ajuda="Gerado no app da Meta, com a permissão de mensagens. Fica cifrado e não é exibido de novo.">
        <Texto id="cnl-token" tipo="password" valor={cfg.page_access_token} onChange={(v) => set({ ...cfg, page_access_token: v })} obrig/>
      </Campo>
      <Campo id="cnl-conta" label={conta} ajuda="Recado de outra conta que chegar por engano é ignorado.">
        <Texto id="cnl-conta" valor={cfg.account_id} onChange={(v) => set({ ...cfg, account_id: v })} ph="17841400000000000"/>
      </Campo>
      <Campo id="cnl-verify" label="Token de verificação" ajuda="Você inventa e cola o mesmo valor no painel da Meta, em Webhooks.">
        <Texto id="cnl-verify" valor={cfg.verify_token} onChange={(v) => set({ ...cfg, verify_token: v })} obrig/>
      </Campo>
      <Campo id="cnl-secret" label="App secret (opcional)" ajuda="Em branco, vale o app secret da instalação.">
        <Texto id="cnl-secret" tipo="password" valor={cfg.app_secret} onChange={(v) => set({ ...cfg, app_secret: v })}/>
      </Campo>
    </>
  );
}

function CamposEmail({ cfg, set }) {
  return (
    <>
      <Campo id="cnl-end" label="Endereço da caixa" ajuda="É o remetente das respostas. O cliente responde para ele.">
        <Texto id="cnl-end" tipo="email" valor={cfg.endereco} onChange={(v) => set({ ...cfg, endereco: v })} ph="contato@loja.com.br" obrig/>
      </Campo>
      <div className="cnl-linha" style={LINHA}>
        <Campo id="cnl-host" label="Servidor SMTP">
          <Texto id="cnl-host" valor={cfg.smtp_host} onChange={(v) => set({ ...cfg, smtp_host: v })} ph="smtp.loja.com.br" obrig/>
        </Campo>
        <Campo id="cnl-porta" label="Porta">
          <Texto id="cnl-porta" valor={cfg.smtp_port} onChange={(v) => set({ ...cfg, smtp_port: v })} ph="587"/>
        </Campo>
        <Campo id="cnl-cripto" label="Segurança">
          <select id="cnl-cripto" className="dsfa cnx-input" value={cfg.smtp_encryption || "tls"} onChange={(e) => set({ ...cfg, smtp_encryption: e.target.value })}>
            <option value="tls">TLS</option><option value="ssl">SSL</option>
          </select>
        </Campo>
      </div>
      <div className="cnl-linha" style={LINHA}>
        <Campo id="cnl-usr" label="Usuário SMTP">
          <Texto id="cnl-usr" valor={cfg.smtp_username} onChange={(v) => set({ ...cfg, smtp_username: v })}/>
        </Campo>
        <Campo id="cnl-pwd" label="Senha SMTP">
          <Texto id="cnl-pwd" tipo="password" valor={cfg.smtp_password} onChange={(v) => set({ ...cfg, smtp_password: v })}/>
        </Campo>
      </div>
      <p className="cnx-help">O e-mail que chega na caixa entra na Caixa Unificada por encaminhamento: depois de salvar, aparece o endereço para configurar no provedor de entrada (Postmark Inbound ou Cloudflare Email Routing).</p>
    </>
  );
}

function NovoCanal({ open, onClose, onSalvar, desligado }) {
  const { Modal } = ds();
  const [tipo, setTipo] = useState("instagram");
  const [apelido, setApelido] = useState("");
  const [cfg, setCfg] = useState({});
  const [tentou, setTentou] = useState(false);
  useEffect(() => { if (open) { setTipo(desligado ? "whatsapp_whatsmeow" : "instagram"); setApelido(""); setCfg({}); setTentou(false); } }, [open, desligado]);
  if (!open) return null;
  const t = TIPOS.find((x) => x.id === tipo);
  const falta = !apelido.trim() ? "Dê um apelido ao canal."
    : (tipo === "instagram" || tipo === "messenger") && (!cfg.page_access_token || !cfg.verify_token) ? "Token da página e token de verificação são obrigatórios."
    : tipo === "email_imap" && (!cfg.endereco || !cfg.smtp_host) ? "Endereço da caixa e servidor SMTP são obrigatórios." : null;

  const corpo = (
    <div className="cnx-form" data-contract="novo-canal-form">
      <Campo id="cnl-tipo" label="Tipo">
        <select id="cnl-tipo" className="dsfa cnx-input" value={tipo} onChange={(e) => { setTipo(e.target.value); setCfg({}); }}>
          {TIPOS.map((x) => (
            <option key={x.id} value={x.id} disabled={x.novo && desligado}>
              {x.label}{x.novo && desligado ? " — em breve" : ""}
            </option>
          ))}
        </select>
      </Campo>
      <p className="cnx-help">{t.nota}</p>
      <Campo id="cnl-apelido" label="Apelido" ajuda="É como o atendente vê o canal no filtro da Caixa Unificada.">
        <Texto id="cnl-apelido" valor={apelido} onChange={setApelido} ph="Comercial · Suporte · Pós-venda" obrig/>
      </Campo>
      {(tipo === "instagram" || tipo === "messenger") && <CamposMeta cfg={cfg} set={setCfg} tipo={tipo}/>}
      {tipo === "email_imap" && <CamposEmail cfg={cfg} set={setCfg}/>}
      {!t.novo && <p className="cnx-help">Os campos do WhatsApp seguem como estão no sistema.</p>}
      {tentou && falta && <p className="cnx-err">{falta}</p>}
    </div>
  );
  const foot = (
    <>
      <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
      <button className="os-btn primary" onClick={() => { if (falta) { setTentou(true); return; } onSalvar({ tipo, apelido: apelido.trim(), cfg }); onClose(); }}>Salvar canal</button>
    </>
  );
  if (!Modal) return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="usr-modal"><h2>Adicionar canal</h2>{corpo}<div className="usr-modal-f">{foot}</div></div>
    </>
  );
  return <Modal open={open} onClose={onClose} title="Adicionar canal" footer={foot}>{corpo}</Modal>;
}

function Conectado({ canal, onClose, toast }) {
  const { Modal } = ds();
  if (!canal) return null;
  const url = urlWebhook(canal.tipo, canal.uuid);
  const email = canal.tipo === "email_imap";
  const corpo = (
    <div className="cnx-form" data-contract="canal-criado">
      <p className="cnx-help">{email
        ? "Configure o provedor de entrada para encaminhar os e-mails desta caixa para o endereço abaixo, com o token de entrada como senha."
        : "No painel da Meta, em Webhooks, cole este endereço e o token de verificação que você definiu. Assine o evento de mensagens."}</p>
      <div className="cnx-fixed"><div><span className="cnx-fixed-l">{email ? "Encaminhar para" : "URL do webhook"}</span><code className="mono">{url}</code></div>
        {email && <div><span className="cnx-fixed-l">Token de entrada</span><code className="mono">gerado ao salvar · copie agora</code></div>}</div>
      <button className="os-btn ghost" onClick={() => { try { navigator.clipboard?.writeText(url); } catch (e) {} toast("Endereço copiado."); }}>Copiar endereço</button>
      <p className="cnx-help">Até a primeira mensagem chegar, o canal aparece como "aguardando 1ª mensagem".</p>
    </div>
  );
  const foot = <button className="os-btn primary" onClick={onClose}>Pronto</button>;
  if (!Modal) return (<><div className="os-drawer-back" onClick={onClose}></div><div className="usr-modal"><h2>Canal criado</h2>{corpo}<div className="usr-modal-f">{foot}</div></div></>);
  return <Modal open onClose={onClose} title={`Canal "${canal.label}" criado`} footer={foot}>{corpo}</Modal>;
}

function CanaisPage() {
  const [cenario, setCenario] = useState("normal");
  const [rows, setRows] = useState(CANAIS0);
  const [novo, setNovo] = useState(false);
  const [criado, setCriado] = useState(null);
  const [toast, setToast] = useState(null);
  useEffect(() => { if (!toast) return; const t = setTimeout(() => setToast(null), 2200); return () => clearTimeout(t); }, [toast]);
  const desligado = cenario === "desligado";
  const lista = cenario === "vazio" ? [] : rows;

  const salvar = ({ tipo, apelido, cfg }) => {
    const c = { id: Date.now(), label: apelido, tipo, ident: cfg.endereco || (cfg.account_id ? `Conta ${cfg.account_id}` : "—"),
      saude: "aguardando 1ª mensagem", conversas: 0, uuid: "9b2e7c1a-4d3f-4e8a-b6c0-1f2d3e4a5b6c" };
    setRows((r) => [...r, c]);
    if (tipo === "instagram" || tipo === "messenger" || tipo === "email_imap") setCriado(c);
    else setToast(`Canal "${apelido}" criado.`);
  };

  return (
    <div className="cnx-page" data-contract="canais-page">
      <div className="cnx-head">
        <h1 className="cnx-h1">Canais de atendimento</h1>
        <div className="cnx-head-r">
          <select className="dsfa cnx-input" value={cenario} onChange={(e) => setCenario(e.target.value)} aria-label="Cenário do protótipo">
            <option value="normal">Cenário: normal</option><option value="vazio">Cenário: nenhum canal</option><option value="desligado">Cenário: canais novos desligados</option>
          </select>
          <button className="os-btn primary" onClick={() => setNovo(true)}>Adicionar canal</button>
        </div>
      </div>
      {lista.length === 0 ? (
        <div className="cnx-vazio"><p>Nenhum canal cadastrado. Conecte o WhatsApp, o Instagram, o Messenger ou o e-mail da empresa: tudo cai na mesma Caixa Unificada.</p></div>
      ) : (
        <table className="os-table" data-contract="canais-lista">
          <thead><tr><th>Apelido</th><th>Tipo</th><th>Identificação</th><th>Situação</th><th className="num">Conversas</th></tr></thead>
          <tbody>
            {lista.map((c) => (
              <tr key={c.id}><td>{c.label}</td><td>{rotulo(c.tipo)}</td><td className="mono">{c.ident}</td><td>{c.saude}</td><td className="num">{c.conversas}</td></tr>
            ))}
          </tbody>
        </table>
      )}
      <NovoCanal open={novo} onClose={() => setNovo(false)} onSalvar={salvar} desligado={desligado}/>
      <Conectado canal={criado} onClose={() => setCriado(null)} toast={setToast}/>
      {toast && <div className="mod-toast">{toast}</div>}
    </div>
  );
}

window.CanaisPage = CanaisPage;
})();
