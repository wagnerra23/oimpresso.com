// @memcofre: NotificationTemplate/Index · US-NOTIF-003 seleção + US-NOTIF-004 editor · flag useV2NotificationTemplates (default ON desde 2026-10-13).
// Fonte: prototipo-ui/cowork/Wagner/notificacoes-page.jsx e .css.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { Box, Grid, Inline, Stack } from '@/Components/layout';
import EmptyState from '@/Components/shared/EmptyState';
import {
  COM_AUTOMATICO, ehPersonalizado, gruposDeTags, iguais, inserirNaSelecao, payload, segmentosSms,
  tagsDesconhecidas, valoresDe, type Auto, type Campo, type Valores,
} from './_components/editor-modelo';

interface Model {
  name: string; subject: string; email_body: string; sms_body: string; whatsapp_text: string;
  auto_send: number | string | boolean; auto_send_sms: number | string | boolean;
  auto_send_wa_notif: number | string | boolean; cc: string; bcc: string; extra_tags: unknown;
}
type Models = Record<string, Model>;
export interface Props { general_notifications?: Models; customer_notifications?: Models; supplier_notifications?: Models }
type Channel = 'email' | 'sms' | 'whatsapp';
const CHANNELS: { key: Channel; label: string; campo: Campo }[] = [
  { key: 'email', label: 'E-mail', campo: 'email_body' }, { key: 'sms', label: 'SMS', campo: 'sms_body' },
  { key: 'whatsapp', label: 'WhatsApp', campo: 'whatsapp_text' },
];
const AUTOS: { key: Auto; label: string }[] = [
  { key: 'auto_send', label: 'E-mail' }, { key: 'auto_send_sms', label: 'SMS' }, { key: 'auto_send_wa_notif', label: 'WhatsApp' },
];

export default function NotificationTemplateIndex(props: Props) {
  const groups = useMemo(() => [
    { name: 'Notificações', models: props.general_notifications ?? {} },
    { name: 'Cliente', models: props.customer_notifications ?? {} },
    { name: 'Fornecedor', models: props.supplier_notifications ?? {} },
  ], [props.general_notifications, props.customer_notifications, props.supplier_notifications]);
  const models = useMemo(() => Object.assign({}, ...groups.map((group) => group.models)) as Models, [groups]);
  const salvos = useMemo(() => Object.fromEntries(Object.entries(models)
    .map(([key, m]) => [key, valoresDe(m as unknown as Record<string, unknown>)])) as Record<string, Valores>, [models]);
  const [edits, setEdits] = useState<Record<string, Valores>>({});
  const [selected, setSelected] = useState('');
  const [channel, setChannel] = useState<Channel>('email');
  const [query, setQuery] = useState('');
  const [abertos, setAbertos] = useState<Record<number, boolean>>({});
  const [erros, setErros] = useState<Record<string, string>>({});
  const [salvando, setSalvando] = useState(false);
  const search = useRef<HTMLInputElement>(null);
  const campos = useRef<Partial<Record<Campo, HTMLInputElement | HTMLTextAreaElement | null>>>({});
  const foco = useRef<Campo>('email_body');
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
  const atual = (key: string): Valores => edits[key] ?? salvos[key] ?? valoresDe({});
  const alterados = Object.keys(edits).filter((key) => salvos[key] && !iguais(atual(key), salvos[key]));
  const visible = groups.map((group) => ({ ...group, entries: Object.entries(group.models)
    .filter(([, model]) => model.name.toLocaleLowerCase('pt-BR').includes(query.toLocaleLowerCase('pt-BR'))) }))
    .filter((group) => group.entries.length > 0);
  const model = models[selected];
  const v = model ? atual(selected) : undefined;
  const supplier = selected in (props.supplier_notifications ?? {});
  const tags = useMemo(() => gruposDeTags(model?.extra_tags), [model]);
  const campoCorpo = CHANNELS.find((c) => c.key === channel)!.campo;
  const altera = (campo: Campo | Auto, valor: string | number) => setEdits((s) => ({
    ...s, [selected]: { ...atual(selected), [campo]: valor },
  }));
  const selecionar = (id: string) => { setSelected(id); setChannel('email'); foco.current = 'email_body'; };
  const inserir = (tag: string) => {
    if (!v) return;
    const alvo = channel === 'email' && foco.current === 'subject' ? 'subject' : campoCorpo;
    const el = campos.current[alvo];
    const texto = v[alvo];
    const r = inserirNaSelecao(texto, tag, el?.selectionStart ?? texto.length, el?.selectionEnd ?? texto.length);
    altera(alvo, r.texto);
    requestAnimationFrame(() => { el?.focus(); el?.setSelectionRange(r.cursor, r.cursor); });
  };
  const salvar = () => {
    const corpo = payload(Object.fromEntries(alterados.map((key) => [key, atual(key)])));
    setSalvando(true);
    router.post('/notification-templates', corpo as never, {
      preserveScroll: true, preserveState: true,
      onSuccess: () => { setEdits({}); setErros({}); },
      onError: (e) => setErros(e as Record<string, string>),
      onFinish: () => setSalvando(false),
    });
  };
  const enviarTeste = () => router.post('/notification-templates/test',
    { template_for: selected, canal: 'email' }, { preserveScroll: true, preserveState: true });
  const corpo = v ? v[campoCorpo] : '';
  const sms = segmentosSms(corpo);
  const desconhecidas = v ? tagsDesconhecidas(`${corpo} ${channel === 'email' ? v.subject : ''}`, tags) : [];
  const erro = (campo: Campo) => erros[`template_data.${selected}.${campo}`];
  const campoRef = (campo: Campo) => (el: HTMLInputElement | HTMLTextAreaElement | null) => { campos.current[campo] = el; };
  return <Stack gap={0}>
    <PageHeader title="Modelos de notificação" subtitle="Mensagens para clientes e fornecedores"
      actions={<Inline gap={2} wrap>
        {alterados.length > 0 && <Inline gap={2} data-contract="alteracoes">
          <span className="text-sm text-muted-foreground" aria-live="polite">
            {alterados.length} {alterados.length === 1 ? 'modelo alterado' : 'modelos alterados'}</span>
          <Button variant="ghost" onClick={() => { setEdits({}); setErros({}); }}>Descartar alterações</Button>
        </Inline>}
        <Button variant="outline" asChild><a href="/notification-templates?legacy=1">Editor clássico</a></Button>
        <Button disabled={alterados.length === 0 || salvando} onClick={salvar}>Salvar</Button>
      </Inline>} />
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
              {group.entries.map(([id, item]) => {
                const x = atual(id);
                return <Button key={id} variant={id === selected ? 'secondary' : 'ghost'}
                  aria-pressed={id === selected} className="h-auto justify-start whitespace-normal text-left"
                  onClick={() => selecionar(id)}>
                  <Stack gap={1}><span>{item.name}</span><Inline gap={1} wrap>
                    {alterados.includes(id) && <Badge variant="outline">Alterado</Badge>}
                    {(x.auto_send || x.auto_send_sms || x.auto_send_wa_notif) === 1 && <Badge variant="secondary">Automático</Badge>}
                    {!(x.email_body || x.sms_body || x.whatsapp_text) && <Badge variant="outline">Vazio</Badge>}
                  </Inline></Stack>
                </Button>;
              })}
            </Stack>)}
          </Stack>
        </Box>
        <Box p={6} className="min-w-0">
          {!model || !v ? <EmptyState title="Nenhum modelo disponível" description="Os modelos disponíveis para sua empresa aparecerão aqui." /> :
            <Stack gap={4}>
              <h2 data-contract="cabecalho-modelo" className="text-lg font-semibold">{model.name}</h2>
              <Inline role="group" aria-label="Canais do modelo" gap={2} wrap>
                {CHANNELS.map((item) => <Button key={item.key} variant={channel === item.key ? 'secondary' : 'outline'}
                  aria-pressed={channel === item.key} disabled={selected === 'send_ledger' && item.key !== 'email'}
                  onClick={() => { setChannel(item.key); foco.current = item.campo; }}>{item.label}</Button>)}
              </Inline>
              {supplier && <Box data-contract="aviso-logo" p={3} bg="muted" rounded="sm">O logo da empresa é exibido somente nos e-mails.</Box>}
              {channel === 'email' && <Grid cols={1} gap={3} className="md:grid-cols-2">
                <Stack gap={1} className="md:col-span-2"><Label htmlFor="nt-subject">Assunto</Label>
                  <Input id="nt-subject" ref={campoRef('subject')} value={v.subject}
                    onFocus={() => { foco.current = 'subject'; }} onChange={(e) => altera('subject', e.target.value)} /></Stack>
                {(['cc', 'bcc'] as const).map((c) => <Stack key={c} gap={1}>
                  <Label htmlFor={`nt-${c}`}>{c === 'cc' ? 'Cópia (CC)' : 'Cópia oculta (BCC)'}</Label>
                  <Input id={`nt-${c}`} type="email" value={v[c]} aria-invalid={!!erro(c)}
                    aria-describedby={erro(c) ? `nt-${c}-erro` : undefined} onChange={(e) => altera(c, e.target.value)} />
                  {erro(c) && <p id={`nt-${c}-erro`} className="text-sm text-destructive">{erro(c)}</p>}
                </Stack>)}
              </Grid>}
              <Stack gap={1} data-contract="editor-canal">
                <Label htmlFor="nt-corpo">{channel === 'email' ? 'Corpo do e-mail (HTML)' : channel === 'sms' ? 'Texto do SMS' : 'Texto do WhatsApp'}</Label>
                <Textarea id="nt-corpo" ref={campoRef(campoCorpo)} rows={8} value={corpo}
                  onFocus={() => { foco.current = campoCorpo; }} onChange={(e) => altera(campoCorpo, e.target.value)} />
                <Inline gap={3} wrap className="text-sm text-muted-foreground">
                  <span>{corpo.length} caracteres</span>
                  {channel === 'sms' && <span>{sms.segmentos} SMS · {sms.limite} por segmento{sms.acento ? ' (tem acento)' : ''}</span>}
                  {desconhecidas.length > 0 && <span className="text-warning">tag não reconhecida: {desconhecidas.join(', ')}</span>}
                </Inline>
              </Stack>
              {tags.length > 0 && <Stack gap={2} data-contract="tags-disponiveis">
                <h3 className="text-sm font-medium">Tags disponíveis</h3>
                {tags.map((grupo, i) => {
                  const fixas = grupo.filter((t) => !ehPersonalizado(t));
                  const extras = grupo.filter(ehPersonalizado);
                  return <Inline key={i} gap={1} wrap>
                    {[...fixas, ...(abertos[i] ? extras : [])].map((t) => <Button key={t} size="sm" variant="outline"
                      onMouseDown={(e) => e.preventDefault()} onClick={() => inserir(t)}>{t}</Button>)}
                    {extras.length > 0 && !abertos[i] && <Button size="sm" variant="ghost"
                      onClick={() => setAbertos((s) => ({ ...s, [i]: true }))}>+ {extras.length} personalizados</Button>}
                  </Inline>;
                })}
              </Stack>}
              {COM_AUTOMATICO.includes(selected) && <Stack gap={2} data-contract="envio-automatico">
                <h3 className="text-sm font-medium">Envio automático</h3>
                {AUTOS.map((a) => <Inline key={a.key} gap={2}>
                  <Switch id={`nt-${a.key}`} checked={v[a.key] === 1} onCheckedChange={(on) => altera(a.key, on ? 1 : 0)} />
                  <Label htmlFor={`nt-${a.key}`}>{a.label}</Label>
                </Inline>)}
              </Stack>}
              {channel === 'email' && <Inline gap={2} wrap>
                <Button variant="outline" disabled={alterados.includes(selected)} onClick={enviarTeste}>Enviar teste pra mim</Button>
                <span className="text-sm text-muted-foreground">
                  {alterados.includes(selected) ? 'Salve antes de testar: o teste usa a versão gravada.' : 'Envia a versão gravada para o seu e-mail.'}</span>
              </Inline>}
            </Stack>}
        </Box>
      </Grid>
    </Deferred>
  </Stack>;
}
NotificationTemplateIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
