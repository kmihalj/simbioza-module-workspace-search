<?php

declare(strict_types=1);

namespace AaiEduHr\SimbiozaModuleWorkspaceSearch\Service;

use AaiEduHr\HeartPhrameModuleMenu\Extension\TopMenuControlProviderInterface;
use HeartPhrame\Localization\TranslatorInterface;
use HeartPhrame\Routing\UrlGenerator;
use Throwable;

/**
 * EN: Adds a compact search form to the top menu's right-hand area.
 *
 * HR: Dodaje kompaktnu pretragu u desnu zonu gornjeg menija.
 */
final readonly class WorkspaceSearchTopMenuControl implements TopMenuControlProviderInterface
{
    /**
     * EN: Initializes the object and its dependencies.
     *
     * HR: Inicijalizira objekt i njegove ovisnosti.
     */
    public function __construct(
        private UrlGenerator $urls,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * EN: Renders the available search form as a standalone `<li>` fragment.
     *
     * HR: Renderira dostupnu tražilicu kao samostalni `<li>` fragment.
     */
    public function render(): string
    {
        try {
            $action = $this->urls->getPathFor('workspace-search.index');
        } catch (Throwable) {
            $action = rtrim($this->urls->getBasePath(), '/') . '/search';
        }

        $label = $this->escape($this->translator->trans('Pretraži područja'));

        return '<li class="nav-item hph-workspace-search-control d-flex align-items-center">'
        . '<form class="d-flex align-items-center" role="search" method="get" action="'
        . $this->escape($action) . '">'
        . '<label class="visually-hidden" for="hph-workspace-search-input">' . $label . '</label>'
        . '<div class="input-group input-group-sm">'
        . '<input id="hph-workspace-search-input" class="form-control" type="search" name="q" '
        . 'placeholder="' . $label . '" autocomplete="off" minlength="2">'
        . '<button class="btn btn-primary" type="submit" title="' . $label . '" aria-label="' . $label . '">'
        . '<span aria-hidden="true">⌕</span></button></div></form></li>';
    }

    /**
     * EN: Safely escapes dynamic HTML attributes and text.
     *
     * HR: Sigurno escapea dinamične HTML atribute i tekst.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
