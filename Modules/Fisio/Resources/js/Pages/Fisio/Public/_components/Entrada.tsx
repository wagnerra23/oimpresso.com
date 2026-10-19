// Entrada do portal do paciente (US-FISIO-007a/007b · UC-FPP-01..07, UC-FPP-21..23): PIN em
// teclado numérico grande, "lembrar este aparelho" e as autorizações separadas. O servidor decide
// a etapa; aqui só coleta e recarrega.
import { router } from '@inertiajs/react';
import { Check, Delete } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { cn } from '@/Lib/utils';
import { enviar, type Termo } from './portal';

const TECLAS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'apagar'] as const;
const TRAVADO = 'Acesso travado por 30 minutos depois de 5 tentativas. Se esqueceu o PIN, peça um novo à sua fisio.';

export function EtapaPin({ token, restantes, lembrarDias }: { token: string; restantes: number; lembrarDias: number }) {
  const [pin, setPin] = useState('');
  const [lembrar, setLembrar] = useState(true);
  const [msg, setMsg] = useState<string | null>(restantes === 0 ? TRAVADO : null);
  const [enviando, setEnviando] = useState(false);
  const completo = pin.length === 4;

  const tecla = (t: (typeof TECLAS)[number]) => {
    if (t === 'apagar') return setPin((p) => p.slice(0, -1));
    if (t !== '') setPin((p) => (p.length < 4 ? p + t : p));
  };

  const entrar = async () => {
    if (!completo || enviando) return;
    setEnviando(true);
    const r = await enviar('POST', `/fisio/p/${token}/pin`, { pin, lembrar });
    setEnviando(false);
    setPin('');
    if (r.status === 200) return router.reload();
    if (r.status === 423) return setMsg(TRAVADO);
    if (r.status === 422) return setMsg(`PIN incorreto. Restam ${String(r.json.tentativas_restantes ?? 0)} tentativa(s).`);
    router.reload();
  };

  return (
    <form
      className="flex flex-1 flex-col gap-5"
      data-contract="fisio-portal-pin"
      onSubmit={(e) => { e.preventDefault(); void entrar(); }}
    >
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold leading-tight">Digite seu PIN</h1>
        <p className="text-muted-foreground">São os 4 números que a sua fisio passou na consulta. Sem app para instalar e sem senha para lembrar.</p>
      </div>

      <div className="flex justify-center gap-3 py-1" role="status" aria-label={`${pin.length} de 4 números digitados`}>
        {[0, 1, 2, 3].map((i) => (
          <span key={i} className={cn('size-4 rounded-full border-2 border-primary', i < pin.length ? 'bg-primary' : 'bg-transparent')} />
        ))}
      </div>

      {/* Campo real para teclado físico e leitor de tela; o teclado grande é atalho de toque. */}
      <Label htmlFor="portal-pin" className="sr-only">PIN</Label>
      <input
        id="portal-pin"
        inputMode="numeric"
        autoComplete="one-time-code"
        maxLength={4}
        value={pin}
        onChange={(e) => setPin(e.target.value.replace(/\D/g, '').slice(0, 4))}
        className="sr-only"
        aria-describedby="portal-pin-msg"
      />
      <div className="grid grid-cols-3 gap-2.5" aria-hidden="false">
        {TECLAS.map((t, i) => (
          t === ''
            ? <span key={i} />
            : (
              <button
                key={i}
                type="button"
                onClick={() => tecla(t)}
                aria-label={t === 'apagar' ? 'Apagar' : `Número ${t}`}
                className={cn(
                  'flex h-14 items-center justify-center rounded-xl font-mono text-2xl outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                  t === 'apagar' ? 'text-muted-foreground' : 'border border-border bg-card active:bg-muted',
                )}
              >
                {t === 'apagar' ? <Delete className="size-6" aria-hidden="true" /> : t}
              </button>
            )
        ))}
      </div>

      <div className="flex items-start gap-3 rounded-xl border border-border bg-card p-3">
        <Checkbox id="portal-lembrar" checked={lembrar} onCheckedChange={(v) => setLembrar(v === true)} className="mt-0.5" />
        <Label htmlFor="portal-lembrar" className="flex flex-col items-start gap-0.5 font-normal">
          <span className="font-medium">Lembrar este aparelho por {lembrarDias} dias</span>
          <span className="text-xs text-muted-foreground">Não marque em celular emprestado ou compartilhado.</span>
        </Label>
      </div>

      {msg && <p id="portal-pin-msg" className="text-sm text-destructive" role="alert">{msg}</p>}

      <Button type="submit" size="lg" className="mt-auto h-14 rounded-xl text-base" disabled={!completo || enviando}>Entrar</Button>
      <p className="text-center text-xs text-muted-foreground">Depois de 5 tentativas erradas o acesso fica travado por 30 minutos.</p>
    </form>
  );
}

const TEXTO_OPCIONAL: Record<string, { titulo: string; detalhe: string }> = {
  whatsapp: { titulo: 'Lembretes no WhatsApp', detalhe: 'Um lembrete por dia com o link dos exercícios.' },
  camera: { titulo: 'Câmera para contar repetições', detalhe: 'Liga só quando você escolher. A imagem fica no seu celular; só a contagem sobe.' },
};

function LinhaAutorizacao({ id, titulo, detalhe, ligado, onChange }: { id: string; titulo: string; detalhe: string; ligado: boolean; onChange: (v: boolean) => void }) {
  return (
    <div className="flex items-start gap-3 rounded-xl border border-border bg-card p-3">
      <Label htmlFor={id} className="flex flex-1 flex-col items-start gap-0.5 font-normal">
        <span className="font-semibold">{titulo}</span>
        <span className="text-sm text-muted-foreground">{detalhe}</span>
      </Label>
      <Switch id={id} checked={ligado} onCheckedChange={onChange} className="mt-1" />
    </div>
  );
}

export function EtapaConsentimento({ token, clinica, versao, opcionais }: { token: string; clinica: string | null; versao: string | null; opcionais: Termo[] }) {
  const [aceito, setAceito] = useState(true);
  const [extras, setExtras] = useState<Record<string, boolean>>({});
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const confirmar = async () => {
    if (!aceito || enviando) return;
    setEnviando(true);
    setErro(null);
    const r = await enviar('POST', `/fisio/p/${token}/consentimentos`, { purpose: 'teleatendimento', aceito: true });
    if (r.status !== 200 && r.status !== 201) {
      setEnviando(false);
      return setErro('Não deu para registrar agora. Confira a internet e tente de novo.');
    }
    for (const t of opcionais) {
      if (extras[t.purpose]) await enviar('POST', `/fisio/p/${token}/consentimentos`, { purpose: t.purpose, aceito: true });
    }
    setEnviando(false);
    router.reload();
  };

  return (
    <form className="flex flex-1 flex-col gap-4" data-contract="fisio-portal-consentimento" onSubmit={(e) => { e.preventDefault(); void confirmar(); }}>
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold leading-tight">Antes de começar</h1>
        <p className="text-muted-foreground">Cada autorização é separada. Você pode mudar qualquer uma depois.</p>
      </div>
      <LinhaAutorizacao
        id="consent-teleatendimento"
        titulo="Meu tratamento"
        detalhe={`Usar meus dados de saúde (dor, exercícios, recados) para ${clinica ?? 'a clínica'} acompanhar meu tratamento à distância. Necessário para usar.`}
        ligado={aceito}
        onChange={setAceito}
      />
      {opcionais.map((t) => (
        <LinhaAutorizacao
          key={t.purpose}
          id={`consent-${t.purpose}`}
          titulo={`${TEXTO_OPCIONAL[t.purpose]?.titulo ?? t.purpose} (opcional)`}
          detalhe={TEXTO_OPCIONAL[t.purpose]?.detalhe ?? ''}
          ligado={!!extras[t.purpose]}
          onChange={(v) => setExtras({ ...extras, [t.purpose]: v })}
        />
      ))}
      <p className="text-xs text-muted-foreground">
        Termo versão {versao ?? '—'} · seus dados ficam com {clinica ?? 'a clínica'} e não são vendidos nem usados para propaganda.
      </p>
      {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
      <Button type="submit" size="lg" className="mt-auto h-14 rounded-xl text-base" disabled={!aceito || enviando}>
        <Check aria-hidden="true" />Concordo e começar
      </Button>
      <p className="text-center text-sm text-muted-foreground">
        {aceito ? 'Lembretes e câmera são opcionais.' : 'Sem a primeira autorização a sua fisio não consegue acompanhar o tratamento por aqui.'}
      </p>
    </form>
  );
}
