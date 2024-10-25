<?php
// Configurações de conexão ao banco de dados
$host = 'localhost';
$dbname = 'ezxy_5343543523324_mysitename';
$username = 'root';
$password = ''; // Sem senha

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
              AND evaluation = 'POSITIVO'  -- Novo filtro para avaliação positiva
              AND td_head LIKE '%social-feed-user%'  -- Filtro para td_head
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

    $lastPrice = null; // Variável para armazenar o último preço

    // Array para armazenar valores de BBCode
    $bbcodeUnder200 = "[size=150]Valores até R$ 200:[/size]\n";
    $bbcodeOver200 = "[size=150]Valores de R$ 200 ou mais:[/size]\n";

    // Loop pelos resultados da consulta
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Verifica se td_head contém "social-feed-user"
        $highlightClass = strpos($row['td_head'], 'social-feed-user') !== false ? 'highlight' : '';

        // Texto adicional para acompanhantes que anunciam no GPARENA
        $additionalText = $highlightClass ? " (Anuncia no GPARENA)" : "";

        // Verifica se há mudança de preço
        if ($lastPrice !== null && $row['job_price'] != $lastPrice) {
            $html .= "<li style='font-weight: bold; color: #ff6600; font-size: 1.2em;'><strong>Mudança de Preço:</strong> Região dos R$ " . number_format($row['job_price'], 2, ',', '.') . "</li>"; // Divisória com título
        }

        // Atualiza o último preço
        $lastPrice = $row['job_price'];

        // Converte a data para o padrão brasileiro
        $formattedDate = date("d/m/Y H:i:s", strtotime($row['date_created']));

        // Adiciona o item ao relatório
        $html .= "<li class='$highlightClass'>";
        $html .= "<strong>Nome da GP:</strong> " . htmlspecialchars($row['escort_name']) . $additionalText . "<br>";
        $html .= "<strong>1h:</strong> R$ " . number_format($row['job_price'], 2, ',', '.') . "<br>";
        $html .= "<strong>Link do último relato:</strong> <a href='" . htmlspecialchars($row['td_link']) . "'>" . htmlspecialchars($row['td_link']) . "</a><br>";
        $html .= "<strong>Título do Relato:</strong> " . htmlspecialchars($row['td_head']) . "<br>";
        $html .= "<strong>Data de Criação:</strong> " . htmlspecialchars($formattedDate) . "<br>";
        $html .= "</li><br>";

        // Adiciona ao BBCode apropriado
        if ($row['job_price'] <= 200) {
            $bbcodeUnder200 .= "[b]Nome da GP:[/b] " . htmlspecialchars($row['escort_name']) . $additionalText . "\n";
            $bbcodeUnder200 .= "[b]1h:[/b] R$ " . number_format($row['job_price'], 2, ',', '.') . "\n";
            $bbcodeUnder200 .= "[b]Link do último relato:[/b] [url=" . htmlspecialchars($row['td_link']) . "]" . htmlspecialchars($row['td_link']) . "[/url]\n";
            $bbcodeUnder200 .= "[b]Título do Relato:[/b] " . htmlspecialchars($row['td_head']) . "";
            $bbcodeUnder200 .= "[b]Data de Criação:[/b] " . htmlspecialchars($formattedDate) . "\n\n";
        } else {
            $bbcodeOver200 .= "[b]Nome da GP:[/b] " . htmlspecialchars($row['escort_name']) . $additionalText . "\n";
            $bbcodeOver200 .= "[b]1h:[/b] R$ " . number_format($row['job_price'], 2, ',', '.') . "\n";
            $bbcodeOver200 .= "[b]Link do último relato:[/b] [url=" . htmlspecialchars($row['td_link']) . "]" . htmlspecialchars($row['td_link']) . "[/url]\n";
            $bbcodeOver200 .= "[b]Título do Relato:[/b] " . htmlspecialchars($row['td_head']) . " ";
            $bbcodeOver200 .= "[b]Data de Criação:[/b] " . htmlspecialchars($formattedDate) . "\n\n";
        }
    }

    $html .= "</ul>";

    // Estilo para destacar resultados
    $html .= "
    <style>
        .highlight {
            background-color: #ffffcc; /* Cor de fundo amarelo claro */
            border: 1px solid #ffcc00; /* Borda amarela */
            padding: 10px;
            margin-bottom: 5px;
        }
    </style>
    ";

    // Adiciona a fonte ao final dos campos BBCode
    $bbcodeUnder200 .= "Fonte: https://github.com/gp-company/cache-rj-gp\n";
    $bbcodeOver200 .= "Fonte: https://github.com/gp-company/cache-rj-gp\n";

    // Exibição do relatório em HTML
    echo $html;

    // Exibição da caixa de texto com o BBCode
    echo "<h3>BBCode Gerado - Valores até R$ 200:</h3>";
    echo "<textarea rows='10' cols='80' readonly>" . htmlspecialchars($bbcodeUnder200) . "</textarea>";

    echo "<h3>BBCode Gerado - Valores de R$ 200 ou mais:</h3>";
    echo "<textarea rows='10' cols='80' readonly>" . htmlspecialchars($bbcodeOver200) . "</textarea>";

} catch (PDOException $e) {
    // Tratamento de erros de conexão
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
}
?>
