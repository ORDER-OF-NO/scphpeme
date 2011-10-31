<?php
################ Tests for lis.php
namespace lisp;
include 'lisp.php';

$lis_tests = array(
	array("(quote (testing 1 (2.0) -3.14e159))", array('testing', 1, array(2.0), -3.14e159)),
	array("(+ 2 2)", 4),
	array("(+ (* 2 100) (* 1 10))", 210),
	array("(if (> 6 5) (+ 1 1) (+ 2 2))", 2),
	array("(if (< 6 5) (+ 1 1) (+ 2 2))", 4),
	array("(define x 3)", None),
	array("x", 3), 
	array("(+ x x)", 6),
	array("(begin (define x 1) (set! x (+ x 1)) (+ x 1))", 3),
	array("((lambda (x) (+ x x)) 5)", 10),
	array("(define twice (lambda (x) (* 2 x)))", None), 
	array("(twice 5)", 10),
	array("(define compose (lambda (f g) (lambda (x) (f (g x)))))", None),
	array("((compose list twice) 5)", array(10)),
	array("(define repeat (lambda (f) (compose f f)))", None),
	array("((repeat twice) 5)", 20), 
	array("((repeat (repeat twice)) 5)", 80),
	array("(define fact (lambda (n) (if (<= n 1) 1 (* n (fact (- n 1))))))", None),
	array("(fact 3)", 6),
	array("(fact 50)", 30414093201713378043612608166064768844377641568960512000000000000),
	array("(define abs (lambda (n) ((if (> n 0) + -) 0 n)))", None),
	array("(list (abs -3) (abs 0) (abs 3))", array(3, 0, 3)),
	array("(define combine (lambda (f)
	(lambda (x y)
	  (if (null? x) (quote ())
	      (f (list (car x) (car y))
	         ((combine f) (cdr x) (cdr y)))))))", None),
	array("(define zip (combine cons))", None),
	array("(zip (list 1 2 3 4) (list 5 6 7 8))", array(array(1, 5), array(2, 6), array(3, 7), array(4, 8))),
	array("(define riff-shuffle (lambda (deck) (begin
		(define take (lambda (n seq) (if (<= n 0) (quote ()) (cons (car seq) (take (- n 1) (cdr seq))))))
		(define drop (lambda (n seq) (if (<= n 0) seq (drop (- n 1) (cdr seq)))))
		(define mid (lambda (seq) (/ (length seq) 2)))
		((combine append) (take (mid deck) deck) (drop (mid deck) deck)))))", None),
	array("(riff-shuffle (list 1 2 3 4 5 6 7 8))", array(1, 5, 2, 6, 3, 7, 4, 8)),
	array("((repeat riff-shuffle) (list 1 2 3 4 5 6 7 8))",  array(1, 3, 5, 7, 2, 4, 6, 8)),
	array("(riff-shuffle (riff-shuffle (riff-shuffle (list 1 2 3 4 5 6 7 8))))", array(1,2,3,4,5,6,7,8)),
	
	array("()", SyntaxError), 
	array("(set! x)", SyntaxError), 
	array("(define 3 4)", SyntaxError),
	array("(quote 1 2)", SyntaxError), 
	array("(if 1 2 3 4)", SyntaxError), 
	array("(lambda 3 3)", SyntaxError), 
	array("(lambda (x))", SyntaxError),
	array("(if (= 1 2) (define-macro a 'a) 
	(define-macro a 'b))", SyntaxError),
	array("(define (twice x) (* 2 x))", None), 
	array("(twice 2)", 4),
	array("(twice 2 2)", TypeError),
	array("(define lyst (lambda items items))", None),
	array("(lyst 1 2 3 (+ 2 2))", array(1,2,3,4)),
	array("(if 1 2)", 2),
	array("(if (= 3 4) 2)", None),
	array("(define ((account bal) amt) (set! bal (+ bal amt)) bal)", None),
	array("(define a1 (account 100))", None),
	array("(a1 0)", 100), 
	array("(a1 10)", 110), 
	array("(a1 10)", 120),
	array("(define (newton guess function derivative epsilon)
	(define guess2 (- guess (/ (function guess) (derivative guess))))
	(if (< (abs (- guess guess2)) epsilon) guess2
	   (newton guess2 function derivative epsilon)))", None),
	array("(define (square-root a)
	(newton 1 (lambda (x) (- (* x x) a)) (lambda (x) (* 2 x)) 1e-8))", None),
	array("(> (square-root 200.) 14.14213)", true),
	array("(< (square-root 200.) 14.14215)", true),
	array("(= (square-root 200.) (sqrt 200.))", true),
	array("(define (sum-squares-range start end)
	    (define (sumsq-acc start end acc)
	       (if (> start end) acc (sumsq-acc (+ start 1) end (+ (* start start) acc))))
	    (sumsq-acc start end 0))", None),
	array("(sum-squares-range 1 3000)", 9004500500), ## Tests tail recursion
	array("(call/cc (lambda (throw) (+ 5 (* 10 (throw 1))))) ;; throw", 1),
	array("(call/cc (lambda (throw) (+ 5 (* 10 1)))) ;; do not throw", 15),
	array("(call/cc (lambda (throw) 
	    (+ 5 (* 10 (call/cc (lambda (escape) (* 100 (escape 3)))))))) ; 1 level", 35),
	array("(call/cc (lambda (throw) 
	    (+ 5 (* 10 (call/cc (lambda (escape) (* 100 (throw 3)))))))) ; 2 levels", 3),
	array("(call/cc (lambda (throw) 
	    (+ 5 (* 10 (call/cc (lambda (escape) (* 100 1))))))) ; 0 levels", 1005),
	array("(let ((a 1) (b 2)) (+ a b))", 3),
	array("(let ((a 1) (b 2 3)) (+ a b))", SyntaxError),
	array("(and 1 2 3)", 3), 
	array("(and (> 2 1) 2 3)", 3), 
	array("(and)", true),
	array("(and (> 2 1) (> 2 3))", false),
	array("(define-macro unless (lambda args `(if (not ,(car args)) (begin ,@(cdr args))))) ; test `", None),
	array("(unless (= 2 (+ 1 1)) (display 2) 3 4)", None),
//	array('(unless (= 4 (+ 1 1)) (display 2) (display "\n") 3 4)', 4),
	array("(quote x)", 'x'), 
	array("(quote (1 2 three))", array(1, 2, 'three')), 
	array("'x", 'x'),
	array("'(one 2 3)", array('one', 2, 3)),
	array("(define L (list 1 2 3))", None),
	array("`(testing ,@L testing)", array('testing',1,2,3,'testing')),
	array("`(testing ,L testing)", array('testing',array(1,2,3),'testing')),
	array("`,@L", SyntaxError),
	array("'(1 ;test comments '
	;skip this line
	2 ; more ; comments ; ) )
	3) ; final comment", array(1,2,3)),
	array('(define-macro metamacro (lambda (name op)
	`(define-macro ,name (lambda args `(,,op ,@args)))))', None)
);

function test($tests, $name=''){
    #For each (exp, expected) test case, see if eval(parse(exp)) == expected.
    $fails = 0;
    foreach($tests as $test) { 
		list($x, $expected) = $test;
        try {
            $result = _eval(parse($x));
			$ok = $result == $expected;
		}
        catch(\Exception $e) {
			if($expected == TypeError && $e instanceof TypeError) {
				$result = 'TypeError';
				$ok  = true;
			} else if($expected == SyntaxError && $e instanceof SyntaxError) {
				$result = 'SyntaxError';
				$ok = true;
			} else {
            	print $x . '=raises=>' . $e->getMessage() . "\n";
            	$ok = false;
			}
		}
		
        if(!$ok) {
            $fails += 1;
            print 'FAIL!!!  Expected: ' . to_string($expected) . "\n";
			die();
		} else {
			print $x . '=>' . to_string($result) . "\n";
			print "PASS\n\n";
		}
		
	}
    printf('%s %s: %d out of %d tests fail.', '*'*45, $name, $fails, count($tests)) . "\n";
}

test($lis_tests, 'lisp.php');