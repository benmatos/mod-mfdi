<?php
/**
 * Módulo MFDI — Template do Formulário Dinâmico
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */
$p = PaginaSEI::getInstance();
$p->setTipoPagina(InfraPagina::$TIPO_PAGINA_SIMPLES);

$p->montarDocType();
$p->abrirHtml();
$p->abrirHead();
$p->montarMeta();
$p->montarTitle($p->getStrNomeSistema() . ' - Preencher Formulário Dinâmico');
$p->montarStyle();

$p->abrirStyle();
?>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    background-color: #f8fafc !important;
    color: #1e293b !important;
    margin: 0;
    padding: 20px;
}

.mfdi-container {
    max-width: 750px;
    margin: 20px auto;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    border: 1px solid #e2e8f0;
    padding: 30px;
}

.mfdi-header {
    margin-bottom: 25px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 15px;
}

.mfdi-title {
    font-size: 20px;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 8px 0;
}

.mfdi-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

.mfdi-form-group {
    margin-bottom: 20px;
}

.mfdi-label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #334155;
    margin-bottom: 6px;
}

.mfdi-label-required {
    color: #ef4444;
    margin-left: 2px;
}

.mfdi-input-text, .mfdi-textarea, .mfdi-select {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background-color: #ffffff;
    color: #0f172a;
    font-family: inherit;
    font-size: 14px;
    transition: all 0.2s ease-in-out;
    box-sizing: border-box;
}

.mfdi-input-text:focus, .mfdi-textarea:focus, .mfdi-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    outline: none;
}

.mfdi-textarea {
    resize: vertical;
    min-height: 100px;
}

.mfdi-btn-container {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
}

.mfdi-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    border: none;
    font-family: inherit;
}

.mfdi-btn-primary {
    background-color: #4f46e5;
    color: #ffffff;
}

.mfdi-btn-primary:hover {
    background-color: #4338ca;
}

.mfdi-btn-secondary {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.mfdi-btn-secondary:hover {
    background-color: #e2e8f0;
}

.mfdi-alert-error {
    background-color: #fef2f2;
    border: 1px solid #fca5a5;
    color: #991b1b;
    border-radius: 8px;
    padding: 14px;
    font-size: 14px;
    margin-bottom: 25px;
    font-weight: 500;
    display: none;
}
<?php
$p->fecharStyle();

$p->montarJavaScript();
$p->abrirJavaScript();
?>
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
<?php
$p->fecharJavaScript();
$p->fecharHead();
$p->abrirBody('Preencher Formulário Dinâmico', '');

$strLinkRetorno = SessaoSEI::getInstance()->assinarLink(
    'controlador.php?acao=documento_visualizar&id_procedimento=' . $idProcedimento . '&id_documento=' . $objDTO->getNumDocumento()
);
?>

<div class="mfdi-container">
    <div class="mfdi-header">
        <h1 class="mfdi-title">Formulário de Preenchimento</h1>
        <p class="mfdi-subtitle">Insira as informações nos campos dinâmicos mapeados no documento.</p>
    </div>

    <div id="divErros" class="mfdi-alert-error"></div>

    <form id="frmMfdi" method="POST" onsubmit="salvarFormulario(event);">
        <input type="hidden" id="numDocumento" value="<?php echo htmlspecialchars($objDTO->getNumDocumento()); ?>" />

        <?php
        $arrCampos = $objDTO->getArrCampos();
        if (empty($arrCampos)) {
            echo '<div style="padding: 30px; text-align: center; font-style: italic; color: #64748b; background-color: #f8fafc; border-radius: 8px;">Este documento não possui campos anotados para o formulário dinâmico (com a classe class="sei-field--...").</div>';
        } else {
            foreach ($arrCampos as $campo) {
                $field = htmlspecialchars($campo['field']);
                $label = htmlspecialchars($campo['label']);
                $type = htmlspecialchars($campo['type']);
                $required = $campo['required'];
                $value = htmlspecialchars($campo['value']);
                
                $strRequiredLabel = $required ? '<span class="mfdi-label-required">*</span>' : '';
                $strRequiredAttr = $required ? 'required="required"' : '';
                
                echo '<div class="mfdi-form-group">';
                echo '<label for="field_' . $field . '" class="mfdi-label">' . $label . $strRequiredLabel . '</label>';
                
                switch ($type) {
                    case 'textarea':
                        echo '<textarea id="field_' . $field . '" class="mfdi-textarea mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" ' . $strRequiredAttr . '>' . $value . '</textarea>';
                        break;
                        
                    case 'boolean':
                        echo '<select id="field_' . $field . '" class="mfdi-select mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" ' . $strRequiredAttr . '>';
                        echo '<option value=""' . ($value === '' ? ' selected' : '') . '>Selecione...</option>';
                        echo '<option value="Sim"' . (strtolower($value) === 'sim' || strtolower($value) === 's' ? ' selected' : '') . '>Sim</option>';
                        echo '<option value="Não"' . (strtolower($value) === 'não' || strtolower($value) === 'nao' || strtolower($value) === 'n' ? ' selected' : '') . '>Não</option>';
                        echo '</select>';
                        break;
                        
                    case 'lista':
                        $arrOptions = isset($campo['options_array']) ? $campo['options_array'] : array();
                        if (!empty($arrOptions)) {
                            echo '<select id="field_' . $field . '" class="mfdi-select mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" ' . $strRequiredAttr . '>';
                            echo '<option value=""' . ($value === '' ? ' selected' : '') . '>Selecione...</option>';
                            foreach ($arrOptions as $opt) {
                                $optValue = htmlspecialchars($opt['value']);
                                $optLabel = htmlspecialchars($opt['label']);
                                echo '<option value="' . $optValue . '"' . ($value === $opt['value'] || $value === $opt['label'] ? ' selected' : '') . '>' . $optLabel . '</option>';
                            }
                            echo '</select>';
                        } else {
                            echo '<input type="text" id="field_' . $field . '" class="mfdi-input-text mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" ' . $strRequiredAttr . ' />';
                        }
                        break;
                        
                    case 'numero':
                        echo '<input type="number" id="field_' . $field . '" class="mfdi-input-text mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" ' . $strRequiredAttr . ' />';
                        break;
                        
                    case 'moeda':
                        echo '<input type="text" id="field_' . $field . '" class="mfdi-input-text mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" placeholder="0,00" ' . $strRequiredAttr . ' oninput="mascaraMoeda(this);" />';
                        break;
                        
                    case 'data':
                        echo '<input type="date" id="field_' . $field . '" class="mfdi-input-text mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" style="width: 200px;" ' . $strRequiredAttr . ' />';
                        break;
                        
                    case 'texto':
                    default:
                        echo '<input type="text" id="field_' . $field . '" class="mfdi-input-text mfdi-input" data-field="' . $field . '" data-label="' . $label . '" data-type="' . $type . '" data-required="' . ($required ? 'true' : 'false') . '" value="' . $value . '" ' . $strRequiredAttr . ' />';
                        break;
                }
                
                echo '</div>';
            }
        }
        ?>

        <div class="mfdi-btn-container">
            <?php if (!empty($arrCampos)) { ?>
                <button type="submit" id="btnSalvar" class="mfdi-btn mfdi-btn-primary">Salvar</button>
            <?php } ?>
            <button type="button" class="mfdi-btn mfdi-btn-secondary" onclick="window.location.href='<?php echo $strLinkRetorno; ?>';">Cancelar</button>
        </div>
    </form>
</div>

<script type="text/javascript">
const urlRetorno = "<?php echo $strLinkRetorno; ?>";

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

<?php
$p->fecharBody();
$p->fecharHtml();
?>
