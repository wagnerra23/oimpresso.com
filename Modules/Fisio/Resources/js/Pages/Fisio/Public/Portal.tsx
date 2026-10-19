// @memcofre
//   tela: /fisio/p/{token}
//   module: Fisio
//   stories: US-FISIO-007a (UC-FPP-01..07, UC-FPP-21) · US-FISIO-007b (UC-FPP-08..20, UC-FPP-22..25) · US-FISIO-008 (jogo)
//   permissao: pública (sem login) — o business viaja assinado no token
//
// Portal do paciente, mobile-first, fora do AppShellV2 (o paciente não tem conta). Uma página
// Inertia só; as telas são componentes escolhidos pela `etapa` e pelo `modo` que o SERVIDOR
// decide (PortalController):
//   Entrada  — PIN (+ lembrar aparelho) e autorizações       → _components/Entrada.tsx
//   Hoje     — exercícios, jogo, "hoje estou em crise"        → _components/Hoje.tsx
//   Check-in — "Como foi hoje?" em 3 toques                   → _components/Checkin.tsx
//   Pausa    — dor ≥ limiar ou sinal de alerta: só a instrução escrita pela fisio (sem jogo).
// App instalável (T-13): manifesto por link + service worker; a tela do dia abre sem rede em
// aparelho lembrado (o servidor decide pelo cabeçalho X-Fisio-Offline)  → _components/Instalar.tsx
// Nada clínico aparece antes do PIN e do consentimento: isso é garantido no servidor.
import { Head, router } from '@inertiajs/react';
import { HeartPulse, ShieldCheck } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { TelaCheckin, InstrucaoDaFisio } from './_components/Checkin';
import { EtapaConsentimento, EtapaPin } from './_components/Entrada';
import { TelaCrise, TelaHoje } from './_components/Hoje';
import { ConviteInstalar } from './_components/Instalar';
import { enviarFila, limparFila, registrarAppDoPortal, type ItemHoje, type Jogo, type Relato, type Termo } from './_components/portal';

interface Props {
  erro: 'link_invalido' | 'sem_link' | null;
  etapa: 'pin' | 'consentimento' | 'hoje' | null;
  token: string;
  clinica?: string | null;
  tentativas_restantes?: number;
  lembrar_dias?: number;
  consentimento?: Termo;
  opcionais?: Termo[];
  modo?: 'normal' | 'pausa';
  instrucao?: string | null;
  jogo?: Jogo;
  registrado_hoje?: boolean;
  guardar_offline?: boolean;
  hoje?: { itens: ItemHoje[] };
  crise?: { itens: string[] };
  relato?: Relato | null;
  red_flags?: string[];
}

function Moldura({ titulo, clinica, token, children }: { titulo: string; clinica?: string | null; token?: string; children: ReactNode }) {
  return (
    <div className="flex min-h-dvh justify-center bg-background text-foreground" data-screen-label="Fisio · Portal do paciente">
      <Head title={titulo}>
        {token && <link rel="manifest" href={`/fisio/p/${token}/manifest.webmanifest`} />}
        {token && <link rel="apple-touch-icon" href="/img/fisio-portal-apple-180.png" />}
      </Head>
      <main className="flex w-full max-w-md flex-col gap-5 px-4 pt-6 pb-4">
        {clinica && (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <span className="flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground"><HeartPulse className="size-4" aria-hidden="true" /></span>
            <span className="font-medium">{clinica}</span>
          </div>
        )}
        {children}
      </main>
    </div>
  );
}

type Tela = 'hoje' | 'checkin' | 'crise' | 'checkin-crise';

function vezesIniciais(itens: ItemHoje[], relato: Relato | null | undefined): Record<number, number> {
  const out: Record<number, number> = {};
  for (const i of itens) out[i.id] = relato?.feitos.includes(i.id) ? 1 : 0;
  return out;
}

function AreaDoDia({ token, props }: { token: string; props: Props }) {
  const itens = props.hoje?.itens ?? [];
  const crise = props.crise?.itens ?? [];
  const [tela, setTela] = useState<Tela>('hoje');
  const [vezes, setVezes] = useState<Record<number, number>>(() => vezesIniciais(itens, props.relato));
  const mudarVezes = (id: number, n: number) => setVezes((v) => ({ ...v, [id]: Math.max(0, n) }));
  const jogo = props.jogo as Jogo;

  if (tela === 'crise') {
    return <TelaCrise itens={crise} sequencia={jogo.sequencia} onVoltar={() => setTela('hoje')} onComecar={() => setTela('checkin-crise')} />;
  }
  if (tela === 'checkin' || tela === 'checkin-crise') {
    return (
      <TelaCheckin
        token={token}
        itens={itens}
        vezes={vezes}
        onVezes={mudarVezes}
        relato={props.relato ?? null}
        sinais={props.red_flags ?? []}
        crise={tela === 'checkin-crise'}
        guardarOffline={props.guardar_offline ?? false}
        onVoltar={() => setTela('hoje')}
      />
    );
  }
  return (
    <>
    <TelaHoje
      itens={itens}
      jogo={jogo}
      vezes={vezes}
      onVezes={mudarVezes}
      registradoHoje={props.registrado_hoje ?? false}
      temCrise={crise.length > 0}
      onCheckin={() => setTela('checkin')}
      onCrise={() => setTela('crise')}
    />
    <ConviteInstalar diasCumpridos={jogo.dias_cumpridos} />
    </>
  );
}

export default function Portal(props: Props) {
  const { erro, etapa, token, clinica } = props;
  useEffect(registrarAppDoPortal, []);

  // Fila offline do check-in (UC-FPP-30..32): envia o que ficou guardado ao abrir a tela do dia
  // e quando a internet volta; link inválido apaga a fila daquele link sem enviar.
  useEffect(() => {
    if (!token) return;
    if (erro === 'link_invalido') {
      limparFila(token);
      return;
    }
    if (etapa !== 'hoje') return;
    const tentar = () => {
      void enviarFila(token).then((r) => {
        if (r === 'enviado') router.reload();
      });
    };
    tentar();
    window.addEventListener('online', tentar);
    return () => window.removeEventListener('online', tentar);
  }, [token, erro, etapa]);

  if (erro === 'sem_link') {
    return (
      <Moldura titulo="Seu programa de exercícios">
        <div className="flex flex-col gap-3" data-contract="fisio-portal-sem-link">
          <h1 className="text-2xl font-semibold">Abra o link da sua clínica</h1>
          <p className="text-muted-foreground">Sua fisioterapeuta manda o link do seu programa pelo WhatsApp. Toque nele para entrar com o seu PIN.</p>
          <p className="text-muted-foreground">Não recebeu? Peça o link à sua clínica.</p>
        </div>
      </Moldura>
    );
  }

  if (erro === 'link_invalido' || etapa === null) {
    return (
      <Moldura titulo="Link inválido">
        <h1 className="text-2xl font-semibold">Link inválido</h1>
        <p className="text-muted-foreground">Este link expirou ou não é válido. Peça um novo link à sua clínica.</p>
      </Moldura>
    );
  }

  if (etapa === 'pin') {
    return (
      <Moldura token={token} titulo="Seu programa">
        <EtapaPin token={token} restantes={props.tentativas_restantes ?? 0} lembrarDias={props.lembrar_dias ?? 30} />
      </Moldura>
    );
  }

  if (etapa === 'consentimento') {
    return (
      <Moldura token={token} titulo="Antes de começar" clinica={clinica}>
        <EtapaConsentimento token={token} clinica={clinica ?? null} versao={props.consentimento?.version ?? null} opcionais={props.opcionais ?? []} />
      </Moldura>
    );
  }

  if (props.modo === 'pausa') {
    return (
      <Moldura titulo="Hoje" clinica={clinica} token={token}>
        <div className="flex flex-1 flex-col gap-4" data-contract="fisio-portal-pausa">
          <h1 className="text-2xl font-semibold leading-tight">Hoje é dia de cuidar</h1>
          <p className="flex items-center gap-2 text-muted-foreground"><ShieldCheck className="size-5 text-primary" aria-hidden="true" />Seu registro foi enviado. Sua sequência está protegida.</p>
          <InstrucaoDaFisio instrucao={props.instrucao ?? null} />
        </div>
      </Moldura>
    );
  }

  return (
    <Moldura titulo="Hoje" clinica={clinica} token={token}>
      <AreaDoDia token={token} props={props} />
    </Moldura>
  );
}
