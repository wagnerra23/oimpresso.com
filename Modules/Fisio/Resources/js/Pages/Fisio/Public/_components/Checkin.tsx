// "Como foi hoje?" do portal do paciente (US-FISIO-007b · UC-FPP-08..13, UC-FPP-19).
// Caminho feliz em 3 toques (etapa 02 §3.5): Fiz tudo → dor → "Não, tudo igual" → enviar.
// Esforço e observação ficam recolhidos. Depois do envio: XP e sequência; em dia de pausa (dor ≥
// limiar ou sinal de alerta) NADA de jogo — só a instrução que a fisio escreveu.
import { router } from '@inertiajs/react';
import { Check, ChevronDown, ShieldCheck } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/Lib/utils';
import { CartaoJogo } from './Hoje';
import { ancoraDor, enviar, ESFORCOS, SINAIS, type Esforco, type ItemHoje, type Relato, type RespostaCheckin } from './portal';

type Feito = 'tudo' | 'parte' | 'nada';

interface CheckinProps {
  token: string;
  itens: ItemHoje[];
  vezes: Record<number, number>;
  onVezes: (id: number, n: number) => void;
  relato: Relato | null;
  sinais: string[];
  crise: boolean;
  onVoltar: () => void;
}

const NOTA_MAX = 280;

function Opcao({ ativo, onClick, children, className }: { ativo: boolean; onClick: () => void; children: ReactNode; className?: string }) {
  return (
    <button
      type="button"
      aria-pressed={ativo}
      onClick={onClick}
      className={cn(
        'min-h-12 rounded-lg border px-3 text-base font-semibold outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50',
        ativo ? 'border-foreground bg-foreground text-background' : 'border-border bg-card text-foreground',
        className,
      )}
    >
      {children}
    </button>
  );
}

export function TelaCheckin({ token, itens, vezes, onVezes, relato, sinais, crise, onVoltar }: CheckinProps) {
  const algumMarcado = itens.some((i) => (vezes[i.id] ?? 0) >= 1);
  const todosMarcados = itens.length > 0 && itens.every((i) => (vezes[i.id] ?? 0) >= i.times_per_day);
  const inicial: Feito | null = crise ? 'parte' : todosMarcados ? 'tudo' : algumMarcado ? 'parte' : relato ? 'nada' : null;

  const [feito, setFeito] = useState<Feito | null>(inicial);
  const [dor, setDor] = useState<number | null>(relato?.dor ?? null);
  const [mudou, setMudou] = useState<boolean | null>(relato ? false : null);
  const [flags, setFlags] = useState<string[]>([]);
  const [esforco, setEsforco] = useState<Esforco>(relato?.esforco ?? 'adequado');
  const [nota, setNota] = useState(relato?.observacao ?? '');
  const [extras, setExtras] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [resposta, setResposta] = useState<RespostaCheckin | null>(null);

  const escolherFeito = (f: Feito) => {
    setFeito(f);
    if (f === 'tudo') itens.forEach((i) => onVezes(i.id, i.times_per_day));
    if (f === 'nada') itens.forEach((i) => onVezes(i.id, 0));
  };

  const pronto = (feito !== null || itens.length === 0) && mudou !== null && (mudou === false || flags.length > 0);

  const mandar = async () => {
    if (!pronto || enviando) return;
    setEnviando(true);
    setErro(null);
    const marcados = itens.filter((i) => (vezes[i.id] ?? 0) >= 1);
    const r = await enviar('POST', `/fisio/p/${token}/checkin`, {
      done_item_ids: marcados.map((i) => i.id),
      vezes: Object.fromEntries(marcados.map((i) => [i.id, Math.min(vezes[i.id] ?? 1, i.times_per_day)])),
      pain: dor,
      effort: esforco,
      note: nota.trim() === '' ? null : nota.trim().slice(0, NOTA_MAX),
      red_flags: mudou ? flags : [],
      crisis: crise,
    });
    setEnviando(false);
    if (r.status === 200 || r.status === 201) return setResposta(r.json as unknown as RespostaCheckin);
    if (r.status === 403 || r.status === 404) return router.reload();
    setErro('Não deu para enviar. Confira a internet e tente de novo — nada se perde, você pode reenviar hoje.');
  };

  if (resposta) return <Enviado resposta={resposta} />;

  return (
    <form className="flex flex-1 flex-col gap-6" data-contract="fisio-portal-checkin" onSubmit={(e) => { e.preventDefault(); void mandar(); }}>
      <header className="flex flex-col gap-1">
        <button type="button" className="self-start text-sm text-primary" onClick={onVoltar}>← Voltar para hoje</button>
        <h1 className="text-3xl font-semibold leading-tight">Como foi hoje?</h1>
        <p className="text-muted-foreground">Leva poucos segundos e ajuda sua fisio a acompanhar você.</p>
      </header>

      {itens.length > 0 && (
        <fieldset className="flex flex-col gap-2">
          <legend className="mb-2 font-semibold">{crise ? 'Versão de crise' : 'Exercícios'}</legend>
          {crise ? (
            <p className="text-sm text-muted-foreground">Dia de crise registrado. Marque abaixo se fez algum exercício do programa normal.</p>
          ) : (
            <div className="grid grid-cols-3 gap-2">
              <Opcao ativo={feito === 'tudo'} onClick={() => escolherFeito('tudo')}>Fiz tudo</Opcao>
              <Opcao ativo={feito === 'parte'} onClick={() => escolherFeito('parte')}>Fiz parte</Opcao>
              <Opcao ativo={feito === 'nada'} onClick={() => escolherFeito('nada')} className="text-sm">Hoje não consegui</Opcao>
            </div>
          )}
          {(feito === 'parte' || crise) && (
            <ul className="flex flex-col gap-1 pt-1">
              {itens.map((i) => {
                const n = vezes[i.id] ?? 0;
                return (
                  <li key={i.id} className="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2">
                    <Checkbox id={`ck-${i.id}`} checked={n >= 1} onCheckedChange={(v) => onVezes(i.id, v === true ? Math.max(1, n) : 0)} />
                    <Label htmlFor={`ck-${i.id}`} className="flex-1 font-normal">{i.nome}</Label>
                    {i.times_per_day > 1 && n >= 1 && (
                      <div className="flex items-center gap-1" role="group" aria-label={`Vezes de ${i.nome ?? 'exercício'}`}>
                        <Button type="button" variant="outline" size="icon-sm" aria-label="Uma vez a menos" disabled={n <= 1} onClick={() => onVezes(i.id, n - 1)}>−</Button>
                        <span className="w-10 text-center font-mono text-sm" aria-live="polite">{Math.min(n, i.times_per_day)}x</span>
                        <Button type="button" variant="outline" size="icon-sm" aria-label="Uma vez a mais" disabled={n >= i.times_per_day} onClick={() => onVezes(i.id, n + 1)}>+</Button>
                      </div>
                    )}
                  </li>
                );
              })}
            </ul>
          )}
        </fieldset>
      )}

      <fieldset className="flex flex-col gap-2">
        <div className="flex items-baseline justify-between">
          <legend className="font-semibold">Dor agora</legend>
          <span className="font-mono text-xl" aria-live="polite">{dor === null ? '—' : `${dor}/10 · ${ancoraDor(dor)}`}</span>
        </div>
        <div className="grid grid-cols-6 gap-1.5">
          {Array.from({ length: 11 }, (_, v) => (
            <Opcao key={v} ativo={dor === v} onClick={() => setDor(v)} className="px-0 font-mono">
              <span aria-label={`Dor ${v}, ${ancoraDor(v)}`}>{v}</span>
            </Opcao>
          ))}
        </div>
        <div className="flex justify-between text-xs text-muted-foreground"><span>0 sem dor</span><span>10 a pior possível</span></div>
      </fieldset>

      <fieldset className="flex flex-col gap-2">
        <legend className="mb-2 font-semibold">Notou algo diferente hoje?</legend>
        <div className="grid grid-cols-2 gap-2">
          <Opcao ativo={mudou === false} onClick={() => { setMudou(false); setFlags([]); }}>Não, tudo igual</Opcao>
          <Opcao ativo={mudou === true} onClick={() => setMudou(true)}>Sim</Opcao>
        </div>
        {mudou && (
          <div className="flex flex-col gap-1 pt-1">
            <p className="text-sm text-muted-foreground">Marque o que notou. Vamos avisar a sua fisio.</p>
            {sinais.map((s) => (
              <div key={s} className="flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2">
                <Checkbox id={`sinal-${s}`} checked={flags.includes(s)} onCheckedChange={(v) => setFlags(v === true ? [...flags, s] : flags.filter((f) => f !== s))} />
                <Label htmlFor={`sinal-${s}`} className="font-normal">{SINAIS[s] ?? s}</Label>
              </div>
            ))}
          </div>
        )}
      </fieldset>

      <div className="flex flex-col gap-3">
        <button type="button" className="flex items-center gap-1 self-start text-sm text-primary" aria-expanded={extras} onClick={() => setExtras(!extras)}>
          Esforço e recado para a fisio (opcional) <ChevronDown className={cn('size-4 transition-transform', extras && 'rotate-180')} aria-hidden="true" />
        </button>
        {extras && (
          <>
            <div className="grid grid-cols-3 gap-2" role="group" aria-label="Esforço">
              {ESFORCOS.map((e) => <Opcao key={e.valor} ativo={esforco === e.valor} onClick={() => setEsforco(e.valor)}>{e.rotulo}</Opcao>)}
            </div>
            <Label htmlFor="ck-nota" className="sr-only">Recado para a fisio</Label>
            <Textarea id="ck-nota" rows={3} maxLength={NOTA_MAX} placeholder="Quer contar algo para a sua fisio?" value={nota} onChange={(e) => setNota(e.target.value)} />
            <span className="self-end text-xs text-muted-foreground">{nota.length}/{NOTA_MAX}</span>
          </>
        )}
      </div>

      {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
      <Button type="submit" size="lg" className="mt-auto h-14 rounded-lg text-base" disabled={!pronto || enviando}>
        {relato ? 'Enviar correção' : 'Enviar para a minha fisio'}
      </Button>
    </form>
  );
}

function Enviado({ resposta }: { resposta: RespostaCheckin }) {
  if (resposta.modo === 'pausa') {
    return (
      <div className="flex flex-1 flex-col gap-4" data-contract="fisio-portal-pausa" role="status">
        <h1 className="text-2xl font-semibold leading-tight">Seu registro foi enviado</h1>
        <p className="flex items-center gap-2 text-muted-foreground"><ShieldCheck className="size-5 text-primary" aria-hidden="true" />Sua sequência está protegida.</p>
        <InstrucaoDaFisio instrucao={resposta.instrucao ?? null} />
        <Button type="button" variant="outline" size="lg" className="mt-auto h-12 rounded-lg" onClick={() => router.reload()}>Voltar ao início</Button>
      </div>
    );
  }
  return (
    <div className="flex flex-1 flex-col items-center gap-4 text-center" data-contract="fisio-portal-enviado" role="status">
      <span className="mt-6 flex size-20 items-center justify-center rounded-full bg-primary text-primary-foreground"><Check className="size-10" aria-hidden="true" /></span>
      <h1 className="text-2xl font-semibold leading-tight">Registro enviado</h1>
      <p className="max-w-xs text-muted-foreground">
        {resposta.xp_ganho ? `+${resposta.xp_ganho} XP. ` : ''}Sua fisio recebeu o registro e acompanha a sua evolução.
      </p>
      {resposta.jogo && <div className="w-full text-left"><CartaoJogo jogo={resposta.jogo} /></div>}
      <Button type="button" size="lg" className="mt-auto h-12 w-full rounded-lg" onClick={() => router.reload()}>Voltar ao início</Button>
    </div>
  );
}

/** Texto ESTÁTICO escrito pelo profissional no programa (ADR 0437 §5): nada calculado aqui. */
export function InstrucaoDaFisio({ instrucao }: { instrucao: string | null }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Orientação da sua fisio</p>
      <p className="whitespace-pre-line">{instrucao ?? 'Sua fisio vai ver o seu registro. Em caso de dúvida, fale com a sua clínica.'}</p>
    </div>
  );
}
