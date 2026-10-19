// Tela "Hoje" do portal do paciente (US-FISIO-007b · UC-FPP-17, UC-FPP-18, UC-FPP-24, UC-FPP-25):
// exercícios do programa ativo com dose e cuidados, estado do jogo (nível, XP para o próximo,
// sequência; pausa com mensagem gentil, sem ranking) e o atalho "hoje estou em crise" quando a
// fisio escreveu a versão de crise. O paciente marca as vezes aqui; o "Como foi hoje" leva isso.
import { Check, ChevronDown, Flame, HeartHandshake, Minus, Plus, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';
import { dose, hojePorExtenso, progressoNivel, type ItemHoje, type Jogo } from './portal';

export function CartaoJogo({ jogo }: { jogo: Jogo }) {
  const pct = progressoNivel(jogo);
  return (
    <section aria-label="Sua constância" className="flex flex-col gap-3 rounded-lg bg-foreground p-4 text-background" data-contract="fisio-portal-jogo">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="flex size-11 items-center justify-center rounded-lg bg-primary font-mono text-lg font-semibold text-primary-foreground">{jogo.nivel}</span>
          <span className="flex flex-col leading-tight">
            <span className="text-xs uppercase tracking-wider opacity-70">Nível</span>
            <span className="font-semibold">{jogo.xp_para_proximo === null ? 'Nível máximo' : `faltam ${jogo.xp_para_proximo} XP`}</span>
          </span>
        </div>
        <span className="flex items-center gap-1.5 font-mono text-lg font-semibold" aria-label={`${jogo.sequencia} dias seguidos`}>
          <Flame className="size-5 text-primary" aria-hidden="true" />{jogo.sequencia}
        </span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-background/20" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={pct} aria-label="Progresso do nível">
        <div className="h-2 rounded-full bg-primary" style={{ width: `${pct}%` }} />
      </div>
      <p className="text-sm opacity-80">
        {jogo.em_pausa
          ? 'Sua sequência está protegida. Descanse e siga o que a sua fisio orientou.'
          : jogo.sequencia > 0
            ? `${jogo.sequencia} ${jogo.sequencia === 1 ? 'dia' : 'dias'} seguidos · ${jogo.dias_cumpridos} cumpridos no total`
            : jogo.dias_cumpridos > 0
              ? `Recomece hoje · ${jogo.dias_cumpridos} dias cumpridos no total`
              : 'Seu primeiro registro começa a sua sequência.'}
      </p>
    </section>
  );
}

function Contador({ item, vezes, onChange }: { item: ItemHoje; vezes: number; onChange: (n: number) => void }) {
  const max = item.times_per_day;
  if (max <= 1) {
    const feito = vezes >= 1;
    return (
      <Button type="button" variant={feito ? 'default' : 'outline'} className="h-11 min-w-28 rounded-lg" aria-pressed={feito} onClick={() => onChange(feito ? 0 : 1)}>
        {feito ? <><Check aria-hidden="true" />Feito</> : 'Marcar feito'}
      </Button>
    );
  }
  return (
    <div className="flex items-center gap-1" role="group" aria-label={`Vezes feitas de ${item.nome ?? 'exercício'}`}>
      <Button type="button" variant="outline" size="icon" className="size-11 rounded-lg" aria-label="Uma vez a menos" disabled={vezes <= 0} onClick={() => onChange(vezes - 1)}>
        <Minus aria-hidden="true" />
      </Button>
      <span className="w-14 text-center font-mono text-base" aria-live="polite">{vezes}/{max}</span>
      <Button type="button" variant="outline" size="icon" className="size-11 rounded-lg" aria-label="Uma vez a mais" disabled={vezes >= max} onClick={() => onChange(vezes + 1)}>
        <Plus aria-hidden="true" />
      </Button>
    </div>
  );
}

function CartaoExercicio({ item, vezes, onChange }: { item: ItemHoje; vezes: number; onChange: (n: number) => void }) {
  const [aberto, setAberto] = useState(false);
  const feito = vezes >= item.times_per_day;
  const detalhes = item.passos.length > 0 || item.cuidados.length > 0;
  return (
    <li className={cn('flex flex-col gap-3 rounded-lg border bg-card p-3', feito ? 'border-primary/40' : 'border-border')}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className={cn('font-semibold', feito && 'text-muted-foreground')}>{item.nome}</span>
          <span className="font-mono text-sm text-muted-foreground">
            {[dose(item), item.times_per_day > 1 ? `${item.times_per_day}x ao dia` : null].filter(Boolean).join(' · ')}
          </span>
        </div>
        <Contador item={item} vezes={vezes} onChange={onChange} />
      </div>
      {item.notes && <p className="rounded-lg bg-muted px-3 py-2 text-sm">Da sua fisio: “{item.notes}”</p>}
      {detalhes && (
        <>
          <button type="button" className="flex items-center gap-1 self-start text-sm text-primary" aria-expanded={aberto} onClick={() => setAberto(!aberto)}>
            Como fazer <ChevronDown className={cn('size-4 transition-transform', aberto && 'rotate-180')} aria-hidden="true" />
          </button>
          {aberto && (
            <div className="flex flex-col gap-2 text-sm">
              {item.passos.length > 0 && (
                <ol className="list-decimal space-y-1 pl-5">{item.passos.map((p, i) => <li key={i}>{p}</li>)}</ol>
              )}
              {item.cuidados.length > 0 && (
                <ul className="space-y-1 rounded-lg border border-border p-2">
                  {item.cuidados.map((c, i) => <li key={i} className="flex gap-2"><ShieldCheck className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />{c}</li>)}
                </ul>
              )}
            </div>
          )}
        </>
      )}
    </li>
  );
}

interface HojeProps {
  itens: ItemHoje[];
  jogo: Jogo;
  vezes: Record<number, number>;
  onVezes: (id: number, n: number) => void;
  registradoHoje: boolean;
  temCrise: boolean;
  onCheckin: () => void;
  onCrise: () => void;
}

export function TelaHoje({ itens, jogo, vezes, onVezes, registradoHoje, temCrise, onCheckin, onCrise }: HojeProps) {
  const feitos = itens.filter((i) => (vezes[i.id] ?? 0) >= 1).length;
  const pct = itens.length === 0 ? 0 : Math.round((feitos / itens.length) * 100);

  return (
    <div className="flex flex-1 flex-col gap-4" data-contract="fisio-portal-hoje">
      <header className="flex flex-col gap-0.5">
        <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{hojePorExtenso()}</span>
        <h1 className="text-3xl font-semibold leading-tight">Hoje</h1>
      </header>

      <CartaoJogo jogo={jogo} />

      {itens.length === 0 ? (
        <p className="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground">Sua fisioterapeuta ainda não montou o programa. Assim que ela montar, ele aparece aqui.</p>
      ) : (
        <section aria-label="Programa de hoje" className="flex flex-col gap-2">
          <div className="flex items-center justify-between text-sm">
            <span className="font-semibold">{feitos} de {itens.length} feitos</span>
            {registradoHoje && <Badge variant="secondary">Dia registrado</Badge>}
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-muted"><div className="h-1.5 rounded-full bg-primary" style={{ width: `${pct}%` }} /></div>
          <ol className="flex flex-col gap-2">
            {itens.map((i) => <CartaoExercicio key={i.id} item={i} vezes={vezes[i.id] ?? 0} onChange={(n) => onVezes(i.id, n)} />)}
          </ol>
        </section>
      )}

      <div className="sticky bottom-0 -mx-4 mt-auto flex flex-col gap-2 border-t border-border bg-background px-4 pt-3 pb-4">
        <Button type="button" size="lg" className="h-14 rounded-lg text-base" onClick={onCheckin} data-contract="fisio-portal-abrir-checkin">
          {registradoHoje ? 'Corrigir como foi hoje' : 'Como foi hoje?'}
        </Button>
        {temCrise && (
          <Button type="button" variant="ghost" className="h-11" onClick={onCrise}>
            <HeartHandshake aria-hidden="true" />Hoje estou em crise
          </Button>
        )}
      </div>
    </div>
  );
}

export function TelaCrise({ itens, sequencia, onVoltar, onComecar }: { itens: string[]; sequencia: number; onVoltar: () => void; onComecar: () => void }) {
  return (
    <div className="flex flex-1 flex-col gap-4" data-contract="fisio-portal-crise">
      <button type="button" className="self-start text-sm text-primary" onClick={onVoltar}>← Voltar para hoje</button>
      <h1 className="text-2xl font-semibold leading-tight">Dia de crise, tudo bem.</h1>
      <p className="text-muted-foreground">Hoje vale fazer a versão curta que a sua fisio deixou para você.</p>
      <ol className="flex flex-col gap-2">
        {itens.map((t, i) => (
          <li key={i} className="flex items-center gap-3 rounded-lg border border-border bg-card p-3">
            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted font-mono text-sm text-primary">{i + 1}</span>
            <span className="font-medium">{t}</span>
          </li>
        ))}
      </ol>
      <div className="flex items-center gap-3 rounded-lg bg-muted p-3 text-sm">
        <ShieldCheck className="size-5 shrink-0 text-primary" aria-hidden="true" />
        <span>Conta como dia cumprido.{sequencia > 0 ? ` Sua sequência de ${sequencia} ${sequencia === 1 ? 'dia' : 'dias'} fica protegida.` : ''}</span>
      </div>
      <Button type="button" size="lg" className="mt-auto h-14 rounded-lg text-base" onClick={onComecar}>Fiz a versão curta · registrar</Button>
    </div>
  );
}
