// ImportSales/Preview — prévia + mapeamento da importação de vendas (resposta do POST
// /import-sales/preview). Thread 05 do playbook venda-menu. PT-02 Form (UI-0013).
// Protótipo: VendaImportPreview em prototipo-ui/cowork/Wagner/venda-blade-telas.jsx.
// O envio vai para o MESMO POST /import-sales do Blade, com o mesmo formato
// (file_name · import_fields[coluna] · group_by · location_id). Refs: ADR 0104 · ADR 0093.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Inline, Stack } from '@/Components/layout';

interface Coluna { coluna: string; rotulo: string }
interface Campo { key: string; label: string }

export interface ImportSalesPreviewProps {
  arquivo: string;
  cabecalho: Coluna[];
  linhas: string[][];
  totalLinhas: number;
  vendasPorColuna: Record<string, number>;
  mapaInicial: Record<string, string | null>;
  campos: Campo[];
  businessLocations: Record<string, string>;
  limiteSincrono: number;
  urls: { importar: string; voltar: string };
}

/** Radix Select não aceita value="" (§5 2026-06-29) — "Ignorar" usa sentinela e vira "" no envio. */
const IGNORAR = '__ignorar__';

/** Opções data-driven sem chave/rótulo vazio. */
function opcoes(mapa: Record<string, string> | undefined) {
  return Object.entries(mapa ?? {}).filter(([k, v]) => k !== '' && v);
}

export default function ImportSalesPreview({
  arquivo, cabecalho, linhas, totalLinhas, vendasPorColuna, mapaInicial, campos, businessLocations, limiteSincrono, urls,
}: ImportSalesPreviewProps) {
  const form = useForm({
    file_name: arquivo,
    location_id: '',
    group_by: '',
    import_fields: Object.fromEntries(cabecalho.map((c) => [c.coluna, mapaInicial[c.coluna] ?? IGNORAR])) as Record<string, string>,
  });
  const { data, setData, processing } = form;

  const rotuloDe = useMemo(() => Object.fromEntries(campos.map((c) => [c.key, c.label])), [campos]);

  // R3 — o que falta antes de enviar (mesmas regras que o Blade checava no submit).
  const faltas = useMemo(() => {
    const usados = Object.values(data.import_fields).filter((v) => v !== IGNORAR);
    const tem = (k: string) => usados.includes(k);
    const f: string[] = [];
    if (!tem('customer_phone_number') && !tem('customer_email')) f.push('Mapeie o telefone ou o e-mail do cliente — um dos dois é obrigatório.');
    if (!tem('product') && !tem('sku')) f.push('Mapeie o nome do produto ou o SKU.');
    if (!tem('quantity')) f.push('Mapeie a quantidade.');
    if (!tem('unit_price')) f.push('Mapeie o preço unitário.');
    const repetido = usados.find((v, i) => usados.indexOf(v) !== i);
    if (repetido) f.push(`Um campo não pode ser usado em duas colunas: ${rotuloDe[repetido] ?? repetido}.`);
    if (!data.group_by) f.push('Escolha por qual coluna agrupar as linhas em vendas.');
    if (!data.location_id) f.push('Escolha o local do negócio.');
    return f;
  }, [data, rotuloDe]);

  const vendas = data.group_by ? vendasPorColuna[data.group_by] ?? 0 : null;
  const vaiParaFila = totalLinhas > limiteSincrono;

  function enviar(e: React.FormEvent) {
    e.preventDefault();
    if (faltas.length) return;
    form.transform((d) => ({
      ...d,
      import_fields: Object.fromEntries(Object.entries(d.import_fields).map(([k, v]) => [k, v === IGNORAR ? '' : v])),
    }));
    form.post(urls.importar);
  }

  return (
    <AppShellV2>
      <Head title="Prévia da importação de vendas" />
      <form onSubmit={enviar} className="container mx-auto px-6 py-6 space-y-4">
        <div data-contract="formulario">
          <Stack gap={4} align="stretch">
            <FormSection title="Prévia da importação" count={arquivo}>
              <FormGrid>
                <Stack gap={1} align="stretch" className="text-xs text-muted-foreground">
                  <span aria-hidden="true">Agrupar linhas da venda por *</span>
                  <Select value={data.group_by || undefined} onValueChange={(v) => setData('group_by', v)}>
                    <SelectTrigger aria-label="Agrupar linhas da venda por"><SelectValue placeholder="Selecione a coluna" /></SelectTrigger>
                    <SelectContent>
                      {cabecalho.filter((c) => c.rotulo).map((c) => <SelectItem key={c.coluna} value={c.coluna}>{c.rotulo}</SelectItem>)}
                    </SelectContent>
                  </Select>
                  <span>As linhas com o mesmo valor nessa coluna viram uma única venda.</span>
                </Stack>
                <Stack gap={1} align="stretch" className="text-xs text-muted-foreground">
                  <span aria-hidden="true">Local do negócio *</span>
                  <Select value={data.location_id || undefined} onValueChange={(v) => setData('location_id', v)}>
                    <SelectTrigger aria-label="Local do negócio"><SelectValue placeholder="Selecione" /></SelectTrigger>
                    <SelectContent>
                      {opcoes(businessLocations).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
                    </SelectContent>
                  </Select>
                  <span>A baixa de estoque das vendas acontece neste local.</span>
                </Stack>
              </FormGrid>
            </FormSection>

            <FormSection
              title="Mapeamento das colunas"
              count={totalLinhas > linhas.length ? `mostrando ${linhas.length} de ${totalLinhas} linhas` : `${totalLinhas} linha(s)`}
            >
              <div className="overflow-x-auto max-h-[480px] overflow-y-auto rounded-md border border-border">
                <table className="w-full text-sm">
                  <thead className="sticky top-0 bg-card">
                    <tr className="text-left">
                      <th className="px-2 py-1.5 text-xs text-muted-foreground font-medium">Linha</th>
                      {cabecalho.map((c) => <th key={c.coluna} className="px-2 py-1.5 font-medium whitespace-nowrap">{c.rotulo || '—'}</th>)}
                    </tr>
                    <tr>
                      <td className="px-2 py-1.5" />
                      {cabecalho.map((c) => (
                        <td key={c.coluna} className="px-2 py-1.5 min-w-[160px]">
                          <Select
                            value={data.import_fields[c.coluna] ?? IGNORAR}
                            onValueChange={(v) => setData('import_fields', { ...data.import_fields, [c.coluna]: v })}
                          >
                            <SelectTrigger aria-label={`Campo da coluna ${c.rotulo || c.coluna}`}><SelectValue /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value={IGNORAR}>Ignorar</SelectItem>
                              {campos.filter((x) => x.key).map((x) => <SelectItem key={x.key} value={x.key}>{x.label}</SelectItem>)}
                            </SelectContent>
                          </Select>
                        </td>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {linhas.map((l, i) => (
                      // Linha 1 da planilha é o cabeçalho: a 1ª linha de dados é a 2 — o mesmo
                      // número que a mensagem de erro do import cita (R6).
                      <tr key={i}>
                        <td className="px-2 py-1 font-mono text-xs text-muted-foreground tabular-nums">{i + 2}</td>
                        {cabecalho.map((c, j) => <td key={c.coluna} className="px-2 py-1 font-mono text-xs whitespace-nowrap">{l[j] ?? ''}</td>)}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </FormSection>
          </Stack>
        </div>

        <div data-contract="acoes">
          <Stack gap={3} align="stretch">
            {faltas.length > 0 ? (
              <Alert>
                <AlertTitle>Falta mapear</AlertTitle>
                <AlertDescription>{faltas[0]}</AlertDescription>
              </Alert>
            ) : (
              <p className="text-sm text-muted-foreground">
                Tudo mapeado — a importação cria {vendas} venda(s) a partir de {totalLinhas} linha(s).
                {vaiParaFila && ` Como passa de ${limiteSincrono} linhas, ela vai para a fila e o andamento aparece na tela de importação.`}
              </p>
            )}
            <Inline gap={2} justify="end">
              <Button type="button" variant="outline" asChild><a href={urls.voltar}>Voltar</a></Button>
              <Button type="submit" disabled={processing || faltas.length > 0}>
                {processing ? 'Enviando…' : 'Importar vendas'}
              </Button>
            </Inline>
          </Stack>
        </div>
      </form>
    </AppShellV2>
  );
}
