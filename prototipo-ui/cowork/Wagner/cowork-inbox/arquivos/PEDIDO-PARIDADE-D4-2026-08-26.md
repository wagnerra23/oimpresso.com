# Paridade Arquivos + D4 respondido — pedido zero-toque

> De [CC] para [CL] · 2026-08-26 · **D4 decidido por [W] neste chat**
> Lido no `main` NESTE turno (árvore `1e34f251a9a4`): `resources/js/Pages/Arquivos/Index.tsx`.
> Build F1 daqui: `arquivos-page.jsx` + `arquivos-data.jsx` + `modulos-faltantes.css` (rota `arquivos`).
> ⚠️ Nada aqui está commitado — as tools de GitHub do Cowork são read-only.

## 1. Decisão de [W]

**D4 — purge pela UI: NÃO.** A tela nunca apaga no request. Ela dispara **dry-run + relatório**;
purge real continua só no `RetentionCleanupCommand`, com `motivo`. **Isto não precisa de ADR** —
é decisão de escopo de tela, registrada aqui e no charter.

Consequência: **PR-8 está liberado** (onda 3, metade legal-livre). **D5 (aviso ao titular) segue
travado** — muda schema (`titular_avisado_at` + ação `notice` no enum), Tier 0, ADR cunhada por [W].
A proposta `arquivos-retencao-ui-aviso-titular` passa a cobrir **só** D5, não o PR-8.

## 2. Estado da paridade (conferido arquivo × arquivo, não por lista)

Convergido nos dois lados — nada a fazer:

| Item | Onde estava errado | Estado |
| --- | --- | --- |
| buckets `common`/`public` (não existem no enum) | protótipo inventou, `main` portou fiel | corrigido nos 2 · 4 buckets reais |
| "recente · em 1824 dias" estourando a célula | protótipo usou `frescor` (idade) pra prazo | corrigido nos 2 · contagem só `≤90` |
| ações com texto encavalando a coluna vizinha | protótipo: 7 colunas com `width` em `table-layout:fixed` | corrigido no protótipo · `main` não tem ação de linha |
| rótulos crus (`sensitive`, `vault`) | espelho de 24/08 tratado como canon | `BUCKET_PT`/`VIS_PT` no `main`, PT-BR no protótipo |
| ação "Auditoria" no header · título "Achados" no Cofre | não portados no PR-1/2 | já no `main` |

Acertado no protótipo HOJE, para fechar a paridade (o `main` já estava certo nos 4):

1. `VIS` ganhou `business: "Equipe"` — antes `visibility=business` mostrava "—".
2. "Escopo do job": `business_id` → **"Uma empresa por vez"**, coluna no `title` (`ds/no-db-jargon-in-ui`).
3. Barra de progresso do Cofre **removida** — denominador era 5 GB de mock e não há quota por disco em `Config/config.php`.
4. Contador da aba **Retenção** retirado — aba de retrato não leva número; Acervo e Trilha (listas) seguem contando.

Diferença que **resta**, e é de escopo declarado, não defeito: o protótipo tem ação por linha
(Avisar · Baixar · Classificar · Excluir), lista de grace e dry-run; o `main` é leitura pura.
Isso é onda 2 (PR-6/PR-7) e onda 3 (PR-8), na ordem do `PEDIDO-PARA-CODE.md` de 24/08.

## 3. PR-8 — rodar retenção pela tela, em dry-run

Escopo mínimo, e nada além dele:

- Rota `POST arquivos/retencao/simular`, com `RetentionRunRequest` (já existe, órfã).
- **`dry_run` forçado `true` no controller**, não confiando no default da Request — o request
  não tem caminho que escreva. `purge` recusado no controller (não só ausente do form).
- `ArquivosRetentionService::run()` + `report()` **em job/fila**, nunca no request.
- Resultado: o relatório da `report()` na própria vista Retenção, com a frase que a tela já
  tem que dizer — o comando é quem apaga, com política. Faixa 90..3650 já validada pela Request.
- Permissão: `arquivos.governanca` (D3), não só `arquivos.access`.
- Trilha: o dry-run **não** escreve em `arquivos_audit_log` (não aconteceu nada com arquivo);
  se quiser rastro de quem simulou, é `activity_log`, não a trilha do arquivo.

Portão: teste que prova que **nenhum caminho** do PR-8 escreve em `arquivos` nem em
`arquivos_audit_log` (canário: mudar `dry_run` pra `false` no controller deve reprovar o teste).
Copy da tela em PT-BR, sem enum cru, smoke 1280/1440 sem scroll horizontal.

## 4. Compare autorizado

[W] liberou rodar `gerar-payload-partes.mjs` → `aplicar-payload.mjs` com `--compare --check`,
delta sobre o bundle de 24/08. Pedido: **veredito por arquivo**, nomeando
`arquivos-page.jsx`, `arquivos-data.jsx` e `modulos-faltantes.css` — os três foram tocados hoje,
então STALE no espelho é esperado e a versão nova é a do Cowork.
