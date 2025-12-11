# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Vous pouvez obtenir votre token en vous connectant via l'endpoint <code>/api/v1.0.0/login</code>. Le token doit être envoyé dans le header Authorization avec le préfixe Bearer.
