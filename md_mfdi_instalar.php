<?php
/**
 * Módulo MFDI – Script de Instalação e Registro no SIP
 * Executar via CLI: php md_mfdi_instalar.php
 */
// 1. Carregar dependências antes de qualquer saída de texto
require_once dirname(__FILE__) . '/../../SEI.php';

$possiveisCaminhosSip = array(
    dirname(__FILE__) . '/../../../sip/web/Sip.php',
    dirname(__FILE__) . '/../../../../sip/web/Sip.php',
);
$sipPath = null;
foreach ($possiveisCaminhosSip as $path) {
    if (file_exists($path)) {
        $sipPath = $path;
        break;
    }
}

if ($sipPath === null) {
    echo "[ERRO] Não foi possível localizar sip/web/Sip.php.\n";
    exit(1);
}

require_once $sipPath;

// 2. Agora podemos emitir saídas
echo "=== Módulo MFDI – Instalação ===\n";

try {
    SessaoSEI::getInstance(false);
    
    $connSip = BancoSip::getInstance();
    $connSip->abrirConexao();
    $connSip->abrirTransacao();
    
    // Buscar id_sistema do SEI
    $rows = $connSip->consultarSql("SELECT id_sistema FROM sistema WHERE sigla = 'SEI'");
    if (empty($rows)) {
        throw new Exception("Sistema SEI não encontrado no SIP.");
    }
    $idSistemaSei = $rows[0]['id_sistema'];
    
    // Criar recurso md_mfdi_formulario se não existir
    $recursoNome = 'md_mfdi_formulario';
    $recursoDesc = 'Módulo MFDI - Preencher Formulário Dinâmico';
    $recursoCaminho = 'controlador.php?acao=md_mfdi_formulario';
    
    echo "Verificando recurso '$recursoNome'...\n";
    $rowsRecurso = $connSip->consultarSql("SELECT id_recurso FROM recurso WHERE id_sistema = ? AND nome = ?", array($idSistemaSei, $recursoNome));
    
    if (empty($rowsRecurso)) {
        // Obter próximo ID de forma compatível
        $rowsNext = $connSip->consultarSql("SELECT COALESCE(MAX(id_recurso), 0) + 1 AS next_id FROM recurso");
        $idRecurso = $rowsNext[0]['next_id'];
        
        $connSip->executarSql("INSERT INTO recurso (id_recurso, id_sistema, nome, descricao, caminho, sin_ativo) VALUES (?, ?, ?, ?, ?, 'S')",
            array($idRecurso, $idSistemaSei, $recursoNome, $recursoDesc, $recursoCaminho)
        );
        echo "   [CRIADO] id_recurso = $idRecurso\n";
    } else {
        $idRecurso = $rowsRecurso[0]['id_recurso'];
        echo "   [JÁ EXISTE] id_recurso = $idRecurso\n";
    }
    
    // Vincular ao perfil de Administrador se existir
    $rowsPerfil = $connSip->consultarSql("SELECT id_perfil FROM perfil WHERE id_sistema = ? AND nome = 'Administrador'", array($idSistemaSei));
    if (!empty($rowsPerfil)) {
        $idPerfil = $rowsPerfil[0]['id_perfil'];
        $rowsRel = $connSip->consultarSql("SELECT id_perfil FROM rel_perfil_recurso WHERE id_perfil = ? AND id_sistema = ? AND id_recurso = ?", array($idPerfil, $idSistemaSei, $idRecurso));
        if (empty($rowsRel)) {
            $connSip->executarSql("INSERT INTO rel_perfil_recurso (id_perfil, id_sistema, id_recurso) VALUES (?, ?, ?)", array($idPerfil, $idSistemaSei, $idRecurso));
            echo "   [VINCULADO] $recursoNome -> perfil Administrador\n";
        } else {
            echo "   [JÁ VINCULADO] $recursoNome -> perfil Administrador\n";
        }
    }
    
    $connSip->confirmarTransacao();
    echo "[OK] Instalação do módulo MFDI realizada com sucesso.\n";
    
} catch (Exception $e) {
    if (isset($connSip) && $connSip->isConexaoAberta()) {
        $connSip->cancelarTransacao();
    }
    echo "[ERRO] " . $e->getMessage() . "\n";
    exit(1);
}
