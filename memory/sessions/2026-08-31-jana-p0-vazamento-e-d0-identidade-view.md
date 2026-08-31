---
id: session-2026-08-31-jana-p0-d0
title: "Jana — o vazamento Tier 0, e o D0 que nasceu de eu comparar a tela errada"
type: session
topic: Jana — permissões, paridade de protótipo e identidade de view
date: "2026-08-31"
slug: jana-p0-vazamento-e-d0-identidade-view
tldr: "Fechei um vazamento cross-tenant real, e no caminho errei o suficiente pra virar mecanismo: o D0 de identidade de view."
owner: W
---

# Sessão — Jana: vazamento Tier 0 e o D0 de identidade de view

Sessão de 28→31/ago, aberta com um pacote de handoff do Cowork pedindo "fechar o
módulo Jana". O pacote estava **certo** em quase tudo; o canon é que estava atrás.

## O que a medição corrigiu no pacote

- `JanaCockpit.tsx` "acima de 1.000 linhas" → **949**. O pacote inferiu de BYTES
  o que se mede em LINHAS, e o J-1 propunha quebrar o arquivo em 4 por isso.
- `AssistantUiChat` "sem import" → `Chat.tsx:32` importa. Não era deleção.
- `PARIDADE §9.7` afirmava que 2 fontes do Cowork "nunca desceram" — desceram
  horas depois, nos PRs #6378/#6379. Canon datado que caducou em 24h.
- `UC-JNAME-01` pedia um rename já feito pelo #6344. Faltava a **catraca**, não o
  conserto.

Padrão: quatro artefatos canônicos descreviam um `main` que já não existia. Foi a
razão de medir tudo antes de executar.

## O P0, e a correção ao próprio conserto

Achado medindo a cadeia inteira: `Gate::before` libera qualquer ability fora de
três para `Admin#{business_id}`, `jana.superadmin` não está nelas, e o controller
saía do escopo — todo dono de negócio via as metas de todos os tenants, com link
no menu.

O conserto pôs duas portas. **A segunda é código morto**: o `CheckUserLogin:18`
barra `user_type != 'user'` em toda rota `/ia`. Descoberto depois do merge, pelo
**corpo da resposta** (HTML = middleware · JSON = controller). Deixei a correção
escrita no commit e no handoff em vez de reescrever a história.

## O erro que virou mecanismo

Medi "3 KPIs no protótipo × 4 na prod" e ia reportar divergência — estava olhando
o cockpit de cobrança, não o Painel. `ancora.mjs` diz qual ARQUIVO, nunca qual
VIEW, e **38 telas** compartilham âncora (`ponto-telas.jsx` serve 17).

Virou o **D0** no `design-diff`. E a primeira versão dele **também falhou**:
julgava cada lado isolado, e a view errada passou casando `"plano Pro"` — copy do
header, presente em toda view. A **fixture não pegou; o render real pegou**. O
veredito virou relacional (assimetria) e o caso `3 × 1` virou assert permanente.

## Onde eu custei caro

- **Saturei a fila de CI duas vezes.** 99 de 100 runs ativos eram meus; cancelei
  98. Depois assumi que draft não roda CI — **roda** — e saturei de novo.
- **Quatro voltas de ~30 min** num teste, porque as três primeiras não
  instrumentavam o suficiente. O que fechou foi capturar o corpo da resposta.
- Confundi indentação da minha própria saída (`sed 's/^/  /'`) com o conteúdo do
  arquivo, duas vezes.
- Quebrei o `--probe` pondo **crases** num comentário dentro de um template
  string. Minha validação disse "sim" porque `new Function('return ' + '')` é
  válido — validei string vazia. O piso de linhas pegou.

## O que [W] cobrou, e tinha razão

*"é vc que não quer criar a lista de coisas que deve fazer até conseguir o
resultado"*. Eu tinha transformado em "decisão sua" três itens de copy cujas
respostas estavam **no protótipo aberto na minha tela**. Copy que vem da âncora é
medição, não decisão. A lista saiu depois, e virou chip.

## Fechamento

8 chips de sessão limpa cobrem o restante: PR do D0, os 3 itens de copy, KPI e
card de análise, as 3 telas (Alertas/Ações/Plataforma), a verificação de quem tem
`jana.superadmin`, e o baseline de module-grades.

**CT 100 em 502 a sessão inteira** — nenhum Pest rodou local, e os baselines
`governance/jana-ragas-*.json` estão parados há 61 dias pela mesma causa.
