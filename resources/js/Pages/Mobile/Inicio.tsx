// Mobile/Inicio — raiz do app das lojas (/m). Forma: HomeScreen de
// mobile/ref/design-v3/.../design/screens-home-tasks.jsx (cabeçalho + pílula da empresa).
// Versão da BASE: só prova o shell. O painel do dia (faturado, KPIs, atalhos, tarefas,
// financeiro) é da sessão de tela do Início — ver Inicio.charter.md §Non-Goals.
import { Clock } from 'lucide-react';
import MobileShell from '@/Layouts/MobileShell';
import { Iniciais, MobileScroll, ScreenHeader } from './_components/MobileHeader';

interface Props {
  usuario: { nome: string };
  empresa: { nome: string };
  /** Data já formatada no fuso do business (ex.: "1 out"). */
  hoje: string;
}

function saudacao(hora: number): string {
  if (hora < 12) return 'Bom dia';
  if (hora < 18) return 'Boa tarde';
  return 'Boa noite';
}

export default function Inicio({ usuario, empresa, hoje }: Props) {
  const primeiroNome = usuario.nome.split(' ')[0] || usuario.nome;
  return (
    <MobileShell tab="inicio" title="Início">
      <ScreenHeader
        eyebrow={`Início · Hoje, ${hoje}`}
        title={`${saudacao(new Date().getHours())}, ${primeiroNome}`}
        actions={<Iniciais nome={usuario.nome} />}
      />
      <MobileScroll>
        <section className="oi-section">
          <div className="oi-tenant-pill" data-testid="empresa-ativa">
            <span className="av oi-av oi-av-5">{empresa.nome.slice(0, 2).toUpperCase()}</span>
            <span className="nm">{empresa.nome}</span>
          </div>
        </section>
        <section className="oi-section">
          <div className="oi-empty">
            <div className="oi-empty-ico" aria-hidden="true">
              <Clock size={22} />
            </div>
            <b>Painel do dia em construção</b>
            <small>Faturamento, tarefas e produção de hoje aparecem aqui na próxima versão do app.</small>
          </div>
        </section>
      </MobileScroll>
    </MobileShell>
  );
}
