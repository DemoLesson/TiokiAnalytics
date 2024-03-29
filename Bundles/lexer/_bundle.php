<?php

namespace bundles\lexer;
use Exception;
use InvalidArgumentException;
use LogicException;
use e;

/**
 * Text processing lexer class
 */

class Bundle {
	public function __getBundle() {
		return new Lexer;
	}
}

class Lexer {
		
	private $grammar;
	
	private $initialToken;
	
	private $source;
	
	private $file;

	private $description = '{unknown}';
	
	/**
	 * Define grammar
	 */
	public function grammar($grammar, $initialToken = 'default') {
		
		// Ensure grammar is an array
		if(!is_array($grammar))
			throw new InvalidArgumentException("Lexer grammar must be defined in an array");
			
		// Ensure initialToken is a string
		if(!is_string($initialToken))
			throw new InvalidArgumentException("Lexer initial token name must be a string");
		
		// Store the grammar and initialToken
		$this->grammar = $grammar;
		$this->initialToken = $initialToken;
		
		// Allow chaining
		return $this;
	}

	/**
	 * Set description
	 * @author Nate Ferrero
	 */
	public function description($description) {
		$this->description = $description;
		return $this;
	}
	
	/**
	 * Set source
	 */
	public function sourceString($source) {
		
		// Ensure source is an string
		if(!is_string($source))
			throw new InvalidArgumentException("Lexer source string must be a string");
		
		// Store the file and source
		$this->file = '{String}';
		$this->source = $source;
		
		// Allow chaining
		return $this;
	}
	
	/**
	 * Load source from a file
	 */
	public function sourceFile($file) {
		
		// Ensure source is a string
		if(!is_file($file))
			throw new InvalidArgumentException("Lexer source file `$file` does not exist");
		
		// Store the file and source
		$this->file = realpath($file);
		$this->source = file_get_contents($file);
		
		// Allow chaining
		return $this;
	}
	
	/**
	 * Get File
	 */
	public function getFile() {
		return $this->file;
	}
	
	/**
	 * Get the tokens for the loaded configuration
	 */
	public function tokenize($token = 'default') {
		
		if(is_null($this->grammar))
			throw new LogicException("Lexer grammar must be loaded before using `tokenize`");
			
		if(is_null($this->source))
			throw new LogicException("Lexer source must be loaded before using `tokenize`");
		
		// Reset line number
		$lineNumber = 1;
		$colNumber = 0;
		
		// Token start positions
		$tokenLine = 1;
		$tokenCol = 0;
		
		// Go through the code one char at a time, starting with default token
		$length = strlen($this->source);
		$tokens = array();
		$queue = '';
		$processImmediately = false;
		for($pointer = 0; $pointer <= $length; true) {
			
			// Check if processing a forwarded $char
			if($processImmediately) {
				
				// Shut off process flag
				$processImmediately = false;
			}
			
			// Else get a new $char
			else {
				
				// Get char at pointer
				$char = substr($this->source, $pointer, 1);
				
				// Step ahead after we have the char
				$pointer++;
				
				// Increment line count
				if($char == "\n" || $char == "\r") {
					$lineNumber++;
					$colNumber = -1;
				}
				
				// Increment column count
				$colNumber++;
			}
			
			// Check that the current token is defined
			if(!isset($this->grammar[$token]))
				throw new LexerSyntaxException("Grammar Error: Undefined token 
					`<i>$token</i>` on line `$tokenLine` at column `$tokenCol` in `$this->description`");
			
			// Use the token
			$xtoken = $this->grammar[$token];
			
			// Check for special token types
			if(isset($xtoken['type'])) {
				switch($xtoken['type']) {
					
					// Check if the token is conditional, which means that there's a choice of
					// which token rules to follow, depending on the conditions specified.
					case 'conditional':
						
						// Loop through all possible conditions
						foreach($xtoken as $key => $condtoken) {
							
							// Skip the type
							if($key === 'type')
								continue;
						
							if(!isset($condtoken['match-sequence'])) {
								$xtoken = $condtoken;
								break 2;
							}
							$seq = $condtoken['match-sequence'];
							$index = count($tokens) - 1;
							
							
							/* DEBUG * /
								echo "<h2>Checking <code>$token</code> conditional token</h2>";
							/* END DEBUG */
							
							$first = true;
							$scan = false;
							$startIndex = 0;
							foreach($condtoken['match-sequence'] as $match_token => $match_value) {
								
								if($first || $scan) {
									while($tokens[$index]->name != $match_token) {
										$index--;
										if($first)
											$startIndex = $index;
										if($index < $startIndex)
											break 2;
									}
									$first = false;
									$scan = false;
								}
								
								$actual_token = $tokens[$index]->name;
								$actual_value = $tokens[$index]->value;
								
								if($match_token == '*') {
									$scan = true;
									$index = count($tokens);
									continue;
								}
								
								/* DEBUG * /
									echo "<p>Comparing match token <strong>$match_token</strong> with actual <strong>$actual_token</strong>
									and expected value <strong>$match_value</strong> with <strong>$actual_value</strong></p>";
								/* END DEBUG */
								
								if($actual_token != $match_token || $actual_value != $match_value)
									continue 2;
								$index++;
							}
							
							/**
							 * The condition token is matched
							 */
							if(isset($condtoken['token'])) {
								switch($condtoken['token']) {
									case 'cdata-block':
										
										/**
										 * Jump to end of block
										 */
										$token = $condtoken['token'];
										$start = $pointer;
										$pointer = strpos($this->source, $condtoken['end'], $start);
										if($pointer === false)
											$pointer = strlen($this->source);
										$char .= substr($this->source, $start, $pointer - $start);
										$len = strlen($char);
										for($i = 0; $i < $len; $i++) {
											$cx = substr($char, $i, 1);
											
											// Increment line count
											if($cx == "\n" || $cx == "\r") {
												$lineNumber++;
												$colNumber = -1;
											}
											
											// Increment column count
											$colNumber++;
										}
										break 3;
								}
							}
						}
						
						// If no conditional match found, throw exception
						throw new Exception("Tokenize Error: The tokenizer has encountered a conditional token `<i>$token</i>` ".
							"that has no valid match in `$this->description`");
						
					default:
						throw new Exception("Tokenize Error: The tokenizer has encountered an invalid token type `<i>$xtoken[type]`
							after token `<i>$token</i>` in `$this->description`");
				
				}
			}
			
			/**
			 * Handle last token
			 */
			if($char === false) {
				$tokens[] = (object) array('name' => $token, 'value' => $queue,
					'line' => $tokenLine, 'col' => $tokenCol);
					
				break;
			}
			
			// Whether to check for the ' ' space token, matches all whitespace
			if($char === "\n" || $char === "\r" || $char === "\t")
				$checkchar = ' ';
			else
				$checkchar = $char;
			
			// Check if the current token has an action for this char, both literal and *
			$literal = isset($xtoken[$checkchar]);
			$star = isset($xtoken['*']);
			$xmatch = false;
			$xqueue = '';

			// Extended cases
			if(isset($xtoken['extended'])) {
				
				// Save pointer to reset
				$oldPointer = $pointer;

				foreach($xtoken['extended'] as $match => $qtoken) {
					$sample = '';
					while($sample !== false) {
						$sample = substr($this->source, ++$pointer, strlen($match));
						if($sample == $match) {

							/**
							 * Add the content before the match
							 * @author Nate Ferrero
							 */
							$queue .= substr($this->source, $oldPointer, $pointer - $oldPointer);

							$xmatch = true;
							$ntoken = $qtoken;
							$xqueue = substr($this->source, $pointer + 1, strlen($match) - 1);
							$pointer += strlen($match);

							break 2;
						}
					}

					// Reset if nothing found
					$pointer = $oldPointer;
				}
			}
			
			// If no extended match, use normal matching
			if(!$xmatch) {

				// If no match, char is part of token and continue
				if(!$literal && !$star) {
					$queue .= $char;
					continue;
				}
				
				// Load the next token
				$ntoken = $xtoken[$literal ? $checkchar : '*'];
			}

			// Handle '#drop' token
			if($ntoken === '#drop') {
				continue;	
			}
			
			// Handle '#self' token
			if($ntoken === '#self') {
				$queue .= $char;
				continue;	
			}
			
			// Handle '#error' token
			if($ntoken === '#error') {
				throw new LexerSyntaxException("Syntax Error: Unexpected <code><b>'$char'</b></code>
					after `<i>$token</i>` token `$queue` on line $lineNumber at column $colNumber in `$this->description`");
			}
			
			// Add the current token to the stack and handle queue
			$tokens[] = (object) array('name' => $token, 'value' => $queue,
				'line' => $tokenLine, 'col' => $tokenCol);
			
			// Update line and column for next token
			$tokenLine = $lineNumber;
			$tokenCol = $colNumber;
			
			// Handle &tokens by immediately queueing the same char on the new token
			if(substr($ntoken, 0, 1) === '&') {
				$token = substr($ntoken, 1);
				$processImmediately = true;
				$queue = '';
			}
			
			// Normal tokens will start queue on next char
			else {
				$token = $ntoken;

				// xqueue adds any special characters after the first character of the token (see above)
				$queue = $char . $xqueue;
			}
		}
		
		// Return tokens
		return $tokens;
	}
	
	/**
	 * Record if style sent
	 */
	private $debugStyleSent;
	
	/**
	 * Debug tokens
	 */
	public function debugHTML() {
		$o = '';
		if(!$this->debugStyleSent)
			echo '<style>'.
				file_get_contents(__DIR__ . '/css/lexer-debug-theme.css').
				'</style><div class="tokens">';
		$this->debugStyleSent = true;
		$tokens = $this->tokenize();
		$i = 0;
		foreach($tokens as $token) {
			$class = $token->name;
			$v = htmlspecialchars($token->value);
			switch($v) {
				case '':
					$v = '&empty;';
					$class .= " blank";
					break;
			}
			$v = str_replace(" ", '&nbsp;', $v);
			$v = str_replace("\n", '<b class="newline">&crarr;</b></div><div class="clear">', $v);
			$v = str_replace("\r", '<b class="newline">&crarr;</b></div><div class="clear">', $v);
			$v = str_replace("\t", '<b class="tab">&raquo;</b>', $v);
			$i++;
			$pos = $i % 6 + 1;
			$o .= "<div class='$class'><span class='x$pos'>$token->name</span>$v</div>";
		}
		
		$o .= '</div>';
		return $o;
	}
}
	
/**
 * Lexer Syntax Exception
 */
class LexerSyntaxException extends Exception {}