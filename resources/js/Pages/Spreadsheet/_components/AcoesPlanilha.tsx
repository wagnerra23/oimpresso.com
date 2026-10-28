import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { toast } from 'sonner'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Inline, Stack } from '@/Components/layout'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog'

/**
 * Ações da lista de planilhas (thread telas-soltas/04, ações que antes levavam à Blade).
 *
 * Cada ação usa o endpoint que a Blade `sheet/index` usa:
 * - nova pasta: `POST /spreadsheet/add-folder` (redireciona para a lista);
 * - mover: `POST /spreadsheet/move-to-folder` (redireciona para a lista);
 * - excluir: `DELETE /spreadsheet/sheets/{id}` em AJAX (responde JSON);
 * - compartilhar: `GET /spreadsheet/get-sheet/{id}/share` em JSON e `POST /spreadsheet/post-share-sheet` em AJAX.
 * As que respondem JSON vão por fetch (com X-Requested-With e o CSRF da página), não pelo router do Inertia.
 */

export type Pasta = { id: number; nome: string }
export type Alvo = { id: number; nome: string; pasta_id: number | null }
type Opcao = { id: number; nome: string }
type Tipo = 'user' | 'role' | 'todo'
type Compartilhar = { usuarios: Opcao[]; funcoes: Opcao[]; tarefas: Opcao[]; selecionados: Record<Tipo, number[]> }

const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''

async function json<T>(url: string, init: RequestInit = {}): Promise<T> {
  const r = await fetch(url, {
    credentials: 'same-origin',
    ...init,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), ...(init.headers ?? {}) },
  })
  if (!r.ok) throw new Error(`HTTP ${r.status}`)
  return (await r.json()) as T
}

const recarregar = () => router.reload({ only: ['planilhas', 'pastas'] })

export function NovaPasta({ aberto, onFechar }: { aberto: boolean; onFechar: () => void }) {
  const [nome, setNome] = useState('')
  useEffect(() => { if (aberto) setNome('') }, [aberto])
  const salvar = () =>
    router.post('/spreadsheet/add-folder', { name: nome.trim() }, { preserveScroll: true, onSuccess: onFechar })

  return (
    <Dialog open={aberto} onOpenChange={(a) => !a && onFechar()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nova pasta</DialogTitle>
          <DialogDescription>Pastas organizam as planilhas; o compartilhamento continua por planilha.</DialogDescription>
        </DialogHeader>
        <Label htmlFor="pl-nova-pasta">Nome</Label>
        <Input id="pl-nova-pasta" value={nome} onChange={(e) => setNome(e.target.value)} autoFocus maxLength={191} />
        <DialogFooter>
          <Button variant="outline" onClick={onFechar}>Cancelar</Button>
          <Button onClick={salvar} disabled={!nome.trim()}>Criar pasta</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function Mover({ alvo, pastas, onFechar }: { alvo: Alvo | null; pastas: Pasta[]; onFechar: () => void }) {
  const [destino, setDestino] = useState<number | null>(null)
  useEffect(() => { setDestino(null) }, [alvo])
  const atual = alvo?.pasta_id ? pastas.find((p) => p.id === alvo.pasta_id)?.nome : 'Sem pasta'
  const mover = () => {
    if (!alvo || destino === null) return
    router.post('/spreadsheet/move-to-folder', { spreadsheet_id: alvo.id, move_to_folder: destino }, { preserveScroll: true, onSuccess: onFechar })
  }

  return (
    <Dialog open={alvo !== null} onOpenChange={(a) => !a && onFechar()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Mover para outra pasta</DialogTitle>
          <DialogDescription>Mover <b>{alvo?.nome}</b> — hoje em {atual ?? 'Sem pasta'}.</DialogDescription>
        </DialogHeader>
        <Stack gap={1} role="radiogroup" aria-label="Pasta de destino">
          {pastas.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma pasta criada ainda.</p>}
          {pastas.filter((p) => p.id !== alvo?.pasta_id).map((p) => (
            <Button key={p.id} variant={destino === p.id ? 'default' : 'outline'} role="radio" aria-checked={destino === p.id}
              className="justify-start" onClick={() => setDestino(p.id)}>{p.nome}</Button>
          ))}
        </Stack>
        <p className="text-xs text-muted-foreground">Mover não muda quem tem acesso — o compartilhamento é por planilha, não por pasta.</p>
        <DialogFooter>
          <Button variant="outline" onClick={onFechar}>Cancelar</Button>
          <Button onClick={mover} disabled={destino === null}>Mover</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function Excluir({ alvo, onFechar }: { alvo: Alvo | null; onFechar: () => void }) {
  const excluir = async () => {
    if (!alvo) return
    try {
      const r = await json<{ success?: boolean; msg?: string }>(`/spreadsheet/sheets/${alvo.id}`, { method: 'DELETE' })
      if (r.success) toast.success(`“${alvo.nome}” excluída.`)
      else toast.error(r.msg ?? 'Não deu para excluir.')
    } catch {
      toast.error('Não deu para excluir agora.')
    } finally {
      onFechar()
      recarregar()
    }
  }

  return (
    <AlertDialog open={alvo !== null} onOpenChange={(a) => !a && onFechar()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Excluir esta planilha?</AlertDialogTitle>
          <AlertDialogDescription>“{alvo?.nome}” some para você e para quem a recebeu. Não dá para desfazer.</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction onClick={excluir}>Excluir planilha</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

function Grupo({ titulo, opcoes, marcados, onTroca }: { titulo: string; opcoes: Opcao[]; marcados: number[]; onTroca: (ids: number[]) => void }) {
  if (opcoes.length === 0) return null
  return (
    <Stack asChild gap={1}>
    <fieldset>
      <legend className="mb-1 text-sm font-medium">{titulo}</legend>
      <div className="max-h-40 overflow-auto rounded-md border p-2">
        {opcoes.map((o) => {
          const id = `pl-sh-${titulo}-${o.id}`
          const on = marcados.includes(o.id)
          return (
            <Inline key={o.id} gap={2} className="py-0.5">
              <Checkbox id={id} checked={on} onCheckedChange={(v) => onTroca(v === true ? [...marcados, o.id] : marcados.filter((x) => x !== o.id))} />
              <Label htmlFor={id} className="font-normal">{o.nome}</Label>
            </Inline>
          )
        })}
      </div>
    </fieldset>
    </Stack>
  )
}

export function CompartilharPlanilha({ alvo, onFechar }: { alvo: Alvo | null; onFechar: () => void }) {
  const [dados, setDados] = useState<Compartilhar | null>(null)
  const [sel, setSel] = useState<Record<Tipo, number[]>>({ user: [], role: [], todo: [] })
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    setDados(null)
    setErro(null)
    if (!alvo) return
    json<Compartilhar>(`/spreadsheet/get-sheet/${alvo.id}/share`)
      .then((d) => { setDados(d); setSel(d.selecionados) })
      .catch(() => setErro('Não deu para carregar com quem ela está compartilhada.'))
  }, [alvo])

  const salvar = async () => {
    if (!alvo) return
    try {
      const r = await json<{ success?: boolean; msg?: string }>('/spreadsheet/post-share-sheet', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sheet_id: alvo.id, share: sel }),
      })
      if (r.success) toast.success('Compartilhamento atualizado.')
      else toast.error(r.msg ?? 'Não deu para compartilhar.')
    } catch {
      toast.error('Não deu para compartilhar agora.')
    } finally {
      onFechar()
      recarregar()
    }
  }

  return (
    <Dialog open={alvo !== null} onOpenChange={(a) => !a && onFechar()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Compartilhar planilha</DialogTitle>
          <DialogDescription>Quem recebe abre e edita <b>{alvo?.nome}</b>, mas não redistribui.</DialogDescription>
        </DialogHeader>
        {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
        {!dados && !erro && <p className="text-sm text-muted-foreground">Carregando…</p>}
        {dados && (
          <Stack gap={3}>
            <Grupo titulo="Usuários" opcoes={dados.usuarios} marcados={sel.user} onTroca={(ids) => setSel((s) => ({ ...s, user: ids }))} />
            <Grupo titulo="Funções" opcoes={dados.funcoes} marcados={sel.role} onTroca={(ids) => setSel((s) => ({ ...s, role: ids }))} />
            <Grupo titulo="Tarefas" opcoes={dados.tarefas} marcados={sel.todo} onTroca={(ids) => setSel((s) => ({ ...s, todo: ids }))} />
          </Stack>
        )}
        <DialogFooter>
          <Button variant="outline" onClick={onFechar}>Cancelar</Button>
          <Button onClick={salvar} disabled={!dados}>Compartilhar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
