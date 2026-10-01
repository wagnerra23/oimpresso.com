// Mobile/EmConstrucao — marcador das abas cuja tela ainda não existe (Tarefas, Pedidos,
// Produção). Some quando a sessão de tela entregar a aba (RUNBOOK-shell-mobile §5.1).
import { Construction } from 'lucide-react';
import MobileShell, { type MobileTab } from '@/Layouts/MobileShell';
import { MobileScroll, ScreenHeader } from './_components/MobileHeader';

interface Props {
  aba: Extract<MobileTab, 'tarefas' | 'pedidos' | 'producao'>;
  titulo: string;
}

export default function EmConstrucao({ aba, titulo }: Props) {
  return (
    <MobileShell tab={aba} title={titulo}>
      <ScreenHeader title={titulo} />
      <MobileScroll>
        <section className="oi-section">
          <div className="oi-empty">
            <div className="oi-empty-ico" aria-hidden="true">
              <Construction size={22} />
            </div>
            <b>Em construção</b>
            <small>{titulo} chega numa próxima versão do app. Enquanto isso, use a versão completa pelo menu Mais.</small>
          </div>
        </section>
      </MobileScroll>
    </MobileShell>
  );
}
