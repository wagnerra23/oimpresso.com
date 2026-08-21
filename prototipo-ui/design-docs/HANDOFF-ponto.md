# Handoff — módulo Ponto (F1 [CC] → F3 [CL])

> Escrito em 20/08/2026. **Não commitado**: as tools de GitHub deste projeto são read-only — nada
> aqui está no `main`. Ponte = você colar 1× (zero-toque) ou abrir Issue `cowork-intake` / drop em
> `cowork-inbox/`.
> Fatos sobre o repo neste documento vêm da leitura do **espelho local** da pasta anexada
> (`Modules/Ponto/**`, `prototipo-ui/contrato/**`, `resources/js/Pages/Compras/*.md`) — não verifiquei
> o `main` neste turno.

## O que foi feito

Import das **10 telas Blade** do `Modules/Ponto` para dentro de `oimpresso.com.html` (rota `ponto`,
abas de área), mais 3 telas que o Blade não tem: **Fechamento**, **Conformidade** e **REP-P (celular)**.

## Arquivos do build (vão para `prototipo-ui/cowork/`)

| Arquivo | Papel |
| --- | --- |
| `ponto-data.jsx` | dados do módulo espelhando os campos de `ponto_*` + `Config/config.php` + `lang/pt/ponto.php` |
| `ponto-ui.jsx` | peças compartilhadas (pílula de estado, KPI, card, nota, tabela, paginação) |
| `ponto-telas.jsx` | aprovações · intercorrências · banco de horas · escalas · colaboradores · importações · relatórios · configurações/REPs |
| `ponto-fechamento.jsx` | fechamento da competência + painel de conformidade CLT |
| `ponto-mobile.jsx` | REP-P: app do colaborador + fila de validação do gestor |
| `ponto-page.jsx` | shell do módulo, painel e espelho (lista, espelho individual, grade, folha de impressão) |
| `ponto-page.css` | estilos `pt-*`/`ptm-*` — só tokens do DS vivo |
| `android-frame.jsx` | frame de aparelho (starter), usado só na aba REP-P |

O guard `cowork-ssot-guard.mjs` aceita esses (jsx/css). **Nada de memória, charter ou screenshot** em
`cowork/`.

## Arquivos de governança (vão para os caminhos do próprio repo)

| Arquivo | Destino |
| --- | --- |
| `prototipo-ui/contrato/ponto-painel.contract.json` | mesmo caminho |
| `prototipo-ui/contrato/ponto-espelho.contract.json` | mesmo caminho |
| `prototipo-ui/contrato/ponto-fechamento.contract.json` | mesmo caminho |
| `prototipo-ui/contrato/ponto-rep-p.contract.json` | mesmo caminho |
| `resources/js/Pages/Ponto/Fechamento.charter.md` | mesmo caminho |
| `resources/js/Pages/Ponto/Fechamento.casos.md` | mesmo caminho |

As âncoras `data-contract="<id>"` **já existem no protótipo** — no F3 elas precisam ser recriadas no
`.tsx` para o check `contrato-de-tela` achar as seções. Os contratos declaram copy literal e ordem;
o que ainda depende de [W] está em `_pendente_w` dentro de cada arquivo.

## Decisões que faltam ([W]) — resumo

1. **Estado da competência** (aberto/consolidado/fechado): tabela nova ou derivado das apurações?
   O protótipo usa localStorage, que é cache de tela, não verdade.
2. **Permissão do fechamento**: `ponto.fechamento.manage` nova ou reusa `ponto.configuracoes.manage`?
3. **Exceções assinadas** na consolidação: onde persistem, e se bloqueiam a geração do AFD.
4. **Reabrir competência fechada**: existe com auditoria ou é definitivo?
5. **REP-P com GPS ruim**: hoje a tela bloqueia (espelha o 422 do Service). O técnico em galpão sem
   sinal fica sem caminho — permitir "bater mesmo assim" com justificativa?
6. **Copy da selfie** (LGPD Art. 9º): o Service guarda SHA-256 + URI em storage. A tela diz
   "guardamos só o código da imagem, nunca a foto" — confirmar o texto cliente-facing.
7. **Relatórios legais**: AFD/AFDT/AEJ seguem 501 no vivo; o wizard registra o pedido. Ordem de
   implementação em `ReportService`?

## O que o protótipo NÃO promete

- Não recalcula apuração (consolidar carimba o que já existe)
- Não edita marcação em nenhum estado — correção é anulação + nova marcação
- Não gera arquivo legal (o wizard marca `NAO_IMPLEMENTADO` quando o vivo retorna 501)
- Não tem fila offline no REP-P (o Service lista isso como pendência fora de escopo)
