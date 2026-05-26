// NFSeDrawer.tsx — Slide-in 480px versão leve pra NFS-e (serviço).
//
// Port do fiscal-page.jsx §NFSeDrawer. NFS-e tem campos diferentes de NF-e
// (cód serviço LC 116, ISS%, tomador município, sem chave 44 dígitos).
//
// Drawer mais simples — NFS-e geralmente tem menos eventos vinculados e
// ciclo de vida mais curto.
//
// Refactor onda 1 (2026-05-26): shell migrado pra <DrawerBase> compartilhado.

import { brl, formatDoc } from '../_lib/fiscal-helpers';

import DrawerBase from './_shared/DrawerBase';

export type NFSeStatus = 'autorizada' | 'processando' | 'rejeitada' | 'cancelada';

export interface NFSeDrawerData {
  id: string | number;
  num: string;
  competencia: string; // ex "05/2026"
  tomador: string;
  cnpj?: string | null;
  cpf?: string | null;
  municipio: string; // ex "São Paulo/SP"
  iss: number; // alíquota %
  codServ: string; // código LC 116 (ex "14.05")
  ref?: string | null; // ex "OS #4807"
  when: string;
  status: NFSeStatus;
  rejMsg?: string | null;
  value: number;
}

interface NFSeDrawerProps {
  nota: NFSeDrawerData | null;
  onClose: () => void;
}

const STATUS_TONE: Record<NFSeStatus, 'ok' | 'warn' | 'bad'> = {
  autorizada: 'ok',
  processando: 'warn',
  rejeitada: 'bad',
  cancelada: 'bad',
};

const STATUS_LABEL: Record<NFSeStatus, string> = {
  autorizada: 'Autorizada',
  processando: 'Processando',
  rejeitada: 'Rejeitada',
  cancelada: 'Cancelada',
};

export default function NFSeDrawer({ nota, onClose }: NFSeDrawerProps) {
  if (!nota) return null;

  const tone = STATUS_TONE[nota.status];

  return (
    <DrawerBase
      open={!!nota}
      onClose={onClose}
      ariaLabel={`Detalhe NFS-e ${nota.num}`}
      header={
        <div>
          <small>NFS-e · Sistema Nacional · LC 214/2025</small>
          <h2>NFSe {nota.num}</h2>
          <code className="fx-drawer-key">cód. serviço {nota.codServ} · {nota.competencia}</code>
        </div>
      }
      footer={
        <>
          <button type="button" className="fx-btn ghost" disabled title="Wire-up no PR seguinte">
            Reconsultar prefeitura
          </button>
          <div className="fx-drawer-f-r">
            <button type="button" className="fx-btn ghost" disabled title="Download XML">XML</button>
            <button type="button" className="fx-btn ghost" disabled title="Download DANFSe">DANFSe</button>
            {nota.status === 'autorizada' && (
              <button type="button" className="fx-btn danger" disabled title="Wire-up no PR seguinte">
                Cancelar
              </button>
            )}
          </div>
        </>
      }
    >
      <section className="fx-drawer-sec">
        <h4>Status prefeitura</h4>
        <div className="fx-drawer-status-row">
          <span className={`fx-sefaz ${tone}`}>
            <span className="lbl">{STATUS_LABEL[nota.status]}</span>
          </span>
        </div>
        {nota.rejMsg && <div className="fx-drawer-rej">↳ {nota.rejMsg}</div>}
      </section>

      <section className="fx-drawer-sec">
        <h4>Operação</h4>
        <dl className="fx-kv">
          <dt>Tomador</dt>
          <dd><b>{nota.tomador}</b></dd>
          <dt>{nota.cnpj ? 'CNPJ' : 'CPF'}</dt>
          <dd className="fx-mono">{formatDoc(nota.cnpj, nota.cpf)}</dd>
          <dt>Município</dt>
          <dd>{nota.municipio} · {nota.iss}% ISS</dd>
          {nota.ref && (
            <>
              <dt>Referência</dt>
              <dd>{nota.ref}</dd>
            </>
          )}
          <dt>Emissão</dt>
          <dd>{nota.when}</dd>
          <dt>Valor</dt>
          <dd className="fx-strong">{brl(nota.value)}</dd>
        </dl>
      </section>
    </DrawerBase>
  );
}
