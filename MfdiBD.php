<?php
/**
 * Módulo MFDI — BD (Acesso a dados e Integração com Editor do SEI)
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */

class MfdiBD extends InfraBD {

    /**
     * Consulta o conteúdo HTML de um documento interno do SEI utilizando o EditorRN.
     * @param float $numDocumento
     * @return string
     */
    public function consultarConteudoDocumento($numDocumento) {
        $objEditorDTO = new EditorDTO();
        $objEditorDTO->setDblIdDocumento($numDocumento);
        $objEditorDTO->setNumIdBaseConhecimento(null);
        $objEditorDTO->setStrSinCabecalho('S');
        $objEditorDTO->setStrSinRodape('S');
        $objEditorDTO->setStrSinCarimboPublicacao('N');
        $objEditorDTO->setStrSinIdentificacaoVersao('N');

        $objEditorRN = new EditorRN();
        return $objEditorRN->consultarHtmlVersao($objEditorDTO);
    }

    /**
     * Atualiza o conteúdo HTML de um documento interno do SEI criando uma nova versão através do EditorRN.
     * @param float $numDocumento
     * @param string $strHtml
     */
    public function atualizarConteudoDocumento($numDocumento, $strHtml) {
        // 1. Consultar as seções atuais da última versão ativa do documento
        $objVersaoSecaoDocumentoDTO = new VersaoSecaoDocumentoDTO();
        $objVersaoSecaoDocumentoDTO->retNumIdSecaoModeloSecaoDocumento();
        $objVersaoSecaoDocumentoDTO->retStrConteudo();
        $objVersaoSecaoDocumentoDTO->retNumVersao();
        $objVersaoSecaoDocumentoDTO->retNumIdSecaoDocumento();
        $objVersaoSecaoDocumentoDTO->setDblIdDocumentoSecaoDocumento($numDocumento);
        $objVersaoSecaoDocumentoDTO->setNumIdBaseConhecimentoSecaoDocumento(null);
        $objVersaoSecaoDocumentoDTO->setStrSinUltima('S');
        $objVersaoSecaoDocumentoDTO->setOrdNumOrdemSecaoDocumento(InfraDTO::$TIPO_ORDENACAO_ASC);

        $objVersaoSecaoDocumentoRN = new VersaoSecaoDocumentoRN();
        $arrObjVersaoSecaoDocumentoDTO = $objVersaoSecaoDocumentoRN->listar($objVersaoSecaoDocumentoDTO);

        if (empty($arrObjVersaoSecaoDocumentoDTO)) {
            throw new InfraException('Nenhuma seção encontrada para o documento.');
        }

        // 2. Se o documento tiver apenas 1 seção (caso padrão), atualizamos o conteúdo dessa única seção com todo o HTML preenchido
        // Caso possua múltiplas seções, atualizamos a primeira seção e deixamos as demais vazias para manter o conteúdo concentrado nela,
        // ou atualizamos conforme a estrutura. Para o MVP de formulários dinâmicos integrados, concentrar na primeira seção garante consistência.
        $arrObjSecaoDocumentoDTO = array();
        $isFirst = true;
        foreach ($arrObjVersaoSecaoDocumentoDTO as $objVersaoSecaoDTO) {
            $objSecaoDTO = new SecaoDocumentoDTO();
            $objSecaoDTO->setNumIdSecaoModelo($objVersaoSecaoDTO->getNumIdSecaoModeloSecaoDocumento());
            $objSecaoDTO->setNumIdSecaoDocumento($objVersaoSecaoDTO->getNumIdSecaoDocumento());
            
            if ($isFirst) {
                $objSecaoDTO->setStrConteudo($strHtml);
                $isFirst = false;
            } else {
                $objSecaoDTO->setStrConteudo(''); // Zera as demais seções para evitar duplicação ou conflitos
            }
            $arrObjSecaoDocumentoDTO[] = $objSecaoDTO;
        }

        // 3. Montar o DTO do Editor e salvar a nova versão
        $objEditorDTO = new EditorDTO();
        $objEditorDTO->setDblIdDocumento($numDocumento);
        $objEditorDTO->setNumIdBaseConhecimento(null);
        $objEditorDTO->setArrObjSecaoDocumentoDTO($arrObjSecaoDocumentoDTO);

        $objEditorRN = new EditorRN();
        
        require_once dirname(__FILE__) . '/../../FeedSEIProtocolos.php';
        $bolIgnorarOriginal = FeedSEIProtocolos::getInstance()->isBolIgnorarFeeds();
        FeedSEIProtocolos::getInstance()->setBolIgnorarFeeds(true);
        try {
            $objEditorRN->adicionarVersao($objEditorDTO);
        } finally {
            FeedSEIProtocolos::getInstance()->setBolIgnorarFeeds($bolIgnorarOriginal);
        }
    }
}
