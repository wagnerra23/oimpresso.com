// Menu "Ações" de cada produto — o `Kebab` do protótipo (produto-blade.jsx `acoesDe`) no
// componente do registro (`ui/dropdown-menu`). União das ações do protótipo com as da lista
// Blade (`ProductController@index`, coluna `action`), com as MESMAS permissões do Blade.
//
// Fora por enquanto, de propósito: "Comprar este produto" e "Transferir entre locais" (as telas
// de destino não recebem o produto — decisão pendente do [F]), "Usar em uma OS" (só com o
// módulo de assistência técnica) e "Dados fiscais" (aba do painel de detalhe, etapa 4).

import { Fragment } from 'react';
import { ChevronDown } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import type { LinhaProduto, PermissoesLista } from './tipos';

export type AcaoProduto =
  | 'ver'
  | 'editar'
  | 'estoque_inicial'
  | 'historico'
  | 'duplicar'
  | 'rotulos'
  | 'precos'
  | 'anexo'
  | 'ativar'
  | 'desativar'
  | 'excluir';

interface Props {
  linha: LinhaProduto;
  permissoes: PermissoesLista;
  gruposDePreco: number;
  aoEscolher: (acao: AcaoProduto, linha: LinhaProduto) => void;
}

export default function AcoesProduto({ linha, permissoes, gruposDePreco, aoEscolher }: Props) {
  const pode = (p: keyof PermissoesLista) => permissoes[p] === true;
  const item = (acao: AcaoProduto, rotulo: string, perigo = false) => (
    <DropdownMenuItem
      key={acao}
      onSelect={() => aoEscolher(acao, linha)}
      className={perigo ? 'text-destructive focus:text-destructive' : undefined}
    >
      {rotulo}
    </DropdownMenuItem>
  );

  const grupo1 = [
    pode('view') && item('ver', 'Ver'),
    pode('update') && item('editar', 'Editar'),
    // "Adicionar ou editar estoque inicial" (Blade: `enable_stock` + `product.opening_stock`) entra com o
    // modal da etapa 4 — o fluxo é o modal do `OpeningStockController`, que não há como abrir daqui ainda.
    pode('view') && item('historico', 'Histórico de estoque do produto'),
  ].filter(Boolean);
  const grupo2 = [
    pode('create') && item('duplicar', 'Duplicar produto'),
    item('rotulos', 'Gerar código de barras'),
    pode('create') && gruposDePreco > 0 && item('precos', 'Adicionar ou editar preços por grupo'),
    linha.anexo && item('anexo', 'Baixar anexo do produto'),
  ].filter(Boolean);
  const grupo3 = [
    // Ativar e desativar exigem `product.update` no servidor (`activate`/`massDeactivate`).
    pode('update') && (linha.inativo ? item('ativar', 'Ativar') : item('desativar', 'Desativar')),
    pode('delete') && item('excluir', 'Excluir', true),
  ].filter(Boolean);
  const grupos = [grupo1, grupo2, grupo3].filter((g) => g.length > 0);

  // A linha da tabela é clicável (abre o produto) e os eventos do React atravessam o portal do
  // menu: gatilho e conteúdo param a propagação, senão escolher um item também abriria a linha.
  const parar = (e: { stopPropagation: () => void }) => e.stopPropagation();
  return (
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="outline"
            size="sm"
            aria-label={`Ações do produto ${linha.nome}`}
            onClick={parar}
            onKeyDown={parar}
          >
            Ações <ChevronDown size={12} aria-hidden />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" className="min-w-[236px]" onClick={parar} onKeyDown={parar}>
          {grupos.map((g, i) => (
            <Fragment key={i}>
              {i > 0 && <DropdownMenuSeparator />}
              {g}
            </Fragment>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
  );
}
