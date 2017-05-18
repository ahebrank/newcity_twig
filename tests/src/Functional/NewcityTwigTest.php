<?php

namespace Drupal\Tests\newcity_twig\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Term; 
/**
 * A test for Twig extension.
 *
 * @group newcity_twig
 */
class NewcityTwigTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'newcity_twig',
    'newcity_twig_test',
    'pathauto',
    'views',
    'node',
    'image',
    'taxonomy',
  ];

  /**
   * create a taxonomy term
   */
  protected function createTerm(array $settings) {
    $term = Term::create($settings);
    $term->save();
    return $term;
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // copy over the test image and add it to the first node
    $image = file_get_contents(drupal_get_path('module', 'newcity_twig_test') . '/dog.jpg');
    $file = file_save_data($image, 'public://dog.jpg', FILE_EXISTS_REPLACE);

    // add some articles
    $node1 = $this->createNode(['title' => 'Alpha', 'type' => 'article']);
    $node1->field_image->target_id = $file->id();
    $node1->save();
    
    $this->createNode(['title' => 'Beta', 'type' => 'article']);
    $this->createNode(['title' => 'Gamma', 'type' => 'article']);

    // add some taxonomy terms
    $this->createTerm(['name' => 'First', 'vid' => 'tags']);
    $this->createTerm(['name' => 'Second', 'vid' => 'tags', 'field_color' => 'blue']);
  }

  /**
   * Tests output produced by the Twig extension.
   */
  public function testOutput() {

    $this->drupalGet('/node/1');

    // Test comment removed.
    $xpath = '//div[@class = "nt-nocomment" and not(comment())]';
    $this->assertByXpath($xpath);

    // Test "thumbnail" in URL.
    $xpath = '//div[@class = "nt-resize-image-url" and contains(text(), "styles/thumbnail/public") and contains(text(), "dog.jpg")]';
    $this->assertByXpath($xpath);

    // Test sentence trim.
    $xpath = '//div[@class = "nt-smarttrim" and text() = "Laboris minim in pariatur velit occaecat enim enim cupidatat labore labore."]';
    $this->assertByXpath($xpath);

    // Test alias.
    $xpath = '//div[@class = "nt-alias" and text() = "/article/alpha"]';
    $this->assertByXpath($xpath);

    // Test has rows
    $xpath = '//div[@class = "nt-hasrows" and text() = "1"]';
    $this->assertByXpath($xpath);

     // Test has no rows
    $xpath = '//div[@class = "nt-hasnorows" and not(text())]';
    $this->assertByXpath($xpath);

    // Test uniqid produces something.
    $xpath = '//div[@class = "nt-uniqid" and text()]';
    $this->assertByXpath($xpath);

    // Test svg output.
    $xpath = '//div[@class = "nt-svg"]';
    $this->assertByXpath($xpath  . '/svg[count(./path) = 6]');

    // Test svg output.
    $xpath = '//div[@class = "nt-rendertermlookup"]';
    $this->assertByXpath($xpath  . '/div[contains(@class, "taxonomy-term")]/*[descendant::a[contains(@href, "/taxonomy/term/2")]]');
  
    // Test image thumbnail
    $xpath = '//div[@class = "nt-resize-image-field"]';
    $this->assertByXpath($xpath  . '/*[descendant::img[contains(@src, "styles/thumbnail/public") and contains(@src, "dog.jpg")]]');
  
  }

  /**
   * Checks that an element specified by a the xpath exists on the current page.
   */
  public function assertByXpath($xpath) {
    $this->assertSession()->elementExists('xpath', $xpath);
  }

}
