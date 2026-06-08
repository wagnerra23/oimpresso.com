/**
 * dropdownEntries — filtra entries com key vazia ('') ou null antes de mapear pra SelectItem.
 *
 * Por que existe:
 *   UltimatePOS forDropdowns (TaxRate, Account, InvoiceScheme, User, etc) frequentemente
 *   chamam `prepend_none` adicionando key '' = "Nenhum" pro Select2 jQuery legacy.
 *
 *   Radix UI <Select.Item value="" /> recusa: "must have a value prop that is not an
 *   empty string". A escolha vazia já é representada pelo SelectValue placeholder.
 *
 *   Este helper centraliza o filter pra todos os dropdowns da migração MWART.
 *
 * Uso:
 *   {dropdownEntries(props.X).map(([id, name]) => (
 *     <SelectItem key={id} value={id}>{name}</SelectItem>
 *   ))}
 *
 * Refs:
 *   - Bug catalogado em GOTCHAS.md (cockpit-runbook) — PRs #245, #247, #248
 *   - Funções afetadas: TaxRate::forBusinessDropdown, Account::forDropdown,
 *     InvoiceScheme::forDropdown, User::forDropdown, BusinessLocation::forDropdown(show_all=true)
 */
export function dropdownEntries(
  record: Record<string | number, unknown> | null | undefined,
): Array<[string, string]> {
  if (!record) return [];
  return Object.entries(record)
    .filter(([id]) => id !== '' && id !== 'null' && id != null)
    .map(([id, value]) => [id, String(value ?? '')] as [string, string]);
}
