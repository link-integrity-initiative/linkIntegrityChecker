# Modelo de segurança — Link Integrity Checker

Este documento descreve as defesas do plugin, as decisões deliberadas e o
que ainda depende de validação em ambiente real. Foi escrito para quem
revisa o código antes de instalar em produção.

---

## Superfície de ataque

O plugin faz o servidor da revista emitir requisições HTTP para URLs
derivadas do conteúdo cadastrado. Esse é, por definição, o padrão de um
**SSRF (Server-Side Request Forgery)**: um atacante que consiga influenciar
as URLs ou acionar o endpoint pode usar o servidor da revista como
intermediário para alcançar a rede interna, endpoints de metadados de nuvem
(`169.254.169.254`) ou serviços que confiam em conexões vindas de localhost.

Há dois vetores distintos:

1. **Quem pode acionar** a verificação.
2. **Para onde** a verificação pode apontar.

A v0.1 não tratava adequadamente nenhum dos dois. A v0.2 trata ambos.

---

## 1. Controle de acesso

### O problema na v0.1

O plugin interceptava o hook `LoadHandler` e, dentro do próprio callback,
renderizava a página ou o JSON e chamava `exit()`. O `LoadHandler` roda
**antes** de o OJS instanciar e autorizar qualquer handler, então o
comentário no código afirmando que a permissão era "verificada pelo próprio
handler `manager`" estava incorreto: nenhum handler chegava a executar.
Na prática, `.../manager/linkintegrityajax?id=123` respondia a qualquer
visitante não autenticado.

### A correção

O callback agora apenas **devolve um handler** ao roteador do OJS
(`$args[3]` no 3.4/3.5; `HANDLER_CLASS` no 3.3) e retorna. O roteador
executa `LinkIntegrityHandler::authorize()` antes da operação, e essa
autorização usa `ContextAccessPolicy` com os papéis
`ROLE_ID_MANAGER` e `ROLE_ID_SITE_ADMIN` — o mesmo mecanismo das demais
páginas administrativas do OJS.

### Defesas adicionais no endpoint

| Defesa | Motivo |
|---|---|
| Só aceita **POST** | Impede acionamento por `<img src>`, link ou prefetch |
| Exige **token CSRF** da sessão | Impede que um terceiro use o navegador de um gerente logado para disparar as requisições de saída (CSRF encadeado com SSRF) |
| Confere `contextId` da submissão | Um gerente da revista A não consulta artigos da revista B |
| Confere `status = publicado` | O endpoint não expõe metadados de submissões não publicadas |
| `X-Content-Type-Options: nosniff`, `Cache-Control: no-store` | Higiene de resposta JSON |

Desde a 0.5.3 o token CSRF é **obrigatório** em todos os endpoints
(`test`, `checkUpdate`, `saveSummary`, `saveSettings`). Ele vem de
`Session::getCSRFToken()` no 3.3/3.4 e de `token()` na sessão Laravel do
3.5. Se a sessão não fornecer o token, a requisição é recusada (falha
fechada). Antes, nesse caso, a checagem era pulada.

### A página pública

`/linkintegrity/status` é a única operação sem exigência de papel, e só
quando o gerente a publica; desligada, responde 404. Ela apenas lê o resumo
gravado (números agregados e data). Não lista artigos nem endereços, não
aceita parâmetros que disparem verificação e não faz requisição de saída.

---

## 2. Controle de destino (anti-SSRF)

Implementado em `classes/LinkIntegrityHttpClient.inc.php`.

1. **Esquemas**: apenas `http` e `https`. `file://`, `gopher://`, `dict://`,
   `ftp://` são rejeitados antes de qualquer resolução.
2. **Credenciais na URL**: `http://user:senha@host/` é rejeitado — é vetor
   clássico de confusão entre parsers.
3. **Portas**: apenas 80 e 443, mais a porta da própria instalação quando
   ela roda em porta não padrão.
4. **Faixas de IP**: o host é resolvido em PHP e **todos** os endereços
   retornados são conferidos. Bloqueadas em IPv4: `0.0.0.0/8`, `10/8`,
   `100.64/10` (CGNAT), `127/8`, `169.254/16` (metadados de nuvem),
   `172.16/12`, `192.0.0/24`, `192.0.2/24`, `192.88.99/24`, `192.168/16`,
   `198.18/15`, `198.51.100/24`, `203.0.113/24`, `224/4`, `240/4`. Em IPv6:
   `::`, `::1`, `fc00::/7`, `fe80::/10`, `ff00::/8`, `2001:db8::/32`,
   `64:ff9b::/96`, `100::/64`, `2002::/16`, além de endereços IPv4 mapeados
   (`::ffff:127.0.0.1` é avaliado como `127.0.0.1`).
5. **DNS rebinding**: o IP validado é fixado com `CURLOPT_RESOLVE`, de modo
   que o cURL não refaz a consulta DNS. Sem isso, um servidor hostil poderia
   responder um IP público na validação e um IP interno na requisição.
6. **Redirecionamentos**: `CURLOPT_FOLLOWLOCATION` está **desligado**. Cada
   salto é resolvido pelo próprio plugin (inclusive `Location` relativo,
   protocolo-relativo e com `..`) e **revalidado do zero**. Um redirect
   `302` de um site externo para `http://127.0.0.1:6379/` é barrado no
   segundo salto. Limite de 10 saltos.
7. **TLS**: `CURLOPT_SSL_VERIFYPEER = true` e `CURLOPT_SSL_VERIFYHOST = 2`.
   Certificado inválido passou a ser uma causa de falha própria (`tls`) — e
   é informação de auditoria legítima, já que o leitor também veria o aviso
   do navegador.
8. **Limites de tamanho**: 32 KB de corpo e 64 KB de cabeçalhos. A
   transferência é abortada via `CURLOPT_WRITEFUNCTION` assim que o limite é
   atingido; só precisamos do status e do `Location`. Um galley de 800 MB
   não consome memória nem banda do servidor. O `CURLE_WRITE_ERROR` gerado
   pelo nosso próprio corte é distinguido de um erro real de rede.
9. **Timeouts**: 8 s de conexão, 12 s totais por tentativa.
10. **Leitura do fim do PDF** (`fetchTail()`, para detectar arquivo
    truncado): pede só os últimos 2 KB via `Range` e passa pelas **mesmas
    defesas** acima, com IP fixado e redirecionamentos revalidados um a um.
    Até a 0.5.2 esse caminho deixava o cURL seguir redirecionamentos sem
    revalidação, o que reabria o SSRF; corrigido na 0.5.3.

---

## 3. Saída no navegador (XSS)

Títulos, DOIs e endereços vêm do banco e podem ter sido digitados por
outras pessoas da equipe editorial (um galley remoto, por exemplo, é
cadastrado por quem cuida da produção). Por isso:

- Os templates Smarty escapam todas as variáveis com `|escape`; `nofilter`
  só aparece em JSON já codificado, no SVG gerado pelo próprio plugin e no
  CHANGELOG renderizado (que escapa o texto antes de montar o HTML).
- No JavaScript, `escapeHtml()` escapa `& < > " '`. Até a 0.5.2 as aspas
  passavam, e uma URL de galley remoto com `"` podia injetar atributos na
  página do gerente; corrigido na 0.5.3.
- Só endereços `http(s)` viram link clicável (`safeUrl()`); `javascript:`,
  `data:` e afins aparecem como texto. O link de download devolvido pelo
  servidor de versões também é filtrado (só `https`) no PHP e no JS.

---

## Exceção deliberada: o host da própria revista

O host da própria instalação entra numa allowlist e **não** passa pela
checagem de faixa de IP. Ele vem **só do `config.inc.php`** (`base_url` e
as substituições `base_url[caminho]`), nunca dos cabeçalhos da requisição.
Endereço IP literal nunca fica isento, mesmo que esteja no `base_url`.

Até a 0.5.3 o host vinha de `Request::getBaseUrl()`, que o OJS monta a
partir de `Host` / `X-Forwarded-Host`. Um gerente que forjasse o cabeçalho
escolhia o host isento (ex.: `169.254.169.254` ou um IP da rede interna) e
a porta liberada; corrigido na 0.5.4.

**Por quê:** é comum o OJS rodar atrás de proxy reverso, em contêiner ou com
split-horizon DNS, onde o próprio domínio da revista resolve para um
endereço privado a partir de dentro do servidor. Sem a exceção, o plugin
bloquearia exatamente os links que existe para auditar.

**Risco residual:** um gerente da revista consegue fazer o servidor buscar
URLs no próprio host da revista, em portas 80/443. Como o gerente já tem
acesso administrativo àquele mesmo host, o ganho para um atacante é
desprezível. A exceção **não** se estende a outros hosts internos.

Se a sua instalação não precisa dessa tolerância, remova os hosts passados
em `LinkIntegrityHandler::selfHosts()` e a checagem de faixa passa a valer
para todos os destinos.

---

## O que este plugin não faz

- **Não altera o conteúdo da revista.** Não cria tabelas nem migrações.
  Grava apenas as próprias configurações em `plugin_settings`
  (`publicPage`, `publicFooter` e `summary`, este só com números
  agregados), pela API de configurações de plugin do OJS.
- **Não executa** shell, `eval`, `unserialize` de dado externo, nem
  desserializa a resposta HTTP: o corpo é descartado, só o status e o
  `Location` são usados.
- **Não grava arquivos** e não lê nada fora da própria pasta do plugin.
- **Não envia dados sem ação de alguém.** As requisições de saída são para
  as URLs auditadas e para `https://doi.org/`. A única exceção é o botão
  "Verificar se há versão mais nova": ao ser clicado, o servidor da revista
  faz um GET anônimo à API pública do GitHub
  (`api.github.com/repos/link-integrity-initiative/linkIntegrityChecker/releases/latest`).
  Nada sobre a revista ou seus usuários é enviado (ver README).

---

## Limites conhecidos e pontos a validar em produção

Estes pontos são conhecidos e **não** foram validados numa instalação real
— são exatamente o que se recomenda testar antes de publicar:

1. **Execução em OJS 3.5.** A compatibilidade é declarada e o código usa
   detecção em tempo de execução (`LinkIntegrityCompat`), mas isso não
   substitui teste prático. Prioridade de teste: registro do hook, `authorize()`,
   token CSRF na sessão Laravel e renderização de `layouts/backend.tpl`.
2. **Sem limite de taxa.** Um gerente autenticado pode disparar muitas
   verificações em sequência. Isso é uso legítimo da ferramenta, mas em
   revistas com milhares de artigos convém rodar fora do horário de pico.
3. **Sem paginação.** O dashboard carrega todos os artigos publicados de uma
   vez. Em acervos muito grandes, isso pesa na memória do PHP e no tempo de
   renderização.
4. **Content-Security-Policy.** CSS e JS são arquivos externos, mas o
   painel ainda usa alguns atributos `style` gerados pelo JavaScript, o que
   exige `style-src 'unsafe-inline'` numa CSP estrita.
5. **Rejeição de certificado autoassinado.** Com a verificação TLS ligada,
   uma revista que sirva seus galleys por HTTPS com certificado autoassinado
   passará a aparecer com causa `tls`. Isso é o comportamento correto: o
   leitor tem o mesmo problema.

---

## Como reportar uma vulnerabilidade

Use o **relato privado de vulnerabilidade** do GitHub (aba *Security* do
repositório → *Report a vulnerability*), e não uma issue pública. Os
mantenedores estão listados em [CREDITS.md](CREDITS.md).
