<?php

/**
 * A simple lexer for Mildred. Looks through a template file, and 
 * creates a list of all the template tokens it finds. 
 * 
 * @author JT Paasch
 * @copyright 2012
 */
class Lexer {
    
    /**
     * A list of terminals and patterns to match.
     * @var array 
     */
    private static $terminals = array(
        '@^{{ ([^}]+) }}@' => 'T_VARIABLE',
        '@^{% if (.*?) %}@' => 'T_IF_START',
        '@^{% endif %}@' => 'T_IF_END',
        '@^{% foreach (.*?) in (.*?) %}@' => 'T_FOREACH_START',
        '@^{% endforeach %}@' => 'T_FOREACH_END',
    );
    
    /**
     * All matched tokens will be stored here.
     * @var array 
     */
    private static $tokens = array();
    
    /**
     * Each line of the source text will be stored here.
     * @var array 
     */
    private static $lines = array();
    
    /**
     * A pointer to the line number for the line the Lexer is analyzing.
     * @var int 
     */
    private static $line = 0;
    
    /**
     * A pointer to the character number/position the Lexer is analyzing.
     * @var type 
     */
    private static $cursor = 0;
    
    /**
     * Analyzes a source file for terminal matches and returns 
     * a list of tokens/matches.
     * @param string $source The source text to be analyzed.
     * @return array The tokens/matches. 
     */
    public static function analyze($source) {
        self::reset();
        self::$lines = self::split_into_lines($source);
        foreach (self::$lines as $line_number => $line_content) {
            self::$line = $line_number;
            foreach (self::$terminals as $pattern => $token) {
                self::in_line($pattern, $token);
            }
        }
        return self::$tokens;
    }
    
    /**
     * Reset all internal class values.
     */
    public static function reset() {
        self::$tokens = array();
        self::$lines = array();
        self::$line = 0;
        self::$cursor = 0;
    }
    
    /**
     * Split a string into lines.
     * @param string $source The source to split into lines.
     * @return array The source, broken up into lines.
     */
    private static function split_into_lines($source) {
        return preg_split('@\n|\r\n|\r@', $source);
    }
    
    /**
     * Add a token to the internal self::$tokens list.
     * @param array $token The token to add.
     */
    private static function add_to_tokens($token) {
        self::$tokens[] = $token;
    }
    
    /**
     * Moves the cursor forward the length of the specified string.
     * @param string $string A string the length of which to move the cursor.
     */
    private static function advance_cursor($string) {
        self::$cursor += strlen($string);
    }
    
    /**
     * Moves the internal line pointer to the next line. 
     */
    private static function next_line() {
        self::$line += 1;
    }
    
    /**
     * Match a regex pattern in a string.
     * @param string $pattern The regex pattern to match.
     * @param string $string The string to find the pattern in.
     * @return array All matches.
     */
    private static function match($pattern, $string) {
        $matches = array();
        preg_match($pattern, $string, $matches);
        return $matches;
    }
    
    /**
     * Find all tokens in a line. The cursor starts at the beginning of 
     * the line then moves forward to the end looking for matches.
     * @param string $pattern The pattern to find in the line.
     * @param string $token The terminal name of the pattern.
     */
    private static function in_line($pattern, $token) {
        self::$cursor = 0;
        $length = strlen(self::$lines[self::$line]);
        while (self::$cursor < $length) {
            self::in_string($pattern, $token);
        }
    }
    
    /**
     * Find all tokens in a string. If a match is found,
     * the cursor is moved to the end of the match. Otherwise, 
     * the cursor is moved to the next character.
     * @param string $pattern The pattern to find in the string.
     * @param string $token The terminal name of the pattern.
     */
    private static function in_string($pattern, $token) {
        $string = substr(self::$lines[self::$line], self::$cursor);
        $result = self::match($pattern, $string);
        if ($result) {
            self::add_to_tokens(array(
                'token' => $token,
                'match' => $result,
                'line' => self::$line,
                'position' => self::$cursor,
            ));
            self::advance_cursor($result[0]);
        } else {
            self::$cursor += 1;
        }
    }
    
}