// @memcofre
//   tela: /fisio/consulta/{consulta}
//   module: Fisio
//   stories: US-FISIO-017 (UC-FCF-01..06)
//   permissao: pública (sem login, sem PIN) — link assinado de um atendimento, vence no horário da sessão
//
// Página de um toque da mensagem de confirmação da consulta (etapa 04 A6; protótipo
// PacienteAgendaConfirmar.dc.html). Mobile-first, fora do AppShellV2, como o portal do paciente.
// Mostra só clínica, profissional, dia e hora. Abrir não muda nada; responder é POST.
//
// Tema: os tokens do DS (fonte IBM Plex, neutros quentes) só existem sob `.cockpit`, e o
// cockpit.css só entra pelo AppShellV2. Página pública fica fora do shell, então importa o CSS
// e se envolve num `.cockpit` com display:contents — mesmo idioma do PortalContatoLayout (CRM)
// e do TemaDoCockpit (Fluxos). Medido em 2026-11-23: sem isso a página saía em fonte do sistema.
import '../../../../../../../resources/css/cockpit.css';
import { Head, router } from '@inertiajs/react';
import { HeartPulse } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { useTheme } from '@/Hooks/useTheme';

type Resultado = 'confirmado' | 'cancelado' | 'fora_do_prazo' | 'encerrada';

interface Props {
  clinica: string;
  profissional: string;
  quando: string;
  status: string;
  aberta: boolean;
  prazo_horas: number;
  resultado: Resultado | null;
  acoes: { confirmar: string; nao_vou: string } | null;
}

function textoDoResultado(r: Resultado, p: Props): string {
  const quem = p.profissional || p.clinica;
  switch (r) {
    case 'confirmado':
      return `Confirmado! Até ${p.quando}.`;
    case 'cancelado':
      return `Tudo bem, avisamos ${quem}. A sessão de ${p.quando} foi desmarcada.`;
    case 'fora_do_prazo':
      return `Faltam menos de ${p.prazo_horas} h para a sessão. Para desmarcar agora, fale com a clínica.`;
    default:
      return 'Esta sessão já não aceita resposta por aqui.';
  }
}

function textoDoStatus(p: Props): string | null {
  if (p.status === 'confirmado') return 'Sessão confirmada.';
  if (p.aberta) return null;
  if (p.status === 'cancelado') return 'Esta sessão foi desmarcada.';
  return 'Esta sessão já não aceita resposta por aqui.';
}

export default function Confirmacao(props: Props) {
  const [remarcar, setRemarcar] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const responder = (url: string) => {
    setEnviando(true);
    router.post(url, {}, { preserveScroll: true, onFinish: () => setEnviando(false) });
  };
  const status = textoDoStatus(props);
  const { effective } = useTheme();

  return (
    <div className="cockpit" data-theme={effective} style={{ display: 'contents' }}>
    <div className="flex min-h-dvh justify-center bg-background text-foreground" data-screen-label="Fisio · Confirmar consulta">
      <Head title="Confirmar consulta" />
      <main className="flex w-full max-w-md flex-col gap-5 px-4 pt-6 pb-4">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span className="flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground">
            <HeartPulse className="size-4" aria-hidden="true" />
          </span>
          <span className="font-medium">{props.clinica}</span>
        </div>

        <section className="flex flex-col gap-2 rounded-lg border bg-card p-4">
          <h1 className="text-lg font-semibold">Sua sessão</h1>
          <p className="text-base">
            <span className="font-medium">{props.quando}</span>
            {props.profissional && <> com {props.profissional}</>}
          </p>
          {/* Logo depois de responder, a faixa do resultado já diz isso; a linha é para quem volta ao link. */}
          {status && !props.resultado && <p className="text-sm text-muted-foreground">{status}</p>}
        </section>

        {props.resultado && (
          <p role="status" className="rounded-lg border bg-muted p-4 text-sm">
            {textoDoResultado(props.resultado, props)}
          </p>
        )}

        {props.aberta && props.acoes && props.status !== 'confirmado' && (
          <div className="flex flex-col gap-3">
            <Button size="lg" className="min-h-11" disabled={enviando} onClick={() => responder(props.acoes!.confirmar)}>
              Confirmo
            </Button>
            <Button size="lg" variant="outline" className="min-h-11" disabled={enviando} onClick={() => setRemarcar(true)}>
              Preciso remarcar
            </Button>
            <Button size="lg" variant="outline" className="min-h-11" disabled={enviando} onClick={() => responder(props.acoes!.nao_vou)}>
              Não vou poder ir
            </Button>
          </div>
        )}

        {props.aberta && props.acoes && props.status === 'confirmado' && (
          <Button size="lg" variant="outline" className="min-h-11" disabled={enviando} onClick={() => responder(props.acoes!.nao_vou)}>
            Não vou poder ir
          </Button>
        )}

        {remarcar && (
          <p role="status" className="rounded-lg border bg-muted p-4 text-sm">
            Para escolher outro horário, abra o link do seu programa de exercícios e vá em <strong>Minhas consultas</strong>.
            Se não tiver o link, responda a mensagem no WhatsApp.
          </p>
        )}

        <p className="text-xs text-muted-foreground">
          Desmarcar com menos de {props.prazo_horas} h segue a política da clínica, sempre confirmada pela profissional.
        </p>
      </main>
    </div>
    </div>
  );
}
