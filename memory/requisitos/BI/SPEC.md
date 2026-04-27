# Modules/BI — Especificação (TODO/AUSENTE)

> **Status (2026-04-27):** módulo **NÃO EXISTE** na branch `6.7-bootstrap`.
> Existia em `3.7-com-nfe` (UltimatePOS 3.7) e foi **perdido na migração 3.7 → 6.7**.

---

## Contexto

Wagner registrou em `memory/claude/preference_modulos_prioridade.md` (2026-04-22):

| Módulo | Existia em 3.7 | Existia no backup main-wip | Decisão |
|--------|----------------|----------------------------|---------|
| **BI** (Business Intelligence) | sim | sim | Avaliar uso real antes |

## Decisão sobre testes (lote 5)

Não escrevemos testes Feature por **falta do módulo no filesystem**. Criar
test files em `Modules/BI/Tests/Feature/` resultaria em namespace
inválido (`Modules\BI\...`) e falha de autoload.

## Próximos passos sugeridos

1. **Recuperar do branch `3.7-com-nfe`** o diretório `Modules/BI/`:
   ```bash
   git checkout 3.7-com-nfe -- Modules/BI/
   ```
2. **Auditar dependências** — em 3.7 era PHP 7.4 + Laravel 5.8 + Blade. Em
   6.7 precisa adaptar para PHP 8.4 + Laravel 13 + Inertia.
3. **Confirmar uso real** com Wagner antes de gastar horas migrando.
4. **Quando reintroduzido:** seguir o padrão Jana (ADR 0011) e cobrir com
   o mesmo formato de testes do `Modules/Essentials/Tests/Feature/`.

## TODO

- [ ] Wagner decide: restaurar, descartar ou reescrever?
- [ ] Se restaurar, fazer cherry-pick + ADR de migração + testes Pest.
- [ ] Atualizar este SPEC com user stories (`US-BI-NNN`) quando definido.
