// "Minhas consultas" do paciente (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/PacienteConsultas.dc.html).
//
// Próximas (com Confirmo, Cheguei, Remarcar, Cancelar dentro do prazo), lista de espera, histórico e a
// nota privada da última sessão. A aba "Pacote" do protótipo e o recibo entram com o Financeiro (lote 4).
import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { Segmented } from '@/Components/ui/segmented';
import { MOTIVO_SEM_AGENDA, erroDe, pedir, podeChegar, quando, type AgendaPortal, type ConsultaPortal } from './agendaPortal';

const ROTULO: Record<ConsultaPortal['status'], [string, 'success' | 'warning' | 'info' | 'danger' | 'neutral']> = {
  agendado: ['Aguardando confirmação', 'warning'],
  confirmado: ['Confirmada', 'success'],
  chegou: ['Você chegou', 'info'],
  em_atendimento: ['Em atendimento', 'info'],
  realizado: ['Compareceu', 'neutral'],
  faltou: ['Faltou sem avisar', 'danger'],
  cancelado: ['Cancelada', 'neutral'],
};

export function TelaConsultas({ token, onVoltar, onAgendar }: {
  token: string;
  onVoltar: () => void;
  onAgendar: (remarcar?: ConsultaPortal) => void;
}) {
  const [dados, setDados] = useState<AgendaPortal | null>(null);
  const [aba, setAba] = useState<'prox' | 'hist'>('prox');
  const [aviso, setAviso] = useState<string | null>(null);
  const base = `/fisio/p/${token}/agenda`;
  const carregar = async () => {
    const r = await pedir('GET', base);
    if (r.status === 200) setDados(r.json as unknown as AgendaPortal);
  };
  useEffect(() => { void carregar(); }, [token]); // eslint-disable-line react-hooks/exhaustive-deps

  async function agir(id: number, acao: string, corpo?: unknown) {
    const r = await pedir('POST', `${base}/${id}/${acao}`, corpo);
    setAviso(r.status === 200 ? null : erroDe(r.json) ?? 'Não deu certo. Tente de novo.');
    await carregar();
  }

  if (!dados) return <p className="text-muted-foreground">Carregando suas consultas…</p>;
  const avaliar = dados.historico.find((c) => c.status === 'realizado' && c.nota === null);

  return (
    <div className="flex flex-col gap-4" data-contract="fisio-portal-consultas">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Minhas consultas</h1>
        <Button variant="ghost" onClick={onVoltar}>Voltar</Button>
      </div>
      <Segmented value={aba} onValueChange={(v) => setAba(v as 'prox' | 'hist')}
        options={[{ value: 'prox', label: 'Próximas' }, { value: 'hist', label: 'Histórico' }]} aria-label="Consultas" />
      {aviso && <p role="alert" className="text-sm text-destructive">{aviso}</p>}

      {aba === 'prox' && (
        <>
          {dados.proximas.length === 0 && <p className="text-muted-foreground">Nenhuma consulta marcada.</p>}
          {dados.proximas.map((c) => (
            <Card key={c.id} data-consulta={c.id}>
              <CardContent className="flex flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex flex-col">
                    <span className="font-medium">{c.tipo ?? 'Consulta'} · {quando(c.inicio)}</span>
                    <span className="text-sm text-muted-foreground">{c.profissional}{c.teleconsulta ? ' · a distância' : ''}</span>
                  </div>
                  <Badge variant={ROTULO[c.status][1]}>{ROTULO[c.status][0]}</Badge>
                </div>
                <div className="flex flex-wrap gap-2">
                  {dados.checkin && !c.teleconsulta && podeChegar(c) && <Button onClick={() => agir(c.id, 'cheguei')}>Cheguei</Button>}
                  {c.status === 'agendado' && <Button variant="outline" onClick={() => agir(c.id, 'confirmar')}>Confirmo</Button>}
                  {c.pode_mexer && dados.impedimento === null && <Button variant="outline" onClick={() => onAgendar(c)}>Remarcar</Button>}
                  {c.pode_mexer && <Button variant="ghost" onClick={() => agir(c.id, 'cancelar')}>Cancelar</Button>}
                </div>
                {!c.pode_mexer && ['agendado', 'confirmado'].includes(c.status) && (
                  <p className="text-xs text-muted-foreground">Faltam menos de {dados.prazo_horas} h: para mudar, fale com a clínica.</p>
                )}
              </CardContent>
            </Card>
          ))}
          {dados.espera && (
            <div className="flex items-center justify-between gap-2 rounded-lg border border-dashed border-border p-3 text-sm">
              <span>Na espera: {dados.espera.preferencia}</span>
              <Button variant="ghost" onClick={async () => { await pedir('DELETE', `${base}/espera`); await carregar(); }}>Sair</Button>
            </div>
          )}
          {dados.impedimento === null
            ? <Button onClick={() => onAgendar()}>Agendar nova</Button>
            : <p className="text-sm text-muted-foreground">{MOTIVO_SEM_AGENDA[dados.impedimento]}</p>}
        </>
      )}

      {aba === 'hist' && (
        <>
          {avaliar && (
            <Card>
              <CardContent className="flex flex-col gap-2 p-4">
                <span className="font-medium">Como foi a sessão de {quando(avaliar.inicio)}?</span>
                <div className="flex gap-2">
                  {[1, 2, 3, 4, 5].map((n) => (
                    <Button key={n} variant="outline" size="icon" aria-label={`Nota ${n}`} onClick={() => agir(avaliar.id, 'nota', { nota: n })}>{n}</Button>
                  ))}
                </div>
                <span className="text-xs text-muted-foreground">Só a clínica vê. Não vira avaliação pública.</span>
              </CardContent>
            </Card>
          )}
          {dados.historico.length === 0 && <p className="text-muted-foreground">Ainda não há consultas anteriores.</p>}
          {dados.historico.map((c) => (
            <div key={c.id} className="flex items-center justify-between border-b border-border py-2 text-sm">
              <span>{quando(c.inicio)} · {c.tipo}</span>
              <Badge variant={ROTULO[c.status][1]}>{ROTULO[c.status][0]}</Badge>
            </div>
          ))}
        </>
      )}
    </div>
  );
}
