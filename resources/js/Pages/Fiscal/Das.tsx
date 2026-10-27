// Fiscal/Das — apuração do DAS pelo Anexo (US-FISCAL-030 fatia 2).
// Nasceu do PT-04 Dashboard via criar-tela.mjs; forma do protótipo
// prototipo-ui/cowork/Wagner/prototipos/fiscal-das.html. Só leitura: a conta vem pronta do
// servidor (ApuracaoDasService) — a tela não recalcula valor.
import { Grid, Inline, Stack } from '@/Components/layout';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

import FxShell from './_components/FxShell';
import { brl } from './_lib/fiscal-helpers';
import '../../../css/fiscal-cockpit.css';

interface Faixa { faixa: number; ate: number; aliquota: number; parcela: number }
interface Apuracao {
  rbt12: number;
  receita_mes: number;
  faixa: number | null;
  aliquota_nominal: number | null;
  parcela_deduzir: number | null;
  aliquota_efetiva: number | null;
  das: number | null;
  acima_sublimite: boolean;
  motivo: string | null;
  rbt12_de: string;
  rbt12_ate: string;
}
interface Props {
  competencia: string;
  anexo: string | null;
  anexos: string[];
  faixas: Faixa[];
  apuracao?: Apuracao | null;
}

const ROTULO_ANEXO: Record<string, string> = {
  I: 'Anexo I · Comércio',
  II: 'Anexo II · Indústria',
  III: 'Anexo III · Serviços',
  IV: 'Anexo IV · Serviços',
  V: 'Anexo V · Serviços',
};

const pct = (v: number, casas = 2) =>
  `${(v * 100).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas })}%`;
const dataBr = (iso: string) => iso.split('-').reverse().join('/');

function vencimento(competencia: string): string {
  const [ano = 0, mes = 1] = competencia.split('-').map(Number);
  return `20/${String((mes % 12) + 1).padStart(2, '0')}/${mes === 12 ? ano + 1 : ano}`;
}

export default function Das({ competencia, anexo, anexos, faixas, apuracao }: Props) {
  const filtrar = (dados: { competencia?: string; anexo?: string }) =>
    router.get('/fiscal/das', { competencia, anexo: anexo ?? '', ...dados }, { preserveScroll: true });

  const semValor = !apuracao || apuracao.das === null;

  return (
    <AppShellV2>
      <Head title="Fiscal · Apuração do DAS" />
      <FxShell
        route="das"
        title="Apuração do DAS"
        crumb="Simples Nacional · prévia da guia do mês pelo anexo do contador"
        env="prévia — não transmite o PGDAS-D"
        envTone="warn"
      >
        <div data-contract="cabecalho">
          <Inline gap={3} align="end" wrap className="mb-3">
            <Stack gap={1}>
              <label htmlFor="das-competencia" className="text-[length:var(--fs-2)] text-[var(--text-2)]">Competência</label>
              <Input
                id="das-competencia"
                type="month"
                value={competencia}
                className="w-[180px]"
                onChange={(e) => e.target.value && filtrar({ competencia: e.target.value })}
              />
            </Stack>
            <Stack gap={1}>
              <label htmlFor="das-anexo" className="text-[length:var(--fs-2)] text-[var(--text-2)]">Anexo (informado pelo contador)</label>
              <Select value={anexo ?? undefined} onValueChange={(v) => filtrar({ anexo: v })}>
                <SelectTrigger id="das-anexo" className="w-[220px]">
                  <SelectValue placeholder="Escolha o anexo" />
                </SelectTrigger>
                <SelectContent>
                  {anexos.filter(Boolean).map((a) => (
                    <SelectItem key={a} value={a}>{ROTULO_ANEXO[a] ?? `Anexo ${a}`}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Stack>
          </Inline>
        </div>

        {!anexo ? (
          <Card data-contract="vazio">
            <CardContent className="py-8 text-center">
              <p className="font-semibold">Escolha o anexo que o contador usa</p>
              <p className="text-[var(--text-2)]">
                O sistema não escolhe o anexo. Para serviços, o Fator R indica III ou V.
              </p>
            </CardContent>
          </Card>
        ) : (
          <Deferred data="apuracao" fallback={<p className="text-[var(--text-2)]">Calculando…</p>}>
          {apuracao ? (
          <Stack gap={3}>
            {apuracao.acima_sublimite && (
              <Alert role="region" aria-label="Aviso de sublimite">
                <AlertTriangle size={16} />
                <AlertTitle>Receita dos 12 meses acima do sublimite (LC 123, art. 13-A)</AlertTitle>
                <AlertDescription>
                  ICMS e ISS saem do DAS e passam a ser pagos por fora. O valor abaixo ainda inclui essa parte:
                  a partilha é decisão do contador.
                </AlertDescription>
              </Alert>
            )}
            {apuracao.motivo && (
              <Alert role="region" aria-label="Por que não há valor">
                <AlertTriangle size={16} />
                <AlertTitle>Sem valor para esta competência</AlertTitle>
                <AlertDescription>{apuracao.motivo}</AlertDescription>
              </Alert>
            )}

            <div data-contract="kpis">
              <KpiGrid cols={4}>
                <KpiCard label="Receita do mês" value={brl(apuracao.receita_mes)} description="vendas finalizadas − devoluções" />
                <KpiCard
                  label="RBT12"
                  value={apuracao.rbt12 > 0 ? brl(apuracao.rbt12) : '—'}
                  description={`${dataBr(apuracao.rbt12_de)} a ${dataBr(apuracao.rbt12_ate)}`}
                />
                <KpiCard
                  label="Alíquota efetiva"
                  value={apuracao.aliquota_efetiva !== null ? pct(apuracao.aliquota_efetiva, 3) : '—'}
                  description={
                    apuracao.faixa !== null && apuracao.aliquota_nominal !== null
                      ? `${apuracao.faixa}ª faixa · nominal ${pct(apuracao.aliquota_nominal)}`
                      : 'sem faixa'
                  }
                />
                <KpiCard
                  label="DAS do mês (prévia)"
                  value={semValor ? '—' : brl(apuracao.das)}
                  description={`vence ${vencimento(competencia)}`}
                />
              </KpiGrid>
            </div>

            <Grid cols={2} gap={3}>
              <Stack gap={3}>
                <Card data-contract="conta">
                  <CardHeader><CardTitle>A conta · LC 123/2006, art. 18 §1º-A</CardTitle></CardHeader>
                  <CardContent>
                    {semValor || apuracao.aliquota_nominal === null || apuracao.aliquota_efetiva === null ? (
                      <p className="text-[var(--text-2)]">Sem RBT12 não há alíquota efetiva.</p>
                    ) : (
                      <p className="font-mono text-[length:var(--fs-3)] leading-7">
                        (RBT12 × Aliq − PD) ÷ RBT12
                        <br />({brl(apuracao.rbt12)} × {pct(apuracao.aliquota_nominal)} − {brl(apuracao.parcela_deduzir)}) ÷ {brl(apuracao.rbt12)} = {pct(apuracao.aliquota_efetiva, 4)}
                        <br />DAS = {brl(apuracao.receita_mes)} × {pct(apuracao.aliquota_efetiva, 4)} = <b>{brl(apuracao.das)}</b>
                      </p>
                    )}
                    <p className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">
                      O valor sai da conta inteira; a alíquota efetiva só aparece arredondada.
                    </p>
                  </CardContent>
                </Card>
                <Card data-contract="faixas">
                  <CardHeader><CardTitle>Faixas do Anexo {anexo}</CardTitle></CardHeader>
                  <CardContent>
                    <table className="w-full text-[length:var(--fs-3)]">
                      <thead>
                        <tr className="text-[var(--text-2)]">
                          <th className="text-left font-medium">Faixa · RBT12 até</th>
                          <th className="text-right font-medium">Alíquota nominal</th>
                          <th className="text-right font-medium">Parcela a deduzir</th>
                        </tr>
                      </thead>
                      <tbody className="font-mono">
                        {faixas.map((f) => (
                          <tr key={f.faixa} aria-current={f.faixa === apuracao.faixa ? 'true' : undefined}
                            className={f.faixa === apuracao.faixa ? 'bg-[var(--accent-soft)] font-semibold' : ''}>
                            <td>{f.faixa}ª · {brl(f.ate)}</td>
                            <td className="text-right">{pct(f.aliquota)}</td>
                            <td className="text-right">{brl(f.parcela)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </CardContent>
                </Card>
              </Stack>
              <Card data-contract="contador">
                <CardHeader><CardTitle>Confirme com o contador</CardTitle></CardHeader>
                <CardContent>
                  <ol className="list-decimal pl-5 text-[length:var(--fs-3)]">
                    <li>A empresa apura por competência ou por caixa?</li>
                    <li>Há receita a segregar (ST retida, monofásico, exportação, outra atividade)?</li>
                    <li>No início de atividade, qual receita proporcional usar?</li>
                    <li>Acima do sublimite, qual parte sai do DAS?</li>
                  </ol>
                </CardContent>
              </Card>
            </Grid>
          </Stack>
          ) : null}
          </Deferred>
        )}
      </FxShell>
    </AppShellV2>
  );
}
