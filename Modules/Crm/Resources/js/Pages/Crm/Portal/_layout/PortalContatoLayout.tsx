// Casca do portal do contato (D6, [W] 2026-10-15): enxuta, pensada para celular, sem a sidebar do ERP.
// Nasce na thread Crm/18 e as threads 19..25 reusam. Forma: prototipo-ui/cowork/Wagner/crm-portal.jsx
// (cp-top · cp-nav · cp-main · cp-bottom) + crm-portal.css, copiado inteiro em cowork-crm-portal-bundle.css.
//
// O menu vem de `shell.portal.menu`, montado pelo PortalContatoMenu — a mesma fonte que o
// ContactSidebarMenu da Blade lê. Cada item já chega filtrado pelo tipo do contato e pela config.
//
// Navegação por carga completa, não <Link>: as outras telas do portal ainda são Blade até as threads
// 19..25, e uma visita Inertia a resposta não-Inertia quebra. Os itens são <button>, como no protótipo,
// porque é `button` que o bundle estiliza (.cp-nav/.cp-bottom) — sem CSS novo além da cópia.
//
// Os tokens de cor só existem sob `.cockpit` (_generated-cockpit-*.css), e o cockpit.css só é importado
// pelo AppShellV2. Daí o import e o invólucro `.cockpit` com display:contents (idioma do TemaDoCockpit).

import '../../../../../../../../resources/css/cockpit.css';
import '../../../../../../../../resources/css/cowork-crm-portal-bundle.css';
import { Head, usePage } from '@inertiajs/react';
import { CalendarClock, LayoutGrid, LogOut, Package, Receipt, ScrollText, Truck, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { useTheme } from '@/Hooks/useTheme';

export type ChavePortal = 'painel' | 'compras' | 'vendas' | 'pedidos' | 'extrato' | 'agendamentos';

export interface CascaPortal {
  empresa: string;
  contato: { nome: string; pessoa: string; tipo: string };
  menu: Array<{ chave: ChavePortal; href: string }>;
  perfil: string;
  novo_pedido: string;
  agendar: string;
}

// Rótulos e ícones do protótipo (TITULOS/ICONE de crm-portal.jsx); a barra de baixo usa o rótulo curto.
const ROTULO: Record<ChavePortal, [string, string, LucideIcon]> = {
  painel: ['Início', 'Início', LayoutGrid],
  vendas: ['Minhas vendas', 'Vendas', Receipt],
  compras: ['Minhas compras', 'Compras', Truck],
  extrato: ['Extrato', 'Extrato', ScrollText],
  agendamentos: ['Agendamentos', 'Agenda', CalendarClock],
  pedidos: ['Pedidos', 'Pedidos', Package],
};
// O protótipo mostra 5 na barra de baixo: Início, vendas ou compras, pedidos, extrato (+ Assistência na thread 25).
const NA_BARRA: ChavePortal[] = ['painel', 'vendas', 'compras', 'pedidos', 'extrato', 'agendamentos'];

function iniciais(nome: string): string {
  return nome.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]?.toUpperCase()).join('') || '?';
}

interface Props {
  ativa: ChavePortal | 'perfil';
  titulo: string;
  children: ReactNode;
}

export default function PortalContatoLayout({ ativa, titulo, children }: Props) {
  const { effective } = useTheme();
  const portal = (usePage().props as { shell?: { portal?: CascaPortal } }).shell?.portal;
  if (!portal) return null;

  const { empresa, contato, menu } = portal;
  const barra = NA_BARRA.filter((k) => menu.some((m) => m.chave === k)).slice(0, 5);
  const href = (k: ChavePortal) => menu.find((m) => m.chave === k)?.href ?? '#';
  const ir = (k: ChavePortal) => window.location.assign(href(k));

  return (
    <div className="cockpit" data-theme={effective} style={{ display: 'contents' }}>
      <Head title={`${titulo} · ${empresa}`} />
      <div className="cp-stage" data-screen-label={`Portal do contato · ${titulo}`}>
        <div className="cp-viewport">
          <div className="cp-frame">
            <div className="cp-root">
              <header className="cp-top">
                <div className="cp-brand">
                  <Avatar size="sm" aria-hidden="true"><AvatarFallback>{iniciais(empresa)}</AvatarFallback></Avatar>
                  <div className="cp-brand-t"><b>{empresa}</b><small>Portal do cliente</small></div>
                </div>
                <div className="sp" />
                <a className="cp-me" href={portal.perfil} aria-label={`Meu perfil — ${contato.pessoa}`} aria-current={ativa === 'perfil' ? 'page' : undefined}>
                  <span className="cp-me-n"><b>{contato.pessoa}</b><small>{contato.nome}</small></span>
                  <Avatar size="sm"><AvatarFallback>{iniciais(contato.pessoa)}</AvatarFallback></Avatar>
                </a>
                <a className="cp-me" href="/logout"><LogOut size={14} aria-hidden="true" /> Sair</a>
              </header>

              <nav className="cp-nav" aria-label="Portal do cliente">
                {menu.map(({ chave }) => {
                  const [rotulo, , Icone] = ROTULO[chave];
                  return (
                    <button key={chave} type="button" onClick={() => ir(chave)} aria-current={ativa === chave ? 'page' : undefined}>
                      <Icone size={15} aria-hidden="true" />{rotulo}
                    </button>
                  );
                })}
              </nav>

              <main className="cp-main" aria-label={titulo}>{children}</main>

              <nav className="cp-bottom" aria-label="Portal do cliente (celular)">
                {barra.map((chave) => {
                  const [, curto, Icone] = ROTULO[chave];
                  return (
                    <button key={chave} type="button" onClick={() => ir(chave)} aria-current={ativa === chave ? 'page' : undefined}>
                      <Icone size={20} aria-hidden="true" />{curto}
                    </button>
                  );
                })}
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
