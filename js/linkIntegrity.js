// Link Integrity Checker — logica do dashboard.
// Executa em DOMContentLoaded, ou seja, depois de pkp.registry.init(),
// que monta o Vue e reconstroi o conteudo de #app. Rodar antes disso
// faria os listeners se perderem na remontagem.
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Dados vindos do PHP via atributos data-* do container (ver
    // LinkIntegrityHandler::index). Atributos sobrevivem a renderizacao
    // do Vue; um <script> com JSON embutido nao sobreviveria.
    var root = document.getElementById('licApp');
    if (!root) return;
    var T = JSON.parse(root.dataset.strings);
    var ROWS = JSON.parse(root.dataset.rows);
    var AJAX_URL = root.dataset.ajax;
    var CSRF = root.dataset.csrf;

    var diag = {};
    ROWS.forEach(function (r) {
        diag[r.id] = {
            doi: { state: r.doi ? 'pending' : 'atencao' },
            link: { state: r.link ? 'pending' : 'atencao' }
        };
    });

    function showAlert(msg) {
        var el = document.getElementById('licAlert');
        el.textContent = msg;
        el.style.display = 'block';
    }

    // Texto de apoio exibido ao passar o mouse sobre qualquer indicador.
    function explainState(state) {
        return {
            ok: T.explainOk,
            broken: T.explainBroken,
            conteudo: T.explainContent,
            atencao: T.explainAttention,
            pending: T.explainPending
        }[state] || '';
    }

    function calcStats(field) {
        var s = { ok: 0, broken: 0, conteudo: 0, atencao: 0, pending: 0 };
        ROWS.forEach(function (r) { s[diag[r.id][field].state]++; });
        return s;
    }

    function calcPct(s) {
        // Arquivo defeituoso conta como falha: o leitor nao consegue ler.
        var total = s.ok + s.broken + s.conteudo;
        if (total === 0) return null;
        return (s.ok / total) * 100;
    }

    // Tambem tira as cores da paleta ativa, senao o rotulo de saude
    // continuaria verde-padrao numa paleta pastel ou monocromatica.
    function healthLabel(pct) {
        var c = themeColors();
        var primary = getComputedStyle(document.body).getPropertyValue('--lic-primary').trim() || '#2c5f7f';
        if (pct >= 95) return [T.healthExcellent, c.ok];
        if (pct >= 80) return [T.healthGood, primary];
        if (pct >= 50) return [T.healthAttention, c.atencao];
        return [T.healthCritical, c.broken];
    }

    function renderCards(containerId, field) {
        var s = calcStats(field);
        var el = document.getElementById(containerId);
        el.innerHTML =
            cardHtml(s.ok, T.ok, 'ok') +
            cardHtml(s.broken, T.broken, 'broken') +
            cardHtml(s.conteudo, T.content, 'conteudo') +
            cardHtml(s.atencao, T.atencao, 'atencao') +
            cardHtml(s.pending, T.pending, 'pending');
    }

    function cardHtml(num, label, state) {
        var tip = escapeHtml(label + ' — ' + explainState(state));
        return '<div class="lic-card lic-tip" title="' + tip + '" tabindex="0" aria-label="' + tip + '">' +
            '<div class="num">' + num + '</div><div>' + escapeHtml(label) + '</div></div>';
    }

    // Donut em SVG puro: sem biblioteca externa, sem CDN e sem conflito
    // com o Vue do OJS. Cada fatia e um arco desenhado com stroke-dasharray
    // sobre um circulo de raio fixo. O <title> dentro de cada arco e o que
    // o navegador mostra ao passar o mouse sobre a fatia.
    // As cores dos graficos saem das mesmas variaveis CSS que o resto da
    // interface: trocar a paleta muda tudo de uma vez, sem cor duplicada
    // entre a folha de estilo e este arquivo.
    function themeColors() {
        var cs = getComputedStyle(document.body);
        var pick = function (name, fallback) {
            var v = cs.getPropertyValue(name);
            return v && v.trim() ? v.trim() : fallback;
        };
        return {
            ok: pick('--lic-ok', '#28a745'),
            broken: pick('--lic-broken', '#dc3545'),
            conteudo: pick('--lic-content', '#e07b39'),
            atencao: pick('--lic-warn', '#f9a825'),
            pending: pick('--lic-pending', '#ccc')
        };
    }

    var LIC_COLORS = themeColors();

    function stateLabel(k) {
        return { ok: T.ok, broken: T.broken, conteudo: T.content, atencao: T.atencao, pending: T.pending }[k];
    }

    function renderDonut(id, field) {
        var el = document.getElementById(id);
        if (!el) return;
        var s = calcStats(field);
        var total = s.ok + s.broken + s.atencao + s.pending;
        var R = 52, C = 2 * Math.PI * R, off = 0, arcs = '';
        ['ok', 'broken', 'conteudo', 'atencao', 'pending'].forEach(function (k) {
            if (!s[k]) return;
            var len = (s[k] / total) * C;
            arcs += '<circle cx="70" cy="70" r="' + R + '" fill="none" stroke="' + LIC_COLORS[k] +
                '" stroke-width="22" stroke-dasharray="' + len.toFixed(2) + ' ' + (C - len).toFixed(2) +
                '" stroke-dashoffset="' + (-off).toFixed(2) + '" transform="rotate(-90 70 70)"><title>' +
                escapeHtml(stateLabel(k) + ': ' + s[k] + ' — ' + explainState(k)) + '</title></circle>';
            off += len;
        });
        var pct = calcPct(s);
        el.innerHTML = '<svg viewBox="0 0 140 140" width="140" height="140" role="img" aria-label="' +
            escapeHtml(field === 'doi' ? T.blockDoi : T.blockLinks) + '">' + arcs +
            '<text class="center" x="70" y="75" text-anchor="middle">' +
            (pct === null ? '–' : pct.toFixed(0) + '%') + '</text></svg>';
    }

    function renderLegend(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = ['ok', 'broken', 'conteudo', 'atencao', 'pending'].map(function (k) {
            var tip = escapeHtml(stateLabel(k) + ' — ' + explainState(k));
            return '<span class="lic-tip" title="' + tip + '" tabindex="0"><i style="background:' +
                LIC_COLORS[k] + '"></i>' + escapeHtml(stateLabel(k)) + '</span>';
        }).join('');
    }

    // Saude geral: media PONDERADA PELO VOLUME de verificacoes, e nao a
    // media simples dos dois percentuais. A media simples dava 100% quando
    // os links iam bem e nenhum DOI tinha sido verificado, porque a
    // dimensao vazia simplesmente sumia da conta. Aqui os dois conjuntos
    // entram no mesmo numerador e denominador, e a cobertura e exibida ao
    // lado: um indice alto sobre poucas verificacoes nao e um bom sinal.
    function setHealthCard(numId, subId, stats, coverageBase) {
        var he = document.getElementById(numId);
        var sub = document.getElementById(subId);
        if (!he) return;
        var pct = calcPct(stats);
        var checked = stats.ok + stats.broken + stats.conteudo;

        if (pct === null) {
            he.textContent = '–';
            he.style.color = '#999';
            he.removeAttribute('title');
        } else {
            var lbl = healthLabel(pct);
            he.textContent = lbl[0];
            he.style.color = lbl[1];
            he.title = pct.toFixed(1) + '%';
        }
        if (sub) {
            sub.textContent = checked === 0
                ? T.coverageNone
                : (pct.toFixed(1) + '% — ' +
                   T.coverage.replace('%1', checked).replace('%2', coverageBase));
        }
    }

    function updateGeneralHealth() {
        var sl = calcStats('link'), sd = calcStats('doi');
        var total = ROWS.length;

        setHealthCard('licHealthLink', 'licHealthLinkSub', sl, total);
        setHealthCard('licHealthDoi', 'licHealthDoiSub', sd, total);

        // Ponderada: soma os dois conjuntos antes de dividir.
        setHealthCard('licGenHealth', 'licGenHealthSub', {
            ok: sl.ok + sd.ok,
            broken: sl.broken + sd.broken,
            conteudo: (sl.conteudo || 0) + (sd.conteudo || 0),
            atencao: sl.atencao + sd.atencao,
            pending: sl.pending + sd.pending
        }, total * 2);
    }

    var doneRows = 0;
    function refreshAll() {
        renderCards('licCardsLink', 'link');
        renderCards('licCardsDoi', 'doi');
        renderDonut('licChartLink', 'link');
        renderDonut('licChartDoi', 'doi');
        updateGeneralHealth();
        document.getElementById('licProg').textContent = doneRows;
    }

    function statusDisplay(st) {
        if (st.state === 'atencao') return [T.atencao, 'lic-st-atencao'];
        if (st.state === 'ok') return [T.ok, 'lic-st-ok'];
        if (st.state === 'conteudo') return [T.content, 'lic-st-conteudo'];
        if (st.state === 'broken') return [T.broken, 'lic-st-broken'];
        return [T.pending, 'lic-st-pending'];
    }

    // Escapa tambem aspas: o resultado vai para dentro de atributos
    // (href, title, id). So com textContent/innerHTML as aspas passam, e uma
    // URL de galley remoto com " fecharia o atributo e injetaria outros.
    function escapeHtml(s) {
        return (s === null || s === undefined ? '' : String(s))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // So http(s) vira link clicavel. Barra javascript:, data: e afins, que
    // passariam pelo escape por nao terem caracteres especiais.
    function safeUrl(u) {
        return /^https?:\/\//i.test(String(u || '').trim()) ? String(u).trim() : '';
    }

    var body = document.getElementById('licBody');
    function renderRows(list) {
        body.innerHTML = '';
        list.forEach(function (r) {
            var d = diag[r.id];
            var doiS = statusDisplay(d.doi), linkS = statusDisplay(d.link);
            var tr = document.createElement('tr');
            tr.id = 'licRow' + r.id;
            // O corte é feito ANTES do escape: cortar depois poderia
            // partir uma entidade HTML (&amp;) ao meio.
            var linkLabel = r.link.length > 40 ? r.link.substring(0, 40) + '…' : r.link;
            var linkCell = safeUrl(r.link)
                ? '<a href="' + escapeHtml(safeUrl(r.link)) + '" target="_blank" rel="noopener noreferrer" title="' +
                  escapeHtml(r.link) + '">' + escapeHtml(linkLabel) + '</a>'
                : escapeHtml(linkLabel);
            tr.innerHTML =
                '<td>' + escapeHtml(r.id) + '</td>' +
                '<td>' + escapeHtml((r.titulo || '').substring(0, 60)) + '</td>' +
                '<td class="c-break">' + escapeHtml(r.doi || '') + '</td>' +
                '<td class="c-break">' + linkCell + '</td>' +
                '<td id="licStDoi' + escapeHtml(r.id) + '" class="' + doiS[1] + '">' + escapeHtml(doiS[0]) + '</td>' +
                '<td id="licStLink' + escapeHtml(r.id) + '" class="' + linkS[1] + '">' + escapeHtml(linkS[0]) + '</td>' +
                '<td><button class="lic-btn" data-test-id="' + escapeHtml(r.id) + '">' + escapeHtml(T.testOne) + '</button></td>';
            body.appendChild(tr);
        });
    }

    var currentFilter = 'all';
    var currentScope = 'both';
    var currentQuery = '';
    function statusMatches(st, f) {
        if (f === 'all') return true;
        // "Problemas" reune tudo que impede o leitor de chegar ao arquivo,
        // seja endereco que nao responde ou arquivo defeituoso.
        if (f === 'broken') return st.state === 'broken' || st.state === 'conteudo';
        return st.state === f;
    }
    function applyFilter() {
        var q = currentQuery;
        var filtered = ROWS.filter(function (r) {
            var matchQ = !q || String(r.id).indexOf(q) !== -1 ||
                (r.titulo || '').toLowerCase().indexOf(q) !== -1 ||
                (r.doi || '').toLowerCase().indexOf(q) !== -1;
            if (!matchQ) return false;
            var d = diag[r.id];
            // O escopo decide QUAL teste o filtro de estado consulta. Com
            // "Ambos", basta um dos dois casar — util para achar problemas,
            // mas faz o filtro OK mostrar ate linhas defeituosas, porque
            // nelas a outra coluna esta OK. Escolhendo DOI ou Link, a
            // pergunta passa a ser sobre um teste so, e a resposta fica
            // sem ambiguidade.
            if (currentScope === 'doi') return statusMatches(d.doi, currentFilter);
            if (currentScope === 'link') return statusMatches(d.link, currentFilter);
            return statusMatches(d.doi, currentFilter) || statusMatches(d.link, currentFilter);
        });
        renderRows(filtered);
    }

    function setCellDisplay(id, field, st) {
        var el = document.getElementById((field === 'doi' ? 'licStDoi' : 'licStLink') + id);
        if (!el) return;
        var r = statusDisplay(st);
        el.textContent = r[0]; el.className = r[1];
        // O codigo HTTP aparece tambem quando o resultado e OK: sem isso
        // nao da para distinguir um 200 legitimo de um 200 devolvido por
        // pagina de erro amigavel ou por tela de login.
        var parts = [];
        if (st.code) parts.push('HTTP ' + st.code);
        if (st.err) parts.push(st.err);
        parts.push(explainState(st.state));
        el.title = parts.join(' — ');
    }

    function applyResult(id, field, resultado) {
        var state = 'ok';
        if (resultado.atencao) state = 'atencao';
        else if (resultado.conteudo) state = 'conteudo';
        else if (!resultado.ok) state = 'broken';

        var novo = state === 'atencao'
            ? { state: 'atencao' }
            : { state: state, code: resultado.code, err: resultado.err, causa: resultado.causa };
        diag[id][field] = novo;
        setCellDisplay(id, field, novo);
    }

    var stopped = false;

    async function testOne(id) {
        if (stopped) return;
        var d = diag[id];
        var needsLink = d.link.state !== 'atencao';
        var needsDoi = d.doi.state !== 'atencao';
        if (!needsLink && !needsDoi) { doneRows++; refreshAll(); return; }

        if (needsLink) { var el1 = document.getElementById('licStLink' + id); if (el1) { el1.textContent = T.testing; el1.className = 'lic-st-pending'; } }
        if (needsDoi) { var el2 = document.getElementById('licStDoi' + id); if (el2) { el2.textContent = T.testing; el2.className = 'lic-st-pending'; } }

        try {
            // POST + token CSRF: o endpoint dispara requisições de saída do
            // servidor, então não pode ser acionável por uma simples URL.
            var params = new URLSearchParams();
            params.set('id', id);
            params.set('csrfToken', CSRF);

            var resp = await fetch(AJAX_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });

            if (resp.status === 403) {
                stopped = true;
                showAlert(T.sessionExpired);
                return;
            }

            var j = await resp.json();
            if (j.error) {
                var aviso = { atencao: true };
                if (needsLink) applyResult(id, 'link', aviso);
                if (needsDoi) applyResult(id, 'doi', aviso);
            } else {
                if (needsLink && j.link) applyResult(id, 'link', j.link);
                if (needsDoi && j.doi) applyResult(id, 'doi', j.doi);
            }
            var row = document.getElementById('licRow' + id);
            if (row) row.classList.add('lic-row-fade');
        } catch (e) {
            var falha = { ok: false, code: 0, err: String(e), causa: 'timeout' };
            if (needsLink) applyResult(id, 'link', falha);
            if (needsDoi) applyResult(id, 'doi', falha);
        }
        doneRows++;
        refreshAll();
    }

    var idx = 0, running = false, workersLeft = 0;
    async function worker() {
        while (idx < ROWS.length && !stopped) {
            var r = ROWS[idx++];
            await testOne(r.id);
            await new Promise(function (res) { setTimeout(res, 120); });
        }
        workersLeft--;
        if (workersLeft === 0 && !stopped) saveSummary();
    }

    // Guarda so os agregados, para alimentar a pagina publica. Nenhuma
    // lista de artigos sai daqui: a pagina publica nao deve poder virar um
    // mapa dos pontos frageis do site.
    async function saveSummary() {
        var url = root.dataset.summaryUrl;
        if (!url) return;
        var sl = calcStats('link'), sd = calcStats('doi');
        var params = new URLSearchParams();
        params.set('csrfToken', CSRF);
        params.set('total', ROWS.length);
        params.set('linkOk', sl.ok); params.set('linkBroken', sl.broken);
        params.set('linkContent', sl.conteudo); params.set('linkAttention', sl.atencao);
        params.set('doiOk', sd.ok); params.set('doiBroken', sd.broken);
        params.set('doiContent', sd.conteudo); params.set('doiAttention', sd.atencao);
        try {
            await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
        } catch (e) {
            // Resumo e acessorio: a verificacao ja cumpriu seu papel na tela.
        }
    }

    document.getElementById('licBtnTestAll').addEventListener('click', function () {
        if (running) return;
        running = true;
        workersLeft = 3;
        for (var w = 0; w < 3; w++) worker();
    });

    body.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-test-id]');
        if (btn) testOne(parseInt(btn.getAttribute('data-test-id'), 10));
    });

    document.getElementById('licBtnReport').addEventListener('click', function () {
        var panel = document.getElementById('licReportPanel');
        var groups = {};
        ROWS.forEach(function (r) {
            ['doi', 'link'].forEach(function (field) {
                var d = diag[r.id][field];
                if (!d || (d.state !== 'broken' && d.state !== 'conteudo')) return;
                var key = d.causa + '|' + field;
                if (!groups[key]) groups[key] = [];
                groups[key].push(r.id);
            });
        });

        var sl = calcStats('link'), sd = calcStats('doi');
        var pctL = calcPct(sl), pctD = calcPct(sd);
        var pct = null;
        if (pctL !== null && pctD !== null) pct = (pctL + pctD) / 2;
        else if (pctL !== null) pct = pctL;
        else if (pctD !== null) pct = pctD;
        var r = pct !== null ? healthLabel(pct) : ['–', '#999'];

        var causeLabels = {
            naoencontrado: T.causeNotFound, timeout: T.causeTimeout, negado: T.causeDenied,
            servidor: T.causeServerError, outro4xx: T.causeOther4xx,
            bloqueado: T.causeBlocked, tls: T.causeTls, redirecionamento: T.causeRedirect,
            arquivoinvalido: T.causeInvalidFile, arquivotruncado: T.causeTruncatedFile,
            paginaerro: T.causeErrorPage, arquivovazio: T.causeEmptyFile
        };
        var solutionLabels = {
            naoencontrado: T.solutionNotFound, timeout: T.solutionTimeout, negado: T.solutionDenied,
            servidor: T.solutionServerError, outro4xx: T.solutionOther4xx,
            bloqueado: T.solutionBlocked, tls: T.solutionTls, redirecionamento: T.solutionRedirect,
            arquivoinvalido: T.solutionInvalidFile, arquivotruncado: T.solutionTruncatedFile,
            paginaerro: T.solutionErrorPage, arquivovazio: T.solutionEmptyFile
        };

        var html = '<p><strong>' + escapeHtml(T.healthGeneral) + ':</strong> <span style="color:' + r[1] + ';font-weight:bold">' + escapeHtml(r[0]) + '</span> — ' + (pct !== null ? pct.toFixed(1) + '%' : '–') + '</p>';

        if (Object.keys(groups).length === 0) {
            html += '<p>' + escapeHtml(T.noProblems) + '</p>';
        } else {
            for (var key in groups) {
                var parts = key.split('|');
                var causa = parts[0], field = parts[1];
                var blockLabel = field === 'doi' ? T.blockDoi : T.blockLinks;
                html += '<div class="lic-report-item">' +
                    '<strong>[' + escapeHtml(blockLabel) + '] ' + escapeHtml(causeLabels[causa] || causa) + '</strong> — ' + groups[key].length + ' ' + escapeHtml(T.affected) + '<br>' +
                    '<span class="lic-report-fix">' + escapeHtml(solutionLabels[causa] || '') + '</span><br>' +
                    '<span class="lic-report-ids">IDs: ' + escapeHtml(groups[key].slice(0, 30).join(', ')) + (groups[key].length > 30 ? '…' : '') + '</span>' +
                    '</div>';
            }
        }
        document.getElementById('licReportBody').innerHTML = html;
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth' });
    });

    document.querySelectorAll('#licFilterToggle button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#licFilterToggle button').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentFilter = btn.dataset.f;
            applyFilter();
        });
    });

    document.querySelectorAll('#licScopeToggle button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#licScopeToggle button').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentScope = btn.dataset.s;
            applyFilter();
        });
    });

    document.getElementById('licSearch').addEventListener('input', function () {
        currentQuery = this.value.toLowerCase();
        applyFilter();
    });


    // Checagem de versao: o pedido vai ao proprio OJS, que consulta o
    // servidor de versoes. Fazer a chamada direto daqui para outro dominio
    // esbarraria em CORS.
    var btnUpd = document.getElementById('licBtnUpdate');
    if (btnUpd) {
        btnUpd.addEventListener('click', async function () {
            var msg = document.getElementById('licUpdateMsg');
            btnUpd.disabled = true;
            msg.className = 'lic-update-msg';
            msg.textContent = T.updChecking;
            try {
                var params = new URLSearchParams();
                params.set('csrfToken', CSRF);
                var resp = await fetch(btnUpd.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                });
                var j = await resp.json();
                if (j.error === 'invalid_csrf' || resp.status === 403) {
                    msg.className = 'lic-update-msg err';
                    msg.textContent = T.sessionExpired;
                } else if (j.error) {
                    msg.className = 'lic-update-msg err';
                    msg.textContent = T.updUnreachable;
                } else if (j.outdated) {
                    msg.className = 'lic-update-msg new';
                    msg.innerHTML = escapeHtml(T.updAvailable.replace('%s', j.latest)) +
                        (safeUrl(j.download) ? ' <a href="' + escapeHtml(safeUrl(j.download)) +
                            '" target="_blank" rel="noopener noreferrer">' + escapeHtml(T.updDownload) + '</a>' : '');
                } else {
                    msg.className = 'lic-update-msg ok';
                    msg.textContent = T.updCurrent;
                }
            } catch (e) {
                msg.className = 'lic-update-msg err';
                msg.textContent = T.updUnreachable;
            }
            btnUpd.disabled = false;
        });
    }


    // Paleta: guardada por navegador. Nao vai para o servidor porque e
    // preferencia de quem olha a tela, nao configuracao da revista — dois
    // editores da mesma revista podem querer paletas diferentes.
    var THEME_KEY = 'licTheme';
    var themeSel = document.getElementById('licTheme');

    function applyTheme(name) {
        document.body.setAttribute('data-lic-theme', name);
        LIC_COLORS = themeColors();
        renderDonut('licChartLink', 'link');
        renderDonut('licChartDoi', 'doi');
        renderLegend('licLegendLink');
        renderLegend('licLegendDoi');
    }

    if (themeSel) {
        var saved = 'tq';
        try {
            saved = localStorage.getItem(THEME_KEY) || 'tq';
        } catch (e) {
            // Navegador com armazenamento bloqueado: segue no padrao.
        }
        if (!themeSel.querySelector('option[value="' + saved + '"]')) saved = 'tq';
        themeSel.value = saved;
        document.body.setAttribute('data-lic-theme', saved);
        LIC_COLORS = themeColors();

        themeSel.addEventListener('change', function () {
            applyTheme(themeSel.value);
            try {
                localStorage.setItem(THEME_KEY, themeSel.value);
            } catch (e) {
                // Sem persistencia; a troca vale para esta visita.
            }
        });
    }

    // Pagina publica: dois interruptores, salvos na hora.
    var optBox = document.querySelector('.lic-settings');
    if (optBox) {
        var optMsg = document.getElementById('licOptMsg');
        var publicLink = document.getElementById('licPublicLink');

        var saveOptions = async function () {
            var params = new URLSearchParams();
            params.set('csrfToken', CSRF);
            params.set('publicPage', document.getElementById('licOptPublic').checked ? 1 : 0);
            params.set('publicFooter', document.getElementById('licOptFooter').checked ? 1 : 0);
            optMsg.className = 'lic-opt-msg';
            optMsg.textContent = T.optSaving;
            try {
                var resp = await fetch(optBox.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                });
                var j = await resp.json();
                optMsg.className = 'lic-opt-msg ' + (j.saved ? 'ok' : 'err');
                optMsg.textContent = j.saved ? T.optSaved : T.optFailed;
            } catch (e) {
                optMsg.className = 'lic-opt-msg err';
                optMsg.textContent = T.optFailed;
            }
            if (publicLink) publicLink.hidden = !document.getElementById('licOptPublic').checked;
        };

        document.getElementById('licOptPublic').addEventListener('change', saveOptions);
        document.getElementById('licOptFooter').addEventListener('change', saveOptions);
    }

    renderLegend('licLegendLink');
    renderLegend('licLegendDoi');
    refreshAll();
    applyFilter();
});
