import { useState } from 'react'
import { Search, X } from 'lucide-react'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import { Inline, Stack } from '@/Components/layout'
import { TIPO_BUSCA_LABEL, type TipoBusca } from '@/Types/os'
import { cn } from '@/Lib/utils'

interface Props {
  onBuscar: (tipo: TipoBusca, numero: string, serie: string) => void
  loading: boolean
  buscaPorCelular: boolean
}

const PLACEHOLDER: Record<TipoBusca, string> = {
  job_sheet_no: 'ex: JS2026/0001',
  invoice_no: 'ex: 0001',
  mobile_num: 'o celular do cadastro',
}

export function OsLookupForm({ onBuscar, loading, buscaPorCelular }: Props) {
  const tipos: TipoBusca[] = buscaPorCelular
    ? ['job_sheet_no', 'invoice_no', 'mobile_num']
    : ['job_sheet_no', 'invoice_no']

  const [tipo, setTipo] = useState<TipoBusca>('job_sheet_no')
  const [numero, setNumero] = useState('')
  const [serie, setSerie] = useState('')
  const [touched, setTouched] = useState(false)

  const invalid = touched && !numero.trim()

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setTouched(true)
    if (!numero.trim()) return
    onBuscar(tipo, numero.trim(), serie.trim())
  }

  function handleLimpar() {
    setTipo('job_sheet_no')
    setNumero('')
    setSerie('')
    setTouched(false)
  }

  return (
    <form onSubmit={handleSubmit}>
      <Stack gap={5}>
        <Stack gap={2}>
          <Label className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Buscar por
          </Label>
          <RadioGroup
            value={tipo}
            onValueChange={(v) => setTipo(v as TipoBusca)}
            className="grid-cols-3"
          >
            {tipos.map((t) => (
              <Inline key={t} gap={2} align="center">
                <RadioGroupItem value={t} id={`tipo-${t}`} />
                <Label htmlFor={`tipo-${t}`} className="text-sm font-normal cursor-pointer">
                  {TIPO_BUSCA_LABEL[t]}
                </Label>
              </Inline>
            ))}
          </RadioGroup>
        </Stack>

        <Stack gap={2}>
          <Label
            htmlFor="os-numero"
            className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground"
          >
            {TIPO_BUSCA_LABEL[tipo]}
          </Label>
          <Input
            id="os-numero"
            type="text"
            placeholder={PLACEHOLDER[tipo]}
            maxLength={20}
            value={numero}
            onChange={(e) => setNumero(e.target.value.replace(/[^A-Za-z0-9/.-]/g, ''))}
            className={cn(
              'h-11 text-base',
              invalid && 'border-destructive ring-destructive focus-visible:ring-destructive',
            )}
            aria-invalid={invalid}
            aria-describedby={invalid ? 'os-numero-error' : undefined}
          />
          {invalid && (
            <p id="os-numero-error" className="text-xs text-destructive">
              Informe o número para pesquisar.
            </p>
          )}
        </Stack>

        <Stack gap={2}>
          <Label
            htmlFor="os-serie"
            className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground"
          >
            Nº de série do aparelho (opcional)
          </Label>
          <Input
            id="os-serie"
            type="text"
            maxLength={50}
            value={serie}
            onChange={(e) => setSerie(e.target.value.replace(/[^A-Za-z0-9/.-]/g, ''))}
            className="h-11 text-base"
          />
        </Stack>

        <div className="grid grid-cols-2 gap-3 pt-1">
          <Button type="submit" disabled={loading} className="h-11 text-sm font-semibold gap-2">
            <Search className="w-4 h-4" />
            {loading ? 'Buscando...' : 'Pesquisar'}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={handleLimpar}
            disabled={loading}
            className="h-11 text-sm font-medium gap-2"
          >
            <X className="w-4 h-4" />
            Limpar
          </Button>
        </div>
      </Stack>
    </form>
  )
}
