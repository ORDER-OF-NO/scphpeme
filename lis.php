<?php

namespace Scphpeme;

class Symbol {
	private $val;
	public function __construct($val) {
		$this->val = $val;
	}
	
	public function __toString() {
		return (string)$this->val;
	}
}

class Lambda {
	private $sexp;
	private $func; 
	
	public function __construct($sexp, $func) {
		$this->sexp = $sexp;
		$this->func = $func;
	}
	
	public function __toString() {
		return to_string($this->sexp);
	}
	public function __invoke() {
		return call_user_func_array($this->func, func_get_args());
	}
	
	public static function create($sexp, $func) { 
		return new Lambda($sexp, $func);
	}
}

class Env {
	//An environment: a dict of {'var':val} pairs, with an outer Env.
	private $values;
	private $outer;
	
	public function __construct($values = array(), $outer = false) {
		$this->values = $values;
		$this->outer = $outer;
	}
	
	public function update($values) {
		$this->values = $this->values + $values;
		return $this;
	}
		
	public function find($var) {
		return (isset($this->values[(string)$var])) ? $this : ($this->outer ? $this->outer->find($var) : $this);
	}
	
	public function &at($var) {
		if(isset($this->values[(string)$var])) {
			$ret =& $this->values[(string)$var];
		} else {
			$false = false;
			$ret =& $false;
		}
		return $ret;
	}
	
	public function setAt($var, $val) {
		$this->values[(string)$var] = $val;
	}
}

function add_globals($env) {
	//add some Scheme standard procedures to an environment.	
	return $env->update(array(
		'+' => function($x, $y) { return $x + $y; },
		'-' => function($x, $y) { return $x - $y; },
		'*' => function($x, $y) { return $x * $y; }, 
		'/' => function($x, $y) {	return $x / $y; },
		'not' => function($x) { return !(bool)$x; },
		'>' => function($x, $y) { return $x > $y; },
		'<' => function($x, $y) { return $x < $y; },
		'>=' => function($x, $y) { return $x >= $y; },
		'<=' => function($x, $y) { return $x <= $y; },
		'=' => function($x, $y) { return $x == $y; },
		'equal?' => function($x, $y) { return $x == $y; },
		'eq?' => function($x, $y) {	return $x === $y; },
		'len' => function($x) { return count($x); }, 
		'cons' => function($x, $y) { return array_merge(array($x), $y); },
		'car' => function($x) { return $x[0]; },
		'cdr' => function($x) { return $x[1]; },
		'append' => function($x,$y) { return array_merge($x, $y); },
		'list' => function() { return func_get_args(); },
		'list?' => function($x) { return is_array($x); },
		'null?' => function($x) { return $x == array(); },
		'symbol?' => function($x) { return isa($x, 'Scphpeme\Symbol'); } 
	));
}

$global_env = add_globals(new Env());

function isa($x, $type) {
	return $type == 'list' ? is_array($x) : (is_object($x) && get_class($x) == $type);
}

function setAt($list, $key, $val) {
	
}

function _eval($x, $env = false) {
	//Evaluate an expression in an environment.
	global $global_env;
	$env || $env = $global_env;
	
	if(isa($x, 'Scphpeme\Symbol')) {     # variable reference
		return $env->find($x)->at($x);
	} elseif(!isa($x, 'list')) {         # constant literal
		return $x;
	} elseif($x[0] == 'quote') {         # (quote exp)
		list($_, $exp) = $x;
		return $exp;
	} elseif($x[0] == 'if') {            # (if test conseq alt)
		list($_, $test, $conseq, $alt) = $x;
		return _eval((_eval($test, $env) ? $conseq : $alt), $env);
	} elseif($x[0] == 'set!') {          # (set! var exp)
		list($_, $var, $exp) = $x;
		$env->find($var)->setAt($var, _eval($exp, $env));
	} elseif($x[0] == 'define') {        # (define var exp)
		list($_, $var, $exp) = $x;
		$env->setAt($var, _eval($exp, $env));
	} elseif($x[0] == 'lambda') {        # (lambda (var*) exp)
		list($_, $vars, $exp) = $x;
		return Lambda::create($x, function() use($vars, $exp, &$env){ return _eval($exp, new Env(array_combine($vars, func_get_args()), $env)); }); 
	} elseif($x[0] == 'begin') {         # (begin exp*)
		foreach(array_slice($x, 1) as $exp) $val = _eval($exp, $env);
		return $val;
	} else {                             # (proc exp*)
		$exps = array_map(function($exp) use(&$env) { return _eval($exp, $env); }, $x);
		return call_user_func_array(array_shift($exps), $exps);
	}
}

################ parse, read, and user interaction

function syntaxError($msg) {
	throw new \Exception($msg);
}

function read($s) {
	//Read a Scheme expression from a string.
	return read_from(tokenize($s));
}

function parse($s) { 
	return read($s);
}

function tokenize($s) {
	//Convert a string into a list of tokens.
	return explode(' ', trim(preg_replace('/[[:blank:]]+/', ' ', str_replace(array('(', ')'), array(' ( ', ' ) '), $s))));
}

function read_from(&$tokens) {
	//Read an expression from a sequence of tokens
    count($tokens) || syntaxError('unexpected EOF while reading');
    $token = array_shift($tokens);
    if('(' == $token) {
        $L = array();
        while($tokens[0] != ')') $L[] = read_from($tokens);
        array_shift($tokens); # pop off ')'
        return $L;
    } elseif(')' == $token) {
       syntaxError('unexpected )');
    } else {
        return atom($token);
	}
}

function atom($token) {
	//Numbers become numbers; every other token is a symbol.
	return is_numeric($token) ? (float)$token : new Symbol($token);
}

function to_string($exp) {
	//Convert a PHP object back into a Lisp-readable string.
	return isa($exp, 'list') ? ('(' . join(' ', array_map('Scphpeme\to_string', $exp)) . ')') : (string)$exp;
}

function raw_input($prompt = '>') {
	static $handle;
	$handle || $handle = fopen('php://stdin', 'r');
	print $prompt;
	return fgets($handle);
}

function repl($prompt = 'lis.php> ') {
	//A prompt-read-eval-print loop.
	while(true) if($val = _eval(parse(raw_input($prompt)))) print to_string($val) . "\n";
}

repl();