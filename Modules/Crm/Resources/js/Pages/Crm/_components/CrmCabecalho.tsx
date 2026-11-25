// Cabeçalho único das telas do CRM (pendência CRM-10, medida em produção em 2026-11-25).
//
// Âncora: `prototipo-ui/cowork/Wagner/crm-blade.jsx` → `CrmBladePage` (`M.Header`): título "CRM"
// com o glifo de pessoas, subtítulo = nome da aba atual, linha de contexto em mono caixa alta
// acima do título, selo "● Atualizado HH:MM" que reapura, e "+ Adicionar lead" como ação
// principal. Em produção cada tela tinha título e frase próprios ("Painel do CRM", "Leads"…).
//
// O que NÃO veio do protótipo, e por quê (declarado, não escondido):
//  - Contagens "n leads · n acompanhamentos · n propostas" da linha de contexto: exigem uma
//    consulta compartilhada por todas as telas do CRM; ficam para quando houver esse dado.
//  - Local ("matriz"): a sessão não carrega o local escolhido nessas telas.
//  - "Portal do contato": no protótipo abre uma prévia do portal; produção não tem rota de prévia
//    para o usuário do ERP.
//  - Seletor de papel: controle de demonstração do protótipo (o papel vem do usuário logado).
//
// Linha de contexto e selo seguem o idioma do Patrimônio (`Pages/Patrimonio/Index.tsx`), que já
// mediu a anatomia do `CliPageHead` do protótipo.

import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import { Icon } from '@/Components/Icon';
import ModuleTabs from '@/Components/shared/ModuleTabs';
import { usePageProps } from '@/Hooks/usePageProps';

interface ItemDoMenu { label?: string; ghosts?: { key: string }[]; primary?: { label: string; href: string } }

const horaAgora = () => new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

export default function CrmCabecalho({ aba, acoes, abas = true }: {
  /** Nome da aba atual — vira o subtítulo, como no protótipo. */
  aba: string;
  /** Ações próprias da tela (ex.: "Novo negócio" do Funil). Substituem o "Adicionar lead". */
  acoes?: ReactNode;
  /** Pedido de ordem não está na barra de abas do CRM (fica em Vendas). */
  abas?: boolean;
}) {
  const props = usePageProps() as { shell?: { cockpit?: { businessNome?: string; usuarioCargo?: string }; menu?: ItemDoMenu[] } };
  const cockpit = props.shell?.cockpit;
  const crm = props.shell?.menu?.find((m) => m.label?.toLowerCase() === 'crm');
  // "Adicionar lead" só para quem vê a aba Leads: a lista de abas já segue o portão da rota.
  const podeLead = crm?.ghosts?.some((g) => g.key === 'leads') ?? false;

  const [hora, setHora] = useState(horaAgora);
  const reapurar = () => router.reload({ onSuccess: () => setHora(horaAgora()) });

  const contexto = [cockpit?.businessNome, cockpit?.usuarioCargo ? `papel: ${cockpit.usuarioCargo}` : null]
    .filter((p): p is string => !!p)
    .join(' · ');

  return (
    <div className="flex flex-col" data-contract="crm-cabecalho">
      {contexto && (
        <p className="-mb-5 font-mono text-[11px] font-medium uppercase tracking-[0.04em] text-muted-foreground [text-wrap:pretty]">
          {contexto}
        </p>
      )}
      <PageHeader
        leading={
          <span aria-hidden className="mr-2 inline-flex translate-y-[1px] align-middle" style={{ color: 'var(--accent)' }}>
            <Icon name="users" size={18} strokeWidth={1.8} />
          </span>
        }
        title="CRM"
        subtitle={aba}
        actions={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={reapurar}
              title="Reapurar agora"
              className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-2 py-0.5 text-[11px] text-muted-foreground transition-colors hover:text-foreground"
            >
              <span aria-hidden className="size-1.5 rounded-full bg-success" />
              Atualizado {hora}
            </button>
            {acoes ?? (podeLead && <PageHeaderPrimary label="Adicionar lead" href={crm?.primary?.href ?? '/crm/leads/create'} />)}
          </div>
        }
        below={abas ? <ModuleTabs modulo="CRM" /> : undefined}
      />
    </div>
  );
}
