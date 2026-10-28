// Menu "Ações" de cada produto — o `Kebab` do protótipo (produto-blade.jsx `acoesDe`) no
// componente do registro (`ui/dropdown-menu`). União das ações do protótipo com as da lista
// Blade (`ProductController@index`, coluna `action`), com as MESMAS permissões do Blade.
//
// Sem "Comprar este produto": a consulta não é a tela de compras (decisão [F] 2026-10-26, UC-PIDX-11).
// Quem compra parte do módulo Compras.
//
// Fora por enquanto: "Transferir entre locais" e "Usar em uma OS" (a tela de destino ainda não
// recebe o produto) e "Dados fiscais" (já é uma aba do painel de detalhe).

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
  | 'fiscal'
  | 'anexo'
  | 'ativar'
  | 'desativar'
  | 'excluir';

interface Props {
  linha: LinhaProduto;
  permissoes: PermissoesLista;
  gruposDePreco: number;
  /** A empresa emite nota (módulo Fiscal no pacote): sem isso não há aba Fiscal para abrir. */
  emiteNota: boolean;
  aoEscolher: (acao: AcaoProduto, linha: LinhaProduto) => void;
}

export default function AcoesProduto({ linha, permissoes, gruposDePreco, emiteNota, aoEscolher }: Props) {
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
    // "Ver" abre o painel de detalhe do protótipo (`DetalheDrawer`) — o conteúdo da janela "Ver" da Blade.
    pode('view') && item('ver', 'Ver'),
    pode('update') && item('editar', 'Editar'),
    // Como na lista Blade: só para produto com estoque gerenciado e com `product.opening_stock`. Abre a
    // página do `OpeningStockController@add` (a mesma que o cadastro abre em "salvar e estoque inicial").
    linha.controla_estoque && pode('opening_stock') && item('estoque_inicial', 'Adicionar ou editar estoque inicial'),
    pode('view') && item('historico', 'Histórico de estoque do produto'),
  ].filter(Boolean);
  const grupo2 = [
    pode('create') && item('duplicar', 'Duplicar produto'),
    item('rotulos', 'Gerar código de barras'),
    pode('create') && gruposDePreco > 0 && item('precos', 'Adicionar ou editar preços por grupo'),
    linha.anexo && item('anexo', 'Baixar anexo do produto'),
  ].filter(Boolean);
  const grupoFiscal = [
    // Protótipo: "Dados fiscais (NCM/CFOP)" abre o painel de detalhe. Aqui ele abre direto na aba
    // Fiscal — no protótipo o painel não tem como nascer noutra aba e o item cai em "Dados", igual ao "Ver".
    // Só em empresa que emite nota, como a aba (decisão [F] 2026-10-26).
    emiteNota && pode('view') && item('fiscal', 'Dados fiscais (NCM/CFOP)'),
  ].filter(Boolean);
  const grupo3 = [
    // Ativar e desativar exigem `product.update` no servidor (`activate`/`massDeactivate`).
    pode('update') && (linha.inativo ? item('ativar', 'Ativar') : item('desativar', 'Desativar')),
    pode('delete') && item('excluir', 'Excluir', true),
  ].filter(Boolean);
  const grupos = [grupo1, grupo2, grupoFiscal, grupo3].filter((g) => g.length > 0);

  // A linha da tabela é clicável (abre o produto) e os eventos do React atravessam o portal do
  // menu: gatilho e conteúdo param a propagação, senão escolher um item também abriria a linha.
  const parar = (e: { stopPropagation: () => void }) => e.stopPropagation();
  return (
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="outline"
            size="sm"
            // 12px como o `Kebab` do protótipo (medido com design-diff em 2026-10-11; o `sm` dava 14px).
            className="text-[12px]"
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
