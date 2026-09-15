// cms-extras.jsx — Onda 1 do refino do módulo Site (CMS): as telas que existem no git
// e faltavam no F1.
//   Leads    → POST /c/submit-contact-form (SubmitContactFormRequest + CmsLeadService):
//              name · email · mobile · message + honeypot _gotcha, notificação pro
//              notifiable_email e log com PII redactada.
//   Módulo   → /cms/install (InstallController: instalar/atualizar/desinstalar, versão
//              config('cms.module_version')), cms:health (3 checks, 03:30 BRT) e
//              cms:import-wp-officeimpresso (conexão WP, limite, dry-run, idempotente).
// Expõe window.CmsExtras = { Leads, Modulo }.
(() => {
const { useState, useMemo } = React;
const { Kpis, Kpi, Nota, Vazio, Confirm, Sw } = window.AcessosDS;

const initials = (n) => n.split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
const avColor = (n) => { const h = [...n].reduce((a, c) => a + c.charCodeAt(0), 0) % 360; return { bg: `oklch(0.92 0.04 ${h})`, fg: `oklch(0.42 0.13 ${h})` }; };

// ── Leads capturados no site ──
const LEADS = [
  { id: 412, nome: "Marcos Vinícius Prado", email: "marcos@grafikaprado.com.br", tel: "(11) 98812-4409", origem: "/c/contact-us", data: "19/08/2026 08:41", situacao: "novo", notificado: true,
    msg: "Tenho uma gráfica com 4 pessoas e hoje uso planilha pro orçamento. Queria entender como funciona o cálculo por m² e se dá pra emitir NF-e junto." },
  { id: 411, nome: "Simone Aparecida Reis", email: "simone.reis@lojareis.com", tel: "(19) 99741-2280", origem: "/c/page/planos-e-precos", data: "18/08/2026 17:12", situacao: "respondido", notificado: true,
    msg: "Qual plano cobre duas lojas? Preciso ver estoque separado por loja e o mesmo cadastro de cliente." },
  { id: 410, nome: "Oficina Martinho", email: "contato@oficinamartinho.com.br", tel: "(11) 4127-0090", origem: "/c/contact-us", data: "18/08/2026 11:35", situacao: "cliente", notificado: true,
    msg: "Já uso o sistema na oficina. Quero saber se dá pra abrir a vistoria pelo celular do técnico." },
  { id: 409, nome: "Débora Nunes", email: "debora@estudionunes.art.br", tel: "", origem: "/c/blog/como-calcular-preco-por-m2-sem-perder-margem-21", data: "17/08/2026 20:04", situacao: "novo", notificado: true,
    msg: "O post do m² salvou meu orçamento. Vocês atendem estúdio pequeno, de uma pessoa só?" },
  { id: 408, nome: "Rogério Pinto", email: "rogerio@pintocomunicacao.com", tel: "(21) 98330-7712", origem: "/c/contact-us", data: "15/08/2026 09:22", situacao: "sem retorno", notificado: false,
    msg: "Preciso de proposta pra 12 usuários. Podem me ligar de manhã?" },
];
const SIT_LEAD = {
  novo: { l: "Novo", cls: "warn" }, respondido: { l: "Respondido", cls: "on" },
  cliente: { l: "Virou cliente", cls: "sys" }, "sem retorno": { l: "Sem retorno", cls: "off" },
};

function LeadDrawer({ d, onClose }) {
  const [nota, setNota] = useState("");
  const [feito, setFeito] = useState(null);
  const c = avColor(d.nome);
  const s = SIT_LEAD[d.situacao];
  return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="os-drawer cms-drawer" data-screen-label="Site (CMS) · Lead">
        <div className="os-drawer-head">
          <div className="os-drawer-head-l">
            <div className="os-drawer-id">Lead #{d.id}</div>
            <h2>{d.nome}</h2>
            <p>{d.data} · veio de <span className="cms-slug">{d.origem}</span></p>
          </div>
          <div className="os-drawer-head-r">
            <span className={`cms-pill ${s.cls}`}>{s.l}</span>
            <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          </div>
        </div>
        <div className="os-drawer-body">
          <div className="os-drawer-section">
            <h3>Contato</h3>
            <div className="cms-lead-id">
              <div className="cms-card-av" style={{ background: c.bg, color: c.fg }}>{initials(d.nome)}</div>
              <div>
                <b>{d.email}</b>
                <small>{d.tel || "sem telefone informado"}</small>
              </div>
            </div>
            {!d.notificado &&
            <div style={{ marginTop: 10 }}>
              <Nota tone="warn" title="O aviso por e-mail não saiu">
                O endereço de aviso estava vazio quando este lead chegou. Preencha em Detalhes do site → Aplicação para não perder o próximo.
              </Nota>
            </div>}
          </div>
          <div className="os-drawer-section">
            <h3>Mensagem</h3>
            <p className="cms-lead-msg">{d.msg}</p>
            <small className="help" style={{ display: "block", marginTop: 8, fontSize: 11, color: "var(--text-mute)" }}>
              Guardado por 24 meses e depois eliminado (política do módulo: 730 dias). O lead pode pedir acesso, correção ou exclusão a qualquer momento (LGPD Art. 18).
            </small>
          </div>
          <div className="os-drawer-section">
            <h3>Anotação interna</h3>
            <div className="cms-f">
              <textarea rows="3" value={nota} onChange={(e) => setNota(e.target.value)} placeholder="O que ficou combinado…" />
            </div>
          </div>
          {feito &&
          <div className="os-drawer-section"><Nota tone="success" title={feito}>Feito.</Nota></div>}
        </div>
        <div className="os-drawer-actions">
          <button className="os-btn ghost danger" onClick={() => setFeito("Marcado como spam e removido da lista.")}>Marcar como spam</button>
          <span style={{ marginLeft: "auto" }}></span>
          <button className="os-btn" onClick={() => window.__selectRoute?.("crm")}>Abrir no CRM</button>
          <button className="os-btn primary" onClick={() => setFeito("Lead virou oportunidade no CRM.")}>Criar oportunidade</button>
        </div>
      </div>
    </>
  );
}

function Leads() {
  const [q, setQ] = useState("");
  const [fSit, setFSit] = useState("all");
  const [aberto, setAberto] = useState(null);
  const rows = useMemo(() => LEADS.filter((d) =>
    (fSit === "all" || d.situacao === fSit) &&
    (d.nome + d.email + d.msg + d.origem).toLowerCase().includes(q.trim().toLowerCase())
  ), [q, fSit]);

  return (
    <>
      <Kpis>
        <Kpi v={LEADS.length} l="Leads no mês" />
        <Kpi v={LEADS.filter((d) => d.situacao === "novo").length} l="Aguardando resposta" tone="warning" />
        <Kpi v={LEADS.filter((d) => d.situacao === "cliente").length} l="Viraram cliente" tone="success" />
        <Kpi v={38} l="Bots barrados" sub="campo-armadilha + limite por IP" />
      </Kpis>
      <div className="cms-toolbar">
        <div className="cms-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nome, e-mail ou mensagem…" />
        </div>
        <div className="cms-chips">
          {[["all", "Todos"], ["novo", "Novos"], ["respondido", "Respondidos"], ["cliente", "Viraram cliente"], ["sem retorno", "Sem retorno"]].map(([k, l]) => (
            <button key={k} className={`cms-chip ${fSit === k ? "active" : ""}`} onClick={() => setFSit(k)}>{l}</button>
          ))}
        </div>
        <span className="cms-count">{rows.length} de {LEADS.length}</span>
      </div>
      <div className="os-table-wrap">
        <table className="os-table">
          <thead><tr><th>Quem</th><th>Mensagem</th><th>Veio de</th><th>Situação</th><th>Quando</th></tr></thead>
          <tbody>
            {rows.map((d) => {
              const c = avColor(d.nome); const s = SIT_LEAD[d.situacao];
              return (
                <tr key={d.id} className="os-row" onClick={() => setAberto(d)}>
                  <td>
                    <div className="cms-lead-id">
                      <div className="cms-card-av sm" style={{ background: c.bg, color: c.fg }}>{initials(d.nome)}</div>
                      <div><b>{d.nome}</b><small>{d.email}</small></div>
                    </div>
                  </td>
                  <td><span className="cms-lead-cut">{d.msg}</span></td>
                  <td><span className="cms-slug">{d.origem}</span></td>
                  <td><span className={`cms-pill ${s.cls}`}>{s.l}</span></td>
                  <td><span className="cms-prio">{d.data}</span></td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {rows.length === 0 && <Vazio title="Nenhum lead com esse filtro." description="Tire o filtro de situação ou limpe a busca." />}
      </div>
      {aberto && <LeadDrawer d={aberto} onClose={() => setAberto(null)} />}
    </>
  );
}

// ── Módulo: instalação · saúde · importação ──
const CHECKS = [
  { k: "schema_cms_pages", l: "Estrutura do banco", ok: true, msg: "cms_pages presente com as colunas canônicas" },
  { k: "pages_published", l: "Páginas publicadas", ok: true, msg: "11 páginas publicadas" },
  { k: "last_update_age", l: "Idade da última edição", ok: true, msg: "última edição há 1 dia" },
];

function Modulo() {
  const [conf, setConf] = useState(null);
  const [imp, setImp] = useState({ conexao: "wp_officeimpresso", limite: "0", dry: true, estado: "parado", res: null });
  const rodar = () => {
    setImp((s) => ({ ...s, estado: "rodando", res: null }));
    setTimeout(() => setImp((s) => ({ ...s, estado: "pronto", res: { achados: 63, paginas: 9, posts: 41, pulados: 13 } })), 900);
  };
  const okTudo = CHECKS.every((c) => c.ok);

  return (
    <div className="cms-cfg-pane cms-mod">
      <h2>Módulo</h2>
      <p className="lead">Instalação, verificação diária e importação do site antigo. Nada aqui muda o conteúdo publicado.</p>

      <div className="cms-mod-card">
        <div className="cms-mod-h">
          <div>
            <b>Site (CMS)</b>
            <small>Instalado · versão <span className="cms-slug">1.0</span> · o site público responde em oimpresso.com</small>
          </div>
          <span className="cms-pill on">Ativo</span>
        </div>
        <div className="cms-mod-acts">
          <button className="os-btn sm" onClick={() => setConf("atualizar")}>Atualizar módulo</button>
          <button className="os-btn sm ghost danger" onClick={() => setConf("desinstalar")}>Desinstalar</button>
        </div>
      </div>

      <div className="cms-mod-card">
        <div className="cms-mod-h">
          <div>
            <b>Verificação diária</b>
            <small>Roda sozinha às 03:30. Se algo falhar, o aviso vai pro registro de governança.</small>
          </div>
          <span className={`cms-pill ${okTudo ? "on" : "warn"}`}>{okTudo ? "Tudo certo" : "Com pendência"}</span>
        </div>
        <div className="cms-blocks">
          {CHECKS.map((c) => (
            <div key={c.k} className="cms-block">
              <span className={`cms-dot ${c.ok ? "ok" : "bad"}`}></span>
              <div><b>{c.l}</b><small>{c.msg}</small></div>
              <span className="cms-prio">{c.k}</span>
            </div>
          ))}
        </div>
        <div className="cms-mod-acts"><button className="os-btn sm">Verificar agora</button></div>
      </div>

      <div className="cms-mod-card">
        <div className="cms-mod-h">
          <div>
            <b>Importar do site antigo (WordPress)</b>
            <small>Traz páginas e posts publicados com mais de 100 caracteres. Rodar de novo não duplica — a chave é o endereço.</small>
          </div>
        </div>
        <div className="cms-f-row">
          <div className="cms-f">
            <label>Conexão do banco</label>
            <input type="text" value={imp.conexao} onChange={(e) => setImp((s) => ({ ...s, conexao: e.target.value }))} />
            <small className="help">Configurada no .env (WP_OFFICEIMPRESSO_DB_*).</small>
          </div>
          <div className="cms-f">
            <label>Limite de itens</label>
            <input type="number" value={imp.limite} onChange={(e) => setImp((s) => ({ ...s, limite: e.target.value }))} />
            <small className="help">0 traz tudo.</small>
          </div>
        </div>
        <Sw on={imp.dry} onToggle={() => setImp((s) => ({ ...s, dry: !s.dry }))}
          label="Só simular (não grava nada)"
          sub="Recomendado na primeira vez: mostra o que entraria sem tocar no conteúdo do site." />
        <div className="cms-mod-acts">
          <button className="os-btn sm primary" disabled={imp.estado === "rodando"} onClick={rodar}>
            {imp.estado === "rodando" ? "Lendo o site antigo…" : imp.dry ? "Simular importação" : "Importar agora"}
          </button>
        </div>
        {imp.res &&
        <div style={{ marginTop: 12 }}>
          <Nota tone={imp.dry ? "info" : "success"} title={imp.dry ? "Simulação concluída" : "Importação concluída"}>
            {imp.res.achados} itens encontrados: {imp.res.paginas} páginas e {imp.res.posts} publicações entrariam, {imp.res.pulados} pulados por já existirem ou não terem conteúdo.
            {imp.dry ? " Nada foi gravado — desligue a simulação para valer." : " Revise o conteúdo antes de publicar."}
          </Nota>
        </div>}
      </div>

      {conf &&
      <Confirm open title={conf === "atualizar" ? "Atualizar o módulo Site?" : "Desinstalar o módulo Site?"}
        cta={conf === "atualizar" ? "Atualizar" : "Desinstalar"} ctaTone={conf === "atualizar" ? "primary" : "danger"}
        onClose={() => setConf(null)} onConfirm={() => setConf(null)}>
        {conf === "atualizar"
          ? "Roda as migrações pendentes do módulo. O site fica fora do ar por alguns segundos."
          : "O site público sai do ar imediatamente. As páginas ficam no banco, mas ninguém consegue abrir nenhum endereço."}
      </Confirm>}
    </div>
  );
}

window.CmsExtras = { Leads, Modulo };
})();
