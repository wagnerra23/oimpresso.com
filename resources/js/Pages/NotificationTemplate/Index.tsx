// @memcofre: NotificationTemplate/Index · US-NOTIF-003 · seleção F3, flag OFF.
// Fonte: prototipo-ui/cowork/Wagner/notificacoes-page.jsx e .css.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Box, Grid, Inline, Stack } from '@/Components/layout';
import EmptyState from '@/Components/shared/EmptyState';

interface Model {
  name: string; subject: string; email_body: string; sms_body: string; whatsapp_text: string;
  auto_send: number | string | boolean; auto_send_sms: number | string | boolean;
  auto_send_wa_notif: number | string | boolean; cc: string; bcc: string; extra_tags: unknown;
}
type Models = Record<string, Model>;
export interface Props { general_notifications?: Models; customer_notifications?: Models; supplier_notifications?: Models }
type Channel = 'email' | 'sms' | 'whatsapp';
const CHANNELS: { key: Channel; label: string }[] = [
  { key: 'email', label: 'E-mail' }, { key: 'sms', label: 'SMS' }, { key: 'whatsapp', label: 'WhatsApp' },
];
const enabled = (value: number | string | boolean) => Number(value) === 1;

export default function NotificationTemplateIndex(props: Props) {
  const groups = useMemo(() => [
    { name: 'Notificações', models: props.general_notifications ?? {} },
    { name: 'Cliente', models: props.customer_notifications ?? {} },
    { name: 'Fornecedor', models: props.supplier_notifications ?? {} },
  ], [props.general_notifications, props.customer_notifications, props.supplier_notifications]);
  const models = useMemo(() => Object.assign({}, ...groups.map((group) => group.models)) as Models, [groups]);
  const [selected, setSelected] = useState('');
  const [channel, setChannel] = useState<Channel>('email');
  const [query, setQuery] = useState('');
  const search = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (!models[selected]) { setSelected(Object.keys(models)[0] ?? ''); setChannel('email'); }
  }, [models, selected]);
  useEffect(() => {
    const keydown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && document.activeElement === search.current) {
        setQuery(''); search.current?.blur(); return;
      }
      const target = event.target as HTMLElement;
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(target?.tagName ?? '') || target?.isContentEditable) return;
      if (event.key === '/') { event.preventDefault(); search.current?.focus(); }
    };
    window.addEventListener('keydown', keydown);
    return () => window.removeEventListener('keydown', keydown);
  }, []);
  const visible = groups.map((group) => ({ ...group, entries: Object.entries(group.models)
    .filter(([, model]) => model.name.toLocaleLowerCase('pt-BR').includes(query.toLocaleLowerCase('pt-BR'))) }))
    .filter((group) => group.entries.length > 0);
  const model = models[selected];
  const supplier = selected in (props.supplier_notifications ?? {});
  const body = model ? channel === 'email' ? model.email_body : channel === 'sms' ? model.sms_body : model.whatsapp_text : '';
  return <Stack gap={0}>
    <PageHeader title="Modelos de notificação" subtitle="Mensagens para clientes e fornecedores"
      actions={<Button asChild><a href="/notification-templates?legacy=1">Editar modelos</a></Button>} />
    <Deferred data={['general_notifications', 'customer_notifications', 'supplier_notifications']}
      fallback={<Box p={6} role="status" aria-busy="true">Carregando modelos de notificação…</Box>}>
      <Grid cols={1} gap={0} className="lg:grid-cols-[236px_minmax(0,1fr)]">
        <Box p={4} border data-contract="lista-modelos">
          <Stack gap={3}>
            <Input ref={search} aria-label="Buscar modelos" placeholder="Buscar modelos" value={query}
              onChange={(event) => setQuery(event.target.value)} />
            {visible.length === 0 && <EmptyState title="Nenhum modelo encontrado" description="Tente outro termo na busca." variant="search" />}
            {visible.map((group) => <Stack key={group.name} gap={1}>
              <h2 className="text-xs font-semibold text-muted-foreground">{group.name}</h2>
              {group.entries.map(([id, item]) => <Button key={id} variant={id === selected ? 'secondary' : 'ghost'}
                aria-pressed={id === selected} className="h-auto justify-start whitespace-normal text-left"
                onClick={() => { setSelected(id); setChannel('email'); }}>
                <Stack gap={1}><span>{item.name}</span><Inline gap={1} wrap>
                  {(enabled(item.auto_send) || enabled(item.auto_send_sms) || enabled(item.auto_send_wa_notif)) && <Badge variant="secondary">Automático</Badge>}
                  {!(item.email_body || item.sms_body || item.whatsapp_text) && <Badge variant="outline">Vazio</Badge>}
                </Inline></Stack>
              </Button>)}
            </Stack>)}
          </Stack>
        </Box>
        <Box p={6} className="min-w-0">
          {!model ? <EmptyState title="Nenhum modelo disponível" description="Os modelos disponíveis para sua empresa aparecerão aqui." /> :
            <Stack gap={4}>
              <h2 data-contract="cabecalho-modelo" className="text-lg font-semibold">{model.name}</h2>
              <Inline role="group" aria-label="Canais do modelo" gap={2} wrap>
                {CHANNELS.map((item) => <Button key={item.key} variant={channel === item.key ? 'secondary' : 'outline'}
                  aria-pressed={channel === item.key} disabled={selected === 'send_ledger' && item.key !== 'email'}
                  onClick={() => setChannel(item.key)}>{item.label}</Button>)}
              </Inline>
              {supplier && <Box data-contract="aviso-logo" p={3} bg="muted" rounded="sm">O logo da empresa é exibido somente nos e-mails.</Box>}
              {channel === 'email' && <dl className="space-y-2">
                <dt className="text-sm text-muted-foreground">Assunto</dt><dd>{model.subject || 'Sem assunto'}</dd>
                <dt className="text-sm text-muted-foreground">Cópia (CC)</dt><dd>{model.cc || 'Nenhuma'}</dd>
                <dt className="text-sm text-muted-foreground">Cópia oculta (BCC)</dt><dd>{model.bcc || 'Nenhuma'}</dd>
              </dl>}
              <Stack gap={2} data-contract="conteudo-salvo">
                <h3 className="text-sm font-medium">{channel === 'email' ? 'HTML salvo do e-mail' : 'Mensagem salva'}</h3>
                {body ? <Box p={4} bg="muted" rounded="sm"><pre className="whitespace-pre-wrap break-words text-sm">{body}</pre></Box>
                  : <EmptyState title="Este canal está vazio" description="Use Editar modelos para preencher a mensagem." />}
              </Stack>
            </Stack>}
        </Box>
      </Grid>
    </Deferred>
  </Stack>;
}
NotificationTemplateIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
