<template>
    <div>
        <slot name="title"><h3>Einträge</h3></slot>
        <div class="list-group" v-loading="loading">
            <router-link v-for="article in articles" :to="'/articles/' + article.id"
                         class="list-group__item"
                         :key="article.id">
                <h4>{{ article.title }}</h4>
                <span v-if="article.views" class="badge badge-secondary">
                    {{ article.views }}
                </span>
                <span v-if="article.modified" class="badge badge-secondary">
                    {{ article.modified | date }}
                </span>
                <span v-if="article.created" class="badge badge-secondary">
                    {{ article.created | date }}
                </span>
            </router-link>
        </div>
    </div>
</template>

<script>
  import { getSelectedArticles } from '../utils/api'

  export default {
    data () {
      return {
        articles: [],
        loading: false
      }
    },
    props: ['mode'],
    methods: {
      loadData: function () {
        this.loading = true
        getSelectedArticles(this.mode)
          .then(articles => {
            this.articles = articles
            this.loading = false
          })
          .catch(e => {
            console.error(e)
          })
      }
    },
    created: function () {
      this.loadData()
    }
  }

</script>

<style scoped>

</style>
