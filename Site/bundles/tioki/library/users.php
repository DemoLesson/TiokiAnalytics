<?php

namespace Bundles\Tioki;
use Bundles\SQL\ListObj;
use Exception;
use StdClass;
use e;

class Users {

	public $fields = array(
		'ID',
		'Name',
		'Email',
		'RSVPs',
		'Vouches',
		'Skills',
		'Videos',
		'Connections',
		'Completion',
		'Triggered Analytics'
	);

	public function totals() {

		// Get the totals for specific tables
		try {
			$totals = new StdClass;
			$totals->vouched_skills = $this->_list('vouched_skills');
			$totals->skill_claims = $this->_list('skill_claims');
			$totals->videos = $this->_list('videos');
			$totals->connections = $this->_list('connections');
			// Average Percent Complete
			$totals->completion = $this->_list('users')
				->replace_select_field('CEIL(AVG(`users`.`completion`)) as `_completion`');

			// Clone the object
			$raw = unserialize(serialize($totals));

			// Dont need to show raw totals
			$all = false;

			// Loop through the lists recursively
			foreach($totals as $table => $l) {
				if(!empty($_GET['user_test']) && $_GET['user_test'] == "- ALL -")
					unset($_GET['user_test']);
				if(!empty($_GET['user_type']) && $_GET['user_type'] == "- ALL -")
					unset($_GET['user_type']);

				// Collections of joins and conds
				$joins = array();
				$condi = array();

				// If this is the completion percentage then add it
				if($table == 'completion') $table = 'users';
				$joins[$table] = false;

				// Filter by date created
				if(!empty($_GET['date_start']) && !empty($_GET['date_end'])) {
					$condi[] = "date(`$table`.`created_at`) BETWEEN '$_GET[date_start]' AND '$_GET[date_end]'";
					$all = true;
				}

				// Filter by user type
				if(!empty($_GET['user_type'])) {
					if(!array_key_exists('teachers', $joins) && $table == 'videos')
						$joins['teachers'] = "LEFT JOIN `teachers` ON `videos`.`teacher_id` = `teachers`.`id`";
					if(!array_key_exists('users', $joins) && $table == 'videos')
						$joins['users'] = "LEFT JOIN `users` ON `teachers`.`user_id` = `users`.`id`";
					if(!array_key_exists('users', $joins) && ($table != 'users' || $table != 'videos'))
						$joins['users'] = "LEFT JOIN `users` ON `$table`.`user_id` = `users`.`id`";
					if(!array_key_exists('teachers', $joins) && $_GET['user_type'] == 'educator')
						$joins['teachers'] = "LEFT JOIN `teachers` ON `users`.`id` = `teachers`.`user_id`";
					if(!array_key_exists('schools', $joins) && $_GET['user_type'] == 'organization')
						$joins['schools'] = "LEFT JOIN `schools` ON `users`.`id` = `schools`.`owned_by`";

					if($_GET['user_type'] == 'educator') $condi[] = "`teachers`.`id` IS NOT NULL";
					if($_GET['user_type'] == 'organization') $condi[] = "`schools`.`id` IS NOT NULL";
					$all = true;
				}

				// Filter by user test
				if(!empty($_GET['user_test'])) {
					if(!array_key_exists('teachers', $joins) && $table == 'videos')
						$joins['teachers'] = "LEFT JOIN `teachers` ON `videos`.`teacher_id` = `teachers`.`id`";
					if(!array_key_exists('users', $joins) && $table == 'videos')
						$joins['users'] = "LEFT JOIN `users` ON `teachers`.`user_id` = `users`.`user_id`";
					if(!array_key_exists('users', $joins) && $table != 'videos' && $table != 'users')
						$joins['users'] = "LEFT JOIN `users` ON `$table`.`user_id` = `users`.`id`";

					if($_GET['user_test'] == 'default') $condi[] = "`users`.`ab` IS NULL";
					else $condi[] = "`users`.`ab` = $_GET[user_test]";
					$all = true;
				}

				if(!empty($_GET['range'])) {

					// Split Start and end
					list($start, $end) = explode('~', $_GET['range']);

					if(!array_key_exists('teachers', $joins) && $table == 'videos')
						$joins['teachers'] = "LEFT JOIN `teachers` ON `videos`.`teacher_id` = `teachers`.`id`";
					if(!array_key_exists('users', $joins) && $table == 'videos')
						$joins['users'] = "LEFT JOIN `users` ON `teachers`.`user_id` = `users`.`user_id`";
					if(!array_key_exists('users', $joins) && $table != 'videos' && $table != 'users')
						$joins['users'] = "LEFT JOIN `users` ON `$table`.`user_id` = `users`.`id`";

					if($start < $end) $condi[] = "`users`.`id` BETWEEN '$start' AND '$end'";
					$all = true;
				}

				if(!empty($_GET['complete'])) {
					if(!array_key_exists('teachers', $joins) && $table == 'videos')
						$joins['teachers'] = "LEFT JOIN `teachers` ON `videos`.`teacher_id` = `teachers`.`id`";
					if(!array_key_exists('users', $joins) && $table == 'videos')
						$joins['users'] = "LEFT JOIN `users` ON `teachers`.`user_id` = `users`.`user_id`";
					if(!array_key_exists('users', $joins) && $table != 'videos' && $table != 'users')
						$joins['users'] = "LEFT JOIN `users` ON `$table`.`user_id` = `users`.`id`";

					if(preg_match("/(^[0-9]{1,3}).$/", $_GET['complete'])) {

						// Get regex data
						preg_match("/(^[0-9]{1,3})/", $_GET['complete'], $matches);
						$operator = str_replace($matches[1], '', $_GET['complete']);

						if(empty($operator)) $operator = '=';
						$condi[] = "'$matches[1]' $operator `users`.`completion`";
					}
					else if(preg_match("/(^[0-9]{1,2})-([0-9]{1,3}$)/", $_GET['complete'], $matches)) {
						
						// Remove original
						array_shift($matches);

						list($start, $end) = $matches;

						if($start < $end) $condi[] = "`users`.`completion` BETWEEN '$start' AND '$end'";
					}
					$all = true;
				}

				foreach($joins as $join)
					$l = $l->join($join);
				foreach($condi as $cond)
					$l = $l->manual_condition($cond);
			}

			// Event RSVPs added after as filters dont work to well
			$totals->events_rsvps = $this->_list('events_rsvps');
		}
		catch(Exception $e) {
			dump($e);
		}

		return array('normal' => $totals, 'raw' => $raw, 'all' => $all);
	}

	public function all() {
		$l = $this->_list('users');

		// Joins and Conds storage
		$joins = array();
		$condi = array();

		if(!empty($_GET['date_start']) && !empty($_GET['date_end']))
			$condi[] = "date(`last_login`) BETWEEN '$_GET[date_start]' AND '$_GET[date_end]'";

		if(!empty($_GET['user_type'])) {
			if($_GET['user_type'] == 'educator') {
				if(!array_key_exists('teachers', $joins))
					$joins['teachers'] = "LEFT JOIN `teachers` ON `users`.`id` = `teachers`.`user_id`";
				$condi[] = "`teachers`.`id` IS NOT NULL";
			}
			if($_GET['user_type'] == 'organization') {
				if(!array_key_exists('schools', $joins))
					$joins['schools'] = "LEFT JOIN `schools` ON `users`.`id` = `schools`.`owned_by`";
				$condi[] = "`schools`.`id` IS NOT NULL";
			}
		}

		if(!empty($_GET['user_test'])) {
			if($_GET['user_test'] == 'default') $condi[] = "`users`.`ab` IS NULL";
			else $condi[] = "`users`.`ab` = '$_GET[user_test]'";
		}

		if(!empty($_GET['range'])) {
			list($start, $end) = explode('~', $_GET['range']);
			if($start < $end) $condi[] = "`users`.`id` BETWEEN '$start' AND '$end'";
		}

		if(!empty($_GET['complete'])) {
			if(preg_match("/(^[0-9]{1,3}).$/", $_GET['complete'])) {

				// Get regex data
				preg_match("/(^[0-9]{1,3})/", $_GET['complete'], $matches);
				$operator = str_replace($matches[1], '', $_GET['complete']);

				if(empty($operator)) $operator = '=';
				$condi[] = "'$matches[1]' $operator `users`.`completion`";
			}
			else if(preg_match("/(^[0-9]{1,2})-([0-9]{1,3}$)/", $_GET['complete'], $matches)) {
				
				// Remove original
				array_shift($matches);

				list($start, $end) = $matches;

				if($start < $end) $condi[] = "`users`.`completion` BETWEEN '$start' AND '$end'";
			}
		}

		foreach($joins as $join)
			$l = $l->join($join);
		foreach($condi as $cond)
			$l = $l->manual_condition($cond);

		// Order by last login
		$l = $l->order('`users`.`last_login`', 'DESC');

		dump($l->raw());

		return $l;
	}

	// Load Tables
	private function _list($table) {
		return new ListObj($table, 'tioki');
	}
}