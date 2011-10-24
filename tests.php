<?php
################ Tests for lis.php
namespace Scphpeme;
include 'lis.php';

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
	array("(fact 10)", 3628800),
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
	array("(riff-shuffle (riff-shuffle (riff-shuffle (list 1 2 3 4 5 6 7 8))))", array(1,2,3,4,5,6,7,8))
);

function test($tests, $name=''){
	global $global_env;
    //For each (exp, expected) test case, see if eval(parse(exp)) == expected.
    $fails = 0;
    foreach($tests as $test) { 
		list($x, $expected) = $test;
        try {
            $result = _eval(parse($x));
            print $x . '=>' . to_string($result) . "\n";
            $ok = $result == $expected;
		}
        catch(\Exception $e) {
            print $x . '=raises=>' . $e->getMessage() . "\n";
            $ok = false;
		}
		
        if(!$ok) {
            $fails += 1;
            print 'FAIL!!!  Expected: ' . to_string($expected) . "\n";
		}
	}
    printf('%s %s: %d out of %d tests fail.', '*'*45, $name, $fails, count($tests)) . "\n";
}

test($lis_tests, 'lis.php');