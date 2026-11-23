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
  | 'armazenagem'
  | 'estoque_inicial'
  | 'historico'
  | 'duplicar'
  | 'rotulos'
  | 'precos'
  | 'fiscal'
  | 'anexo'
  | 'ativar'
  | 'desativar'
  | 'excluir'
  | 'restaurar';

interface Props {
  linha: LinhaProduto;
  permissoes: PermissoesLista;
  gruposDePreco: number;
  /** A empresa emite nota (módulo Fiscal no pacote): sem isso não há aba Fiscal para abrir. */
  emiteNota: boolean;
  aoEscolher: (acao: AcaoProduto, linha: LinhaProduto) => void;
  /**
   * Onde o menu abre: o `.cockpit`. No <body> (padrão do Radix) a fonte caía na do sistema e os
   * tokens chegavam vazios — medido em produção em 2026-11-23 (lápide §5 2026-10-19).
   */
  container?: HTMLElement | null;
}

// Item do menu no `.pb-menu button` do protótipo (`produto-blade.css`): 12px, 6px × 8px, cantos
// de 5px, texto `--text` e `--bg-2` ao passar o mouse ou focar.
const ITEM = 'rounded-[5px] text-[12px] focus:bg-[var(--bg-2)] focus:text-[var(--text)]';

export default function AcoesProduto({ linha, permissoes, gruposDePreco, emiteNota, aoEscolher, container = null }: Props) {
  const pode = (p: keyof PermissoesLista) => permissoes[p] === true;
  const item = (acao: AcaoProduto, rotulo: string, perigo = false) => (
    <DropdownMenuItem
      key={acao}
      onSelect={() => aoEscolher(acao, linha)}
      className={perigo ? `${ITEM} text-destructive focus:text-destructive` : ITEM}
    >
      {rotulo}
    </DropdownMenuItem>
  );

  const grupo1 = [
    // "Ver" abre o painel de detalhe do protótipo (`DetalheDrawer`) — o conteúdo da janela "Ver" da Blade.
    pode('view') && item('ver', 'Ver'),
    // UC-PIDX-45 (decisão [F] 2026-11-11): o excluído não se edita nem ganha estoque, código de barras ou
    // preço — quem quer mexer restaura antes. Fica o que só lê (Ver, Histórico, Dados fiscais, anexo) e Duplicar.
    !linha.excluido && pode('update') && item('editar', 'Editar'),
    // UC-PIDX-47 (#10765): quem só pode mexer nos locais de armazenagem abre o Editar enxuto, que só
    // tem os locais. Quem tem `product.update` já chega lá pelo Editar.
    !linha.excluido && linha.controla_estoque && !pode('update') && pode('armazenagem') && item('armazenagem', 'Locais de armazenagem'),
    // Como na lista Blade: só para produto com estoque gerenciado e com `product.opening_stock`. Abre a
    // página do `OpeningStockController@add` (a mesma que o cadastro abre em "salvar e estoque inicial").
    !linha.excluido && linha.controla_estoque && pode('opening_stock') && item('estoque_inicial', 'Adicionar ou editar estoque inicial'),
    pode('view') && item('historico', 'Histórico de estoque do produto'),
  ].filter(Boolean);
  const grupo2 = [
    pode('create') && item('duplicar', 'Duplicar produto'),
    !linha.excluido && item('rotulos', 'Gerar código de barras'),
    !linha.excluido && pode('create') && gruposDePreco > 0 && item('precos', 'Adicionar ou editar preços por grupo'),
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
    // UC-PIDX-45 (AR-PROD-022): no lugar de Ativar/Desativar/Excluir, o excluído oferece "Restaurar", com a
    // mesma permissão do Excluir.
    !linha.excluido && pode('update') && (linha.inativo ? item('ativar', 'Ativar') : item('desativar', 'Desativar')),
    linha.excluido ? pode('delete') && item('restaurar', 'Restaurar') : pode('delete') && item('excluir', 'Excluir', true),
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
            // Do tamanho do `Kebab` do protótipo (`os-btn sm`, medido no render em 2026-11-12): 26px de
            // altura, letra 11,5px e seta de 12px — 72px de largura. Com o `sm` do DS (32px de altura e a
            // seta forçada a 16px pelo `size-4` do botão) ele dava 80px e não cabia nos 76px da coluna
            // "Ação" (96 menos a margem): a célula cortava e mostrava ".." ao lado do botão.
            className="h-[26px] text-[11.5px]"
            aria-label={`Ações do produto ${linha.nome}`}
            onClick={parar}
            onKeyDown={parar}
          >
            Ações <ChevronDown className="size-3" aria-hidden />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          align="start"
          container={container}
          // Moldura do `.pb-menu` do protótipo: fundo `--surface`, borda `--border`, cantos 8px,
          // sombra 0 12px 32px, 4px de respiro e linha 1,45.
          className="min-w-[236px] rounded-[8px] border-[var(--border)] bg-[var(--surface)] p-1 leading-[1.45] text-[var(--text)] shadow-[0_12px_32px_oklch(0_0_0/.16)]"
          onClick={parar}
          onKeyDown={parar}
        >
          {grupos.map((g, i) => (
            <Fragment key={i}>
              {i > 0 && <DropdownMenuSeparator className="mx-0 bg-[var(--border-2)]" />}
              {g}
            </Fragment>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
  );
}
