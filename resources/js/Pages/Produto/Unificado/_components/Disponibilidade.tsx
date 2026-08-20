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
 * e treina o balcão a ignorar a cor. Aqui ele fica neutro (`muted`). Ratificado por [W] em
 * 2026-08-18.
 *
 * Desde o pacote V2 (§4.6), quando o item tem saldo em mais de um local o conjunto ganha uma
 * segunda linha — "N locais" — que abre o detalhe por local. É a diferença entre "tem 128" e
 * "tem 128, mas 0 na Loja": a segunda resposta muda o que o balcão promete ao cliente.
 */

import { AlertTriangle } from 'lucide-react';
import PopoverAncorado from './PopoverAncorado';
import { Inline } from '@/Components/layout';
import { qtdComUnidade, type EstadoEstoque, type LocalSaldo } from './catalogo';

const ESTILO: Record<EstadoEstoque['chave'], string> = {
  em: 'bg-success-soft text-success-fg border-success/20',
  baixo: 'bg-warning-soft text-warning-fg border-warning/20',
  sem: 'bg-destructive-soft text-destructive-fg border-destructive/20',
  nao: 'bg-muted text-muted-foreground border-border',
};

function Pilula({ estado }: { estado: EstadoEstoque }) {
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

export interface DisponibilidadeProps {
  estado: EstadoEstoque;
  /** Saldo por local. Sem ele (ou com um local só) o badge não vira gatilho. */
  locais?: LocalSaldo[];
  unidade?: string;
  /** Nome do item — só pro rótulo acessível do gatilho. */
  nome?: string;
}

export function Disponibilidade({ estado, locais, unidade = '', nome = '' }: DisponibilidadeProps) {
  if (!locais || locais.length < 2) {
    return <Pilula estado={estado} />;
  }

  // Alerta só quando há zero em UM local E saldo em outro. Tudo zerado já é o badge vermelho;
  // repetir a informação numa linha de alerta só gasta a atenção que o caso misto precisa.
  const zerados = locais.filter((l) => l.qtd === 0);
  const alerta = zerados.length > 0 && locais.some((l) => l.qtd > 0)
    ? `0 na ${zerados.map((l) => l.nome).join(' e ')} — saldo em outro local.`
    : '';

  return (
    <PopoverAncorado
      rotulo={`Saldo por local de ${nome}`}
      largura={216}
      className="block text-left group/est"
      conteudo={
        <>
          <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1.5">
            Saldo por local
          </p>
          {locais.map((l) => (
            <Inline key={l.nome} gap={3} align="baseline" justify="between" className="py-0.5">
              <span className="text-[12px] truncate">{l.nome}</span>
              {/* Zero em destaque: é o local que NÃO resolve, e é o que muda a promessa. */}
              <span className={'font-mono text-[12px] tabular-nums shrink-0 ' + (l.qtd === 0 ? 'font-semibold text-destructive-fg' : '')}>
                {qtdComUnidade(l.qtd, unidade)}
              </span>
            </Inline>
          ))}
          {alerta && (
            <Inline gap={1} align="start" className="mt-1.5 pt-1.5 border-t border-border text-[11px] text-destructive-fg">
              <AlertTriangle size={12} className="mt-px shrink-0" />
              {alerta}
            </Inline>
          )}
        </>
      }
    >
      <Pilula estado={estado} />
      <span className="mt-0.5 block text-[11px] text-muted-foreground underline decoration-dotted underline-offset-2 group-hover/est:text-foreground">
        {locais.length} locais
      </span>
    </PopoverAncorado>
  );
}

export default Disponibilidade;
