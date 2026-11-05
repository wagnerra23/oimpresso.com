// O formulário de produto das telas Novo (Create.tsx) e Editar (Edit.tsx), na forma do protótipo
// (prototipo-ui/cowork/Wagner/produto-blade-forms.jsx, FormProduto): as quatro seções na ordem
// dele — Dados do produto, Configurações, Fiscal, Impostos e preços. O cabeçalho, o useForm, o envio
// e o rodapé de ações (AcoesDoCadastro) continuam em cada tela.

import type { FormEvent } from 'react';
import { BlocoDados } from './BlocoDados';
import { BlocoConfiguracoes } from './BlocoConfiguracoes';
import { BlocoFiscal } from './BlocoFiscal';
import { BlocoImpostosPrecos } from './BlocoImpostosPrecos';
import type { PrecoSimples } from './precos';
import type {
  CamposProduto,
  DefinirCampo,
  ErrosProduto,
  ModoFormulario,
  NaturezaFiscalProduto,
  OpcoesProduto,
} from './tipos';

interface Props {
  /** O id do <form>, que os botões do rodapé referenciam. */
  id: string;
  onSubmit: (e: FormEvent<HTMLFormElement>) => void;
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  errors: ErrosProduto;
  opcoes: OpcoesProduto;
  /** Editar: o rótulo do tipo, que não muda depois de criado. */
  tipoAtual?: string;
  /** Novo: mostra a validade quando a empresa a usa. */
  mostrarValidade?: boolean;
  /** Produto simples: o preço, mostrado no card "Impostos e preços". */
  preco?: { valor: PrecoSimples; mudar: (p: PrecoSimples) => void; aliquota: number };
  /** Editar: a natureza fiscal decidida pelo contador (UC-PEDIT-14). */
  naturezaFiscal?: NaturezaFiscalProduto | null;
}

export function ProdutoFormulario({
  id,
  onSubmit,
  modo,
  data,
  definir,
  errors,
  opcoes,
  tipoAtual,
  mostrarValidade,
  preco,
  naturezaFiscal,
}: Props) {
  return (
    <form id={id} onSubmit={onSubmit} className="space-y-6">
      <BlocoDados modo={modo} data={data} definir={definir} errors={errors} opcoes={opcoes} />
      <BlocoConfiguracoes
        modo={modo}
        data={data}
        definir={definir}
        errors={errors}
        mostrarValidade={mostrarValidade}
      />
      <BlocoFiscal data={data} definir={definir} errors={errors} naturezaFiscal={naturezaFiscal} />
      <BlocoImpostosPrecos
        modo={modo}
        data={data}
        definir={definir}
        opcoes={opcoes}
        tipoAtual={tipoAtual}
        preco={preco}
      />
    </form>
  );
}
