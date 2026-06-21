// Pagina publica — nao usa AppShellV2/Sidebar do ERP. Layout limpo
// e centralizado para o cliente final acompanhar o status do pedido.

import { useState } from 'react'
import { Head } from '@inertiajs/react'
import { ArrowLeft, AlertCircle, FileSearch } from 'lucide-react'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader } from '@/Components/ui/card'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { OsLookupForm } from './_components/OsLookupForm'
import { OsResultCard } from './_components/OsResultCard'
import type { OrdemServico } from '@/Types/os'

type Estado = 'busca' | 'resultado' | 'nao-encontrado'

export default function ConsultaOsIndex() {
  const [estado, setEstado] = useState<Estado>('busca')
  const [loading, setLoading] = useState(false)
  const [os, setOs] = useState<OrdemServico | null>(null)
  const [ultimoNumero, setUltimoNumero] = useState('')
  const [erro, setErro] = useState<string | null>(null)

  async function handleBuscar(numero: string, estagio: string) {
    setLoading(true)
    setErro(null)
    setUltimoNumero(numero)

    try {
      const params = new URLSearchParams({ numero, estagio })
      const res = await fetch(`/consulta-os/buscar?${params}`, {
        headers: { Accept: 'application/json' },
      })

      if (res.status === 404) {
        setOs(null)
        setEstado('nao-encontrado')
        return
      }

      if (!res.ok) throw new Error(`Erro ${res.status}`)

      const data = await res.json()

      if (data.found && data.os) {
        setOs(data.os)
        setEstado('resultado')
      } else {
        setOs(null)
        setEstado('nao-encontrado')
      }
    } catch {
      setErro('Não foi possível conectar ao servidor. Tente novamente.')
    } finally {
      setLoading(false)
    }
  }

  function handleVoltar() {
    setEstado('busca')
    setOs(null)
    setErro(null)
  }

  return (
    <>
      <Head title="Consulta de OS" />

      <div className="min-h-screen bg-muted/30 flex flex-col items-center justify-start py-12 px-4">
        <div className="flex items-center gap-2.5 mb-8">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center flex-shrink-0">
            <FileSearch className="w-4 h-4 text-white" />
          </div>
          <span className="text-base font-semibold text-muted-foreground tracking-tight">
            Oimpresso ERP
          </span>
        </div>

        {estado === 'busca' && (
          <Card className="w-full max-w-md shadow-md">
            <CardHeader className="text-center pb-2 pt-7">
              <h1 className="text-lg font-semibold text-foreground">
                Consulta de Ordem de Serviço
              </h1>
              <p className="text-sm text-muted-foreground mt-1">
                Digite o número da OS para acompanhar o status do seu pedido
              </p>
            </CardHeader>

            <CardContent className="pt-4 pb-7 px-7">
              {erro && (
                <Alert variant="destructive" className="mb-5">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{erro}</AlertDescription>
                </Alert>
              )}
              <OsLookupForm onBuscar={handleBuscar} loading={loading} />
            </CardContent>
          </Card>
        )}

        {estado === 'resultado' && os && (
          <div className="w-full max-w-2xl">
            <Button
              variant="default"
              size="sm"
              onClick={handleVoltar}
              className="mb-5 gap-1.5"
            >
              <ArrowLeft className="w-3.5 h-3.5" />
              Voltar à consulta
            </Button>
            <OsResultCard os={os} />
          </div>
        )}

        {estado === 'nao-encontrado' && (
          <div className="w-full max-w-md">
            <Button
              variant="default"
              size="sm"
              onClick={handleVoltar}
              className="mb-5 gap-1.5"
            >
              <ArrowLeft className="w-3.5 h-3.5" />
              Voltar à consulta
            </Button>

            <Card className="shadow-md text-center py-10 px-8">
              <div className="flex justify-center mb-4">
                <div className="w-14 h-14 rounded-full bg-muted flex items-center justify-center">
                  <AlertCircle className="w-7 h-7 text-muted-foreground" />
                </div>
              </div>
              <h2 className="text-base font-semibold mb-2">OS não encontrada</h2>
              <p className="text-sm text-muted-foreground leading-relaxed">
                Não encontramos a OS{' '}
                <span className="font-mono font-semibold text-foreground">
                  #{ultimoNumero}
                </span>{' '}
                com os filtros selecionados.
                <br />
                Verifique o número ou tente sem filtro de estágio.
              </p>
              <Button onClick={handleVoltar} className="mt-6 gap-2">
                <ArrowLeft className="w-3.5 h-3.5" />
                Tentar novamente
              </Button>
            </Card>
          </div>
        )}
      </div>
    </>
  )
}
