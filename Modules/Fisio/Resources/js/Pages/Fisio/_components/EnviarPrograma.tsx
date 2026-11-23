// "Enviar ao paciente" na aba Programa (US-FISIO-009 · UC-FPS-11, UC-FPS-12, UC-FPS-36, UC-FPS-42).
// Rótulo do protótipo `Prescricao.dc.html` (cabeçalho da aba Programa). Chama a rota que já existe,
// `fisio.programas.enviar` (EnvioWhatsappController), e mostra o que ela respondeu:
//   202 fila    → o número oficial da clínica envia link e código; nada sai do navegador.
//   409         → já foi enviado hoje (1 por dia); avisa em vez de sumir na fila.
//   200 manual  → sem número ativo no módulo Whatsapp: dois links wa.me (o do portal e, depois,
//                 o do código) para o profissional enviar do próprio aparelho, nessa ordem.
//   422/503     → motivo legível; nada foi enviado.
import { useState } from 'react';
import { Send } from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface Manual { link: string; codigo: string }

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const MOTIVO: Record<string, string> = {
  ja_enviado_hoje: 'O programa já foi enviado hoje para este paciente. Amanhã dá para enviar de novo.',
  sem_consentimento_whatsapp: 'O paciente não autorizou mensagens pelo WhatsApp. Registre o consentimento antes de enviar.',
  sem_telefone: 'O paciente não tem celular com WhatsApp autorizado no cadastro.',
  programa_inativo_ou_paciente_bloqueado: 'Programa inativo ou paciente bloqueado: nada foi enviado.',
  portal_indisponivel: 'Não foi possível gerar o link do portal agora. Tente de novo.',
};

export default function EnviarPrograma({ programaId }: { programaId: number }) {
  const [enviando, setEnviando] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [manual, setManual] = useState<Manual | null>(null);

  const enviar = async () => {
    setEnviando(true);
    setMsg(null);
    setManual(null);
    try {
      const res = await fetch(`/fisio/programas/${programaId}/enviar`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      });
      const json = (await res.json().catch(() => ({}))) as { modo?: string; erro?: string; wa_me?: Manual };
      if (res.status === 202) setMsg('Enviado para a fila. O paciente recebe o link e, em seguida, o código pelo WhatsApp da clínica.');
      else if (res.status === 200 && json.modo === 'manual' && json.wa_me) setManual(json.wa_me);
      else setMsg(MOTIVO[json.erro ?? ''] ?? 'Não foi possível enviar. Tente de novo.');
    } catch {
      setMsg('Não foi possível enviar. Tente de novo.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="flex flex-col gap-2" data-contract="fisio-enviar-programa">
      <div>
        <Button size="sm" onClick={enviar} disabled={enviando}>
          <Send className="size-4" aria-hidden="true" />
          Enviar ao paciente
        </Button>
      </div>
      {manual && (
        <div className="flex flex-col gap-2 rounded-lg border border-border p-3 text-sm">
          <p>A clínica não tem número de WhatsApp ativo. Envie do seu aparelho, nesta ordem:</p>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" asChild><a href={manual.link} target="_blank" rel="noopener noreferrer">1. Enviar o link</a></Button>
            <Button size="sm" variant="outline" asChild><a href={manual.codigo} target="_blank" rel="noopener noreferrer">2. Enviar o código</a></Button>
          </div>
          <p className="text-xs text-muted-foreground">O link anterior do portal deixou de valer. Mande o código separado do link.</p>
        </div>
      )}
      {msg && <p className="text-xs text-muted-foreground" role="status">{msg}</p>}
    </div>
  );
}
