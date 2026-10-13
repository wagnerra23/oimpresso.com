// Ficha da empresa licenciada — Officeimpresso (dados, assinatura e máquinas do desktop WR Comercial).
//
//   rotas:    /officeimpresso/computadores (empresa da sessão) e
//             /officeimpresso/licenca_computado/licencas/{id} (qualquer empresa — decisão D1 [W] 2026-10-12),
//             atrás da flag useV2OfficeimpressoEmpresa; flag OFF = Blade computadores, rota de fuga
//   padrão:   PT-03 Detalhe
//   charter:  ./Show.charter.md · casos: ./Show.casos.md
//
// Ações: "Editar" (configuração, Dialog) e bloquear/liberar a empresa (confirmação) — empresa-parity
// 16-21; bloquear/liberar máquina com motivo (item 34), o mesmo POST do drawer da lista de licenças.
// `permissions` só esconde botão: quem autoriza é o controller (authorizeEmpresa/authorizeGerenciar).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { ClipboardList } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import { Skeleton } from '@/Components/ui/skeleton';
import EmptyState from '@/Components/shared/EmptyState';
import StatusBadge from '@/Components/shared/StatusBadge';

interface Empresa {
  id: number; name: string; razao_social: string | null; rua: string | null; telefone: string | null;
  versao_obrigatoria: string | null; versao_disponivel: string | null; caminho_banco_servidor: string | null;
  ultimo_acesso: string | null; bloqueado: boolean; pacote_url: string;
}
interface Computador {
  id: number; dt_cadastro: string | null; user_win: string | null; pasta_instalacao: string | null;
  versao_exe: string | null; ip_interno: string | null; caminho_banco: string | null;
  dt_ultimo_acesso: string | null; bloqueado: boolean;
}
interface Assinatura { limite_maquinas: number | null; fim: string | null; dias_restantes: number | null }
interface Permissoes { empresa: boolean; gerenciar: boolean }
interface Props { empresa: Empresa; permissions: Permissoes; computadores?: Computador[]; assinatura?: Assinatura }

const dataHoraBr = (d: string | null) => (d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const corta = (s: string | null, n: number) => (!s ? '—' : s.length > n ? `${s.slice(0, n)}…` : s);

function EmpresaShow({ empresa, permissions, computadores, assinatura }: Props) {
  const [aviso, setAviso] = useState<string | null>(null);
  return (
    <div className="pb-8">
      <div data-contract="header">
        <PageHeader title="Licenças Office Impresso" subtitle="Gestão de desktops Delphi por empresa"
          actions={
            <Button asChild variant="ghost" size="sm">
              <a href={`${typeof window !== 'undefined' ? window.location.pathname : ''}?legacy=1`}>Abrir na tela antiga</a>
            </Button>
          } />
      </div>
      <div className="flex flex-col gap-4 px-6 pt-4">
        <section data-contract="ficha" className="rounded-md border p-4" aria-labelledby="oi-emp-nome">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <h2 id="oi-emp-nome" className="text-lg font-semibold">{empresa.name}</h2>
              <p className="text-sm text-muted-foreground">{empresa.razao_social || '—'}</p>
            </div>
            <StatusBadge kind="licenca" value={empresa.bloqueado ? 'empresa_bloqueada' : 'ativa'} label={empresa.bloqueado ? 'Bloqueada' : 'Ativa'} />
          </div>
          <dl className="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <Campo rotulo="Endereço" valor={empresa.rua} />
            <Campo rotulo="Telefone" valor={empresa.telefone} />
            <Campo rotulo="Versão obrigatória" valor={empresa.versao_obrigatoria} mono />
            <Campo rotulo="Versão disponível" valor={empresa.versao_disponivel} mono />
            <Campo rotulo="Caminho do banco" valor={empresa.caminho_banco_servidor} mono />
            <Campo rotulo="Último acesso" valor={empresa.ultimo_acesso ? dataHoraBr(empresa.ultimo_acesso) : null} mono />
            <Deferred data="assinatura" fallback={<Skeleton className="h-5 w-48" />}>
              <BlocoAssinatura assinatura={assinatura} />
            </Deferred>
          </dl>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <Button asChild variant="outline" size="sm"><a href={empresa.pacote_url}>Ver pacote</a></Button>
            {permissions.empresa && (
              <>
                <EditarConfiguracao empresa={empresa} onSalvo={() => setAviso('Dados da empresa atualizados.')} />
                <BloquearEmpresa empresa={empresa} onFeito={setAviso} />
              </>
            )}
            {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}
          </div>
        </section>

        <section data-contract="computadores" className="flex flex-col gap-2" aria-labelledby="oi-emp-maquinas">
          <div className="flex items-center justify-between">
            <h3 id="oi-emp-maquinas" className="font-semibold">
              Computadores{computadores && <span className="text-muted-foreground"> ({computadores.length})</span>}
            </h3>
            <Button asChild variant="ghost" size="sm">
              <a href={`/officeimpresso/licenca_log?business_id=${empresa.id}`}><ClipboardList className="size-4" /> Ver log da empresa</a>
            </Button>
          </div>
          <Deferred data="computadores" fallback={<Skeleton className="h-48 w-full" />}>
            <Tabela computadores={computadores ?? []} podeGerenciar={permissions.gerenciar} onFeito={setAviso} />
          </Deferred>
        </section>
      </div>
    </div>
  );
}

// Modal de configuração (empresa-parity 16, 18-21): 3 campos; Cancelar descarta e o form volta ao
// estado da empresa ao fechar. `officeimpresso_numerodemaquinas` não entra — a Blade não tem o input.
function EditarConfiguracao({ empresa, onSalvo }: { empresa: Empresa; onSalvo: () => void }) {
  const [aberto, setAberto] = useState(false);
  const inicial = {
    caminho_banco_servidor: empresa.caminho_banco_servidor ?? '',
    versao_obrigatoria: empresa.versao_obrigatoria ?? '',
    versao_disponivel: empresa.versao_disponivel ?? '',
  };
  const form = useForm(inicial);
  const abrir = (v: boolean) => { if (!v) { form.setData(inicial); form.clearErrors(); } setAberto(v); };
  const salvar = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/officeimpresso/licenca_computador/businessupdate/${empresa.id}`, {
      preserveScroll: true,
      onSuccess: () => { setAberto(false); onSalvo(); },
    });
  };
  const campos: Array<[keyof typeof inicial, string]> = [
    ['caminho_banco_servidor', 'Caminho do banco no servidor'],
    ['versao_obrigatoria', 'Versão obrigatória'],
    ['versao_disponivel', 'Versão disponível'],
  ];
  return (
    <>
      <Button variant="outline" size="sm" onClick={() => abrir(true)}>Editar</Button>
      <Dialog open={aberto} onOpenChange={abrir}>
        <DialogContent>
          <form onSubmit={salvar} className="flex flex-col gap-3">
            <DialogHeader>
              <DialogTitle>Configuração do desktop</DialogTitle>
              <DialogDescription>{empresa.name}</DialogDescription>
            </DialogHeader>
            {campos.map(([campo, rotulo]) => (
              <div key={campo} className="flex flex-col gap-1">
                <Label htmlFor={`oi-emp-${campo}`}>{rotulo}</Label>
                <Input id={`oi-emp-${campo}`} value={form.data[campo]} onChange={(e) => form.setData(campo, e.target.value)}
                  aria-invalid={form.errors[campo] ? true : undefined} />
                {form.errors[campo] && <p className="text-xs text-destructive">{form.errors[campo]}</p>}
              </div>
            ))}
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={() => abrir(false)}>Cancelar</Button>
              <Button type="submit" disabled={form.processing}>Salvar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

// Bloquear/liberar a empresa inteira (empresa-parity 17): derruba TODOS os desktops do cliente, então
// pede confirmação e manda a intenção — duplo clique não desfaz o bloqueio.
function BloquearEmpresa({ empresa, onFeito }: { empresa: Empresa; onFeito: (m: string) => void }) {
  const bloquear = !empresa.bloqueado;
  const confirmar = () => router.post(`/officeimpresso/licenca_computador/businessbloqueado/${empresa.id}`, { bloquear }, {
    preserveScroll: true,
    // O diálogo já fechou no clique: o resultado (inclusive o erro) sai no aviso da ficha.
    onSuccess: () => onFeito(bloquear ? 'Empresa bloqueada.' : 'Empresa liberada.'),
    onError: (e) => onFeito(e.bloquear ?? 'Não foi possível concluir.'),
  });
  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button variant="outline" size="sm">{bloquear ? 'Bloquear empresa' : 'Liberar empresa'}</Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{bloquear ? 'Bloquear' : 'Liberar'} {empresa.name}?</AlertDialogTitle>
          <AlertDialogDescription>
            {bloquear
              ? 'Todos os computadores desta empresa deixam de abrir o Office Impresso.'
              : 'Os computadores desta empresa voltam a abrir o Office Impresso, exceto os bloqueados um a um.'}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction onClick={confirmar}>{bloquear ? 'Bloquear' : 'Liberar'}</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function Campo({ rotulo, valor, mono }: { rotulo: string; valor: string | null; mono?: boolean }) {
  return (
    <div className="flex gap-2">
      <dt className="text-muted-foreground">{rotulo}:</dt>
      <dd className={mono ? 'font-mono text-xs leading-5' : undefined}>{valor || '—'}</dd>
    </div>
  );
}

// Os blocos só existem se houver pacote/assinatura — o Blade os omite em vez de mostrar "—".
function BlocoAssinatura({ assinatura }: { assinatura?: Assinatura }) {
  if (!assinatura) return null;
  return (
    <>
      {assinatura.limite_maquinas !== null && (
        <Campo rotulo="Limite de Máquinas" valor={assinatura.limite_maquinas === 0 ? 'Ilimitado' : String(assinatura.limite_maquinas)} />
      )}
      {assinatura.fim !== null && <Campo rotulo="Data final" valor={assinatura.fim} />}
      {assinatura.dias_restantes !== null && <Campo rotulo="Restante" valor={`${assinatura.dias_restantes} dia (s)`} />}
    </>
  );
}

function Tabela({ computadores, podeGerenciar, onFeito }: {
  computadores: Computador[]; podeGerenciar: boolean; onFeito: (m: string) => void;
}) {
  const [alvo, setAlvo] = useState<Computador | null>(null);
  if (computadores.length === 0) return <EmptyState title="Nenhum registro encontrado" description="A empresa ainda não tem máquina registrada." />;
  return (
    <div data-contract="grade" className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead><tr className="border-b text-left text-xs text-muted-foreground">
          <th className="py-2">#</th><th>Cadastro</th><th>Máquina</th><th>Executável</th><th>Versão</th>
          <th>IP</th><th>Banco</th><th>Último acesso</th><th>Status</th><th><span className="sr-only">Ações</span></th>
        </tr></thead>
        <tbody>
          {computadores.map((c) => (
            <tr key={c.id} className="border-b align-top">
              <td className="py-2 font-mono text-xs">{c.id}</td>
              <td className="font-mono text-xs">{c.dt_cadastro ?? '—'}</td>
              <td className="font-semibold">{c.user_win || '—'}</td>
              <td className="font-mono text-xs" title={c.pasta_instalacao ?? undefined}>{corta(c.pasta_instalacao, 30)}</td>
              <td className="font-mono text-xs">{c.versao_exe || '—'}</td>
              <td className="font-mono text-xs">{c.ip_interno || '—'}</td>
              <td className="font-mono text-xs" title={c.caminho_banco ?? undefined}>{corta(c.caminho_banco, 20)}</td>
              <td className="font-mono text-xs">{c.dt_ultimo_acesso ?? '—'}</td>
              <td><StatusBadge kind="licenca" value={c.bloqueado ? 'maquina_bloqueada' : 'ativa'} label={c.bloqueado ? 'Bloqueada' : 'Ativa'} /></td>
              <td className="whitespace-nowrap">
                {podeGerenciar && (
                  <Button variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => setAlvo(c)}
                    aria-label={`${c.bloqueado ? 'Liberar' : 'Bloquear'} máquina ${c.user_win || c.id}`}>
                    {c.bloqueado ? 'Liberar' : 'Bloquear'}
                  </Button>
                )}
                <a className="text-xs text-primary hover:underline" href={`/officeimpresso/licenca_log?licenca_id=${c.id}`}>Log</a>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <BloquearMaquina alvo={alvo} onFechar={() => setAlvo(null)} onFeito={onFeito} />
    </div>
  );
}

// Bloquear/liberar uma máquina com motivo obrigatório (5 a 500 letras). Mesmo POST e mesmas regras do
// drawer da lista de licenças: o motivo vai ao histórico (licenca_log), a mensagem do desktop não muda.
function BloquearMaquina({ alvo, onFechar, onFeito }: {
  alvo: Computador | null; onFechar: () => void; onFeito: (m: string) => void;
}) {
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  const t = motivo.trim();
  const ok = t.length >= 5 && motivo.length <= 500;
  const fechar = () => { setMotivo(''); setErro(null); onFechar(); };
  const agir = (e: FormEvent) => {
    e.preventDefault();
    if (!alvo || !ok) return;
    setEnviando(true);
    router.post(`/officeimpresso/licenca_computador/${alvo.id}/toggle-block`, { bloquear: !alvo.bloqueado, motivo: t }, {
      preserveScroll: true, preserveState: true,
      onSuccess: () => {
        onFeito(alvo.bloqueado ? 'Máquina liberada.' : 'Máquina bloqueada.');
        fechar();
        router.reload({ only: ['computadores'] });
      },
      onError: (er) => setErro(er.motivo ?? er.bloquear ?? 'Não foi possível concluir.'),
      onFinish: () => setEnviando(false),
    });
  };
  return (
    <Dialog open={alvo !== null} onOpenChange={(v) => { if (!v) fechar(); }}>
      <DialogContent>
        {alvo && (
          <form onSubmit={agir} className="flex flex-col gap-3">
            <DialogHeader>
              <DialogTitle>{alvo.bloqueado ? 'Liberar' : 'Bloquear'} máquina {alvo.user_win || `#${alvo.id}`}</DialogTitle>
              <DialogDescription>
                {alvo.bloqueado ? 'A máquina volta a abrir o Office Impresso.' : 'A máquina deixa de abrir o Office Impresso.'}
              </DialogDescription>
            </DialogHeader>
            <Label htmlFor="oi-emp-motivo">Motivo (obrigatório)</Label>
            <Textarea id="oi-emp-motivo" value={motivo} rows={3} maxLength={500} onChange={(e) => setMotivo(e.target.value)}
              placeholder="ex: máquina trocada pelo cliente" aria-invalid={erro ? true : undefined} />
            <p className={`text-xs ${erro ? 'text-destructive' : 'text-muted-foreground'}`} role={erro ? 'alert' : undefined}>
              {erro ?? (t.length > 0 && t.length < 5 ? 'Motivo precisa ter pelo menos 5 caracteres.' : `Fica no histórico da máquina · ${motivo.length}/500`)}
            </p>
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={fechar}>Cancelar</Button>
              <Button type="submit" variant={alvo.bloqueado ? 'outline' : 'destructive'} disabled={!ok || enviando}>
                {alvo.bloqueado ? 'Liberar máquina' : 'Bloquear máquina'}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

EmpresaShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default EmpresaShow;
