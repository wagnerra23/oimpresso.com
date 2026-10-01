// Primitivos de cabeçalho do app /m — porte de ScreenHeader e DetailHeader de
// mobile/ref/design-v3/.../design/screens-home-tasks.jsx, sobre as classes do bundle.
//
// Regra firme do handoff: "toda tela empilhada tem botão voltar — nunca deixar o usuário
// preso". Aqui o voltar é um LINK para a tela-mãe (`voltarPara`), e não history.back():
// quem abre o app direto num detalhe (deep link, notificação) não tem histórico, e o
// history.back() o tiraria do app.
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ChevronLeft } from 'lucide-react';

interface ScreenHeaderProps {
  title: string;
  eyebrow?: string;
  /** Ícones à direita (sino, avatar…). */
  actions?: ReactNode;
  /** Busca/chips abaixo do título. */
  children?: ReactNode;
}

/** Cabeçalho grande da raiz de uma aba (sem voltar). */
export function ScreenHeader({ title, eyebrow, actions, children }: ScreenHeaderProps) {
  return (
    <header className="oi-head" style={{ position: 'sticky', top: 0, zIndex: 5 }}>
      <div className="oi-head-row">
        <div style={{ flex: '1 1 auto', minWidth: 0 }}>
          {eyebrow ? <div className="oi-head-eyebrow">{eyebrow}</div> : null}
          <h1 className="oi-head-title" style={{ margin: 0 }}>
            {title}
          </h1>
        </div>
        {actions}
      </div>
      {children}
    </header>
  );
}

interface DetailHeaderProps {
  title: string;
  /** Rota da tela-mãe (ex.: '/m/pedidos'). Obrigatória: é o que impede o usuário de ficar preso. */
  voltarPara: string;
  eyebrow?: string;
  actions?: ReactNode;
}

/** Cabeçalho de tela empilhada (detalhe), com voltar. */
export function DetailHeader({ title, voltarPara, eyebrow, actions }: DetailHeaderProps) {
  return (
    <header className="oi-head" style={{ paddingTop: 6 }}>
      <div className="oi-head-row" style={{ minHeight: 32 }}>
        <Link
          href={voltarPara}
          className="oi-iconbtn"
          aria-label="Voltar"
          style={{ marginLeft: -8, color: 'var(--accent)', width: 44, height: 44 }}
        >
          <ChevronLeft size={24} aria-hidden="true" />
        </Link>
        <div style={{ flex: '1 1 auto', minWidth: 0 }}>
          {eyebrow ? <div className="oi-head-eyebrow">{eyebrow}</div> : null}
          <h1 className="oi-head-title" style={{ margin: 0, fontSize: 18 }}>
            {title}
          </h1>
        </div>
        {actions}
      </div>
    </header>
  );
}

/** Corpo rolável da tela. Tudo que não é cabeçalho nem barra de ação vai aqui. */
export function MobileScroll({ children }: { children: ReactNode }) {
  return <main className="oi-scroll">{children}</main>;
}

/** Barra de ação fixa no rodapé da tela, acima da tab bar (ex.: Adiar · Concluir). */
export function ActionBar({ children }: { children: ReactNode }) {
  return <div className="oi-actionbar">{children}</div>;
}

/** Avatar de iniciais no estilo do protótipo (.oi-av). */
export function Iniciais({ nome, size = 30 }: { nome: string; size?: number }) {
  const ini = nome
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('');
  return (
    <div
      className="oi-av oi-av-5"
      aria-hidden="true"
      style={{ width: size, height: size, borderRadius: '50%', fontSize: size * 0.37 }}
    >
      {ini || '?'}
    </div>
  );
}
