// Questionários de uso livre na ficha do paciente (US-FISIO-011; ADR 0437 §8).
//
// O profissional transcreve as respostas do formulário impresso da versão brasileira validada,
// item por item, pelo número. Os enunciados ainda NÃO estão no sistema: aguardam a conferência
// da Dra. Dejeane contra a publicação (nada é parafraseado aqui). A pontuação é do servidor.
// A tela mostra o número, nunca uma interpretação ("melhorou", conduta) — SaMD, ADR 0437 §5.
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';

export interface Referencia {
  tipo: 'original' | 'brasileira';
  citacao: string;
  doi: string | null;
}
export interface Instrumento {
  codigo: string;
  nome: string;
  tipo: 'escala' | 'sim_nao' | 'atividades' | 'likert' | 'numerica';
  itens: { numero: number; texto: string | null }[];
  faixa_item: [number, number];
  max_atividades?: number;
  pontuacao: string;
  faixa_escore: [number, number];
  direcao: string;
  texto_pendente: boolean;
  referencias: Referencia[];
}
export interface RespostaQuestionario {
  id: number;
  instrumento: string;
  score: number | null;
  detalhe: Record<string, number>;
  answered_at: string | null;
}

type Atividade = { descricao: string; nota: string };
interface FormQuestionario {
  instrumento: string;
  valor: string;
  itens: Record<string, string>;
  atividades: Atividade[];
}

const dataHora = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
const numeroBr = (n: number | null) => (n === null ? '—' : n.toLocaleString('pt-BR', { maximumFractionDigits: 2 }));
const ROTULO_DETALHE: Record<string, string> = { dor: 'Dor', incapacidade: 'Incapacidade' };

function Referencias({ instrumento }: { instrumento: Instrumento }) {
  return (
    <ul className="flex flex-col gap-1 text-xs text-muted-foreground">
      {instrumento.referencias.map((r) => (
        <li key={r.tipo + r.citacao}>
          {r.tipo === 'brasileira' ? 'Versão brasileira: ' : 'Original: '}
          {r.citacao}
          {r.doi && <> · DOI {r.doi}</>}
        </li>
      ))}
    </ul>
  );
}

function NovoQuestionario({ pacienteId, instrumentos }: { pacienteId: number; instrumentos: Instrumento[] }) {
  const form = useForm<FormQuestionario>({ instrumento: '', valor: '', itens: {}, atividades: [{ descricao: '', nota: '' }] });
  const atual = instrumentos.find((i) => i.codigo === form.data.instrumento);
  const erros = form.errors as Record<string, string>;
  const [min, max] = atual?.faixa_item ?? [0, 0];

  const registrar = (e: FormEvent) => {
    e.preventDefault();
    if (!atual) return;
    form.transform((d) => {
      if (atual.tipo === 'escala') return { instrumento: d.instrumento, respostas: { valor: d.valor } };
      if (atual.tipo === 'atividades') return { instrumento: d.instrumento, respostas: { atividades: d.atividades } };
      const itens = Object.fromEntries(atual.itens.map((it) => [String(it.numero), d.itens[String(it.numero)] ?? (atual.tipo === 'sim_nao' ? '0' : '')]));
      return { instrumento: d.instrumento, respostas: { itens } };
    });
    form.post(`/fisio/pacientes/${pacienteId}/questionarios`, {
      preserveScroll: true,
      onSuccess: () => form.reset('valor', 'itens', 'atividades'),
    });
  };

  const setItem = (n: number, v: string) => form.setData('itens', { ...form.data.itens, [String(n)]: v });
  const setAtividade = (i: number, campo: keyof Atividade, v: string) =>
    form.setData('atividades', form.data.atividades.map((a, j) => (j === i ? { ...a, [campo]: v } : a)));

  return (
    <form onSubmit={registrar} className="flex flex-col gap-3 rounded-lg border border-border p-4" data-contract="fisio-questionario-novo">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="q-instrumento">Questionário</Label>
        <Select value={form.data.instrumento} onValueChange={(v) => { form.setData('instrumento', v); form.clearErrors(); }}>
          <SelectTrigger id="q-instrumento" className="max-w-md"><SelectValue placeholder="Escolha o questionário" /></SelectTrigger>
          <SelectContent>
            {instrumentos.map((i) => <SafeSelectItem key={i.codigo} value={i.codigo}>{i.nome}</SafeSelectItem>)}
          </SelectContent>
        </Select>
        {erros.instrumento && <p className="text-xs text-destructive">{erros.instrumento}</p>}
      </div>

      {atual && (
        <>
          {atual.texto_pendente && (
            <p className="text-xs text-muted-foreground">
              Os enunciados da versão brasileira validada ainda não estão no sistema. Transcreva as respostas do formulário impresso, pelo número do item.
            </p>
          )}

          {atual.tipo === 'escala' && (
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="q-valor">Valor marcado ({min} a {max})</Label>
              <Input id="q-valor" type="number" inputMode="decimal" min={min} max={max} step="0.1" className="max-w-32" value={form.data.valor} onChange={(e) => form.setData('valor', e.target.value)} />
              {erros['respostas.valor'] && <p className="text-xs text-destructive">{erros['respostas.valor']}</p>}
            </div>
          )}

          {atual.tipo === 'sim_nao' && (
            <fieldset className="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <legend className="mb-2 text-sm">Marque os itens respondidos com &quot;sim&quot;</legend>
              {atual.itens.map((it) => (
                <label key={it.numero} className="flex items-center gap-2 text-sm">
                  <Checkbox checked={form.data.itens[String(it.numero)] === '1'} onCheckedChange={(c) => setItem(it.numero, c === true ? '1' : '0')} />
                  Item {it.numero}
                </label>
              ))}
            </fieldset>
          )}

          {(atual.tipo === 'likert' || atual.tipo === 'numerica') && (
            <fieldset className="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <legend className="mb-2 text-sm">Resposta de cada item ({min} a {max})</legend>
              {atual.itens.map((it) => (
                <div key={it.numero} className="flex flex-col gap-1">
                  <Label htmlFor={`q-item-${it.numero}`}>Item {it.numero}</Label>
                  <Input id={`q-item-${it.numero}`} type="number" inputMode="numeric" min={min} max={max} step="1" value={form.data.itens[String(it.numero)] ?? ''} onChange={(e) => setItem(it.numero, e.target.value)} />
                  {erros[`respostas.itens.${it.numero}`] && <p className="text-xs text-destructive">{erros[`respostas.itens.${it.numero}`]}</p>}
                </div>
              ))}
            </fieldset>
          )}

          {atual.tipo === 'atividades' && (
            <div className="flex flex-col gap-2">
              {form.data.atividades.map((a, i) => (
                <div key={i} className="flex flex-wrap items-end gap-2">
                  <div className="flex min-w-48 flex-1 flex-col gap-1">
                    <Label htmlFor={`q-ativ-${i}`}>Atividade {i + 1} (palavras do paciente)</Label>
                    <Input id={`q-ativ-${i}`} value={a.descricao} onChange={(e) => setAtividade(i, 'descricao', e.target.value)} />
                  </div>
                  <div className="flex flex-col gap-1">
                    <Label htmlFor={`q-nota-${i}`}>Nota ({min} a {max})</Label>
                    <Input id={`q-nota-${i}`} type="number" inputMode="numeric" min={min} max={max} step="1" className="max-w-24" value={a.nota} onChange={(e) => setAtividade(i, 'nota', e.target.value)} />
                  </div>
                  {(erros[`respostas.atividades.${i}.descricao`] || erros[`respostas.atividades.${i}.nota`]) && (
                    <p className="w-full text-xs text-destructive">{erros[`respostas.atividades.${i}.descricao`] ?? erros[`respostas.atividades.${i}.nota`]}</p>
                  )}
                </div>
              ))}
              {form.data.atividades.length < (atual.max_atividades ?? 5) && (
                <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => form.setData('atividades', [...form.data.atividades, { descricao: '', nota: '' }])}>
                  Adicionar atividade
                </Button>
              )}
            </div>
          )}

          {(erros['respostas.itens'] || erros['respostas.atividades'] || erros.respostas) && (
            <p className="text-xs text-destructive">{erros['respostas.itens'] ?? erros['respostas.atividades'] ?? erros.respostas}</p>
          )}
          <p className="text-xs text-muted-foreground">Pontuação: {atual.pontuacao} · {atual.direcao}.</p>
          <Referencias instrumento={atual} />
          <div><Button type="submit" disabled={form.processing}>Registrar questionário</Button></div>
        </>
      )}
    </form>
  );
}

export function HistoricoQuestionarios({ respostas, instrumentos }: { respostas: RespostaQuestionario[]; instrumentos: Instrumento[] }) {
  if (respostas.length === 0) return <p className="text-sm text-muted-foreground">Nenhum questionário registrado.</p>;
  const porCodigo = Object.fromEntries(instrumentos.map((i) => [i.codigo, i]));
  return (
    <ol className="flex flex-col gap-2" data-contract="fisio-questionarios">
      {respostas.map((r) => {
        const def = porCodigo[r.instrumento];
        return (
          <li key={r.id} className="flex flex-wrap items-center gap-2 rounded-lg border border-border p-3 text-sm">
            <Badge variant="secondary">{def?.nome ?? r.instrumento}</Badge>
            <span className="font-semibold tabular-nums">{numeroBr(r.score)}</span>
            {def && <span className="text-muted-foreground">de {def.faixa_escore[0]} a {def.faixa_escore[1]}</span>}
            {Object.entries(r.detalhe).map(([k, v]) => (
              <span key={k} className="text-muted-foreground">{ROTULO_DETALHE[k] ?? k}: {numeroBr(v)}</span>
            ))}
            <span className="ml-auto text-muted-foreground">{dataHora(r.answered_at)}</span>
          </li>
        );
      })}
    </ol>
  );
}

export default function Questionarios({ pacienteId, instrumentos, podeRegistrar }: { pacienteId: number; instrumentos: Instrumento[]; podeRegistrar: boolean }) {
  return (
    <>
      <p className="text-xs text-muted-foreground">O sistema soma as respostas; não interpreta o resultado. A decisão clínica é do profissional.</p>
      {podeRegistrar && <NovoQuestionario pacienteId={pacienteId} instrumentos={instrumentos} />}
    </>
  );
}
