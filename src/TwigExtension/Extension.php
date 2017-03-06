<?php

namespace Drupal\newcity_twig\TwigExtension;

use Drupal\Core\Render\Element;
use Drupal\taxonomy\Entity\Term;
use \Twig_Environment;

class Extension extends \Twig_Extension {

  /**
   * Generates a list of all Twig filters that this extension defines.
   */
  public function getFilters() {
    return [
      // remove HTML comments from markup
      new \Twig_SimpleFilter('nocomment', [$this, 'removeHtmlComments']),
      // try to list only the first level of an array tree
      new \Twig_SimpleFilter('firstlevel', [$this, 'traverseFirstLevel']),
      // an alternate image style (from https://www.drupal.org/files/issues/twig_image_style-2361299-31.patch)
      new \Twig_SimpleFilter('resize', [$this, 'getImageFieldWithStyle']),
      // pull out / flatten field elements for use in a single template
      // useful for tabs, accordions, sliders
      new \Twig_SimpleFilter('flattenfield', [$this, 'flattenField']),
      // smart truncate
      new \Twig_SimpleFilter('smarttrim', [$this, 'smartTrim']),
      // get an alias for an entity
      new \Twig_SimpleFilter('alias', [$this, 'entityAlias']),
      // create a superheading by taking the last word and making it larger
      new \Twig_SimpleFilter('multilinesuperhead', [$this, 'multilineSuperhead']),
      // render a text field from the fielditem
      new \Twig_SimpleFilter('text_format', [$this, 'textFormat']),
      // override a view with custom referenced content
      new \Twig_SimpleFilter('override_eva_with', [$this, 'overrideEva']),
      // check if a view has any content
      new \Twig_SimpleFilter('has_rows', [$this, 'viewHasRows']),
    ];
  }

  /**
   * Generates a list of all Twig functions that this extension defines.
   */
  public function getFunctions()
  {
    return [
        // just wrap PHP uniqid()
        new \Twig_SimpleFunction('uniqid', [$this, 'uniqid']),
        // svg injection
        new \Twig_SimpleFunction('svg', [$this, 'svg'], ['is_safe' => ['html']]),
        // xdebug breakpoint (based on https://github.com/ajgarlag/AjglBreakpointTwigExtension)
        new \Twig_SimpleFunction('xdebug', [$this, 'setBreakpoint'], ['needs_environment' => true, 'needs_context' => true]),
        // load a term from a tid
        new \Twig_SimpleFunction('term_lookup', [$this, 'termLookup']),
        // uri -> url
        new \Twig_SimpleFunction('uritourl', [$this, 'uriToUrl']),
    ];
  }

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName() {
    return 'newcity_twig.twig_extension';
  }

  /**
   * Remove HTML comments (from e.g., a field with Twig debug output on)
   */
  public function removeHtmlComments($mixed) {
    if (is_array($mixed)) {
      // ugh, probably a render array
      return $mixed;
    }
    else {
      $output = trim(preg_replace('/<!--(.|\s)*?-->/', '', $mixed));
    }
    return $output;
  }

  public function traverseFirstLevel($mixed) {
    if (!is_array($mixed)) {
      return $mixed;
    }
    $output = [];
    foreach ($mixed as $i => $v) {
      if (is_array($v)) {
        foreach ($v as $j => $vprime) {
          $output[$i][$j] = array_keys($vprime);
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
   * Function that returns a renderable array of an image field with a given
   * image style.
   *
   * @param $field
   *   Renderable array of a field or maybe a URL
   * @param $style
   *   an image style.
   *
   * @return mixed
   *   a renderable array or NULL if there is no valid input.
   */
  public function getImageFieldWithStyle($field, $style) {
    if(isset($field['#field_type']) && $field['#field_type'] == 'image') {
      $element_children = Element::children($field, TRUE);

      if(!empty($element_children)) {
        foreach ($element_children as $delta => $key) {
          $field[$key]['#image_style'] = $style;
        }
      }
      return $field;
    }

    if (is_string($field)) {
      // assume it's a URL and try to resize
      $stylerenderer = \Drupal\image\Entity\ImageStyle::load($style);
      return $stylerenderer->buildUrl($field);
    }

    return null;
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
   * wrap PHP uniqid
   * @return str hash
   */
  public function uniqid() {
    return uniqid();
  }

  /**
   * Smart trim, word count
   * assumes a string that's had tags stripped out
   *   e.g., field|render|striptags|smarttrim(n)
   * @param  arr $field
   * @return int        $word_count
   */
  public function smartTrim($field, $word_count = 10) {
    $output = strtok($field, " \n");

    while(--$word_count > 0) {
      $word = strtok(" \n");
      $output .= " " . $word;
    }
    // add a few more to get to the end of the sentence
    while (($word !== false) && (strpos($word, '.') !== strlen($word)-1)) {
      $word = strtok(" \n");
      $output .= " " . $word;
    }

    return $output;
  }

  /**
   * Return an alias for an entity
   * @param  obj $entity
   * @return str        $alias
   */
  public function entityAlias($entity) {
    return $entity->toUrl()->toString();
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

  /**
   * render a long text field
   * @param  [type] $field [description]
   * @return [type]        [description]
   */
  public function textFormat($field) {
    $output = [];
    foreach ($field as $f) {
      $fa = $f->toArray();
      // this is borrowed from https://api.drupal.org/api/drupal/core%21modules%21views%21src%21Plugin%21views%21area%21Text.php/function/Text%3A%3Arender/8.2.x
      $format = isset($fa['format']) ? $fa['format'] : 'plain';
      if (isset($fa['value'])) {
        $output[] = array(
          '#type' => 'processed_text',
          '#text' => $fa['value'],
          '#format' => $format,
        );
      }
    }
    return $output;
  }
  
  public function viewHasRows($view) {
    $view = $this->removeHtmlComments($view);
    $dom = new \DOMDocument;
    $dom->loadHTML($view);
    $divs = $dom->getElementsByTagName('div');
    return ($divs->length > 1);
  }
  
  public function svg($filename = null, $opts = array()) {
    if (is_null($filename)) {
        return "No SVG specified.";
    }
    // figure out the current theme path
    $theme_dir = \Drupal::theme()->getActiveTheme()->getPath();
    
    // for PL, assume SVG directory in pl/public
    $svg_dir = realpath($theme_dir . '/svg');
    if ($svg_dir === FALSE) {
      return "SVG directory not found.";
    }
    if (strpos($filename, '.svg')===FALSE) {
      $filename .= '.svg';
    }
    $fn = $svg_dir . '/' . $filename;
    if (!file_exists($fn)) {
      return "SVG file " . $filename . " not found.";
    }
    
    $xml = simplexml_load_file($fn);
    if ($xml === FALSE) {
      return "Unable to read SVG";
    }
    
    $dom = dom_import_simplexml($xml);
    if (!$dom) {
      return "Unable to convert XML to DOM";
    }
    
    // manipulate the output
    foreach ($opts as $k => $v) {
      $dom->setAttribute($k, $v);
    }
    
    // spit out the svg tag
    $output = new \DOMDocument();
    $cloned = $dom->cloneNode(TRUE);
    $output->appendChild($output->importNode($cloned, TRUE));
    
    return $output->saveHTML();
  }

  public function setBreakpoint(Twig_Environment $environment, $context)
  {
    if (function_exists('xdebug_break')) {
      $arguments = array_slice(func_get_args(), 2);
      xdebug_break();
    }
  }
  
  // return a term from a tid
  public function termLookup($tid) {
    if (is_array($tid) && count($tid) == 1) {
      $tid = array_shift($tid);
    }
    return Term::load($tid);
  }
  
  // convert a URI to a URL
  public function uriToUrl($uri) {
    $url = \Drupal\Core\Url::fromUri($uri);
    return $url->toString();
  }
  
}
