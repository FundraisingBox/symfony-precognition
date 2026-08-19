# Configuring CORS

For a cross-origin frontend, allow the precognition request headers and expose
the response headers. With
[NelmioCorsBundle](https://github.com/nelmio/NelmioCorsBundle):

```yaml
nelmio_cors:
    defaults:
        allow_headers: ['Content-Type', 'Precognition', 'Precognition-Validate-Only']
        expose_headers: ['Precognition', 'Precognition-Success']
```

Expose `Precognition` so the client can verify that the server honoured the
request. Expose `Precognition-Success` so it can distinguish the successful
`204` response defined by the protocol.

For a complete client implementation, see the
[vanilla JavaScript example](../examples/vanilla-js).
