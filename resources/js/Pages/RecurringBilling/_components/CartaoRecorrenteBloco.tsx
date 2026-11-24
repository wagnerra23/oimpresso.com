// Cartão recorrente no drawer da assinatura (US-RB-011 · reaberta por [W] 2026-10-27 · b5 2026-11-19).
// Forma: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx → CartaoRecorrenteCard (bloco `cr-card`
// depois do Pix Automático, só em assinatura no cartão). O cartão fica salvo como token quando o
// cliente paga a 1ª fatura no cartão pela página do gateway; o oimpresso nunca vê o número, então o
// bloco mostra só bandeira e final.
//
// Fora desta fatia: a lista de tentativas da fatura em aberto que o protótipo desenha. A régua de
// cartão (US-RB-013) ainda não tem motor, então não há tentativa para mostrar.

export type CartaoRecorrente = { ultimos_4: string | null; bandeira: string | null };

export default function CartaoRecorrenteBloco({ cartao }: { cartao: CartaoRecorrente }) {
  return (
    <div className="rounded-lg border border-border p-3" data-contract="cartao-recorrente">
      <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Cartão recorrente</div>
      <p className="mt-1 text-xs text-foreground">
        {cartao.ultimos_4
          ? `${cartao.bandeira || 'Cartão'} final ${cartao.ultimos_4}. Cada fatura é cobrada nele no vencimento.`
          : 'Nenhum cartão salvo. O cartão fica salvo quando o cliente paga a primeira fatura no cartão.'}
      </p>
    </div>
  );
}
