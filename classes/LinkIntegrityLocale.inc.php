<?php

/**
 * @file plugins/generic/linkIntegrityChecker/classes/LinkIntegrityLocale.inc.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 *
 * @class LinkIntegrityLocale
 * @ingroup plugins_generic_linkIntegrityChecker
 *
 * @brief Lê os arquivos .po do próprio plugin.
 *
 *  Por que não usar a tradução do OJS aqui: o OJS carrega os arquivos de
 *  locale do plugin apenas para o idioma corrente da revista. Pedir a ele
 *  uma chave em outro idioma devolveria a própria chave. Como a interface
 *  permite trocar o idioma só do plugin, sem mexer no painel inteiro, as
 *  strings precisam vir direto dos .po do plugin.
 *
 *  O parser cobre o subconjunto de .po que este plugin usa: entradas
 *  msgid/msgstr de linha única ou em linhas contínuas, sem plurais e sem
 *  contexto (msgctxt).
 */

class LinkIntegrityLocale
{
    /** @var string Idioma usado quando nada mais serve */
    const FALLBACK = 'en_US';

    /** @var array Cache por locale, para não reabrir o arquivo */
    private static $cache = array();

    /**
     * Idiomas com arquivo de tradução presente no plugin.
     *
     * @param string $localeDir
     * @return array lista de códigos, ex.: array('en_US', 'es_ES', 'pt_BR')
     */
    public static function available($localeDir)
    {
        $out = array();
        if (!is_dir($localeDir)) {
            return $out;
        }
        foreach ((array) scandir($localeDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_readable($localeDir . '/' . $entry . '/locale.po')) {
                $out[] = $entry;
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Valida o locale pedido contra os disponíveis.
     *
     * Impede que o valor vindo da URL alcance o sistema de arquivos: só
     * passa o que já foi listado por available().
     *
     * @param string $localeDir
     * @param string|null $requested
     * @param string|null $default
     * @return string
     */
    public static function resolve($localeDir, $requested, $default = null)
    {
        $available = self::available($localeDir);
        if (!$available) {
            return (string) $default;
        }
        foreach (array($requested, $default, self::FALLBACK) as $candidate) {
            if ($candidate !== null && in_array($candidate, $available, true)) {
                return $candidate;
            }
        }
        return $available[0];
    }

    /**
     * Traduções de um locale, indexadas pela chave completa.
     *
     * @param string $localeDir
     * @param string $locale
     * @return array
     */
    public static function load($localeDir, $locale)
    {
        $file = $localeDir . '/' . $locale . '/locale.po';
        if (isset(self::$cache[$file])) {
            return self::$cache[$file];
        }

        $out = array();
        if (is_readable($file)) {
            $out = self::parsePo(file_get_contents($file));
        }
        self::$cache[$file] = $out;
        return $out;
    }

    /**
     * Strings do plugin sem o prefixo, prontas para o template.
     *
     * @param string $localeDir
     * @param string $locale
     * @param string $prefix
     * @return array
     */
    public static function strings($localeDir, $locale, $prefix = 'plugins.generic.linkIntegrity.')
    {
        // Caminho normal: o tradutor do OJS. Ele ja resolve o idioma
        // corrente da revista, enxerga qualquer locale instalado — inclusive
        // traducoes que cheguem pelo Weblate da PKP, que nao estao na pasta
        // do plugin — e e o que a PKP espera de um plugin.
        //
        // A leitura direta do .po abaixo serve a um caso so: a pessoa
        // escolheu, na barra de bandeiras, um idioma diferente do corrente.
        // O OJS carrega os arquivos de locale de um plugin apenas para o
        // idioma corrente, e devolveria a propria chave para os demais.
        if ($locale === null || $locale === LinkIntegrityCompat::currentLocale()) {
            $keys = self::keysFrom($localeDir, $prefix);
            if ($keys) {
                $out = array();
                foreach ($keys as $short => $full) {
                    $out[$short] = LinkIntegrityCompat::translate($full);
                }
                return $out;
            }
        }

        $all = self::load($localeDir, $locale);
        $fallback = $locale === self::FALLBACK ? array() : self::load($localeDir, self::FALLBACK);
        $out = array();
        foreach (array($fallback, $all) as $set) {
            foreach ($set as $key => $value) {
                if (strpos($key, $prefix) === 0) {
                    $out[substr($key, strlen($prefix))] = $value;
                }
            }
        }
        return $out;
    }

    /**
     * Chaves que o plugin declara, lidas de qualquer .po disponível.
     *
     * Serve só para saber QUAIS chaves existem; os textos vêm do tradutor
     * do OJS. Assim a lista de chaves continua vindo do pacote, e as
     * traduções de quem as fornece.
     *
     * @param string $localeDir
     * @param string $prefix
     * @return array mapa chave curta => chave completa
     */
    private static function keysFrom($localeDir, $prefix)
    {
        $available = self::available($localeDir);
        if (!$available) {
            return array();
        }
        $source = in_array(self::FALLBACK, $available, true) ? self::FALLBACK : $available[0];

        $out = array();
        foreach (self::load($localeDir, $source) as $key => $ignored) {
            if (strpos($key, $prefix) === 0) {
                $out[substr($key, strlen($prefix))] = $key;
            }
        }
        return $out;
    }

    /**
     * Parser mínimo de .po.
     *
     * @param string $content
     * @return array
     */
    private static function parsePo($content)
    {
        $out = array();
        $key = null;
        $target = null;
        $buffer = '';

        $flush = function () use (&$key, &$target, &$buffer, &$out) {
            if ($target === 'msgstr' && $key !== null && $key !== '') {
                $out[$key] = $buffer;
            } elseif ($target === 'msgid') {
                $key = $buffer;
            }
        };

        foreach (preg_split('/\r\n|\r|\n/', (string) $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, 'msgid ') === 0) {
                $flush();
                $target = 'msgid';
                $buffer = self::unquote(substr($line, 6));
                continue;
            }
            if (strpos($line, 'msgstr ') === 0) {
                $flush();
                $target = 'msgstr';
                $buffer = self::unquote(substr($line, 7));
                continue;
            }
            if ($line[0] === '"' && $target !== null) {
                $buffer .= self::unquote($line);
            }
        }
        $flush();

        return $out;
    }

    /**
     * Remove as aspas externas e resolve os escapes do formato .po.
     *
     * @param string $raw
     * @return string
     */
    private static function unquote($raw)
    {
        $raw = trim($raw);
        if (strlen($raw) >= 2 && $raw[0] === '"' && substr($raw, -1) === '"') {
            $raw = substr($raw, 1, -1);
        }
        return strtr($raw, array('\\n' => "\n", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\'));
    }
}
