// UC-PCAD-28 · UC-PCAD-30 (Create.casos.md): o "+" de marca/unidade e o rascunho recuperado mudam o
// valor das caixas de seleção por código. No navegador o Radix responde com onValueChange("") e o
// cadastro gravava 0 em unidade, marca e categoria (medido no Chromium em 2026-11-16). O jsdom não
// reproduz esse disparo (medido: um teste montando o Select real passa com e sem a correção), então
// o teste simula o Radix entregando "" ao Select do DS e confere que ele não chega a quem usa.
import { render } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

let entregue: ((valor: string) => void) | undefined
vi.mock('radix-ui', async (original) => {
  const real = (await original()) as typeof import('radix-ui')
  return {
    ...real,
    Select: {
      ...real.Select,
      Root: (props: { onValueChange?: (valor: string) => void }) => {
        entregue = props.onValueChange
        return null
      },
    },
  }
})

import { Select } from '@/Components/ui/select'

describe('Select do DS', () => {
  it('ignora o "" do Radix e repassa a escolha real', () => {
    const recebidos: string[] = []
    render(<Select value="2" onValueChange={(v) => recebidos.push(v)} />)

    entregue?.('')
    entregue?.('3')

    expect(recebidos).toEqual(['3'])
  })

  it('sem onValueChange, não cria um', () => {
    render(<Select value="2" />)
    expect(entregue).toBeUndefined()
  })
})
