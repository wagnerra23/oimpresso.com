// "Configurar pelo certificado" — drawer de 4 passos (playbook Fiscal thread 22 · UC-NFTR-18).
// Alvo de forma: `TrOnboarding` em prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx (D-ANCORA).
// Lê GET /nfe-brasil/tributacao/empresa-fiscal (thread 21, read-only). Nada é gravado até o
// "Aplicar template" do passo 4 — fechar em qualquer passo não faz POST.

import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Check } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';

type Opcao = { valor: string; fonte: string };
type Campo = { valor: unknown; fonte: string | null; motivo?: string; divergente?: boolean; opcoes?: Opcao[] };
type Sugestao = { slug: string; titulo: string; regime: string; uf: string; aderencia: { pontos: number } };
type Leitura = { campos: Record<string, Campo>; sugestoes: Sugestao[] };

const PASSOS = ['Certificado', 'Dados lidos', 'Template', 'Confirmar'];
const REGIMES: Opcao[] = [
  { valor: 'mei', fonte: 'você' },
  { valor: 'simples', fonte: 'você' },
  { valor: 'normal', fonte: 'você' },
];
const REGIME_LABEL: Record<string, string> = {
  mei: 'MEI', simples: 'Simples Nacional', normal: 'Normal (presumido ou real)',
  lucro_presumido: 'Lucro presumido', lucro_real: 'Lucro real',
};
const ROTULO: Record<string, string> = {
  cnpj: 'CNPJ', uf: 'UF', razao_social: 'Razão social', ie: 'Inscrição estadual',
  situacao: 'Situação', cnaes: 'CNAEs',
};
const MOTIVO: Record<string, string> = {
  uf_unsupported: 'sua UF não é consultada — digite com o contador',
  env_homolog: 'ambiente de homologação',
  no_cert: 'sem certificado ativo',
  sefaz_error: 'a SEFAZ não respondeu',
  sem_uf: 'sem UF',
  sem_cnpj: 'sem CNPJ',
};

function texto(v: unknown): string {
  if (v === null || v === undefined || v === '') return '—';
  if (Array.isArray(v)) return v.map((x) => (typeof x === 'object' && x ? Object.values(x).join(' · ') : String(x))).join(' · ');
  return String(v);
}

export const ncmValido = (ncm: string) => /^\d{8}$/.test(ncm) && ncm !== '00000000';

export default function ConfigurarPeloCertificado({
  open, onOpenChange, temConfig,
}: { open: boolean; onOpenChange: (o: boolean) => void; temConfig: boolean }) {
  const [passo, setPasso] = useState(1);
  const [leitura, setLeitura] = useState<Leitura | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [regime, setRegime] = useState<string | null>(null);
  const [tpl, setTpl] = useState<string | null>(null);
  const [ncm, setNcm] = useState('');

  const carregar = (r: string | null) => {
    const q = r ? `?regime=${encodeURIComponent(r)}` : '';
    fetch(`/nfe-brasil/tributacao/empresa-fiscal${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
      .then((d: Leitura) => {
        setLeitura(d);
        setTpl((atual) => atual ?? d.sugestoes[0]?.slug ?? null);
      })
      .catch(() => setErro('Não foi possível ler os dados da empresa.'));
  };

  useEffect(() => {
    if (!open) return;
    setPasso(1); setRegime(null); setTpl(null); setNcm(''); setErro(null); setLeitura(null);
    carregar(null);
  }, [open]);

  useEffect(() => {
    const r = leitura?.campos.regime;
    if (r && !r.divergente && typeof r.valor === 'string') setRegime(r.valor);
  }, [leitura]);

  const escolherRegime = (r: string) => {
    setRegime(r); setTpl(null); carregar(r);
  };

  const campoRegime = leitura?.campos.regime;
  const opcoesRegime = campoRegime?.opcoes?.length ? campoRegime.opcoes : REGIMES;
  const regimeFixo = !!campoRegime && !campoRegime.divergente && typeof campoRegime.valor === 'string';
  const ncmOk = ncmValido(ncm);
  const t = leitura?.sugestoes.find((s) => s.slug === tpl);
  const podeContinuar = (passo === 1 && !!leitura) || (passo === 2 && !!regime) || (passo === 3 && !!tpl && ncmOk);

  const aplicar = () => {
    if (!tpl || !ncmOk) return;
    router.post(`/nfe-brasil/tributacao/templates/${tpl}/aplicar`, { ncm_default: ncm }, {
      preserveScroll: true,
      onSuccess: () => onOpenChange(false),
      onError: (e) => toast.error(e.ncm_default ?? 'Falha ao aplicar template.'),
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-[560px] flex flex-col gap-0 p-0" aria-describedby="onb-desc">
        <SheetHeader className="p-5 border-b">
          <SheetTitle>Configurar pelo certificado</SheetTitle>
          <SheetDescription id="onb-desc">
            Lemos os dados da empresa, você confirma. Nada é aplicado sem o seu clique.
          </SheetDescription>
          <ol aria-label="Etapas" className="flex gap-3 text-xs mt-2">
            {PASSOS.map((p, i) => (
              <li key={p} aria-current={i + 1 === passo ? 'step' : undefined}
                className={i + 1 === passo ? 'font-semibold text-foreground' : 'text-muted-foreground'}>
                {i + 1}. {p}
              </li>
            ))}
          </ol>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto p-5 space-y-3 text-sm">
          {erro && <p role="alert" className="text-destructive">{erro}</p>}
          {!leitura && !erro && <p className="text-muted-foreground">Lendo os dados da empresa…</p>}

          {leitura && passo === 1 && (
            <section aria-labelledby="onb-p1">
              <h3 id="onb-p1" className="font-medium mb-2">Certificado A1</h3>
              <dl className="grid grid-cols-[140px_1fr] gap-y-1">
                <dt className="text-muted-foreground">CNPJ</dt><dd className="font-mono">{texto(leitura.campos.cnpj?.valor)}</dd>
                <dt className="text-muted-foreground">Razão social</dt><dd>{texto(leitura.campos.razao_social?.valor)}</dd>
              </dl>
              <p className="text-xs text-muted-foreground mt-3">
                Com o próprio certificado consultamos a SEFAZ do seu estado. Onde ela não responde,
                inscrição estadual e regime são confirmados por você.
              </p>
            </section>
          )}

          {leitura && passo === 2 && (
            <section aria-labelledby="onb-p2">
              <h3 id="onb-p2" className="font-medium mb-2">O que encontramos</h3>
              <dl className="grid grid-cols-[140px_1fr] gap-y-1">
                {Object.keys(ROTULO).map((k) => {
                  const c = leitura.campos[k];
                  return [
                    <dt key={`${k}-t`} className="text-muted-foreground">{ROTULO[k]}</dt>,
                    <dd key={`${k}-d`}>
                      {texto(c?.valor)}{' '}
                      <span className="text-xs text-muted-foreground">
                        · {c?.fonte ?? (c?.motivo ? MOTIVO[c.motivo] ?? c.motivo : 'sem fonte')}
                      </span>
                    </dd>,
                  ];
                })}
              </dl>
              <fieldset className="mt-3">
                <legend className="font-medium">Regime</legend>
                {regimeFixo ? (
                  <p>{REGIME_LABEL[regime ?? ''] ?? regime} <span className="text-xs text-muted-foreground">· {campoRegime?.fonte}</span></p>
                ) : (
                  <>
                    <p className="text-xs text-muted-foreground">
                      {campoRegime?.divergente ? 'As fontes divergem. Confirme com o contador:' : 'As fontes não informaram. Escolha:'}
                    </p>
                    <RadioGroup value={regime ?? ''} onValueChange={escolherRegime} aria-label="Regime" className="gap-1 mt-1">
                      {opcoesRegime.map((o) => (
                        <label key={o.valor} className="flex items-center gap-2 py-1">
                          <RadioGroupItem value={o.valor} />
                          {REGIME_LABEL[o.valor] ?? o.valor} <span className="text-xs text-muted-foreground">· {o.fonte}</span>
                        </label>
                      ))}
                    </RadioGroup>
                  </>
                )}
              </fieldset>
            </section>
          )}

          {leitura && passo === 3 && (
            <section aria-labelledby="onb-p3">
              <h3 id="onb-p3" className="font-medium mb-2">Template sugerido</h3>
              <RadioGroup value={tpl ?? ''} onValueChange={setTpl} aria-label="Template" className="gap-1">
                {leitura.sugestoes.map((s, i) => (
                  <label key={s.slug} className="flex items-center gap-2 rounded-md border p-2">
                    <RadioGroupItem value={s.slug} />
                    <span className="font-medium">{s.titulo}</span>
                    {i === 0 && s.aderencia.pontos > 0 && <span className="text-xs text-primary">recomendado</span>}
                  </label>
                ))}
              </RadioGroup>
              <label className="block mt-3">
                <span className="text-sm">NCM padrão da empresa (obrigatório)</span>
                <Input inputMode="numeric" maxLength={8} placeholder="8 dígitos" value={ncm}
                  onChange={(e) => setNcm(e.target.value.replace(/\D/g, ''))}
                  aria-invalid={ncm.length > 0 && !ncmOk} />
              </label>
              <p className="text-xs text-muted-foreground">Vale pro item sem NCM, que entra na revisão. 00000000 não é aceito.</p>
            </section>
          )}

          {leitura && passo === 4 && t && (
            <section aria-labelledby="onb-p4">
              <h3 id="onb-p4" className="font-medium mb-2">Vai ser aplicado</h3>
              <dl className="grid grid-cols-[140px_1fr] gap-y-1">
                <dt className="text-muted-foreground">Regime</dt><dd>{REGIME_LABEL[regime ?? ''] ?? regime}</dd>
                <dt className="text-muted-foreground">Template</dt><dd>{t.titulo}</dd>
                <dt className="text-muted-foreground">Valores</dt><dd>{REGIME_LABEL[t.regime] ?? t.regime} · {t.uf}</dd>
                <dt className="text-muted-foreground">NCM padrão</dt><dd className="font-mono">{ncm}</dd>
                <dt className="text-muted-foreground">Regras por NCM</dt><dd>mantidas como estão</dd>
              </dl>
              {temConfig && (
                <div role="note" className="mt-3 rounded-md border border-warning/40 bg-warning-soft p-3">
                  <b>Já existe configuração nesta empresa.</b>{' '}
                  Aplicar substitui o regime e a tributação padrão. As regras por NCM ficam.
                </div>
              )}
            </section>
          )}
        </div>

        <div className="flex items-center gap-2 border-t p-4">
          {passo > 1 && <Button variant="outline" onClick={() => setPasso(passo - 1)}>Voltar</Button>}
          {passo < 4 && <Button disabled={!podeContinuar} onClick={() => setPasso(passo + 1)}>Continuar</Button>}
          {passo === 4 && <Button onClick={aplicar}><Check className="h-4 w-4 mr-1.5" />Aplicar template</Button>}
          {passo === 2 && !regime && <small className="text-xs text-muted-foreground">Escolha o regime pra continuar.</small>}
          {passo === 3 && !ncmOk && <small className="text-xs text-muted-foreground">Informe um NCM de 8 dígitos.</small>}
        </div>
      </SheetContent>
    </Sheet>
  );
}
