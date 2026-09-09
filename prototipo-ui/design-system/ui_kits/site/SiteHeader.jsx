// SiteHeader — sticky, blurred, primary-square wordmark
const SITE_NAV = [
  { label: 'Recursos', href: '#recursos' },
  { label: 'Preços', href: '#precos' },
  { label: 'Ajuda', href: '#' },
  { label: 'Contato', href: '#' },
];

function SiteHeader() {
  return (
    <header className="site-header" data-screen-label="Site Header">
      <div className="container site-header__inner">
        <a href="#" className="brand" aria-label="oimpresso — voltar para o início">
          <span className="brand__square" aria-hidden>oi</span>
          <span>oimpresso</span>
        </a>
        <nav className="site-nav" aria-label="Navegação principal">
          {SITE_NAV.map((i) => <a key={i.href} href={i.href}>{i.label}</a>)}
        </nav>
        <div className="site-header__right">
          <a href="#" className="site-header__login">Entrar</a>
          <a href="#" className="btn btn--primary">Começar grátis</a>
        </div>
      </div>
    </header>
  );
}
window.SiteHeader = SiteHeader;
