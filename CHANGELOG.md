# Histórico de Versões

## Origem do projeto

O **Link Integrity Checker** nasceu como um script PHP interno
(`system_integrity.php`), desenvolvido pelo Dr. Luis Alcides Brandini De
Boni para auxiliar na auditoria interna do **Periódico Tchê Química**,
verificando periodicamente se os links de artigos e os DOIs publicados
continuavam resolvendo corretamente.

Posteriormente, em uma conversa profissional entre o periódico e a
**WebSotto** (https://websotto.com.br/), identificou-se a oportunidade de
transformar essa ferramenta interna em um **plugin distribuível para o
Open Journal Systems (OJS)**, permitindo que qualquer revista que utilize
o OJS possa realizar o mesmo tipo de auditoria interna de integridade,
sem depender de um script customizado rodando fora da plataforma.

Em termos de linhagem de código: **esta versão é baseada no script 0.1 do
Periódico Tchê Química para verificação de integridade do sistema**
(`system_integrity.php`, em produção em
https://www.tchequimica.com/system_integrity.php). A lógica de verificação
e o painel vieram de lá; o que mudou foi a origem dos dados, que passou a
ser o repositório de submissões do OJS em vez de uma tabela própria, e o
controle de acesso, que passou a usar as políticas de autorização da
plataforma.

Este documento registra a evolução do plugin resultante dessa parceria.

---

## [0.5.4.2] — 2026-11-27

### Adicionado
- **Histórico de versões em inglês.** O histórico exibido no rodapé do
  painel e na página pública era sempre este `CHANGELOG.md`, em português,
  qualquer que fosse o idioma da interface. Agora ele acompanha o idioma:
  português mostra este arquivo, e os demais idiomas mostram a tradução em
  inglês, `CHANGELOG.en.md`. Uma tradução para outro idioma pode ser
  acrescentada como `CHANGELOG.<idioma>.md` (ex.: `CHANGELOG.es.md`) e
  passa a ser usada sozinha.

---

## [0.5.4.1] — 2026-11-27

### Corrigido
- O gráfico de rosca do painel deixava os arquivos defeituosos fora do
  total, e as fatias passavam de uma volta completa quando havia algum.
- O relatório de problemas usava a média simples de links e DOIs; agora
  usa a mesma média ponderada do cartão de saúde geral.
- A troca de idioma pela barra de bandeiras acumulava `?licLocale=` na URL
  a partir da segunda troca.

### Limpeza
- Removida a string de locale `guideHealth`, substituída por
  `guideWeighted` e sem uso.
- Cabeçalho de licença acrescentado a `templates/status.tpl`.

---

## [0.5.4] — 2026-11-27

### Segurança
- **SSRF pelo cabeçalho `X-Forwarded-Host`.** O host isento da checagem de
  faixa de IP (o da própria revista) vinha de `Request::getBaseUrl()`, que
  o OJS monta a partir dos cabeçalhos da requisição. Um gerente que
  forjasse o cabeçalho escolhia o host isento e a porta liberada e podia
  sondar a rede interna (`169.254.169.254`, `10.x`). Agora os hosts e
  portas próprios vêm só do `base_url` do `config.inc.php`, e IP literal
  nunca fica isento.
- A verificação de atualização só segue redirecionamentos para `https`.

### Alterado
- **Verificação de versão pelo GitHub.** O botão do rodapé passa a
  consultar a última release em
  `github.com/link-integrity-initiative/linkIntegrityChecker` pela API
  pública do GitHub, e o link "Baixar" leva ao pacote `.tar.gz` da release.
  Não envia mais o nome da revista, o endereço do site nem as versões ao
  servidor do Periódico Tchê Química: é um GET anônimo.
- Versões comparadas em quatro partes (`0.5.4` = `0.5.4.0`).

### Corrigido — funcionamento
- **Card "Saúde geral" sempre "Crítico" com "NaN%".** O total somado para
  o card não incluía as falhas de conteúdo, e o cálculo dava `NaN`.
- **Verificação que estourava o `max_execution_time`.** Uma única
  verificação podia somar vários minutos (repetição × redirecionamentos ×
  12 s, mais a leitura do fim do PDF). Agora cada verificação tem um tempo
  total (o `max_execution_time` menos 5 s, ou 60 s sem limite), o que
  passar disso é relatado como timeout, os redirecionamentos caem de 10
  para 5 e só falhas passageiras (sem resposta, 429, 5xx) são repetidas.
- **Falso "quebrado" em composições que não são PDF.** Bastava a URL
  conter `/download/` para o plugin esperar um PDF, e composições HTML,
  XML ou EPUB apareciam como "página de erro" ou "arquivo inválido". Agora
  o rótulo da composição decide, e a composição PDF, quando houver, é a
  testada primeiro.
- **JS e CSS antigos depois de atualizar.** O OJS marcava os arquivos com
  a versão do próprio OJS (`?v=3.5.0.5`), e o navegador continuava usando
  o JavaScript da versão anterior do plugin. Agora a marca é a versão do
  plugin.
- User-Agent com a versão completa (`0.5.4`).

## [0.5.3] — 2026-11-18

### Corrigido — funcionamento
- **Página pública, resumo e interruptores passam a funcionar.** As
  operações `saveSummary`, `saveSettings` e `status`, anunciadas na 0.5.0,
  eram chamadas pelo painel mas não existiam no handler: os interruptores
  sempre respondiam "falhou", o resumo nunca era gravado e
  `/linkintegrity/status` dava erro. Agora estão implementadas, com os
  gráficos da página pública gerados em SVG no servidor.
- A página pública era negada a visitantes mesmo quando publicada: o
  `PKPHandler` recusa operações quando o handler tem papéis atribuídos e
  nenhuma política os conferiu. A operação `status` agora marca essa
  conferência como feita.
- **Idiomas no OJS 3.4/3.5.** O OJS 3.4 passou a usar `en`, `es`, `de`,
  `it`, `id` e `tr` em vez de `en_US`, `es_ES` etc., e procura a pasta pelo
  nome exato. Revistas nesses idiomas viam as chaves cruas
  (`plugins.generic.linkIntegrity...`). O pacote agora traz as duas grafias;
  `pt_BR` e `fr_CA` não mudaram. Bandeiras também para os códigos novos e
  para o italiano.
- No OJS 3.3, o roteador cria o handler pelo nome da classe, sem passar o
  plugin; o handler agora busca a instância no `PluginRegistry`, sem a qual
  não havia configurações nem templates.
- Resposta 404 compatível com o OJS 3.5, cujo `Dispatcher` não tem mais
  `handle404()`.

### Segurança
- **XSS armazenado no painel.** `escapeHtml()` não escapava aspas, e a URL
  de um galley remoto entrava em `href` e `title`. Quem cadastra galleys
  podia injetar atributos e script na sessão do gerente. O escape cobre
  `& < > " '` e só endereços `http(s)` viram link (`safeUrl()`).
- **SSRF em `fetchTail()`.** A leitura do fim do PDF deixava o cURL seguir
  redirecionamentos sem revalidar e sem fixar o IP. Agora passa pelas mesmas
  defesas de `fetch()` e parte do endereço final já validado.
- **CSRF obrigatório.** Se a sessão não fornecer o token, a requisição é
  recusada; antes a checagem era pulada.
- O link de download vindo do servidor de versões só é aceito se for
  `https`.

### Documentação e metadados
- `README.md` em inglês e `README.pt_BR.md` atualizados para a versão
  atual, com as seções **Dados gravados** e **Verificação de atualização
  (privacidade)**: o botão envia, quando clicado, a versão, o nome da
  revista e o endereço do site ao servidor de versões.
- `SECURITY.md` revisado: página pública, saída no navegador (XSS),
  `fetchTail()`, dados gravados e relato privado de vulnerabilidade pelo
  GitHub.
- `LICENSE` com o texto integral da GPL v3 (antes havia só um resumo em
  `LICENSE.md`, que o GitHub não reconhece como licença).
- `version.xml` 0.5.3.0 com a data correta; versão alinhada nos cabeçalhos
  dos `.po`, no User-Agent e na citação do `CREDITS.md`.

## [0.5.2] — 2026-11-08

### Adicionado
- **Cinco novos idiomas**: francês (Canadá), italiano, alemão, indonésio e
  turco, com as 111 chaves completas em cada um. Bandeiras próprias para
  os novos locales, além de fr_FR, pt_PT e en_GB.
- `CREDITS.md` passa a trazer uma tabela de traduções que distingue as
  revisadas pela equipe das geradas automaticamente, e registra como o
  termo *galley* foi vertido em cada idioma. Marcar o que não foi revisado
  é o que permite à comunidade saber onde ajudar — e é o oposto de
  apresentar tradução automática como se fosse revisada.

## [0.5.1] — 2026-11-08

### Alterado — conformidade com as práticas da PKP
- **O tradutor do OJS voltou a ser o caminho principal.** Desde a 0.2.5 o
  plugin lia os `.po` diretamente para permitir a troca de idioma; o efeito
  colateral era ignorar o mecanismo da plataforma e enxergar apenas os três
  idiomas do pacote. Agora `__()` atende o idioma corrente — e portanto
  qualquer tradução instalada, inclusive as que cheguem pelo Weblate da PKP
  — e a leitura direta do `.po` fica restrita ao único caso em que o OJS
  não serve: a pessoa escolheu, na barra, um idioma diferente do corrente.
- **A barra de idiomas vem dos idiomas habilitados na revista**, via
  `getSupportedLocaleNames()`, em vez de uma lista fixa de três no código.
  Um idioma novo habilitado aparece sozinho; um que a revista não usa não
  aparece. Com um único idioma habilitado, a barra não é exibida.
- Os nomes dos idiomas passam a ser os do próprio OJS. Bandeiras existem
  para pt_BR, pt_PT, en_US, en_GB e es_ES; os demais aparecem só com o
  nome, que é o que de fato identifica a língua.
- O parâmetro `licLocale` agora é validado contra os idiomas habilitados na
  revista antes de qualquer uso.

## [0.5.0] — 2026-09-24

### Adicionado
- **Página pública de transparência**, em `/linkintegrity/status`, desligada
  por padrão. Mostra apenas agregados: total de artigos, percentual de
  links e de DOIs íntegros, os dois gráficos e a data da última
  verificação. Sem lista de artigos, sem endereços individuais e sem botão
  de testar — publicar a lista entregaria a visitantes um mapa dos pontos
  frágeis do site, e permitir disparar a verificação a partir de uma visita
  de leitor seria porta aberta para abuso.
  - Os gráficos são gerados em SVG no servidor: a página funciona sem
    JavaScript algum.
  - Só lê o resumo salvo pela última verificação feita no painel. Nada é
    calculado na visita.
- **Dois interruptores no painel**: publicar a página, e mostrar nela os
  créditos e o histórico. O botão de verificar atualização nunca aparece na
  página pública. Salvam na hora, sem formulário separado.
- O resumo agregado passa a ser gravado ao fim de "Testar Todos". Só
  números: nenhuma lista de artigos sai do navegador.

## [0.4.2] — 2026-09-24

### Adicionado
- **Seletor de escopo** na barra de filtros: Ambos, Só DOI, Só Link. Decide
  a qual dos dois testes o filtro de estado se aplica. Com "Ambos" — o
  padrão — nada muda em relação à versão anterior: a linha aparece se o DOI
  **ou** o link estiverem no estado escolhido. Isso é útil para caçar
  problemas, mas fazia o filtro "OK" exibir linhas defeituosas, já que
  nelas a outra coluna estava OK. Escolhendo DOI ou Link, a pergunta passa
  a ser sobre um teste só.

## [0.4.1] — 2026-09-24

### Adicionado
- **Categoria ARQUIVO**, separada de QUEBRADO. Endereço que não responde e
  arquivo que responde mas não serve são problemas distintos e pedem ações
  distintas: um 404 costuma ser link errado, um PDF truncado costuma ser
  arquivo perdido no armazenamento. Antes os dois caíam no mesmo contador.
  - Contador, fatia no gráfico, item de legenda e cor próprios em todas as
    paletas.
  - Filtro "Arquivo" na barra. O filtro "Problemas" passou a reunir as duas
    categorias, já que ambas impedem o leitor de chegar ao arquivo.
  - Entra no cálculo de saúde como falha, e não como caso não verificado.

## [0.4.0] — 2026-09-24

### Adicionado
- **Inspeção do conteúdo entregue.** Status 200 não garante arquivo
  utilizável, e era só isso que o plugin verificava. Um PDF corrompido
  respondia 200 e aparecia como OK, enquanto o leitor via
  "Invalid PDF structure" e uma página em branco. Agora o verificador
  examina o que o servidor devolveu e distingue quatro situações novas:
  - `paginaerro` — HTML com status 200 no lugar do arquivo (tela de erro
    amigável, aviso de login, bloqueio do servidor);
  - `arquivoinvalido` — o conteúdo não começa com a assinatura `%PDF-`;
  - `arquivotruncado` — começa como PDF mas não termina em `%%EOF`, sinal
    de upload interrompido. Detectado com uma requisição `Range` que lê só
    os últimos 2 KB, sem baixar o arquivo inteiro;
  - `arquivovazio` — 200 com zero byte.
  Cada uma com causa e solução próprias no guia, nos três idiomas.
  Verificado contra um servidor de teste que reproduz os quatro defeitos.

### Alterado
- Botões, filtros e campo de busca mais estreitos e com altura uniforme de
  30px, proporcionais ao restante do painel.

## [0.3.3] — 2026-09-24

### Corrigido
- **Rodapé e barra de idiomas sem cor.** Ambos ficam fora de `#licApp`, e
  era ali que as variáveis de cor estavam declaradas: fora do container
  `var(--lic-dark)` não resolvia, o rodapé saía com fundo transparente e
  texto branco sobre cinza claro. As variáveis passaram para o `body`, e o
  atributo `data-lic-theme` também.
- **Redirecionamento contado como sucesso.** `isSuccessCode()` aceitava de
  200 a 399. Como o cliente já segue a cadeia de redirecionamentos e
  devolve o código do destino final, um 3xx que sobra significa cadeia
  interrompida — limite de saltos, `Location` ausente ou circular. Agora só
  2xx é sucesso, e 3xx ganhou causa e solução próprias no guia.

### Alterado
- O código HTTP passa a aparecer no tooltip do status mesmo quando o
  resultado é OK, e a coluna Link mostra a URL completa ao passar o mouse.
  Sem isso não havia como distinguir um 200 legítimo de um 200 devolvido
  por página de erro amigável ou por tela de login.

## [0.3.2] — 2026-09-24

### Corrigido
- **O teste de link verificava o endereço errado.** Era a página do
  visualizador (`article/view/…`), que responde 200 mesmo quando o arquivo
  sumiu do armazenamento: ela é apenas o invólucro em torno do PDF.js e
  carrega antes de saber se há arquivo. Numa revista com todos os PDFs
  perdidos, o painel diria "tudo certo". Agora o teste usa
  `article/download/…`, que entrega o arquivo ou devolve 404 — e é o
  endereço que o leitor usa ao clicar para baixar. Artigos sem composição
  publicada continuam verificando a página do artigo, já que não há arquivo.

### Adicionado
- **Entrada no menu lateral do painel** (OJS 3.4/3.5), logo após
  "Ferramentas", pelo hook `TemplateManager::setupBackendPage`. Até aqui só
  se chegava ao painel digitando a URL: o hook usado antes
  (`Templates::Manager::Index::ManagementPages`) é da época do OJS 3.2/3.3 e
  não dispara no 3.5. O hook antigo foi mantido para instalações 3.3.

## [0.3.1] — 2026-09-24

### Alterado
- **Rodapé em faixa própria**, com fundo escuro destacado do corpo da
  página, no espírito do rodapé do site do periódico. Histórico, créditos e
  ação numa única linha, em fonte reduzida, com o "Verificar se há versão
  mais nova" como botão à direita.

### Adicionado
- **Registro de uso** no servidor de versões. Ao clicar em verificar
  atualização, o plugin envia a versão do plugin, a versão do OJS, o nome e
  o endereço da revista; `linkIntegrityCheckerVersion.php` grava uma linha
  em `lic-data/usage.csv` com esses dados mais a data e o IP de origem. A
  finalidade é medir se o plugin tem uso real.
  - Nada é enviado sem um clique: não há envio automático em segundo plano.
  - Quem faz a requisição é o servidor da revista, via cURL, então o IP
    registrado é o da hospedagem, não o de uma pessoa natural.
  - O botão traz, ao passar o mouse, a lista exata do que é enviado.
  - A pasta `lic-data/` recebe um `.htaccess` que bloqueia acesso por HTTP.

## [0.3.0] — 2026-09-24

### Adicionado
- **Seletor de paleta** no topo, com cinco opções: Tchê (padrão, cores
  extraídas do CSS do periódico), Sotto (azul royal e preto da WebSotto),
  De Boni (verde, amarelo e azul do Brasil em tom pastel), Pastel e
  Azul e preto. A escolha fica guardada no navegador de cada pessoa, e não
  na configuração da revista: dois editores da mesma revista podem
  preferir paletas diferentes.

### Corrigido
- Seletor de paleta desalinhado em relação ao rótulo ao lado. Controles de
  formulário não herdam tipografia, e o OJS aplica regras próprias a
  `select`: o texto saía maior e a caixa mais alta que as pílulas de
  idioma. Resolvido com `font: inherit` e altura comum entre os controles
  da barra, sob um seletor com especificidade acima da folha do OJS.

### Alterado
- Toda a folha de estilo passou a usar variáveis CSS. Os gráficos leem as
  mesmas variáveis, então não há cor duplicada entre o CSS e o JavaScript
  e trocar a paleta muda tudo de uma vez.
- Tabela redesenhada: fonte menor, espaçamento mais justo, larguras de
  coluna fixas por `colgroup` e alinhamento superior nas células. Antes ela
  destoava em tamanho do restante do painel.
- Estilos embutidos no HTML movidos para classes.

## [0.2.7] — 2026-09-24

### Corrigido
- **Saúde geral enganosa.** Era a média simples dos dois percentuais, e uma
  dimensão sem nada verificado simplesmente sumia da conta: com 6 links OK
  e nenhum DOI cadastrado, o painel anunciava "Excelente, 100%". Agora é
  média ponderada pelo volume — os dois conjuntos entram no mesmo
  numerador e denominador.

### Adicionado
- Três indicadores separados: saúde dos LINKS, saúde dos DOIs e saúde geral
  ponderada.
- Linha de **cobertura** em cada indicador ("6 de 12 verificações
  realizadas"). Um índice alto sobre cobertura baixa não significa periódico
  íntegro, significa que pouca coisa foi testada.
- Explicação ao passar o mouse (ou pelo teclado, via foco) sobre os cartões
  de contagem, os itens da legenda e as fatias dos gráficos.

## [0.2.6] — 2026-09-24

### Corrigido
- **Bandeiras invisíveis no Windows.** Eram emoji de bandeira, e o Windows
  não traz fonte para os caracteres indicadores regionais: apareciam como
  duas letras soltas ("BR", "US", "ES"). Substituídas por SVG embutido,
  que renderiza em qualquer navegador e sistema.
- `linkIntegrityCheckerVersion.php` reescrito para **PHP 5.4**, que é o que
  roda no servidor do periódico. A primeira versão usava
  `declare(strict_types=1)`, tipos em parâmetros e `??`, todos de PHP 7,
  e devolvia HTTP 500 antes mesmo de executar.

## [0.2.5] — 2026-09-24

### Adicionado
- Painel **Como interpretar os resultados**: o que significa cada estado
  (OK, QUEBRADO, ATENÇÃO, Pendente), cada causa de quebra com a solução
  sugerida, e como a saúde geral é calculada.
- Troca de idioma **só do plugin**, por bandeiras no topo, sem alterar o
  idioma do painel do OJS. As strings vêm dos `.po` do próprio plugin:
  o OJS carrega os arquivos de locale de um plugin apenas para o idioma
  corrente da revista, e devolveria a própria chave para os demais.
- Botão **Verificar se há versão mais nova**, consultando
  `linkIntegrityCheckerVersion.php` no Periódico Tchê Química. A consulta
  parte do servidor do OJS, não do navegador, o que dispensa CORS no site
  de destino. Havendo versão mais nova, o link de download aparece na hora.

### Alterado
- O histórico de versões passou a ser renderizado como HTML formatado,
  em vez de texto puro do Markdown.

## [0.2.4] — 2026-09-24

### Adicionado
- Gráficos de rosca (donut) para LINKS e DOI, em SVG puro — sem
  biblioteca externa, sem CDN e sem conflito com o Vue do OJS.
- Rodapé com os créditos da parceria e links para os dois sites.
- Painel "Histórico de versões" na própria interface, lendo este
  CHANGELOG.md. Fonte única: o que está no repositório é o que o gerente
  da revista vê na tela.

### Alterado
- Cartões de contagem redimensionados. Estavam desproporcionais ao
  conteúdo: um dígito ocupava um bloco de 20px de padding e fonte 2.2em.
- Contadores e gráficos passaram a dividir a mesma faixa horizontal.

## [0.2.3] — 2026-09-24

### Corrigido
- **CSS perdido no OJS 3.5.** O dashboard renderizava sem estilo algum. O
  OJS 3.5 monta o Vue sobre `#app` e o compilador de templates do Vue
  descarta `<style>` e `<script>` embutidos ("Tags with side effect are
  ignored in client component templates"). O CSS foi para
  `styles/linkIntegrity.css` e o JS para `js/linkIntegrity.js`, ambos
  registrados no `<head>` via `addStyleSheet()` / `addJavaScript()`.
- Os dados (strings, artigos, URL do endpoint, token CSRF) passaram a
  trafegar em atributos `data-*` do container, que sobrevivem à
  renderização do Vue.
- O JS passou a rodar em `DOMContentLoaded`, portanto depois de
  `pkp.registry.init()`. Antes dependia da ordem de parsing.
- Título da aba do navegador exibia a chave de tradução crua.

---

## [0.2.2] — 2026-09-23

### Corrigido
- **Erro 500 de compilação Smarty em `index.tpl`.** CSS e JavaScript
  inline agora estão em `{literal}...{/literal}`; o bloco é interrompido
  só nas quatro linhas que interpolam variáveis (`$stringsJson`,
  `$articlesJson`, `$ajaxUrlJson`, `$licCsrfJson`). Compilação verificada
  com Smarty 4.5.4 isolado (não em OJS completo).

---

## [0.2.1] — 2026-09-23

### Corrigido
- **Erro 500 no OJS 3.4/3.5.** `fetchPublishedSubmissions()` chamava
  `Repo::submission()->getMany($collector)`, método inexistente no
  Repository. Agora usa `$collector->getMany()`. Pendente de teste em execução
  no OJS 3.5.
- `buildArticleUrl()` recorre a `getId()` se `getBestId()` não existir.

---

## [0.2.0] — 2026-09-01

Versão de endurecimento de segurança, resultante da revisão de código feita
antes da publicação. **Recomendada para qualquer instalação:** a 0.1.0 não
deve ser instalada em produção.

### Corrigido — segurança

- **Endpoint sem autorização (crítico).** A 0.1.0 renderizava a página e o
  endpoint JSON dentro do próprio callback do hook `LoadHandler`, encerrando
  com `exit()`. Esse hook roda antes de o OJS instanciar e autorizar
  qualquer handler, então nenhuma verificação de permissão chegava a
  acontecer — o endpoint respondia a visitantes anônimos, que podiam assim
  induzir requisições de saída do servidor da revista (SSRF). O plugin agora
  devolve um `LinkIntegrityHandler` ao roteador, cujo `authorize()` aplica
  `ContextAccessPolicy` com os papéis de Gerente da Revista e Administrador
  do site.
- **Verificação TLS desativada.** `CURLOPT_SSL_VERIFYPEER` estava em `false`.
  Agora é `true`, junto com `CURLOPT_SSL_VERIFYHOST = 2`.
- **Sem restrição de destino (SSRF).** Foram adicionados: allowlist de
  esquemas (`http`/`https`), rejeição de credenciais embutidas na URL,
  restrição de portas, bloqueio de todas as faixas privadas, de loopback,
  link-local, CGNAT, multicast e reservadas (IPv4 e IPv6, incluindo
  endereços IPv4 mapeados), e fixação do IP validado via `CURLOPT_RESOLVE`
  contra DNS rebinding.
- **Redirecionamentos sem revalidação.** Cada salto passou a ser revalidado
  integralmente pela política acima, então um redirect para
  `169.254.169.254` ou `127.0.0.1` é barrado no salto em que aparece.
- **CSRF no endpoint.** A verificação individual agora exige método POST e
  token CSRF da sessão, impedindo que um terceiro use o navegador de um
  gerente autenticado para disparar as requisições de saída.
- **Isolamento entre revistas.** O endpoint confere `contextId` e status
  publicado da submissão antes de responder.

### Corrigido — robustez

- **Limite de tamanho de resposta.** 32 KB de corpo e 64 KB de cabeçalhos,
  com aborto da transferência ao atingir o limite. Antes, um galley grande
  era baixado inteiro para a memória apenas para se obter o código HTTP.
- **`Location` relativo.** Redirecionamentos relativos, protocolo-relativos
  (`//host/x`), absolutos de raiz (`/x`) e com `..` passaram a ser resolvidos
  conforme a RFC 3986. Antes, um `Location: /novo-caminho` era tratado como
  URL absoluta e a verificação falhava com falso-negativo.
- **`CURLOPT_CONNECTTIMEOUT`** de 8 s, além do timeout total de 12 s.
- **Extração de DOI** passou a aceitar o valor cadastrado como URL completa
  (`https://doi.org/10.x/y`) ou com prefixo `doi:`, e a cobrir o objeto `Doi`
  do OJS 3.4+.
- **`version.xml` fora do schema do PKP.** O arquivo declarava
  `<application>ojs2</application>`, que é o identificador do **núcleo do
  OJS**, não do plugin. Como o OJS grava esse valor na coluna `product` da
  tabela `versions`, o plugin se registrava com o nome errado, colidindo com
  o registro do próprio núcleo e quebrando a detecção de atualização. Agora
  é `linkIntegrityChecker`, igual ao nome da pasta. Também foram removidos
  os elementos `<name>`, `<category>` e `<compatibility>`, que não existem
  em `lib/pkp/dtd/pluginVersion.dtd` e eram silenciosamente ignorados pelo
  instalador; adicionado `<class>`; corrigida a ordem dos elementos exigida
  pelo DTD; e o `DOCTYPE` passou a apontar para o caminho relativo padrão
  (`../../../lib/pkp/dtd/pluginVersion.dtd`) em vez de uma URL externa.

- **Escaping no dashboard.** O corte do texto do link passou a ocorrer antes
  do escape de HTML (cortar depois podia partir uma entidade ao meio), e
  todos os rótulos traduzidos passaram pelo escape.

### Adicionado

- `SECURITY.md` — modelo de segurança, decisões deliberadas (incluindo a
  allowlist do host da própria revista) e limites conhecidos.
- `classes/LinkIntegrityCompat.inc.php` — camada única de compatibilidade
  entre OJS 3.3, 3.4 e 3.5, no lugar de verificações espalhadas pelo código.
- `classes/LinkIntegrityHttpClient.inc.php` — cliente HTTP endurecido,
  separado da lógica de leitura de dados do OJS.
- `pages/LinkIntegrityHandler.inc.php` — handler autorizado.
- Botão de acesso ao painel na própria grade de plugins, caminho mais
  estável entre versões do que o hook de menu de gerenciamento.
- Causas de falha `tls` (certificado inválido) e `bloqueado` (URL barrada
  pela política de segurança), com texto de solução nos três idiomas.

### Observação de compatibilidade

A compatibilidade com OJS 3.5 continua **declarada, não verificada em
execução**: não houve teste em instalação real. Ver a lista de pontos a
validar em `SECURITY.md`.

---

## [0.1.0] — 2026-08-26

Primeira versão do plugin. **Não recomendada para uso:** contém as falhas
de segurança corrigidas na 0.2.0 e foi substituída antes da publicação.

### Adicionado
- Conversão da lógica de verificação do script original para uma classe
  de serviço (`LinkIntegrityService`) integrada às APIs nativas do OJS
  (submissões, publicações e galleys).
- Página de gerenciamento (`Verificador de Integridade`) acessível pelo
  menu de gerenciamento da revista, restrita a Gerentes de Revista e
  Administradores do site.
- Verificação de **links** de artigos publicados (galleys remotos ou
  gerados pelo próprio OJS), com detecção de redirecionamentos e
  classificação da causa provável de falha (404, 403, timeout, erro de
  servidor, outros erros 4xx).
- Verificação de **DOI**, testando a resolução via `https://doi.org/`.
- Dashboard com indicadores de saúde por bloco (Links / DOI), saúde
  geral do sistema, filtros, busca e teste individual ou em lote.
- Relatório de causas e soluções sugeridas para os problemas encontrados.
- Internacionalização completa: **Português (pt_BR)**, **Inglês (en_US)**
  e **Espanhol (es_ES)**.
- Compatibilidade com OJS 3.3, 3.4 e 3.5 (LTS), com detecção automática
  entre a API legada de DAOs e o padrão Repository/Collector introduzido
  no OJS 3.4+.

### Fora de escopo nesta versão (planejado para versões futuras)
- Verificação de retratação de artigos via Crossref API.
- Persistência histórica dos resultados de auditoria em banco de dados.
- Comentários de editores/gerentes sobre itens problemáticos.
- Agendamento automático (Scheduled Tasks) e notificações por e-mail.
- Exportação de relatórios em PDF/CSV.

Veja o `README.md` para instruções de instalação, configuração e uso.
