# Prompt — Implementação do Módulo MFDI (Formulários Dinâmicos Integrados) para o SEI

## Contexto

Você é um agente de codificação especializado em desenvolvimento de módulos nativos para o SEI
(Sistema Eletrônico de Informações), utilizando o framework InfraPHP/SinfoniaPHP.

Você tem acesso ao manual técnico oficial SEI-Modulos-v5.0.pdf na pasta atual.
```

**Leia esse manual antes de escrever qualquer linha de código.** Ele define a API de integração,
os hooks de registro de ações, os métodos de acesso a documentos, e os padrões obrigatórios de
codificação PHP. Qualquer decisão arquitetural que dependa de nome de classe, método ou constante
do SEI deve ser confirmada no manual antes de ser implementada.

---

## Objetivo

Implementar o MVP do Módulo de Formulários Dinâmicos Integrados (MFDI), que transforma documentos
HTML nativos do SEI em formulários estruturados de preenchimento, sem criar persistência própria.

---

## Restrições Arquiteturais Inegociáveis

1. **Sem persistência própria** — o módulo não cria tabelas, schemas, sequences, procedures ou
   triggers. Toda persistência ocorre exclusivamente nos documentos nativos do SEI.

2. **Sem acesso direto ao banco** — nenhum SQL deve ser executado diretamente. Todo acesso ao
   banco passa pela interface `InfraIBanco` ou pelas classes BD do SEI conforme documentado no
   manual.

3. **Sem modificação do core** — nenhum arquivo fora do diretório `sei/web/modulos/mod-mfdi/`
   deve ser alterado. A integração com o SEI ocorre exclusivamente pelos mecanismos de registro
   de módulo definidos no manual.

4. **Sem herança direta de classes do core** — DTOs, BDs, RNs e Controllers do módulo estendem
   apenas as classes base do InfraPHP (`InfraDTO`, `InfraBD`, `InfraRN`), nunca classes internas
   do SEI diretamente, salvo se o manual explicitamente indicar o contrário.

5. **Sem SQL fora da camada BD** — a camada RN nunca executa SQL. A camada BD nunca executa
   lógica de negócio.

---

## Estrutura de Arquivos a Criar

```
C:\Sistemas\seiMGI\fontes\sei\web\modulos\mod-mfdi\
├── MfdiDTO.php
├── MfdiBD.php
├── MfdiRN.php
├── MfdiINT.php
├── MfdiController.php
├── md_mfdi_instalar.php         ← script de instalação/atualização do módulo
└── tpl/
    ├── mfdi_formulario.php      ← template da interface de preenchimento
    └── mfdi_botao.php           ← injeção do botão na tela do documento
```

---

## Responsabilidades de Cada Camada

### MfdiDTO.php
Objeto de transporte entre camadas. Sem lógica.

Campos necessários:
- `$numDocumento` — identificador do documento SEI
- `$strConteudoHtml` — conteúdo HTML atual do documento
- `$arrCampos` — array de campos extraídos/preenchidos, cada item contendo:
  - `field` (string) — nome do campo, valor do atributo `data-sei-field`
  - `label` (string) — rótulo exibido ao usuário, valor do atributo `data-sei-label`
  - `type` (string) — tipo do campo: `texto`, `numero`, `moeda`, `data`, `boolean`, `lista`, `textarea`
  - `required` (bool) — obrigatoriedade
  - `value` (string) — valor atual ou preenchido

### MfdiBD.php
Acesso a dados exclusivamente via mecanismos do SEI/InfraPHP.

Métodos necessários:
- `consultarConteudoDocumento($numDocumento)` — retorna o HTML atual do documento
- `atualizarConteudoDocumento($numDocumento, $strHtml)` — persiste o HTML atualizado

**Importante:** consulte o manual para identificar quais classes e métodos do SEI estão disponíveis
para leitura e atualização de conteúdo de documentos internos. Use exatamente esses métodos.
Verifique também se há necessidade de verificação de bloqueio ou estado do documento antes da
atualização.

### MfdiRN.php
Toda a lógica de negócio fica aqui. Nunca acessa banco diretamente.

Métodos necessários:
- `carregarFormulario(MfdiDTO $objDTO)` — orquestra: valida estado do documento, busca HTML via
  BD, extrai campos com `data-sei-field`, retorna DTO populado
- `salvarFormulario(MfdiDTO $objDTO)` — valida obrigatórios, preenche HTML via DOM, persiste via BD
- `extrairCampos($strHtml)` — usa `DOMDocument` + `DOMXPath` para extrair todos os nós com
  `data-sei-field` e seus atributos `data-sei-*`
- `preencherHtml($strHtml, $arrCampos)` — usa `DOMDocument` + `DOMXPath` para substituir o
  `nodeValue` de cada nó identificado pelo `data-sei-field` correspondente
- `validarEstadoDocumento($numDocumento)` — verifica se o documento está em estado editável;
  lançar `InfraException` se estiver assinado, bloqueado ou em trâmite
- `validarCamposObrigatorios($arrCampos)` — lançar `InfraException` com mensagem identificando
  o label do campo se `required === true` e `value` vazio

### MfdiINT.php
Ponto de integração com outros módulos ou serviços do SEI, se necessário no MVP.
Consulte o manual para verificar se o registro de ações de menu exige um INT ou ocorre
diretamente no script de instalação. Implemente conforme o manual indicar.

### MfdiController.php
Dois endpoints:

- `inicializarHTML()` — recebe `$_GET['numDocumento']`, chama `MfdiRN::carregarFormulario()`,
  renderiza `tpl/mfdi_formulario.php`
- `salvar()` — recebe POST com `numDocumento`, `arrCampos`, chama `MfdiRN::salvarFormulario()`,
  retorna JSON `{"sucesso": true}` ou lança exceção tratada

### md_mfdi_instalar.php
Script de instalação seguindo o padrão do manual (seção de scripts de instalação/atualização).

Deve registrar:
- O módulo no SEI (nome, versão, descrição)
- A ação "Formulário" na tela de visualização de documento, apontando para o controller

Consulte o manual para o método exato de cadastro de ações e o formato esperado (ícone, URL,
contexto de exibição — a ação deve aparecer apenas em documentos internos editáveis).

---

## Modelo de Marcação dos Campos no Documento HTML

Os documentos compatíveis usam atributos `data-sei-*` nas células ou elementos:

```html
<td
  data-sei-field="empresa"
  data-sei-label="Empresa Contratada"
  data-sei-type="texto"
  data-sei-required="true">
  Valor atual aqui
</td>
```

Atributos suportados no MVP:

| Atributo           | Obrigatório | Descrição                                              |
|--------------------|-------------|--------------------------------------------------------|
| `data-sei-field`   | Sim         | Identificador único do campo no documento              |
| `data-sei-label`   | Sim         | Rótulo exibido no formulário                           |
| `data-sei-type`    | Sim         | Tipo: `texto`, `numero`, `moeda`, `data`, `boolean`, `lista`, `textarea` |
| `data-sei-required`| Não         | `"true"` para obrigatório                              |

---

## Template mfdi_formulario.php

Interface gerada dinamicamente a partir de `$arrCampos`. Deve:

- Renderizar um campo de formulário HTML para cada item do array, usando o `type` para determinar
  o elemento (`input[type=text]`, `input[type=date]`, `select`, `textarea`, etc.)
- Aplicar `required` no elemento HTML quando `required === true`
- Pré-preencher o campo com `value` atual
- Ter um botão "Salvar" que submete via AJAX (fetch/XHR) para `MfdiController::salvar()`
- Após salvar com sucesso: fechar o modal/popup e recarregar a visualização do documento pai
- Tratar erros da API exibindo a mensagem da `InfraException` ao usuário

Estilo: usar as classes CSS padrão do SEI para manter consistência visual. Consulte o manual ou
inspecione os templates de outros módulos para identificar as classes corretas.

---

## Fluxo Completo Esperado

```
Usuário clica em [Formulário] na tela do documento
        ↓
MfdiController::inicializarHTML()
        ↓
MfdiRN::carregarFormulario() → valida estado → MfdiBD::consultarConteudo() → extrairCampos()
        ↓
tpl/mfdi_formulario.php renderiza com os campos e valores atuais
        ↓
Usuário preenche e clica em [Salvar]
        ↓
MfdiController::salvar() (POST)
        ↓
MfdiRN::salvarFormulario() → valida obrigatórios → preencherHtml() → MfdiBD::atualizar()
        ↓
JSON {"sucesso": true} → modal fecha → documento recarrega
```

---

## Checklist de Conformidade (verificar antes de entregar)

- [ ] Nenhuma tabela criada ou referenciada fora do core do SEI
- [ ] Nenhum SQL escrito fora da camada BD
- [ ] Nenhuma classe do core do SEI estendida diretamente sem respaldo no manual
- [ ] Registro do módulo e da ação feito exclusivamente via script de instalação
- [ ] Toda exceção de negócio lançada como `InfraException`
- [ ] Template usa classes CSS do SEI, não estilos inline próprios
- [ ] Parse do HTML usa `DOMDocument`/`DOMXPath`, não regex
- [ ] Controller não contém lógica de negócio
- [ ] RN não contém SQL
- [ ] Nomes de arquivo e classes seguem o padrão `Mfdi` + camada (conforme manual seção PHP)

---

## O que NÃO fazer

- Não usar `mysqli_*` ou PDO diretamente
- Não criar arquivos fora do diretório `mod-mfdi/`
- Não modificar `ConfiguracaoSEI.php` ou qualquer arquivo do core
- Não assumir nomes de métodos do SEI sem confirmar no manual — use o manual como fonte primária
- Não usar `innerHTML` ou manipulação de string para atualizar o HTML do documento —
  use `DOMDocument` para garantir integridade estrutural
- Não armazenar estado entre requisições fora do próprio documento SEI
