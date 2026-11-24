/**
 * Resumo da comissão no fechamento da Nova venda.
 *
 * Espelha `ComissaoResumo` de `prototipo-ui/cowork/Wagner/venda-v3.jsx` (seção
 * sells-comissao.jsx), a âncora declarada em `related_prototype`: quanto a venda paga de
 * comissão e quanto isso é da venda, um selo por beneficiário com o valor dele, o gatilho
 * e o botão que abre a gaveta.
 *
 * Substitui o bloco que mostrava só a "Base líquida" e um parágrafo de bastidor
 * ("fazem parte do porte da comissão — próximo passo"), texto que o operador lia como se
 * fosse da tela.
 *
 * ⚠️ NÃO calcula nada novo. Os valores saem de `comissaoTotal`, `comissaoDoBeneficiario`,
 * `percentualSobreALiquida` e `comissaoLiberada`, as mesmas funções que a gaveta usa, com as
 * mesmas bases (`totais`), para os dois lugares mostrarem o mesmo número.
 */

import { Button } from '@/Components/ui/button';
import { Lbl, Pill } from './primitivos';
import { brl, num } from './numeros';
import {
  GATILHOS,
  TIPOS_BENEFICIARIO,
  comissaoDoBeneficiario,
  comissaoLiberada,
  comissaoTotal,
  percentualSobreALiquida,
  type Beneficiario,
  type Gatilho,
  type TipoBeneficiario,
  type TotaisDaVenda,
} from './comissao-dominio';
import { RECEBIDA, type Parcela } from './parcelas-dominio';

/* Cor do selo por tipo, a mesma tríade do `COM_TIPOS` da âncora:
   funcionário = accent, representante = info, agência = warn, técnico = pos. */
const TOM_DO_TIPO: Record<TipoBeneficiario, 'primary' | 'info' | 'warning' | 'success'> = {
  funcionario: 'primary',
  representante: 'info',
  agencia: 'warning',
  tecnico: 'success',
};

const rotuloDoTipo = (t: TipoBeneficiario) => TIPOS_BENEFICIARIO.find((x) => x.k === t)?.l ?? t;

export default function ResumoComissao({
  beneficiarios,
  totais,
  gatilho,
  parcelas,
  totalDaVenda,
  onAbrir,
}: {
  beneficiarios: Beneficiario[];
  totais: TotaisDaVenda;
  gatilho: Gatilho;
  parcelas: Parcela[];
  totalDaVenda: number;
  onAbrir: () => void;
}) {
  const soma = comissaoTotal(beneficiarios, totais);
  const pctDaVenda = percentualSobreALiquida(soma, totais);
  const rotuloGatilho = GATILHOS.find((g) => g.value === gatilho)?.label ?? GATILHOS[0].label;
  const porParcela = gatilho === 'recebimento' && parcelas.length > 0;
  const recebidas = porParcela ? parcelas.filter((p) => p.lanc === RECEBIDA).length : 0;
  const liberada = porParcela ? comissaoLiberada(soma, gatilho, parcelas, totalDaVenda) : 0;

  return (
    <div
      data-testid="resumo-comissao"
      className="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-muted p-3"
    >
      <div>
        <Lbl>Comissão da venda</Lbl>
        <b className="font-mono text-[15px] font-semibold leading-none">{brl(soma)}</b>
        {soma > 0 && (
          <span className="font-mono text-[11.5px] leading-none text-muted-foreground">
            {' '}
            · {num(pctDaVenda, 2)}% da venda
          </span>
        )}
      </div>

      <div className="flex min-w-0 flex-wrap gap-[5px]">
        {beneficiarios.length === 0 ? (
          <span className="text-[12px] leading-[1.4] text-muted-foreground">Nenhum beneficiário — venda sem comissão</span>
        ) : (
          beneficiarios.map((b) => (
            <Pill key={b.k} tom={TOM_DO_TIPO[b.tipo]}>
              {b.nome || rotuloDoTipo(b.tipo)} · {brl(comissaoDoBeneficiario(b, totais))}
            </Pill>
          ))
        )}
      </div>

      <span className="ml-auto flex items-center gap-2">
        {porParcela && (
          <Pill mono tom={liberada > 0 ? 'success' : 'neutro'} title="Comissão já devida pelas parcelas baixadas">
            {brl(liberada)} devida · {recebidas}/{parcelas.length} parcelas
          </Pill>
        )}
        <Pill mono title="Quando a comissão passa a ser devida">
          {rotuloGatilho}
        </Pill>
        <Button type="button" variant="outline" size="sm" onClick={onAbrir}>
          Configurar comissão
        </Button>
      </span>
    </div>
  );
}
