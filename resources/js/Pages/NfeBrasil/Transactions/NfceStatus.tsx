// @memcofre tela=/nfe-brasil/transactions/{tx}/status module=NfeBrasil
//   us: US-NFE-002 fase 2C (UI status NFC-e pós-venda — polling)
//   adrs: UI-0008 (cockpit), 0058 (Centrifugo CT 100), 0062 (Hostinger sem daemons)
//   nota: Page demo do badge useNfceStatus + NfceStatusBadge. Usuário consulta
//         status de uma venda já finalizada. Quando broadcast Centrifugo entrar
//         no escopo, troca-se transport interno do hook — Page não muda.

import AppShellV2 from '@/Layouts/AppShellV2';
import { NfceStatusBadge } from '@/Components/NfeBrasil/NfceStatusBadge';
import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type React from 'react';

interface PageProps {
  transaction_id: number;
}

function NfceStatus({ transaction_id }: PageProps) {
  return (
    <>
      <Head title={`Status NFC-e — Venda #${transaction_id}`} />
      <div style={{ padding: '24px 28px', maxWidth: 720 }}>
        <div style={{ marginBottom: 18 }}>
          <a
            href="/sells"
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 6,
              fontSize: 13,
              color: 'oklch(0.55 0.05 240)',
              textDecoration: 'none',
            }}
          >
            <ArrowLeft size={14} />
            Voltar para vendas
          </a>
        </div>

        <h1 style={{ fontSize: 22, fontWeight: 600, marginBottom: 6 }}>
          Status fiscal — Venda #{transaction_id}
        </h1>
        <p style={{ fontSize: 13, opacity: 0.75, marginBottom: 22 }}>
          Acompanhe o resultado da emissão NFC-e enviada ao SEFAZ. Esta página
          atualiza automaticamente a cada 2 segundos até receber resposta final.
        </p>

        <NfceStatusBadge transactionId={transaction_id} />

        <div
          style={{
            marginTop: 20,
            padding: 14,
            borderRadius: 8,
            background: 'light-dark(oklch(0.97 0.005 240), oklch(0.22 0.01 240))',
            fontSize: 12,
            opacity: 0.8,
          }}
        >
          <strong>Por que polling em vez de tempo real?</strong>
          <p style={{ marginTop: 6, lineHeight: 1.5 }}>
            O ambiente Hostinger não roda daemons (ADR 0062). O broadcast em
            tempo real via Centrifugo (ADR 0058) está disponível no CT 100
            Proxmox e será integrado em fase futura. O polling cobre o caso
            de uso atual sem violar a separação de runtime.
          </p>
        </div>
      </div>
    </>
  );
}

NfceStatus.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Status NFC-e"
    breadcrumbItems={[
      { label: 'NF-e Brasil' },
      { label: 'Vendas' },
      { label: 'Status fiscal' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default NfceStatus;
