// EventosDrawer.tsx — Slide-in 640px com tabela de eventos fiscais.
//
// Port do fiscal-page.jsx §EventosDrawer (Onda 2 D). Invocado pelo chip
// "Eventos" do header do Cockpit, em vez de navegar pra /fiscal/eventos.
// Mais ágil — Eliana fica no Cockpit, abre/fecha sem perder contexto.
//
// Mostra mesma data que /fiscal/eventos mas em formato compacto. Pra
// detalhe profundo (filtros, +período), botão "Ver tudo" leva pra página.
//
// Refactor onda 1 (2026-05-26): shell migrado pra <DrawerBase> compartilhado.

import { router } from '@inertiajs/react';
import { Activity, ExternalLink } from 'lucide-react';

import { sefazLabel, sefazTone } from '../_lib/sefaz-codes';

import DrawerBase from './_shared/DrawerBase';

export type EventoKind = 'cce' | 'cancel' | 'epec' | 'manifest' | 'inutilizacao';

export interface EventoFiscal {
  id: number | string;
  tipo: string; // 'Cancelamento' | 'Carta de Correção' | ...
  kind: EventoKind;
  nota?: string; // ex "NFe 8425"
  sequencia?: number;
  descricao: string;
  emit: string;
  autor?: string;
  sefaz: number;
}

interface EventosDrawerProps {
  open: boolean;
  eventos: EventoFiscal[];
  onClose: () => void;
}

const KIND_LABEL: Record<EventoKind, string> = {
  cce: 'CC-e',
  cancel: 'Cancelamento',
  epec: 'EPEC',
  manifest: 'Manifestação',
  inutilizacao: 'Inutilização',
};

export default function EventosDrawer({ open, eventos, onClose }: EventosDrawerProps) {
  if (!open) return null;

  const crit = eventos.filter((e) => e.kind === 'cancel').length;
  const total = eventos.length;

  return (
    <DrawerBase
      open={open}
      onClose={onClose}
      ariaLabel="Eventos fiscais"
      width={640}
      bodyFlush
      header={
        <div>
          <small>Eventos fiscais</small>
          <h2>CC-e · cancelamento · inutilização</h2>
          <small style={{ color: 'var(--fx-text-mute)' }}>
            {total} eventos esta semana
            {crit > 0 && ` · ${crit} cancelamento${crit > 1 ? 's' : ''}`} · janelas legais validadas automaticamente
          </small>
        </div>
      }
      footer={
        <>
          <small style={{ color: 'var(--fx-text-mute)' }}>
            Janelas legais: CC-e 30d · cancelamento 24h NFC-e / 168h NF-e · inutilização faixas
          </small>
          <div className="fx-drawer-f-r">
            <button
              type="button"
              className="fx-btn ghost"
              onClick={() => {
                onClose();
                router.visit('/fiscal/eventos');
              }}
            >
              <ExternalLink size={12} /> Ver tudo
            </button>
          </div>
        </>
      }
    >
      {eventos.length === 0 ? (
        <div className="fx-empty" style={{ margin: 20 }}>
          <Activity size={20} />
          <b>Nenhum evento no período</b>
          <small>Eventos aparecem após cancelamento, CC-e, EPEC ou manifestação.</small>
        </div>
      ) : (
        <div className="fx-table" style={{ borderRadius: 0, border: 'none', borderTop: '1px solid var(--fx-border)' }}>
          <table>
            <thead>
              <tr>
                <th style={{ width: 130 }}>Tipo</th>
                <th style={{ width: 120 }}>Documento</th>
                <th>Descrição</th>
                <th style={{ width: 90 }}>Emissão</th>
                <th style={{ width: 100 }}>Autor</th>
                <th style={{ width: 130 }}>SEFAZ</th>
              </tr>
            </thead>
            <tbody>
              {eventos.map((e) => {
                return (
                  <tr key={e.id}>
                    <td>
                      <span className={`fx-tl-badge ${e.kind === 'cancel' ? 'cancel' : e.kind === 'epec' || e.kind === 'inutilizacao' ? 'epec' : e.kind === 'cce' ? 'cce' : 'manifest'}`}>
                        {KIND_LABEL[e.kind]}{e.sequencia ? ` seq ${e.sequencia}` : ''}
                      </span>
                    </td>
                    <td><b className="fx-mono">{e.nota ?? '—'}</b></td>
                    <td style={{ fontSize: 12.5 }}>{e.descricao}</td>
                    <td><small className="fx-mut">{e.emit}</small></td>
                    <td><small className="fx-mut">{e.autor ?? '—'}</small></td>
                    <td>
                      <span className={`fx-sefaz ${sefazTone(e.sefaz)} compact`}>
                        <span className="code">{e.sefaz}</span>
                        <span className="lbl">{sefazLabel(e.sefaz)}</span>
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </DrawerBase>
  );
}
