// @memcofre tela=/perfil module=User
//
// "Meu perfil" — conta do usuário logado. Redesign ComVis do legado
// resources/views/user/profile.blade.php (UltimatePOS HRM), em Inertia.
// PageHeader v3 canon (ADR 0189/0190) + primary roxo universal. Tier 0:
// a tela só edita o próprio usuário (controller escopa por session user.id).
// Layout via primitivos Stack/Inline/Grid (ADR 0253) — sem flex/grid solto.

import * as React from 'react';
import { useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import { Stack, Inline, Grid } from '@/Components/layout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { toast } from 'sonner';
import { User, Info, Landmark, Lock, Camera, Mail, Save } from 'lucide-react';

interface BankDetails {
  account_holder_name: string;
  account_number: string;
  bank_name: string;
  bank_code: string;
  branch: string;
  tax_payer_id: string;
}

interface Usuario {
  surname: string;
  first_name: string;
  last_name: string;
  email: string;
  language: string;
  dob: string;
  gender: string;
  marital_status: string;
  blood_group: string;
  contact_number: string;
  alt_number: string;
  family_number: string;
  fb_link: string;
  twitter_link: string;
  social_media_1: string;
  social_media_2: string;
  guardian_name: string;
  id_proof_name: string;
  id_proof_number: string;
  permanent_address: string;
  current_address: string;
  custom_field_1: string;
  custom_field_2: string;
  custom_field_3: string;
  custom_field_4: string;
  bank_details: BankDetails;
  photo_url: string | null;
}

interface Props {
  usuario: Usuario;
  languages: Record<string, string>;
  custom_field_labels: {
    custom_field_1: string;
    custom_field_2: string;
    custom_field_3: string;
    custom_field_4: string;
  };
}

const GENDERS = [
  { v: '', l: 'Selecionar' },
  { v: 'male', l: 'Masculino' },
  { v: 'female', l: 'Feminino' },
  { v: 'others', l: 'Outro' },
];

const MARITAL = [
  { v: '', l: 'Selecionar' },
  { v: 'married', l: 'Casado(a)' },
  { v: 'unmarried', l: 'Solteiro(a)' },
  { v: 'divorced', l: 'Divorciado(a)' },
];

const TABS = [
  { id: 'conta', label: 'Conta', icon: User },
  { id: 'info', label: 'Mais informações', icon: Info },
  { id: 'banco', label: 'Dados bancários', icon: Landmark },
  { id: 'seguranca', label: 'Segurança', icon: Lock },
] as const;

type FormShape = Record<string, string | File | null | BankDetails>;

function CanonSelect({
  id,
  value,
  onChange,
  placeholder,
  options,
}: {
  id?: string;
  value: string;
  onChange: (v: string) => void;
  placeholder: string;
  options: { v: string; l: string }[];
}) {
  return (
    <Select value={value || undefined} onValueChange={onChange}>
      <SelectTrigger id={id} className="w-full">
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {options
          .filter((o) => o.v !== '')
          .map((o) => (
            <SelectItem key={o.v} value={o.v}>
              {o.l}
            </SelectItem>
          ))}
      </SelectContent>
    </Select>
  );
}

function Field({
  label,
  htmlFor,
  required,
  hint,
  error,
  span2,
  children,
}: {
  label: string;
  htmlFor?: string;
  required?: boolean;
  hint?: string;
  error?: string;
  span2?: boolean;
  children: React.ReactNode;
}) {
  return (
    <Stack gap={1} className={`min-w-0 ${span2 ? 'md:col-span-2' : ''}`}>
      {label && (
        <Label htmlFor={htmlFor} className="text-xs font-semibold text-muted-foreground">
          {label}
          {required && <span className="text-destructive ml-0.5">*</span>}
        </Label>
      )}
      {children}
      {error ? (
        <span className="text-xs text-destructive">{error}</span>
      ) : (
        hint && <span className="text-xs text-muted-foreground">{hint}</span>
      )}
    </Stack>
  );
}

function SectionCard({
  icon: Icon,
  title,
  desc,
  children,
}: {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  desc?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="rounded-lg border bg-card overflow-hidden">
      <Inline gap={2} className="px-4 py-3 border-b">
        <Icon className="h-4 w-4 text-muted-foreground shrink-0" />
        <div className="min-w-0">
          <h3 className="text-sm font-semibold text-foreground leading-tight">{title}</h3>
          {desc && <p className="text-xs text-muted-foreground mt-0.5">{desc}</p>}
        </div>
      </Inline>
      <div className="p-4">{children}</div>
    </section>
  );
}

function Perfil({ usuario, languages, custom_field_labels }: Props) {
  const [tab, setTab] = useState<(typeof TABS)[number]['id']>('conta');
  const fileRef = useRef<HTMLInputElement>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(usuario.photo_url);

  const form = useForm<FormShape>({
    surname: usuario.surname ?? '',
    first_name: usuario.first_name ?? '',
    last_name: usuario.last_name ?? '',
    email: usuario.email ?? '',
    language: usuario.language ?? 'pt-BR',
    dob: usuario.dob ?? '',
    gender: usuario.gender ?? '',
    marital_status: usuario.marital_status ?? '',
    blood_group: usuario.blood_group ?? '',
    contact_number: usuario.contact_number ?? '',
    alt_number: usuario.alt_number ?? '',
    family_number: usuario.family_number ?? '',
    fb_link: usuario.fb_link ?? '',
    twitter_link: usuario.twitter_link ?? '',
    social_media_1: usuario.social_media_1 ?? '',
    social_media_2: usuario.social_media_2 ?? '',
    guardian_name: usuario.guardian_name ?? '',
    id_proof_name: usuario.id_proof_name ?? '',
    id_proof_number: usuario.id_proof_number ?? '',
    permanent_address: usuario.permanent_address ?? '',
    current_address: usuario.current_address ?? '',
    custom_field_1: usuario.custom_field_1 ?? '',
    custom_field_2: usuario.custom_field_2 ?? '',
    custom_field_3: usuario.custom_field_3 ?? '',
    custom_field_4: usuario.custom_field_4 ?? '',
    bank_details: { ...usuario.bank_details },
    profile_photo: null,
  });

  const pwForm = useForm({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  const set =
    (k: string) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
      form.setData(k, e.target.value);
  const setBank = (k: keyof BankDetails) => (e: React.ChangeEvent<HTMLInputElement>) =>
    form.setData('bank_details', { ...(form.data.bank_details as BankDetails), [k]: e.target.value });

  const bank = form.data.bank_details as BankDetails;

  const initials =
    (((form.data.first_name as string)?.[0] || '') + ((form.data.last_name as string)?.[0] || ''))
      .toUpperCase() || 'U';
  const fullName = [form.data.surname, form.data.first_name, form.data.last_name]
    .filter(Boolean)
    .join(' ');

  const onPhoto = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.setData('profile_photo', file);
    setPhotoPreview(URL.createObjectURL(file));
  };

  const submitProfile = () => {
    form.post('/perfil/update', {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => toast.success('Perfil atualizado'),
      onError: () => toast.error('Confira os campos destacados'),
    });
  };

  const submitPasswordRaw = () => {
    pwForm.post('/perfil/password', {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Senha atualizada');
        pwForm.reset();
      },
      onError: () => toast.error('Confira a senha atual e a confirmação'),
    });
  };
  const submitPassword = (e: React.FormEvent) => {
    e.preventDefault();
    submitPasswordRaw();
  };

  const Avatar = () =>
    photoPreview ? (
      <img src={photoPreview} alt="" className="h-full w-full object-cover" />
    ) : (
      <span>{initials}</span>
    );

  const subnav = (
    <Inline asChild gap={1} className="overflow-x-auto">
      <nav aria-label="Seções do perfil">
        {TABS.map((t) => {
          const Ic = t.icon;
          const active = tab === t.id;
          return (
            <button
              key={t.id}
              type="button"
              onClick={() => setTab(t.id)}
              aria-current={active ? 'page' : undefined}
              className={`inline-flex items-center gap-1.5 h-8 px-3 rounded-md text-[12.5px] font-medium whitespace-nowrap transition-colors ${
                active
                  ? 'bg-primary/10 text-primary'
                  : 'text-muted-foreground hover:bg-muted hover:text-foreground'
              }`}
            >
              <Ic className="h-3.5 w-3.5" />
              <span>{t.label}</span>
            </button>
          );
        })}
      </nav>
    </Inline>
  );

  const actions =
    tab !== 'seguranca' ? (
      <PageHeaderPrimary
        label={form.isDirty ? 'Salvar alterações' : 'Salvo'}
        icon={Save}
        onClick={submitProfile}
        disabled={!form.isDirty || form.processing}
      />
    ) : null;

  return (
    <div className="max-w-5xl mx-auto">
      <PageHeader
        title="Meu perfil"
        suffix=" · Conta do usuário"
        subtitle={
          <>
            {fullName || '—'} · {(form.data.email as string) || '—'}
          </>
        }
        subnav={subnav}
        actions={actions}
      />

      <div className="p-6 space-y-6">
        {/* ── Conta ── */}
        {tab === 'conta' && (
          <Grid gap={6} className="lg:grid-cols-[1fr_320px] items-start">
            <SectionCard icon={User} title="Editar perfil" desc="Nome de exibição e dados de acesso">
              <Grid gap={4} className="md:grid-cols-3">
                <Field label="Prefixo" htmlFor="surname">
                  <Input id="surname" value={form.data.surname as string} onChange={set('surname')} placeholder="Sr / Sra" />
                </Field>
                <Field label="Primeiro nome" htmlFor="first_name" required error={form.errors.first_name}>
                  <Input id="first_name" value={form.data.first_name as string} onChange={set('first_name')} placeholder="Primeiro nome" />
                </Field>
                <Field label="Sobrenome" htmlFor="last_name">
                  <Input id="last_name" value={form.data.last_name as string} onChange={set('last_name')} placeholder="Sobrenome" />
                </Field>
                <Field label="E-mail" htmlFor="email" required error={form.errors.email} span2>
                  <Inline gap={0} className="relative">
                    <Mail className="absolute left-3 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                    <Input id="email" type="email" value={form.data.email as string} onChange={set('email')} placeholder="email@empresa.com.br" className="pl-9" />
                  </Inline>
                </Field>
                <Field label="Idioma" htmlFor="language">
                  <CanonSelect
                    id="language"
                    value={form.data.language as string}
                    onChange={(v) => form.setData('language', v)}
                    placeholder="Idioma"
                    options={Object.entries(languages).map(([k, v]) => ({ v: k, l: v }))}
                  />
                </Field>
              </Grid>
            </SectionCard>

            <SectionCard icon={Camera} title="Foto de perfil">
              <Stack gap={3} align="center" className="text-center">
                <div className="h-28 w-28 rounded-full bg-primary text-primary-foreground grid place-items-center text-4xl font-semibold overflow-hidden ring-2 ring-background shadow">
                  <Avatar />
                </div>
                <Inline gap={2}>
                  <Button type="button" variant="outline" size="sm" onClick={() => fileRef.current?.click()}>
                    <Camera className="h-3.5 w-3.5 mr-1.5" />
                    Escolher imagem
                  </Button>
                  {photoPreview && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setPhotoPreview(null);
                        form.setData('profile_photo', null);
                      }}
                    >
                      Remover
                    </Button>
                  )}
                </Inline>
                <input ref={fileRef} type="file" accept="image/*" hidden onChange={onPhoto} />
                <p className="text-xs text-muted-foreground">JPG ou PNG · tamanho máximo 5 MB</p>
              </Stack>
            </SectionCard>
          </Grid>
        )}

        {/* ── Mais informações ── */}
        {tab === 'info' && (
          <div className="max-w-3xl space-y-6">
            <SectionCard icon={Info} title="Dados pessoais">
              <Grid gap={4} className="md:grid-cols-2">
                <Field label="Data de nascimento" htmlFor="dob">
                  <Input id="dob" type="date" value={form.data.dob as string} onChange={set('dob')} />
                </Field>
                <Field label="Gênero" htmlFor="gender">
                  <CanonSelect
                    id="gender"
                    value={form.data.gender as string}
                    onChange={(v) => form.setData('gender', v)}
                    placeholder="Selecionar"
                    options={GENDERS}
                  />
                </Field>
                <Field label="Estado civil" htmlFor="marital_status">
                  <CanonSelect
                    id="marital_status"
                    value={form.data.marital_status as string}
                    onChange={(v) => form.setData('marital_status', v)}
                    placeholder="Selecionar"
                    options={MARITAL}
                  />
                </Field>
                <Field label="Grupo sanguíneo" htmlFor="blood_group">
                  <Input id="blood_group" value={form.data.blood_group as string} onChange={set('blood_group')} placeholder="ex: O+" />
                </Field>
                <Field label="Nome do responsável" htmlFor="guardian_name" span2>
                  <Input id="guardian_name" value={form.data.guardian_name as string} onChange={set('guardian_name')} placeholder="Nome do responsável" />
                </Field>
              </Grid>
            </SectionCard>

            <SectionCard icon={Info} title="Contatos">
              <Grid gap={4} className="md:grid-cols-3">
                <Field label="Celular" htmlFor="contact_number">
                  <Input id="contact_number" value={form.data.contact_number as string} onChange={set('contact_number')} placeholder="(00) 0 0000-0000" />
                </Field>
                <Field label="Telefone alternativo" htmlFor="alt_number">
                  <Input id="alt_number" value={form.data.alt_number as string} onChange={set('alt_number')} placeholder="(00) 0000-0000" />
                </Field>
                <Field label="Contato da família" htmlFor="family_number">
                  <Input id="family_number" value={form.data.family_number as string} onChange={set('family_number')} placeholder="(00) 0 0000-0000" />
                </Field>
                <Field label="Facebook" htmlFor="fb_link">
                  <Input id="fb_link" value={form.data.fb_link as string} onChange={set('fb_link')} placeholder="facebook.com/usuario" />
                </Field>
                <Field label="Twitter / X" htmlFor="twitter_link">
                  <Input id="twitter_link" value={form.data.twitter_link as string} onChange={set('twitter_link')} placeholder="x.com/usuario" />
                </Field>
                <Field label="Rede social 1" htmlFor="social_media_1">
                  <Input id="social_media_1" value={form.data.social_media_1 as string} onChange={set('social_media_1')} placeholder="Link" />
                </Field>
                <Field label="Rede social 2" htmlFor="social_media_2">
                  <Input id="social_media_2" value={form.data.social_media_2 as string} onChange={set('social_media_2')} placeholder="Link" />
                </Field>
              </Grid>
            </SectionCard>

            <SectionCard icon={Info} title="Documento &amp; endereços">
              <Grid gap={4} className="md:grid-cols-2">
                <Field label="Tipo de documento" htmlFor="id_proof_name">
                  <Input id="id_proof_name" value={form.data.id_proof_name as string} onChange={set('id_proof_name')} placeholder="ex: RG, CPF, CNH" />
                </Field>
                <Field label="Número do documento" htmlFor="id_proof_number">
                  <Input id="id_proof_number" value={form.data.id_proof_number as string} onChange={set('id_proof_number')} placeholder="Número" />
                </Field>
                <Field label="Endereço permanente" htmlFor="permanent_address" span2>
                  <Textarea id="permanent_address" rows={2} value={form.data.permanent_address as string} onChange={set('permanent_address')} placeholder="Rua, número, bairro, cidade" />
                </Field>
                <Field label="Endereço atual" htmlFor="current_address" span2>
                  <Textarea id="current_address" rows={2} value={form.data.current_address as string} onChange={set('current_address')} placeholder="Rua, número, bairro, cidade" />
                </Field>
              </Grid>
            </SectionCard>

            <SectionCard icon={Info} title="Campos personalizados">
              <Grid gap={4} className="md:grid-cols-2">
                <Field label={custom_field_labels.custom_field_1} htmlFor="custom_field_1">
                  <Input id="custom_field_1" value={form.data.custom_field_1 as string} onChange={set('custom_field_1')} placeholder={custom_field_labels.custom_field_1} />
                </Field>
                <Field label={custom_field_labels.custom_field_2} htmlFor="custom_field_2">
                  <Input id="custom_field_2" value={form.data.custom_field_2 as string} onChange={set('custom_field_2')} placeholder={custom_field_labels.custom_field_2} />
                </Field>
                <Field label={custom_field_labels.custom_field_3} htmlFor="custom_field_3">
                  <Input id="custom_field_3" value={form.data.custom_field_3 as string} onChange={set('custom_field_3')} placeholder={custom_field_labels.custom_field_3} />
                </Field>
                <Field label={custom_field_labels.custom_field_4} htmlFor="custom_field_4">
                  <Input id="custom_field_4" value={form.data.custom_field_4 as string} onChange={set('custom_field_4')} placeholder={custom_field_labels.custom_field_4} />
                </Field>
              </Grid>
            </SectionCard>
          </div>
        )}

        {/* ── Dados bancários ── */}
        {tab === 'banco' && (
          <div className="max-w-3xl">
            <SectionCard icon={Landmark} title="Dados bancários" desc="Usados para folha de pagamento e reembolsos">
              <Grid gap={4} className="md:grid-cols-2">
                <Field label="Titular da conta" htmlFor="account_holder_name">
                  <Input id="account_holder_name" value={bank.account_holder_name} onChange={setBank('account_holder_name')} placeholder="Nome do titular" />
                </Field>
                <Field label="Número da conta" htmlFor="account_number">
                  <Input id="account_number" value={bank.account_number} onChange={setBank('account_number')} placeholder="00000-0" />
                </Field>
                <Field label="Banco" htmlFor="bank_name">
                  <Input id="bank_name" value={bank.bank_name} onChange={setBank('bank_name')} placeholder="Nome do banco" />
                </Field>
                <Field label="Código do banco" htmlFor="bank_code">
                  <Input id="bank_code" value={bank.bank_code} onChange={setBank('bank_code')} placeholder="ex: 341" />
                </Field>
                <Field label="Agência" htmlFor="branch">
                  <Input id="branch" value={bank.branch} onChange={setBank('branch')} placeholder="0000" />
                </Field>
                <Field label="CPF/CNPJ do titular" htmlFor="tax_payer_id">
                  <Input id="tax_payer_id" value={bank.tax_payer_id} onChange={setBank('tax_payer_id')} placeholder="CPF ou CNPJ" />
                </Field>
              </Grid>
              <Inline align="start" gap={2} className="mt-4 rounded-md border bg-muted/40 px-3 py-2.5 text-xs text-muted-foreground">
                <Info className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                <span>Estes dados são sensíveis (LGPD). Visíveis apenas para você e o setor financeiro.</span>
              </Inline>
            </SectionCard>
          </div>
        )}

        {/* ── Segurança ── */}
        {tab === 'seguranca' && (
          <div className="max-w-lg">
            <SectionCard icon={Lock} title="Alterar senha" desc="Recomendamos ao menos 8 caracteres">
              <form onSubmit={submitPassword} className="space-y-4">
                <Field label="Senha atual" htmlFor="current_password" required error={pwForm.errors.current_password}>
                  <Input
                    id="current_password"
                    type="password"
                    value={pwForm.data.current_password}
                    onChange={(e) => pwForm.setData('current_password', e.target.value)}
                    placeholder="Senha atual"
                  />
                </Field>
                <Field label="Nova senha" htmlFor="new_password" required error={pwForm.errors.new_password}>
                  <Input
                    id="new_password"
                    type="password"
                    value={pwForm.data.new_password}
                    onChange={(e) => pwForm.setData('new_password', e.target.value)}
                    placeholder="Nova senha"
                  />
                </Field>
                <Field label="Confirmar nova senha" htmlFor="new_password_confirmation" required>
                  <Input
                    id="new_password_confirmation"
                    type="password"
                    value={pwForm.data.new_password_confirmation}
                    onChange={(e) => pwForm.setData('new_password_confirmation', e.target.value)}
                    placeholder="Confirmar nova senha"
                  />
                </Field>
                <Inline justify="end">
                  <PageHeaderPrimary label="Atualizar senha" icon={Lock} onClick={submitPasswordRaw} disabled={pwForm.processing} />
                </Inline>
              </form>
            </SectionCard>
          </div>
        )}
      </div>
    </div>
  );
}

Perfil.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Meu perfil" breadcrumbItems={[{ label: 'Conta' }, { label: 'Meu perfil' }]}>
    {page}
  </AppShellV2>
);

export default Perfil;
