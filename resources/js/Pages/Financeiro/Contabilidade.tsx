// Financeiro/Contabilidade — exportar os lançamentos da competência para o sistema do contador (US-FIN-077).
// Carimbada do PT-02 por criar-tela.mjs; forma do protótipo prototipo-ui/cowork/Wagner/financeiro-contabil-page.jsx
// (aba "Exportar para o contador"). A aba "Importar títulos" não entra: não há backend (US-FIN-034) e gravar
// título é VALOR — decisão [W].
//
// O arquivo vem do servidor (ExportacaoContabilController@download). Com de-para incompleto ele responde 422
// com as pendências e o arquivo não sai: a tela só mostra o que o servidor devolveu, não calcula valor.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Segmented } from '@/Components/ui/segmented';
import { Skeleton } from '@/Components/ui/skeleton';
import { Inline, Stack } from '@/Components/layout';
import { PageHeader } from '@/Components/PageHeader';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';

interface ItemDePara {
  origem_tipo: 'conta_bancaria' | 'plano_conta' | 'juros' | 'desconto';
  origem_id: number;
  grupo: string;
  nome: string;
  codigo: string;
}

interface Props {
  competencia: string;
  layouts: string[];
  depara?: ItemDePara[];
}

const SISTEMAS = [
  { id: 'dominio', label: 'Domínio', nota: 'Leiaute com separador · registros 0000/6000/6100', onde: 'Utilitários › Importação › Lançamentos Contábeis em Lote.' },
  { id: 'alterdata', label: 'Alterdata', nota: 'Importação personalizada em texto · colunas A a J', onde: 'Lançamentos › Importar Lançamentos › Importação Personalizada – Texto.' },
  { id: 'questor', label: 'Questor', nota: 'Em definição: o Questor importa pelo leiaute que o contador configura', onde: '' },
];

type Erros = Record<string, string | undefined>;

function DeParaForm({ itens }: { itens: ItemDePara[] }) {
  const form = useForm({ itens });
  const faltam = form.data.itens.filter((d) => !d.codigo.trim()).length;
  const erros = form.errors as Erros;

  const mudar = (i: number, v: string) =>
    form.setData('itens', form.data.itens.map((d, j) => (j === i ? { ...d, codigo: v.replace(/[^0-9A-Za-z.-]/g, '') } : d)));

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        form.put('/financeiro/exportacao-contabil/de-para', {
          preserveScroll: true,
          onSuccess: () => toast.success('Códigos do contador salvos.'),
          onError: () => toast.error('Algum código não foi aceito — confira as linhas destacadas.'),
        });
      }}
    >
      <Stack gap={3}>
        <Inline justify="between" align="start" wrap>
          <div>
            <h2 className="text-base font-semibold m-0">Códigos no sistema do contador</h2>
            <p className="text-sm text-muted-foreground m-0">
              O código reduzido de cada conta, como aparece no plano de contas do contador. Vale para os três sistemas.
            </p>
          </div>
          {faltam > 0
            ? <span className="text-xs px-2 py-0.5 rounded bg-warning-soft text-warning-fg">{faltam} sem código</span>
            : <span className="text-xs px-2 py-0.5 rounded bg-success-soft text-success-fg">Completo</span>}
        </Inline>
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-muted-foreground">
                <th className="px-3 py-2 font-medium">Origem</th>
                <th className="px-3 py-2 font-medium">Conta no oimpresso</th>
                <th className="px-3 py-2 font-medium w-48">Código no contador</th>
              </tr>
            </thead>
            <tbody>
              {form.data.itens.length === 0 && (
                <tr><td colSpan={3} className="px-3 py-6 text-center text-muted-foreground">Nenhuma conta bancária nem plano de contas cadastrado.</td></tr>
              )}
              {form.data.itens.map((d, i) => {
                const erro = erros[`itens.${i}.codigo`] ?? erros[`itens.${i}.origem_id`];
                return (
                  <tr key={`${d.origem_tipo}:${d.origem_id}`} className={d.codigo ? 'border-t' : 'border-t bg-warning-soft'}>
                    <td className="px-3 py-2 text-muted-foreground">{d.grupo}</td>
                    <td className="px-3 py-2">{d.nome}</td>
                    <td className="px-3 py-2">
                      <Input
                        aria-label={`Código contábil de ${d.nome}`}
                        className="font-mono"
                        maxLength={30}
                        value={d.codigo}
                        placeholder="Falta código"
                        onChange={(e) => mudar(i, e.target.value)}
                        aria-invalid={erro ? true : undefined}
                      />
                      {erro && <p role="alert" className="text-xs text-destructive m-0 mt-1">{erro}</p>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <Inline justify="end">
          <Button type="submit" variant="outline" disabled={form.processing || !form.isDirty}>Salvar códigos</Button>
        </Inline>
      </Stack>
    </form>
  );
}

// Dentro do <Deferred>, a prop chega pelas page props.
function DeParaCarregado() {
  const { depara } = usePage<{ depara?: ItemDePara[] }>().props;
  return <DeParaForm itens={depara ?? []} />;
}

export default function Contabilidade({ competencia: competenciaInicial }: Props) {
  const [competencia, setCompetencia] = useState(competenciaInicial);
  const [sistema, setSistema] = useState('dominio');
  const [gerando, setGerando] = useState(false);
  const [pendencias, setPendencias] = useState<string[]>([]);
  const [baixado, setBaixado] = useState<string | null>(null);
  const sis = SISTEMAS.find((s) => s.id === sistema) ?? SISTEMAS[0]!;

  const limpar = () => { setPendencias([]); setBaixado(null); };

  async function gerar() {
    setGerando(true);
    limpar();
    try {
      const r = await fetch(`/financeiro/exportacao-contabil/${sistema}?competencia=${encodeURIComponent(competencia)}`, {
        headers: { Accept: 'application/json, text/plain' },
        credentials: 'same-origin',
      });
      if (r.status === 422) {
        const corpo = await r.json().catch(() => ({}));
        setPendencias(Array.isArray(corpo.pendencias) ? corpo.pendencias : [corpo.message ?? 'Não foi possível gerar o arquivo.']);
        return;
      }
      if (!r.ok) {
        toast.error('Não foi possível gerar o arquivo.');
        return;
      }
      const nome = /filename="([^"]+)"/.exec(r.headers.get('Content-Disposition') ?? '')?.[1]
        ?? `lancamentos_${sistema}_${competencia.replace('-', '')}.txt`;
      const url = URL.createObjectURL(await r.blob());
      const a = document.createElement('a');
      a.href = url;
      a.download = nome;
      a.click();
      URL.revokeObjectURL(url);
      setBaixado(nome);
    } finally {
      setGerando(false);
    }
  }

  return (
    <AppShellV2 title="Contabilidade" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Contabilidade' }]}>
      <div className="p-6 max-w-6xl mx-auto">
        <Stack gap={4}>
          <PageHeader title="Contabilidade" subtitle={<>Leve os lançamentos do mês para o sistema do seu contador.</>}>
            <div className="ml-auto">
              <FinanceiroSubNav active="exportacao-contabil" hidePrimary />
            </div>
          </PageHeader>

          <div data-contract="formulario">
            <Stack gap={4}>
              <section aria-label="O que exportar" className="rounded-md border bg-card p-4">
                <Inline gap={6} wrap align="start">
                  <Stack gap={1}>
                    <Label htmlFor="fc-competencia">Competência</Label>
                    <Input
                      id="fc-competencia"
                      type="month"
                      className="w-44"
                      value={competencia}
                      onChange={(e) => { setCompetencia(e.target.value); limpar(); }}
                    />
                  </Stack>
                  <Stack gap={1}>
                    <span className="text-sm font-medium">Sistema do contador</span>
                    <Segmented
                      aria-label="Sistema do contador"
                      value={sistema}
                      onValueChange={(v) => { setSistema(v); limpar(); }}
                      options={SISTEMAS.map((s) => ({
                        value: s.id,
                        label: s.id === 'questor' ? <>Questor <small>em breve</small></> : s.label,
                        disabled: s.id === 'questor',
                      }))}
                    />
                    <span className="text-xs text-muted-foreground">{sis.nota}</span>
                  </Stack>
                </Inline>
              </section>

              <section className="rounded-md border bg-card p-4">
                <Deferred data="depara" fallback={<Skeleton className="h-40 w-full" />}>
                  <DeParaCarregado />
                </Deferred>
              </section>
            </Stack>
          </div>

          <section data-contract="acoes" aria-label="Gerar arquivo" className="rounded-md border bg-card p-4">
            <Stack gap={3}>
              {pendencias.length > 0 && (
                <div role="status" className="text-sm rounded bg-warning-soft text-warning-fg p-3">
                  <p className="m-0 font-medium">O arquivo só sai completo. Falta código para:</p>
                  <ul className="m-0 mt-1 pl-5 list-disc">
                    {pendencias.slice(0, 20).map((p) => <li key={p}>{p}</li>)}
                  </ul>
                  {pendencias.length > 20 && <p className="m-0 mt-1">e mais {pendencias.length - 20}.</p>}
                </div>
              )}
              {baixado && (
                <p className="text-sm m-0">
                  <b className="font-mono">{baixado}</b> baixado. No {sis.label}: {sis.onde}
                </p>
              )}
              <Inline justify="end">
                <Button type="button" onClick={gerar} disabled={gerando || sistema === 'questor' || !competencia}>
                  {gerando ? 'Gerando…' : `Gerar arquivo do ${sis.label}`}
                </Button>
              </Inline>
            </Stack>
          </section>
        </Stack>
      </div>
    </AppShellV2>
  );
}
