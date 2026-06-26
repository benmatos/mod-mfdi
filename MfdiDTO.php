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
        $this->adicionarAtributo(InfraDTO::$PREFIXO_NUM, 'NumDocumento');
        $this->adicionarAtributo(InfraDTO::$PREFIXO_STR, 'StrConteudoHtml');
        $this->adicionarAtributo(InfraDTO::$PREFIXO_ARR, 'ArrCampos');
    }
}
