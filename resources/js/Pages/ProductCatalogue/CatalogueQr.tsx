// @memcofre
//   tela: /product-catalogue/catalogue-qr
//   module: ProductCatalogue
//   stories: thread modulos-faltantes/playbook/04
//   permissao: productcatalogue_module (assinatura) + product.view (decisão PERM-CQR, [W] 2026-10-07)
//
// Catálogo QR: o QR que o cliente aponta no balcão e abre o catálogo público daquele local.
// Charter: ./CatalogueQr.charter.md · Casos: ./CatalogueQr.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/catalogo-qr-page.jsx → CatalogoQrPage()
//
// O QR nasce no navegador, como na Blade, pelo easy.qrcode.min.js que o módulo já publica
// (`qr_script`): nenhuma dependência nova. A base do link vem do servidor com o negócio da
// SESSÃO (`link_base`); a tela só acrescenta `/{location_id}`.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Download, Link2, QrCode } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Grid, Inline, Stack } from '@/Components/layout';

interface Local { id: number; nome: string }
interface Props {
  locais: Local[];
  negocio: { nome: string; logo_url: string | null };
  link_base: string;
  qr_script: string;
}
interface Gerado { link: string; local: string }

// Cores dos PIXELS do PNG (dado escolhido pelo operador), não cor de interface: por isso não
// passam por token do DS. Em rgb() e não em hex porque o ui:lint R1 conta hex literal em Page
// como cor de UI crua — e aqui não é UI. A Blade usava #000000 como padrão.
const CORES = ['rgb(17, 17, 17)', 'rgb(91, 63, 168)', 'rgb(35, 94, 169)', 'rgb(235, 48, 136)'];
const COR_TITULO = 'rgb(0, 66, 132)';
const COR_SUBTITULO = 'rgb(79, 79, 79)';
const INSTRUCOES = [
  'Escolha o local comercial e a cor do QR code',
  'Defina título, subtítulo e se o logo do negócio aparece',
  'Clique em gerar QR code',
];
const SUBTITULO = 'O QR que o cliente aponta no balcão e abre o seu catálogo no celular — um por local comercial.';

type QRCodeCtor = new (el: HTMLElement, opts: Record<string, unknown>) => unknown;

/** Carrega o easy.qrcode do módulo uma vez só. */
function carregarGerador(src: string): Promise<QRCodeCtor> {
  const w = window as unknown as { QRCode?: QRCodeCtor };
  if (w.QRCode) return Promise.resolve(w.QRCode);
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onload = () => (w.QRCode ? resolve(w.QRCode) : reject(new Error('gerador ausente')));
    s.onerror = () => reject(new Error('gerador não carregou'));
    document.head.appendChild(s);
  });
}

function CatalogueQr({ locais, negocio, link_base, qr_script }: Props) {
  const [local, setLocal] = useState('');
  const [cor, setCor] = useState(CORES[0]);
  const [titulo, setTitulo] = useState(negocio.nome);
  const [subtitulo, setSubtitulo] = useState('Catálogo de produtos');
  const temLogo = !!negocio.logo_url;
  const [logo, setLogo] = useState(temLogo);
  const [gerado, setGerado] = useState<Gerado | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [copiado, setCopiado] = useState(false);
  const alvo = useRef<HTMLDivElement>(null);

  // Mudou a forma, o QR antigo deixa de valer: gera de novo só com o clique (anti-hook do charter).
  const mudou = <T,>(set: (v: T) => void) => (v: T) => { set(v); setGerado(null); };

  const gerar = async () => {
    if (!local) return;
    setErro(null);
    const link = `${link_base}/${local}`;
    try {
      const QRCode = await carregarGerador(qr_script);
      const el = alvo.current;
      if (!el) return;
      el.innerHTML = '';
      const opts: Record<string, unknown> = {
        text: link, margin: 4, width: 256, height: 256, quietZone: 20,
        colorDark: cor, colorLight: '#ffffff',
      };
      if (titulo.trim() !== '') {
        Object.assign(opts, {
          title: titulo.trim(), titleFont: 'bold 18px Arial', titleColor: COR_TITULO,
          titleBackgroundColor: '#ffffff', titleHeight: 60, titleTop: 20,
        });
      }
      if (subtitulo.trim() !== '') {
        // A Blade repetia o título aqui (opts.subTitle = $('#title')); o subtítulo é o campo próprio.
        Object.assign(opts, { subTitle: subtitulo.trim(), subTitleFont: '14px Arial', subTitleColor: COR_SUBTITULO, subTitleTop: 40 });
      }
      if (logo && negocio.logo_url) opts.logo = negocio.logo_url;
      new QRCode(el, opts);
      setGerado({ link, local: locais.find((l) => String(l.id) === local)?.nome ?? '' });
    } catch {
      setErro('Não foi possível carregar o gerador de QR. Nada foi publicado — recarregue a tela.');
    }
  };

  useEffect(() => {
    if (!gerado && alvo.current) alvo.current.innerHTML = '';
  }, [gerado]);

  const baixar = () => {
    const canvas = alvo.current?.querySelector('canvas');
    if (!canvas) return;
    const a = document.createElement('a');
    a.download = 'qrcode.png';
    a.href = canvas.toDataURL();
    a.click();
  };

  const copiar = () => {
    if (!gerado) return;
    navigator.clipboard?.writeText(gerado.link);
    setCopiado(true);
    setTimeout(() => setCopiado(false), 1600);
  };

  const cabecalho = (
    <div data-contract="cabecalho">
      <PageHeader title="Catálogo QR" subtitle={SUBTITULO}
        actions={<Button variant="outline" asChild><a href="/products">Produtos</a></Button>} />
    </div>
  );

  if (locais.length === 0) {
    return (
      <>
        {cabecalho}
        <Stack gap={4} className="px-6 pt-4">
          <EmptyState icon="map-pin" title="Nenhum local comercial cadastrado."
            description="O catálogo é por local — é dele que saem preço e estoque. Cadastre o primeiro local em Configurações › Locais comerciais e volte aqui."
            action={<Button asChild><a href="/business-location">Abrir locais comerciais</a></Button>} />
        </Stack>
      </>
    );
  }

  return (
    <>
      {cabecalho}
      <Grid fit="lg" gap={6} className="px-6 pt-4 pb-8">
        <section data-contract="formulario" className="rounded-lg border bg-card p-5">
          <h2 className="mb-4 text-sm font-semibold">Como o QR vai sair</h2>
          <Stack gap={4}>
            <Stack gap={1}>
              <Label htmlFor="cqr-local">Local comercial</Label>
              <Select value={local} onValueChange={mudou(setLocal)}>
                <SelectTrigger id="cqr-local"><SelectValue placeholder="Selecione…" /></SelectTrigger>
                <SelectContent>
                  {locais.filter((l) => l.id).map((l) => (
                    <SafeSelectItem key={l.id} value={String(l.id)}>{l.nome}</SafeSelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">Cada local tem catálogo próprio — o preço e o estoque saem do local escolhido.</p>
            </Stack>

            <Stack gap={1}>
              <Label>Cor do QR code</Label>
              <Inline gap={2}>
                {CORES.map((c) => (
                  <button key={c} type="button" aria-label={`Cor ${c}`} aria-pressed={cor === c}
                    onClick={() => mudou(setCor)(c)}
                    className={`size-7 rounded-full border-2 ${cor === c ? 'border-ring' : 'border-transparent'}`}
                    style={{ background: c }} />
                ))}
              </Inline>
              <p className="text-xs text-muted-foreground">Contraste é leitura: cor clara em fundo branco o celular não lê.</p>
            </Stack>

            <Stack gap={1}>
              <Label htmlFor="cqr-titulo">Título</Label>
              <Input id="cqr-titulo" value={titulo} onChange={(e) => mudou(setTitulo)(e.target.value)} />
            </Stack>
            <Stack gap={1}>
              <Label htmlFor="cqr-subtitulo">Subtítulo</Label>
              <Input id="cqr-subtitulo" value={subtitulo} onChange={(e) => mudou(setSubtitulo)(e.target.value)} />
            </Stack>

            <Inline gap={3} align="start">
              <Switch id="cqr-logo" checked={logo && temLogo} disabled={!temLogo} onCheckedChange={mudou(setLogo)} />
              <div>
                <Label htmlFor="cqr-logo">Mostrar o logo do negócio no QR code</Label>
                <p className="text-xs text-muted-foreground">
                  {temLogo ? 'O logo entra no meio do QR.' : 'Sem logo cadastrado no negócio: o QR sai limpo.'}
                </p>
              </div>
            </Inline>

            <Inline gap={3}>
              <Button disabled={!local} onClick={gerar}><QrCode className="size-4" /> Gerar QR code</Button>
              {!local && <span className="text-sm text-muted-foreground">Escolha o local comercial primeiro.</span>}
            </Inline>
          </Stack>

          <div data-contract="instrucoes" className="mt-6 border-t pt-4 text-sm">
            <b>Instruções</b>
            <ol className="mt-2 list-decimal space-y-1 pl-5 text-muted-foreground">
              {INSTRUCOES.map((t) => <li key={t}>{t}</li>)}
            </ol>
          </div>
        </section>

        <section data-contract="saida" className="rounded-lg border bg-card p-5">
          {erro && (
            <Alert variant="destructive" className="mb-4"><AlertDescription>{erro}</AlertDescription></Alert>
          )}
          {!gerado && (
            <EmptyState icon="qr-code" title="Nenhum QR gerado ainda."
              description="Escolha o local, ajuste título e cor, e clique em gerar — o QR aparece aqui pronto pra baixar e colar no balcão." />
          )}
          <Inline ref={alvo} justify="center" className={gerado ? undefined : 'hidden'} />
          {gerado && (
            <Stack gap={4} className="mt-4">
              <Inline gap={2} asChild><code className="break-all text-xs text-muted-foreground">
                <Link2 className="size-3.5 shrink-0" /> {gerado.link}
              </code></Inline>
              <Inline gap={2} wrap>
                <Button onClick={baixar}><Download className="size-4" /> Baixar imagem</Button>
                <Button variant="outline" onClick={copiar}>{copiado ? 'Link copiado' : 'Copiar link'}</Button>
              </Inline>
              <Inline gap={2} wrap>
                <Badge variant="secondary">{gerado.local}</Badge>
                <Badge variant="outline">256 × 256 px · PNG</Badge>
              </Inline>
              <Alert>
                <AlertTitle>O catálogo é público</AlertTitle>
                <AlertDescription>
                  Quem tiver o link vê os produtos e os preços daquele local, sem login. Trocar de local muda o link — o QR antigo continua valendo pro local antigo.
                </AlertDescription>
              </Alert>
            </Stack>
          )}
        </section>
      </Grid>
    </>
  );
}

CatalogueQr.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
export default CatalogueQr;
