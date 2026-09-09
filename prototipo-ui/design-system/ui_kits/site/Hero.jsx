// Hero — eyebrow pill + 3-line display + CTAs + dashboard plate
function Hero() {
  return (
    <section className="hero" data-screen-label="Site Hero">
      <div className="hero__bloom" aria-hidden/>
      <div className="hero__hairline" aria-hidden/>
      <div className="container hero__grid">
        <div style={{display:'flex', flexDirection:'column', justifyContent:'center'}}>
          <span className="eyebrow-pill">
            <span className="eyebrow-pill__dot" aria-hidden/>
            Comunicação visual · Varejo · Serviços · Multi-loja
          </span>
          <h1>
            <span className="ink">O ERP pra quem</span>
            <span className="accent">orça, imprime, monta</span>
            <span className="accent">e entrega.</span>
          </h1>
          <p className="hero__lede">
            Cálculo automático por <strong>m²</strong>, ordem de produção em tempo real e fechamento fiscal sem retrabalho. PDV, NF-e, estoque, ponto, financeiro e BI integrados — em uma plataforma só.
          </p>
          <div className="hero__cta-row">
            <a href="#" className="btn btn--primary btn--lg">Começar grátis</a>
            <a href="#recursos" className="btn btn--outline btn--lg">Ver recursos</a>
          </div>
          <div className="hero__fineprint">
            <span>Sem cartão de crédito · Suporte humano em português.</span>
            <a href="#">Não sabe qual plano? Me ajuda a escolher <span aria-hidden>→</span></a>
          </div>
        </div>
        <div className="hero__visual">
          <div className="hero__plate" aria-hidden/>
          <DashboardMockup/>
        </div>
      </div>
    </section>
  );
}
window.Hero = Hero;
