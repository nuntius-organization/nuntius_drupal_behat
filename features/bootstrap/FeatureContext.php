<?php

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext {

  /**
   * @var array
   *
   * List of entities to delete.
   */
  protected $entities = [];

  /**
   * @var array
   *
   * List of the users.
   */
  protected $localUsers = [];

  /**
   * @var array
   *
   * Map human readable name to entity type.
   */
  protected $entityTypes = [
    'room' => 'nuntius_room',
    'audience room' => 'nuntius_room_audience',
  ];

  /**
   * FeatureContext constructor.
   */
  function __construct($parameters) {
    $this->localUsers = $parameters['users'];
  }

  /**
   * @Given /^I create a "([^"]*)" entity with the settings:$/
   */
  public function iCreateAEntityWithTheSettings($entity_type, TableNode $table) {

    $headers = $table->getRow(0);

    foreach ($table->getRows() as $key => $value) {
      if ($key == 0) {
        continue;
      }

      $this->setValueFromReference($value);

      /** @var nuntiusRoomEntity $entity */
      $entity = entity_create($this->entityTypes[$entity_type], array_combine($headers, $value));
      $entity->save();

      $this->entities[$this->entityTypes[$entity_type]][] = $entity->identifier();
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

  /**
   * Set property value from reference to property on the class.
   *
   * @param $values
   *   The values of the entity before write it into the DB.
   */
  protected function setValueFromReference(&$values) {
    foreach ($values as &$value) {
      if (strpos($value, ':') !== FALSE) {

        list($entity_type, $name) = explode(':', $value);

        if (property_exists($this, $entity_type)) {
          $identifier_key = NULL;

          if ($entity_type == 'users') {
            $identifier_key = 'uid';
          }

          if ($identifier_key) {
            $value = $this->{$entity_type}[$name]->{$identifier_key};
          }
        }
        else {
          $entity_type = $this->entityTypes[$entity_type];
          $entity_info = entity_get_info($entity_type);

          $query = new EntityFieldQuery();
          $results = $query
            ->entityCondition('entity_type', $entity_type)
            ->propertyCondition($entity_info['entity keys']['label'], $name)
            ->execute();

          if (empty($results['nuntius_room'])) {
            throw new Exception('Could not found entity by the ' . $value . ' filter criteria.');
          }

          $keys = array_keys($results['nuntius_room']);

          $value = reset($keys);
        }
      }
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
   * @BeforeScenario
   */
  public function before(Behat\Behat\Hook\Scope\BeforeScenarioScope $event) {
    if (in_array('create-users', $event->getScenario()->getTags())) {
      // Crete users from the argument table.
      foreach ($this->localUsers as $user) {
        $new_user = (object) $user;
        $new_user->pass = $this->getRandom()->name();
        $this->userCreate($new_user);
      }
    }
  }

  /**
   * @When /^I am logging in as "([^"]*)"$/
   */
  public function iAmLoggingInAs($name) {
    if (!isset($this->users[$name])) {
      throw new \Exception(sprintf('No user with %s name is registered with the driver.', $name));
    }

    // Change internal current user.
    $this->user = $this->users[$name];

    // Login.
    $this->login();
  }

}

