// Aviso de margem mínima no preço do produto simples (telas Novo e Editar), na forma do protótipo
// (prototipo-ui/cowork/Wagner/produto-blade-forms.jsx, `avisos.margem`). A margem mínima vem da regra
// da empresa em Fluxos (UC-FLX-35, RegrasDaEmpresa::margemMinima); o protótipo usava 30% só como
// exemplo. O cadastro só avisa; quem barra é a conversão do orçamento em venda.
import { render, screen } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import { CamposPreco } from '@/Pages/Produto/_components/formulario/CamposPreco'
import { abaixoDaMargemMinima } from '@/Pages/Produto/_components/formulario/precos'

const preco = (margem: number, compra = 10) => ({ compra, margem, venda: compra * (1 + margem / 100) })
const nada = () => {}

describe('Aviso de margem mínima no preço do produto', () => {
  it('com a % de lucro abaixo da margem mínima da empresa, avisa com o valor da regra', () => {
    render(<CamposPreco valor={preco(25)} mudar={nada} aliquota={0} margemMinima={30} />)
    const aviso = screen.getByTestId('aviso-margem-minima')
    expect(aviso.textContent).toContain('Abaixo da margem mínima da empresa (30,00%, em Fluxos)')
  })

  it('na margem mínima ou acima, não avisa', () => {
    render(<CamposPreco valor={preco(30)} mudar={nada} aliquota={0} margemMinima={30} />)
    expect(screen.queryByTestId('aviso-margem-minima')).toBeNull()
  })

  it('empresa sem a regra (margem mínima nula) não avisa nada', () => {
    render(<CamposPreco valor={preco(5)} mudar={nada} aliquota={0} margemMinima={null} />)
    expect(screen.queryByTestId('aviso-margem-minima')).toBeNull()
  })

  it('sem preço de compra lançado não há margem a comparar', () => {
    expect(abaixoDaMargemMinima(preco(0, 0), 30)).toBe(false)
    expect(abaixoDaMargemMinima(preco(10), 30)).toBe(true)
  })
})
