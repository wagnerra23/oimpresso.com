// notificacoes-page.jsx — Modelos de notificação (espelho do módulo /notification-templates
// do UltimatePOS: NotificationTemplateController + NotificationTemplate.php + views/notification_template/*).
// Campos fiéis: subject · cc · bcc · email_body (HTML, TinyMCE no legado) · sms_body · whatsapp_text ·
// auto_send / auto_send_sms / auto_send_wa_notif, nos grupos Notificações / Cliente / Fornecedor com as extra_tags.
// Copy traduzida pro PT-BR (o seed do repo vem em inglês — não serve como UI cliente-facing).
// v2: editor HTML (visual/código), validação de tags, GSM-7 no SMS, teste de envio, busca no rail,
// selos vazio/editado, contador de alterações e prévia de WhatsApp em telefone.
// v3 — DS vivo (mesma decisão da onda 4 do Superadmin e do grupo Usuários): Switch · Alert · Toast ·
// Tooltip · Input vêm do bundle bound (via acessos-ds.jsx quando já há adaptador com fallback).
// Seguem do SHELL de propósito: rail, segmented de canal, chips de tag, tabela/botões e a prévia —
// trocar só nesta tela criaria dois padrões. Os campos que recebem inserção de tag (assunto, SMS,
// WhatsApp) ficam nativos porque o Input/Textarea do DS não expõe ref/seleção (registrado como
// pedido de DS no handoff). Âncoras data-contract declaradas pro Contrato de Tela (ADR 0286).
// Expõe window.NotificacoesPage.
(() => {
const { useState, useRef, useMemo, useEffect } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const ADS = () => window.AcessosDS || {};

// ── grupos de tags (extra_tags do Model) ── fams = famílias de campos personalizados
const G_EMPRESA = { g: "Empresa", tags: ["{business_name}", "{business_logo}"] };
const G_LOCAL = { g: "Local", tags: ["{location_name}", "{location_address}", "{location_email}", "{location_phone}"], fams: [["location_custom_field", 4]] };
const G_CONTATO = { g: "Contato", tags: ["{contact_name}", "{contact_business_name}"], fams: [["contact_custom_field", 10]] };

const TEMPLATES = [
  { key: "send_ledger", grupo: "Notificações", name: "Extrato do cliente", canais: ["email"],
    quando: "Enviado da ficha do cliente, quando você aciona “Enviar extrato”.",
    tags: [G_EMPRESA, { g: "Saldo", tags: ["{balance_due}"] }, G_CONTATO],
    d: { assunto: "Seu extrato — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Segue o seu extrato. Saldo em aberto: <strong>{balance_due}</strong>.</p><p>Qualquer dúvida, é só responder este e-mail.</p><p>{business_name}</p>",
      sms: "", wa: "" } },

  { key: "new_sale", grupo: "Cliente", name: "Nova venda", canais: ["email", "sms", "wa"], auto: true,
    quando: "Pode sair sozinha ao finalizar a venda — marque os canais abaixo.",
    ajuda: "Com envio automático ligado, a notificação sai no momento em que a venda é finalizada (precisa de e-mail/celular no cadastro do cliente).",
    tags: [G_EMPRESA, { g: "Documento", tags: ["{invoice_number}", "{invoice_url}", "{total_amount}", "{paid_amount}", "{due_amount}", "{cumulative_due_amount}", "{due_date}"] }, G_LOCAL, G_CONTATO,
      { g: "Venda", tags: [], fams: [["sell_custom_field", 4], ["shipping_custom_field", 5]] }],
    d: { assunto: "Obrigado pela compra — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Sua venda <strong>{invoice_number}</strong> foi registrada.</p><ul><li>Total: {total_amount}</li><li>Pago: {paid_amount}</li><li>Em aberto: {due_amount}</li></ul><p>Documento: <a href=\"{invoice_url}\">{invoice_url}</a></p><p>Obrigado por comprar com a gente.</p><p>{business_name}</p>",
      sms: "{contact_name}, sua venda {invoice_number} foi registrada. Total {total_amount}. Obrigado! {business_name}",
      wa: "Olá {contact_name}! Sua venda {invoice_number} foi registrada — total {total_amount}, em aberto {due_amount}. Documento: {invoice_url}" } },

  { key: "payment_received", grupo: "Cliente", name: "Pagamento recebido", canais: ["email", "sms", "wa"],
    quando: "Enviado quando você registra um recebimento no financeiro.",
    tags: [G_EMPRESA, { g: "Pagamento", tags: ["{invoice_number}", "{payment_ref_number}", "{received_amount}"] }, G_CONTATO],
    d: { assunto: "Pagamento recebido — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Recebemos seu pagamento de <strong>{received_amount}</strong> (ref. {payment_ref_number}) referente ao documento {invoice_number}.</p><p>Obrigado.</p><p>{business_name}</p>",
      sms: "{contact_name}, recebemos seu pagamento de {received_amount}. Obrigado! {business_name}",
      wa: "Olá {contact_name}! Recebemos seu pagamento de {received_amount} (ref. {payment_ref_number}). Obrigado!" } },

  { key: "payment_reminder", grupo: "Cliente", name: "Lembrete de pagamento", canais: ["email", "sms", "wa"], auto: true,
    quando: "Pode sair sozinha nos títulos vencidos — a rotina roda uma vez por dia.",
    ajuda: "O disparo automático usa a rotina diária de cobrança: um lembrete por título vencido, no máximo um por dia por cliente.",
    tags: [G_EMPRESA, { g: "Título", tags: ["{invoice_number}", "{due_amount}", "{cumulative_due_amount}", "{due_date}"] }, G_CONTATO],
    d: { assunto: "Lembrete de pagamento — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Consta em aberto o valor de <strong>{due_amount}</strong> do documento {invoice_number}, com vencimento em {due_date}.</p><p>Total em aberto na sua conta: {cumulative_due_amount}.</p><p>Se já pagou, desconsidere e nos avise.</p><p>{business_name}</p>",
      sms: "{contact_name}, consta em aberto {due_amount} (venc. {due_date}). {business_name}",
      wa: "Olá {contact_name}! Consta em aberto {due_amount} do documento {invoice_number}, venc. {due_date}. Se já pagou, nos avise." } },

  { key: "new_quotation", grupo: "Cliente", name: "Novo orçamento", canais: ["email", "sms", "wa"],
    quando: "Enviado quando você manda o orçamento pro cliente.",
    tags: [G_EMPRESA, { g: "Orçamento", tags: ["{invoice_number}", "{total_amount}", "{quote_url}"] }, G_LOCAL, G_CONTATO],
    d: { assunto: "Seu orçamento — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Seu orçamento <strong>{invoice_number}</strong> está pronto. Total: {total_amount}</p><p>Ver o orçamento: <a href=\"{quote_url}\">{quote_url}</a></p><p>Qualquer ajuste, é só responder.</p><p>{business_name}</p>",
      sms: "{contact_name}, seu orçamento {invoice_number} está pronto. Total {total_amount}. {business_name}",
      wa: "Olá {contact_name}! Orçamento {invoice_number} pronto — total {total_amount}. Ver: {quote_url}" } },

  { key: "new_booking", grupo: "Cliente", name: "Nova reserva", canais: ["email", "sms", "wa"],
    quando: "Enviado quando uma reserva é confirmada (módulo de agenda).",
    tags: [G_EMPRESA, { g: "Reserva", tags: ["{table}", "{start_time}", "{end_time}", "{service_staff}", "{correspondent}"] }, G_LOCAL, G_CONTATO],
    d: { assunto: "Reserva confirmada — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Sua reserva está confirmada.</p><ul><li>Quando: {start_time} até {end_time}</li><li>Onde: {location_name}</li><li>Atendimento: {service_staff}</li></ul><p>{business_name}</p>",
      sms: "{contact_name}, reserva confirmada: {start_time} até {end_time} em {location_name}.",
      wa: "Olá {contact_name}! Reserva confirmada: {start_time} até {end_time} em {location_name}." } },

  { key: "new_order", grupo: "Fornecedor", name: "Novo pedido", canais: ["email", "sms", "wa"],
    quando: "Enviado ao fornecedor quando você lança a compra.",
    tags: [G_EMPRESA, { g: "Pedido", tags: ["{order_ref_number}", "{total_amount}", "{received_amount}", "{due_amount}"] }, G_LOCAL, G_CONTATO],
    d: { assunto: "Novo pedido — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Temos um novo pedido, referência <strong>{order_ref_number}</strong>, no valor de {total_amount}.</p><p>Por favor, confirme o prazo de entrega.</p><p>{business_name}</p>",
      sms: "Novo pedido {order_ref_number} — {total_amount}. Confirme o prazo. {business_name}",
      wa: "Olá {contact_name}! Novo pedido {order_ref_number} no valor de {total_amount}. Consegue confirmar o prazo?" } },

  { key: "payment_paid", grupo: "Fornecedor", name: "Pagamento efetuado", canais: ["email", "sms", "wa"],
    quando: "Enviado quando você paga um título do fornecedor.",
    tags: [G_EMPRESA, { g: "Pagamento", tags: ["{order_ref_number}", "{payment_ref_number}", "{paid_amount}"] }, G_CONTATO],
    d: { assunto: "Pagamento efetuado — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Pagamos <strong>{paid_amount}</strong> referente ao pedido {order_ref_number} (ref. {payment_ref_number}).</p><p>{business_name}</p>",
      sms: "Pagamos {paid_amount} do pedido {order_ref_number}. {business_name}",
      wa: "Olá {contact_name}! Pagamos {paid_amount} referente ao pedido {order_ref_number}." } },

  { key: "items_received", grupo: "Fornecedor", name: "Itens recebidos", canais: ["email", "sms", "wa"],
    quando: "Enviado quando a compra é totalmente recebida.",
    tags: [G_EMPRESA, { g: "Pedido", tags: ["{order_ref_number}"] }, G_CONTATO],
    d: { assunto: "Itens recebidos — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Recebemos todos os itens do pedido {order_ref_number}. Obrigado pelo atendimento.</p><p>{business_name}</p>",
      sms: "Recebemos todos os itens do pedido {order_ref_number}. Obrigado! {business_name}",
      wa: "Olá {contact_name}! Recebemos todos os itens do pedido {order_ref_number}. Obrigado!" } },

  { key: "items_pending", grupo: "Fornecedor", name: "Itens pendentes", canais: ["email", "sms", "wa"],
    quando: "Enviado quando parte do pedido não chegou.",
    tags: [G_EMPRESA, { g: "Pedido", tags: ["{order_ref_number}"] }, G_CONTATO],
    d: { assunto: "Itens pendentes — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Ainda faltam itens do pedido {order_ref_number}. Pode nos dar uma previsão?</p><p>{business_name}</p>",
      sms: "Faltam itens do pedido {order_ref_number}. Pode dar uma previsão? {business_name}",
      wa: "Olá {contact_name}! Ainda faltam itens do pedido {order_ref_number}. Consegue nos dar uma previsão?" } },

  { key: "purchase_order", grupo: "Fornecedor", name: "Ordem de compra", canais: ["email", "sms", "wa"],
    quando: "Enviado com a ordem de compra em anexo.",
    tags: [G_EMPRESA, { g: "Pedido", tags: ["{order_ref_number}"] }, G_CONTATO],
    d: { assunto: "Nova ordem de compra — {business_name}", cc: "", bcc: "",
      corpo: "<p>Olá {contact_name},</p><p>Segue a ordem de compra <strong>{order_ref_number}</strong> em anexo.</p><p>{business_name}</p>",
      sms: "Nova ordem de compra {order_ref_number}. {business_name}",
      wa: "Olá {contact_name}! Segue a ordem de compra {order_ref_number}." } },
];

const GRUPOS = ["Notificações", "Cliente", "Fornecedor"];
const CANAL_LABEL = { email: "E-mail", sms: "SMS", wa: "WhatsApp" };
const CAMPO_CANAL = { email: "corpo", sms: "sms", wa: "wa" };

const AMOSTRA = {
  "{business_name}": "ROTA LIVRE Comunicação Visual", "{business_logo}": "[logo]",
  "{contact_name}": "Martinho Ferreira", "{contact_business_name}": "Oficina Martinho",
  "{invoice_number}": "VD-2318", "{invoice_url}": "oimpresso.com/i/2318", "{quote_url}": "oimpresso.com/o/1174",
  "{total_amount}": "R$ 1.240,00", "{paid_amount}": "R$ 640,00", "{received_amount}": "R$ 640,00",
  "{due_amount}": "R$ 600,00", "{cumulative_due_amount}": "R$ 1.180,00", "{due_date}": "28/08/2026",
  "{balance_due}": "R$ 1.180,00", "{payment_ref_number}": "PG-2026-0812", "{order_ref_number}": "CP-0471",
  "{location_name}": "Loja Centro", "{location_address}": "Av. Brasil, 1.204 — Centro",
  "{location_email}": "contato@rotalivre.com.br", "{location_phone}": "(31) 3222-1180",
  "{table}": "Box 2", "{start_time}": "21/08/2026 08:30", "{end_time}": "21/08/2026 10:00",
  "{service_staff}": "Larissa", "{correspondent}": "Balcão", "{location}": "Loja Centro",
};
const resolve = (s) => (s || "").replace(/\{[a-z_0-9]+\}/g, (t) => AMOSTRA[t] || t);
const semHtml = (h) => (h || "").replace(/<br\s*\/?>/gi, "\n").replace(/<\/(p|li|ul|ol)>/gi, "\n").replace(/<[^>]+>/g, "").replace(/\n{3,}/g, "\n\n").trim();
const vazio = (s) => semHtml(s).length === 0;

// GSM-7: fora desse conjunto o SMS vira UCS-2 (70 caracteres por segmento)
const GSM7 = /^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§¿äöñüà^{}[\]~|€]*$/;

function tagsPermitidas(t) {
  const set = new Set(), fams = [];
  t.tags.forEach((grp) => {
    (grp.tags || []).forEach((x) => set.add(x));
    (grp.fams || []).forEach(([p, n]) => { fams.push(p); for (let i = 1; i <= n; i++) set.add(`{${p}_${i}}`); });
  });
  return { set, fams };
}
function tagsDesconhecidas(texto, t) {
  const { set } = tagsPermitidas(t);
  const achadas = (texto || "").match(/\{[a-z_0-9]*\}/g) || [];
  return [...new Set(achadas.filter((x) => !set.has(x)))];
}
function expandir(grp) {
  const out = [];
  (grp.fams || []).forEach(([p, n]) => { for (let i = 1; i <= n; i++) out.push(`{${p}_${i}}`); });
  return out;
}

// Switch do DS via adaptador do grupo Usuários (acessos-ds.jsx) — fallback = peça do shell
function Sw({ on, onToggle, label, sub }) {
  const A = ADS();
  if (A.Sw) return <div className="nt-sw-ds"><A.Sw on={on} onToggle={onToggle} label={label} sub={sub} /></div>;
  return (
    <button type="button" className={`nt-sw ${on ? "on" : ""}`} onClick={onToggle} aria-pressed={on}>
      <span className="nt-sw-t"><span /></span>
      <span className="nt-sw-l">{label}{sub && <small>{sub}</small>}</span>
    </button>
  );
}
// Dica acessível (DS Tooltip) — sem ele, title nativo
function Dica({ content, children }) {
  const { Tooltip } = ds();
  if (!Tooltip) return <span title={typeof content === "string" ? content : undefined}>{children}</span>;
  return <Tooltip content={content}>{children}</Tooltip>;
}
// Campo de e-mail simples: Input do DS (não recebe inserção de tag)
function CampoDS({ label, value, placeholder, onChange, help }) {
  const { Input } = ds();
  if (!Input) return (
    <div className="cms-field"><label>{label}</label>
      <input value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
      {help && <small>{help}</small>}</div>
  );
  return <Input label={label} value={value} placeholder={placeholder} help={help} onChange={(e) => onChange(e.target.value)} />;
}

// ── Editor do corpo do e-mail: visual (contentEditable) ou código (HTML cru) ──
function CorpoEmail({ html, onHtml, modo, api, onFoco }) {
  const ref = useRef(null);
  const range = useRef(null);
  const meu = useRef(html);
  useEffect(() => { if (modo === "visual" && ref.current) { ref.current.innerHTML = html; meu.current = html; } }, [modo]);
  useEffect(() => { if (modo === "visual" && ref.current && html !== meu.current) { ref.current.innerHTML = html; meu.current = html; } }, [html, modo]);

  const guardar = () => {
    const s = window.getSelection();
    if (s && s.rangeCount && ref.current && ref.current.contains(s.anchorNode)) range.current = s.getRangeAt(0).cloneRange();
  };
  const restaurar = () => {
    if (!ref.current) return;
    ref.current.focus();
    if (range.current) { const s = window.getSelection(); s.removeAllRanges(); s.addRange(range.current); }
  };
  api.current = {
    inserir: (tag) => {
      if (modo !== "visual") return false;
      restaurar();
      document.execCommand("insertText", false, tag);
      guardar();
      const v = ref.current.innerHTML; meu.current = v; onHtml(v);
      return true;
    },
    cmd: (c) => {
      restaurar();
      if (c === "createLink") {
        const url = window.prompt("Endereço do link (pode usar tag, ex. {invoice_url})", "{invoice_url}");
        if (!url) return;
        document.execCommand("createLink", false, url);
      } else document.execCommand(c, false, null);
      const v = ref.current.innerHTML; meu.current = v; onHtml(v);
    },
  };

  if (modo === "codigo") {
    return <textarea className="nt-html" rows={14} value={html} onFocus={onFoco} onChange={(e) => { meu.current = e.target.value; onHtml(e.target.value); }} spellCheck="false" />;
  }
  return (
    <div ref={ref} className="nt-rte" contentEditable suppressContentEditableWarning
      onInput={(e) => { const v = e.currentTarget.innerHTML; meu.current = v; onHtml(v); }}
      onKeyUp={guardar} onMouseUp={guardar} onBlur={guardar} onFocus={onFoco} />
  );
}

function NotificacoesPage() {
  const inicial = useMemo(() => {
    const o = {};
    TEMPLATES.forEach((t) => { o[t.key] = { ...t.d, autoEmail: false, autoSms: false, autoWa: false }; });
    o.new_sale.autoEmail = true;
    o.payment_reminder.autoEmail = true;
    o.payment_reminder.autoWa = true;
    return o;
  }, []);
  const [salvo, setSalvo] = useState(inicial);
  const [estado, setEstado] = useState(inicial);
  const [sel, setSel] = useState("new_sale");
  const [canal, setCanal] = useState("email");
  const [modo, setModo] = useState("visual");
  const [busca, setBusca] = useState("");
  const [abertos, setAbertos] = useState({});
  const [listaAlt, setListaAlt] = useState(false);
  const [toast, setToast] = useState(null);
  const refs = useRef({});
  const rte = useRef({});
  const buscaRef = useRef(null);
  const foco = useRef({ email: "corpo", sms: "sms", wa: "wa" });

  const t = useMemo(() => TEMPLATES.find((x) => x.key === sel), [sel]);
  const v = estado[sel];
  const canalAtivo = t.canais.includes(canal) ? canal : "email";
  const campoCorpo = CAMPO_CANAL[canalAtivo];
  const texto = v[campoCorpo] || "";

  const set = (campo) => (val) => setEstado((s) => ({ ...s, [sel]: { ...s[sel], [campo]: val } }));
  const liga = (campo) => () => setEstado((s) => ({ ...s, [sel]: { ...s[sel], [campo]: !s[sel][campo] } }));

  const alterados = useMemo(() => TEMPLATES.filter((x) => JSON.stringify(estado[x.key]) !== JSON.stringify(salvo[x.key])), [estado, salvo]);
  const mostrar = (msg) => { setToast(msg); window.clearTimeout(mostrar._id); mostrar._id = window.setTimeout(() => setToast(null), 2600); };

  useEffect(() => {
    const h = (e) => {
      const dentro = /^(INPUT|TEXTAREA)$/.test(e.target.tagName) || e.target.isContentEditable;
      if (e.key === "/" && !dentro) { e.preventDefault(); buscaRef.current && buscaRef.current.focus(); }
      if (e.key === "Escape" && e.target === buscaRef.current) { setBusca(""); e.target.blur(); }
    };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, []);

  const inserir = (tag) => {
    const alvo = foco.current[canalAtivo] || campoCorpo;
    if (alvo === "corpo" && rte.current.inserir && rte.current.inserir(tag)) return;
    const el = refs.current[alvo];
    const atual = v[alvo] || "";
    if (!el) { set(alvo)(atual + tag); return; }
    const a = el.selectionStart ?? atual.length, b = el.selectionEnd ?? a;
    set(alvo)(atual.slice(0, a) + tag + atual.slice(b));
    requestAnimationFrame(() => { el.focus(); el.setSelectionRange(a + tag.length, a + tag.length); });
  };

  const puro = canalAtivo === "email" ? semHtml(texto) : texto;
  const resolvido = resolve(puro);
  const limite = GSM7.test(resolvido) ? 160 : 70;
  const segs = Math.max(1, Math.ceil(resolvido.length / limite));
  const desconhecidas = tagsDesconhecidas(texto + " " + (canalAtivo === "email" ? v.assunto : ""), t);
  const autos = t.auto ? [v.autoEmail, v.autoSms, v.autoWa].filter(Boolean).length : 0;
  const fornecedorAviso = t.grupo === "Fornecedor" && t.canais.length > 1;

  const filtrados = (g) => TEMPLATES.filter((x) => x.grupo === g &&
    (busca.trim() === "" || (x.name + " " + x.key).toLowerCase().includes(busca.trim().toLowerCase())));

  const restaurarPadrao = () => { setEstado((s) => ({ ...s, [sel]: { ...t.d, autoEmail: false, autoSms: false, autoWa: false } })); mostrar("Modelo voltou ao padrão de fábrica — falta salvar"); };
  const salvar = () => { setSalvo(estado); setListaAlt(false); mostrar(`${alterados.length} ${alterados.length === 1 ? "modelo salvo" : "modelos salvos"}`); };
  const descartar = () => { setEstado(salvo); setListaAlt(false); };

  return (
    <div className="os-page nt-page" data-screen-label="Sistema · Modelos de notificação">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Modelos de notificação</h1>
          <p>O que o sistema escreve por você — e-mail, SMS e WhatsApp, com as tags do documento</p>
        </div>
        <div className="os-page-h-r">
          {alterados.length > 0 && (
            <div className="nt-alt">
              <button className="os-btn sm nt-alt-b" onClick={() => setListaAlt((x) => !x)}>
                {alterados.length} {alterados.length === 1 ? "modelo alterado" : "modelos alterados"}
              </button>
              {listaAlt && (
                <div className="nt-alt-pop">
                  {alterados.map((x) => (
                    <button key={x.key} onClick={() => { setSel(x.key); setListaAlt(false); }}>{x.name}<small>{x.grupo}</small></button>
                  ))}
                  <button className="nt-alt-desc" onClick={descartar}>Descartar alterações</button>
                </div>
              )}
            </div>
          )}
          <button className="os-btn ghost" onClick={restaurarPadrao}>Restaurar padrão desta</button>
          <button className="os-btn primary" disabled={alterados.length === 0} onClick={salvar}>Salvar</button>
        </div>
      </header>

      <div className="nt-body">
        <nav className="fnc-rail nt-rail" data-contract="lista-modelos">
          <div className="fnc-rail-search">
            <input ref={buscaRef} value={busca} placeholder="Buscar modelo" onChange={(e) => setBusca(e.target.value)} />
            <span className="nt-kbd">/</span>
          </div>
          {GRUPOS.map((g) => {
            const itens = filtrados(g);
            if (!itens.length) return null;
            return (
              <div key={g} className="fnc-rail-dom">
                <span className="fnc-rail-dom-l">{g}</span>
                {itens.map((x) => {
                  const e = estado[x.key];
                  const n = x.auto ? [e.autoEmail, e.autoSms, e.autoWa].filter(Boolean).length : 0;
                  const mudou = JSON.stringify(e) !== JSON.stringify(salvo[x.key]);
                  const semNada = x.canais.every((c) => vazio(e[CAMPO_CANAL[c]]));
                  return (
                    <button key={x.key} className={`fnc-rail-g ${sel === x.key ? "on" : ""}`}
                      onClick={() => { setSel(x.key); setCanal("email"); setModo("visual"); }}>
                      {mudou && <span className="nt-dot-mudou" title="alterado, não salvo" />}
                      <span className="fnc-rail-g-l">{x.name}</span>
                      {semNada && <Dica content="Nenhum canal preenchido — este modelo não é enviado"><span className="nt-selo nt-selo-vazio">vazio</span></Dica>}
                      {n > 0 && <Dica content={`${n} de 3 canais em envio automático`}><span className="nt-selo nt-selo-auto">auto</span></Dica>}
                    </button>
                  );
                })}
              </div>
            );
          })}
          <p className="nt-rail-nota">{TEMPLATES.length} modelos nesta empresa. Modelo vazio não é enviado.</p>
        </nav>

        <div className="nt-pane">
          <div className="nt-pane-h" data-contract="cabecalho-modelo">
            <div className="nt-pane-h-l">
              <h2>{t.name}</h2>
              <p>{t.quando}</p>
            </div>
            <div className="fnc-seg nt-canais">
              {["email", "sms", "wa"].map((c) => (
                <button key={c} className={canalAtivo === c ? "on" : ""} disabled={!t.canais.includes(c)}
                  onClick={() => setCanal(c)}>
                  {CANAL_LABEL[c]}
                  {t.canais.includes(c) && vazio(v[CAMPO_CANAL[c]]) && <i className="nt-seg-vazio" title="vazio">·</i>}
                </button>
              ))}
            </div>
          </div>

          {fornecedorAviso && (ADS().Nota
            ? <div className="nt-alert-ds" data-contract="aviso-logo">
                {React.createElement(ADS().Nota, { tone: "warn", title: "O {business_logo} só é impresso no e-mail" },
                  "Em SMS e WhatsApp a tag sai como texto — deixe fora desses dois canais.")}
              </div>
            : <div className="nt-alert" data-contract="aviso-logo">
                <b>O {"{business_logo}"} só é impresso no e-mail.</b>
                <span>Em SMS e WhatsApp a tag sai como texto — deixe fora desses dois canais.</span>
              </div>)}

          <div className="nt-cols">
            <div className="nt-col-ed">
              {canalAtivo === "email" && (
                <>
                  <div className="cms-f-row one">
                    <div className="cms-field">
                      <label>Assunto</label>
                      <input ref={(el) => (refs.current.assunto = el)} value={v.assunto}
                        onFocus={() => (foco.current.email = "assunto")} onChange={(e) => set("assunto")(e.target.value)} />
                    </div>
                  </div>
                  <div className="cms-f-row two">
                    <CampoDS label="Cópia (CC)" value={v.cc} placeholder="financeiro@suaempresa.com.br" onChange={set("cc")} />
                    <CampoDS label="Cópia oculta (BCC)" value={v.bcc} placeholder="arquivo@suaempresa.com.br" onChange={set("bcc")} />
                  </div>
                </>
              )}

              <div className="cms-field nt-corpo" data-contract="editor-canal">
                <div className="nt-corpo-h">
                  <label>{canalAtivo === "email" ? "Corpo do e-mail" : canalAtivo === "sms" ? "Texto do SMS" : "Texto do WhatsApp"}</label>
                  {canalAtivo === "email" && (
                    <div className="nt-corpo-tools">
                      <div className="nt-rte-bar">
                        <button title="Negrito" disabled={modo !== "visual"} onClick={() => rte.current.cmd("bold")}><b>N</b></button>
                        <button title="Itálico" disabled={modo !== "visual"} onClick={() => rte.current.cmd("italic")}><i>I</i></button>
                        <button title="Lista" disabled={modo !== "visual"} onClick={() => rte.current.cmd("insertUnorderedList")}>≡</button>
                        <button title="Link" disabled={modo !== "visual"} onClick={() => rte.current.cmd("createLink")}>↗</button>
                      </div>
                      <div className="fnc-seg nt-modo">
                        <button className={modo === "visual" ? "on" : ""} onClick={() => setModo("visual")}>Visual</button>
                        <button className={modo === "codigo" ? "on" : ""} onClick={() => setModo("codigo")}>HTML</button>
                      </div>
                    </div>
                  )}
                </div>
                {canalAtivo === "email"
                  ? <CorpoEmail key={sel + modo} html={v.corpo} onHtml={set("corpo")} modo={modo} api={rte} onFoco={() => (foco.current.email = "corpo")} />
                  : <textarea ref={(el) => (refs.current[campoCorpo] = el)} rows={7} value={texto}
                      onFocus={() => (foco.current[canalAtivo] = campoCorpo)} onChange={(e) => set(campoCorpo)(e.target.value)} />}
                <div className="nt-corpo-meta">
                  <span>{puro.length} caracteres</span>
                  {canalAtivo === "sms" && <span className={segs > 1 ? "nt-warn" : ""}>{segs} SMS · {limite} por segmento{limite === 70 ? " (tem acento)" : ""}</span>}
                  {canalAtivo !== "email" && <span>{"{business_logo}"} não vale aqui</span>}
                  {desconhecidas.length > 0 && <span className="nt-warn">tag não reconhecida: {desconhecidas.join(", ")}</span>}
                </div>
              </div>

              <section className="nt-tags" data-contract="tags-disponiveis">
                <h3>Tags disponíveis</h3>
                <p>Clique para inserir no último campo focado — agora em <b>{foco.current[canalAtivo] === "corpo" ? "corpo" : foco.current[canalAtivo]}</b>.</p>
                {t.tags.map((grp) => {
                  const fam = expandir(grp);
                  return (
                    <div key={grp.g} className="nt-tag-g">
                      <span className="nt-tag-g-l">{grp.g}</span>
                      <div className="nt-tag-chips">
                        {(grp.tags || []).map((tag) => <button key={tag} className="nt-chip" onClick={() => inserir(tag)}>{tag}</button>)}
                        {fam.length > 0 && (abertos[grp.g]
                          ? fam.map((tag) => <button key={tag} className="nt-chip nt-mute" onClick={() => inserir(tag)}>{tag}</button>)
                          : <button className="nt-chip nt-mute" onClick={() => setAbertos((s) => ({ ...s, [grp.g]: true }))}>+ {fam.length} personalizados</button>)}
                      </div>
                    </div>
                  );
                })}
              </section>

              {t.auto && (
                <section className="nt-auto" data-contract="envio-automatico">
                  <h3>Envio automático <span className="nt-auto-n">{autos} de 3 canais</span></h3>
                  <div className="nt-auto-lista">
                    <Sw on={v.autoEmail} onToggle={liga("autoEmail")} label="E-mail" sub="Precisa de e-mail no cadastro" />
                    <Sw on={v.autoSms} onToggle={liga("autoSms")} label="SMS" sub="Consome créditos do gateway" />
                    <Sw on={v.autoWa} onToggle={liga("autoWa")} label="WhatsApp" sub="Usa a conexão do Atendimento" />
                  </div>
                  <p className="nt-nota">{t.ajuda}</p>
                </section>
              )}
            </div>

            <aside className="nt-col-prev" data-contract="previa">
              <div className="nt-prev-h">
                <span>Prévia</span>
                <small>tags resolvidas com dados de exemplo</small>
              </div>
              {canalAtivo === "email" && (
                <div className="nt-prev-mail">
                  <div className="nt-prev-mail-h">
                    <b>{resolve(v.assunto) || "(sem assunto)"}</b>
                    <span>ROTA LIVRE &lt;contato@rotalivre.com.br&gt; → martinho@oficina.com.br</span>
                    {(v.cc || v.bcc) && <span className="nt-mono">{v.cc ? `cc ${v.cc}` : ""}{v.cc && v.bcc ? " · " : ""}{v.bcc ? `bcc ${v.bcc}` : ""}</span>}
                  </div>
                  <div className="nt-prev-mail-b" dangerouslySetInnerHTML={{ __html: resolve(v.corpo) || "<p>(vazio)</p>" }} />
                </div>
              )}
              {canalAtivo === "sms" && (
                <div className="nt-prev-chat">
                  <div className="nt-prev-chat-h">SMS para (31) 99871-2204</div>
                  <div className="nt-bolha">{resolvido || "(vazio)"}</div>
                  <div className="nt-prev-chat-f">{resolvido.length} caracteres · {segs} segmento{segs > 1 ? "s" : ""}</div>
                </div>
              )}
              {canalAtivo === "wa" && (
                <div className="nt-fone">
                  <div className="nt-fone-h"><span className="nt-fone-av">MF</span><b>Martinho Ferreira</b><small>online</small></div>
                  <div className="nt-fone-tela">
                    <div className="nt-fone-dia">hoje</div>
                    <div className="nt-bolha nt-wa">{resolvido || "(vazio)"}<span className="nt-bolha-t">08:42 ✓✓</span></div>
                  </div>
                </div>
              )}
              <button className="os-btn sm nt-teste" onClick={() => mostrar(canalAtivo === "email" ? "Teste enviado para wagner@wr2.com.br" : canalAtivo === "sms" ? "SMS de teste enviado para (31) 99871-2204" : "WhatsApp de teste enviado para (31) 99871-2204")}>
                Enviar teste pra mim
              </button>
              <p className="nt-nota">O teste usa os mesmos dados de exemplo da prévia.</p>
            </aside>
          </div>
        </div>
      </div>

      {toast && (ds().Toast
        ? <div className="nt-toast-ds">{React.createElement(ds().Toast, { tone: "ok" }, toast)}</div>
        : <div className="nt-toast">{toast}</div>)}
    </div>
  );
}

window.NotificacoesPage = NotificacoesPage;
})();
