// @memcofre
//   tela: /fiscal/sped
//   module: Fiscal
//   stories: US-FISCAL-010 (SPED & Livros sub-página 7 do design KB-9.75)
//   adrs: 0093, 0094, 0101, 0104

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head } from '@inertiajs/react';
import { Archive, Download } from 'lucide-react';

import FxShell from './_components/FxShell';
import { brl } from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

interface Periodo {
  mes: string;       // 05/2026
  mesIso: string;    // 2026-05
  notasAutorizadas: number;
  valorAutorizado: number;
  status: 'aberto' | 'pronto' | 'entregue';
  prazoEntrega: string | null;
}

interface SpedProps {
  periodos: Periodo[];
  notice: string;
}

const STATUS_META: Record<Periodo['status'], { label: string; tone: 'ok' | 'warn' | 'bad' }> = {
  aberto:   { label: 'Em curso',  tone: 'warn' },
  pronto:   { label: 'Pronto',    tone: 'ok' },
  entregue: { label: 'Entregue',  tone: 'ok' },
};

export default function Sped({ periodos, notice }: SpedProps) {
  return (
    <AppShellV2>
      <Head title="Fiscal · SPED & Livros" />

      <FxShell
        route="sped"
        title="SPED & Livros"
        crumb="Apuração mensal · EFD ICMS-IPI · PIS/COFINS"
        env="em desenvolvimento"
        envTone="warn"
      >
        <div className="fx-empty" style={{
          background: 'linear-gradient(135deg, var(--warn-soft), white)',
          border: '1px solid var(--warn)',
          textAlign: 'left',
          padding: 18,
          marginBottom: 18,
        }}>
          <b style={{ color: 'var(--warn)' }}>⚠️ Gerador SPED em desenvolvimento</b>
          <p style={{ fontSize: 12 }}>{notice}</p>
          <small>
            Próxima entrega: SPED Fiscal EFD-Reinf + PIS/COFINS via Modules/NfeBrasil.
            Acompanhe em <code className="fx-mono">memory/requisitos/NfeBrasil/SPEC.md</code> US futuras.
          </small>
        </div>

        <div className="fx-table">
          <table>
            <thead>
              <tr>
                <th style={{ width: 110 }}>Competência</th>
                <th style={{ width: 140 }}>Status</th>
                <th style={{ textAlign: 'right' }}>Notas autorizadas</th>
                <th style={{ textAlign: 'right', width: 160 }}>Valor autorizado</th>
                <th style={{ width: 120 }}>Prazo entrega</th>
                <th style={{ width: 80, textAlign: 'center' }}>Export</th>
              </tr>
            </thead>
            <tbody>
              {periodos.map((p) => {
                const stMeta = STATUS_META[p.status];
                return (
                  <tr key={p.mesIso}>
                    <td className="fx-mono fx-strong">{p.mes}</td>
                    <td>
                      <span className={`fx-sefaz ${stMeta.tone}`}>
                        <span className="lbl">{stMeta.label}</span>
                      </span>
                    </td>
                    <td className="fx-mono" style={{ textAlign: 'right' }}>{p.notasAutorizadas}</td>
                    <td className="fx-mono fx-strong" style={{ textAlign: 'right' }}>{brl(p.valorAutorizado)}</td>
                    <td><small>{p.prazoEntrega ?? '—'}</small></td>
                    <td style={{ textAlign: 'center' }}>
                      <button className="fx-btn ghost" disabled title="Gerador em desenvolvimento">
                        <Download size={11} />
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="fx-empty" style={{ marginTop: 18 }}>
          <Archive size={20} />
          <b>Livros fiscais</b>
          <small>
            Apuração ICMS · Apuração ISS · Conciliação SEFAZ × ERP — em desenvolvimento.
            Por enquanto, conferir manualmente via relatórios em /financeiro/relatorios.
          </small>
        </div>
      </FxShell>
    </AppShellV2>
  );
}
