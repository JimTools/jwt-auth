=======
Decoder
=======

By default JWT-Auth ships with a jwt decode this is based on the
`Firebase JWT <https://github.com/firebase/php-jwt>`_ library this will cover
most common uses.

.. code-block:: php

  new FirebaseDecoder(new Secret($_ENV['JWT_SECRET'], 'HSS256'))

Secret
======

The decoder expects at least one secret, but can be passed multiples

.. code-block:: php

  new Secret(secret: 'tooManySecrets', algorithm: 'HS256', kid: 'acme')


secret
------
:Summary: the secret to decode the token
:Types: string
:Default: ``none``

algorithm
---------
:Summary: the algorithm which the token is encrypted with
:Types: string
:Default: ``none``

kid
---

:Summary: the kid of the token
:Types: string|null
:Default: ``null``

the ``kid`` is used when multiple secrets are provided, this is for the decoder
to work out which secret to use for the token.

.. note::

  when only using a singular algorithm and secret you can leave this as null

Exceptions
=========

If the decoder cannnot decode the token it will throw one of several exception.

InvalidArgumentException
------------------------

the provided secret or array of secret is empty or malformed.

DomainException
---------------

this can be for several reasons

#. provided algorithm is unsupported OR
#. provided key is invalid OR
#. unknown error thrown in openSSL or libsodium OR
#. libsodium is required but not available.


SignatureInvalidException
-------------------------

the signature of the token is malformed.

BeforeValidException
--------------------

the token passed is trying to be used before the valid date or before the issued
at date.

ExpiredException
----------------

The token has expired.

UnexpectedValueException
------------------------

this can be throw for one of the following reasons.

#. provided JWT is malformed OR
#. provided JWT is missing an algorithm / using an unsupported algorithm OR
#. provided JWT algorithm does not match provided key OR
#. provided key ID in key/key-array is empty or invalid.


Customr Decoder
===============

If the provided decode doee not meet your needs, you can always create you own
custom decode that impliemnts ``DecoderInterface``

.. code-block:: php

  class MyDecoder impliemnts DecoderInterface
  {
      /**
      * @return array<string, mixed>
      *
      * @throws InvalidArgumentException
      * @throws DomainException
      * @throws SignatureInvalidException
      * @throws BeforeValidException
      * @throws ExpiredException
      * @throws UnexpectedValueException
      */
      public function decode(string $jwt): array
      {
        // decode the token
        return [];
      }
  }
