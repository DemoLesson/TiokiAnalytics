<?php

namespace Bundles\Tioki;
use Exception;
use e;

class Bundle {

	private $sql;

	public function __initBundle() {
		$this->sql = e::sql('tioki');
	}
	
	public function users() {
		return new Users;
	}

	public function slugs() {
		return new Users;
	}

}