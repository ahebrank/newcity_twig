<?php

namespace Drupal\newcity_twig\TwigExtension;

use Drupal\Core\Render\Element;
use Drupal\taxonomy\Entity\Term;
use Drupal\image\Entity\ImageStyle;
use \Twig_Environment;

class GoodExtension extends \Twig_Extension {

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName() {
    return 'newcity_twig.good_extension';
  }

  /**
   * Generates a list of all Twig filters that this extension defines.
   */
  public function getFilters() {
    return [
      // remove HTML comments from markup
      new \Twig_SimpleFilter('nocomment', [$this, 'removeHtmlComments']),
      // an alternate image style (from https://www.drupal.org/files/issues/twig_image_style-2361299-31.patch)
      new \Twig_SimpleFilter('resize', [$this, 'getImageFieldWithStyle']),
      // smart truncate
      new \Twig_SimpleFilter('smarttrim', [$this, 'smartTrim']),
      // get an alias for an entity
      new \Twig_SimpleFilter('alias', [$this, 'entityAlias']),
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
        // uri -> url
        new \Twig_SimpleFunction('uritourl', [$this, 'uriToUrl']),
        // load a term from a tid
        new \Twig_SimpleFunction('term_lookup', [$this, 'termLookup']),
        // return a rendered term based on a field in the term
        new \Twig_SimpleFunction('render_term_lookup', [$this, 'renderTermLookup'], ['is_safe' => ['html']]),
    ];
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
    if (isset($field['#field_type']) && $field['#field_type'] == 'image') {
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
      $stylerenderer = ImageStyle::load($style);
      return $stylerenderer->buildUrl($field);
    }

    return null;
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
    $url = '';
    if ($entity) {
      $url = $entity->toUrl()->toString();
    }
    return $url;
  }

  /**
   * check if a rendered view has rows (really, just looks for divs)
   * @return bool
   */
  public function viewHasRows($view) {
    $view = $this->removeHtmlComments($view);
    $dom = new \DOMDocument;
    $dom->loadHTML($view);
    $divs = $dom->getElementsByTagName('div');
    return ($divs->length > 1);
  }

  /**
   * wrap PHP uniqid
   * @return str hash
   */
  public function uniqid() {
    return uniqid();
  }
  
  /**
   * inject an svg from a theme
   * @return str html
   */
  public function svg($filename = null, $opts = array()) {
    if (is_null($filename)) {
        return "No SVG specified.";
    }
    // figure out the current theme path
    $theme_dir = \Drupal::theme()->getActiveTheme()->getPath();
    
    // svg dir defined relative to theme dir
    $dir = isset($opts['dir'])? $opts['dir'] : 'svg';
    $svg_dir = realpath($theme_dir . '/' . $dir);
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

  /**
   * set an xdebug breakpoint (if the extension is available)
   */
  public function setBreakpoint(Twig_Environment $environment, $context)
  {
    if (function_exists('xdebug_break')) {
      $arguments = array_slice(func_get_args(), 2);
      xdebug_break();
    }
  }

  /**
   * convert a URI into a URL
   */
  public function uriToUrl($uri) {
    $url = \Drupal\Core\Url::fromUri($uri);
    return $url->toString();
  }

  
  /**
   * return a full term entity from a tid
   */
  public function termLookup($tid) {
    if (is_array($tid) && count($tid) == 1) {
      $tid = array_shift($tid);
    }
    return Term::load($tid);
  }
  
  /**
   * lookup and render a term in $taxonomy
   * by matching $needle against $field values
   */
  public function renderTermLookup($taxonomy, $field, $needle) {
    $query = \Drupal::service('entity.query')
        ->get('taxonomy_term')
        ->condition('vid', $taxonomy)
        ->condition($field, $needle);
    $tids = $query->execute();
    if ($tids) {
        $term = $this->termLookup($tids);
        $view_builder = \Drupal::service('entity_type.manager')->getViewBuilder('taxonomy_term');
        return $view_builder->view($term);
    }
  }


  
}
