<?php

/**
 * @file plugins/generic/linkIntegrityChecker/classes/LinkIntegrityHttpClient.inc.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 *
 * @class LinkIntegrityHttpClient
 * @ingroup plugins_generic_linkIntegrityChecker
 *
 * @brief Cliente HTTP endurecido para a verificação de links e DOIs.
 *
 *  Este plugin faz o servidor da revista buscar URLs, o que é exatamente o
 *  padrão de um SSRF (Server-Side Request Forgery) se não houver controle.
 *  As defesas implementadas aqui são:
 *
 *  1. Só os esquemas http e https são aceitos (nada de file://, gopher://,
 *     ftp://, dict://); credenciais embutidas na URL são rejeitadas.
 *  2. Só as portas 80/443 (mais a porta da própria revista, se for outra).
 *  3. O nome do host é resolvido em PHP e cada IP resultante é conferido
 *     contra as faixas privadas, de loopback, link-local, CGNAT, multicast
 *     e reservadas (IPv4 e IPv6, incluindo endereços IPv4 mapeados).
 *  4. O IP validado é fixado com CURLOPT_RESOLVE, de modo que o cURL não
 *     refaz a consulta DNS — isso fecha a janela de DNS rebinding entre a
 *     validação e a requisição.
 *  5. Redirecionamentos NÃO são seguidos pelo cURL (CURLOPT_FOLLOWLOCATION
 *     desligado). Cada salto é resolvido e revalidado por este código, de
 *     modo que um redirect para 127.0.0.1 ou 169.254.169.254 é barrado.
 *  6. TLS é verificado (peer + host). Certificado inválido é falha, e é
 *     informação legítima de auditoria: o link está de fato quebrado para
 *     o leitor.
 *  7. Cabeçalhos e corpo têm limite de bytes; a transferência é abortada
 *     assim que o limite é atingido, então um PDF de 800 MB não derruba o
 *     servidor da revista.
 *
 *  Exceção deliberada: o host da própria revista entra numa allowlist e
 *  escapa da checagem de faixa de IP. É comum o OJS rodar atrás de proxy
 *  reverso ou em contêiner, onde o próprio domínio resolve para um IP
 *  privado — sem essa exceção o plugin bloquearia justamente os links que
 *  ele existe para auditar. Ver SECURITY.md.
 */

class LinkIntegrityHttpClient
{
    /** @var int Máximo de saltos de redirecionamento seguidos manualmente */
    const MAX_REDIRECTS = 5;

    /** @var int Timeout total da requisição, em segundos */
    const TIMEOUT = 12;

    /** @var int Timeout de conexão, em segundos */
    const CONNECT_TIMEOUT = 8;

    /** @var string Identificacao enviada aos servidores consultados */
    const USER_AGENT = 'Mozilla/5.0 (compatible; OJS-LinkIntegrityChecker/0.5.4; +https://websotto.com.br/)';

    /** @var int Limite de corpo lido; só precisamos do status e do Location */
    const MAX_BODY_BYTES = 32768;

    /** @var int Limite de cabeçalhos lidos */
    const MAX_HEADER_BYTES = 65536;

    /** @var array<string,bool> Hosts isentos da checagem de faixa de IP */
    private $allowedHosts = array();

    /** @var int[] Portas permitidas */
    private $allowedPorts = array(80, 443);

    /**
     * Instante (microtime) a partir do qual nenhuma nova requisição sai.
     * Uma verificação inteira (DOI, link, fim do PDF, repetições e
     * redirecionamentos) precisa caber no max_execution_time do PHP.
     *
     * @var float
     */
    private $deadline;

    /** @var array<string,string> Cabeçalhos de resposta do salto atual */
    private $responseHeaders = array();

    /** @var int */
    private $headerBytes = 0;

    /** @var int */
    private $bodyBytes = 0;

    /** @var bool Se a transferência foi cortada por nós (não é erro real) */
    private $truncated = false;

    /** @var string Primeiros bytes do corpo, para inspeção de conteúdo */
    private $bodyHead = '';

    /** @var int Quantos bytes do início guardar */
    const HEAD_SAMPLE_BYTES = 2048;

    /**
     * @param string[] $allowedHosts Hosts próprios da instalação
     * @param int[] $allowedPorts Portas adicionais permitidas
     */
    public function __construct(array $allowedHosts = array(), array $allowedPorts = array())
    {
        foreach ($allowedHosts as $host) {
            $host = strtolower(trim((string) $host, " \t\n\r\0\x0B[]"));
            // IP literal nunca fica isento: a exceção existe para o nome da
            // revista que resolve para endereço privado, não para endereços.
            if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false) {
                $this->allowedHosts[$host] = true;
            }
        }
        foreach ($allowedPorts as $port) {
            $port = (int) $port;
            if ($port > 0 && $port <= 65535) {
                $this->allowedPorts[] = $port;
            }
        }
        $this->allowedPorts = array_values(array_unique($this->allowedPorts));

        $limit = (int) ini_get('max_execution_time');
        $budget = $limit > 0 ? max(8, $limit - 5) : 60;
        $this->deadline = microtime(true) + $budget;
    }

    /**
     * Segundos que ainda restam para esta verificação (0 = esgotado).
     *
     * @return int
     */
    private function remainingSeconds()
    {
        return max(0, (int) floor($this->deadline - microtime(true)));
    }

    /**
     * Tempo total da verificação esgotado? Usado para não repetir à toa.
     *
     * @return bool
     */
    public function outOfTime()
    {
        return $this->remainingSeconds() < 2;
    }

    /**
     * Busca uma URL, seguindo e revalidando redirecionamentos.
     *
     * @param string $url
     * @return array {code: int, err: string, url: string, blocked: bool, blockReason: string, redirects: int}
     */
    public function fetch($url)
    {
        $current = (string) $url;
        $code = 0;
        $err = '';
        $redirects = 0;

        while ($redirects <= self::MAX_REDIRECTS) {
            $check = $this->validateUrl($current);
            if (!$check['ok']) {
                return array(
                    'code' => 0,
                    'err' => '',
                    'url' => $current,
                    'blocked' => true,
                    'blockReason' => $check['reason'],
                    'redirects' => $redirects,
                );
            }

            $attempt = $this->request($current, $check);
            $code = $attempt['code'];
            $err = $attempt['err'];
            $last = $attempt;

            if ($code >= 300 && $code < 400 && !empty($attempt['location'])) {
                $next = $this->resolveUrl($current, $attempt['location']);
                if ($next === null || $next === $current) {
                    break;
                }
                $current = $next;
                $redirects++;
                continue;
            }
            break;
        }

        return array(
            'code' => $code,
            'err' => $err,
            'url' => $current,
            'blocked' => false,
            'blockReason' => '',
            'redirects' => $redirects,
            'body' => isset($last['body']) ? $last['body'] : '',
            'contentType' => isset($last['contentType']) ? $last['contentType'] : '',
            'contentLength' => isset($last['contentLength']) ? $last['contentLength'] : 0,
        );
    }

    /**
     * Lê um trecho do fim do arquivo, via cabeçalho Range.
     *
     * Serve para conferir se o PDF termina com %%EOF. Um arquivo truncado
     * — upload interrompido, disco cheio na hora da cópia — responde 200 e
     * comeca com %PDF- normalmente, mas nao abre no leitor. Sem olhar o
     * fim nao ha como distinguir.
     *
     * @param string $url
     * @param int $lastBytes
     * @return string trecho final, ou '' se o servidor nao suportar Range
     */
    public function fetchTail($url, $lastBytes = 2048)
    {
        if (!function_exists('curl_init')) {
            return '';
        }

        // Mesmas defesas de fetch(): cada salto e validado, o IP validado e
        // fixado e o cURL nao segue redirecionamento sozinho. Antes da 0.5.3
        // este metodo seguia redirects sem revalidar, o que reabria o SSRF.
        $current = (string) $url;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if ($this->outOfTime()) {
                return '';
            }
            $check = $this->validateUrl($current);
            if (!$check['ok']) {
                return '';
            }

            $this->responseHeaders = array();
            $this->headerBytes = 0;
            $this->bodyHead = '';
            $this->bodyBytes = 0;
            $this->truncated = false;

            $ch = curl_init($current);
            if ($ch === false) {
                return '';
            }

            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => min(self::TIMEOUT, $this->remainingSeconds()),
                CURLOPT_CONNECTTIMEOUT => min(self::CONNECT_TIMEOUT, $this->remainingSeconds()),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RANGE => '-' . (int) $lastBytes,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HEADERFUNCTION => array($this, 'onHeaderLine'),
                CURLOPT_WRITEFUNCTION => array($this, 'onBodyChunk'),
            );
            if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
                $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            }
            if (!empty($check['pinIp'])) {
                $options[CURLOPT_RESOLVE] = array(
                    $check['host'] . ':' . $check['port'] . ':' . $check['pinIp'],
                );
            }

            curl_setopt_array($ch, $options);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 300 && $code < 400 && !empty($this->responseHeaders['location'])) {
                $next = $this->resolveUrl($current, $this->responseHeaders['location']);
                if ($next === null || $next === $current) {
                    return '';
                }
                $current = $next;
                continue;
            }

            // 206 = trecho entregue. 200 = servidor ignorou o Range e mandou
            // o arquivo inteiro; o que guardamos foi o inicio, nao o fim,
            // entao nao serve para procurar o %%EOF.
            return $code === 206 ? $this->bodyHead : '';
        }

        return '';
    }

    // =====================================================================
    // Validação da URL (anti-SSRF)
    // =====================================================================

    /**
     * @param string $url
     * @return array {ok: bool, reason: string, host: string, port: int, pinIp: string|null}
     */
    public function validateUrl($url)
    {
        $fail = function ($reason) {
            return array('ok' => false, 'reason' => $reason, 'host' => '', 'port' => 0, 'pinIp' => null);
        };

        if (!is_string($url) || $url === '' || strlen($url) > 2048) {
            return $fail('url_invalida');
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $fail('url_invalida');
        }

        // O esquema é conferido antes do host: file:///etc/passwd não tem
        // host, e "url inválida" esconderia a causa real do bloqueio.
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if ($scheme !== 'http' && $scheme !== 'https') {
            return $fail('esquema_nao_permitido');
        }

        if (empty($parts['host'])) {
            return $fail('url_invalida');
        }

        // URLs com credenciais embutidas (http://user:pass@host) são um
        // vetor clássico de confusão de parser: rejeitadas.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return $fail('credenciais_na_url');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === '') {
            return $fail('url_invalida');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, $this->allowedPorts, true)) {
            return $fail('porta_nao_permitida');
        }

        // Host da própria revista: isento da checagem de faixa de IP.
        if (isset($this->allowedHosts[$host])) {
            return array('ok' => true, 'reason' => '', 'host' => $host, 'port' => $port, 'pinIp' => null);
        }

        $ips = $this->resolveHost($host);
        if (empty($ips)) {
            return $fail('dns_falhou');
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return $fail('endereco_interno');
            }
        }

        // Fixa o primeiro IP validado (preferindo IPv4) para que o cURL não
        // resolva o nome de novo e acabe pegando outro endereço.
        $pin = null;
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $pin = $ip;
                break;
            }
        }
        if ($pin === null) {
            $pin = $ips[0];
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $pin = null; // host já é literal, nada a fixar
        }

        return array('ok' => true, 'reason' => '', 'host' => $host, 'port' => $port, 'pinIp' => $pin);
    }

    /**
     * @param string $host
     * @return string[] IPs resolvidos (v4 e v6)
     */
    private function resolveHost($host)
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return array($host);
        }

        $ips = array();

        if (function_exists('gethostbynamel')) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
        }

        if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ips)));
    }

    /**
     * @param string $ip
     * @return bool true se o IP é roteável publicamente
     */
    public function isPublicIp($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isPublicIpv4($ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isPublicIpv6($ip);
        }
        return false;
    }

    /**
     * @param string $ip
     * @return bool
     */
    private function isPublicIpv4($ip)
    {
        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 4) {
            return false;
        }

        $blocked = array(
            array('0.0.0.0', 8),        // "este" host / rede
            array('10.0.0.0', 8),       // privada
            array('100.64.0.0', 10),    // CGNAT
            array('127.0.0.0', 8),      // loopback
            array('169.254.0.0', 16),   // link-local (metadados de nuvem)
            array('172.16.0.0', 12),    // privada
            array('192.0.0.0', 24),     // IETF protocol assignments
            array('192.0.2.0', 24),     // TEST-NET-1
            array('192.88.99.0', 24),   // 6to4 relay anycast
            array('192.168.0.0', 16),   // privada
            array('198.18.0.0', 15),    // benchmarking
            array('198.51.100.0', 24),  // TEST-NET-2
            array('203.0.113.0', 24),   // TEST-NET-3
            array('224.0.0.0', 4),      // multicast
            array('240.0.0.0', 4),      // reservada / broadcast
        );

        foreach ($blocked as $range) {
            $net = @inet_pton($range[0]);
            if ($net !== false && $this->inCidr($bin, $net, $range[1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $ip
     * @return bool
     */
    private function isPublicIpv6($ip)
    {
        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return false;
        }

        // ::ffff:a.b.c.d — endereço IPv4 mapeado: avalia como IPv4.
        if (substr($bin, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            $v4 = @inet_ntop(substr($bin, 12));
            return $v4 !== false && $this->isPublicIpv4($v4);
        }

        // ::a.b.c.d (compatível, obsoleto), :: e ::1 caem aqui.
        if (substr($bin, 0, 12) === str_repeat("\x00", 12)) {
            return false;
        }

        $blocked = array(
            array('fc00::', 7),       // unique local
            array('fe80::', 10),      // link-local
            array('ff00::', 8),       // multicast
            array('2001:db8::', 32),  // documentação
            array('64:ff9b::', 96),   // NAT64
            array('100::', 64),       // discard-only
            array('2002::', 16),      // 6to4 (pode encapsular IPv4 privado)
        );

        foreach ($blocked as $range) {
            $net = @inet_pton($range[0]);
            if ($net !== false && $this->inCidr($bin, $net, $range[1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compara um IP binário com uma rede binária pelo prefixo.
     *
     * @param string $ipBin
     * @param string $netBin
     * @param int $prefix
     * @return bool
     */
    private function inCidr($ipBin, $netBin, $prefix)
    {
        if (strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && strncmp($ipBin, $netBin, $fullBytes) !== 0) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        if (!isset($ipBin[$fullBytes], $netBin[$fullBytes])) {
            return false;
        }
        $mask = chr((0xFF << (8 - $remainingBits)) & 0xFF);
        return ($ipBin[$fullBytes] & $mask) === ($netBin[$fullBytes] & $mask);
    }

    // =====================================================================
    // Resolução de redirecionamentos relativos
    // =====================================================================

    /**
     * Resolve um cabeçalho Location (absoluto, protocolo-relativo, absoluto
     * de raiz ou relativo) contra a URL corrente, conforme RFC 3986 §5.
     *
     * @param string $base
     * @param string $location
     * @return string|null
     */
    public function resolveUrl($base, $location)
    {
        $location = trim((string) $location);
        if ($location === '') {
            return null;
        }

        // Descarta fragmento: irrelevante para a requisição.
        $hashPos = strpos($location, '#');
        if ($hashPos !== false) {
            $location = substr($location, 0, $hashPos);
        }
        if ($location === '') {
            return null;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false || empty($baseParts['host']) || empty($baseParts['scheme'])) {
            return null;
        }

        $baseScheme = $baseParts['scheme'];
        $baseAuthority = $baseParts['host'];
        if (strpos($baseAuthority, ':') !== false && strpos($baseAuthority, '[') === false) {
            $baseAuthority = '[' . $baseAuthority . ']';
        }
        if (isset($baseParts['port'])) {
            $baseAuthority .= ':' . (int) $baseParts['port'];
        }

        // 1. URL absoluta (tem esquema).
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $location)) {
            return $this->normalizePath($location);
        }

        // 2. Protocolo-relativa: //host/caminho
        if (strpos($location, '//') === 0) {
            return $this->normalizePath($baseScheme . ':' . $location);
        }

        // 3. Absoluta de raiz: /caminho
        if (isset($location[0]) && $location[0] === '/') {
            return $this->normalizePath($baseScheme . '://' . $baseAuthority . $location);
        }

        // 4. Relativa: resolve contra o diretório da URL corrente.
        $basePath = isset($baseParts['path']) ? $baseParts['path'] : '/';
        $dir = substr($basePath, 0, strrpos($basePath, '/') + 1);
        if ($dir === '' || $dir === false) {
            $dir = '/';
        }

        return $this->normalizePath($baseScheme . '://' . $baseAuthority . $dir . $location);
    }

    /**
     * Normaliza segmentos "." e ".." do caminho de uma URL absoluta.
     *
     * @param string $url
     * @return string
     */
    private function normalizePath($url)
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        if ($path === '') {
            $path = '/';
        }

        $segments = explode('/', $path);
        $output = array();
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($output);
                continue;
            }
            $output[] = $segment;
        }

        $normalized = '/' . implode('/', $output);
        if (substr($path, -1) === '/' && $normalized !== '/') {
            $normalized .= '/';
        }

        $host = $parts['host'];
        if (strpos($host, ':') !== false && strpos($host, '[') === false) {
            $host = '[' . $host . ']';
        }

        $result = $parts['scheme'] . '://' . $host;
        if (isset($parts['port'])) {
            $result .= ':' . (int) $parts['port'];
        }
        $result .= $normalized;
        if (isset($parts['query']) && $parts['query'] !== '') {
            $result .= '?' . $parts['query'];
        }

        return $result;
    }

    // =====================================================================
    // Requisição
    // =====================================================================

    /**
     * Executa um único salto HTTP, sem seguir redirecionamento.
     *
     * @param string $url
     * @param array $validation Resultado de validateUrl()
     * @return array {code: int, err: string, location: string|null}
     */
    private function request($url, array $validation)
    {
        if (!function_exists('curl_init')) {
            return array('code' => 0, 'err' => 'curl_indisponivel', 'location' => null);
        }

        // Sem tempo para mais um salto: devolve como timeout (code 0, err).
        if ($this->outOfTime()) {
            return array('code' => 0, 'err' => 'tempo_esgotado', 'location' => null);
        }

        $this->responseHeaders = array();
        $this->headerBytes = 0;
        $this->bodyBytes = 0;
        $this->truncated = false;
        $this->bodyHead = '';

        $ch = curl_init($url);
        if ($ch === false) {
            return array('code' => 0, 'err' => 'curl_init_falhou', 'location' => null);
        }

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => min(self::TIMEOUT, $this->remainingSeconds()),
            CURLOPT_CONNECTTIMEOUT => min(self::CONNECT_TIMEOUT, $this->remainingSeconds()),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HEADERFUNCTION => array($this, 'onHeaderLine'),
            CURLOPT_WRITEFUNCTION => array($this, 'onBodyChunk'),
        );

        if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        if (!empty($validation['pinIp'])) {
            $options[CURLOPT_RESOLVE] = array(
                $validation['host'] . ':' . $validation['port'] . ':' . $validation['pinIp'],
            );
        }

        curl_setopt_array($ch, $options);
        curl_exec($ch);

        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $declaredLength = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // CURLE_WRITE_ERROR (23) é o corte deliberado feito por nós assim
        // que o limite de bytes foi atingido: não é falha do link.
        if ($this->truncated && $errno === 23) {
            $err = '';
        }

        $location = isset($this->responseHeaders['location'])
            ? $this->responseHeaders['location']
            : null;

        return array(
            'code' => $code,
            'err' => $err,
            'location' => $location,
            'body' => $this->bodyHead,
            'contentType' => isset($this->responseHeaders['content-type'])
                ? strtolower($this->responseHeaders['content-type'])
                : '',
            'contentLength' => $declaredLength > 0
                ? $declaredLength
                : (isset($this->responseHeaders['content-length'])
                    ? (int) $this->responseHeaders['content-length']
                    : 0),
        );
    }

    /**
     * Callback de cabeçalho do cURL. Público por exigência do callable.
     *
     * @param resource|\CurlHandle $ch
     * @param string $line
     * @return int Bytes consumidos
     */
    public function onHeaderLine($ch, $line)
    {
        $length = strlen($line);
        $this->headerBytes += $length;
        if ($this->headerBytes > self::MAX_HEADER_BYTES) {
            $this->truncated = true;
            return -1;
        }

        $colon = strpos($line, ':');
        if ($colon !== false) {
            $name = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));
            if ($name !== '') {
                $this->responseHeaders[$name] = $value;
            }
        }

        return $length;
    }

    /**
     * Callback de corpo do cURL: aborta assim que o limite é atingido.
     *
     * @param resource|\CurlHandle $ch
     * @param string $chunk
     * @return int
     */
    public function onBodyChunk($ch, $chunk)
    {
        $length = strlen($chunk);
        if (strlen($this->bodyHead) < self::HEAD_SAMPLE_BYTES) {
            $this->bodyHead .= substr($chunk, 0, self::HEAD_SAMPLE_BYTES - strlen($this->bodyHead));
        }
        $this->bodyBytes += $length;
        if ($this->bodyBytes > self::MAX_BODY_BYTES) {
            $this->truncated = true;
            return -1;
        }
        return $length;
    }
}
