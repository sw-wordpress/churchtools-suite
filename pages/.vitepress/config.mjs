export default {
  lang: 'de-DE',
  title: 'ChurchTools Suite',
  description: 'Nutzerdokumentation der ChurchTools Integration Suite für WordPress.',
  base: '/churchtools-suite/',
  srcExclude: ['README.md'],
  themeConfig: {
    nav: [
      { text: 'Anleitung', link: '/erste-schritte' },
      { text: 'Changelog', link: '/Changelog' }
    ],
    sidebar: [
      { text: 'ChurchTools Suite', items: [
        { text: 'Übersicht', link: '/' },
        { text: 'Erste Schritte', link: '/erste-schritte' },
        { text: 'Einstellungen', link: '/einstellungen' },
        { text: 'Synchronisation', link: '/synchronisation' },
        { text: 'Termine anzeigen', link: '/termine-anzeigen' },
        { text: 'Erweiterungen', link: '/erweiterungen' },
        { text: 'Probleme lösen', link: '/probleme-loesen' },
        { text: 'Changelog', link: '/Changelog' }
      ] }
    ],
    search: { provider: 'local' },
    socialLinks: [{ icon: 'github', link: 'https://github.com/sw-wordpress/churchtools-suite' }],
    editLink: {
      pattern: 'https://github.com/sw-wordpress/churchtools-suite/edit/main/pages/:path',
      text: 'Diese Seite auf GitHub bearbeiten'
    },
    outline: { label: 'Auf dieser Seite' },
    docFooter: { prev: 'Zurück', next: 'Weiter' },
    sidebarMenuLabel: 'Menü',
    returnToTopLabel: 'Nach oben',
    darkModeSwitchLabel: 'Darstellung'
  }
}
