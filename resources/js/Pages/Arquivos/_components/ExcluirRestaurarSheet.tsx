/**
 * Drawer "Excluir" / "Restaurar" do acervo — onda 2 · PR-7 (thread 03 do playbook Arquivos).
 *
 * Drawer lateral (PT-02), mesmo desenho do `ClassificarSheet`. Excluir é SOFT-delete: o
 * arquivo some do acervo, o conteúdo fica, e dá pra restaurar dentro do grace (prazo de carência,
 * `arquivos_retention.grace_period_days` — o número vem do servidor, nunca escrito aqui). Apagar de verdade é só o job de retenção — esta
 * tela nunca faz hard-delete nem purge (D4). O motivo (≥5) vai pra linha da trilha.
 */
import { useForm } from '@inertiajs/react'
import { type FormEvent } from 'react'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Stack } from '@/Components/layout'

export interface AlvoExcluirRestaurar {
  modo: 'excluir' | 'restaurar'
  id: number
  nome: string
  /** `restaurar`: último dia do grace (AAAA-MM-DD). */
  restaurar_ate?: string | null
}

const dataBR = (iso?: string | null) => (iso ? iso.split('-').reverse().join('/') : '—')

export default function ExcluirRestaurarSheet({
  alvo,
  onFechar,
}: {
  alvo: AlvoExcluirRestaurar | null
  onFechar: () => void
}) {
  const form = useForm({ reason: '' })
  const excluir = alvo?.modo === 'excluir'

  const fechar = () => {
    form.reset()
    form.clearErrors()
    onFechar()
  }

  const enviar = (e: FormEvent) => {
    e.preventDefault()
    if (!alvo) return
    form.post(`/arquivos/${alvo.id}/${alvo.modo}`, { preserveScroll: true, onSuccess: fechar })
  }

  const erro = (form.errors as Record<string, string | undefined>).arquivo ?? form.errors.reason

  return (
    <Sheet open={alvo !== null} onOpenChange={(aberto) => !aberto && fechar()}>
      <SheetContent className="w-full sm:max-w-md">
        <Stack asChild gap={0} className="h-full">
          <form onSubmit={enviar}>
            <SheetHeader>
              <SheetTitle>{excluir ? 'Excluir arquivo' : 'Restaurar arquivo'}</SheetTitle>
              <SheetDescription>
                {excluir ? (
                  <>
                    <b>{alvo?.nome}</b> sai do acervo, mas o conteúdo fica guardado: dá pra restaurar durante o prazo
                    de carência. Depois dele a retenção apaga de vez. Nada é apagado agora.
                  </>
                ) : (
                  <>
                    <b>{alvo?.nome}</b> volta para o acervo. Dá pra restaurar até {dataBR(alvo?.restaurar_ate)}.
                  </>
                )}
              </SheetDescription>
            </SheetHeader>

            <Stack gap={2} className="px-4">
              <Label htmlFor="arq-excluir-motivo">Motivo</Label>
              <Textarea
                id="arq-excluir-motivo"
                value={form.data.reason}
                onChange={(e) => form.setData('reason', e.target.value)}
                minLength={5}
                maxLength={500}
                required
                rows={4}
                placeholder="Vai pra trilha, para a auditoria LGPD."
              />
              {erro && <p className="text-xs text-destructive">{erro}</p>}
            </Stack>

            <SheetFooter>
              <Button
                type="submit"
                variant={excluir ? 'destructive' : 'default'}
                disabled={form.processing || form.data.reason.trim().length < 5}
              >
                {excluir ? 'Excluir' : 'Restaurar'}
              </Button>
              <Button type="button" variant="ghost" onClick={fechar}>
                Cancelar
              </Button>
            </SheetFooter>
          </form>
        </Stack>
      </SheetContent>
    </Sheet>
  )
}
