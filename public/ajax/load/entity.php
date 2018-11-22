<?php
$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$time = filter_input(INPUT_POST, 'historic', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);

$setor = empty($_SESSION['userlogin']['setor']) ? 20 : $_SESSION['userlogin']['setor'];
$entidades = \Config\Config::getEntityNotAllow();
$read = new \ConnCrud\Read();

$json = new \Entity\Json();
$hist = $json->get("historic");
$data['data'] = [];

if(empty($entity)) {
    if($entitys = Entity\Entity::dicionario()) {
        foreach ($entitys as $entity => $metas) {
            if (\Entity\Entity::checkPermission($entity) && !empty($entidades[$setor]) && !in_array($entity, $entidades[$setor])) {

                //verifica se há alterações nessa entidade que não forão recebidas pelo app
                if(empty($hist[$entity]) || empty($time[$entity]) || $time[$entity] < $hist[$entity]){
                    $read->exeRead($entity);
                    $data['data']['data'][$entity] = $read->getResult();

                    //preenche caso não tenha nada de informação
                    if(empty($hist[$entity]))
                        $hist[$entity] = strtotime('now');

                    //atualiza variável local
                    $time[$entity] = $hist[$entity];
                }
            }
        }

        $data['data']['historic'] = $time;
        $json->save("historic", $hist);
    } else {
        $data['data'] = 0;
    }

} else {
    if (\Entity\Entity::checkPermission($entity) && !empty($entidades[$setor]) && !in_array($entity, $entidades[$setor])) {

        //verifica se há alterações nessa entidade que não forão recebidas pelo app
        if(empty($hist[$entity]) || empty($time[$entity]) || $time[$entity] < $hist[$entity]){
            $read->exeRead($entity);
            $data['data']['data'][$entity] = $read->getResult();

            //preenche caso não tenha nada de informação
            if(empty($hist[$entity])) {
                $hist[$entity] = strtotime('now');
                $json->save("historic", $hist);
            }

            //atualiza variável local
            $time[$entity] = $hist[$entity];
            $data['data']['historic'] = $time;
        }
    } else {
        $data['data'] = 0;
    }
}