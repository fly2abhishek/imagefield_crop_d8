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
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

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
    /*    $element['preview_image_style'] = array(
          '#title' => t('Preview image style'),
          '#type' => 'select',
          '#options' => image_style_options(FALSE),
          '#empty_option' => '<' . t('no preview') . '>',
          '#default_value' => $this->getSetting('preview_image_style'),
          '#description' => t('The preview image will be shown while editing the content.'),
          '#weight' => 15,
        ); */
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $field_settings = $this->getFieldSettings();
    // Add upload resolution validation.
    if ($field_settings['max_resolution'] || $field_settings['min_resolution']) {
      $element['#upload_validators']['file_validate_image_resolution'] = array(
        $field_settings['max_resolution'],
        $field_settings['min_resolution']
      );
    }
    // If not using custom extension validation, ensure this is an image.
    $supported_extensions = array('png', 'gif', 'jpg', 'jpeg');
    $extensions = isset($element['#upload_validators']['file_validate_extensions'][0]) ? $element['#upload_validators']['file_validate_extensions'][0] : implode(' ', $supported_extensions);
    $extensions = array_intersect(explode(' ', $extensions), $supported_extensions);
    $element['#upload_validators']['file_validate_extensions'][0] = implode(' ', $extensions);
    // Add all extra functionality provided by the image widget.
    $element['#process'][] = array(get_class($this), 'process');
    // Add properties needed by process() method.
    $element['#preview_image_style'] = $this->getSetting('preview_image_style');
    $element['#title_field'] = $field_settings['title_field'];
    $element['#title_field_required'] = $field_settings['title_field_required'];
    $element['#alt_field'] = $field_settings['alt_field'];
    $element['#alt_field_required'] = $field_settings['alt_field_required'];
    // Default image.
    $default_image = $field_settings['default_image'];
    if (empty($default_image['fid'])) {
      $default_image = $this->fieldDefinition->getFieldStorageDefinition()
        ->getSetting('default_image');
    }
    $element['#default_image'] = !empty($default_image['fid']) ? $default_image : array();
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
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];
    $element['#theme'] = 'image_widget';
    $element['#attached']['css'][] = drupal_get_path('module', 'image') . '/css/image.theme.css';
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
        '#theme' => 'image_style',
        '#width' => $variables['width'],
        '#height' => $variables['height'],
        '#style_name' => $variables['style_name'],
        '#uri' => $variables['uri'],
      );
      // Store the dimensions in the form so the file doesn't have to be
      // accessed again. This is important for remote files.
      $element['width'] = array(
        '#type' => 'hidden',
        '#value' => $variables['width'],
      );
      $element['height'] = array(
        '#type' => 'hidden',
        '#value' => $variables['height'],
      );
    }
    elseif (!empty($element['#default_image'])) {
      $default_image = $element['#default_image'];
      $file = File::load($default_image['fid']);
      if (!empty($file)) {
        $element['preview'] = array(
          '#weight' => -10,
          '#theme' => 'image_style',
          '#width' => $default_image['width'],
          '#height' => $default_image['height'],
          '#style_name' => $element['#preview_image_style'],
          '#uri' => $file->getFileUri(),
        );
      }
    }
    // Add the additional alt and title fields.
    $element['alt'] = array(
      '#title' => t('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => isset($item['alt']) ? $item['alt'] : '',
      '#description' => t('This text will be used by screen readers, search engines, or when the image cannot be loaded.'),
      // @see https://drupal.org/node/465106#alt-text
      '#maxlength' => 512,
      '#weight' => -12,
      '#access' => (bool) $item['fids'] && $element['#alt_field'],
      '#element_validate' => $element['#alt_field_required'] == 1 ? array(
          array(
            get_called_class(),
            'validateRequiredFields'
          )
        ) : array(),
    );
    $element['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => isset($item['title']) ? $item['title'] : '',
      '#description' => t('The title is used as a tool tip when the user hovers the mouse over the image.'),
      '#maxlength' => 1024,
      '#weight' => -11,
      '#access' => (bool) $item['fids'] && $element['#title_field'],
      '#element_validate' => $element['#title_field_required'] == 1 ? array(
          array(
            get_called_class(),
            'validateRequiredFields'
          )
        ) : array(),
    );
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

}
