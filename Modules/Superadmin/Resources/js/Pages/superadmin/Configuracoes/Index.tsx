// @memcofre
//   tela: /superadmin/settings
//   module: Superadmin
//   stories: US-SUPER-008 · thread Superadmin/05 parte 2 (Blade/AdminLTE → Inertia)
//   permissao: superadmin
//
// Configurações que valem para a plataforma inteira (aplicação, cobrança, SMTP, gateways,
// tempo real, backup/cron, JS/CSS extra). Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/superadmin-page.jsx → ViewConfig() (L1369)
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-configuracoes.md
//
// SEGREDO (senha, chave secreta, token) nunca chega aqui: o backend manda só `segredos[chave]`
// = definido sim/não. O campo nasce vazio e só é enviado se for preenchido — vazio = manter.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import { Skeleton } from '@/Components/ui/skeleton';
import { PageHeader } from '@/Components/PageHeader';
import { Select, plural } from '../_components/assinatura';

type Opcao = { v: string; label: string };
interface Config {
  valores: Record<string, string>;
  segredos: Record<string, boolean>;
  opcoes: { moedas: Opcao[]; idiomas: Opcao[]; mail: Opcao[]; backup: Opcao[] };
  cron: string;
  versao: string;
}
type Tipo = 'texto' | 'numero' | 'segredo' | 'chave' | 'area' | 'codigo' | `lista:${keyof Config['opcoes'] | 'paypal' | 'pesapal'}`;
interface Campo { k: string; l: string; t: Tipo }
interface Secao { id: string; titulo: string; desc: string; campos: Campo[]; aviso?: string }

const FIXAS: Record<string, Opcao[]> = {
  paypal: [{ v: 'sandbox', label: 'Sandbox (teste)' }, { v: 'live', label: 'Produção' }],
  pesapal: [{ v: 'false', label: 'Teste' }, { v: 'true', label: 'Produção' }],
};

const SECOES: Secao[] = [
  { id: 'app', titulo: 'Aplicação', desc: 'Nome exibido, moeda, idioma e cadastro de novos negócios.', campos: [
    { k: 'APP_NAME', l: 'Nome da aplicação', t: 'texto' }, { k: 'APP_TITLE', l: 'Título da aplicação', t: 'texto' },
    { k: 'app_currency_id', l: 'Moeda padrão', t: 'lista:moedas' }, { k: 'APP_LOCALE', l: 'Idioma padrão', t: 'lista:idiomas' },
    { k: 'ALLOW_REGISTRATION', l: 'Cadastro próprio de negócios', t: 'chave' },
    { k: 'superadmin_enable_register_tc', l: 'Exigir aceite dos termos no cadastro', t: 'chave' },
    { k: 'superadmin_register_tc', l: 'Termos e condições do cadastro (HTML)', t: 'area' },
    { k: 'enable_business_based_username', l: 'Usuário com sufixo do negócio', t: 'chave' },
    { k: 'GOOGLE_MAP_API_KEY', l: 'Chave do Google Maps', t: 'texto' },
  ] },
  { id: 'cobranca', titulo: 'Dados de cobrança', desc: 'Quem emite a fatura da assinatura e quando avisar o vencimento.', campos: [
    { k: 'invoice_business_name', l: 'Razão social', t: 'texto' }, { k: 'email', l: 'E-mail', t: 'texto' },
    { k: 'invoice_business_landmark', l: 'Endereço', t: 'texto' }, { k: 'invoice_business_city', l: 'Cidade', t: 'texto' },
    { k: 'invoice_business_state', l: 'Estado', t: 'texto' }, { k: 'invoice_business_zip', l: 'CEP', t: 'texto' },
    { k: 'invoice_business_country', l: 'País', t: 'texto' },
    { k: 'package_expiry_alert_days', l: 'Avisar o vencimento do pacote com quantos dias', t: 'numero' },
  ] },
  { id: 'smtp', titulo: 'E-mail (SMTP)', desc: 'Servidor de saída usado por avisos, boletos e comunicador.', campos: [
    { k: 'MAIL_MAILER', l: 'Driver', t: 'lista:mail' }, { k: 'MAIL_HOST', l: 'Host', t: 'texto' }, { k: 'MAIL_PORT', l: 'Porta', t: 'texto' },
    { k: 'MAIL_USERNAME', l: 'Usuário', t: 'texto' }, { k: 'MAIL_PASSWORD', l: 'Senha', t: 'segredo' },
    { k: 'MAIL_ENCRYPTION', l: 'Criptografia', t: 'texto' }, { k: 'MAIL_FROM_ADDRESS', l: 'Remetente', t: 'texto' },
    { k: 'MAIL_FROM_NAME', l: 'Nome do remetente', t: 'texto' },
    { k: 'allow_email_settings_to_businesses', l: 'Negócios podem configurar o próprio e-mail', t: 'chave' },
    { k: 'enable_new_business_registration_notification', l: 'Avisar quando um negócio se cadastra', t: 'chave' },
    { k: 'enable_new_subscription_notification', l: 'Avisar quando entra uma assinatura', t: 'chave' },
    { k: 'enable_welcome_email', l: 'Enviar e-mail de boas-vindas', t: 'chave' },
    { k: 'welcome_email_subject', l: 'Assunto do e-mail de boas-vindas', t: 'texto' },
    { k: 'welcome_email_body', l: 'Corpo do e-mail de boas-vindas (HTML)', t: 'area' },
  ] },
  { id: 'gateways', titulo: 'Gateways de pagamento', desc: 'Meios de cobrança oferecidos na tela de assinatura.', campos: [
    { k: 'enable_offline_payment', l: 'Pagamento offline', t: 'chave' }, { k: 'offline_payment_details', l: 'Instruções do pagamento offline', t: 'area' },
    { k: 'STRIPE_PUB_KEY', l: 'Stripe · chave pública', t: 'texto' }, { k: 'STRIPE_SECRET_KEY', l: 'Stripe · chave secreta', t: 'segredo' },
    { k: 'PAYPAL_MODE', l: 'PayPal · modo', t: 'lista:paypal' },
    { k: 'PAYPAL_SANDBOX_API_USERNAME', l: 'PayPal teste · usuário', t: 'texto' },
    { k: 'PAYPAL_SANDBOX_API_PASSWORD', l: 'PayPal teste · senha', t: 'segredo' }, { k: 'PAYPAL_SANDBOX_API_SECRET', l: 'PayPal teste · secret', t: 'segredo' },
    { k: 'PAYPAL_LIVE_API_USERNAME', l: 'PayPal produção · usuário', t: 'texto' },
    { k: 'PAYPAL_LIVE_API_PASSWORD', l: 'PayPal produção · senha', t: 'segredo' }, { k: 'PAYPAL_LIVE_API_SECRET', l: 'PayPal produção · secret', t: 'segredo' },
    { k: 'RAZORPAY_KEY_ID', l: 'Razorpay · key id', t: 'texto' }, { k: 'RAZORPAY_KEY_SECRET', l: 'Razorpay · secret', t: 'segredo' },
    { k: 'PESAPAL_CONSUMER_KEY', l: 'Pesapal · consumer key', t: 'texto' }, { k: 'PESAPAL_CONSUMER_SECRET', l: 'Pesapal · consumer secret', t: 'segredo' },
    { k: 'PESAPAL_LIVE', l: 'Pesapal · ambiente', t: 'lista:pesapal' },
    { k: 'PAYSTACK_PUBLIC_KEY', l: 'Paystack · chave pública', t: 'texto' }, { k: 'PAYSTACK_SECRET_KEY', l: 'Paystack · chave secreta', t: 'segredo' },
    { k: 'FLUTTERWAVE_PUBLIC_KEY', l: 'Flutterwave · chave pública', t: 'texto' },
    { k: 'FLUTTERWAVE_SECRET_KEY', l: 'Flutterwave · chave secreta', t: 'segredo' }, { k: 'FLUTTERWAVE_ENCRYPTION_KEY', l: 'Flutterwave · chave de criptografia', t: 'segredo' },
  ] },
  { id: 'pusher', titulo: 'Notificações em tempo real', desc: 'Credenciais Pusher para notificação no app.', campos: [
    { k: 'PUSHER_APP_ID', l: 'App ID', t: 'texto' }, { k: 'PUSHER_APP_KEY', l: 'App key', t: 'texto' },
    { k: 'PUSHER_APP_SECRET', l: 'App secret', t: 'segredo' }, { k: 'PUSHER_APP_CLUSTER', l: 'Cluster', t: 'texto' },
  ] },
  { id: 'backup', titulo: 'Backup', desc: 'Para onde vai a cópia do banco.', campos: [
    { k: 'BACKUP_DISK', l: 'Destino do backup', t: 'lista:backup' }, { k: 'DROPBOX_ACCESS_TOKEN', l: 'Dropbox · token de acesso', t: 'segredo' },
  ] },
  { id: 'extra', titulo: 'JS e CSS adicionais', desc: 'Injeção de código nas telas do cliente — use com cuidado.',
    aviso: 'Código injetado aqui roda na sessão de todos os clientes.', campos: [
      { k: 'additional_css', l: 'CSS extra', t: 'codigo' }, { k: 'additional_js', l: 'JS extra', t: 'codigo' },
    ] },
];

const INICIAL = SECOES[0] as Secao;
const ligado = (v: string | undefined) => v === '1' || v === 'true';

function ConfiguracoesIndex({ config }: { config?: Config }) {
  return (
    <div className="pb-8">
      <PageHeader title="Configurações do superadmin" subtitle="Vale para toda a plataforma — mexe em todos os negócios" />
      <div className="px-6 pt-4">
        <Deferred data="config" fallback={<Skeleton className="h-[32rem] w-full" />}>
          {config ? <Formulario config={config} /> : null}
        </Deferred>
      </div>
    </div>
  );
}

function Formulario({ config }: { config: Config }) {
  const [sec, setSec] = useState(INICIAL.id);
  const form = useForm<Record<string, string>>({ ...config.valores });
  const [segredos, setSegredos] = useState<Record<string, string>>({});
  const atual = SECOES.find((s) => s.id === sec) ?? INICIAL;

  const salvar = () => {
    // Segredo vazio não viaja: o backend lê ausência como "manter o atual".
    const preenchidos = Object.fromEntries(Object.entries(segredos).filter(([, v]) => v.trim() !== ''));
    // Chave vai sempre como '1'/'0': o backend testa `empty()`, e a string 'false' do .env
    // (ALLOW_REGISTRATION) seria lida como LIGADO se voltasse crua.
    const chaves = Object.fromEntries(SECOES.flatMap((s) => s.campos).filter((c) => c.t === 'chave').map((c) => [c.k, ligado(form.data[c.k]) ? '1' : '0']));
    form.transform((d) => ({ ...d, ...chaves, ...preenchidos }));
    form.put('/superadmin/settings', { preserveScroll: true, onSuccess: () => setSegredos({}) });
  };

  const campo = (c: Campo) => {
    const valor = form.data[c.k] ?? '';
    if (c.t === 'chave') {
      return (
        <label key={c.k} className="flex items-center justify-between gap-3 py-1 text-sm sm:col-span-2">
          <span>{c.l}</span>
          <Switch checked={ligado(valor)} onCheckedChange={(on) => form.setData(c.k, on ? '1' : '0')} aria-label={c.l} />
        </label>
      );
    }
    const largo = c.t === 'area' || c.t === 'codigo' ? 'sm:col-span-2' : '';
    let entrada: ReactNode;
    if (c.t.startsWith('lista:')) {
      const lista = c.t.slice(6);
      const opcoes = (FIXAS[lista] ?? config.opcoes[lista as keyof Config['opcoes']] ?? []).filter((o) => o.v !== '');
      entrada = <Select rotulo={c.l} valor={valor} onChange={(v) => form.setData(c.k, v)} opcoes={[{ v: '', label: '—' }, ...opcoes]} />;
    } else if (c.t === 'segredo') {
      const definido = config.segredos[c.k];
      entrada = (
        <Input type="password" autoComplete="new-password" aria-label={c.l} value={segredos[c.k] ?? ''}
          data-contract="superadmin.configuracoes.segredo"
          placeholder={definido ? '•••••••• definido — deixe vazio para manter' : 'não definido'}
          onChange={(e) => setSegredos({ ...segredos, [c.k]: e.target.value })} />
      );
    } else if (c.t === 'area' || c.t === 'codigo') {
      entrada = <Textarea aria-label={c.l} rows={c.t === 'codigo' ? 6 : 5} className={c.t === 'codigo' ? 'font-mono text-xs' : ''}
        value={valor} onChange={(e) => form.setData(c.k, e.target.value)} />;
    } else {
      entrada = <Input aria-label={c.l} type={c.t === 'numero' ? 'number' : 'text'} value={valor} onChange={(e) => form.setData(c.k, e.target.value)} />;
    }
    return (
      <div key={c.k} className={`flex flex-col gap-1 text-sm ${largo}`}>
        <span className="text-xs text-muted-foreground">{c.l}</span>
        {entrada}
        {form.errors[c.k] && <span className="text-xs text-destructive">{form.errors[c.k]}</span>}
      </div>
    );
  };

  return (
    <div className="grid gap-4 lg:grid-cols-[14rem_1fr]" data-contract="superadmin.configuracoes">
      <nav className="flex flex-col gap-1" aria-label="Seções">
        {SECOES.map((s) => (
          <Button key={s.id} type="button" variant={s.id === sec ? 'secondary' : 'ghost'} className="h-auto flex-col items-start py-2" onClick={() => setSec(s.id)}>
            <b className="text-sm">{s.titulo}</b>
            <small className="text-[11px] text-muted-foreground">{plural(s.campos.length, 'ajuste', 'ajustes')}</small>
          </Button>
        ))}
        <p className="px-3 pt-3 text-[11px] text-muted-foreground">Versão do superadmin: {config.versao || '—'}</p>
      </nav>
      <Card>
        <CardContent className="flex flex-col gap-4 p-4">
          <header className="flex flex-col gap-0.5">
            <h2 className="font-medium">{atual.titulo}</h2>
            <span className="text-xs text-muted-foreground">{atual.desc}</span>
          </header>
          {atual.aviso && <p className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive">{atual.aviso}</p>}
          <div className="grid gap-3 sm:grid-cols-2">{atual.campos.map(campo)}</div>
          {sec === 'backup' && (
            <div className="flex flex-col gap-1 text-xs" data-contract="superadmin.configuracoes.cron">
              <span className="text-muted-foreground">Comando do cron (rodar a cada minuto no servidor)</span>
              <code className="break-all rounded-md border bg-muted/40 p-2 font-mono">{config.cron}</code>
            </div>
          )}
          <footer className="flex justify-end gap-2 border-t pt-3">
            <Button type="button" variant="ghost" disabled={form.processing || !form.isDirty} onClick={() => { form.reset(); setSegredos({}); }}>Descartar</Button>
            <Button type="button" onClick={salvar} disabled={form.processing}>{form.processing ? 'Salvando…' : 'Salvar alterações'}</Button>
          </footer>
        </CardContent>
      </Card>
    </div>
  );
}

ConfiguracoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ConfiguracoesIndex;
