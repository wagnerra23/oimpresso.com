---
sessao: "_saida-03"
thread: "03 · Cadastros: postos e máquinas, roteiros por família (2 trilhas, opcionais pelo orçamento) + emenda E03"
dono: "[CL]"
data: 2026-10-16
tipo: recibo
entregue_em: "PR desta thread (claude/producao-thread-03), base main; nasceu em cima da 02c (#9453)"
---
# _saida-03

## Entregue
- **Tabelas** (`Modules/Producao/Database/Migrations/2026_10_16_000001_create_pcp_cadastros_tables.php`):
  `pcp_motivos` (pausa e refugo por posto), `pcp_roteiros`, `pcp_roteiro_etapas` (bloco · trilha 0/1/2 ·
  ordem · junção · `opcional_por`), `pcp_colaborador_custos`, `pcp_ferramentas`, `pcp_postos.placa`, e a FK
  de `pcp_apontamentos.motivo_id` que a 02 deixou para cá. Todas com `business_id` indexado + FK.
- **UC-PCV-13:** `SalvarRoteiroRequest` recusa 3ª trilha, bloco de 1 trilha, paralelo dentro de trilha,
  trilha sem junção e junção cujo nome não é o da etapa seguinte. Etapa opcional guarda a opção do orçamento
  ("com laminação"); ligar/desligar na OS é o UC-PCV-14 (thread 08).
- **E03** (cito a emenda `E03-cadastros-motivos.md`):
  - motivos de pausa e de refugo por posto, lista fechada; posto novo nasce com o padrão sugerido
    (troca de bobina, setup, falta de material, manutenção, dúvida na arte, intervalo / defeito de mídia,
    cor, cabeça, corte, arte) — UC-PCV-33;
  - custo-hora da pessoa no colaborador: lido e gravado só com `producao.desempenho.ver`;
  - veículo = posto de máquina com placa, custo/h e custo/km (a 02 já tinha `veiculo` e `custo_km`);
  - ferramenta com código QR único por empresa.
- **Controllers próprios da 03:** `RoteirosController`, `PostosController`, `FerramentasController`,
  `ColaboradorCustoController`. Rotas num bloco marcado em `Routes/web.php` (`producao.roteiros.*`,
  `producao.postos.*`, `producao.ferramentas.*`, `producao.colaboradores.custo`), `producao.access` em tudo.
- **Telas:** `Producao/Roteiros/Index` (forma da vista `pcv-roteiros`: cabeçalho, abas, famílias à esquerda,
  fluxo com bloco "em paralelo" à direita, editor em drawer 760) e `Producao/Postos/Index` (postos, veículos,
  ferramentas, custo-hora). Charter e casos ao lado de cada uma.
- **Bundle:** `resources/css/cowork-producao-bundle.css` = cópia inteira do `producao-cv.css` (regra do bundle
  Cowork; PR #9463). As threads seguintes importam o mesmo arquivo.
- **Menu:** `DataController::modifyAdminMenu` ganhou o item "Produção" (pacote → `producao.access` → item),
  apontando para Roteiros até a 06 trazer o Painel.
- **Teste:** `ProducaoRoteirosContratoTest.php` (lane MySQL `verticais-pest`), tenant 98 × 99, um `it` por UC
  (UC-PCV-13, UC-PCV-33) mais dois da E03, cada um com caso discriminante e controle negativo.

## Desvios do prefixo, e por quê
- `Database/Migrations` e `Entities/` (5 entities): a 02 não criou roteiros, motivos, ferramentas nem custo
  da pessoa, e o cadastro precisa das tabelas.
- `Http/Requests/` (regra do UC-PCV-13) e `Pages/Producao/_components/ProducaoAbas.tsx` (abas compartilhadas
  pelas threads seguintes).
- `resources/css/cowork-producao-bundle.css`, `.github/workflows/verticais-pest.yml` (o teste na lane),
  `memory/requisitos/Producao/{SCOPE,SUPERFICIE}.md` (contains + projeção regenerada).

## O que NÃO foi medido
- **Render contra o alvo `producao--roteiros--index`:** não há runtime PHP nesta máquina (ADR 0062), então o
  `design-diff --probe` na tela viva não rodou. Diferenças já sabidas: as abas mostram só Roteiros e Postos (as
  outras 5 vistas ainda não têm rota — aba para rota inexistente seria 404) e o cabeçalho não tem as estatísticas
  "em produção / atrasadas" (dependem das threads 06/08). Medir depois do deploy no staging.
- O Pest roda no CI (lane verticais-pest), não nesta máquina.

## Fonte do protótipo corrigida no git (ADR 0433)
- **`producao-cv.css` (pcv11), regra G3:** `.pcv-perdas i` e `.pcv-feed li.is-refugo .pcv-feed-dot` usavam
  `--color-destructive-fg` como `background`, e o `conformance-gate` não aceita token `-fg` como superfície.
  As duas passaram a `--color-destructive` na própria fonte (`prototipo-ui/cowork/Wagner/producao-cv.css`,
  PR #9463), e o bundle `resources/css/cowork-producao-bundle.css` segue igual a ela.

## Perguntas
1. O protótipo não tem vista de **postos/motivos/veículos/ferramentas**; a tela segue as classes `pcv-*`. Se
   quiser forma própria, é uma vista nova (`pcv-postos`) e um alvo novo.
2. A E03 não traz UC para custo-hora da pessoa, veículo e ferramenta; ficaram como `[BACKLOG]` defendidos pelo
   teste. Se quiser id, o casos do Cowork ganha UC-PCV-5x.

## Provas
- `node scripts/qa/placar.mjs --indice …/producao/playbook/00-INDICE.md --thread 03` → 03 entregue.
- `casos-coverage-guard` sem violação nova · `uc-id-lint` ok · `screen-coverage-map --check` ok ·
  `memory-schemas/validate.mjs` dos 2 charters conforme · `module-surface --all --check` ok ·
  `tsc` sem erro em `Modules/Producao` · `eslint` limpo nas telas novas.
