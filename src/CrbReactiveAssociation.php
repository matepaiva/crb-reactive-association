<?php

namespace CrbReactiveAssociation;

use Carbon_Fields\Container\Container;

class CrbReactiveAssociation
{
  private $containers = [];
  public function __construct()
  { }

  static function init()
  {
    return new Self;
  }

  public static function mapToIntegerId ($item) {
    return (int) $item['id'];
  }

  public function associate($associationField1, $associationField2)
  {
    foreach ([$associationField1, $associationField2] as $associationField) {
      $reactiveField = $associationField === $associationField1 ? $associationField2 : $associationField1;
      $associationField->reactive_base_name = $reactiveField->get_base_name();

      $this->containers[$associationField->containerType] = $this->containers[$associationField->containerType] ?? [];
      $this->containers[$associationField->containerType][] = $associationField;
    }

    return $this;
  }

  public function getContainers() {
    return $this->containers;
  }

  public function generate()
  {
    foreach ($this->containers as $containerType => $associationFields) {
      Container::make('post_meta', 'Associações')
        ->set_context('advanced')
        ->where('post_type', '=', $containerType)
        ->add_fields($associationFields);

      $field_names = array_map(function ($associationField) {
        return $associationField->get_base_name();
      }, $associationFields);

      add_filter(
        'carbon_fields_should_delete_field_value_on_save',
        function ($delete, $field) use ($containerType, $associationFields, $field_names) {
          if (get_post_type() !== $containerType) {
            return $delete;
          }

          $association_field_idx = array_search($field->get_base_name(), $field_names);

          if ($association_field_idx === false) {
            return $delete;
          }

          $associationField = $associationFields[$association_field_idx];

          $old_value = array_map([Self::class, 'mapToIntegerId'], carbon_get_post_meta(get_the_ID(), $field->get_base_name()));
          $new_value = array_map([Self::class, 'mapToIntegerId'], $field->get_value());

          $to_remove_ids = array_diff($old_value, $new_value);
          $to_add_ids = array_diff($new_value, $old_value);

          foreach ($to_remove_ids as $id) {
            $product_collections = carbon_get_post_meta($id, $associationField->reactive_base_name);
            $product_collection_index = array_search(get_the_ID(), array_map([Self::class, 'mapToIntegerId'], $product_collections));

            if ($product_collection_index !== false) {
              unset($product_collections[$product_collection_index]);
              carbon_set_post_meta($id, $associationField->reactive_base_name, $product_collections);
            }
          }

          foreach ($to_add_ids as $id) {
            $product_collections = carbon_get_post_meta($id, $associationField->reactive_base_name);
            $product_collection_index = array_search(get_the_ID(), array_map([Self::class, 'mapToIntegerId'], $product_collections));

            if ($product_collection_index === false) {
              carbon_set_post_meta($id, $associationField->reactive_base_name, array_merge($product_collections, ['id' => get_the_ID()]));
            }
          }

          return $delete;
        },
        1,
        2
      );
    }
  }
}
