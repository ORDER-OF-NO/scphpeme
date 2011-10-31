<?php

namespace lisp; 

################ Symbol, Procedure, classes

const None = false;
const Symbol = 'lisp\Symbol';
const File = 'lisp\Symbol';
const Procedure = 'lisp\Procedure';
const _list = 'list';
const expand = 'lisp\expand';
const SyntaxError = 'lisp\SyntaxError';
const RuntimeWarning = 'lisp\RuntimeWarning';
const TypeError = 'lisp\TypeError';
const LookupError = 'lisp\LookupError';

class SyntaxError extends \Exception {}

class TypeError extends \Exception {}
	
class LookupError extends \Exception {}
	
class RuntimeWarning extends \Exception {
	public $retval;
}
	
class Symbol {
	private $val;
	public function __construct($val) {
		$this->val = $val;
	}
	
	public function __toString() {
		return (string)$this->val;
	}
	
	public function is($val) {
		return $this->val == $val;
	}
}

function Sym($s, &$symbol_table = array()) {
	#Find or create unique Symbol entry for str s in symbol table.
	if(!isset($symbol_table[$s])) {
		$symbol_table[$s] = new Symbol($s);
	}
	return $symbol_table[$s];
}

class S {
	private static $syms = array(
		'quote' => 'quote', 
		'_if' => 'if', 
		'set' => 'set!', 
		'define' => 'define', 
		'lambda' => 'lambda',
		'begin' => 'begin', 
		'define_macro' => 'define-macro', 
		'append' => 'append',
		'cons' => 'cons',
		'quasiquote' => 'quasiquote', 
		'unquote' => 'unquote', 
		'unquote_splicing' => 'unquote-splicing', 
		'eof_object' => '#<eof-object>');
	
	private static $instances = array();
	
	public static function addSymbol($name, $val = null) {
		if(is_null($val)) {
			$val = $name;
		}
		self::$syms[$val] = $name;
	}
	
	public static function __callStatic($sym, $args = array()) {
		if(isset(self::$syms[$sym])) {
			if(!empty($args)) {
				return self::$syms[$sym] == current($args);
			} else {
				if(!isset(self::$instances[$sym])) {
					self::$instances[$sym] = Sym(self::$syms[$sym]);
				}
				return self::$instances[$sym];
			}
		} else {
			throw new \Exception("Invalid symbol $sym");
		}
	}
}

class Procedure {
	#a user-defined Scheme procedure."
	public $parms;
	public $exp;
	public $env;
	
	public function __construct($parms, $exp, $env) {
		$this->parms = $parms;
		$this->exp = $exp;
		$this->env = $env;
	}
	
	public function __toString() {
		return to_string(array(array(S::lambda(), $this->parms, $this->exp), array_keys($this->env->values)));
	}
	
	public function __invoke() {
		return _eval($this->exp, new Env($this->parms, func_get_args(), $this->env));
	}
}

################ parse, read, and user interaction

function parse($inport) {
	#Parse a program: read and expand/error-check it.
	# Backwards compatibility: given a str, convert it to an InPort
	if(is_string($inport)) {
		$inport = new InPort(new StringFile($inport));
	}
	return expand(read($inport), true);
}

class StringFile {
	private $content;
	private $lines;
	private $index;
	private $lineCount;
	
	public function __construct($content) {
		$this->lines = explode("\n", $content);
		$this->lineCount = count($this->lines) - 1;
		$this->index = 0;
	}
	
	public function readline() {
		if($this->index > $this->lineCount) {
			return '';
		} else {
			return $this->lines[$this->index++];
		}
	}
}

class InPort {
	#An input port. Retains a line of chars.
	private $tokenizer = "/\s*(,@|[('`,)]|\"(?:[\\].|[^\"])*\"|;.*|[^\s('\"`,;)]*)(.*)/";
	public $file;
	public $line;
	public function __construct($file) {
		$this->file = $file;
		$this->line = '';
	}
	
	public function next_token() {
		#Return the next oken, reading new text into line buffer if needed.
		while(true) {
			if($this->line == '') {
				$this->line = $this->file->readline();
			}
			if($this->line == '') {
				return S::eof_object();
			}
			preg_match_all($this->tokenizer, $this->line, $matches);
			$token = $matches[1][0];
			$this->line = $matches[2][0];
			if($token != '' && $token[0] != ';') {
				return $token;
			}
		}
	}
}

function readchar($inport) {
	#Read the next character from an input port.
	if($inport->line != '') {
		$ch = $inport->line[0];
		$inport->line = substr($inport->line, 1);
		return $ch;
	} else {
		return S::eof_object();
	}
}

class Quote {
	public static $quotes = array();
	public static function is($val) {
		return isset(self::$quotes[(string)$val]);
	}
	
	public static function get($val) {
		return self::$quotes[(string)$val];
	}
}
Quote::$quotes = array("'" => S::quote(), "`" => S::quasiquote(), "," => S::unquote(), ",@" => S::unquote_splicing());

function read($inport) {
	#Read a Scheme expression from an input port.
	$read_ahead = function($token) use(&$read_ahead, &$inport) {
		if('(' == $token) {
			$L = array();
			while(true) {
				$token = $inport->next_token();
				if($token == ')') {
					return $L;
				} else {
					$L[] = $read_ahead($token);
				}
			}
		} else if(')' == $token) {
			throw new SyntaxError('unexpected )');
		} else if(Quote::is($token)) {
			return array(Quote::get($token), read($inport));
		} else if(S::eof_object($token)) {
			throw new SyntaxError('unexpected EOF in list');
		} else {
			return atom($token);
		}
	};
	
	$token1 = $inport->next_token();
	return S::eof_object($token1) ? $token1 : $read_ahead($token1);
}


function atom($token) {
	#Numbers become numbers; #t and #f are booleans; "..." string; otherwise Symbol.
    if($token == '#t') { 
		return true;
	} else if($token == '#f') {
		return false;
	} else if($token == '"') {
		return stripslashes(substr($token, 1, -1));
	} else {
		return is_numeric($token) ? (float)$token : Sym($token);
	}
}

function to_string($x) {
	#Convert a PHP object back into a Lisp-readable string.
	if($x === true) { 
		return "#t";
	} else if($x === false) {
		return "#f";
	} else if($x instanceof Symbol) {
		return $x;
	} else if(is_string($x)) {
		return '"' . addslashes($x) . '"';
	} else if(is_array($x)) {
		return '(' . join(' ', array_map('lisp\to_string', $x)) . ')';
	} else if($x instanceof \Closure) {
		return "<CLOSURE>";
	} else {
		return (string)$x;
	}
}

function load($filename) {
	#Eval every expression from a file.
	repl(None, new InPort(open($filename)), None);
}

function repl($prompt='lisphp> ', $inport = null, $out = null) {
	#A prompt-read-eval-print-loop
	if(is_null($inport)) {
		$inport = new InPort(stdin);
	}
	if(is_null($out)) {
		$out = stdout;
	}
	print "Lisphp version 2.0\n";
	while(true) {
		try {
			if($prompt) {
				print $prompt;
			}
			$x = parse($inport);
			if(S::eof_object($x)) {
				return;
			}
			$val = _eval($x);
			if($val != None && $out) {
				$out->print(to_string($val));
			}
		} catch (\Exception $e) {
			print $e->getMessage() . "\n";
		}
	}
}

################ Environment class

class Env {
	//An environment: a dict of {'var':val} pairs, with an outer Env.
	public $values;
	public $outer;
	
	public function __construct($parms = array(), $args = array(), $outer = false) {
		$this->values = array();
		$this->outer = $outer;
		if(isa($parms, Symbol)) {
			$this->setAt($parms, $args);
		} else {
			if(count($args) != count($parms)) {
				throw new TypeError('Expected ' . to_string($parms) . ', given ' . to_string($args));
			}
			if(!empty($parms)) {
				$this->update(array_combine($parms, $args));
			}
		}	
	}
	
	public function __toString() {
		$result = array();
		foreach($this->values as $key => $val) {
			$result[] = array($key, to_string($val) . "\n");
		}
		return to_string($result);
	}
	
	public function update($values) {
		foreach($values as $var => $val) {
			$this->setAt($var, $val);
		}
		return $this;
	}
		
	public function find($var) {
		if(isset($this->values[(string)$var])) {
			return $this;
		} else if(!$this->outer) {
			throw new LookupError("Looking for: $var in " . json_encode(array_keys($this->values)));
		} else {
			return $this->outer->find($var);
		}
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
		if(isset($this->outer->values[(string)$var])) {
			$this->outer->values[(string)$var] = $val;
		} else {
			$this->values[(string)$var] = $val;
		}
	}
}

function is_pair($x) { 
	return !empty($x) && is_array($x);
}

function cons($x, $y) {
	return array_merge(array($x), $y);
}

function callcc($proc) {
	#Call proc with current continuation; escape only
	$ball = new RuntimeWarning('Sorry, can\'t continue this continuation any longer.');
	$throw = function($retval) use(&$ball) { $ball->retval = $retval; throw $ball; };
	try {
		return $proc($throw);
	} catch(RuntimeWarning $w) {
		if($w == $ball) {
			return $ball->retval;
		} else {
			throw $w;
		}
	}
}

function add_globals($env) {
	#add some Scheme standard procedures.
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
		'sqrt' => function($x) { return sqrt($x); },
		'equal?' => function($x, $y) { return $x == $y; },
		'eq?' => function($x, $y) {	return $x === $y; },
		'length' => function($x) { return count($x); }, 
		'cons' => function($x, $y) { return array_merge(array($x), $y); },
		'car' => function($x) { return $x[0]; },
		'cdr' => function($x) { return array_slice($x, 1); },
		'append' => function($x,$y) { return array_merge($x, $y); },
		'list' => function() { return func_get_args(); },
		'list?' => function($x) { return is_array($x); },
		'null?' => function($x) { return $x == array(); },
		'symbol?' => function($x) { return isa($x, Symbol); },
		'boolean?' => function($x) { return is_boolean($x); },
		'pair?' => function($x) { return is_pair($x); },
		'port?' => function($x) { return isa($x, File); },
		'apply' => function() { $args = func_get_args(); return call_user_func_array(array_shift($args), $args); },
		'eval' => function($x) { return _eval(expand($x)); },
		'load' => function($fn) { return load($fn); },
		'call/cc' => function($x) { return callcc($x);},
		'open-input-file' => function($file) { return open($file); },
		'close-input-port' => function($p) { return $p->file->close(); },
		'open-output-file' => function($f) { return $f->open($f, 'w'); },
		'close-output-port' => function($p) { return $p->close(); },
		'eof-object?' => function($x) { return S::eof_object($x); },
		'read-char' => function() { return readchar(); },
		'read' => function() { return read(); }, 
		'write' => function($x, $port) { return $port->write(to_string($x)); },
		'display' => function($x, $port) { return $port->write(is_string($x) ? $x : to_string($x)); }
	));
}

function isa($x, $type) {
	return $type == _list ? is_array($x) : (is_object($x) && get_class($x) == $type);
}

function global_env() {
	static $env;
	$env || $env = add_globals(new Env());
	return $env;
}

################ eval (tail recursive)

function _eval($x, $env = false) {
	//Evaluate an expression in an environment.
	$env || $env = global_env();
	while(true) {
		if(isa($x, Symbol)) {                # variable reference
			return $env->find($x)->at($x);
		} elseif(!isa($x, _list)) {         # constant literal
			return $x;
		} elseif(S::quote($x[0])) {         # (quote exp)
			list($_, $exp) = $x;
			return $exp;
		} elseif(S::_if($x[0])) {            # (if test conseq alt)
			list($_, $test, $conseq, $alt) = $x;
			return _eval((_eval($test, $env) ? $conseq : $alt), $env);
		} elseif(S::set($x[0])) {          # (set! var exp)
			list($_, $var, $exp) = $x;
			$env->find($var)->setAt($var, _eval($exp, $env));
			return None;
		} elseif(S::define($x[0])) {        # (define var exp)
			list($_, $var, $exp) = $x;
			$env->setAt($var, _eval($exp, $env));
			return None;
		} elseif(S::lambda($x[0])) {        # (lambda (var*) exp)
			list($_, $vars, $exp) = $x;
			return new Procedure($vars, $exp, $env);
		} elseif(S::begin($x[0])) {         # (begin exp*)
			foreach(array_slice($x, 1) as $exp) {
				$val = _eval($exp, $env);
			}
			return $val;
		} else {                             # (proc exp*)
			$exps = array_map(function($exp) use(&$env) { return _eval($exp, $env); }, $x);
			$proc = array_shift($exps);
			if(isa($proc, Procedure)) {
				$x = $proc->exp;
				//echo "PROC:" . to_string($x) . "\n";
				//echo "ENVR:" . to_string($proc->parms) . "\n";
				//echo "EXPS:" . to_string($exps) . "\n";
				$env = new Env($proc->parms, $exps, $proc->env);
			} else {
				return call_user_func_array($proc, $exps);
			}
		}
	}
}

################ expand

function expand($x, $toplevel = false) {
	#Walk tree of x, making optimizations/fixes, and signaling SyntaxError.
	demand($x, $x == 0 || !empty($x));
	if(!isa($x, _list)) {
		return $x;
	} elseif(S::quote($x[0])) {
		demand($x, count($x) == 2);
		return $x;
	} elseif(S::_if($x[0])) {
		if(count($x) == 3) {
			$x[] = None;
		}
		demand($x, count($x) == 4);
		return array_map(expand, $x);
	} elseif(S::set($x[0])) {
		demand($x, count($x) == 3);
		$var = $x[1];
		demand($x, isa($var, Symbol), 'can set! only a symbol');
		return array(S::set(), $var, expand($x[2]));
	} elseif(S::define($x[0]) || S::define_macro($x[0])) {
		demand($x, count($x) >= 3);
		list($def, $v) = $x; 
		$body = array_slice($x, 2);
		if(isa($v, _list) && $v) {
			$f = $v[0];
			$args = array_slice($v, 1);
			return expand(array(S::define(), $f, array_merge(array(S::lambda(), $args), $body)));
		} else {
			demand($x, count($x) == 3);
			demand($x, isa($v, Symbol), 'can define only a symbol');
			$exp = expand($x[2]);
			if(S::define_macro($def)) {
				demand($x, $toplevel, "define-macro only allowed at top level");
				$proc = _eval($exp);
				demand($x, is_callable($proc), "macro must be a procedure");
				macro_table($v, $proc);
				return None;
			} else {
				return array(S::define(), $v, $exp);
			}
		}
	} elseif(S::begin($x[0])) {
		if(count($x) == 1) {
			return None;
		} else {
			return array_map(function($xi) use(&$toplevel) { 
				return expand($xi, $toplevel);
			}, $x);
		}
	} elseif(S::lambda($x[0])) {
		demand($x, count($x) >= 3);
		$vars = $x[1];
		$body = array_slice($x, 2);
		demand($x, (isa($vars, _list) && all(function($v) { return isa($v, Symbol); }, $vars)) 
					|| isa($vars, Symbol), 'illegal lambda argument list');
		$exp = count($body) == 1 ? $body[0] : array_merge(array(S::begin()), $body);
		return array(S::lambda(), $vars, expand($exp));
	} elseif(S::quasiquote($x[0])) {
		demand($x, count($x) == 2);
		return expand_quasiquote($x[1]);
	} elseif(isa($x[0], Symbol) && macro_table($x[0])) {
		return expand(call_user_func_array(macro_table($x[0]), array_slice($x, 1)), $toplevel);
	} else {
		return array_map(expand, $x);
	}
}

function all($callback, $predicates) {
	foreach($predicates as $predicate) {
		if(!$callback($predicate)) {
			return false;
		}
	}
	return true;
}

function demand($x, $predicate, $msg='wrong length') {
	#Signal a syntax error if predicate is false.
	if(!$predicate) {
		throw new SyntaxError(to_string($x) . ': ' . $msg);
	}
}

function atIndex($arr, $index) {
	return $arr[$index];
}

function expand_quasiquote($x) {
	#Expand `x => 'x; `,x => x; `(,@x y) => (append x y).
	if(!is_pair($x)) {
		return array(S::quote(), $x);
	}
	demand($x, !S::unquote_splicing($x[0]), "can't splice here");
	if(S::unquote($x[0])) {
		demand($x, count($x) == 2);
		return $x[1];
	} elseif(is_pair($x[0]) && S::unquote_splicing($x[0][0])) {
		demand($x[0], count($x[0]) == 2);
		return array(S::append(), $x[0][1], expand_quasiquote(array_slice($x, 1)));
	} else {
		return S::quasiquote($x[0]) ? expand_quasiquote(atIndex(expand_quasiquote(array_slice($x, 1)), 1)) 
			   : 
			   array(S::cons(), expand_quasiquote($x[0]), expand_quasiquote(array_slice($x, 1)));
	}
}

function _unzip($array) {
	$a = array();
	$b = array();
	foreach($array as $x) {
		$a[] = $x[0];
		$b[] = $x[1];
	}
	return array($a, $b);
}

function macro_table($key, $val = null) {
	static $table;
	$table || $table = array();
	
	if(is_null($val)) {
		return isset($table[(string)$key]) ? $table[(string)$key] : false;
	} else {
		S::addSymbol((string)$key);
		$table[(string)$key] = $val;
		return $val;
	}
}

function let() {
	$args = func_get_args();
	$x = cons(S::let(), $args);
	demand($x, count($args) > 1);
	$bindings = $args[0];
	$body = array_slice($args, 1);
	demand($x, all(function($b) { return isa($b, _list) && count($b) == 2 && isa($b[0], Symbol); }, $bindings), 'illegal binding list');
	list($vars, $vals) = _unzip($bindings);	
	$ast = array_map(expand, $vals);
	array_unshift($ast, array_merge(array(S::lambda(), $vars), array_map(expand, $body)));
	return $ast;
}

macro_table('let', 'lisp\let');

_eval(parse("(define-macro and (lambda args 
   (if (null? args) #t
       (if (= (length args) 1) (car args)
           `(if ,(car args) (and ,@(cdr args)) #f)))))"));