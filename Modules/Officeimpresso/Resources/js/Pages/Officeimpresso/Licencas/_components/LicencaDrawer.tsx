// Drawer PT-02 da licença (thread Officeimpresso/06 PR-b): ficha do equipamento + histórico de
// acessos e bloqueios + liberar/bloquear com motivo obrigatório. Senha nunca chega aqui (DTO no
// controller). O motivo vai para o histórico (`licenca_log`, admin_action) — a mensagem que o
// desktop recebe ao ser recusado é outro campo e esta tela não a reescreve.
import { router } from '@inertiajs/react';
import { Fragment, useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';
import StatusBadge from '@/Components/shared/StatusBadge';

export interface Evento {
  id: number; quando: string | null; evento: string; origem: string; ip: string | null; rota: string | null;
  http_status: number | null; erro: string | null; motivo: string | null; autor: string | null;
}
export interface Detalhe {
  ficha: {
    id: number; business_id: number; empresa: string | null; hostname: string | null; user_win: string | null;
    hd: string | null; sistema_operacional: string | null; ip_interno: string | null; processador: string | null;
    memoria: string | null; pasta_instalacao: string | null; versao_exe: string | null; versao_banco: string | null;
    versao_obrigatoria: string | null; created_at: string | null; dt_ultimo_acesso: string | null;
    dt_ultima_assistencia: string | null; dt_validade: string | null; bloqueado: boolean; motivo: string | null;
  };
  historico: Evento[];
}

const quando = (d: string | null) => (d ? new Date(d.replace(' ', 'T')).toLocaleString('pt-BR') : '—');
const ROTULO: Record<string, string> = {
  maquina_bloqueada: 'Máquina bloqueada', maquina_liberada: 'Máquina liberada',
  login_success: 'Autorização concedida', login_error: 'Autorização negada',
};

export default function LicencaDrawer({ id, detalhe, podeGerenciar, onClose }: {
  id: number | null; detalhe?: Detalhe | null; podeGerenciar: boolean; onClose: () => void;
}) {
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  useEffect(() => {
    setMotivo(''); setErro(null);
    if (id) router.reload({ only: ['detalhe'], data: { licenca: id } });
  }, [id]);

  const f = detalhe && id && detalhe.ficha.id === id ? detalhe.ficha : null;
  const t = motivo.trim();
  const ok = t.length >= 5 && motivo.length <= 500;

  const agir = () => {
    if (!f || !ok) return;
    setEnviando(true);
    router.post(`/officeimpresso/licenca_computador/${f.id}/toggle-block`, { bloquear: !f.bloqueado, motivo: t }, {
      preserveScroll: true, preserveState: true,
      onSuccess: () => { setMotivo(''); setErro(null); router.reload({ only: ['detalhe', 'licencas'], data: { licenca: f.id } }); },
      onError: (e) => setErro(e.motivo ?? e.bloquear ?? 'Não foi possível concluir.'),
      onFinish: () => setEnviando(false),
    });
  };

  const linhas: [string, string | null][] = f ? [
    ['Empresa', f.empresa ? `${f.empresa} (biz #${f.business_id})` : `biz #${f.business_id}`],
    ['Usuário do Windows', f.user_win], ['HD', f.hd], ['Sistema', f.sistema_operacional], ['IP interno', f.ip_interno],
    ['Processador', f.processador], ['Memória', f.memoria], ['Pasta de instalação', f.pasta_instalacao],
    ['Versão', `exe ${f.versao_exe ?? '—'} · banco ${f.versao_banco ?? '—'}${f.versao_obrigatoria ? ` · obrigatória ${f.versao_obrigatoria}` : ''}`],
    ['Registrada', quando(f.created_at)], ['Último acesso', quando(f.dt_ultimo_acesso)],
    ['Última assistência', quando(f.dt_ultima_assistencia)], ['Validade', f.dt_validade ? quando(f.dt_validade).slice(0, 10) : '—'],
    ['Mensagem ao desktop', f.motivo],
  ] : [];

  return (
    <Sheet open={id !== null} onOpenChange={(o) => !o && onClose()}>
      <SheetContent data-contract="drawer" side="right" className="cw-sheet flex w-[760px] flex-col p-0 sm:max-w-[760px]"
        onEscapeKeyDown={(e) => e.preventDefault()}>
        <SheetHeader className="border-b px-6 py-4">
          <SheetTitle>{f ? (f.hostname || f.user_win || 'Máquina sem hostname') : 'Licença'}</SheetTitle>
          <SheetDescription>Ficha do equipamento e histórico · senha nunca é exibida</SheetDescription>
          {f && <StatusBadge kind="licenca" value={f.bloqueado ? 'maquina_bloqueada' : 'ativa'} label={f.bloqueado ? 'Bloqueada' : 'Ativa'} />}
        </SheetHeader>
        <div className="flex-1 space-y-6 overflow-y-auto px-6 py-4 text-sm">
          {!detalhe || (id && detalhe.ficha.id !== id) ? (
            detalhe === null ? <p className="text-muted-foreground">Licença não encontrada ou fora do seu negócio.</p> : <Skeleton className="h-64 w-full" />
          ) : (
            <>
              <section>
                <h3 className="mb-2 font-semibold">Identificação</h3>
                <dl className="grid grid-cols-[180px_1fr] gap-x-4 gap-y-1">
                  {linhas.map(([k, v]) => (
                    <Fragment key={k}><dt className="text-muted-foreground">{k}</dt><dd className="break-words">{v || '—'}</dd></Fragment>
                  ))}
                </dl>
              </section>
              <section>
                <h3 className="mb-2 font-semibold">Histórico de acessos e bloqueios ({detalhe.historico.length})</h3>
                {detalhe.historico.length === 0 ? <p className="text-muted-foreground">Nenhum evento registrado para esta máquina.</p> : (
                  <ul className="space-y-2">
                    {detalhe.historico.map((g) => (
                      <li key={g.id} className="border-l-2 pl-3">
                        <b>{ROTULO[g.evento] ?? g.evento}</b>
                        <div className="text-xs text-muted-foreground">{quando(g.quando)} · {g.origem}{g.ip ? ` · ${g.ip}` : ''}{g.http_status ? ` · HTTP ${g.http_status}` : ''}{g.erro ? ` · ${g.erro}` : ''}</div>
                        {g.motivo && <div className="text-xs">Motivo: {g.motivo}{g.autor ? ` — ${g.autor}` : ''}</div>}
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            </>
          )}
        </div>
        {f && podeGerenciar && (
          <footer className="space-y-2 border-t px-6 py-4">
            <Textarea value={motivo} rows={3} maxLength={500} onChange={(e) => setMotivo(e.target.value)}
              aria-label="Motivo (obrigatório)" placeholder="Motivo (obrigatório) — ex: contrato cancelado em 18/08" />
            <div className="flex items-center justify-between gap-2">
              <span className={`text-xs ${erro ? 'text-destructive' : 'text-muted-foreground'}`} role={erro ? 'alert' : undefined}>
                {erro ?? (t.length > 0 && t.length < 5 ? 'Motivo precisa ter pelo menos 5 caracteres.' : `Fica no histórico da máquina · ${motivo.length}/500`)}
              </span>
              <Button variant={f.bloqueado ? 'outline' : 'destructive'} disabled={!ok || enviando} onClick={agir}>
                {f.bloqueado ? 'Liberar máquina' : 'Bloquear máquina'}
              </Button>
            </div>
          </footer>
        )}
      </SheetContent>
    </Sheet>
  );
}
