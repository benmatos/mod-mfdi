<?php
/**
 * Módulo MFDI — Controller
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */
require_once dirname(__FILE__) . '/MfdiDTO.php';
require_once dirname(__FILE__) . '/MfdiBD.php';
require_once dirname(__FILE__) . '/MfdiRN.php';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $strAcao = isset($_GET['acao']) ? $_GET['acao'] : (isset($_POST['acao']) ? $_POST['acao'] : null);
    
    switch ($strAcao) {
        
        case 'md_mfdi_formulario':
            SessaoSEI::getInstance()->verificarPermissao('md_mfdi_formulario');
            
            $numDocumento = isset($_GET['id_documento']) ? $_GET['id_documento'] : (isset($_POST['numDocumento']) ? $_POST['numDocumento'] : null);
            if (InfraString::isBolVazia($numDocumento)) {
                throw new InfraException('Identificador do documento não fornecido.');
            }
            
            $idProcedimento = isset($_GET['id_procedimento']) ? $_GET['id_procedimento'] : '';
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $arrCamposPost = $_POST['campos'];
                
                $arrCampos = array();
                if (is_array($arrCamposPost)) {
                    foreach ($arrCamposPost as $field => $data) {
                        $arrCampos[] = array(
                            'field' => $field,
                            'label' => $data['label'],
                            'type' => $data['type'],
                            'required' => $data['required'] === 'true' || $data['required'] === '1' || $data['required'] === true,
                            'value' => $data['value']
                        );
                    }
                }
                
                $objDTO = new MfdiDTO();
                $objDTO->setNumDocumento($numDocumento);
                $objDTO->setArrCampos($arrCampos);
                
                $objRN = new MfdiRN();
                // Executado de forma controlada via InfraRN
                $objRN->salvarFormulario($objDTO);
                
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array('sucesso' => true));
                exit;
            } else {
                $objDTO = new MfdiDTO();
                $objDTO->setNumDocumento($numDocumento);
                
                $objRN = new MfdiRN();
                // Executado de forma controlada via InfraRN
                $objDTO = $objRN->carregarFormulario($objDTO);
                
                include dirname(__FILE__) . '/tpl/mfdi_formulario.php';
            }
            break;
            
        default:
            throw new InfraException('Ação "' . $strAcao . '" não reconhecida no módulo MFDI.');
    }
    
} catch (Throwable $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        
        $strErro = $e->getMessage();
        if ($e instanceof InfraException && $e->getArrObjInfraValidacao() !== null) {
            $arrMsgs = array();
            foreach ($e->getArrObjInfraValidacao() as $objValidacao) {
                $arrMsgs[] = $objValidacao->getStrDescricao();
            }
            if (!empty($arrMsgs)) {
                $strErro = implode("\n", $arrMsgs);
            }
        }
        
        echo json_encode(array('sucesso' => false, 'erro' => $strErro));
        exit;
    }
    PaginaSEI::getInstance()->processarExcecao($e);
}
