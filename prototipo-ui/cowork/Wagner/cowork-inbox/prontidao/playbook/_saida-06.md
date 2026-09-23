---
sessao: "06"
titulo: Scorecard · núcleo avulso (Arquivos/Index · Backup/Index · User/Perfil)
executor: "[CC]"
base: 317e1b4ec33
---
# _saida 06

## Checklist
1. ✅ Pré-Flight: charter existe nas 3 telas (nenhum PARAR SE de charter) — todos `status: draft`
2. ✅ Nota 16-dim por tela, cada dimensão com evidência `arquivo:linha` no YAML (bloco `evidencia:`)
3. ✅ 3 YAMLs com o slug exato da espec · `baseline_anterior` = a própria nota
4. ✅ Só o prefixo tocado — `.tsx` e charters intocados; zero git ops
5. ⛔ Passos 2-4 (E2E / axe / smoke) fora, como a espec manda

## ⚠️ De onde vem a nota — leia antes de usar o número

**Leitura de código, não render.** Li `.tsx` + charter + controller (e rota/config quando a
dimensão pedia). **Prod não foi aberto** — esta thread não teve browser. Nenhuma dimensão foi
medida em tela viva; a espec pede registrar isso em vez de dar nota de olho, e o pedido do
executor foi dar a nota de leitura declarando a origem. Está declarado nos três YAMLs
(`medicao: leitura-de-codigo`).

**Backup/Index tem um agravante:** o render Inertia nasce **desligado** — `config/mwart.php:39-42`
(`MWART_BACKUP_INDEX` default `false`) e `BackUpController.php:42`. Com a flag off, `/backup` serve
o Blade legado, e a nota **não descreve o que o usuário vê hoje**. O estado da flag em prod não
foi medido.

Método: média simples arredondada das 16 dimensões, peso de persona 1× — nenhuma das três telas
tem entrada em `personas-por-modulo.yml`; a persona veio do charter.

## Notas

| tela | arquétipo | persona | nota | nível | pior dimensão | arquivo |
|---|---|---|---|---|---|---|
| Arquivos/Index | list | wagner (+ eliana) | **74** (73,5) | Advanced | cognitive_load 62 | `memory/governance/scorecards/screens/arquivos-index.yaml` |
| Backup/Index | list | wagner (superadmin) | **77** | Advanced | error_recovery 66 · a11y 66 | `memory/governance/scorecards/screens/backup-index.yaml` |
| User/Perfil | form | misto | **74** (73,9) | Advanced | error_recovery 60 | `memory/governance/scorecards/screens/user-perfil.yaml` |

## Gaps de maior impacto (viraram `gaps:` no YAML — nenhum virou task sem [W])

- **Arquivos** — vazio filtrado mostra o vazio real ("Nenhum arquivo guardado ainda.",
  `Index.tsx:821-826`), contra o estado filtrada-vazia que o charter exige (`charter:122`);
  jargão de código na copy (`DownloadController`, `Storage::url`, `arquivos:retention-cleanup`);
  strings sem acento na Retenção (`Index.tsx:1296-1298`).
- **Backup** — excluir é `GET` (`Index.tsx:84` → `routes/web.php:1028`); confirmação por
  `window.confirm`; motivo de desabilitado só em `title`; KPI "Último backup" mostra só `HH:MM`
  (`Index.tsx:177`). E a decisão [W] de restringir a superadmin segue não implementada
  (`charter:101-104`, `BackUpController.php:37`).
- **Perfil** — o erro `geral` do servidor (`UserController.php:272`) nunca é renderizado, e o toast
  diz "Confira os campos destacados" sem nada destacado (`Perfil.tsx:256`); "Remover" foto não
  remove no servidor (`Perfil.tsx:376-388` × `UserController.php:263`); abas sem semântica de tab.

## Prova — as máquinas

YAML parseia (`js-yaml`), 16 dimensões e 16 evidências em cada, média recalculada bate:

```
arquivos-index Arquivos/Index 74 74 16 73.500 5 16
backup-index   Backup/Index   77 77 16 77.000 4 16
user-perfil    User/Perfil    74 74 16 73.875 4 16
```

`node scripts/qa/screen-grades-ratchet.mjs` (rc=0):

```
Catraca screen-grade · 195 telas · ✅ 192 ok/subiu · ✨ 3 novas · 🔻 0 regrediram · 🗑 0 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.
```

`node scripts/qa/prototipo-readiness.mjs` (rc=0): as 3 saíram de `🟡 1-CICLO (falta: scorecard)`
para `✅ PRONTAS` — o placar foi de **59 → 62 prontas**; restam **32** em 1-ciclo, nenhuma desta
thread:

```
🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): 32
  falta casos.md-com-UC (7): Essentials/Documents/Index · Essentials/Knowledge/Index ·
    Essentials/Messages/Index · Essentials/Reminders/Index · Essentials/Todo/Index ·
    Sells/Caixa/Index · Settings/PaymentGateways/Index
  falta scorecard (25): Essentials/Metas · Essentials/Tipos · Jana/Acoes · Jana/Alertas ·
    Jana/Plataforma · kb/Index.v2 · Manufacturing/{Insumos,Recipes,Report,Settings} ·
    Patrimonio/{Alocacoes,Bens,Configuracoes,Index,Manutencoes} · Sells/CreateV3 ·
    Forja/Aprovacoes/Index · Forja/Trabalho/Index · team-mcp/Forja/Cockpit ·
    Officeimpresso/Logs/{Index,Timeline} · superadmin/{Assinaturas,Dashboard,Negocios,Pacotes}/Index
Total de telas com protótipo real: 94
```

## PARAR SE

- Charter ausente: **não acionado** (3 de 3 com charter).
- Tela não abre em prod: **não medido** — prod não foi aberto. Para Backup/Index, a flag default
  off torna provável que prod sirva o Blade; confirmar antes de tratar a nota 77 como a da tela viva.
