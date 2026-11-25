// O formulário de produto das telas Novo (Create.tsx) e Editar (Edit.tsx), na forma do protótipo
// (prototipo-ui/cowork/Wagner/produto-blade-forms.jsx, FormProduto): as quatro seções na ordem
// dele — Dados do produto, Configurações, Fiscal, Impostos e preços. O cabeçalho, o useForm, o envio
// e o rodapé de ações (AcoesDoCadastro) continuam em cada tela.

import '../../../../../css/cowork-produto-bundle.css';
import type { FormEvent } from 'react';
import { BlocoDados } from './BlocoDados';
import { BlocoConfiguracoes } from './BlocoConfiguracoes';
import { BlocoArmazenagem } from './BlocoArmazenagem';
import type { EnderecoNoEstoque, EstadoArmazenagem, SaldoPorLocal } from './armazenagem';
import { BlocoFiscal } from './BlocoFiscal';
import { BlocoImpostosPrecos } from './BlocoImpostosPrecos';
import type { Grade } from './grade';
import type { PrecoSimples } from './precos';
import type { MetodoCustoDoFormulario } from './metodoCusto';
import type { EstadoPolitica } from './politicaPreco';
import type { Folheto } from './envioMidia';
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
  preco?: { valor: PrecoSimples; mudar: (p: PrecoSimples) => void; aliquota: number; margemMinima?: number | null };
  /** Editar: a natureza fiscal decidida pelo contador (UC-PEDIT-14). */
  naturezaFiscal?: NaturezaFiscalProduto | null;
  /** Método de custo (US-COM-024), mostrado no card "Impostos e preços". */
  metodoCusto?: { dados: MetodoCustoDoFormulario; valor: string; mudar: (v: string) => void };
  /** Locais de armazenagem (CU-PROD-20): só com o estoque controlado; sem isto o bloco não aparece. */
  armazenagem?: { valor: EstadoArmazenagem; mudar: (e: EstadoArmazenagem) => void; endereco: EnderecoNoEstoque; saldos?: SaldoPorLocal };
  /** Política de preço (CU-PROD-21), no card "Impostos e preços". */
  politica?: { valor: EstadoPolitica; mudar: (e: EstadoPolitica) => void };
  /** UC-PEDIT-29: Editar — a imagem e o folheto gravados, trocados na hora. */
  midia?: { produtoId: number; imagem: string | null; folheto: Folheto | null };
  /** UC-PCAD-32: Novo, produto variável — a grade, no card "Impostos e preços". */
  grade?: { valor: Grade; mudar: (g: Grade) => void; aliquota: number; margemPadrao: number; erro?: string };
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
  metodoCusto,
  armazenagem,
  politica,
  midia,
  grade,
}: Props) {
  return (
    <form
      id={id}
      onSubmit={onSubmit}
      // Forma do protótipo (produto-blade.css .pb-fld), só dentro deste formulário: rótulo 10,5 px · 700 ·
      // maiúsculas, caixa com canto 6 px e recuo 9 px. O `!` vence as regras .cw-label/.cw-input do
      // DS, que ficam fora de @layer. Rótulo ao lado de caixa de marcar e de opção (.font-normal) fica
      // em letras normais, como no protótipo. Sem .css novo (MANUAL-CSS-JS: arquivo novo exige ADR).
      className="space-y-6 [&_.cw-label:not(.font-normal)]:!text-[10.5px] [&_.cw-label:not(.font-normal)]:!font-[700] [&_.cw-label:not(.font-normal)]:!uppercase [&_.cw-label:not(.font-normal)]:!tracking-[0.06em] [&_.cw-input]:!rounded-[6px] [&_.cw-input]:!px-[9px]"
    >
      <BlocoDados modo={modo} data={data} definir={definir} errors={errors} opcoes={opcoes} midia={midia} />
      <BlocoConfiguracoes
        modo={modo}
        data={data}
        definir={definir}
        errors={errors}
        mostrarValidade={mostrarValidade}
        campos={opcoes.camposDaEmpresa}
        armazenagem={
          armazenagem && opcoes.businessLocations ? (
            <BlocoArmazenagem
              valor={armazenagem.valor}
              mudar={armazenagem.mudar}
              locais={opcoes.businessLocations}
              endereco={armazenagem.endereco}
              saldos={armazenagem.saldos}
            />
          ) : undefined
        }
      />
      <BlocoFiscal data={data} definir={definir} errors={errors} naturezaFiscal={naturezaFiscal} />
      <BlocoImpostosPrecos
        modo={modo}
        data={data}
        definir={definir}
        opcoes={opcoes}
        tipoAtual={tipoAtual}
        preco={preco}
        grade={grade}
        metodoCusto={metodoCusto}
        politica={
          politica
            ? {
                ...politica,
                erros: { politica: errors.politica_preco, margem: errors.margem_alvo_pct },
              }
            : undefined
        }
      />
    </form>
  );
}
