<?php

namespace ucms\tournamentplanner\models;

class Tournament extends \ultimo\orm\Model {
  public $id;
  public $name;
  public $match_duration;
  public $between_duration;
  public $starts_at;
  public $show_in_dashboard = true;
  public $index;
  
  static protected $fields = array('id', 'name', 'match_duration', 'between_duration', 'starts_at', 'show_in_dashboard', 'index');
  static protected $primaryKey = array('id');
  static protected $autoIncrementField = 'id';
  static protected $relations = array(
    'groups' => array('Group', array('id' => 'tournament_id'), self::ONE_TO_MANY),
    'tournament_fields' => array('TournamentField', array('id' => 'tournament_id'), self::ONE_TO_MANY)
  );
  
  static protected $plugins = array('Sequence');
  
  static protected $scopes = array('byGroup', 'forDashboard', 'forExport');
  
  static public function byGroup($group_id) {
    return function ($q) use ($group_id) {
      $q->with('@groups')
        ->where('@groups.id = ?', array($group_id));
    };
  }
  
  static public function forDashboard() {
    return function ($q) {
      $q->with('@groups')
        ->where('@show_in_dashboard = ?', array(true))
        ->order('@index');
    };
  }
  
  static public function forExport() {
    return function ($q) {
      $q->order('@index');
    };
  }
  
  public function matches() {
    $model = $this;
    $staticModel = $this->_manager->getStaticModel('Match');
    $staticModel->scope(function ($q) use ($model) {
      $q->with('@group')
        ->where('@group.tournament_id = ?', array($model->id))
        ->order('starts_at', 'ASC');
    });
    return $staticModel;
  }
  
  public function fields() {
    $model = $this;
    $staticModel = $this->_manager->getStaticModel('Field');
    $staticModel->scope(function ($q) use ($model) {
      $q->with('@tournament_fields')
        ->where('@tournament_fields.tournament_id = ?', array($model->id))
        ->order('@tournament_fields.index', 'ASC');
    });
    return $staticModel;
  }
  
  public function relatedFields() {
    $model = $this;
    $staticModel = $this->_manager->getStaticModel('Field');
    $staticModel->scope(function ($q) use ($model) {
      $q->with('@tournament_fields')
        ->where('@tournament_fields.tournament_id = ?', array($model->id))
        ->order('@tournament_fields.index', 'ASC');
    });
    return $staticModel;
  }
  
  public function availableFields() {
    $staticModel = $this->_manager->getStaticModel('Field');
    
    $model = $this;
    $staticModel->scope(function ($q) use ($model) {
      $withoutFieldIds = array_keys($model->relatedFields()->fetchIdNameHash());
      $withoutFieldIds[] = 0;
      $q->where('@id NOT IN ?*', array($withoutFieldIds));
    });
    return $staticModel;
  }
  
  public function teams() {
    $model = $this;
    $staticModel = $this->_manager->getStaticModel('Team');
    $staticModel->scope(function ($q) use ($model) {
      $q->with('@group_teams')
        ->with('@group_teams.group')
        ->where('@group_teams.group.tournament_id = ?', array($model->id));
    });
    return $staticModel;
  }
  
  public function availableTeams() {
    $staticModel = $this->_manager->getStaticModel('Team');
    
    $model = $this;
    $staticModel->scope(function ($q) use ($model) {
      $withoutTeamIds = array_keys($model->teams()->fetchIdNameHash());
      
      if (count($withoutTeamIds) > 0) {
        $q->where('@id NOT IN ?*', array($withoutTeamIds));
      }
    });
    return $staticModel;
  }
  
  public function beforeDelete() {
    // delete all groups, group-teams, matches and standings
    $this->_manager->select('Group')
                   ->where('@tournament_id = ?', array($this->id))
                   ->with('@group_teams')
                   ->with('@standings')
                   ->with('@matches')
                   ->delete();
    
    //delete tournament fields
     $this->_manager->select('TournamentField')
                    ->where('@tournament_id = ?', array($this->id))
                    ->delete();
  }
}