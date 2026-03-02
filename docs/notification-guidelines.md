# Notification Guideline (MVP)

This document defines a shared notification pattern to keep UX consistent and code easy to maintain.

## When to use each pattern

- **Toast**: short feedback after user actions (create, update, delete, export, toggle).
- **Inline form error**: field-level validation errors that users must fix in the form.
- **Banner/persistent alert**: important information that should remain visible.
- **Confirmation modal**: destructive or risky actions that require user confirmation.

## Toast standards

- Use only 3 base types: `success`, `error`, `info`.
- Keep messages short, clear, and action-oriented.
- Use a default duration of `2600ms` for common action feedback.
- API errors must be processed through helpers to keep message format consistent.

## Helper usage

Helper locations:

- `resources/js/composables/useToast.js` (base state + auto-dismiss)
- `resources/js/composables/useActionToast.js` (action helper)

Main `useActionToast` API:

- `success(message)`
- `error(message)`
- `info(message)`
- `apiError(error, prefix, fallbackMessage)`
- `runAction(asyncAction, options)`

## Standard implementation pattern

For API actions:

1. Wrap the request with `runAction`.
2. Set `successMessage` and `errorPrefix`.
3. Update local state only when `ok === true`.

Common `runAction` options:

- `successMessage`: automatic success toast.
- `errorPrefix`: prefix for API error messages.
- `fallbackMessage`: fallback when no error message is available.
- `onSuccess(result)`: callback after success.
- `onError(error)`: callback on failure.

## Do / Don't

Do:

- Use toast for non-blocking action outcomes.
- Use consistent phrasing: "... successfully!" / "Failed to ...".

Don't:

- Do not use toast for everything (especially detailed form validation).
- Do not mix multiple notification styles for the same event.
