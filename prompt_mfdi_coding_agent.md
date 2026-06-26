# Prompt — Implementação do Módulo MFDI (Formulários Dinâmicos Integrados) para o SEI

## Contexto

Você é um agente de codificação especializado em desenvolvimento de módulos nativos para o SEI (Sistema Eletrônico de Informações), utilizando o framework InfraPHP/SinfoniaPHP.

---

## Objetivo

Implementar o Módulo de Formulários Dinâmicos Integrados (MFDI), que transforma documentos HTML nativos do SEI em formulários estruturados de preenchimento, sem criar persistência própria no banco de dados.

---

## Restrições Arquiteturais Inegociáveis

1. **Sem persistência própria** — O módulo não cria tabelas, schemas, sequences, ou triggers. Toda a persistência dos valores digitados ocorre exclusivamente atualizando o HTML do documento nativo do SEI.
2. **Sem modificação do core** — Nenhum arquivo fora do diretório `sei/web/modulos/mod-mfdi/` deve ser alterado. A integração com o SEI ocorre exclusivamente pelos mecanismos de registro de módulo e injeção de botões.
3. **Padrão de Nomenclatura** — Classes do módulo herdam de classes base do InfraPHP (`InfraDTO`, `InfraBD`, `InfraRN`, `InfraINT`) e devem seguir o prefixo `Mfdi`.

---

## Estrutura de Arquivos

```
C:\Sistemas\seiMGI\fontes\sei\web\modulos\mod-mfdi\
├── MfdiDTO.php                  ← DTO transiente para transporte de dados
├── MfdiBD.php                   ← Camada BD (leitura/escrita via EditorRN)
├── MfdiRN.php                   ← Camada de Negócio e Parser DOM
├── MfdiINT.php                  ← Camada de Integração (placeholder)
├── MfdiController.php           ← Controlador unificado (GET/POST)
├── md_mfdi_instalar.php         ← Script CLI de instalação/registro no SIP
├── readme.md                    ← Documentação técnica de uso e implantação
└── tpl/
    ├── mfdi_formulario.php      ← Interface do formulário de preenchimento
    └── mfdi_botao.php           ← Template do botão injetado no visualizador
```

---

## Diretrizes de Implementação e Lições Aprendidas (MVP Concluído)

### 1. Sistema de Marcação via Classes CSS (Preservação no XSS)
*   **Problema**: O filtro XSS nativo do SEI (`InfraXSS` / `SeiINT`) remove atributos customizados do tipo `data-sei-*` do HTML do documento no momento de salvamento no CKEditor.
*   **Solução**: Identificar os campos dinâmicos por meio de **classes CSS especiais** prefixadas com `sei-`. Exemplo:
    ```html
    <td class="sei-field--razao_social sei-type--texto sei-required">&nbsp;</td>
    ```
*   **Classes obrigatórias**:
    *   `sei-field--{nome_campo}`: Nome interno do campo.
    *   `sei-type--{tipo}`: Tipos suportados: `texto`, `numero`, `moeda`, `data`, `boolean`, `lista`, `textarea`.
    *   `sei-required`: Campo obrigatório.
    *   `sei-options--{base64url_options}`: Para campos `lista`, contendo opções separadas por `|` ou `,` codificadas em base64url para evitar que caracteres especiais quebrem a estrutura da classe CSS.

### 2. Endpoints Unificados no Controller
*   **Problema**: Adicionar múltiplas ações/recursos no SIP exige scripts de migração complexos e polui o controle de acesso do SEI.
*   **Solução**: Registrar apenas a ação GET `md_mfdi_formulario` no SIP. No `MfdiController.php`, fazer a ramificação de comportamento com base no método da requisição:
    - **GET**: Renderiza a interface do formulário em `tpl/mfdi_formulario.php`.
    - **POST**: Trata a gravação AJAX e devolve uma resposta em JSON.
*   **Tratamento de Exceções**: Em requisições POST, intercepte as exceções para retornar respostas JSON (`{"sucesso": false, "erro": "..."}`) com status HTTP `400` em vez de deixar o SEI renderizar a tela padrão de erro em HTML. Extraia erros múltiplos de `InfraException` utilizando `getArrObjInfraValidacao()` e `getStrDescricao()`.

### 3. Tratamento de Espaços Não-Separáveis (`&nbsp;` / `\u00a0`)
*   **Problema**: Células HTML vazias no SEI frequentemente contêm `&nbsp;` (Unicode `\u00a0` / UTF-8 `\xc2\xa0`). O `DOMDocument` decodifica esses caracteres, e a conversão de codificação do PHP gera artefatos estranhos (como `ï¿½` ou `Ã‚Â`) no valor do input do formulário.
*   **Solução**: No método `extrairCampos`, substitua todas as ocorrências de `\xc2\xa0` e `&nbsp;` por espaços normais antes de executar o `trim()`. Isso faz com que células vazias resultem em um `value=""` limpo.

### 4. Isolamento e Escrita Apenas na Seção Principal
*   **Problema**: Documentos internos no SEI costumam ser divididos em várias seções no banco de dados (Cabeçalho, Corpo Editável, Rodapé, etc.). Métodos gerais como `consultarHtmlVersao()` retornam o HTML de todas as seções concatenadas. Se esse HTML concatenado for salvo de volta no editor, causará duplicação infinita de cabeçalhos e rodapés.
*   **Solução**: 
    - Na leitura (`consultarConteudoDocumento`), liste as seções ativas do documento (`sin_ultima = 'S'`) e retorne **apenas** o conteúdo da seção com `sin_principal = 'S'`.
    - Na gravação (`atualizarConteudoDocumento`), atualize somente o conteúdo da seção onde `sin_principal = 'S'`. Para as demais seções (cabeçalho/rodapé), mantenha o conteúdo original inalterado.

### 5. Bypass do Solr Feed em Ambiente Local
*   **Problema**: Salvar novas versões de documentos via `EditorRN->adicionarVersao()` dispara automaticamente a indexação no Solr. Em ambientes locais de teste, se a URL do Solr estiver usando placeholders (`[Servidor Solr]`), a biblioteca curl dispara uma exceção fatal de URL malformada que aborta o salvamento.
*   **Solução**: Envolva o método `$objEditorRN->adicionarVersao()` desativando temporariamente o feed de indexação, restaurando-o no bloco `finally`:
    ```php
    require_once dirname(__FILE__) . '/../../FeedSEIProtocolos.php';
    $bolIgnorarOriginal = FeedSEIProtocolos::getInstance()->isBolIgnorarFeeds();
    FeedSEIProtocolos::getInstance()->setBolIgnorarFeeds(true);
    try {
        $objEditorRN->adicionarVersao($objEditorDTO);
    } finally {
        FeedSEIProtocolos::getInstance()->setBolIgnorarFeeds($bolIgnorarOriginal);
    }
    ```

### 6. Simulação de Sessão em Scripts CLI
*   **Problema**: Scripts executados por linha de comando (como testes ou correções de banco) rodam fora de um contexto de sessão do navegador, fazendo com que validações de auditoria do SEI joguem exceções de login.
*   **Solução**: Inicialize a sessão e simule o login por ID (para ignorar parâmetros não definidos do SIP):
    ```php
    SessaoSEI::getInstance(false);
    SessaoSEI::getInstance()->simularLogin(null, null, 100000001, 110000001); // ID Usuário, ID Unidade
    ```
