import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';

const NAV_FIXO = [
  { label: 'Recursos', href: '/#recursos' },
  { label: 'Preços', href: '/pricing' },
];

const NAV_TAIL = [
  { label: 'Ajuda', href: '/ajuda' },
  { label: 'Contato', href: '/c/contact-us' },
];

export default function SiteHeader() {
  // Sem "Acompanhar pedido"/"Status do reparo" desde 2026-11-18: os dois levavam a /consulta-os
  // sem empresa, que não busca nada (decisão [W] 2026-11-17 "empresa na URL"). O cliente chega
  // pelo link da empresa (aviso da OS, impressão), não pelo site.
  const NAV = [...NAV_FIXO, ...NAV_TAIL];

  // Menu do celular: abaixo de md o <nav> de cima some, e sem isto Preços, Ajuda e Contato
  // só eram alcançáveis pelo rodapé. Painel inline (sem portal), mesma lista do desktop.
  const [aberto, setAberto] = useState(false);
  const botaoRef = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    if (!aberto) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setAberto(false);
        botaoRef.current?.focus();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [aberto]);

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
          <Button asChild className="h-11 md:h-9">
            <a href="/login">Começar grátis</a>
          </Button>
          <button
            ref={botaoRef}
            type="button"
            className="inline-flex size-11 items-center justify-center rounded-md text-foreground hover:bg-muted md:hidden"
            aria-expanded={aberto}
            aria-controls="menu-site-mobile"
            aria-label={aberto ? 'Fechar menu' : 'Abrir menu'}
            onClick={() => setAberto((v) => !v)}
          >
            {aberto ? <X aria-hidden className="size-6" /> : <Menu aria-hidden className="size-6" />}
          </button>
        </div>
      </div>

      {aberto && (
        <nav
          id="menu-site-mobile"
          aria-label="Navegação principal"
          className="border-t border-border/40 bg-background px-4 pb-4 md:hidden"
        >
          <Stack asChild gap={0} className="py-2">
            <ul>
              {NAV.map((item) => (
                <li key={item.href}>
                  <Inline asChild gap={0}>
                    <a
                      href={item.href}
                      onClick={() => setAberto(false)}
                      className="min-h-11 text-base font-medium text-foreground"
                    >
                      {item.label}
                    </a>
                  </Inline>
                </li>
              ))}
              <li>
                <Inline asChild gap={0}>
                  <a href="/login" className="min-h-11 text-base font-medium text-foreground">
                    Entrar
                  </a>
                </Inline>
              </li>
            </ul>
          </Stack>
          <Button asChild className="h-11 w-full">
            <a href="/login">Começar grátis</a>
          </Button>
        </nav>
      )}
    </header>
  );
}
