/**
 * EntregaFrete — onda 2 do preview `/sells/create-v3` (CU-SELL-11).
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-entrega.jsx` (âncora declarada em
 * `CreateV3.charter.md::related_prototype`). O domínio mora em `entrega-dominio.ts`.
 *
 * ⚠️ POR QUE ESTA ONDA MERECE LEITURA ANTES DE MEXER
 * Isto é a dívida **D-6** do `SDD-tela-venda-v1.0.md`: `CU-SELL-11` (frete/entrega
 * estruturado) está `[must]` e 🟡 parcial porque a tentativa anterior — PR #2104 —
 * foi REVERTIDA (#2107) por regressão reportada por cliente ~30 min após o merge.
 * Incidente: `memory/sessions/2026-06-02-incidente-revert-pr2-sells-endereco.md`.
 *
 * As três causas-raiz suspeitas daquele incidente, e por que nenhuma alcança este arquivo:
 *   1. `ContactController@getCustomers` passou a fazer `->with(['addresses'])` num endpoint
 *      COMPARTILHADO com o Blade → aqui não há backend nem fetch: a cena vem estática.
 *   2. `shipping_address` não persistia → aqui não há persistência (Non-Goal do charter).
 *   3. O cliente não achava o frete na tela viva → esta NÃO é a tela viva.
 * A lição registrada foi "refazer só após smoke real"; um preview que não grava é
 * exatamente onde esse ensaio acontece sem risco ao cliente.
 *
 * ⚠️ TIER 0 — VALOR: **não calcula dinheiro**. O `frete` é state da Page e segue entrando
 * no total pela cadeia que já existia, intocada. O único derivado aqui é peso (kg).
 *
 * FRONTEIRA (charter): cópia local em `_components/v3/`, sem importar nada de
 * `Sells/Create` nem de `Sells/_components` — é o que `UC-V303` prova a cada PR.
 */

import { useMemo, useState, type ReactNode } from 'react';

import { Grid, Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';

import {
  ESPECIES,
  FRETE_CIF,
  FRETE_POR_CONTA,
  MODALIDADES,
  STATUS_REMESSA,
  UFS,
  filtrarTransportadoras,
  pesoBrutoDosItens,
  pesoLiquidoEstimado,
  semOcorrenciaDeTransporte,
  type ItemPesavel,
  type Transportadora,
} from './entrega-dominio';
import { num, parseBR } from './numeros';
import { Lbl, MoneyInput, Pill } from './primitivos';

/* ─── campos ─────────────────────────────────────────────────────────────── */

function Campo({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Lbl>{label}</Lbl>
      {children}
    </div>
  );
}

function Texto({
  label,
  value,
  onChange,
  placeholder,
  readOnly,
}: {
  label: string;
  value?: string;
  onChange?: (v: string) => void;
  placeholder?: string;
  readOnly?: boolean;
}) {
  return (
    <Campo label={label}>
      <Input
        type="text"
        className="h-8 text-[12.5px]"
        value={value ?? ''}
        placeholder={placeholder}
        readOnly={readOnly || !onChange}
        onChange={(e) => onChange?.(e.target.value)}
      />
    </Campo>
  );
}

/** `SafeSelectItem` e não `SelectItem`: opção com value vazio derruba o render inteiro
 *  do Radix (tela branca) — a lápide de 2026-06-29 está no cabeçalho do componente. */
function Escolha({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value?: string;
  onChange?: (v: string) => void;
  options: string[];
}) {
  return (
    <Campo label={label}>
      <Select value={value ?? options[0]} onValueChange={onChange} disabled={!onChange}>
        <SelectTrigger className="h-8 w-full text-[12.5px]">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((o) => (
            <SafeSelectItem key={o} value={o} className="text-[12.5px]">
              {o}
            </SafeSelectItem>
          ))}
        </SelectContent>
      </Select>
    </Campo>
  );
}

function Interruptor({
  id,
  label,
  sublabel,
  checked,
  onChange,
}: {
  id: string;
  label: string;
  sublabel?: string;
  checked: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <Inline gap={2} align="center">
      <Checkbox id={id} checked={checked} onCheckedChange={(v) => onChange(v === true)} />
      <label htmlFor={id} className="min-w-0 cursor-pointer">
        <span className="block text-[12px] leading-tight font-medium">{label}</span>
        {sublabel && <span className="block text-[11px] leading-tight text-muted-foreground">{sublabel}</span>}
      </label>
    </Inline>
  );
}

/* ─── componente ─────────────────────────────────────────────────────────── */

export default function EntregaFrete({
  itens,
  clienteNome,
  enderecoDoCadastro,
  frete,
  onFreteChange,
  transportadoras,
}: {
  itens: ItemPesavel[];
  clienteNome: string;
  enderecoDoCadastro: string;
  frete: string;
  onFreteChange: (v: string) => void;
  transportadoras: Transportadora[];
}) {
  const [porConta, setPorConta] = useState(FRETE_CIF);
  const [transp, setTransp] = useState<Transportadora | null>(null);
  const [consultaAberta, setConsultaAberta] = useState(false);
  const [busca, setBusca] = useState('');
  const [ufVeiculo, setUfVeiculo] = useState('SC');
  const [modalidade, setModalidade] = useState('Rodoviário');
  const [pesoManual, setPesoManual] = useState(false);
  const [pesoBrutoDigitado, setPesoBrutoDigitado] = useState('0,000');
  const [pesoLiqDigitado, setPesoLiqDigitado] = useState('0,000');
  const [outroEndereco, setOutroEndereco] = useState(false);

  const semTransporte = semOcorrenciaDeTransporte(porConta);

  /* derivados — nunca em estado */
  const pesoSomado = useMemo(() => pesoBrutoDosItens(itens), [itens]);
  const pesoBruto = pesoManual ? parseBR(pesoBrutoDigitado) : pesoSomado;
  const pesoLiquido = pesoManual ? parseBR(pesoLiqDigitado) : pesoLiquidoEstimado(pesoSomado);
  const encontradas = useMemo(() => filtrarTransportadoras(transportadoras, busca), [transportadoras, busca]);

  const trazer = (t: Transportadora | null) => {
    setTransp(t);
    if (t) {
      setUfVeiculo(t.uf);
      setModalidade(t.modal);
    }
  };

  const escolher = (t: Transportadora) => {
    trazer(t);
    setConsultaAberta(false);
    setBusca('');
  };

  return (
    <Stack gap={4}>
      {/* ─── Frete e transporte ─────────────────────────────────────────── */}
      <div>
        <Inline gap={2} align="center" className="mb-3 flex-wrap">
          <Lbl className="mb-0">Frete e transporte</Lbl>
          <span className="text-[11.5px] leading-tight text-muted-foreground">
            o <b>valor</b> do frete fica no fechamento, à direita
          </span>
        </Inline>

        <Grid gap={3} className="sm:grid-cols-2">
          <Escolha label="Frete por conta" value={porConta} onChange={setPorConta} options={FRETE_POR_CONTA} />
          <MoneyInput label="Valor do frete (entra no total)" value={frete} onChange={onFreteChange} />
        </Grid>

        {semTransporte ? (
          <div className="mt-3 rounded-lg border border-info/30 bg-info-soft px-3 py-2">
            <b className="block text-[12.5px] leading-tight text-info-fg">Sem ocorrência de transporte</b>
            <span className="block text-[11.5px] leading-snug text-muted-foreground">
              O cliente retira no balcão — a NF-e sai sem grupo de transporte e nenhum campo de volume é exigido.
            </span>
          </div>
        ) : (
          <Stack gap={3} className="mt-3">
            {/* transportadora */}
            <Inline gap={3} align="end" className="flex-wrap">
              <div className="w-[104px] flex-none">
                <Texto
                  label="Código"
                  value={transp?.cod ?? ''}
                  placeholder="000"
                  onChange={(v) => trazer(transportadoras.find((t) => t.cod === v) ?? null)}
                />
              </div>
              <div className="min-w-[240px] flex-1">
                <Texto
                  label="Nome / razão social da transportadora"
                  value={transp?.nome ?? ''}
                  placeholder="Digite o código, ou consulte o cadastro →"
                  readOnly
                />
              </div>
              <Button type="button" variant="outline" size="sm" onClick={() => setConsultaAberta(true)}>
                Consultar cadastro… F2
              </Button>
              {transp && (
                <Inline gap={2} align="center" className="flex-none pb-1">
                  <Pill mono>{transp.doc}</Pill>
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-6"
                    title="Limpar transportadora"
                    onClick={() => trazer(null)}
                  >
                    ×
                  </Button>
                </Inline>
              )}
            </Inline>

            {/* veículo */}
            <Grid gap={3} className="sm:grid-cols-3 lg:grid-cols-4">
              <Texto label="Placa do veículo" value={transp?.placa ?? ''} placeholder="ABC1D23" readOnly />
              <Escolha label="UF do veículo" value={ufVeiculo} onChange={setUfVeiculo} options={UFS} />
              <Texto label="Renavam" placeholder="00000000000" />
              <Texto label="ANTT / RNTRC" value={transp?.antt ?? ''} placeholder="sem registro" readOnly />
              <Escolha label="Modalidade" value={modalidade} onChange={setModalidade} options={MODALIDADES} />
              <Escolha label="UF de destino do transporte" options={UFS} />
            </Grid>

            {/* volumes */}
            <div className="rounded-lg border border-border bg-muted/40 p-3">
              <Inline gap={3} align="center" className="mb-3 flex-wrap">
                <Lbl className="mb-0">Volumes</Lbl>
                <span className="ml-auto">
                  <Interruptor
                    id="v3-peso-manual"
                    label="Informar peso manualmente"
                    sublabel={`Somado dos itens: ${num(pesoSomado, 3)} kg`}
                    checked={pesoManual}
                    onChange={setPesoManual}
                  />
                </span>
              </Inline>
              <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                <Texto label="Quantidade de volumes" value="1" />
                <Escolha label="Espécie" options={ESPECIES} />
                <Texto label="Marca" placeholder="Sem marca" />
                <Texto label="Numeração dos volumes" value="1" />
                <Texto
                  label="Peso bruto (kg)"
                  value={pesoManual ? pesoBrutoDigitado : num(pesoBruto, 3)}
                  onChange={pesoManual ? setPesoBrutoDigitado : undefined}
                  readOnly={!pesoManual}
                />
                <Texto
                  label="Peso líquido (kg)"
                  value={pesoManual ? pesoLiqDigitado : num(pesoLiquido, 3)}
                  onChange={pesoManual ? setPesoLiqDigitado : undefined}
                  readOnly={!pesoManual}
                />
                <Texto label="Código de coleta" placeholder="—" />
                <Texto label="Data da coleta" placeholder="dd/mm/aaaa" />
              </Grid>
              <p className="mt-2 text-[11px] leading-snug text-muted-foreground">
                {pesoManual
                  ? 'Digitado — ignora o peso cadastrado dos itens.'
                  : 'Calculado pelo peso cadastrado de cada item; item sem peso entra como zero.'}
              </p>
            </div>

            {/* remessa */}
            <Grid gap={3} className="sm:grid-cols-3">
              <Escolha label="Status da remessa" options={STATUS_REMESSA} />
              <Texto label="Previsão de entrega" placeholder="dd/mm/aaaa" />
              <Texto label="Rastreio / conhecimento" placeholder="CT-e ou código de rastreio" />
            </Grid>
          </Stack>
        )}
      </div>

      {/* ─── Endereço de entrega ────────────────────────────────────────── */}
      <div className="border-t border-border pt-4">
        <Inline gap={3} align="center" className="mb-3 flex-wrap">
          <Lbl className="mb-0">Endereço de entrega</Lbl>
          <Interruptor
            id="v3-outro-endereco"
            label="Entregar em outro endereço"
            sublabel="Vazio = usa o endereço do cadastro do cliente"
            checked={outroEndereco}
            onChange={setOutroEndereco}
          />
        </Inline>

        {!outroEndereco ? (
          <Inline gap={3} align="center" className="rounded-lg border border-primary/25 bg-primary/10 p-3">
            <div className="min-w-0">
              <b className="block text-[13.5px] leading-snug">{clienteNome}</b>
              <span className="block text-[12.5px] leading-snug text-muted-foreground">{enderecoDoCadastro}</span>
            </div>
            <span className="ml-auto flex-none">
              <Pill tom="info">do cadastro</Pill>
            </span>
          </Inline>
        ) : (
          <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
            <Texto label="Cód. cidade (IBGE)" placeholder="4209102" />
            <Texto label="Cidade" placeholder="Joinville" />
            <Escolha label="UF" options={UFS} />
            <Texto label="CEP" placeholder="89201-601" />
            <Texto label="Logradouro" placeholder="Rua, avenida, rodovia…" />
            <Texto label="Número" placeholder="1400" />
            <Texto label="Bairro" placeholder="Centro" />
            <Texto label="Complemento" placeholder="Galpão 2, fundos" />
            <Texto label="Nome do recebedor" placeholder="Quem recebe na obra" />
            <Texto label="Telefone" placeholder="(47) 9…" />
            <Texto label="E-mail" placeholder="obra@cliente.com.br" />
            <Texto label="Inscrição estadual" placeholder="Isento" />
          </Grid>
        )}
      </div>

      {/* ─── consulta de transportadoras ────────────────────────────────── */}
      <Dialog open={consultaAberta} onOpenChange={setConsultaAberta}>
        <DialogContent className="max-h-[80vh] sm:max-w-[880px]">
          <DialogHeader>
            <DialogTitle>Consulta de transportadoras</DialogTitle>
          </DialogHeader>

          <Input
            autoFocus
            className="h-8 text-[12.5px]"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Buscar por razão social, CNPJ, cidade ou código…"
          />

          <div className="min-h-0 flex-1 overflow-auto">
            <table className="w-full text-[12.5px]">
              <thead className="sticky top-0 bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
                <tr>
                  <th className="px-3 py-2 font-medium">Código</th>
                  <th className="px-3 py-2 font-medium">Razão social</th>
                  <th className="px-3 py-2 font-medium">CNPJ</th>
                  <th className="px-3 py-2 font-medium">Cidade / UF</th>
                  <th className="px-3 py-2 font-medium">Modalidade</th>
                </tr>
              </thead>
              <tbody>
                {encontradas.map((t) => (
                  <tr key={t.cod} className={transp?.cod === t.cod ? 'bg-primary/10' : undefined}>
                    {/* a linha inteira é clicável, mas quem recebe o clique é um <button>
                        de verdade — div com onClick não é alcançável por teclado, e o
                        `a11y:check` reprova com razão. */}
                    <td colSpan={5} className="border-t border-border p-0">
                      <Grid asChild gap={2} className="grid-cols-[80px_1fr_150px_140px_120px] items-center">
                        <button
                          type="button"
                          onClick={() => escolher(t)}
                          className="w-full px-3 py-2 text-left hover:bg-muted/50"
                        >
                          <span className="font-mono">{t.cod}</span>
                          <span className="min-w-0">
                            <span className="block truncate font-medium">{t.nome}</span>
                            <span className="block text-[11px] text-muted-foreground">placa {t.placa}</span>
                          </span>
                          <span className="font-mono">{t.doc}</span>
                          <span>
                            {t.cidade}/{t.uf}
                          </span>
                          <span>
                            <Pill>{t.modal}</Pill>
                          </span>
                        </button>
                      </Grid>
                    </td>
                  </tr>
                ))}
                {encontradas.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">
                      Nenhuma transportadora encontrada para “{busca}”.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <DialogFooter className="sm:justify-start">
            <span className="text-[11.5px] text-muted-foreground">{transportadoras.length} cadastros ativos</span>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Stack>
  );
}
