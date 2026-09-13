---
id: resources-js-pages-arquivos-index-charter
page: /arquivos (Admin Center)
component: resources/js/Pages/Arquivos/Index.tsx (NÃO EXISTE — proposta F1)
related_prototype: prototipo-ui/cowork/modulos-faltantes/arquivos-page.jsx
related_contrato: prototipo-ui/contrato/arquivos.contract.json
related_casos: arquivos.casos.md
owner: wagner
status: draft
parent_module: Arquivos
related_us: [US-ARQ-013]
related_adrs: [123, 126, 93, 84]
tier: B
charter_version: 1
mission: "Dar a quem responde pela conformidade um lugar pra ver o que o sistema guardou, por quanto tempo a lei manda guardar, o que já passou do prazo e quem tocou em quê."
---

# Page Charter — /arquivos (DRAFT · a tela não existe no vivo)

> **Fato do repo, não suposição:** `Modules/Arquivos/Http/Controllers/DataController::modifyAdminMenu()` é **NO-OP** — o módulo é backbone consumido via trait `HasArquivos`, e o próprio docblock aponta o destino: **`Pages/Arquivos` no Admin Center, US-ARQ-013, Sprint 2**. Este charter é a proposta F1 do [CC]; [W] aprova **Non-Goals + Anti-hooks** antes de `status: live`.

## Mission

Arquivos guarda coisa que a lei manda guardar (XML de NF-e por 5 anos) junto com coisa que a lei manda **apagar** (PII depois da finalidade). Sem tela, ninguém no negócio sabe qual é qual — e retenção sem visão é multa esperando acontecer.

## Persona-alvo

Wagner (escritório, 1440px) e Eliana (financeiro) — conformidade e custo de disco. **Não é tela de balcão:** Larissa continua alcançando o anexo pela tela da OS.

## Goals — faz

- **Acervo**: lista administrativa por dono (`arquivable` polimórfico), com bucket, disco, tamanho, contexto e data de vencimento da guarda.
- **Retenção**: quanto vence em 30/90 dias por `sub_destination`, **com a base legal ao lado do prazo**, o que está no grace de 30 dias e o que passou do prazo e não foi apagado (o WARN do `HealthCheckCommand` check #4).
- **Cofre**: espaço por disco, arquivo acima do cap de 50 MB que o `VaultEncryptionService` recusa, órfão sem `arquivable`, MD5 repetido.
- **Trilha**: `arquivos_audit_log` read-only (upload · download · signed_url · soft_delete · restore · hard_delete).
- Soft-delete com aviso explícito quando o contexto tem guarda legal.

## Non-Goals — NÃO faz

- ❌ NÃO faz upload (isso é dos módulos, via trait `HasArquivos`).
- ❌ NÃO é gerenciador de pastas: não existe árvore de diretórios, existe dono.
- ❌ NÃO edita o conteúdo do arquivo nem gera pré-visualização de documento sensível.
- ❌ NÃO faz hard-delete pela UI (é do `retention-cleanup`, depois do grace).
- ❌ NÃO cruza tenants, em nenhuma vista nem em nenhum job disparado daqui.
- ❌ NÃO substitui a Auditoria geral do sistema — a trilha aqui é só de arquivo.

## Automation hooks (faz)

- Calcula vencimento a partir de `retention_days` do contexto; classifica frescor do prazo.
- Aponta achados (cap, órfão, MD5 repetido) por leitura, sem agir sobre eles.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO apaga nada sozinho — quem apaga é o comando agendado, com política.
- ❌ NÃO reclassifica bucket por heurística de mime/nome (classificação tem autor: `classified_by`).
- ❌ NÃO cifra arquivo acima do cap "na melhor das tentativas" — recusa é o comportamento correto.
- ❌ NÃO notifica titular por conta própria (o `notice_period_days` é do job, com canal definido).

## UX targets

- Cabe em 1280px; tabela densa rola na horizontal em vez de esmagar coluna.
- Prazo sempre acompanhado da lei — número sozinho não ensina o domínio.
- Estados: cheia · filtrada-vazia · vazia · carregando · erro · sem-permissão (`arquivos.access`, default off).

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks.
- [ ] Confirmar se a tela mora no Admin Center (US-ARQ-013) ou ganha entry própria de sidebar.
- [ ] Definir se reclassificar bucket/visibility fica nesta tela ou só no dono do arquivo.
- [ ] Confirmar leitura real dos achados (cap/órfão/MD5) — hoje só `HealthCheckCommand` os conhece.
