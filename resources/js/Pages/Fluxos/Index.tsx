// Sistema › Fluxos e regras (ADR 0435) — modelos de fluxo (motor FSM) por porte × atividade.
// Fonte visual: prototipo-ui/cowork/Wagner/fluxos-page.jsx (CSS copiado inteiro em
// resources/css/cowork-fluxos-bundle.css). Lei: Index.charter.md · contrato: Index.casos.md.
//
// Abas como no protótipo: Modelos · Processos · Fluxos da empresa · Casos de uso. "Usar modelo" e mudar
// regra exigem fluxos.configurar (POST /fluxos/modelo, PATCH /fluxos/regra e /fluxos/compra).

import '../../../css/cowork-fluxos-bundle.css';
import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import AbaModelos from './_components/AbaModelos';
import AbaCasos from './_components/AbaCasos';
import AbaEmpresa from './_components/AbaEmpresa';
import AbaProcessos from './_components/AbaProcessos';
import DetalheModelo from './_components/DetalheModelo';
import type {
  CasoCatalogo, EmpresaModelo, MapaCasos, MapaEncaixe, MapaEstadosCaso, MapaFontes, MapaOpcoesCompra, Modelo, Opcao, Perfil,
  ProcessoCatalogo, RegraCompra, RegrasLigadas,
} from './_components/tipos';

type Aba = 'modelos' | 'processos' | 'empresa' | 'casos';

interface Props {
  aba: Aba;
  modelos: Modelo[];
  encaixe: MapaEncaixe;
  portes: Opcao[];
  atividades: Opcao[];
  casos_total: number;
  casos: MapaCasos;
  processos: ProcessoCatalogo[];
  processos_total: number;
  processo: string;
  casos_lista: CasoCatalogo[];
  fontes: MapaFontes;
  estados_caso: MapaEstadosCaso;
  dominios: Opcao[];
  empresa: EmpresaModelo | null;
  regras_ligadas: RegrasLigadas;
  regras_compra: RegraCompra[];
  opcoes_compra: MapaOpcoesCompra;
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
  aba, modelos, encaixe, portes, atividades, casos_total, casos, processos, processos_total, processo,
  casos_lista, fontes, estados_caso, dominios, empresa, regras_ligadas, regras_compra, opcoes_compra, pode_configurar,
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
          subtitle={`${modelos.length} modelos · ${casos_total} casos de uso · ${Object.values(fontes).filter((f) => f.url).length} sistemas pesquisados`}
          actions={emUso ? <span className="flx-dim">Em uso: <b>{emUso.nome}</b></span> : null}
        />
        <div className="px-5">
          <PageHeaderTabs
            ghosts={ABAS.map((a) => ({
              key: a.key,
              label: a.label,
              href: `/fluxos?aba=${a.key}`,
              badge: a.key === 'modelos' ? modelos.length : a.key === 'processos' ? processos_total : a.key === 'casos' ? casos_total : undefined,
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
              casos={casos}
              fontes={fontes}
            />
          )}
          {aba === 'empresa' && (
            <AbaEmpresa
              empresa={empresa}
              emUso={emUso}
              portes={portes}
              atividades={atividades}
              ligadas={regras_ligadas}
              compra={regras_compra}
              opcoesCompra={opcoes_compra}
              podeConfigurar={pode_configurar}
            />
          )}
          {aba === 'processos' && <AbaProcessos processos={processos} inicial={processo} />}
          {aba === 'casos' && (
            <AbaCasos
              casos={casos_lista}
              fontes={fontes}
              estados={estados_caso}
              dominios={dominios}
              portes={portes}
              modelos={modelos}
              emUsoId={emUso?.id ?? null}
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
