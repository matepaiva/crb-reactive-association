<?php

namespace CrbReactiveAssociation;

use Carbon_Fields\Field\Association_Field;

class ReactiveField
{
  private $react_to_name = '';
  private $field;

  public function __construct(Association_Field $field, String $react_to_name, String $type, ?String $subtype)
  {
    $this->field = clone $field;
    $this->react_to_name = $react_to_name;
    $this->type = $type;
    $this->subtype = $subtype ?? $type;
  }

  public static function make(Association_Field $field, String $react_to_name, String $type, ?String $subtype)
  {
    return new Self($field, $react_to_name, $type, $subtype);
  }

  public function getCrbField()
  {
    return $this->field;
  }

  public function getReactiveName()
  {
    $this->react_to_name;
  }

  public function getName()
  {
    return $this->field->get_base_name();
  }

  private function getContextByType($type)
  {
    switch ($type) {
      case 'user':
        return 'user_meta';

      case 'post':
        return 'post_meta';

      case 'term':
        return 'term_meta';
    }
  }

  private function getOldValueByContext()
  {
    $context = $this->field->get_context();

    if ($context === 'user_meta') {
      $id = $_POST['user_id'] ?? null;
      return $id ? carbon_get_user_meta($id, $this->getName()) : [];
    }

    if ($context === 'post_meta') {
      return carbon_get_post_meta(get_the_ID(), $this->getName());
    }

    if ($context === 'term_meta') {
      $id = $_POST['tag_ID'] ?? null;
      return carbon_get_term_meta($id, $this->getName());
    }

    return [];
  }

  private function getCurrentId()
  {
    $context = $this->field->get_context();

    switch ($context) {
      case 'post_meta':
        return get_the_ID();

      case 'user_meta':
        return $_POST['user_id'] ?? null;

      case 'term_meta':
        return $_POST['tag_ID'] ?? null;
    }
  }

  static function compareValueIds($a, $b)
  {
    return $a['id'] - $b['id'];
  }

  private static function group_by($array, $key)
  {
    $groups = [];
    foreach ($array as $val) {
      if (!isset($groups[$val[$key]])) {
        $groups[$val[$key]] = [
          'type' => $val['type'],
          'subtype' => $val['subtype'],
          'ids' => [],
        ];
      }

      $groups[$val[$key]]['ids'][] = [
        'value' => (int) $val['id'],
        'action' => $val['action'],
      ];
    }

    return $groups;
  }

  public function setNewValue()
  {
    $old_value = $this->getOldValueByContext();
    $new_value = $this->field->get_value();
    $to_remove = array_map(function ($item) {
      return array_merge($item, ['action' => 'remove']);
    }, array_udiff($old_value, $new_value, [Self::class, 'compareValueIds']));
    $to_add = array_map(function ($item) {
      return array_merge($item, ['action' => 'add']);
    }, array_udiff($new_value, $old_value, [Self::class, 'compareValueIds']));

    $groups = Self::group_by(array_merge($to_add, $to_remove), 'subtype');
    foreach ($groups as $group) {
      ['type' => $type, 'ids' => $ids] = $group;
      $this->updateReactiveFields($type, $ids);
    }
  }

  private function getValueGetterNameByContext($context)
  {
    switch ($context) {
      case 'post_meta':
        return 'carbon_get_post_meta';

      case 'term_meta':
        return 'carbon_get_term_meta';

      case 'user_meta':
        return 'carbon_get_user_meta';
    }
  }

  private function getValueSetterNameByContext($context)
  {
    switch ($context) {
      case 'post_meta':
        return 'carbon_set_post_meta';

      case 'term_meta':
        return 'carbon_set_term_meta';

      case 'user_meta':
        return 'carbon_set_user_meta';
    }
  }

  private function getType()
  {
    $context = $this->field->get_context();

    switch ($context) {
      case 'post_meta':
        return 'post';

      case 'term_meta':
        return 'carbon_set_term_meta';

      case 'user_meta':
        return 'carbon_set_user_meta';
    }
  }

  public function updateReactiveFields(String $type, array $ids)
  {
    $name = $this->react_to_name;
    $context = $this->getContextByType($type);
    $getter = $this->getValueGetterNameByContext($context);
    $setter = $this->getValueSetterNameByContext($context);
    $current_id = $this->getCurrentId();

    foreach ($ids as $id) {
      $old_values = call_user_func_array($getter, [$id['value'], $name]);

      $new_values = array_filter($old_values ?? [], function ($item) use ($current_id) {
        return (int) $item['id'] !== (int) $current_id;
      });

      if ($id['action'] === 'add') {
        $new_values = array_merge([['id' => $current_id, 'type' => $this->type, 'subtype' => $this->subtype]], $new_values);
      }

      call_user_func_array($setter, [$id['value'], $name, $new_values]);
    }
  }
}
