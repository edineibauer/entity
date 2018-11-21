<?php
$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);

$data['data'] = 0;
$setor = empty($_SESSION['userlogin']['setor']) ? 20 : $_SESSION['userlogin']['setor'];
$entidades = \Config\Config::getEntityNotAllow();

if (\Entity\Entity::checkPermission($entity) && !empty($entidades[$setor]) && !in_array($entity, $entidades[$setor])) {
    $read = new \ConnCrud\Read();
    $read->exeRead($entity);
    $data['data'] = $read->getResult();
}

