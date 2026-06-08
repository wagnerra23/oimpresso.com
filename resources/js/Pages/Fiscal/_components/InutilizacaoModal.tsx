// InutilizacaoModal.tsx — modal pra inutilizar faixa numérica de NFe
// PR #5 Wave (Wave 5). Delega POST /fiscal/acoes/nfe/inutilizar (AcoesController).
//
// SEFAZ regras (CONFAZ Ajuste SINIEF 07/2005 Art. 14):
//   - Modelo: 55 (NFe B2B) ou 65 (NFC-e)
//   - Série: até 3 chars
//   - Faixa: numero_de ≤ numero_ate
//   - Justificativa: 15-255 chars
//
// Use quando há "buraco" no sequencial fiscal (NFe rejeitada/erro_envio que
// pegou número mas não autorizou) — fecha anualmente sem multa.

import { router } from '@inertiajs/react';
import { Eraser, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface InutilizacaoModalProps {
  open: boolean;
  onClose: () => void;
  defaultModelo?: '55' | '65';
  defaultSerie?: string;
}

export default function InutilizacaoModal({
  open,
  onClose,
  defaultModelo = '55',
  defaultSerie = '1',
}: InutilizacaoModalProps) {
  const [modelo, setModelo] = useState<'55' | '65'>(defaultModelo);
  const [serie, setSerie] = useState(defaultSerie);
  const [numeroDe, setNumeroDe] = useState('');
  const [numeroAte, setNumeroAte] = useState('');
  const [justificativa, setJustificativa] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!open) return;
    const h = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !busy) onClose();
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [open, onClose, busy]);

  useEffect(() => {
    if (open) {
      setModelo(defaultModelo);
      setSerie(defaultSerie);
      setNumeroDe('');
      setNumeroAte('');
      setJustificativa('');
    }
  }, [open, defaultModelo, defaultSerie]);

  if (!open) return null;

  const nDe = parseInt(numeroDe, 10);
  const nAte = parseInt(numeroAte, 10);
  const faixaValida = !Number.isNaN(nDe) && !Number.isNaN(nAte) && nDe >= 1 && nAte >= nDe;
  const justValida = justificativa.trim().length >= 15 && justificativa.length <= 255;
  const podeEnviar = faixaValida && justValida && !busy;

  const handleEnviar = () => {
    if (!podeEnviar) return;
    setBusy(true);
    router.post(
      '/fiscal/acoes/nfe/inutilizar',
      {
        modelo,
        serie,
        numero_de: nDe,
        numero_ate: nAte,
        justificativa,
      },
      {
        preserveScroll: true,
        onFinish: () => {
          setBusy(false);
          onClose();
        },
      },
    );
  };

  return (
    <div className="fx-drawer-bg" onClick={() => !busy && onClose()}>
      <div
        role="dialog"
        aria-label="Inutilizar faixa numérica"
        onClick={(e) => e.stopPropagation()}
        style={{
          background: 'white',
          borderRadius: 10,
          padding: 22,
          width: 520,
          maxWidth: '92vw',
          margin: '10vh auto',
          boxShadow: '0 12px 40px rgba(0,0,0,.2)',
        }}
      >
        <header style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 }}>
          <h3 style={{ margin: 0, fontSize: 16, fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: 8 }}>
            <Eraser size={16}/> Inutilizar faixa numérica
          </h3>
          <button
            type="button"
            className="fx-drawer-x"
            onClick={onClose}
            disabled={busy}
            aria-label="Fechar"
            style={{ background: 'transparent', border: 0, cursor: 'pointer' }}
          >
            <X size={16}/>
          </button>
        </header>
        <p style={{ fontSize: 12.5, color: 'var(--fx-text-dim)', margin: '0 0 14px' }}>
          Fecha "buracos" no sequencial fiscal (NFe rejeitada/erro_envio que pegou número mas não autorizou).
          SEFAZ cstat=102 · CONFAZ SINIEF 07/2005 Art. 14.
        </p>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: 10, marginBottom: 12 }}>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11.5, color: 'var(--fx-text-dim)' }}>
            Modelo
            <select
              value={modelo}
              onChange={(e) => setModelo(e.target.value as '55' | '65')}
              disabled={busy}
              style={{ padding: 8, fontSize: 12.5, border: '1px solid var(--fx-border)', borderRadius: 7 }}
            >
              <option value="55">55 (NF-e)</option>
              <option value="65">65 (NFC-e)</option>
            </select>
          </label>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11.5, color: 'var(--fx-text-dim)' }}>
            Série
            <input
              type="text"
              value={serie}
              onChange={(e) => setSerie(e.target.value.slice(0, 3))}
              disabled={busy}
              maxLength={3}
              style={{ padding: 8, fontSize: 12.5, border: '1px solid var(--fx-border)', borderRadius: 7, fontFamily: 'inherit' }}
            />
          </label>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11.5, color: 'var(--fx-text-dim)' }}>
            Número de
            <input
              type="number"
              value={numeroDe}
              onChange={(e) => setNumeroDe(e.target.value)}
              disabled={busy}
              min={1}
              placeholder="123"
              style={{ padding: 8, fontSize: 12.5, border: '1px solid var(--fx-border)', borderRadius: 7, fontFamily: 'inherit' }}
            />
          </label>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11.5, color: 'var(--fx-text-dim)' }}>
            Até
            <input
              type="number"
              value={numeroAte}
              onChange={(e) => setNumeroAte(e.target.value)}
              disabled={busy}
              min={Number.isNaN(nDe) ? 1 : nDe}
              placeholder="125"
              style={{ padding: 8, fontSize: 12.5, border: '1px solid var(--fx-border)', borderRadius: 7, fontFamily: 'inherit' }}
            />
          </label>
        </div>

        <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 11.5, color: 'var(--fx-text-dim)', marginBottom: 6 }}>
          Justificativa (15-255 chars · obrigatória SEFAZ)
          <textarea
            value={justificativa}
            onChange={(e) => setJustificativa(e.target.value.slice(0, 255))}
            placeholder="Ex: NFe nº 124 rejeitada por SEFAZ (cstat 539 duplicidade); número será inutilizado e retransmitido."
            rows={3}
            disabled={busy}
            style={{
              padding: 10,
              fontSize: 12.5,
              border: '1px solid var(--fx-border)',
              borderRadius: 7,
              fontFamily: 'inherit',
              resize: 'vertical',
            }}
          />
        </label>
        <div style={{ fontSize: 11, color: 'var(--fx-text-mute)', margin: '0 0 14px' }}>
          {justificativa.length}/255 ·{' '}
          {justificativa.trim().length < 15
            ? `faltam ${15 - justificativa.trim().length} chars`
            : '✅ ok'}
          {!faixaValida && (numeroDe || numeroAte) && ' · ⚠️ faixa inválida (de ≤ até)'}
        </div>

        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
          <button className="fx-btn ghost" onClick={onClose} disabled={busy}>
            Voltar
          </button>
          <button className="fx-btn warn" onClick={handleEnviar} disabled={!podeEnviar}>
            {busy ? 'Enviando…' : `Inutilizar ${faixaValida ? `[${nDe}..${nAte}]` : 'faixa'}`}
          </button>
        </div>
      </div>
    </div>
  );
}
