<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\Ui;

/**
 * @no-named-arguments
 */
class BootstrapMenu extends \Ease\TWB5\Navbar
{
    /**
     * Navigation.
     */
    public ?\Ease\Html\UlTag $nav = null;

    /**
     * Application Main Menu.
     *
     * @param string $name
     * @param mixed  $content
     * @param array  $properties
     */
    public function __construct(
        $name = null,
        $content = null,
        $properties = [],
    ) {
        $this->mainpage = 'main.php';
        parent::__construct(new \Ease\Html\ImgTag('images/project-logo.svg', $name, ['width' => 50, 'height' => 50, 'class' => 'img-rounded d-inline-block align-top']), 'main-menu', ['class' => 'sticky-top '.(\array_key_exists('class', $properties) ? $properties['class'] : '')]);

        // Render the collapsible menu as a left slide-in Offcanvas drawer on
        // small screens (it stays inline on lg+ via navbar-expand-lg).
        $this->offcanvas = true;
        $this->offcanvasPlacement = 'start';

        if (\Ease\Shared::user()->isLogged() === false) {
            // Bootstrap 5 inline form: flex row on the inner form div (2nd arg)
            // instead of the removed BS4 form-inline class
            $loginForm = new \Ease\TWB5\Form(
                ['action' => 'login.php', 'class' => 'my-2 my-lg-0'],
                ['class' => 'd-flex flex-nowrap align-items-center gap-2'],
            );
            $loginForm->addItem(new \Ease\Html\InputTextTag('login', WebPage::getRequestValue('login'), ['class' => 'form-control', 'placeholder' => _('Login')]));
            $loginForm->addItem(new \Ease\Html\InputPasswordTag('password', WebPage::getRequestValue('password'), ['class' => 'form-control', 'placeholder' => _('Password')]));
            $loginForm->addItem(new \Ease\TWB5\SubmitButton(_('Sign In'), 'success text-nowrap', ['title' => _('Sign in to application'), 'id' => 'signinbuttonmenu']));

            // Add CSRF token to form if CSRF protection is enabled

            if (\Ease\Shared::cfg('CSRF_PROTECTION_ENABLED', true) && isset($GLOBALS['csrfProtection'])) {
                $csrfToken = $GLOBALS['csrfProtection']->generateToken();
                $loginForm->addItem(new \Ease\Html\InputHiddenTag('csrf_token', $csrfToken));
            }

            $loginForm->addItem(new \Ease\TWB5\LinkButton('passwordrecovery.php', _('Password recovery'), 'warning text-nowrap', ['title' => _('Recover your password'), 'id' => 'passwordrecoverybuttonmuenu']));
            $this->addMenuItem($loginForm);
            $this->addItem($content);
        }
    }
}
