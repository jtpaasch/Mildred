<?php

class String {
    
    private $original_string = '';
    private $raw_string = '';
    private $string = '';
    
    public function __construct($string) {
        $this->original_string = $string;
        $this->raw_string = mb_convert_encoding((string) $string, 'UTF-8');
        $this->string = htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    }
    
    public function raw() {
        return $this->raw_string;
    }
    
    public function __toString() {
        return $this->string;
    }
}