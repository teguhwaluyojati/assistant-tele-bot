import {
  mdiAccountCircle,
  mdiAccountMultiple,
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
    to: '/transactions',
    label: 'Transactions',
    icon: mdiTable,
  },
  {
    to: '/users',
    label: 'Users',
    icon: mdiAccountMultiple,
    requiresAdmin: true,
  },
  {
    to: '/tables',
    label: 'Tables',
    icon: mdiTable,
    requiresSuperAdmin: true,
  },
  {
    to: '/audit-logs',
    label: 'Audit Logs',
    icon: mdiClipboardTextClockOutline,
    requiresSuperAdmin: true,
  },
  {
    to: '/forms',
    label: 'Forms',
    icon: mdiSquareEditOutline,
    requiresSuperAdmin: true,
  },
  {
    to: '/ui',
    label: 'UI',
    icon: mdiTelevisionGuide,
    requiresSuperAdmin: true,
  },
  {
    to: '/responsive',
    label: 'Responsive',
    icon: mdiResponsive,
    requiresSuperAdmin: true,
  },
  {
    to: '/',
    label: 'Styles',
    icon: mdiPalette,
    requiresSuperAdmin: true,
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
    requiresSuperAdmin: true,
  },
  {
    to: '/error',
    label: 'Error',
    icon: mdiAlertCircle,
    requiresSuperAdmin: true,
  },
  {
    label: 'Dropdown',
    icon: mdiViewList,
    requiresSuperAdmin: true,
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
