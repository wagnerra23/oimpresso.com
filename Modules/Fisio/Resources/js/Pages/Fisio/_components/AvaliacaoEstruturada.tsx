// Avaliação em seções (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/Avaliacao.dc.html).
// Montada no lugar do formulário simples quando o profissional escolhe "Avaliação" na ficha.
// Grava pelo mesmo POST /fisio/pacientes/{id}/registros (kind=avaliacao), com os 6 campos da
// Res. COFFITO 414/2012 compostos por montarCorpoAvaliacao (UC-FAV-01). Assinada, não muda:
// correção só por adendo. Sinal de alerta marcado só AVISA; a decisão é do profissional (UC-FAV-02).
import { router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { formVazio, montarCorpoAvaliacao, sinaisMarcados, SINAIS_ALERTA, type FormAvaliacao } from './avaliacao';

const MAPA_ERRO: Record<string, string> = {
  'body.queixa_principal': 'Queixa principal', 'body.historia_doenca': 'História da doença atual', 'body.exame_fisico': 'Exame físico-funcional (ao menos um teste)',
  'body.diagnostico_cinesiologico': 'Diagnóstico fisioterapêutico', 'body.objetivos': 'Objetivo principal', 'body.plano_tratamento': 'Plano de tratamento',
};

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <fieldset className="flex flex-col gap-3 rounded-lg border border-border p-4">
      <legend className="px-1 text-sm font-semibold">{titulo}</legend>
      {children}
    </fieldset>
  );
}

export default function AvaliacaoEstruturada({ pacienteId, onPronto, onEvolucao }: { pacienteId: number; onPronto: () => void; onEvolucao: () => void }) {
  const [f, setF] = useState<FormAvaliacao>(formVazio);
  const [crefito, setCrefito] = useState('');
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);
  const set = <K extends keyof FormAvaliacao>(k: K, v: FormAvaliacao[K]) => setF((a) => ({ ...a, [k]: v }));

  const campo = (k: keyof FormAvaliacao, rotulo: string, linhas = 0, extra?: { placeholder?: string; type?: string }) => (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={`av-${k}`}>{rotulo}</Label>
      {linhas ? (
        <Textarea id={`av-${k}`} rows={linhas} value={f[k] as string} onChange={(e) => set(k, e.target.value as never)} />
      ) : (
        <Input id={`av-${k}`} {...extra} value={f[k] as string} onChange={(e) => set(k, e.target.value as never)} />
      )}
    </div>
  );

  const assinar = (e: FormEvent) => {
    e.preventDefault();
    router.post(`/fisio/pacientes/${pacienteId}/registros`, { kind: 'avaliacao', crefito, body: montarCorpoAvaliacao(f) }, {
      preserveScroll: true,
      onStart: () => setEnviando(true),
      onFinish: () => setEnviando(false),
      onError: (er) => setErros(er as Record<string, string>),
      onSuccess: () => onPronto(),
    });
  };
  const faltando = Object.keys(erros).filter((k) => MAPA_ERRO[k]).map((k) => MAPA_ERRO[k]);
  const marcados = sinaisMarcados(f);

  return (
    <form onSubmit={assinar} className="flex flex-col gap-4" data-contract="fisio-avaliacao">
      <div className="flex gap-2" role="group" aria-label="Tipo de registro">
        <Button type="button" size="sm" variant="outline" onClick={onEvolucao}>Evolução</Button>
        <Button type="button" size="sm">Avaliação</Button>
      </div>
      <Secao titulo="1 · Queixa e história">
        {campo('queixa', 'Queixa principal', 2)}
        {campo('historia', 'História da doença atual', 3)}
        <div className="grid gap-3 sm:grid-cols-2">
          {campo('dorAgora', 'Dor agora (0–10)', 0, { type: 'number' })}
          {campo('piorDor', 'Pior dor na semana (0–10)', 0, { type: 'number' })}
        </div>
        {campo('pioraMelhora', 'O que piora / o que melhora', 2)}
        {campo('tratamentos', 'Tratamentos anteriores', 2)}
        {campo('medicamentos', 'Medicamentos em uso', 2)}
      </Secao>
      <Secao titulo="2 · Hábitos e antecedentes">
        <div className="grid gap-3 sm:grid-cols-3">
          {campo('atividade', 'Atividade física')}
          {campo('trabalho', 'Trabalho')}
          {campo('sono', 'Sono')}
        </div>
        {campo('antecedentes', 'Antecedentes pessoais e familiares', 2)}
      </Secao>
      <Secao titulo="3 · Exame físico-funcional">
        {f.exame.map((l, i) => (
          <div key={i} className="grid items-end gap-2 sm:grid-cols-[2fr_1fr_2fr_auto]">
            <Input aria-label={`Teste ${i + 1}`} placeholder="Teste ou medida" value={l.teste} onChange={(e) => set('exame', f.exame.map((x, j) => (j === i ? { ...x, teste: e.target.value } : x)))} />
            <Input aria-label={`Resultado ${i + 1}`} placeholder="Resultado" value={l.resultado} onChange={(e) => set('exame', f.exame.map((x, j) => (j === i ? { ...x, resultado: e.target.value } : x)))} />
            <Input aria-label={`Observação ${i + 1}`} placeholder="Observação" value={l.obs} onChange={(e) => set('exame', f.exame.map((x, j) => (j === i ? { ...x, obs: e.target.value } : x)))} />
            <Button type="button" variant="ghost" size="icon" aria-label={`Remover teste ${i + 1}`} disabled={f.exame.length === 1} onClick={() => set('exame', f.exame.filter((_, j) => j !== i))}>
              <Trash2 className="size-4" aria-hidden="true" />
            </Button>
          </div>
        ))}
        <div><Button type="button" variant="outline" size="sm" onClick={() => set('exame', [...f.exame, { teste: '', resultado: '', obs: '' }])}><Plus className="size-4" aria-hidden="true" /> Adicionar teste ou medida</Button></div>
        <div className="flex flex-col gap-1.5" role="group" aria-labelledby="av-sinais">
          <span id="av-sinais" className="text-sm font-medium">Triagem de sinais de alerta</span>
          {SINAIS_ALERTA.map((s, i) => (
            <label key={s} className="flex min-h-11 items-center gap-2 text-sm">
              <Checkbox checked={f.sinais[i]} onCheckedChange={(c) => set('sinais', f.sinais.map((v, j) => (j === i ? c === true : v)))} />
              {s}
            </label>
          ))}
          <p role="status" className={`text-sm ${marcados.length ? 'text-destructive' : 'text-muted-foreground'}`}>
            {marcados.length ? `${marcados.length} sinal(is) de alerta marcado(s). Registre a conduta no plano; a decisão é sua.` : 'Nenhum sinal de alerta marcado.'}
          </p>
        </div>
      </Secao>
      <Secao titulo="4 · Diagnóstico, prognóstico e plano">
        {campo('diagnostico', 'Diagnóstico fisioterapêutico', 2)}
        {campo('prognostico', 'Prognóstico', 2)}
        {campo('objetivo', 'Objetivo principal', 2)}
        <div className="grid gap-3 sm:grid-cols-2">
          {campo('sessoes', 'Nº provável de sessões', 0, { type: 'number' })}
          {campo('frequencia', 'Frequência', 0, { placeholder: 'ex.: 2× por semana' })}
        </div>
        {campo('plano', 'Plano de tratamento', 3)}
      </Secao>
      {faltando.length > 0 && <p className="text-sm text-destructive">Falta para assinar (Res. COFFITO 414/2012): {faltando.join(', ')}.</p>}
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="av-crefito">CREFITO de quem assina</Label>
        <Input id="av-crefito" className="max-w-xs" placeholder="CREFITO-10/000000-F" value={crefito} onChange={(e) => setCrefito(e.target.value)} />
        {erros.crefito && <p className="text-xs text-destructive">{erros.crefito}</p>}
      </div>
      <p className="text-xs text-muted-foreground">Depois de assinada, a avaliação não muda. Correção só por adendo, que fica no histórico (Res. COFFITO 414/2012).</p>
      <div><Button type="submit" disabled={enviando}>Assinar avaliação</Button></div>
    </form>
  );
}
