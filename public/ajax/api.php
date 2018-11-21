<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$dados = filter_input(INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

$id = \Entity\Entity::add($entity, $dados);
$data['data'] = $id;
