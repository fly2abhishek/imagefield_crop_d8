<?php

/**
 * @file
 * Contains \Drupal\imagefield_crop\Plugin\Field\FieldWidget\ImageCropWidget.
 */
//namespace Drupal\image\Plugin\Field\FieldWidget;
namespace Drupal\imagefield_crop\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Plugin\views\field\Field;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\field\Entity\FieldConfig;

/**
 * Plugin implementation of the 'image_image_crop' widget.
 *
 * @FieldWidget(
 *   id = "image_image_crop",
 *   label = @Translation("Image Crop"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageCropWidget extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'progress_indicator' => 'throbber',
      'preview_image_style' => 'thumbnail',
      'collapsible' => 2,
      'resolution' => '200x150',
      'enforce_ratio' => TRUE,
      'enforce_minimum' => TRUE,
      'croparea' => '500x500',
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['collapsible'] = array(
      '#type' => 'radios',
      '#title' => t('Collapsible behavior'),
      '#options' => array(
        1 => t('None.'),
        2 => t('Collapsible, expanded by default.'),
        3 => t('Collapsible, collapsed by default.'),
      ),
      '#default_value' => $this->getSetting('collapsible'),
    );
    // Resolution settings.
    $resolution = $this->getSetting('resolution')  ;

    $element['resolution'] = array(
      '#title' => t('The resolution to crop the image onto'),
      '#element_validate' => array(
        '_image_field_resolution_validate',
        '_imagefield_crop_widget_resolution_validate'
      ),
      '#theme_wrappers' => array('form_element'),
      '#description' => t('The output resolution of the cropped image, expressed as WIDTHxHEIGHT (e.g. 640x480). Set to 0 not to rescale after cropping. Note: output resolution must be defined in order to present a dynamic preview.'),
    );
    $element['resolution']['x'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($resolution['x']) ? $resolution['x'] : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' x ',
      '#theme_wrappers' => array(),
    );
    $element['resolution']['y'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($resolution['y']) ? $resolution['y'] : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' ' . t('pixels'),
      '#theme_wrappers' => array(),
    );
    $element['enforce_ratio'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enforce crop box ratio'),
      '#default_value' => $this->getSetting('enforce_ratio'),
      '#description' => t('Check this to force the ratio of the output on the crop box. NOTE: If you leave this unchecked but enforce an output resolution, the final image might be distorted'),
      '#element_validate' => array('_imagefield_crop_widget_enforce_ratio_validate'),
    );
    $element['enforce_minimum'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enforce minimum crop size based on the output size'),
      '#default_value' => $this->getSetting('enforce_minimum'),
      '#description' => t('Check this to force a minimum cropping selection equal to the output size. NOTE: If you leave this unchecked you might get zoomed pixels if the cropping area is smaller than the output resolution.'),
      '#element_validate' => array('_imagefield_crop_widget_enforce_minimum_validate'),
    );
    // Crop area settings
    $croparea = $this->getSetting('croparea') ;
    $element['croparea'] = array(
      '#title' => t('The resolution of the cropping area'),
      '#element_validate' => array('_imagefield_crop_widget_croparea_validate'),
      '#theme_wrappers' => array('form_element'),
      '#description' => t('The resolution of the area used for the cropping of the image. Image will displayed at this resolution for cropping. Use WIDTHxHEIGHT format, empty or zero values are permitted, e.g. 500x will limit crop box to 500 pixels width.'),
    );
    $element['croparea']['x'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($croparea['x']) ? $croparea['x'] : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' x ',
      '#theme_wrappers' => array(),
    );
    $element['croparea']['y'] = array(
      '#type' => 'textfield',
      '#default_value' => isset($croparea['y']) ? $croparea['y'] : '',
      '#size' => 5,
      '#maxlength' => 5,
      '#field_suffix' => ' ' . t('pixels'),
      '#theme_wrappers' => array(),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    return $element;
  }

  /**
   * Form API callback: Processes a image_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {

    print_r(FieldConfig::loadByName($element['#entity_type'], $element['#field_name'],  $element['#bundle'])); exit;
 //   print_r( Field::fieldInfo()->getBundleInstance($element['#entity_type'], $element['#field_name'],  $element['#bundle'])); exit;
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $element['#theme'] = 'imagefield_crop_widget';

    $element['#description'] = t('Click on the image and drag to mark how the image will be cropped');

    $path = drupal_get_path('module', 'imagefield_crop');
    $element['#attached']['js'][] = "$path/Jcrop/js/jquery.Jcrop.js";
    // We must define Drupal.behaviors for ahah to work, even if there is no file
    $element['#attached']['js'][] = "$path/imagefield_crop.js";
    $element['#attached']['css'][] = "$path/Jcrop/css/jquery.Jcrop.css";

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);
      $variables = array(
        'style_name' => $element['#preview_image_style'],
        'uri' => $file->getFileUri(),
      );

      // Determine image dimensions.
      if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
        $variables['width'] = $element['#value']['width'];
        $variables['height'] = $element['#value']['height'];
      }
      else {
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $variables['width'] = $image->getWidth();
          $variables['height'] = $image->getHeight();
        }
        else {
          $variables['width'] = $variables['height'] = NULL;
        }
      }

      $element['preview'] = array(
        '#weight' => -10,
        '#theme' => 'imagefield_crop_preview',
        '#width' => $variables['width'],
        '#height' => $variables['height'],
        '#style_name' => $variables['style_name'],
        '#uri' => $variables['uri'],
      );

      $element['cropbox'] = array(
        '#markup' => theme('image', array(
          'path' => $variables->uri,
          'attributes' => array(
            'class' => 'cropbox',
            'id' => $element['#id'] . '-cropbox'))),
      );
      $element['cropinfo'] = self::imagefield_add_cropinfo_fields($element['#file']->fid);
      list($res_w, $res_h) = explode('x', $element['resolution']);
      list($crop_w, $crop_h) = explode('x', $widget_settings['croparea']);
      $settings = array(
        $element['#id'] => array(
          'box' => array(
            'ratio' => $res_h ? $widget_settings['enforce_ratio'] * $res_w/$res_h : 0,
            'box_width' => $crop_w,
            'box_height' => $crop_h,
          ),
          'minimum' => array(
            'width'   => $widget_settings['enforce_minimum'] ? $res_w : NULL,
            'height'  => $widget_settings['enforce_minimum'] ? $res_h : NULL,
          ),
          //'preview' => $preview_js,
        ),
      );
      $element['#attached']['js'][] = array(
        'data' => array('imagefield_crop' => $settings),
        'type' => 'setting',
        'scope' => 'header',
      );

    }

    // prepend submit handler to remove button
   // array_unshift($element['remove_button']['#submit'], 'imagefield_crop_widget_delete');

   // return $element;
    return parent::process($element, $form_state, $form);
  }

  /**
   * Validate callback for alt and title field, if the user wants them required.
   *
   * This is separated in a validate function instead of a #required flag to
   * avoid being validated on the process callback.
   */
  public static function validateRequiredFields($element, FormStateInterface $form_state) {
    // Only do validation if the function is triggered from other places than
    // the image process form.
    if (!in_array('file_managed_file_submit', $form_state->getTriggeringElement()['#submit'])) {
      // If the image is not there, we do not check for empty values.
      $parents = $element['#parents'];
      $field = array_pop($parents);
      $image_field = NestedArray::getValue($form_state->getUserInput(), $parents);
      // We check for the array key, so that it can be NULL (like if the user
      // submits the form without using the "upload" button).
      if (!array_key_exists($field, $image_field)) {
        return;
      } // Check if field is left empty.
      elseif (empty($image_field[$field])) {
        $form_state->setError($element, t('The field !title is required', array('!title' => $element['#title'])));
        return;
      }
    }
  }

  public static function imagefield_add_cropinfo_fields($fid = NULL) {
    $defaults = array(
      'x'       => 0,
      'y'       => 0,
      'width'   => 50,
      'height'  => 50,
      'changed' => 0,
    );
    if ($fid) {
      $crop_info = variable_get('imagefield_crop_info', array());
      if (isset($crop_info[$fid]) && !empty($crop_info[$fid])) {
        $defaults = array_merge($defaults, $crop_info[$fid]);
      }
    }

    foreach ($defaults as $name => $default) {
      $element[$name] = array(
        '#type' => 'hidden',
        '#title' => $name,
        '#attributes' => array('class' => array('edit-image-crop-' . $name)),
        '#default_value' => $default,
      );
    }
    return $element;
  }

}


