<?php
/**
 * Módulo MFDI — Template do Formulário Dinâmico
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */
$p = PaginaSEI::getInstance();

$strHtml = '';

// Alerta de erros
$strHtml .= '<div id="divErros" style="display:none; margin-bottom:15px; padding:10px; background-color:#f8d7da; border:1px solid #f5c6cb; color:#721c24; border-radius:4px; font-weight:bold;"></div>';

$strHtml .= '<form id="frmMfdi" method="POST" onsubmit="salvarFormulario(event);">';
$strHtml .= '<input type="hidden" id="numDocumento" value="' . htmlspecialchars($objDTO->getNumDocumento()) . '" />';

$strHtml .= '<table class="infraTable" width="100%">';

$arrCampos = $objDTO->getArrCampos();
if (empty($arrCampos)) {
    $strHtml .= '<tr><td colspan="2" align="center" style="padding: 20px; font-style: italic; color: #555;">Este documento não possui campos anotados para o formulário dinâmico (atributo data-sei-field).</td></tr>';
} else {
    foreach ($arrCampos as $campo) {
        $field = htmlspecialchars($campo['field']);
        $label = htmlspecialchars($campo['label']);
        $type = htmlspecialchars($campo['type']);
        $required = $campo['required'];
        $value = htmlspecialchars($campo['value']);
        
        $strRequiredLabel = $required ? ' <span style="color:red; font-weight:bold;">*</span>' : '';
        $strRequiredAttr = $required ? 'required="required"' : '';
        
        $strHtml .= '<tr>';
        $strHtml .= '<td class="infraTdRotulo" width="30%" valign="top" style="padding:8px; font-weight:bold;">' . $label . $strRequiredLabel . ':</td>';
        $strHtml .= '<td style="padding:8px;">';
        
        $inputClass = 'infraInput';
        $inputStyle = 'width: 95%; box-sizing: border-box; padding: 5px; font-size: 13px;';
        
        switch ($type) {
            case 'textarea':
                $strHtml .= '<textarea class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" style="' . $inputStyle . ' height:100px;" ' . $strRequiredAttr . '>' . $value . '</textarea>';
                break;
                
            case 'boolean':
                $strHtml .= '<select class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" style="width: 150px; padding: 5px; font-size: 13px;" ' . $strRequiredAttr . '>';
                $strHtml .= '<option value=""' . ($value === '' ? ' selected' : '') . '>Selecione...</option>';
                $strHtml .= '<option value="Sim"' . (strtolower($value) === 'sim' || strtolower($value) === 's' ? ' selected' : '') . '>Sim</option>';
                $strHtml .= '<option value="Não"' . (strtolower($value) === 'não' || strtolower($value) === 'nao' || strtolower($value) === 'n' ? ' selected' : '') . '>Não</option>';
                $strHtml .= '</select>';
                break;
                
            case 'lista':
                $arrOptions = isset($campo['options_array']) ? $campo['options_array'] : array();
                if (!empty($arrOptions)) {
                    $strHtml .= '<select class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" style="' . $inputStyle . '" ' . $strRequiredAttr . '>';
                    $strHtml .= '<option value=""' . ($value === '' ? ' selected' : '') . '>Selecione...</option>';
                    foreach ($arrOptions as $opt) {
                        $optValue = htmlspecialchars($opt['value']);
                        $optLabel = htmlspecialchars($opt['label']);
                        $strHtml .= '<option value="' . $optValue . '"' . ($value === $opt['value'] || $value === $opt['label'] ? ' selected' : '') . '>' . $optLabel . '</option>';
                    }
                    $strHtml .= '</select>';
                } else {
                    $strHtml .= '<input type="text" class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" style="' . $inputStyle . '" ' . $strRequiredAttr . ' />';
                }
                break;
                
            case 'numero':
                $strHtml .= '<input type="number" class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" style="' . $inputStyle . '" ' . $strRequiredAttr . ' />';
                break;
                
            case 'moeda':
                $strHtml .= '<input type="text" class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" placeholder="0,00" style="' . $inputStyle . '" ' . $strRequiredAttr . ' oninput="mascaraMoeda(this);" />';
                break;
                
            case 'data':
                // SEI date inputs can be simple or standard HTML5 date inputs
                $strHtml .= '<input type="date" class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" style="width: 150px; padding: 5px; font-size: 13px;" ' . $strRequiredAttr . ' />';
                break;
                
            case 'texto':
            default:
                $strHtml .= '<input type="text" class="' . $inputClass . ' mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" style="' . $inputStyle . '" ' . $strRequiredAttr . ' />';
                break;
        }
        
        $strHtml .= '</td>';
        $strHtml .= '</tr>';
    }
}

$strHtml .= '</table>';

$strLinkRetorno = SessaoSEI::getInstance()->assinarLink(
    'controlador.php?acao=documento_visualizar&id_procedimento=' . $idProcedimento . '&id_documento=' . $objDTO->getNumDocumento()
);

$strHtml .= '<div style="margin-top:25px; text-align:center; padding-bottom: 20px;">';
if (!empty($arrCampos)) {
    $strHtml .= '<button type="submit" id="btnSalvar" class="infraButton">Salvar</button> ';
}
$strHtml .= '<button type="button" class="infraButton" onclick="window.location.href=\'' . $strLinkRetorno . '\';">Cancelar</button>';
$strHtml .= '</div>';
$strHtml .= '</form>';

$strHtml .= '
<script type="text/javascript">
const urlRetorno = "' . $strLinkRetorno . '";

function mascaraMoeda(campo) {
    let valor = campo.value;
    valor = valor.replace(/\D/g, "");
    if (valor === "") {
        campo.value = "";
        return;
    }
    valor = (parseInt(valor) / 100).toFixed(2) + "";
    valor = valor.replace(".", ",");
    valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    campo.value = valor;
}

function salvarFormulario(event) {
    event.preventDefault();
    
    const divErros = document.getElementById("divErros");
    divErros.style.display = "none";
    divErros.innerHTML = "";
    
    const btnSalvar = document.getElementById("btnSalvar");
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = "Salvando...";
    }
    
    const numDocumento = document.getElementById("numDocumento").value;
    const campos = {};
    
    document.querySelectorAll(".mfdi-input").forEach(input => {
        const field = input.getAttribute("data-field");
        campos[field] = {
            label: input.getAttribute("data-label"),
            type: input.getAttribute("data-type"),
            required: input.getAttribute("data-required"),
            value: input.value
        };
    });
    
    const formData = new FormData();
    formData.append("acao", "md_mfdi_salvar");
    formData.append("numDocumento", numDocumento);
    
    for (const field in campos) {
        formData.append("campos[" + field + "][label]", campos[field].label);
        formData.append("campos[" + field + "][type]", campos[field].type);
        formData.append("campos[" + field + "][required]", campos[field].required);
        formData.append("campos[" + field + "][value]", campos[field].value);
    }
    
    fetch("controlador.php", {
        method: "POST",
        body: formData
    })
    .then(response => {
        return response.json().then(data => {
            return { status: response.status, body: data };
        });
    })
    .then(result => {
        if (result.status === 200 && result.body.sucesso) {
            window.location.href = urlRetorno;
        } else {
            if (btnSalvar) {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = "Salvar";
            }
            divErros.style.display = "block";
            // Exibe a lista de validações se for array, ou string de erro
            if (result.body.erro) {
                divErros.innerHTML = result.body.erro;
            } else {
                divErros.innerHTML = "Erro desconhecido ao salvar.";
            }
        }
    })
    .catch(error => {
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = "Salvar";
        }
        divErros.style.display = "block";
        divErros.innerHTML = "Erro de rede: " + error.message;
    });
}
</script>
';

$p->montarDocumento('Preencher Formulário Dinâmico', $strHtml, 'md_mfdi_formulario');
