<?php

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$carneStore = []; // Armazena os carnês criados temporariamente
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

echo json_encode([
    'method' => $method,
    'uri' => $uri
]);

switch ($uri) {
    case '/api/carne':
        if ($method === 'POST') {
            createCarne();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Método não permitido']);
        }
        break;

    case '/api/recuperaParcela':
        if ($method === 'GET') {
            getParcelas();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Método não permitido']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Rota não encontrada']);
        break;
}

function createCarne() {
    global $carneStore;

    $data = json_decode(file_get_contents('php://input'), true);

    // Validação básica
    $requiredFields = ['valor_total', 'qtd_parcelas', 'data_primeiro_vencimento', 'periodicidade'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo obrigatório ausente: $field"]);
            return;
        }
    }

    $valor_total = (float)$data['valor_total'];
    $qtd_parcelas = (int)$data['qtd_parcelas'];
    $periodicidade = $data['periodicidade'];
    $valor_entrada = isset($data['valor_entrada']) ? (float)$data['valor_entrada'] : 0;

    // Validação de valores
    if ($valor_total <= 0 || $qtd_parcelas <= 0 || ($valor_entrada < 0 || $valor_entrada > $valor_total)) {
        http_response_code(400);
        echo json_encode(['error' => 'Valores inválidos: verifique o valor total, a quantidade de parcelas e o valor de entrada']);
        return;
    }

    if (!in_array($periodicidade, ['mensal', 'semanal'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Periodicidade inválida: deve ser "mensal" ou "semanal"']);
        return;
    }

    // Tratamento de data
    try {
        $data_primeiro_vencimento = new DateTime($data['data_primeiro_vencimento']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de data inválido para o campo data_primeiro_vencimento']);
        return;
    }

    // Geração das parcelas
    $parcelas = [];
    $total_a_pagar = $valor_total - $valor_entrada;
    $valor_parcela = $total_a_pagar / $qtd_parcelas;
    $somatoria_acumulada = 0;

    if ($valor_entrada > 0) {
        $somatoria_acumulada += $valor_entrada;
        $parcelas[] = [
            'data_vencimento' => $data_primeiro_vencimento->format('Y-m-d'),
            'valor' => $valor_entrada,
            'numero' => 1,
            'entrada' => true,
            'somatoria' => round($somatoria_acumulada, 2)
        ];
        $data_primeiro_vencimento->modify("+1 $periodicidade");
    }

    for ($i = 0; $i < $qtd_parcelas; $i++) {
        $somatoria_acumulada += $valor_parcela;
        $parcelas[] = [
            'data_vencimento' => $data_primeiro_vencimento->format('Y-m-d'),
            'valor' => round($valor_parcela, 2),
            'numero' => $i + 1 + ($valor_entrada > 0 ? 1 : 0),
            'entrada' => false,
            'somatoria' => round($somatoria_acumulada, 2)
        ];
        $data_primeiro_vencimento->modify("+1 $periodicidade");
    }

    // Armazenamento do carnê com um ID gerado
    $carne_id = uniqid();
    $carneStore[$carne_id] = [
        'total' => $valor_total,
        'valor_entrada' => $valor_entrada,
        'parcelas' => $parcelas
    ];

    // Resposta com o ID do carnê
    $response = [
        'id' => $carne_id,
        'total' => $valor_total,
        'valor_entrada' => $valor_entrada,
        'parcelas' => $parcelas
    ];

    echo json_encode($response);
}

function getParcelas() {
    global $carneStore;

    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetro id é obrigatório']);
        return;
    }

    $id = $_GET['id'];

    if (!isset($carneStore[$id])) {
        http_response_code(404);
        echo json_encode(['error' => 'Carnê não encontrado']);
        return;
    }

    // Resposta com as parcelas do carnê solicitado
    echo json_encode(['parcelas' => $carneStore[$id]['parcelas']]);
}
