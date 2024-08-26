<?php

namespace CarneApi;

use DateTime;
use Exception;

class Application
{
  private $storagePath = __DIR__ . '/carne_storage/'; // Pasta onde os carnês serão salvos

  public function __construct()
  {
    // Cria a pasta de armazenamento se não existir
    if (!is_dir($this->storagePath)) {
      mkdir($this->storagePath, 0777, true);
    }
  }

  public function start()
  {
    $router = new Router();

    // Criação do carnê
    $router->create("POST", "/carne", function () {
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
      $getPeriodicidade = new Periodicidade();

      if ($valor_entrada > 0) {
        $somatoria_acumulada += $valor_entrada;
        $entrada[] = [
          'data_vencimento' => $data_primeiro_vencimento->format('Y-m-d'),
          'valor' => $valor_entrada,
          'numero' => 1,
          'entrada' => true,
          'somatoria' => round($somatoria_acumulada, 2)
        ];
        $data_primeiro_vencimento = $getPeriodicidade->getPeriodicidade($periodicidade, $data_primeiro_vencimento);
        $response['entrada'] = $entrada;
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
        $data_primeiro_vencimento = $getPeriodicidade->getPeriodicidade($periodicidade, $data_primeiro_vencimento);
      }

      // Armazenamento do carnê em arquivo
      $carne_id = uniqid();
      $carneData = [
        'total' => $valor_total,
        'valor_entrada' => $valor_entrada,
        'parcelas' => $parcelas
      ];
      file_put_contents($this->storagePath . $carne_id . '.json', json_encode($carneData));

      // Resposta com o ID do carnê
      $response["parcelas"] = [
        'idCarne' => $carne_id,
        'total' => $valor_total,
        'valor_entrada' => $valor_entrada,
        'parcelas' => $parcelas
      ];

      echo json_encode($response);
      return;
    });

    // Recuperação das parcelas
    $router->create("GET", "/recuperarParcela", function () {
      $data = json_decode(file_get_contents('php://input'), true);

      if (!$data['id']) {
        http_response_code(404);
        echo json_encode(['error' => 'Parâmetro id é obrigatório']);
        return;
      }

      $filePath = $this->storagePath . $data['id'] . '.json';

      if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Carnê não encontrado']);
        return;
      }

      $carneData = json_decode(file_get_contents($filePath), true);

      http_response_code(200);
      echo json_encode(['parcelas' => $carneData['parcelas']]);
      return;
    });

    $router->init();
  }
}
