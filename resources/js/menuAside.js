import {
  mdiAccountCircle,
  mdiMonitor,
  mdiGithub,
  mdiLock,
  mdiAlertCircle,
  mdiClipboardTextClockOutline,
  mdiSquareEditOutline,
  mdiTable,
  mdiViewList,
  mdiTelevisionGuide,
  mdiResponsive,
  mdiPalette,
} from '@mdi/js'

export default [
  {
    to: '/dashboard',
    icon: mdiMonitor,
    label: 'Dashboard',
  },
  {
    to: '/tables',
    label: 'Tables',
    icon: mdiTable,
    requiresAdmin: true,
  },
  {
    to: '/audit-logs',
    label: 'Audit Logs',
    icon: mdiClipboardTextClockOutline,
    requiresAdmin: true,
  },
  {
    to: '/forms',
    label: 'Forms',
    icon: mdiSquareEditOutline,
    hideForNonAdmin: true,
  },
  {
    to: '/ui',
    label: 'UI',
    icon: mdiTelevisionGuide,
    hideForNonAdmin: true,
  },
  {
    to: '/responsive',
    label: 'Responsive',
    icon: mdiResponsive,
    hideForNonAdmin: true,
  },
  {
    to: '/',
    label: 'Styles',
    icon: mdiPalette,
    hideForNonAdmin: true,
  },
  {
    to: '/profile',
    label: 'Profile',
    icon: mdiAccountCircle,
  },
  {
    to: '/login',
    label: 'Login',
    icon: mdiLock,
    hideForNonAdmin: true,
  },
  {
    to: '/error',
    label: 'Error',
    icon: mdiAlertCircle,
    hideForNonAdmin: true,
  },
  {
    label: 'Dropdown',
    icon: mdiViewList,
    hideForNonAdmin: true,
    menu: [
      {
        label: 'Item One',
      },
      {
        label: 'Item Two',
      },
    ],
  },
  {
    href: 'https://github.com/teguhwaluyojati',
    label: 'GitHub',
    icon: mdiGithub,
    target: '_blank',
  },
]
