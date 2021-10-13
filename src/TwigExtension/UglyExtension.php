<?php

// untested or one-off extensions

namespace Drupal\newcity_twig\TwigExtension;

class UglyExtension extends \Twig_Extension {

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName() {
    return 'newcity_twig.ugly_extension';
  }


  /**
   * Generates a list of all Twig filters that this extension defines.
   */
  public function getFilters() {
    return [
      // try to list only the first level of an array tree
      new \Twig_SimpleFilter('firstlevel', [$this, 'traverseFirstLevel']),
      // pull out / flatten field elements for use in a single template
      // useful for tabs, accordions, sliders
      new \Twig_SimpleFilter('flattenfield', [$this, 'flattenField']),
       // retheme a field value
      new \Twig_SimpleFilter('retheme', [$this, 'reTheme']),
      // retheme a field value
      new \Twig_SimpleFilter('refilter', [$this, 'reFilter']),
      // render a text field from the fielditem
      new \Twig_SimpleFilter('text_format', [$this, 'textFormat']),
      // create a superheading by taking the last word and making it larger
      new \Twig_SimpleFilter('multilinesuperhead', [$this, 'multilineSuperhead']),
      // override a view with custom referenced content
      new \Twig_SimpleFilter('override_eva_with', [$this, 'overrideEva']),
    ];
  }

  /**
   * Generates a list of all Twig functions that this extension defines.
   */
  public function getFunctions()
  {
    return [
    ];
  }

  public function traverseFirstLevel($mixed) {
    if (!is_array($mixed)) {
      return $mixed;
    }
    $output = [];
    foreach ($mixed as $i => $v) {
      if (is_array($v)) {
        foreach ($v as $j => $vprime) {
          $output[$i][$j] = (is_scalar($vprime))? $vprime : (is_array($vprime)? array_keys($vprime) : get_class($vprime));
        }
      }
      elseif (is_string($v) || is_bool($v)) {
        $output[$i] = $v;
      }
      else {
        $output[$i] = gettype($v);
      }
    }
    return $output;
  }

  /**
   * Simplify field values into a manageable array for cycling through tabs, accordion
   * items, carousel slides, etc.
   * @param  arr $field
   * @return arr        $items
   */
  public function flattenField($field, $internal_index = null) {
    $i = 0; $items = [];
    while (isset($field[$i])) {
      if (!is_null($internal_index)) {
        $items[$i] = $field[$i][$internal_index];
        $items[$i]->plain_title = strip_tags($items[$i]->title[0]->value);
      }
      else {
        $items[$i] = $field[$i];
      }
      $i+=1;
    }
    return $items;
  }

  /**
   * Take a field value and a theme function and reapply
   * @param  arr $field
   * @return arr        $render array
   */
  public function reTheme($field, $function) {
    $theme =[
      '#theme' => $function,
    ];
    foreach ($field as $k => $v) {
      $theme['#' . $k] = $v;
    }
    return \Drupal::service('renderer')->render($theme);
  }

  /**
   * Take a formatted text field and retheme
   * @param  arr $field
   * @return arr        $render array
   */
  public function reFilter($field) {
    $theme =[
      '#type' => 'processed_text',
      '#text' => $field->value,
      '#format' => $field->format,
    ];
    return \Drupal::service('renderer')->render($theme);
  }

  /**
   * render a long text field
   * @param  [type] $field [description]
   * @return [type]        [description]
   */
  public function textFormat($field) {
    $output = [];
    foreach ($field as $f) {
      // this is borrowed from https://api.drupal.org/api/drupal/core%21modules%21views%21src%21Plugin%21views%21area%21Text.php/function/Text%3A%3Arender/8.2.x
      $format = isset($fa->format) ? $fa->format : 'plain';
      if (isset($fa->value)) {
        $output[] = $this->reFilter($fa);
      }
    }
    return $output;
  }

  /**
   * Mulitline superheading -- all except for the last word is smaller
   * @param  obj $entity
   * @return str        $alias
   */
  public function multilineSuperhead($text) {
    $words = explode(' ', $text);
    $n = count($words);
    if ($n == 1) {
      return $text;
    }
    $last = $words[$n-1];
    $small = '<small>' . implode(' ', array_slice($words, 0, $n - 1)) . '</small> ';

    return '<span class="multiline">' . $small . $last . '</span>';
  }

  /**
   * override EVA output with featured content
   * useful for e.g., prepending featured content on a list
   * content should use rendered output for both
   * @param  [type] $eva     [description]
   * @param  [type] $content [description]
   * @return [type]          [description]
   */
  public function overrideEva($eva, $content) {
    $override_count = count($content['#items']);
    // why is this like this? grouping?
    $eva_rows = $eva[0]['#rows'][0]['#rows'];
    $eva_count = count($eva_rows);

    if ($override_count >= $eva_count) {
      // just do a replace
      $eva[0]['#rows'][0]['#rows'] = $content;
      return $eva;
    }

    $injected = [];
    for ($i = 0; $i < $override_count; $i++) {
      $injected[] = $content[$i];
    }

    $eva[0]['#rows'][0]['#rows'] = array_merge(
      $injected,
      array_slice($eva_rows, 0, $eva_count - $override_count)
    );

    return $eva;
  }
}
