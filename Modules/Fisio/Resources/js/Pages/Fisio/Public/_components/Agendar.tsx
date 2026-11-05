// Agendar pelo app (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/PacienteAgendar.dc.html):
// o quê → com quem → quando (com "me avise se abrir vaga") → confere e confirma. Também remarca uma
// consulta existente, pulando os dois primeiros passos. Os horários vêm do servidor (UC-FPA-01).
//
// Fora daqui, de propósito: "Para quem" (dependente, A16, pede consentimento do responsável legal) e
// o sinal por Pix (A17, depende do Financeiro).
import { useEffect, useState } from 'react';
import { CheckCircle2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { erroDe, pedir, proximosDias, quando, type AgendaPortal, type ConsultaPortal } from './agendaPortal';

type Passo = 1 | 2 | 3 | 4 | 5;

const opcao = (on: boolean) =>
  `flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-3 text-left ${on ? 'border-2 border-primary bg-primary/10' : 'border border-border bg-card'}`;

export function TelaAgendar({ token, remarcar, onVoltar, onPronto }: {
  token: string;
  remarcar?: ConsultaPortal;
  onVoltar: () => void;
  onPronto: () => void;
}) {
  const base = `/fisio/p/${token}/agenda`;
  const [dados, setDados] = useState<AgendaPortal | null>(null);
  const [passo, setPasso] = useState<Passo>(remarcar ? 3 : 1);
  const [tipo, setTipo] = useState<number | null>(null);
  const [prof, setProf] = useState<number | null>(null);
  const [dia, setDia] = useState<string | null>(null);
  const [hora, setHora] = useState<string | null>(null);
  const [horas, setHoras] = useState<string[] | null>(null);
  const [avisar, setAvisar] = useState(false);
  const [recado, setRecado] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const dias = proximosDias(14);

  useEffect(() => {
    void pedir('GET', base).then((r) => {
      if (r.status !== 200) return;
      const a = r.json as unknown as AgendaPortal;
      setDados(a);
      setProf(remarcar?.professional_id ?? a.profissionais[0]?.id ?? null);
      if (remarcar) setTipo(remarcar.type_id);
    });
  }, [base]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!tipo || !prof || !dia) return;
    setHoras(null);
    void pedir('GET', `${base}/horarios?tipo=${tipo}&profissional=${prof}&dia=${dia}`).then((r) => setHoras((r.json.horarios as string[]) ?? []));
  }, [base, tipo, prof, dia]);

  async function confirmar() {
    const inicio = `${dia} ${hora}`;
    const r = remarcar
      ? await pedir('POST', `${base}/${remarcar.id}/remarcar`, { inicio })
      : await pedir('POST', base, { tipo, profissional: prof, inicio, recado: recado || null, avisar_se_abrir: avisar });
    if (r.status === 200 || r.status === 201) setPasso(5);
    else setErro(erroDe(r.json) ?? 'Não deu certo. Tente de novo.');
  }

  if (!dados) return <p className="text-muted-foreground">Carregando…</p>;
  const tipoSel = dados.tipos.find((t) => t.id === tipo);
  const profSel = dados.profissionais.find((p) => p.id === prof);

  return (
    <div className="flex flex-col gap-4" data-contract="fisio-portal-agendar">
      <div className="flex items-center justify-between">
        <span className="text-sm text-muted-foreground">{passo === 5 ? 'pronto' : `passo ${Math.min(passo, 4)} de 4`}</span>
        {passo < 5 && <Button variant="ghost" onClick={onVoltar}>Voltar</Button>}
      </div>

      {passo === 1 && (
        <>
          <h1 className="text-2xl font-semibold">O que você quer marcar?</h1>
          {dados.tipos.length === 0 && <p className="text-muted-foreground">A clínica ainda não liberou consultas para marcar pelo app.</p>}
          {dados.tipos.map((t) => (
            <button key={t.id} type="button" aria-pressed={tipo === t.id} className={opcao(tipo === t.id)} onClick={() => setTipo(t.id)}>
              <span className="font-medium">{t.nome} · {t.duracao} min</span>
              {t.teleconsulta && <span className="text-sm text-muted-foreground">Por vídeo, aqui no app.</span>}
            </button>
          ))}
          <Button disabled={!tipo} onClick={() => setPasso(2)}>Continuar</Button>
        </>
      )}

      {passo === 2 && (
        <>
          <h1 className="text-2xl font-semibold">Com quem?</h1>
          {dados.profissionais.map((p) => (
            <button key={p.id} type="button" aria-pressed={prof === p.id} className={opcao(prof === p.id)} onClick={() => setProf(p.id)}>
              <span className="font-medium">{p.nome}</span>
              <span className="text-sm text-muted-foreground">{p.acompanha ? 'Fisioterapeuta · acompanha você' : 'Fisioterapeuta'}</span>
            </button>
          ))}
          <p className="text-xs text-muted-foreground">Quem já acompanha o tratamento aparece primeiro. Trocar de fisio não muda o programa de exercícios.</p>
          <Button disabled={!prof} onClick={() => setPasso(3)}>Continuar</Button>
        </>
      )}

      {passo === 3 && (
        <>
          <h1 className="text-2xl font-semibold">{remarcar ? 'Para quando remarcar?' : 'Quando?'}</h1>
          <div className="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Dia">
            {dias.map((d) => (
              <button key={d.iso} type="button" aria-pressed={dia === d.iso} onClick={() => { setDia(d.iso); setHora(null); }}
                className="flex min-w-14 flex-col items-center rounded-lg border border-border px-2 py-2 text-sm aria-pressed:border-primary aria-pressed:bg-primary aria-pressed:text-primary-foreground">
                <span className="text-xs">{d.semana}</span><span className="font-mono text-base">{d.dia}</span>
              </button>
            ))}
          </div>
          {dia && horas === null && <p className="text-muted-foreground">Procurando horários…</p>}
          {dia && horas?.length === 0 && <p className="text-muted-foreground">Nenhum horário livre neste dia.</p>}
          <div className="grid grid-cols-3 gap-2" role="group" aria-label="Horários livres">
            {(horas ?? []).map((h) => (
              <button key={h} type="button" aria-pressed={hora === h} onClick={() => setHora(h)}
                className="rounded-lg border border-border py-2 font-mono aria-pressed:border-foreground aria-pressed:bg-foreground aria-pressed:text-background">{h}</button>
            ))}
          </div>
          {!remarcar && (
            <button type="button" aria-pressed={avisar} onClick={() => setAvisar(!avisar)} className={opcao(avisar)}>
              <span className="font-medium">{avisar ? 'Vou te avisar se abrir antes' : 'Queria mais cedo? Me avise se abrir vaga'}</span>
              <span className="text-sm text-muted-foreground">Se alguém desmarcar antes, te aviso no WhatsApp. Quem confirmar primeiro fica com a vaga.</span>
            </button>
          )}
          <Button disabled={!hora} onClick={() => setPasso(4)}>Continuar</Button>
        </>
      )}

      {passo === 4 && (
        <>
          <h1 className="text-2xl font-semibold">Confere?</h1>
          <dl className="flex flex-col divide-y divide-border rounded-lg border border-border text-sm">
            <Linha k="O quê" v={tipoSel?.nome ?? remarcar?.tipo ?? ''} />
            <Linha k="Com quem" v={profSel?.nome ?? remarcar?.profissional ?? ''} />
            <Linha k="Quando" v={dia && hora ? quando(`${dia}T${hora}:00`) : ''} />
          </dl>
          {!remarcar && (
            <div className="flex flex-col gap-1">
              <Label htmlFor="recado">Recado para a fisio (opcional)</Label>
              <Textarea id="recado" maxLength={280} placeholder="Ex.: o joelho piorou depois da corrida" value={recado} onChange={(e) => setRecado(e.target.value)} />
            </div>
          )}
          <p className="text-xs text-muted-foreground">Lembrete no WhatsApp na véspera. Remarcar ou cancelar pelo app até {dados.prazo_horas} h antes.</p>
          {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
          <Button onClick={confirmar}>{remarcar ? 'Remarcar' : 'Agendar'}</Button>
        </>
      )}

      {passo === 5 && (
        <div className="flex flex-col items-start gap-3">
          <CheckCircle2 className="size-10 text-success" aria-hidden="true" />
          <h1 className="text-2xl font-semibold">{remarcar ? 'Remarcado!' : 'Agendado!'}</h1>
          <p className="text-muted-foreground">{tipoSel?.nome ?? remarcar?.tipo} com {profSel?.nome ?? remarcar?.profissional}, {dia && hora ? quando(`${dia}T${hora}:00`) : ''}.</p>
          <Button onClick={onPronto}>Ver minhas consultas</Button>
        </div>
      )}
    </div>
  );
}

function Linha({ k, v }: { k: string; v: string }) {
  return <div className="flex justify-between gap-2 px-3 py-2"><dt className="text-muted-foreground">{k}</dt><dd className="font-medium">{v}</dd></div>;
}
