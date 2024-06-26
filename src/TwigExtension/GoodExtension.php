<?php

namespace Drupal\newcity_twig\TwigExtension;

use Drupal\Core\Render\Element;
use Drupal\taxonomy\Entity\Term;
use Drupal\image\Entity\ImageStyle;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class GoodExtension extends AbstractExtension {

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
      new TwigFilter('nocomment', [$this, 'removeHtmlComments']),
      // an alternate image style (from https://www.drupal.org/files/issues/twig_image_style-2361299-31.patch)
      new TwigFilter('resize', [$this, 'getImageFieldWithStyle']),
      // smart truncate
      new TwigFilter('smarttrim', [$this, 'smartTrim']),
      // get an alias for an entity
      new TwigFilter('alias', [$this, 'entityAlias']),
      // check if a view has any content
      new TwigFilter('has_rows', [$this, 'viewHasRows']),
      // remove empty items from an array
      new TwigFilter('array_filter', 'array_filter'),
      // run the builder on an entity
      new TwigFilter('entity_view', [$this, 'entityView']),
      // html_decode_entities
      new TwigFilter('unescape', [$this, 'unescape']),
    ];
  }

  /**
   * Generates a list of all Twig functions that this extension defines.
   */
  public function getFunctions()
  {
    return [
        // just wrap PHP uniqid()
        new TwigFunction('uniqid', [$this, 'uniqid']),
        // svg injection
        new TwigFunction('svg', [$this, 'svg'], ['is_safe' => ['html']]),
        // xdebug breakpoint (based on https://github.com/ajgarlag/AjglBreakpointTwigExtension)
        new TwigFunction('xdebug', [$this, 'setBreakpoint'], ['needs_environment' => true, 'needs_context' => true]),
        // uri -> url
        new TwigFunction('uritourl', [$this, 'uriToUrl']),
        // load a term from a tid
        new TwigFunction('term_lookup', [$this, 'termLookup']),
        // return a rendered term based on a field in the term
        new TwigFunction('render_term_lookup', [$this, 'renderTermLookup'], ['is_safe' => ['html']]),
        // return an alias for a language
        new TwigFunction('lang_alias', [$this, 'langAlias']),
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
  public function viewHasRows($view, $class = 'views-row') {
    $view = $this->removeHtmlComments($view);
    if (empty($view)) {
      return FALSE;
    }
    $dom = new \DOMDocument();

    // load HTML but suppress warnings
    $libxml_previous_state = libxml_use_internal_errors(true);
    $dom->loadHTML($view);
    libxml_clear_errors();
    libxml_use_internal_errors($libxml_previous_state);

    $finder = new \DomXPath($dom);
    $rows = $finder->query("//*[contains(@class, '$class')]");
    return ($rows->length >= 1);
  }

  /**
   * build a render array for an entity
   * @return a render array
   */
  public function entityView($entity, $entity_type, $view_mode = 'full') {
    $builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
    return $builder->build($builder->view($entity, $view_mode));
  }

  /**
   * wrap html_entity_decode
   * @return str
   */
  public function unescape($value) {
    return html_entity_decode($value);
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
    $filename = basename($filename);
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
  public function setBreakpoint(Environment $environment, $context)
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
    if (!is_numeric($tid)) {
      // look up by label
      $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('name', $tid)
        ->accessCheck(TRUE); 
      $tids = $query->execute();
      if (count($tids) > 0) {
        $tid = array_shift($tids);
      }
      else {
        $tid = null;
      }
    }
    return Term::load($tid);
  }

  /**
   * lookup and render a term in $taxonomy
   * by matching $needle against $field values
   */
  public function renderTermLookup($taxonomy, $field, $needle) {
    $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('vid', $taxonomy)
        ->condition($field, $needle);
    $tids = $query->execute();
    if ($tids) {
        $term = $this->termLookup($tids);
        $view_builder = \Drupal::service('entity_type.manager')->getViewBuilder('taxonomy_term');
        return $view_builder->view($term);
    }
  }

  /**
   * from a system path
   * return an alias for a particular language
   */
   public function langAlias($system_path, $lang = 'en') {
     return \Drupal::service('path_alias.manager')->getAliasByPath($system_path, $lang);
   }



}
