<?php

namespace ucms\tournamentplanner\forms\groupteam;

class ModifyForm extends \ultimo\form\Form {
  
  protected function init() {
    $this->appendValidator('team_id', 'InArray', array(array_keys($this->getConfig('availableTeams'))));
  }
}