import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

const NAV = [
  { label: 'Recursos', href: '/c/page/recursos' },
  { label: 'Preços', href: '/c/page/precos' },
  { label: 'Soluções', href: '/c/page/solucoes' },
  { label: 'Ajuda', href: '/ajuda/' },
  { label: 'Contato', href: '/c/contact-us' },
];

export default function SiteHeader() {
  return (
    <header className="sticky top-0 z-50 w-full border-b border-border/40 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link
          href="/"
          className="flex items-center gap-2 text-lg font-semibold tracking-tight text-foreground"
          aria-label="oimpresso — voltar para o início"
        >
          <span
            aria-hidden
            className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
          >
            oi
          </span>
          <span>oimpresso</span>
        </Link>

        <nav className="hidden items-center gap-7 md:flex" aria-label="Navegação principal">
          {NAV.map((item) => (
            <a
              key={item.href}
              href={item.href}
              className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
              {item.label}
            </a>
          ))}
        </nav>

        <div className="flex items-center gap-3">
          <a
            href="/login"
            className="hidden text-sm font-medium text-muted-foreground transition-colors hover:text-foreground sm:inline-block"
          >
            Entrar
          </a>
          <Button asChild>
            <a href="/login">Começar grátis</a>
          </Button>
        </div>
      </div>
    </header>
  );
}
