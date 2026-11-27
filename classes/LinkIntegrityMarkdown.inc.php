<?php

/**
 * @file plugins/generic/linkIntegrityChecker/classes/LinkIntegrityMarkdown.inc.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 *
 * @class LinkIntegrityMarkdown
 * @ingroup plugins_generic_linkIntegrityChecker
 *
 * @brief Converte o CHANGELOG.md em HTML para exibição no painel.
 *
 *  Cobre apenas o subconjunto de Markdown usado no CHANGELOG do plugin:
 *  títulos, negrito, itálico, código inline, listas, linhas horizontais,
 *  links explícitos e URLs soltas. Não é um parser Markdown completo, e
 *  não pretende ser.
 *
 *  Segurança: o texto é escapado ANTES de qualquer marcação ser aplicada.
 *  Assim, mesmo que o arquivo passe a conter HTML — por edição manual ou
 *  por um merge malfeito — ele é exibido como texto, nunca interpretado.
 *  A única marcação que chega ao navegador é a gerada aqui.
 */

class LinkIntegrityMarkdown
{
    /**
     * @param string $md
     * @return string HTML
     */
    public static function render($md)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $md);
        $html = '';
        $inList = false;
        $para = array();
        $item = null;

        // Markdown junta linhas consecutivas num mesmo parágrafo. Tratar
        // cada linha isoladamente quebraria toda marcação que atravessa a
        // quebra de linha, como um **negrito** aberto numa linha e fechado
        // na seguinte — que é como o CHANGELOG está escrito.
        $flushPara = function () use (&$para, &$html) {
            if ($para) {
                $html .= '<p>' . self::inline(implode(' ', $para)) . "</p>\n";
                $para = array();
            }
        };
        $flushItem = function () use (&$item, &$html) {
            if ($item !== null) {
                $html .= '<li>' . self::inline(implode(' ', $item)) . "</li>\n";
                $item = null;
            }
        };

        foreach ($lines as $line) {
            $line = rtrim($line);
            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushPara();
                $flushItem();
                $html .= self::closeList($inList);
                continue;
            }

            if (preg_match('/^\s*---+\s*$/', $line)) {
                $flushPara();
                $flushItem();
                $html .= self::closeList($inList) . "<hr>\n";
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                $flushPara();
                $flushItem();
                // Desloca um nível: o h1 da página já é o título do plugin,
                // então o "# " do arquivo vira h2 e assim por diante.
                $level = min(6, strlen($m[1]) + 1);
                $html .= self::closeList($inList)
                    . '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . ">\n";
                continue;
            }

            if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
                $flushPara();
                $flushItem();
                if (!$inList) {
                    $html .= "<ul>\n";
                    $inList = true;
                }
                $item = array($m[1]);
                continue;
            }

            // Continuação: pertence ao item de lista aberto, se houver;
            // caso contrário, ao parágrafo em construção.
            if ($item !== null) {
                $item[] = $trimmed;
                continue;
            }
            $para[] = $trimmed;
        }

        $flushPara();
        $flushItem();
        return $html . self::closeList($inList);
    }

    /**
     * @param bool $inList passado por referência: fecha e marca como fechada
     * @return string
     */
    private static function closeList(&$inList)
    {
        if (!$inList) {
            return '';
        }
        $inList = false;
        return "</ul>\n";
    }

    /**
     * Marcação dentro da linha, sobre texto já escapado.
     *
     * @param string $text
     * @return string
     */
    private static function inline($text)
    {
        $out = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

        // Código inline primeiro: o conteúdo entre crases não deve receber
        // negrito nem virar link.
        $codes = array();
        $out = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
            $token = "\x00C" . count($codes) . "\x00";
            $codes[] = '<code>' . $m[1] . '</code>';
            return $token;
        }, $out);

        $out = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $out);
        $out = preg_replace('/(?<![\w*])\*([^*]+)\*(?![\w*])/', '<em>$1</em>', $out);

        // [texto](url)
        $out = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            function ($m) {
                return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
            },
            $out
        );

        // URLs soltas, desde que ainda não estejam dentro de um href.
        $out = preg_replace(
            '/(?<!["\'=])\b(https?:\/\/[^\s<)]+)/',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $out
        );

        foreach ($codes as $i => $code) {
            $out = str_replace("\x00C" . $i . "\x00", $code, $out);
        }

        return $out;
    }
}
