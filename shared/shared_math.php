<?php
/**
 * Arithmetic for {calculate___...} placeholders.
 *
 * The text inside a {calculate___...} placeholder can originate in an HTTP
 * request (commandvalue, macro step values), so it must never be handed to
 * PHP's compiler. This evaluator accepts only:
 *
 *   numbers          1  2.5  .5  1e3
 *   operators        + - * / %
 *   grouping         ( )
 *   functions        round floor ceil abs sqrt pow min max intval rand mt_rand
 *
 * Anything else -- identifiers, quotes, semicolons, backticks, $variables --
 * makes the whole expression unacceptable. calculateExpressionValue() then
 * returns null and the caller leaves the placeholder untouched.
 */

/**
 * @param string $expression
 * @return string|null Numeric result, or null if the expression is not
 *                     acceptable arithmetic.
 */
function calculateExpressionValue($expression)
{
	if (!is_string($expression)) {
		return null;
	}

	$expression = trim($expression);
	$length = strlen($expression);
	if ($length === 0 || $length > 400) {
		return null;
	}

	$state = array('text' => $expression, 'pos' => 0, 'len' => $length);
	$value = calculateParseSum($state);
	if ($value === null) {
		return null;
	}

	calculateSkipSpace($state);
	if ($state['pos'] !== $state['len']) {
		return null;		// trailing garbage
	}

	return calculateFormatNumber($value);
}

/**
 * sum := product (('+' | '-') product)*
 *
 * @return float|null
 */
function calculateParseSum(&$state)
{
	$value = calculateParseProduct($state);
	if ($value === null) {
		return null;
	}

	while (true) {
		calculateSkipSpace($state);
		if ($state['pos'] >= $state['len']) {
			return $value;
		}
		$op = $state['text'][$state['pos']];
		if ($op !== '+' && $op !== '-') {
			return $value;
		}

		$state['pos']++;
		$right = calculateParseProduct($state);
		if ($right === null) {
			return null;
		}
		$value = ($op === '+') ? $value + $right : $value - $right;
	}
}

/**
 * product := unary (('*' | '/' | '%') unary)*
 *
 * @return float|null
 */
function calculateParseProduct(&$state)
{
	$value = calculateParseUnary($state);
	if ($value === null) {
		return null;
	}

	while (true) {
		calculateSkipSpace($state);
		if ($state['pos'] >= $state['len']) {
			return $value;
		}
		$op = $state['text'][$state['pos']];
		if ($op !== '*' && $op !== '/' && $op !== '%') {
			return $value;
		}

		$state['pos']++;
		$right = calculateParseUnary($state);
		if ($right === null) {
			return null;
		}

		if ($op === '*') {
			$value = $value * $right;
		} elseif ($op === '/') {
			if ($right == 0) {
				return null;
			}
			$value = $value / $right;
		} else {
			if ((int)$right === 0) {
				return null;
			}
			$value = (int)$value % (int)$right;
		}
	}
}

/**
 * unary := ('+' | '-') unary | primary
 *
 * @return float|null
 */
function calculateParseUnary(&$state)
{
	calculateSkipSpace($state);
	if ($state['pos'] < $state['len']) {
		$ch = $state['text'][$state['pos']];
		if ($ch === '-') {
			$state['pos']++;
			$inner = calculateParseUnary($state);
			return ($inner === null) ? null : -$inner;
		}
		if ($ch === '+') {
			$state['pos']++;
			return calculateParseUnary($state);
		}
	}
	return calculateParsePrimary($state);
}

/**
 * primary := number | '(' sum ')' | function '(' args ')'
 *
 * @return float|null
 */
function calculateParsePrimary(&$state)
{
	calculateSkipSpace($state);
	if ($state['pos'] >= $state['len']) {
		return null;
	}

	$ch = $state['text'][$state['pos']];

	if ($ch === '(') {
		$state['pos']++;
		$value = calculateParseSum($state);
		if ($value === null) {
			return null;
		}
		calculateSkipSpace($state);
		if ($state['pos'] >= $state['len'] || $state['text'][$state['pos']] !== ')') {
			return null;
		}
		$state['pos']++;
		return $value;
	}

	if (preg_match('/\G(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?/', $state['text'], $matches, 0, $state['pos'])) {
		$state['pos'] += strlen($matches[0]);
		return (float)$matches[0];
	}

	if (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $state['text'], $matches, 0, $state['pos'])) {
		$name = strtolower($matches[0]);
		$state['pos'] += strlen($matches[0]);
		return calculateCallFunction($state, $name);
	}

	return null;
}

/**
 * @param array  $state
 * @param string $name Lower-cased function name
 * @return float|null
 */
function calculateCallFunction(&$state, $name)
{
	$known = array('round', 'floor', 'ceil', 'abs', 'sqrt', 'pow', 'min', 'max', 'intval', 'rand', 'mt_rand');
	if (!in_array($name, $known, true)) {
		return null;
	}

	calculateSkipSpace($state);
	if ($state['pos'] >= $state['len'] || $state['text'][$state['pos']] !== '(') {
		return null;
	}
	$state['pos']++;

	$args = array();
	calculateSkipSpace($state);
	if ($state['pos'] < $state['len'] && $state['text'][$state['pos']] === ')') {
		$state['pos']++;
	} else {
		while (true) {
			$arg = calculateParseSum($state);
			if ($arg === null) {
				return null;
			}
			$args[] = $arg;
			if (count($args) > 4) {
				return null;
			}
			calculateSkipSpace($state);
			if ($state['pos'] >= $state['len']) {
				return null;
			}
			$ch = $state['text'][$state['pos']];
			$state['pos']++;
			if ($ch === ')') {
				break;
			}
			if ($ch !== ',') {
				return null;
			}
		}
	}

	switch ($name) {
	case 'round':
		if (count($args) < 1 || count($args) > 2) return null;
		return round($args[0], isset($args[1]) ? (int)$args[1] : 0);
	case 'floor':
		return (count($args) === 1) ? floor($args[0]) : null;
	case 'ceil':
		return (count($args) === 1) ? ceil($args[0]) : null;
	case 'abs':
		return (count($args) === 1) ? abs($args[0]) : null;
	case 'sqrt':
		return (count($args) === 1 && $args[0] >= 0) ? sqrt($args[0]) : null;
	case 'intval':
		return (count($args) === 1) ? (int)$args[0] : null;
	case 'pow':
		return (count($args) === 2) ? pow($args[0], $args[1]) : null;
	case 'rand':
	case 'mt_rand':
		// rand() and rand(min, max). Pure functions: no side effects, so they are
		// safe to expose here even though the expression can come from a request.
		if (count($args) === 0) {
			return ($name === 'rand') ? rand() : mt_rand();
		}
		if (count($args) === 2) {
			return ($name === 'rand') ? rand((int)$args[0], (int)$args[1]) : mt_rand((int)$args[0], (int)$args[1]);
		}
		return null;
	case 'min':
		return (count($args) >= 1) ? min($args) : null;
	case 'max':
		return (count($args) >= 1) ? max($args) : null;
	}
	return null;
}

function calculateSkipSpace(&$state)
{
	while ($state['pos'] < $state['len']) {
		$ch = $state['text'][$state['pos']];
		if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r") {
			$state['pos']++;
		} else {
			break;
		}
	}
}

/**
 * @param float $value
 * @return string|null
 */
function calculateFormatNumber($value)
{
	$value = (float)$value;
	if (!is_finite($value)) {
		return null;
	}
	if (floor($value) == $value && abs($value) < 1.0e15) {
		return (string)(int)$value;
	}
	return (string)$value;
}
