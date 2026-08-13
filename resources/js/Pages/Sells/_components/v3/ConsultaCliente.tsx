/**
 * ConsultaCliente — o modal de 880px do passo 1, extra do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-create.jsx:515` (consulta) e `:544`
 * (cadastro mínimo). O domínio mora em `cliente-consulta-dominio.ts`.
 *
 * ⚠️ POR QUE O "NOVO CADASTRO" VEIO JUNTO, E NÃO FICOU DE FORA
 * O rodapé da consulta É o botão de novo cadastro — na fonte os dois modais são um
 * fluxo só. Portar a consulta e deixar o rodapé prometendo o que não abre criaria
 * exatamente o que o cabeçalho da Page recusa: "botão que promete e não entrega é
 * pior que botão ausente". Aqui ele entrega o que o preview inteiro entrega — o
 * cliente nasce **em memória** e volta já selecionado, do mesmo jeito que item,
 * parcela e comissão. Isso é simulação declarada, não persistência: a tela não grava
 * (Non-Goal do charter), e a fonte promete literalmente "o cliente volta já
 * selecionado na venda". O protótipo apenas fecha o modal sem selecionar nada —
 * cumprir a própria copy é o porte correto, não invenção.
 *
 * ⚠️ TIER 0 — VALOR: escolher cliente **não mexe em número**. O racional medido está
 * no cabeçalho de `cliente-consulta-dominio.ts`; aqui basta a consequência: este
 * componente não conhece preço, não recebe `itens` e não devolve nada além do
 * cadastro escolhido.
 *
 * FRONTEIRA (charter): cópia local em `_components/v3/`, sem importar nada de
 * `Sells/Create` nem de `Sells/_components` — é o que `UC-V303` prova a cada PR.
 */

import { useMemo, useState } from 'react';

import { Grid, Inline } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
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
  GRUPOS_DE_PRECO,
  GRUPO_PADRAO,
  cadastroMinimoValido,
  criarClienteDeCadastroMinimo,
  filtrarClientes,
  rotuloIcmsCurto,
  tomIcms,
  type ClienteConsulta,
} from './cliente-consulta-dominio';
import { Lbl, Pill } from './primitivos';

/* ─── cadastro mínimo ─────────────────────────────────────────────────────── */

function NovoCliente({
  aberto,
  onFechar,
  onCriar,
}: {
  aberto: boolean;
  onFechar: () => void;
  onCriar: (dados: { nome: string; doc: string; fone: string; grupo: string }) => void;
}) {
  const [nome, setNome] = useState('');
  const [doc, setDoc] = useState('');
  const [fone, setFone] = useState('');
  const [grupo, setGrupo] = useState(GRUPO_PADRAO);

  const podeCriar = cadastroMinimoValido({ nome });

  const limpar = () => {
    setNome('');
    setDoc('');
    setFone('');
    setGrupo(GRUPO_PADRAO);
  };

  const fechar = () => {
    limpar();
    onFechar();
  };

  return (
    <Dialog
      open={aberto}
      onOpenChange={(v) => {
        if (!v) fechar();
      }}
    >
      <DialogContent className="venda-v3 sm:max-w-[560px]">
        <DialogHeader>
          <DialogTitle>Novo cliente — sem sair da venda</DialogTitle>
        </DialogHeader>

        <Grid gap={3} className="sm:grid-cols-2">
          <div>
            <Lbl>Nome / razão social</Lbl>
            <Input
              autoFocus
              value={nome}
              placeholder="Obrigatório"
              onChange={(e) => setNome(e.target.value)}
            />
          </div>
          <div>
            <Lbl>CPF / CNPJ</Lbl>
            <Input
              value={doc}
              placeholder="Opcional"
              onChange={(e) => setDoc(e.target.value)}
            />
          </div>
          <div>
            <Lbl>Telefone</Lbl>
            <Input
              value={fone}
              placeholder="(47) 9…"
              onChange={(e) => setFone(e.target.value)}
            />
          </div>
          <div>
            <Lbl>Grupo de preço</Lbl>
            {/* `SafeSelectItem` e não `SelectItem`: opção com value vazio derruba o
                render inteiro do Radix (tela branca) — lápide de 2026-06-29. */}
            <Select value={grupo} onValueChange={setGrupo}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {GRUPOS_DE_PRECO.map((g) => (
                  <SafeSelectItem key={g} value={g} className="text-[12.5px]">
                    {g}
                  </SafeSelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </Grid>

        <p className="text-[11.5px] leading-snug text-muted-foreground">
          Cadastro mínimo: o cliente volta <b>já selecionado</b> na venda. Sem tabela de preço no
          cadastro, vale o <b>padrão do balcão</b> — nada é reprecificado por esta escolha.
        </p>

        <DialogFooter className="sm:justify-end">
          <Button type="button" variant="outline" size="sm" onClick={fechar}>
            Cancelar
          </Button>
          <Button
            type="button"
            size="sm"
            disabled={!podeCriar}
            onClick={() => {
              onCriar({ nome, doc, fone, grupo });
              limpar();
            }}
          >
            Criar e selecionar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/* ─── consulta ────────────────────────────────────────────────────────────── */

export default function ConsultaCliente({
  aberta,
  onFechar,
  clientes,
  selecionado,
  onEscolher,
}: {
  aberta: boolean;
  onFechar: () => void;
  clientes: ClienteConsulta[];
  /** Código do cliente já na venda — marca a linha, não filtra. */
  selecionado: string;
  onEscolher: (cliente: ClienteConsulta) => void;
}) {
  const [busca, setBusca] = useState('');
  const [novoAberto, setNovoAberto] = useState(false);

  const encontrados = useMemo(() => filtrarClientes(clientes, busca), [clientes, busca]);

  const escolher = (c: ClienteConsulta) => {
    onEscolher(c);
    setBusca('');
    onFechar();
  };

  return (
    <>
      <Dialog
        open={aberta}
        onOpenChange={(v) => {
          if (!v) onFechar();
        }}
      >
        <DialogContent className="venda-v3 max-h-[80vh] sm:max-w-[880px]">
          <DialogHeader>
            <DialogTitle>Consulta de clientes</DialogTitle>
          </DialogHeader>

          <Input
            autoFocus
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Buscar por nome, CNPJ/CPF, cidade ou código…"
          />

          <div className="min-h-0 flex-1 overflow-auto">
            <table className="w-full text-[12.5px]">
              <thead className="sticky top-0 bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
                <tr>
                  <th className="px-3 py-2 font-medium">Código</th>
                  <th className="px-3 py-2 font-medium">Nome / razão social</th>
                  <th className="px-3 py-2 font-medium">CNPJ / CPF</th>
                  <th className="px-3 py-2 font-medium">ICMS</th>
                  <th className="px-3 py-2 font-medium">Cidade / UF</th>
                  <th className="px-3 py-2 font-medium">Grupo</th>
                </tr>
              </thead>
              <tbody>
                {encontrados.map((c) => (
                  <tr key={c.cod} className={selecionado === c.cod ? 'bg-primary/10' : undefined}>
                    {/* a linha inteira é clicável, mas quem recebe o clique é um <button>
                        de verdade — div com onClick não é alcançável por teclado, e o
                        `a11y:check` reprova com razão. */}
                    <td colSpan={6} className="border-t border-border p-0">
                      <Grid
                        asChild
                        gap={2}
                        className="grid-cols-[80px_1fr_150px_120px_140px_110px] items-center"
                      >
                        <button
                          type="button"
                          onClick={() => escolher(c)}
                          className="w-full px-3 py-2 text-left hover:bg-muted/50"
                        >
                          <span className="font-mono">{c.cod}</span>
                          <span className="min-w-0">
                            <span className="block truncate font-medium">{c.nome}</span>
                            <span className="block text-[11px] text-muted-foreground">
                              {c.ie !== '—' && c.ie !== 'ISENTO' ? `PJ · IE ${c.ie}` : 'PF'}
                            </span>
                          </span>
                          <span className="font-mono">{c.doc}</span>
                          <span>
                            <Pill tom={tomIcms(c.contrib)}>{rotuloIcmsCurto(c.contrib)}</Pill>
                          </span>
                          <span>
                            {c.cidade}/{c.uf}
                          </span>
                          <span>
                            <Pill tom="primary">{c.grupo}</Pill>
                          </span>
                        </button>
                      </Grid>
                    </td>
                  </tr>
                ))}
                {encontrados.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">
                      Nenhum cliente encontrado para “{busca}”.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <p className="text-[11.5px] leading-snug text-muted-foreground">
            Clique na linha para trazer o cliente — grupo de preço, prazo e endereço de entrega vêm
            do cadastro. A consulta só alcança cadastros do business atual.
          </p>

          <DialogFooter className="sm:justify-start">
            <Inline gap={2} align="center" className="w-full flex-wrap">
              <span className="text-[11.5px] text-muted-foreground">
                {clientes.length} cadastros ativos no business atual
              </span>
              <span className="ml-auto">
                <Inline gap={2} align="center">
                  <Button type="button" variant="outline" size="sm" onClick={onFechar}>
                    Fechar
                  </Button>
                  <Button type="button" size="sm" onClick={() => setNovoAberto(true)}>
                    Novo cadastro
                  </Button>
                </Inline>
              </span>
            </Inline>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <NovoCliente
        aberto={novoAberto}
        onFechar={() => setNovoAberto(false)}
        onCriar={(dados) => {
          setNovoAberto(false);
          escolher(criarClienteDeCadastroMinimo(dados, clientes));
        }}
      />
    </>
  );
}
