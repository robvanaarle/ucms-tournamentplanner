<?php

namespace ucms\tournamentplanner\models;

class Group extends \ultimo\orm\Model {
  public $id;
  public $name;
  public $tournament_id;
  public $index;
  
  static protected $fields = array('id', 'name', 'tournament_id', 'index');
  static protected $primaryKey = array('id');
  static protected $autoIncrementField = 'id';
  static protected $relations = array(
    'tournament' => array('Tournament', array('tournament_id' => 'id'), self::MANY_TO_ONE),
    'standings' => array('Standing', array('id' => 'group_id'), self::ONE_TO_MANY),
    'matches' => array('Match', array('id' => 'group_id'), self::ONE_TO_MANY),
    'group_teams' => array('GroupTeam', array('id' => 'group_id'), self::ONE_TO_MANY)
  );
  
  static protected $plugins = array('Sequence');
  static public $_sequenceGroupFields = array('tournament_id');
  
  static protected $scopes = array('forTournament', 'withTournament', 'withTeams', 'withFullStandings', 'forDashboard');
  
  static protected $fetchers = array('getWithGroupsAsKey');
  
  static public function getWithGroupsAsKey($s) {
    $result = array();
    foreach ($s->all() as $group) {
      $result[$group->id] = $group;
    }
    return $result;
  }
  
  static public function forTournament($tournament_id) {
    return function ($q) use ($tournament_id) {
      $q->where('tournament_id = ?', array($tournament_id));
    };
  }
  
  static public function withTournament() {
    return function ($q) {
      $q->with('@tournament');
    };
  }
  
  static public function withTeams() {
    return function ($q) {
      $q->with('@group_teams')
        ->with('@group_teams.team')
        ->order('@group_teams.index', 'ASC');
    };
  }
  
  static public function withFullStandings() {
    return function ($q) {
      $q->with('@standings')
        ->with('@standings.team')
        ->order('@standings.index', 'ASC');
    };
  }
  
  static public function forDashboard() {
    return function ($q) {
      $q->with('@matches')
        ->with('@matches.home_team')
        ->with('@matches.away_team')
        ->with('@matches.field')
        ->with('@matches.field.tournament_fields')
        ->with('@tournament')
        ->where('@matches.field.tournament_fields.tournament_id = @tournament.id')
        ->order('@tournament.index', 'ASC')
        ->order('@index', 'ASC')
        ->order('@matches.starts_at', 'ASC')
        ->order('@matches.field.tournament_fields.index', 'ASC');
    };
  }
  
  public function teams() {
    $model = $this;
    $staticModel = $this->_manager->getStaticModel('Team');
    $staticModel->scope(function ($q) use ($model) {
      $q->with('@group_teams')
        ->where('@group_teams.group_id = ?', array($model->id))
        ->order('@group_teams.index', 'ASC');
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
  
  public function syncStandings() {
    $matches = $this->related('matches')->played()->all();
    $teams = $this->teams()->all();
    $standings = $this->related('standings')->getWithTeamsAsKey();
    
    foreach ($teams as $team) {
      if (!isset($standings[$team->id])) {
        $standings[$team->id] = $this->_manager->create('Standing');
        $standings[$team->id]->team_id = $team->id;
        $standings[$team->id]->group_id = $this->id;
        $standings[$team->id]->save();
      }
      $standings[$team->id]->reset();
      $standings[$team->id]->team = $team;
    }
    
    foreach ($matches as $match) {
      $standingHome = $standings[$match->home_team_id];
      $standingHome->goals_for += $match->goals_home;
      $standingHome->goals_against += $match->goals_away;
      $standingHome->won += ($match->homeWins() ? 1 : 0);
      $standingHome->drawn += ($match->tie() ? 1 : 0);
      $standingHome->lost += ($match->awayWins() ? 1 : 0);

      $standingAway = $standings[$match->away_team_id];
      $standingAway->goals_for += $match->goals_away;
      $standingAway->goals_against += $match->goals_home;
      $standingAway->won += ($match->awayWins() ? 1 : 0);
      $standingAway->drawn += ($match->tie() ? 1 : 0);
      $standingAway->lost += ($match->homeWins() ? 1 : 0);
    }
    
    usort($standings, function($a, $b) {
      $result = $b->compareTo($a);
      if ($result == 0) {
        return ($a->team->group_teams[0]->index > $b->team->group_teams[0]->index) ? 1 : -1;
      } else {
        return $result;
      }
    });
    
    foreach ($standings as $index => $standing) {
      $standing->index = $index;
      $standing->save();
    }
  }
  
  public function beforeDelete() {
    // delete all group-teams, matches and standings
    $this->related('group_teams')->query()->delete();
    $this->related('matches')->query()->delete();
    $this->related('standings')->query()->delete();
  }
}