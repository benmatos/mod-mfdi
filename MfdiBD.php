<?php
/**
 * Módulo MFDI — BD (Acesso a dados e Integração com Editor do SEI)
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */

class MfdiBD extends InfraBD {

    /**
     * Consulta o conteúdo HTML da seção principal de um documento interno do SEI.
     * @param float $numDocumento
     * @return string
     */
    public function consultarConteudoDocumento($numDocumento) {
        $objVersaoSecaoDocumentoDTO = new VersaoSecaoDocumentoDTO();
        $objVersaoSecaoDocumentoDTO->retStrConteudo();
        $objVersaoSecaoDocumentoDTO->retStrSinPrincipalSecaoDocumento();
        $objVersaoSecaoDocumentoDTO->setDblIdDocumentoSecaoDocumento($numDocumento);
        $objVersaoSecaoDocumentoDTO->setStrSinUltima('S');
        $objVersaoSecaoDocumentoDTO->setOrdNumOrdemSecaoDocumento(InfraDTO::$TIPO_ORDENACAO_ASC);

        $objVersaoSecaoDocumentoRN = new VersaoSecaoDocumentoRN();
        $arrObjVersaoSecaoDocumentoDTO = $objVersaoSecaoDocumentoRN->listar($objVersaoSecaoDocumentoDTO);

        if (empty($arrObjVersaoSecaoDocumentoDTO)) {
            return '';
        }

        // Encontrar a seção principal (corpo editável do documento)
        foreach ($arrObjVersaoSecaoDocumentoDTO as $objVersaoSecaoDTO) {
            if ($objVersaoSecaoDTO->getStrSinPrincipalSecaoDocumento() === 'S') {
                return $objVersaoSecaoDTO->getStrConteudo();
            }
        }

        // Fallback: retorna o conteúdo da primeira seção
        return $arrObjVersaoSecaoDocumentoDTO[0]->getStrConteudo();
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
        $objVersaoSecaoDocumentoDTO->retStrSinPrincipalSecaoDocumento();
        $objVersaoSecaoDocumentoDTO->retStrSinSomenteLeituraSecaoDocumento();
        
        $objVersaoSecaoDocumentoDTO->setDblIdDocumentoSecaoDocumento($numDocumento);
        $objVersaoSecaoDocumentoDTO->setNumIdBaseConhecimentoSecaoDocumento(null);
        $objVersaoSecaoDocumentoDTO->setStrSinUltima('S');
        $objVersaoSecaoDocumentoDTO->setOrdNumOrdemSecaoDocumento(InfraDTO::$TIPO_ORDENACAO_ASC);

        $objVersaoSecaoDocumentoRN = new VersaoSecaoDocumentoRN();
        $arrObjVersaoSecaoDocumentoDTO = $objVersaoSecaoDocumentoRN->listar($objVersaoSecaoDocumentoDTO);

        if (empty($arrObjVersaoSecaoDocumentoDTO)) {
            throw new InfraException('Nenhuma seção encontrada para o documento.');
        }

        // 2. Montar as seções atualizadas
        $arrObjSecaoDocumentoDTO = array();
        
        // Identificar se existe uma seção principal para atualizar
        $hasPrincipal = false;
        foreach ($arrObjVersaoSecaoDocumentoDTO as $objVersaoSecaoDTO) {
            if ($objVersaoSecaoDTO->getStrSinPrincipalSecaoDocumento() === 'S') {
                $hasPrincipal = true;
                break;
            }
        }
        
        $isFirst = true;
        foreach ($arrObjVersaoSecaoDocumentoDTO as $objVersaoSecaoDTO) {
            $objSecaoDTO = new SecaoDocumentoDTO();
            $objSecaoDTO->setNumIdSecaoModelo($objVersaoSecaoDTO->getNumIdSecaoModeloSecaoDocumento());
            $objSecaoDTO->setNumIdSecaoDocumento($objVersaoSecaoDTO->getNumIdSecaoDocumento());
            
            // Decidir qual conteúdo aplicar para esta seção
            if ($hasPrincipal) {
                // Se existe uma seção principal, atualiza somente ela
                if ($objVersaoSecaoDTO->getStrSinPrincipalSecaoDocumento() === 'S') {
                    $objSecaoDTO->setStrConteudo($strHtml);
                } else {
                    $objSecaoDTO->setStrConteudo($objVersaoSecaoDTO->getStrConteudo());
                }
            } else {
                // Fallback: se não há seção principal, atualiza a primeira seção
                if ($isFirst) {
                    $objSecaoDTO->setStrConteudo($strHtml);
                    $isFirst = false;
                } else {
                    $objSecaoDTO->setStrConteudo($objVersaoSecaoDTO->getStrConteudo());
                }
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
