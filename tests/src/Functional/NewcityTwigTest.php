<?php

namespace Drupal\Tests\newcity_twig\Functional;

use Drupal\Tests\BrowserTestBase;

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
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // copy over the test image and add it to the first node
    $image = file_get_contents(drupal_get_path('module', 'newcity_twig_test') . '/dog.jpg');
    $file = file_save_data($image, 'public://dog.jpg', FILE_EXISTS_REPLACE);

    // add some articles
    $this->createNode(['title' => 'Alpha', 'type' => 'article',
                      'field_image' => [
                        'target_id' => $file->id(),
                      ]]);
    $this->createNode(['title' => 'Beta', 'type' => 'article']);
    $this->createNode(['title' => 'Gamma', 'type' => 'article']);

    // add some taxonomy terms
    $this->createTerm(['name' => 'First']);
    $this->createTerm(['name' => 'Second', 'field_color' => 'blue']);
  }

  /**
   * Tests output produced by the Twig extension.
   */
  public function testOutput() {

    $this->drupalGet('/node/1');

    // Test comment removed.
    $xpath = '//div[@class = "nt-nocomment"]';
    $this->assertByXpath($xpath . '[count(./comment() = 0]');

    // Test "thumbnail" in URL.
    $xpath = '//div[@class = "nt-resize-image-url"]';
    $this->assertByXpath($xpath . '[contains(text(), "styles/thumbnail/public/dog.jpg"]');

    // Test sentence trim.
    $xpath = '//div[@class = "nt-smarttrim"]';
    $this->assertByXpath($xpath . '[text() = "Laboris minim in pariatur velit occaecat enim enim cupidatat labore labore."]');

    // Test alias.
    $xpath = '//div[@class = "nt-alias"]';
    $this->assertByXpath($xpath  . '[text() = "/article/alpha"]');

    // Test has rows
    $xpath = '//div[@class = "nt-hasrows"]';
    $this->assertByXpath($xpath  . '[text() = "1"]');

     // Test has no rows
    $xpath = '//div[@class = "nt-hasnorows"]';
    $this->assertByXpath($xpath  . '[text() = "0"]');

    // Test uniqid produces something.
    $xpath = '//div[@class = "nt-uniqid"]';
    $this->assertByXpath($xpath  . '[text()]');

    // Test svg output.
    $xpath = '//div[@class = "nt-svg"]';
    $this->assertByXpath($xpath  . '/svg[count(./path = 6]');

    // Test svg output.
    $xpath = '//div[@class = "nt-rendertermlookup"]';
    $this->assertByXpath($xpath  . '/div[contains(@class, "taxonomy_term") and contains(@about, "/taxonomy/term/2")]');
  
    // Test image thumbnail
    $xpath = '//div[@class = "nt-resize-image-field"]';
    $this->assertByXpath($xpath  . '/*[descendant::img[contains(@href, "styles/thumbnail/public/dog.jpg")]]');
  
  }

  /**
   * Checks that an element specified by a the xpath exists on the current page.
   */
  public function assertByXpath($xpath) {
    $this->assertSession()->elementExists('xpath', $xpath);
  }

}
