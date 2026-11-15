// Editor da natureza em 4 passos (protótipo aprovado: natureza → CFOP → tributos → conferência).
// A conferência é do servidor (POST /naturezas/conferir), chamada a cada alteração; o salvar trava
// enquanto houver erro. RUNBOOK-tributacao §Naturezas · onda 3b.
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import { Switch } from '@/Components/ui/switch';
import { Grid, Inline } from '@/Components/layout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  CSOSN, CST_COM_ST, CST_IBS, CST_ICMS, CST_IPI_E, CST_IPI_S, CST_PC_E, CST_PC_S, FINS, MODBC, MODBCST,
  ORIG_IBS, ORIG_ICMS, ORIG_IPI, ORIG_MVA, ORIG_PC, TIPOS, conferir, salvar,
  type Alerta, type Natureza, type Opcao, type Regime,
} from './natureza';

const PASSOS = [
  { value: '0', label: '1 · Natureza' },
  { value: '1', label: '2 · CFOP' },
  { value: '2', label: '3 · Tributos' },
  { value: '3', label: '4 · Conferência' },
];

const ABAS = [
  { value: 'icms', label: 'ICMS' },
  { value: 'ipi', label: 'IPI' },
  { value: 'pc', label: 'PIS e COFINS' },
  { value: 'ibs', label: 'IBS e CBS' },
];

const SECAO_DO_PASSO: Record<string, Alerta['secao'][]> = {
  '0': ['nat'], '1': ['cfop'], '2': ['icms', 'ipi', 'pc', 'ibs'], '3': ['nat', 'cfop', 'icms', 'ipi', 'pc', 'ibs'],
};

function Campo({ id, rotulo, ajuda, children }: { id: string; rotulo: string; ajuda?: string; children: ReactNode }) {
  return (
    <div className="space-y-1">
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {ajuda && <p className="text-xs text-muted-foreground">{ajuda}</p>}
    </div>
  );
}

function Escolha({ id, rotulo, valor, opcoes, onChange, disabled }: {
  id: string; rotulo: string; valor: string; opcoes: readonly Opcao[]; onChange: (v: string) => void; disabled?: boolean;
}) {
  return (
    <Campo id={id} rotulo={rotulo}>
      <Select value={valor} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger id={id}><SelectValue /></SelectTrigger>
        <SelectContent>
          {opcoes.map(([v, r]) => <SelectItem key={v} value={v}>{r}</SelectItem>)}
        </SelectContent>
      </Select>
    </Campo>
  );
}

function Percentual({ id, rotulo, valor, onChange, disabled }: {
  id: string; rotulo: string; valor: number; onChange: (v: number) => void; disabled?: boolean;
}) {
  const [texto, setTexto] = useState(valor ? String(valor).replace('.', ',') : '');
  return (
    <Campo id={id} rotulo={`${rotulo} (%)`}>
      <Input
        id={id}
        inputMode="decimal"
        placeholder="0"
        value={texto}
        disabled={disabled}
        onChange={(e) => {
          setTexto(e.target.value);
          const n = parseFloat(e.target.value.replace(/\./g, '').replace(',', '.'));
          onChange(Number.isFinite(n) ? n : 0);
        }}
      />
    </Campo>
  );
}

function Liga({ id, rotulo, valor, onChange, disabled }: {
  id: string; rotulo: string; valor: boolean; onChange: (v: boolean) => void; disabled?: boolean;
}) {
  return (
    <Inline gap={2}>
      <Switch id={id} checked={valor} onCheckedChange={onChange} disabled={disabled} />
      <Label htmlFor={id}>{rotulo}</Label>
    </Inline>
  );
}

const VARIANTE: Record<Alerta['nivel'], 'danger' | 'warning' | 'info'> = { erro: 'danger', aviso: 'warning', info: 'info' };
const NOME_NIVEL: Record<Alerta['nivel'], string> = { erro: 'Erro', aviso: 'Aviso', info: 'Informação' };

export default function NaturezaEditor({ inicial, editavel, regime, onFechar }: {
  inicial: Natureza; editavel: boolean; regime: Regime; onFechar: (salvou: boolean) => void;
}) {
  const [n, setN] = useState<Natureza>(inicial);
  const [passo, setPasso] = useState('0');
  const [aba, setAba] = useState('icms');
  const [alertas, setAlertas] = useState<Alerta[] | null>(null);
  const [salvando, setSalvando] = useState(false);
  const pedido = useRef(0);
  const sn = regime === 'SN';
  const entrada = n.tipo === 'E';
  const ro = !editavel;

  useEffect(() => {
    if (ro) return;
    const meu = ++pedido.current;
    const t = setTimeout(() => {
      conferir(n).then((a) => { if (meu === pedido.current) setAlertas(a); }).catch(() => {});
    }, 300);
    return () => clearTimeout(t);
  }, [n, ro]);

  const muda = (f: (x: Natureza) => Natureza) => setN((x) => f(structuredClone(x)));
  const erros = (alertas ?? []).filter((a) => a.nivel === 'erro').length;
  const cstAtual = sn ? n.icms.csosn : n.icms.cst;
  const temSt = CST_COM_ST.includes(cstAtual);

  const gravar = async () => {
    setSalvando(true);
    const r: Awaited<ReturnType<typeof salvar>> = await salvar(n)
      .catch(() => ({ ok: false as const, mensagem: 'Não foi possível salvar agora.' }));
    setSalvando(false);
    if (r.ok) { toast.success('Natureza salva.'); onFechar(true); return; }
    if (r.alertas) { setAlertas(r.alertas); setPasso('3'); }
    toast.error(r.mensagem ?? 'A conferência encontrou erro. Corrija antes de salvar.');
  };

  const visiveis = (alertas ?? []).filter((a) => (SECAO_DO_PASSO[passo] ?? []).includes(a.secao));

  return (
    <Card id="naturezas" data-contract="naturezas-editor">
      <CardHeader className="pb-3">
        <CardTitle className="text-base">
          <Inline justify="between" gap={2}>
          <Inline gap={2}>
            <Button size="sm" variant="ghost" aria-label="Voltar para a lista" onClick={() => onFechar(false)}>
              <ArrowLeft className="h-4 w-4" />
            </Button>
            {n.id ? n.nome || 'Natureza sem nome' : 'Nova natureza'}
            {ro && <Badge variant="info">Só leitura</Badge>}
          </Inline>
          {!ro && (
            <Button size="sm" onClick={gravar} disabled={salvando || erros > 0 || alertas === null}>
              <Save className="h-4 w-4 mr-1.5" />
              {erros > 0 ? `${erros} ${erros === 1 ? 'erro' : 'erros'} para salvar` : 'Salvar natureza'}
            </Button>
          )}
          </Inline>
        </CardTitle>
        {ro && (
          <p className="text-xs text-muted-foreground">
            Esta natureza já é usada pelo cálculo da nota. Ela poderá ser editada aqui quando o cálculo passar a ler esta tela.
          </p>
        )}
        <Segmented value={passo} onValueChange={(v) => v && setPasso(v)} options={PASSOS} />
      </CardHeader>
      <CardContent>
        <Grid cols={1} gap={6} className="lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-4">
            {passo === '0' && (
              <Grid cols={1} gap={4} className="sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <Campo id="nat-nome" rotulo="Nome" ajuda="Vai no campo natOp da nota.">
                    <Input id="nat-nome" value={n.nome} disabled={ro} maxLength={80}
                      onChange={(e) => muda((x) => ({ ...x, nome: e.target.value }))} />
                  </Campo>
                </div>
                <Escolha id="nat-tipo" rotulo="Tipo da nota" valor={n.tipo} opcoes={TIPOS} disabled={ro}
                  onChange={(v) => muda((x) => {
                    const e = v === 'E';
                    x.tipo = v as Natureza['tipo'];
                    x.ipi.cst = e ? '49' : '53';
                    x.pis.cst = e ? '98' : '01';
                    x.cofins.cst = e ? '98' : '01';
                    x.pis.cstSN = e ? '98' : '49';
                    x.cofins.cstSN = e ? '98' : '49';
                    return x;
                  })} />
                <Escolha id="nat-fin" rotulo="Finalidade" valor={n.fin} opcoes={FINS} disabled={ro}
                  onChange={(v) => muda((x) => ({ ...x, fin: v as Natureza['fin'], ref: v === '4' ? true : x.ref }))} />
                <Liga id="nat-ref" rotulo="Pede a chave da nota de origem" valor={n.ref} disabled={ro}
                  onChange={(v) => muda((x) => ({ ...x, ref: v }))} />
                <Liga id="nat-ativo" rotulo="Ativa" valor={n.ativo} disabled={ro}
                  onChange={(v) => muda((x) => ({ ...x, ativo: v }))} />
              </Grid>
            )}

            {passo === '1' && (
              <Grid cols={1} gap={4} className="sm:grid-cols-3">
                {([['in', 'Dentro do estado'], ['out', 'Outro estado'], ['ext', 'Exterior (opcional)']] as const).map(([k, r]) => (
                  <Campo key={k} id={`nat-cfop-${k}`} rotulo={r}>
                    <Input id={`nat-cfop-${k}`} className="font-mono" inputMode="numeric" maxLength={4} disabled={ro}
                      placeholder={(entrada ? { in: '1', out: '2', ext: '3' } : { in: '5', out: '6', ext: '7' })[k] + '000'}
                      value={n.cfop[k]}
                      onChange={(e) => muda((x) => { x.cfop[k] = e.target.value.replace(/\D/g, ''); return x; })} />
                  </Campo>
                ))}
              </Grid>
            )}

            {passo === '2' && (
              <div className="space-y-4">
                <Segmented value={aba} onValueChange={(v) => v && setAba(v)} options={ABAS} />

                {aba === 'icms' && (
                  <Grid cols={1} gap={4} className="sm:grid-cols-2">
                    {sn
                      ? <Escolha id="icms-csosn" rotulo="CSOSN" valor={n.icms.csosn} opcoes={CSOSN} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.csosn = v; return x; })} />
                      : <Escolha id="icms-cst" rotulo="CST" valor={n.icms.cst} opcoes={CST_ICMS} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.cst = v; return x; })} />}
                    <Escolha id="icms-modbc" rotulo="Modalidade da base" valor={n.icms.modbc} opcoes={MODBC} disabled={ro}
                      onChange={(v) => muda((x) => { x.icms.modbc = v; return x; })} />
                    <Escolha id="icms-origem" rotulo="Origem da alíquota" valor={n.icms.origem} opcoes={ORIG_ICMS} disabled={ro}
                      onChange={(v) => muda((x) => { x.icms.origem = v as Natureza['icms']['origem']; return x; })} />
                    {n.icms.origem === 'fixa' && <Percentual id="icms-p" rotulo="Alíquota" valor={n.icms.p} disabled={ro}
                      onChange={(v) => muda((x) => { x.icms.p = v; return x; })} />}
                    {!sn && ['20', '70'].includes(n.icms.cst) && <Percentual id="icms-red" rotulo="Redução da base" valor={n.icms.red} disabled={ro}
                      onChange={(v) => muda((x) => { x.icms.red = v; return x; })} />}
                    {!sn && n.icms.cst === '51' && <Percentual id="icms-dif" rotulo="Diferimento" valor={n.icms.pDif} disabled={ro}
                      onChange={(v) => muda((x) => { x.icms.pDif = v; return x; })} />}
                    {sn && ['101', '201'].includes(n.icms.csosn) && <Percentual id="icms-cred" rotulo="Crédito do Simples" valor={n.icms.pCredSN} disabled={ro}
                      onChange={(v) => muda((x) => { x.icms.pCredSN = v; return x; })} />}
                    <Inline wrap gap={4} className="sm:col-span-2">
                      <Liga id="icms-frete" rotulo="Frete e despesas na base" valor={n.icms.bFrete} disabled={ro}
                        onChange={(v) => muda((x) => { x.icms.bFrete = v; return x; })} />
                      <Liga id="icms-ipi" rotulo="IPI na base" valor={n.icms.bIPI} disabled={ro}
                        onChange={(v) => muda((x) => { x.icms.bIPI = v; return x; })} />
                      <Liga id="icms-desc" rotulo="Desconta o desconto da base" valor={n.icms.bDesc} disabled={ro}
                        onChange={(v) => muda((x) => { x.icms.bDesc = v; return x; })} />
                    </Inline>
                    {temSt && (
                      <>
                        <Escolha id="st-modbc" rotulo="Modalidade da base do ST" valor={n.icms.st.modbcst} opcoes={MODBCST} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.st.modbcst = v; return x; })} />
                        <Escolha id="st-mva" rotulo="Origem da MVA" valor={n.icms.st.mvaOrig} opcoes={ORIG_MVA} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.st.mvaOrig = v as 'produto' | 'fixa'; return x; })} />
                        {n.icms.st.mvaOrig === 'fixa' && <Percentual id="st-mva-p" rotulo="MVA" valor={n.icms.st.mva} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.st.mva = v; return x; })} />}
                        <Percentual id="st-red" rotulo="Redução da base do ST" valor={n.icms.st.redB} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.st.redB = v; return x; })} />
                      </>
                    )}
                    {!entrada && n.fin !== '4' && (
                      <Inline wrap gap={4} className="sm:col-span-2">
                        <Liga id="icms-difal" rotulo="Calcular DIFAL" valor={n.icms.difal} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.difal = v; return x; })} />
                        <Liga id="icms-fcp" rotulo="Calcular FCP" valor={n.icms.fcp} disabled={ro}
                          onChange={(v) => muda((x) => { x.icms.fcp = v; return x; })} />
                      </Inline>
                    )}
                  </Grid>
                )}

                {aba === 'ipi' && (
                  <Grid cols={1} gap={4} className="sm:grid-cols-2">
                    <div className="sm:col-span-2">
                      <Liga id="ipi-on" rotulo="Destacar IPI" valor={n.ipi.on} disabled={ro}
                        onChange={(v) => muda((x) => { x.ipi.on = v; return x; })} />
                    </div>
                    {n.ipi.on && (
                      <>
                        <Escolha id="ipi-cst" rotulo="CST do IPI" valor={n.ipi.cst} opcoes={entrada ? CST_IPI_E : CST_IPI_S} disabled={ro}
                          onChange={(v) => muda((x) => { x.ipi.cst = v; return x; })} />
                        <Campo id="ipi-cenq" rotulo="Enquadramento (cEnq)">
                          <Input id="ipi-cenq" className="font-mono" maxLength={3} disabled={ro} value={n.ipi.cEnq}
                            onChange={(e) => muda((x) => { x.ipi.cEnq = e.target.value.replace(/\D/g, ''); return x; })} />
                        </Campo>
                        <Escolha id="ipi-origem" rotulo="Origem da alíquota" valor={n.ipi.origem} opcoes={ORIG_IPI} disabled={ro}
                          onChange={(v) => muda((x) => { x.ipi.origem = v as Natureza['ipi']['origem']; return x; })} />
                        {n.ipi.origem === 'fixa' && <Percentual id="ipi-p" rotulo="Alíquota" valor={n.ipi.p} disabled={ro}
                          onChange={(v) => muda((x) => { x.ipi.p = v; return x; })} />}
                      </>
                    )}
                  </Grid>
                )}

                {aba === 'pc' && (
                  <Grid cols={1} gap={6} className="sm:grid-cols-2">
                    {(['pis', 'cofins'] as const).map((k) => (
                      <div key={k} className="space-y-4">
                        <p className="text-sm font-medium">{k === 'pis' ? 'PIS' : 'COFINS'}</p>
                        <Escolha id={`${k}-cst`} rotulo={sn ? 'CST no Simples' : 'CST'}
                          valor={sn ? n[k].cstSN : n[k].cst}
                          opcoes={entrada ? CST_PC_E : CST_PC_S} disabled={ro}
                          onChange={(v) => muda((x) => { if (sn) x[k].cstSN = v; else x[k].cst = v; return x; })} />
                        <Escolha id={`${k}-origem`} rotulo="Origem da alíquota" valor={n[k].origem} opcoes={ORIG_PC} disabled={ro}
                          onChange={(v) => muda((x) => { x[k].origem = v as Natureza['pis']['origem']; return x; })} />
                        {n[k].origem === 'fixa' && <Percentual id={`${k}-p`} rotulo="Alíquota" valor={n[k].p} disabled={ro}
                          onChange={(v) => muda((x) => { x[k].p = v; return x; })} />}
                        <Liga id={`${k}-excl`} rotulo="Tirar o ICMS da base" valor={n[k].exclICMS} disabled={ro}
                          onChange={(v) => muda((x) => { x[k].exclICMS = v; return x; })} />
                      </div>
                    ))}
                  </Grid>
                )}

                {aba === 'ibs' && (
                  <Grid cols={1} gap={4} className="sm:grid-cols-2">
                    <div className="sm:col-span-2">
                      <Liga id="ibs-on" rotulo="Informar IBS e CBS" valor={n.ibs.on} disabled={ro}
                        onChange={(v) => muda((x) => { x.ibs.on = v; return x; })} />
                    </div>
                    {n.ibs.on && (
                      <>
                        <Escolha id="ibs-cst" rotulo="CST" valor={n.ibs.cst} opcoes={CST_IBS} disabled={ro}
                          onChange={(v) => muda((x) => { x.ibs.cst = v; return x; })} />
                        <Campo id="ibs-cclass" rotulo="cClassTrib" ajuda="6 dígitos.">
                          <Input id="ibs-cclass" className="font-mono" maxLength={6} disabled={ro} value={n.ibs.cClass}
                            onChange={(e) => muda((x) => { x.ibs.cClass = e.target.value.replace(/\D/g, ''); return x; })} />
                        </Campo>
                        <Escolha id="ibs-origem" rotulo="Origem das alíquotas" valor={n.ibs.origem} opcoes={ORIG_IBS} disabled={ro}
                          onChange={(v) => muda((x) => { x.ibs.origem = v as 'padrao' | 'fixa'; return x; })} />
                        {n.ibs.cst === '200' && <Percentual id="ibs-red" rotulo="Redução" valor={n.ibs.pRed} disabled={ro}
                          onChange={(v) => muda((x) => { x.ibs.pRed = v; return x; })} />}
                      </>
                    )}
                  </Grid>
                )}
              </div>
            )}

            {passo === '3' && (
              <p className="text-sm text-muted-foreground">
                Erro bloqueia o salvar. Aviso e informação não bloqueiam. As regras desta conferência ainda não passaram por
                revisão fiscal.
              </p>
            )}
          </div>

          <aside aria-label="Conferência" className="space-y-2">
            <p className="text-sm font-medium">Conferência</p>
            {ro && <p className="text-xs text-muted-foreground">Natureza só de leitura: não é conferida aqui.</p>}
            {!ro && alertas === null && <p className="text-xs text-muted-foreground">Conferindo…</p>}
            {!ro && alertas !== null && visiveis.length === 0 && (
              <p className="text-xs text-muted-foreground">Nada a apontar {passo === '3' ? 'nesta natureza' : 'neste passo'}.</p>
            )}
            <ul className="space-y-2">
              {visiveis.map((a, i) => (
                <Inline key={i} asChild gap={2} align="start" className="text-sm">
                  <li>
                    <Badge variant={VARIANTE[a.nivel]}>{NOME_NIVEL[a.nivel]}</Badge>
                    <span>{a.mensagem}</span>
                  </li>
                </Inline>
              ))}
            </ul>
          </aside>
        </Grid>
      </CardContent>
    </Card>
  );
}
