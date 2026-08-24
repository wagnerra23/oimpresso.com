---
page: /arquivos
component: resources/js/Pages/Arquivos/Index.tsx
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Arquivos
related_prototype: prototipo-ui/cowork/arquivos-page.jsx
tier: B
charter_version: 1
---

# Page Charter — Arquivos/Index (DRAFT · carimbado do PT-01)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
> Preencha os TODO antes de subir de `draft` → `live` (exige screenshot aprovado por Wagner).

> **Onde a tela mora — decidido, não em aberto.** `resources/js/Pages/Arquivos/`, por
> decisão [W] de 2026-07-29 registrada no [SPEC §US-ARQ-013](../../../../memory/requisitos/Arquivos/SPEC.md)
> (*"pode ser dentro do arquivo mesmo"*), depois que a [ADR 0360](../../../../memory/decisions/0360-deprecacao-admin-center-supersede-0122.md)
> deprecou o Admin Center. O docblock de `DataController` e o protótipo F1 ainda dizem
> "Admin Center" — são a fonte **stale**, e o PR-1 corrige o docblock.

## Mission

Arquivos guarda coisa que a lei manda guardar (XML de NF-e por 5 anos) junto com coisa que a
lei manda apagar (PII depois da finalidade). Sem tela, ninguém no negócio sabe qual é qual — e
retenção sem visão é multa esperando acontecer. Esta tela dá a quem responde pela conformidade
um lugar pra ver o que o sistema guardou, por quanto tempo a lei manda guardar, o que já passou
do prazo e quem tocou em quê.

**Persona-alvo:** Wagner (escritório, 1440px) e Eliana (financeiro) — conformidade e custo de
disco. **Não é tela de balcão:** Larissa continua alcançando o anexo pela tela da OS.

## Goals — Features (faz)

- **Acervo** — lista administrativa por dono (`arquivable` polimórfico), com bucket, disco,
  tamanho, contexto e data de vencimento da guarda.
- **Retenção** — o que vence em 30/90 dias por `sub_destination`, **com a base legal ao lado do
  prazo**, o que está no grace de 30 dias, e o que passou do prazo sem ser apagado (o WARN do
  `HealthCheckCommand` check #4).
- **Cofre** — espaço por disco, arquivo acima do cap de 50 MB que o `VaultEncryptionService`
  recusa, órfão sem `arquivable`, MD5 repetido.
- **Trilha** — `arquivos_audit_log` read-only (upload · download · signed_url · soft_delete ·
  restore · hard_delete).
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

> Proposta [CC] a partir do protótipo F1 — **[W] aprova antes de `status: live`.** Cada item
> vira Pest GUARD quando a onda correspondente entrar.

- ❌ NÃO faz upload (isso é dos módulos, via trait `HasArquivos`, cap de 50 MB).
- ❌ NÃO é gerenciador de pastas: não existe árvore de diretórios, existe dono.
- ❌ NÃO edita o conteúdo do arquivo nem gera pré-visualização de documento sensível.
- ❌ NÃO serve arquivo do vault por `Storage::url` — sempre `DownloadController`, signed URL
  de 60 min (ADR 0123 §6).
- ❌ NÃO renderiza filename/storage_path/MD5 em vista de governança (PII fica só em
  `arquivos_audit_log` — LGPD Art. 37).
- ❌ NÃO cruza tenants, em nenhuma vista nem em nenhum job disparado daqui (ADR 0093, Tier 0).
- ❌ NÃO edita, apaga nem corrige linha de `arquivos_audit_log` — append-only, nunca purgado,
  mesmo quando o arquivo é.
- ❌ NÃO substitui a Auditoria geral do sistema — a trilha aqui é só de arquivo.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO apaga nada sozinho — quem apaga é o comando, com política.
- ❌ NÃO reclassifica bucket por heurística de mime/nome (classificação tem autor:
  `classified_by`).
- ❌ NÃO cifra arquivo acima do cap "na melhor das tentativas" — recusar é o comportamento
  correto (OOM).
- ❌ NÃO notifica titular por conta própria.

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE); tabela densa rola na
  horizontal em vez de esmagar coluna.
- Prazo sempre acompanhado da lei — número sozinho não ensina o domínio.
- Estados: cheia · filtrada-vazia · vazia · carregando · erro · sem-permissão
  (`arquivos.access`, default off).

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks acima.
- [ ] Onda 1 mergeada (a rota `/arquivos` ainda não existe — o `page:` acima é o destino).
- [ ] Screenshot 1280/1440 aprovado por [W].
- [ ] Definir se reclassificar bucket/visibility fica nesta tela ou só no dono do arquivo
      (a onda 2 esbarra nisso — ver PR-6).

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- Constituição UI v2: UI-0013
- US: [US-ARQ-013](../../../../memory/requisitos/Arquivos/SPEC.md) (Sprint 2)
- ADRs: [0123](../../../../memory/decisions/0123-modules-arquivos-backbone.md) (módulo mãe) ·
  [0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0) ·
  [0360](../../../../memory/decisions/0360-deprecacao-admin-center-supersede-0122.md) (Admin Center deprecado)
- Contrato de tela: `prototipo-ui/contrato/arquivos.contract.json`
- Casos: [Index.casos.md](Index.casos.md)
