<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Uniafibro\Conteudo;
use App\Support\Uniafibro\Layout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * "Seus Direitos" e "Guias Práticos" em https://uniafibro.com.br/direitos — páginas geradas a
 * partir de Markdown versionado (resources/uniafibro/direitos/*.md). Sem banco, sem tenant,
 * sem JavaScript. Não é Inertia nem Blade pelos mesmos motivos da home (UniafibroSiteController).
 *
 * Ver memory/requisitos/Site/RUNBOOK-uniafibro.md §6.
 */
class UniafibroConteudoController extends Controller
{
    public function indice(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $grupos = Conteudo::todas();

        $miolo = '';
        if ($q !== '') {
            $achados = Conteudo::buscar($q);
            $itens = '';
            foreach ($achados as $a) {
                $p = $a['pagina'];
                $itens .= '<li><a href="/direitos/'.e($p['slug']).'">'.e((string) $p['meta']['title']).'</a><p>'.e($a['trecho']).'</p></li>';
            }
            $miolo .= '<section class="resultados" aria-live="polite"><h2>Resultado para “'.e($q).'”</h2>'
                .($itens === '' ? '<p>Nada encontrado. Tente outra palavra, como "INSS", "laudo" ou "carteirinha".</p>' : '<ul>'.$itens.'</ul>')
                .'</section>';
        }

        foreach (Conteudo::SECOES as $secao => $nome) {
            $cartoes = '';
            foreach ($grupos[$secao] as $p) {
                $cartoes .= '<li><a class="cartao" href="/direitos/'.e($p['slug']).'"><strong>'.e((string) $p['meta']['title']).'</strong>'
                    .'<span>'.e((string) ($p['meta']['resumo'] ?? '')).'</span></a></li>';
            }
            $miolo .= '<section class="grupo" id="'.e($secao).'" aria-labelledby="t-'.e($secao).'"><h2 id="t-'.e($secao).'">'.e($nome).'</h2>'
                .($cartoes === '' ? '<p>Em preparação.</p>' : '<ul class="cartoes">'.$cartoes.'</ul>')
                .'</section>';
        }

        $corpo = Layout::nav()
            .'<main id="conteudo" class="hub"><h1>Seus direitos</h1>'
            .'<p class="sub">O que a lei garante para quem tem fibromialgia, o que depende de avaliação e onde pedir, com o link da fonte oficial em cada ponto.</p>'
            .Layout::busca($q).Layout::aviso().$miolo.'</main>'.Layout::rodape();

        return $this->html(Layout::documento('Seus direitos', 'Direitos da pessoa com fibromialgia em Santa Catarina, com fonte oficial: SUS, deficiência, carteirinha, INSS, trabalho e como pedir.', '/direitos', $corpo));
    }

    public function pagina(string $slug): Response|RedirectResponse
    {
        if (! Conteudo::existe($slug)) {
            return redirect('https://uniafibro.com.br/direitos');
        }
        $p = Conteudo::pagina($slug);
        $m = $p['meta'];
        $secao = Conteudo::SECOES[$m['secao'] ?? ''] ?? 'Seus Direitos';

        $indice = '';
        foreach ($p['indice'] as $i) {
            $indice .= '<li><a href="#'.e($i['id']).'">'.e($i['titulo']).'</a></li>';
        }
        $indice = $indice === '' ? '' : '<details class="indice" open><summary>Nesta página</summary><ol>'.$indice.'</ol></details>';

        $corpo = Layout::nav()
            .'<div class="pagina"><aside>'.$indice.'</aside><main id="conteudo">'
            .'<p class="trilha"><a href="/direitos">'.e($secao).'</a></p>'
            .'<h1>'.e((string) $m['title']).'</h1>'
            .'<p class="sub">'.e((string) ($m['resumo'] ?? '')).'</p>'
            .'<p class="meta">Revisado em '.Layout::data($m['updated_at'] ?? '').' · <a class="btn-pdf" href="/direitos/'.e($slug).'.pdf">Baixar em PDF</a></p>'
            .Layout::aviso()
            .'<article class="texto">'.$p['html'].'</article>'
            .Layout::fontes((array) ($m['fontes'] ?? []))
            .'</main></div>'.Layout::rodape();

        return $this->html(Layout::documento((string) $m['title'], (string) ($m['resumo'] ?? ''), '/direitos/'.$slug, $corpo));
    }

    public function pdf(string $slug): \Symfony\Component\HttpFoundation\Response
    {
        if (! Conteudo::existe($slug)) {
            return redirect('https://uniafibro.com.br/direitos');
        }
        $p = Conteudo::pagina($slug);
        $m = $p['meta'];
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:12pt;line-height:1.5;color:#222}'
            .'h1{color:#4b2477;font-size:20pt}h2{color:#4b2477;font-size:15pt;margin-top:18pt}'
            .'.aviso{border:1pt solid #6b3fa0;padding:8pt;background:#f3eefa}a{color:#4b2477}'
            .'table{border-collapse:collapse}td,th{border:1pt solid #999;padding:4pt}</style></head><body>'
            .'<p>Uniafibro-SC · uniafibro.com.br/direitos/'.e($slug).'</p>'
            .'<h1>'.e((string) $m['title']).'</h1><p>Revisado em '.Layout::data($m['updated_at'] ?? '').'</p>'
            .Layout::aviso().$p['html'].Layout::fontes((array) ($m['fontes'] ?? [])).'</body></html>';

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("uniafibro-{$slug}.pdf");
    }

    private function html(string $html): Response
    {
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
