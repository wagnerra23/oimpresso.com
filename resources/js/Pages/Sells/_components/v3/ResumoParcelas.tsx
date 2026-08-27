/**
 * Resumo das parcelas no fechamento — onda 3 do preview `/sells/create-v3`.
 *
 * Existe porque gerar parcelas não aparecia em lugar nenhum da tela principal: o
 * drawer dividia o total, fechava, e o único sinal de que algo aconteceu era o
 * rótulo do botão virar `Parcelas (2)…`. Quem gerou não tinha como conferir
 * quantas, de quanto e pra quando sem reabrir o drawer — e reabrir para conferir
 * é o oposto de um fechamento que se lê de relance.
 *
 * Espelha `prototipo-ui/cowork/venda-v3/sells-create.jsx:429-445` (a âncora
 * declarada em `related_prototype`): pill com a quantidade, o tipo, atalho de
 * edição, e as primeiras parcelas com número, vencimento e valor.
 *
 * ⚠️ NÃO calcula nada. Lê `parcelas` e formata — o `pago`/`saldo` do fechamento
 * é outra conversa (território Tier 0 de VALOR, que exige prova por dois
 * caminhos e antes→depois). Aqui o `✓` só REPORTA o `lanc` que o drawer gravou.
 */

import { Inline, Stack } from '@/Components/layout';
import { Pill } from './primitivos';
import { fmtBR, parseBR } from './numeros';
import { RECEBIDA, dataBR, type Parcela } from './parcelas-dominio';

/** Quantas parcelas cabem antes de virar "+N" — 4 é o corte da âncora. */
export const PARCELAS_VISIVEIS = 4;

export default function ResumoParcelas({
  parcelas,
  onEditar,
}: {
  parcelas: Parcela[];
  onEditar: () => void;
}) {
  if (parcelas.length === 0) return null;

  const excedente = parcelas.length - PARCELAS_VISIVEIS;

  return (
    <div className="mb-3 rounded-lg border border-border bg-muted/40 px-3 py-2">
      <Inline gap={2} align="center" className="mb-2">
        <Pill tom="primary" mono>
          {parcelas.length}x
        </Pill>
        <span className="min-w-0 overflow-hidden text-ellipsis whitespace-nowrap text-[11.5px] text-muted-foreground">
          {parcelas[0]!.tipo}
        </span>
        <button
          type="button"
          onClick={onEditar}
          className="ml-auto flex-none text-[11.5px] font-semibold text-primary hover:underline"
        >
          Editar parcelas
        </button>
      </Inline>

      {/* `asChild` mantém a semântica de lista (`ul`/`li`) sob os primitivos de
          layout — o teste exercita `role="listitem"`, e trocar por `div` seria
          perder a11y pra satisfazer a catraca. */}
      <Stack asChild gap={1} className="gap-[3px]">
        <ul>
          {parcelas.slice(0, PARCELAS_VISIVEIS).map((p) => (
            <Inline asChild gap={2} align="center" key={p.k}>
              <li className="font-mono text-[11.5px] leading-[1.4] text-muted-foreground">
                <span className="flex-none">
                  {p.num}/{p.de}
                </span>
                <span className="flex-none">{dataBR(p.venc)}</span>
                <b className="ml-auto text-foreground">{fmtBR(parseBR(p.valor))}</b>
                {p.lanc === RECEBIDA && (
                  <span className="flex-none text-success" title="Recebida" aria-label="recebida">
                    ✓
                  </span>
                )}
              </li>
            </Inline>
          ))}
        </ul>
      </Stack>

      {excedente > 0 && (
        <span className="mt-1 block text-[11.5px] text-muted-foreground">
          +{excedente} {excedente === 1 ? 'parcela' : 'parcelas'}
        </span>
      )}
    </div>
  );
}
