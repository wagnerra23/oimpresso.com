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
  tipo: 'escala' | 'sim_nao' | 'atividades' | 'likert' | 'numerica' | 'acr2016';
  // `faixa` e `bloco` só nos critérios ACR 2016 (itens com faixas diferentes; etapa 07, P-03).
  itens: { numero: number; texto: string | null; faixa?: [number, number]; bloco?: string; regiao?: string }[];
  faixa_item: [number, number];
  max_atividades?: number;
  pontuacao: string;
  faixa_escore: [number, number];
  direcao: string | null;
  inteiro?: boolean;
  versao?: string;
  texto_pendente: boolean;
  referencias: Referencia[];
}
export interface RespostaQuestionario {
  id: number;
  instrumento: string;
  versao?: string | null;
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
const ROTULO_DETALHE: Record<string, string> = {
  dor: 'Dor',
  incapacidade: 'Incapacidade',
  funcao: 'Função',
  impacto: 'Impacto geral',
  sintomas: 'Sintomas',
  wpi: 'WPI',
  sss: 'SSS',
  regioes_com_dor: 'Regiões com dor',
};
// Critérios ACR 2016 (0/1): mostrados como sim/não, e o conjunto como INFORMAÇÃO, nunca diagnóstico (ADR 0437 §5).
const CRITERIOS_ACR: Record<string, string> = {
  criterio_wpi_sss: 'WPI e SSS nos limiares',
  dor_generalizada: 'Dor em 4 de 5 regiões',
  sintomas_3_meses: 'Sintomas há 3 meses ou mais',
};
const CRITERIO_GERAL = 'criterios_acr2016_atendidos';
const REGIOES_ACR: Record<string, string> = {
  superior_esquerda: 'Região superior esquerda',
  superior_direita: 'Região superior direita',
  inferior_esquerda: 'Região inferior esquerda',
  inferior_direita: 'Região inferior direita',
  axial: 'Região axial',
};

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
      const itens = Object.fromEntries(atual.itens.map((it) => [String(it.numero), d.itens[String(it.numero)] ?? (atual.tipo === 'sim_nao' || (atual.tipo === 'acr2016' && it.faixa?.[1] === 1) ? '0' : '')]));
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
              <Input id="q-valor" type="number" inputMode={atual.inteiro ? 'numeric' : 'decimal'} min={min} max={max} step={atual.inteiro ? '1' : '0.1'} className="max-w-32" value={form.data.valor} onChange={(e) => form.setData('valor', e.target.value)} />
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

          {atual.tipo === 'acr2016' && <ItensAcr2016 instrumento={atual} itens={form.data.itens} setItem={setItem} erros={erros} />}

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
          <p className="text-xs text-muted-foreground">Pontuação: {atual.pontuacao}{atual.direcao ? ` · ${atual.direcao}` : ''}.</p>
          {atual.versao && <p className="text-xs text-muted-foreground">Versão registrada com a resposta: {atual.versao}.</p>}
          <Referencias instrumento={atual} />
          <div><Button type="submit" disabled={form.processing}>Registrar questionário</Button></div>
        </>
      )}
    </form>
  );
}

function ItensAcr2016({ instrumento, itens, setItem, erros }: {
  instrumento: Instrumento;
  itens: Record<string, string>;
  setItem: (n: number, v: string) => void;
  erros: Record<string, string>;
}) {
  const wpi = instrumento.itens.filter((it) => it.bloco === 'wpi');
  const regioes = [...new Set(wpi.map((it) => it.regiao ?? ''))];
  const gravidade = instrumento.itens.filter((it) => it.bloco === 'sss_gravidade');
  const simNao = instrumento.itens.filter((it) => it.bloco === 'sss_ultimos_6_meses' || it.bloco === 'duracao');
  const marca = (n: number) => (
    <label key={n} className="flex items-center gap-2 text-sm">
      <Checkbox checked={itens[String(n)] === '1'} onCheckedChange={(c) => setItem(n, c === true ? '1' : '0')} />
      Item {n}
    </label>
  );

  return (
    <div className="flex flex-col gap-3" data-contract="fisio-questionario-acr2016">
      <fieldset className="flex flex-col gap-2">
        <legend className="mb-1 text-sm">Áreas com dor (WPI) — marque as respondidas com &quot;sim&quot;</legend>
        {regioes.map((reg) => (
          <div key={reg} className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">{REGIOES_ACR[reg] ?? reg}</span>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">{wpi.filter((it) => it.regiao === reg).map((it) => marca(it.numero))}</div>
          </div>
        ))}
      </fieldset>
      <fieldset className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        <legend className="mb-2 text-sm">Gravidade dos sintomas (0 a 3)</legend>
        {gravidade.map((it) => (
          <div key={it.numero} className="flex flex-col gap-1">
            <Label htmlFor={`q-item-${it.numero}`}>Item {it.numero}</Label>
            <Input id={`q-item-${it.numero}`} type="number" inputMode="numeric" min={0} max={3} step="1" value={itens[String(it.numero)] ?? ''} onChange={(e) => setItem(it.numero, e.target.value)} />
            {erros[`respostas.itens.${it.numero}`] && <p className="text-xs text-destructive">{erros[`respostas.itens.${it.numero}`]}</p>}
          </div>
        ))}
      </fieldset>
      <fieldset className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <legend className="mb-2 text-sm">Últimos 6 meses e duração — marque os &quot;sim&quot;</legend>
        {simNao.map((it) => marca(it.numero))}
      </fieldset>
    </div>
  );
}

function CriteriosAcr({ detalhe }: { detalhe: Record<string, number> }) {
  const simNao = (v: number | undefined) => (v === 1 ? 'sim' : 'não');
  return (
    <span className="w-full text-muted-foreground" data-contract="fisio-acr2016-criterios">
      Critérios ACR 2016: {simNao(detalhe[CRITERIO_GERAL])} (
      {Object.entries(CRITERIOS_ACR).map(([k, rotulo], i) => `${i ? ' · ' : ''}${rotulo}: ${simNao(detalhe[k])}`).join('')}
      ). Informação para o profissional, não é diagnóstico.
    </span>
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
            {Object.entries(r.detalhe)
              .filter(([k]) => !(k in CRITERIOS_ACR) && k !== CRITERIO_GERAL)
              .map(([k, v]) => (
                <span key={k} className="text-muted-foreground">{ROTULO_DETALHE[k] ?? k}: {numeroBr(v)}</span>
              ))}
            {CRITERIO_GERAL in r.detalhe && <CriteriosAcr detalhe={r.detalhe} />}
            <span className="ml-auto text-muted-foreground">{r.versao ? `${r.versao} · ` : ''}{dataHora(r.answered_at)}</span>
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
