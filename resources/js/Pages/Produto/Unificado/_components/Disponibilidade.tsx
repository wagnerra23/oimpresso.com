/**
 * Badge de disponibilidade — o "tem?" da linha e do drawer, com o mesmo componente nos dois
 * lugares (handoff §4.6 e §7).
 *
 * Cor por TOKEN semântico do DS elevado (`*-soft` / `*-fg`), a mesma família que a
 * `FrescorPill` da golden master (`Pages/Cliente/_components/Pills.tsx`) usa — trocam
 * light/dark sozinhos. Zero cor crua (regra binária AP1).
 *
 * ⚠️ DESVIO DECLARADO do protótipo: lá "Não estocável" reusa o valor `distante` do badge de
 * frescor, que no bundle do DS cai em `fresc-cold` — o MESMO vermelho de "Sem estoque". Um
 * serviço não tem saldo por natureza; pintá-lo de vermelho afirma um problema que não existe
 * e treina o balcão a ignorar a cor. Aqui ele fica neutro (`muted`). Diferença fora da lista
 * aprovada do handoff §6 — precisa de aprovação antes de virar definitivo.
 */

import type { EstadoEstoque } from './catalogo';

const ESTILO: Record<EstadoEstoque['chave'], string> = {
  em: 'bg-success-soft text-success-fg border-success/20',
  baixo: 'bg-warning-soft text-warning-fg border-warning/20',
  sem: 'bg-destructive-soft text-destructive-fg border-destructive/20',
  nao: 'bg-muted text-muted-foreground border-border',
};

export function Disponibilidade({ estado }: { estado: EstadoEstoque }) {
  return (
    <span
      className={
        'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium whitespace-nowrap ' +
        ESTILO[estado.chave]
      }
      title={estado.rel === null ? estado.label : `${estado.label} · saldo ${estado.rel}`}
    >
      <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true" />
      {estado.label}
      {/* O saldo viaja DENTRO do badge (o `rel` do protótipo): quem lê "Estoque baixo" precisa
          do número na mesma sacada pra decidir se dá pra vender. */}
      {estado.rel !== null && (
        <span className="tabular-nums opacity-70">{estado.rel}</span>
      )}
    </span>
  );
}

export default Disponibilidade;
