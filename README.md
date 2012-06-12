Mildred 
=======

A simple, type-safe template system for PHP. The syntax is like 
Django or Twig, but the emphasis here is typing. You specify which 
data types to allow in the templates, and Mildred will ignore
anything else. 

Simple use
----------

In the simplest case, include Mildred.php, then tell Mildred which
template to render: 

    <?php 
    // Include "Mildred.php" from the "mildred" folder.
    include 'mildred/Mildred.php';

    // Tell Mildred to render "my_template.html."
    Mildred::render(array(

        // The template to render:
        'template' => 'my_template.html',

    ));


Specifying which types and variables to allow
---------------------------------------------

If you want to use variables in your templates, you need to tell 
Mildred which data types to allow. Data types must be custom 
classes that you have defined. Mildred will ignore all native PHP
scalar types (strings and numbers), because they are unsafe. The 
idea here is that all variables that find their way into your 
templates must be filtered through your own custom classes, 
so that you can be sure to sanitize and validate the data 
before it goes to the user. 

For instance, suppose you have a custom String class that takes 
a regular PHP string, strips out any malicious code, and returns 
a nice, UTF-8 encoded string. Use it like this: 

    <?php 
    // Include "Mildred.php" from the "mildred" folder.
    include 'mildred/Mildred.php';

    // Include your String class so you can use it with Mildred.
    // (Alternatively, you could use autoloading.) 
    include 'types/String.php';

    // Tell Mildred to render "my_template.html," and 
    // allow instances of String as variables:
    Mildred::render(array(
        
        // The template to render: 
        'template' => 'my_template.html',

        // The types that are allowed in the template:
        'types' => array( 
            'String', 
        ),

        // The variables that are allowed in the template:
        'variables' => array(
            'greeting' => new String('Hello World!'),
        ),
    ));

That sends the variable 'greeting' to the template, and since 
'greeting' is an instance of String, Mildred will let it pass. 
If 'greeting' were just a regular string --- 

            'greeting' => 'Hello World!',

--- Mildred would ignore it. 

Mildred does allow arrays as variables, but each 
array _item_ must be an instance of an allowed data type. For instance, Mildred 
would allow 'name' but ignore 'email' if we added the following
array to our variables: 

    'variables' => array(
        'greeting' => new String('Hello World!'),
        'user' => array(
            'name' => new String('Sally Smith'),
            'email' => 'sally@home.com',
        ),
    ),

You can have arrays nested in arrays too, but again, at the bottom
of the chain, any array _item_ must be an instance of an allowed 
data type before Mildred will put it into a template. 


Template syntax
---------------

Template syntax is just a very basic form of Django or Twig style syntax. 
Variables get enclosed in two curly braces: 

    <h1>Test template</h1>

    {{ greeting }}

Arrays take a dot syntax notation: 

    {{ user.name }}

Foreach loops are done like this: 

    {% foreach user in users %}
        <li>{{ user }}</li>
    {% endforeach %}

If statements are allowed too. This tests if 'greeting' 
is defined (or true): 
    
    {% if greeting %}
        {{ greeting }}
    {% endif %}

This tests if a variable called 'on_time' is undefined (or false): 

    {% if not on_time %}
        Your delivery is running late.
    {% endif %}

This tests if 'greeting' has the value 'Hello World!':

    {% if greeting is 'Hello World!' %}
        {{ greeting }}
    {% endif %}

And this tests if 'greeting' is not 'Hello World!':

    {% if greeting is not 'Hello World!' %}
        {{ greeting }}
    {% endif %}


Debugging and reparsing templates
---------------------------------

The first time Mildred encounters a template, it parses it and 
converts it into appropriate PHP code. The next time, it will use
the parsed version. This is to keep things fast. 

However, this also means that if you change your template in any way,
you'll want to tell Mildred to reparse it (otherwise, you'll just keep
seeing the old parsed version). To do that, add a 'start_clean' option 
to the render() method: 

    // Tell Mildred to render "my_template.html," and 
    // allow instances of String as variables:
    Mildred::render(array(
        
        // The template to render: 
        'template' => 'my_template.html',

        // The types that are allowed in the template:
        'types' => array( 
            'String', 
        ),

        // The variables that are allowed in the template:
        'variables' => array(
            'greeting' => new String('Hello World!'),
        ),

        // Start clean each time: reparse the template from scratch.
        'start_clean' => true,
    ));

Mildred silently ignores undefined variables and variables that 
are not an allowed type. This keeps errors out of production: 
we don't want those errors breaking our sites. However, this can 
make it hard to debug. You can turn on debugging by adding a 'debug'
option to the render() method, in which case Mildred will tell you 
about undefined and invalid variable types: 

    // Tell Mildred to render "my_template.html," and 
    // allow instances of String as variables:
    Mildred::render(array(
        
        // The template to render: 
        'template' => 'my_template.html',

        // The types that are allowed in the template:
        'types' => array( 
            'String', 
        ),

        // The variables that are allowed in the template:
        'variables' => array(
            'greeting' => new String('Hello World!'),
        ),

        // Start clean each time: reparse the template from scratch.
        'start_clean' => true,

        // Display errors: 
        'debug' => true,

    ));


Internals and file permissions
------------------------------

Mildred works as follows: 

* When the render() method is called, Mildred first checks
  for a parsed version of the template. If there is a 
  parsed version, Mildred serves that. 
* Otherwise, Mildred creates a parsed version.
    * First, the template file is passed to a Lexer.
    * The Lexer finds all occurrences of template variables,
  if statements, and foreach loops. It then returns a 
  list of all those tokens. 
    * That list of tokens is then passed to a Parser,
  which goes through the template and replaces each 
  token with the appropriate PHP code. The Parser 
  removes any variables it finds that are not 
  in the list of template variables. 
    * When the parser is finished, it returns the PHP code,
  and Mildred saves that code as a php file. This is the 
  parsed template, and it takes the same name as the 
  original template, but it is prefixed by a dot. (E.g., 
  the parsed version of "my_template.html" would be 
  .my_template.html. 
    * That parsed file is then served. 
* When the parsed file is served, Mildred checks each variable
  before it is displayed: it first checks that it exists, and then 
  it makes sure it is an instance of an allowed data type. If it 
  fails either of those tests, it is simply ignored. 

Because Mildred creates a parsed version of the file and saves 
it, PHP needs to have the correct permissions to read and 
write template files. 