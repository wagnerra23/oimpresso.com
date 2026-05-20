// @memcofre
//   tela: /fiscal/sped
//   module: Fiscal
//   stories: US-FISCAL-010 (SPED placeholder), US-FISCAL-016 (gerador EFD-ICMS/IPI MVP — PR #8)
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
          background: 'linear-gradient(135deg, var(--ok-soft, #d4f4dd), white)',
          border: '1px solid var(--ok, #2da764)',
          textAlign: 'left',
          padding: 18,
          marginBottom: 18,
        }}>
          <b style={{ color: 'var(--ok, #2da764)' }}>✅ Gerador EFD-ICMS/IPI MVP disponível (PR #8)</b>
          <p style={{ fontSize: 12 }}>{notice}</p>
          <small>
            <b>Próximas Waves:</b> Bloco E (apuração ICMS · saldo mês anterior) · Bloco H (inventário anual)
            · EFD-Contribuições (PIS/COFINS arquivo separado) · Entradas via DF-e manifestada.
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
                      {p.notasAutorizadas > 0 ? (
                        <a
                          href={`/fiscal/sped/icms-ipi/${p.mesIso.split('-')[0]}/${parseInt(p.mesIso.split('-')[1] ?? '1', 10)}`}
                          className="fx-btn primary"
                          title={`Baixar EFD-ICMS-IPI ${p.mes} (.txt CONFAZ v3.1.1)`}
                          download
                          style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}
                        >
                          <Download size={11} />
                          <span style={{ fontSize: 10 }}>.txt</span>
                        </a>
                      ) : (
                        <button className="fx-btn ghost" disabled title="Sem notas autorizadas no período">
                          <Download size={11} />
                        </button>
                      )}
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
