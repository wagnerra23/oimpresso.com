# O site caía a cada compilação — e eram dois defeitos, não um

**TL;DR:** [W] pediu que o site parasse de cair durante o deploy. Medindo antes de tocar, o problema tinha **duas** causas independentes: o 503 de ~1min por deploy (que 52% das vezes nem precisava acontecer) e um chunk 404 que quebrava a tela de quem já estava com o sistema aberto — este último **depois** da janela, invisível pra quem só olhasse o tempo de manutenção. [#5690](https://github.com/wagnerra23/oimpresso.com/pull/5690) mergeado. Em produção, a causa que mais doía está provada morta; o encurtamento da janela ficou **em aberto**, com a primeira amostra contra a minha própria previsão.

---

## O que o pedido parecia, e o que a medição mostrou

O pedido — *"fazer não cair mais o site quando estiver compilando"* — convida direto ao conserto óbvio: encurtar a janela de `artisan down`. Isso teria resolvido **metade** do problema.

| # | Causa | Como foi medida |
|---|---|---|
| **A** | Site em 503 durante todo o deploy | timing de passo da API do Actions: **54s / 59s / 63s** em 3 runs |
| **B** | Swap do bundle apagava chunks antigos → 404 na sessão aberta | `import.meta.glob('./Pages/**/*.tsx')` + `git grep` por tratamento de erro de chunk = **zero** |

A causa B é a que o usuário sente como "o sistema morreu": a Larissa está com a tela aberta, o deploy troca o diretório de bundles, e a próxima navegação dela pede um arquivo que não existe mais. Acontece **fora** da janela de manutenção — nenhuma redução de 503 a tocaria.

Duas medições adicionais deram o resto do desenho:

- **14 de 27** deploys bem-sucedidos (**52%**) derrubaram o site sem tocar uma linha servida em produção (`.github/`, `.claude/`, `scripts/`, `governance/*.json`)
- **`composer.lock` mudou em 0 de 27** — os ~27s de `composer install` rodavam dentro do 503 sem ter o que instalar, sempre

## As decisões que a medição forçou

**Job separado, não `if:` espalhado.** O caminho leve virou o job `sync-light` em vez de 17 condicionais nos passos do `deploy`. Motivo: esquecer o `if:` em UM passo quebra de formas diferentes — e esquecer justo no `Maintenance mode OFF` deixaria o site em 503 indefinidamente. Num job onde `artisan down` **não existe**, essa classe de erro é impossível por construção.

**Classificação fail-safe.** Qualquer dúvida (evento sem diff, `git diff` falhou, `grep` com erro real) resolve pra deploy completo. Errar pra "completo" custa 30s de 503; errar pra "leve" serviria código sem cache clear.

**Mesclar, não trocar.** O deploy agora faz `cp -af` por cima e poda só chunk morto há +3 dias — o chunk antigo continua servido, e a sessão aberta sobrevive. O `manifest.json` é sobrescrito (carga nova pega o novo) e o anterior fica guardado como afordância de rollback.

**Rede de segurança que não destrói trabalho.** No cliente, erro de chunk em visita **GET** navega sozinho pro destino (carga completa, manifest novo); em **POST/PUT** apenas avisa por toast. A mutação já foi processada pelo servidor e um reload não a repete — recarregar por cima de um formulário preenchido seria trocar um incômodo por perda de dado.

## A verificação achou dois defeitos meus

[W] pediu *"verifique tudo antes"*. Valeu o pedido.

**1. O sério.** O `sync-light` fazia `git reset --hard origin/main` — o tip **atual** da main, não o SHA que disparou o run. Como os runs são serializados pela concurrency, um sync leve podia arrastar código de runtime de um push mais novo **sem** dump-autoload, cache clear ou OPcache reset: exatamente a receita dos 500 de boot de 2026-06-18 e 06-23. Eu teria trocado um incômodo por um incidente. Corrigido: reseta em `github.sha` e, se a main andou, sai limpo — o run do commit mais novo publica tudo com a classificação dele.

**2.** A regex de erro de chunk não casava `Unable to preload` (helper do Vite).

### O falso-positivo que quase passou

O primeiro teste do guard "main andou" **passou nos dois casos**. Só que o caso 2 nunca aconteceu: o segundo clone criou branch `master`, o `git push origin main` falhou com `src refspec main does not match any`, e a main jamais avançou — os dois cenários mediram o mesmo ramo. Os "✓" eram decorativos.

Refeito com `git init -b main` e, principalmente, com **prova de qual ramo executou** (`grep PULANDO` na saída), não só do resultado final. É LC-13 na forma mais educada: verde por não-execução, com asserção bonita em cima.

## Provas colhidas (nada afirmado de leitura)

| Verificação | Resultado |
|---|---|
| `tsc --noEmit`, baseline materializado no mesmo diretório | `app.tsx` com os **mesmos 2 erros pré-existentes** antes e depois → zero erro novo |
| ESLint + `lint:baseline:check` | limpo · delta −185, sem regressão |
| `components`/`reuse`/`layout`/`foundation`/`no-mock` | exit 0 |
| Parse dos **123** workflows | 0 quebrados · 45 gates disparam no diff |
| Classificador (regex extraída do próprio `deploy.yml`) | 17/17 nos controles · 14 leve / 13 completo nos 27 deploys reais |
| Guard "main andou" (repo git real) | os **dois** ramos exercitados; com a main à frente, `app/Novo.php` **não** foi publicado |
| Merge+poda (shell extraído do workflow) | 6/6 asserts |
| Regex de chunk (extraída do `app.tsx`) | 5 mensagens reais de navegador disparam · 5 controles negativos inertes |

Detalhe de método que se repetiu: **extrair o artefato sob teste do próprio arquivo que vai pra produção**, nunca transcrever. Testar uma cópia prova coisa nenhuma sobre o que roda.

## Em produção: o que ficou provado, e o que não

Deploy `31624700876`:

- ✅ `merged build-inertia (chunks mortos ha +3d podados: 0)` — **causa B morta**
- ✅ `Composer install` → **skipped**; `Dump-autoload` → executado
- ✅ Bundle servido `app-CgPPlA1k.js` contém `vite:preloadError` ×2, `unhandledrejection` ×1, o toast ×1
- ✅ Site 200, zero erro de console, render correto
- ❌ **Janela deu 74s**, contra 54/59/63s. Eu previ ~30s

Sobre os 74s, sem maquiar: o `dump-autoload` **sozinho** levou 46s, e antes `install+dump` **juntos** levavam 26-28s. Como o dump não pode ser mais lento que install+dump, o host estava degradado — o SSH tinha dado timeout de 7 minutos poucos minutos antes. Mas contaminada não é o mesmo que inocente: **separei uma chamada SSH em duas**, e cada conexão ao Hostinger custa. É mecanismo plausível de piora, independente do host.

Uma amostra não decide. O caminho é medir 3-5 deploys em host saudável; se a janela não cair, o conserto é dobrar o `dump-autoload` de volta na **mesma** chamada SSH, com o teste do hash dentro do shell — ganha-se o skip **e** a conexão única.

## Achados laterais (não tocados)

- **O CI não roda `tsc`.** O script `typecheck` existe no `package.json` e nenhum workflow o chama; o Vite usa esbuild, que só remove tipos. Erro de tipo em `.tsx` não é pego por gate nenhum hoje.
- **Bundles × fonte no deploy completo.** O job builda do SHA do run mas reseta o fonte pro tip de `origin/main` — com fila, publica PHP mais novo que os bundles. Pré-existente; foi o análogo disso que bloqueei no caminho leve.
- **Falha de SSH ≠ queda.** O run `31620561239` morreu em `Connection timed out` no passo 7, **antes** do `artisan down` → zero downtime. A ordem do pipeline protegeu o site.

## Erro meu, registrado

Declarei que a fila do CI estava **congelada** olhando os 60 runs mais recentes — que estão em `queued` **por construção**, porque todo run nasce assim. Nos 200: 32 completed, 12 in_progress, 142 queued: congestionada e drenando, não travada. Cheguei a levantar hipótese de estouro de cota do GitHub em cima dessa leitura. É **LC-08** (medir a partir da amostra errada) e o corolário é reaproveitável: *ao medir fila ou backlog, a janela mais recente é o pior lugar pra olhar* — ela é enviesada pela própria ordenação.
