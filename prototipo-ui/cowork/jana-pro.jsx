// jana-pro.jsx — Jana Pro (paywall / upgrade) em modo FOCO.
// Espelho do vivo `resources/js/Pages/Jana/Pro.tsx` (lido no `main` neste turno):
// header próprio + hero (pitch | card de prova dark com os 3 ângulos) + tabela Grátis vs Pro
// + preço + confiança + footer sticky com CTA idle → ativando → feito. Sem abas: é decisão
// de compra, análoga a um checkout. Atalhos: ⌘/Ctrl+↵ ativa · Esc volta.
// Preços de concorrente NÃO entram (Tier 0 redigido na fonte) — nada é inventado aqui.
const { useState: useStatePro, useEffect: useEffectPro } = React;

const JP_MENSAL = 149;
const JP_TRIAL = 14;
const JP_PROVA = { bruto: 71320, liquido: 58940, caixa: 46180 };

const jpBRL = (n) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 });

const JP_LINHAS = [
  { t: "Brief diário às 06h", s: "resumo do dia pronto antes de você abrir a loja", free: false, pro: true },
  { t: "Análises automáticas", s: "inadimplência, oportunidades, status de NF-e", free: false, pro: true },
  { t: "Cockpit Saúde", s: "a Jana narra a saúde do negócio de hora em hora", free: false, pro: true },
  { t: "Memória persistente", s: "lembra do seu negócio entre conversas", free: "7 dias", pro: "Ilimitada", selo: true },
  { t: "Metas governadas + alertas", s: "acompanha e avisa quando desvia", free: "1 meta", pro: "Ilimitadas" },
  { t: "Chat com dados reais do ERP", s: "a base dos dois planos — vendas, clientes, NF-e sem integração", free: true, pro: true }];

const JP_CONFIANCA = [
  { icon: "shield", t: "Seus dados são só seus", s: "Isolamento por empresa garantido no núcleo do sistema — ninguém vê o que é seu." },
  { icon: "check", t: "LGPD por padrão", s: "Retenção declarada por tipo de dado. Você decide o que a Jana guarda." },
  { icon: "lock", t: "Hospedado no Brasil", s: "Infra nacional, custo transparente. Sem dado saindo do país." }];

function JpMarca({ on }) {
  if (on === true) return <span className="jp-yes" aria-label="incluído">✓</span>;
  if (on === false) return <span className="jp-no" aria-label="não incluído">✕</span>;
  return on;
}

function JanaProPage({ plano = "free", onVoltar, onAviso }) {
  const JcIcon = window.JcIcon;
  const jaPro = plano === "pro";
  const [fase, setFase] = useStatePro(jaPro ? "feito" : "idle");
  const ativar = () => {
    if (fase !== "idle") return;
    setFase("ativando");
    setTimeout(() => {
      setFase("feito");
      onAviso?.("Jana Pro ativo · " + JP_TRIAL + " dias grátis — a cobrança real (Asaas) é do backend, fora deste protótipo.", "ok");
    }, 900);
  };
  useEffectPro(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key === "Enter") {e.preventDefault();ativar();} else
      if (e.key === "Escape") onVoltar?.();
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  });

  return (
    <div className="jp-page" data-screen-label="Jana — Pro (modo foco)">
      <header className="jp-head">
        <div>
          <div className="jp-eyebrow"><b>Jana</b> · Plano</div>
          <h1>Jana Pro<span className="jp-up">UPGRADE</span></h1>
        </div>
        <span className="jp-spacer" />
        <span className="jp-keys"><span className="kbd">⌘↵</span> ativa <span className="jp-keys-sep">·</span> <span className="kbd">Esc</span> volta</span>
        <button className="jm-btn ghost" onClick={() => onVoltar?.()}>Voltar ao chat</button>
      </header>

      <div className="jp-body">
        <div className="jp-wrap">
          <div className="jp-hero">
            <div className="jp-pitch">
              <div className="jp-kicker">
                {JcIcon && <JcIcon name="sparkles" className="ic" />}
                A Jana já trabalha pra você
              </div>
              <h2>Ela conhece o seu negócio.<br />O <em>Pro</em> tira as amarras.</h2>
              <p>No plano grátis a Jana responde com seus dados reais — mas esquece rápido e não age sozinha. O Pro dá a ela memória ilimitada, brief diário e análises que rodam no automático.</p>
              {plano === "free" ?
              <div className="jp-hoje">
                  <span className="jp-hoje-pill">Seu plano hoje: Grátis</span>
                  <span>· memória de 7 dias · 1 meta · sem brief automático</span>
                </div> :

              <div className="jp-hoje">
                  <span className="jp-hoje-pill pro">Seu plano hoje: Pro</span>
                  <span>· brief das 06h, análises e memória ilimitada já ligados</span>
                </div>}
            </div>

            <div className="jp-prova">
              <div className="jp-prova-h">
                <span className="jp-av">J</span>
                <b>Jana</b>
                <small><i className="jp-live" />lendo seu ERP</small>
              </div>
              <div className="jp-bub them">Jana, como foi meu faturamento esse mês?</div>
              <div className="jp-bub jana">
                Maio fechou acima de abril. Veja pelos 3 ângulos:
                <div className="jp-angulos">
                  <div><small>Bruto</small><b>{jpBRL(JP_PROVA.bruto)}</b></div>
                  <div><small>Líquido</small><b>{jpBRL(JP_PROVA.liquido)}</b></div>
                  <div><small>Caixa</small><b className="pos">{jpBRL(JP_PROVA.caixa)}</b></div>
                </div>
              </div>
              <div className="jp-prova-f">Números reais das suas tabelas — sem planilha, sem integração.</div>
            </div>
          </div>

          <h3 className="jp-h3">Grátis vs Pro<span /></h3>
          <div className="jp-cmp">
            <div className="jp-cmp-row jp-cmp-head">
              <div className="jp-cmp-rec">Recurso</div>
              <div className="jp-cmp-free">Grátis</div>
              <div className="jp-cmp-pro"><b>JANA PRO</b><small>tudo do Grátis, e mais</small></div>
            </div>
            {JP_LINHAS.map((l) =>
            <div key={l.t} className="jp-cmp-row">
                <div className="jp-cmp-rec"><b>{l.t}</b><small>{l.s}</small></div>
                <div className="jp-cmp-free"><JpMarca on={l.free} /></div>
                <div className="jp-cmp-pro">
                  <JpMarca on={l.pro} />
                  {l.selo && <span className="jp-selo">PRO</span>}
                </div>
              </div>
            )}
          </div>

          <h3 className="jp-h3">Preço honesto<span /></h3>
          <div className="jp-preco-grid">
            <div className="jp-card jp-preco">
              <div className="jp-valor"><span>{jpBRL(JP_MENSAL)}</span><small>/ mês · por empresa</small></div>
              <ul>
                <li>Sem fidelidade — cancela quando quiser</li>
                <li>Custo de IA já incluso (você não paga por uso)</li>
                <li>{JP_TRIAL} dias pra testar — ativa hoje, decide depois</li>
              </ul>
            </div>
            <div className="jp-card">
              <h4>Por que confiar</h4>
              {JP_CONFIANCA.map((c, i) =>
              <div key={c.t} className={"jp-trust" + (i === 0 ? " first" : "")}>
                  {JcIcon && <JcIcon name={c.icon} className="ic" />}
                  <div><b>{c.t}</b><small>{c.s}</small></div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      <footer className="jp-foot">
        <div className="jp-foot-t"><b>Jana Pro</b> · <span className="jp-foot-v">{jpBRL(JP_MENSAL)}</span>/mês · {JP_TRIAL} dias grátis</div>
        <span className="jp-spacer" />
        <button className="jm-btn ghost" onClick={() => onVoltar?.()}>Falar com a Jana sobre o Pro</button>
        <button className={"jm-btn" + (fase === "feito" ? " solid-ok" : "")} onClick={ativar} disabled={fase === "ativando" || jaPro} aria-live="polite">
          {jaPro ? "Jana Pro já ativo" : fase === "feito" ? "Jana Pro ativo · " + JP_TRIAL + " dias grátis" : fase === "ativando" ? "Ativando…" : "Ativar Jana Pro"}
        </button>
      </footer>
    </div>);

}

Object.assign(window, { JanaProPage });
