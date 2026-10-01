/**
 * Drawer "Classificar" do acervo — onda 2 · PR-6 (thread 02 do playbook Arquivos).
 *
 * Drawer lateral (PT-02), nunca modal full-screen. O que ele faz é o que o backend faz, e
 * nada além: `POST /arquivos/{id}/classificar` RE-APLICA as regras do curador ao arquivo e
 * grava na trilha o motivo escrito aqui (mínimo 5 caracteres — a `ReclassifyArquivoRequest`
 * cobra). O protótipo desenha seletores de bucket/visibilidade/retenção; eles NÃO entraram
 * porque o Service não tem caminho pra forçar classificação — mostrar o seletor seria
 * prometer uma escolha que o servidor descarta. Pendência registrada no `_saida-02.md`.
 */
import { useForm } from '@inertiajs/react'
import { type FormEvent } from 'react'
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'

export interface AlvoClassificar {
  id: number
  nome: string
  bucket: string | null
  classified_by: string | null
}

export default function ClassificarSheet({ alvo, onFechar }: { alvo: AlvoClassificar | null; onFechar: () => void }) {
  const form = useForm({ motivo: '' })

  const fechar = () => {
    form.reset()
    form.clearErrors()
    onFechar()
  }

  const enviar = (e: FormEvent) => {
    e.preventDefault()
    if (!alvo) return
    form.post(`/arquivos/${alvo.id}/classificar`, { preserveScroll: true, onSuccess: fechar })
  }

  return (
    <Sheet open={alvo !== null} onOpenChange={(aberto) => !aberto && fechar()}>
      <SheetContent className="w-full sm:max-w-md">
        <form onSubmit={enviar} className="flex h-full flex-col">
          <SheetHeader>
            <SheetTitle>Classificar arquivo</SheetTitle>
            <SheetDescription>
              Re-aplica as regras do curador a <b>{alvo?.nome}</b>. A classificação fica gravada com a regra
              que bateu (<code className="mono">classified_by</code>) e o seu motivo entra na trilha.
            </SheetDescription>
          </SheetHeader>

          <div className="flex flex-col gap-4 px-4">
            <p className="text-sm text-muted-foreground">
              Hoje: <code className="mono">{alvo?.bucket ?? 'sem classificação'}</code>
              {alvo?.classified_by ? <> · por <code className="mono">{alvo.classified_by}</code></> : null}
            </p>
            <div className="flex flex-col gap-2">
              <Label htmlFor="arq-classificar-motivo">Motivo</Label>
              <Textarea
                id="arq-classificar-motivo"
                value={form.data.motivo}
                onChange={(e) => form.setData('motivo', e.target.value)}
                minLength={5}
                maxLength={500}
                required
                rows={4}
                placeholder="Por que reclassificar? Vai pra trilha, para a auditoria LGPD."
              />
              {form.errors.motivo && <p className="text-xs text-destructive">{form.errors.motivo}</p>}
            </div>
          </div>

          <SheetFooter>
            <Button type="submit" disabled={form.processing || form.data.motivo.trim().length < 5}>
              Reclassificar
            </Button>
            <Button type="button" variant="ghost" onClick={fechar}>
              Cancelar
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
