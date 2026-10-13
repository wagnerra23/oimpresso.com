---
sessao: "11"
titulo: "Unificado — vínculo #BL- — saída da thread (rodada 2: aplicada)"
autor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 8fe939db25
thread: 11-unificado-xlink-boleto.md
veredito: "aplicada na rodada 2 (2026-10-13). A rodada 1 (2026-10-06) parou pelo PARAR SE; o [W] mandou executar a thread, o que escolhe a opção (a) que ela deixou."
---

# _saida-11 · rodada 2 (2026-10-13) — aplicada

O [W] pediu a thread 11 de novo em 2026-10-13, com a _saida da rodada 1 já no `main`. Li isso como a escolha da **opção (a)** que a rodada 1 deixou: o teste passa a fixar o destino novo. A rodada 1 continua abaixo, sem edição.

## O que mudou (4 arquivos, +7 −5)

1. `FinCrossLinkify.tsx:32`: `href: (_n: string) => '/financeiro/cobranca'`. Comentário da `:10` atualizado.
2. `Unificado/Index.tsx:803` (`FIN_XLINK_DEFS`, chip da lente Vínculos): mesmo href. Label, ícone e classe ficam. O parâmetro `_n` fica na assinatura porque `FinVinculosChips` chama `def.href(m[1])` sobre a união `as const`.
3. Regex `#BL-` mantido nos dois arquivos.
4. `Index.charter.md:123`: o destino do linkify passa de "Boletos" para "Cobrança".
5. **Fora do `prefixo` do índice, declarado:** `Onda7OutputR3Test.php:47`. Era `toContain('/financeiro/boletos/')`, ou seja, fixava o href que dá 404. Virou `toContain('/financeiro/cobranca')` + `not->toContain('/financeiro/boletos/')`. A `:40` (`#BL-`) ficou.

## PROVA

1. **Rotas.** `Modules/Financeiro/Routes/web.php` no `main` @8fe939db25: `:173` `Route::redirect('/boletos', '/financeiro/cobranca', 301)` e `:178` `GET /cobranca`. Não existe GET `boletos/{id}`. O `POST /boletos/{id}/cancelar` da rodada 1 saiu no #8813 (thread 12). O `route:list` da rodada 1 está abaixo; não re-rodei porque o checkout do CT 100 fica atrás do `main`.
2. **`Onda7OutputR3Test`**, rodado no CT 100 (`oimpresso-staging`) contra uma cópia dos arquivos em `/tmp`, com hash conferido (`FinCrossLinkify.tsx` sha256 `54b7328801f49313…` nos dois lados):
   - `main` sem a mudança: **2 failed, 7 passed (56 assertions)**.
   - com a mudança: **2 failed, 7 passed (57 assertions)**. O `it` "FinCrossLinkify detecta 6 padrões" passa.
   - os 2 vermelhos são os mesmos nos dois lados e não têm relação com esta thread: `charter_version: 4` (`:137`) e `fin-fechamento-trigger` (`:110`). É por eles que o arquivo está em `.github/financeiro-pest-quarantine.list:102` ("2 failed, 7 passed (56 assertions)"). **Consequência: a lane `financeiro-pest` não roda este teste.** A prova dele é o run do CT 100, não o CI.
   - controle de mordida: o teste novo contra os `.tsx` do `main` dá **3 failed**. O terceiro é `:48` `toContain('/financeiro/cobranca')`.
3. **Grep:** `git grep -n "financeiro/boletos/" -- resources/js/Pages/Financeiro/Unificado` devolve 0 linhas.
4. **Chip da lente Vínculos:** vem de `FIN_XLINK_DEFS` (`Index.tsx:799`), consumido por `FinVinculosChips`.
5. **Não testado no navegador.** O clique em "Boleto #N" levando à Cobrança é inferido do `href` + `router.visit(ref.href)` (`FinCrossLinkify.tsx:93`) e do `<a href>` do chip.

## Fica fora (sem mudança desde a rodada 1)
- `?busca=N` na Cobrança: o `#BL-N` só existe no seeder de demonstração. Decisão de dado.
- Tirar o `Onda7OutputR3Test` da quarentena exige consertar os 2 asserts de outras ondas. Não é desta thread.

---

# Rodada 1 (2026-10-06)

# _saida-11 · Vínculo `#BL-` no Unificado

**Nada foi editado no produto.** A thread parou antes do primeiro Edit, como manda o `PARAR SE`.

## Por que parou

A PROVA 2 da thread diz que `Modules/Financeiro/Tests/Feature/Onda7OutputR3Test.php` segue verde, citando a `:40` (`toContain('#BL-')`). Mas o mesmo teste, no mesmo `it()`, afirma o **href antigo**:

```
Onda7OutputR3Test.php:46-47
    // Hrefs canon
    expect($src)->toContain('/financeiro/boletos/');
```

`$src` é o fonte do `FinCrossLinkify.tsx`. Com a mudança pedida (`href: () => '/financeiro/cobranca'` + comentário da `:10` atualizado), a string `/financeiro/boletos/` some do arquivo e a `:47` reprova. Isso contradiz a PROVA 2 e cai no `PARAR SE`: *"Algum teste afirmar o href antigo: listar quais e devolver, sem reescrever teste pra caber."* A thread só tinha lido a `:40`.

Além disso, o teste fica fora do prefixo da thread (`Modules/Financeiro/Tests/` não está em `prefixo`), então a sessão não podia ajustá-lo de qualquer forma.

**Testes que afirmam o href antigo (varredura contada):** `git grep -n "financeiro/boletos/"` no `main` devolve 4 linhas em 3 arquivos. 3 são o próprio alvo da thread (`FinCrossLinkify.tsx:10`, `:32` e `Unificado/Index.tsx:803`). O único teste é **`Onda7OutputR3Test.php:47`**. O `CobrancaControllerTest.php:298-303` testa `GET /financeiro/boletos` (sem `/` final, o redirect 301) e não é afetado.

## Os 5 itens

1. **Rotas (PROVA 1):** rodado no runtime, não lido. `php artisan route:list --path=financeiro/boletos` no CT 100 (`oimpresso-staging`):
   ```
   ANY   financeiro/boletos                       financeiro.boletos.index
   POST  financeiro/boletos/{remessaId}/cancelar  financeiro.boletos.cancelar
   Showing [2] routes
   ```
   Não há GET `boletos/{id}`. A premissa da thread se confirma. Ressalva: o checkout do CT 100 está em `e57b78bf5`, mais antigo que o `main`. O `Routes/web.php` do `main` foi conferido à parte e declara as mesmas duas rotas (`:173` e `:174`).
2. **`Onda7OutputR3Test`:** **reprovaria** com a mudança, pela `:47` (acima). Não rodado, porque a mudança não foi feita.
3. **Grep `/financeiro/boletos/`:** não aplicável, nada editado. Hoje os dois arquivos contêm o href antigo.
4. **Chip da lente Vínculos:** vem de `FIN_XLINK_DEFS` em `Unificado/Index.tsx:799-804`, consumido por `FinVinculosChips` (`:806`). Esse `PARAR SE` não disparou.
5. **Charter `:123`:** continua citando "Boletos" como destino do linkify. Não tocado, porque ele mudaria junto com o código.

## O que destrava

Uma das duas, decisão do dono do playbook:

- **(a)** Reemitir a thread com `Modules/Financeiro/Tests/Feature/Onda7OutputR3Test.php` no `prefixo` e a `:47` trocada por `toContain('/financeiro/cobranca')`, mantendo a `:40` (`#BL-`). O teste estrutural passa a fixar o destino novo, que é o que o protótipo faz.
- **(b)** Manter o teste e não mudar o href. O vínculo segue com 404.

A mudança de código continua de ≤10 linhas e cabe numa sessão.
