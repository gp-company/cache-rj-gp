<?php
// Configurações de conexão ao banco de dados
$host = 'localhost';
$dbname = 'ezxy_5343543523324_mysitename';
$username = 'root';
$password = '';

try {
    // Conexão ao banco de dados usando PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta SQL usando a cláusula WITH para buscar os dados com as condições especificadas
    $sql = "
        WITH ranked_reviews AS (
            SELECT 
                escort_name, 
                job_price, 
                td_link, 
                td_head, 
                date_created,
                ROW_NUMBER() OVER (
                    PARTITION BY escort_name 
                    ORDER BY date_created DESC
                ) AS row_num
            FROM ore_gp_reviews
            WHERE job_minutes = 60 
              AND job_price > 0
              AND is_fake = 0 
              AND date_created > '2024-01-01 00:00:01'
        )
        SELECT 
            escort_name, 
            job_price, 
            td_link, 
            td_head, 
            date_created
        FROM ranked_reviews
        WHERE row_num = 1
        ORDER BY job_price ASC
        LIMIT 800
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Início do relatório em HTML
    $html = "<h2>Relatório de Acompanhantes - 60 Minutos</h2>";
    $html .= "<ul>";

    // Loop pelos resultados da consulta
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $html .= "<li>";
        $html .= "<strong>Nome da GP:</strong> " . htmlspecialchars($row['escort_name']) . "<br>";
        $html .= "<strong>1h:</strong> R$ " . number_format($row['job_price'], 2, ',', '.') . "<br>";
        $html .= "<strong>Link do último relato:</strong> <a href='" . htmlspecialchars($row['td_link']) . "'>" . htmlspecialchars($row['td_link']) . "</a><br>";
        $html .= "<strong>Título do Relato:</strong> " . htmlspecialchars($row['td_head']) . "<br>";
        $html .= "<strong>Data de Criação:</strong> " . htmlspecialchars($row['date_created']) . "<br>";
        $html .= "</li><br>";
    }

    $html .= "</ul>";

    // Exibição do relatório em HTML
    echo $html;

} catch (PDOException $e) {
    // Tratamento de erros de conexão
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
}
?>