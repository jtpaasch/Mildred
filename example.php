<?php

// Include Mildred, the template engine. 
include 'mildred/Mildred.php';

// Include data type classes that you want to use in your template. 
include 'types/String.php';

// Make a list of variables to use in the template.
$variables = array(
    
    'greeting' => new String('Hello world!'),
    'another_variable' => 'Lorem ipsum.',
    
    'contact' => array(
        'name' => new String('Sally Johnson'),
        'email' => 'sally@home.com',
    ),
    
    'users' => array(
        array(
            'name'=> new String('Sally'),
            'email' => new String('sally@home.com'),
        ),
        array(
            'name'=> new String('Joe'),
            'email' => new String('joe@home.com'),
        ),
    ),
);

// Render the template called "template.html",
// with the variables listed above. 
Mildred::render(array(
    'template' => 'template.html', 
    'types' => array('String', ),
    'variables' => $variables, 
    'start_clean' => true, // Regenerate the template each time.
));