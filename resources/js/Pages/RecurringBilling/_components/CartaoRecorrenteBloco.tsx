// Cartão recorrente no drawer da assinatura (US-RB-011/013 · reaberta por [W] 2026-10-27).
// O protótipo (prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx) não desenha este bloco: segue a
// forma do PixAutomaticoBloco (mesmo `rounded-lg border` depois do fiscal). Mostra o cartão salvo como
// bandeira + final (nunca o número, que o oimpresso não tem) e as tentativas de cobrança da fatura
// em aberto, com o motivo da recusa.

export type TentativaStatus = 'pending' | 'sent' | 'succeeded' | 'failed' | 'soft_decline' | 'hard_decline';

export type CartaoRecorrente = {
  ultimos_4: string | null;
  bandeira: string | null;
  tentativas: { n: number; status: TentativaStatus; em: string | null; motivo: string | null }[];
};

const ROTULO: Record<TentativaStatus, string> = {
  pending: 'Em processamento',
  sent: 'Enviada ao gateway',
  succeeded: 'Aprovada',
  failed: 'Erro do gateway — tenta de novo',
  soft_decline: 'Recusa leve — tenta de novo',
  hard_decline: 'Recusa definitiva — cliente avisado',
};

function data(iso: string | null): string {
  if (!iso) return '';
  const [a, m, d] = iso.slice(0, 10).split('-');
  return `${d}/${m}/${a}`;
}

export default function CartaoRecorrenteBloco({ cartao }: { cartao: CartaoRecorrente }) {
  const temCartao = cartao.ultimos_4 !== null || cartao.bandeira !== null;

  return (
    <div className="rounded-lg border border-border p-3" data-contract="cartao-recorrente">
      <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Cartão recorrente</div>
      <p className="mt-1 text-xs text-foreground">
        {temCartao
          ? `${cartao.bandeira ?? 'Cartão'} final ${cartao.ultimos_4 ?? '····'}. Cada fatura é cobrada nele no vencimento.`
          : 'Nenhum cartão salvo. O cartão fica salvo quando o cliente paga a primeira fatura no cartão.'}
      </p>

      {cartao.tentativas.length > 0 && (
        <ol className="mt-2 space-y-1">
          {cartao.tentativas.map((t) => (
            <li key={t.n} className="text-[11px] text-muted-foreground">
              <span className="font-medium text-foreground">{t.n}ª tentativa</span> · {data(t.em)} · {ROTULO[t.status]}
              {t.motivo ? <span className="block break-words">{t.motivo}</span> : null}
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}
