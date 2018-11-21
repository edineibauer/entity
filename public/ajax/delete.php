<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$result = \Entity\Entity::delete($entity, $id);
$data['data'] = $result;
