.. title:: JWT-Auth, JWT Authentication Middleware

======================
JWT-Auth Documentation
======================

JWT-Auth is a simple middleware which implements JSON Web Token Authentication
that is PSR-15 compliant.

JWT-Auth does not implement an OAuth 2.0 authorization server nor does it
provide ways to generate, issue or store authentication tokens. It only parses
and authenticates a token when passed via header or cookie. This is useful for
example when you want to use `JSON Web Tokens as API keys <https://auth0.com/blog/2014/12/02/using-json-web-tokens-as-api-keys/>`_.

User Guide
==========

.. toctree::
  :maxdepth: 3

  overview
  quickstart
  options
  decoder
