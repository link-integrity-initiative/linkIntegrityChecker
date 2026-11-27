# Link Integrity Checker — Plugin para OJS

[![OJS](https://img.shields.io/badge/OJS-3.3%20%7C%203.4%20%7C%203.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Versão](https://img.shields.io/badge/vers%C3%A3o-0.5.4.0-blue)](version.xml)
[![Licença](https://img.shields.io/badge/licen%C3%A7a-GPL--3.0-lightgrey)](LICENSE)

[English](README.md) · **Português**

**⬇️ Pacote de instalação:** [linkIntegrityChecker-0.5.4.0.tar.gz](https://github.com/link-integrity-initiative/linkIntegrityChecker/releases/download/0.5.4.0/linkIntegrityChecker-0.5.4.0.tar.gz) (OJS 3.3, 3.4 e 3.5) — ou veja todas as [Releases](../../releases).

> **Mantido pela [Link Integrity Initiative](https://github.com/link-integrity-initiative).**

## Compatibilidade e branches

| Versão do OJS | Branch | Versão do plugin |
|---|---|---|
| 3.3, 3.4, 3.5 | `stable-3_5_0` *(padrão)* | 0.5.4.0 |

Um único pacote atende as três linhas do OJS (uma camada de
compatibilidade escolhe a API certa em tempo de execução). Não renomeie a
pasta: ela precisa se chamar `linkIntegrityChecker`.

**Versão:** 0.5.4 · **Compatibilidade:** OJS 3.3, 3.4 e 3.5 (LTS) · **Licença:** GPL v3+

Plugin genérico para o Open Journal Systems (OJS) que audita, com um
clique, a integridade dos **links** e **DOIs** dos artigos publicados na
sua revista, apontando o que está quebrado e por quê.

Desenvolvido originalmente como ferramenta interna do **Periódico Tchê
Química** e adaptado pela **WebSotto** para distribuição à comunidade
OJS. Veja [CREDITS.md](CREDITS.md) para a atribuição de autoria (padrão
CRediT) e [CHANGELOG.md](CHANGELOG.md) para o histórico.

---

## O que o plugin faz

- Lista todos os artigos **publicados** da revista.
- Para cada artigo, verifica:
  - **Link**: o endereço de download do arquivo (galley remoto ou gerado
    pelo OJS), seguindo redirecionamentos e conferindo o código HTTP final.
  - **Conteúdo do arquivo**: se o que volta é de fato um PDF íntegro, e não
    uma página de erro servida com status 200, um arquivo vazio ou truncado.
  - **DOI**: se o DOI resolve via `https://doi.org/`.
- Classifica cada falha por causa provável (não encontrado, acesso negado,
  timeout, erro de servidor, certificado TLS inválido etc.) e sugere a
  correção.
- Mostra painel com saúde geral, gráficos, filtros, busca e relatório
  agrupado por causa.
- **Página pública de transparência** (opcional, desligada por padrão):
  publica só os números agregados da última verificação.
- Interface em 8 idiomas (ver [Idiomas](#idiomas)).
- **Não altera o conteúdo da revista.** Grava apenas as próprias
  configurações (ver [Dados gravados](#dados-gravados)).

---

## Requisitos

- OJS 3.3.0, 3.4.x ou 3.5.x (LTS).
- PHP com a extensão **cURL**.
- O servidor precisa fazer requisições HTTP(S) de saída. Confirme com a
  hospedagem se não há firewall bloqueando.
- Papel de **Gerente da Revista** ou **Administrador do site**.

---

## Instalação

**Pela interface (recomendado):** em **Configurações → Site → Plugins →
Enviar novo plugin**, envie o arquivo `linkIntegrityChecker-x.y.z.tar.gz`.

**Manualmente:** extraia o pacote de forma que fique assim:

```
[pasta-do-ojs]/plugins/generic/linkIntegrityChecker/
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

Depois, em **Configurações → Site → Plugins**, encontre **"Verificador de
Integridade de Links"** em *Plugins Genéricos* e clique em **Ativar**. Não
é preciso rodar comandos nem mexer no banco de dados.

> **Instalando a partir do GitHub:** a pasta precisa se chamar
> `linkIntegrityChecker`. O ZIP que o GitHub gera vem com outro nome
> (ex.: `linkIntegrityChecker-stable-3_5_0`); renomeie antes de copiar, ou use o
> `.tar.gz` publicado em *Releases*.

---

## Como usar

1. Na lista de plugins, clique em **"Abrir verificador"**, use o item do
   menu lateral (OJS 3.4/3.5) ou acesse
   `https://sua-revista.org/index.php/[caminho-da-revista]/linkintegrity`.
2. Os artigos aparecem como **Pendente** (aguardando teste) ou **Atenção**
   (sem DOI ou sem composição publicada).
3. **"Testar Todos"** roda os testes em lote, 3 por vez. **"Testar"** em
   cada linha testa um artigo só.
4. Use os filtros (Tudo / Problemas / Conteúdo / Atenção / OK), o escopo
   (DOI, link ou ambos) e a busca (ID, título ou DOI).
5. **"Ver Relatório"** agrupa os problemas por causa, com a sugestão de
   correção e os IDs afetados.

### Página pública de transparência

No painel há dois interruptores, salvos na hora:

- **Publicar página de transparência**: libera
  `.../[caminho-da-revista]/linkintegrity/status`. Desligada, ela responde 404.
- **Mostrar créditos e histórico nela**: exibe o rodapé com créditos e
  CHANGELOG na página pública.

A página mostra só os agregados da última vez que alguém rodou **"Testar
Todos"**: total de artigos, percentuais e gráficos. Não lista artigos nem
endereços, e não permite disparar verificação.

---

## Idiomas

Português (Brasil), inglês e espanhol foram revisados pela equipe. Francês
(Canadá), italiano, alemão, indonésio e turco são **tradução automática não
revisada**. Correções são bem-vindas (ver [CREDITS.md](CREDITS.md)).

A barra de idiomas do painel mostra os idiomas **habilitados na revista**.
Com só um habilitado, ela não aparece.

O OJS 3.4 mudou os códigos de idioma: `en_US` virou `en`, `es_ES` virou
`es`, e assim por diante (`pt_BR` e `fr_CA` não mudaram). Por isso o pacote
traz as duas grafias:

| OJS 3.3 | OJS 3.4 / 3.5 |
|---|---|
| `en_US`, `es_ES`, `de_DE`, `it_IT`, `id_ID`, `tr_TR` | `en`, `es`, `de`, `it`, `id`, `tr` |
| `pt_BR`, `fr_CA` | `pt_BR`, `fr_CA` |

**Ao editar uma tradução, altere as duas pastas do idioma** (o conteúdo é
idêntico).

---

## Dados gravados

O plugin não altera artigos, DOIs nem arquivos. Ele grava somente as
próprias configurações por revista, na tabela `plugin_settings` do OJS:

| Chave | Conteúdo |
|---|---|
| `enabled` | Plugin ativo (gravado pelo próprio OJS) |
| `publicPage` | Página pública ligada/desligada |
| `publicFooter` | Créditos e histórico na página pública |
| `summary` | Números agregados da última verificação completa e a data |

Nenhuma lista de artigos ou endereços é gravada.

## Verificação de atualização (privacidade)

O botão **"Verificar se há versão mais nova"**, no rodapé do painel, só faz contato
externo quando alguém clica nele. Nessa hora, o **servidor da revista**
pergunta à API pública do GitHub qual é a última release deste repositório
(`https://api.github.com/repos/link-integrity-initiative/linkIntegrityChecker/releases/latest`)
e compara com a versão instalada. Se houver uma mais nova, o botão mostra o
link do pacote para instalar.

É um GET anônimo: **nada sobre a revista ou seus usuários é enviado** (até a
0.5.3, o nome da revista e o endereço do site iam para o servidor do
Periódico Tchê Química). Quem faz a requisição é o servidor, então o IP que
o GitHub vê é o da hospedagem, e não o de quem clicou. Se não quiser esse
contato, basta não usar o botão.

---

## Segurança

Resumo (detalhes em [SECURITY.md](SECURITY.md)):

- Painel e endpoints exigem **Gerente da Revista** ou **Administrador do
  site**, pela autorização do próprio OJS. Só a página pública, quando
  ligada, é aberta.
- Endpoints aceitam apenas **POST** com **token CSRF** obrigatório.
- Só endereços **http/https públicos**: faixas privadas, loopback,
  link-local (incluindo `169.254.169.254`), CGNAT e reservadas são
  bloqueadas, em IPv4 e IPv6. O IP validado é fixado e **cada
  redirecionamento é revalidado**.
- TLS verificado. Leituras limitadas a 32 KB de corpo; o arquivo nunca é
  baixado inteiro.

---

## Perguntas frequentes

**A auditoria demora em revistas grandes?**
Sim. Cada link e DOI é testado com uma requisição real, com até 12 s de
timeout por tentativa. Rode em horário de pouco movimento.

**Funciona sem DOI ou sem galley?**
Sim. Sem DOI, o DOI fica como "Atenção". Sem galley publicado, o plugin
testa a página do artigo.

**Funciona com várias revistas na mesma instalação?**
Sim. Cada revista ativa o plugin e audita só os próprios artigos.

**Um link que abre no navegador aparece como "URL bloqueada pela política
de segurança".**
O endereço resolve para um IP interno ou usa porta/protocolo não
permitido. Veja a seção sobre a allowlist em [SECURITY.md](SECURITY.md).

**Links passaram a falhar com "Certificado TLS inválido".**
Não é regressão: a v0.1 desligava a verificação de certificado. O leitor
recebe o mesmo aviso do navegador.

**A revista está em inglês e o painel mostra chaves como
`plugins.generic.linkIntegrity.displayName`.**
Isso acontecia até a 0.5.2 no OJS 3.4/3.5 (ver [Idiomas](#idiomas)).
Atualize para a 0.5.3 ou posterior.

**O plugin aparece com nome estranho na tabela `versions`.**
A v0.1.0 se registrava como `ojs2`. Remova a linha com
`product = 'ojs2'` **e** `product_type = 'plugins.generic'`; não confunda
com a linha do núcleo (`product_type = 'core'`).

**Erro com `Repo::submission()` ou DAO inexistente.**
Diferença de API não coberta pela camada de compatibilidade. Abra uma issue
com a versão exata do OJS (ex.: 3.4.0-9) e a mensagem de erro.

---

## Roadmap

- Verificação de **retratação** (Crossref / Retraction Watch).
- **Histórico** de auditorias.
- **Comentários** de editores sobre links problemáticos.
- **Agendamento automático** (Scheduled Tasks) com aviso por e-mail.
- **Exportação** em PDF/CSV.

---

## Créditos

- **Dr. Luis Alcides Brandini De Boni**, Periódico Tchê Química
  (Conceptualization, Software original, Validation)
- **Bruno Sotto**, [WebSotto](https://websotto.com.br/)
  (Software: conversão para plugin OJS; Testing)

Atribuição completa em [CREDITS.md](CREDITS.md).

## Licença

Copyright (c) 2026 Periódico Tchê Química e WebSotto.

GNU General Public License v3.0 ou posterior, a mesma licença do OJS.
Texto completo em [LICENSE](LICENSE).
