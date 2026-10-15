// repair-portal.jsx — portal público de acompanhamento de OS (ConsultaOs, D-PORTAL 2026-10-02).
// Refino 2026-10-15: o cliente chega pelo LINK COM CÓDIGO que a loja manda (WhatsApp, recibo com QR,
// e-mail) e vê a OS sem login e SEM cadastro no portal. O código é aleatório e único no sistema, então
// resolve OS + empresa juntas — fecha o [T0] do _saida-04 (nº de OS sequencial cruzava empresas).
// Três entradas: link /os/<código> (abre direto) · código digitado em /consulta-os · nº da OS + série
// (legado, mantido). Casos: cowork-inbox/repair/ConsultaOs-link.casos.md (UC-COS-12..24).
// Payload = whitelist do RepairConsultaOsRepository (UC-COS-04): sem custo, senha, defeitos, cliente.
// A tira .rpl-preview é controle do protótipo. Expõe window.RepPortal.
(() => {
const { useState } = React;
const R = () => window.RepData;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <span aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span> : null; };
const val = (e) => (e && e.target ? e.target.value : e);
const LOJA = "WR2 Comunicação Visual";
const ALFA = "23456789ABCDEFGHJKMNPQRSTUVWXYZ"; // sem 0/O, 1/I/L: o cliente digita do papel
const codigoDe = (f) => { let h = (f.id * 2654435761) >>> 0, s = ""; for (let i = 0; i < 8; i++) { s += ALFA[h % ALFA.length]; h = (Math.imul(h, 1103515245) + 12345) >>> 0; } return s; };
const fmtCod = (c) => c.slice(0, 4) + "-" + c.slice(4);
const limpa = (c) => String(c || "").toUpperCase().replace(/[^0-9A-Z]/g, "");

// Folha do Repair → forma do cartão único (os-acompanhamento.jsx). Só a whitelist do UC-COS-04.
const paraCartao = (f) => {
  const D = R(); const m = D.modeloDe(f.modelo); const st = D.statusDe(f.status);
  const fase = D.faseDe(f.status); const pronto = fase === D.FASES.length - 1;
  const atv = (D.ATIVIDADES[f.id] || []).slice().reverse().map((a) => ({ ev: a.ev, quando: D.d2(a.dia) + " · " + a.hora, nota: a.nota }));
  return { os: f.os, eq: m.dispositivo + " · " + m.marca + " " + m.nome, status: { nome: st.nome, cor: st.cor }, fase, fases: D.FASES,
    prev: D.d2(f.entrega), recebido: D.d2(f.criado), serie: f.serie,
    orc: f.status === 3 ? "aguardando" : f.custo ? "aprovado" : "avaliacao",
    andamento: atv.length ? atv : [{ ev: "Equipamento recebido", quando: D.d2(f.criado) }],
    codigo: fmtCod(codigoDe(f)), prontoHa: pronto && f.status !== 8 ? Math.max(1, D.dias(f.entrega, D.iso(D.HOJE))) : 0,
    entregue: f.status === 8 ? { em: D.d2(f.entrega), garantiaAte: D.d2(D.iso(new Date(new Date(f.entrega).getTime() + 90 * 86400000))) } : null };
};

function Acompanhamento({ f, origem, semCadastro, onAcesso, aprovar, avisar }) {
  const { Button } = DS();
  const Cartao = window.OsAcompanhamento;
  if (!Cartao) return null;
  return (
    <Cartao os={paraCartao(f)} variante="publico" aprovar={aprovar}
      onAprovar={(sim) => avisar && avisar(sim ? "Aprovação registrada. O balcão libera o reparo e avisa pelo WhatsApp." : "Recusa registrada. A loja avisa quando o equipamento puder ser retirado.", "ok")}
      rodape={<>
        {semCadastro && <div className="rpl-acesso" data-contract="consulta-os-acesso">
          <div><b>Acompanhar todas as suas OS num lugar só</b><span>Sem senha: enviamos um código pelo WhatsApp do celular desta OS ({"(14) 9••••-••" + String(f.id).padStart(2, "0")}).</span></div>
          {Button && <Button onClick={onAcesso}>Receber acesso</Button>}
        </div>}
        <p className="rpl-legal">Esta página mostra só o andamento. Valores, peças e dados pessoais ficam na loja.{origem === "link" ? " Link pessoal — não compartilhe." : ""}</p>
      </>} />
  );
}

function PortalConsulta({ folhas, avisar }) {
  const D = R();
  const { Alert, Button, Input, Select, EmptyState, Switch, Logo } = DS();
  const base = folhas && folhas.length ? folhas : D.FOLHAS;
  const exemplo = base.find((f) => !D.statusDe(f.status).concluido && (D.ATIVIDADES[f.id] || []).length) || base[0];
  const concluida = base.find((f) => D.statusDe(f.status).concluido) || base[base.length - 1];
  const [cenario, setCenario] = useState("link");
  const [modo, setModo] = useState("celular");
  const [semCadastro, setSemCadastro] = useState(false);
  const [porCelular, setPorCelular] = useState(false);
  const [entrada, setEntrada] = useState("codigo");
  const [cod, setCod] = useState("");
  const [num, setNum] = useState("");
  const [serie, setSerie] = useState("");
  const [res, setRes] = useState({ f: exemplo, origem: "link" });
  const [erro, setErro] = useState(null);
  const [acesso, setAcesso] = useState(null);
  const [aprovar, setAprovar] = useState(true);
  const [tema, setTema] = useState(document.documentElement.dataset.theme === "light" ? "light" : "dark");
  const trocarTema = (t) => { setTema(t); window.__setTweak ? window.__setTweak("theme", t) : (document.documentElement.dataset.theme = t); };
  const orcamento = base.find((f) => f.status === 3) || exemplo;

  const aplicar = (c) => {
    setCenario(c); setErro(null); setAcesso(null);
    if (c === "link") setRes({ f: exemplo, origem: "link" });
    else if (c === "pronto") setRes({ f: concluida, origem: "link" });
    else if (c === "orcamento") setRes({ f: orcamento, origem: "link" });
    else if (c === "entregue") setRes({ f: base.find((x) => x.status === 8) || concluida, origem: "link" });
    else if (c === "expirado") { setRes(null); setErro("expirado"); }
    else if (c === "invalido") { setRes(null); setErro("invalido"); }
    else { setRes(null); setCod(""); setNum(""); setSerie(""); }
  };

  const buscar = () => {
    setAcesso(null);
    if (entrada === "codigo") {
      const c = limpa(cod);
      if (c.length !== 8) { setErro("formato"); setRes(null); return; }
      const f = base.find((x) => codigoDe(x) === c);
      if (!f) { setErro("invalido"); setRes(null); return; }
      setErro(null); setRes({ f, origem: "codigo" }); return;
    }
    if (!num.trim() || !serie.trim()) { setErro("legado-incompleto"); setRes(null); return; }
    const f = base.find((x) => x.os.toLowerCase() === num.trim().toLowerCase() && x.serie.toLowerCase() === serie.trim().toLowerCase());
    if (!f) { setErro("invalido"); setRes(null); return; }
    setErro(null); setRes({ f, origem: "numero" });
  };

  const url = res && res.origem === "link" ? "oimpresso.com/os/" + codigoDe(res.f) : erro === "expirado" ? "oimpresso.com/os/7QX4MKP2" : erro === "invalido" && cenario === "invalido" ? "oimpresso.com/os/K7PM4QXZ" : "oimpresso.com/consulta-os";
  const MSG = { formato: ["Confira o código", "O código tem 8 letras e números, como está no recibo (ex.: K7PM-4QXR)."],
    invalido: ["Não encontramos esta OS", "Confira o código no recibo ou na mensagem da loja. Se o link veio de nós, peça um novo no balcão."],
    expirado: ["Este link expirou", "O acompanhamento fica disponível até 90 dias depois da entrega. Para uma OS antiga, fale com a loja."],
    "legado-incompleto": ["Faltam dados", "Informe o número da OS e o número de série que estão no recibo."] };

  return (
    <div className="rp-wrap rpl-wrap">
      <div className="rpl-preview" role="region" aria-label="Controles da prévia (só no protótipo)">
        <b>Prévia</b>
        <label>Como o cliente chega
          <select value={cenario} onChange={(e) => aplicar(e.target.value)}>
            <option value="link">Abriu o link recebido</option>
            <option value="digitar">Digitou o código no site</option>
            <option value="pronto">Link de OS pronta</option>
            <option value="orcamento">Orçamento aguardando aprovação</option>
            <option value="entregue">OS entregue (na garantia)</option>
            <option value="expirado">Link expirado</option>
            <option value="invalido">Link com código errado</option>
          </select>
        </label>
        <span className="rpl-seg" role="group" aria-label="Tamanho da prévia">
          <button aria-pressed={modo === "celular"} onClick={() => setModo("celular")}>Celular 390</button>
          <button aria-pressed={modo === "tela"} onClick={() => setModo("tela")}>Tela cheia</button>
        </span>
        <span className="rpl-seg" role="group" aria-label="Tema">
          <button aria-pressed={tema === "light"} onClick={() => trocarTema("light")}>Claro</button>
          <button aria-pressed={tema === "dark"} onClick={() => trocarTema("dark")}>Escuro</button>
        </span>
      </div>

      <div className="rpl-stage">
        <div className={"rp-frame rpl-frame " + modo}>
          <div className="rp-frame-bar"><Ic name="lock" size={11} /><span className="mono">{url}</span></div>
          <div className="rpl-page">
            <header className="rpl-top">{Logo && <Logo size={24} />}<div><b>{LOJA}</b><small>Acompanhamento de OS</small></div></header>
            <div className="rpl-body">
              {(cenario === "digitar" || (!res && erro && erro !== "expirado" && cenario !== "invalido")) && (
                <section className="rpl-card" data-contract="consulta-os-busca">
                  {React.createElement(res ? "h2" : "h1", { className: "rpl-h1" }, "Acompanhe sua OS")}
                  <p className="rpl-sub">Use o código que está no recibo ou na mensagem da loja. Não precisa de cadastro.</p>
                  <span className="rpl-seg" role="group" aria-label="Como buscar">
                    <button aria-pressed={entrada === "codigo"} onClick={() => setEntrada("codigo")}>Código</button>
                    <button aria-pressed={entrada === "numero"} onClick={() => setEntrada("numero")}>Nº da OS + série</button>
                    {porCelular && <button aria-pressed={entrada === "celular"} onClick={() => setEntrada("celular")}>Celular</button>}
                  </span>
                  <div className="rpl-form">
                    {entrada === "codigo" && Input && <Input label="Código de acompanhamento" value={cod} placeholder="K7PM-4QXR" onChange={(e) => setCod(val(e))} help="8 letras e números. Sem 0, O, 1 ou I." />}
                    {entrada === "numero" && Input && <><Input label="Número da OS" value={num} placeholder="JS-2026-0412" onChange={(e) => setNum(val(e))} /><Input label="Número de série" value={serie} placeholder="Como está na etiqueta" onChange={(e) => setSerie(val(e))} /></>}
                    {entrada === "celular" && Input && <Input label="Celular do cadastro" placeholder="(14) 99999-9999" help="Enviamos um código pelo WhatsApp para confirmar." />}
                    {Button && <Button variant="primary" onClick={buscar}>Consultar</Button>}
                  </div>
                  <p className="rpl-tip"><Ic name="info" size={13} /> Para a sua OS de teste, use <button className="rpl-link mono" onClick={() => { setEntrada("codigo"); setCod(fmtCod(codigoDe(exemplo))); }}>{fmtCod(codigoDe(exemplo))}</button> ou <span className="mono">{exemplo.os}</span> + <span className="mono">{exemplo.serie}</span>.</p>
                </section>
              )}
              {erro && Alert && <Alert tone={erro === "expirado" ? "warn" : "danger"} title={MSG[erro][0]}>{MSG[erro][1]}</Alert>}
              {(erro === "expirado" || cenario === "invalido") && Button && <div className="rpl-bar"><Button onClick={() => aplicar("digitar")}>Digitar outro código</Button></div>}
              {res && <Acompanhamento key={res.f.id + cenario} f={res.f} origem={res.origem} semCadastro={semCadastro && !acesso} onAcesso={() => setAcesso("enviado")} aprovar={aprovar} avisar={avisar} />}
              {acesso && Alert && <Alert tone="info" title="Código enviado">Digite o código de 6 dígitos que chegou pelo WhatsApp. Ele vale 10 minutos. Se o celular da OS não for mais o seu, fale com a loja.</Alert>}
              {!res && !erro && cenario === "digitar" && EmptyState && <EmptyState variant="first" title="Nada consultado ainda" description="O código fica no recibo, abaixo do QR, e na mensagem de WhatsApp da loja." />}
            </div>
            <div role="status" aria-live="polite" className="osc-sr">{res ? "OS " + res.f.os + " aberta." : erro ? MSG[erro][0] + "." : ""}</div>
            <footer className="rpl-foot">{LOJA} · <a href="tel:+551436220000">(14) 3622-0000</a> · seg a sex, 8h às 18h</footer>
          </div>
        </div>

        <aside className="rpl-side" aria-label="Como o link chega ao cliente">
          <section className="rpl-card">
            <h2 className="rpl-h2">Mensagem que a loja envia</h2>
            <div className="rpl-wa" data-contract="consulta-os-mensagem">
              <p>Olá! Recebemos seu equipamento na <b>OS {exemplo.os}</b>.</p>
              <p>Acompanhe o andamento por aqui, sem cadastro:</p>
              <p className="mono rpl-wa-link">oimpresso.com/os/{codigoDe(exemplo)}</p>
              <p>Código: <b className="mono">{fmtCod(codigoDe(exemplo))}</b></p>
            </div>
            <p className="rpl-help">Vai na abertura da OS e a cada troca de status com aviso ligado (template do status). O mesmo link sai no recibo, com QR.</p>
            {Button && <div className="rpl-bar"><Button size="sm" onClick={() => avisar && avisar("Link copiado: oimpresso.com/os/" + codigoDe(exemplo), "ok")}><Ic name="copy" size={12} /> Copiar link</Button><Button size="sm" onClick={() => avisar && avisar("Novo código gerado. O link antigo deixou de abrir.", "ok")}><Ic name="refresh" size={12} /> Gerar novo código</Button></div>}
          </section>
          <section className="rpl-card rp-cfg-col">
            <h2 className="rpl-h2">Configuração da loja</h2>
            {Switch && <Switch checked={semCadastro} onChange={setSemCadastro} label="Oferecer acesso ao portal pela OS" sublabel="Desligado por padrão. O cliente confirma o celular da OS por WhatsApp e passa a ver todas as OS dele. Cliente balcão nunca ganha acesso." />}
            {Switch && <Switch checked={porCelular} onChange={(v) => { setPorCelular(v); if (!v && entrada === "celular") setEntrada("codigo"); }} label="Permitir consulta por celular" sublabel="repair.enable_repair_check_using_mobile_num — exige código pelo WhatsApp (LGPD Art. 7º)." />}
            {Switch && <Switch checked={aprovar} onChange={setAprovar} label="Aprovar orçamento pelo link" sublabel="Ligado por padrão (D-APROVAR). O cliente decide sem ver o valor na página; o valor vai pelo WhatsApp e e-mail." />}
          </section>
        </aside>
      </div>
    </div>
  );
}

window.RepPortal = { PortalConsulta, codigoDe, fmtCod };
})();
