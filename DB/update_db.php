<?php
require_once(__DIR__ . '/../BACKEND/CONNECTION/config.php');

try {
    $pdo = Database::getConexao();
    
    // Check if columns exist before adding them
    $sql = "ALTER TABLE ALUNOS ADD ALUSTATUS VARCHAR(20) DEFAULT 'Pendente'";
    $pdo->exec($sql);
    echo "Column ALUSTATUS added successfully.\n";
} catch (PDOException $e) {
    echo "Error adding ALUSTATUS: " . $e->getMessage() . "\n";
}

try {
    $sql = "ALTER TABLE ALUNOS ADD ALUDATAVENCIMENTO DATE DEFAULT GETDATE()";
    $pdo->exec($sql);
    echo "Column ALUDATAVENCIMENTO added successfully.\n";
} catch (PDOException $e) {
    echo "Error adding ALUDATAVENCIMENTO: " . $e->getMessage() . "\n";
}

// Update existing records
try {
    $sql = "UPDATE ALUNOS SET ALUSTATUS = 'Pendente', ALUDATAVENCIMENTO = GETDATE() WHERE ALUSTATUS IS NULL";
    $pdo->exec($sql);
    echo "Existing records updated.\n";
} catch (PDOException $e) {
    echo "Error updating existing records: " . $e->getMessage() . "\n";
}
?>
