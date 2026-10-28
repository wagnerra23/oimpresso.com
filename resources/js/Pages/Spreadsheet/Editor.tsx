import { useEffect, useRef, useState, type ChangeEvent } from 'react'
import { Head, router } from '@inertiajs/react'
import { ArrowLeft, Download, FileUp, Save } from 'lucide-react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Inline } from '@/Components/layout'

/**
 * Editor de planilha — /spreadsheet/sheets/create e /spreadsheet/sheets/{id} (thread telas-soltas/04b).
 *
 * Lei: `Editor.charter.md`. Casos: `Editor.casos.md`. Receita:
 * `memory/requisitos/Spreadsheet/RUNBOOK-editor.md`. Forma:
 * `prototipo-ui/cowork/Wagner/planilhas-page.jsx` → `Editor`.
 *
 * A grade é o Luckysheet, o mesmo da Blade, carregado do mesmo CDN. Gravar usa os endpoints de
 * sempre (`store`/`update`) com o mesmo formato: `sheet_data = JSON.stringify(getAllSheets())`.
 * `?legacy=1` abre a Blade até o F5.
 */

type Planilha = { id: number; nome: string; dados: unknown[] | null; minha: boolean }
type Props = { planilha: Planilha | null; pasta_id: number | null; pode_gravar: boolean; voltar: string }

type Lucky = {
  create: (opts: Record<string, unknown>) => void
  destroy: () => void
  getAllSheets: () => unknown[]
}
type LuckyExcelApi = {
  transformExcelToLucky: (f: File, cb: (json: { sheets?: unknown[]; info?: { name?: string } }) => void) => void
}
type Janela = { luckysheet?: Lucky; LuckyExcel?: LuckyExcelApi }

// Os mesmos arquivos de `Modules/Spreadsheet/Resources/views/layouts/app.blade.php`.
const CDN = 'https://cdn.jsdelivr.net/npm'
const CSS = [
  `${CDN}/luckysheet/dist/plugins/css/pluginsCss.css`,
  `${CDN}/luckysheet/dist/plugins/plugins.css`,
  `${CDN}/luckysheet/dist/css/luckysheet.css`,
  `${CDN}/luckysheet/dist/assets/iconfont/iconfont.css`,
]
const JS = [
  `${CDN}/luckysheet@latest/dist/plugins/js/plugin.js`,
  `${CDN}/luckysheet@2.1.12/dist/luckysheet.umd.min.js`,
  `${CDN}/luckyexcel/dist/luckyexcel.umd.js`,
]
const CONTAINER = 'oi-luckysheet'

function carregarCss(href: string) {
  if (document.querySelector(`link[href="${href}"]`)) return
  const l = document.createElement('link')
  l.rel = 'stylesheet'
  l.href = href
  document.head.appendChild(l)
}

function carregarJs(src: string): Promise<void> {
  const ja = document.querySelector<HTMLScriptElement>(`script[src="${src}"]`)
  if (ja?.dataset.pronto === '1') return Promise.resolve()
  return new Promise((resolve, reject) => {
    const s = ja ?? document.createElement('script')
    s.addEventListener('load', () => { s.dataset.pronto = '1'; resolve() })
    s.addEventListener('error', () => reject(new Error(`não carregou: ${src}`)))
    if (!ja) {
      s.src = src
      document.head.appendChild(s)
    }
  })
}

/** Carrega o Luckysheet uma vez por página, na ordem da Blade (plugin → luckysheet → luckyexcel). */
async function carregarEditor(): Promise<Janela> {
  CSS.forEach(carregarCss)
  for (const src of JS) await carregarJs(src)
  return window as unknown as Janela
}

export default function SpreadsheetEditor({ planilha, pasta_id, pode_gravar, voltar }: Props) {
  const [nome, setNome] = useState(planilha?.nome ?? 'Planilha sem título')
  const [sujo, setSujo] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)
  const [pronto, setPronto] = useState(false)
  const [gravando, setGravando] = useState(false)
  const arquivo = useRef<HTMLInputElement>(null)
  const podeImportar = pode_gravar && (planilha === null || planilha.minha)

  const montar = (w: Janela, dados: unknown[] | null) => {
    w.luckysheet?.create({
      container: CONTAINER,
      showinfobar: false,
      lang: 'en',
      allowEdit: true,
      forceCalculation: false,
      ...(dados && dados.length ? { data: dados } : {}),
      hook: { updated: () => setSujo(true) },
    })
  }

  useEffect(() => {
    let vivo = true
    carregarEditor()
      .then((w) => {
        if (!vivo) return
        montar(w, planilha?.dados ?? null)
        setPronto(true)
      })
      .catch(() => vivo && setErro('O editor de planilhas não carregou. Confira a conexão e recarregue a página.'))
    return () => {
      vivo = false
      try { (window as unknown as Janela).luckysheet?.destroy() } catch { /* nada montado */ }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [planilha?.id])

  const importar = (e: ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0]
    e.target.value = ''
    if (!f) return
    if (!/\.xlsx$/i.test(f.name)) {
      setAviso('Só arquivos .xlsx — o conversor do módulo não lê .xls.')
      return
    }
    const w = window as unknown as Janela
    w.LuckyExcel?.transformExcelToLucky(f, (json) => {
      if (!json.sheets || json.sheets.length === 0) {
        setAviso('Não deu para ler o conteúdo do arquivo. Arquivos .xls não são aceitos.')
        return
      }
      w.luckysheet?.destroy()
      montar(w, json.sheets)
      setNome(f.name.replace(/\.xlsx$/i, ''))
      setSujo(true)
      setAviso(`“${f.name}” carregado — revise e salve para gravar.`)
    })
  }

  const salvar = () => {
    const w = window as unknown as Janela
    if (!w.luckysheet) return
    const corpo = { name: nome, sheet_data: JSON.stringify(w.luckysheet.getAllSheets()) }
    const fim = { onFinish: () => setGravando(false), onSuccess: () => setSujo(false) }
    setGravando(true)
    if (planilha) {
      router.put(`/spreadsheet/sheets/${planilha.id}`, corpo, fim)
    } else {
      router.post('/spreadsheet/sheets', { ...corpo, folder_id: pasta_id }, fim)
    }
  }

  const estado = sujo ? 'alterações não salvas' : planilha ? 'salva' : 'nunca salva'

  return (
    <AppShellV2
      title={planilha ? planilha.nome : 'Nova planilha'}
      breadcrumbItems={[{ label: 'Planilhas', href: '/spreadsheet/sheets' }, { label: planilha ? planilha.nome : 'Nova planilha' }]}
    >
      <Head title={planilha ? planilha.nome : 'Nova planilha'} />

      <Inline wrap className="mb-3" data-contract="barra-editor">
        <Button asChild variant="outline">
          <a href={voltar}><ArrowLeft className="size-4" aria-hidden="true" /> Voltar</a>
        </Button>
        <Input
          className="w-72"
          value={nome}
          onChange={(e) => { setNome(e.target.value); setSujo(true) }}
          aria-label="Nome da planilha"
          disabled={!pode_gravar}
        />
        <span className="text-sm text-muted-foreground" aria-live="polite">{estado}</span>
        <span className="flex-1" />
        {podeImportar && (
          <>
            <input ref={arquivo} type="file" accept=".xlsx" className="sr-only" onChange={importar} aria-label="Arquivo .xlsx" />
            <Button variant="outline" onClick={() => arquivo.current?.click()} disabled={!pronto}>
              <FileUp className="size-4" aria-hidden="true" /> Importar .xlsx
            </Button>
          </>
        )}
        <Button variant="outline" disabled title="O módulo ainda não tem rota de download.">
          <Download className="size-4" aria-hidden="true" /> Baixar
        </Button>
        {pode_gravar && (
          <Button onClick={salvar} disabled={!pronto || gravando}>
            <Save className="size-4" aria-hidden="true" /> {planilha ? 'Atualizar' : 'Salvar'}
          </Button>
        )}
      </Inline>

      {!pode_gravar && (
        <p className="mb-3 rounded-md border bg-muted/40 px-3 py-2 text-sm">
          Você só tem acesso de leitura: pode ver e mexer na planilha, mas as alterações não são gravadas.
        </p>
      )}
      {erro && <p role="alert" className="mb-3 rounded-md border border-destructive/40 px-3 py-2 text-sm text-destructive">{erro}</p>}
      {aviso && <p role="status" className="mb-3 rounded-md border bg-muted/40 px-3 py-2 text-sm">{aviso}</p>}

      <div id={CONTAINER} className="relative h-[calc(100vh-14rem)] min-h-[420px] w-full rounded-md border" />
    </AppShellV2>
  )
}
