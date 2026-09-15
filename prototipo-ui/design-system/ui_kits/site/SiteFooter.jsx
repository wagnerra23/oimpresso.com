// SiteFooter — 5-col link grid + legal row (verbatim from SiteFooter.tsx)
const FOOTER_COLS = [
  { heading: 'Produto',   links: [{ label: 'Recursos', href: '#recursos' }, { label: 'Preços', href: '#precos' }, { label: 'Novidades', href: '#' }] },
  { heading: 'Soluções',  links: [{ label: 'Comunicação visual', href: '#' }, { label: 'Varejo & multi-loja', href: '#' }, { label: 'Serviços', href: '#' }] },
  { heading: 'Suporte',   links: [{ label: 'Central de ajuda', href: '#' }, { label: 'Fale com a gente', href: '#' }] },
  { heading: 'Empresa',   links: [{ label: 'Entrar no app', href: '#' }, { label: 'Fale com vendas', href: '#' }] },
];

function SiteFooter() {
  const year = 2026;
  return (
    <footer className="site-footer" data-screen-label="Site Footer">
      <div className="container site-footer__inner">
        <div className="site-footer__grid">
          <div className="site-footer__brand">
            <div className="brand">
              <span className="brand__square" aria-hidden style={{width: 28, height: 28, fontSize: 12}}>oi</span>
              <span>oimpresso</span>
            </div>
            <p>ERP completo, em português, para a sua operação.</p>
          </div>
          {FOOTER_COLS.map((col) => (
            <div key={col.heading} className="site-footer__col">
              <h4>{col.heading}</h4>
              <ul>
                {col.links.map((l) => <li key={l.label}><a href={l.href}>{l.label}</a></li>)}
              </ul>
            </div>
          ))}
        </div>
        <div className="site-footer__legal">
          <p>© {year} oimpresso. Todos os direitos reservados.</p>
          <p>Feito no Brasil 🇧🇷</p>
        </div>
      </div>
    </footer>
  );
}
window.SiteFooter = SiteFooter;
