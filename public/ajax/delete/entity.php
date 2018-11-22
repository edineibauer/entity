<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$result = \Entity\Entity::delete($entity, $id);
$data['data']['data'] = $result;

$json = new \Entity\Json();
$data['data']['historic'] = $json->get("historic")[$entity];