// "Locais de armazenagem" (CU-PROD-20), dentro da seção Configurações, na forma do protótipo
// (produto-blade-forms.jsx): uma linha por local de estoque, com o padrão, o local e o endereço
// dentro dele. Só aparece com o estoque controlado.
//
// O endereço mostra só as partes que a empresa usa (prateleira, fileira, posição). O cadastro não
// grava estoque (#10544): no Novo ele entra por "Salvar e adicionar estoque inicial"; no Editar a
// coluna "Em estoque" é só leitura, e local gravado só sai sem saldo ([M] 2026-10-22).
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Inline } from '@/Components/layout';
import { X } from 'lucide-react';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { podeTirar, saldoNoLocal, semLinha } from './armazenagem';
import type { EnderecoNoEstoque, EstadoArmazenagem, LinhaArmazenagem, SaldoPorLocal } from './armazenagem';
import { opcoesDe } from './tipos';
import type { OptionMap } from './tipos';

interface Props {
  valor: EstadoArmazenagem;
  mudar: (e: EstadoArmazenagem) => void;
  locais: OptionMap;
  endereco: EnderecoNoEstoque;
  /** Editar: o saldo por local. Com ele aparece a coluna "Em estoque" e o local sem saldo pode sair. */
  saldos?: SaldoPorLocal;
}

const PARTES: Array<{ chave: keyof EnderecoNoEstoque; campo: 'prateleira' | 'fileira' | 'posicao'; rotulo: string }> = [
  { chave: 'prateleira', campo: 'prateleira', rotulo: 'Prateleira' },
  { chave: 'fileira', campo: 'fileira', rotulo: 'Fileira' },
  { chave: 'posicao', campo: 'posicao', rotulo: 'Posição' },
];

export function BlocoArmazenagem({ valor, mudar, locais, endereco, saldos }: Props) {
  const todos = opcoesDe(locais).map(([id, nome]) => ({ id: Number(id), nome }));
  const nomeDe = (id: number) => todos.find((l) => l.id === id)?.nome ?? `Local ${id}`;
  const usados = valor.linhas.map((l) => l.local);
  const livres = todos.filter((l) => !usados.includes(l.id));
  const partes = PARTES.filter((p) => endereco[p.chave]);

  const mudarLinha = (i: number, linha: Partial<LinhaArmazenagem>) => {
    const antes = valor.linhas[i];
    const linhas = valor.linhas.map((l, j) => (j === i ? { ...l, ...linha } : l));
    // Trocar o local da linha que é o padrão leva o padrão junto.
    const padrao = linha.local !== undefined && antes && valor.padrao === antes.local ? linha.local : valor.padrao;
    mudar({ ...valor, linhas, padrao });
  };
  const adicionar = () => {
    const proximo = livres[0];
    if (!proximo) return;
    const linhas = [...valor.linhas, { local: proximo.id, prateleira: '', fileira: '', posicao: '', salvo: false }];
    mudar({ ...valor, linhas, padrao: valor.padrao ?? proximo.id });
  };

  return (
    <div data-testid="bloco-armazenagem">
      <h4 className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Locais de armazenagem</h4>
      <p className="text-[12px] text-muted-foreground mt-1 mb-2">
        Em qual estoque o produto fica e onde, dentro dele. O padrão é o local sugerido na compra, na
        transferência e na separação; a venda baixa do local onde ela é feita.
      </p>
      <RadioGroup
        value={valor.padrao != null ? String(valor.padrao) : ''}
        onValueChange={(v) => mudar({ ...valor, padrao: Number(v) })}
        className="block"
        aria-label="Local padrão"
      >
        <table className="w-full text-[13px]">
          <thead>
            <tr className="text-left text-[12px] text-muted-foreground">
              <th className="w-16 py-1 font-normal">Padrão</th>
              <th className="py-1 font-normal">Local de estoque</th>
              {partes.map((p) => (
                <th key={p.chave} className="py-1 font-normal">
                  {p.rotulo}
                </th>
              ))}
              {saldos && <th className="py-1 pr-2 text-right font-normal">Em estoque</th>}
              <th className="w-10" />
            </tr>
          </thead>
          <tbody>
            {valor.linhas.map((l, i) => (
              <tr key={`${l.local}-${i}`}>
                <td className="py-1">
                  <RadioGroupItem value={String(l.local)} aria-label={`Local padrão: ${nomeDe(l.local)}`} />
                </td>
                <td className="py-1 pr-2">
                  {l.salvo ? (
                    <span>{nomeDe(l.local)}</span>
                  ) : (
                    <Select value={String(l.local)} onValueChange={(v) => mudarLinha(i, { local: Number(v) })}>
                      <SelectTrigger aria-label={`Local de estoque, linha ${i + 1}`}>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {[{ id: l.local, nome: nomeDe(l.local) }, ...livres].map((o) => (
                          <SelectItem key={o.id} value={String(o.id)}>
                            {o.nome}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </td>
                {partes.map((p) => (
                  <td key={p.chave} className="py-1 pr-2">
                    <Input
                      value={l[p.campo]}
                      onChange={(e) => mudarLinha(i, { [p.campo]: e.target.value })}
                      placeholder={p.rotulo}
                      aria-label={`${p.rotulo}, ${nomeDe(l.local)}`}
                    />
                  </td>
                ))}
                {saldos && (
                  <td className="py-1 pr-2 text-right tabular-nums" aria-label={`Em estoque, ${nomeDe(l.local)}`}>
                    {formatDecimalPtBR(saldoNoLocal(saldos, l.local), 2)}
                  </td>
                )}
                <td className="py-1">
                  {(!l.salvo || saldos) && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label={`Remover local, linha ${i + 1}`}
                      title={podeTirar(l, saldos) ? undefined : 'Local com saldo não pode ser tirado. Transfira ou ajuste o estoque antes.'}
                      disabled={!podeTirar(l, saldos)}
                      onClick={() => mudar(semLinha(valor, i, saldos))}
                    >
                      <X className="w-4 h-4" />
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </RadioGroup>
      <Inline gap={2} className="mt-2">
        <Button type="button" variant="outline" size="sm" onClick={adicionar} disabled={livres.length === 0}>
          + Adicionar local de estoque
        </Button>
      </Inline>
    </div>
  );
}
