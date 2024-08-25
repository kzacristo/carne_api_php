<?php 

namespace CarneApi;

class Periodicidade {

    public function getPeriodicidade($periodicidade, $data_primeiro_vencimento){

        switch ($periodicidade){
            case "semanal":
                return $data_primeiro_vencimento->modify("+1 week");
                break;
            default:
                return $data_primeiro_vencimento->modify("+1 month");
                break;
        }

    }
}