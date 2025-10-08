=================
Breaking Changes
=================

The default ``algorithm`` has changed from ``['HS256', 'HS512', 'HS384']`` to ``['HS256']`` in most cases this will not
be a problem, unless you are using multiple JWT with different encoding

The way the ``secrets`` and ``algorithm`` are passed has changed, It now requires a unique key to match the secret and
algorithm together.

.. code-block:: php

  $app = new Slim\App;

  $app->add(new Tuupola\Middleware\JwtAuthentication([
      "secret" => ["acme" => "supersecretkeyyoushouldnotcommittogithub"],
      "algorithm" => ["amce" => "HS256"]
  ]));

If your application is using multiple JWTs with different algorithms you will need to change how the JWT is created.
Each token now must include the ``kid`` in the header, this must match the corresponding algorithm/secret key as the
middleware uses this to decode the JWT. if you using ``firebase/php-jwt`` to create your tokens here's how to do this.

.. code-block:: php

  $hs256token = JWT::encode([...], 'tooManySecrets', 'HS256', 'acme');
  $hs512token = JWT::encode([...], 'tooManySecrets', 'HS512', 'beta');

Upgrade
=======

Switch over the package by using the following commands, for now, the namespace is the same.

.. code-block:: bash

  composer require -W jimtools/jwt-auth

Update the ``JwtAuthentication`` config to have keys for the ``secret`` and ``algorithm`` to have a unique index.

**Before**

.. code-block:: php

  $app->add(new Tuupola\Middleware\JwtAuthentication([
      "secret" => "supersecretkeyyoushouldnotcommittogithub",
      "algorithm" => ["HS256"]
  ]));

**After**

.. code-block:: php

  $app->add(new Tuupola\Middleware\JwtAuthentication([
      "secret" => ["acme" => "supersecretkeyyoushouldnotcommittogithub"],
      "algorithm" => ["acme" => "HS256"],
  ]));

(Maybe) If you're using multiple encryption algorithms you will need to add the ``kid`` to the JWT header.
`firebase JWT Docs <https://github.com/firebase/php-jwt#example-with-multiple-keys>`_

