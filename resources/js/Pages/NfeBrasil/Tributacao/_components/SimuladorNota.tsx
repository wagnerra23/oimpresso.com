// Simulador read-only da Tributação (playbook Fiscal thread 08 · D-SIM · UC-NFTR-08/09).
// Zero cálculo aqui: o backend chama a mesma montagem de item da emissão
// (`NfeService::montarItensNfe`) e devolve o resultado pronto. Esta tela só pede e mostra.

import { useState, type FormEvent } from 'react';
import { Calculator } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Grid, Stack } from '@/Components/layout';
import ProductSearchAutocomplete, {
  type ProductSearchResult,
} from '@/Pages/Sells/_components/ProductSearchAutocomplete';

const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

const NIVEL: Record<number, string> = {
  1: 'N1 · exceção do produto',
  2: 'N2 · regra do NCM para a UF de destino',
  3: 'N3 · regra do NCM para todas as UFs',
  4: 'N4 · padrão da empresa',
};

interface Tributo {
  tributo: string;
  aliquota: number;
  valor: number;
  codigo: string | null;
}

interface Resultado {
  produto: { id: number; nome: string };
  ncm: string;
  ncm_padrao_usado: boolean;
  cfop: string;
  nivel: number;
  uf_origem: string;
  uf_destino: string;
  base: number;
  tributos: Tributo[];
  total_destacado: number;
}

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const pct = (v: number) => `${(v * 100).toLocaleString('pt-BR', { maximumFractionDigits: 4 })}%`;

export default function SimuladorNota({ localPadrao }: { localPadrao: number | null }) {
  const [produto, setProduto] = useState<ProductSearchResult | null>(null);
  const [quantidade, setQuantidade] = useState('1');
  const [valorUnitario, setValorUnitario] = useState('');
  const [ufDestino, setUfDestino] = useState('SP');
  const [carregando, setCarregando] = useState(false);
  const [resultado, setResultado] = useState<Resultado | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  const simular = async (e: FormEvent) => {
    e.preventDefault();
    if (!produto) {
      setErro('Escolha um produto.');
      return;
    }
    setCarregando(true);
    setErro(null);
    setResultado(null);
    const params = new URLSearchParams({
      product_id: String(produto.product_id),
      quantidade: quantidade.replace(',', '.'),
      valor_unitario: valorUnitario.replace(',', '.'),
      uf_destino: ufDestino,
    });
    try {
      const res = await fetch(`/nfe-brasil/tributacao/simular?${params}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const corpo = await res.json().catch(() => ({}));
      if (res.ok) setResultado(corpo as Resultado);
      else if (corpo.bloqueio) setErro(corpo.bloqueio);
      else if (corpo.errors) setErro(Object.values(corpo.errors as Record<string, string[]>)[0]?.[0] ?? 'Verifique os campos.');
      else setErro(res.status === 404 ? 'Produto não encontrado.' : 'Não foi possível simular agora.');
    } catch {
      setErro('Não foi possível simular agora.');
    } finally {
      setCarregando(false);
    }
  };

  return (
    <Card data-contract="simulador">
      <CardHeader>
        <CardTitle className="text-base">Simular nota</CardTitle>
        <p className="text-xs text-muted-foreground">
          Escolha um produto e o destino para ver como a regra foi achada e os impostos de um item.
        </p>
      </CardHeader>
      <CardContent>
        <Stack gap={4}>
          <form onSubmit={simular}>
            <Stack gap={4}>
              <Stack gap={1}>
                <Label>Produto</Label>
                <ProductSearchAutocomplete
                  locationId={localPadrao}
                  onSelect={(p) => {
                    setProduto(p);
                    if (p.selling_price != null) setValorUnitario(String(p.selling_price));
                  }}
                  placeholder="Buscar produto pelo nome ou SKU"
                />
                {produto && <p className="text-xs text-muted-foreground">Selecionado: {produto.name}</p>}
              </Stack>
              <Grid cols={3} gap={4}>
                <Stack gap={1}>
                  <Label htmlFor="sim_quantidade">Quantidade</Label>
                  <Input id="sim_quantidade" inputMode="decimal" value={quantidade}
                    onChange={(e) => setQuantidade(e.target.value)} className="font-mono" />
                </Stack>
                <Stack gap={1}>
                  <Label htmlFor="sim_valor">Valor unitário (R$)</Label>
                  <Input id="sim_valor" inputMode="decimal" value={valorUnitario}
                    onChange={(e) => setValorUnitario(e.target.value)} className="font-mono" />
                </Stack>
                <Stack gap={1}>
                  <Label htmlFor="sim_uf">UF destino</Label>
                  <Select value={ufDestino} onValueChange={setUfDestino}>
                    <SelectTrigger id="sim_uf"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {UFS.map((uf) => <SelectItem key={uf} value={uf}>{uf}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Stack>
              </Grid>
              <div>
                <Button type="submit" disabled={carregando}>
                  <Calculator className="h-4 w-4 mr-1.5" />
                  {carregando ? 'Simulando…' : 'Simular'}
                </Button>
              </div>
            </Stack>
          </form>

          {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}

          {resultado && (
            <Stack gap={3}>
              <p className="text-sm">
                <b>{NIVEL[resultado.nivel] ?? `N${resultado.nivel}`}</b>
                {' · '}CFOP <span className="font-mono">{resultado.cfop}</span>
                {' · '}NCM <span className="font-mono">{resultado.ncm}</span>
                {' · '}{resultado.uf_origem} → {resultado.uf_destino}
              </p>
              {resultado.ncm_padrao_usado && (
                <p className="text-xs text-muted-foreground">
                  O produto não tem NCM válido no cadastro: a nota usaria o NCM padrão da empresa.
                </p>
              )}
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-muted-foreground">
                    <th className="py-1">Tributo</th>
                    <th className="py-1 text-right">Base</th>
                    <th className="py-1 text-right">Alíquota</th>
                    <th className="py-1 text-right">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {resultado.tributos.map((t) => (
                    <tr key={t.tributo} className="border-t">
                      <td className="py-1"><b>{t.tributo}</b>{t.codigo ? <span className="text-muted-foreground font-mono"> {t.codigo}</span> : null}</td>
                      <td className="py-1 text-right font-mono">{brl(resultado.base)}</td>
                      <td className="py-1 text-right font-mono">{pct(t.aliquota)}</td>
                      <td className="py-1 text-right font-mono">{brl(t.valor)}</td>
                    </tr>
                  ))}
                  <tr className="border-t font-semibold">
                    <td className="py-1">Total destacado</td>
                    <td />
                    <td />
                    <td className="py-1 text-right font-mono">{brl(resultado.total_destacado)}</td>
                  </tr>
                </tbody>
              </table>
              <p className="text-xs text-muted-foreground">
                Prévia: usa o mesmo motor da emissão, com o cadastro de agora. Se o cadastro do produto ou do
                cliente mudar, a nota muda junto. Não é garantia do valor que a SEFAZ vai validar.
              </p>
            </Stack>
          )}
        </Stack>
      </CardContent>
    </Card>
  );
}
