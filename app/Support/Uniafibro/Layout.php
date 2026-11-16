<?php

declare(strict_types=1);

namespace App\Support\Uniafibro;

/**
 * Moldura HTML das páginas de direitos da Uniafibro: HTML + CSS, sem JavaScript, sem rastreador.
 * Não é Blade de propósito (catraca da migração Blade→React, ADR 0277). Todo texto vindo do
 * frontmatter passa por e(); o corpo vem do CommonMark com html_input=strip.
 *
 * Ver memory/requisitos/Site/RUNBOOK-uniafibro.md §6.
 */
final class Layout
{
    public const AVISO_VERSAO = 1;

    public const AVISO = 'Orientação geral, não é consultoria jurídica. As regras mudam e cada caso tem detalhes. '
        .'Antes de pedir um benefício ou entrar na Justiça, fale com um advogado ou com a Defensoria Pública de SC. '
        .'Sobre a sua saúde, fale com o seu médico.';

    public static function documento(string $titulo, string $descricao, string $caminho, string $miolo): string
    {
        $t = e($titulo);
        $d = e($descricao);
        $url = e('https://uniafibro.com.br'.$caminho);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$t} — Uniafibro-SC</title>
            <meta name="description" content="{$d}">
            <meta name="theme-color" content="#6b3fa0">
            <link rel="canonical" href="{$url}">
            <link rel="icon" type="image/png" href="/uniafibro/favicon.png">
            <link rel="stylesheet" href="/uniafibro/direitos.css">
        </head>
        <body>
            <a class="pular" href="#conteudo">Pular para o conteúdo</a>
            <div class="fonte" role="group" aria-label="Tamanho do texto">
                <span>Texto</span>
                <input type="radio" name="fonte" id="fonte-1" checked><label for="fonte-1" title="Texto normal">A</label>
                <input type="radio" name="fonte" id="fonte-2"><label for="fonte-2" title="Texto maior">A+</label>
                <input type="radio" name="fonte" id="fonte-3"><label for="fonte-3" title="Texto bem maior">A++</label>
            </div>
            {$miolo}
        </body>
        </html>
        HTML;
    }

    /**
     * Mesmo menu da home (9 itens, [W] 2026-11-15), com "Direitos" em destaque. O Portal da
     * Associada entra quando a onda 6 da ADR 0451 existir.
     */
    public static function nav(): string
    {
        $itens = '';
        foreach (['fibromialgia' => 'Fibromialgia', 'cuidado' => 'Cuidado', 'quem-somos' => 'Quem somos', 'rede' => 'A rede'] as $id => $nome) {
            $itens .= '<li><a href="/#'.$id.'">'.$nome.'</a></li>';
        }
        $itens .= '<li><a class="destaque" href="/direitos" aria-current="page">'.self::ESCUDO.'Direitos</a></li>';
        foreach (['atendimento' => 'Atendimento', 'app' => 'App', 'duvidas' => 'Dúvidas', 'contato' => 'Contato'] as $id => $nome) {
            $itens .= '<li><a href="/#'.$id.'">'.$nome.'</a></li>';
        }

        return '<nav class="nav" aria-label="Menu do site"><div class="nav-in">'
            .'<a class="marca" href="/"><img src="/uniafibro/simbolo-180.png" alt="" width="34" height="34">Uniafibro</a>'
            .'<ul>'.$itens.'</ul></div></nav>';
    }

    public const ESCUDO = '<svg class="escudo" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Z" fill="currentColor"/></svg>';

    public static function busca(string $valor = ''): string
    {
        $v = e($valor);

        return <<<HTML
        <form class="busca" action="/direitos" method="get" role="search">
            <label for="q">Procurar nos direitos e guias</label>
            <div><input id="q" name="q" type="search" value="{$v}" maxlength="80" placeholder="Ex.: BPC, laudo, carteirinha"><button type="submit">Procurar</button></div>
        </form>
        HTML;
    }

    public static function aviso(): string
    {
        return '<p class="aviso" role="note"><strong>Atenção:</strong> '.e(self::AVISO).'</p>';
    }

    public static function rodape(): string
    {
        $contato = self::blocoContato();
        $zap = self::whatsapp();

        return <<<HTML
        <footer class="rodape">
            {$contato}
            <p>Uniafibro-SC — União das Associações de Fibromiálgicos de Santa Catarina · em formação.</p>
            <p>Este site não usa rastreadores nem publicidade.</p>
        </footer>
        {$zap}
        HTML;
    }

    /**
     * Slots de contato preenchidos (config/uniafibro.php). Número só com dígitos, e-mail só se válido.
     * A chave do slot nunca aparece na página.
     *
     * @return list<array{whatsapp: string, email: string}>
     */
    public static function contatos(): array
    {
        $saida = [];
        foreach ((array) config('uniafibro.contatos', []) as $slot) {
            $zap = preg_replace('/\D+/', '', (string) ($slot['whatsapp'] ?? '')) ?? '';
            $email = trim((string) ($slot['email'] ?? ''));
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
            if ($zap !== '' || $email !== '') {
                $saida[] = ['whatsapp' => $zap, 'email' => $email];
            }
        }

        return $saida;
    }

    /** Bloco "Contato" do rodapé: não é renderizado enquanto os slots estão vazios. */
    public static function blocoContato(): string
    {
        $itens = '';
        foreach (self::contatos() as $c) {
            if ($c['whatsapp'] !== '') {
                $itens .= '<li><a href="https://wa.me/'.e($c['whatsapp']).'" rel="noopener noreferrer">WhatsApp</a></li>';
            }
            if ($c['email'] !== '') {
                $itens .= '<li><a href="mailto:'.e($c['email']).'">'.e($c['email']).'</a></li>';
            }
        }

        return $itens === '' ? '' : '<section class="contato" aria-labelledby="t-contato"><h2 id="t-contato">Contato</h2><ul>'.$itens.'</ul></section>';
    }

    /** Botão flutuante: primeiro slot com WhatsApp. Sem número, não aparece. */
    public static function whatsapp(): string
    {
        foreach (self::contatos() as $c) {
            if ($c['whatsapp'] !== '') {
                return '<a class="zap" href="https://wa.me/'.e($c['whatsapp']).'" rel="noopener noreferrer">Fale pelo WhatsApp</a>';
            }
        }

        return '';
    }

    public static function data(mixed $valor): string
    {
        if (is_int($valor)) {
            return date('d/m/Y', $valor);
        }
        $ts = strtotime((string) $valor);

        return $ts === false ? e((string) $valor) : date('d/m/Y', $ts);
    }

    /** @param  list<mixed>  $fontes */
    public static function fontes(array $fontes): string
    {
        $itens = '';
        foreach ($fontes as $f) {
            if (! is_array($f) || empty($f['url'])) {
                continue;
            }
            $itens .= '<li><a href="'.e((string) $f['url']).'" rel="noopener noreferrer">'.e((string) ($f['titulo'] ?? $f['url'])).'</a></li>';
        }

        return $itens === '' ? '' : '<section class="fontes" aria-labelledby="t-fontes"><h2 id="t-fontes">Fontes oficiais</h2><ul>'.$itens.'</ul></section>';
    }
}
