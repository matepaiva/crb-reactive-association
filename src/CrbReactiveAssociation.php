<?php

namespace CrbReactiveAssociation;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Association_Field;
use Carbon_Fields\Field\Field;

class CrbReactiveAssociation
{
  private $containers = [];
  public function __construct()
  {
  }

  static function init()
  {
    return new Self;
  }

  public static function mapToIntegerId($item)
  {
    return (int) $item['id'];
  }

  private function getRegisteredContainerByName(String $name)
  {
    return $this->containers[$name] ?? false;
  }

  private function registerContainer(ReactiveContainer $container)
  {
    $name = $container->getName();

    $this->containers[$name] = $container;
  }

  private function getOrCreateReactiveContainersByAssociationField(Association_Field $field)
  {
    $containers = ReactiveContainer::getFromAssociationField($field);

    return array_map(function (ReactiveContainer $container) {
      $name = $container->getName();

      if (!$this->getRegisteredContainerByName($name)) {
        $this->registerContainer($container);
      }

      return $this->getRegisteredContainerByName($name);
    }, $containers);
  }

  public function associate(Association_Field $associationField1, Association_Field $associationField2)
  {
    foreach ([$associationField1, $associationField2] as $associationField) {
      $field = $associationField === $associationField1 ? $associationField2 : $associationField1;
      $containers = $this->getOrCreateReactiveContainersByAssociationField($associationField);

      foreach ($containers as $container) {
        $reactive_field = ReactiveField::make($field, $associationField->get_base_name(), $container->getType(), $container->getSubtype());
        $container->addField($reactive_field);
      }
    }

    return $this;
  }

  public function getContainers()
  {
    return $this->containers;
  }

  public function generate()
  {
    $crb_containers = [];
    foreach ($this->containers as $container) {
      $crb_containers[] = $container->generate();
    }
    return $crb_containers;
  }
}
