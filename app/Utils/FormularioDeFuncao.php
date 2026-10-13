<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Lê o formulário de papel que a Blade `role/create|edit` renderizou e devolve os controles em
 * blocos — o editor React de Funções (thread sistema/playbook/02, F3-2) desenha a partir disto.
 *
 * POR QUE ler o HTML renderizado em vez de manter um catálogo próprio: o formulário tem os 153
 * controles do núcleo, condições por módulo ativo (`$enabled_modules`), por configuração do negócio
 * (`common_settings`/`pos_settings`), os grupos de preço do negócio e as permissões que cada módulo
 * declara em `user_permissions()`. Um segundo catálogo escrito à mão divergiria do primeiro, e o
 * `update()` faz `syncPermissions` destrutivo: controle a mais ou a menos vira concessão ou revogação.
 * Lendo o que a Blade renderizou, o React oferece exatamente os mesmos controles, com os mesmos
 * rótulos e os mesmos marcados — e manda o mesmo POST (`permissions[]`, `spg_permissions[]`,
 * `radio_option[<grupo>]`, `is_service_staff`).
 *
 * Quando a Blade sair (F5), o catálogo precisa de outro dono; até lá ela é a fonte, como já é a
 * fonte do `PermissionCatalog::CORE`.
 */
class FormularioDeFuncao
{
    /** Campos do formulário que viram permissão (ou o flag de garçom). O resto é ignorado. */
    private const CAMPOS = '/^(permissions\[\]|spg_permissions\[\]|radio_option\[([^\]]+)\]|is_service_staff)$/';

    /**
     * @return array{nome: string, grupos: array<int, array{titulo: string, itens: array<int, array<string, mixed>>}>}
     */
    public static function extrair(string $html, string $idDoForm): array
    {
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xp = new \DOMXPath($dom);

        $form = $xp->query("//form[@id='{$idDoForm}']")->item(0);
        if (! $form instanceof \DOMElement) {
            throw new \RuntimeException("Formulário #{$idDoForm} não encontrado no HTML da Blade.");
        }

        $nome = '';
        $grupos = [];
        $titulo = '';

        // União de XPath sai em ordem de documento: cada controle cai sob o último <h4> antes dele.
        foreach ($xp->query('.//h4 | .//input', $form) as $no) {
            /** @var \DOMElement $no */
            if ($no->nodeName === 'h4') {
                $titulo = self::texto($no->textContent);

                continue;
            }

            $campo = $no->getAttribute('name');
            if ($campo === 'name') {
                $nome = $no->getAttribute('value');

                continue;
            }
            if (preg_match(self::CAMPOS, $campo, $m) !== 1) {
                continue;
            }

            $item = [
                'campo' => $campo,
                'tipo' => $no->getAttribute('type') === 'radio' ? 'radio' : 'checkbox',
                'valor' => $no->getAttribute('value'),
                'rotulo' => self::rotulo($no),
                'marcado' => $no->hasAttribute('checked'),
            ];

            $chave = count($grupos) - 1;
            if ($chave < 0 || $grupos[$chave]['titulo'] !== $titulo) {
                $grupos[] = ['titulo' => $titulo, 'itens' => []];
                $chave++;
            }
            $grupos[$chave]['itens'][] = $item;
        }

        return ['nome' => $nome, 'grupos' => $grupos];
    }

    /** Texto do <label> que envolve o controle (é assim que a Blade rotula todos). */
    private static function rotulo(\DOMElement $input): string
    {
        for ($pai = $input->parentNode; $pai instanceof \DOMElement; $pai = $pai->parentNode) {
            if ($pai->nodeName === 'label') {
                return self::texto($pai->textContent);
            }
        }

        return $input->getAttribute('value');
    }

    private static function texto(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }
}
