# 12 · Aposentar o `POST /boletos/{id}/cancelar`, só se não houver remessa antiga em aberto

> Emitida pelo [CC] em 2026-10-06. [W] delegou a decisão ("pode fazer tbm") depois do `SINCRONIZAR Boletos`. Mesmo playbook do Financeiro (anti-scatter).
> Base lida: `wagnerra23/oimpresso.com@main` 591ce81d2b33 (2026-10-06). **Reler no turno.**
> **Sessão:** limpa · **dono:** [CL] · **vaga:** 1 · **sem ALVO:** é backend, não tem tela.

## Por que existe
- `Modules/Financeiro/Routes/web.php:172` declarou o `POST cancelar` "preservado durante 60d" a partir de 2026-05-19. O prazo venceu em **2026-07-18**.
- `BoletoController.php` (63 linhas depois do #5871) só tem `cancelar()`. O próprio docblock diz: "Aposentar ou manter é decisão [W]".
- Nenhuma tela chama a rota: em `resources/js/Pages/Financeiro/`, o único lugar que a cita é um comentário em `Cobranca/_components/DrawerCobranca.tsx:122`, que a declara coisa do modelo antigo.
- O modelo `BoletoRemessa` **continua vivo** (`CnabDirectStrategy`, `TituloService`, `ContaBancaria`, `Config/retention`). Esta thread **não** mexe nele.

## Decisão (D-FIN-BOLETO-CANCELAR), condicionada à medida
A regra é: **aposentar se, e somente se, não houver `BoletoRemessa` cancelável.** Cancelável = status ∈ {`gerado`, `enviado`, `registrado`, `vencido`}, conforme `BoletoRemessa.php:46-51` e as guardas de `cancelar()` (recusa `cancelado` e `pago`).

## Passo 1 · MEDIR (read-only, obrigatório antes de qualquer edição)
Em produção (biz=164 e todos os business), via tinker ou consulta read-only:
```
BoletoRemessa::withoutGlobalScopes()->whereIn('status', ['gerado','enviado','registrado','vencido'])->count()
```
Mais o mesmo agrupado por `business_id`. Colar a saída literal no `_saida-12.md`.
- **= 0:** seguir para o Passo 2.
- **> 0:** **PARAR.** Não editar nada. O `_saida-12.md` lista a contagem por business e devolve a decisão a [W] com a pergunta: "cancelar essas N remessas pela tela de Cobrança, ou manter a rota?".

## Passo 2 · APOSENTAR (só se o Passo 1 deu 0)
1. `Modules/Financeiro/Routes/web.php`: remover `:174-176` (o `Route::post('/boletos/{remessaId}/cancelar' …)->name('boletos.cancelar')`) e o `use … BoletoController` da `:8`. Ajustar o comentário `:169-172`: o 301 **fica**, e a nota de 60 dias vira "POST cancelar aposentado 2026-10 (thread 12)".
2. Remover `Modules/Financeiro/Http/Controllers/BoletoController.php`.
3. `Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php:245`: tirar a entrada `'BoletoController.php' => "OtelHelper::spanBiz('financeiro.boleto.cancelar'"`, porque o arquivo deixa de existir. Não reescrever a lógica do teste.
4. `tests/Feature/Architecture/OrphanRenderGateTest.php:40`: o comentário cita `BoletoController::index`. Atualizar só se ele virar referência a arquivo inexistente que algum gate leia; senão, deixar.

## NÃO toca
`Modules/Financeiro/Models/BoletoRemessa.php` · `TituloService::cancelarBoleto` (pode ter outros chamadores) · migrations · o `Route::redirect('/boletos' …)` da `:173`, que **fica** porque o teste do charter da Cobrança `:124` o exige · `resources/js/**`.

## PROVA
1. `_saida-12.md` com a saída literal do Passo 1. Sem ela, nada mais conta.
2. Se aposentou: `php artisan route:list --path=financeiro/boletos` mostra **só** `boletos.index`.
3. Lane `Modules/Financeiro/Tests/Feature/` verde, em especial `MultiTenantIsolationTest`.
4. Grep por `boletos.cancelar` e `BoletoController` em `Modules/`, `app/`, `routes/`, `resources/js/` e `tests/`: zero resultados de código (comentário histórico pode ficar).

## PARAR SE
- Passo 1 > 0.
- O grep do item 4 achar um chamador fora dos listados aqui (job, comando, outro módulo). Listar e devolver.
- `TituloService::cancelarBoleto` ficar sem nenhum chamador depois da remoção. **Não** apagar: registrar no `_saida` como candidato a limpeza, que vira thread separada.

## O que esta thread NÃO resolve (bloco 7)
- O destino dos registros `BoletoRemessa` antigos (retenção ou arquivamento): segue `Config/retention`, fora daqui.
- O link `#BL-` do Unificado: é a thread 11.
