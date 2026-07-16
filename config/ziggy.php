<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ziggy — mapa de rotas Laravel exposto ao JS
|--------------------------------------------------------------------------
|
| POR QUE ESTE ARQUIVO EXISTE (2026-07-16):
| O `@routes` default serializa TODAS as rotas nomeadas do app — 1.418 delas,
| ~169 KB INLINE, dentro de um HTML `Cache-Control: no-cache`. Ou seja: o maior
| payload da pagina e justamente o que NUNCA entra em cache do browser; ele e
| re-baixado e re-parseado a cada navegacao Inertia.
|
| As paginas PUBLICAS (Pages/Site/*: Login, Home, Blogs, BlogPost, Page) nao
| chamam `route()` NENHUMA VEZ — auditado em 2026-07-16 nas 4 camadas que elas
| alcancam: Pages/Site (0), Components/Site (0), Layouts/SiteLayout (0),
| app.tsx (0). Elas navegam por <Link href> e o proprio login faz
| `post('/login')` com URL literal. Logo, o mapa inteiro e desperdicio de 100%
| nessas telas — nao de 96%.
|
| O grupo `public` abaixo e intencionalmente MINIMO (nao vazio): mantem a funcao
| global `route()` definida, para que qualquer componente compartilhado que venha
| a ser importado numa pagina publica no futuro nao exploda com ReferenceError —
| ele so nao carrega o mapa do ERP.
|
| ⚠️ NAO adicionar `only`/`except` GLOBAIS aqui. O app autenticado tem 21 chamadas
| `route(nomeVindoDoServidor)` — nome dinamico via prop Inertia (ex.:
| Whatsapp/_components/ConversationList.tsx:116). Nenhum grep consegue enumerar
| esses nomes, e um `only` global quebraria em runtime SEM o gate visual pegar
| (erro de rota aparece em clique, nao em screenshot).
|
| @see resources/views/layouts/inertia.blade.php (onde o grupo e aplicado)
*/

return [
    'groups' => [
        // Paginas publicas: Ziggy minimo. Nao precisam do mapa do ERP.
        'public' => [
            'login',
            'logout',
            'password.*',
        ],
    ],
];
