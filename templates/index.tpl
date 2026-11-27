{**
 * templates/index.tpl
 *
 * Copyright (c) 2026 Periódico Tchê Química
 * Copyright (c) 2026 WebSotto (https://websotto.com.br/)
 * Distributed under the GNU General Public License v3 (or later).
 *
 * Dashboard de auditoria de integridade de links e DOIs.
 * Os dados dos artigos e as strings traduzidas chegam já prontos em JSON
 * (montados em PHP, ver LinkIntegrityHandler::index) para evitar problemas
 * de escaping ao montar JSON dentro do template Smarty.
 *
 * O modificador "nofilter" é necessário porque estes valores já são JSON
 * válido: sem ele, versões do OJS que ligam o escape automático do Smarty
 * transformariam as aspas em entidades e quebrariam o parse.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="lic-topbar">
    {if $licLocales}
    <div class="lic-langbar">
    {foreach from=$licLocales item=loc}
        <a href="{$loc.url|escape}" class="lic-lang{if $loc.current} active{/if}"
           hreflang="{$loc.code|replace:'_':'-'|escape}" title="{$loc.label|escape}">
            {if $loc.flag}<span class="lic-flag">{$loc.flag nofilter}</span> {/if}{$loc.label|escape}
        </a>
    {/foreach}
    </div>
    {/if}
    <label class="lic-theme">
        {$s.themeLabel|escape}
        <select id="licTheme">
            <option value="tq">{$s.themeTq|escape}</option>
            <option value="sotto">{$s.themeSotto|escape}</option>
            <option value="deboni">{$s.themeDeBoni|escape}</option>
            <option value="pastel">{$s.themePastel|escape}</option>
            <option value="mono">{$s.themeMono|escape}</option>
        </select>
    </label>
</div>

<h1 class="app__pageHeading">{$s.displayName|escape}</h1>
<p>{$s.subtitle|escape}</p>

<div id="licApp"
     data-strings="{$stringsJson|escape}"
     data-rows="{$articlesJson|escape}"
     data-ajax="{$ajaxUrl|escape}"
     data-csrf="{$licCsrf|escape}"
     data-summary-url="{$licSummaryUrl|escape}">
    <div id="licAlert" class="lic-alert"></div>

    <div class="lic-top">
        <div class="lic-blocks">
            <div class="lic-group">
                <div class="lic-group-header">{$s.blockLinks|escape}</div>
                <div class="lic-cards" id="licCardsLink"></div>
            </div>
            <div class="lic-group">
                <div class="lic-group-header">{$s.blockDoi|escape}</div>
                <div class="lic-cards" id="licCardsDoi"></div>
            </div>
            <div class="lic-health">
                <div class="lic-card">
                    <div class="num" id="licHealthLink">–</div>
                    <div>{$s.healthLinksLabel|escape}</div>
                    <div class="sub" id="licHealthLinkSub"></div>
                </div>
                <div class="lic-card">
                    <div class="num" id="licHealthDoi">–</div>
                    <div>{$s.healthDoiLabel|escape}</div>
                    <div class="sub" id="licHealthDoiSub"></div>
                </div>
                <div class="lic-card lic-general">
                    <div class="num" id="licGenHealth">–</div>
                    <div>{$s.healthOverallLabel|escape}</div>
                    <div class="sub" id="licGenHealthSub"></div>
                </div>
            </div>
        </div>

        <div class="lic-charts">
            <div class="lic-chart">
                <h4>{$s.blockLinks|escape}</h4>
                <div id="licChartLink"></div>
                <div class="lic-legend" id="licLegendLink"></div>
            </div>
            <div class="lic-chart">
                <h4>{$s.blockDoi|escape}</h4>
                <div id="licChartDoi"></div>
                <div class="lic-legend" id="licLegendDoi"></div>
            </div>
        </div>
    </div>


    <details class="lic-guide">
        <summary>{$s.guideTitle|escape}</summary>
        <div class="lic-guide-body">
            <p>{$s.guideIntro|escape}</p>

            <h4>{$s.guideStatuses|escape}</h4>
            <dl>
                <dt class="lic-st-ok">{$s.statusOk|escape}</dt><dd>{$s.explainOk|escape}</dd>
                <dt class="lic-st-broken">{$s.statusBroken|escape}</dt><dd>{$s.explainBroken|escape}</dd>
                <dt class="lic-st-conteudo">{$s.statusContent|escape}</dt><dd>{$s.explainContent|escape}</dd>
                <dt class="lic-st-atencao">{$s.statusAttention|escape}</dt><dd>{$s.explainAttention|escape}</dd>
                <dt class="lic-st-pending">{$s.statusPending|escape}</dt><dd>{$s.explainPending|escape}</dd>
            </dl>

            <h4>{$s.guideCauses|escape}</h4>
            <dl>
                <dt>{$s.causeNotFound|escape}</dt><dd>{$s.solutionNotFound|escape}</dd>
                <dt>{$s.causeTimeout|escape}</dt><dd>{$s.solutionTimeout|escape}</dd>
                <dt>{$s.causeDenied|escape}</dt><dd>{$s.solutionDenied|escape}</dd>
                <dt>{$s.causeServerError|escape}</dt><dd>{$s.solutionServerError|escape}</dd>
                <dt>{$s.causeOther4xx|escape}</dt><dd>{$s.solutionOther4xx|escape}</dd>
                <dt>{$s.causeBlocked|escape}</dt><dd>{$s.solutionBlocked|escape}</dd>
                <dt>{$s.causeTls|escape}</dt><dd>{$s.solutionTls|escape}</dd>
                <dt>{$s.causeRedirect|escape}</dt><dd>{$s.solutionRedirect|escape}</dd>
                <dt>{$s.causeErrorPage|escape}</dt><dd>{$s.solutionErrorPage|escape}</dd>
                <dt>{$s.causeInvalidFile|escape}</dt><dd>{$s.solutionInvalidFile|escape}</dd>
                <dt>{$s.causeTruncatedFile|escape}</dt><dd>{$s.solutionTruncatedFile|escape}</dd>
                <dt>{$s.causeEmptyFile|escape}</dt><dd>{$s.solutionEmptyFile|escape}</dd>
            </dl>

            <p class="lic-guide-note">{$s.guideWeighted|escape}</p>
        </div>
    </details>

    <div id="licReportPanel" class="lic-report" style="display:none">
        <h3>{$s.reportTitle|escape}</h3>
        <div id="licReportBody"></div>
    </div>

    <div class="lic-settings" data-url="{$licSettingsUrl|escape}">
        <label class="lic-switch">
            <input type="checkbox" id="licOptPublic" {if $licPublicPage}checked{/if}>
            <span>{$s.optPublicPage|escape}</span>
        </label>
        <label class="lic-switch">
            <input type="checkbox" id="licOptFooter" {if $licPublicFooter}checked{/if}>
            <span>{$s.optPublicFooter|escape}</span>
        </label>
        <a class="lic-public-link" id="licPublicLink" href="{$licStatusUrl|escape}" target="_blank"
           rel="noopener noreferrer" {if !$licPublicPage}hidden{/if}>{$s.optOpenPublic|escape}</a>
        <span id="licOptMsg" class="lic-opt-msg"></span>
    </div>

    <div class="lic-toolbar">
        <div class="lic-toggle" id="licFilterToggle">
            <button data-f="all" class="active">{$s.filterAll|escape}</button>
            <button data-f="broken">{$s.filterBroken|escape}</button>
            <button data-f="conteudo">{$s.filterContent|escape}</button>
            <button data-f="atencao">{$s.filterAttention|escape}</button>
            <button data-f="ok">{$s.filterOk|escape}</button>
        </div>
        <div class="lic-toggle lic-scope" id="licScopeToggle" title="{$s.scopeTip|escape}">
            <button data-s="both" class="active">{$s.scopeBoth|escape}</button>
            <button data-s="doi">{$s.scopeDoi|escape}</button>
            <button data-s="link">{$s.scopeLink|escape}</button>
        </div>
        <button class="lic-btn" id="licBtnTestAll">{$s.testAll|escape}</button>
        <button class="lic-btn lic-btn-report" id="licBtnReport">{$s.report|escape}</button>
        <input type="text" id="licSearch" placeholder="{$s.search|escape}">
    </div>
    <p class="lic-progress">{$s.progress|escape}: <span id="licProg">0</span> / {$articles|@count}</p>

    <table class="lic-table">
        <colgroup>
            <col class="c-id"><col class="c-title"><col class="c-doi">
            <col class="c-link"><col class="c-st"><col class="c-st"><col class="c-act">
        </colgroup>
        <thead><tr>
            <th>{$s.colId|escape}</th>
            <th>{$s.colTitle|escape}</th>
            <th>{$s.colDoi|escape}</th>
            <th>{$s.colLink|escape}</th>
            <th>{$s.colStatusDoi|escape}</th>
            <th>{$s.colStatusLink|escape}</th>
            <th>{$s.colAction|escape}</th>
        </tr></thead>
        <tbody id="licBody"></tbody>
    </table>

    {if $articles|@count == 0}
        <p class="lic-empty">{$s.noArticles|escape}</p>
    {/if}
</div>

<div class="lic-footer">
    <div class="lic-footer-bar">
        {if $licHistoryHtml}
            <details class="lic-history">
                <summary>{$s.history|escape}</summary>
                <div class="lic-md">{$licHistoryHtml nofilter}</div>
            </details>
        {/if}

        <span class="lic-credits">
            {$s.credits|escape}
            <a href="https://www.tchequimica.com/" target="_blank" rel="noopener noreferrer">Periódico Tchê Química</a>
            &amp;
            <a href="https://websotto.com.br/" target="_blank" rel="noopener noreferrer">WebSotto</a>
            <span class="ver">— v{$licVersion|escape}</span>
        </span>

        <span class="lic-footer-actions">
            <span id="licUpdateMsg" class="lic-update-msg"></span>
            <button type="button" class="lic-btn lic-btn-sm" id="licBtnUpdate"
                    data-url="{$licUpdateUrl|escape}"
                    title="{$s.checkUpdateTip|escape}">{$s.checkUpdate|escape}</button>
        </span>
    </div>
</div>

{/block}
