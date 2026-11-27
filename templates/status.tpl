{**
 * templates/status.tpl
 *
 * Página pública de transparência do Link Integrity Checker.
 *
 * Só agregados: nenhuma lista de artigos, nenhum endereço individual,
 * nenhum botão que dispare verificação. O que se mostra aqui é o retrato
 * da última auditoria feita pela equipe editorial.
 *}
{include file="frontend/components/header.tpl" pageTitle=$pageTitle}

<div class="page page_linkintegrity_status" id="licApp">
    <h1>{$s.publicTitle|escape}</h1>

    {if $licSummary}
        <p class="lic-public-intro">
            {$s.publicIntro|escape}
            {if $licCheckedAt} <strong>{$s.publicLastCheck|escape} {$licCheckedAt|escape}.</strong>{/if}
        </p>

        <div class="lic-public-grid">
            <div class="lic-card lic-general">
                <div class="num">{$licSummary.total|escape}</div>
                <div>{$s.publicArticles|escape}</div>
            </div>
            <div class="lic-card lic-general">
                <div class="num">{if $licCharts.link.pct === null}–{else}{$licCharts.link.pct|escape}%{/if}</div>
                <div>{$s.healthLinksLabel|escape}</div>
                <div class="sub">{$licCharts.link.checked|escape} {$s.publicChecked|escape}</div>
            </div>
            <div class="lic-card lic-general">
                <div class="num">{if $licCharts.doi.pct === null}–{else}{$licCharts.doi.pct|escape}%{/if}</div>
                <div>{$s.healthDoiLabel|escape}</div>
                <div class="sub">{$licCharts.doi.checked|escape} {$s.publicChecked|escape}</div>
            </div>
        </div>

        <div class="lic-charts">
            <div class="lic-chart">
                <h4>{$s.blockLinks|escape}</h4>
                {$licCharts.link.svg nofilter}
            </div>
            <div class="lic-chart">
                <h4>{$s.blockDoi|escape}</h4>
                {$licCharts.doi.svg nofilter}
            </div>
        </div>

        <div class="lic-legend lic-public-legend">
            <span><i style="background:#28a745"></i>{$s.statusOk|escape}</span>
            <span><i style="background:#dc3545"></i>{$s.statusBroken|escape}</span>
            <span><i style="background:#e07b39"></i>{$s.statusContent|escape}</span>
            <span><i style="background:#f9a825"></i>{$s.statusAttention|escape}</span>
        </div>

        <p class="lic-public-note">{$s.publicNote|escape}</p>
    {else}
        <p class="lic-public-intro">{$s.publicNoData|escape}</p>
    {/if}

    {if $licShowFooter}
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
            </div>
        </div>
    {/if}
</div>

{include file="frontend/components/footer.tpl"}
