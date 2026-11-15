// Naturezas de operação — lista (RUNBOOK-tributacao §Naturezas · onda 3b). Só aparece com a flag
// useV2NfeNaturezas ligada para a empresa; o Index decide pelo prop `naturezasLiberado`.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { FilePlus2, Pencil, Eye } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Segmented } from '@/Components/ui/segmented';
import { Skeleton } from '@/Components/ui/skeleton';
import NaturezaEditor from './NaturezaEditor';
import { FINS, completar, listar, rotulo, vazia, type LinhaNatureza, type Natureza, type Regime } from './natureza';

type Filtro = 'todas' | 'saidas' | 'devolucoes' | 'entradas' | 'inativas';

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todas', label: 'Todas' },
  { value: 'saidas', label: 'Saídas' },
  { value: 'devolucoes', label: 'Devoluções' },
  { value: 'entradas', label: 'Entradas' },
  { value: 'inativas', label: 'Inativas' },
];

function passa(l: LinhaNatureza, f: Filtro, busca: string): boolean {
  const n = l.natureza;
  if (f === 'inativas' ? n.ativo : !n.ativo) return false;
  if (f === 'saidas' && (n.tipo !== 'S' || n.fin === '4')) return false;
  if (f === 'devolucoes' && n.fin !== '4') return false;
  if (f === 'entradas' && n.tipo !== 'E') return false;
  const b = busca.trim().toLowerCase();
  if (!b) return true;
  return n.nome.toLowerCase().includes(b) || [n.cfop.in, n.cfop.out, n.cfop.ext].some((c) => (c ?? '').includes(b));
}

export default function NaturezasSecao() {
  const [linhas, setLinhas] = useState<LinhaNatureza[] | null>(null);
  const [regime, setRegime] = useState<Regime>('LP');
  const [erro, setErro] = useState<string | null>(null);
  const [filtro, setFiltro] = useState<Filtro>('todas');
  const [busca, setBusca] = useState('');
  const [aberta, setAberta] = useState<{ natureza: Natureza; editavel: boolean } | null>(null);

  const carregar = useCallback(() => {
    setErro(null);
    listar()
      .then((r) => { setLinhas(r.naturezas); setRegime(r.regime); })
      .catch(() => setErro('Não foi possível carregar as naturezas agora.'));
  }, []);

  useEffect(carregar, [carregar]);

  const visiveis = useMemo(() => (linhas ?? []).filter((l) => passa(l, filtro, busca)), [linhas, filtro, busca]);
  const sn = regime === 'SN';

  if (aberta) {
    return (
      <NaturezaEditor
        inicial={aberta.natureza}
        editavel={aberta.editavel}
        regime={regime}
        onFechar={(salvou) => { setAberta(null); if (salvou) carregar(); }}
      />
    );
  }

  return (
    <Card id="naturezas" data-contract="naturezas">
      <CardHeader className="pb-3">
        <CardTitle className="text-base flex items-center justify-between gap-2">
          <span className="flex items-center gap-2">
            Naturezas de operação
            {linhas && <Badge variant="secondary">{linhas.length}</Badge>}
          </span>
          <Button size="sm" variant="outline" onClick={() => setAberta({ natureza: vazia(), editavel: true })}>
            <FilePlus2 className="h-4 w-4 mr-1.5" />
            Nova natureza
          </Button>
        </CardTitle>
        <p className="text-xs text-muted-foreground">
          No lançamento da nota, a natureza escolhida preenche CFOP, {sn ? 'CSOSN' : 'CST'} e a origem das alíquotas de
          cada item. Esta tela guarda códigos, não valores.
        </p>
      </CardHeader>
      <CardContent>
        <div className="flex flex-wrap items-center justify-between gap-3 pb-3">
          <Segmented value={filtro} onValueChange={(v) => v && setFiltro(v as Filtro)} options={FILTROS} />
          <Input
            className="max-w-xs"
            placeholder="Buscar por nome ou CFOP"
            aria-label="Buscar natureza por nome ou CFOP"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
          />
        </div>

        {erro && <p className="text-sm text-destructive py-4">{erro}</p>}
        {!linhas && !erro && <Skeleton className="h-32 w-full" />}
        {linhas && visiveis.length === 0 && (
          <p className="text-sm text-muted-foreground py-6 text-center">Nenhuma natureza neste filtro.</p>
        )}
        {linhas && visiveis.length > 0 && (
          <div className="overflow-x-auto -mx-6">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-left">
                <tr>
                  <th className="px-6 py-2 font-medium">Natureza</th>
                  <th className="px-3 py-2 font-medium">CFOP dentro / fora</th>
                  <th className="px-3 py-2 font-medium">ICMS</th>
                  <th className="px-3 py-2 font-medium">Situação</th>
                  <th className="px-6 py-2 font-medium text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {visiveis.map((l) => {
                  const n = l.natureza;
                  const cst = sn ? n.icms?.csosn : n.icms?.cst;
                  return (
                    <tr key={l.id} className="border-t hover:bg-muted/30">
                      <td className="px-6 py-2">
                        <span className="block font-medium">{n.nome}</span>
                        <span className="block text-xs text-muted-foreground">
                          {n.tipo === 'E' ? 'Entrada' : 'Saída'} · {rotulo(FINS, n.fin).replace(/^\d · /, '')}
                          {n.ref ? ' · com nota referenciada' : ''}
                        </span>
                      </td>
                      <td className="px-3 py-2 font-mono">
                        {n.cfop.in || '—'} / {n.cfop.out || '—'}
                      </td>
                      <td className="px-3 py-2 font-mono">{cst ?? <span className="text-muted-foreground">—</span>}</td>
                      <td className="px-3 py-2">
                        {!n.ativo ? <Badge variant="neutral">Inativa</Badge>
                          : !l.editavel ? <Badge variant="info">Usada no cálculo</Badge>
                          : l.pendencias ? <Badge variant="danger">{l.pendencias} {l.pendencias === 1 ? 'pendência' : 'pendências'}</Badge>
                          : <Badge variant="success">Em uso</Badge>}
                      </td>
                      <td className="px-6 py-2 text-right whitespace-nowrap">
                        <Button
                          size="sm"
                          variant="ghost"
                          aria-label={`${l.editavel ? 'Editar' : 'Ver'} ${n.nome}`}
                          onClick={() => setAberta({ natureza: { ...completar(n), id: l.id }, editavel: l.editavel })}
                        >
                          {l.editavel ? <Pencil className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
