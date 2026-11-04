// Seção "Fiscal" do formulário de produto (Novo e Editar), na forma do protótipo
// (produto-blade-forms.jsx): a natureza fiscal (no Editar), o NCM, o CEST e a origem da mercadoria.
// Até 2026-11-04 esses campos ficavam dentro de "Preço & Imposto". O CFOP padrão de venda do
// protótipo ainda não tem coluna no produto e fica fora desta tela.

import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { OrigemMercadoriaField } from '../OrigemMercadoriaField';
import { CampoNcm } from './CampoNcm';
import { CampoCest } from './CampoCest';
import { NaturezaFiscal } from './NaturezaFiscal';
import type { CamposProduto, DefinirCampo, ErrosProduto, NaturezaFiscalProduto } from './tipos';

interface Props {
  data: CamposProduto;
  definir: DefinirCampo;
  errors: ErrosProduto;
  /** Editar: a natureza fiscal decidida pelo contador (UC-PEDIT-14). */
  naturezaFiscal?: NaturezaFiscalProduto | null;
}

export function BlocoFiscal({ data, definir, errors, naturezaFiscal }: Props) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Fiscal</CardTitle>
      </CardHeader>
      {/* Quatro colunas: NCM, CEST e a origem, que ocupa duas (OrigemMercadoriaField é col-span-2). */}
      <CardContent className="grid grid-cols-4 gap-4">
        {naturezaFiscal && (
          <div className="col-span-4">
            <NaturezaFiscal natureza={naturezaFiscal} />
          </div>
        )}
        <CampoNcm
          value={data.ncm ?? ''}
          onChange={(v) => definir('ncm', v)}
          error={errors.ncm}
          servico={naturezaFiscal?.natureza === 'servico'}
        />
        <CampoCest value={data.cest ?? ''} onChange={(v) => definir('cest', v)} error={errors.cest} />
        <OrigemMercadoriaField
          value={data.origem_mercadoria}
          onChange={(v) => definir('origem_mercadoria', v)}
          error={errors.origem_mercadoria}
        />
      </CardContent>
    </Card>
  );
}
