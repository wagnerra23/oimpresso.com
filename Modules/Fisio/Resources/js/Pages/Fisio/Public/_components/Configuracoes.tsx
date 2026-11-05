// Configurações do paciente no portal (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/PacienteConfig.dc.html).
//
// Só a parte que o servidor já sustenta: as autorizações por finalidade (LGPD), uma por linha,
// ligando e desligando pelas rotas `consentimentos` do portal (US-FISIO-007a). Desligar "Meu
// tratamento" fecha o programa: o servidor volta a pedir a autorização (UC-FPP-06).
//
// Fora daqui, de propósito, porque nada no servidor faz ainda: horário do lembrete por paciente,
// narração lenta / economia de dados / letra maior, baixar cópia dos dados, histórico do termo e
// "sair deste celular".
import { router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { enviar } from './portal';

/** Finalidades mostradas, na ordem do protótipo. O texto de whatsapp/câmera é o mesmo do primeiro acesso. */
const AUTORIZACOES: { purpose: string; titulo: string; detalhe: (clinica: string) => string }[] = [
  { purpose: 'teleatendimento', titulo: 'Meu tratamento', detalhe: (c) => `Meus dados de saúde (dor, exercícios, recados) para ${c} acompanhar meu tratamento.` },
  { purpose: 'whatsapp', titulo: 'Lembretes no WhatsApp', detalhe: () => 'Um lembrete por dia com o link dos exercícios.' },
  { purpose: 'camera', titulo: 'Câmera para contar repetições', detalhe: () => 'Liga só quando você escolher. A imagem fica no seu celular; só a contagem sobe.' },
];

export function TelaConfiguracoes({ token, clinica, consentimentos, onVoltar }: {
  token: string;
  clinica: string | null;
  consentimentos: Record<string, boolean>;
  onVoltar: () => void;
}) {
  const [estado, setEstado] = useState<Record<string, boolean>>(consentimentos);
  const [ocupado, setOcupado] = useState<string | null>(null);
  const [confirmarSaida, setConfirmarSaida] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const nome = clinica ?? 'a clínica';

  const mudar = async (purpose: string, ligar: boolean) => {
    if (purpose === 'teleatendimento' && !ligar && !confirmarSaida) {
      setConfirmarSaida(true);
      return;
    }
    setOcupado(purpose);
    setErro(null);
    const r = ligar
      ? await enviar('POST', `/fisio/p/${token}/consentimentos`, { purpose, aceito: true })
      : await enviar('DELETE', `/fisio/p/${token}/consentimentos/${purpose}`);
    setOcupado(null);
    if (r.status !== 200 && r.status !== 201) {
      return setErro('Não deu para salvar agora. Confira a internet e tente de novo.');
    }
    setEstado({ ...estado, [purpose]: ligar });
    if (purpose === 'teleatendimento' && !ligar) router.reload();
  };

  return (
    <div className="flex flex-1 flex-col gap-5" data-contract="fisio-portal-configuracoes">
      <div className="flex items-center gap-2">
        <Button type="button" variant="ghost" size="icon" onClick={onVoltar} aria-label="Voltar"><ArrowLeft aria-hidden="true" /></Button>
        <h1 className="text-2xl font-semibold leading-tight">Configurações</h1>
      </div>

      <section className="flex flex-col gap-2" aria-labelledby="cfg-aut">
        <h2 id="cfg-aut" className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Suas autorizações</h2>
        {AUTORIZACOES.map((a) => (
          <div key={a.purpose} className="flex items-start gap-3 rounded-lg border border-border bg-card p-3">
            <Label htmlFor={`cfg-${a.purpose}`} className="flex flex-1 flex-col items-start gap-0.5 font-normal">
              <span className="font-semibold">{a.titulo}</span>
              <span className="text-sm text-muted-foreground">{a.detalhe(nome)}</span>
            </Label>
            <Switch id={`cfg-${a.purpose}`} className="mt-1" checked={!!estado[a.purpose]} disabled={ocupado !== null}
              onCheckedChange={(v) => void mudar(a.purpose, v)} />
          </div>
        ))}
        {confirmarSaida && (
          <div role="alert" className="flex flex-col gap-2 rounded-lg border border-warning/40 bg-warning-soft p-3 text-sm">
            <span>Sem esta autorização o portal para de mostrar seu programa até você autorizar de novo. Combine com {nome} outro jeito de acompanhar.</span>
            <div className="flex gap-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setConfirmarSaida(false)}>Manter</Button>
              <Button type="button" variant="destructive" size="sm" onClick={() => void mudar('teleatendimento', false)}>Desligar mesmo assim</Button>
            </div>
          </div>
        )}
        {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
      </section>

      <p className="text-xs text-muted-foreground">Cada autorização é separada e pode ser mudada a qualquer momento. Seus dados ficam com {nome}.</p>
    </div>
  );
}
