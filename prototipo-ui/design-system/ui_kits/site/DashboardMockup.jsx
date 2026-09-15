// DashboardMockup — minimal fake operational view, primary-shadowed
function DashboardMockup() {
  return (
    <div className="mockup" aria-hidden>
      <div className="mockup__chrome">
        <span className="mockup__dot mockup__dot--r"/>
        <span className="mockup__dot mockup__dot--y"/>
        <span className="mockup__dot mockup__dot--g"/>
        <span className="mockup__addr">app.oimpresso.com.br/home</span>
      </div>
      <div className="mockup__body">
        <div className="mockup__headerline">
          <span className="mockup__title">Visão geral · operação</span>
          <span className="mockup__pill">Ao vivo</span>
        </div>
        <div className="mockup__kpis">
          <div className="mockup__kpi">
            <div className="mockup__kpi-lbl">Faturamento mês</div>
            <div className="mockup__kpi-v">R$ 184,2 mil</div>
            <div className="mockup__kpi-d mockup__kpi-d--up">↑ +12% vs mês ant.</div>
          </div>
          <div className="mockup__kpi">
            <div className="mockup__kpi-lbl">A receber</div>
            <div className="mockup__kpi-v">R$ 84,2 mil</div>
            <div className="mockup__kpi-d mockup__kpi-d--dn">3 títulos vencidos</div>
          </div>
          <div className="mockup__kpi">
            <div className="mockup__kpi-lbl">OS em produção</div>
            <div className="mockup__kpi-v">16</div>
            <div className="mockup__kpi-d" style={{color:'var(--fg-2)'}}>2 em acabamento</div>
          </div>
        </div>
        <div className="mockup__chart">
          <svg width="100%" height="80" viewBox="0 0 480 80" preserveAspectRatio="none">
            <defs>
              <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="oklch(0.55 0.15 295)" stopOpacity="0.3"/>
                <stop offset="100%" stopColor="oklch(0.55 0.15 295)" stopOpacity="0"/>
              </linearGradient>
            </defs>
            <path d="M0,60 L40,55 L80,50 L120,42 L160,38 L200,32 L240,28 L280,24 L320,32 L360,20 L400,16 L440,22 L480,12 L480,80 L0,80 Z" fill="url(#g1)"/>
            <path d="M0,60 L40,55 L80,50 L120,42 L160,38 L200,32 L240,28 L280,24 L320,32 L360,20 L400,16 L440,22 L480,12" fill="none" stroke="oklch(0.55 0.15 295)" strokeWidth="2"/>
          </svg>
        </div>
        <div className="mockup__rows">
          <div className="mockup__row">
            <span className="mockup__row-time">09:14</span>
            <span className="mockup__row-name">NF-e #4821 · Acme Comércio</span>
            <span className="mockup__row-tag" style={{background:'hsl(160 84% 39% / .12)', color:'hsl(161 94% 30%)'}}>Autorizada</span>
          </div>
          <div className="mockup__row">
            <span className="mockup__row-time">08:52</span>
            <span className="mockup__row-name">OS #1574 · banner 3×1m</span>
            <span className="mockup__row-tag" style={{background:'hsl(38 92% 50% / .12)', color:'hsl(32 95% 44%)'}}>Acabamento</span>
          </div>
          <div className="mockup__row">
            <span className="mockup__row-time">08:30</span>
            <span className="mockup__row-name">Recebimento · boleto</span>
            <span className="mockup__row-tag" style={{background:'hsl(160 84% 39% / .12)', color:'hsl(161 94% 30%)'}}>Pago</span>
          </div>
        </div>
      </div>
    </div>
  );
}
window.DashboardMockup = DashboardMockup;
