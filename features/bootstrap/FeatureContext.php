<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

  public $entities;

  /**
   * @Given /^I create a "([^"]*)" entity with the settings:$/
   */
  public function iCreateAEntityWithTheSettings($entity_type, TableNode $table) {
    $entities = [
      'room' => 'nuntius_room',
    ];

    $headers = $table->getRow(0);

    foreach ($table->getRows() as $key => $value) {
      if ($key == 0) {
        continue;
      }

      /** @var nuntiusRoomEntity $entity */
      $entity = entity_create($entities[$entity_type], array_combine($headers, $value));
      $entity->save();

      $this->entities[$entities[$entity_type]][] = $entity->identifier();
    }
  }

  /**
   * @AfterScenario
   */
  public function cleanDB($event) {
    if ($this->entities) {
      foreach ($this->entities as $type => $ids) {
        entity_delete_multiple($type, $ids);
      }
    }
  }

  /**
   * @Given /^the json should not contain "([^"]*)"$/
   */
  public function theJsonShouldNotContain($text) {
    if ($this->jsonContains($text)) {
      throw new \Exception('The text ' . $text . ' found in the page');
    }
  }

  /**
   * @Then /^the json should contain "([^"]*)"$/
   */
  public function theJsonShouldContain($text) {
    if (!$this->jsonContains($text)) {
      throw new \Exception('The text ' . $text . ' was not found in the page');
    }
  }

  /**
   * Check if a text exists inside the json.
   *
   * @param $text
   *   The text to search in the json.
   *
   * @return bool
   */
  protected function jsonContains($text) {
    $body = $this->getSession()->getPage()->getContent();
    $json = json_decode($body);

    foreach ($json->data as $data) {
      foreach (get_object_vars($data) as $children_data) {
        if ($text == $children_data) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
