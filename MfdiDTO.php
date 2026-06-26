<?php
/**
 * Módulo MFDI — DTO (Data Transfer Object)
 * MGI - Ministério da Gestão e da Inovação em Serviços Públicos
 */


class MfdiDTO extends InfraDTO {

    public function getStrNomeTabela() {
        return null;
    }

    public function montar() {
        $this->adicionarAtributo(InfraDTO::$PREFIXO_NUM, 'Documento');
        $this->adicionarAtributo(InfraDTO::$PREFIXO_STR, 'ConteudoHtml');
        $this->adicionarAtributo(InfraDTO::$PREFIXO_ARR, 'Campos');
    }
}
