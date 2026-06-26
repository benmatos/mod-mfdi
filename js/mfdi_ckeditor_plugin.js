(function(){
'use strict';

var CFG = {
    TIMEOUT: 30000, POLL: 500
};

var MFDI = {
    initPlugin: function(editor) {
        var attributes = [
            'data-sei-field',
            'data-sei-label',
            'data-sei-type',
            'data-sei-required',
            'data-sei-options'
        ];
        
        var commonElements = [
            'table', 'tableRow', 'tableCell', 
            'paragraph', 'heading1', 'heading2', 'heading3', 'heading4', 'heading5', 'heading6', 
            'listItem', 'span', 'div', 'imageBlock', 'imageInline',
            'htmlDiv', 'htmlSpan', 'htmlTable', 'htmlTr', 'htmlTd', 'htmlTh', 'htmlA', 'htmlP', 'htmlPre', 'htmlBlockquote'
        ];
        
        // Permite atributos globalmente via addAttributeCheck
        if (editor.model.schema.addAttributeCheck) {
            editor.model.schema.addAttributeCheck(function(context, attributeName) {
                if (attributes.indexOf(attributeName) !== -1) {
                    return true;
                }
            });
        }
        
        attributes.forEach(function(attr) {
            // Estende esquemas de texto e bloco abstratos
            if (editor.model.schema.isRegistered('$text')) {
                editor.model.schema.extend('$text', { allowAttributes: attr });
            }
            if (editor.model.schema.isRegistered('$block')) {
                editor.model.schema.extend('$block', { allowAttributes: attr });
            }
            
            // Registra conversores genéricos (para texto e fallback)
            editor.conversion.for('upcast').attributeToAttribute({
                view: attr,
                model: attr
            });
            editor.conversion.for('downcast').attributeToAttribute({
                model: attr,
                view: attr
            });
            
            // Registra regras e conversores específicos para elementos comuns
            commonElements.forEach(function(el) {
                if (editor.model.schema.isRegistered(el)) {
                    editor.model.schema.extend(el, { allowAttributes: attr });
                    
                    // Conversor específico de Upcast (HTML -> Model) para este elemento
                    editor.conversion.for('upcast').attributeToAttribute({
                        model: {
                            name: el,
                            key: attr
                        },
                        view: attr
                    });
                    
                    // Conversor específico de Downcast (Model -> HTML) para este elemento
                    editor.conversion.for('downcast').attributeToAttribute({
                        model: {
                            name: el,
                            key: attr
                        },
                        view: attr
                    });
                }
            });
        });
        
        console.log('[MFDI] Plugin de compatibilidade de atributos carregado no CKEditor 5.');
    }
};

// Auto-detecção e inicialização do plugin no CKEditor 5 do SEI
var t0 = Date.now(), pid = setInterval(function(){
    var ed = null;
    if (window.editor && window.editor.model) {
        ed = window.editor;
    }
    if (!ed) {
        var el = document.querySelector('.ck-editor__editable');
        if (el && el.ckeditorInstance) {
            ed = el.ckeditorInstance;
        }
    }
    if (ed) {
        clearInterval(pid);
        try {
            MFDI.initPlugin(ed);
        } catch(e) {
            console.error('[MFDI] Erro ao registrar plugin no CKEditor 5:', e);
        }
        return;
    }
    if (Date.now() - t0 > CFG.TIMEOUT) {
        clearInterval(pid);
        console.warn('[MFDI] Timeout: CKEditor 5 não detectado.');
    }
}, CFG.POLL);

})();
