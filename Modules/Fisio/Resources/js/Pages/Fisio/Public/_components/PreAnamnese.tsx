// Pré-anamnese no portal (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/PacientePreAnamnese.dc.html).
// Três passos (onde dói · como está a dor · sinais de alerta) e a confirmação. O servidor guarda
// uma resposta por paciente e avisa o profissional se algum sinal for marcado.
//
// RASCUNHO: os nomes das regiões e as faixas de tempo vêm do protótipo e aguardam a revisão da
// responsável técnica. Os sinais usam o mesmo texto do check-in (`SINAIS` em portal.ts).
import { router } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/Lib/utils';
import { enviar, SINAIS } from './portal';

export interface OpcoesPreAnamnese { regioes: string[]; desde: string[]; sinais: string[] }

const REGIAO: Record<string, string> = {
  pescoco: 'Pescoço', ombro_direito: 'Ombro direito', ombro_esquerdo: 'Ombro esquerdo', costas_meio: 'Costas (meio)',
  lombar: 'Lombar', quadril: 'Quadril', joelho_direito: 'Joelho direito', joelho_esquerdo: 'Joelho esquerdo',
  tornozelo_pe: 'Tornozelo / pé', corpo_todo: 'O corpo todo',
};
const DESDE: Record<string, string> = {
  menos_1_mes: 'menos de 1 mês', '1_a_3_meses': '1 a 3 meses', '3_a_12_meses': '3 a 12 meses', mais_1_ano: 'mais de 1 ano',
};

function Opcao({ on, onClick, children, rotulo }: { on: boolean; onClick: () => void; children: ReactNode; rotulo?: string }) {
  return (
    <button type="button" aria-pressed={on} aria-label={rotulo} onClick={onClick}
      className={cn('min-h-12 rounded-lg border px-3 py-2 text-left', on ? 'border-2 border-primary bg-primary/10 font-semibold' : 'border-border bg-card')}>
      {children}
    </button>
  );
}

export function TelaPreAnamnese({ token, clinica, opcoes, onVoltar }: { token: string; clinica: string | null; opcoes: OpcoesPreAnamnese; onVoltar: () => void }) {
  const [passo, setPasso] = useState(1);
  const [regioes, setRegioes] = useState<string[]>([]);
  const [desde, setDesde] = useState<string | null>(null);
  const [dor, setDor] = useState<number | null>(null);
  const [texto, setTexto] = useState('');
  const [sinais, setSinais] = useState<string[]>([]);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const nome = clinica ?? 'a clínica';
  const alterna = (lista: string[], v: string) => (lista.includes(v) ? lista.filter((x) => x !== v) : [...lista, v]);

  const mandar = async () => {
    setEnviando(true);
    setErro(null);
    const r = await enviar('POST', `/fisio/p/${token}/pre-anamnese`, { regioes, desde, dor, piora_melhora: texto.trim() || null, sinais });
    setEnviando(false);
    if (r.status === 201 || r.status === 409) return setPasso(4);
    setErro('Não deu para enviar agora. Confira a internet e tente de novo.');
  };

  return (
    <div className="flex flex-1 flex-col gap-4" data-contract="fisio-portal-pre-anamnese">
      <div className="flex items-center gap-2">
        {passo < 4 && <Button type="button" variant="ghost" size="icon" aria-label="Voltar" onClick={() => (passo === 1 ? onVoltar() : setPasso(passo - 1))}><ArrowLeft aria-hidden="true" /></Button>}
        <span className="text-sm text-muted-foreground">Antes da sua primeira consulta · {passo < 4 ? `passo ${passo} de 3` : 'concluído'}</span>
      </div>

      {passo === 1 && (
        <>
          <h1 className="text-2xl font-semibold leading-tight">Onde dói?</h1>
          <p className="text-muted-foreground">Toque em todas as regiões que incomodam.</p>
          <div className="grid grid-cols-2 gap-2">
            {opcoes.regioes.map((r) => <Opcao key={r} on={regioes.includes(r)} onClick={() => setRegioes(alterna(regioes, r))}>{REGIAO[r] ?? r}</Opcao>)}
          </div>
          <Button type="button" size="lg" className="mt-auto h-14 text-base" disabled={regioes.length === 0} onClick={() => setPasso(2)}>Continuar</Button>
        </>
      )}

      {passo === 2 && (
        <>
          <h1 className="text-2xl font-semibold leading-tight">Como está a dor?</h1>
          <span className="font-medium">Há quanto tempo?</span>
          <div className="grid grid-cols-2 gap-2">
            {opcoes.desde.map((d) => <Opcao key={d} on={desde === d} onClick={() => setDesde(d)}>{DESDE[d] ?? d}</Opcao>)}
          </div>
          <span className="font-medium">Dor agora {dor !== null && <span className="font-mono">{dor}/10</span>}</span>
          <div className="grid grid-cols-6 gap-1.5">
            {Array.from({ length: 11 }, (_, v) => <Opcao key={v} on={dor === v} onClick={() => setDor(v)} rotulo={`Dor ${v}`}><span className="block text-center font-mono">{v}</span></Opcao>)}
          </div>
          <Label htmlFor="pa-txt">O que piora e o que melhora?</Label>
          <Textarea id="pa-txt" rows={3} maxLength={280} value={texto} onChange={(e) => setTexto(e.target.value)} />
          <Button type="button" size="lg" className="mt-auto h-14 text-base" disabled={desde === null || dor === null} onClick={() => setPasso(3)}>Continuar</Button>
        </>
      )}

      {passo === 3 && (
        <>
          <h1 className="text-2xl font-semibold leading-tight">Alguma destas coisas?</h1>
          <p className="text-muted-foreground">Isso ajuda {nome} a saber se você precisa de outro cuidado antes.</p>
          {opcoes.sinais.map((s) => (
            <div key={s} className="flex items-center gap-3 rounded-lg border border-border bg-card p-3">
              <span className="flex-1">{SINAIS[s] ?? s}</span>
              <Button type="button" variant={sinais.includes(s) ? 'destructive' : 'outline'} aria-pressed={sinais.includes(s)} onClick={() => setSinais(alterna(sinais, s))}>
                {sinais.includes(s) ? 'Sim' : 'Não'}
              </Button>
            </div>
          ))}
          {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
          <Button type="button" size="lg" className="mt-auto h-14 text-base" disabled={enviando} onClick={() => void mandar()}>Enviar para {nome}</Button>
        </>
      )}

      {passo === 4 && (
        <>
          <span className="flex size-12 items-center justify-center rounded-full bg-success-soft text-success-fg"><Check aria-hidden="true" /></span>
          <h1 className="text-2xl font-semibold leading-tight">Pronto, obrigado!</h1>
          <p className="text-muted-foreground">{nome} vai ler antes da sua consulta.</p>
          <Button type="button" size="lg" className="mt-auto h-14 text-base" onClick={() => router.reload()}>Ver meu programa</Button>
        </>
      )}
    </div>
  );
}
