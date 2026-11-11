// Dados sociais do paciente (US-FISIO-061 · UC-FSO-01..05). Seção recolhida da ficha.
// Todos os campos são opcionais. Dado sensível (LGPD art. 11): a finalidade aparece na tela e
// nada daqui vai ao portal nem à exportação da pesquisa. Listas e rótulos vêm do servidor
// (DadosSociais::OPCOES) — uma fonte só para a ficha e o relatório agregado.
import { Deferred, useForm, type InertiaFormProps } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';

export interface OpcoesSociais {
  opcoes: Record<string, Record<string, string>>;
  comorbidades: Record<string, string>;
  ufs: string[];
}
export type DadosSociaisValores = Record<string, string | string[] | null>;

const NULO = '__nao_perguntado';

// Ordem e rótulo dos campos de escolha na tela.
const ESCOLHAS: [string, string][] = [
  ['sexo', 'Sexo'], ['genero', 'Gênero'], ['raca_cor', 'Raça/cor (IBGE)'], ['renda_familiar', 'Renda familiar por mês'],
  ['cadunico', 'NIS/CadÚnico'], ['situacao_trabalho', 'Situação de trabalho'], ['escolaridade', 'Escolaridade'],
  ['sintomas_ha', 'Há quanto tempo tem sintomas'], ['diagnostico_por', 'Quem diagnosticou'],
  ['como_chegou', 'Como chegou'], ['cipf', 'Carteira de fibromialgia (CIPF)'],
];
const TEXTOS: [string, string, string][] = [
  ['nascimento', 'Data de nascimento', 'date'], ['municipio', 'Município', 'text'], ['bairro', 'Bairro', 'text'],
  ['cns', 'Cartão SUS (CNS)', 'text'], ['diagnostico_cid', 'CID informado', 'text'], ['diagnostico_em', 'Data do diagnóstico', 'date'],
];

const str = (v: string | string[] | null | undefined) => (typeof v === 'string' ? v : '');

function Leitura({ dados, opcoes }: { dados: DadosSociaisValores; opcoes: OpcoesSociais }) {
  const linhas: [string, string][] = [
    ...TEXTOS.map(([c, r]) => [r, str(dados[c])] as [string, string]),
    ['UF', str(dados.uf)],
    ...ESCOLHAS.map(([c, r]) => [r, opcoes.opcoes[c]?.[str(dados[c])] ?? ''] as [string, string]),
    ['Comorbidades', [...((dados.comorbidades as string[]) ?? []).map((c) => opcoes.comorbidades[c] ?? c), str(dados.comorbidades_outras)].filter(Boolean).join('; ')],
  ];
  return (
    <dl className="grid gap-2 text-sm sm:grid-cols-2">
      {linhas.map(([r, v]) => <div key={r}><dt className="text-muted-foreground">{r}</dt><dd>{v || '—'}</dd></div>)}
    </dl>
  );
}

// O form recebe o estado do pai: o <Deferred> desmonta o filho a cada visita (o envio volta pela
// página e a prop deferida é buscada de novo), e o que foi digitado e o erro do campo sumiriam
// (lápide §5 2026-10-13, medido no staging em 2026-11-11).
function Formulario({ pacienteId, form, opcoes }: { pacienteId: number; form: InertiaFormProps<DadosSociaisValores>; opcoes: OpcoesSociais }) {
  const erros = form.errors as Record<string, string>;
  const salvar = (e: FormEvent) => {
    e.preventDefault();
    form.put(`/fisio/pacientes/${pacienteId}/dados-sociais`, { preserveScroll: true, preserveState: true, onSuccess: () => form.setDefaults() });
  };
  const escolha = (campo: string, rotulo: string, lista: Record<string, string>) => (
    <div key={campo} className="flex flex-col gap-1.5">
      <Label htmlFor={`soc-${campo}`}>{rotulo}</Label>
      <Select value={str(form.data[campo]) || NULO} onValueChange={(v) => form.setData(campo, v === NULO ? null : v)}>
        <SelectTrigger id={`soc-${campo}`} aria-label={rotulo}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SafeSelectItem value={NULO}>Não informado</SafeSelectItem>
          {Object.entries(lista).map(([cod, rot]) => <SafeSelectItem key={cod} value={cod}>{rot}</SafeSelectItem>)}
        </SelectContent>
      </Select>
      {erros[campo] && <p className="text-xs text-destructive">{erros[campo]}</p>}
    </div>
  );
  const comorbidades = (form.data.comorbidades as string[]) ?? [];

  return (
    <form onSubmit={salvar} className="flex flex-col gap-4" data-contract="fisio-dados-sociais-form">
      <div className="grid gap-3 sm:grid-cols-2">
        {TEXTOS.map(([campo, rotulo, tipo]) => (
          <div key={campo} className="flex flex-col gap-1.5">
            <Label htmlFor={`soc-${campo}`}>{rotulo}</Label>
            <Input id={`soc-${campo}`} type={tipo} value={str(form.data[campo])} onChange={(e) => form.setData(campo, e.target.value)}
              inputMode={campo === 'cns' ? 'numeric' : undefined} placeholder={campo === 'diagnostico_cid' ? 'M79.7' : undefined} />
            {erros[campo] && <p className="text-xs text-destructive">{erros[campo]}</p>}
          </div>
        ))}
        {escolha('uf', 'UF', Object.fromEntries(opcoes.ufs.map((u) => [u, u])))}
        {ESCOLHAS.map(([campo, rotulo]) => escolha(campo, rotulo, opcoes.opcoes[campo] ?? {}))}
      </div>
      <fieldset className="flex flex-col gap-2">
        <legend className="text-sm font-medium">Comorbidades</legend>
        <div className="grid gap-2 sm:grid-cols-3">
          {Object.entries(opcoes.comorbidades).map(([cod, rot]) => (
            <label key={cod} className="flex items-center gap-2 text-sm">
              <Checkbox checked={comorbidades.includes(cod)}
                onCheckedChange={(v) => form.setData('comorbidades', v ? [...comorbidades, cod] : comorbidades.filter((c) => c !== cod))} />
              {rot}
            </label>
          ))}
        </div>
        <Input aria-label="Outras comorbidades" placeholder="Outras" value={str(form.data.comorbidades_outras)}
          onChange={(e) => form.setData('comorbidades_outras', e.target.value)} />
      </fieldset>
      {erros.dados_sociais && <p className="text-xs text-destructive">{erros.dados_sociais}</p>}
      <div><Button type="submit" disabled={form.processing}>Salvar dados sociais</Button></div>
    </form>
  );
}

export default function DadosSociais({ pacienteId, dados, opcoes, podeEditar }: {
  pacienteId: number; dados?: DadosSociaisValores; opcoes: OpcoesSociais; podeEditar: boolean;
}) {
  const [aberto, setAberto] = useState(false);
  const form = useForm<DadosSociaisValores>({});
  const [pronto, setPronto] = useState(false);
  // Carrega os valores do servidor UMA vez; depois o form é a fonte (não apaga o que foi digitado).
  useEffect(() => {
    if (dados && !pronto) {
      const v = { ...dados, comorbidades: (dados.comorbidades as string[]) ?? [] };
      form.setDefaults(v);
      form.setData(v);
      setPronto(true);
    }
  }, [dados, pronto]); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <section className="flex flex-col gap-3" data-contract="fisio-dados-sociais">
      <button type="button" className="flex items-center gap-2 text-left text-base font-semibold" aria-expanded={aberto} onClick={() => setAberto(!aberto)}>
        {aberto ? <ChevronDown className="size-4" aria-hidden="true" /> : <ChevronRight className="size-4" aria-hidden="true" />}
        Dados sociais
      </button>
      {aberto && (
        <>
          <p className="text-xs text-muted-foreground">
            Tudo aqui é opcional. Serve para a associação mostrar, só em números somados e sem nome, quem é atendido e se melhora
            (editais, emendas e convênios). Raça/cor e saúde são dados sensíveis (LGPD, art. 11): não vão ao app do paciente
            nem à exportação da pesquisa.
          </p>
          {podeEditar && pronto ? (
            <Formulario pacienteId={pacienteId} form={form} opcoes={opcoes} />
          ) : (
            <Deferred data="dados_sociais" fallback={<Skeleton className="h-40 w-full" />}>
              {podeEditar ? <Skeleton className="h-40 w-full" /> : <Leitura dados={dados ?? {}} opcoes={opcoes} />}
            </Deferred>
          )}
        </>
      )}
    </section>
  );
}
