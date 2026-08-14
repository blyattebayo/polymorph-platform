# Data Platform error placement

`Errors/` contains reusable, transport-independent failure categories whose
meaning is selected by a stable `reason` (for example bad request, missing
resource, invalid state, or invariant violation). They may carry generic
context, but must not own a feature-specific payload contract.

An error whose identity and typed payload belong to one capability stays next
to that capability. Examples are optimistic locking and idempotency in
`Write/`, unique conflicts in `Projection/`, unindexed-query rejection in
`Query/`, and active-reference conflicts in `Delete/` or `Media/`.

HTTP status and serialization remain expressed through
`DomainErrorDescriptor` and `ErrorConvertible`; placement does not make an
exception transport-specific.
