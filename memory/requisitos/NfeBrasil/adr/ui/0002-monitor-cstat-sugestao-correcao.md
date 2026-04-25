# ADR UI-0002 (NfeBrasil) · Monitor com cStat → sugestão de correção

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui
- **Relacionado**: US-NFE-007, ARQ-0002

## Contexto

Quando NFC-e é rejeitada, SEFAZ retorna `cStat` (código de status, ~150 valores documentados):
- 100 — autorizada (sucesso)
- 217 — NFe não consta na base (provavelmente erro de transmissão)
- 224 — falta atributo (campo obrigatório vazio)
- 539 — IE inválida (cadastro do destinatário errado)
- 729 — CFOP inválido para CSOSN do emitente

Tenant **não conhece** esses códigos — se UI mostra só "Erro 539", Larissa fica perdida. Concorrentes (Tiny, Bling) mostram a mensagem oficial SEFAZ tipo "IE Inválida do Destinatário", mas isso só ajuda parcialmente.

O que tenant precisa: **o código + o motivo + onde fica o erro + como corrigir**.

## Decisão

**Lookup table `nfe_cstat_correcoes` mapeando cStat → sugestão acionável.**

```sql
CREATE TABLE nfe_cstat_correcoes (
    cstat INT UNSIGNED PRIMARY KEY,
    motivo_oficial VARCHAR(255) NOT NULL,
    explicacao TEXT NOT NULL,
    sugestao TEXT NOT NULL,
    onde_corrigir VARCHAR(100) NULL,    -- rota oimpresso (ex: '/products/{id}/edit')
    acao_recomendada ENUM('reemitir', 'corrigir_e_reemitir', 'manual') NOT NULL,
    documentacao_url VARCHAR(500) NULL  -- link ajuda externa se relevante
);
```

Seeder pré-populado com top 50 cStats mais comuns + sugestões em PT-BR claro:

| cStat | Motivo | Sugestão | Onde corrigir |
|---|---|---|---|
| 217 | NFe não consta | Provavelmente falha de rede. Tente reemitir | (botão Reemitir) |
| 539 | IE inválida destinatário | Verifique IE do cliente em "Cadastro de clientes" | `/contacts/{id}/edit` |
| 729 | CFOP inválido | Confira CFOP da regra fiscal pro NCM/UF | `/nfe-brasil/tributacao/regras` |
| 754 | Município destinatário inválido | Atualize código IBGE do município no cadastro | `/contacts/{id}/edit#endereco` |

Monitor UI (`/nfe-brasil/monitor`) renderiza:

```
┌──────────────────────────────────────────────────────────────┐
│ Rejeições nas últimas 24h: 3                                │
├──────────────────────────────────────────────────────────────┤
│ NFC-e #1234 — venda Larissa S. — 14:32                      │
│ ❌ cStat 539 — IE inválida do destinatário                   │
│   ↪ Verifique IE do cliente em "Cadastro de clientes"       │
│   [Corrigir cliente] [Reemitir] [Ignorar — manual depois]   │
├──────────────────────────────────────────────────────────────┤
│ NFC-e #1233 — venda balcão — 14:28                          │
│ ❌ cStat 217 — NFe não consta na base SEFAZ                  │
│   ↪ Provavelmente falha de rede. Tente reemitir             │
│   [Reemitir]                                                  │
└──────────────────────────────────────────────────────────────┘
```

## Consequências

**Positivas:**
- Larissa resolve sozinha 80% dos cases (não precisa ligar pro Wagner)
- Suporte oimpresso vê menos tickets de "erro 539, o que faço?"
- Tabela é editável → admin pode adicionar cStat novo conforme aparece em produção
- Busca em `nfe_cstat_correcoes` complementa monitor + lib `eduardokum/sped-nfe` (que retorna cStat cru)
- Docs externos referenciados via `documentacao_url` ajudam casos complexos

**Negativas:**
- Manutenção contínua — SEFAZ adiciona cStats novos; precisa atualizar
- Sugestão errada pode confundir mais que ajudar (mitigar: feedback button "Essa sugestão ajudou?")
- Translation: SEFAZ tem mensagens variadas (versões manual diferentes); manter consistência exige curadoria

## CTA "Reemitir corrigido" — fluxo

1. Tenant clica → modal mostra:
   - Original (read-only)
   - Campos editáveis pra corrigir (baseado em `cstat_correcoes.acao_recomendada`)
   - Diff visual antes/depois
2. Tenant edita → submete
3. **Numeração nova** (não reusa original) — original fica como rejeitada no histórico
4. Job re-emite com correções
5. Audit log liga as duas (`nfe_eventos` com `tipo=correcao_via_monitor`)

## Tests obrigatórios

- `MonitorListaRejeicoesTest` — filtro por período + ordenação
- `CstatCorrecaoLookupTest` — todos os cStats top-50 têm sugestão
- `ReemitirCorrigidoTest` — gera novo número, original mantém status rejeitada, audit log liga ambos

## Métricas a observar (post-launch)

- % rejeições resolvidas via monitor (vs ticket suporte) — meta > 70%
- Top 10 cStats mais frequentes por business — alimenta backlog do tenant (regra fiscal mal cadastrada?)
- Sugestões com baixa taxa de "ajudou" — refinar texto

## Decisões em aberto

- [ ] Sugestões com IA: integrar com chat IA contextual (`auto-memória: ideia_chat_ia_contextual.md`) que sabe da venda corrente?
- [ ] Auto-correção pra cStat 217 (apenas reemitir): habilitar/desabilitar por business?
- [ ] Catálogo cStat: importar oficial SEFAZ ou manter curadoria interna?

## Alternativas consideradas

- **Mostrar só motivo oficial SEFAZ** — rejeitado: tenant não entende
- **Chat de suporte humano direto** — rejeitado: caro, lento; só pra casos extremos
- **IA-only (sem lookup table)** — rejeitado: alucinação em domínio fiscal é crítica; lookup curado é seguro
- **Externo (link pra fórum/blog)** — rejeitado: depende de site externo, perde tenant

## Referências

- US-NFE-007, ARQ-0002 (SPEC)
- `auto-memória: ideia_chat_ia_contextual.md`
- Manual SEFAZ — Tabela de cStats
