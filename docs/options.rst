=======
Options
=======

You can customise the way the middleware works, options control various aspects
of the middleware.

isSecure
--------
:Summary: enforces all requests to be HTTPS
:Types: bool
:Default: true

This will throw a ``RuntimeException`` when a request is sent using HTTP

relaxed
-------
:Summary: A list of domains or IP addresses where not to enforce HTTPS
:Types: string[]
:Default:
  ::

    ["localhost", "127.0.0.1", "::1"]

.. note::

  This is useful for development purposes but is **not recommended** for production

header
------

:Summary: Controls the name of the header to search for the token
:Types: string
:Default: ``Authorization``

you way not want to always use the Authorization header you can change this with
your own custom header.

.. code-block:: php

  new Options(header: 'My-Token')

cookie
------
:Summary: Controls the name of the cookie to search for the token
:Types: string
:Default: ``token``

.. code-block:: php

  new Options(cookie: 'jwt')

regexp
------
:Summary: Control how the token is found in the header and cookie
:Types: string
:Default: ``/Bearer\\s+(.*)$/i``

You may want to change how the token is parsed from the header and cookie, one
common use is to not including the bearer.

.. code-block:: php

  new Options(regexp: '/^(?:[a-z0-9-_]+.){2}(?:[a-z0-9-_]+)$/i')

attribute
---------

:Summary: Control what the attribute name where the decoded token is stored on the request
:Types: string|null
:Default: ``token``

.. code-block:: php

  new Options(attribute: 'jwt')

  // @var RequestInterface $request
  $request->getAttribute('jwt'); // ['iat' => 1717219258 exp' => 1717219258]

.. note::

  If set to null no attribute will be added to the request.

before
------

:Summary: Allows for modification of the request before passing it to the next handler
:Types: BeforeHandlerInterface|null
:Default: ``none``

Sometimes it's useful to modify the request to the next handler for example
adding user information into the request for customer authorization handing.
This must be an instance of ``BeforeHandlerInterface``

.. code-block:: php

  class MyBeforeHandler implements BeforeHandlerInterface {
    /**
     * @param array{decoded: array<string, mixed>, token: string} $arguments
     */
    public function __invoke(ServerRequestInterface $request, array $arguments): ServerRequestInterface
    {
      // adds the unparsed token to the request
      return $request->withAttribute('raw', $arguments['token'])
    }
  }

  // ...

  new Options(before: new MyBeforeHandler())

after
-----

:Summary: Allows for modification of the response from the next handler
:Types: AfterHandlerInterface|null
:Default: ``none``

If you need to modify all response after the authentication middleware has
executed you can do so by providing a instance of ``AfterHandlerInterface``.
This is mostly useful for adding additional response headers.

.. code-block:: php

  class MyAfterHandlerInterface implements AfterHandlerInterface
  {
    /**
     * @param array{decoded: array<string, mixed>, token: string} $arguments
     */
    public function __invoke(ResponseInterface $response, array $arguments): ResponseInterface
    {
      return $response->withHeader('Custom-Header', 'my data')
    }
  }

  // ...

  new Options(after: new MyAfterHandlerInterface());
