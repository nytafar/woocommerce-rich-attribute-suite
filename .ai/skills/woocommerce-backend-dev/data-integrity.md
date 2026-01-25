# Data Integrity Guidelines

## Preventing Accidental Data Loss

- **Validation Before Deletion:** Always verify entity state before operations
- **Race Conditions:** Check ownership and prevent concurrent access issues
- **Session-Based Operations:** Confirm session ownership for cart/checkout actions

## Security Checklist

- Verify entity existence
- Verify entity state and status
- Verify ownership credentials
- Check for potential race conditions
- Consider soft vs. hard delete approaches
- Implement error handling and audit logging
- Check user capabilities

## Common Pitfalls

1. Trusting unvalidated user input
2. Batch operations lacking per-item verification
3. Failing to check operation success/failure results
