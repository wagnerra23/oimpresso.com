// @memcofre
//   tela: /cms/site-details
//   module: Cms
//   stories: US-CMS-004 — thread Cms/01, fase 4a (Blade settings → Inertia)
//   permissao: superadmin
//
// O que o site público exibe e usa: aviso de contato, marca, telefones, e-mails, redes e medição.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// RUNBOOK: memory/requisitos/Cms/RUNBOOK-admin-content.md (§Fase 4a)
// Âncora de design: prototipo-ui/cowork/Wagner/cowork-inbox/cms/SiteDetails.charter.md (PT de formulário)
//
// Esta fase cobre 4 das 8 seções. Estatísticas, perguntas frequentes, chat e botões seguem na tela
// anterior (`?legado=1`) até a fase 4b. A gravação é POR CHAVE (`createOrUpdateSiteDetails`), então
// este formulário manda só as chaves dele e não apaga as outras.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import CmsAbas from '../_shared/CmsAbas';

interface Detalhes {
  notifiable_email: string;
  logo_url: string | null;
  contact_us: { label: string; num: string }[];
  mail_us: { label: string; email: string }[];
  follow_us: Record<'facebook' | 'instagram' | 'linkedin' | 'twitter' | 'youtube', string>;
  google_analytics: string;
  fb_pixel: string;
  custom_js: string;
  custom_css: string;
  meta_tags: string;
}

const REDES: [keyof Detalhes['follow_us'], string][] = [
  ['facebook', 'Facebook'], ['instagram', 'Instagram'], ['linkedin', 'LinkedIn'], ['twitter', 'X (Twitter)'], ['youtube', 'YouTube'],
];
const INTEGRACOES: [keyof Detalhes, string, string][] = [
  ['google_analytics', 'Google Analytics', 'Código completo, com a tag <script>.'],
  ['fb_pixel', 'Pixel da Meta', 'Código completo, com a tag <script>.'],
  ['meta_tags', 'Meta tags', 'Entram no <head> de todas as páginas do site.'],
  ['custom_css', 'CSS personalizado', 'Vale só para o site público, não para este painel.'],
  ['custom_js', 'JavaScript personalizado', 'Roda só no site público, nunca neste painel.'],
];

function DetalhesIndex({ detalhes }: { detalhes?: Detalhes }) {
  return (
    <Deferred data="detalhes" fallback={<div className="p-6"><Skeleton className="h-96 w-full" /></div>}>
      {detalhes ? <Formulario inicial={detalhes} /> : <span />}
    </Deferred>
  );
}

function Formulario({ inicial }: { inicial: Detalhes }) {
  const { logo_url, ...campos } = inicial;
  const form = useForm({ ...campos, logo: null as File | null });

  function salvar(e?: FormEvent) {
    e?.preventDefault();
    form.post('/cms/site-details', { forceFormData: true, preserveScroll: true });
  }

  return (
    <form onSubmit={salvar} className="pb-8" data-contract="cms.site-details.form">
      <PageHeader
        title="Detalhes do site"
        subtitle="Vale para o site público assim que salvar. Campo vazio esconde o bloco no site."
        subnav={<CmsAbas ativa="detalhes" />}
        actions={<PageHeaderPrimary label={form.processing ? 'Salvando…' : 'Salvar'} onClick={() => salvar()} disabled={form.processing} />}
      />

      <div className="flex flex-col gap-4 px-6 pt-4">
        {form.recentlySuccessful && <p role="status" className="text-sm text-muted-foreground">Detalhes salvos.</p>}

        <Secao titulo="Aplicação">
          <Campo id="notifiable_email" rotulo="E-mail que recebe os contatos do site" erro={form.errors.notifiable_email}
            ajuda="Sem e-mail, o formulário do site registra o contato mas não avisa ninguém.">
            <Input id="notifiable_email" value={form.data.notifiable_email} onChange={(e) => form.setData('notifiable_email', e.target.value)} />
          </Campo>
          <Campo id="logo" rotulo="Logotipo" erro={form.errors.logo} ajuda={logo_url ? 'Enviar outro substitui o atual.' : 'Imagem até 5 MB.'}>
            {logo_url && <img src={logo_url} alt="Logotipo atual" className="mb-2 h-10 w-auto self-start" />}
            <Input id="logo" type="file" accept="image/*" onChange={(e) => form.setData('logo', e.target.files?.[0] ?? null)} />
          </Campo>
        </Secao>

        <Secao titulo="Contato">
          {form.data.contact_us.map((c, i) => (
            <div key={`tel-${i}`} className="grid gap-2 sm:grid-cols-2">
              <Input aria-label={`Rótulo do telefone ${i + 1}`} placeholder="Rótulo (ex.: WhatsApp)" value={c.label}
                onChange={(e) => form.setData('contact_us', form.data.contact_us.map((x, k) => (k === i ? { ...x, label: e.target.value } : x)))} />
              <Input aria-label={`Telefone ${i + 1}`} placeholder="Telefone" value={c.num}
                onChange={(e) => form.setData('contact_us', form.data.contact_us.map((x, k) => (k === i ? { ...x, num: e.target.value } : x)))} />
            </div>
          ))}
          {form.data.mail_us.map((m, i) => (
            <div key={`mail-${i}`} className="grid gap-2 sm:grid-cols-2">
              <Input aria-label={`Rótulo do e-mail ${i + 1}`} placeholder="Rótulo (ex.: Comercial)" value={m.label}
                onChange={(e) => form.setData('mail_us', form.data.mail_us.map((x, k) => (k === i ? { ...x, label: e.target.value } : x)))} />
              <Input aria-label={`E-mail ${i + 1}`} placeholder="E-mail" value={m.email}
                onChange={(e) => form.setData('mail_us', form.data.mail_us.map((x, k) => (k === i ? { ...x, email: e.target.value } : x)))} />
            </div>
          ))}
        </Secao>

        <Secao titulo="Redes sociais">
          {REDES.map(([rede, nome]) => (
            <Campo key={rede} id={`follow_us_${rede}`} rotulo={nome}>
              <Input id={`follow_us_${rede}`} placeholder="Endereço do perfil" value={form.data.follow_us[rede]}
                onChange={(e) => form.setData('follow_us', { ...form.data.follow_us, [rede]: e.target.value })} />
            </Campo>
          ))}
        </Secao>

        <Secao titulo="Integrações">
          {INTEGRACOES.map(([chave, rotulo, ajuda]) => (
            <Campo key={chave} id={chave} rotulo={rotulo} ajuda={ajuda} erro={form.errors[chave as keyof typeof form.errors]}>
              <Textarea id={chave} rows={3} className="font-mono text-xs" value={form.data[chave as 'custom_js']}
                onChange={(e) => form.setData(chave as 'custom_js', e.target.value)} />
            </Campo>
          ))}
        </Secao>

        <p className="text-xs text-muted-foreground">
          Estatísticas, perguntas frequentes, chat e botões do site ainda se editam na{' '}
          <a className="underline" href="/cms/site-details?legado=1">tela anterior</a>.
        </p>
      </div>
    </form>
  );
}

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <Card>
      <CardContent className="flex flex-col gap-3 p-4">
        <h2 className="text-sm font-semibold">{titulo}</h2>
        {children}
      </CardContent>
    </Card>
  );
}

function Campo({ id, rotulo, erro, ajuda, children }: { id: string; rotulo: string; erro?: string; ajuda?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro ? <p className="text-xs text-destructive">{erro}</p> : ajuda && <p className="text-xs text-muted-foreground">{ajuda}</p>}
    </div>
  );
}

DetalhesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default DetalhesIndex;
