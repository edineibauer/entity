<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$dados = filter_input(INPUT_POST, 'dados', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {
    $id = \Entity\Entity::add($entity, $dados);
    $data['data']['data'] = $id;

    $json = new \Entity\Json();
    $data['data']['historic'] = $json->get("historic")[$entity];
}