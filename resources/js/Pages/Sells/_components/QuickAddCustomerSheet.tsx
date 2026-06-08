// QuickAddCustomerSheet — Onda R6 (2026-05-27) Dor 4 Larissa @ Rota Livre.
//
// Substitui o fluxo legacy de "abrir nova aba pra cadastrar cliente + postMessage
// devolve contato" por um Sheet lateral in-place. Larissa (não-técnica) achava
// confuso aba nova abrir e voltar. Blade legacy abria modal in-place; voltamos
// a essa UX no Inertia/React.
//
// Form mínimo (quick-add) — 5 campos:
//   - Nome (obrigatório)
//   - Telefone (obrigatório-ish — Larissa sempre tem, ajuda dedupe futuro)
//   - Email (opcional)
//   - Cidade (opcional)
//   - CPF/CNPJ (opcional — útil pra nota fiscal mas não trava cadastro)
//
// Cadastro completo (endereço, IE, regime tributário, etc.) continua em /contacts
// — esse Sheet é "quick-add" no fluxo de venda. Após salvar, Sheet fecha e o
// cliente novo já entra selecionado no CustomerSearchAutocomplete via onCreated.
//
// Backend: POST /contacts (resource controller — ContactController@store).
// O controller já cuida de business_id (Tier 0 ADR 0093) via session. NÃO
// passamos business_id daqui — defesa em profundidade no backend.
//
// Strategy: fetch direto (não Inertia router) — após sucesso, queremos o ID do
// contato criado pra setar no autocomplete sem navegar. Inertia router foi
// recusado porque ele faz redirect + recarrega página (perderia draft da venda).

import { useEffect, useState } from 'react';
import { UserPlus, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import type { CustomerSearchResult } from './CustomerSearchAutocomplete';

interface Props {
  open: boolean;
  onClose: () => void;
  /** Nome pré-preenchido (vem da query do autocomplete — "Cadastrar 'X' como novo cliente"). */
  prefillName?: string;
  /** Callback quando o cliente é criado com sucesso — o pai seta `forcedValue` no autocomplete. */
  onCreated: (customer: CustomerSearchResult) => void;
}

interface QuickAddErrors {
  first_name?: string;
  mobile?: string;
  email?: string;
  city?: string;
  cpf_cnpj?: string;
  msg?: string;
}

/**
 * Tenta extrair token CSRF do meta tag injetado pelo Laravel (resources/views layout).
 * Sem isso, POST volta 419. Wagner 2026-05-27 — não usamos Inertia router aqui
 * pra preservar o draft da venda; fetch direto exige CSRF manual.
 */
function getCsrfToken(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return meta?.content ?? '';
}

export default function QuickAddCustomerSheet({ open, onClose, prefillName = '', onCreated }: Props) {
  const [firstName, setFirstName] = useState(prefillName);
  const [mobile, setMobile] = useState('');
  const [email, setEmail] = useState('');
  const [city, setCity] = useState('');
  const [cpfCnpj, setCpfCnpj] = useState('');
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<QuickAddErrors>({});

  // Reset/refill quando o Sheet abre — prefillName muda por chamada do pai.
  useEffect(() => {
    if (open) {
      setFirstName(prefillName);
      setMobile('');
      setEmail('');
      setCity('');
      setCpfCnpj('');
      setErrors({});
    }
  }, [open, prefillName]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (saving) return;

    // Validação client-side mínima — name é obrigatório no banco (NOT NULL).
    const localErrors: QuickAddErrors = {};
    if (!firstName.trim()) {
      localErrors.first_name = 'Nome é obrigatório';
    }
    if (Object.keys(localErrors).length > 0) {
      setErrors(localErrors);
      return;
    }

    setSaving(true);
    setErrors({});

    try {
      // POST /contacts (Route::resource → contacts.store) com payload mínimo.
      // ContactController@store espera `contact_type_radio` e monta name de
      // prefix+first+middle+last. Mandamos first_name only (suficiente pro
      // contact.name virar `firstName` trimmed) e contact_type_radio='customer'.
      const res = await fetch('/contacts', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          type: 'customer',
          contact_type_radio: 'customer',
          first_name: firstName.trim(),
          mobile: mobile.trim() || null,
          email: email.trim() || null,
          city: city.trim() || null,
          cpf_cnpj: cpfCnpj.trim() || null,
          // Campos UPOS obrigatórios pelo StoreContactRequest — defaults sensatos.
          pay_term_number: null,
          pay_term_type: null,
          credit_limit: '',
          opening_balance: '0',
        }),
      });

      if (res.status === 422) {
        // Validação Laravel — pegar errors normalizados.
        const data = await res.json().catch(() => ({}));
        const validationErrors = (data?.errors ?? {}) as Record<string, string[]>;
        const flat: QuickAddErrors = {};
        if (validationErrors.first_name?.[0]) flat.first_name = validationErrors.first_name[0];
        if (validationErrors.mobile?.[0]) flat.mobile = validationErrors.mobile[0];
        if (validationErrors.email?.[0]) flat.email = validationErrors.email[0];
        if (validationErrors.city?.[0]) flat.city = validationErrors.city[0];
        if (validationErrors.cpf_cnpj?.[0]) flat.cpf_cnpj = validationErrors.cpf_cnpj[0];
        if (Object.keys(flat).length === 0) {
          flat.msg = data?.message ?? 'Falha na validação. Confira os campos.';
        }
        setErrors(flat);
        setSaving(false);
        return;
      }

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setErrors({ msg: data?.msg ?? data?.message ?? 'Não foi possível cadastrar. Tente novamente.' });
        setSaving(false);
        return;
      }

      // Sucesso UPOS legacy retorna { success: true, msg, data: { id, name, ... } }
      // Inertia-aware response retorna redirect — não cai aqui porque mandamos
      // X-Requested-With: XMLHttpRequest (sem X-Inertia → branch JSON puro).
      const data = await res.json().catch(() => ({}));
      const created = data?.data ?? data;
      const newId = Number(created?.id ?? 0);
      if (!newId) {
        setErrors({ msg: 'Cadastro feito mas não recebemos ID. Recarregue e busque manualmente.' });
        setSaving(false);
        return;
      }

      const newName = String(created?.name ?? firstName.trim());
      const newMobile = created?.mobile ?? mobile.trim() ?? null;
      const newCity = created?.city ?? city.trim() ?? null;

      onCreated({ id: newId, text: newName, mobile: newMobile, city: newCity });
      setSaving(false);
      onClose();
    } catch (err) {
      setErrors({ msg: 'Erro de rede. Verifique sua conexão.' });
      setSaving(false);
    }
  };

  return (
    <Sheet open={open} onOpenChange={(o) => !o && !saving && onClose()}>
      <SheetContent side="right" className="w-[420px] sm:max-w-[420px] flex flex-col p-0">
        <header className="border-b border-border px-5 py-4">
          <SheetTitle asChild>
            <h2 className="m-0 flex items-center gap-2 text-base font-semibold">
              <UserPlus size={18} className="text-primary" />
              Cadastrar cliente
            </h2>
          </SheetTitle>
          <SheetDescription asChild>
            <p className="mt-1 text-xs text-muted-foreground">
              Cadastro rápido. Endereço completo, IE e regime ficam em <i>/contacts</i>.
            </p>
          </SheetDescription>
        </header>

        <form onSubmit={submit} className="flex-1 overflow-y-auto p-5 space-y-4 text-[13px]">
          {errors.msg && (
            <div className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-[12px] text-destructive">
              {errors.msg}
            </div>
          )}

          <div className="space-y-1.5">
            <label htmlFor="qa-name" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Nome <span className="text-destructive">*</span>
            </label>
            <Input
              id="qa-name"
              type="text"
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              placeholder="Ex: Maria Silva"
              autoFocus
              required
              maxLength={120}
              data-testid="quickadd-name"
            />
            {errors.first_name && (
              <p className="text-[11px] text-destructive">{errors.first_name}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="qa-mobile" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Telefone
            </label>
            <Input
              id="qa-mobile"
              type="tel"
              value={mobile}
              onChange={(e) => setMobile(e.target.value)}
              placeholder="(48) 99999-0000"
              maxLength={25}
              data-testid="quickadd-mobile"
            />
            {errors.mobile && (
              <p className="text-[11px] text-destructive">{errors.mobile}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="qa-email" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Email
            </label>
            <Input
              id="qa-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="maria@exemplo.com"
              maxLength={120}
              data-testid="quickadd-email"
            />
            {errors.email && (
              <p className="text-[11px] text-destructive">{errors.email}</p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label htmlFor="qa-city" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
                Cidade
              </label>
              <Input
                id="qa-city"
                type="text"
                value={city}
                onChange={(e) => setCity(e.target.value)}
                placeholder="Tubarão"
                maxLength={80}
                data-testid="quickadd-city"
              />
              {errors.city && (
                <p className="text-[11px] text-destructive">{errors.city}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <label htmlFor="qa-cpfcnpj" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
                CPF/CNPJ
              </label>
              <Input
                id="qa-cpfcnpj"
                type="text"
                value={cpfCnpj}
                onChange={(e) => setCpfCnpj(e.target.value)}
                placeholder="(opcional)"
                maxLength={18}
                data-testid="quickadd-cpfcnpj"
              />
              {errors.cpf_cnpj && (
                <p className="text-[11px] text-destructive">{errors.cpf_cnpj}</p>
              )}
            </div>
          </div>

          <p className="text-[11px] text-muted-foreground pt-2">
            Cliente é criado com tipo <b>customer</b>. Outros campos (endereço, IE,
            regime tributário) podem ser preenchidos depois em <i>/contacts</i>.
          </p>
        </form>

        <footer className="border-t border-border px-5 py-3 flex items-center justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            onClick={onClose}
            disabled={saving}
            data-testid="quickadd-cancel"
          >
            Cancelar
          </Button>
          <Button
            type="button"
            onClick={submit}
            disabled={saving || !firstName.trim()}
            data-testid="quickadd-submit"
          >
            {saving ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Salvando…
              </>
            ) : (
              'Cadastrar e usar'
            )}
          </Button>
        </footer>
      </SheetContent>
    </Sheet>
  );
}
