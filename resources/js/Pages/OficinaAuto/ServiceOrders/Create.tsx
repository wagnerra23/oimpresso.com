// @memcofre tela=/oficina-auto/ordens-servico/create module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Form criação de OS em Sheet lateral.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-create.md
//
// 2026-05-26: refatorado de Page fullscreen pra Sheet 720px lateral (Wagner pedido).
// Consistente com ServiceOrderRichSheet padrão drawer OficinaAuto.
// 2026-05-31: P5 controles nativos → DS compound. <select> status → Select shadcn,
// <textarea> notes → Textarea DS, combobox de veículo (Popover + Command) com busca
// de placa/tipo (lista até 500 — <option> plano trava). Erros do useForm em TODOS
// os campos + auto-scroll pro 1º inválido.
// 2026-06-09 (sweep ADR 0265 front · avaliação [CC]):
//   - "Locação" removida do select de tipo (backend valida in:manutencao,mecanica
//     — a opção quebrava o submit com erro de validação).
//   - Select de Status removido da UI — status nasce 'aberta'; quem move é o FSM
//     (canon GUARD: nunca setar estágio manualmente).
//   - Combobox de Cliente (contact) — charter exige; renderiza quando o controller
//     passar `contacts` (graceful: sem prop, campo não aparece).
//   - Copy dev ("V0 — vínculo OS↔Vehicle obrigatório, status livre") trocada por
//     copy cliente-facing.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Wrench, Save, Check, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/Lib/utils';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/Components/ui/command';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import EntryCheckinFields from './_components/EntryCheckinFields';

interface Vehicle {
  id: number;
  plate: string;
  secondary_plate: string | null;
  vehicle_type: string;
}

interface ContactOption {
  id: number;
  name: string;
}

interface Props {
  vehicles: Vehicle[];
  statuses: Record<string, string>;
  /** Clientes pro combobox (charter: Martinho atende caminhões de terceiros).
      Opcional — campo só renderiza quando o controller enviar. */
  contacts?: ContactOption[];
}

function vehicleLabel(v: Vehicle): string {
  const secondary = v.secondary_plate ? ` + ${v.secondary_plate}` : '';
  return `${v.plate}${secondary} (${v.vehicle_type})`;
}

export default function ServiceOrdersCreate({ vehicles, statuses, contacts }: Props) {
  // Sheet abre por default (rota /create dedicada); fechar volta pra lista/producao
  const [open, setOpen] = useState(true);
  const [vehiclePickerOpen, setVehiclePickerOpen] = useState(false);
  const [contactPickerOpen, setContactPickerOpen] = useState(false);
  void statuses; // mantido na assinatura por compat — status é do FSM, não da UI

  // Prefill via ?vehicle={id} (CTA "Abrir OS" do kanban Produção — ADR 0265).
  // Só aceita id presente na lista `vehicles` (já scoped por business_id no
  // controller — Tier 0 ADR 0093): id de outro tenant simplesmente não prefilla.
  const prefillVehicleId = (() => {
    if (typeof window === 'undefined') return '';
    const raw = new URLSearchParams(window.location.search).get('vehicle');
    if (!raw) return '';
    return vehicles.some((v) => String(v.id) === raw) ? raw : '';
  })();

  const { data, setData, post, processing, errors } = useForm({
    vehicle_id: prefillVehicleId,
    contact_id: '',
    // Tipo de OS — 'mecanica' é o fluxo real de reparo de caminhão (ADR 0194),
    // default pra oficina do Martinho. Roda no pipeline FSM oficina_mecanica_os.
    order_type: 'mecanica',
    transaction_id: '',
    mileage_at_service: '',
    fuel_level_at_entry: '',
    entry_damages: [] as string[],
    status: 'aberta',
    entered_at: '',
    expected_completion: '',
    notes: '',
  });

  const selectedVehicle = useMemo(
    () => vehicles.find((v) => String(v.id) === data.vehicle_id) ?? null,
    [vehicles, data.vehicle_id],
  );

  const selectedContact = useMemo(
    () => (contacts ?? []).find((c) => String(c.id) === data.contact_id) ?? null,
    [contacts, data.contact_id],
  );

  // Foco/scroll no 1º campo inválido quando o backend devolve erros de validação.
  const formRef = useRef<HTMLFormElement>(null);
  useEffect(() => {
    const firstKey = Object.keys(errors)[0];
    if (!firstKey || !formRef.current) return;
    const field = formRef.current.querySelector<HTMLElement>(`[data-field="${firstKey}"]`);
    if (field) {
      field.scrollIntoView({ behavior: 'smooth', block: 'center' });
      field.querySelector<HTMLElement>('input,textarea,button')?.focus();
    }
  }, [errors]);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    post('/oficina-auto/ordens-servico');
  }

  function handleClose() {
    setOpen(false);
    // Aguardar animação de fechamento antes de navegar (200ms shadcn default)
    setTimeout(() => {
      // Preferir referer pra UX (drawer abriu de Producao, volta pra Producao)
      const referer = document.referrer;
      if (referer && referer.includes('/oficina-auto/')) {
        router.visit(referer);
      } else {
        router.visit('/oficina-auto/ordens-servico');
      }
    }, 200);
  }

  return (
    <AppShellV2>
      <Head title="Nova OS · Oficina Auto" />
      <Sheet open={open} onOpenChange={(o) => !o && handleClose()}>
        <SheetContent
          side="right"
          className="w-full sm:max-w-[720px] overflow-y-auto"
        >
          <SheetHeader className="space-y-1 pb-3 border-b">
            <SheetTitle className="flex items-center gap-2 text-lg">
              <Wrench className="size-5" />
              Nova Ordem de Serviço
            </SheetTitle>
            <SheetDescription className="text-xs">
              Check-in do veículo — a OS abre em Recepção e segue o quadro de etapas.
            </SheetDescription>
          </SheetHeader>

          <form ref={formRef} onSubmit={handleSubmit} className="space-y-4 pt-4 px-1">
            {/* Veículo — combobox com busca (placa/tipo); lista até 500 (ADR 0137) */}
            <div data-field="vehicle_id">
              <Label htmlFor="vehicle_id">Veículo *</Label>
              <Popover open={vehiclePickerOpen} onOpenChange={setVehiclePickerOpen}>
                <PopoverTrigger asChild>
                  <Button
                    id="vehicle_id"
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={vehiclePickerOpen}
                    aria-invalid={!!errors.vehicle_id}
                    disabled={vehicles.length === 0}
                    className="w-full justify-between font-normal"
                  >
                    <span className={cn(!selectedVehicle && 'text-muted-foreground')}>
                      {selectedVehicle ? vehicleLabel(selectedVehicle) : 'Selecione um veículo…'}
                    </span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent
                  className="w-[var(--radix-popover-trigger-width)] p-0"
                  align="start"
                >
                  <Command>
                    <CommandInput placeholder="Buscar por placa ou tipo…" />
                    <CommandList>
                      <CommandEmpty>Nenhum veículo encontrado.</CommandEmpty>
                      <CommandGroup>
                        {vehicles.map((v) => (
                          <CommandItem
                            key={v.id}
                            value={vehicleLabel(v)}
                            onSelect={() => {
                              setData('vehicle_id', String(v.id));
                              setVehiclePickerOpen(false);
                            }}
                          >
                            <Check
                              className={cn(
                                'mr-2 size-4',
                                data.vehicle_id === String(v.id) ? 'opacity-100' : 'opacity-0',
                              )}
                            />
                            {vehicleLabel(v)}
                          </CommandItem>
                        ))}
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
              {errors.vehicle_id && (
                <p className="text-sm text-destructive mt-1">{errors.vehicle_id}</p>
              )}
              {vehicles.length === 0 && (
                <p className="text-sm text-muted-foreground mt-1">
                  Nenhum veículo cadastrado.{' '}
                  <Link href="/oficina-auto/veiculos/create" className="underline">
                    Cadastrar veículo
                  </Link>
                </p>
              )}
            </div>

            <div data-field="order_type">
              <Label htmlFor="order_type">Tipo de OS *</Label>
              <Select
                value={data.order_type}
                onValueChange={(value) => setData('order_type', value)}
              >
                <SelectTrigger id="order_type" aria-invalid={!!errors.order_type}>
                  <SelectValue placeholder="Selecione o tipo…" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="mecanica">Mecânica / Reparo (caminhão)</SelectItem>
                  <SelectItem value="manutencao">Manutenção simples</SelectItem>
                </SelectContent>
              </Select>
              {errors.order_type && (
                <p className="text-sm text-destructive mt-1">{errors.order_type}</p>
              )}
              <p className="text-xs text-muted-foreground mt-1">
                Mecânica usa o quadro de etapas (Recepção → Pronto p/ retirar).
              </p>
            </div>

            {/* Cliente — dono do caminhão (terceiro). Só renderiza quando o controller
                envia `contacts`; obrigatório pro gate de aprovação WhatsApp ter destinatário. */}
            {contacts && contacts.length > 0 && (
              <div data-field="contact_id">
                <Label htmlFor="contact_id">Cliente *</Label>
                <Popover open={contactPickerOpen} onOpenChange={setContactPickerOpen}>
                  <PopoverTrigger asChild>
                    <Button
                      id="contact_id"
                      type="button"
                      variant="outline"
                      role="combobox"
                      aria-expanded={contactPickerOpen}
                      aria-invalid={!!errors.contact_id}
                      className="w-full justify-between font-normal"
                    >
                      <span className={cn(!selectedContact && 'text-muted-foreground')}>
                        {selectedContact ? selectedContact.name : 'Selecione o cliente…'}
                      </span>
                      <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent
                    className="w-[var(--radix-popover-trigger-width)] p-0"
                    align="start"
                  >
                    <Command>
                      <CommandInput placeholder="Buscar cliente…" />
                      <CommandList>
                        <CommandEmpty>Nenhum cliente encontrado.</CommandEmpty>
                        <CommandGroup>
                          {contacts.map((c) => (
                            <CommandItem
                              key={c.id}
                              value={c.name}
                              onSelect={() => {
                                setData('contact_id', String(c.id));
                                setContactPickerOpen(false);
                              }}
                            >
                              <Check
                                className={cn(
                                  'mr-2 size-4',
                                  data.contact_id === String(c.id) ? 'opacity-100' : 'opacity-0',
                                )}
                              />
                              {c.name}
                            </CommandItem>
                          ))}
                        </CommandGroup>
                      </CommandList>
                    </Command>
                  </PopoverContent>
                </Popover>
                {errors.contact_id && (
                  <p className="text-sm text-destructive mt-1">{errors.contact_id}</p>
                )}
              </div>
            )}

            <div className="grid grid-cols-2 gap-4">
              <div data-field="mileage_at_service">
                <Label htmlFor="mileage_at_service">KM na entrada</Label>
                <Input
                  id="mileage_at_service"
                  type="number"
                  value={data.mileage_at_service}
                  onChange={(e) => setData('mileage_at_service', e.target.value)}
                  min={0}
                  aria-invalid={!!errors.mileage_at_service}
                />
                {errors.mileage_at_service && (
                  <p className="text-sm text-destructive mt-1">{errors.mileage_at_service}</p>
                )}
              </div>
              <div data-field="entered_at">
                <Label htmlFor="entered_at">Data entrada</Label>
                <Input
                  id="entered_at"
                  type="datetime-local"
                  value={data.entered_at}
                  onChange={(e) => setData('entered_at', e.target.value)}
                  aria-invalid={!!errors.entered_at}
                />
                {errors.entered_at && (
                  <p className="text-sm text-destructive mt-1">{errors.entered_at}</p>
                )}
              </div>
              <div data-field="expected_completion">
                <Label htmlFor="expected_completion">Previsão entrega</Label>
                <Input
                  id="expected_completion"
                  type="datetime-local"
                  value={data.expected_completion}
                  onChange={(e) => setData('expected_completion', e.target.value)}
                  aria-invalid={!!errors.expected_completion}
                />
                {errors.expected_completion && (
                  <p className="text-sm text-destructive mt-1">{errors.expected_completion}</p>
                )}
              </div>
            </div>

            <div data-field="notes">
              <Label htmlFor="notes">Defeito / Observações</Label>
              <Textarea
                id="notes"
                value={data.notes}
                onChange={(e) => setData('notes', e.target.value)}
                rows={4}
                aria-invalid={!!errors.notes}
                placeholder="Descrição do serviço solicitado, defeitos relatados, observações…"
              />
              {errors.notes && (
                <p className="text-sm text-destructive mt-1">{errors.notes}</p>
              )}
            </div>

            <EntryCheckinFields
              fuelLevel={data.fuel_level_at_entry}
              damages={data.entry_damages}
              onFuelChange={(v) => setData('fuel_level_at_entry', v)}
              onDamagesChange={(v) => setData('entry_damages', v)}
              fuelError={errors.fuel_level_at_entry}
            />

            <div className="flex justify-end gap-2 pt-4 border-t sticky bottom-0 bg-background -mx-1 px-1 pb-2">
              <Button variant="outline" type="button" onClick={handleClose}>
                Cancelar
              </Button>
              <Button type="submit" disabled={processing || vehicles.length === 0}>
                <Save className="size-4 mr-1" />
                Criar OS
              </Button>
            </div>
          </form>
        </SheetContent>
      </Sheet>
    </AppShellV2>
  );
}
