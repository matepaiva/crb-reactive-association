<?php

namespace CrbReactiveAssociation;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field\Association_Field;
use Carbon_Fields\Field\Field;

class ReactiveContainer
{

  private $name;
  private $type;
  private $subtype;
  private $fields = [];
  private $crb_container;

  public function __construct($name, $type, $subtype)
  {
    $this->name = $name;
    $this->type = $type;
    $this->subtype = $subtype;
  }

  private static function make($type_arr)
  {
    ['type' => $type] = $type_arr;

    switch ($type) {
      case 'user':
        return new Self($type, $type, null);

      case 'comment':
        return new Self($type, $type, null);

      case 'post':
        $subtype = $type_arr['post_type'];
        return new Self("$type/$subtype", $type, $subtype);

      case 'term':
        $subtype = $type_arr['taxonomy'];
        return new Self("$type/$subtype", $type, $subtype);
    }
  }

  public static function getFromAssociationField(Association_Field $field)
  {
    $types = $field->get_types();

    return array_map(function ($type_arr) {
      return Self::make($type_arr);
    }, $types);
  }

  private function getCrbType()
  {
    switch ($this->type) {
      case 'term':
        return 'term_meta';
      case 'post':
        return 'post_meta';
      case 'comment':
        return 'comment_meta';
      case 'user':
        return 'user_meta';
    }
  }

  private function addWhere(Container $container)
  {
    if ($this->subtype) {
      $comparation_type = $this->type === 'post' ? 'post_type' : 'term_taxonomy';
      $container->where($comparation_type, '=', $this->subtype);
    }
  }

  private function generateCrbContainer()
  {
    $container = Container::make($this->getCrbType(), __('Associações', 'app'));

    $this->addWhere($container);

    return $container;
  }

  public function generate()
  {
    $crb_fields = array_map(
      function (ReactiveField $field) {
        return $field->getCrbField();
      },
      $this->getFields()
    );

    $this->crb_container = $this->generateCrbContainer();
    $this->crb_container->add_fields($crb_fields);

    add_filter('carbon_fields_should_delete_field_value_on_save', [$this, 'addSaveHook'], 1, 2);

    return $this->crb_container;
  }

  static private function determineTypeFromHttpRequest()
  {
    $post_type = get_post_type();
    if ($post_type) {
      return "post/$post_type";
    }

    $taxonomy = $_POST['taxonomy'] ?? false;
    if ($taxonomy) {
      return "term/$taxonomy";
    }

    if ($_POST['action'] === 'update' || $_POST['action'] === 'createuser') {
      return 'user';
    }


    return 'comment';
  }

  private function isActive()
  {
    $request_type = Self::determineTypeFromHttpRequest();
    $type = $this->subtype ? "$this->type/$this->subtype" : $this->type;
    return $type === $request_type;
  }

  public function addSaveHook(Bool $delete, Field $field)
  {
    $this->updateFieldValueIfNecessary($field);

    return $delete;
  }

  private function updateFieldValueIfNecessary(Field $field)
  {
    if (!$this->isActive()) return;

    $reactive_field = $this->getFieldByName($field->get_base_name());

    if (!$reactive_field) return;

    $reactive_field->setNewValue();
  }

  public function getName()
  {
    return $this->name;
  }

  public function addField(ReactiveField $field)
  {
    $this->fields[$field->getName()] = $field;
  }

  private function getFields()
  {
    return $this->fields;
  }

  private function getFieldByName(String $name)
  {
    return $this->fields[$name] ?? null;
  }
}
