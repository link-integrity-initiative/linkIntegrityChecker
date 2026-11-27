# Link Integrity Checker — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.3%20%7C%203.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-0.5.4.2-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**English** · [Português](README.pt_BR.md)

**⬇️ Install package:** [linkIntegrityChecker-0.5.4.2.tar.gz](https://github.com/link-integrity-initiative/linkIntegrityChecker/releases/download/0.5.4.2/linkIntegrityChecker-0.5.4.2.tar.gz) (OJS 3.3, 3.4 and 3.5) — or browse all [Releases](../../releases).

> **Maintained by the [Link Integrity Initiative](https://github.com/link-integrity-initiative).**

## Compatibility & branches

| OJS version | Branch | Plugin release |
|---|---|---|
| 3.3, 3.4, 3.5 | `stable-3_5_0` *(default)* | 0.5.4.2 |

One package serves the three OJS lines (a compatibility layer picks the
right API at run time). Do not rename the folder: it must be
`linkIntegrityChecker`.

**Version:** 0.5.4.2 · **Compatibility:** OJS 3.3, 3.4 and 3.5 (LTS) · **License:** GPL v3+

A generic plugin for Open Journal Systems (OJS) that audits, in one click,
the **links** and **DOIs** of the articles published in your journal, and
tells you what is broken and why.

Originally an internal tool of **Periódico Tchê Química**, adapted by
**WebSotto** for the OJS community. See [CREDITS.md](CREDITS.md) for
authorship (CRediT taxonomy, in Portuguese) and
[CHANGELOG.en.md](CHANGELOG.en.md) for the history (original in
Portuguese: [CHANGELOG.md](CHANGELOG.md)). The dashboard shows the history
in Portuguese or English according to the interface language.

---

## What it does

- Lists every **published** article in the journal.
- For each article it checks:
  - **Link**: the file download address (remote galley or OJS-generated),
    following redirects and reading the final HTTP status.
  - **File content**: whether the response really is a valid PDF, and not
    an error page served with status 200, an empty file or a truncated one.
  - **DOI**: whether it resolves through `https://doi.org/`.
- Classifies each failure by likely cause (not found, access denied,
  timeout, server error, invalid TLS certificate, etc.) and suggests a fix.
- Dashboard with overall health, charts, filters, search and a report
  grouped by cause.
- Optional **public transparency page** (off by default) showing only the
  aggregate numbers of the last check.
- Interface in 8 languages (see [Languages](#languages)).
- **Does not change journal content.** It only stores its own settings
  (see [Stored data](#stored-data)).

---

## Requirements

- OJS 3.3.0, 3.4.x or 3.5.x (LTS).
- PHP with the **cURL** extension.
- Outbound HTTP(S) allowed from the server.
- **Journal Manager** or **Site Administrator** role.

---

## Installation

**Through the interface (recommended):** go to **Settings → Website →
Plugins → Upload a new plugin** and upload
`linkIntegrityChecker-x.y.z.tar.gz`.

**Manually:** extract the package so that you get:

```
[ojs-folder]/plugins/generic/linkIntegrityChecker/
├── classes/
├── js/
├── locale/
├── pages/
├── styles/
├── templates/
├── index.php
├── LinkIntegrityCheckerPlugin.inc.php
└── version.xml
```

Then, under **Settings → Website → Plugins**, find **"Link Integrity
Checker"** among the *Generic Plugins* and enable it. No command line or
database changes are needed.

> **Installing from GitHub:** the folder must be named
> `linkIntegrityChecker`. GitHub's ZIP download uses another name (e.g.
> `linkIntegrityChecker-stable-3_5_0`); rename it first, or use the `.tar.gz`
> attached to each release.

---

## Usage

1. In the plugin list, click **"Open checker"**, use the side menu item
   (OJS 3.4/3.5), or go to
   `https://your-journal.org/index.php/[journal-path]/linkintegrity`.
2. Articles start as **Pending** (not tested yet) or **Attention** (no DOI
   or no published galley).
3. **"Test All"** runs the checks in batches of 3. **"Test"** on a row
   checks a single article.
4. Use the filters, the scope (DOI, link or both) and the search box (ID,
   title or DOI).
5. **"View Report"** groups problems by cause, with the suggested fix and
   the affected IDs.

### Public transparency page

Two switches on the dashboard, saved immediately:

- **Publish transparency page**: enables
  `.../[journal-path]/linkintegrity/status`. While off, it returns 404.
- **Show credits and history on it**: adds the credits and changelog footer to
  the public page.

The page shows only the aggregates from the last **"Test All"** run: total
articles, percentages and charts. It never lists articles or addresses and
cannot trigger a check.

---

## Languages

Brazilian Portuguese, English and Spanish were reviewed by the team.
French (Canada), Italian, German, Indonesian and Turkish are **unreviewed
machine translations**. Corrections are welcome.

The dashboard language bar lists the languages **enabled in the journal**,
and is hidden when only one is enabled.

OJS 3.4 changed locale codes: `en_US` became `en`, `es_ES` became `es`, and
so on (`pt_BR` and `fr_CA` did not change). The package therefore ships
both spellings:

| OJS 3.3 | OJS 3.4 / 3.5 |
|---|---|
| `en_US`, `es_ES`, `de_DE`, `it_IT`, `id_ID`, `tr_TR` | `en`, `es`, `de`, `it`, `id`, `tr` |
| `pt_BR`, `fr_CA` | `pt_BR`, `fr_CA` |

**When editing a translation, change both folders of that language** (their
content is identical).

---

## Stored data

The plugin does not modify articles, DOIs or files. It only stores its own
per-journal settings in the OJS `plugin_settings` table:

| Key | Content |
|---|---|
| `enabled` | Plugin enabled (written by OJS itself) |
| `publicPage` | Public page on/off |
| `publicFooter` | Credits and history on the public page |
| `summary` | Aggregate counts of the last full check, and its date |

No list of articles or addresses is stored.

## Update check (privacy)

The **"Check for a newer version"** button in the dashboard footer contacts an
external server only when someone clicks it. The **journal's server** then
asks GitHub's public API for the latest release of this repository
(`https://api.github.com/repos/link-integrity-initiative/linkIntegrityChecker/releases/latest`)
and compares it with the installed version. If there is a newer one, the
button shows a link to its installable package.

It is an anonymous GET: **nothing about the journal or its users is sent**
(up to 0.5.3 the journal name and site address went to the Periódico Tchê
Química server). The request comes from the server, so the IP seen by
GitHub is the hosting provider's, not the clicking user's. If you do not
want this contact, do not use the button.

---

## Security

Summary (full details, in Portuguese, in [SECURITY.md](SECURITY.md)):

- Dashboard and endpoints require **Journal Manager** or **Site
  Administrator**, enforced by OJS's own authorization. Only the public
  page, when enabled, is open.
- Endpoints accept **POST** only, with a mandatory **CSRF token**.
- Only **public http/https** destinations: private, loopback, link-local
  (including `169.254.169.254`), CGNAT and reserved ranges are blocked for
  IPv4 and IPv6. The validated IP is pinned and **every redirect is
  re-validated**.
- TLS is verified. Reads are capped at 32 KB of body; files are never
  downloaded in full.

To report a vulnerability, use GitHub's **private vulnerability reporting**
(Security tab of the repository), not a public issue.

---

## FAQ

**Does the audit take long on large journals?**
Yes. Each link and DOI is a real HTTP request, with up to 12 s timeout per
attempt. Run it at off-peak hours.

**My journal is in English and the dashboard shows keys such as
`plugins.generic.linkIntegrity.displayName`.**
That happened up to 0.5.2 on OJS 3.4/3.5 (see [Languages](#languages)).
Upgrade to 0.5.3 or later.

**A link that opens in the browser shows "URL blocked by the security
policy".**
The address resolves to an internal IP or uses a port/protocol that is not
allowed. See the allowlist section in [SECURITY.md](SECURITY.md).

**Links started failing with "Invalid TLS certificate".**
Not a regression: v0.1 disabled certificate checks. Readers get the same
browser warning.

**Error mentioning `Repo::submission()` or a missing DAO.**
An API difference not covered by the compatibility layer. Please open an
issue with your exact OJS version (e.g. 3.4.0-9) and the full error.

---

## Credits

- **Dr. Luis Alcides Brandini De Boni**, Periódico Tchê Química
  (Conceptualization, Software (original), Validation)
- **Bruno Sotto**, [WebSotto](https://websotto.com.br/)
  (Software (OJS plugin conversion), Testing)

## License

Copyright (c) 2026 Periódico Tchê Química and WebSotto.

GNU General Public License v3.0 or later, the same license as OJS. Full
text in [LICENSE](LICENSE).
