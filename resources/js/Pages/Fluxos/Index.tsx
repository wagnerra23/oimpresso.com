// Sistema › Fluxos e regras (ADR 0435) — modelos de fluxo (motor FSM) por porte × atividade.
// Fonte visual: prototipo-ui/cowork/Wagner/fluxos-page.jsx (CSS copiado inteiro em
// resources/css/cowork-fluxos-bundle.css). Lei: Index.charter.md · contrato: Index.casos.md.
//
// Abas como no protótipo: Modelos · Processos · Fluxos da empresa · Casos de uso. Processos e
// Casos de uso entram nos PRs seguintes. "Usar modelo" exige fluxos.configurar (POST /fluxos/modelo).

import '../../../css/cowork-fluxos-bundle.css';
import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import EmptyState from '@/Components/shared/EmptyState';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import AbaModelos from './_components/AbaModelos';
import AbaEmpresa from './_components/AbaEmpresa';
import DetalheModelo from './_components/DetalheModelo';
import type { EmpresaModelo, MapaCasos, MapaEncaixe, Modelo, Opcao, Perfil } from './_components/tipos';

type Aba = 'modelos' | 'processos' | 'empresa' | 'casos';

interface Props {
  aba: Aba;
  modelos: Modelo[];
  encaixe: MapaEncaixe;
  portes: Opcao[];
  atividades: Opcao[];
  casos_total: number;
  casos: MapaCasos;
  empresa: EmpresaModelo | null;
  pode_configurar: boolean;
}

const ABAS: { key: Aba; label: string }[] = [
  { key: 'modelos', label: 'Modelos' },
  { key: 'processos', label: 'Processos' },
  { key: 'empresa', label: 'Fluxos da empresa' },
  { key: 'casos', label: 'Casos de uso' },
];

/** Perfil inicial do protótipo (Pequena · Comunicação visual). */
const PERFIL_INICIAL: Perfil = { porte: 'pequena', ativ: 'cv' };

export default function FluxosIndex({
  aba, modelos, encaixe, portes, atividades, casos_total, casos, empresa, pode_configurar,
}: Props) {
  // O perfil começa pelo gravado da empresa (quando "Usar modelo" já gravou); senão, o do protótipo.
  const [perfil, setPerfil] = useState<Perfil>({
    porte: empresa?.porte ?? PERFIL_INICIAL.porte,
    ativ: empresa?.atividade ?? PERFIL_INICIAL.ativ,
  });
  const [aberto, setAberto] = useState<string | null>(null);
  const sucesso = (usePage().props as { flash?: { success?: string | null } }).flash?.success ?? null;
  const rotuloAba = ABAS.find((a) => a.key === aba)?.label ?? 'Modelos';
  const emUso = modelos.find((m) => m.id === empresa?.modelo) ?? null;
  const mAberto = modelos.find((m) => m.id === aberto) ?? null;

  return (
    <AppShellV2 title="Fluxos e regras" breadcrumbItems={[{ label: 'Sistema' }, { label: 'Fluxos e regras' }]}>
      <Head title={`Fluxos e regras · ${rotuloAba}`} />
      <div className="flx-root" data-screen-label={`Fluxos e regras · ${rotuloAba}`}>
        <PageHeader
          title="Fluxos e regras"
          subtitle={`${modelos.length} modelos · ${casos_total} casos de uso`}
          actions={emUso ? <span className="flx-dim">Em uso: <b>{emUso.nome}</b></span> : null}
        />
        <div className="px-5">
          <PageHeaderTabs
            ghosts={ABAS.map((a) => ({
              key: a.key,
              label: a.label,
              href: `/fluxos?aba=${a.key}`,
              badge: a.key === 'modelos' ? modelos.length : a.key === 'casos' ? casos_total : undefined,
            }))}
            activeGhostKey={aba}
          />
        </div>

        <div className="flx-body">
          {sucesso && (
            <Alert role="status">
              <AlertTitle>Modelo aplicado</AlertTitle>
              <AlertDescription>{sucesso}</AlertDescription>
            </Alert>
          )}
          {aba === 'modelos' && (
            <AbaModelos
              modelos={modelos}
              encaixe={encaixe}
              portes={portes}
              atividades={atividades}
              perfil={perfil}
              onPerfil={setPerfil}
              emUsoId={emUso?.id ?? null}
              onAbrir={setAberto}
            />
          )}
          {aba === 'empresa' && (
            <AbaEmpresa empresa={empresa} emUso={emUso} portes={portes} atividades={atividades} />
          )}
          {(aba === 'processos' || aba === 'casos') && (
            <EmptyState
              title={`${rotuloAba}: em construção`}
              description="Esta aba chega nos próximos passos da tela. Os modelos já podem ser consultados na aba Modelos."
            />
          )}
        </div>
      </div>

      <DetalheModelo
        modelo={mAberto}
        emUso={emUso}
        encaixe={mAberto ? encaixe[mAberto.id]?.[perfil.porte]?.[perfil.ativ] ?? null : null}
        perfil={perfil}
        portes={portes}
        atividades={atividades}
        casos={casos}
        podeConfigurar={pode_configurar}
        onFechar={() => setAberto(null)}
      />
    </AppShellV2>
  );
}
