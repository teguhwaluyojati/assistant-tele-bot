import { useToast } from '@/composables/useToast'

const resolveErrorMessage = (error, fallbackMessage = 'Unexpected error occurred.') => {
  return error?.response?.data?.message || error?.response?.statusText || error?.message || fallbackMessage
}

export function useActionToast(duration = 2600) {
  const { toast, showToast } = useToast(duration)

  const success = (message) => {
    showToast('success', message)
  }

  const error = (message) => {
    showToast('error', message)
  }

  const info = (message) => {
    showToast('info', message)
  }

  const apiError = (errorObj, prefix = 'Request failed', fallbackMessage = 'Unexpected error occurred.') => {
    const message = resolveErrorMessage(errorObj, fallbackMessage)
    showToast('error', `${prefix}: ${message}`)
  }

  const runAction = async (action, options = {}) => {
    const {
      successMessage = '',
      errorPrefix = 'Action failed',
      fallbackMessage = 'Unexpected error occurred.',
      onSuccess,
      onError,
    } = options

    try {
      const result = await action()

      if (successMessage) {
        success(successMessage)
      }

      if (typeof onSuccess === 'function') {
        onSuccess(result)
      }

      return { ok: true, result }
    } catch (errorObj) {
      apiError(errorObj, errorPrefix, fallbackMessage)

      if (typeof onError === 'function') {
        onError(errorObj)
      }

      return { ok: false, error: errorObj }
    }
  }

  return {
    toast,
    success,
    error,
    info,
    apiError,
    runAction,
  }
}
