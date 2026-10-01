// MobileShell — casca das telas do app nas lojas (/m). Fonte de design:
// mobile/ref/design-v3/.../design/mobile-app.jsx (tab bar de 5 abas) + oimpresso-tokens.css.
// Contrato para as sessões de tela: memory/requisitos/AppMobile/RUNBOOK-shell-mobile.md.
//
// Diferenças deliberadas em relação ao protótipo:
// - Cada aba é uma ROTA (/m, /m/tarefas …), não um estado local: o voltar do sistema, o
//   deep link e o login (url.intended) funcionam sem código próprio.
// - Sem molduras de aparelho e sem painel de tweaks (andaimes do protótipo).
// - Tema segue o sistema (prefers-color-scheme), não um seletor.
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { FileText, House, Inbox, Layers, Printer, type LucideIcon } from 'lucide-react';
import '../../css/cowork-mobile-bundle.css';
import '../../css/mobile-shell.css';

export type MobileTab = 'inicio' | 'tarefas' | 'pedidos' | 'producao' | 'mais';

const MOBILE_TABS: ReadonlyArray<{ id: MobileTab; label: string; href: string; icon: LucideIcon }> = [
  { id: 'inicio', label: 'Início', href: '/m', icon: House },
  { id: 'tarefas', label: 'Tarefas', href: '/m/tarefas', icon: Inbox },
  { id: 'pedidos', label: 'Pedidos', href: '/m/pedidos', icon: FileText },
  { id: 'producao', label: 'Produção', href: '/m/producao', icon: Printer },
  { id: 'mais', label: 'Mais', href: '/m/mais', icon: Layers },
];

type Tema = 'light' | 'dark';

function temaDoSistema(): Tema {
  if (typeof window === 'undefined' || !window.matchMedia) return 'light';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/** Acompanha o tema do aparelho, inclusive quando ele muda com a tela aberta. */
function useTemaDoSistema(): Tema {
  const [tema, setTema] = useState<Tema>(temaDoSistema);
  useEffect(() => {
    if (!window.matchMedia) return;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = () => setTema(mq.matches ? 'dark' : 'light');
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, []);
  return tema;
}

interface MobileShellProps {
  /** Aba destacada na tab bar. Telas empilhadas (detalhe) passam a aba de origem. */
  tab: MobileTab;
  /** <title> da página. */
  title: string;
  /** Contador em vermelho sobre uma aba (ex.: tarefas urgentes). */
  badges?: Partial<Record<MobileTab, number>>;
  children: ReactNode;
}

export default function MobileShell({ tab, title, badges, children }: MobileShellProps) {
  const tema = useTemaDoSistema();
  return (
    <>
      <Head title={title} />
      <div className="oi oi-app oi-mobile" data-theme={tema} data-density="normal" data-mobile-shell="">
        <div className="oi-screen">{children}</div>
        <nav className="oi-tabbar" aria-label="Navegação principal">
          {MOBILE_TABS.map((t) => {
            const ativo = t.id === tab;
            const badge = badges?.[t.id];
            const Icone = t.icon;
            return (
              <Link
                key={t.id}
                href={t.href}
                className={'oi-tab' + (ativo ? ' active' : '')}
                aria-current={ativo ? 'page' : undefined}
                data-tab={t.id}
              >
                <div className="ico-wrap">
                  <Icone size={22} strokeWidth={1.6} aria-hidden="true" />
                </div>
                <span>{t.label}</span>
                {badge ? <span className="pin">{badge}</span> : null}
              </Link>
            );
          })}
        </nav>
      </div>
    </>
  );
}
