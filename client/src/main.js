// The Vue build version to load with the `import` command
// (runtime-only or standalone) has been set in webpack.base.conf with an alias.
import Vue from 'vue'
import ElementUI from 'element-ui'
import 'element-ui/lib/theme-chalk/index.css'
import router from '@/router'
import App from '@/App'
import ArticlesComponent from '@/components/ArticlesComponent'
import ArticleTags from '@/components/ArticleTags'
import ModalDialog from '@/components/ModalDialog'
import TextareaUpload from '@/components/TextareaUpload'
import VueMarkdown from 'vue-markdown' // production

Storage.prototype.setObj = function (key, obj) {
  return this.setItem(key, JSON.stringify(obj))
}
Storage.prototype.getObj = function (key) {
  return JSON.parse(this.getItem(key))
}

Vue.config.productionTip = false

Vue.use(ElementUI)

Vue.directive('focus', {
  // When the bound element is inserted into the DOM...
  inserted: function (el) {
    // Focus the element
    el.focus()
  }
})

Vue.filter('markdown', function (value) {
  if (!value) return ''
  return markdown.toHTML(value)
})

Vue.filter('date', function (strDate) {
  //  Safari & IE browsers do not support the date format “yyyy-mm-dd”
  strDate = strDate.replace(/-/g, '/')
  var date = new Date(strDate)
  return date.toLocaleDateString()
})

Vue.component('articles', ArticlesComponent)
Vue.component('article-tags', ArticleTags)
Vue.component('vue-markdown', VueMarkdown)
Vue.component('modal-dialog', ModalDialog)
Vue.component('textarea-upload', TextareaUpload)

/* eslint-disable no-new */
new Vue({
  el: '#app',
  router,
  template: '<App/>',
  components: {
    App
  }
})
