<?php

/**
 * A simple parser for Mildred. It takes a list of template tokens 
 * from the Lexer, and it replaces each token in the template with 
 * the appropriate PHP code. 
 * 
 * @author JT Paasch
 * @copyright 2012
 */
class Parser {
    
    /**
     * Tokens are stored in this array.
     * @var array 
     */
    private static $tokens = array();
    
    /**
     * The source text, split up into lines.
     * @var array 
     */
    private static $lines = array();
    
    /**
     * The types (class names) to allow in the template.
     * @var array 
     */
    private static $types = array();
    
    /**
     * The variables to output in the parsed file.
     * @var array 
     */
    private static $variables = array();
    
    /**
     * The names of variables in foreach loops.
     * @var array 
     */
    private static $foreachloop_variables = array();
    
    /**
     * Catch and display errors about invalid variables?
     * @var boolean 
     */
    private static $debug = false;
    
    /**
     * Parse the specified source text with the given list of tokens.
     * @param string $source The text to parse.
     * @param array $tokens List of tokens to replace.
     * @param array $types List of types (class names) to allow in the template.
     * @param array $variables List of variables to use in the template.
     */
    public static function parse(
            $source, 
            $tokens, 
            $types=array(), 
            $variables=array(), 
            $debug=false) {
        
        // Store the tokens
        self::$tokens = $tokens;
        
        // Store the source, split into lines.
        self::$lines = self::split_into_lines($source);
        
        // Store the allowed types.
        self::$types = $types;
        
        // Store the variables.
        self::$variables = $variables;
        
        // Set the debug value.
        self::$debug = $debug;
        
        // Parse each token.
        for ($i = 0; $i < count(self::$tokens); $i++) {
            self::parse_token($i);
        }
  
        // Return all the lines, stuck together as a single string.
        return implode(PHP_EOL, self::$lines);
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
     * Shift the position for the remaining tokens in a line.
     * This is needed after a replacement, since the replacement 
     * might be longer or shorter than the original token.
     * @param int $index The index for the token to start shifting from.
     * @param int $line The line to shift tokens on.
     * @param int $offset The number of characters to shift.
     */
    private static function shift_positions($index, $line, $offset) {
        for ($i = $index; $i < count(self::$tokens); $i++) {
            if (self::$tokens[$i]['line'] === $line) {
                self::$tokens[$i]['position'] += $offset;
            } 
        }
    }
    
    /**
     * Replace a token with the parsed text.
     * @param int $index The index of the token.
     * @param array $token The token to replace.
     * @param string $replacement The text to replace the token with.
     */
    private static function replace($index, $token, $replacement) {
        self::$lines[$token['line']] = substr_replace(
                self::$lines[$token['line']], 
                $replacement, 
                $token['position'], 
                strlen($token['match'][0])
        );
        $offset = strlen($replacement) - strlen($token['match'][0]);
        self::shift_positions($index, $token['line'], $offset);
    }
    
    /**
     * Dispatches the parsing for a token.
     * @param int $index The index of the token.
     */
    private static function parse_token($index) {
        switch (self::$tokens[$index]['token']) {
            case ('T_VARIABLE'):
                self::parse_variable($index);
                break;
            case ('T_IF_START'):
                self::parse_if($index);
                break;
            case ('T_IF_END'):
                self::parse_endif($index);
                break;
            case ('T_FOREACH_START'):
                self::parse_foreach($index);
                break;
            case ('T_FOREACH_END'):
                self::parse_endforeach($index);
                break;
        }
    }
    
    /**
     * Checks to make sure the variable is one of 
     * the specified template variables. 
     * @param string $variable The name of the variable (from the template).
     * @return string PHP code for displaying the variable.
     */
    private static function validate($variable) {
        
        // If the variable has dot-style array notation,
        // convert it to php-style array notation.
        if (strpos($variable, '.') > -1) {
            $parts = explode('.', $variable);
            $var = $parts[0];
            $var_name = $parts[0];
            for ($i = 1; $i < count($parts); $i++) {
                $var .= '["' . $parts[$i] . '"]';
            }
        } else {
            $var = $variable;
            $var_name = $variable;
        }
        
        // If the variable is in a foreach loop, get 
        // the name of the array. 
        foreach (self::$foreachloop_variables as $loop) {
            if ($var_name == $loop['item']) {
                $var_name = $loop['list'];
            }
        }
        
        // Make sure the variable is one of the specified 
        // template variables. If not, return nothing.
        if (empty(self::$variables[$var_name])) {
            if (self::$debug) {
                $message = 'This is not in the list of template variables: ';
                throw new Exception($message . $variable);
            } else {
                return null;
            }
        } else {
            return '<? Mildred::display($' . $var . '); ?>';
        }
    }
    
    /**
     * Replace a variable token with the corresponding PHP code.
     * @param int $index The index of the token.
     */
    private static function parse_variable($index) {
        $token = self::$tokens[$index];
        $replacement = self::validate($token['match'][1]);
        self::replace($index, $token, $replacement);
    }
    
    /**
     * Replace a "foreach" token with the corresponding PHP code.
     * @param int $index The index of the token.
     */
    private static function parse_foreach($index) {
        $token = self::$tokens[$index];
        $list = $token['match'][2];
        $item = $token['match'][1];
        self::$foreachloop_variables[] = array(
            'item' => $item,
            'list' => $list,
        );
        $replacement = '<? if (isset($' . $list . ')): ?>';
        $replacement .= '<? foreach ($' . $list . ' as $' . $item . '): ?>'; 
        self::replace($index, $token, $replacement);
    }
    
    /**
     * Replace an "endforeach" token with the corresponding PHP code.
     * @param int $index The index of the token.
     */
    private static function parse_endforeach($index) {
        $token = self::$tokens[$index];
        $replacement = '<? endforeach; ?>';
        $replacement .= '<? endif; ?>';
        self::replace($index, $token, $replacement);
    }
    
    /**
     * Replace an "if" token with the corresponding PHP code.
     * @param int $index The index of the token.
     */
    private static function parse_if($index) {
        $token = self::$tokens[$index];
        $expression = $token['match'][1];
        
        // For expressions "if x is y" or "if x is not y":
        if (strpos($expression, ' is ') !== false) {
            
            // For expressions "if x is not y":
            if (strpos($expression, ' is not ') !== false) {
                $parts = explode(' is not ', $token['match'][1]);
                $var = trim($parts[0]);
                $val = trim($parts[1]);
                $replacement = '<? if (';
                $replacement .= '(!Mildred::is_valid($' . $var . ')) || ';
                $replacement .= '(Mildred::is_valid($' . $var . ') && ';
                $replacement .= '($' . $var . ' != ' . $val . '))';
                $replacement .= '): ?>';
            } 
            
            // For expressions "if x is y":
            else {
                $parts = explode(' is ', $token['match'][1]);
                $var = trim($parts[0]);
                $val = trim($parts[1]);
                $replacement = '<? if (';
                $replacement .= '(Mildred::is_valid($' . $var . ')) && ';
                $replacement .= '($' . $var . ' == ' . $val . ')';
                $replacement .= '): ?>';
            }
        } 
        
        // For expressions "if not x":
        elseif (strpos($expression, 'not ') !== false) {
            $var = str_replace('not ', '', $token['match'][1]);
            $replacement = '<? if (';
            $replacement .= '(!Mildred::is_valid($' . $var . ')) || ';
            $replacement .= '(Mildred::is_valid($' . $var . ') && ($' . $var . ' == false))';
            $replacement .= '): ?>';
        } 
        
        // For expressions "if x":
        else {
            $var = $token['match'][1];
            $replacement = '<? if (';
            $replacement .= '(Mildred::is_valid($' . $var . ') && ';
            $replacement .= '($' . $var . ' == true)) || ';
            $replacement .= 'Mildred::is_valid($' . $var . ')';
            $replacement .= '): ?>';
        }
        self::replace($index, $token, $replacement);
    }
    
    /**
     * Replace an "endif" token with the corresponding PHP code.
     * @param int $index The index of the token.
     */
    private static function parse_endif($index) {
        $token = self::$tokens[$index];
        $replacement = '<? endif; ?>';
        self::replace($index, $token, $replacement);
    }
    
}