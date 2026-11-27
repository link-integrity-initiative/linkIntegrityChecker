# Version History

*English translation of `CHANGELOG.md` (Portuguese). If the two differ,
the Portuguese file is the reference.*

## Project origin

The **Link Integrity Checker** began as an internal PHP script
(`system_integrity.php`), written by Dr. Luis Alcides Brandini De Boni to
support the internal audit of **Periódico Tchê Química**, periodically
checking whether article links and published DOIs still resolved
correctly.

Later, in a professional conversation between the journal and
**WebSotto** (https://websotto.com.br/), the opportunity arose to turn that
internal tool into a **distributable plugin for Open Journal Systems
(OJS)**, so that any journal running OJS could carry out the same kind of
internal integrity audit without depending on a custom script running
outside the platform.

In terms of code lineage: **this version is based on Periódico Tchê
Química's script 0.1 for system integrity checking**
(`system_integrity.php`, in production at
https://www.tchequimica.com/system_integrity.php). The checking logic and
the dashboard came from there; what changed was the data source, now the
OJS submission repository instead of a table of its own, and access
control, now the platform's authorization policies.

This document records the evolution of the plugin that came out of that
partnership.

---

## [0.5.4.2] — 2026-11-27

### Added
- **Version history in English.** The history shown in the dashboard
  footer and on the public page was always `CHANGELOG.md`, in Portuguese,
  whatever the interface language. It now follows the language: Portuguese
  shows `CHANGELOG.md`, and any other language shows this English file,
  `CHANGELOG.en.md`. A translation for another language can be added as
  `CHANGELOG.<language>.md` (e.g. `CHANGELOG.es.md`) and is picked up
  automatically.

---

## [0.5.4.1] — 2026-11-27

### Fixed
- The dashboard donut chart left defective files out of the total, and the
  slices went past a full circle when there were any.
- The problem report used the simple average of links and DOIs; it now uses
  the same weighted average as the overall health card.
- Switching language in the flag bar stacked `?licLocale=` in the URL from
  the second switch on.

### Cleanup
- Removed the locale string `guideHealth`, replaced by `guideWeighted` and
  no longer used.
- License header added to `templates/status.tpl`.

---

## [0.5.4] — 2026-11-27

### Security
- **SSRF through the `X-Forwarded-Host` header.** The host exempt from the
  IP-range check (the journal's own) came from `Request::getBaseUrl()`,
  which OJS builds from the request headers. A manager who forged the
  header chose the exempt host and the allowed port and could probe the
  internal network (`169.254.169.254`, `10.x`). The journal's own hosts and
  ports now come only from `base_url` in `config.inc.php`, and an IP
  literal is never exempt.
- The update check only follows redirects to `https`.

### Changed
- **Version check through GitHub.** The footer button now queries the
  latest release at
  `github.com/link-integrity-initiative/linkIntegrityChecker` through
  GitHub's public API, and the "Download" link leads to the release's
  `.tar.gz` package. It no longer sends the journal name, the site address
  or the versions to Periódico Tchê Química's server: it is an anonymous
  GET.
- Versions compared in four parts (`0.5.4` = `0.5.4.0`).

### Fixed — behaviour
- **"Overall health" card always "Critical" with "NaN%".** The total
  summed for the card did not include content failures, and the
  calculation gave `NaN`.
- **Checks that exceeded `max_execution_time`.** A single check could add
  up to several minutes (retry × redirects × 12 s, plus reading the end of
  the PDF). Each check now has a total time budget (`max_execution_time`
  minus 5 s, or 60 s when there is no limit); anything beyond it is
  reported as a timeout, redirects drop from 10 to 5, and only transient
  failures (no response, 429, 5xx) are retried.
- **False "broken" on galleys that are not PDF.** A URL containing
  `/download/` was enough for the plugin to expect a PDF, and HTML, XML or
  EPUB galleys showed up as "error page" or "invalid file". The galley
  label now decides, and the PDF galley, when there is one, is tested
  first.
- **Old JS and CSS after upgrading.** OJS tagged the files with its own
  version (`?v=3.5.0.5`), and the browser kept using the previous plugin
  version's JavaScript. The tag is now the plugin version.
- User-Agent with the full version (`0.5.4`).

## [0.5.3] — 2026-11-18

### Fixed — behaviour
- **Public page, summary and switches now work.** The `saveSummary`,
  `saveSettings` and `status` operations, announced in 0.5.0, were called
  by the dashboard but did not exist in the handler: the switches always
  answered "failed", the summary was never saved and
  `/linkintegrity/status` gave an error. They are now implemented, with the
  public page charts generated as SVG on the server.
- The public page was denied to visitors even when published:
  `PKPHandler` refuses operations when the handler has role assignments
  and no policy checked them. The `status` operation now marks that check
  as done.
- **Languages on OJS 3.4/3.5.** OJS 3.4 switched to `en`, `es`, `de`,
  `it`, `id` and `tr` instead of `en_US`, `es_ES` etc., and looks the
  folder up by exact name. Journals in those languages saw the raw keys
  (`plugins.generic.linkIntegrity...`). The package now ships both
  spellings; `pt_BR` and `fr_CA` did not change. Flags also for the new
  codes and for Italian.
- On OJS 3.3 the router creates the handler by class name, without passing
  the plugin; the handler now fetches the instance from `PluginRegistry`,
  without which there were no settings or templates.
- 404 response compatible with OJS 3.5, whose `Dispatcher` no longer has
  `handle404()`.

### Security
- **Stored XSS in the dashboard.** `escapeHtml()` did not escape quotes,
  and a remote galley URL went into `href` and `title`. Whoever registers
  galleys could inject attributes and script into the manager's session.
  The escape now covers `& < > " '` and only `http(s)` addresses become
  links (`safeUrl()`).
- **SSRF in `fetchTail()`.** Reading the end of the PDF let cURL follow
  redirects without revalidating and without pinning the IP. It now goes
  through the same defences as `fetch()` and starts from the already
  validated final address.
- **CSRF mandatory.** If the session does not provide the token, the
  request is refused; before, the check was skipped.
- The download link coming from the version server is only accepted if it
  is `https`.

### Documentation and metadata
- `README.md` in English and `README.pt_BR.md` updated to the current
  version, with the sections **Stored data** and **Update check
  (privacy)**: when clicked, the button sends the version, the journal
  name and the site address to the version server.
- `SECURITY.md` revised: public page, browser output (XSS),
  `fetchTail()`, stored data and private vulnerability reporting through
  GitHub.
- `LICENSE` with the full GPL v3 text (before there was only a summary in
  `LICENSE.md`, which GitHub does not recognise as a license).
- `version.xml` 0.5.3.0 with the correct date; version aligned in the
  `.po` headers, the User-Agent and the citation in `CREDITS.md`.

## [0.5.2] — 2026-11-08

### Added
- **Five new languages**: French (Canada), Italian, German, Indonesian and
  Turkish, with all 111 keys in each. Flags for the new locales, plus
  fr_FR, pt_PT and en_GB.
- `CREDITS.md` now has a translations table that tells the team-reviewed
  ones apart from the machine-generated ones, and records how the term
  *galley* was rendered in each language. Marking what was not reviewed is
  what lets the community know where to help — and is the opposite of
  presenting machine translation as if it were reviewed.

## [0.5.1] — 2026-11-08

### Changed — compliance with PKP practices
- **The OJS translator is the main path again.** Since 0.2.5 the plugin
  read the `.po` files directly to allow switching language; the side
  effect was bypassing the platform mechanism and seeing only the three
  languages in the package. Now `__()` serves the current language — and
  therefore any installed translation, including those that arrive through
  PKP's Weblate — and reading the `.po` directly is limited to the one case
  OJS cannot serve: the person chose, in the bar, a language other than the
  current one.
- **The language bar comes from the languages enabled in the journal**,
  through `getSupportedLocaleNames()`, instead of a fixed list of three in
  the code. A newly enabled language appears on its own; one the journal
  does not use does not appear. With a single language enabled, the bar is
  not shown.
- Language names are now the ones OJS itself uses. There are flags for
  pt_BR, pt_PT, en_US, en_GB and es_ES; the others appear with the name
  only, which is what actually identifies the language.
- The `licLocale` parameter is now validated against the languages
  enabled in the journal before any use.

## [0.5.0] — 2026-09-24

### Added
- **Public transparency page**, at `/linkintegrity/status`, off by
  default. It shows aggregates only: total articles, percentage of sound
  links and DOIs, the two charts and the date of the last check. No list of
  articles, no individual addresses and no test button — publishing the
  list would hand visitors a map of the site's weak points, and allowing a
  reader's visit to trigger the check would be an open door to abuse.
  - The charts are generated as SVG on the server: the page works without
    any JavaScript.
  - It only reads the summary saved by the last check run in the
    dashboard. Nothing is calculated on the visit.
- **Two switches in the dashboard**: publish the page, and show the
  credits and history on it. The update-check button never appears on the
  public page. They save immediately, with no separate form.
- The aggregate summary is now saved at the end of "Test All". Numbers
  only: no list of articles leaves the browser.

## [0.4.2] — 2026-09-24

### Added
- **Scope selector** in the filter bar: Both, DOI only, Link only. It
  decides which of the two tests the status filter applies to. With
  "Both" — the default — nothing changes from the previous version: the
  row appears if the DOI **or** the link is in the chosen status. That is
  useful for hunting problems, but it made the "OK" filter show defective
  rows, since the other column was OK in them. Choosing DOI or Link, the
  question becomes about a single test.

## [0.4.1] — 2026-09-24

### Added
- **FILE category**, separate from BROKEN. An address that does not answer
  and a file that answers but is useless are different problems and call
  for different actions: a 404 is usually a wrong link, a truncated PDF is
  usually a file lost in storage. Before, both fell into the same counter.
  - Its own counter, chart slice, legend item and colour in every palette.
  - "File" filter in the bar. The "Problems" filter now groups both
    categories, since both keep the reader from reaching the file.
  - It counts as a failure in the health calculation, not as an unchecked
    case.

## [0.4.0] — 2026-09-24

### Added
- **Inspection of the delivered content.** Status 200 does not guarantee a
  usable file, and that was all the plugin checked. A corrupted PDF
  answered 200 and showed as OK, while the reader saw "Invalid PDF
  structure" and a blank page. The checker now examines what the server
  returned and tells four new situations apart:
  - `paginaerro` — HTML with status 200 instead of the file (friendly
    error screen, login notice, server block);
  - `arquivoinvalido` — the content does not start with the `%PDF-`
    signature;
  - `arquivotruncado` — it starts as a PDF but does not end in `%%EOF`, a
    sign of an interrupted upload. Detected with a `Range` request that
    reads only the last 2 KB, without downloading the whole file;
  - `arquivovazio` — 200 with zero bytes.
  Each with its own cause and solution in the guide, in the three
  languages. Checked against a test server that reproduces the four
  defects.

### Changed
- Buttons, filters and search box narrower and with a uniform 30px height,
  in proportion to the rest of the dashboard.

## [0.3.3] — 2026-09-24

### Fixed
- **Footer and language bar without colour.** Both sit outside `#licApp`,
  which was where the colour variables were declared: outside the
  container `var(--lic-dark)` did not resolve, and the footer came out
  with a transparent background and white text on light grey. The
  variables moved to `body`, and so did the `data-lic-theme` attribute.
- **Redirect counted as success.** `isSuccessCode()` accepted 200 to 399.
  Since the client already follows the redirect chain and returns the code
  of the final destination, a leftover 3xx means a broken chain — hop
  limit, missing or circular `Location`. Now only 2xx is success, and 3xx
  got its own cause and solution in the guide.

### Changed
- The HTTP code now appears in the status tooltip even when the result is
  OK, and the Link column shows the full URL on hover. Without that there
  was no way to tell a legitimate 200 from a 200 returned by a friendly
  error page or a login screen.

## [0.3.2] — 2026-09-24

### Fixed
- **The link test checked the wrong address.** It was the viewer page
  (`article/view/…`), which answers 200 even when the file has vanished
  from storage: it is just the wrapper around PDF.js and loads before
  knowing whether there is a file. In a journal with every PDF lost, the
  dashboard would say "all good". The test now uses `article/download/…`,
  which delivers the file or returns 404 — and is the address the reader
  uses when clicking to download. Articles without a published galley
  still check the article page, since there is no file.

### Added
- **Item in the dashboard side menu** (OJS 3.4/3.5), right after "Tools",
  through the `TemplateManager::setupBackendPage` hook. Until then the
  dashboard could only be reached by typing the URL: the hook used before
  (`Templates::Manager::Index::ManagementPages`) dates from OJS 3.2/3.3 and
  does not fire on 3.5. The old hook was kept for 3.3 installations.

## [0.3.1] — 2026-09-24

### Changed
- **Footer in a band of its own**, with a dark background set apart from
  the page body, in the spirit of the journal website's footer. History,
  credits and action on a single line, in a smaller font, with "Check for a
  newer version" as a button on the right.

### Added
- **Usage log** on the version server. When "check for update" is clicked,
  the plugin sends the plugin version, the OJS version, and the journal's
  name and address; `linkIntegrityCheckerVersion.php` writes a line to
  `lic-data/usage.csv` with that data plus the date and source IP. The
  purpose is to measure whether the plugin has real use.
  - Nothing is sent without a click: there is no automatic background
    sending.
  - The request is made by the journal's server, through cURL, so the IP
    recorded is the hosting's, not a natural person's.
  - The button shows, on hover, the exact list of what is sent.
  - The `lic-data/` folder gets an `.htaccess` that blocks HTTP access.

## [0.3.0] — 2026-09-24

### Added
- **Palette selector** at the top, with five options: Tchê (default,
  colours taken from the journal's CSS), Sotto (WebSotto's royal blue and
  black), De Boni (Brazil's green, yellow and blue in pastel tones), Pastel
  and Blue and black. The choice is stored in each person's browser, not in
  the journal configuration: two editors of the same journal may prefer
  different palettes.

### Fixed
- Palette selector misaligned with the label next to it. Form controls do
  not inherit typography, and OJS applies its own rules to `select`: the
  text came out larger and the box taller than the language pills. Fixed
  with `font: inherit` and a common height for the bar controls, under a
  selector with higher specificity than the OJS stylesheet.

### Changed
- The whole stylesheet now uses CSS variables. The charts read the same
  variables, so there is no colour duplicated between the CSS and the
  JavaScript, and switching palette changes everything at once.
- Table redesigned: smaller font, tighter spacing, fixed column widths
  through `colgroup` and top alignment in cells. Before, its size was out
  of step with the rest of the dashboard.
- Inline styles in the HTML moved to classes.

## [0.2.7] — 2026-09-24

### Fixed
- **Misleading overall health.** It was the simple average of the two
  percentages, and a dimension with nothing checked simply vanished from
  the sum: with 6 links OK and no DOI registered, the dashboard announced
  "Excellent, 100%". It is now an average weighted by volume — both sets
  go into the same numerator and denominator.

### Added
- Three separate indicators: LINKS health, DOI health and weighted overall
  health.
- A **coverage** line on each indicator ("6 of 12 checks done"). A high
  score over low coverage does not mean a sound journal, it means little
  was tested.
- Explanation on hover (or through the keyboard, via focus) over the count
  cards, the legend items and the chart slices.

## [0.2.6] — 2026-09-24

### Fixed
- **Invisible flags on Windows.** They were flag emoji, and Windows ships
  no font for the regional indicator characters: they showed as two loose
  letters ("BR", "US", "ES"). Replaced with inline SVG, which renders in
  any browser and system.
- `linkIntegrityCheckerVersion.php` rewritten for **PHP 5.4**, which is
  what runs on the journal's server. The first version used
  `declare(strict_types=1)`, parameter types and `??`, all PHP 7, and
  returned HTTP 500 before even running.

## [0.2.5] — 2026-09-24

### Added
- **How to read the results** panel: what each status means (OK, BROKEN,
  ATTENTION, Pending), each cause of breakage with the suggested solution,
  and how overall health is calculated.
- Language switch **for the plugin only**, through flags at the top,
  without changing the OJS dashboard language. The strings come from the
  plugin's own `.po` files: OJS loads a plugin's locale files only for the
  journal's current language, and would return the key itself for the
  others.
- **Check for a newer version** button, querying
  `linkIntegrityCheckerVersion.php` at Periódico Tchê Química. The query
  starts from the OJS server, not the browser, which avoids CORS on the
  target site. When there is a newer version, the download link appears
  right away.

### Changed
- The version history is now rendered as formatted HTML instead of plain
  Markdown text.

## [0.2.4] — 2026-09-24

### Added
- Donut charts for LINKS and DOI, in pure SVG — no external library, no
  CDN and no conflict with OJS's Vue.
- Footer with the partnership credits and links to both websites.
- "Version history" panel in the interface itself, reading this
  CHANGELOG. Single source: what is in the repository is what the journal
  manager sees on screen.

### Changed
- Count cards resized. They were out of proportion to their content: one
  digit took a block with 20px padding and a 2.2em font.
- Counters and charts now share the same horizontal band.

## [0.2.3] — 2026-09-24

### Fixed
- **CSS lost on OJS 3.5.** The dashboard rendered with no style at all.
  OJS 3.5 mounts Vue on `#app` and Vue's template compiler discards inline
  `<style>` and `<script>` ("Tags with side effect are ignored in client
  component templates"). The CSS went to `styles/linkIntegrity.css` and
  the JS to `js/linkIntegrity.js`, both registered in `<head>` through
  `addStyleSheet()` / `addJavaScript()`.
- The data (strings, articles, endpoint URL, CSRF token) now travels in
  `data-*` attributes of the container, which survive Vue rendering.
- The JS now runs on `DOMContentLoaded`, therefore after
  `pkp.registry.init()`. Before, it depended on parsing order.
- The browser tab title showed the raw translation key.

---

## [0.2.2] — 2026-09-23

### Fixed
- **Smarty compilation error 500 in `index.tpl`.** Inline CSS and
  JavaScript are now inside `{literal}...{/literal}`; the block is broken
  only on the four lines that interpolate variables (`$stringsJson`,
  `$articlesJson`, `$ajaxUrlJson`, `$licCsrfJson`). Compilation checked
  with an isolated Smarty 4.5.4 (not in a full OJS).

---

## [0.2.1] — 2026-09-23

### Fixed
- **Error 500 on OJS 3.4/3.5.** `fetchPublishedSubmissions()` called
  `Repo::submission()->getMany($collector)`, a method that does not exist
  in the Repository. It now uses `$collector->getMany()`. Run-time test on
  OJS 3.5 still pending.
- `buildArticleUrl()` falls back to `getId()` if `getBestId()` does not
  exist.

---

## [0.2.0] — 2026-09-01

Security hardening release, resulting from the code review done before
publication. **Recommended for every installation:** 0.1.0 must not be
installed in production.

### Fixed — security

- **Endpoint without authorization (critical).** 0.1.0 rendered the page
  and the JSON endpoint inside the `LoadHandler` hook callback itself,
  ending with `exit()`. That hook runs before OJS instantiates and
  authorizes any handler, so no permission check ever happened — the
  endpoint answered anonymous visitors, who could thereby induce outbound
  requests from the journal's server (SSRF). The plugin now hands a
  `LinkIntegrityHandler` to the router, whose `authorize()` applies
  `ContextAccessPolicy` with the Journal Manager and Site Administrator
  roles.
- **TLS verification disabled.** `CURLOPT_SSL_VERIFYPEER` was `false`. It
  is now `true`, together with `CURLOPT_SSL_VERIFYHOST = 2`.
- **No restriction on destination (SSRF).** Added: scheme allowlist
  (`http`/`https`), rejection of credentials embedded in the URL, port
  restriction, blocking of all private, loopback, link-local, CGNAT,
  multicast and reserved ranges (IPv4 and IPv6, including IPv4-mapped
  addresses), and pinning of the validated IP through `CURLOPT_RESOLVE`
  against DNS rebinding.
- **Redirects without revalidation.** Every hop is now fully revalidated
  by the policy above, so a redirect to `169.254.169.254` or `127.0.0.1`
  is blocked at the hop where it appears.
- **CSRF on the endpoint.** The single check now requires the POST method
  and the session's CSRF token, preventing a third party from using an
  authenticated manager's browser to trigger the outbound requests.
- **Isolation between journals.** The endpoint checks the submission's
  `contextId` and published status before answering.

### Fixed — robustness

- **Response size limit.** 32 KB of body and 64 KB of headers, aborting
  the transfer when the limit is reached. Before, a large galley was
  downloaded whole into memory just to get the HTTP code.
- **Relative `Location`.** Relative, protocol-relative (`//host/x`),
  root-absolute (`/x`) and `..` redirects are now resolved according to
  RFC 3986. Before, a `Location: /new-path` was treated as an absolute URL
  and the check failed with a false negative.
- **`CURLOPT_CONNECTTIMEOUT`** of 8 s, in addition to the 12 s total
  timeout.
- **DOI extraction** now accepts the value registered as a full URL
  (`https://doi.org/10.x/y`) or with a `doi:` prefix, and covers the `Doi`
  object of OJS 3.4+.
- **`version.xml` outside the PKP schema.** The file declared
  `<application>ojs2</application>`, which is the identifier of the **OJS
  core**, not of the plugin. Since OJS writes that value to the `product`
  column of the `versions` table, the plugin registered under the wrong
  name, colliding with the core's own record and breaking update
  detection. It is now `linkIntegrityChecker`, the same as the folder
  name. The `<name>`, `<category>` and `<compatibility>` elements, which do
  not exist in `lib/pkp/dtd/pluginVersion.dtd` and were silently ignored
  by the installer, were also removed; `<class>` was added; the element
  order required by the DTD was fixed; and the `DOCTYPE` now points to the
  standard relative path (`../../../lib/pkp/dtd/pluginVersion.dtd`)
  instead of an external URL.

- **Escaping in the dashboard.** The link text is now cut before HTML
  escaping (cutting after could split an entity in half), and all
  translated labels go through the escape.

### Added

- `SECURITY.md` — security model, deliberate decisions (including the
  allowlist for the journal's own host) and known limits.
- `classes/LinkIntegrityCompat.inc.php` — single compatibility layer
  between OJS 3.3, 3.4 and 3.5, instead of checks scattered through the
  code.
- `classes/LinkIntegrityHttpClient.inc.php` — hardened HTTP client,
  separate from the OJS data-reading logic.
- `pages/LinkIntegrityHandler.inc.php` — authorized handler.
- Button to open the dashboard in the plugin grid itself, a more stable
  path across versions than the management menu hook.
- Failure causes `tls` (invalid certificate) and `bloqueado` (URL blocked
  by the security policy), with solution text in the three languages.

### Compatibility note

Compatibility with OJS 3.5 remains **declared, not verified at run
time**: there was no test on a real installation. See the list of points
to validate in `SECURITY.md`.

---

## [0.1.0] — 2026-08-26

First version of the plugin. **Not recommended for use:** it contains the
security flaws fixed in 0.2.0 and was superseded before publication.

### Added
- Conversion of the original script's checking logic into a service class
  (`LinkIntegrityService`) integrated with the native OJS APIs
  (submissions, publications and galleys).
- Management page (`Integrity Checker`) reachable from the journal's
  management menu, restricted to Journal Managers and Site Administrators.
- Checking of the **links** of published articles (remote galleys or
  galleys generated by OJS itself), with redirect detection and
  classification of the likely cause of failure (404, 403, timeout, server
  error, other 4xx errors).
- **DOI** checking, testing resolution through `https://doi.org/`.
- Dashboard with health indicators per block (Links / DOI), overall
  system health, filters, search and single or batch testing.
- Report of causes and suggested solutions for the problems found.
- Full internationalization: **Portuguese (pt_BR)**, **English (en_US)**
  and **Spanish (es_ES)**.
- Compatibility with OJS 3.3, 3.4 and 3.5 (LTS), with automatic detection
  between the legacy DAO API and the Repository/Collector pattern
  introduced in OJS 3.4+.

### Out of scope in this version (planned for future versions)
- Checking article retractions through the Crossref API.
- Historical persistence of audit results in the database.
- Editor/manager comments on problem items.
- Automatic scheduling (Scheduled Tasks) and email notifications.
- Report export to PDF/CSV.

See `README.md` for installation, configuration and usage instructions.
