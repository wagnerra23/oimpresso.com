---
date: "2026-10-07"
time: "18:55 BRT"
slug: gestao-fila-merges-07-out-tarde
tldr: "Gestão da fila de merges 07/10 tarde. ~45 PRs mergeados (Forja até a thread 06, Sistema 04/05, relatórios do Sistema 07, Fiscal 08–10, tema escuro, CI). Defeito causado pela fila: a união de listas juntou dois testes numa linha do forja-pest.yml (exit 126 depois de os testes passarem), consertado no #8985 com normalização. O deploy de produção ficou ~1h sem completar porque cada merge cancelava o pendente; resolvido alternando janelas de merge com a espera do deploy."
prs: [8932, 8937, 8928, 8936, 8939, 8985, 8934, 8956, 8947, 8961, 8963, 8964, 8965, 8966, 8967, 8968, 8969, 8970, 8971, 8972, 8973, 8974, 8975, 8976, 8977, 8978, 8979, 8980, 8981, 8982, 8983, 8984, 8986, 8987, 8988, 8989, 8991, 8993, 8994, 8996, 8997, 8998]
---

# Handoff — Gestão da fila de merges (07/10 tarde)

Sessão "Gerente da fila de merges (sucessor de 07/10 tarde)", renomeada depois para "(noite 07/10)". Mandato [W]: "gerencie o merge de todos, use o que precisar, não me pergunte". Nesta tarde ele acrescentou "não preciso te dizer o que fazer em cada sessão" e "pode fazer, por que me esperar? decida". As exceções continuam as mesmas: valor, migration destrutiva, cutover e baseline/foto (ADR 0409).

## Estado ao fechar (~21:55Z)

- **Fila aberta:** os quatro PRs abaixo só esperam os 48 required.
  - #9002: Sistema 07, Produtos em tendência.
  - #9003: Fiscal 15a, revisão do contador. O desvio de produção para a biz 999 saiu a pedido do gerente.
  - #9004: registro de envio do `_saida-10`.
  - #8999: import do handoff 53 do Cowork. O dono confirmou que está pronto.
- **#9000 (referência da Venda V3 = cópia do [W]):** precisa subir o `venda-v3.jsx` ao Cowork antes do merge. O `pendentes-cowork --plano` o classifica como `fora_do_canal`, e o hook `block-design-sync-without-optin` exige a frase literal `/design-sync` do [W] no chat. O "pode fazer" dele não arma o hook.
- **#8991 (Maiara, KPIs da Fabricação seguindo o filtro):** mergeado com o "decida" do [W]. O corpo do PR pedia o antes→depois de valores fora do git. Sem filtro, os números não mudam.
- **Recibos ao Cowork:** tudo o que está no main foi enviado e conferido byte a byte (#8988, #8993, #8998 e #9004). Regra adotada (item 3 do Gargalo): os recibos entram juntos num único PR de registro por lote.

## Decisões da gerência (com delegação [W])

- Relatórios da thread Sistema 07 que seguem o molde ("Blade segue padrão, 3 caminhos batem, nenhum número muda em produção") são liberados pelo gerente sem consultar o [W].
- `css-size-baseline` regravado no #8982, com +34 linhas do tema escuro (ok [W]).
- `getExpenseReport` passou a respeitar o local permitido, com "lista vazia = nada" (#8986, fail-closed). As funções irmãs mostram tudo nesse caso.
- Fiscal 15 foi fatiada em 15a/15b/15c. A 15b é pública e exige teste Tier 0 de link e código.
- O item 4 do Gargalo do Git (detecção de mudança nos required) ficou de fora: precisa de ADR.

## Incidentes

1. **Lista do forja-pest.yml quebrada pela união.** O `uniao-lista.mjs` deixou duas entradas numa mesma linha. A lane rodava 107 testes verdes e depois caía em `Permission denied`, exit 126 (run 37678542131). O #8985 entrou normalizado. Agora o `serial-forja.sh` roda o `fix_lista.py` (uma entrada por linha, sem duplicata) depois de toda união. O #8992 foi fechado como redundante.
2. **Deploy de produção travado ~1h.** O `deploy.yml` cancela o deploy pendente quando chega um novo (concurrency). A fila de CI segurava cada run uns 15 min, e cada merge cancelava o deploy anterior antes de ele rodar. Agora o `pausa-deploy.sh` alterna uma PAUSA, que espera completar o deploy do HEAD, com uma janela de 10 min de merges. O `fila-perm.sh` e o `serial-forja.sh` respeitam o arquivo `PAUSA`.

## Ferramentas (fora do repo): `D:/oimpresso.com/.claude/gestor-fila-scripts/noite-0710/`

- `fila-perm.sh`: lê o `fila.txt` e mergeia quando o `req-ok` dá OK; roda 2h.
- `pausa-deploy.sh`: alterna a pausa e a janela de merges.
- `lane-then-merge.sh PR RUN TESTE`: confere o head, o passo `Run Pest` com success e o teste PASS pelo nome; depois anexa o PR ao `fila.txt`.
- `serial-forja.sh`: faz a união + `fix_lista.py` + regera a SUPERFICIE da Forja.
- `req-ok.mjs` + `req.txt`: os 48 required, conferidos contra a proteção viva.
- `extrai-transcript.mjs`: grava em disco o recibo lido do Cowork.
- `proxima-leva.txt`: anotações e os oks do [W].

O arquivo `PAUSA` ficou na pasta. O próximo `pausa-deploy.sh` recria ou remove.

## Pendências [W]

1. Escrever `/design-sync` no chat para o #9000.
2. Abrir ticket no suporte do GitHub: a conta Pro roda ~20 jobs simultâneos em vez de 40, e é isso que mais segura a fila.
3. Sistema 01: aprovar a tela, decidir onde ligar `MWART_SISTEMA_USUARIOS_INDEX` e definir a regra D5.
4. Rodar o seeder `NfeIcmsUfSeeder` (a tabela está vazia em produção) quando o motor passar a ler a tabela.
5. A foto do VR `sells-index` escuro segue sem regravar (ADR 0409). O VR não é required.

## Sessões ativas que mandam PR

Fiscal (15b/15c), Sistema 07 (relatórios), Sistema 04 (F3 da thread 05), App das lojas, Luiz mobile e "Oimpresso ERP visual communication import" (#8999; falta subir 57 arquivos ao projeto "cópia" da Maiara, o que não bloqueia o merge).

Arquivadas hoje: Forja, Tema escuro, Gargalo do Git, Comissão, PUXAR ×2, Recibos, Sistema 01, Service unavailable e Contas Google/Apple.

## Estado MCP no momento do fechamento

- `cycles-active` (COPI): nenhum cycle ativo.
- `my-work` (@wr23): sem tasks ativas.
- Brief do início da sessão: 3 HITL pendentes do [W]; 673 US sem atribuição; SDD composta 42,9.
