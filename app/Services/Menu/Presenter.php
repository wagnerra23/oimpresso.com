<?php

namespace App\Services\Menu;

/**
 * Presenter — base abstrata pros presenters de menu HTML.
 *
 * Drop-in replacement do Nwidart\Menus\Presenters\Presenter. O
 * AdminlteCustomPresenter do core UltimatePOS estende esta classe e
 * implementa os wrappers HTML da sidebar AdminLTE.
 */
abstract class Presenter
{
    public function getOpenTagWrapper() { return ''; }
    public function getCloseTagWrapper() { return ''; }
    public function getMenuWithoutDropdownWrapper($item) { return ''; }
    public function getDividerWrapper() { return ''; }
    public function getHeaderWrapper($item) { return ''; }
    public function getMenuWithDropDownWrapper($item) { return ''; }
    public function getMultiLevelDropdownWrapper($item) { return ''; }

    // Sem type hint estrito: AdminlteCustomPresenter override sem type hint (compat com nwidart original).
    public function getChildMenuItems($item)
    {
        $results = '';
        foreach ($item->getChilds() as $child) {
            if ($child->hidden()) continue;

            if ($child->hasSubMenu()) {
                $results .= $this->getMultiLevelDropdownWrapper($child);
            } elseif ($child->isHeader()) {
                $results .= $this->getHeaderWrapper($child);
            } elseif ($child->isDivider()) {
                $results .= $this->getDividerWrapper();
            } else {
                $results .= $this->getMenuWithoutDropdownWrapper($child);
            }
        }
        return $results;
    }
}
