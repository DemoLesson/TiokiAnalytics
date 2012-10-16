<?php

namespace Portals\Site\Controllers;
use Bundles\Controller\normalRoute;
use Exception;
use e;

class Users {
	
	public function excel() {

		// Get Users
		$users = e::$tioki->users();

		// Prepare data
		$data = array();

		// Add Column names
		$data['columns'] = $users->fields;

		// Prepare totals
		$totals = array('*', 'All', 'N/A');
		$totals[] = $users->totals()->normal->events_rsvps->count();

		
		$totals[] = $users->totals()->normal->vouched_skills->count().($users->totals()->all == true ? ' ('.$users->totals()->raw->vouched_skills->count().')' : '');

		$totals[] = $users->totals()->normal->skill_claims->count().($users->totals()->all == true ? ' ('.$users->totals()->raw->skill_claims->count().')' : '');

		$totals[] = $users->totals()->normal->videos->count().($users->totals()->all == true ? ' ('.$users->totals()->raw->videos->count() : '');

		$totals[] = $users->totals()->normal->connections->count().($users->all == true ? ' ('.$users->totals()->raw->videos->count() : '');

		$totals[] = array_shift($users->totals()->normal->completion->first()).'%'.($users->totals()->all == true ? ' ('.array_shift($users->totals()->raw->completion->first()) : '');

		$totals[] = 'N/A';

		$data['rows'] = array();
		array_push($data['rows'], $totals);

		foreach($users->all() as $user) {
			$row = array();
			$row[] = $user['id'];
			$row[] = $user['name'];
			$row[] = $user['email'];

			if($users->totals()->all == false)
				$row[] = $user['events_rsvps'];
			else $row[] = $user['events_rsvps'].' ('.$user['raw_events_rsvps'].')';

			if($users->totals()->all == false)
				$row[] = $user['vouched_skills'];
			else $row[] = $user['vouched_skills'].' ('.$user['raw_vouched_skills'].')';

			if($users->totals()->all == false)
				$row[] = $user['skill_claims'];
			else $row[] = $user['skill_claims'].' ('.$user['raw_skill_claims'].')';

			if($users->totals()->all == false)
				$row[] = $user['videos'];
			else $row[] = $user['videos'].' ('.$user['raw_videos'].')';

			if($users->totals()->all == false)
				$row[] = $user['connections'];
			else $row[] = $user['connections'].' ('.$user['raw_connections'].')';

			if($users->totals()->all == false)
				$row[] = $user['completion'];
			else $row[] = $user['completion'].' ('.$user['raw_completion'].')';

			$row[] = $user['analytics'];
			array_push($data['rows'], $row);
		}

		e::$events->toExcel('users_export_'.date("m_d_Y"), $data);
	}

	public function index() {
		throw new normalRoute;
	}
	
}