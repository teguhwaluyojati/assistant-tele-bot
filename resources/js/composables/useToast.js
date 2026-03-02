import { onBeforeUnmount, ref } from 'vue'

export function useToast(duration = 2600) {
  const toast = ref({
    visible: false,
    type: 'success',
    message: '',
  })

  let timer = null

  const showToast = (type, message) => {
    toast.value = {
      visible: true,
      type,
      message,
    }

    if (timer) {
      clearTimeout(timer)
    }

    timer = setTimeout(() => {
      toast.value.visible = false
    }, duration)
  }

  onBeforeUnmount(() => {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
  })

  return {
    toast,
    showToast,
  }
}
