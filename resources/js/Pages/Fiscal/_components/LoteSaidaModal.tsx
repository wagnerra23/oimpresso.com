// LoteSaidaModal.tsx — confirmação das ações da barra de lote do Cockpit (UC-FCKP-21..23).
//
// Port do `confirmaSaida` do protótipo (`prototipo-ui/cowork/Wagner/fiscal-page.jsx:487-498`):
// antes de o arquivo sair do sistema (ou o e-mail ser disparado), a tela diz o que vai junto
// ("leva") e o aviso de LGPD. Renderiza INLINE (sem portal), dentro do `.cockpit`, para herdar
// os tokens — a lápide §5 2026-10-19 registra que portal não recebe token só com a classe.

import { Button } from '@/Components/ui/button';
import { X } from 'lucide-react';
import { useEffect } from 'react';

export type TipoSaidaLote = 'zip' | 'pdf' | 'email';

interface Props {
  tipo: TipoSaidaLote | null;
  /** Quantas notas marcadas entram (NF-e/NFC-e). */
  qtd: number;
  /** Quantas marcadas ficam fora por serem NFS-e (o lote é só de NF-e/NFC-e). */
  foraNfse: number;
  busy: boolean;
  onConfirm: () => void;
  onClose: () => void;
}

const LEVA = [
  'Nome ou razão social, CPF/CNPJ e endereço do destinatário de cada nota',
  'Itens, valores e chave de acesso',
];

export default function LoteSaidaModal({ tipo, qtd, foraNfse, busy, onConfirm, onClose }: Props) {
  useEffect(() => {
    if (!tipo) return;
    const h = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !busy) onClose();
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [tipo, busy, onClose]);

  if (!tipo) return null;

  const txt = `${qtd} nota${qtd > 1 ? 's' : ''}`;
  const spec = {
    zip: { titulo: `Baixar XMLs de ${txt}`, botao: 'Baixar ZIP' },
    pdf: { titulo: `Baixar DANFEs de ${txt}`, botao: 'Baixar PDF' },
    email: { titulo: `Reenviar ${txt} por e-mail`, botao: 'Reenviar' },
  }[tipo];
  const aviso = tipo === 'email'
    ? 'Cada nota vai só para o e-mail cadastrado do próprio destinatário.'
    : 'O arquivo sai do sistema: guarde e compartilhe só com quem precisa.';

  return (
    // Sem clique no fundo: fecha por Esc, "Cancelar" ou o X — o fundo clicável era um
    // elemento estático com handler (jsx-a11y), e confirmação de saída de dado pede gesto explícito.
    <div className="fx-drawer-bg">
      <div
        role="dialog"
        aria-modal="true"
        aria-label={spec.titulo}
        data-contract="lote-saida-modal"
        style={{
          background: 'var(--surface)',
          color: 'var(--text)',
          border: '1px solid var(--border)',
          borderRadius: 10,
          padding: '14px 16px',
          width: 460,
          maxWidth: '92vw',
          margin: '12vh auto',
          display: 'flex',
          flexDirection: 'column',
          gap: 12,
        }}
      >
        <header style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
          <h3 style={{ margin: 0, fontSize: 'var(--fs-5, 15px)', fontWeight: 600 }}>{spec.titulo}</h3>
          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            aria-label="Fechar"
            style={{ background: 'transparent', border: 0, cursor: 'pointer', color: 'var(--text-mute)' }}
          >
            <X size={16} />
          </button>
        </header>

        <div>
          <p style={{ margin: '0 0 4px', fontSize: 'var(--fs-2, 12px)', color: 'var(--text-mute)' }}>Leva:</p>
          <ul style={{ margin: 0, paddingLeft: 18, display: 'flex', flexDirection: 'column', gap: 3, fontSize: 'var(--fs-3, 13px)', color: 'var(--text-dim)' }}>
            {LEVA.map((l) => <li key={l}>{l}</li>)}
          </ul>
        </div>

        <p style={{ margin: 0, fontSize: 'var(--fs-3, 13px)', color: 'var(--text-dim)' }}>{aviso}</p>

        {foraNfse > 0 && (
          <p data-contract="lote-fora-nfse" style={{ margin: 0, fontSize: 'var(--fs-2, 12px)', color: 'var(--warn)' }}>
            {foraNfse} NFS-e marcada{foraNfse > 1 ? 's' : ''} fica{foraNfse > 1 ? 'm' : ''} fora: esta ação é só para NF-e e NFC-e.
          </p>
        )}

        <footer style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
          <Button type="button" variant="cowork-ghost" onClick={onClose} disabled={busy}>Cancelar</Button>
          <Button type="button" onClick={onConfirm} disabled={busy || qtd === 0}>
            {busy ? 'Processando…' : spec.botao}
          </Button>
        </footer>
      </div>
    </div>
  );
}
