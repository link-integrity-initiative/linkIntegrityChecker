<?php

/**
 * @defgroup plugins_generic_linkIntegrityChecker Link Integrity Checker Plugin
 */

/**
 * @file plugins/generic/linkIntegrityChecker/index.php
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU GPL v3 or later. See LICENSE.
 *
 * @ingroup plugins_generic_linkIntegrityChecker
 * @brief Wrapper for the Link Integrity Checker plugin.
 */

require_once __DIR__ . '/LinkIntegrityCheckerPlugin.inc.php';

return new LinkIntegrityCheckerPlugin();
