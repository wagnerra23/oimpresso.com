// @memcofre
//   tela: /fisio/p/{token}
//   module: Fisio
//   stories: US-FISIO-007a (UC-FPP-01..07) — entrada mínima; as telas do dia e do jogo são da US-FISIO-007b/008
//   permissao: pública (sem login) — o business viaja assinado no token
//
// Portal do paciente, versão mínima: link → PIN → consentimento de teleatendimento → lista do
// dia. Fora do AppShellV2 (o paciente não tem conta). Nada clínico aparece antes do PIN e do
// consentimento: isso é garantido no servidor (PortalController); esta tela só reflete a etapa.
import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface ItemHoje {
  id: number;
  nome: string | null;
  passos: string[];
  cuidados: string[];
  sets: number | null;
  reps: number | null;
  hold_s: number | null;
  times_per_day: number;
  notes: string | null;
}
interface Termo { purpose: string; version: string | null }
interface Props {
  erro: 'link_invalido' | null;
  etapa: 'pin' | 'consentimento' | 'hoje' | null;
  token: string;
  clinica?: string | null;
  tentativas_restantes?: number;
  consentimento?: Termo;
  opcionais?: Termo[];
  hoje?: { itens: ItemHoje[] };
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function enviar(metodo: 'POST' | 'DELETE', url: string, corpo?: unknown): Promise<{ status: number; json: Record<string, unknown> }> {
  const res = await fetch(url, {
    method: metodo,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    body: corpo === undefined ? undefined : JSON.stringify(corpo),
  });
  const json = (await res.json().catch(() => ({}))) as Record<string, unknown>;
  return { status: res.status, json };
}

const TEXTO_OPCIONAL: Record<string, string> = {
  whatsapp: 'Aceito receber lembretes e mensagens da clínica pelo WhatsApp.',
  camera: 'Aceito usar a câmera do celular nos exercícios. A imagem fica no aparelho; só a contagem sobe.',
};

function Moldura({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-8" data-screen-label="Fisio · Portal do paciente">
      <Head title={titulo} />
      <Card className="w-full max-w-md">
        <CardContent className="flex flex-col gap-4 p-6">{children}</CardContent>
      </Card>
    </div>
  );
}

function EtapaPin({ token, restantes }: { token: string; restantes: number }) {
  const [pin, setPin] = useState('');
  const [msg, setMsg] = useState<string | null>(restantes === 0 ? 'Acesso travado por 30 minutos depois de 5 tentativas.' : null);
  const [enviando, setEnviando] = useState(false);

  const entrar = async (e: FormEvent) => {
    e.preventDefault();
    setEnviando(true);
    const r = await enviar('POST', `/fisio/p/${token}/pin`, { pin });
    setEnviando(false);
    setPin('');
    if (r.status === 200) return router.reload();
    if (r.status === 423) return setMsg('Acesso travado por 30 minutos depois de 5 tentativas.');
    if (r.status === 422) return setMsg(`PIN incorreto. Restam ${String(r.json.tentativas_restantes ?? 0)} tentativa(s).`);
    router.reload();
  };

  return (
    <form onSubmit={entrar} className="flex flex-col gap-3" data-contract="fisio-portal-pin">
      <h1 className="text-lg font-semibold">Seu programa de exercícios</h1>
      <p className="text-sm text-muted-foreground">Digite o PIN de 4 números que a sua fisioterapeuta passou.</p>
      <Label htmlFor="portal-pin">PIN</Label>
      <Input id="portal-pin" inputMode="numeric" autoComplete="one-time-code" maxLength={4} value={pin}
        onChange={(e) => setPin(e.target.value.replace(/\D/g, ''))} aria-describedby="portal-pin-msg" />
      {msg && <p id="portal-pin-msg" className="text-sm text-destructive" role="alert">{msg}</p>}
      <Button type="submit" disabled={enviando || pin.length !== 4}>Entrar</Button>
    </form>
  );
}

function EtapaConsentimento({ token, clinica, opcionais }: { token: string; clinica: string | null; opcionais: Termo[] }) {
  const [aceito, setAceito] = useState(false);
  const [extras, setExtras] = useState<Record<string, boolean>>({});
  const [enviando, setEnviando] = useState(false);

  const confirmar = async (e: FormEvent) => {
    e.preventDefault();
    setEnviando(true);
    await enviar('POST', `/fisio/p/${token}/consentimentos`, { purpose: 'teleatendimento', aceito: true });
    for (const t of opcionais) {
      if (extras[t.purpose]) await enviar('POST', `/fisio/p/${token}/consentimentos`, { purpose: t.purpose, aceito: true });
    }
    setEnviando(false);
    router.reload();
  };

  return (
    <form onSubmit={confirmar} className="flex flex-col gap-4" data-contract="fisio-portal-consentimento">
      <h1 className="text-lg font-semibold">Antes de começar</h1>
      <p className="text-sm">
        {clinica ?? 'A clínica'} vai acompanhar seus exercícios à distância por este portal: você registra como foi o dia e a
        sua fisioterapeuta vê no prontuário. Você pode retirar este consentimento quando quiser.
      </p>
      <div className="flex items-start gap-2 text-sm">
        <Checkbox id="consent-teleatendimento" checked={aceito} onCheckedChange={(v) => setAceito(v === true)} />
        <Label htmlFor="consent-teleatendimento" className="font-normal">Aceito o acompanhamento à distância (obrigatório para ver o programa).</Label>
      </div>
      {opcionais.map((t) => (
        <div key={t.purpose} className="flex items-start gap-2 text-sm">
          <Checkbox id={`consent-${t.purpose}`} checked={!!extras[t.purpose]} onCheckedChange={(v) => setExtras({ ...extras, [t.purpose]: v === true })} />
          <Label htmlFor={`consent-${t.purpose}`} className="font-normal">{TEXTO_OPCIONAL[t.purpose] ?? t.purpose} (opcional)</Label>
        </div>
      ))}
      <Button type="submit" disabled={!aceito || enviando}>Continuar</Button>
    </form>
  );
}

function EtapaHoje({ itens }: { itens: ItemHoje[] }) {
  if (itens.length === 0) return <p className="text-sm text-muted-foreground">Sua fisioterapeuta ainda não montou o programa.</p>;
  return (
    <div className="flex flex-col gap-3" data-contract="fisio-portal-hoje">
      <h1 className="text-lg font-semibold">Hoje</h1>
      <ol className="flex flex-col gap-3">
        {itens.map((i) => (
          <li key={i.id} className="rounded-lg border border-border p-3 text-sm">
            <p className="font-medium">{i.nome}</p>
            <p className="text-muted-foreground">
              {[i.sets ? `${i.sets} séries` : null, i.reps ? `${i.reps} repetições` : null, i.hold_s ? `${i.hold_s}s` : null].filter(Boolean).join(' · ')}
            </p>
            {i.notes && <p>{i.notes}</p>}
          </li>
        ))}
      </ol>
    </div>
  );
}

export default function Portal({ erro, etapa, token, clinica, tentativas_restantes, opcionais, hoje }: Props) {
  if (erro === 'link_invalido' || etapa === null) {
    return (
      <Moldura titulo="Link inválido">
        <h1 className="text-lg font-semibold">Link inválido</h1>
        <p className="text-sm text-muted-foreground">Este link expirou ou não é válido. Peça um novo link à sua clínica.</p>
      </Moldura>
    );
  }
  return (
    <Moldura titulo="Seu programa">
      {etapa === 'pin' && <EtapaPin token={token} restantes={tentativas_restantes ?? 0} />}
      {etapa === 'consentimento' && <EtapaConsentimento token={token} clinica={clinica ?? null} opcionais={opcionais ?? []} />}
      {etapa === 'hoje' && <EtapaHoje itens={hoje?.itens ?? []} />}
    </Moldura>
  );
}