# Créditos

Este plugin foi desenvolvido com base na taxonomia **CRediT (Contributor
Roles Taxonomy)** para atribuição transparente de contribuições, seguindo
o padrão adotado pela comunidade científica e recomendado por editoras e
periódicos indexados (https://credit.niso.org/).

## Autoria e papéis (CRediT)

| Contribuidor | Afiliação | Papéis CRediT |
|---|---|---|
| **Dr. Luis Alcides Brandini De Boni** | Periódico Tchê Química | Conceptualization, Software (original script), Validation |
| **Bruno Sotto** | WebSotto (https://websotto.com.br/) | Software (conversão para plugin OJS), Testing |

### Descrição dos papéis

- **Conceptualization** — Ideia original da ferramenta de auditoria de
  integridade de links e DOIs, formulação do problema e dos objetivos.
- **Software (original script)** — Autoria do script PHP standalone
  (`system_integrity.php`) usado internamente pelo Periódico Tchê Química,
  incluindo a lógica de verificação HTTP, resolução de DOI e a interface
  de usuário original.
- **Software (conversão para plugin OJS)** — Adaptação da lógica e da
  interface originais para a arquitetura de plugins do Open Journal
  Systems (OJS), incluindo integração com as APIs nativas de submissões,
  publicações e galleys, hooks, internacionalização e empacotamento para
  distribuição.
- **Validation** — Verificação de que os resultados da auditoria
  correspondem à realidade dos links e DOIs do periódico de origem.
- **Testing** — Testes funcionais do plugin em ambiente OJS antes da
  distribuição para a comunidade.

## Como citar este plugin

> De Boni, L. A. B.; Sotto, B. (2026). *Link Integrity Checker: plugin de
> auditoria de links e DOIs para o Open Journal Systems* (versão 0.5.4.1)
> [Software]. Periódico Tchê Química / WebSotto.

## Traduções

| Idioma | Código | Origem | Revisado por falante nativo |
|---|---|---|---|
| Português (Brasil) | `pt_BR` | Equipe do projeto | Sim |
| Inglês (EUA) | `en_US` | Equipe do projeto | Sim |
| Espanhol | `es_ES` | Equipe do projeto | Sim |
| Francês (Canadá) | `fr_CA` | Tradução automática | **Não** |
| Italiano | `it_IT` | Tradução automática | **Não** |
| Alemão | `de_DE` | Tradução automática | **Não** |
| Indonésio | `id_ID` | Tradução automática | **Não** |
| Turco | `tr_TR` | Tradução automática | **Não** |

As traduções marcadas como não revisadas foram geradas automaticamente e
conferidas apenas quanto à cobertura das chaves e à preservação dos
marcadores de formato. A terminologia editorial — em especial o termo
*galley*, vertido como *épreuve* (fr), *bozza* (it), *Fahne* (de),
*galley* (id) e *dizgi* (tr) — merece revisão por quem edita revistas
nesses idiomas.

**Correções são muito bem-vindas.** Abra uma issue ou um pull request
alterando o `locale/<código>/locale.po` correspondente. Os idiomas
com código diferente no OJS 3.3 e no 3.4+ têm duas pastas de conteúdo
idêntico (`en_US` e `en`, `es_ES` e `es`, `de_DE` e `de`, `it_IT` e `it`,
`id_ID` e `id`, `tr_TR` e `tr`): altere as duas. Um idioma ausente
pode ser acrescentado criando a pasta e o arquivo no mesmo formato: o
plugin detecta sozinho os idiomas presentes e os cruza com os que a
revista tem habilitados.

## Contato

- Periódico Tchê Química — https://www.tchequimica.com/
- WebSotto — https://websotto.com.br/

---
*Contribuições futuras de terceiros são bem-vindas e devem ser adicionadas
a esta tabela seguindo o mesmo padrão CRediT, via pull request.*
