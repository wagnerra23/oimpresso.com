/**
 * ConsultaProduto — "Consultar produto… F3" do passo 2 da Nova venda.
 *
 * Porte de `ConsultaProduto` em `prototipo-ui/cowork/Wagner/venda-v3.jsx`: modal de 880px com
 * busca e uma tabela (SKU · Produto / serviço · Cód. EAN · Cód. fábrica · Categoria · Tipo ·
 * Unid. · Tabela · Estoque). Clicar na linha lança o item: abre o MESMO lançamento da busca
 * do passo 2 (`LancarItem`), que pede medidas e quantidade.
 *
 * Não busca nada sozinho: recebe a busca da Page. Gravando, é o `/products/list`
 * (`ProductUtil::filterProduct`) que a busca do passo 2 já usa, a partir de 2 letras; sem
 * gravar, é o catálogo da cena. Uma busca só, dois jeitos de ver o resultado.
 *
 * O que a busca do servidor NÃO devolve (medido em 2026-11-24, `filterProduct` seleciona id,
 * nome, tipo, variação, estoque, preço, `sub_sku` e unidade): EAN, código de fábrica e
 * categoria. O cadastro não tem coluna própria para EAN nem para código de fábrica (ficam em
 * campos personalizados configurados por empresa), e escolher qual campo é qual seria
 * inventar o dado. Gravando, as três colunas mostram travessão — a mesma regra das colunas
 * do grid sem dado (UC-V368). Dívida declarada no `CreateV3.casos.md` (UC-V412).
 *
 * Fica de fora o "Novo cadastro" do rodapé do protótipo: a nova venda não cadastra produto,
 * e botão que promete e não entrega é pior que botão ausente.
 *
 * TIER 0 — VALOR: a consulta não muda número. Mostra o preço que a busca já trouxe (o mesmo
 * da lista do passo 2) e devolve o produto ao lançamento, que calcula como antes.
 */
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import type { ProdutoCatalogo } from './LancarItem';
import { brl, num } from './numeros';
import { Pill } from './primitivos';
import { useCockpit } from './cockpit-portal';

const COLUNAS = ['SKU', 'Produto / serviço', 'Cód. EAN', 'Cód. fábrica', 'Categoria', 'Tipo', 'Unid.', 'Tabela', 'Estoque'];
const A_DIREITA = new Set(['Tabela', 'Estoque']);

export default function ConsultaProduto({
  aberta,
  onFechar,
  busca,
  onBuscaChange,
  encontrados,
  remoto,
  onEscolher,
}: {
  aberta: boolean;
  onFechar: () => void;
  busca: string;
  onBuscaChange: (v: string) => void;
  encontrados: ProdutoCatalogo[];
  /** Gravando: a busca é no cadastro e só começa com 2 letras. */
  remoto: boolean;
  onEscolher: (p: ProdutoCatalogo) => void;
}) {
  const cockpit = useCockpit();
  const curta = remoto && busca.trim().length < 2;

  return (
    <Dialog open={aberta} onOpenChange={(v) => !v && onFechar()}>
      <DialogContent container={cockpit} className="venda-v3 max-h-[80vh] sm:max-w-[880px]">
        <DialogHeader>
          <DialogTitle>Consulta de produtos e serviços</DialogTitle>
        </DialogHeader>

        <Input
          autoFocus
          value={busca}
          onChange={(e) => onBuscaChange(e.target.value)}
          placeholder="Buscar por nome, SKU, EAN, código de fábrica ou categoria…"
          aria-label="Buscar produto"
        />

        <div className="min-h-0 flex-1 overflow-auto">
          <table className="w-full text-[12.5px]">
            <thead className="sticky top-0 bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
              <tr>
                {COLUNAS.map((c) => (
                  <th key={c} className={'px-3 py-2 font-medium' + (A_DIREITA.has(c) ? ' text-right' : '')}>
                    {c}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {encontrados.map((p) => (
                <tr key={p.sku} className="border-t border-border hover:bg-muted/50">
                  {/* A linha inteira lança o item, mas quem recebe o clique é um <button> de
                      verdade na primeira célula — `<tr onClick>` não é alcançável por teclado. */}
                  <td className="px-3 py-2 font-mono">
                    <button
                      type="button"
                      onClick={() => {
                        onFechar();
                        onEscolher(p);
                      }}
                      className="cursor-pointer text-left underline decoration-dotted underline-offset-2"
                      aria-label={`Lançar ${p.nome}`}
                    >
                      {p.sku}
                    </button>
                  </td>
                  <td className="px-3 py-2 font-medium">{p.nome}</td>
                  <td className="px-3 py-2 font-mono">{p.ean || '—'}</td>
                  <td className="px-3 py-2 font-mono">{p.fabrica || '—'}</td>
                  <td className="px-3 py-2">{p.categoria ? <Pill>{p.categoria}</Pill> : '—'}</td>
                  <td className="px-3 py-2">
                    {p.tipo === 'servico' ? <Pill tom="info">serviço</Pill> : <Pill tom="primary">produto</Pill>}
                  </td>
                  <td className="px-3 py-2 font-mono">{p.un}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums">{brl(p.preco)}</td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums">{p.estoque === null ? '—' : num(p.estoque, 2)}</td>
                </tr>
              ))}
              {encontrados.length === 0 && (
                <tr>
                  <td colSpan={COLUNAS.length} className="px-3 py-6 text-center text-muted-foreground">
                    {curta ? 'Digite pelo menos 2 letras para buscar no cadastro.' : `Nenhum produto encontrado para “${busca}”.`}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <p className="text-[11.5px] leading-[1.5] text-muted-foreground">
          Clique no SKU para lançar o item — a próxima tela pede medidas, quantidade e, se for serviço, o funcionário vinculado.
        </p>

        <DialogFooter className="sm:items-center">
          <span className="mr-auto text-[11.5px] text-muted-foreground">
            {remoto ? `${encontrados.length} encontrado(s) no cadastro` : `${encontrados.length} itens na lista`}
          </span>
          <Button type="button" variant="outline" size="sm" onClick={onFechar}>
            Fechar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
