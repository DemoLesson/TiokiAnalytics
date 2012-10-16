<?php

namespace Bundles\Filters;
use Exception;
use e;

class Bundle {
	
	private $filters;
	
	public function __callBundle($scope, $filter, $source, $args = array()) {
		if(!is_object($this->filters)) $this->filters = new Filters;
		
		if($filter == 'scope') return $this->filters->$filter($scope, $source, $args);
		return call_user_func_array(array($this->filters, $filter), array($source, $args));
	}
	
	public function addFilterClass($class) {
		$class = '\\'.$class;

		$class = new $class;
		if($class instanceof Filters)
			Filters::$_alternate_filters[] = $class;
	}
	
}

class Filters {
	
	public static $_alternate_filters = array();
	
	/**
	 * Force Calling of Filters to be done Statically
	 *
	 * @param string $function 
	 * @param string $args 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __call($function, $args) {
		if(method_exists($this, '_'.$function))
			return call_user_func_array(array($this, '_'.$function), $args);
			
		else if(__NAMESPACE__ == 'Bundles\Filters') foreach(self::$_alternate_filters as $class)
			return call_user_func_array(array($class, $function), $args);
	}

	private function _dump($source, $vars = array()) {
		$overall = isset($_GET['--lhtml-dump-overall']) ? 'position:fixed;width:100%;z-index:1000;' : '';
		if(method_exists($source, '__dumpFilter'))
			$source = call_user_func_array(array($source, '__dumpFilter'), array());
		if(isset($vars[0]) && $vars[0] == 'e3') dump($source);
		echo "<div class='debug_dump' style='padding: 1em;clear:both;margin: 0;border-bottom: 1px solid #000; overflow:auto;max-height:150px; background: #ffe; $overall '><b>Debug Dump".(isset($vars[0]) ? ' &mdash; '.$vars[0] : '')."</b><br/><pre>".var_export($source,true)."</pre></div>";
		return '';
	}
	
	private function _date($source, $vars = array()) {
		$tmp = (float) preg_replace("/[^0-9]*/", '', $source);
		if(!($tmp > 0)) $source = 0;

		if(!is_numeric($source))
			$source = strtotime($source);

		$format = array_shift($vars);

		if(is_null($format))
			$format = "Y-m-d h:ia";
		
		return date($format, $source);
	}

	private function _markdown($source, $vars = array()) {
		return e::markdown($source);
	}
	
	private function _money($source, $vars = array()) {
		$round = isset($vars[1]) ? $vars[1] : 0;
		switch($vars[0]) {
			case 'separate':
				$source = '<b>$</b>'.number_format((float)$source, (float)$round,'.',',');
				break;
			case 'USD':
			case 'US':
			default:
				if(!$source)
					$source = 0.00;
				if(is_numeric($source))
					$source = '$'.number_format($source, $round,'.',',');
				else if(is_object($source))
					$source = '[Object ' . get_class($source) . ']';
				else
					$source = '$' . $source;
			break;
		}
		//if($source == '$0.00') $source = '-.-';
		return $source;
	}
	
	private function _default($source, $vars = array()) {
		if($source === false || $source === null)
			return implode(',', $vars);
		return $source;
	}
	
	private function _default64($source, $vars = array()) {
		if($source === false || $source === null)
			return base64_decode(array_shift($vars));
		return $source;
	}
	
	private function _ucwords($source, $vars = array()) {
		return ucwords($source);
	}
	
	private function _uppercase($source, $vars = array()) {
		return strtoupper($source);
	}
	
	private function _lowercase($source, $vars = array()) {
		return strtolower($source);
	}
	
	private function _abs($source, $vars = array()) {
		return abs($source);
	}
	
	private function _plus($source, $vars = array()) {
		return $source + $vars[0];
	}
	
	private function _count($source, $vars = array()) {
		return count($source);
	}
	
	private function _toDollars($source, $vars = array()) {
		return $source / 100;
	}
	
	private function _add($source, $vars = array()) {
		return $source + array_sum($vars);
	}
	
	private function _toCents($source, $vars = array()) {
		return $source * 100;
	}
	
	private function _TF($source, $vars = array()) {
		if(is_string($source) && strlen($source) > 5)
			$ret = true;
		else if(!is_string($source) && $source)
			$ret = true;
		else $ret = false;

		$t = 'True';
		$f = 'False';

		if(!empty($vars[0]))
			list($t, $f) = explode('|', $vars[0]);

		if($ret) return $t;
		else return $f;
	}

	/**
	 * Simple filter to show singlular or plural text
	 * @author Nate Ferrero
	 */
	private function _pluralText($source, $vars = array()) {
		return $vars[$source == 1 ? 0 : 1];
	}

	/**
	 * Simple filter to show a number to a particular significance
	 * @author Nate Ferrero
	 */
	private function _sigFigs($source, $vars = array()) {
		preg_match('/[0-9.]+/', $source, $original);
		$original = array_shift($original);
		$num = (float) $original;
		$power = ceil(log10($num));
		$precision = (int) ($vars[0] - $power);
		if($precision < 0)
			$precision = 0;
		$num = round((float) $num, $precision);
		return str_replace($original, $num, $source);
	}

	private function _number($source, $vars = array()) {
		return number_format($source);
	}

	private function _html($source) {
		return htmlspecialchars($source);
	}

	private function _split($source, $vars = array()) {
		array_walk($vars,function(&$v){if(strpos($v,'b64:')===0)$v=base64_decode(substr($v,4));});
		$tmp = explode($vars[0], $source);
		return $tmp[$vars[1]];
	}

	private function _replace($source, $vars = array()) {
		if(is_object($source))
			$source = "[Object " . get_class($source) . ']';
		if($vars[0] == '--at')
			$vars[0] = '@';
		return str_replace($vars[0], $vars[1], $source);
	}

	private function _substr($source, $vars = array()) {
		return isset($vars[1]) ? substr($source, $vars[0], $vars[1]) : substr($source, $vars[0]);
	}

	private function _htmlentities($source) {
		return htmlentities($source);
	}

	private function _json($source) {
		return e\json_encode_safe($source);
	}

	private function _addslashes($source) {
		return addslashes($source);
	}

	private function _first($source) {
		return array_shift($source);
	}

	private function _last($source) {
		return array_pop($source);
	}

	private function _time_since($source) {
		return e\time_since($source);
	}

	private function _scope($scope) {
		dump($scope);
	}

	private function _if($source, $vars = array()) {
		$compare = array_shift($vars);
		$ifTrue = array_shift($vars);
		$ifFalse = array_shift($vars);
		eval(d);
	}

	private function _trim($source) {
		return trim($source);
	}

	private function _ifBeginsWith($source, $vars = array()) {
		$compare = array_shift($vars);
		$ifTrue = array_shift($vars);
		$ifFalse = array_shift($vars);

		if(substr($source, 0, strlen($compare)) == $compare) {
			return is_null($ifTrue) ? $source : $ifTrue;
		} else {
			return is_null($ifFalse) ? $source : $ifFalse;
		}
	}
	
}