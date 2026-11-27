<?php

/**
 * @file plugins/generic/linkIntegrityChecker/classes/LinkIntegrityCompat.inc.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 *
 * @class LinkIntegrityCompat
 * @ingroup plugins_generic_linkIntegrityChecker
 *
 * @brief Camada fina de compatibilidade entre OJS 3.3, 3.4 e 3.5.
 *
 *  O OJS 3.4 introduziu classes com namespace (\PKP\..., \APP\...) e o 3.5
 *  removeu boa parte dos aliases globais e das constantes legadas que ainda
 *  existiam no 3.3/3.4. Em vez de espalhar verificações pelo código, todas
 *  as diferenças ficam concentradas aqui: cada método tenta primeiro a API
 *  nova (namespaced) e cai para a legada quando ela não existe.
 *
 *  Nenhum método aqui lança exceção por API ausente: quem chama decide o
 *  que fazer com um retorno nulo.
 */

class LinkIntegrityCompat
{
    /**
     * Registra um callback num hook do OJS.
     *
     * @param string $name
     * @param callable $callback
     */
    public static function hookAdd($name, $callback)
    {
        if (class_exists('\PKP\plugins\Hook')) {
            \PKP\plugins\Hook::add($name, $callback);
            return;
        }
        if (class_exists('HookRegistry')) {
            HookRegistry::register($name, $callback);
        }
    }

    /**
     * @return string Nome da classe Application em uso
     */
    public static function applicationClass()
    {
        return class_exists('\APP\core\Application') ? '\APP\core\Application' : 'Application';
    }

    /**
     * @return object|null Request atual
     */
    public static function request()
    {
        $cls = self::applicationClass();
        if (!class_exists($cls)) {
            return null;
        }
        return $cls::get()->getRequest();
    }

    /**
     * @param object $request
     * @return object|null TemplateManager
     */
    public static function templateManager($request)
    {
        $cls = class_exists('\APP\template\TemplateManager')
            ? '\APP\template\TemplateManager'
            : 'TemplateManager';
        if (!class_exists($cls)) {
            return null;
        }
        return $cls::getManager($request);
    }

    /**
     * @return string Valor da rota de página ('page')
     */
    public static function routePage()
    {
        if (class_exists('\PKP\core\PKPApplication')
            && defined('\PKP\core\PKPApplication::ROUTE_PAGE')) {
            return \PKP\core\PKPApplication::ROUTE_PAGE;
        }
        if (defined('ROUTE_PAGE')) {
            return ROUTE_PAGE;
        }
        return 'page';
    }

    /**
     * @return int Código de status de submissão publicada
     */
    public static function statusPublished()
    {
        if (class_exists('\PKP\submission\PKPSubmission')
            && defined('\PKP\submission\PKPSubmission::STATUS_PUBLISHED')) {
            return \PKP\submission\PKPSubmission::STATUS_PUBLISHED;
        }
        if (defined('STATUS_PUBLISHED')) {
            return STATUS_PUBLISHED;
        }
        return 3;
    }

    /**
     * Papéis autorizados a usar o plugin: Gerente da Revista e
     * Administrador do site.
     *
     * @return int[]
     */
    public static function managerRoles()
    {
        if (class_exists('\PKP\security\Role')) {
            return array(
                \PKP\security\Role::ROLE_ID_MANAGER,
                \PKP\security\Role::ROLE_ID_SITE_ADMIN,
            );
        }
        if (defined('ROLE_ID_MANAGER') && defined('ROLE_ID_SITE_ADMIN')) {
            return array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN);
        }
        // Valores estáveis desde o OJS 3.0 (0x10 e 0x01).
        return array(16, 1);
    }

    /**
     * Instancia a política de acesso ao contexto (revista), que é o que
     * efetivamente exige login e papel adequado.
     *
     * @param object $request
     * @param array $roleAssignments
     * @return object|null
     */
    public static function newContextAccessPolicy($request, $roleAssignments)
    {
        if (class_exists('\PKP\security\authorization\ContextAccessPolicy')) {
            return new \PKP\security\authorization\ContextAccessPolicy($request, $roleAssignments);
        }
        if (!class_exists('ContextAccessPolicy') && function_exists('import')) {
            import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
        }
        if (class_exists('ContextAccessPolicy')) {
            return new ContextAccessPolicy($request, $roleAssignments);
        }
        return null;
    }

    /**
     * @return string Nome da classe base de Handler de página
     */
    public static function handlerBaseClass()
    {
        if (class_exists('\APP\handler\Handler')) {
            return '\APP\handler\Handler';
        }
        if (!class_exists('Handler') && function_exists('import')) {
            import('classes.handler.Handler');
        }
        return 'Handler';
    }

    /**
     * Cria um LinkAction que redireciona para uma URL, usado para expor a
     * página do plugin na grade de plugins. Retorna null se as classes não
     * estiverem disponíveis (o plugin continua funcionando pela URL direta).
     *
     * @param object $request
     * @param string $id
     * @param string $url
     * @param string $label
     * @return object|null
     */
    public static function newRedirectAction($request, $id, $url, $label)
    {
        $linkActionClass = null;
        $redirectClass = null;

        if (class_exists('\PKP\linkAction\LinkAction')
            && class_exists('\PKP\linkAction\request\RedirectAction')) {
            $linkActionClass = '\PKP\linkAction\LinkAction';
            $redirectClass = '\PKP\linkAction\request\RedirectAction';
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.linkAction.LinkAction');
            import('lib.pkp.classes.linkAction.request.RedirectAction');
            if (class_exists('LinkAction') && class_exists('RedirectAction')) {
                $linkActionClass = 'LinkAction';
                $redirectClass = 'RedirectAction';
            }
        }

        if (!$linkActionClass) {
            return null;
        }

        return new $linkActionClass($id, new $redirectClass($url), $label, null);
    }

    /**
     * Recupera o token CSRF da sessão atual.
     *
     * O OJS 3.3/3.4 expõe Session::getCSRFToken(); o 3.5 usa a sessão do
     * Laravel, cujo método equivalente é token(). Retorna null quando
     * nenhum dos dois está disponível.
     *
     * @param object $request
     * @return string|null
     */
    public static function csrfToken($request)
    {
        if (!is_object($request) || !method_exists($request, 'getSession')) {
            return null;
        }
        try {
            $session = $request->getSession();
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_object($session)) {
            return null;
        }
        if (method_exists($session, 'getCSRFToken')) {
            $token = $session->getCSRFToken();
            return $token ? (string) $token : null;
        }
        if (method_exists($session, 'token')) {
            $token = $session->token();
            return $token ? (string) $token : null;
        }
        return null;
    }

    /**
     * Locale corrente do OJS, usado como padrão do plugin.
     *
     * @return string|null
     */
    public static function currentLocale()
    {
        if (class_exists('\\PKP\\facades\\Locale')) {
            try {
                return (string) \PKP\facades\Locale::getLocale();
            } catch (\Throwable $e) {
                // Cai no próximo caminho.
            }
        }
        if (class_exists('AppLocale') && method_exists('AppLocale', 'getLocale')) {
            return (string) AppLocale::getLocale();
        }
        return null;
    }

    /**
     * Traduz uma chave de locale, tolerando ausência da função global.
     *
     * @param string $key
     * @return string
     */
    public static function translate($key)
    {
        if (function_exists('__')) {
            return __($key);
        }
        return $key;
    }

    /**
     * Idiomas que a revista (ou o site) tem habilitados, com seus nomes.
     *
     * A lista vem do OJS, e nao de uma relacao fixa no plugin: assim um
     * idioma novo habilitado na revista aparece sozinho, e um idioma que a
     * revista nao usa nao aparece.
     *
     * @param object|null $context
     * @return array mapa locale => nome do idioma
     */
    public static function supportedLocales($context)
    {
        foreach (array('getSupportedLocaleNames', 'getSupportedFormLocaleNames') as $method) {
            if ($context && method_exists($context, $method)) {
                try {
                    $names = $context->$method();
                    if (is_array($names) && $names) {
                        return $names;
                    }
                } catch (\Throwable $e) {
                    // Tenta o proximo caminho.
                }
            }
        }

        if (class_exists('\\PKP\\facades\\Locale')) {
            try {
                $all = \PKP\facades\Locale::getLocales();
                $out = array();
                foreach ((array) $all as $code => $meta) {
                    $out[$code] = is_object($meta) && method_exists($meta, 'getDisplayName')
                        ? $meta->getDisplayName()
                        : (string) $code;
                }
                if ($out) {
                    return $out;
                }
            } catch (\Throwable $e) {
                // Cai no retorno vazio.
            }
        }

        return array();
    }

    /**
     * Instancia registrada do plugin. Usada quando o roteador do OJS 3.3
     * cria o handler pelo nome da classe, sem passar o plugin.
     *
     * @return object|null
     */
    public static function plugin()
    {
        $cls = class_exists('\PKP\plugins\PluginRegistry') ? '\PKP\plugins\PluginRegistry' : 'PluginRegistry';
        if (!class_exists($cls)) {
            return null;
        }
        $plugin = $cls::getPlugin('generic', 'linkintegritycheckerplugin');
        return $plugin ? $plugin : null;
    }

    /**
     * Responde 404 pela via de cada versao: o 3.3/3.4 usam handle404(); o 3.5,
     * cujo Dispatcher nao tem mais esse metodo, usa a excecao do Symfony.
     *
     * @param object|null $request
     */
    public static function notFound($request = null)
    {
        $dispatcher = $request && method_exists($request, 'getDispatcher') ? $request->getDispatcher() : null;
        if ($dispatcher && method_exists($dispatcher, 'handle404')) {
            $dispatcher->handle404();
            return;
        }
        if (class_exists('\\Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException')) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        http_response_code(404);
        exit;
    }
}
