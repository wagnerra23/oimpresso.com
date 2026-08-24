/**
 * Badge de disponibilidade — o "tem?" da linha e do drawer, com o mesmo componente nos dois
 * lugares (handoff §4.6 e §7).
 *
 * Cor MEDIDA no componente do DS, não inferida. O protótipo não desenha este selo — ele chama
 * `DS.StatusBadge` com os tons `fresc-hot`/`fresc-warm`/`fresc-cold`, e a receita de lá é:
 *
 *   bg: color-mix(in oklch, var(--color-<tom>) 16%, transparent)
 *   border: color-mix(in oklch, var(--color-<tom>) 30%, transparent)
 *   fg: var(--color-<tom>)   ·   dot: true
 *
 * ⚠️ O patch de cor §2 pede 6%/22% AFIRMANDO ser "a mesma receita do guia do DS para Alert e
 * StatusBadge". Medido, não é: o StatusBadge usa 16%/30%. [W] escolheu o DS (2026-08-24).
 *
 * ⚠️ ÚNICO desvio do componente, declarado: o texto usa `-fg` e não o tom cheio. O `--color-warning`
 * é oklch(0.70 0.13 75) — sobre o fundo a 16% dá ~1,9:1 de contraste, ilegível em 12px. O par
 * `-fg` do próprio DS (oklch(0.55 0.12 75)) sobe pra ~3,1:1. O `fresc-cold` do bundle ainda traz
 * um `fg` LITERAL oklch(0.74 0.14 18), que é valor de tema escuro e além disso viola o §6 do
 * patch (zero literal na tela) — por isso `destructive-fg`.
 *
 * ⚠️ DESVIO DECLARADO do protótipo: lá "Não estocável" reusa o valor `distante` do badge de
 * frescor, que no bundle do DS cai em `fresc-cold` — o MESMO vermelho de "Sem saldo". Um
 * serviço não tem saldo por natureza; pintá-lo de vermelho afirma um problema que não existe
 * e treina o balcão a ignorar a cor. Aqui ele fica neutro (`muted`). Ratificado por [W] em
 * 2026-08-18.
 *
 * Quando o item tem saldo em mais de um local o conjunto ganha uma segunda linha — "N locais"
 * — que abre o detalhe por local. É a diferença entre "tem 128" e "tem 128, mas 0 na Loja": a
 * segunda resposta muda o que o balcão promete ao cliente.
 *
 * Na densidade COMPACTA (§3.2) essa segunda linha some. Ela é redundante: o número total já
 * está no selo, e quem precisa da quebra por local abre o painel. O que NÃO some em compacta
 * é marcador semântico — a regra do handoff é "remove só o redundante".
 */

import { AlertTriangle } from 'lucide-react';
import PopoverAncorado from './PopoverAncorado';
import { Inline } from '@/Components/layout';
import { qtdComUnidade, type EstadoEstoque, type LocalSaldo } from './catalogo';

const ESTILO: Record<EstadoEstoque['chave'], string> = {
  em: 'bg-success/16 border-success/30 text-success-fg',
  baixo: 'bg-warning/16 border-warning/30 text-warning-fg',
  sem: 'bg-destructive/16 border-destructive/30 text-destructive-fg',
  // Não estocável é AUSÊNCIA de saldo por natureza, não problema: fundo transparente e
  // texto apagado, sem ponto. Ele é o único dos quatro que não afirma um estado de estoque.
  nao: 'bg-transparent border-border text-muted-foreground',
};

function Pilula({ estado, unidade }: { estado: EstadoEstoque; unidade: string }) {
  return (
    <span
      className={
        // `rounded-full`: a raiz do StatusBadge é `borderRadius: c.mono ? var(--radius-sm) : 9999`,
        // e com rótulo de texto cai no 9999. Cheguei a trocar por `rounded-md` contando raios no
        // bundle INTEIRO em vez de medir o componente — erro corrigido em 2026-08-24.
        'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11.5px] font-medium whitespace-nowrap ' +
        ESTILO[estado.chave]
      }
      title={estado.rel === null ? estado.label : `${estado.label} · saldo ${estado.rel}`}
    >
      {estado.chave !== 'nao' && (
        <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true" />
      )}
      {estado.label}
      {/* Rótulo e valor na MESMA linha do selo (handoff 21/08 §4.3): quem lê "Abaixo do
          mínimo" precisa do número na mesma sacada pra decidir se dá pra vender. Com a
          unidade junto, porque "96" e "96 m²" prometem coisas diferentes ao cliente. */}
      {estado.rel !== null && (
        <span className="tabular-nums opacity-70">
          {estado.rel}
          {unidade ? ` ${unidade}` : ''}
        </span>
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
  /** Densidade compacta: esconde a linha "N locais" (redundante), nunca o selo. */
  densa?: boolean;
}

export function Disponibilidade({ estado, locais, unidade = '', nome = '', densa = false }: DisponibilidadeProps) {
  if (!locais || locais.length < 2 || densa) {
    return <Pilula estado={estado} unidade={unidade} />;
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
      <Pilula estado={estado} unidade={unidade} />
      <span className="mt-0.5 block text-[11px] text-muted-foreground underline decoration-dotted underline-offset-2 group-hover/est:text-foreground">
        {locais.length} locais
      </span>
    </PopoverAncorado>
  );
}

export default Disponibilidade;
