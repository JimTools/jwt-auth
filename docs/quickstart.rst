======================
Quickstart
======================

This page provides a quick introduction to JWT-Auth and introductory examples.
If you have not already installed, JWT-Auth, head over to the
:doc:`overview` page.

This quickstart will use Slim framework as am example but this will work with
any PSR-15 compliant framework.

Basic Usage
============

The below example is the most basic use of the middleware, acting as a global
authentication middleware on all routes. by default it will expect a JWT in the
``Authentication`` header or cookie  named ``token`` prefixed with **Bearer**
followed by the token i.e. ``Bearer xx.yy.zz``


.. code-block:: php

  use Psr\Http\Message\ResponseInterface as Response;
  use Psr\Http\Message\ServerRequestInterface as Request;
  use Slim\Factory\AppFactory;
  use JimTools\JwtAuth\Middleware\JwtAuthentication;
  use JimTools\JwtAuth\Options;

  require __DIR__ . '/../vendor/autoload.php';

  $app = AppFactory::create();
  $app->addRoutingMiddleware();
  $errorMiddleware = $app->addErrorMiddleware(true, true, true);

  // Register Authentication Middleware
  $decoder = new FirebaseDecoder(new Secret($_ENV['JWT_SECRET'], 'HS256'));
  $authentication = new JwtAuthentication(new Options(), $decoder);
  $app->addMiddleware($authentication);

  $app->get('/hello/{name}', function (Request $request, Response $response, $args) {
      $name = $args['name'];
      $response->getBody()->write("Hello, $name");
      return $response;
  });

  $app->run();

Custom Rules
============

You may not always want a global authentication on all routes this is where
custom rules comes in, the third parameter of ``JwtAuthentication`` allow you to
specify which routes to authentication. the third argument should be an array of
``JimTools\JwtAuth\Rules\RuleInterface``

.. code-block:: php

  use JimTools\JwtAuth\Rules\RequestMethodRule;
  use JimTools\JwtAuth\Rules\RequestPathRule;

  $rules = [
    new RequestMethodRule(ignore: ['OPTIONS']),
    new RequestPathRule(paths: ['/'], ignore: ['/auth/login'])
  ]

  // Register Authentication Middleware
  $decoder = new FirebaseDecoder(new Secret($_ENV['JWT_SECRET'], 'HS256'));
  $authentication = new JwtAuthentication(new Options(), $decoder, $rules);
  $app->addMiddleware($authentication);


Out of the box there are two rules provided ``RequestMethodRule`` and
``RequestPathRule``, these should cover 90% of all use cases but if you need
fine-grained control you can while your own custom rules which implements
the ``RuleInterface`` interface.

RequestMethodRule
-----------------

This rule allows you bypass all request with a specific HTTP method, by default
it will ignore ``OPTION``, check out
`MDN Http Request Methods <https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods>`_
for more information on methods.

RequestPathRule
---------------

This rule determines which url paths the authentication will action on, it takes
two parameter both expect an array of strings.

paths is a list or URI where the authentication will take act on, this can be a
string literal or a regular expression for example.

.. code-block:: php

  new RequestPathRule(['/private', '/users/\d+'])

ignore is a list or URI where authentication will be bypassed, this can by a
string literal or a regular expression for example.

.. code-block:: php

  new RequestPathRule(['/'], ['/auth/login', '/products/[a-zA-Z0-9_-]]'])

.. note::
  All regular expressions are **not** treated as case insensitive.

Multiple Tokens
===============

If you need to parse multiple token from different source it's possible, all you
need to do is pass multiple secrets to the to the decoder see the below example.

.. code-block:: php

  $decoder = new FirebaseDecoder(
    new Secret($_ENV['JWT_ALPHA_SECRET'], 'HS256', 'alpha'),
    new Secret($_ENV['JWT_BETA_SECRET'], 'HS256', 'beta'),
  );
  $authentication = new JwtAuthentication(new Options(), $decoder);

.. warning::
  when multiple secrets are passsed the ``kid`` is required in the token header
  and in the ``Secret`` class. The kid is used by the decoder to determine which
  secret to use to decode the JWT.
