<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\ContactAddress;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * ContactAddressController -- CRUD de múltiplos endereços por contato (US-CRM-078).
 *
 * Endpoints (drawer 760 Cliente · Tab Endereço vira lista):
 *   GET    /cliente/{id}/enderecos                  -> index  (lista)
 *   POST   /cliente/{id}/enderecos                  -> store  (criar)
 *   PATCH  /cliente/{id}/enderecos/{addressId}      -> update (editar)
 *   DELETE /cliente/{id}/enderecos/{addressId}      -> destroy(remover · soft delete)
 *   PATCH  /cliente/{id}/enderecos/{addressId}/padrao -> setDefault (marcar padrão)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   - Contact localizado com where('business_id', sessão)->firstOrFail() (404 não vaza).
 *   - ContactAddress tem HasBusinessScope (ScopeByBusiness) + acesso só via
 *     $contact->addresses() (escopo duplo: business_id + contact_id).
 *   - business_id/contact_id NUNCA via request -- setados do contato localizado.
 *
 * Invariantes:
 *   - No máx. 1 endereço is_default=true por contato (o "principal").
 *   - No máx. 1 endereço is_shipping=true por contato (entrega default).
 *   - O endereço is_default é ESPELHADO nos campos inline de `contacts`
 *     (zip_code/address_line_1/.../city_code) -> compat UPOS/NFe/Sells.
 *
 * Permissão: customer.update OU supplier.update (matricial por contact->type),
 * mesmo gate do ClienteAutosaveController (drawer em modo edição).
 *
 * @see app/ContactAddress.php
 * @see Modules/Crm/Http/Controllers/ClienteAutosaveController.php (padrão multi-tenant)
 * @see memory/requisitos/Cliente/SPEC.md §US-CRM-078
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ContactAddressController extends Controller
{
    /** 27 UFs brasileiras. */
    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    /** Campos espelhados de volta em `contacts` quando o endereço é o padrão. */
    private const INLINE_MIRROR = [
        'zip_code', 'address_line_1', 'numero', 'address_line_2',
        'neighborhood', 'city', 'state', 'city_code',
    ];

    /** GET /cliente/{id}/enderecos -- lista os endereços do contato. */
    public function index(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        return response()->json([
            'success' => true,
            'addresses' => $this->listAddresses($contact),
        ]);
    }

    /** POST /cliente/{id}/enderecos -- cria um novo endereço. */
    public function store(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $validator = $this->validateAddress($request);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        // Primeiro endereço do contato vira padrão + entrega automaticamente.
        $isFirst = $contact->addresses()->count() === 0;
        $isDefault = (bool) ($data['is_default'] ?? false) || $isFirst;
        $isShipping = (bool) ($data['is_shipping'] ?? false) || $isFirst;

        $address = new ContactAddress();
        $address->business_id = (int) $contact->business_id;
        $address->contact_id = (int) $contact->id;
        $address->fill($this->onlyAddressFields($data));
        $address->is_default = $isDefault;
        $address->is_shipping = $isShipping;
        $address->save();

        $this->enforceSingleFlags($contact, $address, $isDefault, $isShipping);
        $this->syncInlineMirror($contact);

        return response()->json([
            'success' => true,
            'addresses' => $this->listAddresses($contact),
        ], 201);
    }

    /** PATCH /cliente/{id}/enderecos/{addressId} -- edita um endereço. */
    public function update(Request $request, int $id, int $addressId): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $address = $this->locateAddress($contact, $addressId);
        if (! $address instanceof ContactAddress) {
            return $address;
        }

        $validator = $this->validateAddress($request);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $address->fill($this->onlyAddressFields($data));
        if (array_key_exists('is_default', $data)) {
            $address->is_default = (bool) $data['is_default'];
        }
        if (array_key_exists('is_shipping', $data)) {
            $address->is_shipping = (bool) $data['is_shipping'];
        }
        $address->save();

        $this->enforceSingleFlags($contact, $address, $address->is_default, $address->is_shipping);
        $this->syncInlineMirror($contact);

        return response()->json([
            'success' => true,
            'addresses' => $this->listAddresses($contact),
        ]);
    }

    /** DELETE /cliente/{id}/enderecos/{addressId} -- remove (soft delete). */
    public function destroy(Request $request, int $id, int $addressId): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $address = $this->locateAddress($contact, $addressId);
        if (! $address instanceof ContactAddress) {
            return $address;
        }

        $wasDefault = (bool) $address->is_default;
        $wasShipping = (bool) $address->is_shipping;
        $address->delete();

        // Promove o endereço mais antigo restante a padrão/entrega se removeu o que era.
        if ($wasDefault || $wasShipping) {
            $next = $contact->addresses()->orderBy('id')->first();
            if ($next instanceof ContactAddress) {
                if ($wasDefault) {
                    $next->is_default = true;
                }
                if ($wasShipping) {
                    $next->is_shipping = true;
                }
                $next->save();
            }
            $this->syncInlineMirror($contact);
        }

        return response()->json([
            'success' => true,
            'addresses' => $this->listAddresses($contact),
        ]);
    }

    /** PATCH /cliente/{id}/enderecos/{addressId}/padrao -- marca como padrão (+ entrega). */
    public function setDefault(Request $request, int $id, int $addressId): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $address = $this->locateAddress($contact, $addressId);
        if (! $address instanceof ContactAddress) {
            return $address;
        }

        // "Padrão" marca default; entrega segue o padrão salvo o usuário desacoplar.
        $alsoShipping = $request->boolean('is_shipping', true);

        $address->is_default = true;
        if ($alsoShipping) {
            $address->is_shipping = true;
        }
        $address->save();

        $this->enforceSingleFlags($contact, $address, true, $alsoShipping);
        $this->syncInlineMirror($contact);

        return response()->json([
            'success' => true,
            'addresses' => $this->listAddresses($contact),
        ]);
    }

    // ── internos ────────────────────────────────────────────────────────

    /**
     * Localiza contato com escopo multi-tenant Tier 0 + permission gate matricial.
     * Retorna Contact OU JsonResponse 404/403. Cópia do padrão ClienteAutosaveController.
     */
    private function locateContact(int $id): Contact|JsonResponse
    {
        $businessId = (int) request()->session()->get('user.business_id');

        try {
            $contact = Contact::where('business_id', $businessId)
                ->where('id', $id)
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente nao encontrado'], 404);
        }

        $user = auth()->user();
        $type = (string) ($contact->type ?? 'customer');
        $canCustomer = $user->can('customer.update');
        $canSupplier = $user->can('supplier.update');

        $allowed = match ($type) {
            'supplier' => $canSupplier,
            'customer' => $canCustomer,
            'both' => ($canCustomer || $canSupplier),
            default => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Sem permissao'], 403);
        }

        return $contact;
    }

    /**
     * Localiza endereço DENTRO do contato (escopo business_id + contact_id).
     * Retorna ContactAddress OU JsonResponse 404 (não vaza existência cross-tenant).
     */
    private function locateAddress(Contact $contact, int $addressId): ContactAddress|JsonResponse
    {
        $address = $contact->addresses()->whereKey($addressId)->first();
        if (! $address instanceof ContactAddress) {
            return response()->json(['message' => 'Endereco nao encontrado'], 404);
        }

        return $address;
    }

    /** Validator dos campos de endereço (mesma régua de ClienteAutosaveController::endereco). */
    private function validateAddress(Request $request): \Illuminate\Validation\Validator
    {
        $validator = Validator::make($request->all(), [
            'label' => ['nullable', 'string', 'max:80'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'in:'.implode(',', self::UFS)],
            'city_code' => ['nullable', 'string', 'max:7'],
            'is_default' => ['nullable', 'boolean'],
            'is_shipping' => ['nullable', 'boolean'],
        ], [
            'in' => 'O valor do campo :attribute nao e valido.',
            'max' => 'O campo :attribute excede o tamanho maximo.',
            'string' => 'O campo :attribute deve ser texto.',
            'boolean' => 'Deve ser verdadeiro ou falso.',
        ]);

        $validator->after(function ($v) use ($request) {
            $zip = $request->input('zip_code');
            if ($zip !== null && $zip !== '' && strlen((string) preg_replace('/\D/', '', (string) $zip)) !== 8) {
                $v->errors()->add('zip_code', 'CEP deve ter 8 digitos.');
            }
            $cityCode = $request->input('city_code');
            if ($cityCode !== null && $cityCode !== '' && strlen((string) preg_replace('/\D/', '', (string) $cityCode)) !== 7) {
                $v->errors()->add('city_code', 'Codigo IBGE deve ter 7 digitos.');
            }
        });

        return $validator;
    }

    /** @return array<string, mixed> Só os campos de endereço (sem as flags). */
    private function onlyAddressFields(array $data): array
    {
        return array_intersect_key($data, array_flip(array_merge(['label'], self::INLINE_MIRROR)));
    }

    /**
     * Garante no máx. 1 default + 1 shipping por contato: desmarca os OUTROS
     * endereços quando $address virou default/shipping. Multi-tenant: opera
     * só nos endereços DO contato (escopo business_id + contact_id).
     */
    private function enforceSingleFlags(Contact $contact, ContactAddress $address, bool $isDefault, bool $isShipping): void
    {
        if ($isDefault) {
            $contact->addresses()->whereKeyNot($address->id)
                ->where('is_default', true)->update(['is_default' => false]);
        }
        if ($isShipping) {
            $contact->addresses()->whereKeyNot($address->id)
                ->where('is_shipping', true)->update(['is_shipping' => false]);
        }
    }

    /**
     * Espelha o endereço is_default=true de volta nos campos inline de `contacts`
     * (compat UPOS/NFe/Sells). Se não houver default, não mexe nos campos inline.
     */
    private function syncInlineMirror(Contact $contact): void
    {
        $default = $contact->addresses()->where('is_default', true)->orderBy('id')->first();
        if (! $default instanceof ContactAddress) {
            return;
        }

        $contact->forceFill($default->toInlineArray())->save();
    }

    /** @return array<int, array<string, mixed>> */
    private function listAddresses(Contact $contact): array
    {
        return $contact->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (ContactAddress $a) => $this->shapeAddress($a))
            ->all();
    }

    /** @return array<string, mixed> */
    private function shapeAddress(ContactAddress $a): array
    {
        return [
            'id' => (int) $a->id,
            'label' => $a->label,
            'zip_code' => $a->zip_code,
            'address_line_1' => $a->address_line_1,
            'numero' => $a->numero,
            'address_line_2' => $a->address_line_2,
            'neighborhood' => $a->neighborhood,
            'city' => $a->city,
            'state' => $a->state,
            'city_code' => $a->city_code,
            'is_default' => (bool) $a->is_default,
            'is_shipping' => (bool) $a->is_shipping,
            'one_line' => $a->one_line,
        ];
    }
}
