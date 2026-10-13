// Estoque inicial em janela — `ModalEstoqueInicial` do protótipo (produto-blade.jsx), com tudo o que
// a tela Blade (`opening_stock/form-part.blade.php`) tem: linha por variação, várias linhas por
// variação ("+"), validade, lote, data, observação e quantidade na segunda unidade.
//
// Os dados, o pedido e as chamadas ao servidor moram em estoque-inicial-dominio.ts. A tela NÃO grava
// nem calcula estoque: o subtotal é só exibição, e quem grava é o `save()` da tela Blade, chamado
// por OpeningStockController::salvarDaJanela.

import { useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { brl } from '../../Unificado/_components/catalogo';
import {
  buscarEstoqueInicial,
  comLinhaAlterada,
  comLinhaNova,
  comLinhasEmBranco,
  erroDoEstoqueInicial,
  pedidoDoEstoqueInicial,
  salvarEstoqueInicial,
  subtotalDaLinha,
  totalDoLocal,
  type DadosEstoqueInicial,
  type LinhaEstoqueInicial,
  type PedidoEstoqueInicial,
  type RespostaEstoqueInicial,
} from './estoque-inicial-dominio';

interface Props {
  /** null = fechada. */
  produto: { id: number; nome: string } | null;
  aoFechar: () => void;
  /** Chamado depois de lançar, com a mensagem do servidor. */
  aoSalvar: (msg: string) => void;
  /** Só para teste: trocam as chamadas ao servidor. */
  buscar?: (produtoId: number) => Promise<DadosEstoqueInicial | { erro: string }>;
  enviar?: (pedido: PedidoEstoqueInicial) => Promise<RespostaEstoqueInicial>;
}

const TH = 'border-b border-border px-2 py-1.5 text-left text-[10.5px] font-bold uppercase tracking-[0.06em] text-muted-foreground whitespace-nowrap';
const TD = 'border-b border-border px-2 py-1.5 align-middle';
const CAMPO = 'h-8 text-[12.5px]';

export default function EstoqueInicialProduto({ produto, aoFechar, aoSalvar, buscar = buscarEstoqueInicial, enviar = salvarEstoqueInicial }: Props) {
  const [dados, setDados] = useState<DadosEstoqueInicial | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);
  const produtoId = produto?.id ?? null;

  // Cada abertura busca de novo: o pedido tem de levar as linhas que existem AGORA.
  useEffect(() => {
    if (produtoId === null) return;
    let vivo = true;
    setDados(null);
    setErro(null);
    void buscar(produtoId).then((r) => {
      if (!vivo) return;
      if ('erro' in r) setErro(r.erro);
      else setDados(comLinhasEmBranco(r));
    });
    return () => {
      vivo = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [produtoId]);

  const salvar = async () => {
    if (!dados) return;
    const falta = erroDoEstoqueInicial(dados);
    setErro(falta);
    if (falta) return;
    setSalvando(true);
    const r = await enviar(pedidoDoEstoqueInicial(dados));
    setSalvando(false);
    if (r.ok) {
      aoSalvar(r.msg);
      aoFechar();
      return;
    }
    setErro(r.msg);
  };

  const variavel = (dados?.locais ?? []).some((l) => l.variacoes.some((v) => v.nome !== ''));
  const segunda = dados?.produto.segunda_unidade ?? null;
  const colunas = 6 + (variavel ? 1 : 0) + (segunda ? 1 : 0) + (dados?.usa_validade ? 1 : 0) + (dados?.usa_lote ? 1 : 0) + 1;

  return (
    <Dialog open={produto !== null} onOpenChange={(o) => !o && !salvando && aoFechar()}>
      <DialogContent className="sm:max-w-[1180px]">
        <DialogHeader>
          <DialogTitle>Estoque inicial · {produto?.nome ?? ''}</DialogTitle>
          <DialogDescription>
            Uma linha por local do negócio: quantidade, custo unitário sem imposto, validade e lote. O saldo do produto muda ao salvar.
          </DialogDescription>
        </DialogHeader>

        {dados === null && erro === null && (
          <div className="space-y-2" aria-busy="true">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-8 w-full" />
            ))}
          </div>
        )}

        {dados !== null && dados.locais.length === 0 && (
          <p className="text-sm text-muted-foreground">Este produto não está disponível em nenhum local que você possa acessar.</p>
        )}

        {dados !== null && dados.locais.length > 0 && (
          <div className="max-h-[60vh] overflow-auto">
            <table className="w-full border-collapse text-[12.5px]">
              <caption className="sr-only">Estoque inicial por local</caption>
              <thead>
                <tr>
                  <th scope="col" className={TH}>Local</th>
                  {variavel && <th scope="col" className={TH}>Variação</th>}
                  <th scope="col" className={`${TH} text-right`}>Quantidade</th>
                  {segunda && <th scope="col" className={`${TH} text-right`}>Qtd em {segunda}</th>}
                  <th scope="col" className={`${TH} text-right`}>Custo un. sem imposto</th>
                  {dados.usa_validade && <th scope="col" className={TH}>Validade</th>}
                  {dados.usa_lote && <th scope="col" className={TH}>Lote</th>}
                  <th scope="col" className={`${TH} text-right`}>Subtotal sem imposto</th>
                  <th scope="col" className={TH}>Data</th>
                  <th scope="col" className={TH}>Observação</th>
                  <th scope="col" className={TH}>
                    <span className="sr-only">Mais uma linha</span>
                  </th>
                </tr>
              </thead>
              {dados.locais.map((local) => (
                <tbody key={local.id} data-local={local.id}>
                  {local.variacoes.flatMap((v) =>
                    v.linhas.map((x, i) => {
                      const mudar = (patch: Partial<LinhaEstoqueInicial>) => setDados((d) => (d ? comLinhaAlterada(d, local.id, v.id, i, patch) : d));
                      const rotulo = `${local.nome}${v.nome ? ` · ${v.nome}` : ''}${v.linhas.length > 1 ? ` · linha ${i + 1}` : ''}`;
                      return (
                        <tr key={`${v.id}-${x.purchase_line_id ?? `nova-${i}`}`}>
                          <td className={TD}>{local.nome}</td>
                          {variavel && <td className={TD}>{v.nome}</td>}
                          <td className={`${TD} w-[150px]`}>
                            <NumericInputPtBR
                              aria-label={`Quantidade — ${rotulo}`}
                              value={x.quantidade}
                              onChange={(n) => mudar({ quantidade: n })}
                              precision={dados.casas.quantidade}
                              className={`${CAMPO} text-right`}
                            />
                          </td>
                          {segunda && (
                            <td className={`${TD} w-[130px]`}>
                              <NumericInputPtBR
                                aria-label={`Quantidade em ${segunda} — ${rotulo}`}
                                value={x.segunda_unidade_qtd}
                                onChange={(n) => mudar({ segunda_unidade_qtd: n })}
                                precision={dados.casas.quantidade}
                                className={`${CAMPO} text-right`}
                              />
                            </td>
                          )}
                          <td className={`${TD} w-[150px]`}>
                            <NumericInputPtBR
                              aria-label={`Custo unitário sem imposto — ${rotulo}`}
                              value={x.custo}
                              onChange={(n) => mudar({ custo: n })}
                              precision={dados.casas.preco}
                              className={`${CAMPO} text-right`}
                            />
                          </td>
                          {dados.usa_validade && (
                            <td className={TD}>
                              <Input type="date" aria-label={`Validade — ${rotulo}`} value={x.validade ?? ''} onChange={(e) => mudar({ validade: e.target.value || null })} className={CAMPO} />
                            </td>
                          )}
                          {dados.usa_lote && (
                            <td className={`${TD} w-[120px]`}>
                              <Input aria-label={`Lote — ${rotulo}`} value={x.lote ?? ''} onChange={(e) => mudar({ lote: e.target.value || null })} placeholder="Lote" className={CAMPO} />
                            </td>
                          )}
                          <td className={`${TD} text-right tabular-nums whitespace-nowrap`}>{brl(subtotalDaLinha(x))}</td>
                          <td className={TD}>
                            <Input type="datetime-local" aria-label={`Data — ${rotulo}`} value={x.data ?? ''} onChange={(e) => mudar({ data: e.target.value || null })} className={CAMPO} />
                          </td>
                          <td className={TD}>
                            <Input aria-label={`Observação — ${rotulo}`} value={x.observacao ?? ''} onChange={(e) => mudar({ observacao: e.target.value || null })} className={CAMPO} />
                          </td>
                          <td className={TD}>
                            {i === 0 && (
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-7"
                                aria-label={`Mais uma linha — ${local.nome}${v.nome ? ` · ${v.nome}` : ''}`}
                                onClick={() => setDados((d) => (d ? comLinhaNova(d, local.id, v.id) : d))}
                              >
                                <Plus size={13} aria-hidden />
                              </Button>
                            )}
                          </td>
                        </tr>
                      );
                    }),
                  )}
                  <tr>
                    <td className={`${TD} text-right text-muted-foreground`} colSpan={colunas - 4}>
                      Total sem imposto — {local.nome}
                    </td>
                    <td className={`${TD} text-right font-semibold tabular-nums whitespace-nowrap`}>{brl(totalDoLocal(local))}</td>
                    <td className={TD} colSpan={3} />
                  </tr>
                </tbody>
              ))}
            </table>
          </div>
        )}

        {erro && (
          <p role="alert" className="text-sm text-destructive">
            {erro}
          </p>
        )}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={aoFechar} disabled={salvando}>
            Cancelar
          </Button>
          <Button type="button" onClick={() => void salvar()} disabled={salvando || dados === null || dados.locais.length === 0}>
            {salvando ? 'Salvando…' : 'Salvar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
