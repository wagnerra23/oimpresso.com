// O formulário de produto das telas Novo (Create.tsx) e Editar (Edit.tsx): as quatro seções na
// ordem da tela. O cabeçalho, o useForm e o envio continuam em cada tela.

import type { FormEvent } from 'react';
import { BlocoIdentificacao } from './BlocoIdentificacao';
import { BlocoPrecoImposto } from './BlocoPrecoImposto';
import { BlocoEstoque } from './BlocoEstoque';
import { BlocoMaisOpcoes } from './BlocoMaisOpcoes';
import { BlocoLocais } from './BlocoLocais';
import type { PrecoSimples } from './precos';
import type {
  CamposProduto,
  DefinirCampo,
  ErrosProduto,
  ModoFormulario,
  OpcoesProduto,
} from './tipos';

interface Props {
  /** O id do <form>, que o botão Salvar do cabeçalho referencia. */
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
  /** Chave do localStorage que lembra se "Mais opções" ficou aberta. */
  chaveMaisOpcoes: string;
  /** Novo produto simples: o preço, mostrado no card "Preço & Imposto". */
  preco?: { valor: PrecoSimples; mudar: (p: PrecoSimples) => void; aliquota: number };
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
  chaveMaisOpcoes,
  preco,
}: Props) {
  return (
    <form id={id} onSubmit={onSubmit} className="space-y-6">
      <BlocoIdentificacao
        modo={modo}
        data={data}
        definir={definir}
        errors={errors}
        opcoes={opcoes}
        tipoAtual={tipoAtual}
      />
      {modo === 'novo' && opcoes.businessLocations && (
        <BlocoLocais data={data} definir={definir} locais={opcoes.businessLocations} />
      )}
      <BlocoPrecoImposto modo={modo} data={data} definir={definir} errors={errors} opcoes={opcoes} preco={preco} />
      <BlocoEstoque modo={modo} data={data} definir={definir} opcoes={opcoes} />
      <BlocoMaisOpcoes
        data={data}
        definir={definir}
        opcoes={opcoes}
        chaveAberto={chaveMaisOpcoes}
        mostrarValidade={mostrarValidade}
      />
    </form>
  );
}
