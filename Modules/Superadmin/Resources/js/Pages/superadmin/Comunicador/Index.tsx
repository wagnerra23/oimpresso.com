// @memcofre
//   tela: /superadmin/communicator
//   module: Superadmin
//   stories: US-SUPER-004 · thread Superadmin/05 (Blade/AdminLTE → Inertia)
//   permissao: superadmin
//
// Aviso em massa para os donos dos negócios da plataforma (e-mail + notificação no app).
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/superadmin-page.jsx → ViewComunicador() (L1224)
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-comunicador.md
//
// Destinatário é NEGÓCIO (o backend resolve o dono). O protótipo desenha grupos por status de
// assinatura, agendamento e taxa de abertura — nada disso existe no backend; ver charter
// §Divergências. Esta onda entrega o que o Blade fazia, sem inventar dado.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';
import { Skeleton } from '@/Components/ui/skeleton';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { plural } from '../_components/assinatura';

interface Negocio { id: number; nome: string }
interface Envio { id: number; assunto: string; resumo: string; destinatarios: number; enviado_em: string | null }
interface Props { negocios?: Negocio[]; historico?: Envio[] }

const dataHora = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

function ComunicadorIndex({ negocios, historico }: Props) {
  return (
    <div className="pb-8">
      <PageHeader title="Comunicador" subtitle="Aviso em massa para os negócios da plataforma — chega por e-mail e como notificação no app" />
      <div className="grid gap-4 px-6 pt-4 xl:grid-cols-[3fr_2fr]">
        <Deferred data="negocios" fallback={<Skeleton className="h-96 w-full" />}>
          <Compor negocios={negocios ?? []} />
        </Deferred>
        <Deferred data="historico" fallback={<Skeleton className="h-96 w-full" />}>
          <Historico envios={historico ?? []} />
        </Deferred>
      </div>
    </div>
  );
}

function Compor({ negocios }: { negocios: Negocio[] }) {
  const form = useForm({ recipients: [] as number[], subject: '', message: '' });
  const [filtro, setFiltro] = useState('');
  const [previa, setPrevia] = useState(false);
  const [confirmar, setConfirmar] = useState(false);
  const visiveis = useMemo(
    () => negocios.filter((n) => n.nome.toLowerCase().includes(filtro.trim().toLowerCase())),
    [negocios, filtro],
  );
  const sel = form.data.recipients;
  const alternar = (id: number) =>
    form.setData('recipients', sel.includes(id) ? sel.filter((x) => x !== id) : [...sel, id]);
  const pronto = sel.length > 0 && form.data.subject.trim() !== '' && form.data.message.trim() !== '';
  const enviar = () =>
    form.post('/superadmin/communicator/send', {
      preserveScroll: true,
      onSuccess: () => { form.reset(); setConfirmar(false); setPrevia(false); },
    });

  return (
    <Card data-contract="superadmin.comunicador.compor">
      <CardContent className="flex flex-col gap-4 p-4">
        <header className="flex items-baseline justify-between gap-2">
          <h2 className="font-medium">Compor mensagem</h2>
          <span className="text-xs text-muted-foreground tabular-nums">{plural(sel.length, 'negócio selecionado', 'negócios selecionados')}</span>
        </header>

        <section className="flex flex-col gap-2">
          <div className="flex items-center gap-2">
            <Input placeholder="Filtrar negócios" value={filtro} onChange={(e) => setFiltro(e.target.value)} />
            <Button type="button" variant="ghost" size="sm" onClick={() => form.setData('recipients', negocios.map((n) => n.id))}>Selecionar todos</Button>
            <Button type="button" variant="ghost" size="sm" onClick={() => form.setData('recipients', [])}>Limpar</Button>
          </div>
          <ul className="max-h-56 overflow-y-auto rounded-md border p-2 text-sm">
            {visiveis.map((n) => (
              <li key={n.id}>
                <label className="flex cursor-pointer items-center gap-2 py-0.5">
                  <Checkbox checked={sel.includes(n.id)} onCheckedChange={() => alternar(n.id)} />
                  <span className="truncate">{n.nome}</span>
                </label>
              </li>
            ))}
          </ul>
          <p className="text-xs text-muted-foreground">A mensagem vai para o dono de cada negócio — um dono com dois negócios recebe uma vez só.</p>
          {form.errors.recipients && <p className="text-xs text-destructive">{form.errors.recipients}</p>}
        </section>

        <Input aria-label="Assunto" placeholder="Assunto" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
        {form.errors.subject && <p className="text-xs text-destructive">{form.errors.subject}</p>}
        <div className="flex flex-col gap-1">
          <Textarea aria-label="Mensagem" rows={7} value={form.data.message} onChange={(e) => form.setData('message', e.target.value)}
            placeholder="Escreva em português claro. Diga o que muda, quando, e o que o negócio precisa fazer." />
          <span className="text-xs text-muted-foreground tabular-nums">{plural(form.data.message.length, 'caractere', 'caracteres')} · texto puro, quebras de linha preservadas</span>
          {form.errors.message && <p className="text-xs text-destructive">{form.errors.message}</p>}
        </div>

        {previa && (
          <div className="rounded-md border bg-muted/40 p-3 text-sm" data-contract="superadmin.comunicador.previa">
            <b>{form.data.subject || 'Sem assunto'}</b>
            <p className="whitespace-pre-wrap pt-2">{form.data.message || 'O corpo aparece aqui do jeito que o dono do negócio recebe.'}</p>
          </div>
        )}

        <footer className="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="ghost" onClick={() => setPrevia(!previa)}>{previa ? 'Fechar prévia' : 'Ver prévia'}</Button>
          {confirmar ? (
            <>
              <Button type="button" variant="ghost" onClick={() => setConfirmar(false)} disabled={form.processing}>Cancelar</Button>
              <Button type="button" onClick={enviar} disabled={form.processing}>
                {form.processing ? 'Enviando…' : `Confirmar envio para ${plural(sel.length, 'negócio', 'negócios')}`}
              </Button>
            </>
          ) : (
            <Button type="button" disabled={!pronto} onClick={() => setConfirmar(true)}>
              Enviar para {plural(sel.length, 'negócio', 'negócios')}
            </Button>
          )}
        </footer>
      </CardContent>
    </Card>
  );
}

function Historico({ envios }: { envios: Envio[] }) {
  return (
    <Card data-contract="superadmin.comunicador.historico">
      <CardContent className="flex flex-col gap-3 p-4">
        <header className="flex items-baseline justify-between">
          <h2 className="font-medium">Histórico de envios</h2>
          <span className="text-xs text-muted-foreground">{plural(envios.length, 'mensagem', 'mensagens')}</span>
        </header>
        {envios.length === 0 ? (
          <EmptyState title="Nenhum aviso enviado" description="Cada envio fica registrado aqui com o assunto, o alcance e a data." />
        ) : (
          <ul className="flex flex-col divide-y">
            {envios.map((m) => (
              <li key={m.id} className="flex flex-col gap-0.5 py-2 text-sm">
                <b className="truncate">{m.assunto}</b>
                <span className="line-clamp-2 text-xs text-muted-foreground">{m.resumo}</span>
                <small className="text-[11px] text-muted-foreground tabular-nums">
                  {plural(m.destinatarios, 'negócio', 'negócios')}
                  {m.enviado_em ? ` · ${dataHora.format(new Date(m.enviado_em))}` : ''}
                </small>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

ComunicadorIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ComunicadorIndex;
