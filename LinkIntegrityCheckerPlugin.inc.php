<?php

/**
 * @file plugins/generic/linkIntegrityChecker/LinkIntegrityCheckerPlugin.inc.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 * See the LICENSE file distributed with this plugin for details.
 *
 * @class LinkIntegrityCheckerPlugin
 * @ingroup plugins_generic_linkIntegrityChecker
 *
 * @brief Auditoria de integridade de links e DOIs para artigos publicados
 *  em revistas OJS. Verifica se as URLs de galleys e os DOIs de artigos
 *  publicados ainda resolvem corretamente, apontando quebras e sua causa
 *  provável (404, 403, timeout, erro de servidor, etc).
 *
 * Créditos (CRediT - Contributor Roles Taxonomy):
 *  - Dr. Luis Alcides Brandini De Boni (Periódico Tchê Química):
 *    Conceptualization, Software (original), Validation.
 *  - Bruno Sotto (WebSotto - https://websotto.com.br/):
 *    Software (adaptação/conversão para plugin OJS), Testing.
 *  Ver CREDITS.md para o detalhamento completo.
 */

require_once __DIR__ . '/classes/LinkIntegrityCompat.inc.php';

if (!class_exists('GenericPlugin') && function_exists('import')) {
    import('lib.pkp.classes.plugins.GenericPlugin');
}

if (!class_exists('LinkIntegrityGenericPluginBase', false)) {
    class_alias(
        class_exists('\PKP\plugins\GenericPlugin') ? '\PKP\plugins\GenericPlugin' : 'GenericPlugin',
        'LinkIntegrityGenericPluginBase'
    );
}

class LinkIntegrityCheckerPlugin extends LinkIntegrityGenericPluginBase
{
    /** @var string Página própria do plugin, com handler autorizado */
    const PLUGIN_PAGE = 'linkintegrity';

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled($mainContextId)) {
            LinkIntegrityCompat::hookAdd('LoadHandler', array($this, 'setPageHandler'));
            // Menu lateral do painel, OJS 3.4/3.5. O hook e disparado no
            // fim de PKPTemplateManager::setupBackendPage(), DEPOIS de o
            // menu ter ido para setState(), entao da para ler o array
            // pronto, acrescentar a entrada e devolver.
            LinkIntegrityCompat::hookAdd(
                'TemplateManager::setupBackendPage',
                array($this, 'addBackendMenuItem')
            );

            // Versoes anteriores (3.2/3.3) montavam a pagina de gestao em
            // Smarty e nao conhecem o hook acima; ali o link entra pela
            // lista de paginas de gestao.
            LinkIntegrityCompat::hookAdd(
                'Templates::Manager::Index::ManagementPages',
                array($this, 'addManagementPageLink')
            );
        }

        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return LinkIntegrityCompat::translate('plugins.generic.linkIntegrity.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return LinkIntegrityCompat::translate('plugins.generic.linkIntegrity.description');
    }

    /**
     * Entrega o handler do plugin ao roteador do OJS.
     *
     * IMPORTANTE (mudança de segurança da v0.2): a versão anterior
     * renderizava a página aqui mesmo e chamava exit(). O hook LoadHandler
     * roda antes de qualquer verificação de autorização, então aquele
     * caminho respondia a visitantes não autenticados. Devolvendo um
     * handler, o roteador executa LinkIntegrityHandler::authorize() antes
     * da operação.
     *
     * Assinaturas do hook:
     *  - OJS 3.3:      array(&$page, &$op, &$sourceFile)
     *  - OJS 3.4/3.5:  array(&$page, &$op, &$sourceFile, &$handler)
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function setPageHandler($hookName, $args)
    {
        $page = isset($args[0]) ? $args[0] : null;
        if ($page !== self::PLUGIN_PAGE) {
            return false;
        }

        require_once __DIR__ . '/pages/LinkIntegrityHandler.inc.php';

        // OJS 3.4 / 3.5: o roteador usa a instância devolvida em $args[3].
        if (array_key_exists(3, $args)) {
            $handler = &$args[3];
            $handler = new LinkIntegrityHandler($this);
            return true;
        }

        // OJS 3.3: o roteador instancia a classe nomeada em HANDLER_CLASS.
        if (!defined('HANDLER_CLASS')) {
            define('HANDLER_CLASS', 'LinkIntegrityHandler');
        }
        return true;
    }

    /**
     * Botão na grade de plugins, apontando para a página de auditoria.
     * É o caminho de acesso mais estável entre versões do OJS, já que os
     * hooks de menu de gerenciamento variam bastante entre 3.3 e 3.5.
     *
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);

        if (!$this->getEnabled()) {
            return $actions;
        }

        $url = $this->getPluginPageUrl($request);
        if (!$url) {
            return $actions;
        }

        $action = LinkIntegrityCompat::newRedirectAction(
            $request,
            'linkIntegrityOpen',
            $url,
            LinkIntegrityCompat::translate('plugins.generic.linkIntegrity.openDashboard')
        );

        if ($action) {
            array_unshift($actions, $action);
        }

        return $actions;
    }

    /**
     * Acrescenta o painel ao menu lateral do OJS 3.4/3.5.
     *
     * @param string $hookName
     * @param array $args
     * @return bool false para nao interromper os demais handlers do hook
     */
    public function addBackendMenuItem($hookName, $args)
    {
        $request = LinkIntegrityCompat::request();
        $templateMgr = LinkIntegrityCompat::templateManager($request);
        if (!$templateMgr || !method_exists($templateMgr, 'getState')) {
            return false;
        }

        $menu = $templateMgr->getState('menu');
        if (!is_array($menu) || empty($menu)) {
            // Fora de uma pagina de painel — nada a fazer.
            return false;
        }

        $url = $this->getPluginPageUrl($request);
        if (!$url) {
            return false;
        }

        $isCurrent = false;
        $router = $request ? $request->getRouter() : null;
        if ($router && method_exists($router, 'getRequestedPage')) {
            $isCurrent = $router->getRequestedPage($request) === self::PLUGIN_PAGE;
        }

        // Entra logo depois de "Ferramentas", que e onde o editor procura
        // utilitarios; se nao houver, vai para o fim.
        $item = array(
            'name' => $this->getDisplayName(),
            'url' => $url,
            'isCurrent' => $isCurrent,
            'icon' => 'Tools',
        );

        $rebuilt = array();
        $placed = false;
        foreach ($menu as $key => $value) {
            $rebuilt[$key] = $value;
            if ($key === 'tools') {
                $rebuilt['linkIntegrity'] = $item;
                $placed = true;
            }
        }
        if (!$placed) {
            $rebuilt['linkIntegrity'] = $item;
        }

        $templateMgr->setState(array('menu' => $rebuilt));

        return false;
    }

    /**
     * Adiciona o link do plugin ao menu de gerenciamento da revista, quando
     * a versão do OJS ainda expõe esse ponto de extensão.
     *
     * @param string $hookName
     * @param array $params
     * @return bool
     */
    public function addManagementPageLink($hookName, $params)
    {
        if (!isset($params[2])) {
            return false;
        }
        $output = &$params[2];

        $request = LinkIntegrityCompat::request();
        $url = $this->getPluginPageUrl($request);
        if (!$url) {
            return false;
        }

        $output .= '<li class="pkp_management_link">'
            . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($this->getDisplayName(), ENT_QUOTES, 'UTF-8')
            . '</a></li>';

        return false;
    }

    /**
     * @param object|null $request
     * @return string|null
     */
    private function getPluginPageUrl($request)
    {
        if (!$request) {
            $request = LinkIntegrityCompat::request();
        }
        if (!$request) {
            return null;
        }

        $context = $request->getContext();
        if (!$context) {
            return null;
        }

        return $request->getDispatcher()->url(
            $request,
            LinkIntegrityCompat::routePage(),
            $context->getPath(),
            self::PLUGIN_PAGE
        );
    }
}
