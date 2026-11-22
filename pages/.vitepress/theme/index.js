import { h } from 'vue'
import DefaultTheme from 'vitepress/theme'
import './style.css'

// The default footer is hidden on pages with a sidebar. This global slot is not.
export default {
  extends: DefaultTheme,
  Layout() {
    return h(DefaultTheme.Layout, null, {
      'layout-bottom': () => h('footer', { class: 'cts-docs-footer' },
        'Open source by Stefan Warnat – free to use, free to change, better together.')
    })
  }
}
