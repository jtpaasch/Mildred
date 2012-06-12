<?php

// Include the Lexer and Parser classes (edit the require paths as needed).
require 'Lexer.php';
require 'Parser.php';

/**
 * A simple type-safe template engine. You specify which data types 
 * are allowed in the templates. Invalid and undefined template
 * variables are ignored. 
 * 
 * @author JT Paasch
 * @copyright 2012
 */
class Mildred {
    
    /**
     * If set to true, variable errors will be displayed 
     * when rendering a template. If set to false, any 
     * undefined or invalid variables will be removed silently.
     * @var boolean
     */
    private static $debug = false;
    
    /**
     * If set to true, each time the template is called,
     * it will be re-compiled. This is useful for development.
     * @var type 
     */
    private static $clean = false;
    
    /**
     * A list of valid data types (class names) that can be rendered
     * in a template.
     * @var array 
     */
    private static $types = array();
    
    /**
     * Register the list of data types (class names) that can be
     * rendered in a template.
     * @param array $types The list of class names to allow.
     */
    public static function allow($types) {
        self::$types = $types;
    }
    
    /**
     * Parse and render a template.
     * @param array $options Options include: 
     *      (a) string 'template': the path to the template.
     *      (b) array 'types': the types (class names) to allow in the template.
     *      (c) array 'variables': the variables to use in the template.
     *      (d) boolean 'clean': recompile the template on each view?
     *      (e) boolean 'debug': catch/report invalid and undefined variables?
     */
    public static function render($options) {
        
        // Set the debug and clean options.
        if (!empty($options['debug'])) {
            self::$debug = $options['debug'];
        }
        if (!empty($options['start_clean'])) {
            self::$clean = $options['start_clean'];
        }
        
        // Make sure the template is defined.
        if (empty($options['template'])) {
            if (self::$debug) {
                $message = 'You did not specify a template to render.';
                throw new Exception($message);
            } else {
                return null;
            }
        } else {
            $template = $options['template'];
        }
        
        // Set the types to an empty array if not defined.
        if (!empty($options['types'])) {
            self::$types = $options['types'];
        }
        $types = self::$types;
        
        // Set the variables to an empty array if not defined.
        if (empty($options['variables'])) {
            $variables = array();
        } else {
            $variables = $options['variables'];
        }
        
        // If self::$clean is false, and if the file has been parsed already,
        // serve the parsed file.
        if (!(self::$clean) && self::is_parsed($template)) {
            $parsed_template_name = self::get_parsed_template_name($template);
            self::serve($parsed_template_name, $variables, self::$types);
        }
        
        // Otherwise, start clean: parse the template, save it, and serve that.
        else {
            
            // Get the contents of the template.
            $file = self::template_contents($template);

            // Make sure the lexer and parser are available.
            self::check_lexer_and_parser();

            // Get tokens for the template.
            $tokens = Lexer::analyze($file);

            // Parse the template.
            $parsed_contents = Parser::parse(
                    $file, 
                    $tokens, 
                    $types, 
                    $variables, 
                    self::$debug
            );
            
            // Show the parsed code if needed.
            if (!empty($options['show_php'])) {
                echo $parsed_contents;
            }
            
            // Save the parsed version.
            $parsed_template_name = self::get_parsed_template_name($template);
            self::save($parsed_template_name, $parsed_contents);
            
            // Serve the parsed file.
            self::serve($parsed_template_name, $variables, self::$types);
        }
    }
    
    /**
     * Checks to make sure the Lexer and Parser classes exist.
     * Throws an exception if not.
     * @throws Exception 
     */
    private static function check_lexer_and_parser() {
        if (!class_exists('Lexer')) {
            throw new Exception('The Lexer class does not exist.');
        }
        if (!class_exists('Parser')) {
            throw new Exception('The Parser class does not exist.');
        }
    }
    
    /**
     * Get the contents of a template.
     * @param string $file The path to the template.
     * @return string The template contents
     * @throws Exception if the template does not exist or is not readable.
     */
    private static function template_contents($template) {
        if (file_exists($template)) {
            if (is_readable($template)) {
                return file_get_contents($template);
            } else {
                $message = 'I do not have permission to read the template: ';
                throw new Exception($message . $template);
            }
        } else {
            throw new Exception('This template does not exist: ' . $template);
        }
    }
    
    /**
     * Try to write the specified contents to a file. 
     * @param string $file The path to the file.
     * @param string $contents The contents to write to the file.
     * @return boolean True if successful, false if not.
     */
    private static function save($file, $contents) {
        if (is_writable(dirname($file))) {
            $success = @file_put_contents($file, $contents);
            if ($success === false) {
                $message = 'I could not write to this template: ';
                throw new Exception($message . $file);
            }
        } else {
            $message = 'I do not have permission to write to this template: ';
            throw new Exception($message . $file);
        }
    }
    
    /**
     * Check if a template has been parsed. A parsed template has the 
     * same filename, prefixed by a dot.
     * @param string $template The path to the template.
     * @return boolean True if a parsed template exists, false if not.
     */
    private static function is_parsed($template) {
        return file_exists(self::get_parsed_template_name($template));
    }
    
    /**
     * Get the full path of the parsed version of a template.
     * The parsed version has the same filename, but it is prefixed by a dot.
     * @param string $template The path to the template.
     * @return string The path of the parsed version of the template.
     * @throws Exception if the original template does not exist.
     */
    private static function get_parsed_template_name($template) {
        if (file_exists($template)) {
            $parsed_template = dirname($template) . '/.' . basename($template);
            return $parsed_template;
        } else {
            throw new Exception('This template does not exist: ' . $template);
        }
    }
    
    public static function is_valid($variable) {
        if (isset($variable)) {
            foreach (self::$types as $type) {
                if ($variable instanceof $type) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }
    
    /**
     * Echos a variable, but only if it is defined
     * and one of the allowed data types. 
     * @param mixed $variable The variable to display.
     */
    public static function display($variable) {
        if (isset($variable)) {
            foreach (self::$types as $type) {
                if ($variable instanceof $type) {
                    echo $variable;
                    return;
                }
            }
            if (self::$debug) {
                $message = 'This is not an allowed data type: ';
                throw new Exception($message . $variable);
            } else {
                $variable &= false;
            }
        } else {
            if (self::$debug) {
                $message = 'This variable is undefined: ';
                throw new Exception($message . $variable);
            } else {
                $variable = false;
            }
        }
    }
    
    /**
     * Serve a parsed template. 
     * @param string $template Path to the parsed template.
     * @param array $variables A list of variables to use in the template.
     * @throws Exception If self::$debug is true, exceptions are thrown 
     *      for undefined and invalid template variables.
     */
    private static function serve($template, $variables) {
        
        // Extract the template variables and include the template
        // inside this closure. That way, the template has no
        // access to anything outside the template.
        $render_in_closed_scope = function($template, $variables) {
            
            // Extract the variables
            extract($variables);
            
            // Include the template.
            include $template;
        };
        
        // If the file exists, send it and the variables
        // to the render_in_closed_scope function.
        if (file_exists($template)) {
            $render_in_closed_scope($template, $variables);
        }
    }
    
}