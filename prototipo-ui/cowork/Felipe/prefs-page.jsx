// prefs-page.jsx — Preferências (empresa e usuário). F1 novo: não li as views do /prefs no main,
// então isto é proposta de desenho, não espelho — os campos precisam de conferência campo a campo.
// Expõe window.PrefsPage.
(() => {
const { useState } = React;
const { Sw, Nota, Relogios } = window.AcessosDS;

const SECOES = [
  { g:"Empresa", itens:[
    { id:"identidade", label:"Identidade" },
    { id:"fiscal",     label:"Fiscal e documentos" },
    { id:"numeracao",  label:"Numeração" },
    { id:"regionais",  label:"Formato e região" },
  ]},
  { g:"Você", itens:[
    { id:"conta",      label:"Sua conta" },
    { id:"aparencia",  label:"Aparência" },
    { id:"avisos",     label:"Avisos" },
  ]},
];

function Campo({ label, valor, onChange, ajuda, mono, largo }) {
  return (
    <div className={`cms-field ${largo ? "pf-largo" : ""}`}>
      <label>{label}</label>
      <input className={mono ? "mono" : ""} value={valor} onChange={(e) => onChange(e.target.value)} />
      {ajuda && <small>{ajuda}</small>}
    </div>
  );
}
function Sel({ label, valor, onChange, opcoes, ajuda }) {
  return (
    <div className="cms-field">
      <label>{label}</label>
      <select value={valor} onChange={(e) => onChange(e.target.value)}>
        {opcoes.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
      {ajuda && <small>{ajuda}</small>}
    </div>
  );
}
function Liga({ label, sub, on, onToggle }) {
  return <div className="pf-liga pf-liga-ds"><Sw on={on} onToggle={onToggle} label={label} sub={sub} /></div>;
}
function Seg({ valor, opcoes, onChange }) {
  return (
    <div className="fnc-seg">
      {opcoes.map((o) => <button key={o.v} className={valor === o.v ? "on" : ""} onClick={() => onChange(o.v)}>{o.label}</button>)}
    </div>
  );
}

function PrefsPage() {
  const [sec, setSec] = useState("identidade");
  const [sujo, setSujo] = useState(false);
  const [f, setF] = useState({
    nome:"ROTA LIVRE Comunicação Visual", fantasia:"ROTA LIVRE", cnpj:"41.882.334/0001-07",
    ie:"0018773340092", crt:"Simples Nacional", cfopPadrao:"5405", serieNfe:"1", ambiente:"Produção",
    prefOrc:"ORC-", prefOs:"OS-", prefVenda:"VD-", proxOs:"2319",
    fuso:"America/Sao_Paulo", moeda:"Real (R$)", dataFmt:"dd/mm/aaaa", decimais:"2",
    meuNome:"Wagner Rocha", meuEmail:"wagner@wr2.com.br", tema:"claro", densidade:"compacta",
    atalhos:true, mfa:true, avisoOs:true, avisoTitulo:true, avisoFiscal:true, avisoResumo:false,
  });
  const set = (k) => (v) => { setF((s) => ({ ...s, [k]: v })); setSujo(true); };
  const liga = (k) => () => { setF((s) => ({ ...s, [k]: !s[k] })); setSujo(true); };

  return (
    <div className="os-page usr-page pf-page" data-screen-label="Sistema · Preferências">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Preferências</h1>
          <p>O que vale para toda a empresa e o que é só seu</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("perfil")}>Meu perfil</button>
          <button className="os-btn primary" disabled={!sujo} onClick={() => setSujo(false)}>Salvar</button>
        </div>
      </header>

      <div className="pf-body">
        <nav className="fnc-rail pf-rail">
          {SECOES.map((g) => (
            <div key={g.g} className="fnc-rail-dom">
              <span className="fnc-rail-dom-l">{g.g}</span>
              {g.itens.map((i) => (
                <button key={i.id} className={`fnc-rail-g ${sec === i.id ? "on" : ""}`} onClick={() => setSec(i.id)}>
                  <span className="fnc-rail-g-l">{i.label}</span>
                </button>
              ))}
            </div>
          ))}
        </nav>

        <section className="fnc-pane pf-pane">
          {sec === "identidade" && (
            <>
              <div className="pf-h"><h2>Identidade</h2><p>Aparece na nota, no orçamento e na etiqueta.</p></div>
              <div className="pf-form">
                <div className="cms-f-row two">
                  <Campo label="Razão social" valor={f.nome} onChange={set("nome")} />
                  <Campo label="Nome fantasia" valor={f.fantasia} onChange={set("fantasia")} />
                </div>
                <div className="cms-f-row two">
                  <Campo label="CNPJ" valor={f.cnpj} onChange={set("cnpj")} mono />
                  <Campo label="Inscrição estadual" valor={f.ie} onChange={set("ie")} mono />
                </div>
                <p className="pf-nota">Alterar razão social ou CNPJ não muda documento já emitido — vale das próximas emissões em diante.</p>
              </div>
            </>
          )}

          {sec === "fiscal" && (
            <>
              <div className="pf-h"><h2>Fiscal e documentos</h2><p>Padrões usados quando a venda não diz outra coisa.</p></div>
              <div className="pf-form">
                <div className="cms-f-row two">
                  <Sel label="Regime tributário" valor={f.crt} onChange={set("crt")} opcoes={["Simples Nacional", "Lucro presumido", "Lucro real"]} />
                  <Sel label="Ambiente da NF-e" valor={f.ambiente} onChange={set("ambiente")} opcoes={["Produção", "Homologação"]}
                    ajuda="Homologação não vale como documento fiscal." />
                </div>
                <div className="cms-f-row two">
                  <Campo label="CFOP padrão" valor={f.cfopPadrao} onChange={set("cfopPadrao")} mono ajuda="Usado quando o produto não tem CFOP próprio." />
                  <Campo label="Série da NF-e" valor={f.serieNfe} onChange={set("serieNfe")} mono />
                </div>
                <p className="pf-nota">Certificado A1 e regras de NCM ficam em <b>NF-e Brasil</b> — aqui só os padrões da empresa.</p>
              </div>
            </>
          )}

          {sec === "numeracao" && (
            <>
              <div className="pf-h"><h2>Numeração</h2><p>Prefixo e próximo número de cada documento.</p></div>
              <div className="pf-form">
                <div className="cms-f-row two">
                  <Campo label="Prefixo do orçamento" valor={f.prefOrc} onChange={set("prefOrc")} mono />
                  <Campo label="Prefixo da OS" valor={f.prefOs} onChange={set("prefOs")} mono />
                </div>
                <div className="cms-f-row two">
                  <Campo label="Prefixo da venda" valor={f.prefVenda} onChange={set("prefVenda")} mono />
                  <Campo label="Próxima OS" valor={f.proxOs} onChange={set("proxOs")} mono ajuda="Só aumenta. Baixar o número quebraria a sequência." />
                </div>
                <p className="pf-nota">Mudar prefixo não renumera o que já existe: a OS 2318 continua OS-2318.</p>
              </div>
            </>
          )}

          {sec === "regionais" && (
            <>
              <div className="pf-h"><h2>Formato e região</h2><p>Vale para a empresa inteira — não existe fuso nem formato por usuário.</p></div>
              <div className="pf-form">
                <Nota tone="warn" title="Onde o fuso mora de verdade">
                  O fuso é do <b>negócio</b> (<code>business.time_zone</code>) e o middleware <code>Timezone</code> aplica a cada request.
                  O <b>servidor tem o próprio</b>: <code>config('app.timezone')</code>, cujo padrão no repo é <code>Europe/London</code>
                  quando <code>APP_TIMEZONE</code> não está no <code>.env</code>. Tudo que não passa pelo middleware — cron, job de fila,
                  emissão de NF-e e rotas de API — roda no fuso do servidor. É a origem do erro de 3 h.
                </Nota>
                <Relogios tzEmpresa={f.fuso} />
                <div className="cms-f-row two">
                  <Sel label="Fuso horário da empresa" valor={f.fuso} onChange={set("fuso")} opcoes={["America/Sao_Paulo", "America/Manaus", "America/Belem"]}
                    ajuda="Vale para marcação de ponto, horário de emissão e vencimento." />
                  <Sel label="Moeda" valor={f.moeda} onChange={set("moeda")} opcoes={["Real (R$)"]} />
                </div>
                <div className="cms-f-row two">
                  <Sel label="Formato de data" valor={f.dataFmt} onChange={set("dataFmt")} opcoes={["dd/mm/aaaa", "aaaa-mm-dd"]} />
                  <Sel label="Casas decimais" valor={f.decimais} onChange={set("decimais")} opcoes={["2", "3", "4"]}
                    ajuda="Cálculo por m² costuma pedir 3." />
                </div>
                <p className="pf-nota">
                  Trocar o fuso não reescreve o que já foi gravado: marcação e emissão antigas continuam com o horário do fuso vigente na hora.
                </p>
              </div>
            </>
          )}

          {sec === "conta" && (
            <>
              <div className="pf-h"><h2>Sua conta</h2><p>Só afeta você.</p></div>
              <div className="pf-form">
                <div className="cms-f-row two">
                  <Campo label="Nome" valor={f.meuNome} onChange={set("meuNome")} />
                  <Campo label="E-mail" valor={f.meuEmail} onChange={set("meuEmail")} />
                </div>
                <Liga label="Verificação em 2 etapas" sub="Código no celular além da senha." on={f.mfa} onToggle={liga("mfa")} />
                <p className="pf-nota">Senha se troca por link de redefinição — o sistema nunca mostra a senha atual.</p>
              </div>
            </>
          )}

          {sec === "aparencia" && (
            <>
              <div className="pf-h"><h2>Aparência</h2><p>Densidade alta é a que a Larissa usa no balcão.</p></div>
              <div className="pf-form">
                <div className="pf-liga">
                  <span className="pf-liga-l"><b>Tema</b><small>Segue o sistema se você não escolher.</small></span>
                  <Seg valor={f.tema} onChange={set("tema")} opcoes={[{v:"claro",label:"Claro"},{v:"escuro",label:"Escuro"},{v:"sistema",label:"Sistema"}]} />
                </div>
                <div className="pf-liga">
                  <span className="pf-liga-l"><b>Densidade das tabelas</b><small>Compacta mostra mais linhas por tela.</small></span>
                  <Seg valor={f.densidade} onChange={set("densidade")} opcoes={[{v:"compacta",label:"Compacta"},{v:"confortavel",label:"Confortável"}]} />
                </div>
                <Liga label="Atalhos de teclado" sub="/ busca · n novo · Esc fecha." on={f.atalhos} onToggle={liga("atalhos")} />
              </div>
            </>
          )}

          {sec === "avisos" && (
            <>
              <div className="pf-h"><h2>Avisos</h2><p>O que chega para você, e nada além.</p></div>
              <div className="pf-form">
                <Liga label="OS atrasada" sub="Quando passa da data prometida." on={f.avisoOs} onToggle={liga("avisoOs")} />
                <Liga label="Título vencendo" sub="Sete dias antes do vencimento." on={f.avisoTitulo} onToggle={liga("avisoTitulo")} />
                <Liga label="Rejeição fiscal" sub="NF-e ou NFS-e rejeitada pela SEFAZ." on={f.avisoFiscal} onToggle={liga("avisoFiscal")} />
                <Liga label="Resumo diário por e-mail" sub="Um e-mail às 7h com o dia anterior." on={f.avisoResumo} onToggle={liga("avisoResumo")} />
              </div>
            </>
          )}

          <div className="pf-aviso">
            <Nota tone="info">
              Tela nova: não li as views do <code>/prefs</code> no <code>main</code> neste turno — os campos são proposta e precisam de
              conferência campo a campo contra o <code>BusinessController</code> e as preferências do Essentials.
            </Nota>
          </div>
        </section>
      </div>
    </div>
  );
}

window.PrefsPage = PrefsPage;
})();
