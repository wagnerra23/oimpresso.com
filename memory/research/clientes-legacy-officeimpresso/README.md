---
id: research-clientes-legacy-officeimpresso-readme
title: Inteligência por cliente legacy OfficeImpresso — dossiê estruturado
status: live
date: 2026-05-11
audience: time interno (Wagner / Felipe / Maiara / Eliana / Luiz) + IA-pair
purpose: estrutura padronizada pra acumular conhecimento sobre cada cliente WR Sistemas legacy, sustentando discovery pré-vendas, auditoria pré-migração, plano de cutover, e (futuro) feature comercial paga "snapshot mensal automático"
lgpd: ver _LGPD.md
---

# Clientes legacy OfficeImpresso — dossiê

> Pasta-mãe que acumula **inteligência por cliente** dos 38 clientes WR Sistemas legacy (Delphi/Firebird) ao longo do tempo. Cada cliente vira uma subpasta com perfil + heatmaps + planos + histórico, organizados sob protocolo LGPD estrito.

## Por que isso existe

Plano de migração OfficeImpresso → oimpresso.com (Laravel/MySQL) tem 38 clientes possíveis. Decisões de **ordem de cutover**, **estimativa de fricção**, **preço sugerido**, **escopo customizado** dependem de saber:

- Qual o tipo de negócio real? (gráfica vs oficina vs comvis vs híbrido)
- Quais módulos do Delphi ele USA de fato? (vs os que tem mas não usa)
- Saúde financeira? Inadimplência? MRR contratado?
- Há quanto tempo é cliente? Quem é o decisor? Quando foi último contato?
- Que sinal qualificado existe ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — reclamação? interesse? cancelamento?

Sem dossiê, cada análise refaz pesquisa do zero. Com dossiê, conhecimento **acumula** entre sessões e devs.

## Estrutura padrão por cliente

```
NN-slug-do-cliente/
├── 01-perfil.md                       ← anonimizado (commit OK)
├── 01-perfil-COM-NOMES.md             ← com razão social/CNPJ/contato (gitignored)
├── 02-heatmap-ui-YYYY-MM-DD.md        ← snapshot uso UI do Delphi (commit anon)
├── 03-financeiro-YYYY-MM-DD.md        ← MRR / inadimplência via skill (commit anon)
├── 04-plano-migracao.md               ← ordem de cutover, gaps, riscos (commit, sem PII)
├── 05-decisoes.md                     ← ADRs locais ao cliente (commit)
├── 99-historico-interacoes-COM-NOMES.md ← ligações/emails/demos (gitignored)
└── raw/                               ← JSON brutos do Firebird (gitignored)
```

**Anonimização canônica** (cumprir em todo arquivo commitável):
- Razão social → `Cliente_HASH6` onde HASH6 = `sha1(razao_social)[:6].upper()`
- CNPJ → `XX.XXX.XXX/XXXX-XX`
- Endereço → cidade/UF apenas (sem rua/número)
- Telefone/email decisor → não documentar em commit
- Códigos internos do Delphi → mantidos (não são PII)

## Arquivos meta (raiz)

| Arquivo | Conteúdo |
|---------|----------|
| [README.md](README.md) | este protocolo |
| **[_COMO-ANALISAR.md](_COMO-ANALISAR.md)** | **metodologia canônica 3 camadas** — source-first (Controllers Delphi) > heatmap > probes |
| [_LGPD.md](_LGPD.md) | fundamentação legal + papéis (Wagner=controlador / Claude=operador) |
| [_TEMPLATE-cliente.md](_TEMPLATE-cliente.md) | template novo cliente (copiar pra `NN-slug/01-perfil.md`) |
| [_ANALISE-CROSS-CLIENTE.md](_ANALISE-CROSS-CLIENTE.md) | comparativo entre todos clientes — padrões, segmentos, prioridade migração |
| [_GLOSSARIO.md](_GLOSSARIO.md) | termos do domínio OfficeImpresso/Delphi/Firebird |
| [_OPT-OUT.md](_OPT-OUT.md) | registro de clientes que pediram não-análise (LGPD Art. 18) |
| **[_LICOES-CRITICAS.md](_LICOES-CRITICAS.md)** ⭐ | **8 erros consolidados** anti-bug (Vargas/Gold classification, PROJETO_DT_FIM, BLOB DFM, paralelização agents) — **ler primeiro** se vai analisar cliente novo OU mergear Modules/OficinaAuto V0/V1 |
| **[_MAPPING/](_MAPPING/)** | 6 mappings canônicos Delphi→Laravel — TELA-LISTA-VENDAS · TELA-PESSOAS · TELA-COMPRA · TELA-FINANCEIRO · TELA-PRODUCAO-KANBAN · CONFIGURACOES-GRID |

## Hierarquia de fontes (descoberta 2026-05-11)

| Camada | Fonte | Onde | Quando usar |
|--------|-------|------|-------------|
| **1ª — Source** | Controllers Delphi `.pas` | `D:\Programas\WR Comercial\app\Controller\` | "qual SQL/validação/lógica exata?" — fonte autoritativa, 10 min/tela |
| 2ª — Schema | RDB$RELATIONS Firebird | banco do cliente | "qual estrutura?" — 2 min via script |
| 3ª — Heatmap | queries agregadas | [sells_grade_heatmap.py](../../../scripts/sells_grade_heatmap.py) | "o que cliente USA?" — comportamental real |
| 4ª — Probes | queries pontuais | scripts em [scripts/probe_*.py](../../../scripts/) | dúvidas específicas (config, log, usuários) |

Detalhes em [_COMO-ANALISAR.md](_COMO-ANALISAR.md).

**Descoberta crítica:** Delphi já tem `Controller.OImpresso.pas` que **sincroniza Contatos/Vendas/Financeiro/Produto/Tudo** com `oimpresso.com` via API. Migração pode ser **paralela** (cliente continua usando Delphi + ganha cloud) em vez de cutover.

## Como adicionar cliente novo

```bash
# 1. Criar subpasta
mkdir memory/research/clientes-legacy-officeimpresso/NN-<slug>/

# 2. Copiar template
cp memory/research/clientes-legacy-officeimpresso/_TEMPLATE-cliente.md \
   memory/research/clientes-legacy-officeimpresso/NN-<slug>/01-perfil.md

# 3. Rodar heatmap se o banco estiver acessível
python scripts/sells_grade_heatmap.py \
  --alias '192.168.0.55:D:\DadosClientes\<NomeCliente>\Dados\BANCO.FDB' \
  --slug "NN-<slug>"
# (output vai pra memory/research/2026-05-sells-grade-heatmap/ por enquanto;
#  mover/copiar manualmente pra subpasta do cliente como 02-heatmap-ui-DATA.md)

# 4. Preencher 01-perfil.md com observações qualitativas
# 5. Atualizar _ANALISE-CROSS-CLIENTE.md no fim
```

## Como analisar — checklist

### Análise individual (1 cliente)

1. **Tipo de negócio real** — ler 01-perfil + cruzar com Q1/Q7/Q8 do heatmap
   - Volume vendas/mês > 100 + zero veículos → gráfica/comvis
   - Veículos com PLACA > 30% → oficina (auto / caçambas / recapagem)
   - Centro_trabalho > 1000 linhas → PCP industrial
2. **Módulos OfficeImpresso usados de fato** — Q2/Q3/Q4/Q5
3. **Tamanho operação** — volume × ticket médio × clientes ativos
4. **Saúde financeira** — rodar skill `officeimpresso-financial-snapshot`
5. **Sinal qualificado pra migração** — última reclamação? interesse? alguém puxou contato?

### Análise comparativa (N clientes)

1. **Segmentação** — quantos por vertical (gráfica/comvis/oficina/híbrido)
2. **Tamanho da fila migração** — soma de volume × tícket médio
3. **Ordem de cutover** — quem migra primeiro? (Eliana decide com Wagner)
4. **Customização cluster** — clientes com features parecidas viram lote
5. **Features genéricas vs especializadas** — Modules/<Vertical> recebe o que aparece em >50% do segmento

Resultado da análise comparativa vai em [_ANALISE-CROSS-CLIENTE.md](_ANALISE-CROSS-CLIENTE.md).

### Análise temporal (1 cliente ao longo do tempo)

1. Crescimento de volume mês a mês (Q1)
2. Mudança no mix de status (Q3)
3. Inadimplência crescente (skill financeira)
4. Última atividade real (campo `DT_ALTERACAO`)

Útil pra detectar churn antes de cancelamento + janela ideal de abordagem pra migração.

## Relação com outras pastas/skills

- **[memory/research/2026-05-sells-grade-heatmap/](../2026-05-sells-grade-heatmap/)** — runs específicos de uma análise (heatmap UI). Output aqui é "snapshot do dia X". Esta pasta-mãe aponta pros runs (links em `02-heatmap-ui-DATA.md`).
- **[memory/research/2026-05-receitas-officeimpresso/](../2026-05-receitas-officeimpresso/)** — runs de análise financeira. Idem.
- **Skill [officeimpresso-financial-snapshot](../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md)** — automatiza análise financeira por cliente.
- **Skill [oimpresso-stack](../../../.claude/skills/oimpresso-stack/SKILL.md)** — entender stack do projeto.
- **[ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)** — modular especializado por vertical (este dossiê **alimenta** decisão de qual vertical merece código novo).
- **[ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)** — cliente como sinal qualificado (perfis aqui materializam o "sinal").

## Versionamento

Mudanças em perfis cliente são **append-only** quando possível — preferir adicionar seção "## 2026-MM-DD update" ao final do `01-perfil.md` em vez de reescrever.

Exception: correções de fato errado (ex: "achávamos que era gráfica, descobrimos que é oficina") podem rescrever a seção "Tipo de negócio" mas devem manter histórico em `## Correções`.

## Casos especiais

- **WR Sistemas (Wagner)** — não é cliente, é a empresa-mãe. Vive em `01-wr-sistemas/` por consistência mas não entra em análise comercial.
- **Clientes que pediram não-análise (LGPD)** — `_OPT-OUT.md` na raiz lista quem pediu pra não ser analisado. Honrar sem questionar.

## Quem mantém

| Pessoa | Papel |
|--------|-------|
| Wagner [W] | controlador LGPD, aprova entradas sensíveis |
| Eliana [E] | revisão LGPD (advogada do time) antes de exposição externa (deck, blog, parceiro) |
| Felipe [F] | pode adicionar perfis novos seguindo template |
| Claude (IA-pair) | gera perfis + heatmaps via scripts, escreve análises, **nunca commita COM-NOMES** |

---

**Última atualização:** 2026-05-11 — estrutura criada após sessão Wagner que apontou erros sobre Vargas (oficina recapagem, não gráfica+frota) e Gold (comvis, não gráfica genérica). Pasta nasce com 5 perfis iniciais corrigidos.
