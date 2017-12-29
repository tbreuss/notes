<template>
    <div>
        <div class="loading" v-if="loading">
            Lade...
        </div>
        <div v-else>
            <h1>{{ article.title }}</h1>
            <p>{{ article.abstract }}</p>
            <div class="content">
                <vue-markdown :source="article.content"></vue-markdown>
            </div>
            <div class="tags">
                <article-tags :tags="article.tags"></article-tags>
            </div>
            <div v-if="loggedIn" class="text-left">
                <router-link :to="{ name: 'article-edit', params: { id: article.id }}" class="btn btn-primary">
                    Artikel bearbeiten
                </router-link>
            </div>
        </div>
    </div>
</template>

<script>
  import { getArticle } from '../utils/api'
  import auth from '../utils/auth'

  export default {
    props: ['id'],
    data () {
      return {
        loading: false,
        article: {},
      }
    },
    created: function () {
      this.loading = true
      getArticle(this.id)
        .then(article => {
          this.article = article
          this.loading = false
        })
        .catch(e => {
          console.error(e)
        })
    },
    computed: {
      loggedIn () {
        return auth.loggedIn()
      }
    },
    methods: {
      markdown: function (value) {
        //return value;
        if (!value) return ''
        return markdown.toHTML(value)
      }
    }
  }

</script>

<style scoped>

</style>