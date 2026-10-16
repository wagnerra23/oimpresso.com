// Sistema › Fluxos e regras (ADR 0435) — modelos de fluxo (motor FSM) por porte × atividade.
// Fonte visual: prototipo-ui/cowork/Wagner/fluxos-page.jsx (CSS copiado inteiro em
// resources/css/cowork-fluxos-bundle.css). Lei: Index.charter.md · contrato: Index.casos.md.
//
// Abas como no protótipo: Modelos · Processos · Fluxos da empresa · Casos de uso. Neste PR só
// Modelos tem conteúdo (só leitura); as outras três entram nos PRs seguintes da sequência.

import '../../../css/cowork-fluxos-bundle.css';
import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import EmptyState from '@/Components/shared/EmptyState';
import AbaModelos from './_components/AbaModelos';
import type { MapaEncaixe, Modelo, Opcao, Perfil } from './_components/tipos';

type Aba = 'modelos' | 'processos' | 'empresa' | 'casos';

interface Props {
  aba: Aba;
  modelos: Modelo[];
  encaixe: MapaEncaixe;
  portes: Opcao[];
  atividades: Opcao[];
  casos_total: number;
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

export default function FluxosIndex({ aba, modelos, encaixe, portes, atividades, casos_total }: Props) {
  const [perfil, setPerfil] = useState<Perfil>(PERFIL_INICIAL);
  const rotuloAba = ABAS.find((a) => a.key === aba)?.label ?? 'Modelos';

  return (
    <AppShellV2 title="Fluxos e regras" breadcrumbItems={[{ label: 'Sistema' }, { label: 'Fluxos e regras' }]}>
      <Head title={`Fluxos e regras · ${rotuloAba}`} />
      <div className="flx-root" data-screen-label={`Fluxos e regras · ${rotuloAba}`}>
        <PageHeader
          title="Fluxos e regras"
          subtitle={`${modelos.length} modelos · ${casos_total} casos de uso`}
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
          {aba === 'modelos' && (
            <AbaModelos
              modelos={modelos}
              encaixe={encaixe}
              portes={portes}
              atividades={atividades}
              perfil={perfil}
              onPerfil={setPerfil}
            />
          )}
          {aba !== 'modelos' && (
            <EmptyState
              title={`${rotuloAba}: em construção`}
              description="Esta aba chega nos próximos passos da tela. Os modelos já podem ser consultados na aba Modelos."
            />
          )}
        </div>
      </div>
    </AppShellV2>
  );
}
