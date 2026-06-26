<?php
/**
 * Módulo MFDI — Integração principal com o SEI
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */
require_once dirname(__FILE__) . '/MfdiDTO.php';
require_once dirname(__FILE__) . '/MfdiBD.php';
require_once dirname(__FILE__) . '/MfdiRN.php';
require_once dirname(__FILE__) . '/MfdiINT.php';

class MfdiIntegracao extends SeiIntegracao {

    private static $VERSAO = '1.0.0';

    public function getNome()        { return 'Módulo Formulários Dinâmicos Integrados (MFDI)'; }
    public function getVersao()      { return self::$VERSAO; }
    public function getInstituicao() { return 'MGI - Ministério da Gestão e da Inovação em Serviços Públicos'; }

    public function inicializar($strVersaoSEI) {
        if (version_compare($strVersaoSEI, '5.0.0', '<')) {
            throw new InfraException('Módulo MFDI requer SEI >= 5.0.0. Detectada: ' . $strVersaoSEI);
        }
    }

    public static function getDiretorio() {
        return 'modulos/mod-mfdi';
    }

    public function getArrScriptsEditor() {
        return array($this->getDiretorio() . '/js/mfdi_ckeditor_plugin.js');
    }

    public function montarBotaoChatIA() {
        $strScript = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $strAcao = isset($_GET['acao']) ? $_GET['acao'] : '';
        
        $bolEditor = (strpos($strScript, 'editor_processar.php') !== false) || 
                     (in_array($strAcao, array('editor_montar', 'editor_simular')));
        
        if ($bolEditor) {
            $strPath = (strpos($strScript, '/editor/') !== false) ? '../modulos/mod-mfdi/js/mfdi_ckeditor_plugin.js' : 'modulos/mod-mfdi/js/mfdi_ckeditor_plugin.js';
            return '<script type="text/javascript" src="' . $strPath . '?v=' . time() . '"></script>';
        }
        
        return null;
    }

    public function getArrAtributosPermitidos() {
        return array(
            'data-sei-field',
            'data-sei-label',
            'data-sei-type',
            'data-sei-required',
            'data-sei-options'
        );
    }

    public function getArrRecursos() {
        return array('md_mfdi_formulario' => 'Preencher Formulário Dinâmico');
    }

    public function processarControlador($strAcao) {
        $arr = array('md_mfdi_formulario', 'md_mfdi_salvar');
        return in_array($strAcao, $arr) ? dirname(__FILE__) . '/MfdiController.php' : null;
    }

    /**
     * Hook para montagem de botões na árvore/visualização de documentos do SEI
     * @param ProcedimentoAPI $objProcedimentoAPI
     * @param array $arrObjDocumentoAPI
     * @return array
     */
    public function montarBotaoDocumento(ProcedimentoAPI $objProcedimentoAPI, $arrObjDocumentoAPI) {
        $arrBotoes = array();
        
        // Verifica se o usuário tem permissão para a ação de formulário
        if (SessaoSEI::getInstance()->verificarPermissao('md_mfdi_formulario')) {
            $dblIdProcedimento = $objProcedimentoAPI->getIdProcedimento();
            
            foreach ($arrObjDocumentoAPI as $objDocumentoAPI) {
                // A ação deve aparecer apenas em documentos internos editáveis (Gerados, não assinados e não bloqueados)
                if ($objDocumentoAPI->getTipo() == 'G' && $objDocumentoAPI->getSinAssinado() == 'N' && $objDocumentoAPI->getSinBloqueado() == 'N') {
                    $dblIdDocumento = $objDocumentoAPI->getIdDocumento();
                    $arrBotoes[$dblIdDocumento] = array();
                    
                    // Assina link
                    $strLink = SessaoSEI::getInstance()->assinarLink(
                        'controlador.php?acao=md_mfdi_formulario&id_procedimento=' . $dblIdProcedimento . '&id_documento=' . $dblIdDocumento
                    );
                    $strProxTabBarra = PaginaSEI::getInstance()->getProxTabBarraComandosSuperior();
                    
                    // Renderiza o botão a partir do template
                    ob_start();
                    include dirname(__FILE__) . '/tpl/mfdi_botao.php';
                    $strBotaoHtml = ob_get_clean();
                    
                    // Remove newlines to prevent breaking the JS string literal in the tree view
                    $strBotaoHtml = str_replace(array("\r", "\n"), '', $strBotaoHtml);
                    
                    $arrBotoes[$dblIdDocumento][] = $strBotaoHtml;
                }
            }
        }
        
        return $arrBotoes;
    }
}
