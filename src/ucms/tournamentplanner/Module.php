<?php

namespace ucms\tournamentplanner;

class Module extends \ultimo\mvc\Module implements \ultimo\security\mvc\AuthorizedModule {
  public function getAcl() {
    $acl = new \ultimo\security\Acl();
    $acl->addRole('tournamentplanner.guest');
    $acl->addRole('tournamentplanner.admin');
    
    $acl->allow('tournamentplanner.guest', array('match.dashboard'));
    $acl->allow('tournamentplanner.admin');
    return $acl;
  }

}