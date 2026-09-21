// JanaMetaNovaDrawer — CRIAR meta sem sair do Painel (PR-2b · RUNBOOK-metas §9.4).
//
// Âncora: `prototipo-ui/cowork/Wagner/jana-metas.jsx` §`JmMetaFormDrawer` — âncora de SÍMBOLO
// (re-localize com `grep -n "JmMetaFormDrawer" prototipo-ui/cowork/Wagner/jana-metas.jsx`).
//
// Por que é um componente SEPARADO do `JanaMetaDrawer`: aquele recebe uma `Meta` e mostra
// apuração, série e origem do número — criar não tem nada disso, e enfiar os dois modos no
// mesmo arquivo faria o drawer de detalhe carregar um formulário que ele nunca usa. São
// duas gavetas com o mesmo desenho, não um componente com dois cérebros.
//
// CONTRATO (RUNBOOK-metas §3, lido das Blade, não inventado):
//   · 4 campos no create — nome · slug (`[a-z0-9_]+`) · unidade · tipo_agregacao
//     (o EDIT tem 2, e a diferença é deliberada: slug e agregação não se editam depois)
//   · a validação REAL é o `StoreMetaRequest` — o front não a reimplementa, só evita
//     mandar o que o servidor já recusaria de forma óbvia (nome/slug vazios)
//   · `business_id` é decidido pelo SERVIDOR (Tier 0, ADR 0093) — não vai no payload
import * as React from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Stack } from '@/Components/layout';

/** Enums REAIS da migration (RUNBOOK §3) — não inventados aqui. */
const UNIDADES = ['R$', 'qtd', '%', 'dias'] as const;
const AGREGACOES = [
  { valor: 'soma', rotulo: 'Soma — acumula na janela' },
  { valor: 'media', rotulo: 'Média — valor médio da janela' },
  { valor: 'ultimo', rotulo: 'Último valor da janela' },
  { valor: 'contagem', rotulo: 'Contagem de registros' },
] as const;

/** Enums REAIS da migration `jana_meta_periodos` (mesma lista do `StorePeriodoRequest`). */
const PERIODOS = [
  { valor: 'mes', rotulo: 'Este mes' },
  { valor: 'trim', rotulo: 'Este trimestre' },
  { valor: 'ano', rotulo: 'Este ano' },
  { valor: 'custom', rotulo: 'Personalizado' },
] as const;

/** `Date` -> `YYYY-MM-DD` no fuso LOCAL. `toISOString()` converte pra UTC e, a leste de
 *  Greenwich, devolve o dia anterior — a janela nasceria um dia curta. */
function iso(d: Date): string {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

/** Janela sugerida pro tipo escolhido. E CONVENIENCIA, nao regra: o servidor valida o
 *  que chegar (`StoreMetaRequest`), e em `custom` o usuario digita as duas datas. */
function janelaDe(tipo: string, hoje = new Date()): { ini: string; fim: string } | null {
  const a = hoje.getFullYear();
  if (tipo === 'mes') {
    return { ini: iso(new Date(a, hoje.getMonth(), 1)), fim: iso(new Date(a, hoje.getMonth() + 1, 0)) };
  }
  if (tipo === 'trim') {
    const t = Math.floor(hoje.getMonth() / 3) * 3;
    return { ini: iso(new Date(a, t, 1)), fim: iso(new Date(a, t + 3, 0)) };
  }
  if (tipo === 'ano') {
    return { ini: iso(new Date(a, 0, 1)), fim: iso(new Date(a, 12, 0)) };
  }
  return null; // custom: quem preenche e o usuario
}

/**
 * Slug derivado do nome. O Blade pedia digitado à mão com `pattern="[a-z0-9_]+"`; aqui é
 * derivado e editável antes de criar — depois vira imutável, porque a apuração grava nele.
 */
function slugDeNome(nome: string): string {
  return (nome || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 40);
}

export default function JanaMetaNovaDrawer({ aberto, onClose }: { aberto: boolean; onClose: () => void }) {
  const [nome, setNome] = React.useState('');
  const [slug, setSlug] = React.useState('');
  const [slugManual, setSlugManual] = React.useState(false);
  const [unidade, setUnidade] = React.useState<string>('R$');
  const [agregacao, setAgregacao] = React.useState<string>('soma');
  const [enviando, setEnviando] = React.useState(false);
  // ALVO — o que faltava. Sem ele a meta nascia sem `periodo_atual`, e o card do
  // Painel saia em "Aguardando apuracao..." pra sempre (medido em prod, 2026-09-21).
  const [valorAlvo, setValorAlvo] = React.useState('');
  const [tipoPeriodo, setTipoPeriodo] = React.useState<string>('mes');
  const [janela, setJanela] = React.useState(() => janelaDe('mes') ?? { ini: '', fim: '' });

  // Reabrir a gaveta começa em branco — sem isto o formulário guarda o rascunho anterior.
  React.useEffect(() => {
    if (!aberto) return;
    setNome('');
    setSlug('');
    setSlugManual(false);
    setUnidade('R$');
    setAgregacao('soma');
    setValorAlvo('');
    setTipoPeriodo('mes');
    setJanela(janelaDe('mes') ?? { ini: '', fim: '' });
  }, [aberto]);

  /** Trocar o tipo re-sugere a janela; `custom` preserva o que ja estiver digitado. */
  const trocarTipo = (t: string) => {
    setTipoPeriodo(t);
    const j = janelaDe(t);
    if (j) setJanela(j);
  };

  const criar = () => {
    setEnviando(true);
    router.post(
      '/ia/metas',
      {
        nome,
        slug,
        unidade,
        tipo_agregacao: agregacao,
        // O ALVO vai JUNTO. O `StoreMetaRequest` amarra os tres por `required_with`:
        // ou vem o alvo inteiro, ou nao vem nada — meia-declaracao e recusada.
        valor_alvo: valorAlvo,
        tipo_periodo: tipoPeriodo,
        data_ini: janela.ini,
        data_fim: janela.fim,
      },
      {
        preserveScroll: true,
        onFinish: () => setEnviando(false),
        onSuccess: onClose,
      },
    );
  };

  return (
    <Sheet open={aberto} onOpenChange={(open) => !open && onClose()}>
      <SheetContent side="right" className="w-full gap-0 overflow-y-auto sm:max-w-[520px]">
        <SheetHeader className="gap-1.5 border-b p-5">
          <SheetTitle className="text-base">Nova meta</SheetTitle>
          <SheetDescription>
            O que a Jana vai comparar com o realizado, janela por janela.
          </SheetDescription>
        </SheetHeader>

        <Stack gap={4} className="grow p-5">
          <Stack gap={1}>
            <Label htmlFor="nova-meta-nome">Nome</Label>
            <Input
              id="nova-meta-nome"
              value={nome}
              onChange={(e) => {
                setNome(e.target.value);
                if (!slugManual) setSlug(slugDeNome(e.target.value));
              }}
            />
            <p className="text-sm text-muted-foreground">
              Aparece no card do Painel e nas respostas da Jana.
            </p>
          </Stack>

          <Stack gap={1}>
            <Label htmlFor="nova-meta-slug">Identificador</Label>
            <Input
              id="nova-meta-slug"
              value={slug}
              onChange={(e) => {
                setSlugManual(true);
                setSlug(slugDeNome(e.target.value));
              }}
            />
            <p className="text-sm text-muted-foreground">
              Derivado do nome. Depois de criada não muda — a apuração grava nele.
            </p>
          </Stack>

          <Stack gap={1}>
            <Label htmlFor="nova-meta-unidade">Unidade</Label>
            <Select value={unidade} onValueChange={setUnidade}>
              <SelectTrigger id="nova-meta-unidade">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {UNIDADES.map((u) => (
                  <SelectItem key={u} value={u}>
                    {u}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Stack>

          <Stack gap={1}>
            <Label htmlFor="nova-meta-agregacao">Tipo de agregação</Label>
            <Select value={agregacao} onValueChange={setAgregacao}>
              <SelectTrigger id="nova-meta-agregacao">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {AGREGACOES.map((a) => (
                  <SelectItem key={a.valor} value={a.valor}>
                    {a.rotulo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Stack>

          {/* ALVO — o bloco que faltava. Ate 2026-09-21 esta gaveta criava a meta SEM
              alvo nenhum: o `StoreMetaRequest` nem aceitava o campo, e o resultado
              medido em producao foram 5 metas orfas, todas em "Aguardando apuracao...".
              Quem cria a meta e quem sabe o numero — pedir aqui e o lugar certo. */}
          <Stack gap={1}>
            <Label htmlFor="nova-meta-alvo">Valor alvo</Label>
            <Input
              id="nova-meta-alvo"
              type="number"
              min="0"
              step="any"
              inputMode="decimal"
              value={valorAlvo}
              onChange={(e) => setValorAlvo(e.target.value)}
            />
            <p className="text-sm text-muted-foreground">
              O numero que a Jana compara com o realizado. Em {unidade}.
            </p>
          </Stack>

          <Stack gap={1}>
            <Label htmlFor="nova-meta-periodo">Janela</Label>
            <Select value={tipoPeriodo} onValueChange={trocarTipo}>
              <SelectTrigger id="nova-meta-periodo">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {PERIODOS.map((p) => (
                  <SelectItem key={p.valor} value={p.valor}>
                    {p.rotulo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Stack>

          <div className="grid grid-cols-2 gap-3">
            <Stack gap={1}>
              <Label htmlFor="nova-meta-ini">Inicio</Label>
              <Input
                id="nova-meta-ini"
                type="date"
                value={janela.ini}
                onChange={(e) => setJanela((j) => ({ ...j, ini: e.target.value }))}
              />
            </Stack>
            <Stack gap={1}>
              <Label htmlFor="nova-meta-fim">Fim</Label>
              <Input
                id="nova-meta-fim"
                type="date"
                value={janela.fim}
                onChange={(e) => setJanela((j) => ({ ...j, fim: e.target.value }))}
              />
            </Stack>
          </div>

          {/* ⚠️ RESIDUAL DECLARADO, e o usuario merece saber: com alvo a meta ja mostra
              barra e "% do alvo", mas quem CALCULA o realizado e a FONTE — e nao existe
              UI pra ela em lugar nenhum. A tela `copiloto::fontes.show` e somente-leitura
              e diz no corpo que o editor e a US-COPI-040. Nao se inventa um campo de SQL
              aqui: seria pior que o buraco. */}
          <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
            A <strong>fonte</strong> — de onde a Jana tira o realizado — ainda se configura
            fora daqui. Sem ela a meta mostra o alvo, mas nao apura.
          </p>

          {/* O farol é veredito do SERVIDOR — a âncora traz este aviso no próprio drawer,
              e ele existe pra impedir que alguém volte a calcular veredito no front. */}
          <p className="rounded-md border bg-muted/40 p-3 text-sm text-muted-foreground">
            O farol é veredito do servidor. Sem apuração na janela, a meta aparece cinza —
            aguardando apuração — em vez de chutar um veredito.
          </p>
        </Stack>

        <SheetFooter className="flex-row justify-end gap-2 border-t p-4">
          <Button variant="ghost" onClick={onClose} disabled={enviando}>
            Cancelar
          </Button>
          {/* O alvo entra na trava do botao junto com nome e slug: a gaveta nao
              cria mais meta sem alvo, que era a origem das orfas. */}
          <Button
            onClick={criar}
            disabled={
              enviando || !nome.trim() || !slug.trim() || !valorAlvo.trim() || !janela.ini || !janela.fim
            }
          >
            Criar meta
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
