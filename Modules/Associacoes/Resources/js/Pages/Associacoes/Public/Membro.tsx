// @memcofre
//   tela: /associacoes/m/{token}
//   module: Associacoes
//   stories: US-ASSOC-005 (UC-AMB-01..06)
//   permissao: pública (sem login) — o business viaja assinado no token
//
// Área da associada no app (ADR 0451 D6). Mobile-first, fora do AppShellV2 (a associada não tem
// conta), no visual do portal do paciente do Fisio, que roda no mesmo app. Uma página só; a etapa
// vem do SERVIDOR (PortalAssociadaController): link inválido → PIN → Meus dados + consentimentos.
// Nada aparece antes do PIN: isso é garantido no servidor, não aqui.
import { Head, router } from '@inertiajs/react';
import { Delete, ShieldCheck, Users } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { cn } from '@/Lib/utils';

interface Consentimento { finalidade: string; vigente: boolean; concedido_em: string | null; revogavel: boolean }

interface Props {
  erro: 'link_invalido' | null;
  etapa: 'pin' | 'dados' | null;
  token: string;
  tentativas_restantes?: number;
  dados?: { nome: string | null; associacao: string | null; situacao: string; entrou_em: string | null };
  consentimentos?: Consentimento[];
}

const FINALIDADES: Record<string, { titulo: string; texto: string }> = {
  associada: { titulo: 'Fazer parte da associação', texto: 'É o que mantém você como associada. Para sair, fale com a sua associação.' },
  whatsapp: { titulo: 'Receber avisos pelo WhatsApp', texto: 'A associação manda avisos e convites para o seu celular.' },
  visivel_rede: { titulo: 'Nome visível para a Uniafibro-SC', texto: 'A rede das associações pode ver o seu nome na lista de associadas.' },
};

const TRAVADO = 'Acesso travado por 30 minutos depois de 5 tentativas. Se esqueceu o PIN, peça um novo à sua associação.';
const TECLAS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'apagar'] as const;

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

const dataBr = (iso: string | null | undefined) => (iso ? iso.split('-').reverse().join('/') : '—');

function Moldura({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-dvh justify-center bg-background text-foreground" data-screen-label="Associações · Área da associada">
      <Head title="Minha associação" />
      <main className="flex w-full max-w-md flex-col gap-5 px-4 pt-6 pb-4">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span className="flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground"><Users className="size-4" aria-hidden="true" /></span>
          <span className="font-medium">Uniafibro-SC</span>
        </div>
        {children}
      </main>
    </div>
  );
}

function LinkInvalido() {
  return (
    <div className="flex flex-1 flex-col gap-2" data-contract="assoc-membro-link-invalido">
      <h1 className="text-2xl font-semibold leading-tight">Este link não abre</h1>
      <p className="text-muted-foreground">Ele pode ter vencido ou sido trocado por um novo. Peça um link novo à sua associação.</p>
    </div>
  );
}

function EtapaPin({ token, restantes }: { token: string; restantes: number }) {
  const [pin, setPin] = useState('');
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
    const r = await enviar('POST', `/associacoes/m/${token}/pin`, { pin });
    setEnviando(false);
    setPin('');
    if (r.status === 200) return router.reload();
    if (r.status === 423) return setMsg(TRAVADO);
    if (r.status === 422) return setMsg(`PIN incorreto. Restam ${String(r.json.tentativas_restantes ?? 0)} tentativa(s).`);
    router.reload();
  };

  return (
    <form className="flex flex-1 flex-col gap-5" data-contract="assoc-membro-pin" onSubmit={(e) => { e.preventDefault(); void entrar(); }}>
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold leading-tight">Digite seu PIN</h1>
        <p className="text-muted-foreground">São os 4 números que a sua associação mandou junto com este link.</p>
      </div>

      <div className="flex justify-center gap-3 py-1" role="status" aria-label={`${pin.length} de 4 números digitados`}>
        {[0, 1, 2, 3].map((i) => (
          <span key={i} className={cn('size-4 rounded-full border-2 border-primary', i < pin.length ? 'bg-primary' : 'bg-transparent')} />
        ))}
      </div>

      <Label htmlFor="assoc-pin" className="sr-only">PIN</Label>
      <input
        id="assoc-pin"
        inputMode="numeric"
        autoComplete="one-time-code"
        maxLength={4}
        value={pin}
        onChange={(e) => setPin(e.target.value.replace(/\D/g, '').slice(0, 4))}
        className="sr-only"
        aria-describedby="assoc-pin-msg"
      />
      <div className="grid grid-cols-3 gap-2.5">
        {TECLAS.map((t, i) => (
          t === ''
            ? <span key={i} />
            : (
              <button
                key={i}
                type="button"
                onClick={() => tecla(t)}
                aria-label={t === 'apagar' ? 'Apagar' : `Número ${t}`}
                className="flex h-14 items-center justify-center rounded-xl border bg-card text-xl font-semibold active:bg-muted"
              >
                {t === 'apagar' ? <Delete className="size-5" aria-hidden="true" /> : t}
              </button>
            )
        ))}
      </div>

      <p id="assoc-pin-msg" className="min-h-5 text-sm text-destructive" role="alert">{msg}</p>
      <Button type="submit" size="lg" className="h-12 w-full text-base" disabled={!completo || enviando}>Entrar</Button>
    </form>
  );
}

function MeusDados({ token, dados, iniciais }: { token: string; dados: NonNullable<Props['dados']>; iniciais: Consentimento[] }) {
  const [consentimentos, setConsentimentos] = useState(iniciais);
  const [msg, setMsg] = useState<string | null>(null);
  const [revogando, setRevogando] = useState<string | null>(null);

  const revogar = async (finalidade: string) => {
    setRevogando(finalidade);
    const r = await enviar('DELETE', `/associacoes/m/${token}/consentimentos/${finalidade}`);
    setRevogando(null);
    if (r.status === 200) {
      setConsentimentos(r.json.consentimentos as Consentimento[]);
      return setMsg('Autorização retirada.');
    }
    if (r.status === 403 || r.status === 404) return router.reload();
    setMsg('Não foi possível retirar agora. Tente de novo.');
  };

  return (
    <div className="flex flex-1 flex-col gap-5" data-contract="assoc-membro-dados">
      <section className="flex flex-col gap-3">
        <h1 className="text-2xl font-semibold leading-tight">Meus dados</h1>
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-xl border bg-card p-4 text-sm">
          <dt className="text-muted-foreground">Nome</dt><dd className="font-medium">{dados.nome ?? '—'}</dd>
          <dt className="text-muted-foreground">Associação</dt><dd className="font-medium">{dados.associacao ?? '—'}</dd>
          <dt className="text-muted-foreground">Situação</dt><dd className="font-medium">{dados.situacao === 'ativa' ? 'Ativa' : 'Desligada'}</dd>
          <dt className="text-muted-foreground">Associada desde</dt><dd className="font-medium">{dataBr(dados.entrou_em)}</dd>
        </dl>
        <p className="text-sm text-muted-foreground">Algum dado errado? Fale com a sua associação.</p>
      </section>

      <section className="flex flex-col gap-3" aria-labelledby="assoc-autorizacoes">
        <h2 id="assoc-autorizacoes" className="flex items-center gap-2 text-lg font-semibold"><ShieldCheck className="size-5" aria-hidden="true" />Minhas autorizações</h2>
        <ul className="flex flex-col gap-2.5">
          {consentimentos.map((c) => {
            const f = FINALIDADES[c.finalidade] ?? { titulo: c.finalidade, texto: '' };
            return (
              <li key={c.finalidade} className="flex flex-col gap-2 rounded-xl border bg-card p-4" data-finalidade={c.finalidade}>
                <div className="flex items-start justify-between gap-3">
                  <span className="font-medium">{f.titulo}</span>
                  <span className={cn('shrink-0 text-sm', c.vigente ? 'text-foreground' : 'text-muted-foreground')}>
                    {c.vigente ? `Autorizado em ${dataBr(c.concedido_em)}` : 'Não autorizado'}
                  </span>
                </div>
                {f.texto && <p className="text-sm text-muted-foreground">{f.texto}</p>}
                {c.vigente && c.revogavel && (
                  <Button type="button" variant="outline" className="h-11 self-start" disabled={revogando !== null} onClick={() => void revogar(c.finalidade)}>
                    Retirar autorização
                  </Button>
                )}
              </li>
            );
          })}
        </ul>
        <p className="min-h-5 text-sm" role="status">{msg}</p>
      </section>
    </div>
  );
}

export default function Membro(props: Props) {
  if (props.erro === 'link_invalido' || props.etapa === null) {
    return <Moldura><LinkInvalido /></Moldura>;
  }
  if (props.etapa === 'pin') {
    return <Moldura><EtapaPin token={props.token} restantes={props.tentativas_restantes ?? 5} /></Moldura>;
  }
  return (
    <Moldura>
      <MeusDados token={props.token} dados={props.dados as NonNullable<Props['dados']>} iniciais={props.consentimentos ?? []} />
    </Moldura>
  );
}
