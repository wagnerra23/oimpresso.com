// UC-FLX-38 · US-COM-024 — "Método de custo" nas telas Novo e Editar produto (React).
//
// O módulo Compras desenha e grava o campo pelos ganchos do core; o formulário Blade já o tinha
// (Modules/Compras/Resources/views/partials/metodo_custo_produto.blade.php). Aqui a tela React recebe
// a prop `metodoCusto` e manda os mesmos dois campos do Blade, `has_module_data` e
// `compras_metodo_custo`, e só quando a pessoa mudou o método: salvar sem mexer não regrava nada.
// O que o servidor entrega na prop é provado em
// Modules/Compras/Tests/Feature/MetodoCustoNoCadastroDoProdutoTest.php.
import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

const enviado: { payload: Record<string, unknown> | null } = { payload: null }

vi.mock('@inertiajs/react', async () => {
  const React = await import('react')
  return {
    Head: () => null,
    Link: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) =>
      React.createElement('a', { href, ...resto }, children),
    useForm: (inicial: Record<string, unknown>) => {
      const [data, setData] = React.useState(inicial)
      const transformar = React.useRef((d: Record<string, unknown>) => d)
      const enviar = () => {
        enviado.payload = transformar.current(data)
      }
      return {
        data,
        setData: (k: string, v: unknown) => setData((d) => ({ ...d, [k]: v })),
        transform: (fn: (d: Record<string, unknown>) => Record<string, unknown>) => {
          transformar.current = fn
        },
        post: enviar,
        put: enviar,
        processing: false,
        errors: {},
      }
    },
  }
})
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: unknown }) => children }))

// O Select do Radix usa APIs de ponteiro e rolagem que o jsdom não tem.
Element.prototype.hasPointerCapture ??= () => false
Element.prototype.releasePointerCapture ??= () => {}
Element.prototype.scrollIntoView ??= () => {}

import ProdutoCreate from '@/Pages/Produto/Create'
import {
  camposDoMetodoCusto,
  opcoesDoMetodo,
  valorInicialDoMetodo,
} from '@/Pages/Produto/_components/formulario/metodoCusto'

const base = {
  categories: { 1: 'Banner' },
  brands: {},
  units: { 4: 'Unidade (UN)' },
  taxes: {},
  taxAttributes: {},
  barcodeTypes: { C128: 'Code 128' },
  barcodeDefault: 'C128',
  defaultProfitPercent: 25,
  duplicateProduct: null,
  subCategories: {},
  businessLocations: { 3: 'Loja' },
  rackDetails: null,
  sellingPriceGroupCount: 0,
  productTypes: { single: 'Simples' },
  warranties: {},
  commonSettings: {},
  enableExpiry: false,
  enableLot: false,
  enableRacks: false,
}

function salvar() {
  enviado.payload = null
  fireEvent.change(document.querySelector('#name') as HTMLInputElement, { target: { value: 'Lona 440g' } })
  fireEvent.click(screen.getByRole('button', { name: 'Salvar' }))
  return enviado.payload as Record<string, unknown> | null
}

function escolher(rotulo: string) {
  fireEvent.keyDown(document.querySelector('#compras_metodo_custo') as HTMLElement, { key: 'Enter' })
  fireEvent.click(screen.getByRole('option', { name: rotulo }))
}

describe('UC-PCAD-19 · UC-FLX-38 · Método de custo na tela Novo produto', () => {
  it('sem o módulo Compras no pacote (prop ausente), o campo não aparece e o envio não leva nada dele', () => {
    render(<ProdutoCreate {...base} />)
    expect(document.querySelector('#compras_metodo_custo')).toBeNull()
    const payload = salvar()
    expect(payload).not.toBeNull()
    expect(payload).not.toHaveProperty('compras_metodo_custo')
    expect(payload).not.toHaveProperty('has_module_data')
  })

  it('escolher "Médio" manda compras_metodo_custo=medio e has_module_data=1, como o Blade', () => {
    render(<ProdutoCreate {...base} metodoCusto={{ atual: '', empresa: 'por_insumo', editavel: true }} />)
    expect(screen.getByText(/Hoje a empresa usa: Definido em cada insumo\./)).toBeTruthy()
    escolher('Médio')
    expect(salvar()).toMatchObject({ compras_metodo_custo: 'medio', has_module_data: 1 })
  })

  it('salvar sem mexer no método não manda o campo', () => {
    render(<ProdutoCreate {...base} metodoCusto={{ atual: '', empresa: 'medio', editavel: true }} />)
    const payload = salvar()
    expect(payload).not.toBeNull()
    expect(payload).not.toHaveProperty('compras_metodo_custo')
    expect(payload).not.toHaveProperty('has_module_data')
  })

  it('sem purchase.update o campo vem desabilitado e não vai no envio', () => {
    render(<ProdutoCreate {...base} metodoCusto={{ atual: '', empresa: 'medio', editavel: false }} />)
    expect((document.querySelector('#compras_metodo_custo') as HTMLButtonElement).disabled).toBe(true)
    expect(salvar()).not.toHaveProperty('compras_metodo_custo')
  })
})

describe('UC-PCAD-19 · UC-PEDIT-17 · regras do envio (metodoCusto.ts)', () => {
  const editavel = { atual: 'medio', empresa: 'por_insumo', editavel: true }

  it('trocar para "Não definido" manda vazio, que apaga o método das variações', () => {
    expect(camposDoMetodoCusto(editavel, 'nao-definido')).toEqual({ has_module_data: 1, compras_metodo_custo: '' })
  })

  it('o valor inicial é o método gravado; sem método, "Não definido"; sem mudança, nada vai', () => {
    expect(valorInicialDoMetodo(editavel)).toBe('medio')
    expect(valorInicialDoMetodo({ ...editavel, atual: '' })).toBe('nao-definido')
    expect(camposDoMetodoCusto(editavel, 'medio')).toEqual({})
  })

  it('"misto" só aparece como opção quando é o estado gravado', () => {
    expect(opcoesDoMetodo('misto').map((o) => o.valor)).toEqual(['misto', 'nao-definido', 'medio', 'ultimo'])
    expect(opcoesDoMetodo('medio').map((o) => o.valor)).toEqual(['nao-definido', 'medio', 'ultimo'])
  })
})
