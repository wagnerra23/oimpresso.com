// FiscalStatusBadge — testa o COMPONENTE ÚNICO de status fiscal (handoff Cowork 2026-06-02).
//
// Cobre o que importa pra Larissa: o mesmo status renderiza igual nos 3 documentos
// (NFC-e 65 · NF-e 55 · NFS-e) — vocabulário + label coerentes. Determinístico, sem fetch.
//
// Nota: usa getByText (lança se ausente = asserção de presença) + textContent, em vez
// de @testing-library/jest-dom (não está no setup do projeto — ver tests/js/setup.ts).

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

import { FiscalStatusBadge } from '@/Components/NfeBrasil/FiscalStatusBadge';
import { docLabel, emissaoStatusToKind } from '@/Components/NfeBrasil/fiscalStatus';

afterEach(cleanup);

describe('FiscalStatusBadge — helpers de domínio', () => {
  it('docLabel cobre os 3 documentos + normaliza rótulo legado NFe', () => {
    expect(docLabel('65')).toBe('NFC-e');
    expect(docLabel('55')).toBe('NF-e');
    expect(docLabel('nfse')).toBe('NFS-e');
    expect(docLabel('NFe')).toBe('NF-e'); // rótulo legado dos hooks
    expect(docLabel(null)).toBe('Documento fiscal');
  });

  it('emissaoStatusToKind mapeia os status string dos hooks p/ kind semântico', () => {
    expect(emissaoStatusToKind('autorizada')).toBe('authorized');
    expect(emissaoStatusToKind('rejeitada')).toBe('rejected');
    expect(emissaoStatusToKind('denegada')).toBe('denied');
    expect(emissaoStatusToKind('cancelada')).toBe('cancelled');
    expect(emissaoStatusToKind('inutilizada')).toBe('inutilized');
    expect(emissaoStatusToKind('pendente')).toBe('waiting');
    expect(emissaoStatusToKind(null)).toBe('emitting');
  });
});

describe('FiscalStatusBadge — apresentação unificada', () => {
  it('autorizada (banner) mostra doc + número + chave nos 3 modelos', () => {
    for (const [model, label] of [['65', 'NFC-e'], ['55', 'NF-e'], ['nfse', 'NFS-e']] as const) {
      render(
        <FiscalStatusBadge status="autorizada" model={model} numero={42} chave="CHAVE-44" />,
      );
      expect(screen.getByText(`${label} #42 autorizada`)).toBeTruthy();
      expect(screen.getByText(/Chave CHAVE-44/)).toBeTruthy();
      cleanup();
    }
  });

  it('rejeitada (banner) mostra motivo + cstat', () => {
    render(
      <FiscalStatusBadge status="rejeitada" model="55" cstat="691" motivo="NCM divergente" />,
    );
    expect(screen.getByText('NF-e rejeitada')).toBeTruthy();
    expect(screen.getByText(/cstat 691 — NCM divergente/)).toBeTruthy();
  });

  it('pill é compacto — só a palavra de estado, com role=status', () => {
    render(<FiscalStatusBadge variant="pill" model="65" status="autorizada" numero={7} />);
    const pill = screen.getByRole('status');
    expect(pill.textContent).toContain('Autorizada');
    // pill não despeja o título longo "#7 autorizada"
    expect(pill.textContent).not.toContain('#7');
  });

  it('aceita kind semântico direto (waiting) sem passar pelo mapeamento string', () => {
    render(<FiscalStatusBadge status="waiting" model="nfse" />);
    expect(screen.getByText('NFS-e processando')).toBeTruthy();
  });

  it('título e detalhe podem ser sobrescritos (usado pelo wrapper de polling)', () => {
    render(
      <FiscalStatusBadge
        status="waiting"
        label="NFC-e"
        title="NFC-e: aguardando SEFAZ"
        detail="A SEFAZ pode estar lenta."
      />,
    );
    expect(screen.getByText('NFC-e: aguardando SEFAZ')).toBeTruthy();
    expect(screen.getByText('A SEFAZ pode estar lenta.')).toBeTruthy();
  });
});
