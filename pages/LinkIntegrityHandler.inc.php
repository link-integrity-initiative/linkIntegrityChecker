<?php

/**
 * @file plugins/generic/linkIntegrityChecker/pages/LinkIntegrityHandler.inc.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 *
 * @class LinkIntegrityHandler
 * @ingroup plugins_generic_linkIntegrityChecker
 *
 * @brief Handler das páginas do plugin.
 *
 *  Ponto central da correção de segurança da versão 0.2: na 0.1 o plugin
 *  renderizava a página e o endpoint direto do hook LoadHandler, com um
 *  exit() no final. Isso acontece ANTES do OJS instanciar e autorizar
 *  qualquer handler, ou seja, o comentário no código dizendo que a
 *  permissão era "verificada pelo próprio handler manager" estava errado:
 *  nenhum handler chegava a rodar, e o endpoint respondia a qualquer
 *  visitante anônimo.
 *
 *  Agora o plugin devolve ESTE handler ao roteador, que executa authorize()
 *  antes da operação. O acesso passa por ContextAccessPolicy com os papéis
 *  de Gerente da Revista e Administrador do site, exatamente como as demais
 *  páginas administrativas do OJS.
 */

require_once dirname(__DIR__) . '/classes/LinkIntegrityCompat.inc.php';
require_once dirname(__DIR__) . '/classes/LinkIntegrityService.inc.php';
require_once dirname(__DIR__) . '/classes/LinkIntegrityLocale.inc.php';
require_once dirname(__DIR__) . '/classes/LinkIntegrityMarkdown.inc.php';

if (!class_exists('LinkIntegrityHandlerBase', false)) {
    class_alias(LinkIntegrityCompat::handlerBaseClass(), 'LinkIntegrityHandlerBase');
}

class LinkIntegrityHandler extends LinkIntegrityHandlerBase
{
    /** @var string Serviço de versões mantido pelo Periódico Tchê Química */
    /** Última release publicada no GitHub (API pública, sem token). */
    const UPDATE_ENDPOINT = 'https://api.github.com/repos/link-integrity-initiative/linkIntegrityChecker/releases/latest';

    /** Página das releases, para quando a release não traz o pacote. */
    const RELEASES_PAGE = 'https://github.com/link-integrity-initiative/linkIntegrityChecker/releases';

    /** Bandeiras em SVG embutido; ver localeOptions() para o porquê */
    const FLAG_BR = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#009b3a"/>'
        . '<path d="M14 2.4 25.6 10 14 17.6 2.4 10z" fill="#fedf00"/>'
        . '<circle cx="14" cy="10" r="4.4" fill="#002776"/>'
        . '<path d="M9.8 8.6a9 9 0 0 1 8.5 2.1" stroke="#fff" stroke-width="1.1" fill="none"/></svg>';

    const FLAG_US = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#fff"/>'
        . '<g fill="#b22234"><rect width="28" height="1.54" y="0"/><rect width="28" height="1.54" y="3.08"/>'
        . '<rect width="28" height="1.54" y="6.15"/><rect width="28" height="1.54" y="9.23"/>'
        . '<rect width="28" height="1.54" y="12.3"/><rect width="28" height="1.54" y="15.38"/>'
        . '<rect width="28" height="1.54" y="18.46"/></g>'
        . '<rect width="12" height="10.77" fill="#3c3b6e"/></svg>';

    const FLAG_ES = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#c60b1e"/>'
        . '<rect width="28" height="10" y="5" fill="#ffc400"/></svg>';

    const FLAG_PT = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#da291c"/>'
        . '<rect width="11.2" height="20" fill="#046a38"/>'
        . '<circle cx="11.2" cy="10" r="4" fill="#ffe600" stroke="#fff" stroke-width="0.8"/></svg>';

    const FLAG_FR = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#fff"/>'
        . '<rect width="9.34" height="20" fill="#002395"/>'
        . '<rect width="9.34" height="20" x="18.66" fill="#ed2939"/></svg>';

    const FLAG_CA = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#fff"/>'
        . '<rect width="7" height="20" fill="#d80621"/>'
        . '<rect width="7" height="20" x="21" fill="#d80621"/>'
        . '<path d="M14 5l1.5 3.2 3-1-1 3 2.5 1.4-2.5 1.4.4 1.6-2.9-.5-.3 3h-1.4l-.3-3-2.9.5.4-1.6L8 11.6l2.5-1.4-1-3 3 1z" fill="#d80621"/></svg>';

    const FLAG_DE = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#000"/>'
        . '<rect width="28" height="6.67" y="6.67" fill="#dd0000"/>'
        . '<rect width="28" height="6.66" y="13.34" fill="#ffce00"/></svg>';

    const FLAG_ID = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#fff"/>'
        . '<rect width="28" height="10" fill="#ce1126"/></svg>';

    const FLAG_TR = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#e30a17"/>'
        . '<circle cx="11" cy="10" r="5" fill="#fff"/>'
        . '<circle cx="12.6" cy="10" r="4" fill="#e30a17"/>'
        . '<path d="M17.6 10l3.6-1.2-2.2 3.1V8.1l2.2 3.1z" fill="#fff"/></svg>';

    const FLAG_IT = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#fff"/>'
        . '<rect width="9.34" height="20" fill="#009246"/>'
        . '<rect width="9.34" height="20" x="18.66" fill="#ce2b37"/></svg>';

    const FLAG_GB = '<svg viewBox="0 0 28 20" width="20" height="14" aria-hidden="true">'
        . '<rect width="28" height="20" fill="#012169"/>'
        . '<path d="M0 0l28 20M28 0L0 20" stroke="#fff" stroke-width="4"/>'
        . '<path d="M14 0v20M0 10h28" stroke="#fff" stroke-width="6.5"/>'
        . '<path d="M14 0v20M0 10h28" stroke="#c8102e" stroke-width="3.5"/></svg>';

    /** @var object|null Instância do plugin */
    private $plugin = null;

    /**
     * @param object|null $plugin
     */
    public function __construct($plugin = null)
    {
        parent::__construct();
        // No OJS 3.3 o roteador instancia o handler pelo nome (HANDLER_CLASS),
        // sem passar o plugin; sem ele nao ha configuracoes nem templates.
        $this->plugin = $plugin ? $plugin : LinkIntegrityCompat::plugin();
        $this->addRoleAssignment(
            LinkIntegrityCompat::managerRoles(),
            array('index', 'test', 'checkUpdate', 'saveSummary', 'saveSettings')
        );
    }

    /**
     * Exige sessão autenticada com papel de gerente/administrador no
     * contexto da revista.
     *
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // A pagina publica de transparencia nao passa pela politica de
        // acesso do painel: ela existe justamente para o leitor. Nao expoe
        // lista de artigos nem permite disparar verificacao — so mostra o
        // resumo ja calculado pelo editor.
        if ($request->getRequestedOp() === 'status') {
            // Sem isto o PKPHandler nega o acesso: o handler tem papeis
            // atribuidos (para as demais operacoes) e nenhuma politica os
            // conferiu nesta requisicao.
            if (method_exists($this, 'markRoleAssignmentsChecked')) {
                $this->markRoleAssignmentsChecked();
            }
            return parent::authorize($request, $args, $roleAssignments);
        }

        $policy = LinkIntegrityCompat::newContextAccessPolicy($request, $roleAssignments);
        if ($policy) {
            $this->addPolicy($policy);
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Dashboard de auditoria.
     *
     * @param array $args
     * @param object $request
     */
    public function index($args, $request)
    {
        $this->setupTemplate($request);

        $context = $request->getContext();
        if (!$context) {
            LinkIntegrityCompat::notFound($request);
            return;
        }

        $service = new LinkIntegrityService($this->selfHosts($request), $this->selfPorts($request));
        $articles = $service->getPublishedArticles($context->getId());

        $jsArticles = array_map(function ($a) {
            return array(
                'id' => $a['id'],
                'titulo' => $a['titulo'],
                'doi' => $a['doi'] ? $a['doi'] : '',
                'link' => !empty($a['links']) ? $a['links'][0] : '',
            );
        }, $articles);

        $ajaxUrl = $request->getDispatcher()->url(
            $request,
            LinkIntegrityCompat::routePage(),
            $context->getPath(),
            'linkintegrity',
            'test'
        );

        // Idioma do plugin: independente do idioma do painel, a pedido dos
        // editores que revisam a revista em mais de uma língua. Validado
        // contra a lista de .po existentes, nunca usado direto no caminho.
        $locale = $this->resolveLocale($request);

        $templateMgr = LinkIntegrityCompat::templateManager($request);
        if (method_exists($templateMgr, 'setupBackendPage')) {
            $templateMgr->setupBackendPage();
        }

        // CSS e JS vao no <head> pelo OJS. Embutidos no corpo da pagina eles
        // seriam descartados: o OJS 3.5 monta o Vue sobre #app e o compilador
        // ignora <style> e <script> dentro do template do componente.
        if ($this->plugin && method_exists($templateMgr, 'addStyleSheet')) {
            $base = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath();
            // Versão do plugin na URL: sem ela o OJS acrescenta a versão do
            // próprio OJS, e o navegador seguia com o JS antigo do cache
            // depois de atualizar o plugin.
            $bust = '?v=' . rawurlencode($this->pluginVersion());
            $templateMgr->addStyleSheet(
                'linkIntegrity',
                $base . '/styles/linkIntegrity.css' . $bust,
                array('contexts' => 'backend')
            );
            $templateMgr->addJavaScript(
                'linkIntegrity',
                $base . '/js/linkIntegrity.js' . $bust,
                array('contexts' => 'backend')
            );
        }

        $templateMgr->assign(array(
            'articles' => $articles,
            'articlesJson' => json_encode($jsArticles, JSON_UNESCAPED_UNICODE),
            'ajaxUrl' => $ajaxUrl,
            'stringsJson' => json_encode($this->clientStrings($locale), JSON_UNESCAPED_UNICODE),
            'licCsrf' => (string) LinkIntegrityCompat::csrfToken($request),
            'pageTitle' => __('plugins.generic.linkIntegrity.displayName'),
            'licVersion' => $this->pluginVersion(),
            'licHistoryHtml' => LinkIntegrityMarkdown::render($this->changelog()),
            's' => LinkIntegrityLocale::strings($this->localeDir(), $locale),
            'licLocale' => $locale,
            'licLocales' => $this->localeOptions($request, $locale),
            'licPublicPage' => $context && $this->plugin
                ? (bool) $this->plugin->getSetting($context->getId(), 'publicPage')
                : false,
            'licPublicFooter' => $context && $this->plugin
                ? (bool) $this->plugin->getSetting($context->getId(), 'publicFooter')
                : false,
            'licSettingsUrl' => $request->getDispatcher()->url(
                $request,
                LinkIntegrityCompat::routePage(),
                $context->getPath(),
                'linkintegrity',
                'saveSettings'
            ),
            'licSummaryUrl' => $request->getDispatcher()->url(
                $request,
                LinkIntegrityCompat::routePage(),
                $context->getPath(),
                'linkintegrity',
                'saveSummary'
            ),
            'licStatusUrl' => $request->getDispatcher()->url(
                $request,
                LinkIntegrityCompat::routePage(),
                $context->getPath(),
                'linkintegrity',
                'status'
            ),
            'licUpdateUrl' => $request->getDispatcher()->url(
                $request,
                LinkIntegrityCompat::routePage(),
                $context->getPath(),
                'linkintegrity',
                'checkUpdate'
            ),
        ));

        $template = $this->plugin
            ? $this->plugin->getTemplateResource('index.tpl')
            : 'index.tpl';
        $templateMgr->display($template);
    }

    /**
     * Versão declarada em version.xml, para exibir no rodapé.
     *
     * @return string
     */
    private function pluginVersion()
    {
        if (!$this->plugin || !method_exists($this->plugin, 'getCurrentVersion')) {
            return '';
        }
        $v = $this->plugin->getCurrentVersion();
        return $v ? (string) $v->getVersionString(false) : '';
    }

    /**
     * Conteúdo do CHANGELOG.md, exibido no rodapé da interface.
     *
     * Ler o arquivo em vez de duplicar o histórico em constantes mantém
     * uma única fonte da verdade: o que está no repositório é o que o
     * gerente da revista vê na tela.
     *
     * @return string
     */
    private function changelog()
    {
        $file = dirname(__DIR__) . '/CHANGELOG.md';
        if (!is_readable($file)) {
            return '';
        }
        $txt = file_get_contents($file);
        return $txt === false ? '' : $txt;
    }

    /**
     * Idioma a usar nesta requisição.
     *
     * O padrão é sempre o idioma corrente do OJS. Só muda se a pessoa
     * escolher outro na barra, e apenas para um idioma que a revista tenha
     * habilitado — o valor vindo da URL nunca é usado direto.
     *
     * @param object $request
     * @return string|null
     */
    private function resolveLocale($request)
    {
        $current = LinkIntegrityCompat::currentLocale();
        $wanted = $request->getUserVar('licLocale');
        if (!$wanted || $wanted === $current) {
            return $current;
        }

        $supported = LinkIntegrityCompat::supportedLocales($request->getContext());
        if ($supported && !array_key_exists($wanted, $supported)) {
            return $current;
        }

        // Fora do idioma corrente, os textos vêm dos .po do pacote; se o
        // plugin não traz esse idioma, não há o que exibir e fica o atual.
        return LinkIntegrityLocale::resolve($this->localeDir(), $wanted, $current);
    }

    /**
     * @return string
     */
    private function localeDir()
    {
        return dirname(__DIR__) . '/locale';
    }

    /**
     * Bandeiras e rótulos para a troca de idioma.
     *
     * A bandeira é um atalho visual, não um identificador de idioma: o
     * rótulo textual ao lado é o que de fato identifica a língua, já que
     * um mesmo idioma é falado em muitos países.
     *
     * As bandeiras são SVG embutido, e não emoji: o Windows não traz fonte
     * para os caracteres indicadores regionais, e o emoji de bandeira
     * aparece lá como duas letras soltas.
     *
     * @param object $request
     * @param string $current
     * @return array
     */
    private function localeOptions($request, $current)
    {
        // Bandeiras desenhadas para os idiomas mais comuns nesta base. Um
        // idioma sem bandeira aqui nao fica de fora: aparece so com o nome,
        // que e o que de fato identifica a lingua.
        $flags = array(
            'pt_BR' => self::FLAG_BR,
            'pt_PT' => self::FLAG_PT,
            'en_US' => self::FLAG_US,
            'en_GB' => self::FLAG_GB,
            'es_ES' => self::FLAG_ES,
            'fr_FR' => self::FLAG_FR,
            'fr_CA' => self::FLAG_CA,
            'de_DE' => self::FLAG_DE,
            'id_ID' => self::FLAG_ID,
            'tr_TR' => self::FLAG_TR,
            'it_IT' => self::FLAG_IT,
            // Codigos do OJS 3.4+, sem regiao para a maioria dos idiomas.
            'en' => self::FLAG_US,
            'es' => self::FLAG_ES,
            'pt' => self::FLAG_PT,
            'fr' => self::FLAG_FR,
            'de' => self::FLAG_DE,
            'it' => self::FLAG_IT,
            'id' => self::FLAG_ID,
            'tr' => self::FLAG_TR,
        );

        // A lista sai dos idiomas que a REVISTA tem habilitados, e nao de
        // uma relacao fixa no plugin. Um idioma novo habilitado na revista
        // — ou uma traducao que chegue pelo Weblate da PKP — aparece
        // sozinho; um idioma que a revista nao usa nao aparece.
        $supported = LinkIntegrityCompat::supportedLocales($request->getContext());

        // Sem essa informacao, resta o que o pacote traz.
        if (!$supported) {
            foreach (LinkIntegrityLocale::available($this->localeDir()) as $code) {
                $supported[$code] = $code;
            }
        }

        // Um unico idioma habilitado torna a barra inutil.
        if (count($supported) < 2) {
            return array();
        }

        $out = array();
        foreach ($supported as $code => $name) {
            $out[] = array(
                'code' => $code,
                'flag' => isset($flags[$code]) ? $flags[$code] : '',
                'label' => $name !== '' ? $name : $code,
                'current' => $code === $current,
                'url' => $request->getRequestUrl() . '?licLocale=' . urlencode($code),
            );
        }
        return $out;
    }

    /**
     * Consulta a última release do plugin no GitHub.
     *
     * A consulta é feita AQUI, no servidor, e não no navegador, e a URL é
     * constante, não vem do usuário. Desde a 0.5.4 nada sobre a revista é
     * enviado: é um GET anônimo à API pública do GitHub. O IP registrado
     * do outro lado é o do servidor da revista.
     *
     * @param array $args
     * @param object $request
     */
    public function checkUpdate($args, $request)
    {
        if (!$this->jsonGuard($request)) {
            return;
        }

        $current = $this->pluginVersion();

        $body = $this->httpGet(self::UPDATE_ENDPOINT);
        if ($body === null) {
            echo json_encode(array('error' => 'unreachable', 'current' => $current));
            return;
        }

        $data = json_decode($body, true);
        $tag = is_array($data) && isset($data['tag_name']) ? (string) $data['tag_name'] : '';
        $latest = $this->normalizeVersion($tag);
        if ($latest === '') {
            echo json_encode(array('error' => 'bad_response', 'current' => $current));
            return;
        }

        // Pacote instalável anexado à release; sem ele, a página da release.
        $download = '';
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                $name = isset($asset['name']) ? (string) $asset['name'] : '';
                $link = isset($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';
                if (preg_match('/\.tar\.gz$/i', $name) && preg_match('#^https://github\.com/#i', $link)) {
                    $download = $link;
                    break;
                }
            }
        }
        if ($download === '') {
            $page = isset($data['html_url']) ? (string) $data['html_url'] : '';
            $download = preg_match('#^https://github\.com/#i', $page) ? $page : self::RELEASES_PAGE;
        }

        echo json_encode(array(
            'current' => $current,
            'latest' => $latest,
            'outdated' => $current !== ''
                && version_compare($latest, $this->normalizeVersion($current), '>'),
            'download' => $download,
        ));
    }

    /**
     * Versão em quatro partes (0.5.4 → 0.5.4.0; v0.5.4.0 → 0.5.4.0), para
     * comparar tag e version.xml sem "0.5.4.0" > "0.5.4" dar verdadeiro.
     *
     * @param string $version
     * @return string '' se não for uma versão numérica
     */
    private function normalizeVersion($version)
    {
        $version = ltrim(trim((string) $version), 'vV');
        if (!preg_match('/^\d+(\.\d+){0,3}$/', $version)) {
            return '';
        }
        $parts = array_map('intval', explode('.', $version));
        while (count($parts) < 4) {
            $parts[] = 0;
        }
        return implode('.', $parts);
    }

    /**
     * GET simples, só para o endpoint de versões.
     *
     * @param string $url
     * @return string|null corpo da resposta, ou null em qualquer falha
     */
    private function httpGet($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            // Só https, também nos redirecionamentos: sem rebaixar para
            // http nem seguir para outro protocolo.
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // A API do GitHub recusa requisições sem User-Agent.
            CURLOPT_USERAGENT => 'LinkIntegrityChecker/' . $this->pluginVersion(),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
            ),
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
    }

    /**
     * Endpoint que testa um único artigo (DOI + link).
     *
     * Aceita apenas POST e exige o token CSRF da sessão. Isso impede que um
     * terceiro induza o navegador de um gerente logado a disparar as
     * requisições de saída do servidor (CSRF encadeado com SSRF).
     *
     * @param array $args
     * @param object $request
     */
    public function test($args, $request)
    {
        if (!$this->jsonGuard($request)) {
            return;
        }

        $context = $request->getContext();
        if (!$context) {
            http_response_code(400);
            echo json_encode(array('error' => 'invalid_context'));
            return;
        }

        $submissionId = (int) $request->getUserVar('id');
        if ($submissionId <= 0) {
            http_response_code(400);
            echo json_encode(array('error' => 'missing_id'));
            return;
        }

        $service = new LinkIntegrityService($this->selfHosts($request), $this->selfPorts($request));
        $article = $service->getArticleById($submissionId, $context->getId());

        if (!$article) {
            http_response_code(404);
            echo json_encode(array('error' => 'not_found'));
            return;
        }

        $result = array(
            'doi' => array('atencao' => true),
            'link' => array('atencao' => true),
        );

        if (!empty($article['doi'])) {
            $result['doi'] = $service->checkDoi($article['doi']);
        }
        if (!empty($article['links'])) {
            $result['link'] = $service->checkUrl($article['links'][0]);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Grava os dois interruptores da pagina publica.
     *
     * @param array $args
     * @param object $request
     */
    public function saveSettings($args, $request)
    {
        if (!$this->jsonGuard($request)) {
            return;
        }
        $context = $request->getContext();
        if (!$context || !$this->plugin) {
            $this->jsonOut(array('saved' => false, 'error' => 'invalid_context'), 400);
            return;
        }
        foreach (array('publicPage', 'publicFooter') as $key) {
            $this->plugin->updateSetting($context->getId(), $key, (bool) (int) $request->getUserVar($key), 'bool');
        }
        $this->jsonOut(array('saved' => true));
    }

    /**
     * Grava o resumo agregado da ultima verificacao completa.
     *
     * So numeros: nenhuma lista de artigos chega aqui. Os valores sao
     * limitados e conferidos entre si antes de gravar.
     *
     * @param array $args
     * @param object $request
     */
    public function saveSummary($args, $request)
    {
        if (!$this->jsonGuard($request)) {
            return;
        }
        $context = $request->getContext();
        if (!$context || !$this->plugin) {
            $this->jsonOut(array('saved' => false, 'error' => 'invalid_context'), 400);
            return;
        }

        $sum = array();
        foreach (self::summaryFields() as $key) {
            $sum[$key] = max(0, min(10000000, (int) $request->getUserVar($key)));
        }
        foreach (array('link', 'doi') as $p) {
            $n = $sum[$p . 'Ok'] + $sum[$p . 'Broken'] + $sum[$p . 'Content'] + $sum[$p . 'Attention'];
            if ($n > $sum['total']) {
                $this->jsonOut(array('saved' => false, 'error' => 'inconsistent'), 400);
                return;
            }
        }
        $sum['checkedAt'] = time();

        $this->plugin->updateSetting($context->getId(), 'summary', json_encode($sum), 'string');
        $this->jsonOut(array('saved' => true));
    }

    /**
     * Pagina publica de transparencia. Responde 404 enquanto o gerente nao
     * a publicar. So le o resumo gravado; nada e verificado na visita.
     *
     * @param array $args
     * @param object $request
     */
    public function status($args, $request)
    {
        $context = $request->getContext();
        if (!$context || !$this->plugin || !$this->plugin->getSetting($context->getId(), 'publicPage')) {
            LinkIntegrityCompat::notFound($request);
            return;
        }
        $this->setupTemplate($request);

        $contextId = $context->getId();
        $raw = $this->plugin->getSetting($contextId, 'summary');
        $sum = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($sum)) {
            $sum = null;
        } else {
            foreach (self::summaryFields() as $key) {
                $sum[$key] = isset($sum[$key]) ? (int) $sum[$key] : 0;
            }
        }

        $locale = $this->resolveLocale($request);
        $s = LinkIntegrityLocale::strings($this->localeDir(), $locale);
        $showFooter = (bool) $this->plugin->getSetting($contextId, 'publicFooter');

        $templateMgr = LinkIntegrityCompat::templateManager($request);
        if (method_exists($templateMgr, 'addStyleSheet')) {
            $templateMgr->addStyleSheet(
                'linkIntegrity',
                $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/styles/linkIntegrity.css'
                    . '?v=' . rawurlencode($this->pluginVersion()),
                array('contexts' => 'frontend')
            );
        }

        $templateMgr->assign(array(
            'pageTitle' => isset($s['publicTitle']) ? $s['publicTitle'] : '',
            's' => $s,
            'licSummary' => $sum,
            'licCheckedAt' => $sum && !empty($sum['checkedAt']) ? date('Y-m-d', (int) $sum['checkedAt']) : '',
            'licCharts' => $sum ? array(
                'link' => $this->summaryChart($sum, 'link', $s),
                'doi' => $this->summaryChart($sum, 'doi', $s),
            ) : null,
            'licShowFooter' => $showFooter,
            'licVersion' => $this->pluginVersion(),
            'licHistoryHtml' => $showFooter ? LinkIntegrityMarkdown::render($this->changelog()) : '',
        ));

        $templateMgr->display($this->plugin->getTemplateResource('status.tpl'));
    }

    /**
     * @return string[] Campos numericos do resumo
     */
    private static function summaryFields()
    {
        return array(
            'total',
            'linkOk', 'linkBroken', 'linkContent', 'linkAttention',
            'doiOk', 'doiBroken', 'doiContent', 'doiAttention',
        );
    }

    /**
     * Rosca em SVG gerada no servidor, para a pagina publica funcionar sem
     * JavaScript. Mesmo calculo do painel: arquivo defeituoso conta como
     * falha e ATENCAO fica fora do percentual.
     *
     * @param array $sum
     * @param string $p 'link' ou 'doi'
     * @param array $s strings traduzidas
     * @return array {pct: int|null, checked: int, svg: string}
     */
    private function summaryChart(array $sum, $p, array $s)
    {
        $parts = array(
            'ok' => array($sum[$p . 'Ok'], '#28a745', 'statusOk'),
            'broken' => array($sum[$p . 'Broken'], '#dc3545', 'statusBroken'),
            'conteudo' => array($sum[$p . 'Content'], '#e07b39', 'statusContent'),
            'atencao' => array($sum[$p . 'Attention'], '#f9a825', 'statusAttention'),
        );
        $checked = $parts['ok'][0] + $parts['broken'][0] + $parts['conteudo'][0];
        $pct = $checked > 0 ? (int) round($parts['ok'][0] / $checked * 100) : null;
        $total = $checked + $parts['atencao'][0];

        $h = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        $r = 50;
        $c = 2 * M_PI * $r;
        $arcs = '<circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="#e0e0e0" stroke-width="22"/>';
        $off = 0.0;
        if ($total > 0) {
            foreach ($parts as $part) {
                if ($part[0] <= 0) {
                    continue;
                }
                $len = $part[0] / $total * $c;
                $label = (isset($s[$part[2]]) ? $s[$part[2]] : $part[2]) . ': ' . $part[0];
                $arcs .= sprintf(
                    '<circle cx="70" cy="70" r="%d" fill="none" stroke="%s" stroke-width="22"'
                    . ' stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 70 70)">'
                    . '<title>%s</title></circle>',
                    $r, $part[1], $len, $c - $len, -$off, $h($label)
                );
                $off += $len;
            }
        }
        $title = $p === 'doi' ? 'blockDoi' : 'blockLinks';
        $svg = '<svg viewBox="0 0 140 140" width="140" height="140" role="img" aria-label="'
            . $h(isset($s[$title]) ? $s[$title] : $title) . '">' . $arcs
            . '<text class="center" x="70" y="75" text-anchor="middle">'
            . ($pct === null ? '–' : $pct . '%') . '</text></svg>';

        return array('pct' => $pct, 'checked' => $checked, 'svg' => $svg);
    }

    /**
     * Cabecalhos JSON, exige POST e confere o token CSRF. Responde o erro e
     * devolve false quando a requisicao nao passa.
     *
     * O token e obrigatorio: se a sessao nao o fornecer, a requisicao e
     * recusada (falha fechada). Estes endpoints disparam requisicoes de
     * saida e gravam configuracoes, entao nao podem aceitar pedido forjado.
     *
     * @param object $request
     * @return bool
     */
    private function jsonGuard($request)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        if ($request->getRequestMethod() !== 'POST') {
            $this->jsonOut(array('error' => 'method_not_allowed'), 405);
            return false;
        }
        $expected = LinkIntegrityCompat::csrfToken($request);
        if ($expected === null || !$request->checkCSRF()) {
            $this->jsonOut(array('error' => 'invalid_csrf'), 403);
            return false;
        }
        return true;
    }

    /**
     * @param array $data
     * @param int $code
     */
    private function jsonOut(array $data, $code = 200)
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    /**
     * Hosts da própria instalação, isentos da checagem de faixa de IP.
     * Ver a nota sobre proxy reverso em SECURITY.md.
     *
     * Vêm só do config.inc.php (base_url e base_url[...]). Até a 0.5.3
     * vinham de Request::getBaseUrl(), que o OJS monta a partir dos
     * cabeçalhos Host / X-Forwarded-Host: quem forjasse o cabeçalho
     * escolhia o host isento (ex.: 169.254.169.254) e a porta liberada.
     *
     * @param object $request
     * @return string[]
     */
    private function selfHosts($request)
    {
        $hosts = array();
        foreach ($this->configBaseUrls() as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host) {
                $hosts[] = strtolower($host);
            }
        }
        return array_values(array_unique($hosts));
    }

    /**
     * Porta da própria instalação, quando não for 80/443.
     *
     * @param object $request
     * @return int[]
     */
    private function selfPorts($request)
    {
        $ports = array();
        foreach ($this->configBaseUrls() as $url) {
            $port = parse_url($url, PHP_URL_PORT);
            if ($port) {
                $ports[] = (int) $port;
            }
        }
        return array_values(array_unique($ports));
    }

    /**
     * Endereços-base declarados no config.inc.php: base_url e as
     * substituições base_url[caminho] por revista.
     *
     * @return string[]
     */
    private function configBaseUrls()
    {
        $class = class_exists('PKP\\config\\Config') ? 'PKP\\config\\Config'
            : (class_exists('Config') ? 'Config' : null);
        if ($class === null) {
            return array();
        }

        $urls = array();
        $base = $class::getVar('general', 'base_url');
        if (is_string($base) && $base !== '') {
            $urls[] = $base;
        }

        $data = method_exists($class, 'getData') ? $class::getData() : array();
        $general = isset($data['general']) && is_array($data['general']) ? $data['general'] : array();
        foreach ($general as $key => $value) {
            // O parser do PKP guarda "base_url[caminho]" como chave literal;
            // por garantia aceita também a forma de array aninhado.
            if (is_string($key) && strpos($key, 'base_url[') === 0 && is_string($value)) {
                $urls[] = $value;
            } elseif ($key === 'base_url' && is_array($value)) {
                foreach ($value as $v) {
                    if (is_string($v)) {
                        $urls[] = $v;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Strings traduzidas consumidas pelo JavaScript do dashboard, num único
     * bloco JSON.
     *
     * @return array
     */
    private function clientStrings($locale = null)
    {
        $keys = array(
            'ok' => 'statusOk',
            'broken' => 'statusBroken',
            'atencao' => 'statusAttention',
            'pending' => 'statusPending',
            'testing' => 'statusTesting',
            'testOne' => 'testOne',
            'healthGeneral' => 'healthGeneral',
            'healthExcellent' => 'healthExcellent',
            'healthGood' => 'healthGood',
            'healthAttention' => 'healthAttention',
            'healthCritical' => 'healthCritical',
            'blockLinks' => 'blockLinks',
            'blockDoi' => 'blockDoi',
            'causeNotFound' => 'causeNotFound',
            'causeTimeout' => 'causeTimeout',
            'causeDenied' => 'causeDenied',
            'causeServerError' => 'causeServerError',
            'causeOther4xx' => 'causeOther4xx',
            'causeBlocked' => 'causeBlocked',
            'causeTls' => 'causeTls',
            'causeRedirect' => 'causeRedirect',
            'causeInvalidFile' => 'causeInvalidFile',
            'causeTruncatedFile' => 'causeTruncatedFile',
            'causeErrorPage' => 'causeErrorPage',
            'causeEmptyFile' => 'causeEmptyFile',
            'solutionNotFound' => 'solutionNotFound',
            'solutionTimeout' => 'solutionTimeout',
            'solutionDenied' => 'solutionDenied',
            'solutionServerError' => 'solutionServerError',
            'solutionOther4xx' => 'solutionOther4xx',
            'solutionBlocked' => 'solutionBlocked',
            'solutionTls' => 'solutionTls',
            'solutionRedirect' => 'solutionRedirect',
            'solutionInvalidFile' => 'solutionInvalidFile',
            'solutionTruncatedFile' => 'solutionTruncatedFile',
            'solutionErrorPage' => 'solutionErrorPage',
            'solutionEmptyFile' => 'solutionEmptyFile',
            'affected' => 'affectedRecords',
            'noProblems' => 'noProblemsFound',
            'sessionExpired' => 'sessionExpired',
            'updChecking' => 'updChecking',
            'updCurrent' => 'updCurrent',
            'updAvailable' => 'updAvailable',
            'updDownload' => 'updDownload',
            'updUnreachable' => 'updUnreachable',
            'content' => 'statusContent',
            'explainContent' => 'explainContent',
            'optSaving' => 'optSaving',
            'optSaved' => 'optSaved',
            'optFailed' => 'optFailed',
            'explainOk' => 'explainOk',
            'explainBroken' => 'explainBroken',
            'explainAttention' => 'explainAttention',
            'explainPending' => 'explainPending',
            'healthLinksLabel' => 'healthLinksLabel',
            'healthDoiLabel' => 'healthDoiLabel',
            'healthOverallLabel' => 'healthOverallLabel',
            'coverage' => 'coverage',
            'coverageNone' => 'coverageNone',
        );

        // As strings vêm dos .po do plugin, e não do tradutor do OJS, para
        // acompanhar o idioma escolhido na barra de bandeiras.
        $po = $locale === null ? array() : LinkIntegrityLocale::strings($this->localeDir(), $locale);

        $strings = array();
        foreach ($keys as $jsKey => $localeKey) {
            $strings[$jsKey] = isset($po[$localeKey])
                ? $po[$localeKey]
                : LinkIntegrityCompat::translate('plugins.generic.linkIntegrity.' . $localeKey);
        }
        return $strings;
    }
}
