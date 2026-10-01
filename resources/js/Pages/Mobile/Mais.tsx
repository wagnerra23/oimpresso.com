// Mobile/Mais — hub da aba Mais (/m/mais). Forma: MaisScreen de
// mobile/ref/design-v3/.../design/screens-clientes-producao.jsx (seção Conta + lockup).
// Versão da BASE: só itens que funcionam hoje. Cada sessão de tela acrescenta o item do
// próprio módulo (Pessoas, Ponto, …) quando a tela /m dele existir — Mais.charter.md.
import { ChevronRight, LogOut, Monitor, User } from 'lucide-react';
import MobileShell from '@/Layouts/MobileShell';
import { Iniciais, MobileScroll, ScreenHeader } from './_components/MobileHeader';
import logo from '../../../images/mobile/oimpresso-logo.png';

interface Props {
  usuario: { nome: string };
  empresa: { nome: string };
}

export default function Mais({ usuario, empresa }: Props) {
  return (
    <MobileShell tab="mais" title="Mais">
      <ScreenHeader eyebrow="Módulos · ferramentas · conta" title="Mais" actions={<Iniciais nome={usuario.nome} />} />
      <MobileScroll>
        <section className="oi-section">
          <h2 className="oi-section-h">Conta</h2>
          <div className="oi-list card">
            <div className="oi-list-row">
              <User size={18} color="var(--text-mute)" aria-hidden="true" />
              <span style={{ flex: 1, fontSize: 13.5, fontWeight: 500 }}>{usuario.nome}</span>
              <span style={{ fontSize: 11.5, color: 'var(--text-mute)' }}>{empresa.nome}</span>
            </div>
            {/* Links de página inteira (não Inertia): /home e /logout são telas fora do /m. */}
            <a className="oi-list-row" href="/home">
              <Monitor size={18} color="var(--text-mute)" aria-hidden="true" />
              <span style={{ flex: 1, fontSize: 13.5, fontWeight: 500 }}>Abrir versão completa</span>
              <ChevronRight size={16} color="var(--text-mute)" aria-hidden="true" />
            </a>
            <a className="oi-list-row" href="/logout" style={{ borderBottom: 0 }}>
              <LogOut size={18} color="var(--danger)" aria-hidden="true" />
              <span style={{ flex: 1, fontSize: 13.5, fontWeight: 500, color: 'var(--danger)' }}>Sair</span>
            </a>
          </div>
        </section>

        <section className="oi-section" style={{ paddingTop: 18, paddingBottom: 8 }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, opacity: 0.9 }}>
            <img src={logo} alt="Oimpresso" style={{ width: 26, height: 'auto' }} />
            <div style={{ lineHeight: 1.1 }}>
              <div style={{ fontSize: 15, fontWeight: 700, letterSpacing: '-0.01em', color: 'var(--text)' }}>Oimpresso</div>
              <div style={{ fontSize: 10, color: 'var(--text-mute)' }}>Gestão para comunicação visual</div>
            </div>
          </div>
        </section>
      </MobileScroll>
    </MobileShell>
  );
}
