<?php

/**
 * @file plugins/generic/linkIntegrityChecker/classes/LinkIntegrityService.inc.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 *
 * @class LinkIntegrityService
 * @ingroup plugins_generic_linkIntegrityChecker
 *
 * @brief Busca artigos publicados (DOI + links de galley) usando as APIs
 *  nativas do OJS e executa a verificação de links e resolução de DOI.
 *
 *  A verificação HTTP em si foi movida para LinkIntegrityHttpClient na
 *  v0.2, junto com as proteções contra SSRF; aqui fica apenas a leitura
 *  dos dados do OJS e a classificação da causa provável da falha.
 *
 * Nota de compatibilidade: o OJS 3.4+ introduziu o padrão Repository/
 * Collector (\APP\facades\Repo) para acesso a dados, substituindo parte
 * das DAOs legadas usadas no OJS 3.3. Esta classe detecta em tempo de
 * execução qual API está disponível e usa o caminho correspondente.
 */

require_once __DIR__ . '/LinkIntegrityCompat.inc.php';
require_once __DIR__ . '/LinkIntegrityHttpClient.inc.php';

class LinkIntegrityService
{
    /** @var LinkIntegrityHttpClient */
    private $client;

    /**
     * @param string[] $selfHosts Hosts da própria instalação
     * @param int[] $selfPorts Portas adicionais da própria instalação
     */
    public function __construct(array $selfHosts = array(), array $selfPorts = array())
    {
        $this->client = new LinkIntegrityHttpClient($selfHosts, $selfPorts);
    }

    /**
     * Retorna todos os artigos publicados de uma revista, no formato:
     * [ ['id' => int, 'titulo' => string, 'doi' => string|null, 'links' => string[]], ... ]
     *
     * @param int $contextId
     * @return array
     */
    public function getPublishedArticles($contextId)
    {
        $articles = array();
        $submissions = $this->fetchPublishedSubmissions($contextId);

        foreach ($submissions as $submission) {
            $article = $this->buildArticleData($submission, $contextId);
            if ($article) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * Retorna os dados de um único artigo pelo submission id.
     *
     * @param int $submissionId
     * @param int $contextId
     * @return array|null
     */
    public function getArticleById($submissionId, $contextId)
    {
        $submission = $this->fetchSubmissionById($submissionId);
        if (!$submission) {
            return null;
        }
        // Impede que um gerente da revista A consulte um artigo da revista B.
        if ((int) $submission->getData('contextId') !== (int) $contextId) {
            return null;
        }
        if ((int) $submission->getData('status') !== (int) LinkIntegrityCompat::statusPublished()) {
            return null;
        }
        return $this->buildArticleData($submission, $contextId);
    }

    /**
     * Verifica se uma URL está acessível, seguindo redirecionamentos.
     * Repete uma vez em caso de falha, para reduzir falso-negativo por
     * instabilidade transitória de rede.
     *
     * @param string $url
     * @return array {ok: bool, code: int, err: string, causa: string, atencao: bool}
     */
    public function checkUrl($url)
    {
        if (empty($url)) {
            return $this->attentionResult();
        }
        return $this->checkWithRetry($url);
    }

    /**
     * Verifica se um DOI resolve corretamente via https://doi.org/{doi}.
     *
     * @param string $doi
     * @return array {ok: bool, code: int, err: string, causa: string, atencao: bool}
     */
    public function checkDoi($doi)
    {
        $doi = $this->cleanDoi($doi);
        if ($doi === '') {
            return $this->attentionResult();
        }

        $url = 'https://doi.org/' . str_replace('%2F', '/', rawurlencode($doi));
        return $this->checkWithRetry($url);
    }

    // =====================================================================
    // Verificação
    // =====================================================================

    /**
     * @param string $url
     * @return array
     */
    private function checkWithRetry($url)
    {
        $result = $this->client->fetch($url);

        // URL barrada pela política de segurança: repetir não muda nada.
        if (!empty($result['blocked'])) {
            return array(
                'ok' => false,
                'code' => 0,
                'err' => $result['blockReason'],
                'causa' => 'bloqueado',
                'atencao' => false,
            );
        }

        if (!$this->isSuccessCode($result['code'])) {
            usleep(400000);
            $result = $this->client->fetch($url);
            if (!empty($result['blocked'])) {
                return array(
                    'ok' => false,
                    'code' => 0,
                    'err' => $result['blockReason'],
                    'causa' => 'bloqueado',
                    'atencao' => false,
                );
            }
        }

        $ok = $this->isSuccessCode($result['code']);

        // Codigo 200 nao garante arquivo utilizavel. O servidor pode
        // entregar uma pagina de erro com status 200, ou um PDF truncado
        // que o leitor recusa. Quando ha corpo, ele e inspecionado.
        if ($ok) {
            $content = $this->inspectContent($url, $result);
            if ($content !== null) {
                return $content;
            }
        }

        return array(
            'ok' => $ok,
            'code' => (int) $result['code'],
            'err' => (string) $result['err'],
            'causa' => $this->classifyError($result['code'], $result['err']),
            'atencao' => false,
        );
    }

    /**
     * Verifica se o corpo entregue e de fato o arquivo esperado.
     *
     * @param string $url
     * @param array $result retorno de LinkIntegrityHttpClient::fetch()
     * @return array|null resultado de falha, ou null se nada de errado
     */
    private function inspectContent($url, array $result)
    {
        $head = isset($result['body']) ? (string) $result['body'] : '';
        $type = isset($result['contentType']) ? (string) $result['contentType'] : '';

        if ($head === '') {
            // Corpo vazio com status 200: nao ha arquivo para o leitor.
            if (isset($result['contentLength']) && (int) $result['contentLength'] === 0) {
                return $this->contentFailure($result, 'arquivovazio');
            }
            return null;
        }

        $isHtml = stripos($type, 'text/html') !== false
            || preg_match('/^\s*(<!doctype html|<html)/i', $head);
        $looksPdf = strncmp($head, '%PDF-', 5) === 0;
        $expectsPdf = stripos($type, 'pdf') !== false
            || preg_match('/\.pdf(\?|$)/i', $url)
            || strpos($url, '/download/') !== false;

        // HTML onde deveria vir um arquivo: quase sempre pagina de erro,
        // aviso de login ou tela de bloqueio servida com status 200.
        if ($expectsPdf && $isHtml) {
            return $this->contentFailure($result, 'paginaerro');
        }

        if (!$looksPdf) {
            // Sem assinatura de PDF: so acusa quando havia motivo para
            // esperar um. Composicoes em HTML ou XML sao legitimas.
            if ($expectsPdf && !$isHtml) {
                return $this->contentFailure($result, 'arquivoinvalido');
            }
            return null;
        }

        // Comeca como PDF. Falta conferir se termina: um arquivo truncado
        // passa em todos os testes anteriores e mesmo assim nao abre.
        // Endereco final, ja validado por fetch(): evita repetir a cadeia
        // de redirecionamentos so para ler o fim do arquivo.
        $tail = $this->client->fetchTail(!empty($result['url']) ? $result['url'] : $url);
        if ($tail !== '' && stripos($tail, '%%EOF') === false) {
            return $this->contentFailure($result, 'arquivotruncado');
        }

        return null;
    }

    /**
     * @param array $result
     * @param string $causa
     * @return array
     */
    private function contentFailure(array $result, $causa)
    {
        // 'conteudo' distingue arquivo defeituoso de endereco que nao
        // responde: sao problemas diferentes na pratica — um 404 costuma
        // ser link errado, um PDF truncado costuma ser arquivo perdido no
        // armazenamento — e pedem acoes diferentes do editor.
        return array(
            'ok' => false,
            'code' => (int) $result['code'],
            'err' => $causa,
            'causa' => $causa,
            'atencao' => false,
            'conteudo' => true,
        );
    }

    /**
     * @return array
     */
    private function attentionResult()
    {
        return array('ok' => false, 'code' => 0, 'err' => '', 'causa' => 'naoencontrado', 'atencao' => true);
    }

    /**
     * @param int $code
     * @return bool
     */
    private function isSuccessCode($code)
    {
        // Apenas 2xx. A faixa 3xx nao e sucesso: o cliente ja segue os
        // redirecionamentos por conta propria e devolve o codigo do destino
        // final, entao um 3xx que sobra aqui significa cadeia interrompida —
        // excesso de saltos, Location ausente ou circular. Aceitar 3xx
        // marcava como OK link que nunca chegava a lugar nenhum.
        return $code >= 200 && $code < 300;
    }

    /**
     * Classifica a causa provável do erro para orientar a correção.
     *
     * @param int $code
     * @param string $err
     * @return string timeout|negado|naoencontrado|servidor|outro4xx|tls|bloqueado|ok
     */
    private function classifyError($code, $err)
    {
        if ($err !== '' && $this->looksLikeTlsError($err)) {
            return 'tls';
        }
        if ($err !== '') {
            return 'timeout';
        }
        if ($code == 0) {
            return 'timeout';
        }
        if ($code == 403) {
            return 'negado';
        }
        if ($code == 404) {
            return 'naoencontrado';
        }
        if ($code >= 500) {
            return 'servidor';
        }
        if ($code >= 400) {
            return 'outro4xx';
        }
        if ($code >= 300) {
            // Sobrou um 3xx depois de o cliente seguir a cadeia: ela nao
            // chegou ao fim (limite de saltos, Location ausente ou ciclo).
            return 'redirecionamento';
        }
        return 'ok';
    }

    /**
     * Com a verificação TLS ligada (v0.2), certificado inválido virou uma
     * causa de falha distinta — e é informação de auditoria legítima: o
     * leitor também não conseguiria abrir o link.
     *
     * @param string $err
     * @return bool
     */
    private function looksLikeTlsError($err)
    {
        $needles = array('SSL', 'TLS', 'certificate', 'certificado', 'CACert', 'cipher');
        foreach ($needles as $needle) {
            if (stripos($err, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $doi
     * @return string
     */
    private function cleanDoi($doi)
    {
        if (!$doi) {
            return '';
        }
        $doi = str_replace(array("\r", "\n", "\t"), '', (string) $doi);
        $doi = trim($doi, " \\\"'");

        // Aceita o DOI informado como URL completa ou com prefixo doi:.
        $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
        $doi = preg_replace('#^doi:\s*#i', '', $doi);

        return trim($doi);
    }

    // =====================================================================
    // Acesso a dados do OJS (com fallback entre Repository e DAO legada)
    // =====================================================================

    /**
     * @param int $contextId
     * @return iterable Lista de objetos Submission publicados
     */
    private function fetchPublishedSubmissions($contextId)
    {
        $statusPublished = LinkIntegrityCompat::statusPublished();

        // OJS 3.4 / 3.5 - padrão Repository/Collector
        if (class_exists('\APP\facades\Repo')) {
            $collector = \APP\facades\Repo::submission()->getCollector();
            $collector->filterByContextIds(array($contextId));
            if (method_exists($collector, 'filterByStatus')) {
                $collector->filterByStatus(array($statusPublished));
            }
            // No 3.4/3.5, getMany() pertence ao Collector, não ao Repository.
            return $collector->getMany()->values()->all();
        }

        // OJS 3.3 - DAO legada
        $submissionDao = Application::getSubmissionDAO();
        $result = array();
        $submissions = $submissionDao->getByContextId($contextId);
        while ($submission = $submissions->next()) {
            if ((int) $submission->getData('status') !== (int) $statusPublished) {
                continue;
            }
            $result[] = $submission;
        }
        return $result;
    }

    /**
     * @param int $submissionId
     * @return object|null
     */
    private function fetchSubmissionById($submissionId)
    {
        if (class_exists('\APP\facades\Repo')) {
            return \APP\facades\Repo::submission()->get((int) $submissionId);
        }
        $submissionDao = Application::getSubmissionDAO();
        return $submissionDao->getById((int) $submissionId);
    }

    /**
     * Monta o array normalizado de um artigo a partir do objeto Submission.
     *
     * @param object $submission
     * @param int $contextId
     * @return array|null
     */
    private function buildArticleData($submission, $contextId)
    {
        $publication = method_exists($submission, 'getCurrentPublication')
            ? $submission->getCurrentPublication()
            : null;

        if (!$publication) {
            return null;
        }

        $title = method_exists($publication, 'getLocalizedTitle')
            ? $publication->getLocalizedTitle()
            : $publication->getLocalizedData('title');

        return array(
            'id' => $submission->getId(),
            'titulo' => $title,
            'doi' => $this->extractDoi($publication),
            'links' => $this->extractLinks($submission, $contextId, $publication),
        );
    }

    /**
     * Extrai o DOI de uma publicação, tentando primeiro o campo nativo
     * (OJS 3.4+) e depois o identificador público armazenado por plugin
     * (OJS 3.3 e versões que ainda usam pub-id::doi).
     *
     * @param object $publication
     * @return string|null
     */
    private function extractDoi($publication)
    {
        $doi = $publication->getData('doi');
        if (!empty($doi) && is_string($doi)) {
            return $doi;
        }

        // OJS 3.4+: o DOI pode vir como objeto Doi relacionado.
        if (method_exists($publication, 'getDoi')) {
            $value = $publication->getDoi();
            if (!empty($value) && is_string($value)) {
                return $value;
            }
        }

        if (method_exists($publication, 'getStoredPubId')) {
            $legacyDoi = $publication->getStoredPubId('doi');
            if (!empty($legacyDoi)) {
                return $legacyDoi;
            }
        }

        return null;
    }

    /**
     * Extrai as URLs a serem verificadas: URL remota de cada galley
     * (quando o arquivo é hospedado externamente), URL do galley gerada
     * pelo próprio OJS, ou, na ausência de galleys, a URL da página do
     * artigo.
     *
     * @param object $submission
     * @param int $contextId
     * @param object $publication
     * @return string[]
     */
    private function extractLinks($submission, $contextId, $publication)
    {
        $links = array();
        $galleys = $publication->getData('galleys');

        if (!empty($galleys)) {
            foreach ($galleys as $galley) {
                if (method_exists($galley, 'getRemoteURL') && $galley->getRemoteURL()) {
                    $links[] = $galley->getRemoteURL();
                    continue;
                }
                // DOWNLOAD, e nao VIEW. A pagina do visualizador responde
                // 200 mesmo quando o arquivo sumiu do armazenamento: ela e
                // so o invólucro em torno do PDF.js, e carrega antes de
                // saber se ha arquivo. Ja article/download entrega o
                // arquivo ou devolve 404 — e e esse o endereco que o leitor
                // usa quando clica para baixar. Testar a pagina de
                // visualizacao daria "tudo certo" numa revista com todos os
                // PDFs perdidos.
                $url = $this->buildArticleUrl($submission, $contextId, $galley->getId(), 'download');
                if ($url) {
                    $links[] = $url;
                }
            }
        }

        if (empty($links)) {
            // Sem composicao publicada nao ha arquivo para baixar; resta
            // verificar se a pagina do artigo ao menos existe.
            $url = $this->buildArticleUrl($submission, $contextId, null, 'view');
            if ($url) {
                $links[] = $url;
            }
        }

        return $links;
    }

    private function buildArticleUrl($submission, $contextId, $galleyId, $op = 'view')
    {
        $request = LinkIntegrityCompat::request();
        if (!$request) {
            return null;
        }

        $context = $request->getContext();
        if (!$context || (int) $context->getId() !== (int) $contextId) {
            // O contexto do request pode diferir em tarefas em segundo
            // plano; nesse caso não dá para gerar a URL com segurança.
            return null;
        }

        $args = array(method_exists($submission, 'getBestId') ? $submission->getBestId() : $submission->getId());
        if ($galleyId) {
            $args[] = $galleyId;
        }

        return $request->getDispatcher()->url(
            $request,
            LinkIntegrityCompat::routePage(),
            $context->getPath(),
            'article',
            $op,
            $args
        );
    }
}
