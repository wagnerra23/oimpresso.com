---
sessao: "11"
titulo: "Unificado — vínculo #BL- — saída da thread (PARADA antes de editar)"
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main 1ab4ab51b1 (merge do #8806)
thread: 11-unificado-xlink-boleto.md
veredito: "parada pelo PARAR SE — um teste afirma o href antigo. A premissa da rota ausente se confirmou; o código não foi tocado."
---

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
