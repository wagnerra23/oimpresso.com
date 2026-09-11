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

  // Reabrir a gaveta começa em branco — sem isto o formulário guarda o rascunho anterior.
  React.useEffect(() => {
    if (!aberto) return;
    setNome('');
    setSlug('');
    setSlugManual(false);
    setUnidade('R$');
    setAgregacao('soma');
  }, [aberto]);

  const criar = () => {
    setEnviando(true);
    router.post(
      '/ia/metas',
      { nome, slug, unidade, tipo_agregacao: agregacao },
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
          <Button onClick={criar} disabled={enviando || !nome.trim() || !slug.trim()}>
            Criar meta
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
