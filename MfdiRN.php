<?php
/**
 * Módulo MFDI — RN (Regras de Negócio)
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */

require_once dirname(__FILE__) . '/MfdiDTO.php';
require_once dirname(__FILE__) . '/MfdiBD.php';



class MfdiRN extends InfraRN {

    protected function inicializarObjInfraIBanco() {
        return BancoSEI::getInstance();
    }

    /**
     * Carrega o formulário validando estado e extraindo campos.
     * @param MfdiDTO $objDTO
     * @return MfdiDTO
     */
    protected function carregarFormularioControlado(MfdiDTO $objDTO) {
        $this->validarEstadoDocumento($objDTO->getNumDocumento());
        
        $bd = new MfdiBD($this->getObjInfraIBanco());
        $strHtml = $bd->consultarConteudoDocumento($objDTO->getNumDocumento());
        
        $objDTO->setStrConteudoHtml($strHtml);
        $arrCampos = $this->extrairCampos($strHtml);
        $objDTO->setArrCampos($arrCampos);
        
        return $objDTO;
    }

    /**
     * Salva o formulário validando estado, validando campos obrigatórios e preenchendo o HTML.
     * @param MfdiDTO $objDTO
     */
    protected function salvarFormularioControlado(MfdiDTO $objDTO) {
        // Validação de Permissão e Auditoria
        SessaoSEI::getInstance()->validarAuditarPermissao('md_mfdi_formulario', __METHOD__, $objDTO);

        $this->validarEstadoDocumento($objDTO->getNumDocumento());
        $this->validarCamposObrigatorios($objDTO->getArrCampos());
        
        $bd = new MfdiBD($this->getObjInfraIBanco());
        $strOriginalHtml = $bd->consultarConteudoDocumento($objDTO->getNumDocumento());
        
        $strHtmlPreenchido = $this->preencherHtml($strOriginalHtml, $objDTO->getArrCampos());
        
        $bd->atualizarConteudoDocumento($objDTO->getNumDocumento(), $strHtmlPreenchido);
    }

    /**
     * Extrai todos os campos anotados com classes sei-field-- do HTML do documento.
     * @param string $strHtml
     * @return array
     */
    public function extrairCampos($strHtml) {
        $arrCampos = array();
        if (InfraString::isBolVazia($strHtml)) {
            return $arrCampos;
        }

        $dom = new DOMDocument();
        // Detect encoding to handle both UTF-8 and ISO-8859-1 installations
        $encoding = mb_detect_encoding($strHtml, array('UTF-8', 'ISO-8859-1'), true);
        if (!$encoding) {
            $encoding = 'UTF-8';
        }
        $strHtmlUtf8 = mb_convert_encoding($strHtml, 'HTML-ENTITIES', $encoding);
        
        $isFullHtml = (stripos($strHtml, '<html') !== false || stripos($strHtml, '<body') !== false || stripos($strHtml, '<!DOCTYPE') !== false);
        
        $oldState = libxml_use_internal_errors(true);
        if ($isFullHtml) {
            $dom->loadHTML($strHtmlUtf8);
        } else {
            $dom->loadHTML('<div>' . $strHtmlUtf8 . '</div>');
        }
        libxml_clear_errors();
        libxml_use_internal_errors($oldState);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(@class, 'sei-field--')]");

        foreach ($nodes as $node) {
            $classStr = $node->getAttribute('class');
            $classes = explode(' ', $classStr);
            
            $field = '';
            $type = 'texto';
            $required = false;
            $options = '';
            
            foreach ($classes as $cls) {
                $cls = trim($cls);
                if (strpos($cls, 'sei-field--') === 0) {
                    $field = substr($cls, strlen('sei-field--'));
                } elseif (strpos($cls, 'sei-type--') === 0) {
                    $type = substr($cls, strlen('sei-type--'));
                } elseif ($cls === 'sei-required') {
                    $required = true;
                } elseif (strpos($cls, 'sei-options--') === 0) {
                    $optionsEncoded = substr($cls, strlen('sei-options--'));
                    $decoded = self::base64url_decode($optionsEncoded);
                    if ($decoded !== false && (strpos($decoded, '|') !== false || strpos($decoded, '=') !== false || strpos($decoded, ',') !== false)) {
                        $options = $decoded;
                    } else {
                        // Fallback: se não for base64url, interpreta separado por traço
                        $options = str_replace('-', ',', $optionsEncoded);
                    }
                }
            }
            
            if (empty($field)) {
                continue;
            }
            
            // Humanização do nome do campo (Opção A)
            $label = str_replace('_', ' ', ucwords($field));
            
            $value = $node->nodeValue;
            // Substitui non-breaking spaces (\xc2\xa0) e entidades &nbsp; por espaço simples para o trim remover
            $value = str_replace(array("\xc2\xa0", "&nbsp;"), ' ', $value);
            $value = trim($value);
            
            if ($encoding !== 'UTF-8') {
                $value = mb_convert_encoding($value, $encoding, 'UTF-8');
                if (!empty($options)) {
                    $options = mb_convert_encoding($options, $encoding, 'UTF-8');
                }
            }
            
            $arrCampos[] = array(
                'field' => $field,
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'value' => $value,
                'options' => $options,
                'options_array' => self::parseOptions($options)
            );
        }
        return $arrCampos;
    }

    /**
     * Substitui os valores dos nós correspondentes aos campos informados.
     * @param string $strHtml
     * @param array $arrCampos
     * @return string
     */
    public function preencherHtml($strHtml, $arrCampos) {
        if (InfraString::isBolVazia($strHtml)) {
            return $strHtml;
        }

        $mapValues = array();
        foreach ($arrCampos as $campo) {
            $mapValues[$campo['field']] = $campo['value'];
        }

        $dom = new DOMDocument();
        // Detect encoding to handle both UTF-8 and ISO-8859-1 installations
        $encoding = mb_detect_encoding($strHtml, array('UTF-8', 'ISO-8859-1'), true);
        if (!$encoding) {
            $encoding = 'UTF-8';
        }
        $strHtmlUtf8 = mb_convert_encoding($strHtml, 'HTML-ENTITIES', $encoding);
        
        $isFullHtml = (stripos($strHtml, '<html') !== false || stripos($strHtml, '<body') !== false || stripos($strHtml, '<!DOCTYPE') !== false);
        
        $oldState = libxml_use_internal_errors(true);
        if ($isFullHtml) {
            $dom->loadHTML($strHtmlUtf8);
        } else {
            $dom->loadHTML('<div>' . $strHtmlUtf8 . '</div>');
        }
        libxml_clear_errors();
        libxml_use_internal_errors($oldState);

        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query("//*[contains(@class, 'sei-field--')]");
        foreach ($nodes as $node) {
            $classStr = $node->getAttribute('class');
            $classes = explode(' ', $classStr);
            
            $field = '';
            foreach ($classes as $cls) {
                $cls = trim($cls);
                if (strpos($cls, 'sei-field--') === 0) {
                    $field = substr($cls, strlen('sei-field--'));
                    break;
                }
            }
            
            if (!empty($field) && isset($mapValues[$field])) {
                // Remove todos os filhos atuais do elemento
                while ($node->hasChildNodes()) {
                    $node->removeChild($node->firstChild);
                }
                // Adiciona o novo texto
                $node->appendChild($dom->createTextNode($mapValues[$field]));
            }
        }

        if ($isFullHtml) {
            $newHtmlUtf8 = $dom->saveHTML();
        } else {
            // Recupera o HTML
            $wrapper = $dom->getElementsByTagName('body')->item(0)->firstChild;
            $newHtmlUtf8 = '';
            foreach ($wrapper->childNodes as $child) {
                $newHtmlUtf8 .= $dom->saveHTML($child);
            }
        }
        
        return mb_convert_encoding($newHtmlUtf8, $encoding, 'UTF-8');
    }

    /**
     * Auxiliar para interpretar e parsear strings de opções de listas.
     * @param string $strOptions
     * @return array
     */
    public static function parseOptions($strOptions) {
        if (InfraString::isBolVazia($strOptions)) {
            return array();
        }
        
        if (strpos($strOptions, '[') === 0 || strpos($strOptions, '{') === 0) {
            $json = json_decode($strOptions, true);
            if (is_array($json)) {
                return $json;
            }
        }
        
        $arr = array();
        $separator = (strpos($strOptions, '|') !== false) ? '|' : ',';
        $parts = explode($separator, $strOptions);
        foreach ($parts as $part) {
            $sub = explode('=', $part, 2);
            if (count($sub) === 2) {
                $arr[] = array('value' => trim($sub[0]), 'label' => trim($sub[1]));
            } else {
                $arr[] = array('value' => trim($part), 'label' => trim($part));
            }
        }
        return $arr;
    }

    /**
     * Decodifica string no formato base64url.
     * @param string $data
     * @return string|false
     */
    public static function base64url_decode($data) {
        $b64 = strtr($data, '-_', '+/');
        $padding = strlen($b64) % 4;
        if ($padding) {
            $b64 .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($b64);
    }

    /**
     * Valida se o documento está editável e se encontra na unidade atual.
     * @param float $numDocumento
     * @throws InfraException
     */
    public function validarEstadoDocumento($numDocumento) {
        $objDocumentoDTO = new DocumentoDTO();
        $objDocumentoDTO->retDblIdDocumento();
        $objDocumentoDTO->retDblIdProcedimento();
        $objDocumentoDTO->retStrSinBloqueado();
        $objDocumentoDTO->retNumIdUnidadeGeradoraProtocolo();
        $objDocumentoDTO->setDblIdDocumento($numDocumento);
        
        $objDocumentoRN = new DocumentoRN();
        $objDocumentoDTO = $objDocumentoRN->consultarRN0005($objDocumentoDTO);
        
        if (!$objDocumentoDTO) {
            throw new InfraException('Documento não encontrado.');
        }
        
        // Verifica se o documento possui alguma assinatura
        $objAssinaturaDTO = new AssinaturaDTO();
        $objAssinaturaDTO->retNumIdAssinatura();
        $objAssinaturaDTO->setDblIdDocumento($numDocumento);
        $objAssinaturaDTO->setNumMaxRegistrosRetorno(1);
        $objAssinaturaRN = new AssinaturaRN();
        $arrObjAssinaturaDTO = $objAssinaturaRN->listarRN1323($objAssinaturaDTO);
        if (count($arrObjAssinaturaDTO) > 0) {
            throw new InfraException('Documento já assinado.');
        }
        
        if ($objDocumentoDTO->getStrSinBloqueado() === 'S') {
            throw new InfraException('Documento bloqueado para edição.');
        }
        
        // Verifica se o processo associado está aberto na unidade atual
        $objAtividadeDTO = new AtividadeDTO();
        $objAtividadeDTO->retNumIdAtividade();
        $objAtividadeDTO->setDblIdProtocolo($objDocumentoDTO->getDblIdProcedimento());
        $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objAtividadeDTO->setDthConclusao(null);
        
        $objAtividadeRN = new AtividadeRN();
        if ($objAtividadeRN->contarRN0035($objAtividadeDTO) == 0) {
            throw new InfraException('Documento em trâmite em outra unidade ou concluído.');
        }
    }

    /**
     * Valida os campos obrigatórios.
     * @param array $arrCampos
     * @throws InfraException
     */
    public function validarCamposObrigatorios($arrCampos) {
        $objInfraException = new InfraException();
        foreach ($arrCampos as $campo) {
            if ($campo['required'] && InfraString::isBolVazia(trim($campo['value']))) {
                $objInfraException->adicionarValidacao('O campo "' . $campo['label'] . '" é obrigatório.');
            }
        }
        $objInfraException->lancarValidacoes();
    }
}
