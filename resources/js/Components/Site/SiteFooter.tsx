const COLUMNS = [
  {
    heading: 'Produto',
    links: [
      { label: 'Recursos', href: '/c/page/recursos' },
      { label: 'Preços', href: '/c/page/precos' },
      { label: 'Novidades', href: '/c/blogs' },
    ],
  },
  {
    heading: 'Soluções',
    links: [
      { label: 'Comunicação visual', href: '/c/page/comunicacao-visual' },
      { label: 'Varejo & multi-loja', href: '/c/page/varejo' },
      { label: 'Serviços', href: '/c/page/servicos' },
    ],
  },
  {
    heading: 'Suporte',
    links: [
      { label: 'Central de ajuda', href: '/ajuda/' },
      { label: 'Fale com a gente', href: '/c/contact-us' },
      { label: 'Status', href: '/status' },
    ],
  },
  {
    heading: 'Empresa',
    links: [
      { label: 'Sobre', href: '/c/page/sobre' },
      { label: 'Política de privacidade', href: '/c/page/privacidade' },
      { label: 'Termos de uso', href: '/c/page/termos' },
    ],
  },
];

export default function SiteFooter() {
  const year = new Date().getFullYear();
  return (
    <footer className="border-t border-border bg-muted/30">
      <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="grid gap-10 md:grid-cols-5">
          <div className="md:col-span-1">
            <div className="flex items-center gap-2 text-base font-semibold">
              <span
                aria-hidden
                className="inline-flex h-7 w-7 items-center justify-center rounded-md bg-primary text-primary-foreground text-sm"
              >
                oi
              </span>
              <span>oimpresso</span>
            </div>
            <p className="mt-3 text-sm text-muted-foreground">
              ERP completo, em português, para a sua operação.
            </p>
          </div>

          {COLUMNS.map((col) => (
            <div key={col.heading}>
              <h4 className="text-sm font-semibold text-foreground">{col.heading}</h4>
              <ul className="mt-3 space-y-2">
                {col.links.map((link) => (
                  <li key={link.href}>
                    <a
                      href={link.href}
                      className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                      {link.label}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-12 flex flex-col gap-3 border-t border-border pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
          <p>© {year} oimpresso. Todos os direitos reservados.</p>
          <p>Feito no Brasil 🇧🇷</p>
        </div>
      </div>
    </footer>
  );
}
