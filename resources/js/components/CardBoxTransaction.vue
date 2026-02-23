<script setup>
import { computed } from 'vue'
import { mdiCashMinus, mdiCashPlus, mdiReceipt, mdiCreditCardOutline } from '@mdi/js'
import CardBox from '@/components/CardBox.vue'
import BaseLevel from '@/components/BaseLevel.vue'
import PillTag from '@/components/PillTag.vue'
import IconRounded from '@/components/IconRounded.vue'

const props = defineProps({
  amount: {
    type: [Number, String],
    required: true,
  },
  date: {
    type: String,
    required: true,
  },
  business: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    required: true,
  },
  name: {
    type: String,
    required: true,
  },
  account: {
    type: String,
    required: true,
  },
})

const icon = computed(() => {
  if (props.type === 'expense') {
    return {
      icon: mdiCashMinus,
      type: 'warning',
    }
  } else if (props.type === 'income') {
    return {
      icon: mdiCashPlus,
      type: 'success',
    }
  }

  return {
    icon: mdiCreditCardOutline,
    type: 'info',
  }
})

const amountLabel = computed(() => {
  if (typeof props.amount === 'string') {
    const trimmed = props.amount.trim()
    if (trimmed.length > 0) {
      return trimmed
    }
  }

  const parsed = Number(props.amount)
  return Number.isFinite(parsed) ? `Rp. ${parsed}` : '-'
})
</script>

<template>
  <CardBox class="mb-6 last:mb-0 min-h-[108px] overflow-hidden">
    <BaseLevel class="w-full min-w-0">
      <BaseLevel type="justify-start" class="min-w-0 w-0 flex-1">
        <IconRounded :icon="icon.icon" :color="icon.type" class="md:mr-6" />
        <div class="text-center space-y-1 md:text-left md:mr-6 overflow-hidden min-w-0 flex-1 max-w-full">
          <h4 class="text-xl truncate">{{ amountLabel }}</h4>
          <p class="text-gray-500 dark:text-slate-400 truncate max-w-full">
            <b>{{ date }}</b> via {{ business }}
          </p>
        </div>
      </BaseLevel>
      <div class="text-center md:text-right space-y-2 min-w-0 w-[7rem] md:w-[9rem] shrink-0">
        <p class="text-sm text-gray-500 truncate w-full">
          {{ name }}
        </p>
        <div>
          <PillTag :color="icon.type" :label="type" small />
        </div>
      </div>
    </BaseLevel>
  </CardBox>
</template>
