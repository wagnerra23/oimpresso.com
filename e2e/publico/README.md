# E2E das páginas públicas (sem login)

Specs em `e2e/publico/**` rodam nos projetos `publico-desktop` (1280x800) e `publico-mobile`
(iPhone 12 no Chromium). Não herdam a sessão do ERP.

## Onde rodam

| Contexto | Alvo | Como |
|---|---|---|
| PR (`e2e-gate.yml`) | `php artisan serve` local, pelo nome `uniafibro.com.br` | o Chromium mapeia o domínio para 127.0.0.1 |
| Noturno | `https://uniafibro.com.br` real | `UNIAFIBRO_BASE_URL` + `E2E_SKIP_LOGIN=1`, só specs de leitura |

Com o mapa ativo, um redirect para `https://uniafibro.com.br/` cai em 127.0.0.1:443 e falha:
o CI nunca alcança o site real por acidente.

## Regras

- **Só leitura contra o site real.** Nada de PIN, formulário, revogação ou carga. Spec que
  escreve estado ou testa trava/rate limit fica fora do job noturno (marque com
  `test.skip(!!process.env.UNIAFIBRO_BASE_URL, 'só no CI')`).
- **Feature ainda não mergeada:** `test.skip(!(await rotaExiste(request, '/rota')), 'aguardando PR #N')`.
  Skip não é verde: o relatório conta skipped à parte, sempre com o PR no motivo.
- Locators resilientes (role/label/text), como no resto de `e2e/`.

## Rodar local

```bash
php artisan serve --host=127.0.0.1 --port=8000 &
npx playwright test --project=publico-desktop --project=publico-mobile
```
