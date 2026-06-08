import { useState } from 'react'
import { Search, X } from 'lucide-react'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import { FILTER_OPTIONS } from '@/Types/os'
import { cn } from '@/Lib/utils'

interface Props {
  onBuscar: (numero: string, estagio: string) => void
  loading: boolean
}

export function OsLookupForm({ onBuscar, loading }: Props) {
  const [numero, setNumero] = useState('')
  const [estagio, setEstagio] = useState('todos')
  const [touched, setTouched] = useState(false)

  const invalid = touched && !numero.trim()

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setTouched(true)
    if (!numero.trim()) return
    onBuscar(numero.trim(), estagio)
  }

  function handleLimpar() {
    setNumero('')
    setEstagio('todos')
    setTouched(false)
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      <div className="flex flex-col gap-2">
        <Label
          htmlFor="os-numero"
          className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground"
        >
          Nº da OS
        </Label>
        <Input
          id="os-numero"
          type="text"
          inputMode="numeric"
          placeholder="ex: 4821"
          maxLength={20}
          value={numero}
          onChange={(e) => setNumero(e.target.value.replace(/\D/g, ''))}
          className={cn(
            'h-11 text-base',
            invalid && 'border-destructive ring-destructive focus-visible:ring-destructive',
          )}
          aria-invalid={invalid}
          aria-describedby={invalid ? 'os-numero-error' : undefined}
        />
        {invalid && (
          <p id="os-numero-error" className="text-xs text-destructive">
            Informe o número da OS para pesquisar.
          </p>
        )}
      </div>

      <div className="flex flex-col gap-2">
        <Label className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
          Filtrar por estágio (opcional)
        </Label>
        <RadioGroup value={estagio} onValueChange={setEstagio} className="flex flex-col gap-2">
          {FILTER_OPTIONS.map((opt) => (
            <div
              key={opt.value}
              className={cn(
                'flex items-center gap-3 px-3 py-2.5 rounded-md border cursor-pointer transition-colors',
                estagio === opt.value
                  ? 'border-primary bg-primary/5'
                  : 'border-border hover:border-primary/50 hover:bg-muted/50',
              )}
              onClick={() => setEstagio(opt.value)}
            >
              <RadioGroupItem value={opt.value} id={`estagio-${opt.value}`} />
              <Label
                htmlFor={`estagio-${opt.value}`}
                className="text-sm cursor-pointer font-normal"
              >
                {opt.label}
              </Label>
            </div>
          ))}
        </RadioGroup>
      </div>

      <div className="grid grid-cols-2 gap-3 pt-1">
        <Button
          type="submit"
          disabled={loading}
          className="h-11 text-sm font-semibold gap-2"
        >
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
    </form>
  )
}
